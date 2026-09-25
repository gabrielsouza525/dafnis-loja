<?php
/** @var array $user @var array $kpis @var array $current @var array $orders @var int $awaiting @var int $teamSeats */
use App\Models\Order;

$crumbs = [['Início', '/'], ['Minha conta', null]];
?>
<div class="screen">
<?= partial('account-shell-open', get_defined_vars()) ?>
<div class="acc-head"><div><h1>Olá, <?= e(first_name($user['name'])) ?></h1><p>Acompanhe seus cursos, certificados e compras.</p></div><a class="btn btn-navy btn-sm" href="<?= e(url('/cursos')) ?>">Explorar cursos</a></div>

<?php if ($awaiting > 0): ?>
<div class="note-box" style="margin:0 0 22px"><?= icon('users') ?><span><strong><?= e(pluralize($awaiting, 'vaga aguarda', 'vagas aguardam')) ?> participante.</strong> Informe quem vai fazer o treinamento para liberarmos o acesso. <a href="<?= e(url('/minha-conta/pedidos')) ?>">Indicar participantes</a></span></div>
<?php endif; ?>

<div class="kpis">
<div class="kpi"><span class="kpi-ic"><?= icon('book') ?></span><span class="kpi-n"><?= (int) $kpis['courses'] ?></span><span class="kpi-l">Cursos adquiridos</span></div>
<div class="kpi"><span class="kpi-ic"><?= icon('pulse') ?></span><span class="kpi-n"><?= (int) $kpis['active'] ?></span><span class="kpi-l">Em andamento</span></div>
<div class="kpi"><span class="kpi-ic g"><?= icon('check') ?></span><span class="kpi-n"><?= (int) $kpis['completed'] ?></span><span class="kpi-l">Concluídos</span></div>
<div class="kpi"><span class="kpi-ic o"><?= icon('award') ?></span><span class="kpi-n"><?= (int) $kpis['certificates'] ?></span><span class="kpi-l">Certificados disponíveis</span></div>
</div>

<div class="panel">
<div class="panel-head"><h2>Continuar estudando</h2><a class="text-link" href="<?= e(url('/minha-conta/cursos')) ?>">Todos os cursos<?= icon('arrowR', 'ic-sm') ?></a></div>
<div class="panel-body">
<?php if ($current): ?>
<div class="my-courses"><?php foreach ($current as $e): ?><?= partial('my-course', ['e' => $e]) ?><?php endforeach; ?></div>
<?php else: ?>
<div class="empty" style="padding:40px 20px">
<span class="empty-ic"><?= icon('book') ?></span>
<h3>Nenhum curso em andamento</h3>
<p><?= $kpis['completed'] ? 'Você concluiu todos os seus cursos. Que tal o próximo?' : 'Quando você comprar um treinamento (ou uma empresa indicar você como participante), ele aparece aqui.' ?></p>
<a class="btn btn-primary" href="<?= e(url('/cursos')) ?>">Explorar cursos</a>
</div>
<?php endif; ?>
</div>
</div>

<div class="panel">
<div class="panel-head"><h2>Compras recentes</h2><a class="text-link" href="<?= e(url('/minha-conta/pedidos')) ?>">Todos os pedidos<?= icon('arrowR', 'ic-sm') ?></a></div>
<?php if ($orders): ?>
<div class="table-wrap"><table class="table">
<thead><tr><th>Pedido</th><th>Data</th><th>Participantes</th><th>Situação</th><th class="num">Total</th></tr></thead>
<tbody>
<?php foreach ($orders as $o): ?>
<tr><td><a href="<?= e(url('/minha-conta/pedidos/' . $o['number'])) ?>"><?= e($o['number']) ?></a></td><td><?= e(date_br($o['created_at'])) ?></td><td><?= (int) $o['participants'] ?></td><td><?= partial('status', ['label' => Order::statusLabel($o['status']), 'tone' => Order::STATUS_TONE[$o['status']] ?? 'muted']) ?></td><td class="num"><?= money($o['total']) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<?php else: ?>
<div class="panel-body"><p class="muted">Você ainda não fez nenhum pedido.</p></div>
<?php endif; ?>
</div>
<?= partial('account-shell-close') ?>
</div>
