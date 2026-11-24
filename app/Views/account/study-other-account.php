<?php
/** @var array $user @var string $volta caminho do curso @var string|null $adminUrl matrícula no painel (só equipe) */
$crumbs = [['Início', '/'], ['Minha conta', '/minha-conta'], ['Meus cursos', '/minha-conta/cursos'], ['Treinamento', null]];
?>
<div class="screen">
<?= partial('account-shell-open', get_defined_vars()) ?>
<div class="empty">
<span class="empty-ic"><?= icon('user') ?></span>
<h2 style="font-size:20px">Este treinamento não está nesta conta</h2>
<p>Você entrou como <strong><?= e($user['email']) ?></strong>. O curso só abre na conta com o e-mail que recebeu o aviso de acesso liberado.</p>
<p>Saia e entre com esse e-mail. Se ainda não tem conta, crie uma com ele.</p>
<form method="post" action="<?= e(url('/sair')) ?>"><?= csrf_field() ?><input type="hidden" name="volta" value="<?= e($volta) ?>"><button class="btn btn-primary" type="submit"><?= icon('logout', 'ic-sm') ?>Sair e entrar com o outro e-mail</button></form>
<a class="btn btn-outline btn-sm" href="<?= e(url('/minha-conta/cursos')) ?>">Ver os cursos desta conta</a>
<?php if ($adminUrl): ?><a class="btn btn-ghost btn-sm" href="<?= e($adminUrl) ?>"><?= icon('settings', 'ic-sm') ?>Ver a matrícula no painel</a><?php endif; ?>
</div>
<?= partial('account-shell-close') ?>
</div>
