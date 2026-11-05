-- Medication Safety Second Pair of Eyes
-- Initial Clinical Reference & Synthetic Test Data (Phase 1)

USE `medication_safety`;

-- Clear existing data for clean re-seed
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `safety_alerts`;
TRUNCATE TABLE `patient_medications`;
TRUNCATE TABLE `drug_interactions`;
TRUNCATE TABLE `medicine_aliases`;
TRUNCATE TABLE `medicines`;
TRUNCATE TABLE `patients`;
TRUNCATE TABLE `safety_rules`;
TRUNCATE TABLE `evaluation_cases`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. REFERENCE MEDICINES
INSERT INTO `medicines` (`id`, `generic_name`, `brand_names`, `therapeutic_class`, `atc_code`, `standard_dosage_range`, `max_daily_dose_mg`, `renal_dose_adjustment_required`, `renal_threshold_egfr`, `renal_adjustment_notes`, `contraindicated_conditions`) VALUES
(1, 'metformin', 'Glucophage, Fortamet, Glumetza', 'Biguanide Antidiabetic', 'A10BA02', '500mg - 2000mg daily', 2550.00, 1, 45.00, 'Contraindicated if eGFR < 30 mL/min due to risk of lactic acidosis. Maximum 1000mg/day if eGFR 30-44 mL/min.', 'Severe Renal Impairment, Metabolic Acidosis, Acute Heart Failure'),
(2, 'lisinopril', 'Prinivil, Zestril', 'ACE Inhibitor', 'C09AA03', '10mg - 40mg daily', 40.00, 1, 30.00, 'Reduce initial dose by 50% if eGFR < 30 mL/min. Monitor serum potassium and creatinine.', 'Angioedema, Bilateral Renal Artery Stenosis, Pregnancy'),
(3, 'ibuprofen', 'Advil, Motrin, Nurofen', 'NSAID', 'M01AE01', '200mg - 800mg q4-6h', 3200.00, 1, 60.00, 'Avoid in moderate-to-severe renal impairment (eGFR < 60). Induces renal vasoconstriction.', 'Active Peptic Ulcer, Severe Renal Impairment, Severe Heart Failure, Late Pregnancy'),
(4, 'warfarin', 'Coumadin, Jantoven', 'Vitamin K Antagonist Anticoagulant', 'B01AA03', '1mg - 10mg daily adjusted to INR', 10.00, 0, NULL, 'Dose adjusted per INR. Bleeding risk amplified in renal failure.', 'Active Major Bleeding, Pregnancy, Severe Liver Disease'),
(5, 'aspirin', 'Bayer, Ecotrin, Bufferin', 'Antiplatelet / Salicylate', 'B01AC06', '75mg - 325mg daily', 325.00, 1, 30.00, 'Avoid high doses if eGFR < 30. Increased gastrointestinal and systemic hemorrhage risk.', 'Active Bleeding, Peptic Ulcer Disease, Aspirin-induced Asthma'),
(6, 'atorvastatin', 'Lipitor, Torvast', 'HMG-CoA Reductase Inhibitor (Statin)', 'C10AA05', '10mg - 80mg daily', 80.00, 0, NULL, 'No renal dose adjustment typically needed, but monitor for myopathy.', 'Active Liver Disease, Unexplained Transaminase Elevation'),
(7, 'spironolactone', 'Aldactone, CaroSpir', 'Aldosterone Antagonist / Potassium-sparing Diuretic', 'C03DA01', '25mg - 100mg daily', 100.00, 1, 50.00, 'Risk of severe, life-threatening hyperkalemia if eGFR < 50 or serum K+ > 5.0 mEq/L.', 'Hyperkalemia, Severe Renal Impairment (eGFR < 30), Addisons Disease'),
(8, 'ciprofloxacin', 'Cipro, Ciproxin', 'Fluoroquinolone Antibiotic', 'J01MA02', '250mg - 750mg BID', 1500.00, 1, 50.00, 'If eGFR 30-50: 250-500mg q12h; If eGFR < 30: 250-500mg q18-24h.', 'Hypersensitivity, Myasthenia Gravis, Concomitant Tizanidine'),
(9, 'clopidogrel', 'Plavix', 'P2Y12 Platelet Inhibitor', 'B01AC04', '75mg daily', 75.00, 0, NULL, 'No adjustment required; bleeding precaution in renal disease.', 'Active Bleeding, Severe Hepatic Impairment'),
(10, 'propranolol', 'Inderal, Hemangeol', 'Non-selective Beta-blocker', 'C07AA05', '40mg - 160mg daily', 320.00, 0, NULL, 'Titrate cautiously in severe renal impairment.', 'Asthma, COPD, Sinus Bradycardia, 2nd/3rd Degree AV Block');

