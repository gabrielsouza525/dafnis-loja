<?php
/** @var array $p resultado de AdminController::paginate */
$query = App\Core\App::request()?->queryAll() ?? [];
$link = static function (int $page) use ($query): string {
    $query['pagina'] = $page;
    return url(request_path(), $query);
};
?>
<div class="pager-admin">
<span><?= $p['total'] ? (int) $p['from'] . '–' . (int) $p['to'] . ' de ' . (int) $p['total'] : 'Nenhum registro' ?></span>
<?php if ($p['pages'] > 1): ?>
<nav aria-label="Paginação">
<?php for ($i = max(1, $p['page'] - 3); $i <= min($p['pages'], $p['page'] + 3); $i++): ?>
<?php if ($i === $p['page']): ?><span class="cur" aria-current="page"><?= $i ?></span><?php else: ?><a href="<?= e($link($i)) ?>"><?= $i ?></a><?php endif; ?>
<?php endfor; ?>
</nav>
<?php endif; ?>
</div>
