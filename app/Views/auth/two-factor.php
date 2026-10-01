<?php /** @var string $email e-mail mascarado */ ?>
<div class="auth auth-solo screen">
<div class="wrap" style="max-width:520px">
<div class="auth-card">
<ol class="steps recover-steps" aria-label="Etapas para entrar">
<li class="step done"><span class="step-n"><?= icon('check') ?></span><span>E-mail e senha</span></li>
<li class="step on" aria-current="step"><span class="step-n">2</span><span>Código do celular</span></li>
</ol>
<div class="recover-head">
<span class="recover-ic"><?= icon('shield') ?></span>
<div><h2>Verificação em duas etapas</h2><p>Abra o aplicativo autenticador no celular e digite o código de 6 dígitos de <?= e(App\Services\TwoFactor::issuer()) ?>.</p></div>
</div>
<p class="tf-account"><?= icon('user', 'ic-sm') ?>Entrando como <strong><?= e($email) ?></strong></p>
<form class="auth-form" method="post" action="<?= e(url('/login/verificacao')) ?>" data-loading-form>
<?= csrf_field() ?>
<?= partial('otp', ['name' => 'code', 'label' => 'Código do aplicativo', 'hint' => 'O código muda a cada 30 segundos.', 'autofocus' => !field_error('recovery_code'), 'autosubmit' => true]) ?>
<button class="btn btn-primary btn-lg btn-block" type="submit">Verificar e entrar<?= icon('arrowR') ?></button>
</form>
<details class="tf-recovery"<?= field_error('recovery_code') ? ' open' : '' ?>>
<summary><?= icon('lock', 'ic-sm') ?>Está sem o celular? Use um código de recuperação</summary>
<form class="auth-form" method="post" action="<?= e(url('/login/verificacao')) ?>" data-loading-form>
<?= csrf_field() ?>
<?= partial('field', ['name' => 'recovery_code', 'label' => 'Código de recuperação', 'placeholder' => 'xxxxx-xxxxx', 'hint' => 'Um dos códigos que você guardou ao ativar a verificação. Cada um vale uma vez.', 'attrs' => ['autocomplete' => 'off', 'autocapitalize' => 'off', 'spellcheck' => 'false', 'autofocus' => (bool) field_error('recovery_code')]]) ?>
<button class="btn btn-outline btn-block" type="submit">Entrar com o código de recuperação</button>
</form>
</details>
<p class="auth-alt">Perdeu o celular e os códigos? <a href="<?= e(url('/contato', ['assunto' => 'duvida'])) ?>">Fale com a nossa equipe</a><br><a href="<?= e(url('/login')) ?>">Voltar para o login</a></p>
</div>
</div>
</div>
