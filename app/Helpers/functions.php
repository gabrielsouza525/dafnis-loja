<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Env;
use App\Core\Session;
use App\Core\View;
use App\Services\Auth;
use App\Services\Settings;

function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}

/** Prefixo quando a loja roda em subpasta (ex.: https://site.com/loja). */
function base_path_prefix(): string
{
    static $prefix = null;
    if ($prefix === null) {
        $path = (string) parse_url((string) env('APP_URL', ''), PHP_URL_PATH);
        $prefix = rtrim($path, '/');
    }
    return $prefix;
}

function url(string $path = '/', array $query = []): string
{
    $fragment = '';
    if (($hash = strpos($path, '#')) !== false) {
        $fragment = substr($path, $hash);
        $path = substr($path, 0, $hash);
    }
    $path = '/' . ltrim($path, '/');
    $query = array_filter($query, static fn ($v) => $v !== null && $v !== '' && $v !== []);
    $qs = $query ? '?' . http_build_query($query) : '';
    return base_path_prefix() . ($path === '/' && base_path_prefix() !== '' ? '/' : $path) . $qs . $fragment;
}

function absolute_url(string $path = '/'): string
{
    $root = rtrim((string) env('APP_URL', ''), '/');
    $root = preg_replace('#(https?://[^/]+).*#', '$1', $root);
    return $root . url($path);
}

