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
     * Pedidos pagos e faturados do cliente no mês e uma linha por vaga (aluno), com o que a empresa
     * pede na fatura: nome e CPF do aluno, curso, formação ou reciclagem, data da certificação e
     * centro de custo. Também o total por centro de custo.
     * @return array{client: array, orders: array, rows: array, centers: array, discounts: float, total: float, open: float, paid: float, seats: int}
     */
    public static function statement(string $doc, string $month): array
    {
        [$start, $end] = self::range($month);
        $orders = Database::select(
            "SELECT * FROM orders WHERE status IN ('invoiced', 'paid') AND buyer_document = :d AND created_at >= :s AND created_at < :e ORDER BY created_at, id",
            ['d' => $doc, 's' => $start, 'e' => $end]
        );
        $people = [];
        $types = [];
        if ($orders) {
            $ids = implode(',', array_map(static fn ($o) => (int) $o['id'], $orders));
            foreach (Database::select(
                "SELECT e.id, e.order_item_id, e.participant_name, e.participant_document, e.cost_center, e.status, c.issued_at AS certified_at
                   FROM enrollments e LEFT JOIN certificates c ON c.enrollment_id = e.id
                  WHERE e.order_id IN ($ids) ORDER BY e.id"
            ) as $e) {
                $people[(int) $e['order_item_id']][] = $e;
            }
            foreach (Database::select("SELECT DISTINCT c.id, c.training_type FROM courses c JOIN order_items i ON i.course_id = c.id WHERE i.order_id IN ($ids)") as $c) {
                $types[(int) $c['id']] = $c['training_type'];
            }
        }
        $seats = 0;
        $rows = [];
        $discounts = 0.0;
        foreach ($orders as &$o) {
            $o['items'] = Order::items((int) $o['id']);
            $lines = 0.0;
            $o['seats'] = 0;
            foreach ($o['items'] as $item) {
                $lines += (float) $item['line_total'];
                $o['seats'] += (int) $item['quantity'];
                $list = $people[(int) $item['id']] ?? [];
                $type = $types[(int) $item['course_id']] ?? null;
                // Curso apagado do catálogo: o título diz se era reciclagem.
                $recycling = $type !== null ? $type === 'periodico' : str_contains(mb_strtolower($item['course_title']), 'reciclagem');
                for ($i = 0; $i < (int) $item['quantity']; $i++) {
                    $p = $list[$i] ?? null;
                    $rows[] = [
                        'enrollment_id' => $p ? (int) $p['id'] : null,
                        'name' => $p ? (string) $p['participant_name'] : '',
                        'cpf' => $p ? (string) $p['participant_document'] : '',
                        'cost_center' => $p ? trim((string) $p['cost_center']) : '',
                        'status' => $p ? $p['status'] : 'awaiting_participant',
                        'certified_at' => $p ? $p['certified_at'] : null,
                        'course' => ($item['course_code'] ? $item['course_code'] . ' — ' : '') . $item['course_title'],
                        'hours' => (int) $item['course_hours'],
                        'kind' => $recycling ? 'Reciclagem' : 'Formação',
                        'value' => (float) $item['unit_price'],
                        'order' => $o['number'],
                        'order_id' => (int) $o['id'],
                        'order_date' => $o['created_at'],
                        'order_status' => $o['status'],
                    ];
                }
            }
            $seats += $o['seats'];
            // Desconto do pedido (cupom) além dos preços dos itens.
            $o['order_discount'] = max(0.0, round($lines - (float) $o['total'], 2));
            $discounts += $o['order_discount'];
        }
        unset($o);
        $centers = [];
        foreach ($rows as $r) {
            // Prefixo na chave: centro de custo só com números ("4102") não vira índice numérico.
            $key = 'cc:' . $r['cost_center'];
            $centers[$key] ??= ['name' => $r['cost_center'], 'people' => 0, 'value' => 0.0];
            $centers[$key]['people']++;
            $centers[$key]['value'] += $r['value'];
        }
        // Em ordem alfabética, com os não informados no fim.
        usort($centers, static fn ($a, $b) => ($a['name'] === '') <=> ($b['name'] === '') ?: strnatcasecmp($a['name'], $b['name']));
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
            'rows' => $rows,
            'centers' => $centers,
            'discounts' => round($discounts, 2),
            'total' => array_sum(array_map(static fn ($o) => (float) $o['total'], $orders)),
            'open' => array_sum(array_map(static fn ($o) => $o['status'] === 'invoiced' ? (float) $o['total'] : 0.0, $orders)),
            'paid' => array_sum(array_map(static fn ($o) => $o['status'] === 'paid' ? (float) $o['total'] : 0.0, $orders)),
            'seats' => $seats,
        ];
    }

    /** O que vai na coluna "Data da certificação": a data, ou em que pé está a vaga. */
    public static function certification(array $row): string
    {
        return match (true) {
            !empty($row['certified_at']) => date_br($row['certified_at']),
            $row['status'] === 'cancelled' => 'Vaga cancelada',
            $row['status'] === 'awaiting_participant' => 'Aguardando o aluno',
            default => 'Em andamento',
        };
    }

    /** Planilha para o Excel em português: separador ";", vírgula decimal e BOM para os acentos. */
    private static function csv(array $data): string
    {
        $client = $data['client'];
        $rows = [[$client['pj'] ? 'CNPJ' : 'CPF do cliente', $client['pj'] ? 'Razão social' : 'Cliente', 'Aluno', 'CPF do aluno', 'Curso',
            'Formação ou reciclagem', 'Data da certificação', 'Centro de custo', 'Carga horária (h)', 'Pedido', 'Data do pedido', 'Situação', 'Valor (R$)']];
        $head = [document_display($client['doc']), $client['name']];
        foreach ($data['rows'] as $r) {
            $rows[] = array_merge($head, [
                $r['name'] !== '' ? $r['name'] : 'Aluno a indicar',
                $r['cpf'] !== '' ? document_display($r['cpf']) : '',
                $r['course'],
                $r['kind'],
                self::certification($r),
                $r['cost_center'],
                (string) $r['hours'],
                $r['order'],
                date_br($r['order_date']),
                Order::statusLabel($r['order_status']),
                money($r['value'], false),
            ]);
        }
        foreach ($data['orders'] as $o) {
            if ($o['order_discount'] > 0) {
                $rows[] = array_merge($head, ['Desconto' . ($o['coupon_code'] ? ' (cupom ' . $o['coupon_code'] . ')' : ''), '', '', '', '', '', '', $o['number'], date_br($o['created_at']), Order::statusLabel($o['status']), '-' . money($o['order_discount'], false)]);
            }
        }
        $rows[] = ['', '', '', '', '', '', '', '', '', '', '', 'Total', money($data['total'], false)];
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
