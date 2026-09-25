<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Aplica os arquivos database/migrations/*.sql ainda não executados, em ordem. */
final class Migrator
{
    public static function pending(): array
    {
        self::ensureTable();
        $applied = array_column(Database::select('SELECT filename FROM migrations'), 'filename');
        $files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
        sort($files);
        return array_values(array_filter($files, static fn ($f) => !in_array(basename($f), $applied, true)));
    }

    /** @return string[] arquivos aplicados nesta execução */
    public static function run(?callable $output = null): array
    {
        $done = [];
        foreach (self::pending() as $file) {
            $output && $output('Aplicando ' . basename($file) . '...');
            foreach (self::statements((string) file_get_contents($file)) as $sql) {
                Database::pdo()->exec($sql);
            }
            Database::insert('migrations', ['filename' => basename($file)]);
            $done[] = basename($file);
        }
        return $done;
    }

    private static function ensureTable(): void
    {
        Database::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                filename VARCHAR(190) NOT NULL UNIQUE,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /** Divide o arquivo em comandos (";" no fim da linha), ignorando comentários "--". */
    private static function statements(string $sql): array
    {
        $lines = array_filter(
            preg_split('/\R/', $sql) ?: [],
            static fn ($l) => !str_starts_with(ltrim($l), '--')
        );
        $parts = preg_split('/;\s*$/m', implode("\n", $lines)) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn ($s) => $s !== ''));
    }
}
