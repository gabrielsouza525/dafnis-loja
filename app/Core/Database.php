<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use Throwable;

/**
 * Acesso ao banco via PDO com prepared statements reais (sem emulação).
 * Nomes de tabela/coluna recebidos por insert()/update() vêm sempre do código,
 * nunca da entrada do usuário.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                env('DB_HOST', '127.0.0.1'),
                env('DB_PORT', '3306'),
                env('DB_DATABASE', 'dafnis')
            );
            try {
                self::$pdo = new PDO($dsn, (string) env('DB_USERNAME', 'root'), (string) env('DB_PASSWORD', ''), [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                ]);
            } catch (PDOException $e) {
                throw new DatabaseUnavailable('Não foi possível conectar ao banco de dados.', 0, $e);
            }
            // Mantém NOW()/CURDATE() do MySQL no mesmo fuso do PHP.
            self::$pdo->exec("SET time_zone = '" . date('P') . "'");
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public static function select(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function first(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $value = self::query($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public static function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn ($c) => "`$c`", $columns)),
            implode(', ', array_map(static fn ($c) => ":$c", $columns))
        );
        self::query($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    /** @param array $where coluna => valor, combinados com AND */
    public static function update(string $table, array $data, array $where): int
    {
        $set = implode(', ', array_map(static fn ($c) => "`$c` = :set_$c", array_keys($data)));
        $cond = implode(' AND ', array_map(static fn ($c) => "`$c` = :where_$c", array_keys($where)));
        $params = [];
        foreach ($data as $k => $v) {
            $params["set_$k"] = $v;
        }
        foreach ($where as $k => $v) {
            $params["where_$k"] = $v;
        }
        return self::query("UPDATE `$table` SET $set WHERE $cond", $params)->rowCount();
    }

    public static function delete(string $table, array $where): int
    {
        $cond = implode(' AND ', array_map(static fn ($c) => "`$c` = :$c", array_keys($where)));
        return self::query("DELETE FROM `$table` WHERE $cond", $where)->rowCount();
    }

    /**
     * Executa o callback numa transação. Transações aninhadas reaproveitam a externa.
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) {
            return $callback();
        }
        $pdo->beginTransaction();
        try {
            $result = $callback();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Monta "IN (:p0, :p1...)" com os parâmetros correspondentes. */
    public static function in(array $values, string $prefix = 'in'): array
    {
        $placeholders = [];
        $params = [];
        foreach (array_values($values) as $i => $v) {
            $placeholders[] = ":{$prefix}{$i}";
            $params["{$prefix}{$i}"] = $v;
        }
        return ['(' . implode(', ', $placeholders ?: ['NULL']) . ')', $params];
    }
}