/** URL de asset com versão pelo mtime, para cache longo sem servir arquivo velho. */
function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : 0;
    return url('/assets/' . ltrim($path, '/')) . ($v ? '?v=' . $v : '');
}

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function partial(string $name, array $data = []): string
{
    return View::file('partials/' . $name, $data);
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function old(string $key, mixed $default = ''): mixed
{
    $old = Session::getFlash('_old', []);
    return $old[$key] ?? $default;
}

function has_old(): bool
{
    return Session::getFlash('_old') !== null;
}

function field_error(string $key): ?string
{
    $errors = Session::getFlash('_errors', []);
    return $errors[$key] ?? null;
}

function flash(string $type, string $message): void
{
    Session::flash('toast', ['type' => $type, 'message' => $message]);
}

function auth_user(): ?array
{
    return Auth::user();
}

/**
 * Prévia estática (GitHub Pages): STATIC_DEMO=true no ambiente do servidor que gera
 * as páginas. Filtros rodam no navegador e ações que precisam do servidor viram
 * navegação entre telas de exemplo. Na loja publicada fica sempre desligado.
 */
function static_demo(): bool
{
    return (bool) env('STATIC_DEMO', false);
}

function setting(string $key, mixed $default = null): mixed
{
    return Settings::get($key, $default);
}

/**
 * Ícones de traço do protótipo (viewBox 24×24). O desenho fica no HTML para não
 * depender de requisição extra; cada ícone tem poucos bytes.
 */
const ICONS = [
    'search' => 'M18 11a7 7 0 1 1-14 0a7 7 0 1 1 14 0z M21 21l-4.6-4.6',
    'cart' => 'M3 4h2.2l2.3 10.6a1.2 1.2 0 0 0 1.2.9h8.9a1.2 1.2 0 0 0 1.2-.9L20.6 8H6.1 M10 20a1 1 0 1 1-2 0a1 1 0 1 1 2 0z M18 20a1 1 0 1 1-2 0a1 1 0 1 1 2 0z',
    'user' => 'M16 8a4 4 0 1 1-8 0a4 4 0 1 1 8 0z M4 20.5a8 8 0 0 1 16 0',
    'menu' => 'M4 7h16 M4 12h16 M4 17h16',
    'close' => 'M6 6l12 12 M18 6L6 18',
    'chevR' => 'M9 6l6 6-6 6',
    'chevL' => 'M15 6l-6 6 6 6',
    'chevD' => 'M6 9l6 6 6-6',
    'arrowR' => 'M5 12h14 M13 6l6 6-6 6',
    'arrowL' => 'M19 12H5 M11 6l-6 6 6 6',
    'clock' => 'M21 12a9 9 0 1 1-18 0a9 9 0 1 1 18 0z M12 7v5l3 2',
    'monitor' => 'M3 5h18v11H3z M8 20h8 M12 16v4',
    'award' => 'M17 9a5 5 0 1 1-10 0a5 5 0 1 1 10 0z M9 13.4L8 21l4-2 4 2-1-7.6',
    'check' => 'M5 12.5l4.5 4.5L19 7.5',
    'filter' => 'M4 5h16l-6 8v5l-4 2v-7z',
    'trash' => 'M4 7h16 M9 7V4h6v3 M6 7l1 13h10l1-13 M10 11v6 M14 11v6',
    'plus' => 'M12 5v14 M5 12h14',
    'minus' => 'M5 12h14',
    'info' => 'M21 12a9 9 0 1 1-18 0a9 9 0 1 1 18 0z M12 11v5 M12 7.5v.5',
    'alert' => 'M12 3l10 17H2z M12 10v4 M12 17v.5',
    'lock' => 'M5 11h14v10H5z M8 11V7a4 4 0 0 1 8 0v4',
    'building' => 'M4 21V5l8-2v18 M12 8h8v13 M2 21h20 M7 8h2 M7 12h2 M7 16h2 M15 12h2 M15 16h2',
    'users' => 'M12.5 7.5a3.5 3.5 0 1 1-7 0a3.5 3.5 0 1 1 7 0z M2.5 20a6.5 6.5 0 0 1 13 0 M16 4.3a3.5 3.5 0 0 1 0 6.4 M18 14.3a6.5 6.5 0 0 1 3.5 5.7',
    'briefcase' => 'M3 8h18v12H3z M8 8V5h8v3 M3 13h18',
    'clipboard' => 'M9 3h6v3H9z M7 4.5H5V21h14V4.5h-2 M8 11h8 M8 15h5',
    'clipcheck' => 'M9 3h6v3H9z M7 4.5H5V21h14V4.5h-2 M8.5 13.5l2.5 2.5 4.5-5',
    'hardhat' => 'M3 18h18 M5 18v-2.5a7 7 0 0 1 14 0V18 M10 8.7V6h4v2.7 M9.5 18v-4 M14.5 18v-4',
    'aid' => 'M9 3h6v6h6v6h-6v6H9v-6H3V9h6z',
    'flame' => 'M12 3c1 3.5 5 5.5 5 10a5 5 0 0 1-10 0c0-2.6 1.4-3.8 2-5.2 1 1.4 1.6 2 2.6 2.2C11.4 7.6 11 5.5 12 3z',
    'cog' => 'M15 12a3 3 0 1 1-6 0a3 3 0 1 1 6 0z M12 2.5v3 M12 18.5v3 M4.6 4.6l2.1 2.1 M17.3 17.3l2.1 2.1 M2.5 12h3 M18.5 12h3 M4.6 19.4l2.1-2.1 M17.3 6.7l2.1-2.1',
    'pulse' => 'M3 12h4l2-4.5 3.5 9 2.5-4.5h6',
    'leaf' => 'M5 19c0-8 5-14 15-14 0 10-6 15-14 15 M5 19l7.5-7.5',
    'bolt' => 'M13 2L4 14h7l-1 8 9-12h-7z',
    'box' => 'M4 8l8-4 8 4v8l-8 4-8-4z M4 8l8 4 8-4 M12 12v8',
    'forklift' => 'M3 18a2 2 0 1 0 4 0a2 2 0 1 0-4 0z M11 18a2 2 0 1 0 4 0a2 2 0 1 0-4 0z M3 16V9h6l3 5v4 M9 9V5.5 M17 3v15h4',
    'drop' => 'M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z',
    'gauge' => 'M4 17a8 8 0 1 1 16 0 M12 17l3.5-4.5 M4 17h16',
    'chair' => 'M7 3v10h10 M7 13l-1 8 M17 13l1 8 M7 17h10',
    'crane' => 'M5 21V4h2v17 M7 5h13 M18 5v5 M16 10h4v3h-4z M2 21h9',
    'sign' => 'M12 3l10 17H2z M12 10v4 M12 17v.5',
    'hospital' => 'M4 4h16v16H4z M12 8v8 M8 12h8',
    'spark' => 'M12 2.5v5 M12 16.5v5 M2.5 12h5 M16.5 12h5 M5.3 5.3l3.5 3.5 M15.2 15.2l3.5 3.5 M5.3 18.7l3.5-3.5 M15.2 8.8l3.5-3.5',
    'car' => 'M3 16v-4l2.2-5h13.6l2.2 5v4z M3 16v3h3v-3 M18 16v3h3v-3 M3 12h18',
    'game' => 'M7 8h10a4.5 4.5 0 0 1 0 9h-1.5L13.5 15h-3L8.5 17H7a4.5 4.5 0 0 1 0-9z M8.5 10.5v3 M7 12h3 M15.5 11v.5 M17.5 13v.5',
    'pix' => 'M12 3l9 9-9 9-9-9z M8 12h8',
    'card' => 'M3 6h18v12H3z M3 10h18 M7 15h3',
    'barcode' => 'M4 6v12 M7 6v12 M10 6v12 M14 6v12 M17 6v12 M20 6v12',
    'shield' => 'M12 3l8 3v6c0 4.5-3.4 8-8 9-4.6-1-8-4.5-8-9V6z M9 12l2 2 4-4',
    'eye' => 'M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z M15 12a3 3 0 1 1-6 0a3 3 0 1 1 6 0z',
    'logout' => 'M15 4h4v16h-4 M10 8l-4 4 4 4 M6 12h10',
    'login' => 'M15 4h4v16h-4 M11 8l4 4-4 4 M4 12h11',
    'home' => 'M3 11l9-7 9 7 M5 9.5V20h14V9.5 M10 20v-6h4v6',
    'grid' => 'M4 4h7v7H4z M13 4h7v7h-7z M4 13h7v7H4z M13 13h7v7h-7z',
    'book' => 'M4 5a2 2 0 0 1 2-2h14v16H6a2 2 0 0 0-2 2z M4 21V5 M8 7h8',
    'receipt' => 'M6 3h12v18l-3-2-3 2-3-2-3 2z M9 8h6 M9 12h6 M9 16h3',
    'file' => 'M6 3h8l4 4v14H6z M14 3v4h4 M9 13h6 M9 17h6',
    'download' => 'M12 4v11 M7 10l5 5 5-5 M4 20h16',
    'upload' => 'M12 20V9 M7 14l5-5 5 5 M4 4h16',
    'external' => 'M14 4h6v6 M20 4l-9 9 M18 14v6H4V6h6',
    'mail' => 'M3 6h18v12H3z M3 7l9 6 9-6',
    'phone' => 'M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z',
    'whatsapp' => 'M4 20l1.3-4A8 8 0 1 1 8 18.8z M9 9.5c0 3 2.5 5.5 5.5 5.5l1-1.5-2-1-1 1a4 4 0 0 1-2-2l1-1-1-2z',
    'pin' => 'M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z M14.5 10a2.5 2.5 0 1 1-5 0a2.5 2.5 0 1 1 5 0z',
    'edit' => 'M4 20h4L19 9l-4-4L4 16z M13.5 6.5l4 4',
    'tag' => 'M3 12V4h8l10 10-8 8z M8 8.5v.5',
    'settings' => 'M4 7h10 M18 7h2 M4 17h2 M10 17h10 M16 7a2 2 0 1 1-4 0a2 2 0 1 1 4 0z M10 17a2 2 0 1 1-4 0a2 2 0 1 1 4 0z',
    'chart' => 'M4 20V10 M10 20V4 M16 20v-7 M3 20h18',
    'message' => 'M4 5h16v11H9l-5 4z',
    'refresh' => 'M20 11a8 8 0 0 0-14.5-4.5L4 8 M4 4v4h4 M4 13a8 8 0 0 0 14.5 4.5L20 16 M20 20v-4h-4',
    'calendar' => 'M4 6h16v14H4z M4 10h16 M8 3v5 M16 3v5',
    'instagram' => 'M4 8a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v8a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4z M16 12a4 4 0 1 1-8 0a4 4 0 1 1 8 0z M17 7v.5',
    'linkedin' => 'M4 4h16v16H4z M8 10v6 M8 7.5v.5 M12 16v-6 M12 12.5a2.5 2.5 0 0 1 5 0V16',
    'youtube' => 'M3 8a3 3 0 0 1 3-3h12a3 3 0 0 1 3 3v8a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3z M10 9v6l5-3z',
];

function icon(string $name, string $class = ''): string
{
    $d = ICONS[$name] ?? ICONS['clipboard'];
    return '<svg class="ic' . ($class !== '' ? ' ' . e($class) : '') . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="' . $d . '"></path></svg>';
}

function money(mixed $value, bool $symbol = true): string
{
    $formatted = number_format((float) $value, 2, ',', '.');
    return $symbol ? 'R$ ' . $formatted : $formatted;
}

function number_br(mixed $value, int $decimals = 0): string
{
    return number_format((float) $value, $decimals, ',', '.');
}

function date_br(?string $value, bool $withTime = false): string
{
    if (!$value) {
        return '—';
    }
    $ts = strtotime($value);
    return $ts ? date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $ts) : '—';
}

