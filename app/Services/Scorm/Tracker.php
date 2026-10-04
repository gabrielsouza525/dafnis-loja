<?php
declare(strict_types=1);

namespace App\Services\Scorm;

use App\Core\Database;
use App\Core\Request;
use App\Models\Enrollment;
use App\Services\Activity;
use App\Services\Notify;

/**
 * Andamento dos participantes nos cursos SCORM: o que o curso grava pela API (situação, nota,
 * ponto de parada, respostas) e o registro de acesso com o tempo de estudo medido pela loja.
 */
final class Tracker
{
    public const STATUS = [
        'not attempted' => 'Não iniciado',
        'browsed' => 'Visualizado',
        'incomplete' => 'Em andamento',
        'failed' => 'Reprovado na prova',
        'completed' => 'Concluído',
        'passed' => 'Aprovado',
    ];

    /** Tempo máximo creditado por aviso de presença (a página avisa a cada minuto). */
    private const MAX_HEARTBEAT_SECONDS = 90;

    /** Situações que encerram a parte on-line e não voltam atrás. */
    private const DONE = ['passed', 'completed'];

    /**
     * Tentativa do participante: continua na versão em que começou; quem ainda não começou
     * recebe a versão em uso do curso. Null quando o curso não tem conteúdo SCORM.
     */
    public static function attemptFor(array $enrollment): ?array
    {
        $attempt = Database::first(
            'SELECT a.* FROM scorm_attempts a WHERE a.enrollment_id = :e ORDER BY a.updated_at DESC, a.id DESC LIMIT 1',
            ['e' => $enrollment['id']]
        );
        if ($attempt) {
            return $attempt;
        }
        $package = $enrollment['course_id'] ? Packages::current((int) $enrollment['course_id']) : null;
        if (!$package) {
            return null;
        }
        Database::query(
            'INSERT IGNORE INTO scorm_attempts (enrollment_id, package_id) VALUES (:e, :p)',
            ['e' => $enrollment['id'], 'p' => $package['id']]
        );
        return Database::first('SELECT * FROM scorm_attempts WHERE enrollment_id = :e AND package_id = :p', ['e' => $enrollment['id'], 'p' => $package['id']]);
    }

