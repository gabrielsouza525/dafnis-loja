<?php /** @var string $token @var bool $valid */ ?>
<div class="auth screen">
<div class="wrap" style="max-width:520px">
<div class="auth-card">
<?php if (!$valid): ?>
<h2>Link expirado</h2>
<p>Este link já foi usado ou passou do prazo de 60 minutos. Peça um novo.</p>
<a class="btn btn-primary btn-block" style="margin-top:22px" href="<?= e(url('/esqueci-senha')) ?>">Pedir novo link</a>
<?php else: ?>
<h2>Criar nova senha</h2>
<p>Use pelo menos 8 caracteres, combinando letras e números.</p>
<form class="auth-form" method="post" action="<?= e(url('/redefinir-senha')) ?>" data-loading-form>
<?= csrf_field() ?>
<input type="hidden" name="token" value="<?= e($token) ?>">
<?= partial('field', ['name' => 'password', 'label' => 'Nova senha', 'type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'new-password', 'autofocus' => true]]) ?>
<?= partial('field', ['name' => 'password_confirmation', 'label' => 'Confirme a nova senha', 'type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'new-password']]) ?>
<button class="btn btn-primary btn-lg btn-block" type="submit">Salvar nova senha</button>
</form>
<?php endif; ?>
</div>
</div>
</div>
