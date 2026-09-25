<?php
declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Request;

/**
 * Sem gateway configurado: o pedido fica "Aguardando pagamento" e a equipe
 * combina o pagamento com o cliente e dá a baixa no painel. Nunca aprova nada sozinho.
 */
final class ManualGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'manual';
    }

    public function isLive(): bool
    {
        return false;
    }

    public function startCheckout(array $order, array $items): ?array
    {
        return null;
    }

    public function fetchPayment(string $paymentId): ?array
    {
        return null;
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }
}
