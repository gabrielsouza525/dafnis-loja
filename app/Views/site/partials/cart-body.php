<?php
/** @var array $lines @var array $totals @var string|null $coupon_error @var string|null $coupon_input */
use App\Models\Coupon;
use App\Services\Payments\Payments;

$couponError = $coupon_error ?? ($totals['coupon_error'] ?? null) ?? field_error('coupon');
?>
<?php if (!$lines): ?>
<div style="padding:40px 0 100px">
<div class="empty">
<span class="empty-ic"><?= icon('cart') ?></span>
<h2 style="font-size:20px">Seu carrinho está vazio</h2>
<p>Explore o catálogo e adicione os treinamentos que você ou sua equipe precisam.</p>
<a class="btn btn-primary" href="<?= e(url('/cursos')) ?>">Explorar cursos</a>
</div>
</div>
<?php else: ?>
<div class="cart-layout">
<div>
<div class="cart-list">
<div class="cart-head" aria-hidden="true"><span>Treinamento</span><span>Participantes</span><span>Valor</span><span></span></div>
<?php foreach ($lines as $line): $c = $line['course']; ?>
<div class="cart-row" data-cart-row="<?= (int) $c['id'] ?>">
<div class="cart-prod">
<?= partial('cover', ['course' => $c, 'variant' => 'thumb']) ?>
<div style="min-width:0"><h3><a href="<?= e($c['url']) ?>"><?= e($c['title']) ?></a></h3><p class="cart-meta"><?= e($c['code_label']) ?> · <?= e($c['hours_label']) ?> · <?= e($c['modality_label']) ?></p></div>
</div>
<form class="cart-qty" method="post" action="<?= e(url('/carrinho/atualizar')) ?>" data-cart-form>
<?= csrf_field() ?><input type="hidden" name="course_id" value="<?= (int) $c['id'] ?>">
<div class="stepper" data-stepper data-autosubmit>
<button type="button" aria-label="<?= e('Diminuir participantes de ' . $c['code_label']) ?>" data-step="-1"<?= $line['qty'] <= 1 ? ' disabled' : '' ?>><?= icon('minus', 'ic-sm') ?></button>
<input name="qty" type="number" inputmode="numeric" min="1" max="200" value="<?= (int) $line['qty'] ?>" aria-label="<?= e('Participantes em ' . $c['title']) ?>">
<button type="button" aria-label="<?= e('Aumentar participantes de ' . $c['code_label']) ?>" data-step="1"><?= icon('plus', 'ic-sm') ?></button>
</div>
<noscript><button class="btn btn-xs btn-outline" type="submit" style="margin-top:6px">Atualizar</button></noscript>
</form>
<div class="line-total">
<?php if ($line['line_list'] > $line['line_total']): ?><s><?= money($line['line_list']) ?></s><?php endif; ?>
<strong><?= money($line['line_total']) ?></strong><small><?= money($line['unit_price']) ?> / participante</small>
</div>
<form method="post" action="<?= e(url('/carrinho/remover')) ?>" data-cart-form data-remove>
<?= csrf_field() ?><input type="hidden" name="course_id" value="<?= (int) $c['id'] ?>">
<button class="icon-btn remove" type="submit" aria-label="<?= e('Remover ' . $c['code_label'] . ' — ' . $c['title'] . ' do carrinho') ?>"><?= icon('trash') ?></button>
</form>
</div>
<?php endforeach; ?>
</div>
<div class="cart-under">
<a class="text-link back" href="<?= e(url('/cursos')) ?>"><?= icon('arrowL', 'ic-sm') ?>Continuar comprando</a>
<span class="hint" style="margin:0">Participantes = pessoas que vão fazer o treinamento. Você informa quem são depois do pagamento.</span>
</div>
</div>
<aside class="summary" aria-labelledby="resumo-titulo">
<h2 id="resumo-titulo">Resumo do pedido</h2>
<div class="sum-row"><span>Subtotal (<?= e(pluralize($totals['count'], 'participante', 'participantes')) ?>)</span><span><?= money($totals['subtotal']) ?></span></div>
<?php if ($totals['offers'] > 0): ?><div class="sum-row disc"><span>Descontos das ofertas</span><span>− <?= money($totals['offers']) ?></span></div><?php endif; ?>
<?php if ($totals['coupon']): ?>
<div class="sum-row disc"><span>Cupom <?= e($totals['coupon']['code']) ?>
<form method="post" action="<?= e(url('/carrinho/cupom/remover')) ?>" data-cart-form style="display:inline"><?= csrf_field() ?><button class="link-rm" type="submit">remover</button></form></span><span>− <?= money($totals['coupon_discount']) ?></span></div>
<?php endif; ?>
<?php if (!$totals['offers'] && !$totals['coupon']): ?><div class="sum-row disc"><span>Desconto</span><span>R$ 0,00</span></div><?php endif; ?>
<?php if (!$totals['coupon']): ?>
<form class="coupon" method="post" action="<?= e(url('/carrinho/cupom')) ?>" data-cart-form>
<?= csrf_field() ?>
<label class="sr-only" for="cupom">Cupom de desconto</label>
<input class="input" id="cupom" name="coupon" value="<?= e($coupon_input ?? ($totals['coupon_code'] ?? '')) ?>" placeholder="Cupom de desconto" autocomplete="off"<?= $couponError ? ' aria-invalid="true" aria-describedby="cupom-erro"' : '' ?>>
<button class="btn btn-outline btn-sm" type="submit">Aplicar</button>
</form>
<?php if ($couponError): ?><p class="msg-err" id="cupom-erro" role="alert"><?= e($couponError) ?></p><?php endif; ?>
<?php else: ?>
<p class="msg-ok"><?= icon('check', 'ic-sm') ?>Cupom <?= e($totals['coupon']['code']) ?> aplicado: <?= e(Coupon::describe($totals['coupon'])) ?>.</p>
<?php endif; ?>
<div class="sum-total"><span>Total</span><strong><?= money($totals['total']) ?></strong></div>
<?php if ($totals['discount'] > 0): ?><p class="saving">Você economiza <?= money($totals['discount']) ?></p><?php endif; ?>
<div class="sum-actions">
<a class="btn btn-buy btn-lg btn-block" href="<?= e(url('/checkout')) ?>">Finalizar compra<?= icon('arrowR') ?></a>
<a class="btn btn-outline btn-block" href="<?= e(url('/cursos')) ?>">Continuar comprando</a>
</div>
<p class="secure"><?= icon('lock') ?><?= Payments::isOnline() ? 'Pagamento em ambiente seguro do Mercado Pago: Pix, cartão ou boleto.' : 'O pedido é registrado e a nossa equipe envia as instruções de pagamento. Nenhuma cobrança é feita automaticamente.' ?></p>
</aside>
</div>
<?php endif; ?>
