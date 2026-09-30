<?php
/** @var array $lines @var array $totals @var string|null $coupon_error @var string|null $coupon_input */
use App\Models\Coupon;
use App\Services\Catalog;
use App\Services\Payments\Payments;

$couponError = $coupon_error ?? ($totals['coupon_error'] ?? null) ?? field_error('coupon');
$allCertify = $lines && !array_filter($lines, static fn ($l) => !$l['course']['certificate']);
?>
<?php if (!$lines): ?>
<div class="cart-empty">
<div class="empty">
<span class="empty-ic"><?= icon('cart') ?></span>
<h2 style="font-size:20px">Seu carrinho está vazio</h2>
<p>Explore o catálogo e adicione os treinamentos que você ou sua equipe precisam.</p>
<a class="btn btn-primary" href="<?= e(url('/cursos')) ?>">Explorar cursos<?= icon('arrowR') ?></a>
</div>
<?php $sugg = Catalog::suggestions(4); if ($sugg): ?>
<div class="empty-sugg">
<h3>Treinamentos em destaque</h3>
<div class="grid4"><?php foreach ($sugg as $course): ?><?= partial('course-card', ['course' => $course]) ?><?php endforeach; ?></div>
</div>
<?php endif; ?>
</div>
<?php else: ?>
<div class="cart-layout">
<div>
<div class="cart-list">
<div class="cart-list-head"><h2>Treinamentos</h2><span><?= e(pluralize(count($lines), 'treinamento', 'treinamentos')) ?> · <?= e(pluralize($totals['count'], 'participante', 'participantes')) ?></span></div>
<?php foreach ($lines as $line): $c = $line['course']; ?>
<div class="cart-row" data-cart-row="<?= (int) $c['id'] ?>">
<a class="cart-thumb" href="<?= e($c['url']) ?>" tabindex="-1" aria-hidden="true"><?= partial('cover', ['course' => $c, 'variant' => 'thumb']) ?></a>
<div class="cart-info">
<div class="card-top"><span class="nr-tag"><?= e($c['code_label']) ?></span><span><?= e($c['category_name']) ?></span></div>
<h3><a href="<?= e($c['url']) ?>"><?= e($c['title']) ?></a></h3>
<div class="facts"><span class="fact"><?= icon('clock') ?><?= e($c['hours_label']) ?></span><span class="fact"><?= icon('monitor') ?><?= e($c['modality_label']) ?></span></div>
<p class="cart-unit"><?= money($line['unit_price']) ?> por participante</p>
</div>
<div class="cart-side">
<div class="line-total">
<?php if ($line['line_list'] > $line['line_total']): ?><s><?= money($line['line_list']) ?></s><?php endif; ?>
<strong><?= money($line['line_total']) ?></strong>
</div>
<form class="cart-qty" method="post" action="<?= e(url('/carrinho/atualizar')) ?>" data-cart-form>
<?= csrf_field() ?><input type="hidden" name="course_id" value="<?= (int) $c['id'] ?>">
<span class="cart-qty-label" aria-hidden="true">Participantes</span>
<div class="stepper" data-stepper data-autosubmit>
<button type="button" aria-label="<?= e('Diminuir participantes de ' . $c['code_label']) ?>" data-step="-1"<?= $line['qty'] <= 1 ? ' disabled' : '' ?>><?= icon('minus', 'ic-sm') ?></button>
<input name="qty" type="number" inputmode="numeric" min="1" max="200" value="<?= (int) $line['qty'] ?>" aria-label="<?= e('Participantes em ' . $c['title']) ?>">
<button type="button" aria-label="<?= e('Aumentar participantes de ' . $c['code_label']) ?>" data-step="1"><?= icon('plus', 'ic-sm') ?></button>
</div>
<noscript><button class="btn btn-xs btn-outline" type="submit" style="margin-top:6px">Atualizar</button></noscript>
</form>
<form method="post" action="<?= e(url('/carrinho/remover')) ?>" data-cart-form data-remove>
<?= csrf_field() ?><input type="hidden" name="course_id" value="<?= (int) $c['id'] ?>">
<button class="remove" type="submit" aria-label="<?= e('Remover ' . $c['code_label'] . ' — ' . $c['title'] . ' do carrinho') ?>"><?= icon('trash', 'ic-sm') ?>Remover</button>
</form>
</div>
</div>
<?php endforeach; ?>
</div>
<div class="note-box info cart-note"><?= icon('users') ?><span><strong>Participantes são as pessoas que vão fazer o treinamento.</strong> Depois da confirmação do pagamento, você informa o nome, o e-mail e o CPF de cada um em Minha conta.</span></div>
<a class="text-link back cart-back" href="<?= e(url('/cursos')) ?>"><?= icon('arrowL', 'ic-sm') ?>Continuar comprando</a>
</div>
<aside class="summary" aria-labelledby="resumo-titulo">
<h2 id="resumo-titulo">Resumo do pedido</h2>
<div class="sum-row"><span>Subtotal (<?= e(pluralize($totals['count'], 'participante', 'participantes')) ?>)</span><span><?= money($totals['subtotal']) ?></span></div>
<?php if ($totals['offers'] > 0): ?><div class="sum-row disc"><span>Descontos das ofertas</span><span>− <?= money($totals['offers']) ?></span></div><?php endif; ?>
<?php if ($totals['coupon']): ?>
<div class="sum-row disc"><span>Cupom <?= e($totals['coupon']['code']) ?>
<form method="post" action="<?= e(url('/carrinho/cupom/remover')) ?>" data-cart-form style="display:inline"><?= csrf_field() ?><button class="link-rm" type="submit">remover</button></form></span><span>− <?= money($totals['coupon_discount']) ?></span></div>
<p class="msg-ok"><?= icon('check', 'ic-sm') ?>Cupom <?= e($totals['coupon']['code']) ?> aplicado: <?= e(Coupon::describe($totals['coupon'])) ?>.</p>
<?php else: ?>
<details class="coupon-box"<?= $couponError ? ' open' : '' ?>>
<summary><?= icon('tag', 'ic-sm') ?>Tem um cupom de desconto?</summary>
<form class="coupon" method="post" action="<?= e(url('/carrinho/cupom')) ?>" data-cart-form>
<?= csrf_field() ?>
<label class="sr-only" for="cupom">Cupom de desconto</label>
<input class="input" id="cupom" name="coupon" value="<?= e($coupon_input ?? ($totals['coupon_code'] ?? '')) ?>" placeholder="Código do cupom" autocomplete="off"<?= $couponError ? ' aria-invalid="true" aria-describedby="cupom-erro"' : '' ?>>
<button class="btn btn-outline btn-sm" type="submit">Aplicar</button>
</form>
<?php if ($couponError): ?><p class="msg-err" id="cupom-erro" role="alert"><?= e($couponError) ?></p><?php endif; ?>
</details>
<?php endif; ?>
<div class="sum-total"><span>Total</span><strong><?= money($totals['total']) ?></strong></div>
<?php if ($totals['discount'] > 0): ?><p class="saving">Você economiza <?= money($totals['discount']) ?></p><?php endif; ?>
<div class="sum-actions">
<a class="btn btn-buy btn-lg btn-block" href="<?= e(url('/checkout')) ?>">Finalizar compra<?= icon('arrowR') ?></a>
</div>
<ul class="sum-perks">
<?php if ($allCertify): ?><li><?= icon('award', 'ic-sm') ?>Certificado de conclusão para cada participante</li><?php endif; ?>
<li><?= icon('users', 'ic-sm') ?>Compre para você ou para a sua equipe</li>
<li><?= icon('building', 'ic-sm') ?>Pedido em nome da empresa, com CNPJ</li>
</ul>
<p class="secure"><?= icon('lock') ?><?= Payments::isOnline() ? 'Pagamento em ambiente seguro do Mercado Pago: Pix, cartão ou boleto.' : 'O pedido é registrado e a nossa equipe envia as instruções de pagamento. Nenhuma cobrança é feita automaticamente.' ?></p>
</aside>
</div>
<div class="m-buybar cart-bar">
<div class="mb-price"><small><?= e(pluralize($totals['count'], 'participante', 'participantes')) ?></small><strong><?= money($totals['total']) ?></strong></div>
<a class="btn btn-buy" href="<?= e(url('/checkout')) ?>">Finalizar compra<?= icon('arrowR', 'ic-sm') ?></a>
</div>
<?php endif; ?>
