<?php
/** @var array $coupons */
use App\Models\Coupon;
?>
<div class="adm-head"><div><h1>Cupons</h1><p>Descontos aplicados no carrinho. O limite de usos conta os pedidos registrados.</p></div>
<div class="adm-actions"><a class="btn btn-navy btn-sm" href="<?= e(url('/admin/cupons/novo')) ?>"><?= icon('plus', 'ic-sm') ?>Novo cupom</a></div></div>
<div class="panel">
<div class="table-wrap"><table class="table">
<thead><tr><th>Código</th><th>Desconto</th><th>Validade</th><th class="num">Usos</th><th>Situação</th><th></th></tr></thead>
<tbody>
<?php foreach ($coupons as $c): ?>
<tr<?= (int) $c['is_active'] ? '' : ' class="is-off"' ?>>
<td><a class="mono" href="<?= e(url('/admin/cupons/' . $c['id'] . '/editar')) ?>"><?= e($c['code']) ?></a><?= $c['description'] ? '<span class="sub">' . e($c['description']) . '</span>' : '' ?></td>
<td><?= e(Coupon::describe($c)) ?><?= $c['min_subtotal'] !== null ? '<span class="sub">Compras a partir de ' . money($c['min_subtotal']) . '</span>' : '' ?></td>
<td><?= $c['starts_at'] || $c['ends_at'] ? e(($c['starts_at'] ? date_br($c['starts_at']) : '…') . ' a ' . ($c['ends_at'] ? date_br($c['ends_at']) : '…')) : 'Sem prazo' ?></td>
<td class="num"><?= (int) $c['uses'] ?><?= $c['max_uses'] !== null ? ' / ' . (int) $c['max_uses'] : '' ?></td>
<td><?= partial('status', ['label' => (int) $c['is_active'] ? 'Ativo' : 'Inativo', 'tone' => (int) $c['is_active'] ? 'ok' : 'muted']) ?></td>
<td class="num"><a class="btn btn-ghost btn-xs" href="<?= e(url('/admin/cupons/' . $c['id'] . '/editar')) ?>">Editar</a></td>
</tr>
<?php endforeach; ?>
<?php if (!$coupons): ?><tr><td colspan="6" class="muted">Nenhum cupom cadastrado.</td></tr><?php endif; ?>
</tbody></table></div>
</div>
