<?php
/** @var array $e @var array $order @var string|null $accessUrl @var array $activity @var array|null $study @var array $sessions @var array $certMissing @var array $certDefaults @var bool $certGenerated */
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
<?php $presential = !empty($e['practical_required']) || $e['modality'] === 'presencial'; ?>
<div class="panel" id="certificado"><div class="panel-head"><h2>Certificado</h2><?php if ($e['certificate_id']): ?><?= partial('status', ['label' => 'Emitido', 'tone' => 'ok']) ?><?php endif; ?></div>
<div class="panel-body">
<?php if ($e['certificate_id']): ?>
<p style="margin-bottom:12px">Código <a class="mono" href="<?= e(url('/certificados/' . $e['certificate_code'])) ?>" target="_blank"><strong><?= e($e['certificate_code']) ?></strong></a> · <?= e(date_br($e['certificate_issued_at'])) ?><?= $certGenerated ? ' · gerado pela loja' : '' ?></p>
<div style="display:flex;gap:8px;flex-wrap:wrap">
<?php if ($e['certificate_file'] || $certGenerated): ?><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/matriculas/' . $e['id'] . '/certificado')) ?>" target="_blank"><?= icon('file', 'ic-sm') ?>Ver PDF</a><?php endif; ?>
<?php if ($e['certificate_url']): ?><a class="btn btn-outline btn-sm" href="<?= e($e['certificate_url']) ?>" target="_blank" rel="noopener"><?= icon('external', 'ic-sm') ?>Link</a><?php endif; ?>
</div>
<?php endif; ?>

<form method="post" action="<?= e(url('/admin/matriculas/' . $e['id'] . '/certificado/gerar')) ?>" data-loading-form style="<?= $e['certificate_id'] ? 'margin-top:16px;padding-top:16px;border-top:1px solid var(--line)' : '' ?>">
<?= csrf_field() ?>
<input type="hidden" name="_scope" value="gerar">
<p class="hint" style="margin:0 0 12px"><?php if ($e['certificate_id']): ?>Corrigiu o nome, o CPF ou o curso? Gere de novo: o código continua o mesmo e o participante não recebe outro e-mail.<?php elseif ($presential): ?>Depois da parte presencial, registre quando e onde ela foi: o certificado sai no modelo da Dafnis, a matrícula vira "Concluído" e o participante recebe o aviso por e-mail.<?php else: ?>Gera o certificado no modelo da Dafnis: a matrícula vira "Concluído" e o participante recebe o aviso por e-mail.<?php endif; ?></p>
<?php if ($certMissing): ?>
<div class="note-box err" style="margin:0 0 12px"><?= icon('alert') ?><span>Para gerar, falta: <?= e(implode(', ', $certMissing)) ?>.</span></div>
<?php endif; ?>
<?php if ($err = field_error('certificate')): ?><p class="field-error" style="margin:0 0 10px"><?= icon('alert') ?><?= e($err) ?></p><?php endif; ?>
<div class="form-grid">
<?= partial('field', ['name' => 'start', 'label' => 'Início', 'type' => 'date', 'value' => $certDefaults['start'], 'required' => true, 'scope' => 'gerar']) ?>
<?= partial('field', ['name' => 'end', 'label' => $presential ? 'Data da prática (término)' : 'Término', 'type' => 'date', 'value' => $certDefaults['end'], 'required' => true, 'scope' => 'gerar']) ?>
<?php if ($presential): ?>
<div class="full"><?= partial('field', ['name' => 'practical_location', 'label' => 'Local da parte presencial', 'value' => $certDefaults['practical_location'], 'required' => true, 'placeholder' => 'Ex.: Araçatuba/SP', 'scope' => 'gerar']) ?></div>
<?php endif; ?>
<div class="full"><?= partial('field', ['name' => 'issued_at', 'label' => 'Data no certificado', 'type' => 'date', 'value' => $certDefaults['issued_at'], 'optional' => true, 'hint' => 'Vazio: a data do término.', 'scope' => 'gerar']) ?></div>
</div>
<button class="btn btn-buy btn-block" type="submit" style="margin-top:14px"<?= $certMissing ? ' disabled' : '' ?>><?= icon('award') ?><?= $e['certificate_id'] ? 'Gerar de novo' : ($presential ? 'Registrar a prática e gerar o certificado' : 'Gerar certificado') ?></button>
</form>

<details style="margin-top:16px;padding-top:14px;border-top:1px solid var(--line)"<?= field_error('certificate_file') ? ' open' : '' ?>>
<summary style="cursor:pointer;font-weight:600;font-size:14px">Enviar um PDF próprio ou o link da plataforma</summary>
<form method="post" action="<?= e(url('/admin/matriculas/' . $e['id'] . '/certificado')) ?>" enctype="multipart/form-data" data-loading-form style="margin-top:12px">
<?= csrf_field() ?>
<p class="hint" style="margin:0 0 12px">Para certificados emitidos fora da loja (por exemplo, na plataforma de ensino). Enviar outro arquivo substitui o atual.</p>
<div class="field"><label for="cf">PDF do certificado</label><input class="input" style="padding:9px" type="file" id="cf" name="certificate_file" accept="application/pdf"><?php if ($err = field_error('certificate_file')): ?><p class="field-error"><?= icon('alert') ?><?= e($err) ?></p><?php endif; ?></div>
<div style="margin-top:12px"><?= partial('field', ['name' => 'external_url', 'label' => 'Ou link do certificado na plataforma', 'type' => 'url', 'value' => $e['certificate_url'], 'optional' => true]) ?></div>
<div style="margin-top:12px"><?= partial('field', ['name' => 'issued_at', 'id' => 'f-issued-upload', 'label' => 'Data de emissão', 'type' => 'date', 'value' => $e['certificate_issued_at'] ?: date('Y-m-d'), 'required' => true]) ?></div>
<button class="btn btn-outline btn-block" type="submit" style="margin-top:14px"><?= icon('upload', 'ic-sm') ?><?= $e['certificate_id'] ? 'Substituir pelo arquivo enviado' : 'Registrar certificado enviado' ?></button>
</form>
</details>
</div>
</div>

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
<div class="note-box info"><?= icon('info') ?><span>Parte on-line concluída. Depois da prática presencial, registre a prática no quadro "Certificado": ele sai no modelo da Dafnis e a matrícula vira "Concluído".<?= $e['practical_hours'] ? ' Prática: ' . e((string) $e['practical_hours']) . '.' : '' ?></span></div>
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
