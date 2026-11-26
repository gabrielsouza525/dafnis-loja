<?php
declare(strict_types=1);

/**
 * Fatura mensal e Faturamento: a equipe autoriza o CNPJ de uma conta, o comprador faz o pedido faturado
 * (acesso na hora), o painel soma o mês por cliente, o demonstrativo sai na tela, para imprimir e na
 * planilha de medição (.xlsx), e a baixa da fatura passa os pedidos a pago. Também as proteções: sem autorização não há
 * fatura, a fatura só vale para o CNPJ autorizado e o cliente não abre o Faturamento.
 * Requer os dados de demonstração (db:fresh --demo) e o servidor rodando; acessa o banco do .env.
 *
 *   php tests/billing.php http://localhost:8000
 */

require __DIR__ . '/lib.php';
require dirname(__DIR__) . '/app/bootstrap.php';
restore_exception_handler();

use App\Core\Database;

$base = rtrim($argv[1] ?? 'http://localhost:8000', '/');
$failures = 0;
$checks = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures, $checks;
    $checks++;
    if (!$ok) {
        $failures++;
    }
    echo ($ok ? '  ok   ' : '  FAIL ') . $label . ($detail !== '' && !$ok ? "  → $detail" : '') . PHP_EOL;
}

/** Arquivos de um ZIP (o .xlsx), conferindo o CRC de cada um; sem a extensão zip do PHP. @return array<string, string> */
function unzip(string $zip): array
{
    $files = [];
    for ($pos = 0; substr($zip, $pos, 4) === "PK\x03\x04";) {
        $h = unpack('vversion/vflags/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnlen/vxlen', substr($zip, $pos + 4, 26));
        $name = substr($zip, $pos + 30, $h['nlen']);
        $data = substr($zip, $pos + 30 + $h['nlen'] + $h['xlen'], $h['csize']);
        $data = $h['method'] === 8 ? (string) gzinflate($data) : $data;
        if (crc32($data) !== $h['crc'] || strlen($data) !== $h['usize']) {
            return [];
        }
        $files[$name] = $data;
        $pos += 30 + $h['nlen'] + $h['xlen'] + $h['csize'];
    }
    return $files;
}

// Empresa dos dados de demonstração (database/seeders/DemoSeeder.php)
$cnpj = '11222333000181';
$month = date('Y-m');
$rhId = (int) Database::value("SELECT id FROM users WHERE email = 'rh@example.com'");
$nr10Id = (int) Database::value("SELECT id FROM courses WHERE slug = 'nr-10-seguranca-em-instalacoes-e-servicos-com-eletricidade-basico'");
Database::update('users', ['invoice_cnpj' => null], ['id' => $rhId]);
$orders = static fn (): int => (int) Database::value('SELECT COUNT(*) FROM orders');
$invoiceCnpj = static fn () => Database::value('SELECT invoice_cnpj FROM users WHERE id = :id', ['id' => $rhId]);
$form = [
    'buyer_type' => 'pj', 'company_name' => 'Metalúrgica Exemplo Ltda', 'company_document' => '11.222.333/0001-81',
    'buyer_name' => 'Carlos Lima', 'buyer_email' => 'rh@example.com', 'buyer_phone' => '(18) 90000-0003',
    'payment_method' => 'invoice', 'accept_terms' => '1',
];

echo "Dafnis — fatura mensal e faturamento em $base\n\nSem autorização\n";
$rh = new Client($base);
check('login do comprador da empresa', $rh->login('rh@example.com', 'dafnis123'));
$rh->request('GET', '/carrinho');
$rh->request('POST', '/carrinho/adicionar', ['course_id' => $nr10Id, 'qty' => 2]);
$page = $rh->request('GET', '/checkout')['body'];
check('o checkout não oferece a fatura mensal', str_contains($page, 'value="pix"') && !str_contains($page, 'value="invoice"'));
$before = $orders();
$r = $rh->request('POST', '/checkout', $form);
check('a fatura é recusada', $r['status'] === 302 && str_ends_with($r['location'], '/checkout') && $orders() === $before, $r['location']);
check('cliente não abre o Faturamento', $rh->request('GET', '/admin/faturamento')['status'] !== 200);

