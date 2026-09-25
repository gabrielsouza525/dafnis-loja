<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function verify(Request $request): bool
    {
        $sent = $request->input('_token') ?? $request->header('X-CSRF-Token');
        return is_string($sent) && $sent !== '' && hash_equals(self::token(), $sent);
    }
}
