<?php
/** @var string $code @var array|null $certificate @var bool $searched */
use App\Services\Settings;

$c = $certificate;
?>
<div class="screen">
<section class="phead">
<div class="wrap">
<?= partial('crumbs', ['items' => [['Início', '/'], ['Validar certificado', null]]]) ?>
<div class="phead-row"><div><h1>Validar certificado</h1><p>Digite o código impresso no certificado (ele começa com DF-) ou leia o QR code.</p></div></div>
</div>
</section>
<div class="wrap verify-wrap">
<form class="form-card verify-form" method="get" action="<?= e(url('/certificados')) ?>">
<div class="field"><label for="f-codigo">Código do certificado</label><input class="input mono" id="f-codigo" name="codigo" value="<?= e($code) ?>" placeholder="DF-XXXXXXXX" maxlength="11" autocomplete="off" autocapitalize="characters" required></div>
<button class="btn btn-primary" type="submit"><?= icon('search', 'ic-sm') ?>Validar</button>
</form>

<?php if ($c && $c['valid']): ?>
<div class="form-card verify-result is-ok">
<span class="success-ic"><?= icon('check') ?></span>
<div>
<div class="kicker">Certificado válido</div>
<h2><?= e(mb_strtoupper($c['name'])) ?></h2>
<p>Certificado emitido pela <?= e($c['company']) ?>.</p>
<dl class="verify-facts">
<div><dt>Código</dt><dd class="mono"><?= e($c['code']) ?></dd></div>
<div><dt>CPF</dt><dd class="mono"><?= e($c['document']) ?></dd></div>
<div class="wide"><dt>Treinamento</dt><dd><?= e($c['course']) ?></dd></div>
<div><dt>Carga horária</dt><dd><?= e(hours_long($c['hours'])) ?></dd></div>
<?php if ($c['period']): ?><div><dt>Realizado</dt><dd><?= e($c['period']) ?></dd></div><?php endif; ?>
<div><dt>Emitido em</dt><dd><?= e(date_br($c['issued_at'])) ?></dd></div>
</dl>
</div>
</div>
<?php elseif ($c): ?>
<div class="note-box err"><?= icon('alert') ?><span>O certificado <strong><?= e($c['code']) ?></strong> foi cancelado e não tem mais validade.</span></div>
<?php elseif ($searched): ?>
<div class="note-box err"><?= icon('alert') ?><span>Nenhum certificado encontrado com o código <strong class="mono"><?= e($code) ?></strong>. Confira se digitou igual ao impresso (letras e números, sem espaços).<?php if ($email = Settings::get('business.email')): ?> Dúvidas: <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>.<?php endif; ?></span></div>
<?php endif; ?>
</div>
</div>
