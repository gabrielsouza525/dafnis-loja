<?php
/** @var array $page @var string $status */
use App\Controllers\Site\PageController;
?>
<div class="adm-head"><div><h1>Contatos</h1><p>Mensagens do formulário "Fale com a nossa equipe".</p></div></div>
<div class="chips" style="margin-bottom:16px">
<a href="<?= e(url('/admin/contatos')) ?>"<?= $status === 'new' ? ' class="on"' : '' ?>>Novos</a>
<a href="<?= e(url('/admin/contatos', ['status' => 'atendidos'])) ?>"<?= $status === 'handled' ? ' class="on"' : '' ?>>Atendidos</a>
</div>
<?php if (!$page['rows']): ?>
<div class="empty"><span class="empty-ic"><?= icon('message') ?></span><h2 style="font-size:20px"><?= $status === 'new' ? 'Nenhum contato novo' : 'Nenhum contato atendido' ?></h2></div>
<?php endif; ?>
<div style="display:flex;flex-direction:column;gap:16px">
<?php foreach ($page['rows'] as $r): ?>
<div class="panel" style="margin:0">
<div class="panel-head"><h2 style="font-size:16px"><?= e($r['name']) ?><?= $r['company'] ? ' · ' . e($r['company']) : '' ?></h2><span class="muted" style="font-size:13px"><?= e(date_br($r['created_at'], true)) ?></span></div>
<div class="panel-body">
<dl class="dl">
<dt>Assunto</dt><dd><?= e(PageController::SUBJECTS[$r['subject']] ?? $r['subject']) ?><?= $r['course_title'] ? ' — ' . e(($r['nr_number'] ? ($r['course_code'] ?: 'NR ' . $r['nr_number']) . ' ' : '') . $r['course_title']) : '' ?></dd>
<dt>Contato</dt><dd><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a><?php if ($r['phone']): ?> · <?= e(phone_display($r['phone'])) ?> · <a href="<?= e(wa_link($r['phone'], 'Olá, ' . first_name($r['name']) . '! Aqui é da Dafnis Treinamentos, sobre o seu contato pelo site.')) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?></dd>
<?php if ($r['participants']): ?><dt>Participantes</dt><dd><?= (int) $r['participants'] ?></dd><?php endif; ?>
<?php if ($r['message']): ?><dt>Mensagem</dt><dd style="white-space:pre-line;font-weight:400"><?= e($r['message']) ?></dd><?php endif; ?>
</dl>
<form method="post" action="<?= e(url('/admin/contatos/' . $r['id'])) ?>" style="margin-top:14px"><?= csrf_field() ?><button class="btn btn-<?= $status === 'new' ? 'navy' : 'outline' ?> btn-xs" type="submit"><?= $status === 'new' ? 'Marcar como atendido' : 'Reabrir' ?></button></form>
</div>
</div>
<?php endforeach; ?>
</div>
<?php if ($page['pages'] > 1): ?><div class="panel" style="margin-top:16px"><?= partial('admin-pager', ['p' => $page]) ?></div><?php endif; ?>
