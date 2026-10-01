<?php
/**
 * Nova senha + confirmação, com os requisitos marcados enquanto a pessoa digita (app.js: data-pw-rules).
 * @var string|null $label @var string|null $confirmLabel @var bool|null $autofocus
 */
$attrs = ['autocomplete' => 'new-password', 'minlength' => 8, 'aria-describedby' => 'pw-rules'];
if (!empty($autofocus)) {
    $attrs['autofocus'] = true;
}
?>
<div class="pw-group">
<?= partial('field', ['name' => 'password', 'label' => $label ?? 'Senha', 'type' => 'password', 'required' => true, 'attrs' => $attrs]) ?>
<ul class="pw-rules" id="pw-rules" data-pw-rules="f-password" aria-label="A senha precisa ter">
<li data-rule="len"><?= icon('check', 'ic-sm') ?>8 caracteres ou mais</li>
<li data-rule="letter"><?= icon('check', 'ic-sm') ?>Letras</li>
<li data-rule="digit"><?= icon('check', 'ic-sm') ?>Números</li>
</ul>
</div>
<div class="pw-group">
<?= partial('field', ['name' => 'password_confirmation', 'label' => $confirmLabel ?? 'Confirme a senha', 'type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'new-password', 'aria-describedby' => 'pw-match']]) ?>
<p class="pw-match" id="pw-match" data-pw-match="f-password-confirmation" aria-live="polite" hidden></p>
</div>
