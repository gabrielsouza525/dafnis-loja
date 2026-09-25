<?php /** @var array $order @var array $items @var int $awaiting @var string $url */ ?>
<p style="margin:0 0 12px">Olá, <?= e(first_name($order['buyer_name'])) ?>.</p>
<p style="margin:0 0 12px">O pagamento do pedido <strong><?= e($order['number']) ?></strong> foi confirmado. Obrigado!</p>
<?= App\Core\View::file('emails/items', ['order' => $order, 'items' => $items]) ?>
<?php if ($awaiting > 0): ?>
<p style="margin:0 0 12px"><strong>Próximo passo:</strong> informe quem vai fazer cada treinamento (<?= e(pluralize($awaiting, 'vaga', 'vagas')) ?> sem participante). Assim que você indicar, liberamos o acesso e o participante recebe o link por e-mail.</p>
<?= App\Core\View::file('emails/button', ['url' => $url, 'label' => 'Indicar participantes', 'color' => '#15803D']) ?>
<?php else: ?>
<p style="margin:0 0 12px"><strong>Próximo passo:</strong> estamos liberando o acesso na plataforma de ensino. Você recebe o link por e-mail e o curso aparece em Minha conta › Meus cursos.</p>
<?= App\Core\View::file('emails/button', ['url' => $url, 'label' => 'Acompanhar pedido']) ?>
<?php endif; ?>
