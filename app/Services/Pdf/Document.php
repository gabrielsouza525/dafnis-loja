<?php
declare(strict_types=1);

namespace App\Services\Pdf;

use RuntimeException;

/**
 * Gerador de PDF pequeno e sem bibliotecas, feito para documentos como o certificado: páginas,
 * as fontes padrão do PDF (Times e Helvetica, com acentos pela codificação WinAnsi), texto com
 * quebra de linha e justificação, linhas, retângulos, círculos, polígonos de cantos arredondados,
 * imagens JPEG e PNG (com transparência), recorte em círculo e links.
 *
 * Coordenadas em pontos (1/72 de polegada) a partir do canto superior esquerdo; o y do texto é a
 * linha de base. Cores em [r, g, b] de 0 a 255.
 */
final class Document
{
    private const FONT_KEYS = [
        'Times-Roman' => 'F1', 'Times-Bold' => 'F2', 'Helvetica' => 'F3', 'Helvetica-Bold' => 'F4', 'Helvetica-BoldOblique' => 'F5',
    ];
    /** Distância dos pontos de controle da curva de Bézier que aproxima um quarto de círculo. */
    private const KAPPA = 0.5522847498;

    /** @var list<array{content: string, links: list<array>}> */
    private array $pages = [];
    private int $current = -1;
    /** @var array<string, array> imagens por chave (caminho ou hash do conteúdo) */
    private array $images = [];
    private string $font = 'Times-Roman';
    private float $size = 12;

    public function __construct(
        private readonly float $width,
        private readonly float $height,
        private readonly string $title = '',
    ) {
    }

    public function addPage(): void
    {
        $this->pages[] = ['content' => '', 'links' => []];
        $this->current = count($this->pages) - 1;
    }

    public function width(): float
    {
        return $this->width;
    }

    public function height(): float
    {
        return $this->height;
    }

    public function font(string $name, float $size): self
    {
        if (!isset(self::FONT_KEYS[$name])) {
            throw new RuntimeException("Fonte não disponível: $name");
        }
        $this->font = $name;
        $this->size = $size;
        return $this;
    }

    public function fontSize(): float
    {
        return $this->size;
    }

    public function textWidth(string $text, ?string $font = null, ?float $size = null): float
    {
        $font ??= $this->font;
        $units = 0;
        foreach (unpack('C*', self::encode($text)) ?: [] as $byte) {
            $units += Fonts::width($font, $byte);
        }
        return $units * ($size ?? $this->size) / 1000;
    }

    /**
     * Uma linha de texto. Com $align "center", x é o centro; com "right", a borda direita.
     * $wordSpacing acrescenta espaço a cada espaço em branco (usado na justificação).
     */
    public function text(float $x, float $y, string $text, string $align = 'left', ?array $color = null, float $wordSpacing = 0, bool $underline = false): void
    {
        if ($text === '') {
            return;
        }
        $width = $this->textWidth($text) + $wordSpacing * substr_count($text, ' ');
        if ($align === 'center') {
            $x -= $width / 2;
        } elseif ($align === 'right') {
            $x -= $width;
        }
        $ops = 'q ' . self::fill($color ?? [0, 0, 0]) . ' BT /' . self::FONT_KEYS[$this->font] . ' ' . self::num($this->size) . ' Tf ';
        if ($wordSpacing != 0) {
            $ops .= self::num($wordSpacing) . ' Tw ';
        }
        $ops .= '1 0 0 1 ' . self::num($x) . ' ' . self::num($this->height - $y) . ' Tm (' . self::escape(self::encode($text)) . ') Tj ET Q';
        $this->out($ops);
        if ($underline) {
            $this->line($x, $y + $this->size * 0.12, $x + $width, $y + $this->size * 0.12, max(0.5, $this->size * 0.055), $color ?? [0, 0, 0]);
        }
    }

