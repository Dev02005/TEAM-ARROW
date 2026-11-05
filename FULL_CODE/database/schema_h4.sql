-- Medication Safety Second Pair of Eyes - H4 Official Schema
-- Supports 496 Drugs, 98 Combinations, 2,196 Interactions, 169 Renal Rules,
-- 74 Duplicate Therapy Groups, 152 Contraindications, 58 Conditions, and Chandrapuri Patient Cohorts

CREATE DATABASE IF NOT EXISTS `medication_safety`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `medication_safety`;

SET FOREIGN_KEY_CHECKS = 0;

-- 1. REFERENCE MEDICINES (from drug_reference.csv)
DROP TABLE IF EXISTS `medicines`;
CREATE TABLE `medicines` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `drug_id` VARCHAR(50) NOT NULL UNIQUE,
  `generic_name` VARCHAR(150) NOT NULL UNIQUE,
  `drug_class` VARCHAR(150) NOT NULL,
  `therapeutic_group` VARCHAR(150) NOT NULL,
  `therapeutic_class` VARCHAR(150) NOT NULL,
  `duplicate_group` VARCHAR(50) NULL,
  `common_brands` TEXT NULL,
  `brand_names` TEXT NULL,
  `dose_forms` VARCHAR(255) NOT NULL,
  `usual_adult_daily_dose_range` VARCHAR(255) NULL,
  `standard_dosage_range` VARCHAR(255) NULL,
  `max_daily_dose_mg` DECIMAL(10, 2) NULL,
  `renal_dose_adjustment_required` TINYINT(1) NOT NULL DEFAULT 0,
  `renal_adjustment_flag` CHAR(1) NOT NULL DEFAULT 'N',
  `renal_rule` TEXT NULL,
  `renal_adjustment_notes` TEXT NULL,
  `pregnancy_category` VARCHAR(5) NOT NULL DEFAULT 'N',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_med_generic` (`generic_name`),
  INDEX `idx_med_class` (`drug_class`),
  INDEX `idx_med_group` (`therapeutic_group`),
  INDEX `idx_med_dup_grp` (`duplicate_group`),
  INDEX `idx_med_renal` (`renal_dose_adjustment_required`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. COMBINATION BRANDS (from combination_brands.csv)
DROP TABLE IF EXISTS `combination_brands`;
CREATE TABLE `combination_brands` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `brand_name` VARCHAR(150) NOT NULL,
  `strength_label` VARCHAR(50) NULL,
  `dose_form` VARCHAR(50) NOT NULL,
  `components` TEXT NOT NULL,
  `generic_components` TEXT NOT NULL,
  INDEX `idx_combo_brand` (`brand_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. DRUG INTERACTIONS (from interactions.csv)
DROP TABLE IF EXISTS `drug_interactions`;
CREATE TABLE `drug_interactions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `drug_a` VARCHAR(150) NOT NULL,
  `drug_b` VARCHAR(150) NOT NULL,
  `severity` ENUM('contraindicated', 'major', 'moderate', 'minor') NOT NULL,
  `mechanism_effect` TEXT NOT NULL,
  `recommended_action` TEXT NOT NULL,
  UNIQUE KEY `uk_interaction_pair` (`drug_a`, `drug_b`),
  INDEX `idx_ddi_b` (`drug_b`),
  INDEX `idx_ddi_severity` (`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. RENAL RULES (from renal_rules.csv)
