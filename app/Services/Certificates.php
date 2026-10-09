<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;
use App\Core\ValidationException;
use App\Models\Course;
use App\Models\Enrollment;
use Throwable;

/**
 * Certificados no modelo da Dafnis, gerados pela própria loja (CertificatePdf), com código e QR code
 * de validação em /certificados/{código}.
 *
 * - Curso feito na loja, sem prática: o certificado sai sozinho quando o participante é aprovado.
 * - Curso com prática presencial: sai quando a equipe registra a prática na matrícula.
 * - Qualquer curso (inclusive os da plataforma de ensino): a equipe gera pelo painel da matrícula.
 *
 * O que vai impresso fica guardado no registro (coluna data), para gerar de novo o mesmo documento
 * e para a página de validação.
 */
final class Certificates
{
    public const SAMPLE_CODE = 'DF-EXEMPLO1';

    /** Dados da empresa e preferências do certificado, com os padrões do cadastro da loja. */
    public static function settings(): array
    {
        $city = Settings::get('business.city');
        $state = Settings::get('business.state');
        return [
            'auto' => Settings::get('certificate.auto', '1') !== '0',
            'company' => (string) (Settings::get('certificate.company') ?: Settings::businessName()),
            'legal_name' => (string) (Settings::get('certificate.legal_name') ?: mb_strtoupper(Settings::businessName())),
            'city' => (string) (Settings::get('certificate.city') ?: ($city ? $city . ($state ? '/' . $state : '') : '')),
            'cnpj' => preg_replace('/\D/', '', (string) Settings::get('business.cnpj', '')),
            'address' => (string) Settings::get('business.address', ''),
            'phone' => (string) (Settings::get('business.phone') ?: Settings::get('business.whatsapp', '')),
        ];
    }

    /** Signatários cadastrados em Configurações: [{id, name, role, registry, signature, default}]. */
    public static function signers(): array
    {
        return array_values(array_filter(
            Settings::json('certificate.signers'),
            static fn ($s) => is_array($s) && trim((string) ($s['name'] ?? '')) !== '' && trim((string) ($s['id'] ?? '')) !== ''
        ));
    }

    /** Quem assina o certificado do curso: os escolhidos no curso ou, sem escolha, os marcados como padrão. */
    public static function signersFor(array $course): array
    {
        $all = self::signers();
        $ids = json_decode((string) ($course['cert_signers'] ?? ''), true);
        if (is_array($ids) && $ids) {
            $byId = array_column($all, null, 'id');
            return array_values(array_filter(array_map(static fn ($id) => $byId[$id] ?? null, $ids)));
        }
        return array_values(array_filter($all, static fn ($s) => !empty($s['default'])));
    }

    /** Nome do treinamento no certificado: o informado no curso ou "NR 10 – Título". */
    public static function courseName(array $course): string
    {
        $custom = trim((string) ($course['cert_name'] ?? ''));
        if ($custom !== '') {
            return $custom;
        }
        $code = $course['code'] ?: ($course['nr_number'] ? 'NR ' . $course['nr_number'] : '');
        return ($code ? $code . ' – ' : '') . $course['title'];
    }

    /** Conteúdo programático do verso: o próprio do certificado ou, sem ele, os módulos da página do curso. */
    public static function syllabusFor(array $course): string
    {
        $custom = trim((string) ($course['cert_syllabus'] ?? ''));
        if ($custom !== '') {
            return $custom;
        }
        $lines = [];
        foreach (Course::syllabus($course) as $i => $module) {
            $lines[] = ($i + 1) . '. ' . $module['title'];
            foreach (preg_split('/\R/u', $module['topics']) ?: [] as $topic) {
                $topic = trim((string) preg_replace('/^[-•*]\s*/u', '', trim($topic)));
                if ($topic !== '') {
                    $lines[] = $topic;
                }
            }
        }
        return implode("\n", $lines);
    }

