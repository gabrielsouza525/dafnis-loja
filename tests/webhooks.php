<?php
declare(strict_types=1);

/**
 * Notificações do Mercado Pago: o que é processado, ignorado (200) ou recusado (401).
 * Não precisa do servidor nem do banco.
 *
 *   php tests/webhooks.php
 */

require dirname(__DIR__) . '/app/bootstrap.php';
restore_exception_handler();

use App\Core\Request;
use App\Services\Payments\MercadoPagoGateway;

$failures = 0;
$checks = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures, $checks;
    $checks++;
    if (!$ok) {
        $failures++;
    }
    echo ($ok ? '  ok   ' : '  FAIL ') . $label . ($detail !== '' && !$ok ? "  → $detail" : '') . PHP_EOL;
}

/** Notificação como o PHP a recebe ("data.id" na URL vira data_id em $_GET). */
function notification(array $query, array $headers = []): Request
{
    $_GET = $query;
    $_POST = [];
    $_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/webhooks/mercadopago', 'REMOTE_ADDR' => '127.0.0.1'];
    foreach ($headers as $name => $value) {
        $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
    }
    return Request::capture();
}

function signature(string $secret, string $dataId, string $requestId, string $ts): string
{
    return 'ts=' . $ts . ',v1=' . hash_hmac('sha256', "id:$dataId;request-id:$requestId;ts:$ts;", $secret);
}

$secret = 'segredo-de-teste';
$mp = new MercadoPagoGateway('token-de-teste', $secret, true);
$signed = static fn (string $dataId, string $type = 'payment', ?string $sig = null) => notification(
    ['data_id' => $dataId, 'type' => $type],
    ['X-Request-Id' => 'req-1', 'X-Signature' => $sig ?? signature($secret, strtolower($dataId), 'req-1', '1700000000')]
);

echo "Webhooks do Mercado Pago\n";

[$action] = $mp->classifyWebhook($signed('123456'));
check('webhook de pagamento assinado é processado', $action === 'process', $action);

[$action, $reason] = $mp->classifyWebhook($signed('123456', 'payment', signature('outra-chave', '123456', 'req-1', '1700000000')));
check('assinatura com outra chave é recusada (401)', $action === 'reject' && $reason === 'assinatura não confere', "$action: $reason");

[$action, $reason] = $mp->classifyWebhook(notification(['data_id' => '123456', 'type' => 'payment']));
check('webhook de pagamento sem assinatura é recusado', $action === 'reject' && $reason === 'sem x-signature', "$action: $reason");

[$action] = $mp->classifyWebhook($signed('ABC123'));
check('data.id alfanumérico entra na assinatura em minúsculas', $action === 'process', $action);

$ipn = notification(['id' => '123456', 'topic' => 'payment']);
[$action, $reason] = $mp->classifyWebhook($ipn);
check('IPN de pagamento (formato antigo) é ignorado com 200', $action === 'ignore' && str_starts_with((string) $reason, 'formato antigo (IPN)'), "$action: $reason");
check('o número do pagamento é lido nos dois formatos', MercadoPagoGateway::webhookPaymentId($ipn) === '123456' && MercadoPagoGateway::webhookPaymentId($signed('987')) === '987');

[$action, $reason] = $mp->classifyWebhook(notification(['id' => '999', 'topic' => 'merchant_order']));
check('IPN de ordem comercial é ignorado', $action === 'ignore' && $reason === 'tópico merchant_order', "$action: $reason");

[$action, $reason] = $mp->classifyWebhook($signed('999', 'topic_merchant_order_wh', 'ts=1,v1=errada'));
check('webhook de ordem comercial é ignorado, mesmo com assinatura que não confere', $action === 'ignore' && $reason === 'tópico topic_merchant_order_wh', "$action: $reason");

[$action] = (new MercadoPagoGateway('token-de-teste', '', true))->classifyWebhook(notification(['data_id' => '123456', 'type' => 'payment']));
check('sem chave configurada, o pagamento é processado (o status vem da API)', $action === 'process', $action);

echo PHP_EOL . ($failures === 0 ? "Tudo certo: $checks verificações." : "$failures de $checks verificações falharam.") . PHP_EOL;
exit($failures === 0 ? 0 : 1);
