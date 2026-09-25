<?php
/** @var array $categories */
use App\Controllers\Admin\CourseController;
use App\Models\Category;

$row = static function (?array $k): string {
    $isNew = $k === null;
    $k ??= ['id' => 0, 'name' => '', 'slug' => '', 'description' => '', 'icon' => 'clipboard', 'tone' => 'a', 'sort_order' => 0, 'is_active' => 1, 'course_count' => 0];
    $action = $isNew ? url('/admin/categorias') : url('/admin/categorias/' . $k['id']);
    ob_start(); ?>
<form class="panel" method="post" action="<?= e($action) ?>" style="margin-top:0">
<?= csrf_field() ?>
<div class="panel-head"><h2 style="display:flex;gap:10px;align-items:center"><span class="cat-ic" style="width:36px;height:36px"><?= icon($k['icon']) ?></span><?= $isNew ? 'Nova categoria' : e($k['name']) ?></h2><?php if (!$isNew): ?><span class="muted" style="font-size:13.5px"><?= e(pluralize((int) $k['course_count'], 'curso', 'cursos')) ?> · <a href="<?= e(url('/categorias/' . $k['slug'])) ?>" target="_blank">ver na loja</a></span><?php endif; ?></div>
<div class="panel-body">
<div class="form-grid-3">
<div class="field"><label>Nome</label><input class="input" name="name" value="<?= e($k['name']) ?>" required></div>
<div class="field"><label>Endereço (slug)</label><input class="input" name="slug" value="<?= e($k['slug']) ?>" placeholder="gerado pelo nome"></div>
<div class="field"><label>Ordem</label><input class="input" type="number" name="sort_order" value="<?= (int) $k['sort_order'] ?>"></div>
<div class="field" style="grid-column:1 / -1"><label>Descrição</label><input class="input" name="description" value="<?= e((string) $k['description']) ?>" maxlength="255"></div>
<div class="field"><label>Ícone</label><select class="form-select" name="icon"><?php foreach (CourseController::ICON_CHOICES as $key => $label): ?><option value="<?= e($key) ?>"<?= selected($key, $k['icon']) ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
<div class="field"><label>Tom da capa</label><select class="form-select" name="tone"><?php foreach (Category::TONES as $key => $label): ?><option value="<?= e($key) ?>"<?= selected($key, $k['tone']) ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
<label class="switch" style="align-self:end"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1"<?= checked((int) $k['is_active']) ?>><span>Ativa</span></label>
</div>
<div style="display:flex;gap:8px;margin-top:16px">
<button class="btn btn-navy btn-sm" type="submit"><?= $isNew ? 'Criar categoria' : 'Salvar' ?></button>
</div>
</div>
</form>
<?php if (!$isNew && (int) $k['course_count'] === 0): ?>
<form method="post" action="<?= e(url('/admin/categorias/' . $k['id'] . '/excluir')) ?>" data-confirm="Excluir a categoria <?= e($k['name']) ?>?" style="margin:-12px 0 0 22px"><?= csrf_field() ?><button class="btn btn-danger btn-xs" type="submit">Excluir</button></form>
<?php endif; ?>
<?php return (string) ob_get_clean();
};
?>
<div class="adm-head"><div><h1>Categorias</h1><p>Áreas do catálogo mostradas na home e nos filtros.</p></div></div>
<?php if ($err = field_error('category')): ?><div class="note-box err" style="margin:0 0 16px"><?= icon('alert') ?><span><?= e($err) ?></span></div><?php endif; ?>
<div style="display:flex;flex-direction:column;gap:22px">
<?php foreach ($categories as $k): ?><?= $row($k) ?><?php endforeach; ?>
<?= $row(null) ?>
</div>