    /**
     * Quebra o texto em linhas que cabem na largura (respeita as quebras de linha do próprio texto).
     * @return list<string>
     */
    public function wrap(string $text, float $width, ?float $firstLineWidth = null): array
    {
        $lines = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $paragraph) {
            $line = '';
            foreach (preg_split('/\s+/u', trim($paragraph), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                $max = ($lines === [] && $firstLineWidth !== null) ? $firstLineWidth : $width;
                $candidate = $line === '' ? $word : "$line $word";
                if ($this->textWidth($candidate) <= $max) {
                    $line = $candidate;
                    continue;
                }
                if ($line !== '') {
                    $lines[] = $line;
                    $max = $width;
                }
                // Palavra maior que a linha inteira: corta por caracteres.
                while ($this->textWidth($word) > $max && mb_strlen($word) > 1) {
                    $cut = mb_strlen($word) - 1;
                    while ($cut > 1 && $this->textWidth(mb_substr($word, 0, $cut)) > $max) {
                        $cut--;
                    }
                    $lines[] = mb_substr($word, 0, $cut);
                    $word = mb_substr($word, $cut);
                    $max = $width;
                }
                $line = $word;
            }
            $lines[] = $line;
        }
        return $lines;
    }

    /**
     * Parágrafo com quebra automática. $align: left, center, right ou justify (a última linha
     * fica à esquerda). Devolve o y da linha de base seguinte.
     */
    public function paragraph(float $x, float $y, float $width, string $text, float $lineHeight, string $align = 'left', ?array $color = null): float
    {
        $lines = $this->wrap($text, $width);
        $last = count($lines) - 1;
        foreach ($lines as $i => $line) {
            if ($align === 'justify' && $i < $last && ($spaces = substr_count($line, ' ')) > 0) {
                $this->text($x, $y, $line, 'left', $color, ($width - $this->textWidth($line)) / $spaces);
            } elseif ($align === 'center') {
                $this->text($x + $width / 2, $y, $line, 'center', $color);
            } elseif ($align === 'right') {
                $this->text($x + $width, $y, $line, 'right', $color);
            } else {
                $this->text($x, $y, $line, 'left', $color);
            }
            $y += $lineHeight;
        }
        return $y;
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $width = 1, array $color = [0, 0, 0]): void
    {
        $this->out(sprintf('q %s %s w %s %s m %s %s l S Q', self::stroke($color), self::num($width), self::num($x1), self::num($this->height - $y1), self::num($x2), self::num($this->height - $y2)));
    }

    public function rect(float $x, float $y, float $w, float $h, ?array $fill = null, ?array $stroke = null, float $lineWidth = 1): void
    {
        $path = sprintf('%s %s %s %s re', self::num($x), self::num($this->height - $y - $h), self::num($w), self::num($h));
        $this->paint($path, $fill, $stroke, $lineWidth);
    }

    public function circle(float $cx, float $cy, float $r, ?array $fill = null, ?array $stroke = null, float $lineWidth = 1): void
    {
        $this->paint($this->circlePath($cx, $cy, $r), $fill, $stroke, $lineWidth);
    }

