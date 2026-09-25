<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status);
    }

    public static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Requisição inválida.',
            401 => 'Faça login para continuar.',
            403 => 'Você não tem permissão para acessar esta página.',
            404 => 'Página não encontrada.',
            405 => 'Método não permitido.',
            413 => 'Arquivo grande demais.',
            419 => 'Sua sessão expirou. Recarregue a página e tente de novo.',
            422 => 'Alguns dados precisam de correção.',
            429 => 'Muitas tentativas. Aguarde alguns minutos e tente de novo.',
            503 => 'Sistema temporariamente indisponível.',
            default => 'Ocorreu um erro inesperado.',
        };
    }
}