const MONTHS = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

/** "25 de setembro de 2026" */
function date_long(?string $value): string
{
    if (!$value) {
        return '—';
    }
    $ts = strtotime($value);
    return (int) date('j', $ts) . ' de ' . MONTHS[(int) date('n', $ts) - 1] . ' de ' . date('Y', $ts);
}

/** "há 5 min", "ontem", "12/09/2026" */
function time_ago(?string $value): string
{
    if (!$value) {
        return '';
    }
    $diff = time() - strtotime($value);
    return match (true) {
        $diff < 60 => 'agora',
        $diff < 3600 => 'há ' . intdiv($diff, 60) . ' min',
        $diff < 86400 => 'há ' . intdiv($diff, 3600) . ' h',
        $diff < 172800 => 'ontem',
        $diff < 604800 => 'há ' . intdiv($diff, 86400) . ' dias',
        default => date_br($value),
    };
}

function phone_display(?string $digits): string
{
    $d = preg_replace('/\D/', '', (string) $digits);
    if (strlen($d) === 11) {
        return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7));
    }
    if (strlen($d) === 10) {
        return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6));
    }
    return (string) $digits;
}

function document_display(?string $digits): string
{
    $d = preg_replace('/\D/', '', (string) $digits);
    if (strlen($d) === 11) {
        return vsprintf('%s.%s.%s-%s', [substr($d, 0, 3), substr($d, 3, 3), substr($d, 6, 3), substr($d, 9)]);
    }
    if (strlen($d) === 14) {
        return vsprintf('%s.%s.%s/%s-%s', [substr($d, 0, 2), substr($d, 2, 3), substr($d, 5, 3), substr($d, 8, 4), substr($d, 12)]);
    }
    return (string) $digits;
}

