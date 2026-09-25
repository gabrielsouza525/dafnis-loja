<?php
/** @var array $e @var array $order @var string|null $accessUrl @var array $activity */
use App\Models\Enrollment;

$statusOptions = Enrollment::STATUS;
?>
<div class="adm-head">
<div><h1><?= e(($e['course_code'] ? $e['course_code'] . ' — ' : '') . $e['course_title']) ?></h1><p>Matrícula #<?= (int) $e['id'] ?> · pedido <a href="<?= e(url('/admin/pedidos/' . $e['order_id'])) ?>"><?= e($e['order_number']) ?></a></p></div>
<div class="adm-actions"><?= partial('status', ['label' => Enrollment::statusLabel($e['status']), 'tone' => Enrollment::STATUS_TONE[$e['status']] ?? 'muted']) ?><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/matriculas')) ?>"><?= icon('arrowL', 'ic-sm') ?>Matrículas</a></div>
</div>

<?php if ($e['status'] === 'processing'): ?>
<div class="note-box info" style="margin:0 0 22px"><?= icon('info') ?><span><strong>Para liberar:</strong> cadastre <?= e((string) $e['participant_name']) ?> (<?= e((string) $e['participant_email']) ?>) na plataforma de ensino, depois mude a situação para "Em andamento". O participante recebe o link de acesso por e-mail.</span></div>
<?php elseif ($e['status'] === 'awaiting_participant'): ?>
<div class="note-box" style="margin:0 0 22px"><?= icon('users') ?><span>O comprador ainda não indicou o participante desta vaga. Você pode preencher aqui se ele informou por outro canal.</span></div>
<?php endif; ?>

<div class="grid-2">
<form class="panel" method="post" action="<?= e(url('/admin/matriculas/' . $e['id'])) ?>" data-loading-form>
<?= csrf_field() ?>
<div class="panel-head"><h2>Participante e acesso</h2></div>
<div class="panel-body">
<div class="form-grid">
<div class="full"><?= partial('field', ['name' => 'participant_name', 'label' => 'Nome do participante', 'value' => $e['participant_name']]) ?></div>
<?= partial('field', ['name' => 'participant_email', 'label' => 'E-mail', 'type' => 'email', 'value' => $e['participant_email']]) ?>
<?= partial('field', ['name' => 'participant_document', 'label' => 'CPF', 'value' => $e['participant_document'] ? document_display($e['participant_document']) : '', 'mask' => 'cpf']) ?>
<?= partial('field', ['name' => 'status', 'label' => 'Situação', 'type' => 'select', 'value' => $e['status'], 'options' => $statusOptions]) ?>
<?= partial('field', ['name' => 'progress', 'label' => 'Progresso (%)', 'type' => 'number', 'value' => (int) $e['progress'], 'attrs' => ['min' => 0, 'max' => 100]]) ?>
<div class="full"><?= partial('field', ['name' => 'access_url', 'label' => 'Link de acesso deste participante', 'type' => 'url', 'value' => $e['access_url'], 'optional' => true, 'hint' => $accessUrl && !$e['access_url'] ? 'Vazio: usa ' . $accessUrl : 'Vazio: usa o link do curso ou o link geral da plataforma (Configurações).']) ?></div>
</div>
<button class="btn btn-navy" type="submit" style="margin-top:18px">Salvar matrícula</button>
</div>
</form>

<aside>
<form class="panel" method="post" action="<?= e(url('/admin/matriculas/' . $e['id'] . '/certificado')) ?>" enctype="multipart/form-data" data-loading-form>
<?= csrf_field() ?>
<div class="panel-head"><h2>Certificado</h2></div>
<div class="panel-body">
<?php if ($e['certificate_id']): ?>
<p style="margin-bottom:12px">Código <strong class="mono"><?= e($e['certificate_code']) ?></strong> · emitido em <?= e(date_br($e['certificate_issued_at'])) ?></p>
<?php if ($e['certificate_file']): ?><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/matriculas/' . $e['id'] . '/certificado')) ?>" target="_blank"><?= icon('file', 'ic-sm') ?>Ver PDF</a><?php endif; ?>
<?php if ($e['certificate_url']): ?><a class="btn btn-outline btn-sm" href="<?= e($e['certificate_url']) ?>" target="_blank" rel="noopener"><?= icon('external', 'ic-sm') ?>Link</a><?php endif; ?>
<p class="hint">Enviar outro arquivo substitui o atual.</p>
<?php else: ?>
<p class="hint" style="margin:0 0 12px">Ao registrar, a matrícula vira "Concluído" e o participante recebe o aviso por e-mail.</p>
<?php endif; ?>
<div class="field" style="margin-top:12px"><label for="cf">PDF do certificado</label><input class="input" style="padding:9px" type="file" id="cf" name="certificate_file" accept="application/pdf"><?php if ($err = field_error('certificate_file')): ?><p class="field-error"><?= icon('alert') ?><?= e($err) ?></p><?php endif; ?></div>
<div style="margin-top:12px"><?= partial('field', ['name' => 'external_url', 'label' => 'Ou link do certificado na plataforma', 'type' => 'url', 'value' => $e['certificate_url'], 'optional' => true]) ?></div>
<div style="margin-top:12px"><?= partial('field', ['name' => 'issued_at', 'label' => 'Data de emissão', 'type' => 'date', 'value' => $e['certificate_issued_at'] ?: date('Y-m-d'), 'required' => true]) ?></div>
<button class="btn btn-buy btn-block" type="submit" style="margin-top:16px"><?= icon('award') ?><?= $e['certificate_id'] ? 'Atualizar certificado' : 'Registrar certificado' ?></button>
</div>
</form>

<div class="panel"><div class="panel-head"><h2>Compra</h2></div><div class="panel-body">
<dl class="dl">
<dt>Comprador</dt><dd><?= e($order['buyer_type'] === 'pj' ? (string) $order['company_name'] : $order['buyer_name']) ?></dd>
<dt>Contato</dt><dd><?= e($order['buyer_email']) ?> · <?= e(phone_display($order['buyer_phone'])) ?></dd>
<?php if ($e['released_at']): ?><dt>Liberado em</dt><dd><?= e(date_br($e['released_at'], true)) ?></dd><?php endif; ?>
<?php if ($e['completed_at']): ?><dt>Concluído em</dt><dd><?= e(date_br($e['completed_at'], true)) ?></dd><?php endif; ?>
</dl>
</div></div>

<?php if ($activity): ?>
<div class="panel"><div class="panel-head"><h2>Histórico</h2></div><div class="panel-body">
<ul class="timeline"><?php foreach ($activity as $a): ?><li><div><?= e($a['details'] ?: $a['action']) ?><small><?= e(($a['user_name'] ?: 'Sistema') . ' · ' . date_br($a['created_at'], true)) ?></small></div></li><?php endforeach; ?></ul>
</div></div>
<?php endif; ?>
</aside>
</div>
