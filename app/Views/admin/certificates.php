<?php
/** @var array $settings @var array $values @var array $signers @var array $courses */
$f = static fn (string $key, string $label, array $extra = []) => partial('field', array_merge([
    'name' => str_replace('.', '_', $key),
    'label' => $label,
    'value' => $values[$key] ?? '',
    'optional' => true,
], $extra));
$rows = $signers ?: [['id' => '', 'name' => '', 'role' => '', 'registry' => '', 'default' => true, 'signature' => null]];
$cnpjOk = strlen($settings['cnpj']) === 14;
?>
<div class="adm-head"><div><h1>Certificados</h1><p>Modelo dos certificados gerados pela loja: textos da empresa e quem assina. O nome do treinamento e o conteúdo programático ficam no cadastro de cada curso.</p></div></div>
<form method="post" action="<?= e(url('/admin/certificados')) ?>" enctype="multipart/form-data" data-loading-form>
<?= csrf_field() ?>
<div class="grid-2">
<div>
<div class="panel"><div class="panel-head"><h2>Emissão</h2></div><div class="panel-body">
<label class="switch"><input type="hidden" name="certificate_auto" value="0"><input type="checkbox" name="certificate_auto" value="1"<?= checked($settings['auto']) ?>><span>Emitir automaticamente ao concluir o curso na loja<small>Cursos sem prática: o certificado sai quando o participante é aprovado. Cursos com prática presencial: sai quando a equipe registra a prática na matrícula.</small></span></label>
<div class="form-grid" style="margin-top:16px">
<?= $f('certificate.company', 'Empresa no texto do certificado', ['placeholder' => App\Services\Settings::businessName(), 'hint' => '"…aprovado pela empresa ___, inscrita no CNPJ…". Vazio: o nome da empresa em Configurações.']) ?>
<?= $f('certificate.legal_name', 'Razão social (cabeçalho do verso)', ['placeholder' => mb_strtoupper(App\Services\Settings::businessName())]) ?>
<?= $f('certificate.city', 'Cidade da data', ['placeholder' => 'Araçatuba/SP', 'hint' => 'Vazio: a cidade e a UF de Configurações.']) ?>
</div>
<div class="note-box<?= $cnpjOk ? ' info' : ' err' ?>" style="margin:16px 0 0"><?= icon($cnpjOk ? 'info' : 'alert') ?><span>CNPJ<?= $cnpjOk ? ' ' . e(document_display($settings['cnpj'])) : ' não informado' ?>, endereço e telefone do verso vêm de <a href="<?= e(url('/admin/configuracoes')) ?>">Configurações</a>.<?= $cnpjOk ? '' : ' Sem o CNPJ, o certificado não é gerado.' ?></span></div>
</div></div>

<div class="panel"><div class="panel-head"><h2>Quem assina</h2><button class="btn btn-outline btn-xs" type="button" data-repeater-add="signers"><?= icon('plus', 'ic-sm') ?>Pessoa</button></div><div class="panel-body">
<p class="hint" style="margin:0 0 12px">Instrutores e responsável técnico, no verso do certificado (até 4 por curso). Com a imagem da assinatura, o PDF já sai assinado; sem ela, sai a linha para assinar à mão. Use a imagem só com a autorização da pessoa.</p>
<div data-repeater="signers">
<?php foreach ($rows as $s): ?>
<div class="repeater-row signer" data-repeater-row>
<input type="hidden" name="signer_id[]" value="<?= e($s['id']) ?>">
<div class="field"><label>Nome</label><input class="input" name="signer_name[]" value="<?= e($s['name']) ?>" maxlength="120"></div>
<div class="field"><label>Registro <small>(opcional)</small></label><input class="input" name="signer_registry[]" value="<?= e($s['registry']) ?>" maxlength="60" placeholder="Ex.: CREA/SP 0000000000"></div>
<button class="btn btn-danger btn-sm" type="button" data-repeater-remove aria-label="Remover pessoa"><?= icon('trash', 'ic-sm') ?></button>
<div class="field full"><label>Formação e função</label><input class="input" name="signer_role[]" value="<?= e($s['role']) ?>" maxlength="120" placeholder="Ex.: Engenheiro Eletricista / Resp. Técnico"></div>
<div class="field"><label>Assina</label><select class="form-select" name="signer_default[]"><option value="1"<?= selected(!empty($s['default']), true) ?>>Todos os cursos (padrão)</option><option value="0"<?= selected(!empty($s['default']), false) ?>>Só os cursos em que for escolhido</option></select></div>
<div class="field"><label>Imagem da assinatura <small>(PNG ou JPG)</small></label><input class="input" style="padding:9px" type="file" name="signer_signature[]" accept="image/png,image/jpeg"></div>
<input type="hidden" name="signer_remove_signature[]" value="0" data-remove-signature>
<?php if (!empty($s['signature'])): ?>
<div class="field full sig-current" data-repeater-drop><img class="sig-thumb" src="<?= e(url('/admin/certificados/assinaturas/' . $s['id'])) ?>" alt="Assinatura atual de <?= e($s['name']) ?>"><label class="switch"><input type="checkbox" value="1" data-remove-signature-toggle><span>Remover a imagem da assinatura</span></label></div>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php if ($err = field_error('signer_signature')): ?><p class="field-error"><?= icon('alert') ?><?= e($err) ?></p><?php endif; ?>
</div></div>
</div>

<aside>
<div class="panel"><div class="panel-head"><h2>Conferir o modelo</h2></div><div class="panel-body">
<p class="hint" style="margin:0 0 12px">PDF de exemplo, com um participante fictício e os dados do curso. Salve antes de conferir.</p>
<?php if ($courses): ?>
<ul class="link-list">
<?php foreach ($courses as $c): ?>
<li><a href="<?= e(url('/admin/cursos/' . $c['id'] . '/certificado-exemplo')) ?>" target="_blank"><?= icon('file', 'ic-sm') ?><?= e(($c['code'] ?: ($c['nr_number'] ? 'NR ' . $c['nr_number'] : '')) . ($c['code'] || $c['nr_number'] ? ' — ' : '') . $c['title']) ?></a></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<p class="hint" style="margin:12px 0 0">Qualquer curso tem o exemplo no fim do cadastro dele, em "Certificado".</p>
</div></div>
<div class="panel"><div class="panel-head"><h2>Validação</h2></div><div class="panel-body">
<p class="hint" style="margin:0">Cada certificado tem um código e um QR code que levam a <a href="<?= e(url('/certificados')) ?>" target="_blank"><?= e(preg_replace('#^https?://#', '', absolute_url('/certificados'))) ?></a>, onde qualquer pessoa confere se ele é verdadeiro (nome, curso, carga horária e datas; o CPF aparece mascarado).</p>
</div></div>
<button class="btn btn-buy btn-lg btn-block" type="submit"><?= icon('check') ?>Salvar modelo</button>
</aside>
</div>
</form>
