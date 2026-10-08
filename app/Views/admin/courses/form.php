<?php
/** @var array|null $course @var array $categories @var array $syllabus @var array $packages */
use App\Controllers\Admin\CourseController;
use App\Services\Scorm\Packages;

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
<?php $limit = Packages::uploadLimitBytes(); $currentPackage = array_values(array_filter($packages, static fn ($p) => $p['is_current']))[0] ?? null; ?>
<div class="panel" id="conteudo" style="margin-top:22px">
<div class="panel-head"><h2>Conteúdo on-line próprio (SCORM)</h2><?php if ($currentPackage): ?><a class="btn btn-outline btn-xs" href="<?= e(url('/admin/cursos/' . $course['id'] . '/pacotes/' . $currentPackage['id'] . '/previa')) ?>" target="_blank"><?= icon('external', 'ic-sm') ?>Ver como o aluno</a><?php endif; ?></div>
<div class="panel-body">
<p class="hint" style="margin:0 0 14px">Para cursos feitos pela Dafnis (Rise 360, Storyline e outras ferramentas): exporte para <strong>LMS em SCORM 1.2</strong> e envie o .zip. Com um conteúdo em uso, o aluno faz o curso aqui na loja (botão "Começar o curso" em Meus cursos), em vez da plataforma de ensino; a loja registra os acessos, o tempo de estudo e a nota. Quem já começou continua na versão em que começou.</p>
<?php if ($packages): ?>
<div class="table-wrap" style="margin-bottom:16px"><table class="table">
<thead><tr><th>Versão</th><th>Título no pacote</th><th>Enviado</th><th>Tamanho</th><th>Participantes</th><th></th></tr></thead>
<tbody>
<?php foreach ($packages as $p): ?>
<tr>
<td><strong>v<?= (int) $p['version'] ?></strong><?php if ($p['is_current']): ?> <?= partial('status', ['label' => 'Em uso', 'tone' => 'ok']) ?><?php endif; ?></td>
<td><?= e((string) ($p['title'] ?: '—')) ?><br><small class="muted mono"><?= e($p['launch_path']) ?><?= $p['mastery_score'] !== null ? ' · nota mínima ' . (int) $p['mastery_score'] : '' ?></small></td>
<td><?= e(date_br($p['created_at'], true)) ?><?php if ($p['uploaded_by_name']): ?><br><small class="muted"><?= e($p['uploaded_by_name']) ?></small><?php endif; ?></td>
<td><?= e(number_br($p['size_bytes'] / 1048576, 1)) ?> MB<br><small class="muted"><?= (int) $p['file_count'] ?> arquivos</small></td>
<td><?= (int) $p['attempts'] ?></td>
<td style="white-space:nowrap">
<a class="btn btn-outline btn-xs" href="<?= e(url('/admin/cursos/' . $course['id'] . '/pacotes/' . $p['id'] . '/previa')) ?>" target="_blank">Pré-visualizar</a>
<?php if (!$p['is_current']): ?>
<form method="post" action="<?= e(url('/admin/cursos/' . $course['id'] . '/pacotes/' . $p['id'] . '/usar')) ?>" style="display:inline" data-confirm="Colocar a versão <?= (int) $p['version'] ?> em uso? Novos participantes passam a receber esta versão."><?= csrf_field() ?><button class="btn btn-outline btn-xs" type="submit">Usar esta versão</button></form>
<?php if (!(int) $p['attempts']): ?><form method="post" action="<?= e(url('/admin/cursos/' . $course['id'] . '/pacotes/' . $p['id'] . '/excluir')) ?>" style="display:inline" data-confirm="Excluir a versão <?= (int) $p['version'] ?>? Os arquivos dela são apagados."><?= csrf_field() ?><button class="btn btn-danger btn-xs" type="submit">Excluir</button></form><?php endif; ?>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
<form method="post" action="<?= e(url('/admin/cursos/' . $course['id'] . '/pacotes')) ?>" enctype="multipart/form-data" data-loading-form>
<?= csrf_field() ?>
<div class="form-grid">
<div class="field full"><label for="f-package">Pacote SCORM 1.2 <small>(.zip<?= $limit ? ', até ' . e(number_br($limit / 1048576)) . ' MB pelo painel' : '' ?>)</small></label><input class="input" style="padding:9px" type="file" id="f-package" name="package" accept=".zip,application/zip" required><?php if ($err = field_error('package')): ?><p class="field-error"><?= icon('alert') ?><?= e($err) ?></p><?php endif; ?>
<p class="hint">Pacote maior: copie o .zip para a pasta <code>storage/scorm/entrada</code> do servidor (Gerenciador de Arquivos do cPanel ou FTP) e importe abaixo, ou rode <code>php bin/console scorm:import --course=<?= (int) $course['id'] ?> --file=caminho/do/pacote.zip</code>.</p></div>
<div class="full"><label class="switch"><input type="checkbox" name="make_current" value="1" checked><span>Colocar em uso assim que importar<small>Desmarque para conferir na pré-visualização antes de liberar aos alunos.</small></span></label></div>
</div>
<button class="btn btn-navy" type="submit" style="margin-top:14px"><?= icon('upload', 'ic-sm') ?>Importar pacote</button>
</form>
<?php if ($inbox = Packages::inbox()): ?>
<form method="post" action="<?= e(url('/admin/cursos/' . $course['id'] . '/pacotes/servidor')) ?>" data-loading-form style="margin-top:22px;padding-top:18px;border-top:1px solid var(--line)">
<?= csrf_field() ?>
<div class="form-grid">
<div class="full"><?= partial('field', ['name' => 'arquivo', 'label' => 'Ou importe um pacote da pasta de entrada do servidor', 'type' => 'select', 'value' => $inbox[0]['name'], 'options' => array_combine(array_column($inbox, 'name'), array_map(static fn ($f) => $f['name'] . ' (' . number_br($f['size'] / 1048576, 1) . ' MB, ' . date('d/m/Y H:i', $f['modified']) . ')', $inbox)), 'hint' => 'Depois de importado, o .zip sai da pasta de entrada.']) ?></div>
<div class="full"><label class="switch"><input type="checkbox" name="make_current" value="1" checked><span>Colocar em uso assim que importar</span></label></div>
</div>
<button class="btn btn-outline" type="submit" style="margin-top:14px"><?= icon('upload', 'ic-sm') ?>Importar do servidor</button>
</form>
<?php endif; ?>
</div>
</div>

<div class="panel" style="margin-top:22px">
<div class="panel-head"><h2>Zona de cuidado</h2></div>
<div class="panel-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
<form method="post" action="<?= e(url('/admin/cursos/' . $course['id'] . '/excluir')) ?>" data-confirm="Excluir este curso? Se ele já tiver pedidos, será apenas desativado."><?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit"><?= icon('trash', 'ic-sm') ?>Excluir curso</button></form>
<span class="hint" style="margin:0">Cursos com pedidos são desativados em vez de excluídos, para manter o histórico.</span>
</div>
</div>
<?php endif; ?>
