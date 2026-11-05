<?php
/**
 * Settings loader: environment variables first, then the project's .env file, then a default.
 * .env is never committed (see .gitignore); .env.example lists every setting with dummy values.
 */

declare(strict_types=1);

/** Read a setting, e.g. env('DB_HOST', 'localhost'). */
function env(string $key, ?string $default = null): ?string
{
    $fromServer = getenv($key);
    if ($fromServer !== false && $fromServer !== '') {
        return $fromServer;
    }
    $file = envFileValues();
    return array_key_exists($key, $file) ? $file[$key] : $default;
}

/** True for "1", "true", "yes", "on" (case-insensitive). */
function envBool(string $key, bool $default = false): bool
{
    $value = env($key);
    if ($value === null) {
        return $default;
    }
    return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
}

/** Parse KEY=VALUE lines from the project-root .env once per request. */
function envFileValues(): array
{
    static $values = null;
    if ($values !== null) {
        return $values;
    }
    $values = [];
    $path = __DIR__ . '/../.env';
    if (!is_readable($path)) {
        return $values;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $values[$key] = trim($value, "\"'");
    }
    return $values;
}
