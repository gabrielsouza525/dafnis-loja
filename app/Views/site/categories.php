<?php
/**
 * Categorias: um cartão por área, com alguns treinamentos, a quantidade e o menor preço.
 * @var array $cards categorias ativas + sample (até 3 cursos) + from_price @var int $total @var array $nrIndex
 */
$withCourses = array_values(array_filter($cards, static fn ($k) => $k['course_count'] > 0));
?>
<div class="screen">
<section class="phead phead-dark">
<div class="grid-bg" aria-hidden="true"></div>
<div class="wrap">
<?= partial('crumbs', ['items' => [['Início', '/'], ['Categorias', null]]]) ?>
<div class="phead-row">
<div><h1>Categorias de treinamento</h1><p><?= e(pluralize(count($withCourses), 'área', 'áreas')) ?> de capacitação e <?= e(pluralize($total, 'treinamento', 'treinamentos')) ?> para profissionais e empresas.</p></div>
<form role="search" action="<?= e(url('/cursos')) ?>" method="get">
<label class="search-field cat-search"><?= icon('search') ?><span class="sr-only">Pesquisar treinamentos</span><input type="search" name="q" placeholder="Pesquise por curso, NR ou palavra-chave..." autocomplete="off" enterkeyhint="search" maxlength="80"></label>
</form>
</div>
<nav class="cat-jump" aria-label="Ir para a categoria">
<?php foreach ($withCourses as $k): ?><a href="#<?= e($k['slug']) ?>"><?= icon($k['icon'], 'ic-sm') ?><?= e($k['name']) ?></a><?php endforeach; ?>
</nav>
</div>
</section>

<div class="wrap">
<div class="catx-grid">
<?php foreach ($cards as $k): $soon = $k['course_count'] === 0; ?>
<article class="catx<?= $soon ? ' is-soon' : '' ?>" id="<?= e($k['slug']) ?>">
<div class="catx-head">
<span class="catx-ic"><?= icon($k['icon']) ?></span>
<div>
<h2><?php if ($soon): ?><?= e($k['name']) ?><?php else: ?><a href="<?= e($k['url']) ?>"><?= e($k['name']) ?></a><?php endif; ?></h2>
<p class="catx-meta"><?php if ($soon): ?><span class="soon">Em breve</span><?php else: ?><?= e(pluralize($k['course_count'], 'treinamento', 'treinamentos')) ?><?php if ($k['from_price'] !== null): ?> · a partir de <strong><?= e(money($k['from_price'])) ?></strong><?php endif; ?><?php endif; ?></p>
</div>
</div>
<?php if ($k['description']): ?><p class="catx-desc"><?= e($k['description']) ?></p><?php endif; ?>
<?php if ($k['sample']): ?>
<ul class="catx-list">
<?php foreach ($k['sample'] as $c): ?>
<li><a href="<?= e($c['url']) ?>"><span class="nr-tag"><?= e($c['code_label']) ?></span><span class="catx-title"><?= e($c['title']) ?></span><span class="catx-h"><?= e($c['hours_label']) ?></span></a></li>
<?php endforeach; ?>
</ul>
<a class="btn btn-outline catx-btn" href="<?= e($k['url']) ?>"><?= e($k['course_count'] > 3 ? 'Ver os ' . $k['course_count'] . ' treinamentos' : 'Ver ' . pluralize($k['course_count'], 'o treinamento', 'os treinamentos')) ?><?= icon('arrowR', 'ic-sm') ?></a>
<?php elseif ($soon): ?>
<p class="catx-desc">Os treinamentos desta área ainda não estão no catálogo. <a href="<?= e(url('/contato', ['assunto' => 'curso'])) ?>">Fale com a nossa equipe</a> se precisar de um deles.</p>
<?php endif; ?>
</article>
<?php endforeach; ?>
</div>

<div class="catx-more">
<?php if ($nrIndex): ?>
<div class="catx-more-card is-dark">
<div class="grid-bg" aria-hidden="true"></div>
<div class="catx-more-in">
<div class="kicker">Por norma</div>
<h2>Prefere buscar pela NR?</h2>
<p>Veja os treinamentos de cada Norma Regulamentadora: formação inicial, reciclagem e simuladores.</p>
<div class="quick">
<?php foreach (array_slice($nrIndex, 0, 10) as $m): ?><a class="chip" href="<?= e($m['url']) ?>"><?= e($m['code']) ?></a><?php endforeach; ?>
</div>
<a class="btn btn-gold" href="<?= e(url('/nrs')) ?>">Ver todas as <?= count($nrIndex) ?> NRs<?= icon('arrowR', 'ic-sm') ?></a>
</div>
</div>
<?php endif; ?>
<div class="catx-more-card">
<div class="catx-more-in">
<div class="kicker">Para empresas</div>
<h2>Treinamento para a equipe toda?</h2>
<p>Compre vagas com o CNPJ da empresa, indique os participantes e reúna os certificados na sua conta.</p>
<a class="btn btn-navy" href="<?= e(url('/empresas')) ?>">Ver soluções para empresas<?= icon('arrowR', 'ic-sm') ?></a>
</div>
</div>
</div>
</div>
</div>
