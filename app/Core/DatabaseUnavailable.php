<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/** Banco fora do ar: vira página 503 amigável, sem expor detalhes. */
final class DatabaseUnavailable extends RuntimeException
{
}
