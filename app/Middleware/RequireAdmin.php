<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;
use App\Services\TwoFactor;

final class RequireAdmin
{
    public function handle(Request $request, callable $next, ?string $param): Response
    {
        if (!Auth::isAdmin()) {
            // 404 em vez de 403: não confirma a existência do painel para alunos.
            throw new HttpException(404);
        }
        // Equipe sem a verificação em duas etapas: o painel só abre depois de ativar
        if (TwoFactor::required(Auth::user()) && !TwoFactor::enabled(Auth::user())) {
            if ($request->wantsJson()) {
                throw new HttpException(403, 'Ative a verificação em duas etapas para usar o painel.');
            }
            flash('info', 'Para abrir o painel, ative a verificação em duas etapas. Ela é obrigatória para a equipe.');
            return Response::redirect('/minha-conta/duas-etapas');
        }
        return $next($request);
    }
}
