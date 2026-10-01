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
    'matriculas' => ['/admin/matriculas', 'Matrículas', 'award', (int) ($counts['processing'] ?? 0)],
    'cursos' => ['/admin/cursos', 'Cursos', 'book', 0],
    'categorias' => ['/admin/categorias', 'Categorias', 'tag', 0],
    'cupons' => ['/admin/cupons', 'Cupons', 'card', 0],
    'usuarios' => ['/admin/usuarios', 'Usuários', 'users', 0],
    'contatos' => ['/admin/contatos', 'Contatos', 'message', (int) ($counts['contacts'] ?? 0)],
    'configuracoes' => ['/admin/configuracoes', 'Configurações', 'settings', 0],
];
// Menu em grupos (o primeiro não tem título)
$groups = [
    '' => ['painel'],
    'Vendas' => ['pedidos', 'matriculas', 'cupons'],
    'Catálogo' => ['cursos', 'categorias'],
    'Pessoas' => ['usuarios', 'contatos'],
    'Sistema' => ['configuracoes'],
];
$initials = mb_strtoupper(implode('', array_map(static fn ($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim((string) $user['name'])) ?: [], 0, 2))));
$title = ($title ?? 'Painel') . ' — Painel';
$noindex = true;
$css = ['admin.css'];
$scripts = array_merge(['admin.js'], $scripts ?? []);
?>
<!doctype html>
<html lang="pt-BR" data-themeable>
<head>
<?= partial('head', get_defined_vars()) ?>
</head>
<body class="admin">
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<?php if (static_demo()): ?><div class="demobar" role="note"><?= icon('info', 'ic-sm') ?><span><strong>Prévia do painel da equipe.</strong> Dados de exemplo; nesta demonstração os botões não salvam nada.</span></div><?php endif; ?>
<div class="adm">
<aside class="adm-side" id="adm-side" aria-label="Menu do painel">
<a class="logo inv" href="<?= e(url('/admin')) ?>"><?= partial('logo', ['sub' => 'Painel da equipe']) ?></a>
<nav class="adm-nav">
<?php foreach ($groups as $group => $keys): ?>
<?php if ($group !== ''): ?><span class="adm-nav-label"><?= e($group) ?></span><?php endif; ?>
<?php foreach ($keys as $key): [$href, $label, $ic, $count] = $items[$key]; ?>
<a href="<?= e(url($href)) ?>"<?= $section === $key ? ' class="on" aria-current="page"' : '' ?>><?= icon($ic) ?><?= e($label) ?><?php if ($count > 0): ?><span class="count" aria-label="<?= e(pluralize($count, 'pendente', 'pendentes')) ?>"><?= $count ?></span><?php endif; ?></a>
<?php endforeach; ?>
<?php endforeach; ?>
</nav>
<div class="adm-side-foot">
<div class="adm-user"><span class="avatar"><?= e($initials) ?></span><div><strong><?= e($user['name']) ?></strong><small><?= e($user['email']) ?></small></div></div>
<div class="adm-side-links">
<a href="<?= e(url('/')) ?>" target="_blank"><?= icon('external', 'ic-sm') ?>Ver a loja</a>
<a href="<?= e(url('/minha-conta')) ?>"><?= icon('user', 'ic-sm') ?>Minha conta</a>
<form method="post" action="<?= e(url('/sair')) ?>"><?= csrf_field() ?><button type="submit"><?= icon('logout', 'ic-sm') ?>Sair</button></form>
</div>
</div>
</aside>
<div class="adm-main">
<header class="adm-top">
<button class="icon-btn" type="button" aria-label="Abrir menu" aria-controls="adm-side" aria-expanded="false" data-admin-menu><?= icon('menu') ?></button>
<nav class="adm-crumbs" aria-label="Você está em"><a href="<?= e(url('/admin')) ?>">Painel</a><?= icon('chevR', 'ic-sm') ?><span aria-current="page"><?= e($items[$section][1] ?? 'Painel') ?></span></nav>
<div class="adm-top-actions">
<button class="icon-btn theme-btn" type="button" aria-label="Modo escuro" aria-pressed="false" title="Alternar modo escuro" data-theme-toggle hidden><?= icon('moon', 'ic-moon') ?><?= icon('sun', 'ic-sun') ?></button>
<a class="btn btn-outline btn-xs" href="<?= e(url('/')) ?>" target="_blank"><?= icon('external', 'ic-sm') ?>Ver a loja</a>
<a class="adm-me" href="<?= e(url('/minha-conta')) ?>" title="<?= e($user['name'] . ' — minha conta') ?>" aria-label="<?= e('Minha conta (' . $user['name'] . ')') ?>"><span class="avatar"><?= e($initials) ?></span></a>
</div>
</header>
<main class="adm-content" id="conteudo" tabindex="-1">
<?= $content ?>
</main>
</div>
</div>
<?= partial('flash') ?>
</body>
</html>
