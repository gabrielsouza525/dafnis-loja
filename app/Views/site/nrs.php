<?php
/**
 * NRs: um cartão por norma, com os tipos de treinamento e o menor preço; filtro por número ou nome ao digitar.
 * @var array $nrIndex nrIndex + inicial, periodico, simulador, from_price @var array $categories
 */
$courses = array_sum(array_column($nrIndex, 'count'));
?>
<div class="screen">
<section class="phead phead-dark">
<div class="grid-bg" aria-hidden="true"></div>
<div class="wrap">
<?= partial('crumbs', ['items' => [['Início', '/'], ['NRs', null]]]) ?>
<div class="phead-row">
<div><h1>Normas Regulamentadoras</h1><p><?= e(pluralize($courses, 'treinamento', 'treinamentos')) ?> em <?= e(pluralize(count($nrIndex), 'NR', 'NRs')) ?>: formação inicial, reciclagem e simuladores. Escolha a norma para ver os cursos.</p></div>
<form role="search" action="<?= e(url('/cursos')) ?>" method="get" data-nr-filter-form>
<label class="search-field cat-search"><?= icon('search') ?><span class="sr-only">Filtrar as NRs</span><input type="search" name="q" placeholder="Número ou nome, ex.: 33 ou incêndio" autocomplete="off" enterkeyhint="search" maxlength="80" data-nr-filter></label>
</form>
</div>
</div>
</section>

<div class="wrap">
<p class="nr-empty" data-nr-empty hidden><?= icon('info', 'ic-sm') ?><span>Nenhuma NR encontrada com esse filtro.</span> <a href="<?= e(url('/cursos')) ?>" data-nr-search-link>Pesquisar no catálogo</a></p>
<div class="nrc-grid">
<?php foreach ($nrIndex as $m): ?>
<a class="nrc" href="<?= e($m['url']) ?>" data-nr-item data-nr="<?= (int) $m['nr'] ?>" data-name="<?= e(normalize_text($m['name'])) ?>">
<div class="nrc-top">
<span class="nrc-badge"><small>NR</small><?= (int) $m['nr'] ?></span>
<span class="nrc-count"><?= e(pluralize($m['count'], 'treinamento', 'treinamentos')) ?></span>
</div>
<h2 class="nrc-name"><?= e($m['name']) ?></h2>
<div class="nrc-tags">
<?php if ($m['inicial']): ?><span>Formação inicial<b><?= $m['inicial'] ?></b></span><?php endif; ?>
<?php if ($m['periodico']): ?><span>Reciclagem<b><?= $m['periodico'] ?></b></span><?php endif; ?>
<?php if ($m['simulador']): ?><span>Simulador<b><?= $m['simulador'] ?></b></span><?php endif; ?>
</div>
<div class="nrc-foot"><span><?php if ($m['from_price'] !== null): ?>a partir de <strong><?= e(money($m['from_price'])) ?></strong><?php else: ?>Sob consulta<?php endif; ?></span><?= icon('arrowR', 'ic-sm') ?></div>
</a>
<?php endforeach; ?>
</div>
<p class="demo-note"><?= icon('info') ?><span>Não encontrou a norma que procura? <a href="<?= e(url('/contato', ['assunto' => 'curso'])) ?>">Fale com a nossa equipe</a>.</span></p>

<div class="catx-more">
<?php if ($categories): ?>
<div class="catx-more-card is-dark">
<div class="grid-bg" aria-hidden="true"></div>
<div class="catx-more-in">
<div class="kicker">Por área</div>
<h2>Prefere navegar por área?</h2>
<p>Segurança do trabalho, primeiros socorros, brigada de incêndio, operação de máquinas e mais.</p>
<div class="cat-jump">
<?php foreach (array_slice($categories, 0, 6) as $k): ?><a href="<?= e($k['url']) ?>"><?= icon($k['icon'], 'ic-sm') ?><?= e($k['name']) ?></a><?php endforeach; ?>
</div>
<a class="btn btn-gold" href="<?= e(url('/categorias')) ?>">Ver todas as categorias<?= icon('arrowR', 'ic-sm') ?></a>
</div>
</div>
<?php endif; ?>
<div class="catx-more-card">
<div class="catx-more-in">
<div class="kicker">Para empresas</div>
<h2>A equipe precisa estar em dia com as normas?</h2>
<p>Compre vagas com o CNPJ da empresa, indique os participantes e reúna os certificados na sua conta.</p>
<a class="btn btn-navy" href="<?= e(url('/empresas')) ?>">Ver soluções para empresas<?= icon('arrowR', 'ic-sm') ?></a>
</div>
</div>
</div>
</div>
</div>
