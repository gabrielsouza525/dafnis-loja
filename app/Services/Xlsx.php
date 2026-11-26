<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Planilha do Excel (.xlsx) sem biblioteca: abas com texto, números, datas, valores em reais e
 * fórmulas, no visual da planilha de medição que as empresas recebem (cabeçalho azul com letra
 * branca, bordas finas, R$ contábil). O .xlsx é um ZIP de XMLs, montado aqui mesmo porque a
 * extensão zip do PHP nem sempre existe na hospedagem.
 *
 * Célula: null (vazia), string, int/float, ou ['v' => valor, 's' => estilo, 'f' => 'SUM(J2:J9)'].
 * Com o estilo DATE, um valor "AAAA-MM-DD" vira data do Excel; outro texto fica como texto.
 */
final class Xlsx
{
    /** Estilos (posição no cellXfs de styles.xml). */
    public const HEAD = 1;  // cabeçalho: negrito branco no azul
    public const TEXT = 2;
    public const DATE = 3;  // dd/mm/aaaa no Excel em português
    public const MONEY = 4; // R$ contábil
    public const INT = 5;
    public const TOTAL = 6; // R$ em negrito
    public const LABEL = 7; // negrito, alinhado à direita

    private const MONEY_FORMAT = '_-"R$"\ * #,##0.00_-;\-"R$"\ * #,##0.00_-;_-"R$"\ * "-"??_-;_-@_-';

    /** @var list<array{name: string, rows: list<list<mixed>>, options: array}> */
    private array $sheets = [];
    /** @var array<string, int> */
    private array $strings = [];
    private int $stringRefs = 0;

    public function __construct(private string $title = '')
    {
    }

    /**
     * @param list<list<mixed>> $rows linhas a partir de A1
     * @param array{widths?: list<float|int>, filter?: string, freeze?: bool} $options filter = "A1:J20";
     *        freeze = primeira linha fixa na tela e repetida em cada página impressa
     */
    public function sheet(string $name, array $rows, array $options = []): self
    {
        $name = mb_substr(trim((string) preg_replace('/[\[\]:*?\/\\\\]+/', ' ', $name)), 0, 31);
        $this->sheets[] = ['name' => $name !== '' ? $name : 'Planilha' . (count($this->sheets) + 1), 'rows' => $rows, 'options' => $options];
        return $this;
    }

    public function build(): string
    {
        $this->strings = [];
        $this->stringRefs = 0;
        $files = [];
        foreach ($this->sheets as $i => $sheet) {
            $files['xl/worksheets/sheet' . ($i + 1) . '.xml'] = $this->worksheet($sheet);
        }
        $n = count($this->sheets);
        $overrides = '';
        $rels = '';
        $list = '';
        $names = '';
        foreach ($this->sheets as $i => $sheet) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $rels .= '<Relationship Id="rId' . ($i + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
            $list .= '<sheet name="' . self::x($sheet['name']) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
            $quoted = "'" . str_replace("'", "''", $sheet['name']) . "'";
            if (!empty($sheet['options']['filter'])) {
                $names .= '<definedName name="_xlnm._FilterDatabase" localSheetId="' . $i . '" hidden="1">' . self::x($quoted . '!' . self::absolute($sheet['options']['filter'])) . '</definedName>';
            }
            if (!empty($sheet['options']['freeze'])) {
                $names .= '<definedName name="_xlnm.Print_Titles" localSheetId="' . $i . '">' . self::x($quoted . '!$1:$1') . '</definedName>';
            }
        }
        $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n";
        $strings = '';
        foreach (array_keys($this->strings) as $s) {
            $s = (string) $s;
            $strings .= '<si><t' . ($s !== trim($s) ? ' xml:space="preserve"' : '') . '>' . self::x($s) . '</t></si>';
        }
        $now = gmdate('Y-m-d\TH:i:s\Z');
        return self::zip([
            '[Content_Types].xml' => $head . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . $overrides
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
                . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
                . '</Types>',
            '_rels/.rels' => $head . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
                . '</Relationships>',
            'docProps/core.xml' => $head . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
                . ($this->title !== '' ? '<dc:title>' . self::x($this->title) . '</dc:title>' : '')
                . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
                . '</cp:coreProperties>',
            'xl/workbook.xml' => $head . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<bookViews><workbookView/></bookViews><sheets>' . $list . '</sheets>'
                . ($names !== '' ? '<definedNames>' . $names . '</definedNames>' : '')
                . '<calcPr calcId="191029" fullCalcOnLoad="1"/></workbook>',
            'xl/_rels/workbook.xml.rels' => $head . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels
                . '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '<Relationship Id="rId' . ($n + 2) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
                . '</Relationships>',
            'xl/styles.xml' => $head . self::styles(),
            'xl/sharedStrings.xml' => $head . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $this->stringRefs . '" uniqueCount="' . count($this->strings) . '">' . $strings . '</sst>',
        ] + $files);
    }

