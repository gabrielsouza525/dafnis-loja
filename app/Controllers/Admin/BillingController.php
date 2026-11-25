<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\View;
use App\Models\Order;
use App\Services\Activity;
use App\Services\Orders;
use App\Services\Settings;

/**
 * Faturamento: quanto cada cliente gastou no mês (pedidos pagos e faturados, pela data do pedido),
 * o demonstrativo de cada cliente para enviar à empresa (tela, impressão e planilha) e a baixa
 * da fatura mensal quando o pagamento chega.
 */
final class BillingController extends AdminController
{
    public function index(): Response
    {
        $month = $this->month();
        [$start, $end] = self::range($month);
        $clients = Database::select(
            "SELECT o.buyer_document AS doc,
                    SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(NULLIF(o.company_name, ''), o.buyer_name) ORDER BY o.id DESC SEPARATOR '\n'), '\n', 1) AS name,
                    COUNT(*) AS orders,
                    COALESCE(SUM(it.qty), 0) AS seats,
                    SUM(o.total) AS total,
                    SUM(CASE WHEN o.status = 'invoiced' THEN o.total ELSE 0 END) AS open_total,
                    SUM(CASE WHEN o.status = 'paid' THEN o.total ELSE 0 END) AS paid_total,
                    SUM(o.payment_method = 'invoice') AS invoice_orders
               FROM orders o
               LEFT JOIN (SELECT order_id, SUM(quantity) AS qty FROM order_items GROUP BY order_id) it ON it.order_id = o.id
              WHERE o.status IN ('invoiced', 'paid') AND o.created_at >= :s AND o.created_at < :e
              GROUP BY o.buyer_document
              ORDER BY total DESC, name",
            ['s' => $start, 'e' => $end]
        );
        // Faturas em aberto de qualquer mês: o que ainda falta cobrar.
        $open = Database::select(
            "SELECT o.buyer_document AS doc, DATE_FORMAT(o.created_at, '%Y-%m') AS ym,
                    SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(NULLIF(o.company_name, ''), o.buyer_name) ORDER BY o.id DESC SEPARATOR '\n'), '\n', 1) AS name,
                    COUNT(*) AS orders, SUM(o.total) AS total
               FROM orders o
              WHERE o.status = 'invoiced'
              GROUP BY o.buyer_document, ym
              ORDER BY ym, total DESC"
        );
        $months = array_column(Database::select(
            "SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') AS ym FROM orders WHERE status IN ('invoiced', 'paid') ORDER BY ym DESC LIMIT 36"
        ), 'ym');
        if (!in_array(date('Y-m'), $months, true)) {
            array_unshift($months, date('Y-m'));
        }
        if (!in_array($month, $months, true)) {
            $months[] = $month;
            rsort($months);
        }
        return $this->view('admin/billing/index', [
            'title' => 'Faturamento',
            'section' => 'faturamento',
            'month' => $month,
            'months' => $months,
            'clients' => $clients,
            'open' => $open,
            'sum' => [
                'total' => array_sum(array_column($clients, 'total')),
                'open' => array_sum(array_column($clients, 'open_total')),
                'paid' => array_sum(array_column($clients, 'paid_total')),
                'seats' => array_sum(array_column($clients, 'seats')),
                'orders' => array_sum(array_column($clients, 'orders')),
            ],
        ]);
    }

    /** Demonstrativo de um cliente no mês: na tela, para imprimir (?imprimir=1) ou em planilha (?formato=csv). */
    public function client(): Response
    {
        $month = $this->month();
        $doc = $this->doc();
        $data = self::statement($doc, $month);
        if (!$data['orders']) {
            flash('info', 'Nenhum pedido pago ou faturado deste cliente em ' . month_br($month) . '.');
            return $this->redirect('/admin/faturamento?mes=' . $month);
        }
        if ($this->request->query('formato') === 'csv') {
            return Response::download(self::csv($data), 'demonstrativo-' . $doc . '-' . $month . '.csv', 'text/csv; charset=UTF-8');
        }
        $data += [
            'title' => 'Faturamento — ' . $data['client']['name'],
            'section' => 'faturamento',
            'month' => $month,
            'doc' => $doc,
            'otherOpen' => Database::select(
                "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS orders, SUM(total) AS total
                   FROM orders WHERE status = 'invoiced' AND buyer_document = :d
                  GROUP BY ym HAVING ym <> :m ORDER BY ym",
                ['d' => $doc, 'm' => $month]
            ),
        ];
        if ($this->request->query('imprimir') === '1') {
            $data['business'] = [
                'name' => Settings::businessName(),
                'cnpj' => (string) Settings::get('business.cnpj', ''),
                'email' => (string) Settings::get('business.email', ''),
                'phone' => (string) Settings::get('business.phone', ''),
                'city' => Settings::cityLine(),
            ];
            return Response::html(View::render('admin/billing/print', $data));
        }
        return $this->view('admin/billing/client', $data);
    }

    /** Fatura do mês paga: todos os pedidos faturados do cliente naquele mês passam a "Pago". */
    public function settle(): Response
    {
        $month = $this->month('input');
        $doc = $this->doc('input');
        $note = mb_substr(trim((string) $this->request->input('note', '')), 0, 300);
        [$start, $end] = self::range($month);
        $ids = array_map('intval', array_column(Database::select(
            "SELECT id FROM orders WHERE status = 'invoiced' AND buyer_document = :d AND created_at >= :s AND created_at < :e ORDER BY id",
            ['d' => $doc, 's' => $start, 'e' => $end]
        ), 'id'));
        if (!$ids) {
            throw ValidationException::with('settle', 'Não há pedidos faturados em aberto deste cliente em ' . month_br($month) . '.');
        }
        $total = 0.0;
        $count = 0;
        foreach ($ids as $id) {
            if (Orders::markPaid($id, 'invoice')) {
                $order = Order::find($id);
                $total += (float) $order['total'];
                $count++;
                $line = '[' . date('d/m/Y H:i') . '] Fatura de ' . month_br($month) . ' paga' . ($note !== '' ? ': ' . $note : '');
                Database::update('orders', ['admin_notes' => trim(($order['admin_notes'] ? $order['admin_notes'] . "\n" : '') . $line)], ['id' => $id]);
            }
        }
        Activity::log('billing.settled', 'billing', null, document_display($doc) . ' · ' . $month . ' · ' . pluralize($count, 'pedido', 'pedidos') . ' · ' . money($total));
        return $this->success(
            'Fatura de ' . month_br($month) . ' paga: ' . pluralize($count, 'pedido', 'pedidos') . ', ' . money($total) . '.',
            '/admin/faturamento/cliente?' . http_build_query(['doc' => $doc, 'mes' => $month])
        );
    }

    /**
     * Pedidos pagos e faturados do cliente no mês, com os itens e os participantes de cada vaga.
     * @return array{client: array, orders: array, total: float, open: float, paid: float, seats: int}
     */
    public static function statement(string $doc, string $month): array
    {
        [$start, $end] = self::range($month);
        $orders = Database::select(
            "SELECT * FROM orders WHERE status IN ('invoiced', 'paid') AND buyer_document = :d AND created_at >= :s AND created_at < :e ORDER BY created_at, id",
            ['d' => $doc, 's' => $start, 'e' => $end]
        );
        $people = [];
        if ($orders) {
            $ids = implode(',', array_map(static fn ($o) => (int) $o['id'], $orders));
            foreach (Database::select("SELECT order_item_id, participant_name, status FROM enrollments WHERE order_id IN ($ids) ORDER BY id") as $e) {
                $people[(int) $e['order_item_id']][] = $e;
            }
        }
        $seats = 0;
        foreach ($orders as &$o) {
            $o['items'] = Order::items((int) $o['id']);
            $lines = 0.0;
            foreach ($o['items'] as &$item) {
                $item['people'] = $people[(int) $item['id']] ?? [];
                $lines += (float) $item['line_total'];
                $seats += (int) $item['quantity'];
            }
            unset($item);
            // Desconto do pedido (cupom) além dos preços dos itens.
            $o['order_discount'] = max(0.0, round($lines - (float) $o['total'], 2));
        }
        unset($o);
        $last = $orders ? end($orders) : null;
        return [
            'client' => [
                'doc' => $doc,
                'name' => $last ? ($last['company_name'] ?: $last['buyer_name']) : '',
                'pj' => strlen($doc) === 14,
                'contact' => $last ? $last['buyer_name'] : '',
                'email' => $last ? $last['buyer_email'] : '',
            ],
            'orders' => $orders,
            'total' => array_sum(array_map(static fn ($o) => (float) $o['total'], $orders)),
            'open' => array_sum(array_map(static fn ($o) => $o['status'] === 'invoiced' ? (float) $o['total'] : 0.0, $orders)),
            'paid' => array_sum(array_map(static fn ($o) => $o['status'] === 'paid' ? (float) $o['total'] : 0.0, $orders)),
            'seats' => $seats,
        ];
    }

    /** Planilha para o Excel em português: separador ";", vírgula decimal e BOM para os acentos. */
    private static function csv(array $data): string
    {
        $rows = [['Data', 'Pedido', 'Situação', 'Treinamento', 'Carga horária (h)', 'Participante', 'Valor (R$)']];
        foreach ($data['orders'] as $o) {
            $status = Order::statusLabel($o['status']);
            foreach ($o['items'] as $item) {
                $title = ($item['course_code'] ? $item['course_code'] . ' — ' : '') . $item['course_title'];
                $people = $item['people'] ?: [];
                for ($i = 0; $i < (int) $item['quantity']; $i++) {
                    $person = $people[$i] ?? null;
                    $name = $person ? ((string) $person['participant_name'] ?: 'Vaga sem participante indicado') : 'Vaga sem participante indicado';
                    if ($person && $person['status'] === 'cancelled') {
                        $name .= ' (vaga cancelada)';
                    }
                    $rows[] = [date_br($o['created_at']), $o['number'], $status, $title, (string) (int) $item['course_hours'], $name, money($item['unit_price'], false)];
                }
            }
            if ($o['order_discount'] > 0) {
                $rows[] = [date_br($o['created_at']), $o['number'], $status, 'Desconto' . ($o['coupon_code'] ? ' (cupom ' . $o['coupon_code'] . ')' : ''), '', '', '-' . money($o['order_discount'], false)];
            }
        }
        $rows[] = ['', '', '', '', '', 'Total', money($data['total'], false)];
        $out = "\xEF\xBB\xBF";
        foreach ($rows as $row) {
            $out .= implode(';', array_map(static function ($v) {
                $v = (string) $v;
                // Evita que o Excel interprete o texto como fórmula.
                if ($v !== '' && str_contains('=+-@', $v[0]) && !preg_match('/^-\d/', $v)) {
                    $v = "'" . $v;
                }
                return '"' . str_replace('"', '""', $v) . '"';
            }, $row)) . "\r\n";
        }
        return $out;
    }

    /** Mês pedido (AAAA-MM), ou o atual. */
    private function month(string $from = 'query'): string
    {
        $m = (string) ($from === 'input' ? $this->request->input('mes', '') : $this->request->query('mes', ''));
        return preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/', $m) ? $m : date('Y-m');
    }

    /** CPF ou CNPJ do cliente, só com dígitos. */
    private function doc(string $from = 'query'): string
    {
        $doc = preg_replace('/\D/', '', (string) ($from === 'input' ? $this->request->input('doc', '') : $this->request->query('doc', '')));
        if (!in_array(strlen($doc), [11, 14], true)) {
            $this->notFound('Cliente não encontrado.');
        }
        return $doc;
    }

    /** @return array{0: string, 1: string} início do mês e início do mês seguinte */
    private static function range(string $month): array
    {
        $start = $month . '-01 00:00:00';
        return [$start, date('Y-m-01 00:00:00', strtotime($start . ' +1 month'))];
    }
}
