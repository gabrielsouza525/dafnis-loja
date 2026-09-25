<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Order
{
    public const STATUS = [
        'pending' => 'Aguardando pagamento',
        'paid' => 'Pago',
        'cancelled' => 'Cancelado',
        'refunded' => 'Estornado',
    ];

    public const STATUS_TONE = ['pending' => 'warn', 'paid' => 'ok', 'cancelled' => 'muted', 'refunded' => 'muted'];

    public const METHODS = [
        'pix' => ['label' => 'Pix', 'sub' => 'Pagamento instantâneo', 'icon' => 'pix'],
        'card' => ['label' => 'Cartão de crédito', 'sub' => 'Parcelamento conforme o Mercado Pago', 'icon' => 'card'],
        'boleto' => ['label' => 'Boleto bancário', 'sub' => 'Compensação em até 3 dias úteis', 'icon' => 'barcode'],
    ];

    public static function findByNumber(string $number): ?array
    {
        return Database::first('SELECT * FROM orders WHERE number = :n', ['n' => $number]);
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM orders WHERE id = :id', ['id' => $id]);
    }

    public static function items(int $orderId): array
    {
        return Database::select(
            'SELECT i.*, c.slug AS course_slug, c.nr_number, c.short_title, c.icon, c.is_active AS course_active, k.tone
               FROM order_items i
               LEFT JOIN courses c ON c.id = i.course_id
               LEFT JOIN categories k ON k.id = c.category_id
              WHERE i.order_id = :o ORDER BY i.id',
            ['o' => $orderId]
        );
    }

    public static function forUser(int $userId, ?int $limit = null): array
    {
        return Database::select(
            'SELECT o.*, (SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = o.id) AS participants,
                    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
               FROM orders o WHERE o.user_id = :u ORDER BY o.id DESC' . ($limit ? ' LIMIT ' . (int) $limit : ''),
            ['u' => $userId]
        );
    }

    public static function statusLabel(string $status): string
    {
        return self::STATUS[$status] ?? $status;
    }
}
