<?php
declare(strict_types=1);

namespace App\Services\Payments;

use App\Core\Request;

/**
 * Contrato de um gateway de pagamento. Para trocar de gateway (Asaas, Pagar.me...),
 * implemente esta interface e registre em Payments::gateway().
 */
interface PaymentGateway
{
    /** Identificador gravado em orders.gateway. */
    public function name(): string;

    /** true quando cobra online de verdade (credenciais configuradas). */
    public function isLive(): bool;

    /**
     * Cria a sessão de pagamento do pedido.
     * @return array{url: string, reference: string}|null null quando o pagamento é combinado fora da loja
     */
    public function startCheckout(array $order, array $items): ?array;

    /**
     * Consulta um pagamento direto na API do gateway (fonte da verdade).
     * @return array{id: string, status: string, order_number: ?string, amount: float, currency: string, method: ?string}|null
     */
    public function fetchPayment(string $paymentId): ?array;

    /** Confere a assinatura da notificação recebida no webhook. */
    public function verifyWebhook(Request $request): bool;
}
