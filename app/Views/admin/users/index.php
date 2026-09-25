<?php /** @var array $page @var string $q @var string $role */ ?>
<div class="adm-head"><div><h1>Usuários</h1><p>Alunos, compradores e a equipe com acesso ao painel.</p></div></div>
<div class="chips" style="margin-bottom:14px">
<?php foreach (['' => 'Todos', 'student' => 'Alunos e compradores', 'admin' => 'Equipe (admin)'] as $key => $label): ?><a href="<?= e(url('/admin/usuarios', ['perfil' => $key, 'q' => $q])) ?>"<?= $role === $key ? ' class="on"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
</div>
<form class="toolbar" method="get" action="<?= e(url('/admin/usuarios')) ?>">
<input class="input search" type="search" name="q" value="<?= e($q) ?>" placeholder="Nome, e-mail ou CPF" aria-label="Buscar usuários">
<?php if ($role): ?><input type="hidden" name="perfil" value="<?= e($role) ?>"><?php endif; ?>
<button class="btn btn-outline btn-sm" type="submit">Buscar</button>
</form>
<div class="panel">
<div class="table-wrap"><table class="table">
<thead><tr><th>Nome</th><th>Contato</th><th>Perfil</th><th class="num">Pedidos pagos</th><th class="num">Cursos</th><th>Cadastro</th></tr></thead>
<tbody>
<?php foreach ($page['rows'] as $u): ?>
<tr<?= (int) $u['is_active'] ? '' : ' class="is-off"' ?>>
<td><a href="<?= e(url('/admin/usuarios/' . $u['id'])) ?>"><?= e($u['name']) ?></a><?= (int) $u['is_active'] ? '' : '<span class="sub">Desativado</span>' ?></td>
<td><?= e($u['email']) ?><span class="sub"><?= e($u['phone'] ? phone_display($u['phone']) : '—') ?></span></td>
<td><?= partial('status', ['label' => $u['role'] === 'admin' ? 'Equipe' : 'Aluno', 'tone' => $u['role'] === 'admin' ? 'blue' : 'muted']) ?></td>
<td class="num"><?= (int) $u['paid_orders'] ?></td>
<td class="num"><?= (int) $u['courses'] ?></td>
<td><?= e(date_br($u['created_at'])) ?><span class="sub"><?= $u['last_login_at'] ? 'Último acesso ' . e(time_ago($u['last_login_at'])) : 'Nunca entrou' ?></span></td>
</tr>
<?php endforeach; ?>
<?php if (!$page['rows']): ?><tr><td colspan="6" class="muted">Nenhum usuário encontrado.</td></tr><?php endif; ?>
</tbody></table></div>
<?= partial('admin-pager', ['p' => $page]) ?>
</div>
