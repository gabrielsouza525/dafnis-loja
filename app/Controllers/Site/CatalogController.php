<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Response;
use App\Core\View;
use App\Models\Category;
use App\Models\Course;
use App\Services\Catalog;
use App\Services\Seo;

final class CatalogController extends Controller
{
    public function index(): Response
    {
        return $this->render([], [
            'heading' => 'Todos os treinamentos',
            'lead' => 'NRs e cursos complementares para profissionais e empresas.',
            'canonical' => '/cursos',
            'crumbs' => [['Início', '/'], ['Cursos', null]],
            'title' => 'Cursos NR e treinamentos de segurança do trabalho',
        ]);
    }

    public function category(string $slug): Response
    {
        $cat = Category::findActiveBySlug($slug);
        if (!$cat || $cat['course_count'] === 0) {
            $this->notFound('Categoria não encontrada.');
        }
        return $this->render(['categoria' => [$slug]], [
            'heading' => $cat['name'],
            'lead' => $cat['description'],
            'canonical' => '/categorias/' . $slug,
            'crumbs' => [['Início', '/'], ['Cursos', '/cursos'], [$cat['name'], null]],
            'title' => $cat['name'] . ' — treinamentos',
            'description' => $cat['description'] . ' Treinamentos com certificado de conclusão na Dafnis.',
        ]);
    }

    public function nr(int $nr): Response
    {
        $index = array_column(Course::nrIndex(), null, 'nr');
        if (!isset($index[$nr])) {
            $this->notFound('Ainda não temos treinamentos desta NR no catálogo.');
        }
        $name = Course::NR_NAMES[$nr] ?? '';
        return $this->render(['nr' => [$nr]], [
            'heading' => 'NR ' . $nr . ($name ? ' — ' . $name : ''),
            'lead' => 'Todos os treinamentos da NR ' . $nr . ': formação inicial, reciclagem e simuladores.',
            'canonical' => '/nr/' . $nr,
            'crumbs' => [['Início', '/'], ['NRs', '/nrs'], ['NR ' . $nr, null]],
            'title' => 'Treinamentos NR ' . $nr . ($name ? ' — ' . $name : ''),
            'description' => 'Cursos da NR ' . $nr . ($name ? ' (' . $name . ')' : '') . ' com certificado de conclusão. Compre para você ou para sua equipe.',
        ], 'nrs');
    }

    public function nrs(): Response
    {
        return $this->view('site/nrs', [
            'nav' => 'nrs',
            'title' => 'Treinamentos por Norma Regulamentadora',
            'description' => 'Encontre os treinamentos por NR: NR 1, NR 5, NR 10, NR 11, NR 12, NR 20, NR 33 e outras normas regulamentadoras.',
            'canonical' => '/nrs',
            'nrIndex' => Course::nrIndex(),
            'jsonLd' => [Seo::breadcrumbs([['Início', '/'], ['NRs', '/nrs']])],
        ]);
    }

    private function render(array $preset, array $context, string $nav = 'cursos'): Response
    {
        $f = Catalog::filtersFromRequest($this->request, $preset);
        $result = Catalog::search($f);
        if (!$preset) {
            $context = array_merge($context, $this->headingFor($f));
        }
        $data = [
            'f' => $f,
            'result' => $result,
            'context' => $context,
            'query' => Catalog::queryFor($f),
        ];

        if ($this->request->header('X-Requested-With') === 'fetch') {
            if ($this->request->query('append')) {
                $html = '';
                foreach ($result['items'] as $course) {
                    $html .= View::file('partials/course-card', ['course' => $course]);
                }
                return $this->json([
                    'items' => $html,
                    'has_more' => $result['has_more'],
                    'next' => $result['page'] + 1,
                    'shown' => min($result['total'], $result['page'] * Catalog::pageSize()),
                    'total' => $result['total'],
                ]);
            }
            return $this->json([
                'results' => View::file('site/partials/catalog-results', $data),
                'filters' => View::file('site/partials/catalog-filters', $data),
                'total' => $result['total'],
                'active' => $result['active_filters'],
                'heading' => $context['heading'],
                'url' => url('/cursos', Catalog::queryFor($f, ['pagina' => 1])),
            ]);
        }

        return $this->view('site/catalog', array_merge($data, [
            'nav' => $nav,
            'title' => $context['title'],
            'description' => $context['description'] ?? 'Catálogo completo de treinamentos: Normas Regulamentadoras, segurança do trabalho, primeiros socorros, brigada de incêndio, simuladores e cursos corporativos, com certificado.',
            'canonical' => $context['canonical'],
            'noindex' => $f['q'] !== '',
            'jsonLd' => [
                Seo::breadcrumbs(array_map(static fn ($c) => [$c[0], $c[1] ?? $context['canonical']], $context['crumbs'])),
                Seo::itemList($result['items']),
            ],
            'scripts' => [static_demo() ? 'catalog-static.js' : 'catalog.js'],
        ]));
    }

    /** Em /cursos, um único filtro de NR ou de categoria vira o título da página. */
    private function headingFor(array $f): array
    {
        $others = count($f['modalidade']) + count($f['carga']) + count($f['preco']) + count($f['tipo']);
        if ($others === 0 && count($f['nr']) === 1 && !$f['categoria']) {
            $nr = $f['nr'][0];
            $name = Course::NR_NAMES[$nr] ?? '';
            return ['heading' => 'NR ' . $nr . ($name ? ' — ' . $name : ''), 'title' => 'Treinamentos NR ' . $nr];
        }
        if ($others === 0 && count($f['categoria']) === 1 && !$f['nr'] && ($cat = Category::findActiveBySlug($f['categoria'][0]))) {
            return ['heading' => $cat['name'], 'title' => $cat['name'] . ' — treinamentos'];
        }
        return [];
    }
}
