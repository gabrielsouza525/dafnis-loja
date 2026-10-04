<?php
declare(strict_types=1);

namespace App\Services\Scorm;

/**
 * Percentual de andamento a partir do suspend_data. O SCORM 1.2 não tem campo de progresso;
 * cada ferramenta guarda o seu de um jeito. Null quando o formato não é reconhecido.
 */
final class RiseProgress
{
    public static function fromSuspendData(?string $data): ?int
    {
        return null;
    }
}
