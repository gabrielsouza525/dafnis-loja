<?php /** @var array $order @var array $items @var int $awaiting @var string|null $ready link do curso já liberado @var int $readyCount @var string $url */ ?>
<?= App\Core\View::file('emails/title', ['kicker' => 'Pagamento confirmado', 'title' => 'Tudo certo com o pedido ' . $order['number'], 'color' => '#0F6A32']) ?>
<p style="margin:0 0 12px">Olá, <?= e(first_name($order['buyer_name'])) ?>.</p>
<p style="margin:0 0 12px">O pagamento do pedido <strong><?= e($order['number']) ?></strong> foi confirmado. Obrigado!</p>
<?= App\Core\View::file('emails/items', ['order' => $order, 'items' => $items]) ?>
<?php if (!empty($ready)): ?>
<p style="margin:0 0 12px"><strong>Seu acesso já está liberado.</strong> O curso é feito aqui no nosso site, na sua conta (Minha conta › Meus cursos). Pode começar agora mesmo.</p>
<?= App\Core\View::file('emails/button', ['url' => $ready, 'label' => 'Começar o curso', 'color' => '#15803D']) ?>
<?php if ($awaiting > 0): ?><p style="margin:0 0 12px">Ainda <?= $awaiting === 1 ? 'falta indicar quem vai fazer a outra vaga' : 'faltam indicar os participantes de ' . e(pluralize($awaiting, 'vaga', 'vagas')) ?>: <a href="<?= e($url) ?>">indicar participantes</a>.</p><?php endif; ?>
<?php elseif ($awaiting > 0): ?>
<p style="margin:0 0 12px"><strong>Próximo passo:</strong> informe quem vai fazer cada treinamento (<?= e(pluralize($awaiting, 'vaga', 'vagas')) ?> sem participante). Assim que você indicar, liberamos o acesso e o participante recebe o link por e-mail.</p>
<?= App\Core\View::file('emails/button', ['url' => $url, 'label' => 'Indicar participantes', 'color' => '#15803D']) ?>
<?php else: ?>
<p style="margin:0 0 12px"><strong>Próximo passo:</strong> estamos liberando o acesso na plataforma de ensino. Você recebe o link por e-mail e o curso aparece em Minha conta › Meus cursos.</p>
<?= App\Core\View::file('emails/button', ['url' => $url, 'label' => 'Acompanhar pedido']) ?>
<?php endif; ?>