    /**
     * Polígono fechado com cantos arredondados (raio $radius; 0 = cantos vivos).
     * @param list<array{0: float, 1: float}> $points
     */
    public function polygon(array $points, float $radius = 0, ?array $fill = null, ?array $stroke = null, float $lineWidth = 1): void
    {
        $n = count($points);
        $path = '';
        for ($i = 0; $i < $n; $i++) {
            [$px, $py] = $points[($i - 1 + $n) % $n];
            [$vx, $vy] = $points[$i];
            [$nx, $ny] = $points[($i + 1) % $n];
            if ($radius <= 0) {
                $path .= self::num($vx) . ' ' . self::num($this->height - $vy) . ($i === 0 ? ' m ' : ' l ');
                continue;
            }
            $ta = min(0.5, $radius / max(0.001, hypot($px - $vx, $py - $vy)));
            $tb = min(0.5, $radius / max(0.001, hypot($nx - $vx, $ny - $vy)));
            $ax = $vx + ($px - $vx) * $ta;
            $ay = $vy + ($py - $vy) * $ta;
            $bx = $vx + ($nx - $vx) * $tb;
            $by = $vy + ($ny - $vy) * $tb;
            $path .= self::num($ax) . ' ' . self::num($this->height - $ay) . ($i === 0 ? ' m ' : ' l ');
            $path .= sprintf(
                '%s %s %s %s %s %s c ',
                self::num($ax + ($vx - $ax) * self::KAPPA), self::num($this->height - ($ay + ($vy - $ay) * self::KAPPA)),
                self::num($bx + ($vx - $bx) * self::KAPPA), self::num($this->height - ($by + ($vy - $by) * self::KAPPA)),
                self::num($bx), self::num($this->height - $by)
            );
        }
        $this->paint($path . 'h', $fill, $stroke, $lineWidth);
    }

    /** Desenha o que $draw fizer recortado dentro do círculo. */
    public function clipCircle(float $cx, float $cy, float $r, callable $draw): void
    {
        $this->out('q ' . $this->circlePath($cx, $cy, $r) . ' W n');
        $draw($this);
        $this->out('Q');
    }

    /** Imagem JPEG ou PNG, de um arquivo ou do conteúdo binário. */
    public function image(string $source, float $x, float $y, float $w, float $h): void
    {
        $key = is_file($source) ? 'f:' . realpath($source) : 'd:' . md5($source);
        if (!isset($this->images[$key])) {
            $data = is_file($source) ? (string) file_get_contents($source) : $source;
            $this->images[$key] = self::parseImage($data) + ['name' => 'I' . (count($this->images) + 1)];
        }
        $this->out(sprintf('q %s 0 0 %s %s %s cm /%s Do Q', self::num($w), self::num($h), self::num($x), self::num($this->height - $y - $h), $this->images[$key]['name']));
    }

    /** Confere se o gerador consegue usar a imagem (lança RuntimeException com o motivo). */
    public static function assertImage(string $data): void
    {
        self::parseImage($data);
    }

    /** Proporção largura/altura de uma imagem, sem desenhá-la. */
    public static function imageRatio(string $source): float
    {
        $info = is_file($source) ? @getimagesize($source) : @getimagesizefromstring($source);
        return $info && $info[1] > 0 ? $info[0] / $info[1] : 1.0;
    }

    public function link(float $x, float $y, float $w, float $h, string $url): void
    {
        $this->pages[$this->current]['links'][] = [$x, $this->height - $y - $h, $x + $w, $this->height - $y, $url];
    }

    /** Matriz de módulos (QR code) desenhada em quadrados; cada linha da matriz é uma lista de bool. */
    public function modules(array $matrix, float $x, float $y, float $moduleSize, array $color = [0, 0, 0]): void
    {
        $path = '';
        foreach ($matrix as $row => $cells) {
            $count = count($cells);
            for ($col = 0; $col < $count; $col++) {
                if (!$cells[$col]) {
                    continue;
                }
                $start = $col;
                while ($col + 1 < $count && $cells[$col + 1]) {
                    $col++;
                }
                $path .= sprintf('%s %s %s %s re ', self::num($x + $start * $moduleSize), self::num($this->height - $y - ($row + 1) * $moduleSize), self::num(($col - $start + 1) * $moduleSize), self::num($moduleSize));
            }
        }
        if ($path !== '') {
            $this->out('q ' . self::fill($color) . ' ' . $path . 'f Q');
        }
    }

