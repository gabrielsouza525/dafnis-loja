<?php
/**
 * @var string $subject @var array|null $course @var string $message @var array|null $user
 * @var string|null $whatsapp @var string|null $phone @var string|null $email
 */
use App\Controllers\Site\PageController;

$sent = App\Core\App::request()?->query('enviado') === '1';
$current = has_old() ? old('subject', $subject) : $subject;
// Um cartão por assunto (os rótulos vêm de PageController::SUBJECTS)
$topics = [
    'empresas' => ['building', 'Vagas para equipes e proposta'],
    'curso' => ['book', 'Carga horária, modalidade, parte prática'],
    'conteudo' => ['file', 'Ementa oficial do treinamento'],
    'duvida' => ['message', 'Compra, acesso ou certificado'],
];
?>
<div class="screen">
<section class="phead phead-dark">
<div class="grid-bg" aria-hidden="true"></div>
<div class="wrap">
<?= partial('crumbs', ['items' => [['Início', '/'], ['Contato', null]]]) ?>
<div class="phead-row"><div><h1>Fale com a nossa equipe</h1><p>Dúvidas sobre treinamentos, conteúdo programático ou uma proposta para a sua empresa.</p></div></div>
</div>
</section>
<div class="wrap">
<div class="contact-grid">
<div>
<?php if ($sent): ?>
<div class="form-card contact-sent">
<span class="success-ic"><?= icon('check') ?></span>
<h2>Mensagem enviada</h2>
<p>Recebemos o seu contato. A nossa equipe responde pelo e-mail ou telefone informado.</p>
<div class="contact-sent-actions"><a class="btn btn-primary" href="<?= e(url('/cursos')) ?>">Voltar ao catálogo<?= icon('arrowR') ?></a><a class="btn btn-outline" href="<?= e(url('/')) ?>">Ir para o início</a></div>
</div>
<?php else: ?>
<form class="form-card" method="post" action="<?= e(url('/contato')) ?>" data-loading-form>
<?= csrf_field() ?>
<div class="form-sec">
<h2><span>1</span>Sobre o que você quer falar?</h2>
<div class="topics" role="radiogroup" aria-label="Assunto">
<?php foreach (PageController::SUBJECTS as $key => $label): [$ic, $sub] = $topics[$key] ?? ['message', '']; ?>
<label class="pay topic"><input type="radio" name="subject" value="<?= e($key) ?>"<?= checked($current === $key) ?>><span class="pay-top"><?= icon($ic) ?><span class="radio"></span></span><span><strong><?= e($label) ?></strong><small><?= e($sub) ?></small></span></label>
<?php endforeach; ?>
</div>
<?php if ($err = field_error('subject')): ?><p class="field-error"><?= icon('alert') ?><?= e($err) ?></p><?php endif; ?>
</div>
<div class="form-sec">
<h2><span>2</span>Seus dados e a mensagem</h2>
<div class="fields" style="margin-top:20px">
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
<div class="help contact-steps">
<h3>Como funciona</h3>
<ol>
<li><span>1</span><div><strong>Você envia a mensagem</strong><small>Conte o que precisa: treinamentos, número de participantes, prazos.</small></div></li>
<li><span>2</span><div><strong>A equipe responde</strong><small>Pelo e-mail ou telefone que você informar.</small></div></li>
<li><span>3</span><div><strong>Para empresas, a proposta</strong><small>Com as vagas, os valores e a organização das turmas.</small></div></li>
</ol>
</div>
<?php if ($whatsapp || $phone || $email): ?>
<div class="help">
<h3>Outros canais</h3>
<div class="checks" style="margin-top:12px">
<?php if ($whatsapp): ?><a class="check" href="<?= e(wa_link($whatsapp, 'Olá! Vim pelo site da Dafnis Treinamentos.')) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?><?= e(phone_display($whatsapp)) ?></a><?php endif; ?>
<?php if ($phone && $phone !== $whatsapp): ?><a class="check" href="<?= e(tel_link($phone)) ?>"><?= icon('phone') ?><?= e(phone_display($phone)) ?></a><?php endif; ?>
<?php if ($email): ?><a class="check" href="mailto:<?= e($email) ?>"><?= icon('mail') ?><?= e($email) ?></a><?php endif; ?>
</div>
</div>
<?php endif; ?>
<div class="help contact-links">
<a href="<?= e(url('/#faq')) ?>"><?= icon('info', 'ic-sm') ?>Perguntas frequentes<?= icon('arrowR', 'ic-sm') ?></a>
<a href="<?= e(url('/cursos')) ?>"><?= icon('book', 'ic-sm') ?>Catálogo de treinamentos<?= icon('arrowR', 'ic-sm') ?></a>
<a href="<?= e(url('/nrs')) ?>"><?= icon('clipboard', 'ic-sm') ?>Treinamentos por NR<?= icon('arrowR', 'ic-sm') ?></a>
</div>
</aside>
</div>
</div>
</div>
