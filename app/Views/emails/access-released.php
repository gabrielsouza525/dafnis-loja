<?php /** @var array $enrollment @var string|null $accessUrl @var bool $internal curso aberto na própria loja @var string $accountUrl */ ?>
<?= App\Core\View::file('emails/title', ['kicker' => 'Acesso liberado', 'title' => 'Seu treinamento já está disponível', 'color' => '#174BB0']) ?>
<p style="margin:0 0 12px">Olá, <?= e(first_name((string) $enrollment['participant_name'])) ?>!</p>
<p style="margin:0 0 12px">Seu acesso ao treinamento <strong><?= e(($enrollment['course_code'] ? $enrollment['course_code'] . ' — ' : '') . $enrollment['course_title']) ?></strong> foi liberado.</p>
<?php if ($accessUrl): ?>
<?= App\Core\View::file('emails/button', ['url' => $accessUrl, 'label' => 'Acessar o treinamento']) ?>
<?php if (!empty($internal)): ?>
<p style="margin:0 0 12px">O curso é feito aqui no nosso site. Entre com o seu e-mail (<?= e((string) $enrollment['participant_email']) ?>); se ainda não tem conta, crie uma com este mesmo e-mail.</p>
<?php else: ?>
<p style="margin:0 0 12px">Use o seu e-mail (<?= e((string) $enrollment['participant_email']) ?>) para entrar na plataforma de ensino.</p>
<?php endif; ?>
<?php else: ?>
<p style="margin:0 0 12px">As instruções de acesso à plataforma de ensino chegam em seguida pela nossa equipe.</p>
<?php endif; ?>
<?php if (empty($internal)): ?>
<p style="margin:0;color:#566074">Crie uma conta na loja com este mesmo e-mail para acompanhar o progresso e baixar o certificado: <a href="<?= e($accountUrl) ?>"><?= e($accountUrl) ?></a></p>
<?php endif; ?>
