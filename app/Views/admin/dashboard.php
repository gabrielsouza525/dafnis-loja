<?php
/** @var array $kpis @var array $orders @var array $toRelease @var array $contacts @var bool $online */
use App\Controllers\Site\PageController;
use App\Models\Order;
?>
<div class="adm-head"><div><h1>Visão geral</h1><p><?= e(date_long(date('Y-m-d'))) ?></p></div>
<div class="adm-actions"><a class="btn btn-navy btn-sm" href="<?= e(url('/admin/cursos/novo')) ?>"><?= icon('plus', 'ic-sm') ?>Novo curso</a></div></div>

<?php if (!$online): ?>
<div class="note-box" style="margin:0 0 22px"><?= icon('info') ?><span><strong>Pagamento online desativado.</strong> Os pedidos entram como "aguardando pagamento" e a baixa é feita aqui, em Pedidos. Para cobrar pelo Mercado Pago, preencha <code>MP_ACCESS_TOKEN</code> no arquivo .env (veja o README).</span></div>
<?php endif; ?>

<div class="kpis-admin">
<div class="kpi"><span class="kpi-ic o"><?= icon('clock') ?></span><span class="kpi-n"><?= (int) $kpis['pending'] ?></span><span class="kpi-l">Pedidos aguardando pagamento · <?= money($kpis['pending_total']) ?></span><a href="<?= e(url('/admin/pedidos', ['status' => 'pending'])) ?>">Ver pedidos</a></div>
<div class="kpi"><span class="kpi-ic g"><?= icon('chart') ?></span><span class="kpi-n"><?= money($kpis['revenue_month']) ?></span><span class="kpi-l">Recebido no mês · <?= e(pluralize((int) $kpis['paid_month'], 'pedido', 'pedidos')) ?></span><a href="<?= e(url('/admin/pedidos', ['status' => 'paid'])) ?>">Ver pagos</a></div>
<div class="kpi"><span class="kpi-ic"><?= icon('users') ?></span><span class="kpi-n"><?= (int) $kpis['to_release'] ?></span><span class="kpi-l">Acessos a liberar · <?= e(pluralize((int) $kpis['awaiting'], 'vaga sem participante', 'vagas sem participante')) ?></span><a href="<?= e(url('/admin/matriculas', ['status' => 'processing'])) ?>">Liberar acessos</a></div>
<div class="kpi"><span class="kpi-ic"><?= icon('book') ?></span><span class="kpi-n"><?= (int) $kpis['courses'] ?></span><span class="kpi-l">Cursos ativos · <?= e(pluralize((int) $kpis['students'], 'aluno', 'alunos')) ?></span><a href="<?= e(url('/admin/cursos')) ?>">Gerenciar cursos</a></div>
</div>

<div class="grid-2">
<div>
<div class="panel">
<div class="panel-head"><h2>Últimos pedidos</h2><a class="text-link" href="<?= e(url('/admin/pedidos')) ?>">Todos<?= icon('arrowR', 'ic-sm') ?></a></div>
<?php if ($orders): ?>
<div class="table-wrap"><table class="table"><thead><tr><th>Pedido</th><th>Comprador</th><th>Situação</th><th class="num">Total</th></tr></thead><tbody>
<?php foreach ($orders as $o): ?>
<tr><td><a href="<?= e(url('/admin/pedidos/' . $o['id'])) ?>"><?= e($o['number']) ?></a><span class="sub"><?= e(time_ago($o['created_at'])) ?></span></td><td><?= e($o['buyer_type'] === 'pj' ? $o['company_name'] : $o['buyer_name']) ?><span class="sub"><?= e($o['buyer_email']) ?></span></td><td><?= partial('status', ['label' => Order::statusLabel($o['status']), 'tone' => Order::STATUS_TONE[$o['status']] ?? 'muted']) ?></td><td class="num"><?= money($o['total']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php else: ?><div class="panel-body"><p class="muted">Nenhum pedido ainda.</p></div><?php endif; ?>
</div>
<div class="panel">
<div class="panel-head"><h2>Acessos a liberar</h2><a class="text-link" href="<?= e(url('/admin/matriculas', ['status' => 'processing'])) ?>">Todos<?= icon('arrowR', 'ic-sm') ?></a></div>
<?php if ($toRelease): ?>
<div class="table-wrap"><table class="table"><thead><tr><th>Participante</th><th>Treinamento</th><th></th></tr></thead><tbody>
<?php foreach ($toRelease as $e): ?>
<tr><td><?= e((string) $e['participant_name']) ?><span class="sub"><?= e((string) $e['participant_email']) ?></span></td><td><?= e(($e['course_code'] ? $e['course_code'] . ' — ' : '') . $e['course_title']) ?><span class="sub">Pedido <?= e($e['order_number']) ?></span></td><td class="num"><a class="btn btn-navy btn-xs" href="<?= e(url('/admin/matriculas/' . $e['id'])) ?>">Liberar</a></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php else: ?><div class="panel-body"><p class="muted">Nenhum acesso aguardando liberação.</p></div><?php endif; ?>
</div>
</div>
<aside>
<div class="panel">
<div class="panel-head"><h2>Contatos novos</h2><a class="text-link" href="<?= e(url('/admin/contatos')) ?>">Todos<?= icon('arrowR', 'ic-sm') ?></a></div>
<div class="panel-body">
<?php if ($contacts): ?>
<ul class="timeline">
<?php foreach ($contacts as $c): ?><li><div><strong><?= e($c['name']) ?></strong> · <?= e(PageController::SUBJECTS[$c['subject']] ?? $c['subject']) ?><small><?= e($c['email']) ?> · <?= e(time_ago($c['created_at'])) ?></small></div></li><?php endforeach; ?>
</ul>
<?php else: ?><p class="muted">Nenhum contato novo.</p><?php endif; ?>
</div>
</div>
<div class="panel">
<div class="panel-head"><h2>Rotina da equipe</h2></div>
<div class="panel-body">
<ol class="timeline">
<li><div><strong>Confirmar pagamentos</strong><small>Pedidos pagos por fora do site recebem a baixa em Pedidos.</small></div></li>
<li><div><strong>Liberar acessos</strong><small>Cadastre o participante na plataforma de ensino e marque a vaga como "Em andamento".</small></div></li>
<li><div><strong>Anexar certificados</strong><small>Ao concluir, envie o PDF na matrícula: o aluno recebe o aviso por e-mail.</small></div></li>
</ol>
</div>
</div>
</aside>
</div>
