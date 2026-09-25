<?php /** @var array $enrollment @var string $url */ ?>
<p style="margin:0 0 12px">Parabéns, <?= e(first_name((string) $enrollment['participant_name'])) ?>!</p>
<p style="margin:0 0 12px">O certificado do treinamento <strong><?= e(($enrollment['course_code'] ? $enrollment['course_code'] . ' — ' : '') . $enrollment['course_title']) ?></strong> está disponível.</p>
<?= App\Core\View::file('emails/button', ['url' => $url, 'label' => 'Baixar certificado', 'color' => '#15803D']) ?>
<p style="margin:0;color:#566074">Entre com o e-mail <?= e((string) $enrollment['participant_email']) ?>. Se ainda não tem conta, crie uma com esse e-mail.</p>
