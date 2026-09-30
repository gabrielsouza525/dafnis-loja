<?php
/**
 * Empresas: compra de vagas para a equipe e pedido de proposta (vai para o mesmo cadastro do Contato).
 * @var int $total @var array $nrIndex @var array|null $sample @var array $stats @var array|null $user
 * @var string|null $whatsapp @var string|null $phone @var string|null $email
 */
$sent = App\Core\App::request()?->query('enviado') === '1';
$sampleLabel = $sample ? $sample['code_label'] . ' · ' . $sample['hours_label'] : 'NR 33 · 16 h';
$perks = [
    ['users', 'Toda a equipe em um pedido', 'Compre vagas para quantos colaboradores precisar, em um ou em vários treinamentos.'],
    ['receipt', 'Compra no nome da empresa', 'Razão social e CNPJ no pedido, com a confirmação no e-mail do responsável pela compra.'],
    ['clipboard', 'Gestão pela sua conta', 'Indique quem vai fazer cada vaga e acompanhe a situação de cada participante.'],
    ['award', 'Certificados reunidos', 'Os certificados de cada colaborador ficam disponíveis na conta de quem comprou.'],
    ['book', 'Catálogo completo', pluralize($total, 'treinamento', 'treinamentos') . ' em ' . pluralize(count($nrIndex), 'Norma Regulamentadora', 'Normas Regulamentadoras') . ' e cursos complementares, online e semipresenciais.'],
    ['message', 'Proposta sob medida', 'Para turmas maiores ou necessidades específicas, a nossa equipe monta uma proposta.'],
];
$steps = [
    ['Monte o pedido', 'Escolha os treinamentos no catálogo e a quantidade de vagas: uma por participante.'],
    ['Compre como empresa', 'No checkout, marque "Empresa" e informe a razão social e o CNPJ.'],
    ['Indique os participantes', 'Com o pagamento confirmado, informe nome, e-mail e CPF de cada um em Minha conta › Pedidos e vagas.'],
    ['Acompanhe até o certificado', 'Cada participante recebe o acesso por e-mail. Os certificados da equipe ficam na sua conta.'],
];
?>
<div class="screen">
<section class="phead phead-dark intro-hero">
<div class="grid-bg" aria-hidden="true"></div>
<div class="wrap">
<?= partial('crumbs', ['items' => [['Início', '/'], ['Empresas', null]]]) ?>
<div class="intro-in">
<div>
<div class="kicker">Para empresas</div>
<h1>Treinamentos para toda a sua equipe, em um só pedido</h1>
<p class="intro-lead">Compre vagas com o CNPJ da empresa, indique quem vai participar e acompanhe cada colaborador até o certificado.</p>
<ul class="intro-points">
<li><?= icon('check', 'ic-sm') ?>Compra com razão social e CNPJ</li>
<li><?= icon('check', 'ic-sm') ?>Uma vaga por participante, quantas precisar</li>
<li><?= icon('check', 'ic-sm') ?>Certificados da equipe reunidos na sua conta</li>
</ul>
<div class="intro-ctas">
<a class="btn btn-gold btn-lg" href="#proposta">Solicitar proposta<?= icon('arrowR') ?></a>
<a class="btn btn-line-w btn-lg" href="<?= e(url('/cursos')) ?>">Ver catálogo</a>
</div>
<?php if ($stats): ?>
<div class="intro-stats">
<?php foreach ($stats as $s): ?><div><strong><?= e($s['n']) ?></strong><span><?= e($s['label']) ?></span></div><?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<div class="seat-demo" aria-hidden="true">
<div class="sd-head">
<span class="sd-ic"><?= icon('receipt') ?></span>
<div><strong>Pedido da sua empresa</strong><small><?= e($sampleLabel) ?> · 12 vagas</small></div>
</div>
<div class="sd-rows">
<div class="sd-row"><span class="avatar">P1</span><div><strong>Participante 1</strong><small>Certificado disponível</small></div><?= partial('status', ['label' => 'Concluído', 'tone' => 'ok']) ?></div>
<div class="sd-row"><span class="avatar">P2</span><div><strong>Participante 2</strong><small>Estudando na plataforma</small></div><?= partial('status', ['label' => 'Em andamento', 'tone' => 'blue']) ?></div>
<div class="sd-row"><span class="avatar">P3</span><div><strong>Participante 3</strong><small>Acesso enviado por e-mail</small></div><?= partial('status', ['label' => 'Acesso em liberação', 'tone' => 'info']) ?></div>
<div class="sd-row sd-empty"><span class="avatar">+</span><div><strong>Vaga 4</strong><small>Indique quem vai participar</small></div><?= partial('status', ['label' => 'Aguardando participante', 'tone' => 'warn']) ?></div>
</div>
<div class="sd-foot"><?= icon('award', 'ic-sm') ?>Certificados da equipe reunidos na sua conta</div>
</div>
</div>
</div>
</section>

