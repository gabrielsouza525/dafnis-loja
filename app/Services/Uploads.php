<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\ValidationException;

/**
 * Arquivos enviados pelo admin.
 * - Capas de curso: públicas em public/uploads/cursos, convertidas para WebP quando a GD permite.
 * - Certificados: privados em storage/uploads/certificados, entregues só ao participante.
 */
final class Uploads
{
    private const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** @return string caminho relativo a public/uploads */
    public static function courseImage(array $file, string $slug): string
    {
        self::assertOk($file, 'image');
        $mime = self::mime($file['tmp_name']);
        if (!isset(self::IMAGE_TYPES[$mime])) {
            throw ValidationException::with('image', 'Envie a capa em JPG, PNG ou WebP.');
        }
        if (!@getimagesize($file['tmp_name'])) {
            throw ValidationException::with('image', 'O arquivo enviado não é uma imagem válida.');
        }
        $dir = BASE_PATH . '/public/uploads/cursos';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = $slug . '-' . substr(bin2hex(random_bytes(4)), 0, 8);

        // Reencoda (remove metadados e qualquer conteúdo embutido) e limita a 1600 px.
        if (function_exists('imagewebp') && function_exists('imagecreatefromstring')) {
            $src = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
            if ($src) {
                $w = imagesx($src);
                $h = imagesy($src);
                $max = 1600;
                if ($w > $max) {
                    $nh = (int) round($h * $max / $w);
                    $dst = imagecreatetruecolor($max, $nh);
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                    imagecopyresampled($dst, $src, 0, 0, 0, 0, $max, $nh, $w, $h);
                    imagedestroy($src);
                    $src = $dst;
                }
                imagewebp($src, "$dir/$name.webp", 82);
                imagedestroy($src);
                return "cursos/$name.webp";
            }
        }
        $ext = self::IMAGE_TYPES[$mime];
        if (!move_uploaded_file($file['tmp_name'], "$dir/$name.$ext")) {
            throw ValidationException::with('image', 'Não foi possível salvar a imagem.');
        }
        return "cursos/$name.$ext";
    }

    public static function deletePublic(?string $relative): void
    {
        if ($relative && !str_contains($relative, '..')) {
            @unlink(BASE_PATH . '/public/uploads/' . $relative);
        }
    }

    /** @return string caminho relativo a storage/uploads */
    public static function certificate(array $file): string
    {
        self::assertOk($file, 'certificate_file');
        if (self::mime($file['tmp_name']) !== 'application/pdf') {
            throw ValidationException::with('certificate_file', 'O certificado precisa ser um arquivo PDF.');
        }
        $dir = BASE_PATH . '/storage/uploads/certificados';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = date('Ym') . '-' . bin2hex(random_bytes(12)) . '.pdf';
        if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) {
            throw ValidationException::with('certificate_file', 'Não foi possível salvar o certificado.');
        }
        return "certificados/$name";
    }

    public static function privatePath(?string $relative): ?string
    {
        if (!$relative || str_contains($relative, '..')) {
            return null;
        }
        $path = BASE_PATH . '/storage/uploads/' . $relative;
        return is_file($path) ? $path : null;
    }

    public static function deletePrivate(?string $relative): void
    {
        if ($path = self::privatePath($relative)) {
            @unlink($path);
        }
    }

    private static function assertOk(array $file, string $field): void
    {
        $maxMb = (int) env('UPLOAD_MAX_MB', 8);
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $message = in_array($file['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? "Arquivo maior que o permitido ($maxMb MB)."
                : 'Falha no envio do arquivo. Tente novamente.';
            throw ValidationException::with($field, $message);
        }
        if ((int) $file['size'] > $maxMb * 1024 * 1024) {
            throw ValidationException::with($field, "Arquivo maior que o permitido ($maxMb MB).");
        }
    }

    private static function mime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = (string) finfo_file($finfo, $path);
            finfo_close($finfo);
            return $mime;
        }
        $head = (string) file_get_contents($path, false, null, 0, 12);
        return match (true) {
            str_starts_with($head, '%PDF') => 'application/pdf',
            str_starts_with($head, "\xFF\xD8\xFF") => 'image/jpeg',
            str_starts_with($head, "\x89PNG") => 'image/png',
            str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP' => 'image/webp',
            default => 'application/octet-stream',
        };
    }
}
