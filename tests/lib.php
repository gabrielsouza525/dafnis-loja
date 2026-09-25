<?php
declare(strict_types=1);

/** Cliente HTTP mínimo com cookies e CSRF para os testes. */
final class Client
{
    private array $cookies = [];
    public string $csrf = '';
    private string $lastPage = '';

    public function __construct(private string $base)
    {
    }

    public function request(string $method, string $path, array $data = [], array $headers = []): array
    {
        $ch = curl_init($this->base . $path);
        $cookie = implode('; ', array_map(static fn ($k, $v) => "$k=$v", array_keys($this->cookies), $this->cookies));
        $headers[] = 'Cookie: ' . $cookie;
        if ($method === 'POST' && $this->lastPage !== '') {
            $headers[] = 'Referer: ' . $this->lastPage; // como um navegador faz
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);
        if ($method === 'POST') {
            $data['_token'] ??= $this->csrf;
            $hasFile = (bool) array_filter($data, static fn ($v) => $v instanceof CURLFile);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $hasFile ? $data : http_build_query($data));
        }
        $raw = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $head = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        foreach (preg_split('/\r\n/', $head) as $line) {
            if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $line, $m)) {
                $this->cookies[$m[1]] = $m[2];
            }
        }
        if (preg_match('/name="csrf-token" content="([^"]+)"/', $body, $m)) {
            $this->csrf = $m[1];
        }
        if ($method === 'GET' && $status === 200 && str_contains($head, 'text/html')) {
            $this->lastPage = $this->base . $path;
        }
        preg_match('/^Location:\s*(.+)$/mi', $head, $loc);
        return ['status' => $status, 'body' => $body, 'location' => trim($loc[1] ?? '')];
    }

    public function login(string $email, string $password): bool
    {
        $this->request('GET', '/login');
        $r = $this->request('POST', '/login', ['email' => $email, 'password' => $password]);
        return $r['status'] === 302 && !str_contains($r['location'], '/login');
    }
}

