<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    private array $params = [];
    private ?array $json = null;

    private function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query,
        private readonly array $body,
        private readonly array $files,
        private readonly array $server,
        private readonly array $cookies,
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri = rawurldecode($uri);

        $base = base_path_prefix();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $path = '/' . trim($uri, '/');

        return new self($method, $path, $_GET, $_POST, $_FILES, $_SERVER, $_COOKIE);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $json = $this->json();
        $value = $this->body[$key] ?? $json[$key] ?? $this->query[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    /** Somente as chaves pedidas, já com trim. */
    public function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->input($key);
        }
        return $out;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->json() ?? [], $this->body);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        $value = $this->query[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    public function queryAll(): array
    {
        return $this->query;
    }

    public function has(string $key): bool
    {
        return $this->input($key) !== null && $this->input($key) !== '';
    }

    public function bool(string $key): bool
    {
        return in_array($this->input($key), ['1', 1, true, 'true', 'on', 'yes'], true);
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->input($key);
        return is_numeric($v) ? (int) $v : $default;
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $file;
    }

    /** Normaliza <input type=file multiple> em uma lista de arquivos. */
    public function files(string $key): array
    {
        $raw = $this->files[$key] ?? null;
        if (!is_array($raw)) {
            return [];
        }
        if (!is_array($raw['name'])) {
            return ($raw['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$raw];
        }
        $list = [];
        foreach ($raw['name'] as $i => $name) {
            if (($raw['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $list[] = [
                'name' => $name,
                'type' => $raw['type'][$i],
                'tmp_name' => $raw['tmp_name'][$i],
                'error' => $raw['error'][$i],
                'size' => $raw['size'][$i],
            ];
        }
        return $list;
    }

    public function json(): ?array
    {
        if ($this->json === null && str_contains($this->header('Content-Type') ?? '', 'application/json')) {
            $decoded = json_decode((string) file_get_contents('php://input'), true);
            $this->json = is_array($decoded) ? $decoded : [];
        }
        return $this->json;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if ($name === 'Content-Type') {
            return $this->server['CONTENT_TYPE'] ?? null;
        }
        return $this->server[$key] ?? null;
    }

    public function cookie(string $name): ?string
    {
        $v = $this->cookies[$name] ?? null;
        return is_string($v) ? $v : null;
    }

    public function wantsJson(): bool
    {
        return str_contains($this->header('Accept') ?? '', 'application/json')
            || ($this->header('X-Requested-With') ?? '') === 'XMLHttpRequest'
            || str_starts_with($this->path, '/api/');
    }

    public function ip(): string
    {
        // Sem confiar em X-Forwarded-For: pode ser forjado pelo cliente.
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function isSecure(): bool
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || (int) ($this->server['SERVER_PORT'] ?? 0) === 443;
    }

    public function fullUrl(): string
    {
        $qs = $this->query ? '?' . http_build_query($this->query) : '';
        return $this->path . $qs;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function param(string $key): ?string
    {
        return $this->params[$key] ?? null;
    }

    public function params(): array
    {
        return $this->params;
    }

    public function contentLength(): int
    {
        return (int) ($this->server['CONTENT_LENGTH'] ?? 0);
    }
}
