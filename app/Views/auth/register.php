<?php /** @var string|null $volta @var bool $fromCheckout */ ?>
<div class="auth screen">
<div class="wrap">
<div class="auth-grid">
<div class="auth-side">
<div class="kicker"><?= $fromCheckout ? 'Etapa 2 · Identificação' : 'Área do aluno' ?></div>
<h1>Crie sua conta em menos de um minuto</h1>
<p>Com a conta você acompanha pedidos, indica os participantes de cada vaga e acessa cursos e certificados.</p>
<div class="checks">
<div class="check"><?= icon('check') ?>Compra para você ou para a sua equipe</div>
<div class="check"><?= icon('check') ?>Acompanhamento do progresso dos cursos</div>
<div class="check"><?= icon('check') ?>Certificados reunidos em um só lugar</div>
</div>
</div>
<div class="auth-card">
<div class="tabs"><a href="<?= e(url('/login', ['volta' => $volta])) ?>">Entrar</a><a class="on" href="<?= e(url('/cadastro', ['volta' => $volta])) ?>" aria-current="page">Criar conta</a></div>
<h2>Criar conta</h2>
<p>Os dados de CPF ou CNPJ são pedidos só na hora da compra.</p>
<form class="auth-form" method="post" action="<?= e(url('/cadastro')) ?>" data-loading-form>
<?= csrf_field() ?>
<?php if ($volta): ?><input type="hidden" name="volta" value="<?= e($volta) ?>"><?php endif; ?>
<?= partial('field', ['name' => 'name', 'label' => 'Nome completo', 'required' => true, 'attrs' => ['autocomplete' => 'name', 'autofocus' => true]]) ?>
<?= partial('field', ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'required' => true, 'attrs' => ['autocomplete' => 'email']]) ?>
<?= partial('field', ['name' => 'phone', 'label' => 'Telefone / WhatsApp', 'type' => 'tel', 'required' => true, 'mask' => 'phone', 'placeholder' => '(00) 00000-0000', 'attrs' => ['autocomplete' => 'tel']]) ?>
<?= partial('field', ['name' => 'password', 'label' => 'Senha', 'type' => 'password', 'required' => true, 'hint' => 'Mínimo de 8 caracteres, com letras e números.', 'attrs' => ['autocomplete' => 'new-password', 'minlength' => 8]]) ?>
<?= partial('field', ['name' => 'password_confirmation', 'label' => 'Confirme a senha', 'type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'new-password']]) ?>
<label class="check-row"><input type="checkbox" name="accept_terms" value="1" required><span>Li e aceito os <a href="<?= e(url('/termos-de-uso')) ?>" target="_blank">termos de uso</a> e a <a href="<?= e(url('/politica-de-privacidade')) ?>" target="_blank">política de privacidade</a>.</span></label>
<?php if ($err = field_error('accept_terms')): ?><p class="field-error"><?= icon('alert') ?><?= e($err) ?></p><?php endif; ?>
<button class="btn btn-primary btn-lg btn-block" type="submit">Criar conta<?= icon('arrowR') ?></button>
</form>
<p class="auth-alt">Já tem conta? <a href="<?= e(url('/login', ['volta' => $volta])) ?>">Entrar</a></p>
</div>
</div>
</div>
</div>
