<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

/**
 * Configurações da loja (tabela settings), carregadas uma vez por requisição.
 * Campo vazio significa "não informado": a loja esconde, nunca inventa.
 */
final class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            try {
                foreach (Database::select('SELECT `key`, `value` FROM settings') as $row) {
                    self::$cache[$row['key']] = $row['value'];
                }
            } catch (Throwable) {
                // Banco indisponível: a página de erro ainda precisa renderizar.
            }
        }
        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::all()[$key] ?? null;
        return ($value === null || $value === '') ? $default : $value;
    }

    public static function json(string $key, array $default = []): array
    {
        $decoded = json_decode((string) self::get($key, ''), true);
        return is_array($decoded) ? $decoded : $default;
    }

    public static function set(string $key, ?string $value): void
    {
        Database::query(
            'INSERT INTO settings (`key`, `value`) VALUES (:k, :v) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)',
            ['k' => $key, 'v' => $value]
        );
        self::$cache = null;
    }

    public static function businessName(): string
    {
        return (string) self::get('business.name', 'Dafnis Soluções em EPI');
    }

    public static function cityLine(): ?string
    {
        $city = self::get('business.city');
        $state = self::get('business.state');
        return $city ? $city . ($state ? ', ' . $state : '') : null;
    }

    /** Perguntas frequentes da home: [{q, a}]. */
    public static function faq(): array
    {
        return array_values(array_filter(
            self::json('content.faq'),
            static fn ($item) => is_array($item) && trim((string) ($item['q'] ?? '')) !== '' && trim((string) ($item['a'] ?? '')) !== ''
        ));
    }

    /** Indicadores da seção "Para empresas" preenchidos no admin (os vazios não aparecem). */
    public static function stats(): array
    {
        $labels = [
            'stats.companies' => 'Empresas atendidas',
            'stats.professionals' => 'Profissionais capacitados',
            'stats.years' => 'Anos de atuação',
        ];
        $out = [];
        foreach ($labels as $key => $label) {
            if ($value = self::get($key)) {
                $out[] = ['n' => $value, 'label' => $label];
            }
        }
        return $out;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
