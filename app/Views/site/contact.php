<?php
/**
 * @var string $subject @var array|null $course @var string $message @var array|null $user
 * @var string|null $whatsapp @var string|null $phone @var string|null $email
 */
use App\Controllers\Site\PageController;

$sent = App\Core\App::request()?->query('enviado') === '1';
$current = has_old() ? old('subject', $subject) : $subject;
?>
<div class="screen">
<section class="phead">
<div class="wrap">
<?= partial('crumbs', ['items' => [['Início', '/'], ['Contato', null]]]) ?>
<div class="phead-row"><div><h1>Fale com a nossa equipe</h1><p>Dúvidas sobre treinamentos, conteúdo programático ou uma proposta para a sua empresa.</p></div></div>
</div>
</section>
<div class="wrap">
<div class="contact-grid">
<div>
<?php if ($sent): ?>
<div class="empty" style="border-style:solid">
<span class="empty-ic" style="background:var(--green-tint);color:var(--green-dk)"><?= icon('check') ?></span>
<h2 style="font-size:22px">Mensagem enviada</h2>
<p>Recebemos o seu contato. A nossa equipe responde pelo e-mail ou telefone informado.</p>
<a class="btn btn-primary" href="<?= e(url('/cursos')) ?>">Voltar ao catálogo</a>
</div>
<?php else: ?>
<form class="form-card" method="post" action="<?= e(url('/contato')) ?>" data-loading-form>
<?= csrf_field() ?>
<div class="form-sec">
<h2><span><?= icon('message', 'ic-sm') ?></span>Sua mensagem</h2>
<div class="fields" style="margin-top:20px">
<div class="full"><?= partial('field', ['name' => 'subject', 'label' => 'Assunto', 'type' => 'select', 'value' => $current, 'options' => PageController::SUBJECTS]) ?></div>
<?php if ($course): ?>
<input type="hidden" name="course_slug" value="<?= e($course['slug']) ?>">
<div class="full"><div class="note-box info" style="margin-top:0"><?= icon('book') ?><span>Treinamento: <strong><?= e($course['display_title']) ?></strong></span></div></div>
<?php endif; ?>
<?= partial('field', ['name' => 'name', 'label' => 'Nome completo', 'value' => $user['name'] ?? '', 'required' => true, 'attrs' => ['autocomplete' => 'name']]) ?>
<?= partial('field', ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'value' => $user['email'] ?? '', 'required' => true, 'attrs' => ['autocomplete' => 'email']]) ?>
<?= partial('field', ['name' => 'phone', 'label' => 'Telefone / WhatsApp', 'type' => 'tel', 'value' => isset($user['phone']) ? phone_display($user['phone']) : '', 'optional' => true, 'mask' => 'phone', 'attrs' => ['autocomplete' => 'tel']]) ?>
<?= partial('field', ['name' => 'company', 'label' => 'Empresa', 'optional' => true, 'attrs' => ['autocomplete' => 'organization']]) ?>
<?= partial('field', ['name' => 'participants', 'label' => 'Número de participantes', 'type' => 'number', 'optional' => true, 'attrs' => ['min' => 1, 'max' => 65000, 'inputmode' => 'numeric']]) ?>
<div class="full"><?= partial('field', ['name' => 'message', 'label' => 'Mensagem', 'type' => 'textarea', 'value' => $message, 'optional' => true, 'placeholder' => 'Conte o que você precisa: treinamentos, prazos, cidade da parte prática...']) ?></div>
</div>
<div style="position:absolute;left:-9999px" aria-hidden="true"><label>Não preencha<input name="website" tabindex="-1" autocomplete="off"></label></div>
<p class="hint" style="margin-top:16px">Usamos estes dados só para responder ao seu contato. Veja a <a href="<?= e(url('/politica-de-privacidade')) ?>">política de privacidade</a>.</p>
<button class="btn btn-primary btn-lg" type="submit" style="margin-top:18px">Enviar mensagem<?= icon('arrowR') ?></button>
</div>
</form>
<?php endif; ?>
</div>
<aside class="contact-aside">
<div class="help" style="margin-top:0">
<h3>Atendimento para empresas</h3>
<p>Compra de vagas para equipes, condições corporativas e organização das turmas.</p>
<a class="text-link" href="<?= e(url('/cursos')) ?>">Ver catálogo<?= icon('arrowR', 'ic-sm') ?></a>
</div>
<?php if ($whatsapp || $phone || $email): ?>
<div class="help" style="margin-top:0">
<h3>Outros canais</h3>
<div class="checks" style="margin-top:12px">
<?php if ($whatsapp): ?><a class="check" href="<?= e(wa_link($whatsapp, 'Olá! Vim pelo site da Dafnis Treinamentos.')) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?><?= e(phone_display($whatsapp)) ?></a><?php endif; ?>
<?php if ($phone && $phone !== $whatsapp): ?><a class="check" href="<?= e(tel_link($phone)) ?>"><?= icon('phone') ?><?= e(phone_display($phone)) ?></a><?php endif; ?>
<?php if ($email): ?><a class="check" href="mailto:<?= e($email) ?>"><?= icon('mail') ?><?= e($email) ?></a><?php endif; ?>
</div>
</div>
<?php endif; ?>
</aside>
</div>
</div>
</div>
