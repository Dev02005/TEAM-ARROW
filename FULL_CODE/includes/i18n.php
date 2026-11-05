<?php
/**
 * Multi-language support (English, Telugu, Hindi)
 * DoseWise - Medication Safety for Citizens
 *
 * - Language is chosen with ?lang=xx on any page and remembered (cookie for 1 year + session).
 * - First visit: falls back to the phone's language (Accept-Language), then English.
 * - Text lives in lang/<code>.php. Any key missing in Telugu/Hindi falls back to English.
 *
 * Usage in templates:
 *   <?= t('home.title') ?>                       // trusted translation text (may hold <strong>)
 *   <?= t('cabinet.welcome', ['name' => $n]) ?>  // {name} is HTML-escaped automatically
 *   <?= t_attr('login.email_ph') ?>              // safe inside HTML attributes / JS strings
 */

declare(strict_types=1);

const SUPPORTED_LANGS = [
    'en' => ['native' => 'English', 'speech' => 'en-IN', 'html' => 'en'],
    'te' => ['native' => 'తెలుగు',  'speech' => 'te-IN', 'html' => 'te'],
    'hi' => ['native' => 'हिन्दी',   'speech' => 'hi-IN', 'html' => 'hi'],
];

const LANG_COOKIE = 'dosewise_lang';

/** Resolve (once per request) and return the active language code. */
function current_lang(): string
{
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }

    $requested = $_GET['lang'] ?? null;
    if (is_string($requested) && isset(SUPPORTED_LANGS[$requested])) {
        $lang = $requested;
        if (!headers_sent()) {
            setcookie(LANG_COOKIE, $lang, [
                'expires'  => time() + 365 * 24 * 3600,
                'path'     => '/',
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE[LANG_COOKIE] = $lang;
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['lang'] = $lang;
        }
        return $lang;
    }

    foreach ([$_SESSION['lang'] ?? null, $_COOKIE[LANG_COOKIE] ?? null] as $cand) {
        if (is_string($cand) && isset(SUPPORTED_LANGS[$cand])) {
            return $lang = $cand;
        }
    }

    // Phone / browser language, e.g. "te-IN,te;q=0.9,en;q=0.8"
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    foreach (explode(',', $accept) as $part) {
        $code = substr(trim($part), 0, 2);
        if (isset(SUPPORTED_LANGS[$code])) {
            return $lang = $code;
        }
    }

    return $lang = 'en';
}

/** True once the person has picked a language themselves (used for the first-visit picker). */
function lang_chosen(): bool
{
    return isset($_GET['lang']) || isset($_COOKIE[LANG_COOKIE]) || isset($_SESSION['lang']);
}

function lang_table(string $code): array
{
    static $cache = [];
    if (!isset($cache[$code])) {
        $file = __DIR__ . '/../lang/' . $code . '.php';
        $cache[$code] = is_file($file) ? (array)require $file : [];
    }
    return $cache[$code];
}

/** Raw lookup with English fallback, no escaping. */
function t_raw(string $key, ?string $lang = null): string
{
    $lang ??= current_lang();
    $table = lang_table($lang);
    if (isset($table[$key])) {
        return (string)$table[$key];
    }
    $en = lang_table('en');
    return isset($en[$key]) ? (string)$en[$key] : $key;
}

/**
 * Translated text for HTML output. Translation strings are trusted (they may contain
 * simple tags like <strong>); every {placeholder} value is HTML-escaped.
 */
function t(string $key, array $vars = [], ?string $lang = null): string
{
    $text = t_raw($key, $lang);
    if ($vars) {
        $map = [];
        foreach ($vars as $k => $v) {
            $map['{' . $k . '}'] = htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        $text = strtr($text, $map);
    }
    return $text;
}

/** Plain text, safe inside HTML attributes (title=, placeholder=, data-confirm=). */
function t_attr(string $key, array $vars = [], ?string $lang = null): string
{
    $text = t_raw($key, $lang);
    if ($vars) {
        $text = strtr($text, array_combine(
            array_map(fn($k) => '{' . $k . '}', array_keys($vars)),
            array_map('strval', array_values($vars))
        ));
    }
    return htmlspecialchars(strip_tags($text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Plain text (no HTML), e.g. for flash messages, which are escaped on output. */
function t_plain(string $key, array $vars = [], ?string $lang = null): string
{
    $text = t_raw($key, $lang);
    if ($vars) {
        $text = strtr($text, array_combine(
            array_map(fn($k) => '{' . $k . '}', array_keys($vars)),
            array_map('strval', array_values($vars))
        ));
    }
    return strip_tags($text);
}

/**
 * Bilingual label for documents shown to doctors: "<patient language> / English".
 * In English it is just the English text.
 */
function t_bi(string $key, array $vars = []): string
{
    $local = t($key, $vars);
    if (current_lang() === 'en') {
        return $local;
    }
    $en = t($key, $vars, 'en');
    return $local === $en ? $local : $local . ' <span class="bi-en">/ ' . $en . '</span>';
}

/** URL of the current page with the language switched (other query parameters kept). */
function lang_url(string $code): string
{
    $path = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '';
    $query = $_GET;
    $query['lang'] = $code;
    return $path . '?' . http_build_query($query);
}

function html_lang(): string
{
    return SUPPORTED_LANGS[current_lang()]['html'];
}

function speech_lang(): string
{
    return SUPPORTED_LANGS[current_lang()]['speech'];
}

/** Translated month name for dates (1-12). */
function t_month(int $m): string
{
    $months = t_raw('date.months');
    $list = explode(',', $months);
    return trim($list[$m - 1] ?? (string)$m);
}
