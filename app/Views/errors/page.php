<?php
/**
 * Página de erro independente do banco (funciona mesmo com o MySQL fora do ar).
 * @var int $status
 * @var string $message
 * @var Throwable|null $debug
 */
$titles = [
    403 => 'Acesso não permitido',
    404 => 'Página não encontrada',
    405 => 'Ação não permitida',
    413 => 'Arquivo grande demais',
    419 => 'Sessão expirada',
    429 => 'Muitas tentativas',
    503 => 'Loja indisponível no momento',
];
$heading = $titles[$status] ?? 'Algo deu errado';
// Texto padrão quando a mensagem só repete o título (ex.: "Página não encontrada.")
$defaults = [
    404 => 'O endereço pode ter mudado ou não existir mais. Procure o treinamento abaixo ou volte ao início.',
    419 => 'Por segurança, a página ficou aberta tempo demais. Volte e tente de novo.',
    503 => 'Estamos com uma instabilidade momentânea. Tente de novo em alguns minutos.',
];
if (trim($message, " .") === '' || trim($message, " .") === $heading) {
    $message = $defaults[$status] ?? $message;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($heading) ?> | Dafnis Treinamentos</title>
<link rel="icon" href="<?= e(url('/favicon.png')) ?>" type="image/png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@700;800&amp;family=IBM+Plex+Sans:wght@400;600&amp;family=IBM+Plex+Mono:wght@600&amp;display=swap">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="err-page">
<main class="err">
<div class="err-in">
<a class="logo inv" href="<?= e(url('/')) ?>" aria-label="Dafnis — página inicial"><?= partial('logo') ?></a>
<div class="err-code" aria-hidden="true"><?= (int) $status ?></div>
<p class="kicker">Erro <?= (int) $status ?></p>
<h1><?= e($heading) ?></h1>
<p class="err-msg"><?= e($message) ?></p>
<?php if (static_demo() && $status === 404): ?><p class="err-msg err-note">Esta é uma prévia estática da loja: algumas telas (como filtros do painel e páginas secundárias) só existem na versão publicada.</p><?php endif; ?>
<?php if ($status === 404): ?>
<form class="err-search" action="<?= e(url('/cursos')) ?>" method="get" role="search">
<label class="search-field"><?= icon('search') ?><span class="sr-only">Pesquisar treinamentos</span><input type="search" name="q" placeholder="Pesquise por curso, NR ou palavra-chave..." autocomplete="off" enterkeyhint="search"></label>
<button class="btn btn-gold" type="submit">Pesquisar</button>
</form>
<?php endif; ?>
<div class="err-actions">
<a class="btn <?= $status === 404 ? 'btn-line-w' : 'btn-gold' ?>" href="<?= e(url('/')) ?>"><?= icon('home', 'ic-sm') ?>Ir para o início</a>
<?php if ($status === 503 || $status === 419): ?>
<a class="btn btn-line-w" href=""><?= icon('refresh', 'ic-sm') ?>Tentar de novo</a>
<?php else: ?>
<a class="btn btn-line-w" href="<?= e(url('/cursos')) ?>"><?= icon('book', 'ic-sm') ?>Ver cursos</a>
<?php endif; ?>
</div>
<?php if ($status === 404): ?>
<nav class="err-links" aria-label="Páginas da loja">
<a href="<?= e(url('/categorias')) ?>">Categorias</a><a href="<?= e(url('/nrs')) ?>">NRs</a><a href="<?= e(url('/empresas')) ?>">Para empresas</a><a href="<?= e(url('/contato')) ?>">Contato</a>
</nav>
<?php endif; ?>
<?php if ($debug): ?>
<pre class="err-debug">[DEBUG — APP_DEBUG=true]
<?= e(get_class($debug) . ': ' . $debug->getMessage() . "\n" . $debug->getFile() . ':' . $debug->getLine() . "\n\n" . $debug->getTraceAsString()) ?></pre>
<?php endif; ?>
</div>
</main>
</body>
</html>
