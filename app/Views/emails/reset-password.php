<?php /** @var string $name @var string $url @var int $minutes */ ?>
<p style="margin:0 0 12px">Olá, <?= e(first_name($name)) ?>.</p>
<p style="margin:0 0 12px">Recebemos um pedido para criar uma nova senha para a sua conta. O link abaixo vale por <?= (int) $minutes ?> minutos e só pode ser usado uma vez.</p>
<?= App\Core\View::file('emails/button', ['url' => $url, 'label' => 'Criar nova senha']) ?>
<p style="margin:0;color:#566074">Se não foi você, ignore este e-mail. Sua senha atual continua valendo.</p>