    /** O que falta para gerar o certificado desta matrícula (lista vazia = pode gerar). */
    public static function missing(array $enrollment, ?array $course): array
    {
        $out = [];
        if (!$course) {
            return ['o curso, que não existe mais na loja'];
        }
        if (mb_strlen(trim((string) $enrollment['participant_name'])) < 3) {
            $out[] = 'o nome do participante';
        }
        if (strlen(preg_replace('/\D/', '', (string) $enrollment['participant_document'])) !== 11) {
            $out[] = 'o CPF do participante';
        }
        if (strlen(self::settings()['cnpj']) !== 14) {
            $out[] = 'o CNPJ da empresa (Configurações)';
        }
        if (!self::signersFor($course)) {
            $out[] = 'quem assina (Configurações › Certificados)';
        }
        if (self::syllabusFor($course) === '') {
            $out[] = 'o conteúdo programático do curso';
        }
        return $out;
    }

    /**
     * Gera (ou gera de novo, com o mesmo código) o certificado e conclui a matrícula.
     * $options: start, end, issued_at (Y-m-d), practical_location, location.
     */
    public static function generate(int $enrollmentId, array $options = [], bool $notify = true): array
    {
        $e = Enrollment::find($enrollmentId);
        if (!$e) {
            throw ValidationException::with('certificate', 'Matrícula não encontrada.');
        }
        $course = $e['course_id'] ? Database::first('SELECT * FROM courses WHERE id = :id', ['id' => $e['course_id']]) : null;
        if ($missing = self::missing($e, $course)) {
            throw ValidationException::with('certificate', 'Para gerar o certificado, falta ' . self::listText($missing) . '.');
        }
        $existing = Database::first('SELECT * FROM certificates WHERE enrollment_id = :e', ['e' => $enrollmentId]);
        $code = $existing['code'] ?? self::newCode();
        $data = self::data($e, $course, $options, $code);
        $path = self::store(CertificatePdf::render(self::withFiles($data)), $code);

        if ($existing) {
            if ($existing['file_path'] !== $path) {
                Uploads::deletePrivate($existing['file_path']);
            }
            Database::update('certificates', ['file_path' => $path, 'data' => self::json($data), 'issued_at' => $data['issued_at']], ['id' => $existing['id']]);
        } else {
            Database::insert('certificates', [
                'enrollment_id' => $enrollmentId,
                'code' => $code,
                'file_path' => $path,
                'data' => self::json($data),
                'issued_at' => $data['issued_at'],
            ]);
        }
        $fields = [];
        if ($e['status'] !== 'completed') {
            $fields += ['status' => 'completed', 'progress' => 100, 'completed_at' => $e['completed_at'] ?: date('Y-m-d H:i:s')];
        }
        if (!empty($e['practical_required']) && !empty($options['practical_location'])) {
            $fields += ['practical_done_at' => $data['end'], 'practical_location' => mb_substr((string) $options['practical_location'], 0, 160)];
        }
        if ($fields) {
            Database::update('enrollments', $fields, ['id' => $enrollmentId]);
        }
        Activity::log($existing ? 'certificate.regenerated' : 'certificate.generated', 'enrollment', $enrollmentId, 'Certificado ' . $code . ($existing ? ' gerado de novo' : ' emitido'));
        if (!$existing && $notify) {
            Notify::certificateIssued(Enrollment::find($enrollmentId));
        }
        return Database::first('SELECT * FROM certificates WHERE enrollment_id = :e', ['e' => $enrollmentId]) ?? [];
    }

    /**
     * Emissão automática quando o participante conclui o curso na loja.
     * @return list<string> o que impediu a emissão (vazio = certificado emitido)
     */
    public static function autoIssue(int $enrollmentId): array
    {
        $e = Enrollment::find($enrollmentId);
        if (!$e) {
            return ['a matrícula'];
        }
        if ($e['certificate_id']) {
            return [];
        }
        $course = $e['course_id'] ? Database::first('SELECT * FROM courses WHERE id = :id', ['id' => $e['course_id']]) : null;
        if (!$course || !$course['certificate']) {
            return ['curso sem certificado'];
        }
        if (!self::settings()['auto']) {
            return ['emissão automática desligada (Configurações › Certificados)'];
        }
        if ($missing = self::missing($e, $course)) {
            Activity::log('certificate.pending', 'enrollment', $enrollmentId, 'Certificado não emitido: falta ' . self::listText($missing));
            if (in_array('o CPF do participante', $missing, true)) {
                Notify::certificateNeedsDocument($e);
            }
            return $missing;
        }
        try {
            self::generate($enrollmentId);
            return [];
        } catch (Throwable $ex) {
            Logger::error('Falha ao gerar certificado', ['enrollment' => $enrollmentId, 'error' => $ex->getMessage()]);
            return ['erro ao gerar o PDF: ' . $ex->getMessage()];
        }
    }

