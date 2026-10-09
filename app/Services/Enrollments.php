<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ValidationException;
use App\Models\Enrollment;
use App\Services\Scorm\Packages;

/**
 * Operações sobre as vagas: indicar participante (comprador), liberar acesso,
 * registrar progresso, concluir e anexar certificado (equipe Dafnis).
 */
final class Enrollments
{
    /**
     * Libera na hora a vaga de curso com conteúdo próprio na loja (SCORM em uso): não há cadastro a
     * fazer em outra plataforma. Vale para vagas "em liberação", com participante definido.
     * Cursos da plataforma de ensino externa continuam com a liberação feita pela equipe.
     */
    public static function autoRelease(int $id, bool $notify = true): bool
    {
        $e = Enrollment::find($id);
        if (!$e || $e['status'] !== 'processing' || !$e['participant_email'] || !$e['course_id'] || !Packages::current((int) $e['course_id'])) {
            return false;
        }
        Database::update('enrollments', ['status' => 'active', 'released_at' => $e['released_at'] ?: date('Y-m-d H:i:s')], ['id' => $id]);
        Activity::log('enrollment.auto_release', 'enrollment', $id, 'Acesso liberado automaticamente (curso na loja)');
        if ($notify) {
            Notify::accessReleased(Enrollment::find($id));
        }
        return true;
    }

    /** Vagas de um pedido recém-pago: libera as de cursos na loja. O e-mail do pagamento já leva o acesso. */
    public static function autoReleaseOrder(int $orderId): int
    {
        $n = 0;
        foreach (Database::select("SELECT e.id, e.participant_email, o.buyer_email FROM enrollments e JOIN orders o ON o.id = e.order_id WHERE e.order_id = :o AND e.status = 'processing'", ['o' => $orderId]) as $row) {
            // Quem comprou para si recebe o acesso no e-mail de pagamento confirmado; os demais, num e-mail próprio.
            $isBuyer = mb_strtolower((string) $row['participant_email']) === mb_strtolower((string) $row['buyer_email']);
            $n += self::autoRelease((int) $row['id'], !$isBuyer) ? 1 : 0;
        }
        return $n;
    }

    /** Conteúdo próprio colocado em uso: libera as vagas do curso que esperavam liberação. */
    public static function autoReleaseCourse(int $courseId): int
    {
        $n = 0;
        foreach (Database::select("SELECT id FROM enrollments WHERE course_id = :c AND status = 'processing' AND participant_email IS NOT NULL", ['c' => $courseId]) as $row) {
            $n += self::autoRelease((int) $row['id']) ? 1 : 0;
        }
        return $n;
    }

    /**
     * O comprador indica (ou corrige) quem vai fazer o curso, enquanto o acesso não foi liberado.
     * @return bool true se o acesso já foi liberado (curso com conteúdo próprio na loja)
     */
    public static function assignParticipant(array $enrollment, array $data): bool
    {
        if (!in_array($enrollment['status'], ['awaiting_participant', 'processing'], true)) {
            throw ValidationException::with('participant_email', 'O acesso desta vaga já foi liberado. Para trocar o participante, fale com a nossa equipe.');
        }
        $email = mb_strtolower($data['participant_email']);
        $duplicate = (int) Database::value(
            "SELECT COUNT(*) FROM enrollments WHERE order_item_id = :i AND participant_email = :e AND id <> :id AND status <> 'cancelled'",
            ['i' => $enrollment['order_item_id'], 'e' => $email, 'id' => $enrollment['id']]
        );
        if ($duplicate) {
            throw ValidationException::with('participant_email', 'Este participante já ocupa outra vaga deste mesmo curso.');
        }
        Database::update('enrollments', [
            'participant_name' => $data['participant_name'],
            'participant_email' => $email,
            'participant_document' => $data['participant_document'] ?? null,
            'status' => 'processing',
        ], ['id' => $enrollment['id']]);
        Activity::log('enrollment.participant', 'enrollment', (int) $enrollment['id'], $data['participant_name'] . ' <' . $email . '>');
        return self::autoRelease((int) $enrollment['id']);
    }

    /** Admin: atualiza a vaga. Mudança para "em andamento" avisa o participante. */
    public static function adminUpdate(int $id, array $data): void
    {
        $before = Enrollment::find($id);
        if (!$before) {
            throw ValidationException::with('status', 'Matrícula não encontrada.');
        }
        $status = $data['status'];
        if (in_array($status, ['processing', 'active', 'completed'], true) && !$data['participant_email']) {
            throw ValidationException::with('participant_email', 'Informe o participante antes de liberar o acesso.');
        }
        $progress = $status === 'completed' ? 100 : max(0, min(100, (int) $data['progress']));
        $fields = [
            'participant_name' => $data['participant_name'],
            'participant_email' => $data['participant_email'] ? mb_strtolower($data['participant_email']) : null,
            'participant_document' => $data['participant_document'],
            'status' => $status,
            'progress' => $progress,
            'access_url' => $data['access_url'],
        ];
        if ($status === 'active' && $before['status'] !== 'active' && !$before['released_at']) {
            $fields['released_at'] = date('Y-m-d H:i:s');
        }
        if ($status === 'completed' && !$before['completed_at']) {
            $fields['completed_at'] = date('Y-m-d H:i:s');
        }
        if ($status !== 'completed') {
            $fields['completed_at'] = null;
        }
        Database::update('enrollments', $fields, ['id' => $id]);
        Activity::log('enrollment.updated', 'enrollment', $id, Enrollment::statusLabel($status) . ' · ' . $progress . '%');

        if ($status === 'active' && $before['status'] !== 'active' && $before['status'] !== 'completed') {
            Notify::accessReleased(Enrollment::find($id));
        }
    }

    /** Admin: registra o certificado (PDF enviado e/ou link da plataforma). */
    public static function attachCertificate(int $id, ?string $filePath, ?string $externalUrl, string $issuedAt): void
    {
        $enrollment = Enrollment::find($id);
        if (!$enrollment) {
            throw ValidationException::with('certificate', 'Matrícula não encontrada.');
        }
        if (!$filePath && !$externalUrl && !$enrollment['certificate_id']) {
            throw ValidationException::with('certificate_file', 'Envie o PDF do certificado ou informe o link.');
        }
        $existing = Database::first('SELECT * FROM certificates WHERE enrollment_id = :e', ['e' => $id]);
        if ($existing) {
            $fields = ['issued_at' => $issuedAt, 'external_url' => $externalUrl ?: $existing['external_url']];
            if ($filePath) {
                Uploads::deletePrivate($existing['file_path']);
                $fields['file_path'] = $filePath;
            }
            Database::update('certificates', $fields, ['id' => $existing['id']]);
        } else {
            Database::insert('certificates', [
                'enrollment_id' => $id,
                'code' => Certificates::newCode(),
                'file_path' => $filePath,
                'external_url' => $externalUrl,
                'issued_at' => $issuedAt,
            ]);
        }
        if ($enrollment['status'] !== 'completed') {
            Database::update('enrollments', ['status' => 'completed', 'progress' => 100, 'completed_at' => $enrollment['completed_at'] ?: date('Y-m-d H:i:s')], ['id' => $id]);
        }
        Activity::log('certificate.attached', 'enrollment', $id);
        if (!$existing) {
            Notify::certificateIssued(Enrollment::find($id));
        }
    }
}
