<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Enrollment;
use App\Models\Order;
use App\Services\Activity;
use App\Services\Orders;
use App\Services\Payments\Payments;

final class OrderController extends AdminController
{
    public function index(): Response
    {
        $status = (string) $this->request->query('status', '');
        $q = trim((string) $this->request->query('q', ''));
        $where = ['1 = 1'];
        $params = [];
        if (isset(Order::STATUS[$status])) {
            $where[] = 'o.status = :st';
            $params['st'] = $status;
        }
        if ($q !== '') {
            $digits = preg_replace('/\D/', '', $q);
            $where[] = '(o.number LIKE :q1 OR o.buyer_name LIKE :q2 OR o.buyer_email LIKE :q3 OR o.company_name LIKE :q4' . ($digits !== '' ? ' OR o.buyer_document LIKE :q5' : '') . ')';
            $params += ['q1' => $this->likeTerm($q), 'q2' => $this->likeTerm($q), 'q3' => $this->likeTerm($q), 'q4' => $this->likeTerm($q)];
            if ($digits !== '') {
                $params['q5'] = $this->likeTerm($digits);
            }
        }
        $page = $this->paginate(
            'SELECT o.*, (SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = o.id) AS participants
               FROM orders o WHERE ' . implode(' AND ', $where) . ' ORDER BY o.id DESC',
            $params
        );
        $counts = array_column(Database::select('SELECT status, COUNT(*) AS n FROM orders GROUP BY status'), 'n', 'status');
        return $this->view('admin/orders/index', [
            'title' => 'Pedidos',
            'section' => 'pedidos',
            'page' => $page,
            'status' => $status,
            'q' => $q,
            'counts' => $counts,
        ]);
    }

    public function show(int $id): Response
    {
        $order = $this->findOr404(Order::find($id), 'Pedido não encontrado.');
        return $this->view('admin/orders/show', [
            'title' => 'Pedido ' . $order['number'],
            'section' => 'pedidos',
            'order' => $order,
            'items' => Order::items($id),
            'seats' => Enrollment::forOrder($id),
            'events' => Database::select('SELECT * FROM payment_events WHERE order_id = :o ORDER BY id DESC LIMIT 20', ['o' => $id]),
            'activity' => \App\Services\Activity::forSubject('order', $id),
            'buyer' => $order['user_id'] ? Database::first('SELECT id, name, email FROM users WHERE id = :id', ['id' => $order['user_id']]) : null,
            'online' => Payments::isOnline(),
        ]);
    }

    public function markPaid(int $id): Response
    {
        $order = $this->findOr404(Order::find($id), 'Pedido não encontrado.');
        if ($order['status'] !== 'pending' && $order['status'] !== 'cancelled') {
            throw ValidationException::with('order', 'Só pedidos aguardando pagamento (ou cancelados) podem receber baixa.');
        }
        $note = trim((string) $this->request->input('note', ''));
        Orders::markPaid($id, 'admin', ['gateway' => $order['gateway'] === 'mercadopago' && $order['gateway_payment_id'] ? 'mercadopago' : 'manual']);
        if ($note !== '') {
            $this->appendNote($order, 'Baixa manual: ' . $note);
        }
        return $this->success('Pagamento confirmado. As vagas foram criadas e o cliente foi avisado por e-mail.', '/admin/pedidos/' . $id);
    }

    public function cancel(int $id): Response
    {
        $order = $this->findOr404(Order::find($id), 'Pedido não encontrado.');
        $reason = trim((string) $this->request->input('reason', '')) ?: 'Cancelado pela equipe';
        if (!Orders::cancel($id, $reason)) {
            throw ValidationException::with('order', 'Só pedidos aguardando pagamento podem ser cancelados.');
        }
        $this->appendNote($order, 'Cancelado: ' . $reason);
        return $this->success('Pedido cancelado.', '/admin/pedidos/' . $id);
    }

    /** Consulta o pagamento no Mercado Pago de novo (útil se um webhook se perdeu). */
    public function sync(int $id): Response
    {
        $order = $this->findOr404(Order::find($id), 'Pedido não encontrado.');
        $paymentId = trim((string) $this->request->input('payment_id', '')) ?: (string) $order['gateway_payment_id'];
        if (!Payments::isOnline() || $paymentId === '') {
            throw ValidationException::with('payment_id', 'Informe o número do pagamento no Mercado Pago (ID da operação).');
        }
        $updated = Payments::reconcile($paymentId, 'sincronizacao');
        if (!$updated || $updated['id'] !== $order['id']) {
            throw ValidationException::with('payment_id', 'Pagamento não encontrado no Mercado Pago ou pertence a outro pedido.');
        }
        return $this->success('Consulta feita. Situação atual: ' . Order::statusLabel($updated['status']) . '.', '/admin/pedidos/' . $id);
    }

    public function notes(int $id): Response
    {
        $order = $this->findOr404(Order::find($id), 'Pedido não encontrado.');
        Database::update('orders', ['admin_notes' => mb_substr(trim((string) $this->request->input('admin_notes', '')), 0, 5000) ?: null], ['id' => $id]);
        Activity::log('order.notes', 'order', $id);
        return $this->success('Observações salvas.', '/admin/pedidos/' . $order['id']);
    }

    private function appendNote(array $order, string $text): void
    {
        $line = '[' . date('d/m/Y H:i') . '] ' . $text;
        Database::update('orders', ['admin_notes' => trim(($order['admin_notes'] ? $order['admin_notes'] . "\n" : '') . $line)], ['id' => $order['id']]);
    }
}