    /** Letra da coluna: 0 → A, 25 → Z, 26 → AA. */
    public static function col(int $index): string
    {
        $s = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $s = chr(65 + ($n - 1) % 26) . $s;
        }
        return $s;
    }

    private function worksheet(array $sheet): string
    {
        $o = $sheet['options'];
        $data = '';
        $maxCol = 0;
        foreach ($sheet['rows'] as $r => $row) {
            $cells = '';
            foreach (array_values($row) as $c => $cell) {
                $cells .= $this->cell(self::col($c) . ($r + 1), $cell);
                $maxCol = max($maxCol, $c);
            }
            $data .= '<row r="' . ($r + 1) . '">' . $cells . '</row>';
        }
        $cols = '';
        foreach (array_values($o['widths'] ?? []) as $i => $w) {
            $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . round((float) $w, 2) . '" customWidth="1"/>';
        }
        $last = max(1, count($sheet['rows']));
        $freeze = !empty($o['freeze']);
        return '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            . '<dimension ref="A1:' . self::col($maxCol) . $last . '"/>'
            . '<sheetViews><sheetView workbookViewId="0">'
            . ($freeze ? '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A2" sqref="A2"/>' : '')
            . '</sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . ($cols !== '' ? '<cols>' . $cols . '</cols>' : '')
            . '<sheetData>' . $data . '</sheetData>'
            . (!empty($o['filter']) ? '<autoFilter ref="' . self::x($o['filter']) . '"/>' : '')
            . '<pageMargins left="0.4" right="0.4" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>'
            . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            . '</worksheet>';
    }

    private function cell(string $ref, mixed $cell): string
    {
        if ($cell === null || $cell === '') {
            return '';
        }
        if (!is_array($cell)) {
            $cell = ['v' => $cell];
        }
        $v = $cell['v'] ?? null;
        $style = (int) ($cell['s'] ?? (is_int($v) || is_float($v) ? self::INT : self::TEXT));
        $s = ' s="' . $style . '"';
        if (isset($cell['f'])) {
            $cached = is_int($v) || is_float($v) ? '<v>' . self::number($v) . '</v>' : '';
            return '<c r="' . $ref . '"' . $s . '><f>' . self::x((string) $cell['f']) . '</f>' . $cached . '</c>';
        }
        if ($v === null || $v === '') {
            return '<c r="' . $ref . '"' . $s . '/>';
        }
        if ($style === self::DATE && is_string($v) && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) {
            // Data do Excel: dias desde 30/12/1899.
            $days = intdiv(gmmktime(0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]), 86400) + 25569;
            return '<c r="' . $ref . '"' . $s . '><v>' . $days . '</v></c>';
        }
        if (is_int($v) || is_float($v)) {
            return '<c r="' . $ref . '"' . $s . '><v>' . self::number($v) . '</v></c>';
        }
        if ($style === self::DATE) {
            $s = ' s="' . self::TEXT . '"';
        }
        $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $v);
        if (!isset($this->strings[$text])) {
            $this->strings[$text] = count($this->strings);
        }
        $this->stringRefs++;
        return '<c r="' . $ref . '"' . $s . ' t="s"><v>' . $this->strings[$text] . '</v></c>';
    }

    private static function styles(): string
    {
        $font = static fn (string $extra) => '<font>' . $extra . '<sz val="11"/><name val="Aptos Narrow"/><family val="2"/></font>';
        $thin = '<left style="thin"><color auto="1"/></left><right style="thin"><color auto="1"/></right><top style="thin"><color auto="1"/></top><bottom style="thin"><color auto="1"/></bottom><diagonal/>';
        $xf = static fn (int $fmt, int $font, int $fill, int $border, string $align = '') => '<xf numFmtId="' . $fmt . '" fontId="' . $font . '" fillId="' . $fill . '" borderId="' . $border . '" xfId="0"'
            . ($fmt ? ' applyNumberFormat="1"' : '') . ($font ? ' applyFont="1"' : '') . ($fill ? ' applyFill="1"' : '') . ($border ? ' applyBorder="1"' : '')
            . ($align !== '' ? ' applyAlignment="1"><alignment ' . $align . '/></xf>' : '/>');
        return '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="' . self::x(self::MONEY_FORMAT) . '"/></numFmts>'
            . '<fonts count="3">' . $font('') . $font('<b/><color rgb="FFFFFFFF"/>') . $font('<b/>') . '</fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF156082"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border>' . $thin . '</border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="8">'
            . $xf(0, 0, 0, 0)
            . $xf(0, 1, 2, 1, 'horizontal="left" vertical="center"')  // HEAD
            . $xf(0, 0, 0, 1, 'horizontal="left"')                     // TEXT
            . $xf(14, 0, 0, 1, 'horizontal="left"')                    // DATE
            . $xf(164, 0, 0, 1)                                        // MONEY
            . $xf(1, 0, 0, 1, 'horizontal="center"')                   // INT
            . $xf(164, 2, 0, 1)                                        // TOTAL
            . $xf(0, 2, 0, 1, 'horizontal="right"')                    // LABEL
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    /** "A1:J20" → "$A$1:$J$20" */
    private static function absolute(string $range): string
    {
        return (string) preg_replace('/([A-Z]+)(\d+)/', '\$$1\$$2', $range);
    }

    private static function number(int|float $v): string
    {
        return is_int($v) ? (string) $v : rtrim(rtrim(sprintf('%.6F', $v), '0'), '.');
    }

    private static function x(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** ZIP com os arquivos comprimidos (deflate), como o Excel espera. @param array<string, string> $files */
    private static function zip(array $files): string
    {
        $t = getdate();
        $time = ($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2);
        $date = (max(0, $t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'];
        $out = '';
        $dir = '';
        foreach ($files as $name => $content) {
            $name = (string) $name;
            $crc = crc32($content);
            $packed = function_exists('gzdeflate') ? gzdeflate($content, 6) : false;
            [$method, $body] = $packed !== false ? [8, $packed] : [0, $content];
            $offset = strlen($out);
            $out .= "PK\x03\x04" . pack('vvvvvVVVvv', 20, 0, $method, $time, $date, $crc, strlen($body), strlen($content), strlen($name), 0) . $name . $body;
            $dir .= "PK\x01\x02" . pack('vvvvvvVVVvvvvvVV', 20, 20, 0, $method, $time, $date, $crc, strlen($body), strlen($content), strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
        }
        return $out . $dir . "PK\x05\x06" . pack('vvvvVVv', 0, 0, count($files), count($files), strlen($dir), strlen($out), 0);
    }
}
