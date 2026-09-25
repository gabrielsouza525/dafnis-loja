<?php /** @var array $order @var array $items @var bool $online @var string $url */ ?>
<p style="margin:0 0 12px">Olá, <?= e(first_name($order['buyer_name'])) ?>.</p>
<p style="margin:0 0 12px">Recebemos o seu pedido <strong><?= e($order['number']) ?></strong>. Ele está <strong>aguardando pagamento</strong>.</p>
<?= App\Core\View::file('emails/items', ['order' => $order, 'items' => $items]) ?>
<?php if ($online): ?>
<p style="margin:0 0 12px">Se ainda não concluiu o pagamento, use o botão abaixo. O pedido fica reservado por 3 dias.</p>
<?= App\Core\View::file('emails/button', ['url' => $url, 'label' => 'Ver pedido e pagar', 'color' => '#15803D']) ?>
<?php else: ?>
<p style="margin:0 0 12px">Nossa equipe vai enviar as instruções de pagamento em breve. Nenhuma cobrança foi feita automaticamente.</p>
<?= App\Core\View::file('emails/button', ['url' => $url, 'label' => 'Ver pedido']) ?>
<?php endif; ?>