-- 2. MEDICINE ALIASES & MISSPELLINGS
INSERT INTO `medicine_aliases` (`medicine_id`, `alias_name`, `alias_type`) VALUES
(1, 'glucophage', 'brand'),
(1, 'metformn', 'misspelling'),
(1, 'glumetza', 'brand'),
(2, 'zestril', 'brand'),
(2, 'prinivil', 'brand'),
(2, 'lisnopril', 'misspelling'),
(3, 'advil', 'brand'),
(3, 'motrin', 'brand'),
(3, 'ibprufen', 'misspelling'),
(4, 'coumadin', 'brand'),
(4, 'jantoven', 'brand'),
(4, 'warfarn', 'misspelling'),
(5, 'ecotrin', 'brand'),
(5, 'bayer aspirin', 'brand'),
(6, 'lipitor', 'brand'),
(6, 'atorvastatn', 'misspelling'),
(7, 'aldactone', 'brand'),
(8, 'cipro', 'brand'),
(9, 'plavix', 'brand'),
(10, 'inderal', 'brand');

-- 3. CLINICAL DRUG INTERACTIONS
INSERT INTO `drug_interactions` (`drug_a_id`, `drug_b_id`, `severity`, `mechanism`, `clinical_evidence`, `suggested_action`, `rule_code`) VALUES
(4, 5, 'major', 'Synergistic inhibition of hemostasis: Warfarin inhibits vitamin K-dependent clotting factors while Aspirin impairs platelet aggregation and causes gastric mucosal injury.', 'CHEST Guidelines 2021; FDA Warfarin prescribing information warning on concurrent antiplatelet agents.', 'Review clinical indication for concurrent therapy. If not specifically indicated for acute coronary stenting, consider stopping Aspirin or add a proton-pump inhibitor (PPI) and monitor INR closely.', 'DDI-WAR-ASP-01'),
(2, 7, 'major', 'Concomitant ACE inhibitor (Lisinopril) and potassium-sparing diuretic (Spironolactone) produce additive retention of potassium in renal distal tubules, triggering acute hyperkalemia and arrhythmias.', 'KDIGO Clinical Practice Guideline for CKD 2023; AHA Heart Failure Guidelines.', 'Check baseline serum potassium and eGFR. Recheck electrolytes within 1 week of initiation. Discontinue or reduce Spironolactone if K+ > 5.0 mEq/L or eGFR < 30 mL/min.', 'DDI-LIS-SPI-01'),
(2, 3, 'major', 'NSAIDs inhibit renal prostaglandin synthesis, reducing renal blood flow and antagonizing the antihypertensive and nephroprotective effects of ACE inhibitors. Risk of acute kidney injury (Triple Whammy component).', 'British National Formulary (BNF); Kidney International Acute Kidney Injury Consensuses.', 'Avoid chronic concurrent use. Switch to topical analgesic, acetaminophen, or non-pharmacologic therapy. Monitor renal function and blood pressure within 2 weeks.', 'DDI-ACE-NSAID-01'),
(4, 8, 'major', 'Ciprofloxacin inhibits CYP1A2 and CYP3A4 metabolism of Warfarin and disrupts intestinal flora producing vitamin K, dramatically elevating INR and catastrophic hemorrhage risk.', 'American College of Clinical Pharmacy (ACCP) Drug Interaction Compendium.', 'Avoid combination if possible. Choose alternative antibiotic (e.g. cephalosporin if appropriate) or reduce Warfarin dose by 30-50% and monitor INR every 48 hours.', 'DDI-WAR-CIP-01'),
(4, 3, 'major', 'NSAIDs increase risk of severe gastrointestinal bleeding when combined with anticoagulants through gastric mucosa irritation and reversible platelet inhibition.', 'FDA Boxed Warning for Oral Anticoagulants and NSAID co-administration.', 'Avoid systemic NSAIDs in anticoagulated patients. Use paracetamol/acetaminophen or local physical therapy.', 'DDI-WAR-NSAID-01');