echo "\nAutorização pela equipe\n";
$admin = new Client($base);
check('login da equipe', $admin->login('admin@dafnis.test', 'dafnis123', DEMO_ADMIN_TOTP));
$admin->request('GET', '/admin/usuarios/' . $rhId);
$admin->request('POST', '/admin/usuarios/' . $rhId . '/fatura', ['invoice_enabled' => '1', 'invoice_cnpj' => '11.222.333/0001-00']);
check('CNPJ inválido não autoriza', $invoiceCnpj() === null);
$admin->request('GET', '/admin/usuarios/' . $rhId);
$admin->request('POST', '/admin/usuarios/' . $rhId . '/fatura', ['invoice_enabled' => '1', 'invoice_cnpj' => '11.222.333/0001-81']);
check('a equipe autoriza a fatura mensal no CNPJ', $invoiceCnpj() === $cnpj);
$userPage = $admin->request('GET', '/admin/usuarios/' . $rhId)['body'];
check('a página do usuário mostra a fatura autorizada', str_contains($userPage, 'Fatura mensal') && str_contains($userPage, 'Autorizada') && str_contains($userPage, '11.222.333/0001-81'));

echo "\nPedido faturado\n";
$page = $rh->request('GET', '/checkout')['body'];
check('o checkout oferece a fatura mensal com o CNPJ autorizado', str_contains($page, 'value="invoice"') && str_contains($page, 'data-invoice-cnpj="11.222.333/0001-81"'));
$before = $orders();
$r = $rh->request('POST', '/checkout', ['company_document' => '11.444.777/0001-61'] + $form);
check('a fatura só vale para o CNPJ autorizado', $r['status'] === 302 && str_ends_with($r['location'], '/checkout') && $orders() === $before, $r['location']);
check('o motivo aparece no checkout', str_contains($rh->request('GET', '/checkout')['body'], 'vale só para o CNPJ 11.222.333/0001-81'));
$r = $rh->request('POST', '/checkout', $form);
$number = preg_match('#/pedido/(DF\d{6})$#', $r['location'], $m) ? $m[1] : '';
$order = Database::first('SELECT * FROM orders WHERE number = :n', ['n' => $number]);
check('pedido faturado, sem pagamento na hora', $order && $order['status'] === 'invoiced' && $order['payment_method'] === 'invoice' && $order['gateway'] === 'manual' && $order['invoiced_at'] && !$order['paid_at'], $r['location']);
$orderId = (int) ($order['id'] ?? 0);
check('as vagas são criadas na hora', (int) Database::value('SELECT COUNT(*) FROM enrollments WHERE order_id = :o', ['o' => $orderId]) === 2);
$p = $rh->request('GET', '/pedido/' . $number)['body'];
check('a página do pedido mostra o pedido faturado e a indicação dos participantes', str_contains($p, 'Pedido faturado') && str_contains($p, 'fatura mensal') && str_contains($p, 'Indicar participantes'));
$seat = (int) Database::value('SELECT id FROM enrollments WHERE order_id = :o ORDER BY id LIMIT 1', ['o' => $orderId]);
$accountOrder = $rh->request('GET', '/minha-conta/pedidos/' . $number)['body'];
check('a vaga da fatura pede a função e o centro de custo', str_contains($accountOrder, 'name="job_title"') && str_contains($accountOrder, 'Função (cargo)')
    && str_contains($accountOrder, 'name="cost_center"') && str_contains($accountOrder, 'Centro de custo (departamento)'));
$participant = ['participant_name' => 'Joana Pereira', 'participant_email' => 'joana@example.com', 'participant_document' => '529.982.247-25'];
$rh->request('POST', '/minha-conta/vagas/' . $seat, $participant + ['_scope' => 'seat-' . $seat]);
$accountOrder = $rh->request('GET', '/minha-conta/pedidos/' . $number)['body'];
check('na fatura, a função e o centro de custo são obrigatórios', Database::value('SELECT participant_name FROM enrollments WHERE id = :id', ['id' => $seat]) === null
    && str_contains($accountOrder, 'Preencha o campo função') && str_contains($accountOrder, 'Preencha o campo centro de custo'));
