<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\ValidationException;

/**
 * Autenticação por sessão + "manter conectado" com token selector/validator
 * (o validador só existe no cookie; o banco guarda o hash SHA-256).
 * Perfis: student (aluno/comprador) e admin (equipe Dafnis).
 */
final class Auth
{
    private const REMEMBER_COOKIE = 'dafnis_remember';
    private const REMEMBER_DAYS = 30;

    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = Session::get('user_id');
            if ($id) {
                self::$user = self::findActive((int) $id);
                if (self::$user === null) {
                    // Usuário desativado ou removido durante a sessão.
                    Session::forget('user_id');
                }
            }
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['role'] ?? null) === 'admin';
    }

    /**
     * @throws ValidationException quando as credenciais não conferem
     */
    public static function attempt(string $email, string $password, bool $remember, Request $request): array
    {
        $email = mb_strtolower(trim($email));
        RateLimiter::check('login', $email, $request->ip(), 5, 20, 15);

        $user = Database::first('SELECT * FROM users WHERE email = :e', ['e' => $email]);

        // password_verify roda mesmo sem usuário, para não revelar pelo tempo de resposta quais e-mails existem.
        $hash = $user['password_hash'] ?? password_hash(random_token(12), PASSWORD_DEFAULT);
        $valid = password_verify($password, $hash);

        if (!$user || !$valid) {
            RateLimiter::hit('login', $email, $request->ip());
            throw ValidationException::with('email', 'E-mail ou senha incorretos.');
        }
        if (!(int) $user['is_active']) {
            RateLimiter::hit('login', $email, $request->ip());
            throw ValidationException::with('email', 'Este acesso está desativado. Fale com a nossa equipe.');
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], ['id' => $user['id']]);
        }

        RateLimiter::clear('login', $email);
        RateLimiter::hit('login', $email, $request->ip(), true);
        self::login((int) $user['id'], $remember, $request);
        return self::user();
    }

    public static function login(int $userId, bool $remember, Request $request): void
    {
        Session::regenerate();
        Session::set('user_id', $userId);
        Session::forget('_expired');
        Database::update('users', ['last_login_at' => date('Y-m-d H:i:s')], ['id' => $userId]);
        self::reset();

        if ($remember) {
            self::issueRememberToken($userId, $request);
        }
        Activity::log('login', 'user', $userId);
    }

    /** Sai da conta sem perder o carrinho (a compra pode continuar como visitante). */
    public static function logout(Request $request): void
    {
        $cookie = $request->cookie(self::REMEMBER_COOKIE);
        if ($cookie && str_contains($cookie, ':')) {
            [$selector] = explode(':', $cookie, 2);
            Database::query('DELETE FROM remember_tokens WHERE selector = :s', ['s' => $selector]);
        }
        self::clearRememberCookie();
        $cart = Session::get('cart');
        Session::destroy();
        session_start();
        Session::regenerate();
        if ($cart) {
            Session::set('cart', $cart);
        }
        self::reset();
    }

    /** Encerra todas as sessões lembradas do usuário (troca de senha, desativação). */
    public static function forgetAllDevices(int $userId): void
    {
        Database::query('DELETE FROM remember_tokens WHERE user_id = :u', ['u' => $userId]);
    }

    public static function restoreFromRememberCookie(Request $request): void
    {
        if (Session::get('user_id')) {
            return;
        }
        $cookie = $request->cookie(self::REMEMBER_COOKIE);
        if (!$cookie || !preg_match('/^([A-Za-z0-9_-]{24}):([A-Za-z0-9_-]{43})$/', $cookie, $m)) {
            return;
        }
        [, $selector, $validator] = $m;
        try {
            $row = Database::first(
                'SELECT * FROM remember_tokens WHERE selector = :s AND expires_at > NOW()',
                ['s' => $selector]
            );
        } catch (\App\Core\DatabaseUnavailable) {
            return;
        }
        if (!$row || !hash_equals($row['validator_hash'], hash('sha256', $validator))) {
            if ($row) {
                // Validador errado para um seletor válido: possível roubo de cookie.
                self::forgetAllDevices((int) $row['user_id']);
            }
            self::clearRememberCookie();
            return;
        }
        if (!self::findActive((int) $row['user_id'])) {
            Database::query('DELETE FROM remember_tokens WHERE id = :id', ['id' => $row['id']]);
            self::clearRememberCookie();
            return;
        }
        // Rotaciona o token a cada uso.
        Database::query('DELETE FROM remember_tokens WHERE id = :id', ['id' => $row['id']]);
        Session::regenerate();
        Session::set('user_id', (int) $row['user_id']);
        self::issueRememberToken((int) $row['user_id'], $request);
        self::reset();
    }

    private static function issueRememberToken(int $userId, Request $request): void
    {
        $selector = random_token(18);   // 24 caracteres
        $validator = random_token(32);  // 43 caracteres
        $expires = time() + self::REMEMBER_DAYS * 86400;
        Database::insert('remember_tokens', [
            'user_id' => $userId,
            'selector' => $selector,
            'validator_hash' => hash('sha256', $validator),
            'expires_at' => date('Y-m-d H:i:s', $expires),
            'user_agent' => $request->userAgent(),
        ]);
        setcookie(self::REMEMBER_COOKIE, $selector . ':' . $validator, [
            'expires' => $expires,
            'path' => base_path_prefix() ?: '/',
            'secure' => (bool) env('SESSION_SECURE', false) || $request->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function clearRememberCookie(): void
    {
        setcookie(self::REMEMBER_COOKIE, '', [
            'expires' => time() - 3600,
            'path' => base_path_prefix() ?: '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function findActive(int $id): ?array
    {
        return Database::first(
            'SELECT id, name, email, phone, document, role, is_active, last_login_at, created_at
               FROM users WHERE id = :id AND is_active = 1',
            ['id' => $id]
        );
    }

    public static function reset(): void
    {
        self::$user = null;
        self::$loaded = false;
    }

    /** Destino padrão depois do login. */
    public static function homePath(): string
    {
        return self::isAdmin() ? '/admin' : '/minha-conta';
    }

    /** Aceita só caminhos internos no parâmetro "volta" (evita redirecionamento aberto). */
    public static function safeReturnPath(?string $path): ?string
    {
        $path = (string) $path;
        if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) {
            return null;
        }
        return $path;
    }
}
