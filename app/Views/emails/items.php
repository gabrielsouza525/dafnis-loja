<?php /** @var array $order @var array $items */ ?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E2E7EE;border-radius:10px;margin:18px 0;font-size:14px">
<?php foreach ($items as $i): ?>
<tr><td style="padding:10px 14px;border-bottom:1px solid #EEF1F5"><?= e(($i['course_code'] ? $i['course_code'] . ' — ' : '') . $i['course_title']) ?><br><span style="color:#566074;font-size:12px"><?= (int) $i['quantity'] ?> × <?= money($i['unit_price']) ?></span></td><td align="right" style="padding:10px 14px;border-bottom:1px solid #EEF1F5;white-space:nowrap"><?= money($i['line_total']) ?></td></tr>
<?php endforeach; ?>
<?php if ((float) $order['discount'] > 0): ?><tr><td style="padding:8px 14px;color:#0F6A32">Descontos</td><td align="right" style="padding:8px 14px;color:#0F6A32">− <?= money($order['discount']) ?></td></tr><?php endif; ?>
<tr><td style="padding:12px 14px;font-weight:bold">Total</td><td align="right" style="padding:12px 14px;font-weight:bold;font-size:16px"><?= money($order['total']) ?></td></tr>
</table>