/** CPF parcialmente oculto para telas de acompanhamento: ***.456.789-** */
function document_masked(?string $digits): string
{
    $d = preg_replace('/\D/', '', (string) $digits);
    if (strlen($d) === 11) {
        return '***.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-**';
    }
    return document_display($d);
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [''];
    $first = mb_substr($parts[0], 0, 1);
    $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
}

function first_name(string $name): string
{
    return explode(' ', trim($name))[0];
}

function str_limit(?string $text, int $limit = 80): string
{
    $text = (string) $text;
    return mb_strlen($text) > $limit ? rtrim(mb_substr($text, 0, $limit - 1)) . '…' : $text;
}

function json_attr(mixed $data): string
{
    return e(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP));
}

/** JSON seguro para <script type="application/json"> e JSON-LD. */
function json_script(mixed $data): string
{
    return (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

function request_path(): string
{
    return App\Core\App::request()?->path ?? '/';
}

function ini_bytes(string $value): int
{
    $value = trim($value);
    $unit = strtolower(substr($value, -1));
    $n = (int) $value;
    return match ($unit) {
        'g' => $n * 1024 ** 3,
        'm' => $n * 1024 ** 2,
        'k' => $n * 1024,
        default => $n,
    };
}

function random_token(int $bytes = 32): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

/** Link wa.me: aceita número só com dígitos (DDD + número) e acrescenta o 55. */
function wa_link(?string $phone, string $message = ''): ?string
{
    $d = preg_replace('/\D/', '', (string) $phone);
    if ($d === '') {
        return null;
    }
    if (strlen($d) <= 11) {
        $d = '55' . $d;
    }
    return 'https://wa.me/' . $d . ($message !== '' ? '?text=' . rawurlencode($message) : '');
}

function tel_link(?string $phone): ?string
{
    $d = preg_replace('/\D/', '', (string) $phone);
    return $d === '' ? null : 'tel:+55' . $d;
}

function pluralize(int $n, string $singular, string $plural): string
{
    return $n . ' ' . ($n === 1 ? $singular : $plural);
}

function selected(mixed $a, mixed $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked(mixed $value): string
{
    return $value ? ' checked' : '';
}

/** Minúsculas sem acentos, para busca e slugs. */
function normalize_text(?string $text): string
{
    $text = mb_strtolower((string) $text);
    $converted = class_exists(Normalizer::class)
        ? preg_replace('/\p{Mn}+/u', '', (string) Normalizer::normalize($text, Normalizer::FORM_D))
        : iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    return (string) $converted;
}

function slugify(string $text): string
{
    $slug = preg_replace('/[^a-z0-9]+/', '-', normalize_text($text)) ?? '';
    return trim((string) preg_replace('/-{2,}/', '-', $slug), '-');
}

/** "40 h", "1 h" */
function hours_short(int|string|null $hours): string
{
    return (int) $hours . ' h';
}

/** "40 horas", "1 hora" */
function hours_long(int|string|null $hours): string
{
    $h = (int) $hours;
    return $h . ($h === 1 ? ' hora' : ' horas');
}

const MODALITIES = ['online' => 'Online', 'semipresencial' => 'Semipresencial', 'presencial' => 'Presencial'];
const TRAINING_TYPES = ['inicial' => 'Formação inicial', 'periodico' => 'Periódico / Reciclagem'];

function modality_label(?string $modality): string
{
    return MODALITIES[$modality] ?? 'Online';
}

/** Marca o link do menu ativo: exato para "/", prefixo para o resto. */
function nav_active(string $path, bool $exact = false): string
{
    $current = request_path();
    $active = $exact ? $current === $path : ($current === $path || str_starts_with($current, rtrim($path, '/') . '/'));
    return $active ? ' on" aria-current="page' : '';
}
