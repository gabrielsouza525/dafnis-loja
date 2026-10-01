<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use App\Core\ValidationException;

/**
 * Verificação em duas etapas: depois da senha, o código de 6 dígitos do aplicativo autenticador
 * (ou um código de recuperação, que vale uma vez). Opcional para cada conta.
 */
final class TwoFactor
{
    public const RECOVERY_COUNT = 10;
    private const PENDING_MINUTES = 10;
    private const CODES_MINUTES = 15;
    private const RECOVERY_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

    public static function enabled(?array $user): bool
    {
        return !empty($user['two_factor_enabled_at']);
    }

    /** Obrigatória para a equipe (perfil admin): o painel só abre com ela ativa, e não dá para desativar a própria. */
    public static function required(?array $user): bool
    {
        return ($user['role'] ?? null) === 'admin';
    }

    public static function issuer(): string
    {
        return (string) env('APP_NAME', 'Dafnis Treinamentos');
    }

    // Ativação -------------------------------------------------------------

    /** Chave da configuração em andamento (fica na sessão até a pessoa confirmar um código). */
    public static function setupSecret(): string
    {
        $secret = Session::get('2fa_setup');
        if (!is_string($secret) || $secret === '') {
            $secret = Totp::newSecret();
            Session::set('2fa_setup', $secret);
        }
        return $secret;
    }

    /** @return string[] códigos de recuperação em texto (mostrados uma vez) */
    public static function enable(array $user, string $code): array
    {
        $secret = Session::get('2fa_setup');
        if (!is_string($secret) || $secret === '') {
            throw ValidationException::with('code', 'A configuração expirou. Leia o QR code de novo.');
        }
        $step = Totp::verify($secret, $code, null);
        if ($step === null) {
            throw ValidationException::with('code', 'Código incorreto. Confira o aplicativo e digite o código que aparece agora.');
        }
        $codes = self::newRecoveryCodes();
        Database::update('users', [
            'two_factor_secret' => Crypto::encrypt($secret),
            'two_factor_enabled_at' => date('Y-m-d H:i:s'),
            'two_factor_recovery' => json_encode(array_map([self::class, 'hashRecovery'], $codes)),
            'two_factor_last_step' => $step,
        ], ['id' => $user['id']]);
        Session::forget('2fa_setup');
        self::rememberCodes($codes, 'enabled');
        // Quem ativa pode estar desconfiando da senha: os aparelhos lembrados entram de novo
        Auth::forgetAllDevices((int) $user['id']);
        Activity::log('2fa.enabled', 'user', (int) $user['id']);
        Notify::twoFactorChanged($user, true);
        return $codes;
    }

    public static function disable(array $user, bool $byTeam = false): void
    {
        Database::update('users', [
            'two_factor_secret' => null,
            'two_factor_enabled_at' => null,
            'two_factor_recovery' => null,
            'two_factor_last_step' => null,
        ], ['id' => $user['id']]);
        Activity::log('2fa.disabled', 'user', (int) $user['id'], $byTeam ? 'pela equipe' : null);
        Notify::twoFactorChanged($user, false, $byTeam);
    }

    /** @return string[] */
    public static function regenerateRecovery(array $user): array
    {
        $codes = self::newRecoveryCodes();
        Database::update('users', ['two_factor_recovery' => json_encode(array_map([self::class, 'hashRecovery'], $codes))], ['id' => $user['id']]);
        self::rememberCodes($codes, 'regenerated');
        Activity::log('2fa.recovery_regenerated', 'user', (int) $user['id']);
        return $codes;
    }

    /** Códigos recém-gerados, para a tela de "guarde estes códigos" (somem em 15 minutos ou ao concluir). */
    public static function freshCodes(): ?array
    {
        $data = Session::get('2fa_codes');
        if (!is_array($data) || time() - (int) ($data['at'] ?? 0) > self::CODES_MINUTES * 60) {
            Session::forget('2fa_codes');
            return null;
        }
        return $data;
    }

    public static function forgetFreshCodes(): void
    {
        Session::forget('2fa_codes');
    }

    public static function recoveryLeft(array $row): int
    {
        return count(json_decode((string) ($row['two_factor_recovery'] ?? '[]'), true) ?: []);
    }

    // Conferência ----------------------------------------------------------

    /** Código do aplicativo para o usuário (linha completa de users). Atualiza o último passo usado. */
    public static function verifyApp(array $row, string $code): bool
    {
        $secret = Crypto::decrypt((string) $row['two_factor_secret']);
        if ($secret === null) {
            return false;
        }
        $last = $row['two_factor_last_step'] !== null ? (int) $row['two_factor_last_step'] : null;
        $step = Totp::verify($secret, $code, $last);
        if ($step === null) {
            return false;
        }
        Database::update('users', ['two_factor_last_step' => $step], ['id' => $row['id']]);
        return true;
    }

    /** Código de recuperação: confere e risca da lista (vale uma vez). */
    public static function useRecoveryCode(array $row, string $code): bool
    {
        $hash = self::hashRecovery($code);
        $hashes = json_decode((string) ($row['two_factor_recovery'] ?? '[]'), true) ?: [];
        foreach ($hashes as $i => $stored) {
            if (hash_equals((string) $stored, $hash)) {
                unset($hashes[$i]);
                Database::update('users', ['two_factor_recovery' => json_encode(array_values($hashes))], ['id' => $row['id']]);
                Activity::log('2fa.recovery_used', 'user', (int) $row['id'], 'restam ' . count($hashes));
                return true;
            }
        }
        return false;
    }

    /** Aceita o código do aplicativo (6 dígitos) ou um de recuperação. */
    public static function verifyAny(array $row, string $code): bool
    {
        $digits = preg_replace('/\D/', '', $code) ?? '';
        return strlen($digits) === Totp::DIGITS && $digits === trim($code)
            ? self::verifyApp($row, $digits)
            : self::useRecoveryCode($row, $code);
    }

    // Login em duas etapas -------------------------------------------------

    public static function beginLogin(array $user, bool $remember, ?string $volta): void
    {
        Session::regenerate();
        Session::set('2fa_pending', ['user_id' => (int) $user['id'], 'remember' => $remember, 'volta' => $volta, 'at' => time()]);
    }

    public static function pendingLogin(): ?array
    {
        $pending = Session::get('2fa_pending');
        if (!is_array($pending) || time() - (int) ($pending['at'] ?? 0) > self::PENDING_MINUTES * 60) {
            Session::forget('2fa_pending');
            return null;
        }
        return $pending;
    }

    public static function forgetPendingLogin(): void
    {
        Session::forget('2fa_pending');
    }

    // Auxiliares -----------------------------------------------------------

    /** @return string[] no formato "abcde-fghij" */
    private static function newRecoveryCodes(): array
    {
        $codes = [];
        $max = strlen(self::RECOVERY_ALPHABET) - 1;
        while (count($codes) < self::RECOVERY_COUNT) {
            $code = '';
            for ($i = 0; $i < 10; $i++) {
                $code .= self::RECOVERY_ALPHABET[random_int(0, $max)];
            }
            $codes[substr($code, 0, 5) . '-' . substr($code, 5)] = true;
        }
        return array_keys($codes);
    }

    private static function hashRecovery(string $code): string
    {
        return hash('sha256', strtolower(preg_replace('/[^a-z0-9]/i', '', $code) ?? ''));
    }

    private static function rememberCodes(array $codes, string $reason): void
    {
        Session::set('2fa_codes', ['codes' => $codes, 'reason' => $reason, 'at' => time()]);
    }
}
