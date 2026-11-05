-- =====================================================================
-- DoseWise - demo logins for the one-click buttons on login.php
--
--   eleanor@example.com  / citizen123   weak kidneys (eGFR 28)     -> 3 warnings
--   robert@example.com   / citizen123   blood thinner (warfarin)   -> 1 warning
--   grace@example.com    / citizen123   simple list                -> no warnings
--   admin@dosewise.com   / admin123     clinical staff (all patients)
--
-- Run AFTER  php database/import_h4_data.php  (it creates the database).
-- Safe to run again: it removes and re-creates only these demo rows.
--   phpMyAdmin: select database medication_safety -> Import -> this file
--   or:  C:\xampp\mysql\bin\mysql -u root medication_safety < database\seed_demo_logins.sql
-- =====================================================================

USE `medication_safety`;

-- Login table (same definition as database/upgrade_citizen.php)
CREATE TABLE IF NOT EXISTS `users` (
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

START TRANSACTION;

-- Clean out earlier copies of these demo rows (medicines go with the patient: ON DELETE CASCADE)
DELETE FROM `users` WHERE `email` IN ('eleanor@example.com', 'robert@example.com', 'grace@example.com');
DELETE FROM `patients` WHERE `patient_id` IN ('DEMO-ELEANOR', 'DEMO-ROBERT', 'DEMO-GRACE');

-- ---------------------------------------------------------------------
-- 1. Eleanor Vance: weak kidneys (eGFR 28), diabetes + BP, penicillin allergy
-- ---------------------------------------------------------------------
INSERT INTO `patients`
    (`patient_id`, `patient_mrn`, `full_name`, `cohort`, `age`, `sex`, `gender`, `weight_kg`,
     `egfr`, `egfr_ml_min_1_73m2`, `pregnant`, `diagnoses`, `conditions`, `allergies`,
     `medication_list`, `prescriber_count`, `is_near_miss_case`)
VALUES
    ('DEMO-ELEANOR', 'DEMO-ELEANOR', 'Eleanor Vance', 'train', 78, 'F', 'female', 58.00,
     28.00, 28, 'NA', 'Type 2 diabetes; Hypertension', 'Type 2 diabetes; Hypertension', 'Penicillin',
     'Glycomet 500; Brufen 400; Telma 40', 2, 1);
SET @eleanor = LAST_INSERT_ID();

INSERT INTO `patient_medications`
    (`patient_id`, `raw_medicine_name`, `prescribed_generic_name`, `dosage`, `dose_amount_mg`,
     `frequency`, `route`, `prescribing_doctor`, `prescription_date`, `start_date`, `status`, `indication`)
VALUES
    (@eleanor, 'Glycomet 500', 'metformin',   '500 mg', 500, 'Twice daily after food', 'oral', 'Dr. Rao (Diabetes clinic)', '2025-01-10', '2025-01-10', 'active', 'Sugar'),
    (@eleanor, 'Brufen 400',   'ibuprofen',   '400 mg', 400, 'When needed for pain',   'oral', 'Dr. Reddy (Orthopaedics)',  '2025-06-02', '2025-06-02', 'active', 'Knee pain'),
    (@eleanor, 'Telma 40',     'telmisartan', '40 mg',   40, 'Once daily morning',     'oral', 'Dr. Rao (Diabetes clinic)', '2025-01-10', '2025-01-10', 'active', 'BP');

-- ---------------------------------------------------------------------
-- 2. Robert Chen: takes warfarin (blood thinner) + aspirin from two doctors
-- ---------------------------------------------------------------------
INSERT INTO `patients`
    (`patient_id`, `patient_mrn`, `full_name`, `cohort`, `age`, `sex`, `gender`, `weight_kg`,
     `egfr`, `egfr_ml_min_1_73m2`, `pregnant`, `diagnoses`, `conditions`, `allergies`,
     `medication_list`, `prescriber_count`, `is_near_miss_case`)
VALUES
    ('DEMO-ROBERT', 'DEMO-ROBERT', 'Robert Chen', 'train', 71, 'M', 'male', 72.00,
     72.00, 72, 'NA', 'Atrial fibrillation', 'Atrial fibrillation', NULL,
     'Warf 5; Ecosprin 75; Atorva 10', 2, 1);
SET @robert = LAST_INSERT_ID();

INSERT INTO `patient_medications`
    (`patient_id`, `raw_medicine_name`, `prescribed_generic_name`, `dosage`, `dose_amount_mg`,
     `frequency`, `route`, `prescribing_doctor`, `prescription_date`, `start_date`, `status`, `indication`)
VALUES
    (@robert, 'Warf 5',      'warfarin',     '5 mg',  5,  'Once daily at night', 'oral', 'Dr. Iyer (Cardiology)', '2024-11-20', '2024-11-20', 'active', 'Irregular heartbeat'),
    (@robert, 'Ecosprin 75', 'aspirin',      '75 mg', 75, 'Once daily',          'oral', 'Dr. Khan (Family doctor)', '2025-04-15', '2025-04-15', 'active', 'Heart'),
    (@robert, 'Atorva 10',   'atorvastatin', '10 mg', 10, 'Once daily at night', 'oral', 'Dr. Iyer (Cardiology)', '2024-11-20', '2024-11-20', 'active', 'Cholesterol');

-- ---------------------------------------------------------------------
-- 3. Grace Hopper: simple list, no warnings expected
-- ---------------------------------------------------------------------
INSERT INTO `patients`
    (`patient_id`, `patient_mrn`, `full_name`, `cohort`, `age`, `sex`, `gender`, `weight_kg`,
     `egfr`, `egfr_ml_min_1_73m2`, `pregnant`, `diagnoses`, `conditions`, `allergies`,
     `medication_list`, `prescriber_count`, `is_near_miss_case`)
VALUES
    ('DEMO-GRACE', 'DEMO-GRACE', 'Grace Hopper', 'train', 66, 'F', 'female', 61.00,
     95.00, 95, 'NA', 'Hypertension', 'Hypertension', NULL,
     'Telma 40; Atorva 10', 1, 0);
SET @grace = LAST_INSERT_ID();

INSERT INTO `patient_medications`
    (`patient_id`, `raw_medicine_name`, `prescribed_generic_name`, `dosage`, `dose_amount_mg`,
     `frequency`, `route`, `prescribing_doctor`, `prescription_date`, `start_date`, `status`, `indication`)
VALUES
    (@grace, 'Telma 40',  'telmisartan',  '40 mg', 40, 'Once daily morning',  'oral', 'Dr. Khan (Family doctor)', '2025-02-01', '2025-02-01', 'active', 'BP'),
    (@grace, 'Atorva 10', 'atorvastatin', '10 mg', 10, 'Once daily at night', 'oral', 'Dr. Khan (Family doctor)', '2025-02-01', '2025-02-01', 'active', 'Cholesterol');

-- ---------------------------------------------------------------------
-- 4. Logins (bcrypt hashes made with PHP password_hash)
--    citizen123 -> $2y$10$TEpFkAtL78f7...   admin123 -> $2y$10$pBjmxSeUx/Ta...
-- ---------------------------------------------------------------------
INSERT INTO `users` (`patient_id`, `email`, `password_hash`, `full_name`, `role`) VALUES
    (@eleanor, 'eleanor@example.com', '$2y$10$TEpFkAtL78f7UUCZDSbhv./Xk0P/3snxIKpdwunPHGTSgw51bJn46', 'Eleanor Vance', 'citizen'),
    (@robert,  'robert@example.com',  '$2y$10$TEpFkAtL78f7UUCZDSbhv./Xk0P/3snxIKpdwunPHGTSgw51bJn46', 'Robert Chen',   'citizen'),
    (@grace,   'grace@example.com',   '$2y$10$TEpFkAtL78f7UUCZDSbhv./Xk0P/3snxIKpdwunPHGTSgw51bJn46', 'Grace Hopper',  'citizen');

-- Staff login: created by the importer already; this makes sure it exists with the name shown on the login page
INSERT INTO `users` (`patient_id`, `email`, `password_hash`, `full_name`, `role`) VALUES
    (NULL, 'admin@dosewise.com', '$2y$10$pBjmxSeUx/TAxk3C01X6gOO.Fy7FJ598IY2ZLYIOVb.NsAMZEevMu', 'Dr. Sarah Jenkins', 'admin')
ON DUPLICATE KEY UPDATE
    `password_hash` = VALUES(`password_hash`), `full_name` = VALUES(`full_name`), `role` = 'admin', `patient_id` = NULL;

COMMIT;

-- Check
SELECT u.email, u.role, p.full_name, p.egfr,
       (SELECT COUNT(*) FROM patient_medications pm WHERE pm.patient_id = p.id AND pm.status = 'active') AS medicines
FROM users u LEFT JOIN patients p ON p.id = u.patient_id
WHERE u.email IN ('eleanor@example.com', 'robert@example.com', 'grace@example.com', 'admin@dosewise.com');
