<?php
declare(strict_types=1);

/**
 * Fluxo de ponta a ponta, como um cliente de verdade:
 * busca → filtros → curso → carrinho → checkout → cadastro → pedido → baixa no admin
 * → participantes → liberação → certificado → Minha conta. Inclui as proteções (CSRF, IDOR).
 * Requer o banco com os dados de demonstração (db:fresh --demo) e o servidor rodando.
 *
 *   php tests/flow.php http://localhost:8000
 */

require __DIR__ . '/lib.php';

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

function json(array $r): array
{
    return json_decode($r['body'], true) ?: [];
}

function countResults(string $html): int
{
    return preg_match('#<strong>(\d+)</strong> cursos? encontrados?#u', $html, $m) ? (int) $m[1] : -1;
}

$ajax = ['Accept: application/json', 'X-Requested-With: XMLHttpRequest'];
$fetch = ['Accept: application/json', 'X-Requested-With: fetch'];

echo "Dafnis — fluxo completo em $base\n\n";

// 1. Busca e filtros ------------------------------------------------------
echo "Busca e filtros\n";
$guest = new Client($base);
$home = $guest->request('GET', '/')['body'];
check('home mostra o total real do catálogo', str_contains($home, '119 treinamentos no catálogo'));
check('home não mostra placeholders do protótipo', !str_contains($home, '[000]') && !str_contains($home, 'DEMO10'));
foreach (['NR 10' => 'Eletricidade', 'nr10' => 'Eletricidade', 'eletricidade' => 'Eletricidade', 'espaço confinado' => 'Espaços Confinados', 'EPI' => 'EPI e EPC', 'brigada' => 'Brigada de Incêndio', 'empilhadeira' => 'Empilhadeiras'] as $q => $expect) {
    $html = $guest->request('GET', '/cursos?q=' . rawurlencode($q))['body'];
    check("busca \"$q\" encontra $expect", countResults($html) > 0 && str_contains($html, $expect), 'resultados: ' . countResults($html));
}
$html = $guest->request('GET', '/cursos?q=nr+10')['body'];
check('busca "nr 10" traz só os 5 cursos da NR 10', countResults($html) === 5 && substr_count($html, 'class="nr-tag">NR 10<') === 5, (string) countResults($html));
$html = $guest->request('GET', '/cursos?q=xyzcursoquenaoexiste')['body'];
check('busca sem resultado mostra estado vazio', countResults($html) === 0 && str_contains($html, 'Nenhum treinamento encontrado'));
$html = $guest->request('GET', '/cursos?nr=10&modalidade=semipresencial&carga=17-a-40h')['body'];
// Básico (40 h), Reciclagem (20 h) e os dois SEP (40 h): todos com prática obrigatória.
check('filtros combinados NR 10 + semipresencial + 17 a 40 h = 4', countResults($html) === 4, (string) countResults($html));
$html = $guest->request('GET', '/cursos?nr=10&modalidade=online')['body'];
check('NR 10 + online = só o simulador', countResults($html) === 1 && str_contains($html, 'Desafio dos EPIs'));
$html = $guest->request('GET', '/cursos?preco=sob-consulta')['body'];
check('filtro "sob consulta" = 6 reciclagens de brigada', countResults($html) === 6, (string) countResults($html));
$json = json($guest->request('GET', '/cursos?nr=33&ordem=menor-preco', [], $fetch));
check('catálogo responde JSON para o filtro ao vivo', ($json['total'] ?? null) === 5 && str_contains($json['results'] ?? '', 'NR 33') && ($json['heading'] ?? '') === 'NR 33 — Espaços Confinados');
$json = json($guest->request('GET', '/cursos?pagina=2&append=1', [], $fetch));
check('"carregar mais" devolve a página 2', substr_count($json['items'] ?? '', 'class="card"') === 12 && ($json['has_more'] ?? false));
check('página de NR', $guest->request('GET', '/nr/33')['status'] === 200);
check('página de categoria', $guest->request('GET', '/categorias/brigada-de-incendio')['status'] === 200);

