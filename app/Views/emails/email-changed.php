<?php /** @var string $name @var string $old e-mail anterior @var string $new e-mail novo @var string $url */ ?>
<?= App\Core\View::file('emails/title', ['kicker' => 'Segurança da conta', 'title' => 'Seu e-mail de acesso foi alterado', 'color' => '#7F5A10']) ?>
<p style="margin:0 0 12px">Olá, <?= e(first_name($name)) ?>.</p>
<p style="margin:0 0 12px">A nossa equipe alterou o e-mail de acesso da sua conta de <strong><?= e($old) ?></strong> para <strong><?= e($new) ?></strong>. A senha continua a mesma, e os seus cursos e certificados passam para o e-mail novo.</p>
<p style="margin:0 0 12px">Para entrar, use o e-mail novo.</p>
<?= App\Core\View::file('emails/button', ['url' => $url, 'label' => 'Entrar na minha conta', 'color' => '#0B2545']) ?>
<p style="margin:0;color:#566074">Não pediu essa alteração? Fale com a nossa equipe.</p>
