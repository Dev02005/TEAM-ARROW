<?php
/**
 * Database Configuration
 * Medication Safety Second Pair of Eyes
 */

declare(strict_types=1);

defined('APP_INIT') or require_once __DIR__ . '/config.php';

// Database settings come from environment variables or .env (XAMPP defaults otherwise).
define('DB_HOST', (string)env('DB_HOST', 'localhost'));
define('DB_PORT', (string)env('DB_PORT', '3306'));
define('DB_NAME', (string)env('DB_NAME', 'medication_safety'));
define('DB_USER', (string)env('DB_USER', 'root'));
define('DB_PASS', (string)env('DB_PASS', ''));
define('DB_CHARSET', (string)env('DB_CHARSET', 'utf8mb4'));
define('DB_TIMEOUT_SECONDS', (int)env('DB_TIMEOUT_SECONDS', '5'));