// 2. Curso e carrinho ----------------------------------------------------
echo "\nCurso e carrinho\n";
$slug = 'nr-33-espacos-confinados-trabalhador-e-vigia';
$page = $guest->request('GET', "/cursos/$slug");
check('página do curso', $page['status'] === 200 && str_contains($page['body'], 'Espaços Confinados') && str_contains($page['body'], '"@type":"Course"'));
preg_match('/name="course_id" value="(\d+)"/', $page['body'], $m);
$courseId = (int) ($m[1] ?? 0);
$r = $guest->request('POST', '/carrinho/adicionar', ['course_id' => $courseId, 'qty' => 2], $ajax);
check('adicionar ao carrinho (JSON) atualiza a contagem', (json($r)['count'] ?? 0) === 2, $r['body']);
$nr10 = $guest->request('GET', '/cursos/nr-10-seguranca-em-instalacoes-e-servicos-com-eletricidade-basico')['body'];
preg_match('/name="course_id" value="(\d+)"/', $nr10, $m);
$nr10Id = (int) $m[1];
$r = $guest->request('POST', '/carrinho/adicionar', ['course_id' => $nr10Id, 'qty' => 1, 'buy_now' => 1]);
check('"Comprar agora" leva ao carrinho', $r['status'] === 302 && str_ends_with($r['location'], '/carrinho'));
$cart = $guest->request('GET', '/carrinho')['body'];
check('carrinho lista os dois cursos', str_contains($cart, 'Espaços Confinados') && str_contains($cart, 'Eletricidade'));
check('cabeçalho mostra 3 participantes', (bool) preg_match('/data-cart-count>3</', $cart));
check('total do carrinho = 2 × 228 + 251 = 707', str_contains($cart, 'R$ 707,00'));
$r = json($guest->request('POST', '/carrinho/atualizar', ['course_id' => $nr10Id, 'qty' => 3], $ajax));
check('alterar quantidade recalcula (2 × 228 + 3 × 251 = 1.209)', ($r['count'] ?? 0) === 5 && str_contains($r['html'] ?? '', 'R$ 1.209,00'));
$r = json($guest->request('POST', '/carrinho/cupom', ['coupon' => 'NAOEXISTE'], $ajax));
check('cupom inválido mostra erro', str_contains($r['html'] ?? '', 'Cupom inválido'));
$r = json($guest->request('POST', '/carrinho/cupom', ['coupon' => 'bemvindo10'], $ajax));
check('cupom válido dá 10% (− R$ 120,90)', str_contains($r['html'] ?? '', '− R$ 120,90') && str_contains($r['html'] ?? '', 'R$ 1.088,10'));
$r = json($guest->request('POST', '/carrinho/remover', ['course_id' => $nr10Id], $ajax));
check('remover item', ($r['count'] ?? 0) === 2 && !str_contains($r['html'] ?? '', 'Eletricidade'));
$r = $guest->request('POST', '/carrinho/adicionar', ['course_id' => $courseId, '_token' => 'invalido'], $ajax);
check('POST sem CSRF válido é recusado', $r['status'] === 419);
$consulta = $guest->request('GET', '/cursos?preco=sob-consulta')['body'];
preg_match('#href="/cursos/([a-z0-9-]+)"#', $consulta, $m);
$consultaPage = $guest->request('GET', '/cursos/' . $m[1])['body'];
check('curso sob consulta não tem botão de compra', str_contains($consultaPage, 'Solicitar proposta') && !str_contains($consultaPage, 'id="buy-form"'));