-- 4. CDS SAFETY RULES
INSERT INTO `safety_rules` (`rule_code`, `rule_type`, `title`, `description`, `severity`, `supporting_evidence`, `default_suggested_action`) VALUES
('RENAL-MET-01', 'renal_impairment', 'Metformin in Renal Impairment', 'Metformin accumulates in renal clearance failure, significantly elevating risk of lactic acidosis (approx 50% mortality rate). Contraindicated if eGFR < 30 mL/min.', 'critical', 'FDA Drug Safety Communication; ADA Standards of Medical Care in Diabetes 2024.', 'Discontinue Metformin immediately if eGFR < 30 mL/min. If eGFR is 30-44 mL/min, restrict dose to max 1000mg daily.'),
('DUP-STATIN-01', 'duplicate_therapy', 'Duplicate HMG-CoA Reductase Inhibitor (Statin) Therapy', 'Prescribing brand and generic statin simultaneously (e.g., Atorvastatin and Lipitor) leads to unintended overdose and rhabdomyolysis.', 'high', 'ISMP High Alert Medications; National Quality Forum Medication Safety Standards.', 'Discontinue the duplicate prescription immediately. Clarify current brand vs generic dispensed with the community pharmacy.'),
('CONTRA-BETA-ASTHMA-01', 'contraindication', 'Non-selective Beta-blocker in Asthma', 'Non-selective beta blockade (e.g., Propranolol) induces severe bronchospasm and refractoriness to beta-2 agonist rescue inhalers in patients with asthma.', 'critical', 'GINA Global Strategy for Asthma Management 2023; AHA/ACC Guidelines.', 'Discontinue non-selective beta blocker. Consult cardiology/neurology for cardio-selective alternative (e.g. Metoprolol succinate) or calcium channel blocker.'),
('RENAL-NSAID-01', 'renal_impairment', 'NSAID in Moderate to Severe CKD', 'Systemic NSAIDs cause renal afferent arteriolar vasoconstriction, hastening progression of chronic kidney disease and precipitating acute renal failure.', 'high', 'KDIGO CKD Work Group 2023.', 'Discontinue systemic NSAID. Consider paracetamol (acetaminophen) or non-pharmacologic pain modalities.');

-- 5. SYNTHETIC PATIENTS
INSERT INTO `patients` (`id`, `patient_mrn`, `full_name`, `date_of_birth`, `gender`, `egfr`, `serum_creatinine`, `conditions`, `allergies`, `is_near_miss_case`, `notes`) VALUES
(1, 'PT-2024-8801', 'Eleanor Vance', '1953-04-12', 'female', 28.00, 2.30, 'Type 2 Diabetes, Chronic Kidney Disease Stage 4, Hypertension, Osteoarthritis', 'Penicillin', 1, 'Multi-doctor patient followed by PCP, Nephrology, and Orthopedics. High clinical risk.'),
(2, 'PT-2024-8802', 'Robert Chen', '1960-08-25', 'male', 72.00, 1.10, 'Atrial Fibrillation, Deep Vein Thrombosis (History), Hyperlipidemia', 'Sulfa drugs', 1, 'Dual anticoagulant/antiplatelet co-prescribed across Cardiology and Hematology.'),
(3, 'PT-2024-8803', 'Maria Hernandez', '1966-11-03', 'female', 88.00, 0.90, 'Hyperlipidemia, Primary Hypertension', 'None known', 0, 'Prescriptions from PCP and Private Clinic with duplicate brand/generic statin.'),
(4, 'PT-2024-8804', 'David Miller', '1979-02-17', 'male', 105.00, 0.85, 'Bronchial Asthma, Chronic Migraine', 'Codeine', 1, 'Prescribed non-selective beta-blocker for migraine despite known active asthma.'),
(5, 'PT-2024-8805', 'Grace Hopper', '1956-12-09', 'female', 95.00, 0.75, 'Mild Primary Hypertension (Controlled)', 'None known', 0, 'Stable patient on single-agent therapy, serves as healthy control baseline.'),
(6, 'PT-2024-8806', 'Arthur Pendelton', '1952-06-18', 'male', 42.00, 1.80, 'Heart Failure with Reduced EF, CKD Stage 3b, Hypertension', 'Aspirin', 1, 'Seeded near-miss case: Prescribed ACEi + Spironolactone with border potassium and eGFR 42.');

-- 6. PATIENT MEDICATION HISTORIES (Multi-doctor over several years)
INSERT INTO `patient_medications` (`patient_id`, `medicine_id`, `raw_medicine_name`, `prescribed_generic_name`, `dosage`, `dose_amount_mg`, `frequency`, `route`, `prescribing_doctor`, `prescription_date`, `start_date`, `end_date`, `status`, `indication`) VALUES
-- Eleanor Vance (Patient 1): 3 doctors over 3 years producing multiple severe risks
(1, 1, 'Glucophage 1000mg', 'metformin', '1000 mg', 1000.00, 'Twice daily with meals', 'oral', 'Dr. Arthur Dent (Primary Care)', '2021-03-10', '2021-03-10', NULL, 'active', 'Type 2 Diabetes Mellitus'),
(1, 2, 'Lisinopril 20mg', 'lisinopril', '20 mg', 20.00, 'Once daily in the morning', 'oral', 'Dr. Marcus Vance (Cardiology)', '2022-09-15', '2022-09-15', NULL, 'active', 'Hypertension & Renal Protection'),
(1, 3, 'Motrin 800mg', 'ibuprofen', '800 mg', 800.00, 'Three times daily PRN severe pain', 'oral', 'Dr. Elena Rostova (Orthopedic Surgery)', '2023-11-20', '2023-11-20', NULL, 'active', 'Knee Osteoarthritis Flare'),

