<?php
use App\Services\Settings;

$name = Settings::businessName();
// Data da última mudança no texto (não no visual): atualize junto com o conteúdo
$updated = '25 de setembro de 2026';
$toc = [
    'conta' => 'Conta',
    'pedidos' => 'Pedidos e preços',
    'acesso' => 'Participantes e acesso',
    'pratica' => 'Parte prática',
    'certificados' => 'Certificados',
    'cancelamento' => 'Cancelamento e arrependimento',
    'contato' => 'Contato',
];
$n = array_flip(array_keys($toc));
$num = static fn (string $id) => $n[$id] + 1;
?>
<div class="screen">
<section class="phead phead-dark">
<div class="grid-bg" aria-hidden="true"></div>
<div class="wrap">
<?= partial('crumbs', ['items' => [['Início', '/'], ['Termos de uso', null]]]) ?>
<div class="phead-row legal-head">
<div>
<h1>Termos de uso</h1>
<p>Regras para comprar e usar os treinamentos da loja.</p>
<p class="legal-meta"><?= icon('calendar', 'ic-sm') ?>Atualizado em <?= e($updated) ?></p>
</div>
<div class="legal-actions">
<button class="btn btn-line-w btn-sm" type="button" data-print hidden><?= icon('file', 'ic-sm') ?>Imprimir</button>
<a class="btn btn-line-w btn-sm" href="<?= e(url('/politica-de-privacidade')) ?>"><?= icon('lock', 'ic-sm') ?>Política de privacidade</a>
</div>
</div>
</div>
</section>

<div class="wrap legal">
<aside class="legal-aside">
<nav class="legal-toc" aria-label="Nesta página">
<h2>Nesta página</h2>
<ol>
<?php foreach ($toc as $id => $label): ?><li><a href="#<?= e($id) ?>" data-toc-link><span><?= $num($id) ?></span><?= e($label) ?></a></li><?php endforeach; ?>
</ol>
</nav>
<div class="help legal-help">
<h3>Ficou com alguma dúvida?</h3>
<p>A nossa equipe explica qualquer ponto destes termos.</p>
<a class="btn btn-navy btn-sm" href="<?= e(url('/contato')) ?>">Fale com a nossa equipe</a>
</div>
</aside>

<article class="legal-doc">
<p class="legal-intro">Estes termos valem para a loja de treinamentos da <?= e($name) ?>. Ao criar uma conta ou fazer um pedido, você concorda com eles.</p>

<section class="legal-sec prose" id="conta">
<h2><span><?= $num('conta') ?></span>Conta</h2>
<p>Para comprar é preciso ter uma conta com dados verdadeiros. Você é responsável pela senha e por tudo o que for feito com o seu acesso.</p>
</section>

<section class="legal-sec prose" id="pedidos">
<h2><span><?= $num('pedidos') ?></span>Pedidos e preços</h2>
<ul>
<li>Os valores exibidos são por participante e podem mudar sem aviso; vale o preço do momento em que o pedido é registrado.</li>
<li>O pedido só é confirmado depois da aprovação do pagamento. Enquanto isso, ele aparece como "aguardando pagamento".</li>
<li>Treinamentos marcados como "sob consulta" dependem de proposta da nossa equipe.</li>
</ul>
</section>

<section class="legal-sec prose" id="acesso">
<h2><span><?= $num('acesso') ?></span>Participantes e acesso</h2>
<ul>
<li>Cada vaga comprada corresponde a um participante. Em compras para empresas, quem comprou informa os participantes na área Minha conta.</li>
<li>Depois da confirmação do pagamento e da indicação do participante, o acesso é liberado na plataforma de ensino e enviado por e-mail.</li>
<li>O acesso é pessoal e não pode ser compartilhado.</li>
</ul>
</section>

<section class="legal-sec prose" id="pratica">
<h2><span><?= $num('pratica') ?></span>Parte prática</h2>
<p>Alguns treinamentos exigem, por norma, carga horária prática presencial. Isso é informado na página de cada curso e deve ser combinado com a nossa equipe.</p>
</section>

<section class="legal-sec prose" id="certificados">
<h2><span><?= $num('certificados') ?></span>Certificados</h2>
<p>O certificado de conclusão é emitido quando o participante conclui o treinamento e cumpre os critérios de aprovação do curso.</p>
</section>

<section class="legal-sec prose" id="cancelamento">
<h2><span><?= $num('cancelamento') ?></span>Cancelamento e arrependimento</h2>
<div class="legal-note"><?= icon('info') ?><p>Em compras pela internet, você pode desistir em até 7 dias a partir da confirmação do pedido (art. 49 do Código de Defesa do Consumidor). Para isso, fale com a nossa equipe pelo <a href="<?= e(url('/contato')) ?>">contato</a>.</p></div>
</section>

<section class="legal-sec prose" id="contato">
<h2><span><?= $num('contato') ?></span>Contato</h2>
<p>Dúvidas sobre estes termos: <a href="<?= e(url('/contato')) ?>">fale com a nossa equipe</a>.</p>
</section>
</article>
</div>
</div>
