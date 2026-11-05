<?php
/**
 * Database Initialization Script (CLI & Web Compatible)
 * Medication Safety Second Pair of Eyes
 */

declare(strict_types=1);

// Setup scripts change the whole database: command line only, never from a browser.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This setup script can only be run from the command line, e.g. php database/' . basename(__FILE__));
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$isCli = (php_sapi_name() === 'cli');

function outputMsg(string $msg, string $type = 'info', bool $isCli = false): void
{
    if ($isCli) {
        $prefix = match ($type) {
            'success' => '[SUCCESS] ',
            'error'   => '[ERROR] ',
            'warning' => '[WARNING] ',
            default   => '[INFO] '
        };
        echo $prefix . $msg . PHP_EOL;
    } else {
        $color = match ($type) {
            'success' => '#10b981',
            'error'   => '#ef4444',
            'warning' => '#f59e0b',
            default   => '#3b82f6'
        };
        echo "<div style='font-family:sans-serif; margin:8px 0; padding:10px 14px; border-radius:6px; background:#f8fafc; border-left:4px solid {$color}; color:#1e293b;'>";
        echo "<strong>[" . strtoupper($type) . "]</strong> " . htmlspecialchars($msg);
        echo "</div>";
    }
}

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><title>Database Initialization</title><meta charset='utf-8'></head><body style='max-width:800px; margin:40px auto; font-family:sans-serif; background:#f1f5f9; padding:20px;'>";
    echo "<h2 style='color:#0f172a; border-bottom:2px solid #e2e8f0; padding-bottom:10px;'>Medication Safety CDS &mdash; Database Initializer</h2>";
}

try {
    // 1. Connect without dbname to create database if missing
    $pdoRoot = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET),
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    outputMsg("Connected to MySQL server at " . DB_HOST . ":" . DB_PORT, 'info', $isCli);

    // 2. Create Database
    $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` DEFAULT CHARACTER SET " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci");
    outputMsg("Database `" . DB_NAME . "` verified / created successfully.", 'success', $isCli);

    // 3. Connect to specific database
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET),
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 4. Run schema.sql
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new RuntimeException("Schema file not found at: " . $schemaFile);
    }
    $schemaSql = file_get_contents($schemaFile);
    $pdo->exec($schemaSql);
    outputMsg("Schema executed: tables (patients, medicines, medicine_aliases, drug_interactions, patient_medications, safety_rules, safety_alerts, evaluation_cases, import_logs) created.", 'success', $isCli);

    // 5. Run seed_phase1.sql
    $seedFile = __DIR__ . '/seed_phase1.sql';
    if (!file_exists($seedFile)) {
        throw new RuntimeException("Seed file not found at: " . $seedFile);
    }
    $seedSql = file_get_contents($seedFile);
    $pdo->exec($seedSql);
    outputMsg("Seed data loaded: reference medicines, aliases, interaction rules, CDS guidelines, and 6 synthetic multi-doctor patient cases.", 'success', $isCli);

    // 6. Verification counts
    $counts = [
        'Patients' => (int)$pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn(),
        'Reference Medicines' => (int)$pdo->query("SELECT COUNT(*) FROM medicines")->fetchColumn(),
        'Medicine Aliases' => (int)$pdo->query("SELECT COUNT(*) FROM medicine_aliases")->fetchColumn(),
        'Drug Interactions' => (int)$pdo->query("SELECT COUNT(*) FROM drug_interactions")->fetchColumn(),
        'Prescribed Medications' => (int)$pdo->query("SELECT COUNT(*) FROM patient_medications")->fetchColumn(),
        'Safety Rules' => (int)$pdo->query("SELECT COUNT(*) FROM safety_rules")->fetchColumn(),
        'Evaluation Cases' => (int)$pdo->query("SELECT COUNT(*) FROM evaluation_cases")->fetchColumn()
    ];

    outputMsg("--- Database Verification Summary ---", 'info', $isCli);
    foreach ($counts as $table => $count) {
        outputMsg("{$table}: {$count} records", 'info', $isCli);
    }

    outputMsg("Phase 1 Database Initialization Complete!", 'success', $isCli);

    if (!$isCli) {
        echo "<p style='margin-top:20px;'><a href='../index.php' style='display:inline-block; background:#2563eb; color:white; padding:10px 18px; border-radius:6px; text-decoration:none; font-weight:600;'>Go to Dashboard &rarr;</a></p>";
        echo "</body></html>";
    }

} catch (Exception $e) {
    outputMsg("Initialization failed: " . $e->getMessage(), 'error', $isCli);
    if (!$isCli) {
        echo "</body></html>";
    }
    exit(1);
}
