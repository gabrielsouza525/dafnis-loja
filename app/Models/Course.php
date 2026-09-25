<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Cursos do catálogo. present() acrescenta os campos que as views usam
 * (rótulos, preço final, selo, capa), para nenhuma view recalcular regra de preço.
 */
final class Course
{
    public const BADGES = ['mais' => 'Mais vendido', 'oferta' => 'Oferta', 'novo' => 'Novo'];

    private static ?array $activeCache = null;

    private const SELECT = 'SELECT c.*, k.slug AS category_slug, k.name AS category_name, k.tone AS category_tone
                              FROM courses c LEFT JOIN categories k ON k.id = c.category_id';

    /** Todos os cursos ativos já apresentados, indexados por id (cache da requisição). */
    public static function allActive(): array
    {
        if (self::$activeCache === null) {
            self::$activeCache = [];
            foreach (Database::select(self::SELECT . ' WHERE c.is_active = 1 ORDER BY c.nr_number IS NULL, c.nr_number, c.title') as $row) {
                self::$activeCache[(int) $row['id']] = self::present($row);
            }
        }
        return self::$activeCache;
    }

    public static function flushCache(): void
    {
        self::$activeCache = null;
    }

    public static function findActiveBySlug(string $slug): ?array
    {
        foreach (self::allActive() as $course) {
            if ($course['slug'] === $slug) {
                return $course;
            }
        }
        return null;
    }

    public static function findActive(int $id): ?array
    {
        return self::allActive()[$id] ?? null;
    }

    /** Busca qualquer curso (inclusive inativo), para o admin. */
    public static function find(int $id): ?array
    {
        $row = Database::first(self::SELECT . ' WHERE c.id = :id', ['id' => $id]);
        return $row ? self::present($row) : null;
    }

    public static function featured(int $limit = 8): array
    {
        $list = array_filter(self::allActive(), static fn ($c) => $c['featured_order'] !== null);
        usort($list, static fn ($a, $b) => $a['featured_order'] <=> $b['featured_order']);
        return array_slice($list, 0, $limit);
    }

    public static function bestsellers(int $limit = 4): array
    {
        $list = array_values(array_filter(self::allActive(), static fn ($c) => $c['is_bestseller']));
        return array_slice($list, 0, $limit);
    }

    /** Mesma categoria primeiro, depois destaques; nunca o próprio curso. */
    public static function related(array $course, int $limit = 4): array
    {
        $all = self::allActive();
        $sameNr = array_filter($all, static fn ($c) => $c['id'] !== $course['id'] && $course['nr_number'] && $c['nr_number'] === $course['nr_number'] && !$c['is_simulator']);
        $sameCat = array_filter($all, static fn ($c) => $c['id'] !== $course['id'] && $c['category_id'] === $course['category_id']);
        $featured = array_filter($all, static fn ($c) => $c['id'] !== $course['id'] && $c['featured_order'] !== null);
        $out = [];
        foreach ([$sameNr, $sameCat, $featured] as $group) {
            foreach ($group as $c) {
                $out[$c['id']] = $c;
                if (count($out) >= $limit) {
                    return array_values($out);
                }
            }
        }
        return array_values($out);
    }

    /** Índice de NRs com a quantidade de treinamentos (menu NRs, atalhos e filtro). */
    public static function nrIndex(): array
    {
        $names = self::NR_NAMES;
        $counts = [];
        foreach (self::allActive() as $c) {
            if ($c['nr_number']) {
                $counts[$c['nr_number']] = ($counts[$c['nr_number']] ?? 0) + 1;
            }
        }
        ksort($counts);
        $out = [];
        foreach ($counts as $nr => $count) {
            $out[] = ['nr' => $nr, 'code' => 'NR ' . $nr, 'name' => $names[$nr] ?? '', 'count' => $count, 'url' => url('/nr/' . $nr)];
        }
        return $out;
    }

    /** Nome oficial resumido de cada NR (texto da própria norma, não do curso). */
    public const NR_NAMES = [
        1 => 'Disposições Gerais e GRO', 5 => 'CIPA', 6 => 'EPI', 10 => 'Eletricidade', 11 => 'Transporte e Movimentação de Materiais',
        12 => 'Máquinas e Equipamentos', 13 => 'Caldeiras e Vasos de Pressão', 15 => 'Atividades Insalubres', 17 => 'Ergonomia',
        18 => 'Indústria da Construção', 20 => 'Inflamáveis e Combustíveis', 22 => 'Mineração', 23 => 'Proteção Contra Incêndios',
        26 => 'Sinalização de Segurança', 31 => 'Trabalho Rural', 32 => 'Serviços de Saúde', 33 => 'Espaços Confinados',
        34 => 'Trabalho a Quente', 35 => 'Trabalho em Altura', 37 => 'Plataformas de Petróleo',
    ];