$rh->request('POST', '/minha-conta/vagas/' . $seat, $participant + ['job_title' => 'Eletricista', 'cost_center' => 'Manutenção elétrica']);
$seatRow = Database::first('SELECT participant_name, job_title, cost_center, status FROM enrollments WHERE id = :id', ['id' => $seat]);
check('o comprador indica o participante, a função e o centro de custo', $seatRow['participant_name'] === 'Joana Pereira' && $seatRow['job_title'] === 'Eletricista' && $seatRow['cost_center'] === 'Manutenção elétrica');
// Vaga já liberada: o participante não muda mais, mas a função e o centro de custo ainda podem ser corrigidos.
Database::update('enrollments', ['status' => 'active'], ['id' => $seat]);
$rh->request('GET', '/minha-conta/pedidos/' . $number);
$rh->request('POST', '/minha-conta/vagas/' . $seat . '/empresa', ['job_title' => 'Eletricista de força e controle', 'cost_center' => '4102']);
$seatRow = Database::first('SELECT job_title, cost_center FROM enrollments WHERE id = :id', ['id' => $seat]);
check('a função e o centro de custo da vaga liberada podem ser corrigidos', $seatRow['job_title'] === 'Eletricista de força e controle' && $seatRow['cost_center'] === '4102');
$otherBuyer = new Client($base);
$otherBuyer->login('ana@example.com', 'dafnis123');
$otherBuyer->request('GET', '/minha-conta');
check('outra conta não muda a vaga', $otherBuyer->request('POST', '/minha-conta/vagas/' . $seat . '/empresa', ['job_title' => 'X', 'cost_center' => 'X'])['status'] === 404
    && Database::value('SELECT cost_center FROM enrollments WHERE id = :id', ['id' => $seat]) === '4102');
$adminSeat = $admin->request('GET', '/admin/matriculas/' . $seat)['body'];
check('a matrícula no painel mostra a função e o centro de custo', str_contains($adminSeat, 'value="4102"') && str_contains($adminSeat, 'value="Eletricista de força e controle"'));
Database::insert('certificates', ['enrollment_id' => $seat, 'code' => 'FATURATESTE1', 'issued_at' => date('Y-m-d')]);
$rh->request('GET', '/minha-conta/pedidos/' . $number);
$rh->request('POST', '/pedido/' . $number . '/cancelar');
check('o cliente não cancela o pedido faturado', Database::value('SELECT status FROM orders WHERE id = :id', ['id' => $orderId]) === 'invoiced');
$adminOrder = $admin->request('GET', '/admin/pedidos/' . $orderId)['body'];
check('o pedido no painel mostra a fatura mensal', str_contains($adminOrder, 'Faturado') && str_contains($adminOrder, 'Ver a fatura do mês'));

echo "\nFaturamento\n";
$expected = (float) Database::value(
    "SELECT SUM(total) FROM orders WHERE buyer_document = :d AND status IN ('invoiced', 'paid') AND created_at >= :s",
    ['d' => $cnpj, 's' => $month . '-01 00:00:00']
);
$bill = $admin->request('GET', '/admin/faturamento?mes=' . $month);
check('o Faturamento abre e soma o mês do cliente', $bill['status'] === 200 && str_contains($bill['body'], 'Metalúrgica Exemplo Ltda') && str_contains($bill['body'], money($expected)), money($expected));
check('as faturas em aberto mostram o pedido faturado', str_contains($bill['body'], 'Faturas em aberto') && str_contains($bill['body'], money($order['total'])));
check('mês inválido volta para o mês atual', str_contains($admin->request('GET', '/admin/faturamento?mes=2026-13')['body'], e(month_br($month, true))));
$q = '?doc=' . $cnpj . '&mes=' . $month;
$client = $admin->request('GET', '/admin/faturamento/cliente' . $q)['body'];
$today = date_br(date('Y-m-d'));
check('o demonstrativo lista o pedido, o aluno e o total', str_contains($client, $number) && str_contains($client, 'Joana Pereira') && preg_match('/\d+ vagas? ainda sem aluno/', $client) === 1 && str_contains($client, money($expected)));
check('o demonstrativo traz CPF, formação, certificação e centro de custo do aluno', str_contains($client, '529.982.247-25') && str_contains($client, 'Formação')
    && str_contains($client, $today) && str_contains($client, '4102') && str_contains($client, 'Aguardando o aluno'));
check('o demonstrativo soma por centro de custo', str_contains($client, 'Por centro de custo') && preg_match('#<td>4102</td><td class="num">1</td>#', $client) === 1);
$print = $admin->request('GET', '/admin/faturamento/cliente' . $q . '&imprimir=1');
check('o demonstrativo para imprimir sai sem o menu do painel', $print['status'] === 200 && str_contains($print['body'], 'Demonstrativo de treinamentos') && str_contains($print['body'], 'Joana Pereira') && !str_contains($print['body'], 'adm-side'));
check('o demonstrativo para imprimir tem os dados pedidos pela empresa', str_contains($print['body'], 'Razão social') && str_contains($print['body'], 'Metalúrgica Exemplo Ltda') && str_contains($print['body'], '11.222.333/0001-81')
    && str_contains($print['body'], '529.982.247-25') && str_contains($print['body'], 'Formação ou reciclagem') && str_contains($print['body'], 'Data da certificação')
    && str_contains($print['body'], $today) && str_contains($print['body'], '4102'));
