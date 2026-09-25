<?php
/** @var string $content @var string $subject */
use App\Services\Settings;

$name = Settings::businessName();
$contact = Settings::get('business.whatsapp') ?: Settings::get('business.phone');
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($subject) ?></title>
</head>
<body style="margin:0;padding:0;background:#F5F7FA;font-family:Arial,Helvetica,sans-serif;color:#2B3445">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F7FA;padding:24px 12px">
<tr><td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #E2E7EE">
<tr><td style="background:#0B2545;padding:20px 28px">
<span style="font-size:22px;font-weight:bold;color:#ffffff;letter-spacing:-0.5px">Dafnis</span><br>
<span style="font-size:10px;letter-spacing:2px;color:#8FA3C0;font-family:Courier New,monospace">SOLUÇÕES EM EPI · TREINAMENTOS</span>
</td></tr>
<tr><td style="height:5px;background:#F47B2E"></td></tr>
<tr><td style="padding:28px;font-size:15px;line-height:1.6">
<?= $content ?>
</td></tr>
<tr><td style="padding:18px 28px;background:#F5F7FA;border-top:1px solid #E2E7EE;font-size:12px;color:#566074;line-height:1.5">
<?= e($name) ?><?= $contact ? ' · ' . e(phone_display($contact)) : '' ?><br>
Você recebeu este e-mail por causa de uma conta, um pedido ou um treinamento na loja <?= e(absolute_url('/')) ?>.
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
