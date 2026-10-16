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

        [$action, $reason] = $gateway->classifyWebhook($this->request);
        $type = MercadoPagoGateway::webhookType($this->request);
        $id = MercadoPagoGateway::webhookPaymentId($this->request);

        if ($action !== 'process') {
            // Só o necessário para entender a notificação: nada de dados pessoais nem da chave.
            $body = $this->request->json() ?? [];
            $context = [
                'motivo' => $reason,
                'id' => mb_substr($id, 0, 40),
                'query' => array_keys($this->request->queryAll()),
                'corpo' => array_keys($body),
                'acao' => $body['action'] ?? null,
                'request_id' => $this->request->header('X-Request-Id') !== null,
                'ip' => $this->request->ip(),
            ];
            if ($action === 'reject') {
                Logger::error('Webhook Mercado Pago com assinatura inválida', $context);
                return $this->json(['ok' => false], 401);
            }
            if ($type === 'payment') {
                Logger::info('Webhook Mercado Pago ignorado', $context);
            }
            return $this->json(['ok' => true, 'ignored' => true]);
        }

        if ($id !== '') {
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
