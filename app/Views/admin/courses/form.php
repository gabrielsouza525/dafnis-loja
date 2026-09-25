<?php
/** @var array|null $course @var array $categories @var array $syllabus */
use App\Controllers\Admin\CourseController;

$c = $course ?? [];
$v = static fn (string $key, mixed $default = '') => $c[$key] ?? $default;
$modules = $syllabus ?: [['title' => '', 'hours' => '', 'topics' => '']];
$action = $course ? url('/admin/cursos/' . $course['id']) : url('/admin/cursos');
?>
<div class="adm-head">
<div><h1><?= $course ? e($course['display_title']) : 'Novo curso' ?></h1><?php if ($course): ?><p><a href="<?= e($course['url']) ?>" target="_blank">/cursos/<?= e($course['slug']) ?> <?= icon('external', 'ic-sm') ?></a><?= $course['source_ref'] ? ' · Origem: ' . e($course['source_ref']) : '' ?></p><?php endif; ?></div>
<div class="adm-actions"><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/cursos')) ?>"><?= icon('arrowL', 'ic-sm') ?>Voltar</a></div>
</div>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" data-loading-form>
<?= csrf_field() ?>
<div class="grid-2">
<div>
<div class="panel"><div class="panel-head"><h2>Identificação</h2></div><div class="panel-body">
<div class="form-grid">
<div class="full"><?= partial('field', ['name' => 'title', 'label' => 'Título', 'value' => $v('title'), 'required' => true, 'hint' => 'Sem o código da NR (ele vem do campo "Número da NR").', 'attrs' => ['data-slug-source' => true]]) ?></div>
<?= partial('field', ['name' => 'nr_number', 'label' => 'Número da NR', 'type' => 'number', 'value' => $v('nr_number'), 'optional' => true, 'attrs' => ['min' => 1, 'max' => 99]]) ?>
<?= partial('field', ['name' => 'code', 'label' => 'Código exibido', 'value' => $v('code'), 'optional' => true, 'placeholder' => 'Ex.: NR 31.7', 'hint' => 'Só quando for diferente de "NR + número".']) ?>
<?= partial('field', ['name' => 'category_id', 'label' => 'Categoria', 'type' => 'select', 'value' => $v('category_id'), 'options' => ['' => 'Sem categoria'] + $categories]) ?>
<?= partial('field', ['name' => 'short_title', 'label' => 'Texto da capa', 'value' => $v('short_title'), 'optional' => true, 'hint' => 'Para cursos sem NR (ex.: "Primeiros Socorros").']) ?>
<div class="full"><?= partial('field', ['name' => 'slug', 'label' => 'Endereço (slug)', 'value' => $v('slug'), 'optional' => true, 'hint' => 'Gerado a partir do título se ficar vazio. Mudar o slug muda o link do curso.', 'attrs' => ['data-slug-target' => true, 'pattern' => '[a-z0-9-]*']]) ?></div>
</div>
</div></div>

<div class="panel"><div class="panel-head"><h2>Carga horária e modalidade</h2></div><div class="panel-body">
<div class="form-grid-3">
<?= partial('field', ['name' => 'hours', 'label' => 'Carga horária (h)', 'type' => 'number', 'value' => $v('hours', 0), 'required' => true, 'attrs' => ['min' => 0, 'max' => 2000]]) ?>
<?= partial('field', ['name' => 'modality', 'label' => 'Modalidade', 'type' => 'select', 'value' => $v('modality', 'online'), 'options' => MODALITIES]) ?>
<?= partial('field', ['name' => 'training_type', 'label' => 'Tipo', 'type' => 'select', 'value' => $v('training_type', 'inicial'), 'options' => TRAINING_TYPES]) ?>
</div>
<div class="form-grid" style="margin-top:16px">
<div class="full"><?= partial('field', ['name' => 'hours_note', 'label' => 'Observação da carga horária', 'value' => $v('hours_note'), 'optional' => true, 'placeholder' => 'Ex.: + prática conforme o tipo de caldeira']) ?></div>
<div class="full"><label class="switch"><input type="hidden" name="practical_required" value="0"><input type="checkbox" name="practical_required" value="1"<?= checked($v('practical_required', false)) ?>><span>Prática presencial obrigatória<small>Mostra o aviso de parte prática na página do curso.</small></span></label></div>
<?= partial('field', ['name' => 'practical_hours', 'label' => 'Carga prática', 'value' => $v('practical_hours'), 'optional' => true, 'placeholder' => 'Ex.: 8 h']) ?>
<?= partial('field', ['name' => 'practical_note', 'label' => 'Observação da prática', 'value' => $v('practical_note'), 'optional' => true]) ?>
</div>
</div></div>

