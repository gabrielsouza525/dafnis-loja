<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\View;
use App\Services\Auth;

abstract class Controller
{
    /** Layout padrão da área (site, painel do cliente ou admin). */
    protected string $layout = 'layouts/site';

    public function __construct(protected Request $request)
    {
    }

    protected function view(string $template, array $data = [], ?string $layout = null): Response
    {
        return Response::html(View::render($template, $data, $layout ?? $this->layout));
    }

    protected function redirect(string $to): Response
    {
        return Response::redirect($to);
    }

    /** Volta para a página anterior (mesmo domínio) ou para o destino informado. */
    protected function back(string $fallback = '/'): Response
    {
        $referer = $this->request->header('Referer');
        if ($referer && parse_url($referer, PHP_URL_HOST) === explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]) {
            return Response::redirect($referer);
        }
        return Response::redirect($fallback);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function success(string $message, string $to, array $extra = []): Response
    {
        if ($this->request->wantsJson()) {
            return Response::json(array_merge(['ok' => true, 'message' => $message, 'redirect' => url($to)], $extra));
        }
        flash('success', $message);
        return Response::redirect($to);
    }

    protected function validate(array $rules, array $labels = [], array $messages = []): array
    {
        return Validator::validate($this->request->all(), $rules, $labels, $messages);
    }

    protected function authorize(string $permission): void
    {
        if (!Auth::can($permission)) {
            throw new HttpException(403, 'Seu perfil não tem permissão para esta ação.');
        }
    }

    protected function notFound(string $message = 'Registro não encontrado.'): never
    {
        throw new HttpException(404, $message);
    }

    /** Garante que o registro existe (404 caso contrário). */
    protected function findOr404(?array $row, string $message = 'Registro não encontrado.'): array
    {
        if ($row === null) {
            $this->notFound($message);
        }
        return $row;
    }

    protected function pageNumber(): int
    {
        return max(1, $this->request->int('pagina', 1));
    }
}
