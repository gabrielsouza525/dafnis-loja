<?php
/**
 * Demonstrativo de um cliente no mês: pedidos, um aluno por linha (com CPF, formação ou reciclagem,
 * certificação e centro de custo), o total por centro de custo e a baixa da fatura.
 * @var array $client @var array $orders @var array $rows @var array $centers @var float $discounts
 * @var float $total @var float $open @var float $paid @var int $seats @var string $month @var string $doc @var array $otherOpen
 */
use App\Controllers\Admin\BillingController;
use App\Models\Order;

$q = ['doc' => $doc, 'mes' => $month];
$openCount = count(array_filter($orders, static fn ($o) => $o['status'] === 'invoiced'));
$active = array_filter($rows, static fn ($r) => $r['status'] !== 'cancelled');
$noName = count(array_filter($active, static fn ($r) => $r['name'] === ''));
$noCenter = $client['pj'] ? count(array_filter($active, static fn ($r) => $r['name'] !== '' && $r['cost_center'] === '')) : 0;
?>
<div class="adm-head"><div><h1><?= e($client['name']) ?></h1><p><?= $client['pj'] ? 'CNPJ' : 'CPF' ?> <?= e(document_display($client['doc'])) ?> · <?= e(month_br($month, true)) ?></p></div>
<div class="adm-actions">
<a class="btn btn-outline btn-sm" href="<?= e(url('/admin/faturamento', ['mes' => $month])) ?>"><?= icon('arrowL', 'ic-sm') ?>Faturamento</a>
<a class="btn btn-outline btn-sm" href="<?= e(url('/admin/faturamento/cliente', $q + ['imprimir' => '1'])) ?>" target="_blank" rel="noopener"><?= icon('file', 'ic-sm') ?>Imprimir demonstrativo</a>
<a class="btn btn-outline btn-sm" href="<?= e(url('/admin/faturamento/cliente', $q + ['formato' => 'csv'])) ?>"><?= icon('download', 'ic-sm') ?>Baixar planilha</a>
</div></div>
<?php if ($err = field_error('settle')): ?><div class="note-box err" style="margin:0 0 16px"><?= icon('alert') ?><span><?= e($err) ?></span></div><?php endif; ?>

<div class="kpis-admin">
<div class="kpi"><span class="kpi-ic g"><?= icon('chart') ?></span><span class="kpi-n"><?= money($total) ?></span><span class="kpi-l">Total no mês · <?= e(pluralize(count($orders), 'pedido', 'pedidos')) ?></span></div>
<div class="kpi"><span class="kpi-ic o"><?= icon('receipt') ?></span><span class="kpi-n"><?= money($open) ?></span><span class="kpi-l">A receber (faturado)</span></div>
<div class="kpi"><span class="kpi-ic"><?= icon('check') ?></span><span class="kpi-n"><?= money($paid) ?></span><span class="kpi-l">Já pago</span></div>
<div class="kpi"><span class="kpi-ic"><?= icon('users') ?></span><span class="kpi-n"><?= (int) $seats ?></span><span class="kpi-l">Alunos (vagas)</span></div>
</div>

<div class="grid-2">
<div>
<div class="panel">
<div class="panel-head"><h2>Pedidos do mês</h2></div>
<div class="table-wrap"><table class="table">
<thead><tr><th>Pedido</th><th>Data</th><th>Pagamento</th><th>Situação</th><th class="num">Alunos</th><th class="num">Valor</th></tr></thead>
<tbody>
<?php foreach ($orders as $o): ?>
<tr>
<td><a href="<?= e(url('/admin/pedidos/' . $o['id'])) ?>"><strong><?= e($o['number']) ?></strong></a><span class="sub"><?= e($o['buyer_name']) ?></span></td>
<td><?= e(date_br($o['created_at'])) ?></td>
<td><?= e(Order::METHODS[$o['payment_method']]['label'] ?? $o['payment_method']) ?></td>
<td><?= partial('status', ['label' => Order::statusLabel($o['status']), 'tone' => Order::STATUS_TONE[$o['status']] ?? 'muted']) ?></td>
<td class="num"><?= (int) $o['seats'] ?></td>
<td class="num"><?= money($o['total']) ?><?php if ($o['order_discount'] > 0): ?><span class="sub">desconto − <?= money($o['order_discount']) ?></span><?php endif; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot><tr><th colspan="4">Total de <?= e(month_br($month)) ?></th><th class="num"><?= (int) $seats ?></th><th class="num"><?= money($total) ?></th></tr></tfoot>
</table></div>
</div>
</div>
<aside>
<div class="panel"><div class="panel-head"><h2>Fatura do mês</h2><?= $openCount ? partial('status', ['label' => 'Em aberto', 'tone' => 'warn']) : partial('status', ['label' => 'Nada a receber', 'tone' => 'ok']) ?></div><div class="panel-body">
<?php if ($openCount): ?>
<p style="margin:0 0 12px"><strong><?= money($open) ?></strong> em <?= e(pluralize($openCount, 'pedido faturado', 'pedidos faturados')) ?>. Envie o demonstrativo para a empresa e, quando o pagamento chegar, dê a baixa aqui.</p>
<form method="post" action="<?= e(url('/admin/faturamento/baixa')) ?>" data-confirm="Confirmar o recebimento de <?= e(money($open)) ?> da fatura de <?= e(month_br($month)) ?>? <?= $openCount === 1 ? 'O pedido faturado passa' : 'Os ' . $openCount . ' pedidos faturados passam' ?> a Pago.">
<?= csrf_field() ?>
<input type="hidden" name="doc" value="<?= e($doc) ?>"><input type="hidden" name="mes" value="<?= e($month) ?>">
<div class="field"><label for="note">Como foi pago <small>(opcional)</small></label><input class="input" id="note" name="note" maxlength="300" placeholder="Ex.: transferência recebida em 10/12, NF 1234"></div>
<button class="btn btn-buy btn-block" type="submit" style="margin-top:12px"><?= icon('check') ?>Fatura paga: dar baixa</button>
</form>
<p class="hint">Use só depois de conferir o dinheiro na conta. O cliente não recebe e-mail.</p>
<?php else: ?>
<p class="muted" style="margin:0">Todos os pedidos deste mês estão pagos.</p>
<?php endif; ?>
</div></div>
<div class="panel"><div class="panel-head"><h2>Cliente</h2></div><div class="panel-body">
<dl class="dl">
<dt><?= $client['pj'] ? 'Razão social' : 'Nome' ?></dt><dd><?= e($client['name']) ?></dd>
<dt><?= $client['pj'] ? 'CNPJ' : 'CPF' ?></dt><dd><?= e(document_display($client['doc'])) ?></dd>
<dt>Último comprador</dt><dd><?= e($client['contact']) ?></dd>
<dt>E-mail</dt><dd><a href="mailto:<?= e($client['email']) ?>"><?= e($client['email']) ?></a></dd>
</dl>
</div></div>
<?php if ($otherOpen): ?>
<div class="panel"><div class="panel-head"><h2>Em aberto em outros meses</h2></div><div class="panel-body">
<ul class="plain-list" style="margin:0;padding-left:18px">
<?php foreach ($otherOpen as $m): ?><li><a href="<?= e(url('/admin/faturamento/cliente', ['doc' => $doc, 'mes' => $m['ym']])) ?>"><?= e(month_br($m['ym'], true)) ?></a>: <?= money($m['total']) ?> (<?= e(pluralize((int) $m['orders'], 'pedido', 'pedidos')) ?>)</li><?php endforeach; ?>
</ul>
</div></div>
<?php endif; ?>
</aside>
</div>