// 3. Checkout com cadastro -----------------------------------------------
echo "\nCheckout e cadastro\n";
$r = $guest->request('GET', '/checkout');
check('checkout exige login', $r['status'] === 302 && str_contains($r['location'], '/login?volta=%2Fcheckout'));
$guest->request('GET', '/cadastro?volta=/checkout');
$email = 'teste' . time() . '@example.com';
$r = $guest->request('POST', '/cadastro', ['name' => 'Marina', 'email' => $email, 'phone' => '(18) 99999-0000', 'password' => 'senha1234', 'password_confirmation' => 'senha1234', 'accept_terms' => '1', 'volta' => '/checkout']);
check('cadastro exige sobrenome', $r['status'] === 302 && str_contains($r['location'], '/cadastro'), $r['location']);
$r = $guest->request('POST', '/cadastro', ['name' => 'Marina Teste', 'email' => $email, 'phone' => '(18) 99999-0000', 'password' => 'senha1234', 'password_confirmation' => 'senha1234', 'accept_terms' => '1', 'volta' => '/checkout']);
check('cadastro volta para o checkout', $r['status'] === 302 && str_ends_with($r['location'], '/checkout'), $r['location']);
$checkout = $guest->request('GET', '/checkout');
check('checkout mostra o carrinho salvo', $checkout['status'] === 200 && str_contains($checkout['body'], 'Espaços Confinados'));
$r = $guest->request('POST', '/checkout', ['buyer_type' => 'pj', 'company_name' => 'Empresa Teste', 'company_document' => '11.222.333/0001-00', 'buyer_name' => 'Marina Teste', 'buyer_email' => $email, 'buyer_phone' => '(18) 99999-0000', 'payment_method' => 'pix', 'accept_terms' => '1']);
check('CNPJ inválido é recusado', $r['status'] === 302 && str_contains($r['location'], '/checkout'));
$r = $guest->request('POST', '/checkout', ['buyer_type' => 'pj', 'company_name' => 'Empresa Teste', 'company_document' => '11.222.333/0001-81', 'buyer_name' => 'Marina Teste', 'buyer_email' => $email, 'buyer_phone' => '(18) 99999-0000', 'payment_method' => 'pix']);
check('termos são obrigatórios', $r['status'] === 302 && str_contains($r['location'], '/checkout'));
$r = $guest->request('POST', '/checkout', ['buyer_type' => 'pj', 'company_name' => 'Empresa Teste', 'company_document' => '11.222.333/0001-81', 'buyer_name' => 'Marina Teste', 'buyer_email' => $email, 'buyer_phone' => '(18) 99999-0000', 'payment_method' => 'pix', 'accept_terms' => '1']);
preg_match('#/pedido/(DF\d{6})#', $r['location'], $m);
$number = $m[1] ?? '';
check('pedido criado', $r['status'] === 302 && $number !== '', $r['location']);
$orderPage = $guest->request('GET', "/pedido/$number")['body'];
check('confirmação diz "aguardando pagamento" e não finge aprovação', str_contains($orderPage, 'aguardando pagamento') && !str_contains($orderPage, 'Pagamento confirmado'));
check('carrinho esvaziado depois do pedido', (bool) preg_match('/data-cart-count hidden>0</', $orderPage));
check('pedido aparece em Minha conta', str_contains($guest->request('GET', '/minha-conta/pedidos')['body'], $number));
check('rota de pagamento sem gateway volta para o pedido', str_contains($guest->request('GET', "/pedido/$number/pagar")['location'], "/pedido/$number"));

