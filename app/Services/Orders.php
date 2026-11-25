<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\ValidationException;
use App\Models\Coupon;
use App\Models\Order;
use App\Services\Payments\Payments;

/**
 * Ciclo de vida do pedido: criação a partir do carrinho, confirmação de pagamento
 * (gateway ou baixa manual pela equipe), cancelamento e estorno.
 */
final class Orders
{
    /**
     * Cria o pedido com os preços atuais do catálogo (nunca os da sessão).
     * @param array $buyer buyer_type, buyer_name, buyer_document, buyer_email, buyer_phone, company_name
     */
    public static function createFromCart(array $buyer, int $userId, string $paymentMethod, string $ip): array
    {
        $lines = Cart::lines();
        if (!$lines) {
            throw ValidationException::with('cart', 'Seu carrinho está vazio.');
        }
        $totals = Cart::totals($lines);
        if ($totals['coupon_error']) {
            throw ValidationException::with('coupon', $totals['coupon_error'] . ' Remova o cupom no carrinho para continuar.');
        }

        $order = Database::transaction(static function () use ($lines, $totals, $buyer, $userId, $paymentMethod, $ip) {
            $couponId = null;
            $couponCode = null;
            $couponDiscount = 0.0;
            if ($totals['coupon_code']) {
                // Trava a linha do cupom: dois pedidos simultâneos não estouram o limite de usos.
                $coupon = Coupon::findByCode($totals['coupon_code'], true);
                $problem = Coupon::problem($coupon, $totals['items_total']);
                if ($problem !== null) {
                    throw ValidationException::with('coupon', $problem . ' Remova o cupom no carrinho para continuar.');
                }
                $couponId = (int) $coupon['id'];
                $couponCode = $coupon['code'];
                $couponDiscount = Coupon::discountFor($coupon, $totals['items_total']);
                Database::query('UPDATE coupons SET uses = uses + 1 WHERE id = :id', ['id' => $couponId]);
            }

            $total = round($totals['items_total'] - $couponDiscount, 2);
            $orderId = Database::insert('orders', [
                'user_id' => $userId,
                'status' => 'pending',
                'buyer_type' => $buyer['buyer_type'],
                'buyer_name' => $buyer['buyer_name'],
                'buyer_document' => $buyer['buyer_document'],
                'buyer_email' => mb_strtolower($buyer['buyer_email']),
                'buyer_phone' => $buyer['buyer_phone'],
                'company_name' => $buyer['company_name'] ?? null,
                'subtotal' => $totals['subtotal'],
                'discount' => round($totals['offers'] + $couponDiscount, 2),
                'total' => $total,
                'coupon_id' => $couponId,
                'coupon_code' => $couponCode,
                'payment_method' => $paymentMethod,
                'gateway' => $total > 0 && $paymentMethod !== 'invoice' ? Payments::gateway()->name() : 'manual',
                'terms_accepted_at' => date('Y-m-d H:i:s'),
                'ip' => $ip,
            ]);
            $number = self::numberFor($orderId);
            Database::update('orders', ['number' => $number], ['id' => $orderId]);

            foreach ($lines as $line) {
                $c = $line['course'];
                Database::insert('order_items', [
                    'order_id' => $orderId,
                    'course_id' => $c['id'],
                    'course_title' => $c['title'],
                    'course_code' => $c['nr_number'] && !$c['is_simulator'] ? $c['code_label'] : null,
                    'course_hours' => $c['hours'],
                    'list_price' => $c['list_price'],
                    'unit_price' => $line['unit_price'],
                    'quantity' => $line['qty'],
                    'line_total' => $line['line_total'],
                ]);
            }
            Activity::log('order.created', 'order', $orderId, $number . ' — ' . money($total));
            return Order::find($orderId);
        });

        // Cupom que cobre o valor inteiro: não há o que cobrar (o Mercado Pago recusa cobrança
        // de R$ 0), então o pedido já sai pago e o e-mail de confirmação substitui o de "recebido".
        if ((float) $order['total'] <= 0) {
            self::markPaid((int) $order['id'], 'coupon');
            return Order::find((int) $order['id']);
        }
        if ($paymentMethod === 'invoice') {
            self::markInvoiced((int) $order['id']);
            return Order::find((int) $order['id']);
        }
        Notify::orderCreated($order);
        return $order;
    }

    /**
     * Fatura mensal: o pedido vale na hora (vagas criadas e acesso liberado) e o pagamento vem no
     * fechamento do mês, pela página Faturamento do painel (Orders::markPaid com a origem "invoice").
     */
    private static function markInvoiced(int $orderId): void
    {
        $changed = Database::transaction(static function () use ($orderId) {
            $order = Database::first('SELECT * FROM orders WHERE id = :id FOR UPDATE', ['id' => $orderId]);
            if (!$order || $order['status'] !== 'pending') {
                return false;
            }
            Database::update('orders', ['status' => 'invoiced', 'invoiced_at' => date('Y-m-d H:i:s')], ['id' => $orderId]);
            self::createEnrollments($order);
            return true;
        });
        if ($changed) {
            Enrollments::autoReleaseOrder($orderId);
            Activity::log('order.invoiced', 'order', $orderId, 'Pedido faturado (fatura mensal)');
            Notify::orderPaid(Order::find($orderId));
        }
    }

