<?php
declare(strict_types=1);

/** Chave da verificação em duas etapas da equipe de exemplo (database/seeders/DemoSeeder.php). */
const DEMO_ADMIN_TOTP = 'DAFNISDEMOADMIN2FAKEY234567DAFNI';

/** Cliente HTTP mínimo com cookies e CSRF para os testes. */
final class Client
{
    private array $cookies = [];
    public string $csrf = '';
    private string $lastPage = '';

    /** Subpasta da loja (ex.: "/dafnis-loja" no Apache do XAMPP); vazio quando roda na raiz. */
    private string $prefix;

    /** A exportação da prévia (bin/static-export.php) precisa das páginas como vieram, com o prefixo. */
    public static bool $keepPrefix = false;

    public function __construct(private string $base)
    {
        $this->prefix = rtrim((string) parse_url($base, PHP_URL_PATH), '/');
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
        if ($this->prefix !== '' && !self::$keepPrefix) {
            // Os testes procuram links como href="/cursos/..."; com a loja numa subpasta eles vêm
            // como href="/dafnis-loja/cursos/...". Tira o prefixo para os mesmos testes valerem nos dois casos.
            $body = str_replace(['="' . $this->prefix . '/', '="' . $this->prefix . '"'], ['="/', '="/"'], $body);
        }
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

    /** Com $totpSecret, passa também pela verificação em duas etapas (código gerado como o aplicativo faz). */
    public function login(string $email, string $password, ?string $totpSecret = null): bool
    {
        $this->request('GET', '/login');
        $r = $this->request('POST', '/login', ['email' => $email, 'password' => $password]);
        if ($totpSecret !== null && $r['status'] === 302 && str_ends_with($r['location'], '/login/verificacao')) {
            require_once dirname(__DIR__) . '/app/Services/Totp.php';
            $this->request('GET', '/login/verificacao');
            // O mesmo código não vale duas vezes: se outro teste acabou de usar o atual, vale o próximo;
            // se os dois já foram usados (testes seguidos no mesmo banco), espera o código seguinte
            foreach ([0, 1, 'esperar', 1] as $offset) {
                if ($offset === 'esperar') {
                    sleep(31 - time() % 30);
                    continue;
                }
                $code = App\Services\Totp::code($totpSecret, intdiv(time(), 30) + $offset);
                $r = $this->request('POST', '/login/verificacao', ['code' => $code]);
                if (!str_contains($r['location'], '/login')) {
                    break;
                }
            }
        }
        return $r['status'] === 302 && !str_contains($r['location'], '/login');
    }
}

