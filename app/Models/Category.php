<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Category
{
    private static ?array $cache = null;

    public const TONES = ['a' => 'Azul-marinho', 'b' => 'Azul-aço', 'c' => 'Azul-royal', 'd' => 'Grafite'];

    /** Categorias ativas com a quantidade de cursos ativos. */
    public static function active(): array
    {
        if (self::$cache === null) {
            self::$cache = Database::select(
                'SELECT k.*, (SELECT COUNT(*) FROM courses c WHERE c.category_id = k.id AND c.is_active = 1) AS course_count
                   FROM categories k WHERE k.is_active = 1 ORDER BY k.sort_order, k.name'
            );
            foreach (self::$cache as &$cat) {
                $cat['id'] = (int) $cat['id'];
                $cat['course_count'] = (int) $cat['course_count'];
                $cat['url'] = url('/categorias/' . $cat['slug']);
            }
            unset($cat);
        }
        return self::$cache;
    }

    public static function findActiveBySlug(string $slug): ?array
    {
        foreach (self::active() as $cat) {
            if ($cat['slug'] === $slug) {
                return $cat;
            }
        }
        return null;
    }

    public static function all(): array
    {
        return Database::select(
            'SELECT k.*, (SELECT COUNT(*) FROM courses c WHERE c.category_id = k.id) AS course_count
               FROM categories k ORDER BY k.sort_order, k.name'
        );
    }

    public static function options(): array
    {
        return array_column(Database::select('SELECT id, name FROM categories ORDER BY sort_order, name'), 'name', 'id');
    }

    public static function flushCache(): void
    {
        self::$cache = null;
    }
}
