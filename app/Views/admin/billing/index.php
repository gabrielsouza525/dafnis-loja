<?php
/**
 * Faturamento: quanto cada cliente gastou no mês e as faturas em aberto.
 * @var string $month AAAA-MM @var array $months @var array $clients @var array $open @var array $sum
 */
$prev = date('Y-m', strtotime($month . '-01 -1 month'));
$next = date('Y-m', strtotime($month . '-01 +1 month'));
$who = static fn (array $c) => document_display((string) $c['doc']);
?>
<div class="adm-head"><div><h1>Faturamento</h1><p>Quanto cada cliente gastou no mês, pela data do pedido. Entram os pedidos pagos e os faturados (fatura mensal).</p></div></div>

<form class="toolbar" method="get" action="<?= e(url('/admin/faturamento')) ?>">
<a class="btn btn-outline btn-sm" href="<?= e(url('/admin/faturamento', ['mes' => $prev])) ?>" aria-label="Mês anterior"><?= icon('arrowL', 'ic-sm') ?></a>
<select class="input" name="mes" aria-label="Mês" style="max-width:220px">
<?php foreach ($months as $m): ?><option value="<?= e($m) ?>"<?= $m === $month ? ' selected' : '' ?>><?= e(month_br($m, true)) ?></option><?php endforeach; ?>
</select>
<button class="btn btn-outline btn-sm" type="submit">Ver mês</button>
<?php if ($next <= date('Y-m')): ?><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/faturamento', ['mes' => $next])) ?>" aria-label="Mês seguinte"><?= icon('arrowR', 'ic-sm') ?></a><?php endif; ?>
</form>

<div class="kpis-admin">
<div class="kpi"><span class="kpi-ic g"><?= icon('chart') ?></span><span class="kpi-n"><?= money($sum['total']) ?></span><span class="kpi-l">Total de <?= e(month_br($month)) ?> · <?= e(pluralize((int) $sum['orders'], 'pedido', 'pedidos')) ?></span></div>
<div class="kpi"><span class="kpi-ic o"><?= icon('receipt') ?></span><span class="kpi-n"><?= money($sum['open']) ?></span><span class="kpi-l">A receber (faturado)</span></div>
<div class="kpi"><span class="kpi-ic"><?= icon('check') ?></span><span class="kpi-n"><?= money($sum['paid']) ?></span><span class="kpi-l">Já pago</span></div>
<div class="kpi"><span class="kpi-ic"><?= icon('users') ?></span><span class="kpi-n"><?= (int) $sum['seats'] ?></span><span class="kpi-l">Vagas compradas</span></div>
</div>

<div class="panel">
<div class="panel-head"><h2>Clientes em <?= e(month_br($month)) ?></h2></div>
<div class="table-wrap"><table class="table">
<thead><tr><th>Cliente</th><th>Pedidos</th><th>Vagas</th><th class="num">A receber</th><th class="num">Pago</th><th class="num">Total</th><th></th></tr></thead>
<tbody>
<?php foreach ($clients as $c): ?>
<tr>
<td><span class="who"><span class="avatar"><?= e(initials((string) $c['name'])) ?></span><span class="who-txt"><?= e($c['name']) ?><span class="sub"><?= strlen((string) $c['doc']) === 14 ? 'CNPJ' : 'CPF' ?> <?= e($who($c)) ?><?= (int) $c['invoice_orders'] > 0 ? ' · fatura mensal' : '' ?></span></span></span></td>
<td><?= (int) $c['orders'] ?></td>
<td><?= (int) $c['seats'] ?></td>
<td class="num"><?= (float) $c['open_total'] > 0 ? '<strong>' . money($c['open_total']) . '</strong>' : '—' ?></td>
<td class="num"><?= (float) $c['paid_total'] > 0 ? money($c['paid_total']) : '—' ?></td>
<td class="num"><strong><?= money($c['total']) ?></strong></td>
<td class="num"><a class="btn btn-ghost btn-xs" href="<?= e(url('/admin/faturamento/cliente', ['doc' => $c['doc'], 'mes' => $month])) ?>">Demonstrativo</a></td>
</tr>
<?php endforeach; ?>
<?php if (!$clients): ?><tr><td colspan="7" class="muted">Nenhum pedido pago ou faturado em <?= e(month_br($month)) ?>.</td></tr><?php endif; ?>
</tbody>
<?php if (count($clients) > 1): ?>
<tfoot><tr><th>Total do mês</th><th><?= (int) $sum['orders'] ?></th><th><?= (int) $sum['seats'] ?></th><th class="num"><?= money($sum['open']) ?></th><th class="num"><?= money($sum['paid']) ?></th><th class="num"><?= money($sum['total']) ?></th><th></th></tr></tfoot>
<?php endif; ?>
</table></div>
</div>

<div class="panel">
<div class="panel-head"><h2>Faturas em aberto</h2><span class="muted" style="font-size:14px">todos os meses</span></div>
<?php if ($open): ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Cliente</th><th>Mês</th><th>Pedidos</th><th class="num">Valor</th><th></th></tr></thead>
<tbody>
<?php foreach ($open as $f): ?>
<tr>
<td><?= e($f['name']) ?><span class="sub"><?= e($who($f)) ?></span></td>
<td><?= e(month_br($f['ym'], true)) ?></td>
<td><?= (int) $f['orders'] ?></td>
<td class="num"><strong><?= money($f['total']) ?></strong></td>
<td class="num"><a class="btn btn-ghost btn-xs" href="<?= e(url('/admin/faturamento/cliente', ['doc' => $f['doc'], 'mes' => $f['ym']])) ?>">Abrir</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php else: ?>
<div class="panel-body"><p class="muted" style="margin:0">Nenhuma fatura em aberto. Os clientes com fatura mensal são autorizados na página de cada usuário, em Usuários.</p></div>
<?php endif; ?>
</div>
