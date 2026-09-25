<?php
/** @var array $enrollments @var array $user */
$crumbs = [['Início', '/'], ['Minha conta', '/minha-conta'], ['Meus cursos', null]];
$groups = [
    'Em andamento' => array_filter($enrollments, static fn ($e) => $e['status'] === 'active'),
    'Acesso em liberação' => array_filter($enrollments, static fn ($e) => $e['status'] === 'processing'),
    'Concluídos' => array_filter($enrollments, static fn ($e) => $e['status'] === 'completed'),
];
?>
<div class="screen">
<?= partial('account-shell-open', get_defined_vars()) ?>
<div class="acc-head"><div><h1>Meus cursos</h1><p>Treinamentos em que você é o participante.</p></div></div>
<?php if (!$enrollments): ?>
<div class="empty">
<span class="empty-ic"><?= icon('book') ?></span>
<h2 style="font-size:20px">Você ainda não tem cursos</h2>
<p>Compre um treinamento ou peça para a sua empresa indicar você como participante usando o e-mail <?= e($user['email']) ?>.</p>
<a class="btn btn-primary" href="<?= e(url('/cursos')) ?>">Explorar cursos</a>
</div>
<?php else: ?>
<?php foreach ($groups as $label => $list): if (!$list) { continue; } ?>
<div class="panel">
<div class="panel-head"><h2><?= e($label) ?></h2><span class="muted" style="font-size:14px"><?= count($list) ?></span></div>
<div class="panel-body"><div class="my-courses"><?php foreach ($list as $e): ?><?= partial('my-course', ['e' => $e]) ?><?php endforeach; ?></div></div>
</div>
<?php endforeach; ?>
<?php endif; ?>
<?= partial('account-shell-close') ?>
</div>
