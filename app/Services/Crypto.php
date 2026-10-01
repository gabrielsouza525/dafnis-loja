<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Criptografia de dados guardados no banco (ex.: chave da verificação em duas etapas), AES-256-GCM.
 * Chave: APP_KEY do .env ou, sem ela, storage/app.key (criada na primeira vez, fora do Git).
 * Perder a chave impede ler o que foi guardado: quem usa a verificação em duas etapas precisa ativar de novo.
 */
final class Crypto
{
    private const PREFIX = 'v1:';

    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Não foi possível criptografar.');
        }
        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    /** null quando o texto foi adulterado ou a chave mudou. */
    public static function decrypt(string $payload): ?string
    {
        if (!str_starts_with($payload, self::PREFIX)) {
            return null;
        }
        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? null : $plain;
    }

    private static function key(): string
    {
        static $key = null;
        if ($key !== null) {
            return $key;
        }
        $secret = (string) env('APP_KEY', '');
        if ($secret === '') {
            $file = BASE_PATH . '/storage/app.key';
            // 'x' cria só se não existir: duas requisições ao mesmo tempo não geram chaves diferentes
            $handle = @fopen($file, 'x');
            if ($handle) {
                fwrite($handle, base64_encode(random_bytes(32)));
                fclose($handle);
                @chmod($file, 0600);
            }
            $secret = trim((string) @file_get_contents($file));
            if ($secret === '') {
                throw new RuntimeException('Defina APP_KEY no .env (não foi possível criar storage/app.key).');
            }
        }
        return $key = hash('sha256', $secret, true);
    }
}
