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
     * Expira o link de pagamento (preferência), para que não se abra um pagamento novo. Um boleto já
     * gerado não é cancelado: ele pode ter sido pago e ainda não ter compensado. Se compensar, o pedido
     * volta a valer (Orders::markPaid).
     */
    public function cancelCheckout(array $order): void
    {
        $preference = (string) ($order['gateway_reference'] ?? '');
        if ($preference === '' || !preg_match('/^[\w-]{1,80}$/', $preference)) {
            return;
        }
        try {
            $this->request('PUT', '/checkout/preferences/' . $preference, ['expires' => true, 'expiration_date_to' => date('Y-m-d\TH:i:s.000P')]);
        } catch (RuntimeException $e) {
            Logger::error('Mercado Pago: falha ao expirar o checkout do pedido ' . $order['number'] . ': ' . $e->getMessage());
        }
    }

    /**
     * Assinatura x-signature: "ts=...,v1=..." com HMAC-SHA256 de
     * "id:{data.id};request-id:{x-request-id};ts:{ts};" usando a chave secreta do webhook.
     * Sem chave configurada, aceita a notificação: o status é sempre conferido na API.
     */
    public function verifyWebhook(Request $request): bool
    {
        return $this->webhookProblem($request) === null;
    }

    /**
     * O que fazer com uma notificação: "process" (pagamento com assinatura válida), "ignore" (responde
     * 200 sem fazer nada) ou "reject" (401: assinatura que não confere, ou seja, chave errada).
     *
     * Para cada evento, o Mercado Pago dispara várias notificações ao mesmo tempo: o webhook assinado
     * (?data.id=...&type=payment), que é o que vale, e as do formato antigo, IPN (?id=...&topic=payment
     * e topic=merchant_order), sem a assinatura que a loja confere. Recusar estas faz o Mercado Pago
     * reenviá-las durante dias; ignorá-las não perde nada, porque o webhook assinado do mesmo evento
     * também chega. Ordens comerciais e outros tópicos não interessam à loja.
     * @return array{0: string, 1: ?string} [ação, motivo]
     */
    public function classifyWebhook(Request $request): array
    {
        $type = self::webhookType($request);
        if ($type !== 'payment') {
            return ['ignore', 'tópico ' . ($type !== '' ? $type : 'vazio')];
        }
        $problem = $this->webhookProblem($request);
        if ($problem === null) {
            return ['process', null];
        }
        return self::webhookDataId($request) === ''
            ? ['ignore', 'formato antigo (IPN), ' . $problem]
            : ['reject', $problem];
    }

    /** Tópico da notificação: "payment", "merchant_order", "topic_merchant_order_wh"... */
    public static function webhookType(Request $request): string
    {
        $body = $request->json() ?? [];
        return (string) ($request->query('type') ?: $request->query('topic') ?: ($body['type'] ?? $body['topic'] ?? ''));
    }

    /** data.id do webhook (na URL, que o PHP entrega como data_id, ou no corpo). Vazio no formato IPN. */
    public static function webhookDataId(Request $request): string
    {
        return (string) ($request->query('data_id') ?: ($request->json()['data']['id'] ?? ''));
    }

    /** Número do pagamento avisado, nos dois formatos. */
    public static function webhookPaymentId(Request $request): string
    {
        return self::webhookDataId($request) ?: (string) ($request->query('id') ?: '');
    }

    /** Motivo da recusa da assinatura (para o log), ou null quando a notificação é válida. */
    public function webhookProblem(Request $request): ?string
    {
        if ($this->webhookSecret === '') {
            return null;
        }
        $header = (string) $request->header('X-Signature');
        if ($header === '') {
            return 'sem x-signature';
        }
        $parts = [];
        foreach (explode(',', $header) as $piece) {
            [$k, $v] = array_pad(explode('=', trim($piece), 2), 2, '');
            $parts[$k] = $v;
        }
        if (empty($parts['ts']) || empty($parts['v1'])) {
            return 'x-signature sem ts ou v1';
        }
        $dataId = self::webhookDataId($request);
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
        if (hash_equals(hash_hmac('sha256', $manifest, $this->webhookSecret), $parts['v1'])) {
            return null;
        }
        return 'assinatura não confere' . ($dataId === '' ? ' (sem data.id)' : '');
    }

    /** A forma escolhida no checkout da loja define o que aparece no Mercado Pago. */
    private function excludedFor(string $method): array
    {
        $types = match ($method) {
            'pix' => ['credit_card', 'debit_card', 'prepaid_card', 'ticket', 'atm'],
            'boleto' => ['credit_card', 'debit_card', 'prepaid_card', 'bank_transfer'],
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
