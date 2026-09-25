<?php
/** Card de curso comprado (área do aluno). @var array $e matrícula com dados do curso */
use App\Models\Enrollment;

$course = [
    'tone' => $e['tone'] ?? 'a', 'badge' => null, 'image_url' => null, 'icon' => $e['icon'] ?? 'clipboard',
    'kicker' => $e['nr_number'] ? 'Norma Regulamentadora' : 'Curso complementar',
    'plate_big' => $e['course_code'] ?: ($e['short_title'] ?: 'Curso'),
    'plate_is_text' => !$e['course_code'],
    'display_title' => $e['course_title'],
    'url' => $e['course_slug'] ? url('/cursos/' . $e['course_slug']) : '#',
];
$access = Enrollment::accessUrl($e);
$done = $e['status'] === 'completed';
?>
<article class="my-course">
<?= partial('cover', ['course' => $course, 'variant' => 'my']) ?>
<div class="my-body">
<div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start;flex-wrap:wrap">
<span class="nr-tag"><?= e($e['course_code'] ?: 'Complementar') ?></span>
<?= partial('status', ['label' => Enrollment::statusLabel($e['status']), 'tone' => Enrollment::STATUS_TONE[$e['status']] ?? 'muted']) ?>
</div>
<h3><?= e($e['course_title']) ?></h3>
<div class="facts"><span class="fact"><?= icon('clock') ?><?= e(hours_short($e['course_hours'])) ?></span><?php if ($e['modality']): ?><span class="fact"><?= icon('monitor') ?><?= e(modality_label($e['modality'])) ?></span><?php endif; ?></div>
<?php if ($e['status'] !== 'processing'): ?>
<div>
<div class="progress-row"><span>Progresso</span><span><?= (int) $e['progress'] ?>%</span></div>
<div class="progress<?= $done ? ' done' : '' ?>" role="progressbar" aria-valuenow="<?= (int) $e['progress'] ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= e('Progresso em ' . $e['course_title']) ?>"><span style="width:<?= (int) $e['progress'] ?>%"></span></div>
</div>
<?php else: ?>
<p class="hint" style="margin:0">Estamos cadastrando você na plataforma de ensino. O link de acesso chega por e-mail.</p>
<?php endif; ?>
<div class="my-actions">
<?php if ($e['status'] === 'active' && $access): ?>
<a class="btn btn-primary btn-sm" href="<?= e($access) ?>" target="_blank" rel="noopener">Continuar<?= icon('external', 'ic-sm') ?></a>
<?php elseif ($done && $e['certificate_id']): ?>
<a class="btn btn-buy btn-sm" href="<?= e(url('/minha-conta/certificados/' . $e['certificate_id'] . '/baixar')) ?>"><?= icon('download', 'ic-sm') ?>Certificado</a>
<?php elseif ($done): ?>
<span class="hint" style="margin:0">Certificado em emissão</span>
<?php else: ?>
<button class="btn btn-ghost btn-sm" type="button" disabled>Acesso em liberação</button>
<?php endif; ?>
<?php if ($e['course_slug']): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('/cursos/' . $e['course_slug'])) ?>">Ver curso</a><?php endif; ?>
</div>
</div>
</article>
