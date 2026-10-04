<?php
declare(strict_types=1);

namespace App\Core;

class Response
{
    /** Códigos fora do padrão HTTP que a loja usa, com a frase enviada na linha de status. */
    private const NONSTANDARD = [419 => 'Page Expired'];

    private ?string $filePath = null;

    /** Trecho do arquivo pedido com Range (vídeos e áudios dos cursos): [início, fim] inclusivos. */
    private ?array $range = null;

    public function __construct(
        protected string $body = '',
        protected int $status = 200,
        protected array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8', 'Cache-Control' => 'no-store']
        );
    }

    public static function redirect(string $to, int $status = 302): self
    {
        $location = preg_match('#^https?://#', $to) ? $to : url($to);
        return new self('', $status, ['Location' => $location]);
    }

    public static function text(string $body, string $contentType = 'text/plain; charset=UTF-8', int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => $contentType]);
    }

    public static function file(string $path, string $mime, array $headers = []): self
    {
        $r = new self('', 200, array_merge([
            'Content-Type' => $mime,
            'Content-Length' => (string) filesize($path),
            'X-Content-Type-Options' => 'nosniff',
        ], $headers));
        $r->filePath = $path;
        return $r;
    }

    /**
     * Arquivo com suporte a Range (206), para o navegador avançar vídeos e áudios sem baixar tudo.
     * Cabeçalho Range inválido ou fora do arquivo responde 416.
     */
    public static function fileRange(string $path, string $mime, ?string $rangeHeader, array $headers = []): self
    {
        $size = (int) filesize($path);
        $r = self::file($path, $mime, array_merge(['Accept-Ranges' => 'bytes'], $headers));
        if ($rangeHeader === null || $rangeHeader === '' || $size === 0) {
            return $r;
        }
        if (!preg_match('/^bytes=(\d*)-(\d*)$/', trim($rangeHeader), $m) || ($m[1] === '' && $m[2] === '')) {
            return $r; // vários trechos ou formato desconhecido: entrega o arquivo inteiro
        }
        if ($m[1] === '') {
            $start = max(0, $size - (int) $m[2]);
            $end = $size - 1;
        } else {
            $start = (int) $m[1];
            $end = $m[2] === '' ? $size - 1 : min((int) $m[2], $size - 1);
        }
        if ($start > $end || $start >= $size) {
            $r->status = 416;
            $r->filePath = null;
            $r->headers['Content-Range'] = 'bytes */' . $size;
            $r->headers['Content-Length'] = '0';
            return $r;
        }
        $r->status = 206;
        $r->range = [$start, $end];
        $r->headers['Content-Range'] = sprintf('bytes %d-%d/%d', $start, $end, $size);
        $r->headers['Content-Length'] = (string) ($end - $start + 1);
        return $r;
    }

    public static function download(string $content, string $filename, string $mime): self
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
        return new self($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . $safe . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function withHeader(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function header(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            if (isset(self::NONSTANDARD[$this->status])) {
                // O Apache (mod_php) troca por 500 os códigos que não conhece, como o 419,
                // quando a linha de status vem sem a frase. Com a frase, ele respeita o código.
                $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
                header(sprintf('%s %d %s', $protocol, $this->status, self::NONSTANDARD[$this->status]), true, $this->status);
            } else {
                http_response_code($this->status);
            }
            foreach ($this->headers as $name => $value) {
                header("$name: $value");
            }
        }
        if ($this->filePath !== null && $this->range !== null) {
            [$start, $end] = $this->range;
            $fh = fopen($this->filePath, 'rb');
            fseek($fh, $start);
            $left = $end - $start + 1;
            while ($left > 0 && !feof($fh)) {
                $chunk = fread($fh, min(1048576, $left));
                if ($chunk === false) {
                    break;
                }
                echo $chunk;
                $left -= strlen($chunk);
            }
            fclose($fh);
            return;
        }
        if ($this->filePath !== null) {
            readfile($this->filePath);
            return;
        }
        echo $this->body;
    }
}
