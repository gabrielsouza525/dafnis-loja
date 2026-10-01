<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Códigos de 6 dígitos que mudam a cada 30 segundos (TOTP, RFC 6238), os mesmos do
 * Google Authenticator, Microsoft Authenticator e similares. Chave em base32 (RFC 4648).
 */
final class Totp
{
    public const DIGITS = 6;
    public const PERIOD = 30;
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Chave nova de 160 bits, em base32 (32 caracteres). */
    public static function newSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function code(string $secret, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);
        return str_pad((string) ($value % 10 ** self::DIGITS), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Confere o código aceitando 30 s de diferença no relógio do celular. Um passo já usado
     * ($lastStep) não vale de novo, para o mesmo código não servir duas vezes.
     * @return int|null o passo que conferiu
     */
    public static function verify(string $secret, string $code, ?int $lastStep, ?int $now = null): ?int
    {
        $code = preg_replace('/\D/', '', $code) ?? '';
        if (strlen($code) !== self::DIGITS) {
            return null;
        }
        $current = intdiv($now ?? time(), self::PERIOD);
        foreach ([0, -1, 1] as $drift) {
            $step = $current + $drift;
            if ($lastStep !== null && $step <= $lastStep) {
                continue;
            }
            if (hash_equals(self::code($secret, $step), $code)) {
                return $step;
            }
        }
        return null;
    }

    /** Endereço lido pelo aplicativo no QR code. */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account)
            . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer);
    }

    public static function base32Encode(string $binary): string
    {
        $out = '';
        $buffer = 0;
        $bits = 0;
        foreach (str_split($binary) as $char) {
            $buffer = ($buffer << 8) | ord($char);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $out .= self::ALPHABET[($buffer >> $bits) & 31];
            }
        }
        if ($bits > 0) {
            $out .= self::ALPHABET[($buffer << (5 - $bits)) & 31];
        }
        return $out;
    }

    public static function base32Decode(string $text): string
    {
        $text = strtoupper(preg_replace('/[\s=-]/', '', $text) ?? '');
        $out = '';
        $buffer = 0;
        $bits = 0;
        foreach (str_split($text) as $char) {
            $value = strpos(self::ALPHABET, $char);
            if ($value === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $value;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $out .= chr(($buffer >> $bits) & 0xFF);
            }
        }
        return $out;
    }
}
