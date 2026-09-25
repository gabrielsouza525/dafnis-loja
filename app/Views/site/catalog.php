<?php
/** @var array $f @var array $result @var array $context @var array $query */
use App\Core\View;
use App\Services\Catalog;
?>
<div class="screen">
<section class="phead">
<div class="wrap">
<?= partial('crumbs', ['items' => $context['crumbs']]) ?>
<div class="phead-row">
<div><h1 data-catalog-heading><?= e($context['heading']) ?></h1><p><?= e($context['lead']) ?></p></div>
<div role="search">
<label class="search-field cat-search" id="busca"><?= icon('search') ?><span class="sr-only">Pesquisar no catálogo</span><input type="search" name="q" form="filtros-form" value="<?= e($f['q']) ?>" placeholder="Pesquise por curso, NR ou palavra-chave..." autocomplete="off" enterkeyhint="search" data-catalog-search maxlength="80"><a class="icon-btn" href="<?= e(url('/cursos', Catalog::queryFor($f, ['q' => '', 'pagina' => 1]))) ?>" style="width:36px;height:36px" aria-label="Limpar busca" data-clear-search<?= $f['q'] === '' ? ' hidden' : '' ?>><?= icon('close', 'ic-sm') ?></a></label>
</div>
</div>
</div>
</section>
<div class="wrap">
<div class="cat-layout">
<div class="f-backdrop-root" data-filters-backdrop hidden><button class="backdrop f-backdrop" type="button" aria-label="Fechar filtros" data-filters-close tabindex="-1"></button></div>
<aside class="filters" id="filtros" aria-label="Filtros" data-filters>
<div class="sheet-head"><h2>Filtrar cursos</h2><button class="icon-btn" type="button" aria-label="Fechar filtros" data-filters-close><?= icon('close') ?></button></div>
<div class="f-head"><h2><?= icon('filter') ?>Filtros</h2><a class="f-clear" href="<?= e(url('/cursos', array_filter(['q' => $f['q']]))) ?>" data-clear-filters<?= $result['active_filters'] === 0 ? ' aria-disabled="true"' : '' ?>>Limpar filtros</a></div>
<form class="filters-body" id="filtros-form" action="<?= e(url('/cursos')) ?>" method="get" data-filters-form>
<div data-filters-body>
<?= View::file('site/partials/catalog-filters', ['f' => $f, 'result' => $result]) ?>
</div>
<button class="btn btn-navy btn-block f-apply" type="submit">Aplicar filtros</button>
</form>
<div class="sheet-foot"><a class="btn btn-outline" href="<?= e(url('/cursos', array_filter(['q' => $f['q']]))) ?>" data-clear-filters>Limpar</a><button class="btn btn-navy" type="button" data-filters-close>Ver <span data-result-count><?= (int) $result['total'] ?></span> cursos</button></div>
</aside>
<div>
<div class="m-toolbar">
<button class="btn btn-outline btn-sm" type="button" data-filters-open aria-controls="filtros" aria-expanded="false"><?= icon('filter', 'ic-sm') ?>Filtrar cursos<span class="filter-count" data-filter-count<?= $result['active_filters'] ? '' : ' hidden' ?>><?= (int) $result['active_filters'] ?></span></button>
<span class="select-wrap"><select class="select" name="ordem" form="filtros-form" aria-label="Ordenar" data-sort>
<?php foreach (Catalog::SORTS as $value => $label): ?><option value="<?= e($value) ?>"<?= selected($value, $f['ordem']) ?>><?= e($label) ?></option><?php endforeach; ?>
</select><?= icon('chevD') ?></span>
</div>
<div class="results" id="resultados" data-results>
<?= View::file('site/partials/catalog-results', ['f' => $f, 'result' => $result]) ?>
</div>
</div>
</div>
</div>
<template id="skeleton-template">
<div class="cgrid skel-grid" aria-hidden="true">
<?php for ($i = 0; $i < 6; $i++): ?><div class="skel"><div class="skel-cover shim"></div><div class="skel-line shim w40"></div><div class="skel-line shim w90"></div><div class="skel-line shim w70"></div></div><?php endfor; ?>
</div>
</template>
</div>
