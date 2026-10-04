<?php /** @var array $enrollment @var array|null $summary @var bool $practical @var string $url */
use App\Services\Scorm\Tracker;
?>
<?= App\Core\View::file('emails/title', ['kicker' => 'Parte on-line concluída', 'title' => $practical ? 'Agendar a prática presencial' : 'Emitir o certificado', 'color' => '#0B2545']) ?>
<p style="margin:0 0 12px"><?= e((string) $enrollment['participant_name']) ?> · <?= e((string) $enrollment['participant_email']) ?></p>
<p style="margin:0 0 12px">Treinamento: <strong><?= e(($enrollment['course_code'] ? $enrollment['course_code'] . ' — ' : '') . $enrollment['course_title']) ?></strong></p>
<?php if ($summary): ?>
<p style="margin:0 0 12px;background:#F5F7FA;padding:12px;border-radius:8px">
Situação: <?= e($summary['status_label']) ?><?= $summary['score_raw'] !== null ? ' · nota ' . e(rtrim(rtrim((string) $summary['score_raw'], '0'), '.')) : '' ?><br>
<?php if ($summary['progress'] !== null): ?>Lições vistas: <?= (int) $summary['progress'] ?>% do conteúdo<br><?php endif; ?>
Tempo de estudo medido pela loja: <?= e(Tracker::duration((int) $summary['active_seconds'])) ?> em <?= (int) $summary['sessions'] ?> acesso(s)<?php if ($hours = Tracker::onlineHours($enrollment)): ?> (parte on-line: <?= $hours ?> h)<?php endif; ?>
</p>
<?php endif; ?>
<p style="margin:0 0 12px"><?= $practical
    ? 'O curso exige prática presencial. Combine a data com o participante e, depois da prática, registre o certificado na matrícula.'
    : 'Confira o tempo de estudo e registre o certificado na matrícula. O participante é avisado por e-mail.' ?></p>
<?= App\Core\View::file('emails/button', ['url' => $url, 'label' => 'Abrir a matrícula', 'color' => '#0B2545']) ?>