    public static function numberFor(int $id): string
    {
        return 'DF' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Confirma o pagamento e cria as vagas. Idempotente: chamar duas vezes
     * (webhook + retorno do checkout) não duplica nada.
     * @param string $source "gateway", "admin", "coupon" (pedido de valor zero) ou "invoice"
     *                       (fatura mensal paga: as vagas já existiam e o cliente não recebe outro e-mail)
     */
    public static function markPaid(int $orderId, string $source, array $gatewayFields = []): bool
    {
        $wasInvoiced = false;
        $changed = Database::transaction(static function () use ($orderId, $gatewayFields, $source, &$wasInvoiced) {
            $order = Database::first('SELECT * FROM orders WHERE id = :id FOR UPDATE', ['id' => $orderId]);
            if (!$order || in_array($order['status'], ['paid', 'refunded'], true)) {
                return false;
            }
            // A baixa da fatura só vale para pedido faturado (um pedido cancelado não volta por ela).
            if ($source === 'invoice' && $order['status'] !== 'invoiced') {
                return false;
            }
            $wasInvoiced = $order['status'] === 'invoiced';
            Database::update('orders', array_merge($gatewayFields, [
                'status' => 'paid',
                'paid_at' => date('Y-m-d H:i:s'),
                'cancelled_at' => null,
            ]), ['id' => $orderId]);
            if (!$wasInvoiced) {
                self::createEnrollments($order);
            }
            return true;
        });

        if ($changed) {
            $order = Order::find($orderId);
            Activity::log('order.paid', 'order', $orderId, 'Pagamento confirmado (' . match ($source) {
                'admin' => $wasInvoiced ? 'baixa manual da fatura' : 'baixa manual',
                'coupon' => 'cupom cobriu o valor total',
                'invoice' => 'fatura mensal paga',
                default => 'gateway',
            } . ')');
            if (!$wasInvoiced) {
                Enrollments::autoReleaseOrder($orderId);
                Notify::orderPaid($order);
            }
        }
        return $changed;
    }

    /** Uma matrícula por vaga. Compra de pessoa física com 1 vaga já vai no nome do comprador. */
    private static function createEnrollments(array $order): void
    {
        if ((int) Database::value('SELECT COUNT(*) FROM enrollments WHERE order_id = :o', ['o' => $order['id']]) > 0) {
            // Pedido reaberto depois de cancelado: as vagas voltam a valer.
            Database::query("UPDATE enrollments SET status = IF(participant_email IS NULL, 'awaiting_participant', 'processing') WHERE order_id = :o AND status = 'cancelled'", ['o' => $order['id']]);
            return;
        }
        foreach (Database::select('SELECT * FROM order_items WHERE order_id = :o', ['o' => $order['id']]) as $item) {
            $selfAssign = (int) $item['quantity'] === 1 && $order['buyer_type'] === 'pf';
            for ($i = 0; $i < (int) $item['quantity']; $i++) {
                Database::insert('enrollments', [
                    'order_id' => $order['id'],
                    'order_item_id' => $item['id'],
                    'course_id' => $item['course_id'],
                    'buyer_user_id' => $order['user_id'],
                    'participant_name' => $selfAssign ? $order['buyer_name'] : null,
                    'participant_email' => $selfAssign ? mb_strtolower($order['buyer_email']) : null,
                    'participant_document' => $selfAssign ? $order['buyer_document'] : null,
                    'status' => $selfAssign ? 'processing' : 'awaiting_participant',
                ]);
            }
        }
    }

    /**
     * Cancela o pedido aguardando pagamento. Com $invoiced, a equipe também cancela um pedido
     * faturado ainda não pago (sai da fatura, e as vagas, já liberadas, são canceladas).
     */
    public static function cancel(int $orderId, string $reason, bool $invoiced = false): bool
    {
        $changed = Database::transaction(static function () use ($orderId, $invoiced) {
            $order = Database::first('SELECT * FROM orders WHERE id = :id FOR UPDATE', ['id' => $orderId]);
            if (!$order || !($order['status'] === 'pending' || ($invoiced && $order['status'] === 'invoiced'))) {
                return false;
            }
            Database::update('orders', ['status' => 'cancelled', 'cancelled_at' => date('Y-m-d H:i:s')], ['id' => $orderId]);
            if ($order['status'] === 'invoiced') {
                Database::query("UPDATE enrollments SET status = 'cancelled' WHERE order_id = :o", ['o' => $orderId]);
            }
            if ($order['coupon_id']) {
                Database::query('UPDATE coupons SET uses = GREATEST(uses - 1, 0) WHERE id = :id', ['id' => $order['coupon_id']]);
            }
            return true;
        });
        if ($changed) {
            Activity::log('order.cancelled', 'order', $orderId, $reason);
        }
        return $changed;
    }

    /** Pagamento estornado/contestado no gateway: pedido estornado e vagas canceladas. */
    public static function refund(int $orderId, string $reason): bool
    {
        $changed = Database::transaction(static function () use ($orderId) {
            $order = Database::first('SELECT * FROM orders WHERE id = :id FOR UPDATE', ['id' => $orderId]);
            if (!$order || $order['status'] !== 'paid') {
                return false;
            }
            Database::update('orders', ['status' => 'refunded', 'cancelled_at' => date('Y-m-d H:i:s')], ['id' => $orderId]);
            Database::query("UPDATE enrollments SET status = 'cancelled' WHERE order_id = :o", ['o' => $orderId]);
            return true;
        });
        if ($changed) {
            Activity::log('order.refunded', 'order', $orderId, $reason);
        }
        return $changed;
    }
}
