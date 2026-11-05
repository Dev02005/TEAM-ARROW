<?php
/**
 * Application Configuration
 * Medication Safety Second Pair of Eyes
 */

declare(strict_types=1);

// Prevent direct access to includes
defined('APP_INIT') or define('APP_INIT', true);

// Application Metadata
define('APP_NAME', 'DoseWise');
define('APP_SHORT_NAME', 'DoseWise');
define('APP_TAGLINE', 'Medication Safety Second Pair of Eyes');
define('APP_VERSION', '1.0.0-hackathon');

// Mandatory Clinical Safety Disclaimer
define('CLINICAL_DISCLAIMER', 'This system supports clinical decision-making and does not replace a qualified clinician. All alerts and recommendations require professional review.');

// Settings from environment variables / .env (see .env.example)
require_once __DIR__ . '/env.php';

// URL path the app is served under, e.g. /medication/ for http://localhost/medication/
if (!defined('BASE_URL')) {
    define('BASE_URL', '/' . trim((string)env('APP_BASE_URL', '/medication/'), '/') . '/');
}

// Never show PHP errors (paths, SQL) to users unless APP_DEBUG=true; always log them.
define('APP_DEBUG', envBool('APP_DEBUG', false));
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// Default timezone
date_default_timezone_set('UTC');

// Multi-language support (English / Telugu / Hindi). Resolve the language now,
// before any page output, so a ?lang= choice can be saved in a cookie.
require_once __DIR__ . '/../includes/i18n.php';
current_lang();

// Last-resort handler: log the details, show a short message in the user's language.
if (PHP_SAPI !== 'cli') {
    set_exception_handler(function (Throwable $e): void {
        error_log('Unhandled ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }
        if (APP_DEBUG) {
            echo '<pre>' . htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8') . '</pre>';
            return;
        }
        echo '<!DOCTYPE html><html lang="' . html_lang() . '"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . APP_NAME . '</title></head>'
            . '<body style="font-family:system-ui,sans-serif;max-width:520px;margin:40px auto;padding:0 16px;font-size:18px;line-height:1.6;color:#0f172a">'
            . '<h1 style="font-size:24px">⚠️ ' . t('error.title') . '</h1><p>' . t('error.body') . '</p>'
            . '<p><a href="' . BASE_URL . 'index.php" style="font-weight:700">' . t('nav.home') . '</a></p></body></html>';
    });
}