// 4. Admin confirma pagamento ------------------------------------------
echo "\nPainel: pagamento, liberação e certificado\n";
$admin = new Client($base);
check('login do admin', $admin->login('admin@dafnis.test', 'dafnis123'));
$list = $admin->request('GET', '/admin/pedidos?q=' . $number)['body'];
preg_match('#/admin/pedidos/(\d+)"#', $list, $m);
$orderId = (int) ($m[1] ?? 0);
check('pedido aparece no painel', $orderId > 0);
$admin->request('GET', "/admin/pedidos/$orderId");
$r = $admin->request('POST', "/admin/pedidos/$orderId/confirmar-pagamento", ['note' => 'Pix conferido (teste)']);
check('baixa manual do pagamento', $r['status'] === 302);
$orderPage = $guest->request('GET', "/pedido/$number")['body'];
check('cliente vê "Pagamento confirmado" só depois da baixa', str_contains($orderPage, 'Pagamento confirmado') && str_contains($orderPage, 'Indicar participantes'));
$acc = $guest->request('GET', "/minha-conta/pedidos/$number")['body'];
preg_match_all('#action="/minha-conta/vagas/(\d+)"#', $acc, $m);
$seats = $m[1];
check('compra de empresa com 2 vagas pede 2 participantes', count($seats) === 2, (string) count($seats));
$r = $guest->request('POST', '/minha-conta/vagas/' . $seats[0], ['_scope' => 'seat-' . $seats[0], 'participant_name' => 'João Participante', 'participant_email' => 'joao.participante@example.com', 'participant_document' => '111.444.777-00']);
check('CPF inválido do participante é recusado', $r['status'] === 302 && ($guest->request('GET', "/minha-conta/pedidos/$number")['body'] !== '') && str_contains($guest->request('GET', "/minha-conta/pedidos/$number")['body'], 'Aguardando participante'));
$r = $guest->request('POST', '/minha-conta/vagas/' . $seats[0], ['participant_name' => 'João Participante', 'participant_email' => 'joao.participante@example.com', 'participant_document' => '111.444.777-35']);
check('participante indicado', $r['status'] === 302);
$r = $guest->request('POST', '/minha-conta/vagas/' . $seats[1], ['participant_name' => 'Outro Nome', 'participant_email' => 'joao.participante@example.com', 'participant_document' => '529.982.247-25']);
$acc = $guest->request('GET', "/minha-conta/pedidos/$number")['body'];
check('mesmo participante não ocupa duas vagas do mesmo curso', str_contains($acc, 'já ocupa outra vaga'));

$admin->request('GET', '/admin/matriculas/' . $seats[0]);
$r = $admin->request('POST', '/admin/matriculas/' . $seats[0], ['participant_name' => 'João Participante', 'participant_email' => 'joao.participante@example.com', 'participant_document' => '111.444.777-35', 'status' => 'active', 'progress' => 30, 'access_url' => 'https://example.com/plataforma']);
check('admin libera o acesso', $r['status'] === 302);

// O participante cria a própria conta e vê o curso
$joao = new Client($base);
$joao->request('GET', '/cadastro');
$joao->request('POST', '/cadastro', ['name' => 'João Participante', 'email' => 'joao.participante@example.com', 'phone' => '18999990001', 'password' => 'senha1234', 'password_confirmation' => 'senha1234', 'accept_terms' => '1']);
$courses = $joao->request('GET', '/minha-conta/cursos')['body'];
check('participante vê o curso com botão Continuar', str_contains($courses, 'Espaços Confinados') && str_contains($courses, 'https://example.com/plataforma') && str_contains($courses, '30%'));

