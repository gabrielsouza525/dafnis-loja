<?php
declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * QR Code (ISO/IEC 18004) em SVG, sem dependências: modo byte, correção de erros nível M,
 * versões 1 a 15 (até ~410 bytes). Usado para o aplicativo autenticador ler a chave da
 * verificação em duas etapas. Segue o gerador de referência do Project Nayuki (MIT).
 */
final class QrCode
{
    // Por versão (índice = versão), nível M
    private const ECC_PER_BLOCK = [0, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24];
    private const NUM_BLOCKS = [0, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10];
    private const FORMAT_BITS_M = 0;

    private int $size;
    /** @var array<int, array<int, bool>> [y][x], true = escuro */
    private array $modules;
    /** @var array<int, array<int, bool>> */
    private array $isFunction;

    /** @return array<int, array<int, bool>> módulos [y][x], true = escuro */
    public static function matrix(string $text): array
    {
        return self::encode($text)->modules;
    }

    /** SVG com 4 módulos de margem (exigida pelos leitores). */
    public static function svg(string $text, string $label = 'QR Code'): string
    {
        $qr = self::encode($text);
        $margin = 4;
        $dim = $qr->size + 2 * $margin;
        $path = '';
        foreach ($qr->modules as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    $path .= 'M' . ($x + $margin) . ',' . ($y + $margin) . 'h1v1h-1z';
                }
            }
        }
        return '<svg class="qr" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $dim . ' ' . $dim . '" role="img" aria-label="' . e($label) . '" shape-rendering="crispEdges">'
            . '<rect width="' . $dim . '" height="' . $dim . '" fill="#ffffff"/><path d="' . $path . '" fill="#0E1726"/></svg>';
    }

    private static function encode(string $text): self
    {
        $length = strlen($text);
        $version = 0;
        for ($v = 1; $v <= 15; $v++) {
            if (4 + ($v <= 9 ? 8 : 16) + 8 * $length <= self::dataCodewords($v) * 8) {
                $version = $v;
                break;
            }
        }
        if ($version === 0) {
            throw new InvalidArgumentException('Texto grande demais para o QR Code.');
        }

        // Segmento em modo byte, terminador e preenchimento
        $bits = [];
        self::appendBits($bits, 0x4, 4);
        self::appendBits($bits, $length, $version <= 9 ? 8 : 16);
        for ($i = 0; $i < $length; $i++) {
            self::appendBits($bits, ord($text[$i]), 8);
        }
        $capacity = self::dataCodewords($version) * 8;
        self::appendBits($bits, 0, min(4, $capacity - count($bits)));
        self::appendBits($bits, 0, (8 - count($bits) % 8) % 8);
        $codewords = [];
        for ($i = 0, $n = count($bits); $i < $n; $i += 8) {
            $byte = 0;
            for ($j = 0; $j < 8; $j++) {
                $byte = ($byte << 1) | $bits[$i + $j];
            }
            $codewords[] = $byte;
        }
        for ($pad = 0xEC; count($codewords) < self::dataCodewords($version); $pad ^= 0xEC ^ 0x11) {
            $codewords[] = $pad;
        }
        return new self($version, $codewords);
    }

    private function __construct(private int $version, array $dataCodewords)
    {
        $this->size = $version * 4 + 17;
        $row = array_fill(0, $this->size, false);
        $this->modules = array_fill(0, $this->size, $row);
        $this->isFunction = array_fill(0, $this->size, $row);

        $this->drawFunctionPatterns();
        $this->drawCodewords($this->addEccAndInterleave($dataCodewords));

        // A máscara com menor penalidade deixa o código mais fácil de ler
        $best = 0;
        $minPenalty = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->applyMask($mask);
            $this->drawFormatBits($mask);
            $penalty = $this->penalty();
            if ($penalty < $minPenalty) {
                $best = $mask;
                $minPenalty = $penalty;
            }
            $this->applyMask($mask); // a máscara é um XOR: aplicar de novo desfaz
        }
        $this->applyMask($best);
        $this->drawFormatBits($best);
    }

    private function drawFunctionPatterns(): void
    {
        for ($i = 0; $i < $this->size; $i++) {
            $this->setFunction(6, $i, $i % 2 === 0);
            $this->setFunction($i, 6, $i % 2 === 0);
        }
        $this->drawFinder(3, 3);
        $this->drawFinder($this->size - 4, 3);
        $this->drawFinder(3, $this->size - 4);

        $positions = $this->alignmentPositions();
        $count = count($positions);
        for ($i = 0; $i < $count; $i++) {
            for ($j = 0; $j < $count; $j++) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $count - 1) || ($i === $count - 1 && $j === 0)) {
                    continue; // cantos ocupados pelos localizadores
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $this->setFunction($positions[$i] + $dx, $positions[$j] + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        $this->drawFormatBits(0); // reserva a área; a máscara final redesenha
        $this->drawVersion();
    }

    private function drawFinder(int $x, int $y): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $xx = $x + $dx;
                $yy = $y + $dy;
                if ($xx >= 0 && $xx < $this->size && $yy >= 0 && $yy < $this->size) {
                    $dist = max(abs($dx), abs($dy));
                    $this->setFunction($xx, $yy, $dist !== 2 && $dist !== 4);
                }
            }
        }
    }

    private function drawFormatBits(int $mask): void
    {
        $data = (self::FORMAT_BITS_M << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;

        for ($i = 0; $i <= 5; $i++) {
            $this->setFunction(8, $i, self::bit($bits, $i));
        }
        $this->setFunction(8, 7, self::bit($bits, 6));
        $this->setFunction(8, 8, self::bit($bits, 7));
        $this->setFunction(7, 8, self::bit($bits, 8));
        for ($i = 9; $i < 15; $i++) {
            $this->setFunction(14 - $i, 8, self::bit($bits, $i));
        }
        for ($i = 0; $i < 8; $i++) {
            $this->setFunction($this->size - 1 - $i, 8, self::bit($bits, $i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFunction(8, $this->size - 15 + $i, self::bit($bits, $i));
        }
        $this->setFunction(8, $this->size - 8, true); // módulo sempre escuro
    }

    private function drawVersion(): void
    {
        if ($this->version < 7) {
            return;
        }
        $rem = $this->version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $bits = ($this->version << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $bit = self::bit($bits, $i);
            $a = $this->size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $this->setFunction($a, $b, $bit);
            $this->setFunction($b, $a, $bit);
        }
    }

    private function addEccAndInterleave(array $data): array
    {
        $numBlocks = self::NUM_BLOCKS[$this->version];
        $eccLen = self::ECC_PER_BLOCK[$this->version];
        $rawCodewords = intdiv(self::rawDataModules($this->version), 8);
        $numShort = $numBlocks - $rawCodewords % $numBlocks;
        $shortLen = intdiv($rawCodewords, $numBlocks);
        $divisor = self::rsDivisor($eccLen);

        $blocks = [];
        $k = 0;
        for ($i = 0; $i < $numBlocks; $i++) {
            $datLen = $shortLen - $eccLen + ($i < $numShort ? 0 : 1);
            $dat = array_slice($data, $k, $datLen);
            $k += $datLen;
            $ecc = self::rsRemainder($dat, $divisor);
            if ($i < $numShort) {
                $dat[] = 0; // posição vazia, pulada no entrelaçamento
            }
            $blocks[] = array_merge($dat, $ecc);
        }
        $result = [];
        for ($i = 0, $len = count($blocks[0]); $i < $len; $i++) {
            foreach ($blocks as $j => $block) {
                if ($i !== $shortLen - $eccLen || $j >= $numShort) {
                    $result[] = $block[$i];
                }
            }
        }
        return $result;
    }

    private function drawCodewords(array $data): void
    {
        $i = 0;
        $total = count($data) * 8;
        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5; // pula a coluna do padrão de tempo
            }
            for ($vert = 0; $vert < $this->size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $this->size - 1 - $vert : $vert;
                    if (!$this->isFunction[$y][$x] && $i < $total) {
                        $this->modules[$y][$x] = self::bit($data[$i >> 3], 7 - ($i & 7));
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->isFunction[$y][$x]) {
                    continue;
                }
                $invert = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => $x * $y % 2 + $x * $y % 3 === 0,
                    6 => ($x * $y % 2 + $x * $y % 3) % 2 === 0,
                    default => (($x + $y) % 2 + $x * $y % 3) % 2 === 0,
                };
                if ($invert) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    /** Penalidades da norma: sequências longas, blocos 2×2, padrões parecidos com localizadores e equilíbrio claro/escuro. */
    private function penalty(): int
    {
        $result = 0;
        $n = $this->size;
        $lines = [];
        for ($i = 0; $i < $n; $i++) {
            $lines[] = $this->modules[$i];
            $lines[] = array_column($this->modules, $i);
        }
        $finderLike = [[1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0], [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1]];
        foreach ($lines as $line) {
            $run = 1;
            for ($i = 1; $i <= $n; $i++) {
                if ($i < $n && $line[$i] === $line[$i - 1]) {
                    $run++;
                    continue;
                }
                if ($run >= 5) {
                    $result += 3 + ($run - 5);
                }
                $run = 1;
            }
            // Margem clara dos dois lados conta para o padrão 1:1:3:1:1
            $padded = array_merge([0, 0, 0, 0], array_map('intval', $line), [0, 0, 0, 0]);
            for ($i = 0, $last = count($padded) - 11; $i <= $last; $i++) {
                $window = array_slice($padded, $i, 11);
                if ($window === $finderLike[0] || $window === $finderLike[1]) {
                    $result += 40;
                }
            }
        }
        $dark = 0;
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                $dark += $this->modules[$y][$x] ? 1 : 0;
                if ($x < $n - 1 && $y < $n - 1) {
                    $c = $this->modules[$y][$x];
                    if ($c === $this->modules[$y][$x + 1] && $c === $this->modules[$y + 1][$x] && $c === $this->modules[$y + 1][$x + 1]) {
                        $result += 3;
                    }
                }
            }
        }
        $total = $n * $n;
        $k = intdiv(abs($dark * 20 - $total * 10) + $total - 1, $total) - 1;
        return $result + max(0, $k) * 10;
    }

    private function setFunction(int $x, int $y, bool $dark): void
    {
        $this->modules[$y][$x] = $dark;
        $this->isFunction[$y][$x] = true;
    }

    private function alignmentPositions(): array
    {
        if ($this->version === 1) {
            return [];
        }
        $count = intdiv($this->version, 7) + 2;
        $step = intdiv($this->version * 8 + $count * 3 + 5, $count * 4 - 4) * 2;
        $result = [];
        for ($i = 0; $i < $count - 1; $i++) {
            $result[] = $this->size - 7 - $i * $step;
        }
        $result[] = 6;
        return array_reverse($result);
    }

    private static function rawDataModules(int $version): int
    {
        $result = (16 * $version + 128) * $version + 64;
        if ($version >= 2) {
            $count = intdiv($version, 7) + 2;
            $result -= (25 * $count - 10) * $count - 55;
            if ($version >= 7) {
                $result -= 36;
            }
        }
        return $result;
    }

    private static function dataCodewords(int $version): int
    {
        return intdiv(self::rawDataModules($version), 8) - self::ECC_PER_BLOCK[$version] * self::NUM_BLOCKS[$version];
    }

    private static function rsDivisor(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::gfMultiply($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = self::gfMultiply($root, 0x02);
        }
        return $result;
    }

    private static function rsRemainder(array $data, array $divisor): array
    {
        $result = array_fill(0, count($divisor), 0);
        foreach ($data as $byte) {
            $factor = $byte ^ array_shift($result);
            $result[] = 0;
            foreach ($divisor as $i => $coef) {
                $result[$i] ^= self::gfMultiply($coef, $factor);
            }
        }
        return $result;
    }

    private static function gfMultiply(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z;
    }

    private static function appendBits(array &$bits, int $value, int $length): void
    {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    private static function bit(int $value, int $index): bool
    {
        return (($value >> $index) & 1) !== 0;
    }
}
