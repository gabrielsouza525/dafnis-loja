<?php
/**
 * Sobre nós: só dados reais (catálogo e painel › Configurações). O que não foi preenchido não aparece.
 * @var int $total @var int $nrCount @var array $categories @var array $stats @var ?string $about
 * @var string $business @var ?string $city @var ?string $address @var ?string $cnpj
 * @var ?string $whatsapp @var ?string $phone @var ?string $email
 */
// Indicadores da empresa (painel) primeiro, depois os números do catálogo
$numbers = array_merge($stats, [
    ['n' => (string) $total, 'label' => 'Treinamentos no catálogo'],
    ['n' => (string) $nrCount, 'label' => 'Normas Regulamentadoras atendidas'],
    ['n' => (string) count($categories), 'label' => 'Áreas de capacitação'],
]);
$steps = [
    ['Escolha o treinamento', 'Pesquise no catálogo por NR, categoria ou nome do curso.'],
    ['Compre as vagas', 'Para você ou para a sua equipe, com quantos participantes precisar.'],
    ['Estude na plataforma', 'Cada participante recebe o acesso e estuda pelo computador ou celular.'],
    ['Receba o certificado', 'Concluiu e foi aprovado? O certificado fica na área do aluno.'],
];
?>
<div class="screen">
<section class="phead phead-dark about-hero">
<div class="grid-bg" aria-hidden="true"></div>
<div class="wrap">
<?= partial('crumbs', ['items' => [['Início', '/'], ['Sobre nós', null]]]) ?>
<div class="about-hero-in">
<div>
<div class="kicker">Sobre nós</div>
<h1>Treinamentos que preparam pessoas e empresas para trabalhar com segurança</h1>
<?php if ($about): ?>
<p class="about-lead"><?= nl2br(e($about)) ?></p>
<?php else: ?>
<p class="about-lead">A <?= e($business) ?> reúne treinamentos de Normas Regulamentadoras, segurança do trabalho e cursos complementares, com compra online, acesso pela plataforma de ensino e certificado de conclusão.</p>
<?php endif; ?>
<?php if ($city): ?><p class="about-place"><?= icon('pin', 'ic-sm') ?><?= e($city) ?></p><?php endif; ?>
<div class="about-ctas">
<a class="btn btn-gold btn-lg" href="<?= e(url('/cursos')) ?>">Ver treinamentos<?= icon('arrowR') ?></a>
<a class="btn btn-line-w btn-lg" href="<?= e(url('/contato')) ?>">Fale com a nossa equipe</a>
</div>
</div>
<div class="stats about-stats">
<?php foreach (array_slice($numbers, 0, 6) as $s): ?>
<div class="stat"><div class="stat-n"><?= e($s['n']) ?></div><div class="stat-l"><?= e($s['label']) ?></div></div>
<?php endforeach; ?>
</div>
</div>
</div>
</section>

<section class="sec" aria-labelledby="fazemos-titulo">
<div class="wrap">
<div class="sec-head reveal"><div><div class="kicker">O que fazemos</div><h2 id="fazemos-titulo">Capacitação do catálogo ao certificado</h2><p>Treinamentos para quem precisa se qualificar e para empresas que precisam manter as equipes em dia com as normas.</p></div></div>
<div class="pillars reveal">
<article class="pillar">
<span class="pillar-ic"><?= icon('hardhat') ?></span>
<h3>Treinamentos NR e complementares</h3>
<p>Formação inicial e reciclagem das Normas Regulamentadoras, segurança do trabalho, primeiros socorros e outros cursos, online e semipresenciais.</p>
<a class="text-link" href="<?= e(url('/cursos')) ?>">Ver o catálogo<?= icon('arrowR', 'ic-sm') ?></a>
</article>
<article class="pillar">
<span class="pillar-ic"><?= icon('building') ?></span>
<h3>Atendimento para empresas</h3>
<p>Vagas para equipes inteiras, indicação dos participantes pela sua conta e uma proposta sob medida para o que a empresa precisa.</p>
<a class="text-link" href="<?= e(url('/contato', ['assunto' => 'empresas'])) ?>">Solicitar proposta<?= icon('arrowR', 'ic-sm') ?></a>
</article>
<article class="pillar">
<span class="pillar-ic"><?= icon('award') ?></span>
<h3>Certificado de conclusão</h3>
<p>Emitido para cada participante que concluir o curso e cumprir os critérios de aprovação, com download na área do aluno.</p>
<a class="text-link" href="<?= e(url('/#certificado')) ?>">Como funciona o certificado<?= icon('arrowR', 'ic-sm') ?></a>
</article>
</div>
</div>
</section>

