<?php
/** @var array $order @var array $items (+ course) @var array $seats @var bool $online */
use App\Models\Enrollment;
use App\Models\Order;

$crumbs = [['Início', '/'], ['Minha conta', '/minha-conta'], ['Pedidos e vagas', '/minha-conta/pedidos'], [$order['number'], null]];
$seatsByItem = [];
foreach ($seats as $s) {
    $seatsByItem[$s['order_item_id']][] = $s;
}
$method = Order::METHODS[$order['payment_method']] ?? ['label' => $order['payment_method'], 'icon' => 'card'];
$filled = count(array_filter($seats, static fn ($s) => $s['participant_email'] && $s['status'] !== 'cancelled'));
$open = count(array_filter($seats, static fn ($s) => $s['status'] !== 'cancelled'));
?>
<div class="screen">
<?= partial('account-shell-open', get_defined_vars()) ?>
<div class="acc-head">
<div><h1>Pedido <?= e($order['number']) ?></h1><p>Feito em <?= e(date_br($order['created_at'], true)) ?> · <?= e($method['label']) ?></p></div>
<?= partial('status', ['label' => Order::statusLabel($order['status']), 'tone' => Order::STATUS_TONE[$order['status']] ?? 'muted']) ?>
</div>

<?php if ($order['status'] === 'pending'): ?>
<div class="note-box" style="margin:0 0 22px"><?= icon('clock') ?><span><?php if ($online): ?><strong>Aguardando pagamento.</strong> <a href="<?= e(url('/pedido/' . $order['number'] . '/pagar')) ?>">Pagar com Mercado Pago</a><?php else: ?><strong>Aguardando pagamento.</strong> Nossa equipe envia as instruções para <?= e($order['buyer_email']) ?>. Depois da confirmação, você indica os participantes aqui.<?php endif; ?></span></div>
<?php endif; ?>

<div class="acc-order">
<div class="acc-order-main">
<?php if ($seats): ?>
<section class="panel" id="vagas">
<div class="panel-head"><h2>Participantes</h2><span class="muted" style="font-size:14px"><?= e(pluralize(count($seats), 'vaga', 'vagas')) ?></span></div>
<div class="panel-body">
<div class="seat-progress">
<div class="seat-progress-row"><strong><?= $filled ?> de <?= $open ?></strong> <?= $open === 1 ? 'vaga com participante' : 'vagas com participante' ?><?php if ($open > $filled): ?><span><?= e(pluralize($open - $filled, 'falta indicar', 'faltam indicar')) ?></span><?php endif; ?></div>
<div class="progress<?= $open > 0 && $filled === $open ? ' done' : '' ?>" role="progressbar" aria-valuenow="<?= $filled ?>" aria-valuemin="0" aria-valuemax="<?= $open ?>" aria-label="Vagas com participante"><span style="width:<?= $open ? round($filled / $open * 100) : 0 ?>%"></span></div>
<p>Informe quem vai fazer cada treinamento. O participante recebe o acesso por e-mail e, criando uma conta com esse e-mail, acompanha o curso e baixa o certificado.</p>
</div>
<?php foreach ($items as $i): if (empty($seatsByItem[$i['id']])) { continue; } ?>
<h3 class="seat-course"><?php if ($i['course']): ?><?= partial('cover', ['course' => $i['course'], 'variant' => 'thumb']) ?><?php else: ?><span class="thumb order-thumb"><?= icon('book') ?></span><?php endif; ?><span><?= e(($i['course_code'] ? $i['course_code'] . ' — ' : '') . $i['course_title']) ?></span></h3>
<?php foreach ($seatsByItem[$i['id']] as $n => $s): $editable = in_array($s['status'], ['awaiting_participant', 'processing'], true); ?>
<div class="seat<?= $s['participant_email'] ? '' : ' is-empty' ?>" id="vaga-<?= (int) $s['id'] ?>">
<div class="seat-head"><strong><span class="seat-n"><?= $n + 1 ?></span>Vaga <?= $n + 1 ?></strong><?= partial('status', ['label' => Enrollment::statusLabel($s['status']), 'tone' => Enrollment::STATUS_TONE[$s['status']] ?? 'muted']) ?></div>
<?php if ($editable): ?>
<form method="post" action="<?= e(url('/minha-conta/vagas/' . $s['id'])) ?>" data-loading-form>
<?= csrf_field() ?>
<input type="hidden" name="_scope" value="seat-<?= (int) $s['id'] ?>">
<div class="fields">
<?= partial('field', ['name' => 'participant_name', 'scope' => 'seat-' . $s['id'], 'id' => 'pn-' . $s['id'], 'label' => 'Nome completo', 'value' => $s['participant_name'], 'required' => true]) ?>
<?= partial('field', ['name' => 'participant_email', 'scope' => 'seat-' . $s['id'], 'id' => 'pe-' . $s['id'], 'label' => 'E-mail', 'type' => 'email', 'value' => $s['participant_email'], 'required' => true]) ?>
<?= partial('field', ['name' => 'participant_document', 'scope' => 'seat-' . $s['id'], 'id' => 'pd-' . $s['id'], 'label' => 'CPF', 'value' => $s['participant_document'] ? document_display($s['participant_document']) : '', 'required' => true, 'mask' => 'cpf']) ?>
<button class="btn <?= $s['participant_email'] ? 'btn-outline' : 'btn-navy' ?>" type="submit"><?= $s['participant_email'] ? 'Atualizar' : 'Salvar' ?></button>
</div>
</form>
<?php else: ?>
<dl class="dl"><dt>Participante</dt><dd><?= e((string) $s['participant_name']) ?> · <?= e((string) $s['participant_email']) ?></dd><?php if ($s['status'] !== 'cancelled'): ?><dt>Progresso</dt><dd><?= (int) $s['progress'] ?>%</dd><?php endif; ?></dl>
<?php endif; ?>
</div>
<?php endforeach; ?>
<?php endforeach; ?>
</div>
</section>
<?php else: ?>
<div class="panel"><div class="panel-body acc-order-empty"><?= icon('users') ?><p><?= $order['status'] === 'pending' ? 'As vagas aparecem aqui depois da confirmação do pagamento. Aí você indica quem vai fazer cada treinamento.' : 'Este pedido não tem vagas ativas.' ?></p></div></div>
<?php endif; ?>
</div>

