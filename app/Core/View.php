<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

final class View
{
    public static function render(string $template, array $data = [], ?string $layout = null): string
    {
        $content = self::file($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::file($layout, array_merge($data, ['content' => $content]));
    }

    /**
     * Os nomes internos usam prefixo "__" para não colidirem com as variáveis
     * da view (ex.: uma view pode receber $data ou $template à vontade).
     */
    public static function file(string $__template, array $__vars = []): string
    {
        $__path = BASE_PATH . '/app/Views/' . $__template . '.php';
        if (!is_file($__path)) {
            throw new RuntimeException("View não encontrada: $__template");
        }
        unset($__vars['__path'], $__vars['__template'], $__vars['__vars']);
        extract($__vars, EXTR_OVERWRITE);
        ob_start();
        try {
            include $__path;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
