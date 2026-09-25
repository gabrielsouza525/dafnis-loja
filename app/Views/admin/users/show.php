<?php
/** @var array $u @var array $orders @var array $courses @var bool $isSelf */
use App\Models\Enrollment;
use App\Models\Order;
?>
<div class="adm-head"><div><h1><?= e($u['name']) ?></h1><p><?= e($u['email']) ?> · cadastro em <?= e(date_br($u['created_at'])) ?></p></div>
<div class="adm-actions"><a class="btn btn-outline btn-sm" href="<?= e(url('/admin/usuarios')) ?>"><?= icon('arrowL', 'ic-sm') ?>Usuários</a></div></div>
<?php if ($err = field_error('role')): ?><div class="note-box err" style="margin:0 0 16px"><?= icon('alert') ?><span><?= e($err) ?></span></div><?php endif; ?>
<div class="grid-2">
<div>
<div class="panel"><div class="panel-head"><h2>Pedidos</h2></div>
<?php if ($orders): ?>
<div class="table-wrap"><table class="table"><thead><tr><th>Pedido</th><th>Data</th><th>Situação</th><th class="num">Total</th></tr></thead><tbody>
<?php foreach ($orders as $o): ?><tr><td><a href="<?= e(url('/admin/pedidos/' . $o['id'])) ?>"><?= e($o['number']) ?></a></td><td><?= e(date_br($o['created_at'])) ?></td><td><?= partial('status', ['label' => Order::statusLabel($o['status']), 'tone' => Order::STATUS_TONE[$o['status']] ?? 'muted']) ?></td><td class="num"><?= money($o['total']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php else: ?><div class="panel-body"><p class="muted">Nenhum pedido.</p></div><?php endif; ?>
</div>
<div class="panel"><div class="panel-head"><h2>Cursos como participante</h2></div>
<?php if ($courses): ?>
<div class="table-wrap"><table class="table"><thead><tr><th>Treinamento</th><th>Progresso</th><th>Situação</th><th></th></tr></thead><tbody>
<?php foreach ($courses as $e): ?><tr><td><?= e(($e['course_code'] ? $e['course_code'] . ' — ' : '') . $e['course_title']) ?></td><td><?= (int) $e['progress'] ?>%</td><td><?= partial('status', ['label' => Enrollment::statusLabel($e['status']), 'tone' => Enrollment::STATUS_TONE[$e['status']] ?? 'muted']) ?></td><td class="num"><a class="btn btn-ghost btn-xs" href="<?= e(url('/admin/matriculas/' . $e['id'])) ?>">Abrir</a></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php else: ?><div class="panel-body"><p class="muted">Nenhum curso.</p></div><?php endif; ?>
</div>
</div>
<aside>
<div class="panel"><div class="panel-head"><h2>Dados</h2></div><div class="panel-body">
<dl class="dl">
<dt>Telefone</dt><dd><?= e($u['phone'] ? phone_display($u['phone']) : '—') ?></dd>
<dt>CPF</dt><dd><?= e($u['document'] ? document_display($u['document']) : '—') ?></dd>
<dt>Último acesso</dt><dd><?= e($u['last_login_at'] ? date_br($u['last_login_at'], true) : 'Nunca entrou') ?></dd>
</dl>
</div></div>
<form class="panel" method="post" action="<?= e(url('/admin/usuarios/' . $u['id'])) ?>" data-confirm="Salvar as alterações de acesso deste usuário?">
<?= csrf_field() ?>
<div class="panel-head"><h2>Acesso</h2></div>
<div class="panel-body">
<?php if ($isSelf): ?><p class="hint" style="margin:0">Esta é a sua conta: o perfil e o status não podem ser alterados por você.</p><?php else: ?>
<?= partial('field', ['name' => 'role', 'label' => 'Perfil', 'type' => 'select', 'value' => $u['role'], 'options' => ['student' => 'Aluno / comprador', 'admin' => 'Equipe (acesso ao painel)']]) ?>
<label class="switch" style="margin-top:10px"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1"<?= checked((int) $u['is_active']) ?>><span>Conta ativa<small>Desativar encerra as sessões e impede o login.</small></span></label>
<button class="btn btn-navy btn-sm" type="submit" style="margin-top:14px">Salvar acesso</button>
<?php endif; ?>
</div>
</form>
</aside>
</div>
