<?php /** @var bool $done */ ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Instalação | Dafnis Treinamentos</title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<main class="auth" style="min-height:100vh">
<div class="wrap" style="max-width:560px">
<div class="auth-card">
<a class="logo" href="<?= e(url('/')) ?>"><?= partial('logo') ?></a>
<?php if ($done): ?>
<h2 style="margin-top:24px">Instalação concluída</h2>
<p style="color:var(--muted);margin-top:8px">Banco criado, catálogo importado e administrador cadastrado. Por segurança, remova o INSTALL_TOKEN do arquivo .env.</p>
<a class="btn btn-primary btn-block" style="margin-top:22px" href="<?= e(url('/login')) ?>">Entrar no painel</a>
<?php else: ?>
<h2 style="margin-top:24px">Instalar a loja</h2>
<p style="color:var(--muted);margin-top:8px">Cria as tabelas, importa o catálogo e cadastra o primeiro administrador.</p>
<form class="auth-form" method="post" action="<?= e(url('/instalar')) ?>">
<?= csrf_field() ?>
<?= partial('field', ['name' => 'install_token', 'label' => 'Token de instalação (INSTALL_TOKEN do .env)', 'type' => 'password', 'required' => true]) ?>
<?= partial('field', ['name' => 'name', 'label' => 'Seu nome', 'required' => true]) ?>
<?= partial('field', ['name' => 'email', 'label' => 'E-mail do administrador', 'type' => 'email', 'required' => true]) ?>
<?= partial('field', ['name' => 'password', 'label' => 'Senha (mín. 8 caracteres, letras e números)', 'type' => 'password', 'required' => true]) ?>
<button class="btn btn-primary btn-lg btn-block" type="submit">Instalar</button>
</form>
<?php endif; ?>
</div>
</div>
</main>
</body>
</html>
