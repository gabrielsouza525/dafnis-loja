<?php /** @var list<string> $pending @var array $checks @var list<string>|null $done */ ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Atualizar o banco | Dafnis Treinamentos</title>
<link rel="icon" href="<?= e(url('/favicon.png')) ?>" type="image/png">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script src="<?= e(asset('js/boot.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="err-page">
<main class="auth auth-solo">
<div class="wrap" style="max-width:560px">
<div class="install-brand"><a class="logo inv" href="<?= e(url('/')) ?>"><?= partial('logo') ?></a></div>
<div class="auth-card">
<?php $blocking = array_filter($checks, static fn ($c) => $c['status'] === 'falta'); ?>
<?php if ($done !== null): ?>
<div class="recover-head">
<span class="recover-ic is-ok"><?= icon('check') ?></span>
<div><h2>Banco atualizado</h2><p><?= $done ? 'Aplicado: ' . e(implode(', ', $done)) . '.' : 'Não havia nada pendente.' ?> Por segurança, remova o INSTALL_TOKEN do arquivo .env.</p></div>
</div>
<div class="recover-actions"><a class="btn btn-primary btn-lg btn-block" href="<?= e(url('/')) ?>">Abrir a loja<?= icon('arrowR') ?></a></div>
<?php else: ?>
<div class="recover-head">
<span class="recover-ic"><?= icon('settings') ?></span>
<div><h2>Atualizar o banco</h2><p>Depois de enviar uma versão nova da loja, aplica as mudanças de banco que ela traz.</p></div>
</div>
<?php if ($blocking): ?>
<div class="note-box err" style="margin:0 0 18px;display:block"><strong>Resolva antes no servidor:</strong>
<ul style="margin:8px 0 0;padding-left:18px;list-style:disc"><?php foreach ($blocking as $c): ?><li><strong><?= e($c['label']) ?></strong> — <?= e($c['detail']) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?php if ($pending): ?>
<p style="margin:0 0 14px">Pendente: <strong class="mono"><?= e(implode(', ', $pending)) ?></strong></p>
<form class="auth-form" method="post" action="<?= e(url('/atualizar')) ?>" data-loading-form>
<?= csrf_field() ?>
<?= partial('field', ['name' => 'install_token', 'label' => 'Token', 'type' => 'password', 'required' => true, 'hint' => 'O valor de INSTALL_TOKEN no arquivo .env do servidor.']) ?>
<button class="btn btn-primary btn-lg btn-block" type="submit">Aplicar<?= icon('arrowR') ?></button>
</form>
<?php else: ?>
<div class="note-box ok" style="margin:0"><?= icon('check') ?><span>O banco já está em dia. Remova o INSTALL_TOKEN do arquivo .env.</span></div>
<?php endif; ?>
<?php endif; ?>
</div>
</div>
</main>
</body>
</html>
