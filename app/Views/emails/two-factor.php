<?php /** @var string $name @var bool $enabled @var bool $byTeam @var bool $deviceQr QR aberto para outro celular @var string $url */
$deviceQr = $deviceQr ?? false;
$title = $deviceQr ? 'QR code aberto para outro celular' : ($enabled ? 'Verificação em duas etapas ativada' : 'Verificação em duas etapas desativada');
?>
<?= App\Core\View::file('emails/title', ['kicker' => 'Segurança da conta', 'title' => $title, 'color' => $enabled ? '#0F6A32' : '#7F5A10']) ?>
<p style="margin:0 0 12px">Olá, <?= e(first_name($name)) ?>.</p>
<?php if ($deviceQr): ?>
<p style="margin:0 0 12px">O QR code da verificação em duas etapas foi aberto na sua conta, com a sua senha, para conectar mais um celular. Todos os celulares conectados geram os mesmos códigos.</p>
<?php elseif ($enabled): ?>
<p style="margin:0 0 12px">A partir de agora, ao entrar na sua conta, pedimos também o código do aplicativo autenticador do seu celular. Guarde os códigos de recuperação: eles servem para entrar se você perder o celular.</p>
<?php elseif ($byTeam): ?>
<p style="margin:0 0 12px">A nossa equipe desativou a verificação em duas etapas da sua conta, como você pediu. Para continuar protegido, ative de novo assim que puder.</p>
<?php else: ?>
<p style="margin:0 0 12px">A verificação em duas etapas foi desativada na sua conta. Agora basta o e-mail e a senha para entrar.</p>
<?php endif; ?>
<?= App\Core\View::file('emails/button', ['url' => $url, 'label' => 'Ver a segurança da conta', 'color' => '#0B2545']) ?>
<p style="margin:0;color:#566074">Não foi você? Troque a sua senha<?= $deviceQr ? ', desative e ative de novo a verificação (isso troca o QR code)' : '' ?> e fale com a nossa equipe.</p>
