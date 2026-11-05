-- Migration 001: patient profile columns used by sign-up and the staff "add/edit patient" forms.
-- New installs already get these from schema_h4.sql. Run this once on a database that was
-- imported before they were added. Safe to run again (MariaDB / XAMPP: IF NOT EXISTS).
--   C:\xampp\mysql\bin\mysql.exe -u root medication_safety < database\migrations\001_patient_profile_columns.sql

USE `medication_safety`;

ALTER TABLE `patients`
    ADD COLUMN IF NOT EXISTS `date_of_birth` DATE NULL AFTER `full_name`,
    ADD COLUMN IF NOT EXISTS `serum_creatinine` DECIMAL(5, 2) NULL AFTER `egfr_ml_min_1_73m2`,
    ADD COLUMN IF NOT EXISTS `notes` TEXT NULL AFTER `is_near_miss_case`,
    MODIFY COLUMN `age` INT NOT NULL DEFAULT 50,
    MODIFY COLUMN `medication_list` TEXT NULL;
