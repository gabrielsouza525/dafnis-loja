<?php
/** @var string $secret @var string $qr @var string $issuer @var array $user */
$crumbs = [['Início', '/'], ['Minha conta', '/minha-conta'], ['Meus dados', '/minha-conta/dados'], ['Verificação em duas etapas', null]];
?>
<div class="screen">
<?= partial('account-shell-open', get_defined_vars()) ?>
<div class="acc-head"><div><h1>Ativar a verificação em duas etapas</h1><p>Além da senha, pedimos um código do seu celular para entrar na conta.</p></div></div>
<div class="tf-setup">
<form class="form-card" method="post" action="<?= e(url('/minha-conta/duas-etapas')) ?>" data-loading-form>
<?= csrf_field() ?>
<div class="form-sec">
<h2><span>1</span>Instale um aplicativo autenticador</h2>
<p>Use o Google Authenticator, o Microsoft Authenticator ou outro de sua preferência. Eles são gratuitos na App Store e no Google Play.</p>
</div>
<div class="form-sec">
<h2><span>2</span>Leia o QR code com o aplicativo</h2>
<div class="tf-qr-row">
<div class="tf-qr"><?= $qr ?></div>
<div class="tf-key">
<p>No aplicativo, toque em <strong>+</strong> e escolha ler um QR code.</p>
<p class="tf-key-label">Não consegue ler? Digite esta chave:</p>
<code class="tf-secret" id="tf-secret" data-copy-text="<?= e($secret) ?>"><?= e(trim(chunk_split($secret, 4, ' '))) ?></code>
<button class="btn btn-outline btn-xs" type="button" data-copy="tf-secret" data-copied="Chave copiada." hidden><?= icon('clipboard', 'ic-sm') ?>Copiar a chave</button>
<dl class="tf-meta"><div><dt>Conta</dt><dd><?= e($issuer) ?> · <?= e($user['email']) ?></dd></div><div><dt>Tipo</dt><dd>Baseado em tempo</dd></div></dl>
</div>
</div>
</div>
<div class="form-sec">
<h2><span>3</span>Digite o código que aparece no aplicativo</h2>
<div class="tf-confirm">
<?= partial('otp', ['name' => 'code', 'label' => 'Código de 6 dígitos', 'hint' => 'O código muda a cada 30 segundos.', 'autosubmit' => true]) ?>
<button class="btn btn-primary btn-lg" type="submit">Ativar a verificação<?= icon('arrowR') ?></button>
</div>
</div>
</form>
<aside class="contact-aside">
<div class="help contact-steps">
<h3>Como funciona</h3>
<ol>
<li><span>1</span><div><strong>Você entra com a senha</strong><small>Como sempre, com o seu e-mail e a senha.</small></div></li>
<li><span>2</span><div><strong>Digita o código do celular</strong><small>O aplicativo mostra um código novo a cada 30 segundos, mesmo sem internet.</small></div></li>
<li><span>3</span><div><strong>Guarda os códigos de recuperação</strong><small>Eles servem para entrar se você perder o celular.</small></div></li>
</ol>
</div>
<div class="help">
<h3>Por que ativar?</h3>
<p>Mesmo que alguém descubra a sua senha, não consegue entrar sem o código do seu celular.</p>
<a class="text-link" href="<?= e(url('/minha-conta/dados')) ?>"><?= icon('arrowL', 'ic-sm') ?>Agora não</a>
</div>
</aside>
</div>
<?= partial('account-shell-close') ?>
</div>