    /** Valores iniciais do formulário "Gerar certificado" da matrícula: os do certificado já gerado ou os calculados. */
    public static function defaults(array $e): array
    {
        $stored = json_decode((string) (Database::value('SELECT data FROM certificates WHERE enrollment_id = :e', ['e' => $e['id']]) ?? ''), true);
        if (is_array($stored) && isset($stored['start'], $stored['end'])) {
            return [
                'start' => $stored['start'],
                'end' => $stored['end'],
                'issued_at' => $stored['issued_at'] ?? $stored['end'],
                'practical_location' => (string) ($e['practical_location'] ?? ''),
            ];
        }
        $end = $e['practical_done_at'] ?: (($e['online_done_at'] ?? null) ? date('Y-m-d', strtotime((string) $e['online_done_at'])) : date('Y-m-d'));
        return [
            'start' => self::firstAccess($e),
            'end' => $end,
            'issued_at' => $end,
            'practical_location' => (string) ($e['practical_location'] ?? ''),
        ];
    }

    /** Caminho do PDF; se o arquivo sumiu, gera de novo a partir dos dados guardados. */
    public static function file(array $certificate): ?string
    {
        if ($path = Uploads::privatePath($certificate['file_path'] ?? null)) {
            return $path;
        }
        $data = json_decode((string) ($certificate['data'] ?? ''), true);
        if (!is_array($data)) {
            return null;
        }
        $relative = self::store(CertificatePdf::render(self::withFiles($data)), (string) $certificate['code']);
        Database::update('certificates', ['file_path' => $relative], ['id' => $certificate['id']]);
        return Uploads::privatePath($relative);
    }

    /** PDF de exemplo do curso, para conferir o modelo no painel. */
    public static function sample(array $course): string
    {
        $fake = [
            'participant_name' => 'Nome do Participante', 'participant_document' => '00000000000',
            'course_hours' => $course['hours'], 'practical_required' => $course['practical_required'],
            'practical_location' => null, 'practical_done_at' => null,
        ];
        $options = ['start' => date('Y-m-d', strtotime('-4 days')), 'end' => date('Y-m-d'), 'practical_location' => $course['practical_required'] ? 'local da prática' : null];
        return CertificatePdf::render(self::withFiles(self::data($fake, $course, $options, self::SAMPLE_CODE)));
    }

    /** Certificado pelo código, para a página pública de validação. */
    public static function findByCode(string $code): ?array
    {
        $code = strtoupper(trim($code));
        if (!preg_match('/^DF-[A-Z0-9]{8}$/', $code)) {
            return null;
        }
        $row = Database::first(
            'SELECT c.*, e.participant_name, e.participant_document, e.status AS enrollment_status, e.completed_at,
                    i.course_title, i.course_code, i.course_hours
               FROM certificates c
               JOIN enrollments e ON e.id = c.enrollment_id
               JOIN order_items i ON i.id = e.order_item_id
              WHERE c.code = :c',
            ['c' => $code]
        );
        if (!$row) {
            return null;
        }
        $data = json_decode((string) ($row['data'] ?? ''), true) ?: [];
        return [
            'code' => $row['code'],
            'valid' => $row['enrollment_status'] !== 'cancelled',
            'name' => (string) ($data['name'] ?? $row['participant_name']),
            'document' => document_masked((string) ($data['cpf'] ?? $row['participant_document'])),
            'course' => (string) ($data['course_name'] ?? (($row['course_code'] ? $row['course_code'] . ' – ' : '') . $row['course_title'])),
            'hours' => (int) ($data['hours'] ?? $row['course_hours']),
            'period' => isset($data['start'], $data['end']) ? self::periodText($data['start'], $data['end']) : null,
            'issued_at' => $row['issued_at'],
            'company' => (string) ($data['company'] ?? self::settings()['company']),
        ];
    }

