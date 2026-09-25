<?php
use App\Services\Settings;

$name = Settings::businessName();
?>
<div class="screen">
<section class="phead"><div class="wrap">
<?= partial('crumbs', ['items' => [['Início', '/'], ['Política de privacidade', null]]]) ?>
<div class="phead-row"><div><h1>Política de privacidade</h1><p>Como tratamos os seus dados pessoais, conforme a LGPD (Lei nº 13.709/2018).</p></div></div>
</div></section>
<div class="wrap page">
<div class="prose page-narrow">
<p>A <?= e($name) ?> é a controladora dos dados pessoais tratados nesta loja.</p>
<h2>Dados que coletamos</h2>
<ul>
<li><strong>Cadastro:</strong> nome, e-mail, telefone e senha (guardada de forma criptografada).</li>
<li><strong>Pedidos:</strong> CPF ou CNPJ, razão social, dados de contato e os treinamentos comprados.</li>
<li><strong>Participantes:</strong> nome, e-mail e CPF de quem vai fazer cada treinamento.</li>
<li><strong>Contato:</strong> o que você enviar pelo formulário.</li>
<li><strong>Dados técnicos:</strong> endereço IP e registros de acesso, para segurança.</li>
</ul>
<h2>Para que usamos</h2>
<ul>
<li>Registrar e cobrar os pedidos, emitir documentos e cumprir obrigações legais.</li>
<li>Liberar o acesso aos treinamentos e emitir os certificados.</li>
<li>Responder ao seu contato e enviar avisos sobre os seus pedidos.</li>
</ul>
<h2>Com quem compartilhamos</h2>
<ul>
<li><strong>Processador de pagamento</strong> (Mercado Pago), só com os dados necessários para a cobrança. Dados de cartão são digitados no ambiente dele e não passam pela nossa loja.</li>
<li><strong>Plataforma de ensino</strong> onde os treinamentos são realizados, com os dados dos participantes.</li>
<li>Autoridades, quando a lei exigir.</li>
</ul>
<h2>Cookies</h2>
<p>Usamos apenas cookies necessários para o funcionamento da loja: sessão, carrinho, segurança dos formulários e, se você marcar, "manter conectado". Não usamos cookies de publicidade.</p>
<h2>Seus direitos</h2>
<p>Você pode pedir acesso, correção, portabilidade ou exclusão dos seus dados, e revogar consentimentos, pelo nosso <a href="<?= e(url('/contato')) ?>">contato</a>. Alguns dados são mantidos pelo prazo exigido por lei (por exemplo, registros fiscais).</p>
<h2>Segurança</h2>
<p>Adotamos conexão segura, senhas criptografadas, controle de acesso e registro das operações importantes.</p>
</div>
</div>
</div>
