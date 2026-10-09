<?php
declare(strict_types=1);

namespace App\Services;

use App\Services\Pdf\Document;

/**
 * Desenha o certificado no modelo da Dafnis (A4 deitado, duas páginas):
 * - frente: "CERTIFICADO", selo da NR, faixa azul com o logo, data, nome, texto de conclusão com
 *   CPF, curso, período e carga horária, linha de assinatura do participante e o QR code de validação;
 * - verso: dados da empresa, título, conteúdo programático em três colunas, local de realização e
 *   as assinaturas dos instrutores e do responsável técnico.
 *
 * As medidas vêm do modelo em PowerPoint (10 x 7,5 polegadas), ampliadas para a altura do A4.
 * Recebe os dados prontos de Certificates::data().
 */
final class CertificatePdf
{
    private const W = 841.89;
    private const H = 595.28;
    private const NAVY = [41, 67, 96];
    private const GOLD = [191, 144, 69];
    private const TEAL = [21, 96, 130];
    private const FRAME = [198, 198, 198];
    private const INK = [17, 17, 17];

    private Document $pdf;
    private float $scale;
    private float $offset;

    private function __construct(private readonly array $d)
    {
        $this->pdf = new Document(self::W, self::H, 'Certificado ' . $d['code'] . ' - ' . $d['name']);
        $this->scale = self::H / 540;
        $this->offset = (self::W - 720 * $this->scale) / 2;
    }

    public static function render(array $data): string
    {
        $c = new self($data);
        $c->front();
        $c->back();
        return $c->pdf->output();
    }

    /** Posição horizontal do modelo (0 a 720) na página A4. */
    private function x(float $v): float
    {
        return $v * $this->scale + $this->offset;
    }

    /** Posição vertical ou medida do modelo na página A4. */
    private function u(float $v): float
    {
        return $v * $this->scale;
    }

    // ------------------------------------------------------------------ frente

    private function front(): void
    {
        $pdf = $this->pdf;
        $d = $this->d;
        $pdf->addPage();

        $bandX = $this->x(595.8);
        $pdf->rect($bandX, 0, self::W - $bandX, self::H, self::NAVY);

        $pdf->font('Times-Roman', $this->u(44));
        $pdf->text($this->x(269.6), $this->u(74.5), 'CERTIFICADO', 'center', self::INK);

        if (!empty($d['nr'])) {
            $this->badge((string) $d['nr']);
        }

        $left = $this->x(35);
        $width = $this->u(469);
        $pdf->rect($this->x(27.7), $this->u(95.4), $this->u(483.8), $this->u(418.6), null, self::FRAME, 0.75);

        $pdf->font('Times-Bold', $this->u(12));
        $pdf->text($left + $width, $this->u(152), $d['city_date'], 'right', self::INK);
        $pdf->text($left, $this->u(175.7), 'Certificamos', 'left', self::INK);

        $this->participantName(mb_strtoupper($d['name']), $left, $width);

        // Texto de conclusão: diminui a letra se o nome do curso for longo.
        $size = 12;
        do {
            $pdf->font('Times-Bold', $this->u($size));
            $lines = count($pdf->wrap($d['statement'], $width));
            $lineHeight = $this->u($size * 1.8);
            $size -= 0.5;
        } while ($this->u(284.4) + ($lines - 1) * $lineHeight > $this->u(432) && $size >= 8);
        $pdf->paragraph($left, $this->u(284.4), $width, $d['statement'], $lineHeight, 'justify', self::INK);

        $pdf->line($this->x(218.5), $this->u(459), $this->x(386.3), $this->u(459), 0.8, self::INK);
        $pdf->font('Times-Bold', $this->u(12));
        $pdf->text($this->x(302.4), $this->u(472), 'Participante', 'center', self::INK);

        // Logo no círculo com borda dourada, sobre a faixa.
        $cx = $this->x(596.9);
        $cy = $this->u(271);
        $pdf->circle($cx, $cy, $this->u(85.35), [255, 255, 255], self::GOLD, $this->u(1.75));
        $r = $this->u(77);
        $logo = BASE_PATH . '/public/assets/img/certificado-logo.jpg';
        $pdf->clipCircle($cx, $cy, $r, static function (Document $p) use ($cx, $cy, $r, $logo): void {
            $p->rect($cx - $r, $cy - $r, 2 * $r, 2 * $r, self::NAVY);
            if (is_file($logo)) {
                $p->image($logo, $cx - $r, $cy - $r, 2 * $r, 2 * $r);
            }
        });

        $this->verification($bandX);
    }

