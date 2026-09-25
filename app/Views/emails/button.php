<?php /** @var string $url @var string $label @var string|null $color */ ?>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0"><tr><td style="background:<?= e($color ?? '#1F5FD6') ?>;border-radius:10px">
<a href="<?= e($url) ?>" style="display:inline-block;padding:13px 22px;color:#ffffff;font-weight:bold;text-decoration:none;font-size:15px"><?= e($label) ?></a>
</td></tr></table>
<p style="font-size:12px;color:#566074">Se o botão não funcionar, copie e cole este endereço no navegador:<br><span style="word-break:break-all"><?= e($url) ?></span></p>
