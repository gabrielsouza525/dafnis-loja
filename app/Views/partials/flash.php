<?php
// Mensagem da requisição anterior (redirect), exibida como toast pelo app.js.
$toast = App\Core\Session::getFlash('toast');
?>
<div class="toasts" id="toasts" aria-live="polite" aria-atomic="false"></div>
<?php if ($toast): ?>
<script type="application/json" id="flash-data"><?= json_script($toast) ?></script>
<?php endif; ?>