check('o demonstrativo mostra a função do aluno', str_contains($client, 'Eletricista de força e controle') && str_contains($print['body'], 'Eletricista de força e controle'));

// Planilha de medição (.xlsx): as células de cada linha, já com os textos no lugar.
$sheet = static function (array $response): array {
    $files = unzip($response['body']);
    preg_match_all('#<si><t[^>]*>(.*?)</t></si>#s', $files['xl/sharedStrings.xml'] ?? '', $m);
    $strings = array_map(static fn ($s) => html_entity_decode($s, ENT_XML1 | ENT_QUOTES, 'UTF-8'), $m[1]);
    $rows = [];
    preg_match_all('#<row r="(\d+)">(.*?)</row>#s', $files['xl/worksheets/sheet1.xml'] ?? '', $rm, PREG_SET_ORDER);
    foreach ($rm as [, $r, $cells]) {
        preg_match_all('#<c r="([A-Z]+)\d+" s="(\d+)"( t="s")?(?:/>|>(?:<f>(.*?)</f>)?(?:<v>(.*?)</v>)?</c>)#s', $cells, $cm, PREG_SET_ORDER);
        foreach ($cm as $c) {
            $v = $c[5] ?? '';
            $rows[(int) $r][$c[1]] = ['v' => ($c[3] ?? '') !== '' ? $strings[(int) $v] : $v, 's' => (int) $c[2], 'f' => $c[4] ?? ''];
        }
    }
    return ['files' => $files, 'rows' => $rows];
};
$xlsx = $admin->request('GET', '/admin/faturamento/cliente' . $q . '&formato=xlsx');
$x = $sheet($xlsx);
$header = array_map(static fn ($c) => $c['v'], $x['rows'][1] ?? []);
check('a planilha de medição sai em Excel', str_contains((string) Client::header($xlsx, 'Content-Type'), 'spreadsheetml.sheet') && str_starts_with($xlsx['body'], "PK\x03\x04")
    && str_contains((string) Client::header($xlsx, 'Content-Disposition'), 'medicao-treinamentos-metalurgica-exemplo-ltda-' . substr($month, 5, 2) . substr($month, 0, 4) . '.xlsx')
    && isset($x['files']['xl/workbook.xml'], $x['files']['xl/styles.xml']));
