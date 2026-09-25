<?php
/** @var array $items [[rótulo, caminho|null], ...] — o último é a página atual */
?>
<nav class="crumbs" aria-label="Navegação estrutural">
<?php foreach ($items as $i => [$label, $path]): ?>
<?php if ($i > 0): ?><?= icon('chevR') ?><?php endif; ?>
<?php if ($path !== null && $i < count($items) - 1): ?><a class="crumb" href="<?= e(url($path)) ?>"><?= e($label) ?></a><?php else: ?><span class="crumb-cur" aria-current="page"><?= e($label) ?></span><?php endif; ?>
<?php endforeach; ?>
</nav>
