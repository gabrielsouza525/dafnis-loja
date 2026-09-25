<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use App\Core\View;
use App\Services\Mail\SmtpTransport;
use Throwable;

/**
 * Envio de e-mails transacionais.
 * MAIL_DRIVER=log grava a mensagem em storage/mail (desenvolvimento);
 * MAIL_DRIVER=smtp envia de verdade pelo servidor configurado no .env.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $template, array $data = []): bool
    {
        $data['subject'] = $subject;
        try {
            $html = View::render('emails/' . $template, $data, 'emails/layout');
        } catch (Throwable $e) {
            Logger::exception($e);
            return false;
        }
        $text = self::toText($html);
        $driver = (string) env('MAIL_DRIVER', 'log');

        try {
            if ($driver === 'smtp') {
                (new SmtpTransport(
                    (string) env('MAIL_HOST'),
                    (int) env('MAIL_PORT', 587),
                    (string) env('MAIL_ENCRYPTION', 'tls'),
                    (string) env('MAIL_USERNAME'),
                    (string) env('MAIL_PASSWORD'),
                ))->send(
                    (string) env('MAIL_FROM_ADDRESS', env('MAIL_USERNAME')),
                    (string) env('MAIL_FROM_NAME', Settings::businessName()),
                    $to,
                    $subject,
                    $html,
                    $text
                );
            } else {
                self::writeToLog($to, $subject, $html, $text);
            }
            Logger::info('E-mail enviado', ['to' => self::mask($to), 'subject' => $subject, 'driver' => $driver]);
            return true;
        } catch (Throwable $e) {
            // Falha de e-mail não pode travar o fluxo principal: registra e segue.
            Logger::error('Falha ao enviar e-mail: ' . $e->getMessage(), ['to' => self::mask($to), 'subject' => $subject]);
            return false;
        }
    }

    public static function isRealDelivery(): bool
    {
        return env('MAIL_DRIVER', 'log') === 'smtp' && env('MAIL_HOST');
    }

    private static function writeToLog(string $to, string $subject, string $html, string $text): void
    {
        $dir = BASE_PATH . '/storage/mail';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $slug = substr(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $subject) ?: 'email')), 0, 40);
        $file = $dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '-' . trim($slug, '-') . '.html';
        $header = "<!--\nPara: $to\nAssunto: $subject\nData: " . date('d/m/Y H:i:s') . "\n\n$text\n-->\n";
        file_put_contents($file, $header . $html);
    }

    private static function toText(string $html): string
    {
        $html = preg_replace('#<(style|head)[^>]*>.*?</\1>#si', '', $html) ?? '';
        $html = preg_replace('#<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>#si', '$2 ($1)', $html) ?? '';
        $html = preg_replace('#<br\s*/?>|</p>|</tr>|</h\d>#i', "\n", $html) ?? '';
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? '';
        return trim((string) preg_replace("/\n\s*\n+/", "\n\n", $text));
    }

    private static function mask(string $email): string
    {
        [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');
        return mb_substr($user, 0, 2) . '***@' . $domain;
    }
}