<div class="panel"><div class="panel-head"><h2>Conteúdo da página</h2></div><div class="panel-body">
<div class="form-grid">
<div class="full"><?= partial('field', ['name' => 'summary', 'label' => 'Descrição curta', 'type' => 'textarea', 'value' => $v('summary'), 'optional' => true, 'hint' => 'Aparece nos cards, no topo da página e no Google. Até 400 caracteres.', 'attrs' => ['maxlength' => 400, 'rows' => 3, 'style' => 'min-height:90px']]) ?></div>
<div class="full"><?= partial('field', ['name' => 'description', 'label' => 'Sobre o curso', 'type' => 'textarea', 'value' => $v('description'), 'optional' => true, 'hint' => 'Vazio: a página monta um texto só com os dados cadastrados.']) ?></div>
<div class="full"><?= partial('field', ['name' => 'audience', 'label' => 'Para quem é', 'type' => 'textarea', 'value' => $v('audience'), 'optional' => true]) ?></div>
<div class="full"><?= partial('field', ['name' => 'objectives', 'label' => 'Objetivos', 'type' => 'textarea', 'value' => $v('objectives'), 'optional' => true, 'hint' => 'A seção só aparece quando preenchida.']) ?></div>
<div class="full"><?= partial('field', ['name' => 'requirements', 'label' => 'Pré-requisitos', 'value' => $v('requirements'), 'optional' => true]) ?></div>
</div>
</div></div>

<div class="panel"><div class="panel-head"><h2>Conteúdo programático</h2><button class="btn btn-outline btn-xs" type="button" data-repeater-add="modules"><?= icon('plus', 'ic-sm') ?>Módulo</button></div><div class="panel-body">
<p class="hint" style="margin:0 0 12px">Sem módulos, a página oferece "Solicitar conteúdo programático".</p>
<div data-repeater="modules">
<?php foreach ($modules as $m): ?>
<div class="repeater-row" data-repeater-row>
<div class="field"><label>Título do módulo</label><input class="input" name="module_title[]" value="<?= e($m['title']) ?>"></div>
<div class="field"><label>Carga</label><input class="input" name="module_hours[]" value="<?= e($m['hours']) ?>" placeholder="2 h"></div>
<button class="btn btn-danger btn-sm" type="button" data-repeater-remove aria-label="Remover módulo"><?= icon('trash', 'ic-sm') ?></button>
<div class="field full"><label>Tópicos</label><textarea class="textarea" name="module_topics[]" style="min-height:80px"><?= e($m['topics']) ?></textarea></div>
</div>
<?php endforeach; ?>
</div>
</div></div>

<div class="panel"><div class="panel-head"><h2>SEO e busca</h2></div><div class="panel-body">
<div class="form-grid">
<?= partial('field', ['name' => 'meta_title', 'label' => 'Título para o Google', 'value' => $v('meta_title'), 'optional' => true]) ?>
<?= partial('field', ['name' => 'keywords', 'label' => 'Palavras-chave da busca', 'value' => $v('keywords'), 'optional' => true, 'hint' => 'Sinônimos que o cliente pode digitar.']) ?>
<div class="full"><?= partial('field', ['name' => 'meta_description', 'label' => 'Descrição para o Google', 'value' => $v('meta_description'), 'optional' => true, 'attrs' => ['maxlength' => 255]]) ?></div>
</div>
</div></div>
</div>

