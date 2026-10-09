<?php /** @var array $enrollment @var string $url */ ?>
<?= App\Core\View::file('emails/title', ['kicker' => 'Treinamento concluído', 'title' => 'Falta o seu CPF para o certificado', 'color' => '#7F5A10']) ?>
<p style="margin:0 0 12px">Parabéns, <?= e(first_name((string) $enrollment['participant_name'])) ?>! Você concluiu o treinamento <strong><?= e(($enrollment['course_code'] ? $enrollment['course_code'] . ' — ' : '') . $enrollment['course_title']) ?></strong>.</p>
<p style="margin:0 0 12px">O certificado leva o seu CPF. Informe o número em Minha conta › Certificados: o certificado sai na hora.</p>
<?= App\Core\View::file('emails/button', ['url' => $url, 'label' => 'Informar o CPF', 'color' => '#15803D']) ?>
<p style="margin:0;color:#566074">Entre com o e-mail <?= e((string) $enrollment['participant_email']) ?>.</p>