<section class="sec" aria-labelledby="vantagens-titulo">
<div class="wrap">
<div class="sec-head reveal"><div><div class="kicker">Vantagens</div><h2 id="vantagens-titulo">Tudo o que a empresa precisa para treinar a equipe</h2><p>Da compra das vagas ao certificado de cada colaborador, em um só lugar.</p></div></div>
<div class="pillars reveal">
<?php foreach ($perks as [$ic, $title, $text]): ?>
<article class="pillar"><span class="pillar-ic"><?= icon($ic) ?></span><h3><?= e($title) ?></h3><p><?= e($text) ?></p></article>
<?php endforeach; ?>
</div>
</div>
</section>

<section class="sec sec-gray" aria-labelledby="como-titulo">
<div class="wrap">
<div class="sec-head reveal"><div><div class="kicker">Como funciona</div><h2 id="como-titulo">Do pedido ao certificado da equipe</h2><p>Quatro passos, todos pela loja e pela sua conta.</p></div></div>
<ol class="how reveal">
<?php foreach ($steps as $i => [$title, $text]): ?>
<li class="how-step"><span class="how-n"><?= $i + 1 ?></span><h3><?= e($title) ?></h3><p><?= e($text) ?></p></li>
<?php endforeach; ?>
</ol>
<p class="how-note reveal"><?= icon('info', 'ic-sm') ?>Nos treinamentos semipresenciais, a parte prática é presencial e combinada com a nossa equipe.</p>
</div>
</section>

<?php if ($nrIndex): ?>
<section class="sec" aria-labelledby="normas-titulo">
<div class="wrap">
<div class="sec-head reveal">
<div><div class="kicker">Normas Regulamentadoras</div><h2 id="normas-titulo">Treinamentos para cada norma</h2><p>Escolha a norma e veja os treinamentos: formação inicial, reciclagem e simuladores.</p></div>
<a class="text-link" href="<?= e(url('/cursos')) ?>">Ver catálogo completo<?= icon('arrowR', 'ic-sm') ?></a>
</div>
<div class="nr-board reveal">
<div class="mega-grid">
<?php foreach ($nrIndex as $m): ?>
<a class="mega-item" href="<?= e($m['url']) ?>"><span class="mega-code"><?= e($m['code']) ?></span><span class="mega-name"><?= e($m['name']) ?><small><?= e(pluralize($m['count'], 'treinamento', 'treinamentos')) ?></small></span></a>
<?php endforeach; ?>
</div>
</div>
</div>
</section>
<?php endif; ?>

