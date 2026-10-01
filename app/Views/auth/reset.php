<?php /** @var string $token @var bool $valid */ ?>
<div class="auth auth-solo screen">
<div class="wrap" style="max-width:520px">
<div class="auth-card">
<?php if (!$valid): ?>
<div class="recover-head">
<span class="recover-ic is-warn"><?= icon('clock') ?></span>
<div><h2>Link expirado</h2><p>Este link já foi usado ou passou do prazo de 60 minutos. Peça um novo.</p></div>
</div>
<div class="recover-actions">
<a class="btn btn-primary btn-lg btn-block" href="<?= e(url('/esqueci-senha')) ?>">Pedir novo link<?= icon('arrowR') ?></a>
<a class="btn btn-outline btn-block" href="<?= e(url('/login')) ?>">Voltar para o login</a>
</div>
<?php else: ?>
<ol class="steps recover-steps" aria-label="Etapas para trocar a senha">
<li class="step done"><span class="step-n"><?= icon('check') ?></span><span>Pedir o link</span></li>
<li class="step on" aria-current="step"><span class="step-n">2</span><span>Criar a nova senha</span></li>
</ol>
<div class="recover-head">
<span class="recover-ic"><?= icon('lock') ?></span>
<div><h2>Criar nova senha</h2><p>Depois de salvar, você já entra na sua conta.</p></div>
</div>
<form class="auth-form" method="post" action="<?= e(url('/redefinir-senha')) ?>" data-loading-form>
<?= csrf_field() ?>
<input type="hidden" name="token" value="<?= e($token) ?>">
<div class="pw-group">
<?= partial('field', ['name' => 'password', 'label' => 'Nova senha', 'type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'new-password', 'autofocus' => true, 'minlength' => 8, 'aria-describedby' => 'pw-rules']]) ?>
<ul class="pw-rules" id="pw-rules" data-pw-rules="f-password" aria-label="A senha precisa ter">
<li data-rule="len"><?= icon('check', 'ic-sm') ?>8 caracteres ou mais</li>
<li data-rule="letter"><?= icon('check', 'ic-sm') ?>Letras</li>
<li data-rule="digit"><?= icon('check', 'ic-sm') ?>Números</li>
</ul>
</div>
<div class="pw-group">
<?= partial('field', ['name' => 'password_confirmation', 'label' => 'Confirme a nova senha', 'type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'new-password', 'aria-describedby' => 'pw-match']]) ?>
<p class="pw-match" id="pw-match" data-pw-match="f-password-confirmation" aria-live="polite" hidden></p>
</div>
<button class="btn btn-primary btn-lg btn-block" type="submit">Salvar nova senha<?= icon('arrowR') ?></button>
<p class="recover-note"><?= icon('info', 'ic-sm') ?><span>Por segurança, os aparelhos em que você marcou "manter conectado" vão precisar entrar de novo.</span></p>
</form>
<?php endif; ?>
</div>
</div>
</div>