check('a planilha tem as colunas da medição', array_values($header) === ['CNPJ', 'Razão Social', 'Nome', 'CPF', 'Função', 'Centro de Custo', 'Treinamento', 'Data Realizada', 'REC/FOR', 'Valor'], implode(' | ', $header));
$joana = current(array_filter($x['rows'], static fn ($r) => ($r['C']['v'] ?? '') === 'JOANA PEREIRA')) ?: [];
$serial = intdiv(gmmktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y')), 86400) + 25569;
check('a linha do aluno traz CNPJ, CPF, função, centro de custo, sigla, data, formação e valor',
    ($joana['A']['v'] ?? '') === '11.222.333/0001-81' && ($joana['B']['v'] ?? '') === 'Metalúrgica Exemplo Ltda' && ($joana['D']['v'] ?? '') === '529.982.247-25'
    && ($joana['E']['v'] ?? '') === 'ELETRICISTA DE FORÇA E CONTROLE' && ($joana['F']['v'] ?? '') === '4102' && ($joana['G']['v'] ?? '') === 'NR10'
    && ($joana['H']['v'] ?? '') === (string) $serial && ($joana['H']['s'] ?? 0) === 3 && ($joana['I']['v'] ?? '') === 'FORMAÇÃO'
    && (float) ($joana['J']['v'] ?? 0) === (float) Database::value('SELECT unit_price FROM order_items WHERE order_id = :o', ['o' => $orderId]), json_encode($joana, JSON_UNESCAPED_UNICODE));
$last = max(array_keys(array_filter($x['rows'], static fn ($r) => ($r['A']['v'] ?? '') === '11.222.333/0001-81')));
$total = $x['rows'][$last + 1]['J'] ?? [];
check('o total da planilha é uma soma com o valor do mês', ($x['rows'][$last + 1]['I']['v'] ?? '') === 'TOTAL' && $total && $total['f'] === 'SUM(J2:J' . $last . ')' && abs((float) $total['v'] - $expected) < 0.005, json_encode($total));
check('a vaga ainda sem aluno aparece na planilha', (bool) array_filter($x['rows'], static fn ($r) => ($r['C']['v'] ?? '') === 'ALUNO A INDICAR' && ($r['H']['v'] ?? '') === 'Aguardando o aluno'));
check('embaixo, valor e carga de cada treinamento', (bool) array_filter($x['rows'], static fn ($r) => ($r['A']['v'] ?? '') === 'Treinamento' && ($r['B']['v'] ?? '') === 'Formação' && ($r['E']['v'] ?? '') === 'Reciclagem')
    && (bool) array_filter($x['rows'], static fn ($r) => ($r['A']['v'] ?? '') === 'NR10' && ($r['B']['v'] ?? '') !== '' && (int) ($r['C']['v'] ?? 0) === 40));
Database::update('courses', ['billing_code' => 'NR10BAS'], ['id' => $nr10Id]);
$x = $sheet($admin->request('GET', '/admin/faturamento/cliente' . $q . '&formato=xlsx'));
check('a sigla definida no curso vai na coluna Treinamento', (bool) array_filter($x['rows'], static fn ($r) => ($r['C']['v'] ?? '') === 'JOANA PEREIRA' && ($r['G']['v'] ?? '') === 'NR10BAS'));
Database::update('courses', ['billing_code' => null], ['id' => $nr10Id]);
$courseForm = $admin->request('GET', '/admin/cursos/' . $nr10Id . '/editar')['body'];
check('o curso no painel tem a sigla da planilha de medição', str_contains($courseForm, 'name="billing_code"') && str_contains($courseForm, 'Sigla na planilha de medição'));
check('cliente sem pedidos no mês volta ao Faturamento', $admin->request('GET', '/admin/faturamento/cliente?doc=' . $cnpj . '&mes=2020-01')['status'] === 302);
check('documento inválido não abre demonstrativo', $admin->request('GET', '/admin/faturamento/cliente?doc=123&mes=' . $month)['status'] === 404);

echo "\nBaixa e cancelamento\n";
$admin->request('GET', '/admin/faturamento/cliente' . $q);
$r = $admin->request('POST', '/admin/faturamento/baixa', ['doc' => $cnpj, 'mes' => $month, 'note' => 'transferência recebida']);
$after = Database::first('SELECT * FROM orders WHERE id = :id', ['id' => $orderId]);
check('a baixa da fatura passa o pedido a pago', $after['status'] === 'paid' && $after['paid_at'] && str_contains((string) $after['admin_notes'], 'paga: transferência recebida'), $r['location']);
check('as vagas continuam valendo depois da baixa', (int) Database::value("SELECT COUNT(*) FROM enrollments WHERE order_id = :o AND status <> 'cancelled'", ['o' => $orderId]) === 2);
check('sem fatura em aberto, nada é baixado de novo', $admin->request('POST', '/admin/faturamento/baixa', ['doc' => $cnpj, 'mes' => $month])['status'] === 302
    && Database::value('SELECT paid_at FROM orders WHERE id = :id', ['id' => $orderId]) === $after['paid_at']);
$rh->request('POST', '/carrinho/adicionar', ['course_id' => $nr10Id, 'qty' => 1]);
$rh->request('GET', '/checkout');
$r = $rh->request('POST', '/checkout', $form);
$second = Database::first('SELECT * FROM orders WHERE number = :n', ['n' => preg_match('#/pedido/(DF\d{6})$#', $r['location'], $m) ? $m[1] : '']);
$admin->request('GET', '/admin/pedidos/' . $second['id']);
$admin->request('POST', '/admin/pedidos/' . $second['id'] . '/cancelar', ['reason' => 'pedido em duplicidade']);
check('a equipe cancela um pedido faturado e as vagas dele', Database::value('SELECT status FROM orders WHERE id = :id', ['id' => $second['id']]) === 'cancelled'
    && (int) Database::value("SELECT COUNT(*) FROM enrollments WHERE order_id = :o AND status <> 'cancelled'", ['o' => $second['id']]) === 0);
$admin->request('GET', '/admin/usuarios/' . $rhId);
$admin->request('POST', '/admin/usuarios/' . $rhId . '/fatura', ['invoice_enabled' => '0', 'invoice_cnpj' => '']);
check('a equipe desativa a fatura mensal', $invoiceCnpj() === null);
$rh->request('POST', '/carrinho/adicionar', ['course_id' => $nr10Id, 'qty' => 1]);
check('desativada, o checkout não oferece mais a fatura', !str_contains($rh->request('GET', '/checkout')['body'], 'value="invoice"'));

echo "\n" . ($failures ? "$failures de $checks verificações falharam." : "Todas as $checks verificações passaram.") . PHP_EOL;
exit($failures ? 1 : 0);