<section class="sec sec-gray" aria-labelledby="como-titulo">
<div class="wrap">
<div class="sec-head reveal"><div><div class="kicker">Como funciona</div><h2 id="como-titulo">Do primeiro clique ao certificado</h2><p>O mesmo caminho para quem compra um curso para si e para empresas que compram vagas para a equipe.</p></div></div>
<ol class="how reveal">
<?php foreach ($steps as $i => [$title, $text]): ?>
<li class="how-step"><span class="how-n"><?= $i + 1 ?></span><h3><?= e($title) ?></h3><p><?= e($text) ?></p></li>
<?php endforeach; ?>
</ol>
<p class="how-note reveal"><?= icon('info', 'ic-sm') ?>Nos treinamentos semipresenciais, a parte prática é presencial e combinada com a nossa equipe.</p>
</div>
</section>

<section class="sec" aria-labelledby="dif-titulo">
<div class="wrap">
<div class="sec-head reveal"><div><div class="kicker">Nossos diferenciais</div><h2 id="dif-titulo">Por que escolher a Dafnis</h2></div></div>
<?= partial('whys') ?>
</div>
</section>

<?php if ($categories): ?>
<section class="sec sec-gray" aria-labelledby="areas-titulo">
<div class="wrap">
<div class="sec-head reveal">
<div><div class="kicker">Áreas de capacitação</div><h2 id="areas-titulo">Onde podemos ajudar</h2></div>
<a class="text-link" href="<?= e(url('/nrs')) ?>">Ver treinamentos por NR<?= icon('arrowR', 'ic-sm') ?></a>
</div>
<div class="areas reveal">
<?php foreach ($categories as $k): ?>
<a class="area" href="<?= e($k['url']) ?>"><span class="area-ic"><?= icon($k['icon']) ?></span><span class="area-name"><?= e($k['name']) ?><small><?= e(pluralize($k['course_count'], 'treinamento', 'treinamentos')) ?></small></span><?= icon('arrowR', 'ic-sm') ?></a>
<?php endforeach; ?>
</div>
</div>
</section>
<?php endif; ?>

<section class="sec about-end" aria-labelledby="fale-titulo">
<div class="wrap">
<div class="about-cta reveal">
<div class="grid-bg" aria-hidden="true"></div>
<div class="about-cta-text">
<div class="kicker">Fale com a gente</div>
<h2 id="fale-titulo">Vamos capacitar a sua equipe?</h2>
<p>Conte quais treinamentos você procura e quantas pessoas vão participar. A nossa equipe responde pelo e-mail ou telefone que você informar.</p>
<?php if ($city || $address || $cnpj): ?>
<ul class="about-facts">
<li><?= icon('building', 'ic-sm') ?><?= e($business) ?><?= $cnpj ? ' · CNPJ ' . e(document_display($cnpj)) : '' ?></li>
<?php if ($address || $city): ?><li><?= icon('pin', 'ic-sm') ?><?= e(implode(' · ', array_filter([$address, $city]))) ?></li><?php endif; ?>
</ul>
<?php endif; ?>
</div>
<div class="about-cta-side">
<a class="btn btn-gold btn-lg" href="<?= e(url('/contato', ['assunto' => 'empresas'])) ?>">Solicitar proposta<?= icon('arrowR') ?></a>
<a class="btn btn-line-w btn-lg" href="<?= e(url('/contato')) ?>">Enviar uma mensagem</a>
<?php if ($whatsapp || $phone || $email): ?>
<div class="about-channels">
<?php if ($whatsapp): ?><a href="<?= e(wa_link($whatsapp, 'Olá! Vim pelo site da Dafnis Treinamentos.')) ?>" target="_blank" rel="noopener"><?= icon('whatsapp', 'ic-sm') ?><?= e(phone_display($whatsapp)) ?></a><?php endif; ?>
<?php if ($phone && $phone !== $whatsapp): ?><a href="<?= e(tel_link($phone)) ?>"><?= icon('phone', 'ic-sm') ?><?= e(phone_display($phone)) ?></a><?php endif; ?>
<?php if ($email): ?><a href="mailto:<?= e($email) ?>"><?= icon('mail', 'ic-sm') ?><?= e($email) ?></a><?php endif; ?>
</div>
<?php endif; ?>
</div>
</div>
</div>
</section>
</div>
