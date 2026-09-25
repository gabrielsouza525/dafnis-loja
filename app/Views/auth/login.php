<?php /** @var string|null $volta @var bool $fromCheckout */ ?>
<div class="auth screen">
<div class="wrap">
<div class="auth-grid">
<div class="auth-side">
<div class="kicker"><?= $fromCheckout ? 'Etapa 2 · Identificação' : 'Área do aluno' ?></div>
<h1><?= $fromCheckout ? 'Entre para finalizar a compra' : 'Acesse sua conta' ?></h1>
<p><?= $fromCheckout ? 'Seu carrinho está salvo. Entre ou crie a sua conta para concluir o pedido.' : 'Veja seus cursos, acompanhe o progresso, baixe certificados e gerencie as vagas da sua equipe.' ?></p>
<div class="checks">
<div class="check"><?= icon('check') ?>Cursos adquiridos e progresso</div>
<div class="check"><?= icon('check') ?>Certificados disponíveis para download</div>
<div class="check"><?= icon('check') ?>Pedidos e vagas da sua empresa</div>
</div>
</div>
<div class="auth-card">
<div class="tabs"><a class="on" href="<?= e(url('/login', ['volta' => $volta])) ?>" aria-current="page">Entrar</a><a href="<?= e(url('/cadastro', ['volta' => $volta])) ?>">Criar conta</a></div>
<h2>Entrar</h2>
<p>Use o e-mail e a senha da sua conta.</p>
<form class="auth-form" method="post" action="<?= e(url('/login')) ?>" data-loading-form>
<?= csrf_field() ?>
<?php if ($volta): ?><input type="hidden" name="volta" value="<?= e($volta) ?>"><?php endif; ?>
<?= partial('field', ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'required' => true, 'attrs' => ['autocomplete' => 'email', 'autofocus' => true]]) ?>
<?= partial('field', ['name' => 'password', 'label' => 'Senha', 'type' => 'password', 'required' => true, 'attrs' => ['autocomplete' => 'current-password']]) ?>
<div class="auth-row">
<label class="check-row"><input type="checkbox" name="remember" value="1">Manter conectado</label>
<a href="<?= e(url('/esqueci-senha')) ?>">Esqueci a senha</a>
</div>
<button class="btn btn-primary btn-lg btn-block" type="submit">Entrar<?= icon('arrowR') ?></button>
</form>
<p class="auth-alt">Ainda não tem conta? <a href="<?= e(url('/cadastro', ['volta' => $volta])) ?>">Criar conta</a></p>
</div>
</div>
</div>
</div>
