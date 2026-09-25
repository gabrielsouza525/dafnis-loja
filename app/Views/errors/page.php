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
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($heading) ?> | Dafnis Treinamentos</title>
<link rel="icon" href="<?= e(url('/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@700;800&amp;family=IBM+Plex+Sans:wght@400;600&amp;family=IBM+Plex+Mono:wght@600&amp;display=swap">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<main class="wrap" style="min-height:100vh;display:grid;place-content:center;padding-top:48px;padding-bottom:48px">
<a class="logo" href="<?= e(url('/')) ?>" aria-label="Dafnis — página inicial"><?= partial('logo') ?></a>
<p class="kicker" style="margin-top:32px">Erro <?= (int) $status ?></p>
<h1 style="font-size:40px;letter-spacing:-.03em;margin-top:10px"><?= e($heading) ?></h1>
<p style="color:var(--muted);font-size:17px;margin-top:12px;max-width:520px"><?= e($message) ?></p>
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:28px">
<a class="btn btn-primary" href="<?= e(url('/')) ?>">Ir para o início</a>
<?php if ($status !== 503): ?>
<a class="btn btn-outline" href="<?= e(url('/cursos')) ?>">Ver cursos</a>
<?php else: ?>
<a class="btn btn-outline" href="">Tentar de novo</a>
<?php endif; ?>
</div>
<?php if ($debug): ?>
<pre style="margin-top:28px;white-space:pre-wrap;font-size:12px;color:#B42318;background:#FDECEC;padding:16px;border-radius:10px;overflow:auto;max-width:960px">[DEBUG — APP_DEBUG=true]
<?= e(get_class($debug) . ': ' . $debug->getMessage() . "\n" . $debug->getFile() . ':' . $debug->getLine() . "\n\n" . $debug->getTraceAsString()) ?></pre>
<?php endif; ?>
</main>
</body>
</html>
