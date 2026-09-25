<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Models\Category;
use App\Models\Course;

/**
 * Busca, filtros combinados, contagem por opção e ordenação do catálogo.
 *
 * O catálogo tem algumas centenas de cursos no máximo, então tudo é feito em
 * memória sobre Course::allActive() (uma consulta por requisição). Se um dia
 * passar de alguns milhares, troque por consultas com FULLTEXT sem mudar a API.
 */
final class Catalog
{
    public const PAGE_SIZE = 12;

    /** Na prévia estática todos os cursos vão na mesma página (o filtro roda no navegador). */
    public static function pageSize(): int
    {
        return static_demo() ? 1000 : self::PAGE_SIZE;
    }

    public const HOURS = [
        'ate-4h' => ['label' => 'Até 4 h', 'min' => 0, 'max' => 4],
        '5-a-8h' => ['label' => '5 a 8 h', 'min' => 5, 'max' => 8],
        '9-a-16h' => ['label' => '9 a 16 h', 'min' => 9, 'max' => 16],
        '17-a-40h' => ['label' => '17 a 40 h', 'min' => 17, 'max' => 40],
        'acima-40h' => ['label' => 'Acima de 40 h', 'min' => 41, 'max' => null],
    ];

    public const PRICES = [
        'ate-150' => ['label' => 'Até R$ 150', 'min' => 0, 'max' => 150],
        '150-a-200' => ['label' => 'R$ 150 a R$ 200', 'min' => 150.01, 'max' => 200],
        '200-a-250' => ['label' => 'R$ 200 a R$ 250', 'min' => 200.01, 'max' => 250],
        'acima-250' => ['label' => 'Acima de R$ 250', 'min' => 250.01, 'max' => null],
        'sob-consulta' => ['label' => 'Sob consulta', 'min' => null, 'max' => null],
    ];

    public const SORTS = [
        'relevancia' => 'Relevância',
        'nr' => 'Número da NR',
        'menor-preco' => 'Menor preço',
        'maior-preco' => 'Maior preço',
        'carga-horaria' => 'Carga horária',
        'nome' => 'Nome (A–Z)',
    ];

    private const STOPWORDS = ['de', 'da', 'do', 'das', 'dos', 'e', 'em', 'para', 'com', 'a', 'o', 'as', 'os', 'no', 'na', 'curso', 'cursos', 'treinamento', 'treinamentos'];

