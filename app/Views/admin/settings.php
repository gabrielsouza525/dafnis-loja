<?php
/** @var array $values @var array $faq @var bool $online @var string $gatewayName @var string $webhookUrl */
$f = static fn (string $key, string $label, array $extra = []) => partial('field', array_merge([
    'name' => str_replace('.', '__', $key),
    'label' => $label,
    'value' => $values[$key] ?? '',
], $extra));
$faqRows = $faq ?: [['q' => '', 'a' => '']];
?>
<div class="adm-head"><div><h1>Configurações</h1><p>Dados da empresa, contatos, textos da home e integração de pagamento. Campos vazios não aparecem na loja.</p></div></div>
<form method="post" action="<?= e(url('/admin/configuracoes')) ?>" data-loading-form>
<?= csrf_field() ?>
<div class="grid-2">
<div>
<div class="panel"><div class="panel-head"><h2>Empresa e contato</h2></div><div class="panel-body">
<div class="form-grid">
<?= $f('business.name', 'Nome da empresa', ['required' => true]) ?>
<?= $f('business.cnpj', 'CNPJ', ['mask' => 'cnpj', 'optional' => true, 'value' => $values['business.cnpj'] ? document_display($values['business.cnpj']) : '']) ?>
<?= $f('business.email', 'E-mail de contato', ['type' => 'email', 'optional' => true, 'hint' => 'Também recebe os avisos de pedidos e contatos (se MAIL_ADMIN_ADDRESS estiver vazio).']) ?>
<?= $f('business.whatsapp', 'WhatsApp', ['mask' => 'phone', 'optional' => true, 'value' => $values['business.whatsapp'] ? phone_display($values['business.whatsapp']) : '']) ?>
<?= $f('business.phone', 'Telefone fixo', ['mask' => 'phone', 'optional' => true, 'value' => $values['business.phone'] ? phone_display($values['business.phone']) : '']) ?>
<?= $f('business.address', 'Endereço', ['optional' => true]) ?>
<?= $f('business.city', 'Cidade', ['optional' => true]) ?>
<?= $f('business.state', 'UF', ['optional' => true, 'attrs' => ['maxlength' => 2, 'style' => 'text-transform:uppercase']]) ?>
</div>
</div></div>

<div class="panel"><div class="panel-head"><h2>Redes sociais</h2></div><div class="panel-body">
<div class="form-grid-3">
<?= $f('business.instagram', 'Instagram (usuário)', ['optional' => true, 'placeholder' => 'dafnis']) ?>
<?= $f('business.linkedin', 'LinkedIn (link)', ['type' => 'url', 'optional' => true]) ?>
<?= $f('business.youtube', 'YouTube (link)', ['type' => 'url', 'optional' => true]) ?>
</div>
</div></div>

<div class="panel"><div class="panel-head"><h2>Home</h2></div><div class="panel-body">
<p class="hint" style="margin:0 0 14px">Indicadores da seção "Para empresas". "Treinamentos no catálogo" e "NRs atendidas" são calculados automaticamente; os demais só aparecem se preenchidos com dados reais.</p>
<div class="form-grid-3">
<?= $f('stats.companies', 'Empresas atendidas', ['optional' => true, 'placeholder' => 'Ex.: 350+']) ?>
<?= $f('stats.professionals', 'Profissionais capacitados', ['optional' => true, 'placeholder' => 'Ex.: 5.000+']) ?>
<?= $f('stats.years', 'Anos de atuação', ['optional' => true, 'placeholder' => 'Ex.: 10']) ?>
</div>
<div style="margin-top:16px"><?= $f('content.about', 'Texto institucional (seção "Nossos diferenciais")', ['type' => 'textarea', 'optional' => true]) ?></div>
<div style="margin-top:16px"><?= $f('notice.text', 'Aviso no topo da loja', ['optional' => true, 'hint' => 'Vazio: mostra automaticamente o aviso de pagamento em ativação enquanto o Mercado Pago não estiver configurado.']) ?></div>
<div style="margin-top:16px"><?= $f('content.practical_note', 'Aviso dos cursos com parte prática', ['optional' => true, 'hint' => 'Aparece na página dos cursos semipresenciais. Padrão: "Fale com a nossa equipe para combinar a parte prática."']) ?></div>
</div></div>

<div class="panel"><div class="panel-head"><h2>Perguntas frequentes (home)</h2><button class="btn btn-outline btn-xs" type="button" data-repeater-add="faq"><?= icon('plus', 'ic-sm') ?>Pergunta</button></div><div class="panel-body">
<div data-repeater="faq">
<?php foreach ($faqRows as $item): ?>
<div class="repeater-row faq" data-repeater-row>
<div class="field"><label>Pergunta</label><input class="input" name="faq_q[]" value="<?= e($item['q']) ?>"></div>
<button class="btn btn-danger btn-sm" type="button" data-repeater-remove aria-label="Remover pergunta"><?= icon('trash', 'ic-sm') ?></button>
<div class="field full"><label>Resposta</label><textarea class="textarea" name="faq_a[]" style="min-height:80px"><?= e($item['a']) ?></textarea></div>
</div>
<?php endforeach; ?>
</div>
</div></div>
</div>

<aside>
<div class="panel"><div class="panel-head"><h2>Plataforma de ensino</h2></div><div class="panel-body">
<?= $f('lms.url', 'Link de acesso dos alunos', ['type' => 'url', 'optional' => true, 'hint' => 'Endereço da plataforma white label. Usado no botão "Continuar" quando o curso não tem link próprio.']) ?>
</div></div>
<div class="panel"><div class="panel-head"><h2>Pagamento</h2></div><div class="panel-body">
<?php if ($online): ?>
<p><?= partial('status', ['label' => 'Mercado Pago ativo', 'tone' => 'ok']) ?></p>
<p class="hint">Cadastre no painel do Mercado Pago (Suas integrações › Webhooks) a URL abaixo, evento "Pagamentos", e copie a chave secreta para <code>MP_WEBHOOK_SECRET</code>.</p>
<div class="code-box"><?= e($webhookUrl) ?></div>
<?php else: ?>
<p><?= partial('status', ['label' => 'Pagamento manual', 'tone' => 'warn']) ?></p>
<p class="hint">Para cobrar online, preencha <code>MP_ACCESS_TOKEN</code> no arquivo .env do servidor. Enquanto isso, os pedidos aguardam a baixa manual em Pedidos.</p>
<?php endif; ?>
</div></div>
<button class="btn btn-buy btn-lg btn-block" type="submit"><?= icon('check') ?>Salvar configurações</button>
</aside>
</div>
</form>
