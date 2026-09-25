<?php /** @var array $enrollment @var string|null $accessUrl @var string $accountUrl */ ?>
<p style="margin:0 0 12px">Olá, <?= e(first_name((string) $enrollment['participant_name'])) ?>!</p>
<p style="margin:0 0 12px">Seu acesso ao treinamento <strong><?= e(($enrollment['course_code'] ? $enrollment['course_code'] . ' — ' : '') . $enrollment['course_title']) ?></strong> foi liberado.</p>
<?php if ($accessUrl): ?>
<?= App\Core\View::file('emails/button', ['url' => $accessUrl, 'label' => 'Acessar o treinamento']) ?>
<p style="margin:0 0 12px">Use o seu e-mail (<?= e((string) $enrollment['participant_email']) ?>) para entrar na plataforma de ensino.</p>
<?php else: ?>
<p style="margin:0 0 12px">As instruções de acesso à plataforma de ensino chegam em seguida pela nossa equipe.</p>
<?php endif; ?>
<p style="margin:0;color:#566074">Crie uma conta na loja com este mesmo e-mail para acompanhar o progresso e baixar o certificado: <a href="<?= e($accountUrl) ?>"><?= e($accountUrl) ?></a></p>