    public function output(): string
    {
        if (!$this->pages) {
            $this->addPage();
        }
        $objects = [];
        $add = static function (string $body) use (&$objects): int {
            $objects[] = $body;
            return count($objects);
        };
        $reserve = static function () use (&$objects): int {
            $objects[] = '';
            return count($objects);
        };

        $catalogId = $reserve();
        $pagesId = $reserve();
        $fontIds = [];
        foreach (self::FONT_KEYS as $name => $key) {
            $fontIds[$key] = $add("<< /Type /Font /Subtype /Type1 /BaseFont /$name /Encoding /WinAnsiEncoding >>");
        }
        $imageIds = [];
        foreach ($this->images as $img) {
            $smask = '';
            if (isset($img['alpha'])) {
                $alphaId = $add(self::stream(
                    "/Type /XObject /Subtype /Image /Width {$img['w']} /Height {$img['h']} /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode" . $img['alphaParms'],
                    $img['alpha']
                ));
                $smask = " /SMask $alphaId 0 R";
            }
            $imageIds[$img['name']] = $add(self::stream(
                "/Type /XObject /Subtype /Image /Width {$img['w']} /Height {$img['h']} /ColorSpace {$img['cs']} /BitsPerComponent {$img['bpc']} /Filter /{$img['filter']}{$img['parms']}$smask",
                $img['data']
            ));
        }
        $resources = '<< /Font << ' . implode(' ', array_map(static fn ($k, $id) => "/$k $id 0 R", array_keys($fontIds), $fontIds)) . ' >>'
            . ($imageIds ? ' /XObject << ' . implode(' ', array_map(static fn ($k, $id) => "/$k $id 0 R", array_keys($imageIds), $imageIds)) . ' >>' : '')
            . ' >>';
        $resourcesId = $add($resources);

        $pageIds = [];
        foreach ($this->pages as $page) {
            $contentId = $add(self::stream('/Filter /FlateDecode', (string) gzcompress($page['content'], 9)));
            $annots = [];
            foreach ($page['links'] as [$x1, $y1, $x2, $y2, $url]) {
                $annots[] = $add(sprintf('<< /Type /Annot /Subtype /Link /Rect [%s %s %s %s] /Border [0 0 0] /A << /S /URI /URI (%s) >> >>', self::num($x1), self::num($y1), self::num($x2), self::num($y2), self::escape($url)));
            }
            $pageIds[] = $add(sprintf(
                '<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %s %s] /Resources %d 0 R /Contents %d 0 R%s >>',
                $pagesId, self::num($this->width), self::num($this->height), $resourcesId, $contentId,
                $annots ? ' /Annots [' . implode(' ', array_map(static fn ($id) => "$id 0 R", $annots)) . ']' : ''
            ));
        }
        $objects[$pagesId - 1] = '<< /Type /Pages /Kids [' . implode(' ', array_map(static fn ($id) => "$id 0 R", $pageIds)) . '] /Count ' . count($pageIds) . ' >>';
        $objects[$catalogId - 1] = "<< /Type /Catalog /Pages $pagesId 0 R >>";
        $infoId = $add(sprintf(
            '<< /Title %s /Producer (Dafnis HSE Compliance) /CreationDate (D:%s) >>',
            self::textString($this->title), date('YmdHis') . str_replace(':', "'", date('P')) . "'"
        ));

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer << /Size ' . (count($objects) + 1) . " /Root $catalogId 0 R /Info $infoId 0 R >>\nstartxref\n$xref\n%%EOF\n";
        return $pdf;
    }

    // ------------------------------------------------------------------ internos

    private function out(string $ops): void
    {
        if ($this->current < 0) {
            $this->addPage();
        }
        $this->pages[$this->current]['content'] .= $ops . "\n";
    }

    private function paint(string $path, ?array $fill, ?array $stroke, float $lineWidth): void
    {
        if ($fill === null && $stroke === null) {
            return;
        }
        $ops = 'q ';
        if ($fill !== null) {
            $ops .= self::fill($fill) . ' ';
        }
        if ($stroke !== null) {
            $ops .= self::stroke($stroke) . ' ' . self::num($lineWidth) . ' w ';
        }
        $this->out($ops . $path . ' ' . ($fill !== null && $stroke !== null ? 'B' : ($fill !== null ? 'f' : 'S')) . ' Q');
    }

