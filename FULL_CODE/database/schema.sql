-- Medication Safety Second Pair of Eyes
-- Database Schema for Clinical Decision Support System
-- Target: MySQL 8.0+ / MariaDB 10.4+ (XAMPP default)

CREATE DATABASE IF NOT EXISTS `medication_safety`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `medication_safety`;

-- 1. PATIENTS TABLE
-- Holds core patient demographic & clinical parameters (eGFR, Comorbidities, Allergies)
-- Supports ~5000 synthetic patients and 300 near-miss cases
CREATE TABLE IF NOT EXISTS `patients` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `patient_mrn` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Medical Record Number / Unique ID',
  `full_name` VARCHAR(150) NOT NULL,
  `date_of_birth` DATE NOT NULL,
  `gender` ENUM('male', 'female', 'other') NOT NULL DEFAULT 'other',
  `egfr` DECIMAL(5, 2) NULL COMMENT 'Estimated Glomerular Filtration Rate in mL/min/1.73m2',
  `serum_creatinine` DECIMAL(5, 2) NULL COMMENT 'Serum Creatinine in mg/dL',
  `conditions` TEXT NULL COMMENT 'JSON array or comma-separated chronic conditions (e.g. Hypertension, CKD, Diabetes)',
  `allergies` TEXT NULL COMMENT 'JSON array or comma-separated allergy list',
  `is_near_miss_case` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Flagged for near-miss benchmark dataset',
  `notes` TEXT NULL COMMENT 'Clinical notes or history highlights',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_patient_mrn` (`patient_mrn`),
  INDEX `idx_patient_egfr` (`egfr`),
  INDEX `idx_patient_near_miss` (`is_near_miss_case`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. MEDICINES REFERENCE TABLE
-- Holds ~500 medicine reference master records (RxNorm / Generic, ATC classes, renal limits)
CREATE TABLE IF NOT EXISTS `medicines` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `generic_name` VARCHAR(150) NOT NULL COMMENT 'Standard generic INN or RxNorm name (lowercase normalized)',
  `brand_names` TEXT NULL COMMENT 'Comma or JSON list of known brand names',
  `therapeutic_class` VARCHAR(150) NOT NULL COMMENT 'Drug class, e.g., NSAID, ACE Inhibitor, Statin',
  `atc_code` VARCHAR(20) NULL COMMENT 'Anatomical Therapeutic Chemical code',
  `standard_dosage_range` VARCHAR(255) NULL COMMENT 'e.g. 10mg - 40mg daily',
  `max_daily_dose_mg` DECIMAL(10, 2) NULL COMMENT 'Upper daily threshold for safety checks',
  `renal_dose_adjustment_required` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Requires adjustment in kidney impairment',
  `renal_threshold_egfr` DECIMAL(5, 2) NULL COMMENT 'Threshold eGFR below which dose must be reduced or avoided',
  `renal_adjustment_notes` TEXT NULL COMMENT 'Clinical guidance for renal dosing',
  `contraindicated_conditions` TEXT NULL COMMENT 'Comma or JSON list of conditions contraindicated with this drug',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_generic_name` (`generic_name`),
  INDEX `idx_med_class` (`therapeutic_class`),
  INDEX `idx_med_renal` (`renal_dose_adjustment_required`, `renal_threshold_egfr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. MEDICINE ALIASES / SYNONYMS & MISSPELLINGS
-- Supports brand-to-generic normalization and phonetic/Levenshtein matching
CREATE TABLE IF NOT EXISTS `medicine_aliases` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `medicine_id` INT UNSIGNED NOT NULL,
  `alias_name` VARCHAR(150) NOT NULL COMMENT 'Brand name, synonym, or common misspelling',
  `alias_type` ENUM('brand', 'synonym', 'misspelling', 'brand_combination') NOT NULL DEFAULT 'brand',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_alias_lookup` (`alias_name`),
  INDEX `idx_alias_type` (`alias_type`),
  CONSTRAINT `fk_alias_medicine` FOREIGN KEY (`medicine_id`)
    REFERENCES `medicines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. DRUG INTERACTIONS REFERENCE TABLE
-- Holds ~2000 drug interaction pairs with mechanisms, severity, and action guidance
CREATE TABLE IF NOT EXISTS `drug_interactions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `drug_a_id` INT UNSIGNED NOT NULL,
  `drug_b_id` INT UNSIGNED NOT NULL,
  `severity` ENUM('contraindicated', 'major', 'moderate', 'minor') NOT NULL,
  `mechanism` TEXT NOT NULL COMMENT 'Pharmacological mechanism of interaction',
  `clinical_evidence` TEXT NOT NULL COMMENT 'Citations, literature backing or summary evidence',
  `suggested_action` TEXT NOT NULL COMMENT 'Actionable recommendation for the clinician',
  `rule_code` VARCHAR(50) NOT NULL COMMENT 'Standard identifier for clinical audit',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_ddi_pair` (`drug_a_id`, `drug_b_id`),
  INDEX `idx_ddi_severity` (`severity`),
  INDEX `idx_ddi_rule` (`rule_code`),
  CONSTRAINT `fk_ddi_drug_a` FOREIGN KEY (`drug_a_id`)
    REFERENCES `medicines` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ddi_drug_b` FOREIGN KEY (`drug_b_id`)
    REFERENCES `medicines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. PATIENT MEDICATIONS (Active and Past History from Multiple Doctors)
-- Records multiple doctors prescribing over several years
CREATE TABLE IF NOT EXISTS `patient_medications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `patient_id` INT UNSIGNED NOT NULL,
  `medicine_id` INT UNSIGNED NULL COMMENT 'Matched reference medicine, or NULL if unresolved',
  `raw_medicine_name` VARCHAR(255) NOT NULL COMMENT 'Raw entry by prescriber (brand or typo)',
  `prescribed_generic_name` VARCHAR(150) NULL COMMENT 'Resolved generic name',
  `dosage` VARCHAR(100) NOT NULL COMMENT 'e.g. 20 mg',
  `dose_amount_mg` DECIMAL(10, 2) NULL COMMENT 'Calculated numeric dose for quantitative checks',
  `frequency` VARCHAR(100) NOT NULL COMMENT 'e.g. Once daily, BID, TID, PRN',
  `route` VARCHAR(50) NOT NULL DEFAULT 'oral',
  `prescribing_doctor` VARCHAR(150) NOT NULL COMMENT 'e.g. Dr. Jane Smith (Cardiology)',
  `prescription_date` DATE NOT NULL COMMENT 'Original date written',
  `start_date` DATE NOT NULL COMMENT 'Start of therapy',
  `end_date` DATE NULL COMMENT 'Discontinuation date or NULL if ongoing',
  `status` ENUM('active', 'discontinued', 'completed', 'unknown') NOT NULL DEFAULT 'active',
  `indication` VARCHAR(255) NULL COMMENT 'Reason for prescription, e.g., Atrial Fibrillation',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_pm_patient_status` (`patient_id`, `status`),
  INDEX `idx_pm_medicine` (`medicine_id`),
  INDEX `idx_pm_dates` (`start_date`, `end_date`),
  CONSTRAINT `fk_pm_patient` FOREIGN KEY (`patient_id`)
    REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pm_medicine` FOREIGN KEY (`medicine_id`)
    REFERENCES `medicines` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. SAFETY RULES MASTER
-- Knowledge base of explainable rules with severity, evidence, and default actions
CREATE TABLE IF NOT EXISTS `safety_rules` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `rule_code` VARCHAR(50) NOT NULL UNIQUE,
  `rule_type` ENUM('drug_interaction', 'duplicate_therapy', 'renal_impairment', 'contraindication', 'dosage_limit', 'name_mismatch') NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `severity` ENUM('critical', 'high', 'moderate', 'low') NOT NULL,
  `supporting_evidence` TEXT NOT NULL,
  `default_suggested_action` TEXT NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_rule_type` (`rule_type`),
  INDEX `idx_rule_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. GENERATED SAFETY ALERTS
-- Logs generated alerts for patient charts, auditing, and explainable decision records
CREATE TABLE IF NOT EXISTS `safety_alerts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `patient_id` INT UNSIGNED NOT NULL,
  `rule_code` VARCHAR(50) NULL,
  `alert_type` ENUM('drug_interaction', 'duplicate_therapy', 'renal_risk', 'contraindication', 'name_discrepancy', 'high_dose') NOT NULL,
  `severity` ENUM('critical', 'high', 'moderate', 'low') NOT NULL,
  `medicines_involved` TEXT NOT NULL COMMENT 'JSON array of medicine names or IDs',
  `title` VARCHAR(255) NOT NULL,
  `explanation` TEXT NOT NULL COMMENT 'Detailed explanation why this alert was triggered',
  `supporting_evidence` TEXT NOT NULL COMMENT 'Clinical citations and pharmacological rationale',
  `confidence` DECIMAL(4, 2) NOT NULL DEFAULT 1.00 COMMENT '0.00 to 1.00 confidence level',
  `limitations` TEXT NULL COMMENT 'Explicit statement of data limitations (e.g. missing recent labs)',
  `suggested_action` TEXT NOT NULL COMMENT 'Concrete suggested action for prescriber',
  `status` ENUM('unreviewed', 'acknowledged', 'overridden', 'resolved') NOT NULL DEFAULT 'unreviewed',
  `clinician_override_reason` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_alert_patient` (`patient_id`),
  INDEX `idx_alert_severity` (`severity`),
  INDEX `idx_alert_type` (`alert_type`),
  CONSTRAINT `fk_alert_patient` FOREIGN KEY (`patient_id`)
    REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. EVALUATION CASES & NEAR-MISS BENCHMARKING
-- Stores ground-truth evaluation labels for recall, false positive, and near-miss verification
CREATE TABLE IF NOT EXISTS `evaluation_cases` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `case_code` VARCHAR(50) NOT NULL UNIQUE,
  `patient_id` INT UNSIGNED NOT NULL,
  `expected_alert_type` VARCHAR(100) NOT NULL,
  `expected_severity` VARCHAR(50) NOT NULL,
  `expected_medicines` TEXT NOT NULL,
  `is_near_miss` TINYINT(1) NOT NULL DEFAULT 1,
  `ground_truth_description` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_eval_patient` (`patient_id`),
  INDEX `idx_eval_near_miss` (`is_near_miss`),
  CONSTRAINT `fk_eval_patient` FOREIGN KEY (`patient_id`)
    REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. DATASET IMPORT LOGS
-- For Phase 2 file import management and adaptation to challenge datasets
CREATE TABLE IF NOT EXISTS `import_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `file_name` VARCHAR(255) NOT NULL,
  `dataset_type` ENUM('medicines', 'interactions', 'patients', 'eval_labels') NOT NULL,
  `records_imported` INT UNSIGNED NOT NULL DEFAULT 0,
  `records_skipped` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('success', 'partial', 'failed') NOT NULL DEFAULT 'success',
  `error_details` TEXT NULL,
  `imported_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
