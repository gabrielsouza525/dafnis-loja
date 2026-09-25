<?php
/** @var array $page @var string $status @var string $q @var array $counts */
use App\Models\Order;

$all = array_sum($counts);
?>
<div class="adm-head"><div><h1>Pedidos</h1><p>Pagamentos, baixas manuais e vagas de cada compra.</p></div></div>
<div class="chips" style="margin-bottom:14px">
<a href="<?= e(url('/admin/pedidos', ['q' => $q])) ?>"<?= $status === '' ? ' class="on"' : '' ?>>Todos <span><?= (int) $all ?></span></a>
<?php foreach (Order::STATUS as $key => $label): ?><a href="<?= e(url('/admin/pedidos', ['status' => $key, 'q' => $q])) ?>"<?= $status === $key ? ' class="on"' : '' ?>><?= e($label) ?> <span><?= (int) ($counts[$key] ?? 0) ?></span></a><?php endforeach; ?>
</div>
<form class="toolbar" method="get" action="<?= e(url('/admin/pedidos')) ?>">
<input class="input search" type="search" name="q" value="<?= e($q) ?>" placeholder="Número, nome, e-mail, empresa ou CPF/CNPJ" aria-label="Buscar pedidos">
<?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
<button class="btn btn-outline btn-sm" type="submit">Buscar</button>
</form>
<div class="panel">
<div class="table-wrap"><table class="table">
<thead><tr><th>Pedido</th><th>Comprador</th><th>Pagamento</th><th>Participantes</th><th>Situação</th><th class="num">Total</th></tr></thead>
<tbody>
<?php foreach ($page['rows'] as $o): ?>
<tr>
<td><a href="<?= e(url('/admin/pedidos/' . $o['id'])) ?>"><?= e($o['number']) ?></a><span class="sub"><?= e(date_br($o['created_at'], true)) ?></span></td>
<td><?= e($o['buyer_type'] === 'pj' ? $o['company_name'] : $o['buyer_name']) ?><span class="sub"><?= e($o['buyer_email']) ?></span></td>
<td><?= e(Order::METHODS[$o['payment_method']]['label'] ?? $o['payment_method']) ?><span class="sub"><?= e($o['gateway'] === 'mercadopago' ? 'Mercado Pago' : 'Manual') ?></span></td>
<td><?= (int) $o['participants'] ?></td>
<td><?= partial('status', ['label' => Order::statusLabel($o['status']), 'tone' => Order::STATUS_TONE[$o['status']] ?? 'muted']) ?></td>
<td class="num"><strong><?= money($o['total']) ?></strong></td>
</tr>
<?php endforeach; ?>
<?php if (!$page['rows']): ?><tr><td colspan="6" class="muted">Nenhum pedido encontrado.</td></tr><?php endif; ?>
</tbody></table></div>
<?= partial('admin-pager', ['p' => $page]) ?>
</div>
