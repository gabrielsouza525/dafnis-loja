<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Core\ValidationException;
use App\Models\Coupon;
use App\Models\Course;

/**
 * Carrinho guardado na sessão: [curso_id => participantes] + cupom.
 * Preços nunca ficam na sessão: são lidos do catálogo a cada exibição e de novo
 * ao fechar o pedido, então uma alteração de preço no admin vale na hora.
 */
final class Cart
{
    public const MAX_QTY = 200;
    public const MAX_ITEMS = 30;

    private static function state(): array
    {
        $cart = Session::get('cart', []);
        return [
            'items' => is_array($cart['items'] ?? null) ? $cart['items'] : [],
            'coupon' => $cart['coupon'] ?? null,
        ];
    }

    private static function save(array $state): void
    {
        Session::set('cart', $state);
    }

    /** @throws ValidationException quando o curso não pode ser comprado online */
    public static function add(int $courseId, int $qty = 1): array
    {
        $course = Course::findActive($courseId);
        if (!$course) {
            throw ValidationException::with('course', 'Este treinamento não está mais disponível.');
        }
        if (!$course['has_price']) {
            throw ValidationException::with('course', 'Este treinamento é vendido sob consulta. Fale com a nossa equipe para receber uma proposta.');
        }
        $state = self::state();
        if (!isset($state['items'][$courseId]) && count($state['items']) >= self::MAX_ITEMS) {
            throw ValidationException::with('course', 'O carrinho atingiu o limite de ' . self::MAX_ITEMS . ' treinamentos diferentes.');
        }
        $state['items'][$courseId] = min(self::MAX_QTY, max(1, ($state['items'][$courseId] ?? 0) + max(1, $qty)));
        self::save($state);
        return $course;
    }

    public static function setQuantity(int $courseId, int $qty): void
    {
        $state = self::state();
        if (isset($state['items'][$courseId])) {
            $state['items'][$courseId] = min(self::MAX_QTY, max(1, $qty));
            self::save($state);
        }
    }

    public static function remove(int $courseId): ?array
    {
        $state = self::state();
        $course = isset($state['items'][$courseId]) ? Course::findActive($courseId) : null;
        unset($state['items'][$courseId]);
        self::save($state);
        return $course;
    }

    public static function clear(): void
    {
        Session::forget('cart');
    }

    /** Participantes no carrinho (é o número mostrado no ícone do cabeçalho). */
    public static function count(): int
    {
        return (int) array_sum(self::state()['items']);
    }

    public static function isEmpty(): bool
    {
        return self::lines() === [];
    }

    /** Linhas válidas do carrinho; itens desativados ou sem preço saem sozinhos. */
    public static function lines(): array
    {
        $state = self::state();
        $lines = [];
        $changed = false;
        foreach ($state['items'] as $id => $qty) {
            $course = Course::findActive((int) $id);
            if (!$course || !$course['has_price']) {
                unset($state['items'][$id]);
                $changed = true;
                continue;
            }
            $qty = max(1, min(self::MAX_QTY, (int) $qty));
            $lines[] = [
                'course' => $course,
                'qty' => $qty,
                'unit_price' => $course['final_price'],
                'list_price' => $course['list_price'],
                'line_total' => round($course['final_price'] * $qty, 2),
                'line_list' => round($course['list_price'] * $qty, 2),
            ];
        }
        if ($changed) {
            self::save($state);
        }
        return $lines;
    }

    /**
     * Subtotal pelos preços de tabela; descontos de oferta e de cupom separados.
     */
    public static function totals(?array $lines = null): array
    {
        $lines ??= self::lines();
        $listTotal = array_sum(array_column($lines, 'line_list'));
        $itemsTotal = array_sum(array_column($lines, 'line_total'));
        $offers = round($listTotal - $itemsTotal, 2);

        $couponDiscount = 0.0;
        $coupon = null;
        $couponError = null;
        $code = self::state()['coupon'];
        if ($code && $lines) {
            $row = Coupon::findByCode($code);
            $couponError = Coupon::problem($row, $itemsTotal);
            if ($couponError === null) {
                $coupon = $row;
                $couponDiscount = Coupon::discountFor($row, $itemsTotal);
            }
        }

        return [
            'count' => (int) array_sum(array_column($lines, 'qty')),
            'subtotal' => round($listTotal, 2),
            'offers' => $offers,
            'coupon' => $coupon,
            'coupon_code' => $code,
            'coupon_error' => $couponError,
            'coupon_discount' => $couponDiscount,
            'discount' => round($offers + $couponDiscount, 2),
            'items_total' => round($itemsTotal, 2),
            'total' => round($itemsTotal - $couponDiscount, 2),
        ];
    }

    /** @throws ValidationException quando o cupom não vale */
    public static function applyCoupon(string $code): array
    {
        $code = Coupon::normalize($code);
        if ($code === '') {
            throw ValidationException::with('coupon', 'Digite um cupom para aplicar.');
        }
        $lines = self::lines();
        $row = Coupon::findByCode($code);
        $problem = Coupon::problem($row, (float) array_sum(array_column($lines, 'line_total')));
        if ($problem !== null) {
            throw ValidationException::with('coupon', $problem);
        }
        $state = self::state();
        $state['coupon'] = $code;
        self::save($state);
        return $row;
    }

    public static function removeCoupon(): void
    {
        $state = self::state();
        $state['coupon'] = null;
        self::save($state);
    }
}