$pdf = sys_get_temp_dir() . '/cert-teste.pdf';
file_put_contents($pdf, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
$admin->request('GET', '/admin/matriculas/' . $seats[0]);
$r = $admin->request('POST', '/admin/matriculas/' . $seats[0] . '/certificado', ['issued_at' => date('Y-m-d'), 'certificate_file' => new CURLFile($pdf, 'application/pdf', 'cert.pdf')]);
check('admin anexa o certificado (PDF)', $r['status'] === 302);
$certs = $joao->request('GET', '/minha-conta/certificados')['body'];
preg_match('#/minha-conta/certificados/(\d+)/baixar#', $certs, $m);
$certId = $m[1] ?? '';
check('certificado aparece para o participante', $certId !== '');
$dl = $joao->request('GET', "/minha-conta/certificados/$certId/baixar");
check('download do certificado é um PDF', $dl['status'] === 200 && str_starts_with($dl['body'], '%PDF'));
$dlBuyer = $guest->request('GET', "/minha-conta/certificados/$certId/baixar");
check('empresa compradora também baixa o certificado do colaborador', $dlBuyer['status'] === 200);

// 5. Isolamento entre contas (IDOR) --------------------------------------
echo "\nIsolamento entre contas\n";
$ana = new Client($base);
check('login da Ana', $ana->login('ana@example.com', 'dafnis123'));
check('Ana não vê o pedido de outra pessoa', $ana->request('GET', "/pedido/$number")['status'] === 404);
check('Ana não vê o detalhe do pedido na conta', $ana->request('GET', "/minha-conta/pedidos/$number")['status'] === 404);
check('Ana não baixa certificado alheio', $ana->request('GET', "/minha-conta/certificados/$certId/baixar")['status'] === 404);
$ana->request('GET', '/minha-conta');
check('Ana não altera vaga alheia', $ana->request('POST', '/minha-conta/vagas/' . $seats[1], ['participant_name' => 'Invasor Teste', 'participant_email' => 'x@example.com', 'participant_document' => '529.982.247-25'])['status'] === 404);
check('aluno não acessa o painel', $ana->request('GET', '/admin')['status'] === 404);
check('Ana vê o próprio certificado de teste', str_contains($ana->request('GET', '/minha-conta/certificados')['body'], 'DF-DEMO0001'));

// 6. Webhook, contato, admin de cursos ----------------------------------
echo "\nWebhook, contato e catálogo pelo painel\n";
$r = (new Client($base))->request('POST', '/webhooks/mercadopago?type=payment&data.id=123', [], ['Content-Type: application/json']);
check('webhook sem gateway configurado responde 200 e ignora', $r['status'] === 200 && str_contains($r['body'], 'ignored'));
$c = new Client($base);
$c->request('GET', '/contato?assunto=empresas');
$r = $c->request('POST', '/contato', ['subject' => 'empresas', 'name' => 'Contato Teste', 'email' => 'contato.teste@example.com', 'participants' => 12, 'message' => 'Teste automatizado']);
check('formulário de contato', $r['status'] === 302 && str_contains($r['location'], 'enviado=1'));
check('contato aparece no painel', str_contains($admin->request('GET', '/admin/contatos')['body'], 'Contato Teste'));

$edit = $admin->request('GET', "/admin/cursos/$courseId/editar")['body'];
check('formulário de edição do curso', str_contains($edit, 'Espaços Confinados'));
$r = $admin->request('POST', "/admin/cursos/$courseId/alternar", ['campo' => 'mais-vendido']);
$home = $guest->request('GET', '/')['body'];
check('marcar "Mais vendido" cria a seção na home', str_contains($home, 'Os mais procurados') && str_contains($home, 'badge-mais'));
$admin->request('POST', "/admin/cursos/$courseId/alternar", ['campo' => 'mais-vendido']);
$r = $admin->request('POST', "/admin/cursos/$courseId/alternar");
check('desativar curso tira da loja', $guest->request('GET', "/cursos/$slug")['status'] === 404);
$admin->request('POST', "/admin/cursos/$courseId/alternar");
check('reativar curso', $guest->request('GET', "/cursos/$slug")['status'] === 200);

$admin->request('GET', '/admin/cursos/novo');
$r = $admin->request('POST', '/admin/cursos', ['title' => 'Curso Criado no Teste', 'hours' => 6, 'modality' => 'online', 'training_type' => 'inicial', 'price' => '99,90', 'promo_price' => '79,90', 'icon' => 'clipboard', 'is_active' => 1, 'certificate' => 1, 'module_title' => ['Módulo A', ''], 'module_hours' => ['2 h', ''], 'module_topics' => ['Tópicos do módulo A', '']]);
check('criar curso pelo painel', $r['status'] === 302 && str_contains($r['location'], '/editar'));
$new = $guest->request('GET', '/cursos/curso-criado-no-teste')['body'];
check('curso novo com oferta e ementa', str_contains($new, 'R$ 79,90') && str_contains($new, '-20%') && str_contains($new, 'Módulo A'));
preg_match('#/admin/cursos/(\d+)/editar#', $r['location'], $m);
$admin->request('GET', '/admin/cursos/' . $m[1] . '/editar');
$admin->request('POST', '/admin/cursos/' . $m[1] . '/excluir');
check('excluir curso sem vendas', $guest->request('GET', '/cursos/curso-criado-no-teste')['status'] === 404);

echo "\n$checks verificações, $failures falha(s).\n";
exit($failures ? 1 : 0);
