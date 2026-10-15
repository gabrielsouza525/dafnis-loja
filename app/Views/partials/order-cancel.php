<?php
/**
 * Botão "Cancelar pedido" para o próprio cliente, enquanto o pedido não está pago (e o pagamento
 * não está em análise no gateway).
 * @var array $order
 * @var string|null $size classe de tamanho do botão (btn-sm, btn-lg)
 */
use App\Services\Payments\Payments;

if (Payments::cancelBlocked($order) !== null) {
    return;
}
$confirm = 'Cancelar o pedido ' . $order['number'] . '? Use esta opção só se você ainda não pagou. '
    . 'Se já pagou um boleto, a compensação pode levar até 3 dias úteis: nesse caso, não cancele.';
?>
<form method="post" action="<?= e(url('/pedido/' . $order['number'] . '/cancelar')) ?>" class="order-cancel" data-confirm="<?= e($confirm) ?>" data-loading-form>
<?= csrf_field() ?>
<button class="btn btn-danger <?= e($size ?? 'btn-sm') ?>" type="submit"><?= icon('close', 'ic-sm') ?>Cancelar pedido</button>
</form>
