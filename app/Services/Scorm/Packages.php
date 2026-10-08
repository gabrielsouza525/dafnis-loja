<?php
declare(strict_types=1);

namespace App\Services\Scorm;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\Activity;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SimpleXMLElement;
use Throwable;

/**
 * Pacotes SCORM 1.2 dos cursos próprios: importação do .zip exportado pela ferramenta de autoria,
 * versões e entrega dos arquivos. Os arquivos ficam em storage/scorm (fora da web) e só saem por
 * uma rota que confere o acesso.
 */
final class Packages
{
    /** Tipos aceitos dentro do pacote. Qualquer outro arquivo recusa a importação inteira. */
    public const MIME = [
        'html' => 'text/html; charset=UTF-8', 'htm' => 'text/html; charset=UTF-8',
        'js' => 'text/javascript; charset=UTF-8', 'mjs' => 'text/javascript; charset=UTF-8',
        'css' => 'text/css; charset=UTF-8', 'json' => 'application/json; charset=UTF-8', 'map' => 'application/json; charset=UTF-8',
        'xml' => 'application/xml; charset=UTF-8', 'xsd' => 'application/xml; charset=UTF-8', 'dtd' => 'application/xml-dtd; charset=UTF-8',
        'txt' => 'text/plain; charset=UTF-8', 'vtt' => 'text/vtt; charset=UTF-8', 'srt' => 'text/plain; charset=UTF-8', 'csv' => 'text/csv; charset=UTF-8',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp',
        'svg' => 'image/svg+xml', 'ico' => 'image/x-icon', 'avif' => 'image/avif', 'bmp' => 'image/bmp',
        'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'ogv' => 'video/ogg', 'mov' => 'video/quicktime',
        'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'aac' => 'audio/aac', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'otf' => 'font/otf', 'eot' => 'application/vnd.ms-fontobject',
        'pdf' => 'application/pdf',
    ];

    /** Limites contra pacotes defeituosos ou maliciosos (zip bomb). */
    private const MAX_FILES = 20000;
    private const MAX_TOTAL_BYTES = 4 * 1024 * 1024 * 1024;

