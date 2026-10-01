<?php /** @var bool $sent */ ?>
<div class="auth auth-solo screen">
<div class="wrap" style="max-width:520px">
<div class="auth-card">
<ol class="steps recover-steps" aria-label="Etapas para trocar a senha">
<?php if ($sent): ?>
<li class="step done"><span class="step-n"><?= icon('check') ?></span><span>Pedir o link</span></li>
<li class="step"><span class="step-n">2</span><span>Criar a nova senha</span></li>
<?php else: ?>
<li class="step on" aria-current="step"><span class="step-n">1</span><span>Pedir o link</span></li>
<li class="step"><span class="step-n">2</span><span>Criar a nova senha</span></li>
<?php endif; ?>
</ol>
<?php if ($sent): ?>
<div class="recover-head">
<span class="recover-ic is-ok"><?= icon('mail') ?></span>
<div><h2>Confira o seu e-mail</h2><p>Se existir uma conta com esse e-mail, enviamos um link para criar uma nova senha.</p></div>
</div>
<ul class="recover-tips">
<li><?= icon('clock') ?><span>O link vale por 60 minutos e só pode ser usado uma vez.</span></li>
<li><?= icon('mail') ?><span>Não chegou? Veja a caixa de spam ou <a href="<?= e(url('/esqueci-senha')) ?>">peça de novo</a>.</span></li>
</ul>
<div class="recover-actions">
<a class="btn btn-outline btn-block" href="<?= e(url('/login')) ?>">Voltar para o login</a>
</div>
<?php else: ?>
<div class="recover-head">
<span class="recover-ic"><?= icon('lock') ?></span>
<div><h2>Recuperar senha</h2><p>Informe o e-mail da sua conta e enviaremos um link para criar uma nova senha.</p></div>
</div>
<form class="auth-form" method="post" action="<?= e(url('/esqueci-senha')) ?>" data-loading-form>
<?= csrf_field() ?>
<?= partial('field', ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'required' => true, 'attrs' => ['autocomplete' => 'email', 'autofocus' => true]]) ?>
<button class="btn btn-primary btn-lg btn-block" type="submit">Enviar link<?= icon('arrowR') ?></button>
</form>
<p class="auth-alt">Lembrou a senha? <a href="<?= e(url('/login')) ?>">Entrar</a></p>
<?php endif; ?>
</div>
</div>
</div>
