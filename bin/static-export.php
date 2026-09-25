<?php
declare(strict_types=1);

/**
 * Gera a prévia estática da loja (para o GitHub Pages, que não roda PHP).
 *
 * 1. Banco com dados de demonstração:  php bin/console db:fresh --demo
 * 2. Servidor em modo prévia, com a URL final da prévia:
 *      STATIC_DEMO=true APP_URL=https://usuario.github.io/repositorio APP_DEBUG=false \
 *      php -S localhost:8001 -t public public/index.php
 * 3. Exportação (mesmas variáveis de ambiente):
 *      STATIC_DEMO=true APP_URL=https://usuario.github.io/repositorio php bin/static-export.php http://localhost:8001 ../saida
 *
 * Páginas públicas saem como estão; carrinho, checkout, pedido, Minha conta e painel saem
 * como "fotografias" de uma compra feita durante a exportação (aluna e equipe de exemplo).
 */

if (PHP_SAPI !== 'cli') {
    exit("Somente linha de comando.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';
require BASE_PATH . '/tests/lib.php';

use App\Core\Database;
use App\Models\Category;
use App\Models\Course;

restore_exception_handler();

[$server, $out] = [rtrim($argv[1] ?? '', '/'), rtrim($argv[2] ?? '', '/\\')];
if ($server === '' || $out === '' || !static_demo()) {
    fwrite(STDERR, "Uso: STATIC_DEMO=true APP_URL=... php bin/static-export.php http://localhost:8001 pasta-de-saida\n");
    exit(1);
}
if (Database::value("SELECT COUNT(*) FROM orders") > 5) {
    fwrite(STDERR, "Rode 'php bin/console db:fresh --demo' antes: a exportação faz uma compra de exemplo.\n");
    exit(1);
}

$prefix = base_path_prefix();
$base = $server . $prefix;
$pages = 0;

/** Links internos terminam em "/" (o GitHub Pages serve pasta/index.html). */
$rewrite = static function (string $html) use ($prefix): string {
    return (string) preg_replace_callback('#(href|action)="(' . preg_quote($prefix, '#') . '/[^"]*)"#', static function ($m) {
        $url = html_entity_decode($m[2]);
        $hash = '';
        if (($p = strpos($url, '#')) !== false) {
            [$url, $hash] = [substr($url, 0, $p), substr($url, $p)];
        }
        $query = '';
        if (($p = strpos($url, '?')) !== false) {
            [$url, $query] = [substr($url, 0, $p), substr($url, $p)];
        }
        if (!str_ends_with($url, '/') && !preg_match('#\.[a-z0-9]{2,5}$#i', $url)) {
            $url .= '/';
        }
        return $m[1] . '="' . htmlspecialchars($url . $query . $hash, ENT_QUOTES, 'UTF-8') . '"';
    }, $html);
};

$save = static function (string $path, string $body, bool $html = true) use ($out, $rewrite, &$pages): void {
    $file = match (true) {
        $path === '/' => '/index.html',
        (bool) preg_match('#\.[a-z]{2,4}$#', $path) => $path,
        default => rtrim($path, '/') . '/index.html',
    };
    $target = $out . $file;
    if (!is_dir(dirname($target))) {
        mkdir(dirname($target), 0775, true);
    }
    file_put_contents($target, $html ? $rewrite($body) : $body);
    $pages++;
};

$get = static function (Client $c, string $path, int $expect = 200) use ($save): array {
    $r = $c->request('GET', $path);
    if ($r['status'] !== $expect) {
        throw new RuntimeException("GET $path respondeu {$r['status']}");
    }
    $save($path, $r['body'], str_contains($r['body'], '<html'));
    return $r;
};

echo "Exportando $base para $out\n";
if (is_dir($out)) {
    fwrite(STDERR, "A pasta de saída já existe; apague antes.\n");
    exit(1);
}
mkdir($out, 0775, true);

// Páginas públicas ----------------------------------------------------------
$guest = new Client($base);
$public = ['/', '/cursos', '/nrs', '/contato', '/termos-de-uso', '/politica-de-privacidade', '/login', '/cadastro', '/esqueci-senha', '/sitemap.xml', '/robots.txt'];
foreach (Course::nrIndex() as $nr) {
    $public[] = '/nr/' . $nr['nr'];
}
foreach (Category::active() as $cat) {
    if ($cat['course_count'] > 0) {
        $public[] = '/categorias/' . $cat['slug'];
    }
}
foreach (Course::allActive() as $course) {
    $public[] = '/cursos/' . $course['slug'];
}
foreach ($public as $path) {
    $get($guest, $path);
}
$r = $guest->request('GET', '/pagina-que-nao-existe');
file_put_contents($out . '/404.html', $rewrite($r['body']));
echo "  $pages páginas públicas\n";

// Compra de exemplo: aluna monta o carrinho, finaliza e a equipe confirma --------
$ana = new Client($base);
if (!$ana->login('ana@example.com', 'dafnis123')) {
    throw new RuntimeException('Login da aluna de exemplo falhou.');
}
$nr33 = Course::findActiveBySlug('nr-33-espacos-confinados-trabalhador-e-vigia');
$nr10 = Course::findActiveBySlug('nr-10-seguranca-em-instalacoes-e-servicos-com-eletricidade-basico');
$ana->request('GET', '/cursos/' . $nr33['slug']);
$ana->request('POST', '/carrinho/adicionar', ['course_id' => $nr33['id'], 'qty' => 3]);
$ana->request('POST', '/carrinho/adicionar', ['course_id' => $nr10['id'], 'qty' => 1]);
$get($ana, '/carrinho');
$get($ana, '/checkout');
$r = $ana->request('POST', '/checkout', [
    'buyer_type' => 'pf', 'buyer_name' => 'Ana Souza', 'buyer_document' => '529.982.247-25',
    'buyer_email' => 'ana@example.com', 'buyer_phone' => '(18) 90000-0002', 'payment_method' => 'pix', 'accept_terms' => '1',
]);
if (!preg_match('#/pedido/(DF\d{6})#', $r['location'], $m)) {
    throw new RuntimeException('Checkout de exemplo falhou: ' . $r['location']);
}
$number = $m[1];
$get($ana, '/pedido/' . $number);

$admin = new Client($base);
if (!$admin->login('admin@dafnis.test', 'dafnis123')) {
    throw new RuntimeException('Login da equipe de exemplo falhou.');
}
$orderId = (int) Database::value('SELECT id FROM orders WHERE number = :n', ['n' => $number]);
$admin->request('GET', '/admin/pedidos/' . $orderId);
$admin->request('POST', '/admin/pedidos/' . $orderId . '/confirmar-pagamento', ['note' => 'Pix conferido (exemplo)']);

foreach (['/minha-conta', '/minha-conta/cursos', '/minha-conta/certificados', '/minha-conta/pedidos', '/minha-conta/dados'] as $path) {
    $get($ana, $path);
}
foreach (Database::select('SELECT number FROM orders WHERE buyer_email = :e', ['e' => 'ana@example.com']) as $o) {
    $get($ana, '/minha-conta/pedidos/' . $o['number']);
}

// Painel da equipe --------------------------------------------------------------
foreach (['/admin', '/admin/pedidos', '/admin/matriculas', '/admin/cursos', '/admin/cursos/novo', '/admin/categorias', '/admin/cupons', '/admin/usuarios', '/admin/contatos', '/admin/configuracoes'] as $path) {
    $get($admin, $path);
}
foreach (Database::select('SELECT id FROM orders') as $o) {
    $get($admin, '/admin/pedidos/' . $o['id']);
}
foreach (Database::select('SELECT id FROM enrollments') as $e) {
    $get($admin, '/admin/matriculas/' . $e['id']);
}
foreach (Database::select('SELECT id FROM users') as $u) {
    $get($admin, '/admin/usuarios/' . $u['id']);
}
foreach (Course::featured(8) as $course) {
    $get($admin, '/admin/cursos/' . $course['id'] . '/editar');
}

// Arquivos estáticos ------------------------------------------------------------
$copy = static function (string $from, string $to) use (&$copy): void {
    if (is_dir($from)) {
        @mkdir($to, 0775, true);
        foreach (scandir($from) as $item) {
            if ($item !== '.' && $item !== '..') {
                $copy("$from/$item", "$to/$item");
            }
        }
        return;
    }
    copy($from, $to);
};
$copy(BASE_PATH . '/public/assets', $out . '/assets');
copy(BASE_PATH . '/public/favicon.svg', $out . '/favicon.svg');
copy(BASE_PATH . '/public/apple-touch-icon.png', $out . '/apple-touch-icon.png');
file_put_contents($out . '/assets/js/demo-map.js', '// Gerado por bin/static-export.php: destino dos botões na prévia.' . "\n" . 'window.DAFNIS_DEMO = ' . json_encode([
    'cart' => $prefix . '/carrinho/',
    'order' => $prefix . '/pedido/' . $number . '/',
    'account' => $prefix . '/minha-conta/',
    'home' => $prefix . '/',
], JSON_UNESCAPED_SLASHES) . ";\n");
file_put_contents($out . '/.nojekyll', '');

echo "  $pages páginas no total (pedido de exemplo $number)\n";
