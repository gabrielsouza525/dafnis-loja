<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Response;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Services\Auth;
use App\Services\Notify;
use App\Services\Orders;
use App\Services\Payments\MercadoPagoGateway;
use App\Services\Payments\Payments;
use App\Services\Settings;

/**
 * Etapa de confirmação: mostra o status real do pedido. "Pagamento confirmado"
 * só aparece quando o banco diz "paid" — o que exige o gateway ou a baixa manual.
 */
final class OrderController extends Controller
{
    public function show(string $number): Response
    {
        $order = $this->ownOrder($number);
        $active = Course::allActive();
        // Miniatura do curso no resumo (curso desativado depois da compra fica sem)
        $items = array_map(static fn ($i) => $i + ['course' => $active[(int) $i['course_id']] ?? null], Order::items((int) $order['id']));
        $seats = $order['status'] === 'paid' ? Enrollment::forOrder((int) $order['id']) : [];

        return $this->view('site/order', [
            'title' => 'Pedido ' . $order['number'],
            'noindex' => true,
            'order' => $order,
            'items' => $items,
            'awaiting' => count(array_filter($seats, static fn ($s) => $s['status'] === 'awaiting_participant')),
            // Curso na loja liberado na hora para o próprio comprador: a página leva direto ao curso.
            'ready' => array_values(array_filter($seats, static fn ($s) => $s['status'] === 'active' && $s['has_content'] && $s['participant_email'] === mb_strtolower($order['buyer_email']))),
            // Pessoa física com uma vaga por curso já é a participante (Orders::createSeats)
            'needsParticipants' => $order['buyer_type'] === 'pj' || array_filter($items, static fn ($i) => (int) $i['quantity'] > 1) !== [],
            'online' => Payments::isOnline(),
            'autoPay' => $this->request->query('pagar') === '1' && $order['status'] === 'pending' && Payments::isOnline(),
            'gatewayStatus' => MercadoPagoGateway::statusLabel($order['gateway_status']),
            'whatsapp' => Settings::get('business.whatsapp'),
            'contactEmail' => Settings::get('business.email'),
        ]);
    }

    /** Leva ao checkout do gateway (link comum, fora do formulário: a CSP continua restrita). */
    public function pay(string $number): Response
    {
        $order = $this->ownOrder($number);
        if ($order['status'] !== 'pending') {
            return $this->redirect('/pedido/' . $number);
        }
        $url = Payments::checkoutUrl($order);
        if (!$url) {
            flash('error', 'Não foi possível abrir o pagamento agora. Tente novamente em instantes ou fale com a nossa equipe.');
            return $this->redirect('/pedido/' . $number);
        }
        return Response::redirect($url, 303);
    }

    /**
     * Volta do Mercado Pago. Os parâmetros da URL não são confiáveis: o status é
     * consultado direto na API antes de mexer no pedido.
     */
    public function returned(string $number): Response
    {
        $order = $this->ownOrder($number);
        $paymentId = (string) ($this->request->query('payment_id') ?: $this->request->query('collection_id') ?: '');
        if ($paymentId !== '' && $paymentId !== 'null' && Payments::isOnline()) {
            $updated = Payments::reconcile($paymentId, 'retorno');
            if ($updated && $updated['number'] === $order['number']) {
                $order = $updated;
            }
        }
        $message = match (true) {
            $order['status'] === 'paid' => ['success', 'Pagamento confirmado. Obrigado pela compra!'],
            $order['gateway_status'] === 'rejected' => ['error', 'O pagamento não foi aprovado. Você pode tentar de novo com outra forma de pagamento.'],
            in_array($order['gateway_status'], ['pending', 'in_process', 'authorized'], true) => ['info', 'Pagamento em processamento. Avisaremos por e-mail assim que for confirmado.'],
            default => null,
        };
        if ($message) {
            flash($message[0], $message[1]);
        }
        return $this->redirect('/pedido/' . $order['number']);
    }

    /**
     * O cliente cancela o próprio pedido enquanto ele não está pago. Antes, confere o pagamento no
     * gateway (pode ter acabado de ser aprovado); depois, expira o link de pagamento.
     * Se um pagamento ainda chegar, o pedido volta a valer (Orders::markPaid).
     */
    public function cancel(string $number): Response
    {
        $order = $this->ownOrder($number);
        $back = '/minha-conta/pedidos/' . $number;
        if ((int) $order['user_id'] !== Auth::id()) {
            $this->notFound('Pedido não encontrado.');
        }
        if ($order['status'] === 'pending' && $order['gateway_payment_id'] && Payments::isOnline()) {
            $order = Payments::reconcile((string) $order['gateway_payment_id'], 'cancelamento') ?? $order;
        }
        if ($order['status'] === 'paid') {
            flash('success', 'O pagamento deste pedido já foi confirmado, então ele não foi cancelado.');
            return $this->redirect($back);
        }
        if ($blocked = Payments::cancelBlocked($order)) {
            flash('error', $blocked);
            return $this->redirect($back);
        }
        if (!Orders::cancel((int) $order['id'], 'Cancelado pelo cliente')) {
            flash('error', 'Não foi possível cancelar o pedido agora. Tente de novo ou fale com a nossa equipe.');
            return $this->redirect($back);
        }
        $order = Order::find((int) $order['id']);
        Payments::cancelCheckout($order);
        Notify::orderCancelled($order, true);
        flash('success', 'Pedido ' . $number . ' cancelado. Nenhuma cobrança foi feita.');
        return $this->redirect($back);
    }

    private function ownOrder(string $number): array
    {
        $order = Order::findByNumber($number);
        // 404 também para pedido de outra pessoa: não confirma que o número existe.
        if (!$order || ((int) $order['user_id'] !== Auth::id() && !Auth::isAdmin())) {
            $this->notFound('Pedido não encontrado.');
        }
        return $order;
    }
}
