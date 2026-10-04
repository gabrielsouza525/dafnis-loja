<?php
declare(strict_types=1);

namespace App\Services\Scorm;

/**
 * Andamento dos cursos do Rise 360. O SCORM 1.2 não tem campo de progresso; o Rise guarda o seu no
 * suspend_data, como {"v":3,"d":[...]}: um JSON comprimido em LZW (códigos abaixo de 256 são
 * caracteres) com o percentual de cada lição já aberta em progress.lessons[n].p. O total de lições
 * vem do pacote (scormcontent/runtime-data.js), lido na importação. Formato desconhecido: null.
 */
final class RiseProgress
{
    /** Percentual do curso (0 a 100): média das lições, contando como 0 as que não foram abertas. */
    public static function fromSuspendData(?string $data, ?int $lessonCount): ?int
    {
        if (!$lessonCount || $data === null || !str_starts_with($data, '{"v":')) {
            return null;
        }
        $json = self::decode($data);
        $lessons = $json['progress']['lessons'] ?? null;
        if (!is_array($lessons)) {
            return null;
        }
        $sum = 0;
        foreach ($lessons as $index => $lesson) {
            if (is_numeric($index) && (int) $index < $lessonCount && is_array($lesson)) {
                $sum += max(0, min(100, (int) ($lesson['p'] ?? 0)));
            }
        }
        return (int) floor($sum / $lessonCount);
    }

    /** Lições do curso no runtime-data.js do pacote (JSON em base64), sem contar os títulos de seção. */
    public static function lessonCount(string $packageDir): ?int
    {
        $file = $packageDir . '/scormcontent/runtime-data.js';
        if (!is_file($file) || filesize($file) > 64 * 1024 * 1024) {
            return null;
        }
        if (!preg_match('/__jsonp\(\s*["\']runtime-data\.js["\']\s*,\s*["\']([A-Za-z0-9+\/=]+)["\']/', (string) file_get_contents($file), $m)) {
            return null;
        }
        $data = json_decode((string) base64_decode($m[1], true), true);
        $lessons = $data['course']['lessons'] ?? null;
        if (!is_array($lessons)) {
            return null;
        }
        $count = count(array_filter($lessons, static fn ($l) => is_array($l) && ($l['type'] ?? '') !== 'section'));
        return $count ?: null;
    }

    /** Descomprime o suspend_data do Rise. */
    private static function decode(string $data): ?array
    {
        $wrapper = json_decode($data, true);
        $codes = $wrapper['d'] ?? null;
        if (!is_array($codes) || !$codes || !is_int($codes[0])) {
            return null;
        }
        $dict = [];
        $next = 256;
        $previous = self::entry($codes[0], $dict);
        if ($previous === null) {
            return null;
        }
        $out = $previous;
        for ($i = 1, $n = count($codes); $i < $n; $i++) {
            $code = $codes[$i];
            if (!is_int($code)) {
                return null;
            }
            $entry = self::entry($code, $dict) ?? ($code === $next ? $previous . mb_substr($previous, 0, 1) : null);
            if ($entry === null) {
                return null;
            }
            $out .= $entry;
            $dict[$next++] = $previous . mb_substr($entry, 0, 1);
            $previous = $entry;
        }
        $json = json_decode($out, true);
        return is_array($json) ? $json : null;
    }

    private static function entry(int $code, array $dict): ?string
    {
        if ($code < 256) {
            return $code >= 0 ? mb_chr($code, 'UTF-8') : null;
        }
        return $dict[$code] ?? null;
    }
}