    public static function newCode(): string
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

    /** Período do treinamento por extenso curto: "de 07 a 11/09/2026", "em 11/09/2026". */
    public static function periodText(string $start, string $end): string
    {
        $s = strtotime($start);
        $e = strtotime($end);
        if ($e < $s) {
            [$s, $e] = [$e, $s];
        }
        return match (true) {
            date('Y-m-d', $s) === date('Y-m-d', $e) => 'em ' . date('d/m/Y', $e),
            date('Y-m', $s) === date('Y-m', $e) => 'de ' . date('d', $s) . ' a ' . date('d/m/Y', $e),
            date('Y', $s) === date('Y', $e) => 'de ' . date('d/m', $s) . ' a ' . date('d/m/Y', $e),
            default => 'de ' . date('d/m/Y', $s) . ' a ' . date('d/m/Y', $e),
        };
    }

    // ------------------------------------------------------------------ internos

    /** Monta tudo o que vai impresso. */
    private static function data(array $e, array $course, array $options, string $code): array
    {
        $s = self::settings();
        $practical = !empty($course['practical_required']);
        $start = self::dateOption($options['start'] ?? null) ?? self::firstAccess($e);
        $end = self::dateOption($options['end'] ?? null) ?? self::conclusion($e);
        if ($end < $start) {
            $end = $start;
        }
        $issued = self::dateOption($options['issued_at'] ?? null) ?? $end;
        $hours = (int) ($course['hours'] ?: ($e['course_hours'] ?? 0));
        $kind = ($course['training_type'] ?? '') === 'periodico' ? 'Reciclagem' : 'Formação';
        $name = self::courseName($course);
        $cpf = preg_replace('/\D/', '', (string) $e['participant_document']);
        // Site no cabeçalho do verso, como no modelo: "www." + domínio da loja.
        $site = preg_replace('#^https?://#', '', rtrim((string) env('APP_URL', ''), '/'));
        if (preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/i', $site) && !str_starts_with($site, 'www.')) {
            $site = 'www.' . $site;
        }
        $verifyBase = rtrim(absolute_url('/certificados'), '/');

        $online = 'on-line (EAD), na plataforma de ensino da ' . $s['company'];
        $practicalPlace = trim((string) ($options['practical_location'] ?? $e['practical_location'] ?? ''));
        $location = trim((string) ($options['location'] ?? ''));
        if ($location === '') {
            $location = match (true) {
                ($course['modality'] ?? '') === 'presencial' => $practicalPlace !== '' ? $practicalPlace : $s['city'],
                $practical => 'parte teórica ' . $online . '; parte prática presencial' . ($practicalPlace !== '' ? ' em ' . $practicalPlace : '') . ', em ' . date('d/m/Y', strtotime($end)),
                default => $online,
            };
        }

        $statement = sprintf(
            'Titular do CPF: %s, concluiu o Treinamento de %s em %s, realizado %s, com carga horária de %s, tendo o seu aproveitamento avaliado e aprovado pela empresa %s, inscrita no CNPJ: %s, que atesta a sua aptidão, pelo que lhe é conferido o presente Certificado.',
            document_display($cpf), $kind, mb_strtoupper($name), self::periodText($start, $end), self::hoursText($hours), $s['company'], document_display($s['cnpj'])
        );

        return [
            'code' => $code,
            'issued_at' => $issued,
            'start' => $start,
            'end' => $end,
            'name' => trim(preg_replace('/\s+/u', ' ', (string) $e['participant_name'])),
            'cpf' => $cpf,
            'course_id' => (int) ($course['id'] ?? 0),
            'course_name' => $name,
            'kind' => $kind,
            'hours' => $hours,
            'hours_label' => self::hoursText($hours),
            'nr' => self::nrLabel($course),
            'city_date' => ($s['city'] !== '' ? $s['city'] . ', ' : '') . self::longDate($issued),
            'statement' => $statement,
            'company' => $s['company'],
            'legal_name' => $s['legal_name'],
            'cnpj' => $s['cnpj'],
            'site' => $site,
            'contact_line' => self::contactLine($s),
            'syllabus' => self::syllabusFor($course),
            'location' => rtrim($location, '.') . '.',
            'signers' => array_map(static fn ($x) => [
                'id' => (string) $x['id'],
                'name' => trim((string) $x['name']),
                'role' => trim((string) ($x['role'] ?? '')),
                'registry' => trim((string) ($x['registry'] ?? '')),
                'signature' => $x['signature'] ?? null,
            ], self::signersFor($course)),
            'verify_url' => $verifyBase . '/' . $code,
            'verify_host' => preg_replace('#^https?://#', '', $verifyBase),
        ];
    }

