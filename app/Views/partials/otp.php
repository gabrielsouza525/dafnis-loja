<?php
/**
 * Código de 6 dígitos. Sem JavaScript é um campo comum; o app.js transforma em seis casas
 * (digitar avança, colar preenche tudo, Backspace volta, setas navegam) e envia ao completar.
 * Adaptado do componente OtpInput (React) para o JavaScript puro da loja.
 * @var string|null $name @var string|null $label @var string|null $hint @var bool|null $autofocus @var bool|null $autosubmit
 */
$name = $name ?? 'code';
$id = 'f-' . $name;
$label = $label ?? 'Código de verificação';
$error = field_error($name);
?>
<div class="otp-field">
<label class="otp-label" for="<?= e($id) ?>"><?= e($label) ?></label>
<div class="otp<?= $error ? ' is-error' : '' ?>" data-otp data-otp-label="<?= e($label) ?>"<?= !empty($autosubmit) ? ' data-otp-autosubmit' : '' ?>>
<input class="input otp-native" id="<?= e($id) ?>" name="<?= e($name) ?>" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" required aria-describedby="<?= e($id) ?>-msg"<?= $error ? ' aria-invalid="true"' : '' ?><?= !empty($autofocus) ? ' autofocus' : '' ?> data-otp-input>
</div>
<p class="otp-msg<?= $error ? ' is-error' : '' ?>" id="<?= e($id) ?>-msg" data-otp-msg data-hint="<?= e($hint ?? '') ?>"><?php if ($error): ?><?= icon('alert') ?><span><?= e($error) ?></span><?php elseif (!empty($hint)): ?><span><?= e($hint) ?></span><?php endif; ?></p>
</div>
