<?php
/** @var array|null $coupon */
$c = $coupon ?? [];
$v = static fn (string $key, mixed $default = '') => $c[$key] ?? $default;
?>
<div class="adm-head"><div><h1><?= $coupon ? 'Cupom ' . e($coupon['code']) : 'Novo cupom' ?></h1></div>
<div class="adm-actions"><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/cupons')) ?>"><?= icon('arrowL', 'ic-sm') ?>Cupons</a></div></div>
<form class="panel" method="post" action="<?= e(url($coupon ? '/admin/cupons/' . $coupon['id'] : '/admin/cupons')) ?>" style="max-width:760px" data-loading-form>
<?= csrf_field() ?>
<div class="panel-body">
<div class="form-grid">
<?= partial('field', ['name' => 'code', 'label' => 'Código', 'value' => $v('code'), 'required' => true, 'hint' => 'Letras, números, - e _. Fica em maiúsculas.', 'attrs' => ['style' => 'text-transform:uppercase']]) ?>
<?= partial('field', ['name' => 'description', 'label' => 'Descrição interna', 'value' => $v('description'), 'optional' => true]) ?>
<?= partial('field', ['name' => 'type', 'label' => 'Tipo', 'type' => 'select', 'value' => $v('type', 'percent'), 'options' => ['percent' => 'Percentual (%)', 'fixed' => 'Valor fixo (R$)']]) ?>
<?= partial('field', ['name' => 'value', 'label' => 'Valor do desconto', 'type' => 'money', 'value' => $v('value'), 'required' => true, 'mask' => 'money']) ?>
<?= partial('field', ['name' => 'min_subtotal', 'label' => 'Compra mínima', 'type' => 'money', 'value' => $v('min_subtotal'), 'optional' => true, 'mask' => 'money']) ?>
<?= partial('field', ['name' => 'max_uses', 'label' => 'Limite de usos', 'type' => 'number', 'value' => $v('max_uses'), 'optional' => true, 'attrs' => ['min' => 1]]) ?>
<?= partial('field', ['name' => 'starts_at', 'label' => 'Válido a partir de', 'type' => 'date', 'value' => $v('starts_at') ? substr((string) $v('starts_at'), 0, 10) : '', 'optional' => true]) ?>
<?= partial('field', ['name' => 'ends_at', 'label' => 'Válido até', 'type' => 'date', 'value' => $v('ends_at') ? substr((string) $v('ends_at'), 0, 10) : '', 'optional' => true]) ?>
<div class="full"><label class="switch"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1"<?= checked($v('is_active', 1)) ?>><span>Ativo</span></label></div>
</div>
<div style="display:flex;gap:8px;margin-top:18px;flex-wrap:wrap">
<button class="btn btn-navy" type="submit"><?= $coupon ? 'Salvar cupom' : 'Criar cupom' ?></button>
</div>
</div>
</form>
<?php if ($coupon): ?>
<form method="post" action="<?= e(url('/admin/cupons/' . $coupon['id'] . '/excluir')) ?>" data-confirm="Excluir o cupom <?= e($coupon['code']) ?>?" style="margin-top:16px"><?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit"><?= icon('trash', 'ic-sm') ?>Excluir cupom</button></form>
<?php endif; ?>