<div class="panel" id="alunos" style="margin-top:22px">
<div class="panel-head"><h2>Alunos do mês</h2><span class="muted" style="font-size:14px"><?= e(pluralize(count($rows), 'vaga', 'vagas')) ?></span></div>
<?php if ($noName || $noCenter): ?>
<div class="panel-body" style="padding-bottom:0"><div class="note-box" style="margin:0"><?= icon('info') ?><span>
<?= $noName ? e(pluralize($noName, 'vaga ainda sem aluno', 'vagas ainda sem aluno')) . '. ' : '' ?><?= $noCenter ? e(pluralize($noCenter, 'aluno sem centro de custo', 'alunos sem centro de custo')) . '. ' : '' ?>O comprador preenche em Minha conta › Pedidos; você também pode preencher clicando no aluno.
</span></div></div>
<?php endif; ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Aluno</th><th>CPF</th><th>Curso</th><th>Tipo</th><th>Certificação</th><?php if ($client['pj']): ?><th>Centro de custo</th><?php endif; ?><th class="num">Valor</th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
<td><?php if ($r['enrollment_id']): ?><a href="<?= e(url('/admin/matriculas/' . $r['enrollment_id'])) ?>"><?= $r['name'] !== '' ? e($r['name']) : '<em>Aluno a indicar</em>' ?></a><?php else: ?><em>Aluno a indicar</em><?php endif; ?></td>
<td class="mono"><?= $r['cpf'] !== '' ? e(document_display($r['cpf'])) : '' ?></td>
<td><?= e($r['course']) ?><span class="sub"><?= (int) $r['hours'] ?> h · pedido <?= e($r['order']) ?></span></td>
<td><?= e($r['kind']) ?></td>
<td><?= e(BillingController::certification($r)) ?></td>
<?php if ($client['pj']): ?><td><?= $r['cost_center'] !== '' ? e($r['cost_center']) : ($r['name'] !== '' && $r['status'] !== 'cancelled' ? '<span class="muted">Não informado</span>' : '') ?></td><?php endif; ?>
<td class="num"><?= money($r['value']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table></div>
</div>

<?php if ($client['pj']): ?>
<div class="panel" style="margin-top:22px;max-width:640px">
<div class="panel-head"><h2>Por centro de custo</h2></div>
<div class="table-wrap"><table class="table">
<thead><tr><th>Centro de custo</th><th class="num">Alunos</th><th class="num">Valor</th></tr></thead>
<tbody>
<?php foreach ($centers as $c): ?>
<tr><td><?= $c['name'] !== '' ? e($c['name']) : '<span class="muted">Não informado</span>' ?></td><td class="num"><?= (int) $c['people'] ?></td><td class="num"><?= money($c['value']) ?></td></tr>
<?php endforeach; ?>
<?php if ($discounts > 0): ?><tr><td colspan="2">Descontos</td><td class="num">− <?= money($discounts) ?></td></tr><?php endif; ?>
</tbody>
<tfoot><tr><th>Total</th><th class="num"><?= (int) $seats ?></th><th class="num"><?= money($total) ?></th></tr></tfoot>
</table></div>
</div>
<?php endif; ?>
