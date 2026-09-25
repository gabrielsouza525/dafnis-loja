<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Logger;
use App\Core\Response;
use App\Services\Payments\MercadoPagoGateway;
use App\Services\Payments\Payments;
use Throwable;

/**
 * Notificações do Mercado Pago (configure a URL https://SEU-DOMINIO/webhooks/mercadopago
 * no painel do Mercado Pago, evento "Pagamentos"). O corpo da notificação só diz
 * "o pagamento X mudou": o status real vem sempre de uma consulta à API.
 */
final class WebhookController extends Controller
{
    public function mercadoPago(): Response
    {
        $gateway = Payments::gateway();
        if (!$gateway instanceof MercadoPagoGateway) {
            return $this->json(['ok' => true, 'ignored' => 'gateway desativado']);
        }
        if (!$gateway->verifyWebhook($this->request)) {
            Logger::error('Webhook Mercado Pago com assinatura inválida', ['ip' => $this->request->ip()]);
            return $this->json(['ok' => false], 401);
        }

        $body = $this->request->json() ?? [];
        $type = (string) ($this->request->query('type') ?: $this->request->query('topic') ?: ($body['type'] ?? ''));
        $id = (string) ($this->request->query('data_id') ?: ($body['data']['id'] ?? '') ?: $this->request->query('id') ?: '');

        if ($type === 'payment' && $id !== '') {
            try {
                Payments::reconcile($id, 'webhook');
            } catch (Throwable $e) {
                Logger::exception($e, $this->request);
                // 500 faz o Mercado Pago reenviar a notificação mais tarde.
                return $this->json(['ok' => false], 500);
            }
        }
        return $this->json(['ok' => true]);
    }
}
