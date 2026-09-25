<?php
/** @var int $current 1 carrinho · 2 identificação · 3 pagamento · 4 confirmação */
$labels = ['Carrinho', 'Identificação', 'Pagamento', 'Confirmação'];
?>
<ol class="steps" aria-label="Etapas da compra">
<?php foreach ($labels as $i => $label): $n = $i + 1; $cls = $n < $current ? 'done' : ($n === $current || ($current === 2 && $n === 3) ? 'on' : ''); ?>
<li class="step <?= $cls ?>"<?= $n === $current ? ' aria-current="step"' : '' ?>><span class="step-n"><?= $n < $current ? icon('check') : $n ?></span><span><?= e($label) ?></span></li>
<?php endforeach; ?>
</ol>
