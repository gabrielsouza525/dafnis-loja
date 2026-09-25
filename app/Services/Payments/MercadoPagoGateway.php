<?php
declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Logger;
use App\Core\Request;
use App\Services\Settings;
use RuntimeException;

/**
 * Mercado Pago — Checkout Pro (o cliente paga no ambiente do Mercado Pago;
 * dados de cartão nunca passam pela loja).
 *
 * Configuração (.env): MP_ACCESS_TOKEN, MP_WEBHOOK_SECRET e, para testes, MP_SANDBOX=true.
 * Docs: https://www.mercadopago.com.br/developers/pt/docs/checkout-pro
 */
final class MercadoPagoGateway implements PaymentGateway
{
    private const API = 'https://api.mercadopago.com';

    public function __construct(
        private readonly string $accessToken,
        private readonly string $webhookSecret,
        private readonly bool $sandbox,
    ) {
    }

    public function name(): string
    {
        return 'mercadopago';
    }

    public function isLive(): bool
    {
        return $this->accessToken !== '';
    }

    public function startCheckout(array $order, array $items): ?array
    {
        $https = str_starts_with((string) env('APP_URL', ''), 'https://');
        $back = absolute_url('/pedido/' . $order['number'] . '/retorno');

        // Com cupom, o total não bate com a soma dos itens: vai um item único com o valor do pedido.
        $discounted = abs(array_sum(array_column($items, 'line_total')) - (float) $order['total']) > 0.009;
        $mpItems = $discounted
            ? [[
                'id' => $order['number'],
                'title' => 'Pedido ' . $order['number'] . ' — ' . Settings::businessName(),
                'quantity' => 1,
                'currency_id' => 'BRL',
                'unit_price' => round((float) $order['total'], 2),
            ]]
            : array_map(static fn ($i) => [
                'id' => (string) ($i['course_id'] ?? $i['id']),
                'title' => mb_substr(($i['course_code'] ? $i['course_code'] . ' — ' : '') . $i['course_title'], 0, 250),
                'quantity' => (int) $i['quantity'],
                'currency_id' => 'BRL',
                'unit_price' => round((float) $i['unit_price'], 2),
            ], $items);

        $names = preg_split('/\s+/', trim($order['buyer_name']), 2) ?: [''];
        $payload = [
            'items' => $mpItems,
            'external_reference' => $order['number'],
            'payer' => [
                'name' => $names[0],
                'surname' => $names[1] ?? '',
                'email' => $order['buyer_email'],
                'identification' => [
                    'type' => strlen($order['buyer_document']) === 14 ? 'CNPJ' : 'CPF',
                    'number' => $order['buyer_document'],
                ],
            ],
            'back_urls' => ['success' => $back, 'pending' => $back, 'failure' => $back],
            'payment_methods' => [
                'excluded_payment_types' => $this->excludedFor((string) $order['payment_method']),
                'installments' => 12,
            ],
            'statement_descriptor' => mb_substr(strtoupper(preg_replace('/[^A-Za-z ]/', '', Settings::businessName()) ?? 'DAFNIS'), 0, 22),
            'expires' => true,
            'expiration_date_to' => date('Y-m-d\TH:i:s.000P', strtotime('+3 days')),
            'metadata' => ['order_number' => $order['number']],
        ];
        // O Mercado Pago só aceita notificação e retorno automático em endereço público HTTPS.
        if ($https) {
            $payload['notification_url'] = absolute_url('/webhooks/mercadopago');
            $payload['auto_return'] = 'approved';
        }

        $response = $this->request('POST', '/checkout/preferences', $payload);
        $url = $this->sandbox ? ($response['sandbox_init_point'] ?? null) : ($response['init_point'] ?? null);
        if (!$url || empty($response['id'])) {
            throw new RuntimeException('Resposta inesperada do Mercado Pago ao criar o checkout.');
        }
        return ['url' => $url, 'reference' => (string) $response['id']];
    }