<section class="sec sec-gray" id="proposta" aria-labelledby="proposta-titulo">
<div class="wrap biz-form">
<div class="biz-form-text">
<div class="kicker">Proposta</div>
<h2 id="proposta-titulo">Peça uma proposta para a sua equipe</h2>
<p>Conte quais treinamentos você procura e quantas pessoas vão participar. A nossa equipe responde com as vagas, os valores e a organização das turmas.</p>
<div class="checks">
<div class="check"><?= icon('check') ?>Resposta pelo e-mail ou telefone que você informar</div>
<div class="check"><?= icon('check') ?>Um ou vários treinamentos na mesma proposta</div>
<div class="check"><?= icon('check') ?>Prefere comprar direto? Escolha as vagas no catálogo e finalize como empresa</div>
</div>
<?php if ($whatsapp || $phone || $email): ?>
<div class="biz-channels">
<?php if ($whatsapp): ?><a href="<?= e(wa_link($whatsapp, 'Olá! Quero uma proposta de treinamentos para a minha empresa.')) ?>" target="_blank" rel="noopener"><?= icon('whatsapp', 'ic-sm') ?><?= e(phone_display($whatsapp)) ?></a><?php endif; ?>
<?php if ($phone && $phone !== $whatsapp): ?><a href="<?= e(tel_link($phone)) ?>"><?= icon('phone', 'ic-sm') ?><?= e(phone_display($phone)) ?></a><?php endif; ?>
<?php if ($email): ?><a href="mailto:<?= e($email) ?>"><?= icon('mail', 'ic-sm') ?><?= e($email) ?></a><?php endif; ?>
</div>
<?php endif; ?>
</div>
<?php if ($sent): ?>
<div class="form-card contact-sent">
<span class="success-ic"><?= icon('check') ?></span>
<h2>Pedido de proposta enviado</h2>
<p>Recebemos os seus dados. A nossa equipe responde pelo e-mail ou telefone informado.</p>
<div class="contact-sent-actions"><a class="btn btn-primary" href="<?= e(url('/cursos')) ?>">Ver o catálogo<?= icon('arrowR') ?></a><a class="btn btn-outline" href="<?= e(url('/')) ?>">Ir para o início</a></div>
</div>
<?php else: ?>
<form class="form-card" method="post" action="<?= e(url('/contato')) ?>" data-loading-form>
<?= csrf_field() ?>
<input type="hidden" name="subject" value="empresas">
<input type="hidden" name="_from" value="empresas">
<div class="fields">
<?= partial('field', ['name' => 'name', 'label' => 'Nome completo', 'value' => $user['name'] ?? '', 'required' => true, 'attrs' => ['autocomplete' => 'name']]) ?>
<?= partial('field', ['name' => 'company', 'label' => 'Empresa', 'optional' => true, 'attrs' => ['autocomplete' => 'organization']]) ?>
<?= partial('field', ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'value' => $user['email'] ?? '', 'required' => true, 'attrs' => ['autocomplete' => 'email']]) ?>
<?= partial('field', ['name' => 'phone', 'label' => 'Telefone / WhatsApp', 'type' => 'tel', 'value' => isset($user['phone']) ? phone_display($user['phone']) : '', 'optional' => true, 'mask' => 'phone', 'attrs' => ['autocomplete' => 'tel']]) ?>
<div class="full"><?= partial('field', ['name' => 'participants', 'label' => 'Número de participantes', 'type' => 'number', 'optional' => true, 'attrs' => ['min' => 1, 'max' => 65000, 'inputmode' => 'numeric']]) ?></div>
<div class="full"><?= partial('field', ['name' => 'message', 'label' => 'Quais treinamentos?', 'type' => 'textarea', 'optional' => true, 'placeholder' => 'Ex.: NR 10 e NR 33 para a equipe de manutenção, prazo, cidade da parte prática...']) ?></div>
</div>
<div style="position:absolute;left:-9999px" aria-hidden="true"><label>Não preencha<input name="website" tabindex="-1" autocomplete="off"></label></div>
<p class="hint" style="margin-top:16px">Usamos estes dados só para responder ao seu pedido. Veja a <a href="<?= e(url('/politica-de-privacidade')) ?>">política de privacidade</a>.</p>
<button class="btn btn-gold btn-lg biz-submit" type="submit">Solicitar proposta<?= icon('arrowR') ?></button>
</form>
<?php endif; ?>
</div>
</section>
</div>
