<?php
/**
 * Card de curso (home, catálogo, relacionados).
 * @var array $course
 * @var bool|null $showDesc
 * @var string|null $heading  h2 ou h3
 */
$c = $course;
$heading = $heading ?? 'h3';
?>
<article class="card">
<?= partial('cover', ['course' => $c, 'tag' => 'a', 'badge' => true]) ?>
<div class="card-body">
<div class="card-top"><span class="nr-tag"><?= e($c['code_label']) ?></span><span><?= e($c['category_name']) ?></span></div>
<<?= $heading ?> class="card-title"><a href="<?= e($c['url']) ?>"><?= e($c['title']) ?></a></<?= $heading ?>>
<?php if (($showDesc ?? true) && $c['summary']): ?><p class="card-desc"><?= e($c['summary']) ?></p><?php endif; ?>
<div class="facts"><span class="fact"><?= icon('clock') ?><?= e($c['hours_label']) ?></span><span class="fact"><?= icon('monitor') ?><?= e($c['modality_label']) ?></span></div>
<div class="price-row"><div>
<?php if (!$c['has_price']): ?>
<div class="price-consult">Sob consulta</div><div class="price-note">Valor conforme a sua demanda</div>
<?php else: ?>
<?php if ($c['old_price']): ?><div><span class="price-old"><?= money($c['old_price']) ?></span><span class="pct">-<?= (int) $c['discount_pct'] ?>%</span></div><?php endif; ?>
<div class="price"><?= money($c['final_price']) ?></div>
<?php endif; ?>
</div></div>
<div class="card-actions">
<a class="btn btn-ghost" href="<?= e($c['url']) ?>">Ver curso</a>
<?php if ($c['has_price']): ?>
<form method="post" action="<?= e(url('/carrinho/adicionar')) ?>" data-add-to-cart>
<?= csrf_field() ?><input type="hidden" name="course_id" value="<?= (int) $c['id'] ?>">
<button class="btn btn-buy" type="submit" aria-label="<?= e('Comprar ' . $c['display_title']) ?>">Comprar</button>
</form>
<?php else: ?>
<a class="btn btn-navy" href="<?= e(url('/contato', ['assunto' => 'curso', 'curso' => $c['slug']])) ?>">Consultar</a>
<?php endif; ?>
</div>
</div>
</article>
