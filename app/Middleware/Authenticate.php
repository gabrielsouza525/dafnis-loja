<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Auth;

final class Authenticate
{
    public function handle(Request $request, callable $next, ?string $param): Response
    {
        if (!Auth::check()) {
            $expired = (bool) Session::pull('_expired', false);
            if ($request->wantsJson()) {
                throw new HttpException(401, $expired ? 'Sua sessão expirou. Entre novamente.' : 'Faça login para continuar.');
            }
            Session::flash('toast', [
                'type' => $expired ? 'warning' : 'info',
                'message' => $expired ? 'Sua sessão expirou por inatividade. Entre novamente.' : 'Entre na sua conta para continuar.',
            ]);
            $back = $request->method === 'GET' ? $request->fullUrl() : '/';
            return Response::redirect('/login?volta=' . rawurlencode($back));
        }
        return $next($request);
    }
}
