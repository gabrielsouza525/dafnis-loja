<?php
declare(strict_types=1);

// Servidor embutido do PHP (desenvolvimento): arquivos estáticos são servidos direto.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file) && !str_ends_with($file, '.php')) {
        return false;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

(new App\Core\App())->run();