    public static function startSession(array $attempt, Request $request): int
    {
        return Database::insert('study_sessions', [
            'attempt_id' => $attempt['id'],
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    public static function session(array $attempt, int $sessionId): ?array
    {
        return Database::first('SELECT * FROM study_sessions WHERE id = :id AND attempt_id = :a', ['id' => $sessionId, 'a' => $attempt['id']]);
    }

    /** Valores iniciais da API do SCORM 1.2 (cmi.*) para a página do curso. */
    public static function initialData(array $attempt, array $package, array $enrollment): array
    {
        $resume = $attempt['lesson_status'] !== 'not attempted' || (string) $attempt['suspend_data'] !== '' || (string) $attempt['lesson_location'] !== '';
        $done = in_array($attempt['lesson_status'], self::DONE, true);
        return [
            'cmi.core.student_id' => 'DF' . str_pad((string) $enrollment['id'], 6, '0', STR_PAD_LEFT),
            'cmi.core.student_name' => self::scormName((string) $enrollment['participant_name']),
            'cmi.core.lesson_location' => (string) $attempt['lesson_location'],
            'cmi.core.credit' => 'credit',
            'cmi.core.lesson_status' => $attempt['lesson_status'],
            'cmi.core.entry' => $resume ? 'resume' : 'ab-initio',
            'cmi.core.score.raw' => $attempt['score_raw'] !== null ? self::number($attempt['score_raw']) : '',
            'cmi.core.score.max' => $attempt['score_max'] !== null ? self::number($attempt['score_max']) : '',
            'cmi.core.score.min' => '',
            'cmi.core.total_time' => self::formatTime(self::courseSeconds((int) $attempt['id'])),
            'cmi.core.lesson_mode' => $done ? 'review' : 'normal',
            'cmi.suspend_data' => (string) $attempt['suspend_data'],
            'cmi.launch_data' => '',
            'cmi.comments' => '',
            'cmi.comments_from_lms' => '',
            'cmi.student_data.mastery_score' => $package['mastery_score'] !== null ? (string) $package['mastery_score'] : '',
            'cmi.student_data.max_time_allowed' => '',
            'cmi.student_data.time_limit_action' => 'continue,no message',
            'cmi.student_preference.audio' => '0',
            'cmi.student_preference.language' => 'pt-BR',
            'cmi.student_preference.speed' => '0',
            'cmi.student_preference.text' => '0',
            // As respostas são só de escrita no SCORM 1.2; o curso usa a contagem para numerar as novas.
            'cmi.interactions._count' => (string) count(json_decode((string) $attempt['interactions'], true) ?: []),
        ];
    }

    /**
     * Grava o que o curso enviou (LMSCommit/LMSFinish). Aprovação e conclusão não voltam atrás:
     * refazer a prova depois de aprovado não troca a situação nem a nota.
     */
    public static function commit(array $attempt, array $enrollment, ?int $sessionId, array $data): array
    {
        $fields = [];
        $current = $attempt['lesson_status'];
        $status = is_string($data['status'] ?? null) ? strtolower(trim($data['status'])) : null;
        $locked = in_array($current, self::DONE, true);
        if ($status !== null && isset(self::STATUS[$status]) && $status !== $current) {
            if (!$locked || $status === 'passed') {
                $fields['lesson_status'] = $status;
            }
        }
        $keepScore = $locked && ($fields['lesson_status'] ?? $current) === $current && $status !== 'passed';
        if (isset($data['score']) && is_array($data['score']) && !$keepScore) {
            foreach (['raw' => 'score_raw', 'max' => 'score_max'] as $key => $column) {
                if (array_key_exists($key, $data['score'])) {
                    $value = $data['score'][$key];
                    $fields[$column] = is_numeric($value) ? max(-9999, min(9999, round((float) $value, 2))) : null;
                }
            }
        }
        if (array_key_exists('location', $data) && is_string($data['location'])) {
            $fields['lesson_location'] = mb_substr($data['location'], 0, 255);
        }
        if (array_key_exists('suspend', $data) && is_string($data['suspend'])) {
            $fields['suspend_data'] = substr($data['suspend'], 0, 1024 * 1024);
        }
        if (!empty($data['interactions']) && is_array($data['interactions'])) {
            $fields['interactions'] = self::mergeInteractions((string) $attempt['interactions'], $data['interactions']);
        }
        $newStatus = $fields['lesson_status'] ?? $current;
        if (array_key_exists('suspend_data', $fields)) {
            $progress = RiseProgress::fromSuspendData($fields['suspend_data']);
            if ($progress !== null) {
                $fields['progress'] = $progress;
            }
        }
        $justDone = !$attempt['passed_at'] && in_array($newStatus, self::DONE, true);
        if ($justDone) {
            $fields['passed_at'] = date('Y-m-d H:i:s');
        }
        if ($fields) {
            Database::update('scorm_attempts', $fields, ['id' => $attempt['id']]);
        }

        if ($sessionId !== null && isset($data['session_time']) && is_string($data['session_time'])) {
            $seconds = self::parseTime($data['session_time']);
            if ($seconds !== null) {
                // Cada abertura do curso tem a sua sessão; o curso informa o tempo acumulado dela.
                Database::query(
                    'UPDATE study_sessions SET course_seconds = GREATEST(course_seconds, :s) WHERE id = :id AND attempt_id = :a',
                    ['s' => min($seconds, 24 * 3600), 'id' => $sessionId, 'a' => $attempt['id']]
                );
            }
        }

        $attempt = array_merge($attempt, $fields);
        self::syncEnrollment($attempt, $enrollment, $justDone);
        return $attempt;
    }

    /**
     * Aviso de presença da página do curso: soma o tempo desde o último aviso, se a pessoa estava
     * estudando. Com o curso aberto em duas abas, só a aberta primeiro conta, enquanto estiver em uso.
     */
    public static function heartbeat(array $session, bool $active): void
    {
        $elapsed = max(0, time() - strtotime((string) $session['last_seen_at']));
        $credit = $active ? min($elapsed, self::MAX_HEARTBEAT_SECONDS) : 0;
        if ($credit > 0) {
            $parallel = (int) Database::value(
                'SELECT COUNT(*) FROM study_sessions WHERE attempt_id = :a AND id < :id AND last_active_at >= NOW() - INTERVAL ' . self::MAX_HEARTBEAT_SECONDS . ' SECOND',
                ['a' => $session['attempt_id'], 'id' => $session['id']]
            );
            if ($parallel > 0) {
                $credit = 0;
            }
        }
        Database::query(
            'UPDATE study_sessions SET active_seconds = active_seconds + :c, last_seen_at = NOW()' . ($active ? ', last_active_at = NOW()' : '') . ' WHERE id = :id',
            ['c' => $credit, 'id' => $session['id']]
        );
    }

    /** Resumo para a área do aluno e o painel: situação, nota, tempos e acessos (todas as versões). */
    public static function summary(int $enrollmentId): ?array
    {
        $attempt = Database::first(
            'SELECT a.*, p.version, p.mastery_score FROM scorm_attempts a JOIN course_packages p ON p.id = a.package_id
              WHERE a.enrollment_id = :e ORDER BY a.updated_at DESC, a.id DESC LIMIT 1',
            ['e' => $enrollmentId]
        );
        if (!$attempt) {
            return null;
        }
        $totals = Database::first(
            'SELECT COUNT(*) AS sessions, COALESCE(SUM(s.active_seconds), 0) AS active_seconds, COALESCE(SUM(s.course_seconds), 0) AS course_seconds,
                    MIN(s.started_at) AS first_access, MAX(s.last_seen_at) AS last_access
               FROM study_sessions s JOIN scorm_attempts a ON a.id = s.attempt_id WHERE a.enrollment_id = :e',
            ['e' => $enrollmentId]
        );
        $interactions = json_decode((string) $attempt['interactions'], true) ?: [];
        return $attempt + $totals + [
            'status_label' => self::STATUS[$attempt['lesson_status']] ?? $attempt['lesson_status'],
            'done' => in_array($attempt['lesson_status'], self::DONE, true),
            'answers' => count($interactions),
            'answers_correct' => count(array_filter($interactions, static fn ($i) => in_array($i['result'] ?? '', ['correct', '1'], true))),
        ];
    }

    public static function sessions(int $enrollmentId, int $limit = 30): array
    {
        return Database::select(
            'SELECT s.*, p.version FROM study_sessions s JOIN scorm_attempts a ON a.id = s.attempt_id JOIN course_packages p ON p.id = a.package_id
              WHERE a.enrollment_id = :e ORDER BY s.started_at DESC, s.id DESC LIMIT ' . max(1, $limit),
            ['e' => $enrollmentId]
        );
    }

    /** CMITimespan do SCORM 1.2 (HHHH:MM:SS.SS) em segundos. */
    public static function parseTime(string $value): ?int
    {
        if (!preg_match('/^(\d{1,4}):([0-5]?\d):([0-5]?\d)(?:\.\d{1,2})?$/', trim($value), $m)) {
            return null;
        }
        return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3];
    }

    public static function formatTime(int $seconds): string
    {
        return sprintf('%04d:%02d:%02d.00', min(9999, intdiv($seconds, 3600)), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    /** "3 h 12 min" para as telas. */
    public static function duration(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        if ($h === 0) {
            return $m . ' min';
        }
        return $h . ' h' . ($m ? ' ' . $m . ' min' : '');
    }

    /** Horas da parte on-line: carga total menos a prática presencial informada no curso ("8 h ..."). */
    public static function onlineHours(array $course): int
    {
        $hours = (int) ($course['hours'] ?? $course['course_hours'] ?? 0);
        if (!empty($course['practical_required']) && preg_match('/^\s*(\d{1,3})\s*h/i', (string) ($course['practical_hours'] ?? ''), $m)) {
            $hours -= (int) $m[1];
        }
        return max(0, $hours);
    }

    private static function courseSeconds(int $attemptId): int
    {
        return (int) Database::value('SELECT COALESCE(SUM(course_seconds), 0) FROM study_sessions WHERE attempt_id = :a', ['a' => $attemptId]);
    }

    /**
     * Leva o andamento para a matrícula. Ao concluir a parte on-line: sem prática obrigatória, a
     * matrícula fica "Concluído" (a equipe emite o certificado); com prática, continua "Em andamento"
     * até a equipe registrar a parte presencial. Nos dois casos a equipe recebe o aviso.
     */
    private static function syncEnrollment(array $attempt, array $enrollment, bool $justDone): void
    {
        $fields = [];
        if (in_array($attempt['lesson_status'], self::DONE, true)) {
            if ((int) $enrollment['progress'] < 100) {
                $fields['progress'] = 100;
            }
        } elseif ($attempt['progress'] !== null && (int) $attempt['progress'] > (int) $enrollment['progress']) {
            $fields['progress'] = min(99, (int) $attempt['progress']);
        }
        $practical = (bool) Database::value('SELECT practical_required FROM courses WHERE id = :id', ['id' => $enrollment['course_id']]);
        if ($justDone && !$practical && $enrollment['status'] === 'active') {
            $fields['status'] = 'completed';
            $fields['completed_at'] = $enrollment['completed_at'] ?: date('Y-m-d H:i:s');
        }
        if ($fields) {
            Database::update('enrollments', $fields, ['id' => $enrollment['id']]);
        }
        if ($justDone) {
            $score = $attempt['score_raw'] !== null ? ' · nota ' . self::number($attempt['score_raw']) : '';
            Activity::log('enrollment.online_done', 'enrollment', (int) $enrollment['id'], 'Parte on-line: ' . (self::STATUS[$attempt['lesson_status']] ?? $attempt['lesson_status']) . $score);
            Notify::onlinePartDone(Enrollment::find((int) $enrollment['id']), self::summary((int) $enrollment['id']), $practical);
        }
    }

    /** Junta as respostas novas (por índice, como o SCORM numera) às já gravadas. */
    private static function mergeInteractions(string $stored, array $incoming): string
    {
        $list = json_decode($stored, true) ?: [];
        $allowed = ['id', 'time', 'type', 'weighting', 'student_response', 'result', 'latency', 'correct_responses', 'objectives'];
        foreach ($incoming as $index => $values) {
            if (!is_numeric($index) || (int) $index < 0 || (int) $index >= 2000 || !is_array($values)) {
                continue;
            }
            $row = $list[(int) $index] ?? [];
            foreach ($values as $key => $value) {
                if (!in_array($key, $allowed, true)) {
                    continue;
                }
                $row[$key] = is_array($value)
                    ? array_slice(array_map(static fn ($v) => mb_substr((string) $v, 0, 4096), $value), 0, 50)
                    : mb_substr((string) $value, 0, 4096);
            }
            $list[(int) $index] = $row;
        }
        ksort($list);
        return (string) json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** "Souza, Ana" (sobrenome, nome), o formato que o SCORM 1.2 pede. */
    private static function scormName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if (count($parts) < 2) {
            return trim($name);
        }
        $last = array_pop($parts);
        return $last . ', ' . implode(' ', $parts);
    }

    private static function number(mixed $value): string
    {
        $n = (float) $value;
        return $n == (int) $n ? (string) (int) $n : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }
}