    public function fetchPayment(string $paymentId): ?array
    {
        if (!preg_match('/^\d{1,20}$/', $paymentId)) {
            return null;
        }
        try {
            $p = $this->request('GET', '/v1/payments/' . $paymentId);
        } catch (RuntimeException $e) {
            Logger::error('Mercado Pago: falha ao consultar pagamento ' . $paymentId . ': ' . $e->getMessage());
            return null;
        }
        return [
            'id' => (string) ($p['id'] ?? $paymentId),
            'status' => (string) ($p['status'] ?? 'unknown'),
            'order_number' => isset($p['external_reference']) ? (string) $p['external_reference'] : null,
            'amount' => (float) ($p['transaction_amount'] ?? 0),
            'currency' => (string) ($p['currency_id'] ?? ''),
            'method' => $p['payment_type_id'] ?? null,
        ];
    }

    /**
     * Assinatura x-signature: "ts=...,v1=..." com HMAC-SHA256 de
     * "id:{data.id};request-id:{x-request-id};ts:{ts};" usando a chave secreta do webhook.
     * Sem chave configurada, aceita a notificação: o status é sempre conferido na API.
     */
    public function verifyWebhook(Request $request): bool
    {
        if ($this->webhookSecret === '') {
            return true;
        }
        $header = (string) $request->header('X-Signature');
        $parts = [];
        foreach (explode(',', $header) as $piece) {
            [$k, $v] = array_pad(explode('=', trim($piece), 2), 2, '');
            $parts[$k] = $v;
        }
        if (empty($parts['ts']) || empty($parts['v1'])) {
            return false;
        }
        $dataId = (string) ($request->query('data_id') ?? ($request->json()['data']['id'] ?? ''));
        if (ctype_alnum($dataId)) {
            $dataId = strtolower($dataId);
        }
        $manifest = '';
        if ($dataId !== '') {
            $manifest .= 'id:' . $dataId . ';';
        }
        if ($requestId = $request->header('X-Request-Id')) {
            $manifest .= 'request-id:' . $requestId . ';';
        }
        $manifest .= 'ts:' . $parts['ts'] . ';';
        return hash_equals(hash_hmac('sha256', $manifest, $this->webhookSecret), $parts['v1']);
    }

    /** A forma escolhida no checkout da loja define o que aparece no Mercado Pago. */
    private function excludedFor(string $method): array
    {
        $types = match ($method) {
            'pix' => ['credit_card', 'debit_card', 'ticket', 'atm'],
            'boleto' => ['credit_card', 'debit_card', 'bank_transfer'],
            'card' => ['ticket', 'atm', 'bank_transfer'],
            default => [],
        };
        return array_map(static fn ($t) => ['id' => $t], $types);
    }

    private function request(string $method, string $path, ?array $body = null, array $headers = []): array
    {
        $ch = curl_init(self::API . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER => array_merge([
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json',
                'Accept: application/json',
            ], $headers),
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new RuntimeException('Sem resposta do Mercado Pago: ' . $error);
        }
        $data = json_decode((string) $raw, true);
        if ($status >= 400 || !is_array($data)) {
            $message = is_array($data) ? ($data['message'] ?? $data['error'] ?? 'erro') : 'resposta inválida';
            throw new RuntimeException("Mercado Pago respondeu $status: $message");
        }
        return $data;
    }

    /** Rótulo amigável do status do Mercado Pago (tela do pedido e admin). */
    public static function statusLabel(?string $status): ?string
    {
        return match ($status) {
            'approved' => 'Aprovado',
            'pending', 'in_process', 'authorized' => 'Em análise',
            'in_mediation' => 'Em mediação',
            'rejected' => 'Recusado',
            'cancelled' => 'Cancelado',
            'refunded' => 'Estornado',
            'charged_back' => 'Contestado',
            null, '' => null,
            default => $status,
        };
    }
}
