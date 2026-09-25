<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ValidationException;
use App\Models\Enrollment;

/**
 * Operações sobre as vagas: indicar participante (comprador), liberar acesso,
 * registrar progresso, concluir e anexar certificado (equipe Dafnis).
 */
final class Enrollments
{
    /** O comprador indica (ou corrige) quem vai fazer o curso, enquanto o acesso não foi liberado. */
    public static function assignParticipant(array $enrollment, array $data): void
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
                'code' => self::newCertificateCode(),
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

    private static function newCertificateCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = 'DF-';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while ((int) Database::value('SELECT COUNT(*) FROM certificates WHERE code = :c', ['c' => $code]) > 0);
        return $code;
    }
}
