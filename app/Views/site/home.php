<?php
/**
 * @var int $total @var array $categories @var array $featured @var array $bestsellers @var array $nrIndex
 * @var array|null $heroCourse @var array|null $certCourse @var array $stats @var ?string $about @var array $faq
 */
$whys = [
    ['title' => 'Conteúdo profissional', 'text' => 'Conteúdos organizados de forma clara, objetiva e aplicada à rotina de trabalho.', 'icon' => 'clipcheck'],
    ['title' => 'Plataforma online', 'text' => 'Estude pelo computador ou celular, no seu ritmo.', 'icon' => 'monitor'],
    ['title' => 'Acesso fácil', 'text' => 'Encontre e acesse seus treinamentos em poucos cliques.', 'icon' => 'search'],
    ['title' => 'Certificação', 'text' => 'Certificado de conclusão ao finalizar o treinamento, conforme as regras de cada curso.', 'icon' => 'award'],
    ['title' => 'Treinamentos para empresas', 'text' => 'Compra de vagas para equipes e atendimento dedicado.', 'icon' => 'building'],
    ['title' => 'Catálogo diversificado', 'text' => 'NRs, cursos complementares, jogos e simuladores reunidos em um só lugar.', 'icon' => 'briefcase'],
];
?>
<div class="screen">
<section class="hero">
<div class="wrap hero-in">
<div>
<div class="kicker">Educação corporativa · Segurança do trabalho</div>
<h1>Treinamentos profissionais para preparar <span>você e sua empresa.</span></h1>
<p class="hero-sub">Capacite sua equipe com treinamentos online, conteúdos especializados e certificação.</p>
<div class="hero-ctas">
<a class="btn btn-primary btn-lg" href="<?= e(url('/cursos')) ?>">Explorar cursos<?= icon('arrowR') ?></a>
<a class="btn btn-outline btn-lg" href="#categorias">Conhecer treinamentos</a>
</div>
<ul class="hero-points">
<li class="hero-point"><?= icon('check') ?>NRs e cursos complementares</li>
<li class="hero-point"><?= icon('check') ?>Certificado de conclusão</li>
<li class="hero-point"><?= icon('check') ?>Soluções para empresas</li>
</ul>
</div>
<div class="hero-visual">
<div class="hv-panel" aria-hidden="true">
<div class="grid-bg"></div>
<div class="hv-tag"><span>EPI · Capacete de segurança</span><span>Vista frontal</span></div>
<svg class="hv-drawing" viewBox="0 0 320 230">
<path class="ln2" d="M160 30 V200"></path>
<path class="ln" d="M52 168 A108 104 0 0 1 268 168"></path>
<path class="ln" d="M30 168 H290 Q296 168 296 174 V178 Q296 184 290 184 H30 Q24 184 24 178 V174 Q24 168 30 168 Z"></path>
<path class="ln" d="M138 66 Q160 58 182 66"></path>
<path class="ln" d="M132 168 C132 118 140 86 150 68"></path>
<path class="ln" d="M188 168 C188 118 180 86 170 68"></path>
<path class="ln2" d="M78 168 C82 132 100 104 124 86"></path>
<path class="ln2" d="M242 168 C238 132 220 104 196 86"></path>
<path class="ln2" d="M60 150 H260"></path>
<path class="dim" d="M24 206 H296 M24 200 V212 M296 200 V212"></path>
<path class="dim" d="M308 64 V184 M302 64 H314 M302 184 H314"></path>
<circle class="dot" cx="226" cy="104" r="3.5"></circle>
<path class="dim" d="M226 104 L262 80 H290"></path>
<circle class="dot" cx="100" cy="176" r="3.5"></circle>
<path class="dim" d="M100 176 L70 140 H36"></path>
<text x="266" y="75">01 CASCO</text>
<text x="36" y="134">02 ABA</text>
<text x="140" y="224">LARGURA</text>
</svg>
<div class="hv-stripe"></div>
</div>
<?php if ($heroCourse): ?>
<a class="hv-card hv-course" href="<?= e($heroCourse['url']) ?>" aria-label="<?= e('Ver curso ' . $heroCourse['display_title']) ?>">
<div class="hv-row"><span class="hv-plate"><?= e($heroCourse['code_label']) ?></span><div><div class="hv-title">Espaços Confinados</div><div class="hv-sub">Trabalhador e Vigia</div></div></div>
<div class="hv-chips"><span class="mini-chip"><?= e($heroCourse['modality_label']) ?></span><span class="mini-chip"><?= e($heroCourse['hours_label']) ?></span></div>
</a>
<?php endif; ?>
<div class="hv-card hv-cert" aria-hidden="true">
<div class="hv-row"><span class="hv-badge"><?= icon('award') ?></span><div><div class="hv-title">Certificado de conclusão</div><div class="hv-sub">[Nome do participante]</div></div></div>
<div class="hv-foot"><span><strong><?= e($certCourse['code_label'] ?? 'NR 10') ?></strong> · <?= e($certCourse['hours_label'] ?? '40 h') ?></span><span>Emitido em [data]</span></div>
</div>
</div>
</div>
</section>

