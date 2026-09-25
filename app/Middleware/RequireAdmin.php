<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;

final class RequireAdmin
{
    public function handle(Request $request, callable $next, ?string $param): Response
    {
        if (!Auth::isAdmin()) {
            // 404 em vez de 403: não confirma a existência do painel para alunos.
            throw new HttpException(404);
        }
        return $next($request);
    }
}
