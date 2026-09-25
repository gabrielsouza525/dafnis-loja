<?php
use App\Services\Settings;

$name = Settings::businessName();
?>
<div class="screen">
<section class="phead"><div class="wrap">
<?= partial('crumbs', ['items' => [['Início', '/'], ['Termos de uso', null]]]) ?>
<div class="phead-row"><div><h1>Termos de uso</h1><p>Regras para comprar e usar os treinamentos da loja.</p></div></div>
</div></section>
<div class="wrap page">
<div class="prose page-narrow">
<p>Estes termos valem para a loja de treinamentos da <?= e($name) ?>. Ao criar uma conta ou fazer um pedido, você concorda com eles.</p>
<h2>1. Conta</h2>
<p>Para comprar é preciso ter uma conta com dados verdadeiros. Você é responsável pela senha e por tudo o que for feito com o seu acesso.</p>
<h2>2. Pedidos e preços</h2>
<ul>
<li>Os valores exibidos são por participante e podem mudar sem aviso; vale o preço do momento em que o pedido é registrado.</li>
<li>O pedido só é confirmado depois da aprovação do pagamento. Enquanto isso, ele aparece como "aguardando pagamento".</li>
<li>Treinamentos marcados como "sob consulta" dependem de proposta da nossa equipe.</li>
</ul>
<h2>3. Participantes e acesso</h2>
<ul>
<li>Cada vaga comprada corresponde a um participante. Em compras para empresas, quem comprou informa os participantes na área Minha conta.</li>
<li>Depois da confirmação do pagamento e da indicação do participante, o acesso é liberado na plataforma de ensino e enviado por e-mail.</li>
<li>O acesso é pessoal e não pode ser compartilhado.</li>
</ul>
<h2>4. Parte prática</h2>
<p>Alguns treinamentos exigem, por norma, carga horária prática presencial. Isso é informado na página de cada curso e deve ser combinado com a nossa equipe.</p>
<h2>5. Certificados</h2>
<p>O certificado de conclusão é emitido quando o participante conclui o treinamento e cumpre os critérios de aprovação do curso.</p>
<h2>6. Cancelamento e arrependimento</h2>
<p>Em compras pela internet, você pode desistir em até 7 dias a partir da confirmação do pedido (art. 49 do Código de Defesa do Consumidor). Para isso, fale com a nossa equipe pelo <a href="<?= e(url('/contato')) ?>">contato</a>.</p>
<h2>7. Contato</h2>
<p>Dúvidas sobre estes termos: <a href="<?= e(url('/contato')) ?>">fale com a nossa equipe</a>.</p>
</div>
</div>
</div>