<section class="search-band" aria-labelledby="busca-titulo">
<div class="wrap">
<div class="search-panel">
<div class="search-head"><h2 id="busca-titulo">Encontre o treinamento que você precisa</h2><span><?= e(pluralize($total, 'treinamento no catálogo', 'treinamentos no catálogo')) ?></span></div>
<form class="search-form" action="<?= e(url('/cursos')) ?>" method="get" role="search">
<label class="search-field"><?= icon('search') ?><span class="sr-only">Pesquisar treinamentos</span><input type="search" name="q" placeholder="Pesquise por curso, NR ou palavra-chave... ex.: NR 10" autocomplete="off" enterkeyhint="search"></label>
<button class="btn btn-primary" type="submit">Pesquisar</button>
</form>
<div class="quick">
<span class="quick-label">Acesso rápido por NR:</span>
<?php foreach ($nrIndex as $m): ?><a class="chip" href="<?= e($m['url']) ?>"><?= e($m['code']) ?></a><?php endforeach; ?>
</div>
</div>
</div>
</section>

<section class="sec" id="categorias" aria-labelledby="cat-titulo">
<div class="wrap">
<div class="sec-head reveal">
<div><div class="kicker">Categorias</div><h2 id="cat-titulo">Encontre o treinamento ideal</h2><p>Navegue pelas áreas de capacitação e encontre o conteúdo certo para cada função.</p></div>
<a class="text-link" href="<?= e(url('/cursos')) ?>">Ver catálogo completo<?= icon('arrowR', 'ic-sm') ?></a>
</div>
<div class="cats reveal">
<?php foreach ($categories as $k): ?>
<a class="cat" href="<?= e($k['url']) ?>">
<span class="cat-ic"><?= icon($k['icon']) ?></span>
<h3><?= e($k['name']) ?></h3>
<p><?= e($k['description']) ?></p>
<span class="cat-foot">
<?php if ($k['course_count'] > 0): ?><span><?= e(pluralize($k['course_count'], 'curso', 'cursos')) ?></span><?= icon('arrowR', 'ic-sm') ?><?php else: ?><span class="soon">Em breve</span><?php endif; ?>
</span>
</a>
<?php endforeach; ?>
</div>
</div>
</section>

<?php if ($featured): ?>
<section class="sec sec-gray" id="destaques" aria-labelledby="dest-titulo">
<div class="wrap">
<div class="sec-head reveal">
<div><div class="kicker">Destaques</div><h2 id="dest-titulo">Treinamentos em destaque</h2><p>Treinamentos essenciais para profissionais e empresas.</p></div>
<a class="text-link" href="<?= e(url('/cursos')) ?>">Ver todos os treinamentos<?= icon('arrowR', 'ic-sm') ?></a>
</div>
<div class="grid4 reveal">
<?php foreach ($featured as $course): ?><?= partial('course-card', ['course' => $course]) ?><?php endforeach; ?>
</div>
</div>
</section>
<?php endif; ?>

