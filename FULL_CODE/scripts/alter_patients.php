<?php
require_once __DIR__ . '/../config/database.php';
$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
try {
    $pdo->exec('ALTER TABLE patients ADD COLUMN date_of_birth DATE NULL AFTER full_name');
} catch (Exception $e) {}
try {
    $pdo->exec('ALTER TABLE patients ADD COLUMN serum_creatinine DECIMAL(5,2) NULL AFTER egfr');
} catch (Exception $e) {}
try {
    $pdo->exec('ALTER TABLE patients ADD COLUMN notes TEXT NULL AFTER is_near_miss_case');
} catch (Exception $e) {}
$pdo->exec('ALTER TABLE patients MODIFY COLUMN age INT NOT NULL DEFAULT 50');
$pdo->exec('ALTER TABLE patients MODIFY COLUMN medication_list TEXT NULL');

// Also ensure legacy table compatibility for tests: safety_rules, evaluation_cases, import_logs
$pdo->exec("
CREATE TABLE IF NOT EXISTS `safety_rules` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `rule_code` VARCHAR(50) NOT NULL UNIQUE,
  `rule_type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `severity` VARCHAR(50) NOT NULL,
  `supporting_evidence` TEXT NOT NULL,
  `default_suggested_action` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS `evaluation_cases` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `case_code` VARCHAR(50) NOT NULL UNIQUE,
  `patient_id` INT UNSIGNED NOT NULL,
  `expected_alert_type` VARCHAR(100) NOT NULL,
  `expected_severity` VARCHAR(50) NOT NULL,
  `expected_medicines` TEXT NOT NULL,
  `is_near_miss` TINYINT(1) NOT NULL DEFAULT 1,
  `ground_truth_description` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

$pdo->exec("
CREATE TABLE IF NOT EXISTS `import_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `file_name` VARCHAR(255) NOT NULL,
  `dataset_type` VARCHAR(50) NOT NULL,
  `records_imported` INT UNSIGNED NOT NULL DEFAULT 0,
  `records_skipped` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` VARCHAR(50) NOT NULL DEFAULT 'success',
  `error_details` TEXT NULL,
  `imported_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

echo "Table modifications complete!\n";