    private function circlePath(float $cx, float $cy, float $r): string
    {
        $cy = $this->height - $cy;
        $k = $r * self::KAPPA;
        $n = static fn (float $v) => self::num($v);
        return sprintf('%s %s m ', $n($cx + $r), $n($cy))
            . sprintf('%s %s %s %s %s %s c ', $n($cx + $r), $n($cy + $k), $n($cx + $k), $n($cy + $r), $n($cx), $n($cy + $r))
            . sprintf('%s %s %s %s %s %s c ', $n($cx - $k), $n($cy + $r), $n($cx - $r), $n($cy + $k), $n($cx - $r), $n($cy))
            . sprintf('%s %s %s %s %s %s c ', $n($cx - $r), $n($cy - $k), $n($cx - $k), $n($cy - $r), $n($cx), $n($cy - $r))
            . sprintf('%s %s %s %s %s %s c h', $n($cx + $k), $n($cy - $r), $n($cx + $r), $n($cy - $k), $n($cx + $r), $n($cy));
    }

    /** UTF-8 para WinAnsi (Windows-1252); o que não existe nela vira "?". */
    public static function encode(string $text): string
    {
        $text = strtr($text, [
            "\u{2010}" => '-', "\u{2011}" => '-', "\u{2012}" => '-', "\u{2212}" => '-', "\u{202F}" => ' ', "\t" => ' ',
            // Caracteres invisíveis que vêm em textos colados (viravam "?").
            "\u{200B}" => '', "\u{200C}" => '', "\u{200D}" => '', "\u{2060}" => '', "\u{FEFF}" => '',
        ]);
        return (string) mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
    }

