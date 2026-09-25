<?php
/** @var array $order @var array $items @var array $seats @var bool $online */
use App\Models\Enrollment;
use App\Models\Order;

$crumbs = [['Início', '/'], ['Minha conta', '/minha-conta'], ['Pedidos e vagas', '/minha-conta/pedidos'], [$order['number'], null]];
$seatsByItem = [];
foreach ($seats as $s) {
    $seatsByItem[$s['order_item_id']][] = $s;
}
$method = Order::METHODS[$order['payment_method']]['label'] ?? $order['payment_method'];
?>
<div class="screen">
<?= partial('account-shell-open', get_defined_vars()) ?>
<div class="acc-head">
<div><h1>Pedido <?= e($order['number']) ?></h1><p>Feito em <?= e(date_br($order['created_at'], true)) ?> · <?= e($method) ?></p></div>
<?= partial('status', ['label' => Order::statusLabel($order['status']), 'tone' => Order::STATUS_TONE[$order['status']] ?? 'muted']) ?>
</div>

<?php if ($order['status'] === 'pending'): ?>
<div class="note-box" style="margin:0 0 22px"><?= icon('clock') ?><span><?php if ($online): ?><strong>Aguardando pagamento.</strong> <a href="<?= e(url('/pedido/' . $order['number'] . '/pagar')) ?>">Pagar com Mercado Pago</a><?php else: ?><strong>Aguardando pagamento.</strong> Nossa equipe envia as instruções para <?= e($order['buyer_email']) ?>. Depois da confirmação, você indica os participantes aqui.<?php endif; ?></span></div>
<?php endif; ?>

<div class="panel">
<div class="panel-head"><h2>Resumo</h2></div>
<div class="panel-body">
<div class="sum-items">
<?php foreach ($items as $i): ?>
<div class="sum-item"><span><?= e(($i['course_code'] ? $i['course_code'] . ' — ' : '') . $i['course_title']) ?><br><small><?= (int) $i['quantity'] ?> × <?= money($i['unit_price']) ?></small></span><strong><?= money($i['line_total']) ?></strong></div>
<?php endforeach; ?>
</div>
<div class="sum-row"><span>Subtotal</span><span><?= money($order['subtotal']) ?></span></div>
<div class="sum-row disc"><span>Desconto<?= $order['coupon_code'] ? ' (cupom ' . e($order['coupon_code']) . ')' : '' ?></span><span><?= (float) $order['discount'] > 0 ? '− ' . money($order['discount']) : 'R$ 0,00' ?></span></div>
<div class="sum-total"><span>Total</span><strong><?= money($order['total']) ?></strong></div>
<dl class="dl" style="margin-top:18px">
<dt>Comprador</dt><dd><?= e($order['buyer_type'] === 'pj' ? $order['company_name'] . ' — CNPJ ' . document_display($order['buyer_document']) : $order['buyer_name'] . ' — CPF ' . document_masked($order['buyer_document'])) ?></dd>
<?php if ($order['buyer_type'] === 'pj'): ?><dt>Responsável</dt><dd><?= e($order['buyer_name']) ?></dd><?php endif; ?>
<dt>Contato</dt><dd><?= e($order['buyer_email']) ?> · <?= e(phone_display($order['buyer_phone'])) ?></dd>
<?php if ($order['paid_at']): ?><dt>Pago em</dt><dd><?= e(date_br($order['paid_at'], true)) ?></dd><?php endif; ?>
</dl>
</div>
</div>

<?php if ($seats): ?>
<div class="panel" id="vagas">
<div class="panel-head"><h2>Participantes</h2><span class="muted" style="font-size:14px"><?= e(pluralize(count($seats), 'vaga', 'vagas')) ?></span></div>
<div class="panel-body">
<p class="muted" style="margin-bottom:16px">Informe quem vai fazer cada treinamento. O participante recebe o acesso por e-mail e, criando uma conta com esse e-mail, acompanha o curso e baixa o certificado.</p>
<?php foreach ($items as $i): if (empty($seatsByItem[$i['id']])) { continue; } ?>
<h3 style="font-size:16px;margin:22px 0 12px"><?= e(($i['course_code'] ? $i['course_code'] . ' — ' : '') . $i['course_title']) ?></h3>
<?php foreach ($seatsByItem[$i['id']] as $n => $s): $editable = in_array($s['status'], ['awaiting_participant', 'processing'], true); ?>
<div class="seat" id="vaga-<?= (int) $s['id'] ?>">
<div class="seat-head"><strong>Vaga <?= $n + 1 ?></strong><?= partial('status', ['label' => Enrollment::statusLabel($s['status']), 'tone' => Enrollment::STATUS_TONE[$s['status']] ?? 'muted']) ?></div>
<?php if ($editable): ?>
<form method="post" action="<?= e(url('/minha-conta/vagas/' . $s['id'])) ?>" data-loading-form>
<?= csrf_field() ?>
<input type="hidden" name="_scope" value="seat-<?= (int) $s['id'] ?>">
<div class="fields">
<?= partial('field', ['name' => 'participant_name', 'scope' => 'seat-' . $s['id'], 'id' => 'pn-' . $s['id'], 'label' => 'Nome completo', 'value' => $s['participant_name'], 'required' => true]) ?>
<?= partial('field', ['name' => 'participant_email', 'scope' => 'seat-' . $s['id'], 'id' => 'pe-' . $s['id'], 'label' => 'E-mail', 'type' => 'email', 'value' => $s['participant_email'], 'required' => true]) ?>
<?= partial('field', ['name' => 'participant_document', 'scope' => 'seat-' . $s['id'], 'id' => 'pd-' . $s['id'], 'label' => 'CPF', 'value' => $s['participant_document'] ? document_display($s['participant_document']) : '', 'required' => true, 'mask' => 'cpf']) ?>
<button class="btn btn-navy" type="submit"><?= $s['participant_email'] ? 'Atualizar' : 'Salvar' ?></button>
</div>
</form>
<?php else: ?>
<dl class="dl"><dt>Participante</dt><dd><?= e((string) $s['participant_name']) ?> · <?= e((string) $s['participant_email']) ?></dd><?php if ($s['status'] !== 'cancelled'): ?><dt>Progresso</dt><dd><?= (int) $s['progress'] ?>%</dd><?php endif; ?></dl>
<?php endif; ?>
</div>
<?php endforeach; ?>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
<?= partial('account-shell-close') ?>
</div>
