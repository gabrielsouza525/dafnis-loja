<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(Request $request): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $lifetimeMinutes = (int) env('SESSION_LIFETIME', 120);
        $secure = (bool) env('SESSION_SECURE', false) || $request->isSecure();

        $dir = BASE_PATH . '/storage/sessions';
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.gc_maxlifetime', (string) max(1440, $lifetimeMinutes * 60));
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');

        session_name('dafnis_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => base_path_prefix() ?: '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // Expiração por inatividade. Requisições de fundo (polling de notificações)
        // não renovam a sessão, senão uma aba esquecida a manteria viva para sempre.
        $now = time();
        $last = $_SESSION['_last_activity'] ?? $now;
        if (isset($_SESSION['user_id']) && $now - $last > $lifetimeMinutes * 60) {
            $_SESSION = ['_expired' => true];
            session_regenerate_id(true);
        }
        if (($request->header('X-Background') ?? '') !== '1') {
            $_SESSION['_last_activity'] = $now;
        }

        // Flash: o que foi gravado na requisição anterior fica disponível nesta.
        $_SESSION['_flash_old'] = $_SESSION['_flash_new'] ?? [];
        $_SESSION['_flash_new'] = [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $value;
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash_new'][$key] = $value;
    }

    public static function getFlash(string $key, mixed $default = null): mixed
    {
        return $_SESSION['_flash_old'][$key] ?? $default;
    }

    /** Mantém os flashes atuais por mais uma requisição (ex.: redirect encadeado). */
    public static function reflash(): void
    {
        $_SESSION['_flash_new'] = array_merge($_SESSION['_flash_old'] ?? [], $_SESSION['_flash_new'] ?? []);
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $p['path'],
                'secure' => $p['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
    }
}
