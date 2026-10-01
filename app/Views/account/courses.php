<?php
/** @var array $enrollments @var array $user */
$crumbs = [['Início', '/'], ['Minha conta', '/minha-conta'], ['Meus cursos', null]];
$groups = [
    'andamento' => ['Em andamento', 'pulse', array_filter($enrollments, static fn ($e) => $e['status'] === 'active')],
    'liberacao' => ['Acesso em liberação', 'clock', array_filter($enrollments, static fn ($e) => $e['status'] === 'processing')],
    'concluidos' => ['Concluídos', 'award', array_filter($enrollments, static fn ($e) => $e['status'] === 'completed')],
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
<nav class="acc-jump" aria-label="Situação dos cursos">
<?php foreach ($groups as $id => [$label, $ic, $list]): ?>
<a href="#<?= e($id) ?>"<?= $list ? '' : ' aria-disabled="true" tabindex="-1"' ?>><?= icon($ic, 'ic-sm') ?><?= e($label) ?><b><?= count($list) ?></b></a>
<?php endforeach; ?>
</nav>
<?php foreach ($groups as $id => [$label, $ic, $list]): if (!$list) { continue; } ?>
<section class="acc-sec" id="<?= e($id) ?>">
<h2 class="acc-sec-title"><?= e($label) ?><span><?= count($list) ?></span></h2>
<div class="my-courses"><?php foreach ($list as $e): ?><?= partial('my-course', ['e' => $e]) ?><?php endforeach; ?></div>
</section>
<?php endforeach; ?>
<?php endif; ?>
<?= partial('account-shell-close') ?>
</div>