    /** Nome em destaque, sublinhado: reduz a letra para caber e, no limite, quebra em duas linhas. */
    private function participantName(string $name, float $left, float $width): void
    {
        $pdf = $this->pdf;
        $center = $left + $width / 2;
        for ($size = 28; $size >= 18; $size -= 0.5) {
            $pdf->font('Times-Bold', $this->u($size));
            if ($pdf->textWidth($name) <= $width) {
                $pdf->text($center, $this->u(234.7), $name, 'center', self::INK, 0, true);
                return;
            }
        }
        $pdf->font('Times-Bold', $this->u(20));
        $lines = array_slice($pdf->wrap($name, $width), 0, 2);
        foreach ($lines as $i => $line) {
            $pdf->text($center, $this->u(222 + $i * 26), $line, 'center', self::INK, 0, true);
        }
    }

    /** Selo da NR (placa amarela em losango) no quadro do canto, como no modelo. $label: "10", "31.7"... */
    private function badge(string $label): void
    {
        $pdf = $this->pdf;
        $pdf->rect($this->x(508.4), $this->u(26), $this->u(79.2), $this->u(59.4), [255, 255, 255], self::TEAL, $this->u(1.5));
        $cx = $this->x(548);
        $cy = $this->u(55.7);
        $diamond = fn (float $r) => [[$cx, $cy - $r], [$cx + $r, $cy], [$cx, $cy + $r], [$cx - $r, $cy]];
        $pdf->polygon($diamond($this->u(27.5)), $this->u(5), [255, 255, 255], [190, 190, 190], 0.4);
        $pdf->polygon($diamond($this->u(25.2)), $this->u(4.2), [20, 20, 20]);
        $pdf->polygon($diamond($this->u(23.4)), $this->u(3.8), [255, 204, 0]);
        $pdf->circle($cx, $cy - $this->u(17.5), $this->u(1.3), [20, 20, 20]);
        $pdf->circle($cx, $cy + $this->u(17.5), $this->u(1.3), [20, 20, 20]);
        $pdf->font('Helvetica-Bold', $this->u(12.5));
        $pdf->text($cx, $cy - $this->u(0.8), 'NR', 'center', [20, 20, 20]);
        $pdf->font('Helvetica-Bold', $this->u(match (true) { strlen($label) <= 2 => 12.5, strlen($label) === 3 => 10.5, default => 9 }));
        $pdf->text($cx, $cy + $this->u(10.6), $label, 'center', [214, 31, 38]);
    }

    /** QR code e código de validação na parte de baixo da faixa azul. */
    private function verification(float $bandX): void
    {
        $pdf = $this->pdf;
        $center = ($bandX + self::W) / 2;
        $matrix = QrCode::matrix($this->d['verify_url']);
        $modules = count($matrix);
        $module = $this->u(74) / ($modules + 8);
        $box = $module * ($modules + 8);
        $top = $this->u(410);
        $pdf->rect($center - $box / 2, $top, $box, $box, [255, 255, 255]);
        $pdf->modules($matrix, $center - $box / 2 + 4 * $module, $top + 4 * $module, $module, [0, 0, 0]);
        $pdf->link($center - $box / 2, $top, $box, $box, $this->d['verify_url']);

        $white = [255, 255, 255];
        $pdf->font('Helvetica-Bold', $this->u(7.5));
        $pdf->text($center, $top + $box + $this->u(13), 'Certificado ' . $this->d['code'], 'center', $white);
        $pdf->font('Helvetica', $this->u(6.2));
        $pdf->text($center, $top + $box + $this->u(23), 'Confira a autenticidade em', 'center', $white);
        $pdf->text($center, $top + $box + $this->u(31), $this->d['verify_host'], 'center', $white);
    }

