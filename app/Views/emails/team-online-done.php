<?php /** @var array $enrollment @var array|null $summary @var bool $practical @var list<string>|null $pending @var string $url */
use App\Services\Scorm\Tracker;

$pending ??= null;
$issued = !$practical && $pending === [];
$title = match (true) {
    $practical => 'Agendar a prática presencial',
    $issued => 'Certificado emitido',
    default => 'Emitir o certificado',
};
?>
<?= App\Core\View::file('emails/title', ['kicker' => 'Parte on-line concluída', 'title' => $title, 'color' => '#0B2545']) ?>
<p style="margin:0 0 12px"><?= e((string) $enrollment['participant_name']) ?> · <?= e((string) $enrollment['participant_email']) ?></p>
<p style="margin:0 0 12px">Treinamento: <strong><?= e(($enrollment['course_code'] ? $enrollment['course_code'] . ' — ' : '') . $enrollment['course_title']) ?></strong></p>
<?php if ($summary): ?>
<p style="margin:0 0 12px;background:#F5F7FA;padding:12px;border-radius:8px">
Situação: <?= e($summary['status_label']) ?><?= $summary['score_raw'] !== null ? ' · nota ' . e(rtrim(rtrim((string) $summary['score_raw'], '0'), '.')) : '' ?><br>
<?php if ($summary['progress'] !== null): ?>Lições vistas: <?= (int) $summary['progress'] ?>% do conteúdo<br><?php endif; ?>
Tempo de estudo medido pela loja: <?= e(Tracker::duration((int) $summary['active_seconds'])) ?> em <?= (int) $summary['sessions'] ?> acesso(s)<?php if ($hours = Tracker::onlineHours($enrollment)): ?> (parte on-line: <?= $hours ?> h)<?php endif; ?>
</p>
<?php endif; ?>
<p style="margin:0 0 12px"><?php if ($practical): ?>O curso exige prática presencial. Combine a data com o participante e, depois da prática, gere o certificado na matrícula: ele sai no modelo da Dafnis e o participante recebe por e-mail.
<?php elseif ($issued): ?>O certificado <?= $enrollment['certificate_code'] ? '<strong>' . e($enrollment['certificate_code']) . '</strong> ' : '' ?>foi emitido automaticamente e o participante já recebeu o aviso. Se precisar corrigir algum dado, gere de novo na matrícula (o código continua o mesmo).
<?php else: ?>O certificado não saiu automaticamente<?= $pending ? ': falta ' . e(implode(', ', $pending)) : '' ?>. Complete os dados e gere o certificado na matrícula. O participante é avisado por e-mail.
<?php endif; ?></p>
<?= App\Core\View::file('emails/button', ['url' => $url, 'label' => 'Abrir a matrícula', 'color' => '#0B2545']) ?>
