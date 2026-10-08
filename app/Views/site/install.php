<?php /** @var bool $done @var array $checks */ ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Instalação | Dafnis Treinamentos</title>
<link rel="icon" href="<?= e(url('/favicon.png')) ?>" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@500;600;700;800&amp;family=IBM+Plex+Sans:wght@400;500;600&amp;family=IBM+Plex+Mono:wght@500;600&amp;display=swap">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script src="<?= e(asset('js/boot.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="err-page">
<main class="auth auth-solo">
<div class="wrap" style="max-width:560px">
<div class="install-brand"><a class="logo inv" href="<?= e(url('/')) ?>"><?= partial('logo') ?></a></div>
<div class="auth-card">
<?php if ($done): ?>
<div class="recover-head">
<span class="recover-ic is-ok"><?= icon('check') ?></span>
<div><h2>Instalação concluída</h2><p>Banco criado, catálogo importado e administrador cadastrado. Por segurança, remova o INSTALL_TOKEN do arquivo .env.<?= App\Services\TwoFactor::teamRequired() ? ' No primeiro acesso ao painel, você ativa a verificação em duas etapas (obrigatória para a equipe).' : '' ?></p></div>
</div>
<div class="recover-actions"><a class="btn btn-primary btn-lg btn-block" href="<?= e(url('/login')) ?>">Entrar no painel<?= icon('arrowR') ?></a></div>
<?php else: ?>
<div class="recover-head">
<span class="recover-ic"><?= icon('settings') ?></span>
<div><h2>Instalar a loja</h2><p>Cria as tabelas, importa o catálogo e cadastra o primeiro administrador.</p></div>
</div>
<?php $problems = array_values(array_filter($checks ?? [], static fn ($c) => $c['status'] !== 'ok')); $blocking = array_filter($problems, static fn ($c) => $c['status'] === 'falta'); ?>
<?php if ($problems): ?>
<div class="note-box<?= $blocking ? ' err' : '' ?>" style="margin:0 0 18px;display:block">
<strong><?= $blocking ? 'Antes de instalar, resolva o que falta no servidor:' : 'Servidor pronto. Pontos de atenção:' ?></strong>
<ul style="margin:8px 0 0;padding-left:18px;list-style:disc">
<?php foreach ($problems as $c): ?><li style="margin:4px 0"><strong><?= e($c['label']) ?></strong><?= $c['status'] === 'falta' ? ' (obrigatório)' : '' ?><?= $c['detail'] !== '' ? ' — ' . e($c['detail']) : '' ?></li><?php endforeach; ?>
</ul>
</div>
<?php else: ?>
<div class="note-box ok" style="margin:0 0 18px"><?= icon('check') ?><span>Servidor pronto: PHP, extensões, pastas e banco conferidos.</span></div>
<?php endif; ?>
<form class="auth-form" method="post" action="<?= e(url('/instalar')) ?>" data-loading-form>
<?= csrf_field() ?>
<p class="install-sec"><span>1</span>Acesso</p>
<?= partial('field', ['name' => 'install_token', 'label' => 'Token de instalação', 'type' => 'password', 'required' => true, 'hint' => 'O valor de INSTALL_TOKEN no arquivo .env do servidor.']) ?>
<p class="install-sec"><span>2</span>Administrador</p>
<?= partial('field', ['name' => 'name', 'label' => 'Seu nome', 'required' => true, 'attrs' => ['autocomplete' => 'name']]) ?>
<?= partial('field', ['name' => 'email', 'label' => 'E-mail do administrador', 'type' => 'email', 'required' => true, 'attrs' => ['autocomplete' => 'email']]) ?>
<?= partial('new-password', ['label' => 'Senha', 'confirm' => false]) ?>
<button class="btn btn-primary btn-lg btn-block" type="submit">Instalar<?= icon('arrowR') ?></button>
</form>
<?php endif; ?>
</div>
</div>
</main>
</body>
</html>