    private static function escape(string $s): string
    {
        return strtr($s, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '\\r', "\n" => '\\n']);
    }

    /** Texto de metadado (título): UTF-16 com BOM quando tem acento. */
    private static function textString(string $s): string
    {
        if (preg_match('/^[\x20-\x7E]*$/', $s)) {
            return '(' . self::escape($s) . ')';
        }
        return '<FEFF' . strtoupper(bin2hex((string) mb_convert_encoding($s, 'UTF-16BE', 'UTF-8'))) . '>';
    }

    private static function stream(string $dict, string $data): string
    {
        return '<< ' . $dict . ' /Length ' . strlen($data) . " >>\nstream\n" . $data . "\nendstream";
    }

    private static function fill(array $rgb): string
    {
        return self::rgb($rgb) . ' rg';
    }

    private static function stroke(array $rgb): string
    {
        return self::rgb($rgb) . ' RG';
    }

    private static function rgb(array $rgb): string
    {
        return implode(' ', array_map(static fn ($c) => self::num(max(0, min(255, (float) $c)) / 255), array_slice(array_values($rgb), 0, 3)));
    }

    private static function num(float $v): string
    {
        $s = rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
        return $s === '-0' || $s === '' ? '0' : $s;
    }

    // ------------------------------------------------------------------ imagens

    private static function parseImage(string $data): array
    {
        if (str_starts_with($data, "\xFF\xD8\xFF")) {
            $info = @getimagesizefromstring($data);
            if (!$info) {
                throw new RuntimeException('Imagem JPEG inválida.');
            }
            $channels = (int) ($info['channels'] ?? 3);
            $cs = match ($channels) { 1 => '/DeviceGray', 4 => '/DeviceCMYK', default => '/DeviceRGB' };
            return [
                'w' => $info[0], 'h' => $info[1], 'cs' => $cs, 'bpc' => 8, 'filter' => 'DCTDecode', 'data' => $data,
                'parms' => $channels === 4 ? ' /Decode [1 0 1 0 1 0 1 0]' : '',
            ];
        }
        if (str_starts_with($data, "\x89PNG\r\n\x1a\n")) {
            return self::parsePng($data);
        }
        throw new RuntimeException('Formato de imagem não suportado (use JPEG ou PNG).');
    }

    /**
     * PNG sem decodificar os pixels: os dados vão para o PDF com o mesmo preditor do PNG. Com canal
     * alfa, as cores e a transparência são separadas linha a linha (o filtro de cada linha vale para
     * as duas partes, porque ele compara sempre o mesmo canal do pixel vizinho).
     */
    private static function parsePng(string $data): array
    {
        $pos = 8;
        $ihdr = null;
        $palette = '';
        $trns = null;
        $idat = '';
        $length = strlen($data);
        while ($pos + 8 <= $length) {
            $size = unpack('N', substr($data, $pos, 4))[1];
            $type = substr($data, $pos + 4, 4);
            $chunk = substr($data, $pos + 8, $size);
            $pos += 12 + $size;
            if ($type === 'IHDR') {
                $ihdr = unpack('Nw/Nh/Cbits/Ccolor/Ccomp/Cfilter/Cinterlace', $chunk);
            } elseif ($type === 'PLTE') {
                $palette = $chunk;
            } elseif ($type === 'tRNS') {
                $trns = $chunk;
            } elseif ($type === 'IDAT') {
                $idat .= $chunk;
            } elseif ($type === 'IEND') {
                break;
            }
        }
        if (!$ihdr || $idat === '') {
            throw new RuntimeException('Imagem PNG inválida.');
        }
        ['w' => $w, 'h' => $h, 'bits' => $bits, 'color' => $color, 'interlace' => $interlace] = $ihdr;
        $paletted = $color === 3;
        if ($interlace || ($paletted ? !in_array($bits, [1, 2, 4, 8], true) : $bits !== 8) || !in_array($color, [0, 2, 3, 4, 6], true)) {
            throw new RuntimeException('PNG em formato não suportado (use PNG de 8 bits, não entrelaçado).');
        }
        if ($paletted && $trns !== null) {
            throw new RuntimeException('PNG com paleta e transparência não suportado (salve como PNG de 24/32 bits).');
        }
        $colors = match ($color) { 0, 3, 4 => 1, default => 3 };
        $parms = static fn (int $c, int $b) => " /DecodeParms << /Predictor 15 /Colors $c /BitsPerComponent $b /Columns $w >>";
        $cs = $paletted ? '[/Indexed /DeviceRGB ' . (intdiv(strlen($palette), 3) - 1) . ' <' . bin2hex($palette) . '>]' : ($colors === 1 ? '/DeviceGray' : '/DeviceRGB');
        $image = ['w' => $w, 'h' => $h, 'cs' => $cs, 'bpc' => $bits, 'filter' => 'FlateDecode', 'parms' => $parms($colors, $bits)];
        if ($color < 4) {
            return $image + ['data' => $idat];
        }

        $raw = @gzuncompress($idat);
        if ($raw === false) {
            throw new RuntimeException('Imagem PNG corrompida.');
        }
        $pixel = $color === 6 ? 4 : 2;
        $rowLength = 1 + $pixel * $w;
        $rgb = '';
        $alpha = '';
        $colorPattern = $color === 6 ? '/(.{3})./s' : '/(.)./s';
        $alphaPattern = $color === 6 ? '/.{3}(.)/s' : '/.(.)/s';
        for ($y = 0; $y < $h; $y++) {
            $filter = $raw[$y * $rowLength];
            $line = substr($raw, $y * $rowLength + 1, $rowLength - 1);
            $rgb .= $filter . preg_replace($colorPattern, '$1', $line);
            $alpha .= $filter . preg_replace($alphaPattern, '$1', $line);
        }
        return $image + [
            'data' => (string) gzcompress($rgb, 9),
            'alpha' => (string) gzcompress($alpha, 9),
            'alphaParms' => $parms(1, 8),
        ];
    }
}
