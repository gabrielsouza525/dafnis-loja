<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Database;
use Throwable;

/** Trilha de auditoria das ações importantes (login, pedidos, liberações, edições do admin). */
final class Activity
{
    public static function log(string $action, ?string $subjectType = null, ?int $subjectId = null, ?string $details = null): void
    {
        try {
            Database::insert('activity_log', [
                'user_id' => Auth::id(),
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'details' => $details !== null ? mb_substr($details, 0, 500) : null,
                'ip' => App::request()?->ip(),
            ]);
        } catch (Throwable) {
            // Auditoria nunca interrompe a ação principal.
        }
    }

    public static function forSubject(string $type, int $id, int $limit = 30): array
    {
        return Database::select(
            'SELECT a.*, u.name AS user_name FROM activity_log a LEFT JOIN users u ON u.id = a.user_id
              WHERE a.subject_type = :t AND a.subject_id = :id ORDER BY a.id DESC LIMIT ' . (int) $limit,
            ['t' => $type, 'id' => $id]
        );
    }
}