-- Robert Chen (Patient 2): 2 doctors prescribing Warfarin + Aspirin
(2, 4, 'Coumadin 5mg', 'warfarin', '5 mg', 5.00, 'Once daily at bedtime', 'oral', 'Dr. Sarah Adams (Hematology)', '2022-01-14', '2022-01-14', NULL, 'active', 'Atrial Fibrillation Thromboprophylaxis'),
(2, 5, 'Bayer Aspirin 325mg', 'aspirin', '325 mg', 325.00, 'Once daily', 'oral', 'Dr. James Miller (Cardiology)', '2024-02-05', '2024-02-05', NULL, 'active', 'Coronary Primary Prevention'),

-- Maria Hernandez (Patient 3): Duplicate Brand/Generic Statin
(3, 6, 'Atorvastatin 40mg', 'atorvastatin', '40 mg', 40.00, 'Once daily at bedtime', 'oral', 'Dr. Arthur Dent (Primary Care)', '2022-05-18', '2022-05-18', NULL, 'active', 'Hyperlipidemia'),
(3, 6, 'Lipitor 20mg', 'atorvastatin', '20 mg', 20.00, 'Once daily in evening', 'oral', 'Dr. Marcus Vance (Cardiology Clinic)', '2023-10-12', '2023-10-12', NULL, 'active', 'Cholesterol Lowering'),

-- David Miller (Patient 4): Asthma + Beta Blocker Contraindication
(4, 10, 'Inderal 40mg', 'propranolol', '40 mg', 40.00, 'Twice daily', 'oral', 'Dr. Sonia Gupta (Neurology)', '2023-08-10', '2023-08-10', NULL, 'active', 'Migraine Prophylaxis'),

-- Grace Hopper (Patient 5): Safe baseline control
(5, 2, 'Lisinopril 10mg', 'lisinopril', '10 mg', 10.00, 'Once daily', 'oral', 'Dr. Arthur Dent (Primary Care)', '2023-04-12', '2023-04-12', NULL, 'active', 'Essential Hypertension'),

-- Arthur Pendelton (Patient 6): Seeded Near-Miss (Spironolactone + Lisinopril in eGFR 42)
(6, 2, 'Zestril 20mg', 'lisinopril', '20 mg', 20.00, 'Once daily', 'oral', 'Dr. Marcus Vance (Cardiology)', '2022-03-20', '2022-03-20', NULL, 'active', 'Heart Failure Management'),
(6, 7, 'Aldactone 25mg', 'spironolactone', '25 mg', 25.00, 'Once daily in the morning', 'oral', 'Dr. Marcus Vance (Cardiology)', '2023-06-15', '2023-06-15', NULL, 'active', 'Heart Failure Symptom Control');

-- 7. EVALUATION GROUND TRUTH CASES (For Near-Miss and Recall Validation)
INSERT INTO `evaluation_cases` (`case_code`, `patient_id`, `expected_alert_type`, `expected_severity`, `expected_medicines`, `is_near_miss`, `ground_truth_description`) VALUES
('NM-CASE-001', 1, 'renal_risk', 'critical', 'metformin', 1, 'Metformin prescribed at 2000mg/day to patient with eGFR 28 mL/min (Contraindicated, high lactic acidosis risk).'),
('NM-CASE-002', 1, 'drug_interaction', 'major', 'lisinopril, ibuprofen', 1, 'NSAID + ACE Inhibitor co-prescribed causing renal vasoconstriction and blunting antihypertensive effect in CKD patient.'),
('NM-CASE-003', 2, 'drug_interaction', 'major', 'warfarin, aspirin', 1, 'Concurrent Warfarin and Aspirin co-prescribed across separate specialists without PPI gastroprotection.'),
('NM-CASE-004', 3, 'duplicate_therapy', 'high', 'atorvastatin, lipitor', 0, 'Duplicate statin prescription across two physicians due to brand/generic name discrepancy.'),
('NM-CASE-005', 4, 'contraindication', 'critical', 'propranolol', 1, 'Non-cardioselective beta-blocker prescribed to patient with active bronchial asthma.');