    public static function present(array $c): array
    {
        $c['id'] = (int) $c['id'];
        $c['category_id'] = $c['category_id'] !== null ? (int) $c['category_id'] : null;
        $c['nr_number'] = $c['nr_number'] !== null ? (int) $c['nr_number'] : null;
        $c['hours'] = (int) $c['hours'];
        $c['featured_order'] = $c['featured_order'] !== null ? (int) $c['featured_order'] : null;
        foreach (['is_active', 'is_bestseller', 'is_new', 'practical_required', 'certificate'] as $flag) {
            $c[$flag] = (bool) (int) $c[$flag];
        }

        $price = $c['price'] !== null ? (float) $c['price'] : null;
        $promo = $c['promo_price'] !== null ? (float) $c['promo_price'] : null;
        $hasPromo = $price !== null && $promo !== null && $promo < $price;
        $c['list_price'] = $price;
        $c['final_price'] = $hasPromo ? $promo : $price;
        $c['old_price'] = $hasPromo ? $price : null;
        $c['discount_pct'] = $hasPromo ? (int) round((1 - $promo / $price) * 100) : 0;
        $c['has_price'] = $price !== null;

        $badge = $c['is_bestseller'] ? 'mais' : ($hasPromo ? 'oferta' : ($c['is_new'] ? 'novo' : null));
        $c['badge'] = $badge;
        $c['badge_label'] = $badge ? self::BADGES[$badge] : null;

        $c['is_simulator'] = ($c['category_slug'] ?? '') === 'simuladores-e-jogos';
        $c['code_label'] = $c['code'] ?: ($c['nr_number'] ? 'NR ' . $c['nr_number'] : 'Complementar');
        $c['kicker'] = $c['nr_number'] ? 'Norma Regulamentadora' : 'Curso complementar';
        $useNrPlate = $c['nr_number'] && !$c['is_simulator'];
        $c['plate_big'] = match (true) {
            $useNrPlate => $c['code'] ?: 'NR ' . $c['nr_number'],
            (bool) $c['short_title'] => $c['short_title'],
            $c['is_simulator'] => 'Simulador' . ($c['nr_number'] ? ' NR ' . $c['nr_number'] : ''),
            default => $c['title'],
        };
        $c['plate_is_text'] = !$useNrPlate;
        $c['tone'] = $c['category_tone'] ?? 'a';
        $c['hours_label'] = hours_short($c['hours']);
        $c['hours_long'] = hours_long($c['hours']);
        $c['modality_label'] = modality_label($c['modality']);
        $c['type_label'] = TRAINING_TYPES[$c['training_type']] ?? TRAINING_TYPES['inicial'];
        $c['url'] = url('/cursos/' . $c['slug']);
        $c['display_title'] = ($c['nr_number'] && !$c['is_simulator'] ? $c['code_label'] . ' — ' : '') . $c['title'];
        $c['image_url'] = $c['image_path'] ? url('/uploads/' . ltrim($c['image_path'], '/')) : null;
        return $c;
    }

    /** Módulos do conteúdo programático (JSON no banco). */
    public static function syllabus(array $course): array
    {
        $data = json_decode((string) ($course['syllabus'] ?? ''), true);
        if (!is_array($data)) {
            return [];
        }
        return array_values(array_filter(array_map(static fn ($m) => [
            'title' => trim((string) ($m['title'] ?? '')),
            'hours' => trim((string) ($m['hours'] ?? '')),
            'topics' => trim((string) ($m['topics'] ?? '')),
        ], $data), static fn ($m) => $m['title'] !== ''));
    }

    /** Uma frase para o topo da página e para o Google, montada só com dados cadastrados. */
    public static function shortSummary(array $c): string
    {
        $what = $c['training_type'] === 'periodico' ? 'Reciclagem periódica' : 'Treinamento';
        $hours = $c['hours_long'] . ($c['hours_note'] ? ' ' . $c['hours_note'] : '');
        return match ($c['modality']) {
            'semipresencial' => $what . ' semipresencial de ' . $hours . ': conteúdo online e parte prática presencial obrigatória.',
            'presencial' => $what . ' presencial de ' . $hours . ($c['certificate'] ? ', com certificado de conclusão.' : '.'),
            default => $what . ' online de ' . $hours . ($c['certificate'] ? ', com certificado de conclusão.' : '.'),
        };
    }

    /** Texto de apresentação montado só com dados cadastrados, quando não há descrição própria. */
    public static function factualSummary(array $c): string
    {
        $parts = [];
        $parts[] = ($c['nr_number'] && !$c['is_simulator'] ? 'Treinamento da ' . $c['code_label'] . ' — ' : 'Treinamento ') . $c['title'] . '.';
        $mode = match ($c['modality']) {
            'semipresencial' => 'semipresencial (conteúdo online e parte prática presencial)',
            'presencial' => 'presencial',
            default => 'online',
        };
        $parts[] = 'Modalidade ' . $mode . ', com carga horária de ' . $c['hours_long'] . ($c['hours_note'] ? ' ' . $c['hours_note'] : '') . '.';
        if ($c['certificate']) {
            $parts[] = 'Certificado de conclusão ao cumprir os critérios de aprovação do curso.';
        }
        return implode(' ', $parts);
    }
}
