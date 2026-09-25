<?php /** @var array $nrIndex */ ?>
<div class="screen">
<section class="phead">
<div class="wrap">
<?= partial('crumbs', ['items' => [['Início', '/'], ['NRs', null]]]) ?>
<div class="phead-row"><div><h1>Normas Regulamentadoras</h1><p>Selecione uma NR para ver todos os treinamentos relacionados.</p></div>
<a class="btn btn-outline" href="<?= e(url('/cursos', ['ordem' => 'nr'])) ?>">Ver catálogo por número da NR<?= icon('arrowR', 'ic-sm') ?></a></div>
</div>
</section>
<div class="wrap" style="padding-top:36px;padding-bottom:100px">
<div class="nr-board">
<div class="mega-grid">
<?php foreach ($nrIndex as $m): ?>
<a class="mega-item" href="<?= e($m['url']) ?>"><span class="mega-code"><?= e($m['code']) ?></span><span class="mega-name"><?= e($m['name']) ?><small><?= e(pluralize($m['count'], 'treinamento', 'treinamentos')) ?></small></span></a>
<?php endforeach; ?>
</div>
</div>
<p class="demo-note"><?= icon('info') ?>Não encontrou a norma que procura? <a href="<?= e(url('/contato', ['assunto' => 'curso'])) ?>">Fale com a nossa equipe</a>.</p>
</div>
</div>