    /** Troca o caminho guardado da assinatura pelo arquivo no disco, para o desenho. */
    private static function withFiles(array $data): array
    {
        foreach ($data['signers'] as $i => $signer) {
            $data['signers'][$i]['signature_file'] = !empty($signer['signature']) ? Uploads::privatePath($signer['signature']) : null;
        }
        return $data;
    }

    private static function store(string $pdf, string $code): string
    {
        $dir = BASE_PATH . '/storage/uploads/certificados';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Não foi possível criar a pasta dos certificados.');
        }
        $name = date('Ym') . '-' . strtolower($code) . '-' . bin2hex(random_bytes(6)) . '.pdf';
        if (file_put_contents("$dir/$name", $pdf) === false) {
            throw new \RuntimeException('Não foi possível gravar o certificado.');
        }
        return "certificados/$name";
    }

    /** Início: primeiro acesso ao curso na loja; sem acesso registrado, a liberação. */
    private static function firstAccess(array $e): string
    {
        $first = isset($e['id']) ? Database::value(
            'SELECT MIN(s.started_at) FROM study_sessions s JOIN scorm_attempts a ON a.id = s.attempt_id WHERE a.enrollment_id = :e',
            ['e' => $e['id']]
        ) : null;
        return date('Y-m-d', strtotime((string) ($first ?: ($e['released_at'] ?? null) ?: ($e['created_at'] ?? null) ?: 'today')));
    }

    /** Término: a prática registrada, senão a aprovação no curso on-line, senão a conclusão. */
    private static function conclusion(array $e): string
    {
        $when = $e['practical_done_at'] ?? null;
        if (!$when && !empty($e['practical_required'])) {
            $when = date('Y-m-d');
        }
        $when = $when ?: ($e['online_done_at'] ?? null) ?: ($e['completed_at'] ?? null) ?: 'today';
        return date('Y-m-d', strtotime((string) $when));
    }

    /** Número do selo: o do código exibido ("NR 31.7" → "31.7") ou o número da NR do curso; sem NR, sem selo. */
    private static function nrLabel(array $course): ?string
    {
        if (preg_match('/^NR\s*(\d{1,2}(?:\.\d{1,2})?)$/i', trim((string) ($course['code'] ?? '')), $m)) {
            return $m[1];
        }
        return !empty($course['nr_number']) ? (string) (int) $course['nr_number'] : null;
    }

    private static function dateOption(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' && ($ts = strtotime($value)) ? date('Y-m-d', $ts) : null;
    }

    private static function hoursText(int $hours): string
    {
        return $hours === 1 ? '1 hora' : $hours . ' horas';
    }

    /** "11 de Setembro de 2026", como no modelo. */
    private static function longDate(string $date): string
    {
        $ts = strtotime($date);
        return (int) date('j', $ts) . ' de ' . mb_convert_case(MONTHS[(int) date('n', $ts) - 1], MB_CASE_TITLE) . ' de ' . date('Y', $ts);
    }

    private static function contactLine(array $s): string
    {
        $parts = [];
        $place = trim($s['address'] . ($s['address'] !== '' && $s['city'] !== '' ? ', ' : '') . $s['city']);
        if ($place !== '') {
            $parts[] = $place;
        }
        if ($s['phone'] !== '') {
            $parts[] = 'Telefone: ' . phone_display($s['phone']);
        }
        return implode(' – ', $parts);
    }

    private static function listText(array $items): string
    {
        $last = array_pop($items);
        return $items ? implode(', ', $items) . ' e ' . $last : (string) $last;
    }

    private static function json(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
