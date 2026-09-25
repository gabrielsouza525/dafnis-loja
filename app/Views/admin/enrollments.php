<?php
/** @var array $page @var string $status @var string $q @var string $orderNumber @var array $counts */
use App\Models\Enrollment;
?>
<div class="adm-head"><div><h1>Matrículas e certificados</h1><p>Cada linha é uma vaga comprada. Libere o acesso na plataforma de ensino e registre a conclusão.</p></div></div>
<div class="chips" style="margin-bottom:14px">
<a href="<?= e(url('/admin/matriculas', ['q' => $q, 'pedido' => $orderNumber])) ?>"<?= $status === '' ? ' class="on"' : '' ?>>Todas <span><?= (int) array_sum($counts) ?></span></a>
<?php foreach (Enrollment::STATUS as $key => $label): ?><a href="<?= e(url('/admin/matriculas', ['status' => $key, 'q' => $q, 'pedido' => $orderNumber])) ?>"<?= $status === $key ? ' class="on"' : '' ?>><?= e($label) ?> <span><?= (int) ($counts[$key] ?? 0) ?></span></a><?php endforeach; ?>
</div>
<form class="toolbar" method="get" action="<?= e(url('/admin/matriculas')) ?>">
<input class="input search" type="search" name="q" value="<?= e($q) ?>" placeholder="Participante, e-mail ou treinamento" aria-label="Buscar matrículas">
<input class="input" name="pedido" value="<?= e($orderNumber) ?>" placeholder="Pedido (DF000123)" aria-label="Número do pedido" style="min-width:160px">
<?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
<button class="btn btn-outline btn-sm" type="submit">Buscar</button>
</form>
<div class="panel">
<div class="table-wrap"><table class="table">
<thead><tr><th>Participante</th><th>Treinamento</th><th>Pedido / comprador</th><th>Progresso</th><th>Situação</th><th></th></tr></thead>
<tbody>
<?php foreach ($page['rows'] as $e): ?>
<tr>
<td><?= $e['participant_name'] ? e($e['participant_name']) . '<span class="sub">' . e((string) $e['participant_email']) . '</span>' : '<span class="muted">Não indicado</span>' ?></td>
<td><?= e(($e['course_code'] ? $e['course_code'] . ' — ' : '') . $e['course_title']) ?></td>
<td><a href="<?= e(url('/admin/pedidos/' . $e['order_id'])) ?>"><?= e($e['order_number']) ?></a><span class="sub"><?= e($e['buyer_type'] === 'pj' ? (string) $e['company_name'] : $e['buyer_name']) ?></span></td>
<td><?= (int) $e['progress'] ?>%<?= $e['certificate_id'] ? '<span class="sub">Certificado anexado</span>' : '' ?></td>
<td><?= partial('status', ['label' => Enrollment::statusLabel($e['status']), 'tone' => Enrollment::STATUS_TONE[$e['status']] ?? 'muted']) ?></td>
<td class="num"><a class="btn btn-<?= $e['status'] === 'processing' ? 'navy' : 'ghost' ?> btn-xs" href="<?= e(url('/admin/matriculas/' . $e['id'])) ?>"><?= $e['status'] === 'processing' ? 'Liberar' : 'Abrir' ?></a></td>
</tr>
<?php endforeach; ?>
<?php if (!$page['rows']): ?><tr><td colspan="6" class="muted">Nenhuma matrícula encontrada.</td></tr><?php endif; ?>
</tbody></table></div>
<?= partial('admin-pager', ['p' => $page]) ?>
</div>
