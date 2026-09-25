<?php
declare(strict_types=1);

/**
 * Teste de fumaça: status HTTP de todas as páginas por perfil (visitante, aluno, admin).
 * Requer os dados de demonstração (db:fresh --demo) e o servidor rodando.
 *
 *   php tests/smoke.php http://localhost:8000
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

function expect(Client $c, string $path, int $status): array
{
    $r = $c->request('GET', $path);
    $extra = '';
    if ($r['status'] >= 500 && preg_match('/\[DEBUG[^\]]*\]\s*(.{0,300})/s', $r['body'], $m)) {
        $extra = html_entity_decode(strip_tags($m[1]));
    }
    check("GET $path → $status", $r['status'] === $status, 'recebeu ' . $r['status'] . ($r['location'] ? ' → ' . $r['location'] : '') . ($extra ? " $extra" : ''));
    return $r;
}

echo "Dafnis — teste de fumaça em $base\n\nVisitante\n";
$guest = new Client($base);
foreach (['/', '/cursos', '/cursos?ordem=menor-preco', '/cursos?ordem=maior-preco', '/cursos?ordem=carga-horaria', '/cursos?ordem=nome', '/cursos?ordem=nr', '/cursos?pagina=3', '/cursos?nr[]=10&nr[]=33', '/nrs', '/nr/1', '/nr/23', '/categorias/primeiros-socorros', '/categorias/simuladores-e-jogos', '/carrinho', '/login', '/cadastro', '/esqueci-senha', '/contato', '/contato?assunto=conteudo&curso=nr-10-seguranca-em-instalacoes-e-servicos-com-eletricidade-basico', '/termos-de-uso', '/politica-de-privacidade', '/sitemap.xml', '/robots.txt'] as $p) {
    expect($guest, $p, 200);
}
foreach (['/nr/99', '/cursos/nao-existe', '/categorias/nao-existe', '/pagina-que-nao-existe', '/instalar'] as $p) {
    expect($guest, $p, 404);
}
foreach (['/checkout', '/minha-conta', '/minha-conta/cursos', '/admin', '/pedido/DF000001'] as $p) {
    expect($guest, $p, 302);
}
expect($guest, '/curso/nr-10-seguranca-em-instalacoes-e-servicos-com-eletricidade-basico', 301);
$sitemap = $guest->request('GET', '/sitemap.xml')['body'];
check('sitemap lista os 119 cursos', substr_count($sitemap, '/cursos/') === 119);
preg_match_all('#<loc>[^<]+/cursos/([a-z0-9-]+)</loc>#', $sitemap, $m);
foreach ($m[1] as $slug) {
    $r = $guest->request('GET', '/cursos/' . $slug);
    if ($r['status'] !== 200) {
        check("curso $slug", false, (string) $r['status']);
    }
}
check('todas as páginas de curso do sitemap abrem', true);
$home = $guest->request('GET', '/')['body'];
check('home tem JSON-LD de organização e FAQ', str_contains($home, '"EducationalOrganization"') && str_contains($home, '"FAQPage"'));
check('home tem canonical e og:image', str_contains($home, 'rel="canonical"') && str_contains($home, 'og:image'));

echo "\nAluna (ana@example.com)\n";
$ana = new Client($base);
check('login', $ana->login('ana@example.com', 'dafnis123'));
foreach (['/minha-conta', '/minha-conta/cursos', '/minha-conta/certificados', '/minha-conta/pedidos', '/minha-conta/pedidos/DF000001', '/minha-conta/dados', '/pedido/DF000003', '/checkout'] as $p) {
    expect($ana, $p, $p === '/checkout' ? 302 : 200);
}
expect($ana, '/minha-conta/pedidos/DF000004', 404);
expect($ana, '/admin', 404);
$dash = $ana->request('GET', '/minha-conta')['body'];
check('painel do aluno com cursos, concluído e certificado', str_contains($dash, 'Continuar estudando') && str_contains($dash, 'Certificados disponíveis'));

echo "\nEmpresa (rh@example.com)\n";
$rh = new Client($base);
check('login', $rh->login('rh@example.com', 'dafnis123'));
$orders = $rh->request('GET', '/minha-conta/pedidos')['body'];
check('empresa vê os pedidos', str_contains($orders, 'DF000004') && str_contains($orders, 'DF000005'));
$order = $rh->request('GET', '/minha-conta/pedidos/DF000004')['body'];
check('empresa vê as 5 vagas (3 × NR 33 + 2 × Primeiros Socorros) para indicar participantes', substr_count($order, 'action="/minha-conta/vagas/') === 5);

echo "\nAdministrador\n";
$admin = new Client($base);
check('login', $admin->login('admin@dafnis.test', 'dafnis123'));
$pages = ['/admin', '/admin/cursos', '/admin/cursos?status=inativos', '/admin/cursos?status=sem-preco', '/admin/cursos?q=nr+10', '/admin/cursos?categoria=1', '/admin/cursos/novo', '/admin/categorias', '/admin/pedidos', '/admin/pedidos?status=pending', '/admin/pedidos?q=metal', '/admin/matriculas', '/admin/matriculas?status=processing', '/admin/usuarios', '/admin/usuarios?perfil=admin', '/admin/cupons', '/admin/cupons/novo', '/admin/contatos', '/admin/contatos?status=atendidos', '/admin/configuracoes'];
foreach ($pages as $p) {
    expect($admin, $p, 200);
}
foreach ([['/admin/pedidos', '#/admin/pedidos/(\d+)"#', '/admin/pedidos/'], ['/admin/matriculas', '#/admin/matriculas/(\d+)"#', '/admin/matriculas/'], ['/admin/usuarios', '#/admin/usuarios/(\d+)"#', '/admin/usuarios/'], ['/admin/cursos', '#/admin/cursos/(\d+)/editar"#', '/admin/cursos/'], ['/admin/cupons', '#/admin/cupons/(\d+)/editar"#', '/admin/cupons/']] as [$list, $re, $prefix]) {
    preg_match_all($re, $admin->request('GET', $list)['body'], $mm);
    $ids = array_slice(array_unique($mm[1]), 0, 12);
    check("lista $list tem itens", count($ids) > 0);
    foreach ($ids as $id) {
        expect($admin, $prefix . $id . (in_array($prefix, ['/admin/cursos/', '/admin/cupons/'], true) ? '/editar' : ''), 200);
    }
}
expect($admin, '/admin/matriculas/2/certificado', 200);

echo "\n$checks verificações, $failures falha(s).\n";
exit($failures ? 1 : 0);