    /** Lê os filtros da URL. Aceita "nr=10,33" (links da loja) e "nr[]=10" (formulário sem JS). */
    public static function filtersFromRequest(Request $request, array $preset = []): array
    {
        $list = static function (mixed $value): array {
            if (is_array($value)) {
                $value = implode(',', array_map('strval', $value));
            }
            $items = array_filter(array_map('trim', explode(',', (string) $value)), static fn ($v) => $v !== '');
            return array_values(array_unique($items));
        };
        $f = [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 80),
            'nr' => array_values(array_filter(array_map('intval', $list($request->query('nr'))), static fn ($n) => $n > 0 && $n < 100)),
            'categoria' => $list($request->query('categoria')),
            'modalidade' => array_values(array_intersect($list($request->query('modalidade')), array_keys(MODALITIES))),
            'carga' => array_values(array_intersect($list($request->query('carga')), array_keys(self::HOURS))),
            'preco' => array_values(array_intersect($list($request->query('preco')), array_keys(self::PRICES))),
            'tipo' => array_values(array_intersect($list($request->query('tipo')), array_keys(TRAINING_TYPES))),
            'ordem' => array_key_exists((string) $request->query('ordem'), self::SORTS) ? (string) $request->query('ordem') : 'relevancia',
            'pagina' => max(1, (int) $request->query('pagina', 1)),
        ];
        foreach ($preset as $key => $values) {
            $f[$key] = array_values(array_unique(array_merge($values, $f[$key])));
        }
        return $f;
    }

    /** Query string limpa (vírgulas) para links, histórico do navegador e canonical. */
    public static function queryFor(array $f, array $override = []): array
    {
        $f = array_merge($f, $override);
        return array_filter([
            'q' => $f['q'] ?? '',
            'nr' => implode(',', $f['nr'] ?? []),
            'categoria' => implode(',', $f['categoria'] ?? []),
            'modalidade' => implode(',', $f['modalidade'] ?? []),
            'carga' => implode(',', $f['carga'] ?? []),
            'preco' => implode(',', $f['preco'] ?? []),
            'tipo' => implode(',', $f['tipo'] ?? []),
            'ordem' => ($f['ordem'] ?? 'relevancia') !== 'relevancia' ? $f['ordem'] : '',
            'pagina' => ($f['pagina'] ?? 1) > 1 ? $f['pagina'] : '',
        ], static fn ($v) => $v !== '' && $v !== null);
    }

    public static function search(array $f): array
    {
        $scores = [];
        $base = [];
        foreach (Course::allActive() as $id => $course) {
            $score = self::score($course, $f['q']);
            if ($score !== null) {
                $scores[$id] = $score;
                $base[$id] = $course;
            }
        }

        $items = array_filter($base, static fn ($c) => self::pass($c, $f, null));
        $items = self::sort($items, $f['ordem'], $scores, $f['q'] !== '');

        $total = count($items);
        $size = self::pageSize();
        $pages = max(1, (int) ceil($total / $size));
        $page = min($f['pagina'], $pages);

        return [
            'items' => array_slice($items, ($page - 1) * $size, $size),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'has_more' => $page < $pages,
            'groups' => self::facets($base, $f),
            'chips' => self::chips($f),
            'active_filters' => count(self::chips($f)) - ($f['q'] !== '' ? 1 : 0),
        ];
    }

    /** Cursos mostrados como sugestão quando a busca não encontra nada. */
    public static function suggestions(int $limit = 4): array
    {
        return array_slice(Course::featured($limit), 0, $limit);
    }

    /**
     * Pontuação da busca (null = não combina). Cada termo precisa aparecer como
     * início de palavra em algum campo; o título e a NR pesam mais que o público-alvo.
     */
    private static function score(array $c, string $query): ?float
    {
        $q = trim(normalize_text($query));
        if ($q === '') {
            return 0.0;
        }
        // "NR 10", "nr10", "NR-10", "nr 31.7": busca exata pela norma.
        if (preg_match('/^nr\s*-?\s*(\d{1,2})(?:[.,]\d+)?$/', $q, $m)) {
            return $c['nr_number'] === (int) $m[1] ? 100.0 + ($c['is_simulator'] ? 0 : 10) : null;
        }

        $fields = [
            8 => normalize_text(($c['nr_number'] ? 'nr ' . $c['nr_number'] . ' nr' . $c['nr_number'] . ' ' : '') . $c['code_label']),
            6 => normalize_text($c['title'] . ' ' . $c['short_title']),
            4 => normalize_text((string) $c['keywords']),
            3 => normalize_text(($c['category_name'] ?? '') . ' ' . $c['modality_label']),
            1 => normalize_text($c['audience'] . ' ' . $c['summary'] . ' ' . $c['type_label']),
        ];

        $tokens = preg_split('/\s+/', preg_replace('/[^a-z0-9\s.]/', ' ', $q) ?? '') ?: [];
        $tokens = array_values(array_filter($tokens, static fn ($t) => $t !== ''));
        $meaningful = array_values(array_filter($tokens, static fn ($t) => !in_array($t, self::STOPWORDS, true)));
        $tokens = $meaningful ?: $tokens;

        $score = 0.0;
        foreach ($tokens as $i => $token) {
            // "nr10" dentro de uma frase ("nr10 reciclagem")
            if (preg_match('/^nr-?(\d{1,2})$/', $token, $m)) {
                if ($c['nr_number'] !== (int) $m[1]) {
                    return null;
                }
                $score += 20;
                continue;
            }
            // "nr" seguido do número ("nr 10 reciclagem"): o número decide
            if ($token === 'nr' && isset($tokens[$i + 1]) && ctype_digit($tokens[$i + 1])) {
                continue;
            }
            if (ctype_digit($token) && $i > 0 && $tokens[$i - 1] === 'nr') {
                if ($c['nr_number'] !== (int) $token) {
                    return null;
                }
                $score += 20;
                continue;
            }
            $best = 0;
            $pattern = '/(?<![a-z0-9])' . preg_quote($token, '/') . '/';
            foreach ($fields as $weight => $text) {
                if ($weight > $best && preg_match($pattern, $text)) {
                    $best = $weight;
                }
            }
            if ($best === 0) {
                return null;
            }
            $score += $best;
        }
        return $score;
    }

    /** O curso passa em todos os filtros? $skip ignora um grupo (para contar as opções dele). */
    private static function pass(array $c, array $f, ?string $skip): bool
    {
        if ($skip !== 'nr' && $f['nr'] && !in_array($c['nr_number'], $f['nr'], true)) {
            return false;
        }
        if ($skip !== 'categoria' && $f['categoria'] && !in_array($c['category_slug'], $f['categoria'], true)) {
            return false;
        }
        if ($skip !== 'modalidade' && $f['modalidade'] && !in_array($c['modality'], $f['modalidade'], true)) {
            return false;
        }
        if ($skip !== 'carga' && $f['carga'] && !self::anyRange($c['hours'], $f['carga'], self::HOURS)) {
            return false;
        }
        if ($skip !== 'preco' && $f['preco'] && !self::priceIn($c, $f['preco'])) {
            return false;
        }
        if ($skip !== 'tipo' && $f['tipo'] && !in_array($c['training_type'], $f['tipo'], true)) {
            return false;
        }
        return true;
    }

    private static function anyRange(float|int $value, array $ids, array $ranges): bool
    {
        foreach ($ids as $id) {
            $r = $ranges[$id];
            if ($value >= $r['min'] && ($r['max'] === null || $value <= $r['max'])) {
                return true;
            }
        }
        return false;
    }

    private static function priceIn(array $c, array $ids): bool
    {
        if (!$c['has_price']) {
            return in_array('sob-consulta', $ids, true);
        }
        $ranged = array_values(array_diff($ids, ['sob-consulta']));
        return $ranged && self::anyRange($c['final_price'], $ranged, self::PRICES);
    }

    private static function sort(array $items, string $order, array $scores, bool $searching): array
    {
        $byTitle = static fn ($a, $b) => strcmp(normalize_text($a['title']), normalize_text($b['title']));
        $nrThenTitle = static fn ($a, $b) => [$a['nr_number'] === null, $a['nr_number'], $a['is_simulator']] <=> [$b['nr_number'] === null, $b['nr_number'], $b['is_simulator']] ?: $byTitle($a, $b);
        $priceKey = static fn ($c, $dir) => $c['has_price'] ? $c['final_price'] * $dir : PHP_INT_MAX;

        $cmp = match ($order) {
            'nr' => $nrThenTitle,
            'menor-preco' => static fn ($a, $b) => $priceKey($a, 1) <=> $priceKey($b, 1) ?: $nrThenTitle($a, $b),
            'maior-preco' => static fn ($a, $b) => $priceKey($a, -1) <=> $priceKey($b, -1) ?: $nrThenTitle($a, $b),
            'carga-horaria' => static fn ($a, $b) => $a['hours'] <=> $b['hours'] ?: $nrThenTitle($a, $b),
            'nome' => $byTitle,
            default => static function ($a, $b) use ($scores, $searching, $nrThenTitle) {
                if ($searching && ($s = ($scores[$b['id']] ?? 0) <=> ($scores[$a['id']] ?? 0)) !== 0) {
                    return $s;
                }
                // Destaques primeiro, depois mais vendidos, depois treinamentos (simuladores por último).
                $fa = $a['featured_order'] ?? PHP_INT_MAX;
                $fb = $b['featured_order'] ?? PHP_INT_MAX;
                return $fa <=> $fb ?: $b['is_bestseller'] <=> $a['is_bestseller'] ?: $a['is_simulator'] <=> $b['is_simulator'] ?: $nrThenTitle($a, $b);
            },
        };
        usort($items, $cmp);
        return array_values($items);
    }

    /** Grupos de filtro com a contagem de cada opção considerando os demais filtros. */
    private static function facets(array $base, array $f): array
    {
        $all = Course::allActive();
        $count = static fn (string $group, callable $test) => count(array_filter($base, static fn ($c) => self::pass($c, $f, $group) && $test($c)));
        // Opções sem nenhum curso no catálogo inteiro nem aparecem.
        $exists = static fn (callable $test) => (bool) array_filter($all, $test);

        $option = static function (string $group, string $value, string $label, callable $test) use ($f, $count) {
            $selected = in_array($value, array_map('strval', $f[$group]), true);
            $n = $count($group, $test);
            return ['value' => $value, 'label' => $label, 'count' => $n, 'checked' => $selected, 'dim' => $n === 0 && !$selected];
        };

        $groups = [];

        $nrOptions = [];
        foreach (Course::nrIndex() as $nr) {
            $nrOptions[] = $option('nr', (string) $nr['nr'], $nr['code'], static fn ($c) => $c['nr_number'] === $nr['nr']);
        }
        $groups[] = ['key' => 'nr', 'title' => 'NR', 'cols' => true, 'options' => $nrOptions];

        $catOptions = [];
        foreach (Category::active() as $cat) {
            if ($exists(static fn ($c) => $c['category_slug'] === $cat['slug'])) {
                $catOptions[] = $option('categoria', $cat['slug'], $cat['name'], static fn ($c) => $c['category_slug'] === $cat['slug']);
            }
        }
        $groups[] = ['key' => 'categoria', 'title' => 'Categoria', 'cols' => false, 'options' => $catOptions];

        $h = [];
        foreach (self::HOURS as $id => $r) {
            $test = static fn ($c) => self::anyRange($c['hours'], [$id], self::HOURS);
            if ($exists($test)) {
                $h[] = $option('carga', $id, $r['label'], $test);
            }
        }
        $groups[] = ['key' => 'carga', 'title' => 'Carga horária', 'cols' => false, 'options' => $h];

        $m = [];
        foreach (MODALITIES as $id => $label) {
            $test = static fn ($c) => $c['modality'] === $id;
            if ($exists($test)) {
                $m[] = $option('modalidade', $id, $label, $test);
            }
        }
        $groups[] = ['key' => 'modalidade', 'title' => 'Modalidade', 'cols' => false, 'options' => $m];

        $p = [];
        foreach (self::PRICES as $id => $r) {
            $test = static fn ($c) => self::priceIn($c, [$id]);
            if ($exists($test)) {
                $p[] = $option('preco', $id, $r['label'], $test);
            }
        }
        $groups[] = ['key' => 'preco', 'title' => 'Faixa de preço', 'cols' => false, 'options' => $p];

        $t = [];
        foreach (TRAINING_TYPES as $id => $label) {
            $test = static fn ($c) => $c['training_type'] === $id;
            if ($exists($test)) {
                $t[] = $option('tipo', $id, $label, $test);
            }
        }
        $groups[] = ['key' => 'tipo', 'title' => 'Tipo de treinamento', 'cols' => false, 'options' => $t];

        return $groups;
    }

    /** Filtros ativos como "chips" removíveis. */
    private static function chips(array $f): array
    {
        $chips = [];
        if ($f['q'] !== '') {
            $chips[] = ['label' => '“' . $f['q'] . '”', 'query' => self::queryFor($f, ['q' => '', 'pagina' => 1])];
        }
        $remove = static fn (string $group, string $value) => self::queryFor($f, [
            $group => array_values(array_filter($f[$group], static fn ($v) => (string) $v !== $value)),
            'pagina' => 1,
        ]);
        foreach ($f['nr'] as $nr) {
            $chips[] = ['label' => 'NR ' . $nr, 'query' => $remove('nr', (string) $nr)];
        }
        $catNames = array_column(Category::active(), 'name', 'slug');
        foreach ($f['categoria'] as $slug) {
            $chips[] = ['label' => $catNames[$slug] ?? $slug, 'query' => $remove('categoria', $slug)];
        }
        foreach ($f['carga'] as $id) {
            $chips[] = ['label' => self::HOURS[$id]['label'], 'query' => $remove('carga', $id)];
        }
        foreach ($f['modalidade'] as $id) {
            $chips[] = ['label' => MODALITIES[$id], 'query' => $remove('modalidade', $id)];
        }
        foreach ($f['preco'] as $id) {
            $chips[] = ['label' => self::PRICES[$id]['label'], 'query' => $remove('preco', $id)];
        }
        foreach ($f['tipo'] as $id) {
            $chips[] = ['label' => TRAINING_TYPES[$id], 'query' => $remove('tipo', $id)];
        }
        return $chips;
    }
}
