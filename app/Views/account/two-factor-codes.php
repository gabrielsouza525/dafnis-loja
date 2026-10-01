<?php
/** @var string[] $codes @var string $reason enabled|regenerated */
$crumbs = [['Início', '/'], ['Minha conta', '/minha-conta'], ['Meus dados', '/minha-conta/dados'], ['Códigos de recuperação', null]];
$text = App\Services\TwoFactor::issuer() . " — códigos de recuperação\n" . $user['email'] . "\n\n" . implode("\n", $codes) . "\n\nCada código vale uma vez.\n";
?>
<div class="screen">
<?= partial('account-shell-open', get_defined_vars()) ?>
<div class="form-card tf-codes-card">
<div class="recover-head">
<span class="recover-ic is-ok"><?= icon('check') ?></span>
<div><h2><?= $reason === 'enabled' ? 'Verificação em duas etapas ativada' : 'Novos códigos de recuperação' ?></h2>
<p><?= $reason === 'enabled' ? 'A partir de agora, ao entrar, pedimos o código do aplicativo.' : 'Os códigos antigos deixaram de funcionar.' ?> Guarde os códigos abaixo: eles servem para entrar se você perder o celular.</p></div>
</div>
<ol class="tf-codes" id="tf-codes" data-copy-text="<?= e(implode("\n", $codes)) ?>">
<?php foreach ($codes as $code): ?><li><code><?= e($code) ?></code></li><?php endforeach; ?>
</ol>
<div class="note-box" style="margin:0"><?= icon('info') ?><span>Cada código vale uma vez. Guarde num lugar seguro, como um gerenciador de senhas ou impresso. <strong>Depois de sair desta tela, eles não aparecem de novo.</strong></span></div>
<div class="tf-codes-actions">
<button class="btn btn-outline btn-sm" type="button" data-copy="tf-codes" data-copied="Códigos copiados." hidden><?= icon('clipboard', 'ic-sm') ?>Copiar</button>
<button class="btn btn-outline btn-sm" type="button" data-download-text="<?= e($text) ?>" data-filename="codigos-de-recuperacao-dafnis.txt" hidden><?= icon('download', 'ic-sm') ?>Baixar .txt</button>
<button class="btn btn-outline btn-sm" type="button" data-print hidden><?= icon('file', 'ic-sm') ?>Imprimir</button>
<a class="btn btn-primary btn-sm tf-done" href="<?= e(url('/minha-conta/dados')) ?>#duas-etapas">Já guardei os códigos<?= icon('arrowR', 'ic-sm') ?></a>
</div>
</div>
<?= partial('account-shell-close') ?>
</div>
