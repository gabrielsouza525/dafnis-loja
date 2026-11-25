<?php
/**
 * Demonstrativo para imprimir ou salvar em PDF e enviar ao cliente (página própria, sem o menu do painel).
 * @var array $client @var array $orders @var float $total @var float $open @var float $paid @var int $seats
 * @var string $month @var array $business
 */
use App\Models\Order;
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
.sheet{max-width:880px;margin:24px auto;background:#fff;padding:40px 44px;border-radius:8px;box-shadow:0 2px 12px rgba(11,37,69,.08)}
.bar{max-width:880px;margin:16px auto 0;display:flex;gap:8px;justify-content:flex-end}
.bar button,.bar a{font:600 14px/1 inherit;padding:10px 16px;border-radius:6px;border:1px solid #0B2545;background:#0B2545;color:#fff;cursor:pointer;text-decoration:none}
.bar a{background:#fff;color:#0B2545}
header{display:flex;justify-content:space-between;gap:24px;border-bottom:3px solid #0B2545;padding-bottom:16px;margin-bottom:20px}
h1{font-size:20px;margin:0 0 4px;color:#0B2545}
.muted{color:#5B6777;font-size:13px}
.from{text-align:right}
.from strong{display:block;font-size:15px}
.client{display:grid;grid-template-columns:1fr 1fr;gap:6px 24px;margin:0 0 20px;padding:14px 16px;background:#F5F7FA;border-radius:6px}
.client dt{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#5B6777}
.client dd{margin:0 0 6px;font-weight:600}
table{width:100%;border-collapse:collapse}
th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#5B6777;border-bottom:2px solid #D8DEE6;padding:8px 6px}
td{padding:8px 6px;border-bottom:1px solid #E6EAF0;vertical-align:top}
.num{text-align:right;white-space:nowrap}
tr.order td{background:#F5F7FA;font-size:12.5px;color:#3A4656;border-bottom:0;padding-top:10px}
td.people{font-size:12.5px;color:#3A4656}
tfoot td{border-top:2px solid #0B2545;border-bottom:0;font-weight:700;font-size:15px;padding-top:12px}
.totals{margin-top:14px;display:flex;justify-content:flex-end;gap:28px;font-size:13px}
.totals span strong{display:block;font-size:15px}
footer{margin-top:28px;font-size:12px;color:#5B6777;border-top:1px solid #E6EAF0;padding-top:12px}
@media print{body{background:#fff}.bar{display:none}.sheet{margin:0;padding:0;box-shadow:none;max-width:none}@page{margin:16mm}}
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
<div><dt><?= $client['pj'] ? 'Empresa' : 'Cliente' ?></dt><dd><?= e($client['name']) ?></dd></div>
<div><dt><?= $client['pj'] ? 'CNPJ' : 'CPF' ?></dt><dd><?= e(document_display($client['doc'])) ?></dd></div>
<div><dt>Pedidos no mês</dt><dd><?= count($orders) ?></dd></div>
<div><dt>Vagas</dt><dd><?= (int) $seats ?></dd></div>
</dl>
<table>
<thead><tr><th>Treinamento</th><th>Participantes</th><th class="num">Vagas</th><th class="num">Valor</th></tr></thead>
<tbody>
<?php foreach ($orders as $o): ?>
<tr class="order"><td colspan="3">Pedido <?= e($o['number']) ?> · <?= e(date_br($o['created_at'])) ?> · solicitado por <?= e($o['buyer_name']) ?></td><td class="num"><?= e(Order::statusLabel($o['status'])) ?></td></tr>
<?php foreach ($o['items'] as $item): ?>
<?php $names = array_values(array_filter(array_map(static fn ($p) => $p['status'] === 'cancelled' ? '' : (string) $p['participant_name'], $item['people']), static fn ($n) => trim($n) !== '')); $missing = (int) $item['quantity'] - count($names); ?>
<tr>
<td><?= e(($item['course_code'] ? $item['course_code'] . ' — ' : '') . $item['course_title']) ?><div class="muted"><?= (int) $item['course_hours'] ?> h · <?= money($item['unit_price']) ?> por vaga</div></td>
<td class="people"><?= e(implode(', ', $names)) ?><?= $missing > 0 ? ($names ? '; ' : '') . e(pluralize($missing, 'vaga a indicar', 'vagas a indicar')) : '' ?></td>
<td class="num"><?= (int) $item['quantity'] ?></td>
<td class="num"><?= money($item['line_total']) ?></td>
</tr>
<?php endforeach; ?>
<?php if ($o['order_discount'] > 0): ?><tr><td colspan="3">Desconto<?= $o['coupon_code'] ? ' (cupom ' . e($o['coupon_code']) . ')' : '' ?></td><td class="num">− <?= money($o['order_discount']) ?></td></tr><?php endif; ?>
<?php endforeach; ?>
</tbody>
<tfoot><tr><td colspan="3">Total de <?= e(month_br($month)) ?></td><td class="num"><?= money($total) ?></td></tr></tfoot>
</table>
<?php if ($paid > 0 && $open > 0): ?>
<div class="totals"><span>Já pago<strong><?= money($paid) ?></strong></span><span>A pagar<strong><?= money($open) ?></strong></span></div>
<?php endif; ?>
<footer>Treinamentos contratados pela loja <?= e($business['name']) ?>. Os certificados ficam disponíveis para cada participante após a conclusão e a aprovação no curso.</footer>
</main>
</body>
</html>
