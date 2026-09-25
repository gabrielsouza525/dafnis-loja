<?php
/** @var array $lines @var array $totals @var array $buyer @var bool $online @var array $methods */
$type = has_old() ? (old('buyer_type', $buyer['buyer_type']) === 'pj' ? 'pj' : 'pf') : $buyer['buyer_type'];
$method = has_old() ? old('payment_method', $buyer['payment_method']) : $buyer['payment_method'];
?>
<div class="screen">
<section class="phead">
<div class="wrap">
<?= partial('crumbs', ['items' => [['Início', '/'], ['Carrinho', '/carrinho'], ['Finalizar compra', null]]]) ?>
<div class="phead-row"><div><h1>Finalizar compra</h1>
<?= partial('steps', ['current' => 2]) ?>
</div></div>
</div>
</section>
<div class="wrap">
<form class="cart-layout" method="post" action="<?= e(url('/checkout')) ?>" data-checkout novalidate>
<?= csrf_field() ?>
<div class="form-card">
<div class="form-sec">
<h2><span>1</span>Dados do comprador</h2>
<div class="seg" role="radiogroup" aria-label="Tipo de comprador">
<label><input type="radio" name="buyer_type" value="pf"<?= checked($type === 'pf') ?> data-buyer-type><span>Pessoa física</span></label>
<label><input type="radio" name="buyer_type" value="pj"<?= checked($type === 'pj') ?> data-buyer-type><span>Empresa</span></label>
</div>
<div class="fields">
<div class="full" data-pj<?= $type === 'pj' ? '' : ' hidden' ?>>
<?= partial('field', ['name' => 'company_name', 'label' => 'Razão social', 'value' => $buyer['company_name'], 'placeholder' => 'Nome da empresa', 'attrs' => ['autocomplete' => 'organization', 'data-pj-required' => true, 'required' => $type === 'pj']]) ?>
</div>
<div data-pj<?= $type === 'pj' ? '' : ' hidden' ?>>
<?= partial('field', ['name' => 'company_document', 'label' => 'CNPJ', 'value' => $buyer['company_document'], 'placeholder' => '00.000.000/0000-00', 'mask' => 'cnpj', 'attrs' => ['data-pj-required' => true, 'required' => $type === 'pj']]) ?>
</div>
<div class="<?= $type === 'pj' ? '' : 'full' ?>" data-name-wrap>
<?= partial('field', ['name' => 'buyer_name', 'label' => $type === 'pj' ? 'Responsável pela compra' : 'Nome completo', 'value' => $buyer['buyer_name'], 'required' => true, 'placeholder' => 'Nome completo', 'attrs' => ['autocomplete' => 'name', 'data-name-label' => true]]) ?>
</div>
<div data-pf<?= $type === 'pf' ? '' : ' hidden' ?>>
<?= partial('field', ['name' => 'buyer_document', 'label' => 'CPF', 'value' => $buyer['buyer_document'], 'placeholder' => '000.000.000-00', 'mask' => 'cpf', 'attrs' => ['data-pf-required' => true, 'required' => $type === 'pf']]) ?>
</div>
<?= partial('field', ['name' => 'buyer_email', 'label' => 'E-mail', 'type' => 'email', 'value' => $buyer['buyer_email'], 'required' => true, 'placeholder' => 'nome@empresa.com.br', 'hint' => 'Enviamos a confirmação do pedido para este e-mail.', 'attrs' => ['autocomplete' => 'email']]) ?>
<?= partial('field', ['name' => 'buyer_phone', 'label' => 'Telefone / WhatsApp', 'type' => 'tel', 'value' => $buyer['buyer_phone'], 'required' => true, 'placeholder' => '(00) 00000-0000', 'mask' => 'phone', 'attrs' => ['autocomplete' => 'tel']]) ?>
</div>
</div>
<div class="form-sec">
<h2><span>2</span>Forma de pagamento</h2>
<div class="pay-opts" role="radiogroup" aria-label="Forma de pagamento">
<?php foreach ($methods as $id => $m): ?>
<label class="pay"><input type="radio" name="payment_method" value="<?= e($id) ?>"<?= checked($method === $id) ?>><span class="pay-top"><?= icon($m['icon']) ?><span class="radio"></span></span><span><strong><?= e($m['label']) ?></strong><small><?= e($m['sub']) ?></small></span></label>
<?php endforeach; ?>
</div>
<?php if ($online): ?>
<div class="note-box info"><?= icon('lock') ?><span>Ao confirmar, você vai para o ambiente seguro do <strong>Mercado Pago</strong> para concluir o pagamento. Os dados do cartão não passam pela nossa loja.</span></div>
<?php else: ?>
<div class="note-box"><?= icon('info') ?><span>O pagamento online está em ativação. Ao confirmar, seu pedido fica registrado como <strong>aguardando pagamento</strong> e a nossa equipe envia as instruções pela forma escolhida. Nenhuma cobrança é feita automaticamente.</span></div>
<?php endif; ?>
</div>
<div class="form-sec">
<label class="check-row"><input type="checkbox" name="accept_terms" value="1" required<?= checked(has_old() && old('accept_terms')) ?>><span>Li e aceito os <a href="<?= e(url('/termos-de-uso')) ?>" target="_blank">termos de uso</a> e a <a href="<?= e(url('/politica-de-privacidade')) ?>" target="_blank">política de privacidade</a>.</span></label>
<?php if ($err = field_error('accept_terms')): ?><p class="field-error"><?= icon('alert') ?><?= e($err) ?></p><?php endif; ?>
<?php if ($err = field_error('coupon') ?: field_error('cart')): ?><div class="note-box err"><?= icon('alert') ?><span><?= e($err) ?> <a href="<?= e(url('/carrinho')) ?>">Voltar ao carrinho</a></span></div><?php endif; ?>
</div>
</div>
<aside class="summary" aria-labelledby="pedido-titulo">
<h2 id="pedido-titulo">Seu pedido</h2>
<div class="sum-items">
<?php foreach ($lines as $line): $c = $line['course']; ?>
<div class="sum-item"><span><?= e($c['nr_number'] && !$c['is_simulator'] ? $c['code_label'] . ' — ' : '') ?><?= e($c['title']) ?><br><small><?= (int) $line['qty'] ?> × <?= money($line['unit_price']) ?></small></span><strong><?= money($line['line_total']) ?></strong></div>
<?php endforeach; ?>
</div>
<div class="sum-row"><span>Subtotal</span><span><?= money($totals['subtotal']) ?></span></div>
<div class="sum-row disc"><span>Desconto<?= $totals['coupon'] ? ' (cupom ' . e($totals['coupon']['code']) . ')' : '' ?></span><span><?= $totals['discount'] > 0 ? '− ' . money($totals['discount']) : 'R$ 0,00' ?></span></div>
<div class="sum-total"><span>Total</span><strong><?= money($totals['total']) ?></strong></div>
<div class="sum-actions">
<button class="btn btn-buy btn-lg btn-block" type="submit" data-submit><?= $online ? 'Ir para o pagamento' : 'Confirmar pedido' ?><?= icon('arrowR') ?></button>
<a class="btn btn-outline btn-block" href="<?= e(url('/carrinho')) ?>">Voltar ao carrinho</a>
</div>
<p class="secure"><?= icon('shield') ?>Seus dados são usados só para emitir o pedido, liberar o acesso e os certificados.</p>
</aside>
</form>
</div>
</div>
