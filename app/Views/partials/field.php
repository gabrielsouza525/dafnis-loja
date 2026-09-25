<?php
/**
 * Campo de formulário com label, dica e erro de validação do servidor.
 * @var string $name
 * @var string $label
 * @var string|null $type text|email|tel|password|number|date|textarea|select|money
 * @var mixed $value
 * @var array|null $options  valor => rótulo (select)
 * @var string|null $hint
 * @var bool|null $required
 * @var bool|null $optional  mostra "(opcional)"
 * @var string|null $mask    cpf|cnpj|document|phone|money
 * @var array|null $attrs    atributos extras
 * @var string|null $placeholder
 * @var string|null $class   classe extra no .field
 * @var string|null $scope   vários formulários iguais na página: só o que foi enviado recebe os erros
 */
$type = $type ?? 'text';
$id = $id ?? ('f-' . preg_replace('/[^a-z0-9]+/i', '-', $name));
$useOld = !isset($scope) || (string) old('_scope', '') === (string) $scope;
$error = $useOld ? field_error($name) : null;
$current = $useOld && has_old() ? old($name, $value ?? '') : ($value ?? '');
if (is_array($current)) {
    $current = '';
}
$attrs = $attrs ?? [];
if (!empty($required)) {
    $attrs['required'] = true;
}
if (!empty($mask)) {
    $attrs['data-mask'] = $mask;
    $attrs['inputmode'] ??= $mask === 'money' ? 'decimal' : 'numeric';
}
if (!empty($placeholder)) {
    $attrs['placeholder'] = $placeholder;
}
$describedBy = [];
if (!empty($hint)) {
    $describedBy[] = $id . '-hint';
}
if ($error) {
    $describedBy[] = $id . '-error';
    $attrs['aria-invalid'] = 'true';
}
if ($describedBy) {
    $attrs['aria-describedby'] = implode(' ', $describedBy);
}
$attrHtml = '';
foreach ($attrs as $k => $v) {
    if ($v === false || $v === null) {
        continue;
    }
    $attrHtml .= $v === true ? ' ' . e($k) : ' ' . e($k) . '="' . e($v) . '"';
}
if ($type === 'money' && $current !== '' && is_numeric($current)) {
    $current = number_format((float) $current, 2, ',', '.');
}
$htmlType = $type === 'money' ? 'text' : $type;
?>
<div class="field<?= !empty($class) ? ' ' . e($class) : '' ?>">
<label for="<?= e($id) ?>"><?= e($label) ?><?php if (!empty($optional)): ?> <small>(opcional)</small><?php endif; ?></label>
<?php if ($type === 'textarea'): ?>
<textarea class="textarea" id="<?= e($id) ?>" name="<?= e($name) ?>"<?= $attrHtml ?>><?= e($current) ?></textarea>
<?php elseif ($type === 'select'): ?>
<select class="form-select" id="<?= e($id) ?>" name="<?= e($name) ?>"<?= $attrHtml ?>>
<?php foreach ($options ?? [] as $optValue => $optLabel): ?>
<option value="<?= e($optValue) ?>"<?= selected($optValue, $current) ?>><?= e($optLabel) ?></option>
<?php endforeach; ?>
</select>
<?php elseif ($type === 'password'): ?>
<div class="input-affix">
<input class="input" type="password" id="<?= e($id) ?>" name="<?= e($name) ?>"<?= $attrHtml ?> style="padding-right:48px">
<button type="button" class="password-toggle" aria-label="Mostrar senha" aria-pressed="false" data-password-toggle="<?= e($id) ?>"><?= icon('eye') ?></button>
</div>
<?php else: ?>
<input class="input" type="<?= e($htmlType) ?>" id="<?= e($id) ?>" name="<?= e($name) ?>" value="<?= e($current) ?>"<?= $attrHtml ?>>
<?php endif; ?>
<?php if (!empty($hint)): ?><p class="hint" id="<?= e($id) ?>-hint"><?= e($hint) ?></p><?php endif; ?>
<?php if ($error): ?><p class="field-error" id="<?= e($id) ?>-error"><?= icon('alert') ?><?= e($error) ?></p><?php endif; ?>
</div>
