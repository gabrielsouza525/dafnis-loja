<?php
/**
 * @var array $order @var array $items @var int $awaiting @var bool $online @var bool $autoPay
 * @var string|null $gatewayStatus @var string|null $whatsapp @var string|null $contactEmail
 */
use App\Models\Order;

$status = $order['status'];
$method = Order::METHODS[$order['payment_method']]['label'] ?? $order['payment_method'];
$step = $status === 'paid' ? 5 : 3;
?>
<div class="screen">
<section class="phead">
<div class="wrap">
<?= partial('crumbs', ['items' => [['Início', '/'], ['Minha conta', '/minha-conta'], ['Pedido ' . $order['number'], null]]]) ?>
<div class="phead-row"><div><h1>Pedido <?= e($order['number']) ?></h1>
<?= partial('steps', ['current' => $step]) ?>
</div></div>
</div>
</section>
<div class="wrap">
<div class="success">
<?php if ($status === 'paid'): ?>
<span class="success-ic"><?= icon('check') ?></span>
<div class="kicker">Pagamento confirmado</div>
<h1 style="margin-top:10px">Tudo certo com o seu pedido</h1>
<p><?php if ($awaiting > 0): ?>Agora indique quem vai fazer cada treinamento: <?= e(pluralize($awaiting, 'vaga aguarda', 'vagas aguardam')) ?> o nome e o e-mail do participante. Assim que você informar, liberamos o acesso na plataforma de ensino.<?php else: ?>Estamos liberando o acesso na plataforma de ensino. O participante recebe o link por e-mail e o curso aparece em Minha conta › Meus cursos.<?php endif; ?></p>
<div class="btns">
<?php if ($awaiting > 0): ?>
<a class="btn btn-buy btn-lg" href="<?= e(url('/minha-conta/pedidos/' . $order['number'])) ?>">Indicar participantes<?= icon('arrowR') ?></a>
<?php else: ?>
<a class="btn btn-primary btn-lg" href="<?= e(url('/minha-conta/cursos')) ?>">Ir para Meus cursos<?= icon('arrowR') ?></a>
<?php endif; ?>
<a class="btn btn-outline btn-lg" href="<?= e(url('/cursos')) ?>">Explorar mais cursos</a>
</div>

<?php elseif ($status === 'pending' && $online): ?>
<span class="success-ic wait"><?= icon($order['gateway_status'] === 'rejected' ? 'alert' : 'clock') ?></span>
<div class="kicker">Pedido registrado · aguardando pagamento</div>
<h1 style="margin-top:10px"><?= $order['gateway_status'] === 'rejected' ? 'O pagamento não foi aprovado' : (in_array($order['gateway_status'], ['pending', 'in_process', 'authorized'], true) ? 'Pagamento em processamento' : 'Falta só o pagamento') ?></h1>
<p><?php if (in_array($order['gateway_status'], ['pending', 'in_process', 'authorized'], true)): ?>O Mercado Pago está processando o pagamento<?= $order['payment_method'] === 'boleto' ? ' (boleto compensa em até 3 dias úteis)' : '' ?>. Avisaremos por e-mail assim que for confirmado.<?php elseif ($order['gateway_status'] === 'rejected'): ?>Nenhum valor foi cobrado. Você pode tentar novamente, inclusive com outra forma de pagamento.<?php else: ?>Conclua o pagamento no ambiente seguro do Mercado Pago. O pedido fica reservado por 3 dias.<?php endif; ?></p>
<div class="btns">
<a class="btn btn-buy btn-lg" href="<?= e(url('/pedido/' . $order['number'] . '/pagar')) ?>" data-autopay="<?= $autoPay ? '1' : '0' ?>"><?= icon('lock') ?><?= $order['gateway_status'] === 'rejected' ? 'Tentar pagar de novo' : 'Pagar com Mercado Pago' ?></a>
<a class="btn btn-outline btn-lg" href="<?= e(url('/minha-conta/pedidos')) ?>">Ver meus pedidos</a>
</div>
<?php if ($autoPay): ?><p class="hint" data-autopay-note>Abrindo o Mercado Pago…</p><?php endif; ?>

<?php elseif ($status === 'pending'): ?>
<span class="success-ic wait"><?= icon('clock') ?></span>
<div class="kicker">Pedido registrado · aguardando pagamento</div>
<h1 style="margin-top:10px">Recebemos o seu pedido</h1>
<p>O pagamento online está em ativação, então nenhuma cobrança foi feita. Nossa equipe vai enviar as instruções para pagamento via <strong><?= e($method) ?></strong> para <?= e($order['buyer_email']) ?>. Assim que o pagamento for confirmado, liberamos o acesso.</p>
<div class="btns">
<?php if ($whatsapp): ?><a class="btn btn-buy btn-lg" href="<?= e(wa_link($whatsapp, 'Olá! Acabei de fazer o pedido ' . $order['number'] . ' na loja da Dafnis e gostaria de receber as instruções de pagamento.')) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?>Falar no WhatsApp</a><?php endif; ?>
<a class="btn <?= $whatsapp ? 'btn-outline' : 'btn-primary' ?> btn-lg" href="<?= e(url('/minha-conta/pedidos')) ?>">Ver meus pedidos</a>
</div>

<?php else: ?>
<span class="success-ic err"><?= icon('close') ?></span>
<div class="kicker"><?= e(Order::statusLabel($status)) ?></div>
<h1 style="margin-top:10px"><?= $status === 'refunded' ? 'Pagamento estornado' : 'Pedido cancelado' ?></h1>
<p>Este pedido não está mais ativo. Se precisar, monte um novo pedido pelo catálogo ou fale com a nossa equipe.</p>
<div class="btns"><a class="btn btn-primary btn-lg" href="<?= e(url('/cursos')) ?>">Explorar cursos</a><a class="btn btn-outline btn-lg" href="<?= e(url('/contato')) ?>">Falar com a equipe</a></div>
<?php endif; ?>

<div class="order-box">
<div class="order-box-head"><span>Pedido <strong><?= e($order['number']) ?></strong> · <?= e(date_br($order['created_at'], true)) ?></span><?= partial('status', ['label' => Order::statusLabel($status), 'tone' => Order::STATUS_TONE[$status] ?? 'muted']) ?></div>
<div class="order-box-body">
<div class="sum-items" style="padding-top:12px">
<?php foreach ($items as $i): ?>
<div class="sum-item"><span><?= e(($i['course_code'] ? $i['course_code'] . ' — ' : '') . $i['course_title']) ?><br><small><?= (int) $i['quantity'] ?> × <?= money($i['unit_price']) ?></small></span><strong><?= money($i['line_total']) ?></strong></div>
<?php endforeach; ?>
</div>
<div class="sum-row"><span>Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
<div class="sum-row disc"><span>Desconto<?= $order['coupon_code'] ? ' (cupom ' . e($order['coupon_code']) . ')' : '' ?></span><span><?= (float) $order['discount'] > 0 ? '− ' . money($order['discount']) : 'R$ 0,00' ?></span></div>
<div class="sum-total"><span>Total</span><strong><?= money($order['total']) ?></strong></div>
<p class="hint">Forma de pagamento escolhida: <?= e($method) ?><?= $gatewayStatus ? ' · Mercado Pago: ' . e($gatewayStatus) : '' ?></p>
</div>
</div>
</div>
</div>
</div>
