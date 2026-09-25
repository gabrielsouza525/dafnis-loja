<?php /** @var string $name @var string $url */ ?>
<p style="margin:0 0 12px">Olá, <?= e(first_name($name)) ?>!</p>
<p style="margin:0 0 12px">Sua conta na loja de treinamentos da Dafnis foi criada. Nela você acompanha pedidos, indica os participantes das vagas compradas e encontra seus cursos e certificados.</p>
<?= App\Core\View::file('emails/button', ['url' => $url, 'label' => 'Acessar minha conta']) ?>
