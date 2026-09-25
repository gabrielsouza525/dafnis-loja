<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\Auth;
use Throwable;

final class App
{
    private static ?Request $request = null;

    public static function request(): ?Request
    {
        return self::$request;
    }

    public function run(): void
    {
        $request = Request::capture();
        self::$request = $request;

        try {
            Session::start($request);
            $response = $this->handle($request);
        } catch (Throwable $e) {
            $response = ErrorHandler::toResponse($e, $request);
        }

        $this->secureHeaders($response, $request);
        $response->send();
    }

    private function handle(Request $request): Response
    {
        if ($request->method === 'POST') {
            $limit = ini_bytes((string) ini_get('post_max_size'));
            if ($limit > 0 && $request->contentLength() > $limit) {
                throw new HttpException(413, 'O envio ultrapassou o tamanho máximo permitido.');
            }
            // Webhooks vêm do gateway de pagamento, sem sessão: a autenticidade é
            // conferida pela assinatura e por uma consulta direta à API do gateway.
            if (!str_starts_with($request->path, '/webhooks/') && !Csrf::verify($request)) {
                throw new HttpException(419);
            }
        }

        Auth::restoreFromRememberCookie($request);

        $router = new Router();
        (require BASE_PATH . '/routes/web.php')($router);
        return $router->dispatch($request);
    }

    private function secureHeaders(Response $response, Request $request): void
    {
        $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()')
            ->withHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->withHeader('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "img-src 'self' data: blob:",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "font-src 'self' https://fonts.gstatic.com",
                "script-src 'self'",
                "connect-src 'self'",
                "frame-src 'none'",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "object-src 'none'",
            ]));

        if ($request->isSecure()) {
            $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        // Páginas com dados pessoais (ou com o carrinho no cabeçalho) não podem ficar em cache.
        $personal = isset($_SESSION['user_id']) || !empty($_SESSION['cart']['items']);
        if ($personal && $response->header('Cache-Control') === null) {
            $response->withHeader('Cache-Control', 'no-store, private');
        }
    }
}
