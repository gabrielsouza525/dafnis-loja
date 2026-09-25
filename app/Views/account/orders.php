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
<div class="panel">
<div class="table-wrap"><table class="table">
<thead><tr><th>Pedido</th><th>Data</th><th>Treinamentos</th><th>Participantes</th><th>Situação</th><th class="num">Total</th></tr></thead>
<tbody>
<?php foreach ($orders as $o): ?>
<tr><td><a href="<?= e(url('/minha-conta/pedidos/' . $o['number'])) ?>"><?= e($o['number']) ?></a></td><td><?= e(date_br($o['created_at'])) ?></td><td><?= (int) $o['item_count'] ?></td><td><?= (int) $o['participants'] ?></td><td><?= partial('status', ['label' => Order::statusLabel($o['status']), 'tone' => Order::STATUS_TONE[$o['status']] ?? 'muted']) ?></td><td class="num"><?= money($o['total']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
</div>
<?php endif; ?>
<?= partial('account-shell-close') ?>
</div>
