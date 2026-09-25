<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Uma matrícula = uma vaga comprada em um curso, com o participante que vai fazê-lo. */
final class Enrollment
{
    public const STATUS = [
        'awaiting_participant' => 'Aguardando participante',
        'processing' => 'Acesso em liberação',
        'active' => 'Em andamento',
        'completed' => 'Concluído',
        'cancelled' => 'Cancelado',
    ];

    public const STATUS_TONE = [
        'awaiting_participant' => 'warn',
        'processing' => 'info',
        'active' => 'blue',
        'completed' => 'ok',
        'cancelled' => 'muted',
    ];

    private const SELECT = 'SELECT e.*, i.course_title, i.course_code, i.course_hours,
                                   c.slug AS course_slug, c.nr_number, c.short_title, c.icon, c.modality, c.access_url AS course_access_url,
                                   k.tone, o.number AS order_number, o.status AS order_status,
                                   cert.id AS certificate_id, cert.code AS certificate_code, cert.issued_at AS certificate_issued_at,
                                   cert.file_path AS certificate_file, cert.external_url AS certificate_url
                              FROM enrollments e
                              JOIN order_items i ON i.id = e.order_item_id
                              JOIN orders o ON o.id = e.order_id
                              LEFT JOIN courses c ON c.id = e.course_id
                              LEFT JOIN categories k ON k.id = c.category_id
                              LEFT JOIN certificates cert ON cert.enrollment_id = e.id';

    public static function find(int $id): ?array
    {
        return Database::first(self::SELECT . ' WHERE e.id = :id', ['id' => $id]);
    }

    /** Cursos em que a pessoa é a participante (comprados por ela ou pela empresa dela). */
    public static function forParticipant(string $email): array
    {
        return Database::select(
            self::SELECT . " WHERE e.participant_email = :e AND e.status IN ('processing','active','completed')
                             ORDER BY FIELD(e.status, 'active', 'processing', 'completed'), e.updated_at DESC",
            ['e' => mb_strtolower($email)]
        );
    }

    /** Vagas compradas por este usuário (para indicar participantes). */
    public static function seatsBoughtBy(int $userId, ?int $orderId = null): array
    {
        $params = ['u' => $userId];
        $sql = self::SELECT . " WHERE e.buyer_user_id = :u AND e.status <> 'cancelled'";
        if ($orderId !== null) {
            $sql .= ' AND e.order_id = :o';
            $params['o'] = $orderId;
        }
        return Database::select($sql . ' ORDER BY e.order_item_id, e.id', $params);
    }

    public static function forOrder(int $orderId): array
    {
        return Database::select(self::SELECT . ' WHERE e.order_id = :o ORDER BY e.order_item_id, e.id', ['o' => $orderId]);
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUS[$status] ?? $status;
    }

    /** Link de acesso: o da matrícula, senão o do curso, senão o endereço geral da plataforma. */
    public static function accessUrl(array $e): ?string
    {
        return $e['access_url'] ?: ($e['course_access_url'] ?: (\App\Services\Settings::get('lms.url') ?: null));
    }
}
