<?php
declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

final class ErrorHandler
{
    public static function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(static function (Throwable $e): void {
            self::toResponse($e, null)->send();
        });

        // Erros fatais (fora do alcance de try/catch) também são registrados e
        // viram uma página de erro, em vez de uma tela em branco.
        register_shutdown_function(static function (): void {
            $error = error_get_last();
            if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }
            Logger::error('Erro fatal: ' . $error['message'], ['file' => $error['file'] . ':' . $error['line']]);
            if (PHP_SAPI === 'cli') {
                return;
            }
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=UTF-8');
            }
            try {
                echo View::file('errors/page', [
                    'status' => 500,
                    'message' => 'Algo deu errado do nosso lado. O erro foi registrado e vamos verificar.',
                    'debug' => null,
                ]);
            } catch (Throwable) {
                echo 'Erro interno.';
            }
        });
    }

    public static function toResponse(Throwable $e, ?Request $request): Response
    {
        $wantsJson = $request?->wantsJson() ?? false;

        if ($e instanceof ValidationException) {
            if ($wantsJson) {
                return Response::json(['ok' => false, 'message' => $e->getMessage(), 'errors' => $e->errors], 422);
            }
            Session::flash('_errors', $e->errors);
            Session::flash('_old', self::safeOldInput($request));
            Session::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
            return Response::redirect(self::backUrl($request));
        }

        if ($e instanceof HttpException) {
            $status = $e->status;
            $message = $e->getMessage();
            if ($status === 401 && !$wantsJson) {
                return Response::redirect('/login?volta=' . rawurlencode($request?->fullUrl() ?? '/'));
            }
            if ($status === 419 && !$wantsJson) {
                Session::flash('toast', ['type' => 'error', 'message' => $message]);
                return Response::redirect(self::backUrl($request));
            }
        } elseif ($e instanceof DatabaseUnavailable) {
            Logger::exception($e, $request);
            $status = 503;
            $message = 'Estamos com uma instabilidade momentânea. Tente novamente em alguns minutos.';
        } else {
            Logger::exception($e, $request);
            $status = 500;
            $message = 'Algo deu errado do nosso lado. O erro foi registrado e vamos verificar.';
        }

        if ($wantsJson) {
            $payload = ['ok' => false, 'message' => $message];
            if ($status === 500 && env('APP_DEBUG', false)) {
                $payload['debug'] = $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
            }
            return Response::json($payload, $status);
        }

        try {
            $html = View::file('errors/page', [
                'status' => $status,
                'message' => $message,
                'debug' => ($status >= 500 && env('APP_DEBUG', false)) ? $e : null,
            ]);
        } catch (Throwable) {
            $html = '<!doctype html><meta charset="utf-8"><title>Erro</title><p>' . e($message) . '</p>';
        }
        $response = Response::html($html, $status);
        if ($status === 503) {
            $response->withHeader('Retry-After', '120');
        }
        return $response;
    }

    private static function backUrl(?Request $request): string
    {
        $referer = $request?->header('Referer');
        if ($referer) {
            $host = parse_url($referer, PHP_URL_HOST);
            $appHost = parse_url((string) env('APP_URL', ''), PHP_URL_HOST);
            $reqHost = $_SERVER['HTTP_HOST'] ?? null;
            $reqHost = $reqHost ? explode(':', $reqHost)[0] : null;
            if ($host && ($host === $appHost || $host === $reqHost)) {
                return $referer;
            }
        }
        return '/';
    }

    /** Devolve a entrada ao formulário, sem senhas nem tokens. */
    private static function safeOldInput(?Request $request): array
    {
        if ($request === null) {
            return [];
        }
        $input = $request->all();
        foreach (array_keys($input) as $key) {
            if (preg_match('/password|senha|_token/i', (string) $key)) {
                unset($input[$key]);
            }
        }
        return $input;
    }
}
