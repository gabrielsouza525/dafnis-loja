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
<section class="acc-sec">
<h2 class="acc-sec-title"><?= e($heading) ?><span><?= count($list) ?></span></h2>
<div class="cert-grid">
<?php foreach ($list as $e): $ready = $e['certificate_id'] && ($e['certificate_file'] || $e['certificate_url']); ?>
<article class="cert-card">
<div class="cert-card-top"><span class="cert-card-seal"><?= icon('award') ?></span><?php if ($e['certificate_code']): ?><span class="cert-card-code"><?= e($e['certificate_code']) ?></span><?php endif; ?></div>
<h3><?= e(($e['course_code'] ? $e['course_code'] . ' — ' : '') . $e['course_title']) ?></h3>
<?php if ($showName): ?><p class="cert-card-who"><?= icon('user', 'ic-sm') ?><?= e((string) $e['participant_name']) ?></p><?php endif; ?>
<dl class="cert-card-facts">
<div><dt>Carga horária</dt><dd><?= e(hours_short($e['course_hours'])) ?></dd></div>
<div><dt>Emissão</dt><dd><?= e($e['certificate_issued_at'] ? date_br($e['certificate_issued_at']) : date_br($e['completed_at'])) ?></dd></div>
</dl>
<?php if ($ready): ?>
<a class="btn btn-buy btn-sm btn-block" href="<?= e(url('/minha-conta/certificados/' . $e['certificate_id'] . '/baixar')) ?>"><?= icon('download', 'ic-sm') ?>Baixar certificado</a>
<?php elseif (!$showName && strlen(preg_replace('/\D/', '', (string) $e['participant_document'])) !== 11): ?>
<form class="cert-card-doc" method="post" action="<?= e(url('/minha-conta/certificados/' . $e['id'] . '/cpf')) ?>" data-loading-form>
<?= csrf_field() ?>
<input type="hidden" name="_scope" value="cpf-<?= (int) $e['id'] ?>">
<?= partial('field', ['name' => 'document', 'id' => 'cpf-' . $e['id'], 'label' => 'Seu CPF, para o certificado', 'value' => '', 'mask' => 'cpf', 'required' => true, 'scope' => 'cpf-' . $e['id']]) ?>
<button class="btn btn-buy btn-sm btn-block" type="submit"><?= icon('award', 'ic-sm') ?>Salvar e emitir o certificado</button>
</form>
<?php else: ?>
<span class="cert-card-wait"><?= icon('clock', 'ic-sm') ?>Certificado em emissão</span>
<?php endif; ?>
</article>
<?php endforeach; ?>
</div>
</section>
<?php endforeach; ?>
<?= partial('account-shell-close') ?>
</div>
