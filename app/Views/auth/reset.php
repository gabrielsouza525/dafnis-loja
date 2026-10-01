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
<?= partial('new-password', ['label' => 'Nova senha', 'confirmLabel' => 'Confirme a nova senha', 'autofocus' => true]) ?>
<button class="btn btn-primary btn-lg btn-block" type="submit">Salvar nova senha<?= icon('arrowR') ?></button>
<p class="recover-note"><?= icon('info', 'ic-sm') ?><span>Por segurança, os aparelhos em que você marcou "manter conectado" vão precisar entrar de novo.</span></p>
</form>
<?php endif; ?>
</div>
</div>
</div>
