<?php
/** @var array $user @var array|null $twoFactor ['since', 'left'] quando ativada */
$crumbs = [['Início', '/'], ['Minha conta', '/minha-conta'], ['Meus dados', null]];
?>
<div class="screen">
<?= partial('account-shell-open', get_defined_vars()) ?>
<div class="acc-head"><div><h1>Meus dados</h1><p>Dados pessoais e senha de acesso.</p></div></div>
<form class="panel" method="post" action="<?= e(url('/minha-conta/dados')) ?>" data-loading-form>
<?= csrf_field() ?>
<div class="panel-head"><h2>Dados pessoais</h2></div>
<div class="panel-body">
<div class="fields">
<div class="full"><?= partial('field', ['name' => 'name', 'label' => 'Nome completo', 'value' => $user['name'], 'required' => true, 'attrs' => ['autocomplete' => 'name']]) ?></div>
<?= partial('field', ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'value' => $user['email'], 'required' => true, 'hint' => 'Seus cursos ficam ligados a este e-mail.', 'attrs' => ['autocomplete' => 'email']]) ?>
<?= partial('field', ['name' => 'phone', 'label' => 'Telefone / WhatsApp', 'type' => 'tel', 'value' => $user['phone'] ? phone_display($user['phone']) : '', 'mask' => 'phone', 'optional' => true, 'attrs' => ['autocomplete' => 'tel']]) ?>
<?= partial('field', ['name' => 'document', 'label' => 'CPF', 'value' => $user['document'] ? document_display($user['document']) : '', 'mask' => 'cpf', 'optional' => true, 'hint' => 'Usado nos pedidos como pessoa física e nos certificados.']) ?>
</div>
<button class="btn btn-primary" type="submit" style="margin-top:20px">Salvar dados</button>
</div>
</form>
<form class="panel" method="post" action="<?= e(url('/minha-conta/senha')) ?>" data-loading-form>
<?= csrf_field() ?>
<div class="panel-head"><h2>Alterar senha</h2></div>
<div class="panel-body">
<div class="fields">
<div class="full"><?= partial('field', ['name' => 'current_password', 'label' => 'Senha atual', 'type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'current-password']]) ?></div>
<?= partial('new-password', ['label' => 'Nova senha', 'confirmLabel' => 'Confirme a nova senha']) ?>
</div>
<button class="btn btn-outline" type="submit" style="margin-top:20px">Alterar senha</button>
<p class="hint">Ao alterar a senha, os outros aparelhos conectados precisam entrar de novo.</p>
</div>
</form>
<section class="panel" id="duas-etapas">
<div class="panel-head"><h2>Verificação em duas etapas</h2><?= partial('status', $twoFactor ? ['label' => 'Ativada', 'tone' => 'ok'] : ['label' => 'Desativada', 'tone' => 'muted']) ?></div>
<div class="panel-body">
<?php if (!$twoFactor): ?>
<div class="tf-state">
<span class="tf-state-ic"><?= icon('shield') ?></span>
<div>
<strong>Proteja a sua conta com um código do celular</strong>
<p>Além da senha, pedimos o código de 6 dígitos de um aplicativo autenticador, como o Google Authenticator. Mesmo que alguém descubra a sua senha, não consegue entrar.</p>
<a class="btn btn-primary" href="<?= e(url('/minha-conta/duas-etapas')) ?>"><?= icon('shield', 'ic-sm') ?>Ativar a verificação em duas etapas</a>
</div>
</div>
<?php else: ?>
<div class="tf-state is-on">
<span class="tf-state-ic"><?= icon('shield') ?></span>
<div>
<strong>Ativada desde <?= e(date_br($twoFactor['since'])) ?></strong>
<p>Ao entrar, pedimos o código do aplicativo autenticador. <?= $twoFactor['left'] > 0 ? 'Restam ' . e(pluralize($twoFactor['left'], 'código', 'códigos')) . ' de recuperação.' : '<strong>Não restam códigos de recuperação:</strong> gere novos abaixo.' ?></p>
</div>
</div>
<div class="tf-actions">
<details class="tf-more"<?= old('_scope') === '2fa-codes' ? ' open' : '' ?>>
<summary><?= icon('refresh', 'ic-sm') ?>Gerar novos códigos de recuperação</summary>
<form method="post" action="<?= e(url('/minha-conta/duas-etapas/codigos')) ?>" data-loading-form>
<?= csrf_field() ?>
<input type="hidden" name="_scope" value="2fa-codes">
<p class="hint" style="margin:0 0 12px">Os códigos atuais deixam de funcionar.</p>
<div class="tf-more-row">
<?= partial('field', ['name' => 'current_password', 'scope' => '2fa-codes', 'id' => 'tf-codes-pw', 'label' => 'Senha atual', 'type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'current-password']]) ?>
<button class="btn btn-outline" type="submit">Gerar novos códigos</button>
</div>
</form>
</details>
<details class="tf-more"<?= old('_scope') === '2fa-off' ? ' open' : '' ?>>
<summary><?= icon('close', 'ic-sm') ?>Desativar a verificação em duas etapas</summary>
<form method="post" action="<?= e(url('/minha-conta/duas-etapas/desativar')) ?>" data-loading-form>
<?= csrf_field() ?>
<input type="hidden" name="_scope" value="2fa-off">
<p class="hint" style="margin:0 0 12px">Depois de desativar, basta o e-mail e a senha para entrar. Enviamos um aviso por e-mail.</p>
<div class="fields">
<?= partial('field', ['name' => 'current_password', 'scope' => '2fa-off', 'id' => 'tf-off-pw', 'label' => 'Senha atual', 'type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'current-password']]) ?>
<?= partial('field', ['name' => 'tf_code', 'scope' => '2fa-off', 'id' => 'tf-off-code', 'label' => 'Código do aplicativo ou de recuperação', 'required' => true, 'placeholder' => '000000', 'attrs' => ['autocomplete' => 'one-time-code', 'autocapitalize' => 'off', 'spellcheck' => 'false']]) ?>
</div>
<button class="btn btn-danger" type="submit" style="margin-top:14px">Desativar</button>
</form>
</details>
</div>
<?php endif; ?>
</div>
</section>
<?= partial('account-shell-close') ?>
</div>
