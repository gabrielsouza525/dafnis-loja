<?php
/** @var string $secret @var string $qr @var string $issuer @var array $user @var bool $required equipe */
$crumbs = [['Início', '/'], ['Minha conta', '/minha-conta'], ['Meus dados', '/minha-conta/dados'], ['Conectar outro celular', null]];
?>
<div class="screen">
<?= partial('account-shell-open', get_defined_vars()) ?>
<div class="acc-head"><div><h1>Conectar outro celular</h1><p>Leia o QR code no aplicativo autenticador do outro celular. Todos os celulares conectados mostram os mesmos códigos.</p></div></div>
<div class="note-box" style="margin:0 0 22px"><?= icon('shield') ?><span><strong>Não mostre este QR code a ninguém.</strong> Com ele, outra pessoa também gera os seus códigos. Esta tela fica disponível por 10 minutos.</span></div>
<div class="tf-setup">
<div class="form-card">
<div class="form-sec">
<h2><span>1</span>Instale um aplicativo autenticador no outro celular</h2>
<p>Pode ser o mesmo do primeiro celular ou outro: Google Authenticator, Microsoft Authenticator ou o de sua preferência.</p>
</div>
<div class="form-sec">
<h2><span>2</span>Leia o QR code com o aplicativo</h2>
<div class="tf-qr-row">
<div class="tf-qr"><?= $qr ?></div>
<div class="tf-key">
<p>No aplicativo, toque em <strong>+</strong> e escolha ler um QR code.</p>
<p class="tf-key-label">Não consegue ler? Digite esta chave:</p>
<code class="tf-secret" id="tf-secret" data-copy-text="<?= e($secret) ?>"><?= e(trim(chunk_split($secret, 4, ' '))) ?></code>
<button class="btn btn-outline btn-xs" type="button" data-copy="tf-secret" data-copied="Chave copiada." hidden><?= icon('clipboard', 'ic-sm') ?>Copiar a chave</button>
<dl class="tf-meta"><div><dt>Conta</dt><dd><?= e($issuer) ?> · <?= e($user['email']) ?></dd></div><div><dt>Tipo</dt><dd>Baseado em tempo</dd></div></dl>
</div>
</div>
</div>
<div class="form-sec">
<h2><span>3</span>Confira o código</h2>
<p>O código do novo celular deve ser igual ao do primeiro (os dois mudam juntos a cada 30 segundos). Se for, está pronto: você pode entrar com qualquer um dos celulares.</p>
<a class="btn btn-primary btn-lg" href="<?= e(url('/minha-conta/dados#duas-etapas')) ?>">Pronto<?= icon('arrowR') ?></a>
</div>
</div>
<aside class="contact-aside">
<div class="help">
<h3>Perdeu o celular antigo?</h3>
<?php if ($required): ?>
<p>Conectar outro celular não desliga o antigo. Se ele foi perdido ou roubado, peça a outra pessoa da equipe para desativar a sua verificação em Painel › Usuários e ative de novo: isso troca o QR code, e o celular antigo deixa de funcionar.</p>
<?php else: ?>
<p>Conectar outro celular não desliga o antigo. Se ele foi perdido ou roubado, desative e ative de novo a verificação em Meus dados: isso troca o QR code, e o celular antigo deixa de funcionar.</p>
<?php endif; ?>
<a class="text-link" href="<?= e(url('/minha-conta/dados#duas-etapas')) ?>"><?= icon('arrowL', 'ic-sm') ?>Voltar para Meus dados</a>
</div>
</aside>
</div>
<?= partial('account-shell-close') ?>
</div>