    public static function root(): string
    {
        return BASE_PATH . '/storage/scorm';
    }

    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM course_packages WHERE id = :id', ['id' => $id]);
    }

    public static function current(int $courseId): ?array
    {
        return Database::first('SELECT * FROM course_packages WHERE course_id = :c AND is_current = 1 ORDER BY version DESC LIMIT 1', ['c' => $courseId]);
    }

    /** Versões do curso, da mais nova para a mais antiga, com quantos participantes usam cada uma. */
    public static function forCourse(int $courseId): array
    {
        return Database::select(
            'SELECT p.*, u.name AS uploaded_by_name,
                    (SELECT COUNT(*) FROM scorm_attempts a WHERE a.package_id = p.id) AS attempts
               FROM course_packages p LEFT JOIN users u ON u.id = p.uploaded_by
              WHERE p.course_id = :c ORDER BY p.version DESC',
            ['c' => $courseId]
        );
    }

    /**
     * Pasta de entrada: pacotes grandes demais para o envio pelo navegador são copiados para cá
     * (Gerenciador de Arquivos do cPanel, FTP) e importados pelo painel.
     */
    public static function inboxDir(): string
    {
        return self::root() . '/entrada';
    }

    /** @return list<array{name:string,size:int,modified:int}> */
    public static function inbox(): array
    {
        $files = glob(self::inboxDir() . '/*.zip') ?: [];
        $list = array_map(static fn ($f) => ['name' => basename($f), 'size' => (int) filesize($f), 'modified' => (int) filemtime($f)], $files);
        usort($list, static fn ($a, $b) => $b['modified'] <=> $a['modified']);
        return $list;
    }

    /** Importa um .zip da pasta de entrada e o apaga de lá (a cópia extraída fica na versão). */
    public static function importFromInbox(int $courseId, string $name, ?int $userId, bool $makeCurrent): array
    {
        $match = array_values(array_filter(self::inbox(), static fn ($f) => $f['name'] === $name));
        if (!$match) {
            throw ValidationException::with('package', 'Arquivo não encontrado na pasta de entrada.');
        }
        $path = self::inboxDir() . '/' . $match[0]['name'];
        $package = self::import($courseId, $path, $match[0]['name'], $userId, $makeCurrent);
        @unlink($path);
        return $package;
    }

    /** Tamanho máximo de envio pelo painel (o menor entre o .env e os limites do PHP). */
    public static function uploadLimitBytes(): int
    {
        $limits = array_filter([
            (int) env('SCORM_MAX_MB', 512) * 1024 * 1024,
            ini_bytes((string) ini_get('upload_max_filesize')),
            ini_bytes((string) ini_get('post_max_size')),
        ], static fn ($v) => $v > 0);
        return $limits ? min($limits) : 0;
    }

    /**
     * Importa o .zip como nova versão do curso.
     * @param bool $makeCurrent passa a ser a versão entregue aos novos participantes
     */
    public static function import(int $courseId, string $zipPath, ?string $originalName = null, ?int $userId = null, bool $makeCurrent = true): array
    {
        if (!is_file($zipPath) || filesize($zipPath) === 0) {
            throw ValidationException::with('package', 'Arquivo do pacote não encontrado.');
        }
        $version = 1 + (int) Database::value('SELECT COALESCE(MAX(version), 0) FROM course_packages WHERE course_id = :c', ['c' => $courseId]);
        $directory = $courseId . '/v' . $version . '-' . bin2hex(random_bytes(4));
        $target = self::root() . '/' . $directory;
        if (!is_dir($target) && !mkdir($target, 0775, true)) {
            throw ValidationException::with('package', 'Não foi possível criar a pasta do pacote em storage/scorm.');
        }

        try {
            [$count, $bytes] = self::extract($zipPath, $target);
            $manifest = self::readManifest($target) + ['lessons' => RiseProgress::lessonCount($target)];
        } catch (Throwable $e) {
            self::removeDirectory($target);
            throw $e instanceof ValidationException ? $e : ValidationException::with('package', 'Não foi possível abrir o pacote: ' . $e->getMessage());
        }

        $id = Database::transaction(static function () use ($courseId, $version, $directory, $manifest, $count, $bytes, $originalName, $userId, $makeCurrent) {
            if ($makeCurrent) {
                Database::update('course_packages', ['is_current' => 0], ['course_id' => $courseId]);
            }
            return Database::insert('course_packages', [
                'course_id' => $courseId,
                'version' => $version,
                'is_current' => $makeCurrent ? 1 : 0,
                'title' => $manifest['title'] !== null ? mb_substr($manifest['title'], 0, 190) : null,
                'directory' => $directory,
                'launch_path' => $manifest['launch'],
                'mastery_score' => $manifest['mastery'],
                'lesson_count' => $manifest['lessons'],
                'file_count' => $count,
                'size_bytes' => $bytes,
                'original_name' => $originalName !== null ? mb_substr($originalName, 0, 190) : null,
                'uploaded_by' => $userId,
            ]);
        });
        Activity::log('course.package', 'course', $courseId, 'Conteúdo SCORM versão ' . $version . ($makeCurrent ? ' (em uso)' : ''));
        if ($makeCurrent) {
            \App\Services\Enrollments::autoReleaseCourse($courseId);
        }
        return self::find($id);
    }

    public static function makeCurrent(array $package): void
    {
        Database::transaction(static function () use ($package) {
            Database::update('course_packages', ['is_current' => 0], ['course_id' => $package['course_id']]);
            Database::update('course_packages', ['is_current' => 1], ['id' => $package['id']]);
        });
        Activity::log('course.package', 'course', (int) $package['course_id'], 'Conteúdo SCORM: versão ' . $package['version'] . ' em uso');
        \App\Services\Enrollments::autoReleaseCourse((int) $package['course_id']);
    }

    /** Só versões que ninguém usou e que não estão em uso podem ser apagadas. */
    public static function delete(array $package): void
    {
        if ($package['is_current']) {
            throw ValidationException::with('package', 'Esta é a versão em uso. Coloque outra em uso antes de excluir.');
        }
        if ((int) Database::value('SELECT COUNT(*) FROM scorm_attempts WHERE package_id = :p', ['p' => $package['id']]) > 0) {
            throw ValidationException::with('package', 'Há participantes nesta versão. Ela fica guardada para não perder o andamento deles.');
        }
        Database::delete('course_packages', ['id' => $package['id']]);
        self::removeDirectory(self::root() . '/' . $package['directory']);
        Activity::log('course.package', 'course', (int) $package['course_id'], 'Conteúdo SCORM: versão ' . $package['version'] . ' excluída');
    }

    /** Caminho no disco de um arquivo do pacote, ou null se não existir ou tentar sair da pasta. */
    public static function filePath(array $package, string $relative): ?string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if ($relative === '' || str_contains($relative, "\0") || preg_match('#(^|/)\.\.?(/|$)#', $relative)) {
            return null;
        }
        $base = realpath(self::root() . '/' . $package['directory']);
        $path = realpath(self::root() . '/' . $package['directory'] . '/' . $relative);
        if (!$base || !$path || !is_file($path)) {
            return null;
        }
        $base = rtrim(str_replace('\\', '/', $base), '/') . '/';
        return str_starts_with(str_replace('\\', '/', $path), $base) ? $path : null;
    }

    /**
     * Resposta com um arquivo do pacote. O conteúdo da ferramenta de autoria usa scripts e estilos
     * embutidos, então tem CSP própria; continua sem acesso a outros sites, salvo mídia e iframes
     * em https (vídeos incorporados). Só pode ser aberto dentro da loja (frame-ancestors 'self').
     */
    public static function fileResponse(array $package, string $relative, ?string $range): Response
    {
        $path = self::filePath($package, $relative);
        $mime = $path ? self::mime($path) : null;
        if (!$path || !$mime) {
            throw new HttpException(404, 'Arquivo do curso não encontrado.');
        }
        return Response::fileRange($path, $mime, $range, [
            'Cache-Control' => 'private, max-age=604800',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
                "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
                "font-src 'self' data: https://fonts.gstatic.com",
                "img-src 'self' data: blob: https:",
                "media-src 'self' data: blob: https:",
                "connect-src 'self'",
                "frame-src 'self' https:",
                "frame-ancestors 'self'",
                "base-uri 'self'",
                "form-action 'self'",
                "object-src 'none'",
            ]),
        ]);
    }

    public static function mime(string $path): ?string
    {
        return self::MIME[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;
    }

    /**
     * Copia os arquivos do .zip para a pasta, conferindo nome, tipo e tamanho de cada um.
     * Usa a extensão zip do PHP quando está ativa; senão, o leitor de zip do Phar, que vem no PHP.
     * @return array{0:int,1:int} quantidade de arquivos e bytes
     */
    private static function extract(string $zipPath, string $target): array
    {
        $count = 0;
        $bytes = 0;
        $write = static function (string $name, int $size, callable $copy) use ($target, &$count, &$bytes): void {
            $name = str_replace('\\', '/', $name);
            if (str_ends_with($name, '/')) {
                return; // pasta
            }
            if ($name === '' || str_starts_with($name, '/') || preg_match('#^[A-Za-z]:#', $name) || str_contains($name, "\0") || preg_match('#(^|/)\.\.?(/|$)#', $name)) {
                throw ValidationException::with('package', 'O pacote tem um caminho de arquivo inválido: ' . $name);
            }
            if (str_starts_with($name, '__MACOSX/') || basename($name) === '.DS_Store' || basename($name) === 'Thumbs.db') {
                return; // lixo de sistema operacional
            }
            if (self::mime($name) === null) {
                throw ValidationException::with('package', 'O pacote tem um tipo de arquivo não aceito: ' . $name);
            }
            if (++$count > self::MAX_FILES || ($bytes += $size) > self::MAX_TOTAL_BYTES) {
                throw ValidationException::with('package', 'O pacote passa do limite de arquivos ou de tamanho.');
            }
            $dest = $target . '/' . $name;
            if (!is_dir(dirname($dest))) {
                mkdir(dirname($dest), 0775, true);
            }
            $copy($dest);
        };

        if (class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                throw ValidationException::with('package', 'O arquivo enviado não é um .zip válido.');
            }
            try {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $stat = $zip->statIndex($i);
                    $write((string) $stat['name'], (int) $stat['size'], static function (string $dest) use ($zip, $i): void {
                        $in = $zip->getStream((string) $zip->getNameIndex($i));
                        $out = fopen($dest, 'wb');
                        if (!$in || !$out || stream_copy_to_stream($in, $out) === false) {
                            throw ValidationException::with('package', 'Falha ao extrair ' . $zip->getNameIndex($i));
                        }
                        fclose($in);
                        fclose($out);
                    });
                }
            } finally {
                $zip->close();
            }
            return [$count, $bytes];
        }

        // O Phar reconhece o formato pela extensão: o arquivo enviado (php123.tmp) ganha uma cópia .zip.
        $tmp = null;
        if (strtolower(pathinfo($zipPath, PATHINFO_EXTENSION)) !== 'zip') {
            $tmp = sys_get_temp_dir() . '/scorm-' . bin2hex(random_bytes(6)) . '.zip';
            copy($zipPath, $tmp);
            $zipPath = $tmp;
        }
        try {
            try {
                $phar = new \PharData($zipPath, FilesystemIterator::SKIP_DOTS);
            } catch (Throwable) {
                throw ValidationException::with('package', 'O arquivo enviado não é um .zip válido.');
            }
            $prefix = 'phar://' . str_replace('\\', '/', $zipPath) . '/';
            foreach (new RecursiveIteratorIterator($phar) as $file) {
                $source = str_replace('\\', '/', $file->getPathname());
                $name = str_starts_with($source, $prefix) ? substr($source, strlen($prefix)) : basename($source);
                $write($name, (int) $file->getSize(), static function (string $dest) use ($source, $name): void {
                    if (!copy($source, $dest)) {
                        throw ValidationException::with('package', 'Falha ao extrair ' . $name);
                    }
                });
            }
            unset($phar);
        } finally {
            if ($tmp) {
                @unlink($tmp);
            }
        }
        return [$count, $bytes];
    }

    /**
     * Lê o imsmanifest.xml: título, arquivo de abertura (primeiro item com recurso da organização
     * padrão) e nota mínima. Recusa SCORM 2004, que usa outra API.
     * @return array{title:?string,launch:string,mastery:?int}
     */
    private static function readManifest(string $dir): array
    {
        $file = $dir . '/imsmanifest.xml';
        if (!is_file($file)) {
            throw ValidationException::with('package', 'O pacote não tem o imsmanifest.xml na raiz. Exporte o curso para LMS em SCORM 1.2 e envie o .zip sem descompactar.');
        }
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string((string) file_get_contents($file), SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($previous);
        if (!$xml) {
            throw ValidationException::with('package', 'O imsmanifest.xml do pacote está corrompido.');
        }
        $ns = $xml->getNamespaces(true);
        $default = $ns[''] ?? 'http://www.imsproject.org/xsd/imscp_rootv1p1p2';
        $xml->registerXPathNamespace('m', $default);
        $schemaVersion = trim((string) ($xml->xpath('//m:metadata/m:schemaversion')[0] ?? ''));
        if ($schemaVersion !== '' && !str_starts_with($schemaVersion, '1.2')) {
            throw ValidationException::with('package', 'O pacote é SCORM ' . $schemaVersion . '. A loja usa SCORM 1.2: exporte de novo escolhendo "SCORM 1.2".');
        }

        // Organização padrão (ou a primeira) e o primeiro item dela que aponta para um recurso.
        $orgs = $xml->xpath('//m:organizations')[0] ?? null;
        $defaultOrg = $orgs ? (string) $orgs['default'] : '';
        $orgPath = '//m:organization' . ($defaultOrg !== '' && preg_match('/^[\w.:-]+$/', $defaultOrg) ? '[@identifier="' . $defaultOrg . '"]' : '');
        if (!$xml->xpath($orgPath)) {
            $orgPath = '//m:organization';
        }
        $org = $xml->xpath($orgPath)[0] ?? null;
        $item = $xml->xpath('(' . $orgPath . ')[1]//m:item[@identifierref]')[0] ?? null;
        $resource = null;
        $ref = $item ? (string) $item['identifierref'] : '';
        if ($ref !== '' && preg_match('/^[\w.:-]+$/', $ref)) {
            $resource = $xml->xpath('//m:resource[@identifier="' . $ref . '"]')[0] ?? null;
        }
        $resource ??= $xml->xpath('//m:resource[@href]')[0] ?? null;
        $href = $resource ? (string) $resource['href'] : '';
        if ($href === '') {
            throw ValidationException::with('package', 'O imsmanifest.xml não indica o arquivo de abertura do curso.');
        }
        $xmlBase = $ns['xml'] ?? 'http://www.w3.org/XML/1998/namespace';
        $base = (string) ($resource->attributes($xmlBase)['base'] ?? '');
        $launch = ltrim(str_replace('\\', '/', $base . $href), '/');
        $launchFile = (string) strtok($launch, '?#');
        if (!is_file($dir . '/' . $launchFile)) {
            throw ValidationException::with('package', 'O arquivo de abertura indicado no manifesto não está no pacote: ' . $launchFile);
        }

        $mastery = null;
        if ($item) {
            foreach ($ns as $uri) {
                $value = trim((string) ($item->children($uri)->masteryscore ?? ''));
                if ($value !== '' && is_numeric($value)) {
                    $mastery = max(0, min(100, (int) round((float) $value)));
                    break;
                }
            }
        }
        $title = trim((string) (($org ? $org->children($default)->title : null) ?? ''));

        return ['title' => $title !== '' ? $title : null, 'launch' => $launch, 'mastery' => $mastery];
    }

    private static function removeDirectory(string $dir): void
    {
        $root = realpath(self::root());
        $real = realpath($dir);
        if (!$root || !$real || !str_starts_with($real, $root) || $real === $root) {
            return;
        }
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($real);
    }
}
