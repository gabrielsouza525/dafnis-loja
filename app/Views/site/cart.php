<?php /** @var array $lines @var array $totals */ ?>
<div class="screen">
<section class="phead">
<div class="wrap">
<?= partial('crumbs', ['items' => [['Início', '/'], ['Carrinho', null]]]) ?>
<div class="phead-row"><div><h1>Seu carrinho</h1>
<?= partial('steps', ['current' => 1]) ?>
</div></div>
</div>
</section>
<div class="wrap" data-cart-root>
<?= App\Core\View::file('site/partials/cart-body', ['lines' => $lines, 'totals' => $totals]) ?>
</div>
</div>