<aside>
<div class="panel"><div class="panel-head"><h2>Venda</h2></div><div class="panel-body">
<?= partial('field', ['name' => 'price', 'label' => 'Preço por participante', 'type' => 'money', 'value' => $v('price'), 'optional' => true, 'mask' => 'money', 'hint' => 'Vazio = "Sob consulta" (sem compra online).']) ?>
<div style="margin-top:14px"><?= partial('field', ['name' => 'promo_price', 'label' => 'Preço promocional', 'type' => 'money', 'value' => $v('promo_price'), 'optional' => true, 'mask' => 'money', 'hint' => 'Mostra o preço riscado e o selo "Oferta".']) ?></div>
<div style="margin-top:14px">
<label class="switch"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1"<?= checked($v('is_active', true)) ?>><span>Ativo na loja</span></label>
<label class="switch"><input type="hidden" name="is_bestseller" value="0"><input type="checkbox" name="is_bestseller" value="1"<?= checked($v('is_bestseller', false)) ?>><span>Selo "Mais vendido"<small>Também entra na seção "Os mais procurados" da home.</small></span></label>
<label class="switch"><input type="hidden" name="is_new" value="0"><input type="checkbox" name="is_new" value="1"<?= checked($v('is_new', false)) ?>><span>Selo "Novo"</span></label>
<label class="switch"><input type="hidden" name="certificate" value="0"><input type="checkbox" name="certificate" value="1"<?= checked($v('certificate', true)) ?>><span>Emite certificado</span></label>
</div>
<div style="margin-top:14px"><?= partial('field', ['name' => 'featured_order', 'label' => 'Posição nos destaques da home', 'type' => 'number', 'value' => $v('featured_order'), 'optional' => true, 'hint' => 'Vazio = não aparece nos destaques. 1 = primeiro.', 'attrs' => ['min' => 1, 'max' => 99]]) ?></div>
</div></div>

<div class="panel"><div class="panel-head"><h2>Acesso</h2></div><div class="panel-body">
<?= partial('field', ['name' => 'access_url', 'label' => 'Link do curso na plataforma', 'type' => 'url', 'value' => $v('access_url'), 'optional' => true, 'hint' => 'Vazio = usa o link geral da plataforma (Configurações).']) ?>
<div style="margin-top:14px"><?= partial('field', ['name' => 'access_days', 'label' => 'Prazo de acesso (dias)', 'type' => 'number', 'value' => $v('access_days'), 'optional' => true, 'hint' => 'Só aparece na página se preenchido.']) ?></div>
</div></div>

<div class="panel"><div class="panel-head"><h2>Capa</h2></div><div class="panel-body">
<?php if ($course && $course['image_url']): ?>
<img class="img-preview" src="<?= e($course['image_url']) ?>" alt="Capa atual">
<label class="switch"><input type="checkbox" name="remove_image" value="1"><span>Remover foto e voltar à capa da marca</span></label>
<?php elseif ($course): ?>
<div style="max-width:320px"><?= partial('cover', ['course' => $course]) ?></div>
<p class="hint">Sem foto: usa a capa da marca com a NR.</p>
<?php endif; ?>
<div class="field" style="margin-top:12px"><label for="f-image">Enviar foto <small>(JPG, PNG ou WebP)</small></label><input class="input" style="padding:9px" type="file" id="f-image" name="image" accept="image/jpeg,image/png,image/webp"><?php if ($err = field_error('image')): ?><p class="field-error"><?= icon('alert') ?><?= e($err) ?></p><?php endif; ?></div>
<div style="margin-top:14px"><?= partial('field', ['name' => 'icon', 'label' => 'Ícone da capa', 'type' => 'select', 'value' => $v('icon', 'clipboard'), 'options' => CourseController::ICON_CHOICES]) ?></div>
</div></div>

<button class="btn btn-buy btn-lg btn-block" type="submit"><?= icon('check') ?><?= $course ? 'Salvar alterações' : 'Criar curso' ?></button>
</aside>
</div>
</form>

<?php if ($course): ?>
<div class="panel" style="margin-top:22px">
<div class="panel-head"><h2>Zona de cuidado</h2></div>
<div class="panel-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
<form method="post" action="<?= e(url('/admin/cursos/' . $course['id'] . '/excluir')) ?>" data-confirm="Excluir este curso? Se ele já tiver pedidos, será apenas desativado."><?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit"><?= icon('trash', 'ic-sm') ?>Excluir curso</button></form>
<span class="hint" style="margin:0">Cursos com pedidos são desativados em vez de excluídos, para manter o histórico.</span>
</div>
</div>
<?php endif; ?>
