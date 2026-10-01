<?php
/** Cartão de uma NR (página de NRs e home). @var array $m linha de Course::nrIndex() @var string|null $heading h2 ou h3 */
$h = $heading ?? 'h2';
?>
<a class="nrc" href="<?= e($m['url']) ?>" data-nr-item data-nr="<?= (int) $m['nr'] ?>" data-name="<?= e(normalize_text($m['name'])) ?>">
<div class="nrc-top">
<span class="nrc-badge"><small>NR</small><?= (int) $m['nr'] ?></span>
<span class="nrc-count"><?= e(pluralize($m['count'], 'treinamento', 'treinamentos')) ?></span>
</div>
<<?= $h ?> class="nrc-name"><?= e($m['name']) ?></<?= $h ?>>
<div class="nrc-tags">
<?php if ($m['inicial']): ?><span>Formação inicial<b><?= $m['inicial'] ?></b></span><?php endif; ?>
<?php if ($m['periodico']): ?><span>Reciclagem<b><?= $m['periodico'] ?></b></span><?php endif; ?>
<?php if ($m['simulador']): ?><span>Simulador<b><?= $m['simulador'] ?></b></span><?php endif; ?>
</div>
<div class="nrc-foot"><span><?php if ($m['from_price'] !== null): ?>a partir de <strong><?= e(money($m['from_price'])) ?></strong><?php else: ?>Sob consulta<?php endif; ?></span><?= icon('arrowR', 'ic-sm') ?></div>
</a>
