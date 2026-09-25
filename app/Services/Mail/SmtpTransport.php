<?php
declare(strict_types=1);

namespace App\Services\Mail;

use RuntimeException;

/**
 * Cliente SMTP mínimo: STARTTLS (587) ou SSL implícito (465), AUTH LOGIN,
 * mensagem multipart texto + HTML em UTF-8.
 */
final class SmtpTransport
{
    /** @var resource|null */
    private $socket = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $encryption,
        private readonly string $username,
        private readonly string $password,
        private readonly int $timeout = 15,
    ) {
        if ($host === '') {
            throw new RuntimeException('MAIL_HOST não configurado.');
        }
    }

    public function send(string $fromEmail, string $fromName, string $to, string $subject, string $html, string $text): void
    {
        $remote = ($this->encryption === 'ssl' ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port;
        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $socket = @stream_socket_client($remote, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$socket) {
            throw new RuntimeException("Não foi possível conectar ao SMTP ($errno $errstr).");
        }
        $this->socket = $socket;
        stream_set_timeout($socket, $this->timeout);

        try {
            $this->expect(220);
            $hostname = gethostname() ?: 'localhost';
            $this->command("EHLO $hostname", 250);
            if ($this->encryption === 'tls') {
                $this->command('STARTTLS', 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                    throw new RuntimeException('Falha ao iniciar TLS com o servidor SMTP.');
                }
                $this->command("EHLO $hostname", 250);
            }
            if ($this->username !== '') {
                $this->command('AUTH LOGIN', 334);
                $this->command(base64_encode($this->username), 334);
                $this->command(base64_encode($this->password), 235);
            }
            $this->command('MAIL FROM:<' . $this->clean($fromEmail) . '>', 250);
            $this->command('RCPT TO:<' . $this->clean($to) . '>', [250, 251]);
            $this->command('DATA', 354);
            $this->write($this->buildMessage($fromEmail, $fromName, $to, $subject, $html, $text) . "\r\n.");
            $this->expect(250);
            $this->command('QUIT', 221);
        } finally {
            fclose($socket);
            $this->socket = null;
        }
    }

    private function buildMessage(string $fromEmail, string $fromName, string $to, string $subject, string $html, string $text): string
    {
        $boundary = 'rc_' . bin2hex(random_bytes(12));
        $domain = substr(strrchr($fromEmail, '@') ?: '@localhost', 1);
        $headers = [
            'Date: ' . date('r'),
            'From: ' . $this->encodeHeader($fromName) . ' <' . $this->clean($fromEmail) . '>',
            'To: <' . $this->clean($to) . '>',
            'Subject: ' . $this->encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        $body = "--$boundary\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text))
            . "--$boundary\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--$boundary--";
        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    /** Impede injeção de cabeçalhos por quebras de linha. */
    private function clean(string $value): string
    {
        return str_replace(["\r", "\n", '<', '>'], '', $value);
    }

    private function command(string $line, int|array $expected): string
    {
        $this->write($line);
        return $this->expect($expected);
    }

    private function write(string $data): void
    {
        fwrite($this->socket, $data . "\r\n");
    }

    private function expect(int|array $codes): string
    {
        $codes = (array) $codes;
        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException('SMTP respondeu ' . trim($response));
        }
        return $response;
    }
}
