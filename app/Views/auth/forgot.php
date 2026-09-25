<?php /** @var bool $sent */ ?>
<div class="auth screen">
<div class="wrap" style="max-width:520px">
<div class="auth-card">
<?php if ($sent): ?>
<span class="empty-ic" style="background:var(--green-tint);color:var(--green-dk)"><?= icon('mail') ?></span>
<h2 style="margin-top:14px">Confira o seu e-mail</h2>
<p>Se existir uma conta com esse e-mail, enviamos um link para criar uma nova senha. O link vale por 60 minutos.</p>
<a class="btn btn-outline btn-block" style="margin-top:22px" href="<?= e(url('/login')) ?>">Voltar para o login</a>
<?php else: ?>
<h2>Recuperar senha</h2>
<p>Informe o e-mail da sua conta e enviaremos um link para criar uma nova senha.</p>
<form class="auth-form" method="post" action="<?= e(url('/esqueci-senha')) ?>" data-loading-form>
<?= csrf_field() ?>
<?= partial('field', ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'required' => true, 'attrs' => ['autocomplete' => 'email', 'autofocus' => true]]) ?>
<button class="btn btn-primary btn-lg btn-block" type="submit">Enviar link</button>
</form>
<p class="auth-alt"><a href="<?= e(url('/login')) ?>">Voltar para o login</a></p>
<?php endif; ?>
</div>
</div>
</div>
