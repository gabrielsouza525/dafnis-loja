<?php
/**
 * Demonstrativo para imprimir ou salvar em PDF e enviar ao cliente (página própria, sem o menu do painel).
 * Uma linha por aluno, com o que a empresa pede na fatura, e o total por centro de custo.
 * @var array $client @var array $orders @var array $rows @var array $centers @var float $discounts
 * @var float $total @var float $open @var float $paid @var int $seats @var string $month @var array $business
 */
use App\Controllers\Admin\BillingController;

$mixed = $paid > 0 && $open > 0;
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Demonstrativo <?= e(month_br($month)) ?> — <?= e($client['name']) ?></title>
<style>
*{box-sizing:border-box}
body{margin:0;background:#EEF1F5;color:#16202E;font:14px/1.5 -apple-system,"Segoe UI",Roboto,Arial,sans-serif}
.sheet{max-width:1120px;margin:24px auto;background:#fff;padding:36px 40px;border-radius:8px;box-shadow:0 2px 12px rgba(11,37,69,.08)}
.bar{max-width:1120px;margin:16px auto 0;display:flex;gap:8px;justify-content:flex-end}
.bar button,.bar a{font:600 14px/1 inherit;padding:10px 16px;border-radius:6px;border:1px solid #0B2545;background:#0B2545;color:#fff;cursor:pointer;text-decoration:none}
.bar a{background:#fff;color:#0B2545}
header{display:flex;justify-content:space-between;gap:24px;border-bottom:3px solid #0B2545;padding-bottom:14px;margin-bottom:18px}
h1{font-size:20px;margin:0 0 4px;color:#0B2545}
h2{font-size:14px;margin:26px 0 8px;color:#0B2545;text-transform:uppercase;letter-spacing:.05em}
.muted{color:#5B6777;font-size:12.5px}
.from{text-align:right}
.from strong{display:block;font-size:15px}
.client{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:6px 24px;margin:0 0 18px;padding:12px 16px;background:#F5F7FA;border-radius:6px}
.client dt{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#5B6777}
.client dd{margin:0;font-weight:600}
table{width:100%;border-collapse:collapse;font-size:12.5px}
th{text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.05em;color:#5B6777;border-bottom:2px solid #D8DEE6;padding:7px 6px;vertical-align:bottom}
td{padding:7px 6px;border-bottom:1px solid #E6EAF0;vertical-align:top}
tr{break-inside:avoid}
thead{display:table-header-group}
.num{text-align:right;white-space:nowrap}
.nowrap{white-space:nowrap}
td .sub{display:block;color:#5B6777;font-size:11.5px}
td.empty{color:#7F5A10;font-style:italic}
tr.disc td{color:#3A4656}
tfoot td{border-top:2px solid #0B2545;border-bottom:0;font-weight:700;font-size:14px;padding-top:10px}
.centers{max-width:560px}
.totals{margin-top:14px;display:flex;justify-content:flex-end;gap:28px;font-size:13px}
.totals span strong{display:block;font-size:15px}
footer{margin-top:26px;font-size:11.5px;color:#5B6777;border-top:1px solid #E6EAF0;padding-top:10px}
@media print{body{background:#fff}.bar{display:none}.sheet{margin:0;padding:0;box-shadow:none;max-width:none}@page{size:A4 landscape;margin:12mm}}
</style>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body>
<div class="bar"><a href="<?= e(url('/admin/faturamento/cliente', ['doc' => $client['doc'], 'mes' => $month])) ?>">Voltar</a><button type="button" data-print hidden>Imprimir ou salvar em PDF</button></div>
<main class="sheet">
<header>
<div><h1>Demonstrativo de treinamentos</h1><div class="muted">Referente a <?= e(month_br($month)) ?> · emitido em <?= e(date_br(date('Y-m-d'))) ?></div></div>
<div class="from"><strong><?= e($business['name']) ?></strong>
<?php if ($business['cnpj']): ?><span class="muted">CNPJ <?= e(document_display($business['cnpj'])) ?></span><br><?php endif; ?>
<?php if ($business['city']): ?><span class="muted"><?= e($business['city']) ?></span><br><?php endif; ?>
<span class="muted"><?= e(implode(' · ', array_filter([$business['email'], $business['phone'] ? phone_display($business['phone']) : '']))) ?></span></div>
</header>
<dl class="client">
<div><dt><?= $client['pj'] ? 'Razão social' : 'Cliente' ?></dt><dd><?= e($client['name']) ?></dd></div>
<div><dt><?= $client['pj'] ? 'CNPJ' : 'CPF' ?></dt><dd><?= e(document_display($client['doc'])) ?></dd></div>
<div><dt>Referência</dt><dd><?= e(month_br($month, true)) ?></dd></div>
<div><dt>Alunos</dt><dd><?= (int) $seats ?></dd></div>
</dl>
<table>
<thead><tr><th>Aluno</th><th>CPF</th><th>Curso</th><th>Formação ou reciclagem</th><th>Data da certificação</th><th>Centro de custo</th><th class="num">Valor</th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
<?php if ($r['name'] !== ''): ?><td><?= e($r['name']) ?><?php if ($r['job_title'] !== ''): ?><span class="sub"><?= e($r['job_title']) ?></span><?php endif; ?></td><?php else: ?><td class="empty">Aluno a indicar</td><?php endif; ?>
<td class="nowrap"><?= $r['cpf'] !== '' ? e(document_display($r['cpf'])) : '' ?></td>
<td><?= e($r['course']) ?><span class="sub"><?= (int) $r['hours'] ?> h · pedido <?= e($r['order']) ?> de <?= e(date_br($r['order_date'])) ?><?= $mixed && $r['order_status'] === 'paid' ? ' · já pago' : '' ?></span></td>
<td><?= e($r['kind']) ?></td>
<td class="nowrap"><?= e(BillingController::certification($r)) ?></td>
<td><?= e($r['cost_center']) ?></td>
<td class="num"><?= money($r['value']) ?></td>
</tr>
<?php endforeach; ?>
<?php foreach ($orders as $o): if ($o['order_discount'] <= 0) { continue; } ?>
<tr class="disc"><td colspan="6">Desconto<?= $o['coupon_code'] ? ' (cupom ' . e($o['coupon_code']) . ')' : '' ?> · pedido <?= e($o['number']) ?></td><td class="num">− <?= money($o['order_discount']) ?></td></tr>
<?php endforeach; ?>
</tbody>
<tfoot><tr><td colspan="6">Total de <?= e(month_br($month)) ?></td><td class="num"><?= money($total) ?></td></tr></tfoot>
</table>
<?php if ($mixed): ?>
<div class="totals"><span>Já pago<strong><?= money($paid) ?></strong></span><span>A pagar<strong><?= money($open) ?></strong></span></div>
<?php endif; ?>

<?php if ($client['pj']): ?>
<h2>Por centro de custo</h2>
<table class="centers">
<thead><tr><th>Centro de custo</th><th class="num">Alunos</th><th class="num">Valor</th></tr></thead>
<tbody>
<?php foreach ($centers as $c): ?>
<tr><?php if ($c['name'] !== ''): ?><td><?= e($c['name']) ?></td><?php else: ?><td class="empty">Não informado</td><?php endif; ?><td class="num"><?= (int) $c['people'] ?></td><td class="num"><?= money($c['value']) ?></td></tr>
<?php endforeach; ?>
<?php if ($discounts > 0): ?><tr class="disc"><td colspan="2">Descontos</td><td class="num">− <?= money($discounts) ?></td></tr><?php endif; ?>
</tbody>
<tfoot><tr><td>Total</td><td class="num"><?= (int) $seats ?></td><td class="num"><?= money($total) ?></td></tr></tfoot>
</table>
<?php endif; ?>
<footer>Treinamentos contratados pela loja <?= e($business['name']) ?>. A data da certificação aparece quando o aluno conclui o curso e o certificado é emitido; até lá, a linha mostra a situação da vaga.</footer>
</main>
</body>
</html>
