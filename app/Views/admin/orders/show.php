<?php
/**
 * @var array $order @var array $items @var array $seats @var array $events @var array $activity
 * @var array|null $buyer @var bool $online
 */
use App\Models\Enrollment;
use App\Models\Order;
use App\Services\Payments\MercadoPagoGateway;
?>
<div class="adm-head">
<div><h1>Pedido <?= e($order['number']) ?></h1><p><?= e(date_br($order['created_at'], true)) ?> · <?= e(Order::METHODS[$order['payment_method']]['label'] ?? $order['payment_method']) ?> · <?= $order['gateway'] === 'mercadopago' ? 'Mercado Pago' : 'Pagamento manual' ?></p></div>
<div class="adm-actions"><?= partial('status', ['label' => Order::statusLabel($order['status']), 'tone' => Order::STATUS_TONE[$order['status']] ?? 'muted']) ?><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/pedidos')) ?>"><?= icon('arrowL', 'ic-sm') ?>Pedidos</a></div>
</div>
<?php if ($err = field_error('order') ?: field_error('payment_id')): ?><div class="note-box err" style="margin:0 0 16px"><?= icon('alert') ?><span><?= e($err) ?></span></div><?php endif; ?>

<div class="grid-2">
<div>
<div class="panel"><div class="panel-head"><h2>Itens</h2></div>
<div class="table-wrap"><table class="table"><thead><tr><th>Treinamento</th><th class="num">Qtd.</th><th class="num">Unitário</th><th class="num">Total</th></tr></thead><tbody>
<?php foreach ($items as $i): ?>
<tr><td><?php if ($i['course_id']): ?><a href="<?= e(url('/admin/cursos/' . $i['course_id'] . '/editar')) ?>"><?php endif; ?><?= e(($i['course_code'] ? $i['course_code'] . ' — ' : '') . $i['course_title']) ?><?= $i['course_id'] ? '</a>' : '' ?><span class="sub"><?= e(hours_short($i['course_hours'])) ?><?= (float) $i['list_price'] > (float) $i['unit_price'] ? ' · tabela ' . money($i['list_price']) : '' ?></span></td><td class="num"><?= (int) $i['quantity'] ?></td><td class="num"><?= money($i['unit_price']) ?></td><td class="num"><?= money($i['line_total']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<div class="panel-body" style="border-top:1px solid var(--line)">
<div class="sum-row"><span>Subtotal (preços de tabela)</span><span><?= money($order['subtotal']) ?></span></div>
<div class="sum-row disc"><span>Descontos<?= $order['coupon_code'] ? ' (cupom ' . e($order['coupon_code']) . ')' : '' ?></span><span><?= (float) $order['discount'] > 0 ? '− ' . money($order['discount']) : 'R$ 0,00' ?></span></div>
<div class="sum-total"><span>Total</span><strong><?= money($order['total']) ?></strong></div>
</div>
</div>

<?php if ($seats): ?>
<div class="panel"><div class="panel-head"><h2>Vagas</h2><a class="text-link" href="<?= e(url('/admin/matriculas', ['pedido' => $order['number']])) ?>">Gerenciar<?= icon('arrowR', 'ic-sm') ?></a></div>
<div class="table-wrap"><table class="table"><thead><tr><th>Treinamento</th><th>Participante</th><th>Situação</th><th></th></tr></thead><tbody>
<?php foreach ($seats as $s): ?>
<tr><td><?= e(($s['course_code'] ? $s['course_code'] . ' — ' : '') . $s['course_title']) ?></td><td><?= $s['participant_name'] ? e($s['participant_name']) . '<span class="sub">' . e((string) $s['participant_email']) . '</span>' : '<span class="muted">Aguardando o comprador indicar</span>' ?></td><td><?= partial('status', ['label' => Enrollment::statusLabel($s['status']), 'tone' => Enrollment::STATUS_TONE[$s['status']] ?? 'muted']) ?></td><td class="num"><a class="btn btn-ghost btn-xs" href="<?= e(url('/admin/matriculas/' . $s['id'])) ?>">Abrir</a></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php endif; ?>

<?php if ($events): ?>
<div class="panel"><div class="panel-head"><h2>Eventos do gateway</h2></div><div class="panel-body">
<ul class="timeline">
<?php foreach ($events as $ev): ?><li><div><strong><?= e($ev['event']) ?></strong> · pagamento <?= e((string) $ev['reference']) ?> · <?= e(MercadoPagoGateway::statusLabel($ev['status']) ?? (string) $ev['status']) ?><small><?= e(date_br($ev['created_at'], true)) ?></small></div></li><?php endforeach; ?>
</ul>
</div></div>
<?php endif; ?>
</div>

<aside>
<div class="panel"><div class="panel-head"><h2>Comprador</h2></div><div class="panel-body">
<dl class="dl">
<?php if ($order['buyer_type'] === 'pj'): ?><dt>Empresa</dt><dd><?= e($order['company_name']) ?></dd><dt>CNPJ</dt><dd><?= e(document_display($order['buyer_document'])) ?></dd><dt>Responsável</dt><dd><?= e($order['buyer_name']) ?></dd>
<?php else: ?><dt>Nome</dt><dd><?= e($order['buyer_name']) ?></dd><dt>CPF</dt><dd><?= e(document_display($order['buyer_document'])) ?></dd><?php endif; ?>
<dt>E-mail</dt><dd><a href="mailto:<?= e($order['buyer_email']) ?>"><?= e($order['buyer_email']) ?></a></dd>
<dt>Telefone</dt><dd><?= e(phone_display($order['buyer_phone'])) ?><?php if ($wa = wa_link($order['buyer_phone'], 'Olá! Aqui é da Dafnis Treinamentos, sobre o pedido ' . $order['number'] . '.')): ?> · <a href="<?= e($wa) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></dd>
<?php if ($buyer): ?><dt>Conta</dt><dd><a href="<?= e(url('/admin/usuarios/' . $buyer['id'])) ?>"><?= e($buyer['name']) ?></a></dd><?php endif; ?>
<?php if ($order['paid_at']): ?><dt>Pago em</dt><dd><?= e(date_br($order['paid_at'], true)) ?></dd><?php endif; ?>
<?php if ($order['gateway_payment_id']): ?><dt>ID Mercado Pago</dt><dd class="mono"><?= e($order['gateway_payment_id']) ?> (<?= e(MercadoPagoGateway::statusLabel($order['gateway_status']) ?? '') ?>)</dd><?php endif; ?>
</dl>
</div></div>

<?php if (in_array($order['status'], ['pending', 'cancelled'], true)): ?>
<div class="panel"><div class="panel-head"><h2>Pagamento</h2></div><div class="panel-body">
<form method="post" action="<?= e(url('/admin/pedidos/' . $order['id'] . '/confirmar-pagamento')) ?>" data-confirm="Confirmar o recebimento de <?= e(money($order['total'])) ?>? As vagas serão criadas e o cliente será avisado.">
<?= csrf_field() ?>
<div class="field"><label for="note">Como foi pago <small>(opcional)</small></label><input class="input" id="note" name="note" placeholder="Ex.: Pix recebido em 25/09, comprovante no e-mail"></div>
<button class="btn btn-buy btn-block" type="submit" style="margin-top:12px"><?= icon('check') ?>Confirmar pagamento recebido</button>
</form>
<p class="hint">Use só depois de conferir o dinheiro na conta. Pagamentos pelo Mercado Pago são confirmados sozinhos.</p>
<?php if ($online): ?>
<form class="inline-form" method="post" action="<?= e(url('/admin/pedidos/' . $order['id'] . '/sincronizar')) ?>" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--line)">
<?= csrf_field() ?>
<div class="field"><label for="pid">ID do pagamento no Mercado Pago</label><input class="input" id="pid" name="payment_id" value="<?= e((string) $order['gateway_payment_id']) ?>" inputmode="numeric"></div>
<button class="btn btn-outline btn-sm" type="submit"><?= icon('refresh', 'ic-sm') ?>Consultar</button>
</form>
<?php endif; ?>
<?php if ($order['status'] === 'pending'): ?>
<form method="post" action="<?= e(url('/admin/pedidos/' . $order['id'] . '/cancelar')) ?>" data-confirm="Cancelar este pedido?" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--line)">
<?= csrf_field() ?>
<div class="field"><label for="reason">Motivo do cancelamento <small>(opcional)</small></label><input class="input" id="reason" name="reason"></div>
<button class="btn btn-danger btn-sm" type="submit" style="margin-top:10px">Cancelar pedido</button>
</form>
<?php endif; ?>
</div></div>
<?php endif; ?>

<form class="panel" method="post" action="<?= e(url('/admin/pedidos/' . $order['id'] . '/observacoes')) ?>">
<?= csrf_field() ?>
<div class="panel-head"><h2>Observações internas</h2></div>
<div class="panel-body"><textarea class="textarea" name="admin_notes" aria-label="Observações internas"><?= e((string) $order['admin_notes']) ?></textarea><button class="btn btn-outline btn-sm" type="submit" style="margin-top:10px">Salvar</button></div>
</form>

<?php if ($activity): ?>
<div class="panel"><div class="panel-head"><h2>Histórico</h2></div><div class="panel-body">
<ul class="timeline"><?php foreach ($activity as $a): ?><li><div><?= e($a['details'] ?: $a['action']) ?><small><?= e(($a['user_name'] ?: 'Sistema') . ' · ' . date_br($a['created_at'], true)) ?></small></div></li><?php endforeach; ?></ul>
</div></div>
<?php endif; ?>
</aside>
</div>