<section class="sec" id="nrs" aria-labelledby="nrs-titulo">
<div class="wrap">
<div class="sec-head reveal">
<div><div class="kicker">Normas Regulamentadoras</div><h2 id="nrs-titulo">NRs em destaque</h2><p>Escolha a norma e veja todos os treinamentos relacionados: formação inicial, reciclagem e simuladores.</p></div>
<a class="text-link" href="<?= e(url('/nrs')) ?>">Ver todas as NRs<?= icon('arrowR', 'ic-sm') ?></a>
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

<?php if ($bestsellers): ?>
<section class="sec sec-gray" id="mais-vendidos" aria-labelledby="mv-titulo">
<div class="wrap">
<div class="sec-head reveal">
<div><div class="kicker">Mais vendidos</div><h2 id="mv-titulo">Os mais procurados</h2><p>Os treinamentos que profissionais e empresas mais compram.</p></div>
<a class="text-link" href="<?= e(url('/cursos')) ?>">Ver catálogo<?= icon('arrowR', 'ic-sm') ?></a>
</div>
<div class="grid4 reveal">
<?php foreach ($bestsellers as $course): ?><?= partial('course-card', ['course' => $course]) ?><?php endforeach; ?>
</div>
</div>
</section>
<?php endif; ?>

<section class="ent" id="empresas" aria-labelledby="ent-titulo">
<div class="grid-bg"></div>
<div class="wrap ent-in">
<div class="reveal">
<div class="kicker">Para empresas</div>
<h2 id="ent-titulo">Treinamentos para empresas</h2>
<p class="ent-lead">Capacite equipes inteiras com um único parceiro: compra de vagas para vários colaboradores, acompanhamento e atendimento dedicado.</p>
<div class="ent-feats">
<div class="ent-feat"><span class="ent-feat-ic"><?= icon('users') ?></span><div><h3>Capacitação de equipes</h3><p>Treinamentos para todos os níveis da operação.</p></div></div>
<div class="ent-feat"><span class="ent-feat-ic"><?= icon('clipboard') ?></span><div><h3>Gestão de treinamentos</h3><p>Indique os participantes de cada vaga e acompanhe tudo na sua conta.</p></div></div>
<div class="ent-feat"><span class="ent-feat-ic"><?= icon('building') ?></span><div><h3>Diversos colaboradores</h3><p>Compre vagas para quantos participantes precisar.</p></div></div>
<div class="ent-feat"><span class="ent-feat-ic"><?= icon('briefcase') ?></span><div><h3>Soluções corporativas</h3><p>Condições e atendimento sob medida para sua empresa.</p></div></div>
</div>
<div class="ent-ctas">
<a class="btn btn-orange btn-lg" href="<?= e(url('/contato', ['assunto' => 'empresas'])) ?>">Fale com nossa equipe<?= icon('arrowR') ?></a>
<a class="btn btn-line-w btn-lg" href="<?= e(url('/cursos')) ?>">Ver catálogo</a>
</div>
</div>
<div class="reveal">
<div class="stats">
<?php foreach (array_slice($stats, 0, 4) as $s): ?>
<div class="stat"><div class="stat-n"><?= e($s['n']) ?></div><div class="stat-l"><?= e($s['label']) ?></div></div>
<?php endforeach; ?>
</div>
</div>
</div>
</section>

