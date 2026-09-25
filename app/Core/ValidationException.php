<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    /** @param array<string,string> $errors campo => mensagem */
    public function __construct(public readonly array $errors, string $message = 'Alguns dados precisam de correção.')
    {
        parent::__construct($message, 422);
    }

    public static function with(string $field, string $message): self
    {
        return new self([$field => $message], $message);
    }
}
