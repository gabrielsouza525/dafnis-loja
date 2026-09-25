<?php
/**
 * @var array $course @var string $summary @var array $syllabus @var array $related
 * @var string $practicalNote @var array $crumbs
 */
use App\Models\Course;

$c = $course;
$crumbItems = $crumbs;
$crumbItems[count($crumbItems) - 1][1] = null;
$sections = [['c-sobre', 'Sobre'], ['c-publico', 'Para quem é']];
if ($c['objectives']) {
    $sections[] = ['c-objetivos', 'Objetivos'];
}
$sections[] = ['c-conteudo', 'Conteúdo programático'];
$sections[] = ['c-info', 'Informações'];
$sections[] = ['c-faq', 'Perguntas frequentes'];

$infoRows = [
    ['Carga horária', $c['hours_long'] . ($c['hours_note'] ? ' ' . $c['hours_note'] : '')],
    ['Modalidade', $c['modality_label']],
    ['Tipo', $c['type_label']],
    ['Categoria', $c['category_name'] ?? '—'],
    ['Prática obrigatória', $c['practical_required'] ? ($c['practical_hours'] ? 'Sim — ' . $c['practical_hours'] : 'Sim') : 'Não'],
    ['Certificado', $c['certificate'] ? 'Certificado de conclusão' : 'Não emite certificado'],
];
if ($c['nr_number']) {
    $infoRows[] = ['Norma', 'NR ' . $c['nr_number'] . (isset(Course::NR_NAMES[$c['nr_number']]) ? ' — ' . Course::NR_NAMES[$c['nr_number']] : '')];
}
if ($c['access_days']) {
    $infoRows[] = ['Prazo de acesso', pluralize((int) $c['access_days'], 'dia', 'dias')];
}
if ($c['requirements']) {
    $infoRows[] = ['Pré-requisitos', $c['requirements']];
}
$infoRows[] = ['Idioma', 'Português'];

