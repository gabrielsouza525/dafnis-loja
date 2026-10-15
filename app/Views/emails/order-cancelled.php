<?php /** @var array $order @var array $items @var bool $byCustomer @var string $url */ ?>
<?= App\Core\View::file('emails/title', ['kicker' => 'Pedido cancelado', 'title' => 'Pedido ' . $order['number'] . ' cancelado', 'color' => '#5B6472']) ?>
<p style="margin:0 0 12px">Olá, <?= e(first_name($order['buyer_name'])) ?>.</p>
<p style="margin:0 0 12px"><?= $byCustomer ? 'Conforme você pediu, o' : 'O' ?> pedido <strong><?= e($order['number']) ?></strong> foi <strong>cancelado</strong> antes do pagamento. Nenhuma cobrança foi feita.</p>
<?= App\Core\View::file('emails/items', ['order' => $order, 'items' => $items]) ?>
<p style="margin:0 0 12px">Se você já tinha pago um boleto ou um Pix deste pedido, não se preocupe: quando o pagamento for confirmado, o pedido volta a valer e você recebe o aviso por e-mail.</p>
<?= App\Core\View::file('emails/button', ['url' => $url, 'label' => 'Ver os cursos']) ?>