<section class="sec" id="certificado" aria-labelledby="cert-titulo">
<div class="wrap cert-in">
<div class="reveal">
<div class="kicker">Certificação</div>
<h2 id="cert-titulo" style="font-size:40px;letter-spacing:-.03em;margin-top:12px">Certificado de conclusão em cada treinamento</h2>
<p style="color:var(--muted);margin-top:14px;font-size:17px;max-width:520px">Ao concluir o treinamento e cumprir os critérios de aprovação do curso, o certificado fica disponível na sua área do aluno.</p>
<div class="cert-list">
<div class="check"><?= icon('check') ?>Emitido para cada participante que concluir o curso</div>
<div class="check"><?= icon('check') ?>Disponível para download em Minha conta › Certificados</div>
<div class="check"><?= icon('check') ?>Para empresas: certificados de cada colaborador reunidos na conta de quem comprou</div>
</div>
</div>
<div class="cert-stage reveal" aria-hidden="true">
<div class="grid-bg"></div>
<div class="hv-card">
<div class="hv-row"><span class="hv-badge"><?= icon('award') ?></span><div><div class="hv-title">Certificado de conclusão</div><div class="hv-sub">[Nome do participante]</div></div></div>
<div class="hv-foot"><span><strong><?= e($heroCourse['code_label'] ?? 'NR 33') ?></strong> · <?= e($heroCourse['hours_label'] ?? '16 h') ?></span><span>Emitido em [data]</span></div>
</div>
<div class="hv-stripe"></div>
</div>
</div>
</section>

<section class="sec sec-gray" id="sobre" aria-labelledby="sobre-titulo">
<div class="wrap">
<div class="sec-head reveal"><div><div class="kicker">Nossos diferenciais</div><h2 id="sobre-titulo">Por que escolher nossos treinamentos?</h2><?php if ($about): ?><p><?= nl2br(e($about)) ?></p><?php endif; ?></div></div>
<div class="why reveal">
<?php foreach ($whys as $w): ?>
<div class="why-item"><span class="why-ic"><?= icon($w['icon']) ?></span><div><h3><?= e($w['title']) ?></h3><p><?= e($w['text']) ?></p></div></div>
<?php endforeach; ?>
</div>
</div>
</section>

<?php if ($faq): ?>
<section class="sec" id="faq" aria-labelledby="faq-titulo">
<div class="wrap faq-in">
<div class="faq-aside reveal">
<div class="kicker">Dúvidas frequentes</div>
<h2 id="faq-titulo">Perguntas frequentes</h2>
<p>Tudo o que você precisa saber antes de escolher seu treinamento.</p>
<div class="help"><h3>Ainda tem dúvidas?</h3><p>Nossa equipe ajuda você a encontrar o treinamento certo.</p><a class="btn btn-navy btn-sm" href="<?= e(url('/contato')) ?>">Fale com nossa equipe</a></div>
</div>
<div class="reveal">
<div class="acc" data-accordion>
<?php foreach ($faq as $i => $q): ?>
<div class="acc-item">
<button class="acc-q" type="button" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="faq-<?= $i ?>" id="faq-q-<?= $i ?>"><?= e($q['q']) ?><span class="acc-sign"><?= icon('plus', 'ic-sm') ?></span></button>
<div class="acc-a" id="faq-<?= $i ?>" role="region" aria-labelledby="faq-q-<?= $i ?>"<?= $i === 0 ? '' : ' hidden' ?>><?= nl2br(e($q['a'])) ?></div>
</div>
<?php endforeach; ?>
</div>
</div>
</div>
</section>
<?php endif; ?>

<section class="cta-band" aria-labelledby="cta-titulo">
<div class="grid-bg"></div>
<div class="wrap cta-in">
<div><h2 id="cta-titulo">Pronto para capacitar você e sua equipe?</h2><p>Escolha o treinamento, defina os participantes e finalize em poucos passos.</p></div>
<div class="cta-ctas">
<a class="btn btn-orange btn-lg" href="<?= e(url('/cursos')) ?>">Explorar cursos<?= icon('arrowR') ?></a>
<a class="btn btn-line-w btn-lg" href="<?= e(url('/contato', ['assunto' => 'empresas'])) ?>">Atendimento para empresas</a>
</div>
</div>
</section>
</div>
