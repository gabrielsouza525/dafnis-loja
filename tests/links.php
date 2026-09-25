<?php
declare(strict_types=1);

/**
 * Rastreia todos os links internos a partir de cada área (visitante, cliente, administrador)
 * e falha se algum responder 404 ou 5xx.
 *
 *   php tests/links.php http://localhost:8000
 */

require __DIR__ . '/lib.php';

$base = rtrim($argv[1] ?? 'http://localhost:8000', '/');
$failures = 0;
$visited = 0;

function crawl(Client $c, string $label, array $starts, int $limit = 400): void
{
    global $failures, $visited;
    $queue = $starts;
    $seen = array_fill_keys($starts, true);
    $count = 0;
    while ($queue && $count < $limit) {
        $path = array_shift($queue);
        $r = $c->request('GET', $path);
        $count++;
        $visited++;
        if ($r['status'] === 404 || $r['status'] >= 500) {
            $failures++;
            echo "  FAIL [$label] $path → {$r['status']}\n";
            continue;
        }
        if ($r['status'] !== 200 || !str_contains($r['body'], '<html')) {
            continue;
        }
        preg_match_all('/href="([^"]+)"/', $r['body'], $m);
        foreach ($m[1] as $href) {
            $href = html_entity_decode($href);
            if (preg_match('#^(https?:|mailto:|tel:|\#|javascript:)#', $href)) {
                continue;
            }
            $href = preg_replace('/#.*$/', '', $href);
            if ($href === '' || !str_starts_with($href, '/') || str_starts_with($href, '//')) {
                continue;
            }
            // Arquivos estáticos e exportações são checados à parte; paginação muito longa é cortada.
            if (preg_match('#^/(assets|uploads)/|\.(css|js|svg|png|webp|jpg|ico|xml|txt)(\?|$)|/exportar#', $href)) {
                continue;
            }
            if (!isset($seen[$href])) {
                $seen[$href] = true;
                $queue[] = $href;
            }
        }
    }
    echo "  [$label] $count páginas visitadas\n";
}

echo "Rastreando links em $base\n";
crawl(new Client($base), 'visitante', ['/']);

$client = new Client($base);
$client->login('ana@example.com', 'dafnis123');
crawl($client, 'cliente', ['/minha-conta']);

$admin = new Client($base);
$admin->login('admin@dafnis.test', 'dafnis123');
crawl($admin, 'admin', ['/admin'], 600);

echo "\n$visited páginas, $failures link(s) quebrado(s).\n";
exit($failures ? 1 : 0);
