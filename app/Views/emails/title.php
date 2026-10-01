<?php
/** Rótulo e título no topo do e-mail (só HTML simples: clientes de e-mail removem SVG). @var string $kicker @var string $title @var string|null $color */
?>
<p style="margin:0 0 6px;font-size:11px;letter-spacing:2px;font-weight:bold;text-transform:uppercase;color:<?= e($color ?? '#174BB0') ?>;font-family:Courier New,monospace"><?= e($kicker) ?></p>
<h1 style="margin:0 0 18px;font-size:22px;line-height:1.3;color:#0E1726;font-family:Arial,Helvetica,sans-serif"><?= e($title) ?></h1>
