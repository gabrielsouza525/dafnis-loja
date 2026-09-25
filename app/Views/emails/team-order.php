<?php /** @var array $order @var array $items @var string $headline @var string $url */ ?>
<p style="margin:0 0 12px"><strong><?= e($headline) ?></strong></p>
<p style="margin:0 0 12px">Pedido <?= e($order['number']) ?> — <?= e($order['buyer_type'] === 'pj' ? (string) $order['company_name'] . ' (' . $order['buyer_name'] . ')' : $order['buyer_name']) ?><br><?= e($order['buyer_email']) ?> · <?= e(phone_display($order['buyer_phone'])) ?></p>
<?= App\Core\View::file('emails/items', ['order' => $order, 'items' => $items]) ?>
<?= App\Core\View::file('emails/button', ['url' => $url, 'label' => 'Abrir no painel', 'color' => '#0B2545']) ?>