DROP TABLE IF EXISTS `renal_rules`;
CREATE TABLE `renal_rules` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `generic_name` VARCHAR(150) NOT NULL,
  `egfr_below` INT NOT NULL,
  `severity` ENUM('contraindicated', 'major', 'moderate', 'minor') NOT NULL,
  `recommended_action` TEXT NOT NULL,
  INDEX `idx_renal_name` (`generic_name`),
  INDEX `idx_renal_cutoff` (`egfr_below`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. DUPLICATE THERAPY GROUPS (from duplicate_therapy_groups.csv)
DROP TABLE IF EXISTS `duplicate_therapy_groups`;
CREATE TABLE `duplicate_therapy_groups` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `duplicate_group` VARCHAR(50) NOT NULL UNIQUE,
  `group_name` VARCHAR(150) NOT NULL,
  `severity` ENUM('contraindicated', 'major', 'moderate', 'minor') NOT NULL,
  `recommended_action` TEXT NOT NULL,
  `member_generics` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. DRUG DISEASE CONTRAINDICATIONS (from drug_disease_contraindications.csv)
DROP TABLE IF EXISTS `drug_disease_contraindications`;
CREATE TABLE `drug_disease_contraindications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `condition_id` VARCHAR(100) NOT NULL,
  `condition_name` VARCHAR(150) NOT NULL,
  `generic_name` VARCHAR(150) NOT NULL,
  `severity` ENUM('contraindicated', 'major', 'moderate', 'minor') NOT NULL,
  `reason` TEXT NOT NULL,
  `recommended_action` TEXT NOT NULL,
  INDEX `idx_ddc_generic` (`generic_name`),
  INDEX `idx_ddc_condition` (`condition_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. CONDITIONS REFERENCE (from conditions.csv)
DROP TABLE IF EXISTS `conditions`;
CREATE TABLE `conditions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `condition_id` VARCHAR(100) NOT NULL UNIQUE,
  `condition_name` VARCHAR(150) NOT NULL,
  `kind` VARCHAR(50) NOT NULL,
  `description` TEXT NOT NULL,
  `example_terms` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. PATIENTS (from patients_train.csv and patients_test.csv)
DROP TABLE IF EXISTS `patients`;
CREATE TABLE `patients` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `patient_id` VARCHAR(50) NOT NULL UNIQUE,
  `patient_mrn` VARCHAR(50) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `date_of_birth` DATE NULL,
  `cohort` ENUM('train', 'test') NOT NULL DEFAULT 'train',
  `age` INT NOT NULL DEFAULT 50,
  `sex` ENUM('M', 'F', 'NA') NOT NULL DEFAULT 'NA',
  `gender` ENUM('male', 'female', 'other') NOT NULL DEFAULT 'other',
  `weight_kg` DECIMAL(6, 2) NULL,
  `egfr` DECIMAL(6, 2) NULL,
  `egfr_ml_min_1_73m2` INT NULL,
  `serum_creatinine` DECIMAL(5, 2) NULL,
  `pregnant` ENUM('Y', 'N', 'NA') NOT NULL DEFAULT 'NA',
  `diagnoses` TEXT NULL,
  `conditions` TEXT NULL,
  `allergies` TEXT NULL,
  `medication_list` TEXT NULL,
  `prescriber_count` INT NOT NULL DEFAULT 1,
  `is_near_miss_case` TINYINT(1) NOT NULL DEFAULT 0,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_patient_id` (`patient_id`),
  INDEX `idx_patient_cohort` (`cohort`),
  INDEX `idx_patient_egfr` (`egfr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. PATIENT MEDICATIONS (Parsed entries for patient charts and active lockers)
DROP TABLE IF EXISTS `patient_medications`;
CREATE TABLE `patient_medications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `patient_id` INT UNSIGNED NOT NULL,
  `medicine_id` INT UNSIGNED NULL,
  `raw_medicine_name` VARCHAR(255) NOT NULL,
  `prescribed_generic_name` VARCHAR(255) NULL,
  `dosage` VARCHAR(100) NOT NULL DEFAULT '',
  `dose_amount_mg` DECIMAL(10, 2) NULL,
  `frequency` VARCHAR(100) NOT NULL DEFAULT '',
  `route` VARCHAR(50) NOT NULL DEFAULT 'oral',
  `prescribing_doctor` VARCHAR(150) NOT NULL DEFAULT 'Hospital Physician',
  `prescription_date` DATE NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NULL,
  `status` ENUM('active', 'discontinued', 'completed', 'unknown') NOT NULL DEFAULT 'active',
  `indication` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_pm_patient_status` (`patient_id`, `status`),
  INDEX `idx_pm_medicine` (`medicine_id`),
  CONSTRAINT `fk_pm_patient` FOREIGN KEY (`patient_id`)
    REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. MEDICINE ALIASES & BRAND LOOKUPS
DROP TABLE IF EXISTS `medicine_aliases`;
CREATE TABLE `medicine_aliases` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `medicine_id` INT UNSIGNED NOT NULL,
  `alias_name` VARCHAR(150) NOT NULL,
  `alias_type` ENUM('brand', 'synonym', 'misspelling', 'brand_combination') NOT NULL DEFAULT 'brand',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_alias_lookup` (`alias_name`),
  CONSTRAINT `fk_alias_medicine` FOREIGN KEY (`medicine_id`)
    REFERENCES `medicines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. USERS & CITIZEN AUTHENTICATION
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `patient_id` INT UNSIGNED NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `role` ENUM('citizen', 'admin') NOT NULL DEFAULT 'citizen',
  `phone` VARCHAR(50) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user_email` (`email`),
  CONSTRAINT `fk_user_patient` FOREIGN KEY (`patient_id`) 
    REFERENCES `patients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. SAFETY ALERTS AUDIT LOG
DROP TABLE IF EXISTS `safety_alerts`;
CREATE TABLE `safety_alerts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `patient_id` INT UNSIGNED NOT NULL,
  `issue_type` ENUM('interaction', 'duplicate_therapy', 'renal_dose', 'contraindication') NOT NULL,
  `severity` ENUM('contraindicated', 'major', 'moderate', 'minor') NOT NULL,
  `drugs_involved` VARCHAR(255) NOT NULL,
  `suggested_action` TEXT NOT NULL,
  `confidence` DECIMAL(4, 2) NOT NULL DEFAULT 1.00,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_alert_patient` (`patient_id`),
  INDEX `idx_alert_severity` (`severity`),
  INDEX `idx_alert_type` (`issue_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