<aside class="summary order-summary" aria-labelledby="resumo-titulo">
<div class="sum-head"><h2 id="resumo-titulo">Resumo</h2></div>
<ul class="sum-lines">
<?php foreach ($items as $i): ?>
<li class="sum-line"><?php if ($i['course']): ?><?= partial('cover', ['course' => $i['course'], 'variant' => 'thumb']) ?><?php else: ?><span class="thumb order-thumb"><?= icon('book') ?></span><?php endif; ?><div><span class="sum-line-t"><?= e(($i['course_code'] ? $i['course_code'] . ' — ' : '') . $i['course_title']) ?></span><small><?= (int) $i['quantity'] ?> × <?= money($i['unit_price']) ?></small></div><strong><?= money($i['line_total']) ?></strong></li>
<?php endforeach; ?>
</ul>
<div class="sum-row"><span>Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
<?php if ((float) $order['discount'] > 0): ?><div class="sum-row disc"><span>Desconto<?= $order['coupon_code'] ? ' (cupom ' . e($order['coupon_code']) . ')' : '' ?></span><span>− <?= money($order['discount']) ?></span></div><?php endif; ?>
<div class="sum-total"><span>Total</span><strong><?= money($order['total']) ?></strong></div>
<dl class="order-facts">
<div><dt><?= icon($order['buyer_type'] === 'pj' ? 'building' : 'user', 'ic-sm') ?>Comprador</dt><dd><?= e($order['buyer_type'] === 'pj' ? $order['company_name'] : $order['buyer_name']) ?><small><?= e($order['buyer_type'] === 'pj' ? 'CNPJ ' . document_display($order['buyer_document']) : 'CPF ' . document_masked($order['buyer_document'])) ?></small></dd></div>
<?php if ($order['buyer_type'] === 'pj'): ?><div><dt><?= icon('user', 'ic-sm') ?>Responsável</dt><dd><?= e($order['buyer_name']) ?></dd></div><?php endif; ?>
<div><dt><?= icon('mail', 'ic-sm') ?>Contato</dt><dd><?= e($order['buyer_email']) ?><small><?= e(phone_display($order['buyer_phone'])) ?></small></dd></div>
<div><dt><?= icon($method['icon'] ?? 'card', 'ic-sm') ?>Pagamento</dt><dd><?= e($method['label']) ?><?php if ($order['paid_at']): ?><small>Pago em <?= e(date_br($order['paid_at'], true)) ?></small><?php endif; ?></dd></div>
</dl>
</aside>
</div>
<?= partial('account-shell-close') ?>
</div>
