<?php
/** @var array $mine @var array $team */
$crumbs = [['Início', '/'], ['Minha conta', '/minha-conta'], ['Certificados', null]];
?>
<div class="screen">
<?= partial('account-shell-open', get_defined_vars()) ?>
<div class="acc-head"><div><h1>Certificados</h1><p>Certificados dos treinamentos concluídos.</p></div></div>
<?php if (!$mine && !$team): ?>
<div class="empty">
<span class="empty-ic"><?= icon('award') ?></span>
<h2 style="font-size:20px">Nenhum certificado ainda</h2>
<p>Ao concluir um treinamento e cumprir os critérios de aprovação, o certificado aparece aqui para download.</p>
<a class="btn btn-primary" href="<?= e(url('/minha-conta/cursos')) ?>">Ver meus cursos</a>
</div>
<?php endif; ?>
<?php foreach (['Meus certificados' => [$mine, false], 'Certificados da sua equipe' => [$team, true]] as $heading => [$list, $showName]): if (!$list) { continue; } ?>
<div class="panel"><div class="panel-head"><h2><?= e($heading) ?></h2></div>
<div class="table-wrap"><table class="table">
<thead><tr><th><?= $showName ? 'Treinamento / participante' : 'Treinamento' ?></th><th>Carga</th><th>Emissão</th><th>Código</th><th class="num">Arquivo</th></tr></thead>
<tbody>
<?php foreach ($list as $e): ?>
<tr>
<td><strong><?= e(($e['course_code'] ? $e['course_code'] . ' — ' : '') . $e['course_title']) ?></strong><?php if ($showName): ?><br><small class="muted"><?= e((string) $e['participant_name']) ?></small><?php endif; ?></td>
<td><?= e(hours_short($e['course_hours'])) ?></td>
<td><?= e($e['certificate_issued_at'] ? date_br($e['certificate_issued_at']) : date_br($e['completed_at'])) ?></td>
<td class="mono" style="font-size:13px"><?= e($e['certificate_code'] ?: '—') ?></td>
<td class="num"><?php if ($e['certificate_id'] && ($e['certificate_file'] || $e['certificate_url'])): ?><a class="btn btn-buy btn-xs" href="<?= e(url('/minha-conta/certificados/' . $e['certificate_id'] . '/baixar')) ?>"><?= icon('download', 'ic-sm') ?>Baixar</a><?php else: ?><?= partial('status', ['label' => 'Em emissão', 'tone' => 'info']) ?><?php endif; ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>
<?php endforeach; ?>
<?= partial('account-shell-close') ?>
</div>
