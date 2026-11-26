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
use App\Services\Xlsx;

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

    /** Demonstrativo de um cliente no mês: na tela, para imprimir (?imprimir=1) ou na planilha de medição (?formato=xlsx). */
    public function client(): Response
    {
        $month = $this->month();
        $doc = $this->doc();
        $data = self::statement($doc, $month);
        if (!$data['orders']) {
            flash('info', 'Nenhum pedido pago ou faturado deste cliente em ' . month_br($month) . '.');
            return $this->redirect('/admin/faturamento?mes=' . $month);
        }
        if ($this->request->query('formato') === 'xlsx') {
            $name = 'medicao-treinamentos-' . (slugify($data['client']['name']) ?: $doc) . '-' . substr($month, 5, 2) . substr($month, 0, 4) . '.xlsx';
            return Response::download(self::measurement($data, $month), $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
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
     * pede na fatura: nome, CPF e função do aluno, curso (e a sigla dele na medição), formação ou
     * reciclagem, data da certificação e centro de custo. Também o total por centro de custo.
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
                "SELECT e.id, e.order_item_id, e.participant_name, e.participant_document, e.job_title, e.cost_center, e.status, c.issued_at AS certified_at
                   FROM enrollments e LEFT JOIN certificates c ON c.enrollment_id = e.id
                  WHERE e.order_id IN ($ids) ORDER BY e.id"
            ) as $e) {
                $people[(int) $e['order_item_id']][] = $e;
            }
            foreach (Database::select("SELECT DISTINCT c.id, c.training_type, c.billing_code FROM courses c JOIN order_items i ON i.course_id = c.id WHERE i.order_id IN ($ids)") as $c) {
                $types[(int) $c['id']] = $c;
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
                $course = $types[(int) $item['course_id']] ?? null;
                // Curso apagado do catálogo: o título diz se era reciclagem.
                $recycling = $course !== null ? $course['training_type'] === 'periodico' : str_contains(mb_strtolower($item['course_title']), 'reciclagem');
                $code = self::trainingCode($course['billing_code'] ?? null, $item['course_code'], $item['course_title']);
                for ($i = 0; $i < (int) $item['quantity']; $i++) {
                    $p = $list[$i] ?? null;
                    $rows[] = [
                        'enrollment_id' => $p ? (int) $p['id'] : null,
                        'name' => $p ? (string) $p['participant_name'] : '',
                        'cpf' => $p ? (string) $p['participant_document'] : '',
                        'job_title' => $p ? trim((string) $p['job_title']) : '',
                        'cost_center' => $p ? trim((string) $p['cost_center']) : '',
                        'status' => $p ? $p['status'] : 'awaiting_participant',
                        'certified_at' => $p ? $p['certified_at'] : null,
                        'code' => $code,
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

    /**
     * Sigla do treinamento na planilha de medição: a definida no curso (ex.: NR10SEP) ou o código
     * da NR sem espaço e com dois dígitos ("NR 6" → NR06, "NR 31.7" → NR31.7). Curso sem NR sai pelo nome.
     */
    public static function trainingCode(?string $billingCode, ?string $courseCode, string $title): string
    {
        $billingCode = trim((string) $billingCode);
        if ($billingCode !== '') {
            return $billingCode;
        }
        $code = (string) preg_replace('/\s+/', '', (string) $courseCode);
        return $code !== '' ? (string) preg_replace('/^NR(\d)(?!\d)/i', 'NR0$1', $code) : $title;
    }

    /**
     * Planilha de medição no formato que a empresa recebe com a nota: uma linha por treinamento de
     * cada aluno (agrupada por aluno), o total com fórmula e, embaixo, o valor e a carga de cada
     * treinamento em formação e em reciclagem.
     */
    private static function measurement(array $data, string $month): string
    {
        $client = $data['client'];
        $T = Xlsx::TEXT;
        $head = [$client['pj'] ? 'CNPJ' : 'CPF do cliente', $client['pj'] ? 'Razão Social' : 'Cliente', 'Nome', 'CPF', 'Função',
            'Centro de Custo', 'Treinamento', 'Data Realizada', 'REC/FOR', 'Valor'];
        $doc = document_display($client['doc']);
        $people = $data['rows'];
        // Agrupada por aluno, como a medição da empresa; vagas ainda sem aluno no fim.
        usort($people, static fn ($a, $b) => ($a['name'] === '') <=> ($b['name'] === '')
            ?: strcmp(normalize_text($a['name']), normalize_text($b['name']))
            ?: strnatcasecmp($a['code'], $b['code'])
            ?: ($a['enrollment_id'] ?? PHP_INT_MAX) <=> ($b['enrollment_id'] ?? PHP_INT_MAX));
        $sheet = [array_map(static fn ($h) => ['v' => $h, 's' => Xlsx::HEAD], $head)];
        foreach ($people as $r) {
            $sheet[] = [
                ['v' => $doc, 's' => $T],
                ['v' => $client['name'], 's' => $T],
                ['v' => $r['name'] !== '' ? mb_strtoupper($r['name']) : 'ALUNO A INDICAR', 's' => $T],
                ['v' => $r['cpf'] !== '' ? document_display($r['cpf']) : '', 's' => $T],
                ['v' => mb_strtoupper($r['job_title']), 's' => $T],
                ['v' => $r['cost_center'], 's' => $T],
                ['v' => $r['code'], 's' => $T],
                ['v' => !empty($r['certified_at']) ? (string) $r['certified_at'] : self::certification($r), 's' => Xlsx::DATE],
                ['v' => mb_strtoupper($r['kind']), 's' => $T],
                ['v' => round((float) $r['value'], 2), 's' => Xlsx::MONEY],
            ];
        }
        foreach ($data['orders'] as $o) {
            if ($o['order_discount'] > 0) {
                $label = 'Desconto' . ($o['coupon_code'] ? ' (cupom ' . $o['coupon_code'] . ')' : '') . ' · pedido ' . $o['number'];
                $sheet[] = [['v' => $doc, 's' => $T], ['v' => $client['name'], 's' => $T], ['v' => '', 's' => $T], ['v' => '', 's' => $T], ['v' => '', 's' => $T],
                    ['v' => '', 's' => $T], ['v' => $label, 's' => $T], ['v' => '', 's' => $T], ['v' => '', 's' => $T], ['v' => -round((float) $o['order_discount'], 2), 's' => Xlsx::MONEY]];
            }
        }
        $last = count($sheet);
        $money = Xlsx::col(count($head) - 1);
        $sheet[] = array_merge(array_fill(0, count($head) - 2, null), [
            ['v' => 'TOTAL', 's' => Xlsx::LABEL],
            ['v' => round((float) $data['total'], 2), 'f' => 'SUM(' . $money . '2:' . $money . $last . ')', 's' => Xlsx::TOTAL],
        ]);

        // Valor e carga de cada treinamento: formação e reciclagem lado a lado, pela sigla.
        $prices = [];
        foreach ($data['rows'] as $r) {
            $side = $r['kind'] === 'Reciclagem' ? 'r' : 'f';
            $combo = [round((float) $r['value'], 2), (int) $r['hours']];
            $prices[$r['code']] ??= ['f' => [], 'r' => []];
            if (!in_array($combo, $prices[$r['code']][$side], true)) {
                $prices[$r['code']][$side][] = $combo;
            }
        }
        uksort($prices, static fn ($a, $b) => strnatcasecmp((string) $a, (string) $b));
        array_push($sheet, [], [], [], []);
        $sheet[] = array_map(static fn ($h) => ['v' => $h, 's' => Xlsx::HEAD], ['Treinamento', 'Formação', 'Carga (h)', 'Carga (h)', 'Reciclagem']);
        foreach ($prices as $code => $p) {
            for ($i = 0, $n = max(count($p['f']), count($p['r'])); $i < $n; $i++) {
                $f = $p['f'][$i] ?? null;
                $c = $p['r'][$i] ?? null;
                $sheet[] = [
                    ['v' => (string) $code, 's' => $T],
                    ['v' => $f ? $f[0] : '', 's' => Xlsx::MONEY],
                    ['v' => $f ? $f[1] : '', 's' => Xlsx::INT],
                    ['v' => $c ? $c[1] : '', 's' => Xlsx::INT],
                    ['v' => $c ? $c[0] : '', 's' => Xlsx::MONEY],
                ];
            }
        }

        // Largura das colunas pelo conteúdo, dentro de um limite.
        $widths = [];
        foreach ($head as $i => $h) {
            $len = mb_strlen($h);
            for ($r = 1; $r < $last; $r++) {
                $v = $sheet[$r][$i]['v'] ?? '';
                $len = max($len, is_string($v) ? mb_strlen($v) : 12);
            }
            $widths[] = min(48, max([10, 19, 12, 16, 12, 16, 12, 14, 13, 15][$i], $len * 1.1 + 3));
        }
        // A tabela de baixo começa na coluna A: o nome do treinamento precisa caber nela.
        foreach (array_keys($prices) as $code) {
            $widths[0] = min(48, max($widths[0], mb_strlen((string) $code) * 1.1 + 3));
        }
        $title = 'Medição de treinamentos — ' . $client['name'] . ' — ' . month_br($month);
        return (new Xlsx($title))
            ->sheet('Medição ' . substr($month, 5, 2) . '-' . substr($month, 0, 4), $sheet, [
                'widths' => $widths,
                'filter' => 'A1:' . $money . $last,
                'freeze' => true,
            ])
            ->build();
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
