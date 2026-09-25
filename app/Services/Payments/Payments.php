<?php
declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Database;
use App\Core\Logger;
use App\Models\Order;
use App\Services\Activity;
use App\Services\Orders;
use Throwable;

/**
 * Ponto único de acesso ao gateway e à conciliação dos pagamentos.
 *
 *   PAYMENT_GATEWAY=mercadopago + MP_ACCESS_TOKEN  → cobra online pelo Mercado Pago
 *   sem token (ou PAYMENT_GATEWAY=manual)          → pedido aguarda baixa manual no admin
 */
final class Payments
{
    private static ?PaymentGateway $gateway = null;

    public static function gateway(): PaymentGateway
    {
        if (self::$gateway === null) {
            $choice = (string) env('PAYMENT_GATEWAY', 'mercadopago');
            $token = trim((string) env('MP_ACCESS_TOKEN', ''));
            self::$gateway = ($choice === 'mercadopago' && $token !== '')
                ? new MercadoPagoGateway($token, trim((string) env('MP_WEBHOOK_SECRET', '')), (bool) env('MP_SANDBOX', false))
                : new ManualGateway();
        }
        return self::$gateway;
    }

    public static function isOnline(): bool
    {
        return self::gateway()->isLive();
    }

    /** Link de pagamento do pedido (reaproveita o checkout ainda válido). */
    public static function checkoutUrl(array $order): ?string
    {
        $gateway = self::gateway();
        if ($order['status'] !== 'pending' || !$gateway->isLive()) {
            return null;
        }
        $fresh = $order['checkout_url'] && $order['gateway'] === $gateway->name()
            && strtotime((string) $order['updated_at']) > strtotime('-2 days');
        if ($fresh) {
            return $order['checkout_url'];
        }
        try {
            $session = $gateway->startCheckout($order, Order::items((int) $order['id']));
        } catch (Throwable $e) {
            Logger::error('Falha ao criar checkout do pedido ' . $order['number'] . ': ' . $e->getMessage());
            return null;
        }
        if (!$session) {
            return null;
        }
        Database::update('orders', [
            'gateway' => $gateway->name(),
            'gateway_reference' => $session['reference'],
            'checkout_url' => $session['url'],
        ], ['id' => $order['id']]);
        return $session['url'];
    }

    /**
     * Aplica ao pedido o status de um pagamento consultado na API do gateway.
     * Valor e moeda precisam bater com o pedido; qualquer divergência vai para o log.
     */
    public static function reconcile(string $paymentId, string $source): ?array
    {
        $gateway = self::gateway();
        $payment = $gateway->fetchPayment($paymentId);
        if (!$payment || !$payment['order_number']) {
            return null;
        }
        $order = Order::findByNumber($payment['order_number']);
        self::logEvent($order['id'] ?? null, $gateway->name(), $source, $payment['id'], $payment['status'], $payment);
        if (!$order) {
            Logger::error('Pagamento sem pedido correspondente', ['payment' => $payment]);
            return null;
        }

        $fields = ['gateway' => $gateway->name(), 'gateway_payment_id' => $payment['id'], 'gateway_status' => $payment['status']];
        if ($payment['status'] === 'approved') {
            if (abs($payment['amount'] - (float) $order['total']) > 0.01 || strtoupper($payment['currency']) !== 'BRL') {
                Logger::error('Pagamento aprovado com valor divergente — conferir manualmente', ['order' => $order['number'], 'payment' => $payment]);
                Activity::log('payment.mismatch', 'order', (int) $order['id'], 'Valor pago ' . money($payment['amount']) . ' ≠ total ' . money($order['total']));
                Database::update('orders', $fields, ['id' => $order['id']]);
            } else {
                Orders::markPaid((int) $order['id'], 'gateway', $fields);
            }
        } elseif (in_array($payment['status'], ['refunded', 'charged_back'], true)) {
            Database::update('orders', $fields, ['id' => $order['id']]);
            Orders::refund((int) $order['id'], 'Pagamento ' . $payment['status'] . ' no gateway');
        } elseif ($order['status'] === 'pending') {
            Database::update('orders', $fields, ['id' => $order['id']]);
        }
        return Order::find((int) $order['id']);
    }

    public static function logEvent(?int $orderId, string $gateway, string $event, ?string $reference, ?string $status, mixed $payload): void
    {
        try {
            Database::insert('payment_events', [
                'order_id' => $orderId,
                'gateway' => $gateway,
                'event' => mb_substr($event, 0, 60),
                'reference' => $reference,
                'status' => $status,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable $e) {
            Logger::error('Falha ao registrar evento de pagamento: ' . $e->getMessage());
        }
    }
}
