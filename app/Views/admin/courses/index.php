<?php
/** @var array $page @var string $q @var string $status @var int $category @var array $categories */
$filters = ['todos' => 'Todos', 'ativos' => 'Ativos', 'inativos' => 'Inativos', 'sem-preco' => 'Sob consulta', 'destaques' => 'Destaques / mais vendidos'];
?>
<div class="adm-head"><div><h1>Cursos</h1><p>Catálogo da loja: preços, cargas horárias, destaques e disponibilidade.</p></div>
<div class="adm-actions"><a class="btn btn-navy btn-sm" href="<?= e(url('/admin/cursos/novo')) ?>"><?= icon('plus', 'ic-sm') ?>Novo curso</a></div></div>

<form class="toolbar" method="get" action="<?= e(url('/admin/cursos')) ?>">
<input class="input search" type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por título, NR ou slug" aria-label="Buscar cursos">
<select class="form-select" name="categoria" aria-label="Categoria"><option value="">Todas as categorias</option><?php foreach ($categories as $id => $name): ?><option value="<?= (int) $id ?>"<?= selected($id, $category) ?>><?= e($name) ?></option><?php endforeach; ?></select>
<input type="hidden" name="status" value="<?= e($status) ?>">
<button class="btn btn-outline btn-sm" type="submit">Filtrar</button>
</form>
<div class="chips" style="margin-bottom:16px">
<?php foreach ($filters as $key => $label): ?><a href="<?= e(url('/admin/cursos', ['status' => $key, 'q' => $q, 'categoria' => $category ?: null])) ?>"<?= $status === $key ? ' class="on"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
</div>

<div class="panel">
<div class="table-wrap"><table class="table">
<thead><tr><th>Curso</th><th>Categoria</th><th>Carga</th><th>Modalidade</th><th class="num">Preço</th><th class="num">Vendas</th><th>Situação</th><th class="num">Ações</th></tr></thead>
<tbody>
<?php foreach ($page['rows'] as $c): ?>
<tr<?= (int) $c['is_active'] ? '' : ' class="is-off"' ?>>
<td><a href="<?= e(url('/admin/cursos/' . $c['id'] . '/editar')) ?>"><?= e(($c['code'] ?: ($c['nr_number'] ? 'NR ' . $c['nr_number'] : '')) ? ($c['code'] ?: 'NR ' . $c['nr_number']) . ' — ' : '') ?><?= e($c['title']) ?></a><span class="sub">/cursos/<?= e($c['slug']) ?></span></td>
<td><?= e((string) $c['category_name']) ?></td>
<td><?= e(hours_short($c['hours'])) ?></td>
<td><?= e(modality_label($c['modality'])) ?></td>
<td class="num"><?php if ($c['price'] === null): ?><span class="muted">Sob consulta</span><?php else: ?><?php if ($c['promo_price'] !== null): ?><s class="muted" style="font-size:12.5px"><?= money($c['price']) ?></s><br><?= money($c['promo_price']) ?><?php else: ?><?= money($c['price']) ?><?php endif; ?><?php endif; ?></td>
<td class="num"><?= (int) $c['sold'] ?></td>
<td><?= partial('status', ['label' => (int) $c['is_active'] ? 'Ativo' : 'Inativo', 'tone' => (int) $c['is_active'] ? 'ok' : 'muted']) ?><?php if ((int) $c['is_bestseller']): ?> <span class="pill badge-mais" style="height:22px">Mais vendido</span><?php endif; ?><?php if ($c['featured_order'] !== null): ?> <span class="pill badge-novo" style="height:22px">Destaque <?= (int) $c['featured_order'] ?></span><?php endif; ?></td>
<td><div class="actions">
<a class="btn btn-ghost btn-xs" href="<?= e(url('/admin/cursos/' . $c['id'] . '/editar')) ?>">Editar</a>
<form method="post" action="<?= e(url('/admin/cursos/' . $c['id'] . '/alternar')) ?>"><?= csrf_field() ?><input type="hidden" name="volta" value="lista"><button class="btn btn-outline btn-xs" type="submit"><?= (int) $c['is_active'] ? 'Desativar' : 'Ativar' ?></button></form>
</div></td>
</tr>
<?php endforeach; ?>
<?php if (!$page['rows']): ?><tr><td colspan="8" class="muted">Nenhum curso encontrado.</td></tr><?php endif; ?>
</tbody></table></div>
<?= partial('admin-pager', ['p' => $page]) ?>
</div>
