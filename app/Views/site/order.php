<?php
/**
 * Pedido depois do checkout: situação, próximos passos e o resumo.
 * @var array $order @var array $items (+ course) @var int $awaiting @var array $ready vagas do comprador já liberadas (curso na loja) @var bool $needsParticipants @var bool $online @var bool $autoPay
 * @var string|null $gatewayStatus @var string|null $whatsapp @var string|null $contactEmail
 */
use App\Models\Order;

$status = $order['status'];
$gw = (string) $order['gateway_status'];
$method = Order::METHODS[$order['payment_method']] ?? ['label' => $order['payment_method'], 'icon' => 'card'];
$step = $status === 'paid' ? 5 : 3;
$processing = in_array($gw, ['pending', 'in_process', 'authorized'], true);

// Próximos passos: [título, texto, done|on|todo]
$next = [];
if ($status === 'paid') {
    $next = [
        ['Pagamento confirmado', $order['paid_at'] ? 'Recebido em ' . date_br($order['paid_at'], true) . '.' : 'Recebemos o pagamento.', 'done'],
        ['Indicar os participantes', $awaiting > 0 ? ucfirst(pluralize($awaiting, 'vaga aguarda', 'vagas aguardam')) . ' o nome, o e-mail e o CPF de quem vai fazer o curso.' : 'Os participantes já estão definidos.', $awaiting > 0 ? 'on' : 'done'],
        $ready
            ? ['Acesso liberado', 'O curso já está em Minha conta › Meus cursos e é feito aqui no site.', 'done']
            : ['Acesso à plataforma de ensino', 'Cada participante recebe o link por e-mail, e o curso aparece em Minha conta › Meus cursos.', $awaiting > 0 ? 'todo' : 'on'],
        ['Certificado', 'Ao concluir o curso e cumprir os critérios de aprovação, o certificado fica em Minha conta.', 'todo'],
    ];
} elseif ($status === 'pending') {
    $next = [
        ['Pedido registrado', 'Feito em ' . date_br($order['created_at'], true) . '.', 'done'],
        [$online ? 'Pagamento pelo Mercado Pago' : 'Instruções de pagamento', $online ? ($processing ? 'O pagamento está em processamento. Avisamos por e-mail quando for confirmado.' : 'Conclua o pagamento no ambiente seguro do Mercado Pago.') : 'A nossa equipe envia as instruções para ' . $order['buyer_email'] . '.', 'on'],
        $needsParticipants
            ? ['Indicar os participantes', 'Com o pagamento confirmado, você informa quem vai fazer cada treinamento.', 'todo']
            : ['Acesso liberado para você', 'Com o pagamento confirmado, o acesso vai para o seu e-mail.', 'todo'],
        ['Certificado', 'Ao concluir o curso e cumprir os critérios de aprovação, o certificado fica em Minha conta.', 'todo'],
    ];
}
?>
<div class="screen">
<section class="phead phead-dark">
<div class="grid-bg" aria-hidden="true"></div>
<div class="wrap">
<?= partial('crumbs', ['items' => [['Início', '/'], ['Minha conta', '/minha-conta'], ['Pedido ' . $order['number'], null]]]) ?>
<div class="phead-row"><div><h1>Pedido <?= e($order['number']) ?></h1>
<?= partial('steps', ['current' => $step]) ?>
</div></div>
</div>
</section>
<div class="wrap">
<div class="cart-layout order-layout">
<div class="order-main">
<div class="form-card order-status is-<?= e($status) ?>">
<?php if ($status === 'paid'): ?>
<span class="success-ic"><?= icon('check') ?></span>
<div><div class="kicker">Pagamento confirmado</div>
<h2><?= $ready ? 'Seu acesso já está liberado' : 'Tudo certo com o seu pedido' ?></h2>
<p><?php if ($ready): ?>O curso é feito aqui no nosso site e já está em Minha conta › Meus cursos. Pode começar agora mesmo.<?= $awaiting > 0 ? ' Falta indicar quem vai fazer ' . e(pluralize($awaiting, 'vaga', 'vagas')) . '.' : '' ?><?php elseif ($awaiting > 0): ?>Agora indique quem vai fazer cada treinamento: <?= e(pluralize($awaiting, 'vaga aguarda', 'vagas aguardam')) ?> o participante. Assim que você informar, liberamos o acesso na plataforma de ensino.<?php else: ?>Estamos liberando o acesso na plataforma de ensino. O participante recebe o link por e-mail e o curso aparece em Minha conta › Meus cursos.<?php endif; ?></p>
<div class="btns">
<?php if ($ready): ?>
<a class="btn btn-buy btn-lg" href="<?= e(url(App\Models\Enrollment::studyPath($ready[0]))) ?>">Começar o curso<?= icon('arrowR') ?></a>
<?php if ($awaiting > 0): ?><a class="btn btn-outline btn-lg" href="<?= e(url('/minha-conta/pedidos/' . $order['number'])) ?>#vagas">Indicar participantes</a><?php endif; ?>
<?php elseif ($awaiting > 0): ?>
<a class="btn btn-buy btn-lg" href="<?= e(url('/minha-conta/pedidos/' . $order['number'])) ?>#vagas">Indicar participantes<?= icon('arrowR') ?></a>
<?php else: ?>
<a class="btn btn-primary btn-lg" href="<?= e(url('/minha-conta/cursos')) ?>">Ir para Meus cursos<?= icon('arrowR') ?></a>
<?php endif; ?>
<a class="btn btn-outline btn-lg" href="<?= e(url('/cursos')) ?>">Explorar mais cursos</a>
</div></div>

