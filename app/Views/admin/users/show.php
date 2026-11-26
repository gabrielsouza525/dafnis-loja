<?php
/** @var array $u @var array $orders @var array $courses @var bool $isSelf */
$teamRequired = App\Services\TwoFactor::teamRequired();
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
<form class="panel" method="post" action="<?= e(url('/admin/usuarios/' . $u['id'] . '/dados')) ?>" id="dados" data-loading-form>
<?= csrf_field() ?>
<input type="hidden" name="_scope" value="dados">
<div class="panel-head"><h2>Dados</h2></div>
<div class="panel-body">
<div class="fields">
<div class="full"><?= partial('field', ['name' => 'name', 'scope' => 'dados', 'id' => 'u-name', 'label' => 'Nome completo', 'value' => $u['name'], 'required' => true, 'attrs' => ['autocomplete' => 'off']]) ?></div>
<div class="full"><?= partial('field', ['name' => 'email', 'scope' => 'dados', 'id' => 'u-email', 'label' => 'E-mail', 'type' => 'email', 'value' => $u['email'], 'required' => true, 'hint' => 'É o login da pessoa. Ao trocar, os cursos dela vão junto e ela recebe um aviso no e-mail antigo e no novo.', 'attrs' => ['autocomplete' => 'off']]) ?></div>
<div class="full"><?= partial('field', ['name' => 'phone', 'scope' => 'dados', 'id' => 'u-phone', 'label' => 'Telefone / WhatsApp', 'type' => 'tel', 'value' => $u['phone'] ? phone_display($u['phone']) : '', 'mask' => 'phone', 'optional' => true, 'attrs' => ['autocomplete' => 'off']]) ?></div>
<div class="full"><?= partial('field', ['name' => 'document', 'scope' => 'dados', 'id' => 'u-document', 'label' => 'CPF', 'value' => $u['document'] ? document_display($u['document']) : '', 'mask' => 'cpf', 'optional' => true, 'attrs' => ['autocomplete' => 'off']]) ?></div>
</div>
<p class="hint">Pedidos já feitos guardam os dados da compra. O nome e o CPF do certificado são os da matrícula: para corrigir, abra o curso em "Cursos como participante".</p>
<button class="btn btn-navy btn-sm" type="submit" style="margin-top:14px">Salvar dados</button>
</div>
</form>
<div class="panel"><div class="panel-head"><h2>Conta</h2></div><div class="panel-body">
<dl class="dl">
<dt>Último acesso</dt><dd><?= e($u['last_login_at'] ? date_br($u['last_login_at'], true) : 'Nunca entrou') ?></dd>
<dt>Duas etapas</dt><dd><?= $u['two_factor_enabled_at'] ? 'Ativada em ' . e(date_br($u['two_factor_enabled_at'])) : ($u['role'] === 'admin' && $teamRequired ? 'Pendente (obrigatória para a equipe)' : 'Desativada') ?></dd>
</dl>
<?php if ($u['two_factor_enabled_at'] && !$isSelf): ?>
<form method="post" action="<?= e(url('/admin/usuarios/' . $u['id'] . '/duas-etapas/desativar')) ?>" data-confirm="Desativar a verificação em duas etapas desta conta? Faça isso só depois de confirmar que é a própria pessoa pedindo (ela perdeu o celular e os códigos de recuperação)." style="margin-top:14px">
<?= csrf_field() ?>
<button class="btn btn-outline btn-xs" type="submit"><?= icon('shield', 'ic-sm') ?>Desativar a verificação em duas etapas</button>
<p class="hint">Para quem perdeu o celular e os códigos. A pessoa recebe um aviso por e-mail.<?= $u['role'] === 'admin' && $teamRequired ? ' Por ser da equipe, ela ativa de novo no próximo acesso ao painel.' : '' ?></p>
</form>
<?php endif; ?>
</div></div>
<form class="panel" method="post" action="<?= e(url('/admin/usuarios/' . $u['id'] . '/fatura')) ?>" id="fatura">
<?= csrf_field() ?>
<div class="panel-head"><h2>Fatura mensal</h2><?php if ($u['invoice_cnpj']): ?><?= partial('status', ['label' => 'Autorizada', 'tone' => 'ok']) ?><?php endif; ?></div>
<div class="panel-body">
<p class="hint" style="margin:0 0 12px">Para empresas que pagam todos os treinamentos de uma vez, no fim do mês. Com a fatura autorizada, esta conta vê a opção "Fatura mensal" no checkout: o acesso é liberado na hora e o pedido entra em Faturamento, para cobrar no fechamento.</p>
<label class="switch"><input type="hidden" name="invoice_enabled" value="0"><input type="checkbox" name="invoice_enabled" value="1"<?= checked((bool) $u['invoice_cnpj'] || (has_old() && old('invoice_enabled') === '1')) ?>><span>Pode comprar com fatura mensal<small>Vale só para o CNPJ abaixo, para ninguém faturar em nome de outra empresa.</small></span></label>
<div style="margin-top:12px"><?= partial('field', ['name' => 'invoice_cnpj', 'label' => 'CNPJ da fatura', 'value' => $u['invoice_cnpj'] ? document_display($u['invoice_cnpj']) : '', 'placeholder' => '00.000.000/0000-00', 'mask' => 'cnpj']) ?></div>
<button class="btn btn-navy btn-sm" type="submit" style="margin-top:14px">Salvar fatura mensal</button>
</div>
</form>
<form class="panel" method="post" action="<?= e(url('/admin/usuarios/' . $u['id'])) ?>" data-confirm="Salvar as alterações de acesso deste usuário?">
<?= csrf_field() ?>
<div class="panel-head"><h2>Acesso</h2></div>
<div class="panel-body">
<?php if ($isSelf): ?><p class="hint" style="margin:0">Esta é a sua conta: o perfil e o status não podem ser alterados por você.</p><?php else: ?>
<?= partial('field', ['name' => 'role', 'label' => 'Perfil', 'type' => 'select', 'value' => $u['role'], 'options' => ['student' => 'Aluno / comprador', 'admin' => 'Equipe (acesso ao painel)'], 'hint' => $teamRequired ? 'A equipe precisa ativar a verificação em duas etapas para abrir o painel.' : null]) ?>
<label class="switch" style="margin-top:10px"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1"<?= checked((int) $u['is_active']) ?>><span>Conta ativa<small>Desativar encerra as sessões e impede o login.</small></span></label>
<button class="btn btn-navy btn-sm" type="submit" style="margin-top:14px">Salvar acesso</button>
<?php endif; ?>
</div>
</form>
</aside>
</div>
