<?php
/** @var array $e @var array $order @var string|null $accessUrl @var array $activity @var array|null $study @var array $sessions */
use App\Models\Enrollment;
use App\Services\Scorm\Tracker;

$statusOptions = Enrollment::STATUS;
?>
<div class="adm-head">
<div><h1><?= e(($e['course_code'] ? $e['course_code'] . ' — ' : '') . $e['course_title']) ?></h1><p>Matrícula #<?= (int) $e['id'] ?> · pedido <a href="<?= e(url('/admin/pedidos/' . $e['order_id'])) ?>"><?= e($e['order_number']) ?></a></p></div>
<div class="adm-actions"><?= partial('status', ['label' => Enrollment::statusLabel($e['status']), 'tone' => Enrollment::STATUS_TONE[$e['status']] ?? 'muted']) ?><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/matriculas')) ?>"><?= icon('arrowL', 'ic-sm') ?>Matrículas</a></div>
</div>

<?php if ($e['status'] === 'processing' && $e['has_content']): ?>
<div class="note-box info" style="margin:0 0 22px"><?= icon('info') ?><span><strong>Para liberar:</strong> este curso é feito aqui na loja. Mude a situação para "Em andamento": <?= e((string) $e['participant_name']) ?> recebe o aviso por e-mail e faz o curso em Minha conta › Meus cursos, entrando com <?= e((string) $e['participant_email']) ?>.</span></div>
<?php elseif ($e['status'] === 'processing'): ?>
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

<?php if ($e['has_content']): ?>
<?php $onlineHours = Tracker::onlineHours($e); ?>
<div class="panel" style="margin-top:22px">
<div class="panel-head"><h2>Curso on-line na loja</h2><?php if ($study): ?><?= partial('status', ['label' => $study['status_label'], 'tone' => $study['done'] ? 'ok' : ($study['lesson_status'] === 'failed' ? 'warn' : 'blue')]) ?><?php endif; ?></div>
<div class="panel-body">
<?php if (!$study): ?>
<p class="hint" style="margin:0">O participante ainda não abriu o curso.</p>
<?php else: ?>
<dl class="dl">
<dt>Situação</dt><dd><?= e($study['status_label']) ?><?= $study['passed_at'] ? ' em ' . e(date_br($study['passed_at'], true)) : '' ?><?= $study['score_raw'] !== null ? ' · nota ' . e(rtrim(rtrim((string) $study['score_raw'], '0'), '.')) . ($study['mastery_score'] !== null ? ' (mínimo ' . (int) $study['mastery_score'] . ')' : '') : '' ?></dd>
<?php if ($study['progress'] !== null): ?><dt>Lições vistas</dt><dd><?= (int) $study['progress'] ?>% do conteúdo</dd><?php endif; ?>
<dt>Tempo de estudo</dt><dd><strong><?= e(Tracker::duration((int) $study['active_seconds'])) ?></strong><?= $onlineHours ? ' de ' . $onlineHours . ' h previstas na parte on-line' : '' ?> · medido pela loja (curso aberto, aba visível e com atividade)</dd>
<dt>Tempo informado pelo curso</dt><dd><?= e(Tracker::duration((int) $study['course_seconds'])) ?></dd>
<dt>Acessos</dt><dd><?= (int) $study['sessions'] ?> · primeiro em <?= e(date_br($study['first_access'], true)) ?> · último em <?= e(date_br($study['last_access'], true)) ?></dd>
<?php if ($study['answers']): ?><dt>Respostas registradas</dt><dd><?= (int) $study['answers'] ?> (<?= (int) $study['answers_correct'] ?> corretas)</dd><?php endif; ?>
<dt>Versão do conteúdo</dt><dd>v<?= (int) $study['version'] ?></dd>
</dl>
<?php
$shortContent = $study['done'] && $study['progress'] !== null && (int) $study['progress'] < 100;
$shortTime = $study['done'] && $onlineHours > 0 && (int) $study['active_seconds'] < $onlineHours * 3600;
?>
<?php if ($shortContent || $shortTime): ?>
<div class="note-box err"><?= icon('alert') ?><span><strong>Confira antes do certificado:</strong> aprovado<?= $shortContent ? ' com ' . (int) $study['progress'] . '% das lições vistas' : '' ?><?= $shortContent && $shortTime ? ' e' : '' ?><?= $shortTime ? ' com ' . e(Tracker::duration((int) $study['active_seconds'])) . ' de estudo, abaixo das ' . $onlineHours . ' h da parte on-line' : '' ?>.</span></div>
<?php endif; ?>
<?php if ($e['practical_required'] && $study['done'] && $e['status'] === 'active'): ?>
<div class="note-box info"><?= icon('info') ?><span>Parte on-line concluída. Depois da prática presencial, registre o certificado: a matrícula vira "Concluído".<?= $e['practical_hours'] ? ' Prática: ' . e((string) $e['practical_hours']) . '.' : '' ?></span></div>
<?php endif; ?>
<?php if ($sessions): ?>
<div class="table-wrap" style="margin-top:16px"><table class="table">
<thead><tr><th>Início</th><th>Último sinal</th><th class="num">Tempo de estudo</th><th class="num">Informado pelo curso</th><th>IP</th></tr></thead>
<tbody>
<?php foreach ($sessions as $s): ?>
<tr><td><?= e(date_br($s['started_at'], true)) ?></td><td><?= e(date_br($s['last_seen_at'], true)) ?></td><td class="num"><?= e(Tracker::duration((int) $s['active_seconds'])) ?></td><td class="num"><?= e(Tracker::duration((int) $s['course_seconds'])) ?></td><td class="mono"><?= e((string) $s['ip']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if ((int) $study['sessions'] > count($sessions)): ?><p class="hint">Mostrando os <?= count($sessions) ?> acessos mais recentes.</p><?php endif; ?>
<?php endif; ?>
<?php endif; ?>
</div>
</div>
<?php endif; ?>