<?php elseif ($status === 'pending' && $online): ?>
<span class="success-ic wait"><?= icon($gw === 'rejected' ? 'alert' : 'clock') ?></span>
<div><div class="kicker">Pedido registrado · aguardando pagamento</div>
<h2><?= $gw === 'rejected' ? 'O pagamento não foi aprovado' : ($processing ? 'Pagamento em processamento' : 'Falta só o pagamento') ?></h2>
<p><?php if ($processing): ?>O Mercado Pago está processando o pagamento<?= $order['payment_method'] === 'boleto' ? ' (boleto compensa em até 3 dias úteis)' : '' ?>. Avisaremos por e-mail assim que for confirmado.<?php elseif ($gw === 'rejected'): ?>Nenhum valor foi cobrado. Você pode tentar novamente, inclusive com outra forma de pagamento.<?php else: ?>Conclua o pagamento no ambiente seguro do Mercado Pago. O pedido fica reservado por 3 dias.<?php endif; ?></p>
<div class="btns">
<a class="btn btn-buy btn-lg" href="<?= e(url('/pedido/' . $order['number'] . '/pagar')) ?>" data-autopay="<?= $autoPay ? '1' : '0' ?>"><?= icon('lock') ?><?= $gw === 'rejected' ? 'Tentar pagar de novo' : 'Pagar com Mercado Pago' ?></a>
<a class="btn btn-outline btn-lg" href="<?= e(url('/minha-conta/pedidos')) ?>">Ver meus pedidos</a>
</div>
<?php if ($autoPay): ?><p class="hint" data-autopay-note>Abrindo o Mercado Pago…</p><?php endif; ?>
</div>

<?php elseif ($status === 'pending'): ?>
<span class="success-ic wait"><?= icon('clock') ?></span>
<div><div class="kicker">Pedido registrado · aguardando pagamento</div>
<h2>Recebemos o seu pedido</h2>
<p>O pagamento online está em ativação, então nenhuma cobrança foi feita. Nossa equipe vai enviar as instruções para pagamento via <strong><?= e($method['label']) ?></strong> para <?= e($order['buyer_email']) ?>. Assim que o pagamento for confirmado, liberamos o acesso.</p>
<div class="btns">
<?php if ($whatsapp): ?><a class="btn btn-buy btn-lg" href="<?= e(wa_link($whatsapp, 'Olá! Acabei de fazer o pedido ' . $order['number'] . ' na loja da Dafnis e gostaria de receber as instruções de pagamento.')) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?>Falar no WhatsApp</a><?php endif; ?>
<a class="btn <?= $whatsapp ? 'btn-outline' : 'btn-primary' ?> btn-lg" href="<?= e(url('/minha-conta/pedidos')) ?>">Ver meus pedidos</a>
</div></div>