    // ------------------------------------------------------------------ verso

    private function back(): void
    {
        $pdf = $this->pdf;
        $d = $this->d;
        $pdf->addPage();
        $center = $this->x(360);

        // Cabeçalho com os dados da empresa (o site vira link).
        $pdf->font('Helvetica-BoldOblique', $this->u(7));
        $first = trim($d['legal_name'] . ($d['site'] ? ' – ' : ''));
        $total = $pdf->textWidth($first . ($d['site'] ? ' ' . $d['site'] : ''));
        $start = $center - $total / 2;
        $pdf->text($start, $this->u(18.7), $first, 'left', self::INK);
        if ($d['site']) {
            $siteX = $start + $pdf->textWidth($first . ' ');
            $pdf->text($siteX, $this->u(18.7), $d['site'], 'left', self::TEAL, 0, true);
            $pdf->link($siteX, $this->u(11), $pdf->textWidth($d['site']), $this->u(10), 'https://' . preg_replace('#^https?://#', '', $d['site']));
        }
        if ($d['contact_line'] !== '') {
            $pdf->text($center, $this->u(28), $d['contact_line'], 'center', self::INK);
        }

        $title = 'Treinamento de ' . $d['course_name'];
        $size = 12;
        do {
            $pdf->font('Times-Bold', $this->u($size));
            $size -= 0.5;
        } while ($pdf->textWidth($title) > $this->u(640) && $size > 8);
        $pdf->text($center, $this->u(47.5), $title, 'center', self::INK);
        $pdf->font('Times-Bold', $this->u(12));
        $pdf->text($center, $this->u(61.2), $d['hours_label'] . ' (' . $d['kind'] . ')', 'center', self::INK);
        $pdf->font('Times-Bold', $this->u(16));
        $pdf->text($center, $this->u(84.6), 'Conteúdo Programático:', 'center', self::INK);

        $this->syllabus($d['syllabus'], $this->x(83.2), $this->u(99), $this->u(553.6), $this->u(286));

        $pdf->font('Times-Bold', $this->u(9));
        $pdf->paragraph($this->x(90), $this->u(406), $this->u(540), 'Local de realização: ' . $d['location'], $this->u(11), 'center', self::INK);

        $this->signatures($d['signers']);

        $pdf->font('Helvetica', $this->u(6.5));
        $pdf->text($center, $this->u(530), 'Certificado ' . $d['code'] . ' · emitido em ' . date_br($d['issued_at']) . ' · confira a autenticidade em ' . $d['verify_host'], 'center', [90, 90, 90]);
    }

