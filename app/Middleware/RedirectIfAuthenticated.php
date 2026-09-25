<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;

final class RedirectIfAuthenticated
{
    public function handle(Request $request, callable $next, ?string $param): Response
    {
        if (Auth::check() && !$request->wantsJson()) {
            return Response::redirect(Auth::homePath());
        }
        return $next($request);
    }
}
