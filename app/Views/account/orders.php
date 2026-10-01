<?php
/** @var array $orders */
use App\Models\Order;

$crumbs = [['Início', '/'], ['Minha conta', '/minha-conta'], ['Pedidos e vagas', null]];
?>
<div class="screen">
<?= partial('account-shell-open', get_defined_vars()) ?>
<div class="acc-head"><div><h1>Pedidos e vagas</h1><p>Suas compras e os participantes de cada vaga.</p></div></div>
<?php if (!$orders): ?>
<div class="empty">
<span class="empty-ic"><?= icon('receipt') ?></span>
<h2 style="font-size:20px">Nenhum pedido ainda</h2>
<p>Quando você finalizar uma compra, o pedido aparece aqui.</p>
<a class="btn btn-primary" href="<?= e(url('/cursos')) ?>">Explorar cursos</a>
</div>
<?php else: ?>
<ul class="order-list">
<?php foreach ($orders as $o): $awaiting = (int) $o['awaiting']; ?>
<li>
<a class="order-row<?= $awaiting > 0 ? ' has-pending' : '' ?>" href="<?= e(url('/minha-conta/pedidos/' . $o['number'])) ?>">
<span class="order-row-ic"><?= icon('receipt') ?></span>
<span class="order-row-main">
<strong>Pedido <?= e($o['number']) ?></strong>
<small><?= e(date_br($o['created_at'])) ?> · <?= e(pluralize((int) $o['item_count'], 'treinamento', 'treinamentos')) ?> · <?= e(pluralize((int) $o['participants'], 'participante', 'participantes')) ?></small>
<?php if ($awaiting > 0): ?><span class="order-row-alert"><?= icon('users', 'ic-sm') ?><?= e(pluralize($awaiting, 'vaga aguarda', 'vagas aguardam')) ?> o participante</span><?php endif; ?>
</span>
<?= partial('status', ['label' => Order::statusLabel($o['status']), 'tone' => Order::STATUS_TONE[$o['status']] ?? 'muted']) ?>
<strong class="order-row-total"><?= money($o['total']) ?></strong>
<?= icon('chevR', 'order-row-go') ?>
</a>
</li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?= partial('account-shell-close') ?>
</div>