<?php else: ?>
<span class="success-ic err"><?= icon('close') ?></span>
<div><div class="kicker"><?= e(Order::statusLabel($status)) ?></div>
<h2><?= $status === 'refunded' ? 'Pagamento estornado' : 'Pedido cancelado' ?></h2>
<p>Este pedido não está mais ativo. Se precisar, monte um novo pedido pelo catálogo ou fale com a nossa equipe.</p>
<div class="btns"><a class="btn btn-primary btn-lg" href="<?= e(url('/cursos')) ?>">Explorar cursos</a><a class="btn btn-outline btn-lg" href="<?= e(url('/contato')) ?>">Falar com a equipe</a></div>
</div>
<?php endif; ?>
</div>

<?php if ($next): ?>
<div class="form-card order-next">
<h2>Próximos passos</h2>
<ol class="order-timeline">
<?php foreach ($next as $i => [$title, $text, $state]): ?>
<li class="is-<?= $state ?>"<?= $state === 'on' ? ' aria-current="step"' : '' ?>><span class="ot-n"><?= $state === 'done' ? icon('check') : $i + 1 ?></span><div><strong><?= e($title) ?></strong><small><?= e($text) ?></small></div></li>
<?php endforeach; ?>
</ol>
</div>
<?php endif; ?>
</div>

<aside class="summary order-summary" aria-labelledby="resumo-titulo">
<div class="sum-head"><h2 id="resumo-titulo">Resumo</h2><?= partial('status', ['label' => Order::statusLabel($status), 'tone' => Order::STATUS_TONE[$status] ?? 'muted']) ?></div>
<p class="order-meta">Pedido <strong><?= e($order['number']) ?></strong> · <?= e(date_br($order['created_at'], true)) ?></p>
<ul class="sum-lines">
<?php foreach ($items as $i): ?>
<li class="sum-line"><?php if ($i['course']): ?><?= partial('cover', ['course' => $i['course'], 'variant' => 'thumb']) ?><?php else: ?><span class="thumb order-thumb"><?= icon('book') ?></span><?php endif; ?><div><span class="sum-line-t"><?= e(($i['course_code'] ? $i['course_code'] . ' — ' : '') . $i['course_title']) ?></span><small><?= e(pluralize((int) $i['quantity'], 'participante', 'participantes')) ?> × <?= money($i['unit_price']) ?></small></div><strong><?= money($i['line_total']) ?></strong></li>
<?php endforeach; ?>
</ul>
<div class="sum-row"><span>Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
<?php if ((float) $order['discount'] > 0): ?><div class="sum-row disc"><span>Desconto<?= $order['coupon_code'] ? ' (cupom ' . e($order['coupon_code']) . ')' : '' ?></span><span>− <?= money($order['discount']) ?></span></div><?php endif; ?>
<div class="sum-total"><span>Total</span><strong><?= money($order['total']) ?></strong></div>
<dl class="order-facts">
<div><dt><?= icon($method['icon'] ?? 'card', 'ic-sm') ?>Pagamento</dt><dd><?= e($method['label']) ?><?= $gatewayStatus ? ' · ' . e($gatewayStatus) : '' ?></dd></div>
<div><dt><?= icon($order['buyer_type'] === 'pj' ? 'building' : 'user', 'ic-sm') ?>Comprador</dt><dd><?= e($order['buyer_type'] === 'pj' && $order['company_name'] ? $order['company_name'] : $order['buyer_name']) ?><small><?= e($order['buyer_email']) ?></small></dd></div>
</dl>
<a class="btn btn-outline btn-block" href="<?= e(url('/minha-conta/pedidos/' . $order['number'])) ?>">Ver o pedido em Minha conta</a>
</aside>
</div>
</div>
</div>
