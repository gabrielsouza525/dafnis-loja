<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

/**
 * Diagnóstico do servidor: versão do PHP, extensões, pastas graváveis, banco, HTTPS e limites de
 * envio. Usado no instalador pela web e em `php bin/console check`, para achar o que falta na
 * hospedagem (na HostGator, PHP e extensões se escolhem no cPanel) antes de abrir a loja.
 */
final class Requirements
{
    public const OK = 'ok';
    public const WARN = 'aviso';
    public const FAIL = 'falta';

    /** @return list<array{group:string,label:string,status:string,detail:string}> */
    public static function check(bool $withDatabase = true): array
    {
        $out = [];
        $add = static function (string $group, string $label, string $status, string $detail = '') use (&$out): void {
            $out[] = compact('group', 'label', 'status', 'detail');
        };

        // PHP
        $phpOk = version_compare(PHP_VERSION, '8.1.0', '>=');
        $add('PHP', 'Versão ' . PHP_VERSION, $phpOk ? self::OK : self::FAIL, $phpOk ? '' : 'Mínimo 8.1. Na HostGator: cPanel › MultiPHP Manager, escolha 8.2 ou mais nova.');
        $extensions = [
            'pdo_mysql' => [true, 'banco de dados'],
            'mbstring' => [true, 'textos com acento'],
            'openssl' => [true, 'criptografia e e-mail seguro'],
            'json' => [true, 'dados do catálogo e do pagamento'],
            'curl' => [true, 'Mercado Pago'],
            'fileinfo' => [true, 'conferência dos arquivos enviados'],
            'simplexml' => [true, 'leitura dos cursos SCORM'],
            'gd' => [false, 'capas convertidas para WebP (sem ela, a capa fica no formato enviado)'],
        ];
        foreach ($extensions as $ext => [$required, $why]) {
            $loaded = extension_loaded($ext);
            $add('PHP', 'Extensão ' . $ext, $loaded ? self::OK : ($required ? self::FAIL : self::WARN), $loaded ? '' : 'Usada para: ' . $why . '. Na HostGator: cPanel › Select PHP Version › Extensions.');
        }
        $zip = class_exists(\ZipArchive::class);
        $phar = extension_loaded('phar');
        $add('PHP', 'Leitura de .zip (cursos SCORM)', $zip || $phar ? self::OK : self::FAIL, $zip ? 'extensão zip' : ($phar ? 'pelo Phar (a extensão zip é mais rápida, se puder ativar)' : 'Ative a extensão zip ou phar.'));

        // Limites
        $upload = ini_bytes((string) ini_get('upload_max_filesize'));
        $post = ini_bytes((string) ini_get('post_max_size'));
        $limit = min($upload ?: PHP_INT_MAX, $post ?: PHP_INT_MAX);
        $add('Limites', 'Envio de arquivos: ' . self::mb($limit), $limit >= 64 * 1048576 ? self::OK : self::WARN, $limit >= 64 * 1048576 ? '' : 'Pacotes de curso costumam ter 30 a 100 MB. Suba upload_max_filesize e post_max_size (cPanel › MultiPHP INI Editor) ou importe pelo comando scorm:import.');
        $memory = ini_bytes((string) ini_get('memory_limit'));
        $add('Limites', 'Memória: ' . ($memory < 0 ? 'sem limite' : self::mb($memory)), $memory < 0 || $memory >= 128 * 1048576 ? self::OK : self::WARN, $memory < 0 || $memory >= 128 * 1048576 ? '' : 'Recomendado 128 MB ou mais (memory_limit).');

        // Pastas
        foreach (['storage/sessions', 'storage/logs', 'storage/cache', 'storage/mail', 'storage/uploads', 'storage/scorm', 'storage/scorm/entrada', 'public/uploads'] as $dir) {
            $path = BASE_PATH . '/' . $dir;
            $ok = is_dir($path) ? is_writable($path) : @mkdir($path, 0775, true);
            $add('Pastas', $dir, $ok ? self::OK : self::FAIL, $ok ? '' : 'Sem permissão de escrita. No Gerenciador de Arquivos, dê permissão 755 à pasta.');
        }

        // Configuração
        $production = env('APP_ENV', 'local') === 'production';
        $url = (string) env('APP_URL', '');
        $add('Configuração', 'APP_URL ' . ($url !== '' ? $url : '(vazio)'), $url === '' ? self::FAIL : (str_starts_with($url, 'https://') || !$production ? self::OK : self::WARN), $url === '' ? 'Defina o endereço da loja no .env.' : (str_starts_with($url, 'https://') || !$production ? '' : 'Em produção use https:// (exigência do Mercado Pago). Ative o SSL no cPanel › SSL/TLS Status.'));
        $add('Configuração', 'APP_ENV=' . env('APP_ENV', 'local'), $production ? self::OK : self::WARN, $production ? '' : 'No servidor, use APP_ENV=production.');
        $debug = filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
        $add('Configuração', 'APP_DEBUG=' . ($debug ? 'true' : 'false'), $production && $debug ? self::FAIL : self::OK, $production && $debug ? 'Em produção, APP_DEBUG precisa ser false: com true, os erros mostram detalhes internos aos visitantes.' : '');
        $key = (string) env('APP_KEY', '');
        $add('Configuração', 'APP_KEY', $key !== '' || !$production ? self::OK : self::WARN, $key !== '' || !$production ? '' : 'Vazia: a loja cria storage/app.key. Em produção, gere uma e guarde junto com o backup.');
        if ($production) {
            $secure = filter_var(env('SESSION_SECURE', false), FILTER_VALIDATE_BOOLEAN);
            $add('Configuração', 'SESSION_SECURE=' . ($secure ? 'true' : 'false'), $secure ? self::OK : self::WARN, $secure ? '' : 'Com HTTPS ativo, use true.');
        }
        $mail = (string) env('MAIL_DRIVER', 'log');
        $add('Configuração', 'E-mail: ' . ($mail === 'smtp' ? 'SMTP ' . env('MAIL_HOST', '') : 'gravado em arquivo (não envia)'), $mail === 'smtp' ? self::OK : self::WARN, $mail === 'smtp' ? '' : 'Para enviar de verdade, configure MAIL_DRIVER=smtp com uma conta de e-mail do domínio.');
        $add('Configuração', 'Mercado Pago', Payments\Payments::isOnline() ? self::OK : self::WARN, Payments\Payments::isOnline() ? '' : 'Sem token: os pedidos ficam aguardando a baixa manual no painel.');

        // Banco
        if ($withDatabase) {
            try {
                $version = (string) Database::value('SELECT VERSION()');
                $add('Banco', 'Conexão (' . $version . ')', self::OK);
            } catch (Throwable $e) {
                $add('Banco', 'Conexão', self::FAIL, 'Não conectou: confira DB_HOST, DB_DATABASE, DB_USERNAME e DB_PASSWORD no .env. Na HostGator o nome do banco e do usuário começam com o seu usuário do cPanel (ex.: usuario_dafnis).');
            }
        }
        return $out;
    }

    /** true se nada obrigatório falta. */
    public static function passes(array $results): bool
    {
        return !array_filter($results, static fn ($r) => $r['status'] === self::FAIL);
    }

    private static function mb(int $bytes): string
    {
        return $bytes >= PHP_INT_MAX ? 'sem limite' : number_format($bytes / 1048576, 0, ',', '.') . ' MB';
    }
}
