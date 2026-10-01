<?php
/**
 * Nova senha (+ confirmação), com o medidor de força: quatro barras, o rótulo (fraca a forte), o aviso de senha
 * fácil de adivinhar e os itens marcados enquanto a pessoa digita (app.js: data-pw-strength).
 * Adaptado do componente PasswordStrength (React). Os dois primeiros itens são as regras do servidor; os outros, recomendações.
 * @var string|null $label @var string|null $confirmLabel @var bool|null $autofocus @var bool|null $confirm false = sem confirmação
 */
$attrs = ['autocomplete' => 'new-password', 'minlength' => 8, 'spellcheck' => 'false', 'aria-describedby' => 'pw-strength'];
if (!empty($autofocus)) {
    $attrs['autofocus'] = true;
}
$labels = ['Vazia', 'Fraca', 'Razoável', 'Boa', 'Forte'];
$rules = [
    'len' => ['8 caracteres ou mais', false],
    'mix' => ['Letras e números', false],
    'case' => ['Maiúsculas e minúsculas', true],
    'symbol' => ['Um símbolo, como ! ou #', true],
];
$check = '<svg viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="M2 6.2 4.7 8.9 10 3.3" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>';
?>
<div class="pw-group">
<?= partial('field', ['name' => 'password', 'label' => $label ?? 'Senha', 'type' => 'password', 'required' => true, 'attrs' => $attrs]) ?>
<div class="pw-strength" id="pw-strength" data-pw-strength="f-password" data-tone="none">
<div class="pw-meter" role="meter" aria-label="Força da senha" aria-valuemin="0" aria-valuemax="4" aria-valuenow="0" aria-valuetext="Vazia" data-pw-meter>
<?php for ($i = 0; $i < 4; $i++): ?><span><i></i></span><?php endfor; ?>
</div>
<div class="pw-meta" aria-hidden="true">
<span class="pw-label"><?php foreach ($labels as $i => $text): ?><span data-score="<?= $i ?>"<?= $i === 0 ? ' class="on"' : '' ?>><?= e($text) ?></span><?php endforeach; ?></span>
<span class="pw-guess">Fácil de adivinhar</span>
</div>
<ul class="pw-rules" aria-label="Itens da senha">
<?php foreach ($rules as $id => [$text, $optional]): ?>
<li data-rule="<?= e($id) ?>"><span class="pw-box"><?= $check ?></span><span><?= e($text) ?><?php if ($optional): ?> <small>recomendado</small><?php endif; ?></span><span class="sr-only" data-rule-state>não cumprido</span></li>
<?php endforeach; ?>
</ul>
<p class="sr-only" aria-live="polite" data-pw-announce></p>
</div>
</div>
<?php if ($confirm ?? true): ?>
<div class="pw-group">
<?= partial('field', ['name' => 'password_confirmation', 'label' => $confirmLabel ?? 'Confirme a senha', 'type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'new-password', 'aria-describedby' => 'pw-match']]) ?>
<p class="pw-match" id="pw-match" data-pw-match="f-password-confirmation" aria-live="polite" hidden></p>
</div>
<?php endif; ?>
