<?php
/** @var array $f @var array $result */
use App\Services\Catalog;

$shown = min($result['total'], $result['page'] * Catalog::PAGE_SIZE);
?>
<div class="results-bar">
<p class="results-count" aria-live="polite"><strong><?= (int) $result['total'] ?></strong> <?= $result['total'] === 1 ? 'curso encontrado' : 'cursos encontrados' ?></p>
<label class="sort">Ordenar por<span class="select-wrap"><select class="select" name="ordem" form="filtros-form" data-sort>
<?php foreach (Catalog::SORTS as $value => $label): ?><option value="<?= e($value) ?>"<?= selected($value, $f['ordem']) ?>><?= e($label) ?></option><?php endforeach; ?>
</select><?= icon('chevD') ?></span></label>
</div>
<?php if ($result['chips']): ?>
<div class="active-chips">
<?php foreach ($result['chips'] as $ch): ?>
<a class="achip" href="<?= e(url('/cursos', $ch['query'])) ?>" aria-label="<?= e('Remover filtro ' . $ch['label']) ?>" data-catalog-link><?= e($ch['label']) ?><?= icon('close') ?></a>
<?php endforeach; ?>
<a class="text-link" style="font-size:13.5px;padding:0 4px" href="<?= e(url('/cursos')) ?>" data-catalog-link>Limpar tudo</a>
</div>
<?php endif; ?>
<?php if ($result['items']): ?>
<div class="cgrid" data-results-grid>
<?php foreach ($result['items'] as $course): ?><?= partial('course-card', ['course' => $course]) ?><?php endforeach; ?>
</div>
<?php if ($result['has_more']): ?>
<div class="load-more" data-load-more>
<p><span data-shown><?= $shown ?></span> de <?= (int) $result['total'] ?> treinamentos</p>
<a class="btn btn-outline" href="<?= e(url('/cursos', Catalog::queryFor($f, ['pagina' => $result['page'] + 1]))) ?>" data-load-more-btn data-next="<?= $result['page'] + 1 ?>">Carregar mais treinamentos</a>
</div>
<?php endif; ?>
<?php if ($result['pages'] > 1): ?>
<nav class="pager" aria-label="Paginação">
<?php for ($p = 1; $p <= $result['pages']; $p++): ?>
<?php if ($p === $result['page']): ?><span aria-current="page"><?= $p ?></span><?php else: ?><a href="<?= e(url('/cursos', Catalog::queryFor($f, ['pagina' => $p]))) ?>"><?= $p ?></a><?php endif; ?>
<?php endfor; ?>
</nav>
<?php endif; ?>
<?php else: ?>
<div class="empty">
<span class="empty-ic"><?= icon('search') ?></span>
<h2 style="font-size:20px">Nenhum treinamento encontrado</h2>
<p>Não encontramos resultados para esta combinação. Tente remover alguns filtros ou pesquisar outro termo.</p>
<div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center">
<a class="btn btn-navy" href="<?= e(url('/cursos')) ?>" data-catalog-link>Limpar filtros e busca</a>
<a class="btn btn-outline" href="<?= e(url('/contato', ['assunto' => 'curso', 'mensagem' => $f['q'] !== '' ? 'Procuro o treinamento: ' . $f['q'] : null])) ?>">Pedir um treinamento</a>
</div>
</div>
<?php $sugg = Catalog::suggestions(3); if ($sugg): ?>
<div class="empty-sugg">
<h3>Treinamentos em destaque</h3>
<div class="cgrid"><?php foreach ($sugg as $course): ?><?= partial('course-card', ['course' => $course]) ?><?php endforeach; ?></div>
</div>
<?php endif; ?>
<?php endif; ?>
