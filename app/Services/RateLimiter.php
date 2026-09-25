<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;

/**
 * Limita tentativas (login, cadastro, recuperação de senha) por identificador e por IP.
 */
final class RateLimiter
{
    public static function check(string $scope, string $identifier, string $ip, int $maxPerIdentifier, int $maxPerIp, int $minutes): void
    {
        $since = date('Y-m-d H:i:s', time() - $minutes * 60);
        $byIdentifier = (int) Database::value(
            'SELECT COUNT(*) FROM login_attempts WHERE scope = :s AND identifier = :i AND success = 0 AND created_at >= :since',
            ['s' => $scope, 'i' => $identifier, 'since' => $since]
        );
        $byIp = (int) Database::value(
            'SELECT COUNT(*) FROM login_attempts WHERE scope = :s AND ip = :ip AND success = 0 AND created_at >= :since',
            ['s' => $scope, 'ip' => $ip, 'since' => $since]
        );
        if ($byIdentifier >= $maxPerIdentifier || $byIp >= $maxPerIp) {
            throw new HttpException(429, "Muitas tentativas. Aguarde $minutes minutos e tente de novo.");
        }
    }

    public static function hit(string $scope, string $identifier, string $ip, bool $success = false): void
    {
        Database::insert('login_attempts', [
            'scope' => $scope,
            'identifier' => mb_substr($identifier, 0, 190),
            'ip' => $ip,
            'success' => $success ? 1 : 0,
        ]);
        // Limpeza ocasional para a tabela não crescer sem fim.
        if (random_int(1, 50) === 1) {
            Database::query('DELETE FROM login_attempts WHERE created_at < :old', ['old' => date('Y-m-d H:i:s', strtotime('-7 days'))]);
        }
    }

    /** Após login bem-sucedido, zera as falhas daquele e-mail. */
    public static function clear(string $scope, string $identifier): void
    {
        Database::query('DELETE FROM login_attempts WHERE scope = :s AND identifier = :i AND success = 0', ['s' => $scope, 'i' => $identifier]);
    }
}
