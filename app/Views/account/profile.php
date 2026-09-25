<?php
/** @var array $user */
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
<?= partial('field', ['name' => 'password', 'label' => 'Nova senha', 'type' => 'password', 'required' => true, 'hint' => 'Mínimo de 8 caracteres, com letras e números.', 'attrs' => ['autocomplete' => 'new-password']]) ?>
<?= partial('field', ['name' => 'password_confirmation', 'label' => 'Confirme a nova senha', 'type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'new-password']]) ?>
</div>
<button class="btn btn-outline" type="submit" style="margin-top:20px">Alterar senha</button>
<p class="hint">Ao alterar a senha, os outros aparelhos conectados precisam entrar de novo.</p>
</div>
</form>
<?= partial('account-shell-close') ?>
</div>
