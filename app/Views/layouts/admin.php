<?php
/**
 * Layout do painel da equipe.
 * @var string $content @var string|null $section item ativo do menu
 */
use App\Core\Database;
use App\Services\Auth;

$user = Auth::user();
$section = $section ?? 'painel';
$counts = Database::first(
    "SELECT (SELECT COUNT(*) FROM orders WHERE status = 'pending') AS pending,
            (SELECT COUNT(*) FROM enrollments WHERE status = 'processing') AS processing,
            (SELECT COUNT(*) FROM contact_requests WHERE status = 'new') AS contacts"
) ?? [];
$items = [
    'painel' => ['/admin', 'Visão geral', 'grid', 0],
    'pedidos' => ['/admin/pedidos', 'Pedidos', 'receipt', (int) ($counts['pending'] ?? 0)],
    'matriculas' => ['/admin/matriculas', 'Matrículas e certificados', 'award', (int) ($counts['processing'] ?? 0)],
    'cursos' => ['/admin/cursos', 'Cursos', 'book', 0],
    'categorias' => ['/admin/categorias', 'Categorias', 'tag', 0],
    'cupons' => ['/admin/cupons', 'Cupons', 'card', 0],
    'usuarios' => ['/admin/usuarios', 'Usuários', 'users', 0],
    'contatos' => ['/admin/contatos', 'Contatos', 'message', (int) ($counts['contacts'] ?? 0)],
    'configuracoes' => ['/admin/configuracoes', 'Configurações', 'settings', 0],
];
$title = ($title ?? 'Painel') . ' — Painel';
$noindex = true;
$css = ['admin.css'];
$scripts = array_merge(['admin.js'], $scripts ?? []);
?>
<!doctype html>
<html lang="pt-BR">
<head>
<?= partial('head', get_defined_vars()) ?>
</head>
<body class="admin">
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<div class="adm">
<aside class="adm-side" id="adm-side" aria-label="Menu do painel">
<a class="logo inv" href="<?= e(url('/admin')) ?>"><?= partial('logo', ['sub' => 'Painel da equipe']) ?></a>
<nav class="adm-nav">
<?php foreach ($items as $key => [$href, $label, $ic, $count]): ?>
<a href="<?= e(url($href)) ?>"<?= $section === $key ? ' class="on" aria-current="page"' : '' ?>><?= icon($ic) ?><?= e($label) ?><?php if ($count > 0): ?><span class="count"><?= $count ?></span><?php endif; ?></a>
<?php endforeach; ?>
<span class="sep"></span>
<a href="<?= e(url('/')) ?>" target="_blank"><?= icon('external') ?>Ver a loja</a>
<a href="<?= e(url('/minha-conta')) ?>"><?= icon('user') ?>Minha conta</a>
</nav>
<div class="adm-side-foot">
<strong><?= e($user['name']) ?></strong><?= e($user['email']) ?>
<form method="post" action="<?= e(url('/sair')) ?>"><?= csrf_field() ?><button type="submit"><?= icon('logout', 'ic-sm') ?>Sair</button></form>
</div>
</aside>
<div class="adm-main">
<header class="adm-top">
<button class="icon-btn" type="button" aria-label="Abrir menu" aria-controls="adm-side" aria-expanded="false" data-admin-menu><?= icon('menu') ?></button>
<span class="adm-top-title"><?= e($items[$section][1] ?? 'Painel') ?></span>
<div class="adm-top-actions"><a class="btn btn-outline btn-xs" href="<?= e(url('/')) ?>" target="_blank"><?= icon('external', 'ic-sm') ?>Loja</a></div>
</header>
<main class="adm-content" id="conteudo" tabindex="-1">
<?= $content ?>
</main>
</div>
</div>
<?= partial('flash') ?>
</body>
</html>
