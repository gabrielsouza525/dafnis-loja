<?php
/**
 * Abre a área do aluno: topo escuro com a saudação e as abas, depois o conteúdo.
 * @var array $user @var string $active @var int $awaitingSeats @var array $crumbs
 */
$links = [
    'painel' => ['/minha-conta', 'Painel', 'grid'],
    'cursos' => ['/minha-conta/cursos', 'Meus cursos', 'book'],
    'certificados' => ['/minha-conta/certificados', 'Certificados', 'award'],
    'pedidos' => ['/minha-conta/pedidos', 'Pedidos e vagas', 'receipt'],
    'dados' => ['/minha-conta/dados', 'Meus dados', 'user'],
];
?>
<section class="phead phead-dark acc-hero">
<div class="grid-bg" aria-hidden="true"></div>
<div class="wrap">
<?= partial('crumbs', ['items' => $crumbs]) ?>
<div class="acc-hero-row">
<span class="avatar acc-avatar" aria-hidden="true"><?= e(initials($user['name'])) ?></span>
<div class="acc-hero-id">
<div class="kicker">Área do aluno</div>
<p class="acc-hello">Olá, <?= e(first_name($user['name'])) ?></p>
<p class="acc-mail"><?= e($user['email']) ?></p>
</div>
<div class="acc-hero-actions">
<?php if (App\Services\Auth::isAdmin()): ?><a class="btn btn-line-w btn-sm" href="<?= e(url('/admin')) ?>"><?= icon('settings', 'ic-sm') ?>Painel da equipe</a><?php endif; ?>
<form method="post" action="<?= e(url('/sair')) ?>"><?= csrf_field() ?><button class="btn btn-line-w btn-sm" type="submit"><?= icon('logout', 'ic-sm') ?>Sair</button></form>
</div>
</div>
<nav class="acc-tabs" aria-label="Minha conta">
<?php foreach ($links as $key => [$href, $label, $ic]): ?>
<a href="<?= e(url($href)) ?>"<?= $active === $key ? ' class="on" aria-current="page"' : '' ?>><?= icon($ic, 'ic-sm') ?><?= e($label) ?><?php if ($key === 'pedidos' && $awaitingSeats > 0): ?><span class="count" title="Vagas aguardando participante" aria-label="<?= e(pluralize($awaitingSeats, 'vaga aguardando participante', 'vagas aguardando participante')) ?>"><?= (int) $awaitingSeats ?></span><?php endif; ?></a>
<?php endforeach; ?>
</nav>
</div>
</section>
<div class="wrap">
<div class="acc-main">
