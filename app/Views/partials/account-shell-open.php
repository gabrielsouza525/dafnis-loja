<?php
/** Abre a grade da área do aluno. @var array $user @var string $active @var int $awaitingSeats @var array $crumbs */
$links = [
    'painel' => ['/minha-conta', 'Painel', 'grid'],
    'cursos' => ['/minha-conta/cursos', 'Meus cursos', 'book'],
    'certificados' => ['/minha-conta/certificados', 'Certificados', 'award'],
    'pedidos' => ['/minha-conta/pedidos', 'Pedidos e vagas', 'receipt'],
    'dados' => ['/minha-conta/dados', 'Meus dados', 'user'],
];
?>
<section class="phead" style="padding:22px 0 26px">
<div class="wrap"><?= partial('crumbs', ['items' => $crumbs]) ?></div>
</section>
<div class="wrap">
<div class="acc-layout">
<nav class="acc-nav" aria-label="Minha conta">
<div class="acc-user"><span class="avatar" aria-hidden="true"><?= e(initials($user['name'])) ?></span><div><strong><?= e($user['name']) ?></strong><small><?= e($user['email']) ?></small></div></div>
<?php foreach ($links as $key => [$href, $label, $ic]): ?>
<a href="<?= e(url($href)) ?>"<?= $active === $key ? ' class="on" aria-current="page"' : '' ?>><?= icon($ic) ?><?= e($label) ?><?php if ($key === 'pedidos' && $awaitingSeats > 0): ?><span class="count" title="Vagas aguardando participante"><?= (int) $awaitingSeats ?></span><?php endif; ?></a>
<?php endforeach; ?>
<?php if (App\Services\Auth::isAdmin()): ?><a href="<?= e(url('/admin')) ?>"><?= icon('settings') ?>Painel da equipe</a><?php endif; ?>
<form method="post" action="<?= e(url('/sair')) ?>"><?= csrf_field() ?><button type="submit"><?= icon('logout') ?>Sair</button></form>
</nav>
<div class="acc-main">
