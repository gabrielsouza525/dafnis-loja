<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Coupon
{
    public static function findByCode(string $code, bool $forUpdate = false): ?array
    {
        return Database::first(
            'SELECT * FROM coupons WHERE code = :c' . ($forUpdate ? ' FOR UPDATE' : ''),
            ['c' => self::normalize($code)]
        );
    }

    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', $code) ?? '');
    }

    /**
     * Confere se o cupom vale para este subtotal.
     * @return string|null mensagem de erro, ou null quando o cupom é válido
     */
    public static function problem(?array $coupon, float $subtotal): ?string
    {
        if (!$coupon || !(int) $coupon['is_active']) {
            return 'Cupom inválido ou expirado.';
        }
        $now = date('Y-m-d H:i:s');
        if (($coupon['starts_at'] && $coupon['starts_at'] > $now) || ($coupon['ends_at'] && $coupon['ends_at'] < $now)) {
            return 'Este cupom não está dentro do período de validade.';
        }
        if ($coupon['max_uses'] !== null && (int) $coupon['uses'] >= (int) $coupon['max_uses']) {
            return 'Este cupom já atingiu o limite de usos.';
        }
        if ($coupon['min_subtotal'] !== null && $subtotal < (float) $coupon['min_subtotal']) {
            return 'Este cupom vale para compras a partir de ' . money($coupon['min_subtotal']) . '.';
        }
        return null;
    }

    public static function discountFor(array $coupon, float $subtotal): float
    {
        $value = (float) $coupon['value'];
        $discount = $coupon['type'] === 'percent' ? round($subtotal * $value / 100, 2) : $value;
        return min(round($discount, 2), $subtotal);
    }

    public static function describe(array $coupon): string
    {
        return $coupon['type'] === 'percent'
            ? rtrim(rtrim(number_format((float) $coupon['value'], 2, ',', '.'), '0'), ',') . '% de desconto'
            : money($coupon['value']) . ' de desconto';
    }
}
