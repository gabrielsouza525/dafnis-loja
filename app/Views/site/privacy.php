<?php
use App\Services\Settings;

$name = Settings::businessName();
// Data da última mudança no texto (não no visual): atualize junto com o conteúdo
$updated = '25 de setembro de 2026';
$toc = [
    'dados' => 'Dados que coletamos',
    'uso' => 'Para que usamos',
    'compartilhamento' => 'Com quem compartilhamos',
    'cookies' => 'Cookies',
    'direitos' => 'Seus direitos',
    'seguranca' => 'Segurança',
];
$n = array_flip(array_keys($toc));
$num = static fn (string $id) => $n[$id] + 1;
$data = [
    ['user', 'Cadastro', 'nome, e-mail, telefone e senha (guardada de forma criptografada).'],
    ['receipt', 'Pedidos', 'CPF ou CNPJ, razão social, dados de contato e os treinamentos comprados.'],
    ['users', 'Participantes', 'nome, e-mail e CPF de quem vai fazer cada treinamento.'],
    ['message', 'Contato', 'o que você enviar pelo formulário.'],
    ['shield', 'Dados técnicos', 'endereço IP e registros de acesso, para segurança.'],
];
?>
<div class="screen">
<section class="phead phead-dark">
<div class="grid-bg" aria-hidden="true"></div>
<div class="wrap">
<?= partial('crumbs', ['items' => [['Início', '/'], ['Política de privacidade', null]]]) ?>
<div class="phead-row legal-head">
<div>
<h1>Política de privacidade</h1>
<p>Como tratamos os seus dados pessoais, conforme a LGPD (Lei nº 13.709/2018).</p>
<p class="legal-meta"><?= icon('calendar', 'ic-sm') ?>Atualizado em <?= e($updated) ?></p>
</div>
<div class="legal-actions">
<button class="btn btn-line-w btn-sm" type="button" data-print hidden><?= icon('file', 'ic-sm') ?>Imprimir</button>
<a class="btn btn-line-w btn-sm" href="<?= e(url('/termos-de-uso')) ?>"><?= icon('clipboard', 'ic-sm') ?>Termos de uso</a>
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
<h3>Quer falar sobre os seus dados?</h3>
<p>Peça acesso, correção, portabilidade ou exclusão pela nossa equipe.</p>
<a class="btn btn-navy btn-sm" href="<?= e(url('/contato')) ?>">Fale com a nossa equipe</a>
</div>
</aside>

<article class="legal-doc">
<p class="legal-intro">A <?= e($name) ?> é a controladora dos dados pessoais tratados nesta loja.</p>

<div class="legal-summary">
<h2>Em resumo</h2>
<ul>
<li><?= icon('shield') ?><span>Só cookies necessários para a loja funcionar, nenhum de publicidade.</span></li>
<li><?= icon('card') ?><span>Os dados de cartão são digitados no ambiente do Mercado Pago e não passam pela loja.</span></li>
<li><?= icon('user') ?><span>Você pode pedir acesso, correção, portabilidade ou exclusão dos seus dados.</span></li>
</ul>
</div>

<section class="legal-sec prose" id="dados">
<h2><span><?= $num('dados') ?></span>Dados que coletamos</h2>
<ul class="legal-data">
<?php foreach ($data as [$ic, $label, $text]): ?>
<li><span class="legal-data-ic"><?= icon($ic) ?></span><p><strong><?= e($label) ?>:</strong> <?= e($text) ?></p></li>
<?php endforeach; ?>
</ul>
</section>

<section class="legal-sec prose" id="uso">
<h2><span><?= $num('uso') ?></span>Para que usamos</h2>
<ul>
<li>Registrar e cobrar os pedidos, emitir documentos e cumprir obrigações legais.</li>
<li>Liberar o acesso aos treinamentos e emitir os certificados.</li>
<li>Responder ao seu contato e enviar avisos sobre os seus pedidos.</li>
</ul>
</section>

<section class="legal-sec prose" id="compartilhamento">
<h2><span><?= $num('compartilhamento') ?></span>Com quem compartilhamos</h2>
<ul>
<li><strong>Processador de pagamento</strong> (Mercado Pago), só com os dados necessários para a cobrança. Dados de cartão são digitados no ambiente dele e não passam pela nossa loja.</li>
<li><strong>Plataforma de ensino</strong> onde os treinamentos são realizados, com os dados dos participantes.</li>
<li>Autoridades, quando a lei exigir.</li>
</ul>
</section>

<section class="legal-sec prose" id="cookies">
<h2><span><?= $num('cookies') ?></span>Cookies</h2>
<p>Usamos apenas cookies necessários para o funcionamento da loja: sessão, carrinho, segurança dos formulários e, se você marcar, "manter conectado". Não usamos cookies de publicidade.</p>
</section>

<section class="legal-sec prose" id="direitos">
<h2><span><?= $num('direitos') ?></span>Seus direitos</h2>
<div class="legal-note"><?= icon('info') ?><p>Você pode pedir acesso, correção, portabilidade ou exclusão dos seus dados, e revogar consentimentos, pelo nosso <a href="<?= e(url('/contato')) ?>">contato</a>. Alguns dados são mantidos pelo prazo exigido por lei (por exemplo, registros fiscais).</p></div>
</section>

<section class="legal-sec prose" id="seguranca">
<h2><span><?= $num('seguranca') ?></span>Segurança</h2>
<p>Adotamos conexão segura, senhas criptografadas, controle de acesso e registro das operações importantes.</p>
</section>
</article>
</div>
</div>