    /**
     * Conteúdo programático em três colunas emolduradas, um item por linha do texto. Itens que
     * começam com "1." ou "a)" ficam com recuo. A letra diminui até tudo caber.
     */
    private function syllabus(string $text, float $x, float $y, float $width, float $height): void
    {
        $pdf = $this->pdf;
        $items = array_values(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: []), static fn ($l) => $l !== ''));
        $column = $width / 3;
        $pad = $this->u(6);
        $inner = $column - 2 * $pad;
        $placed = [];
        for ($size = 9.5; $size >= 5.5; $size -= 0.25) {
            $placed = $this->flowSyllabus($items, $this->u($size), $inner, $height - 2 * $pad, $size <= 5.5);
            if ($placed !== null) {
                break;
            }
        }
        $pdf->rect($x, $y, $width, $height, null, self::INK, 1.1);
        $pdf->line($x + $column, $y, $x + $column, $y + $height, 1.1, self::INK);
        $pdf->line($x + 2 * $column, $y, $x + 2 * $column, $y + $height, 1.1, self::INK);
        foreach ($placed ?? [] as [$col, $top, $prefix, $line, $indent, $fontSize]) {
            $pdf->font('Helvetica-Bold', $fontSize);
            $baseline = $y + $pad + $top + $fontSize;
            $left = $x + $col * $column + $pad;
            if ($prefix !== '') {
                $pdf->text($left, $baseline, $prefix, 'left', self::INK);
            }
            $pdf->text($left + $indent, $baseline, $line, 'left', self::INK);
        }
    }

    /** @return list<array>|null linhas posicionadas, ou null se não couber com esta letra */
    private function flowSyllabus(array $items, float $size, float $width, float $height, bool $force): ?array
    {
        $pdf = $this->pdf;
        $pdf->font('Helvetica-Bold', $size);
        $lineHeight = $size * 1.3;
        $indent = $pdf->textWidth('00. ');
        $blocks = [];
        foreach ($items as $item) {
            $prefix = '';
            $body = $item;
            if (preg_match('/^(\d{1,2}[.)]|[a-zA-Z][.)])\s+(.+)$/u', $item, $m)) {
                $prefix = $m[1];
                $body = $m[2];
            }
            $blocks[] = [$prefix, $pdf->wrap($body, $width - ($prefix !== '' ? $indent : 0)), str_ends_with($body, ':')];
        }
        $col = 0;
        $top = 0.0;
        $out = [];
        foreach ($blocks as $k => [$prefix, $lines, $opensList]) {
            $blockHeight = count($lines) * $lineHeight;
            // Título de tópico ("4. Medidas...:") não fica sozinho no fim da coluna: desce com o primeiro item.
            $needed = $blockHeight + ($opensList && isset($blocks[$k + 1]) ? $lineHeight : 0);
            if ($top > 0 && $top + $needed > $height) {
                $col++;
                $top = 0.0;
            }
            if ($col > 2 || ($blockHeight > $height && !$force)) {
                if (!$force) {
                    return null;
                }
                $col = min($col, 2);
            }
            foreach ($lines as $i => $line) {
                $out[] = [$col, $top, $i === 0 ? $prefix : '', $line, $prefix !== '' ? $indent : 0, $size];
                $top += $lineHeight;
            }
        }
        return $out;
    }

    /** Até quatro assinaturas lado a lado: imagem da assinatura (se houver), linha, nome, função e registro. */
    private function signatures(array $signers): void
    {
        $pdf = $this->pdf;
        $signers = array_slice($signers, 0, 4);
        $n = count($signers);
        if (!$n) {
            return;
        }
        $gap = $n === 4 ? 172 : 224;
        $lineWidth = $this->u($n === 4 ? 150 : 178);
        foreach ($signers as $i => $s) {
            $cx = $this->x(360 + ($i - ($n - 1) / 2) * $gap);
            $lineY = $this->u(476);
            if (!empty($s['signature_file']) && is_file($s['signature_file'])) {
                $ratio = Document::imageRatio($s['signature_file']);
                $h = $this->u(36);
                $w = min($lineWidth, $h * $ratio);
                $h = $w / $ratio;
                try {
                    $pdf->image($s['signature_file'], $cx - $w / 2, $lineY - $h + $this->u(4), $w, $h);
                } catch (\RuntimeException) {
                    // Imagem que o gerador não lê: fica só a linha para assinar à mão.
                }
            }
            $pdf->line($cx - $lineWidth / 2, $lineY, $cx + $lineWidth / 2, $lineY, 0.7, self::INK);
            $this->fitted(mb_strtoupper((string) $s['name']), $cx, $this->u(486), 'Times-Bold', 9, $lineWidth + $this->u(30));
            $this->fitted(mb_strtoupper((string) $s['role']), $cx, $this->u(495), 'Times-Bold', 7.5, $lineWidth + $this->u(30));
            if (!empty($s['registry'])) {
                $this->fitted(mb_strtoupper((string) $s['registry']), $cx, $this->u(504), 'Times-Bold', 7.5, $lineWidth + $this->u(30));
            }
        }
    }

    /** Uma linha centralizada que diminui a letra para caber na largura. */
    private function fitted(string $text, float $cx, float $y, string $font, float $size, float $maxWidth): void
    {
        for (; $size > 5; $size -= 0.25) {
            $this->pdf->font($font, $this->u($size));
            if ($this->pdf->textWidth($text) <= $maxWidth) {
                break;
            }
        }
        $this->pdf->text($cx, $y, $text, 'center', self::INK);
    }
}