$faqs = [
    ['Qual é a carga horária deste treinamento?', 'A carga horária é de ' . $c['hours_long'] . ($c['hours_note'] ? ' ' . $c['hours_note'] : '') . '.' . ($c['practical_required'] ? ' O treinamento tem parte prática presencial obrigatória' . ($c['practical_hours'] ? ' (' . $c['practical_hours'] . ')' : '') . '.' : '')],
    ['Posso comprar para mais de um participante?', 'Sim. Ajuste a quantidade de participantes antes de comprar. Depois da confirmação do pagamento, você informa o nome, o e-mail e o CPF de cada participante em Minha conta.'],
    ['Como acesso o curso depois da compra?', 'Com o pagamento confirmado, liberamos o acesso na plataforma de ensino e o participante recebe o link por e-mail. O curso também aparece em Minha conta › Meus cursos.'],
];
if ($c['certificate']) {
    $faqs[] = ['Como recebo o certificado?', 'Ao concluir o treinamento e cumprir os critérios de aprovação do curso, o certificado fica disponível para download em Minha conta › Certificados.'];
}
?>
<div class="screen">
<section class="c-hero">
<div class="wrap">
<?= partial('crumbs', ['items' => $crumbItems]) ?>
<div class="c-hero-grid">
<div>
<div class="c-badges"><span class="c-code"><?= e($c['code_label']) ?></span><?php if ($c['badge']): ?><span class="pill badge-<?= e($c['badge']) ?>"><?= e($c['badge_label']) ?></span><?php endif; ?><span class="pill pill-plain"><?= e($c['category_name']) ?></span></div>
<h1><?= e($c['title']) ?></h1>
<p class="c-desc"><?= e($summary) ?></p>
<div class="c-facts">
<div class="c-fact"><?= icon('clock') ?><div><small>Carga horária</small><strong><?= e($c['hours_long']) ?></strong></div></div>
<div class="c-fact"><?= icon('monitor') ?><div><small>Modalidade</small><strong><?= e($c['modality_label']) ?></strong></div></div>
<div class="c-fact"><?= icon('briefcase') ?><div><small>Tipo</small><strong><?= e($c['training_type'] === 'periodico' ? 'Periódico / Reciclagem' : 'Formação') ?></strong></div></div>
<div class="c-fact"><?= icon('award') ?><div><small>Conclusão</small><strong><?= $c['certificate'] ? 'Certificado' : 'Sem certificado' ?></strong></div></div>
</div>
</div>
<?= partial('cover', ['course' => $c, 'variant' => 'lg']) ?>
</div>
</div>
</section>
<div class="wrap">
<div class="c-layout">
<div>
<nav class="c-subnav" aria-label="Seções do curso" data-subnav>
<?php foreach ($sections as [$id, $label]): ?><a href="#<?= e($id) ?>"><?= e($label) ?></a><?php endforeach; ?>
</nav>
<section class="c-sec" id="c-sobre">
<h2>Sobre este treinamento</h2>
<div class="prose">
<?php if ($c['description']): ?><?= nl2br(e($c['description'])) ?><?php else: ?><p><?= e(Course::factualSummary($c)) ?></p><?php endif; ?>
</div>
<?php if ($c['practical_required']): ?>
<div class="note-box info"><?= icon('info') ?><span><strong>Parte prática obrigatória<?= $c['practical_hours'] ? ': ' . e($c['practical_hours']) : '' ?>.</strong> <?= $c['practical_note'] ? e($c['practical_note']) . ' ' : '' ?><?= e($practicalNote) ?> <a href="<?= e(url('/contato', ['assunto' => 'curso', 'curso' => $c['slug']])) ?>">Falar com a equipe</a></span></div>
<?php endif; ?>
</section>
<section class="c-sec" id="c-publico">
<h2>Para quem é</h2>
<?php if ($c['audience']): ?>
<div class="prose"><p><?= nl2br(e($c['audience'])) ?></p></div>
<?php else: ?>
<div class="checks">
<div class="check"><?= icon('check') ?>Profissionais que atuam em atividades relacionadas ao tema do treinamento.</div>
<div class="check"><?= icon('check') ?>Empresas que precisam capacitar suas equipes.</div>
<div class="check"><?= icon('check') ?>Profissionais que buscam atualização e desenvolvimento.</div>
</div>
<?php endif; ?>
</section>
<?php if ($c['objectives']): ?>
<section class="c-sec" id="c-objetivos">
<h2>Objetivos</h2>
<div class="prose"><p><?= nl2br(e($c['objectives'])) ?></p></div>
</section>
<?php endif; ?>
<section class="c-sec" id="c-conteudo">
<h2>Conteúdo programático</h2>
<?php if ($syllabus): ?>
<div class="mods" data-accordion>
<?php foreach ($syllabus as $i => $m): ?>
<div class="mod">
<button class="mod-q" type="button" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="mod-<?= $i ?>"><span class="mod-n"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span><span class="mod-t"><?= e($m['title']) ?></span><?php if ($m['hours'] !== ''): ?><span class="mod-m"><?= e($m['hours']) ?></span><?php endif; ?><?= icon('chevD') ?></button>
<div class="mod-a" id="mod-<?= $i ?>"<?= $i === 0 ? '' : ' hidden' ?>><?= $m['topics'] !== '' ? e($m['topics']) : 'Tópicos detalhados disponíveis sob solicitação.' ?></div>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty" style="padding:36px 24px;align-items:flex-start;text-align:left">
<span class="empty-ic" style="margin:0 0 4px"><?= icon('file') ?></span>
<h3 style="font-size:18px">Ementa completa sob solicitação</h3>
<p style="margin-bottom:6px">Enviamos o conteúdo programático oficial deste treinamento para você avaliar antes da compra.</p>
<a class="btn btn-outline btn-sm" href="<?= e(url('/contato', ['assunto' => 'conteudo', 'curso' => $c['slug']])) ?>"><?= icon('mail', 'ic-sm') ?>Solicitar conteúdo programático</a>
</div>
<?php endif; ?>
</section>
<section class="c-sec" id="c-info">
<h2>Informações do curso</h2>
<dl class="info-grid">
<?php foreach ($infoRows as [$k, $v]): ?><div class="info-row"><dt><span><?= e($k) ?></span></dt><dd style="margin:0"><strong><?= e($v) ?></strong></dd></div><?php endforeach; ?>
</dl>
</section>
<section class="c-sec" id="c-faq">
<h2>Perguntas frequentes</h2>
<div class="acc" data-accordion>
<?php foreach ($faqs as $i => [$q, $a]): ?>
<div class="acc-item">
<button class="acc-q" type="button" aria-expanded="false" aria-controls="cfaq-<?= $i ?>"><?= e($q) ?><span class="acc-sign"><?= icon('plus', 'ic-sm') ?></span></button>
<div class="acc-a" id="cfaq-<?= $i ?>" hidden><?= e($a) ?></div>
</div>
<?php endforeach; ?>
</div>
</section>
</div>
<aside class="c-aside" aria-label="Compra">
<div class="buy-card">
<div class="buy-body">
<?php if ($c['has_price']): ?>
<div class="buy-top"><?php if ($c['old_price']): ?><span class="price-old"><?= money($c['old_price']) ?></span><span class="pct">-<?= (int) $c['discount_pct'] ?>%</span><?php endif; ?></div>
<div class="buy-price"><?= money($c['final_price']) ?></div>
<p class="buy-note">Valor por participante<?= $c['practical_required'] ? ' · parte teórica online' : '' ?></p>
<form id="buy-form" method="post" action="<?= e(url('/carrinho/adicionar')) ?>" data-add-to-cart data-buy-form data-unit="<?= e((string) $c['final_price']) ?>">
<?= csrf_field() ?>
<input type="hidden" name="course_id" value="<?= (int) $c['id'] ?>">
<div class="qty-row">
<label class="qty-label" for="qty">Participantes<small>Compre para você ou sua equipe</small></label>
<div class="stepper" data-stepper><button type="button" aria-label="Diminuir participantes" data-step="-1" disabled><?= icon('minus', 'ic-sm') ?></button><input id="qty" name="qty" type="number" inputmode="numeric" min="1" max="200" value="1" aria-live="polite"><button type="button" aria-label="Aumentar participantes" data-step="1"><?= icon('plus', 'ic-sm') ?></button></div>
</div>
<div class="sum-row" style="padding-top:0;margin-top:-6px;margin-bottom:10px" data-qty-total hidden><span>Total para <span data-qty-n>1</span> participantes</span><strong data-qty-sum><?= money($c['final_price']) ?></strong></div>
<div class="buy-actions has-bar">
<button class="btn btn-buy btn-lg btn-block" type="submit" name="buy_now" value="1">Comprar agora</button>
<button class="btn btn-outline btn-block" type="submit"><?= icon('cart') ?>Adicionar ao carrinho</button>
</div>
</form>
<?php else: ?>
<div class="buy-price is-consult">Sob consulta</div>
<p class="buy-note">O valor deste treinamento depende da turma e da legislação aplicável. Envie sua necessidade e retornamos com uma proposta.</p>
<div class="buy-actions" style="margin-top:22px">
<a class="btn btn-buy btn-lg btn-block" href="<?= e(url('/contato', ['assunto' => 'curso', 'curso' => $c['slug']])) ?>">Solicitar proposta</a>
</div>
<?php endif; ?>
<div class="buy-incl">
<div class="check"><?= icon('check') ?><?= e($c['hours_long']) ?> de carga horária</div>
<div class="check"><?= icon('check') ?>Modalidade <?= e(mb_strtolower($c['modality_label'])) ?></div>
<?php if ($c['certificate']): ?><div class="check"><?= icon('check') ?>Certificado de conclusão</div><?php endif; ?>
<div class="check"><?= icon('check') ?>Compra para múltiplos participantes</div>
</div>
</div>
<div class="buy-foot"><?= icon('building') ?><span>Vai treinar uma equipe grande? <a href="<?= e(url('/contato', ['assunto' => 'empresas', 'curso' => $c['slug']])) ?>">Fale com nossa equipe</a></span></div>
</div>
</aside>
</div>
</div>
<?php if ($related): ?>
<section class="related">
<div class="wrap">
<div class="sec-head"><div><div class="kicker">Continue explorando</div><h2 style="font-size:32px">Treinamentos relacionados</h2></div><a class="text-link" href="<?= e(url('/cursos')) ?>">Ver catálogo<?= icon('arrowR', 'ic-sm') ?></a></div>
<div class="grid4">
<?php foreach ($related as $r): ?><?= partial('course-card', ['course' => $r, 'showDesc' => false]) ?><?php endforeach; ?>
</div>
</div>
</section>
<?php endif; ?>
</div>
<div class="m-buybar">
<?php if ($c['has_price']): ?>
<div class="mb-price"><?php if ($c['old_price']): ?><small><?= money($c['old_price']) ?></small><?php endif; ?><strong><?= money($c['final_price']) ?></strong></div>
<button class="btn btn-outline" type="submit" form="buy-form" aria-label="Adicionar ao carrinho" style="width:48px;padding:0"><?= icon('cart') ?></button>
<button class="btn btn-buy" type="submit" form="buy-form" name="buy_now" value="1">Comprar agora</button>
<?php else: ?>
<div class="mb-price"><strong>Sob consulta</strong></div>
<a class="btn btn-buy" href="<?= e(url('/contato', ['assunto' => 'curso', 'curso' => $c['slug']])) ?>">Solicitar proposta</a>
<?php endif; ?>
</div>
