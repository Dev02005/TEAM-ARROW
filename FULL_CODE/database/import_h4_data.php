<?php
/**
 * H4 Official Dataset Importer
 * Loads all authoritative CSV tables into MySQL / MariaDB
 * Supports 496 Medicines, 98 Combinations, 2,196 Interactions, 169 Renal Rules,
 * 74 Duplicate Groups, 152 Contraindications, 58 Conditions, and Patient Cohorts.
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

function logInfo(string $msg, bool $isCli): void {
    if ($isCli) {
        echo "[INFO] " . $msg . PHP_EOL;
    } else {
        echo "<div style='font-family:sans-serif; margin:4px 0; padding:6px 10px; background:#f1f5f9; border-left:3px solid #0284c7; font-size:14px;'>" . htmlspecialchars($msg) . "</div>";
    }
}

function logSuccess(string $msg, bool $isCli): void {
    if ($isCli) {
        echo "[SUCCESS] " . $msg . PHP_EOL;
    } else {
        echo "<div style='font-family:sans-serif; margin:4px 0; padding:6px 10px; background:#ecfdf5; border-left:3px solid #10b981; font-size:14px; color:#065f46; font-weight:600;'>" . htmlspecialchars($msg) . "</div>";
    }
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET),
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // 1. Run schema_h4.sql
    logInfo("Executing database/schema_h4.sql...", $isCli);
    $schemaSql = file_get_contents(__DIR__ . '/schema_h4.sql');
    $pdo->exec($schemaSql);
    logSuccess("Schema created / refreshed successfully.", $isCli);

    $pdo->exec("USE `" . DB_NAME . "`");
    $dataDir = __DIR__ . '/../data';

    // Helper to open CSV
    $openCsv = function(string $filename) use ($dataDir): array {
        $path = $dataDir . '/' . $filename;
        if (!file_exists($path)) {
            throw new RuntimeException("Data file not found: " . $path);
        }
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            $row = [];
            foreach ($header as $i => $col) {
                $row[$col] = $data[$i] ?? '';
            }
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    };

    // 2. Import Medicines (drug_reference.csv)
    logInfo("Importing drug_reference.csv...", $isCli);
    $drugs = $openCsv('drug_reference.csv');
    $stmtDrug = $pdo->prepare("
        INSERT INTO medicines (
            drug_id, generic_name, drug_class, therapeutic_group, therapeutic_class,
            duplicate_group, common_brands, brand_names, dose_forms,
            usual_adult_daily_dose_range, standard_dosage_range, max_daily_dose_mg,
            renal_dose_adjustment_required, renal_adjustment_flag, renal_rule,
            renal_adjustment_notes, pregnancy_category, notes
        ) VALUES (
            :drug_id, :generic_name, :drug_class, :therapeutic_group, :therapeutic_class,
            :duplicate_group, :common_brands, :brand_names, :dose_forms,
            :usual_adult_daily_dose_range, :standard_dosage_range, :max_daily_dose_mg,
            :renal_dose_adjustment_required, :renal_adjustment_flag, :renal_rule,
            :renal_adjustment_notes, :pregnancy_category, :notes
        )
    ");
    $stmtAlias = $pdo->prepare("
        INSERT INTO medicine_aliases (medicine_id, alias_name, alias_type)
        VALUES (:medicine_id, :alias_name, :alias_type)
    ");

    $pdo->beginTransaction();
    $drugIdMap = [];
    foreach ($drugs as $d) {
        $genLower = strtolower(trim($d['generic_name']));
        $maxDose = ($d['max_daily_dose_mg'] !== '') ? (float)$d['max_daily_dose_mg'] : null;
        $renalReq = ($d['renal_adjustment_flag'] === 'Y') ? 1 : 0;
        $stmtDrug->execute([
            ':drug_id' => $d['drug_id'],
            ':generic_name' => $genLower,
            ':drug_class' => $d['drug_class'],
            ':therapeutic_group' => $d['therapeutic_group'],
            ':therapeutic_class' => $d['drug_class'],
            ':duplicate_group' => !empty($d['duplicate_group']) ? $d['duplicate_group'] : null,
            ':common_brands' => $d['common_brands'],
            ':brand_names' => $d['common_brands'],
            ':dose_forms' => $d['dose_forms'],
            ':usual_adult_daily_dose_range' => $d['usual_adult_daily_dose_range'],
            ':standard_dosage_range' => $d['usual_adult_daily_dose_range'],
            ':max_daily_dose_mg' => $maxDose,
            ':renal_dose_adjustment_required' => $renalReq,
            ':renal_adjustment_flag' => $d['renal_adjustment_flag'] ?: 'N',
            ':renal_rule' => $d['renal_rule'],
            ':renal_adjustment_notes' => $d['renal_rule'],
            ':pregnancy_category' => $d['pregnancy_category'] ?: 'N',
            ':notes' => $d['notes']
        ]);
        $medPk = (int)$pdo->lastInsertId();
        $drugIdMap[$genLower] = $medPk;

        // Insert brands into aliases
        if (!empty($d['common_brands'])) {
            $brands = explode(';', $d['common_brands']);
            foreach ($brands as $b) {
                $bClean = trim($b);
                if (!empty($bClean)) {
                    $stmtAlias->execute([
                        ':medicine_id' => $medPk,
                        ':alias_name' => $bClean,
                        ':alias_type' => 'brand'
                    ]);
                }
            }
        }
    }
    $pdo->commit();
    logSuccess("Imported " . count($drugs) . " reference medicines and brand aliases.", $isCli);

    // 3. Import Combination Brands (combination_brands.csv)
    logInfo("Importing combination_brands.csv...", $isCli);
    $combos = $openCsv('combination_brands.csv');
    $stmtCombo = $pdo->prepare("
        INSERT INTO combination_brands (brand_name, strength_label, dose_form, components, generic_components)
        VALUES (:brand_name, :strength_label, :dose_form, :components, :generic_components)
    ");
    $pdo->beginTransaction();
    foreach ($combos as $c) {
        $stmtCombo->execute([
            ':brand_name' => trim($c['brand_name']),
            ':strength_label' => trim($c['strength_label']),
            ':dose_form' => trim($c['dose_form']),
            ':components' => trim($c['components']),
            ':generic_components' => trim($c['generic_components'])
        ]);
    }
    $pdo->commit();
    logSuccess("Imported " . count($combos) . " combination brands.", $isCli);

    // 4. Import Drug Interactions (interactions.csv)
    logInfo("Importing interactions.csv...", $isCli);
    $interactions = $openCsv('interactions.csv');
    $stmtInter = $pdo->prepare("
        INSERT IGNORE INTO drug_interactions (drug_a, drug_b, severity, mechanism_effect, recommended_action)
        VALUES (:drug_a, :drug_b, :severity, :mechanism_effect, :recommended_action)
    ");
    $pdo->beginTransaction();
    foreach ($interactions as $ix) {
        $a = strtolower(trim($ix['drug_a']));
        $b = strtolower(trim($ix['drug_b']));
        if ($a > $b) {
            $temp = $a; $a = $b; $b = $temp;
        }
        $stmtInter->execute([
            ':drug_a' => $a,
            ':drug_b' => $b,
            ':severity' => strtolower(trim($ix['severity'])),
            ':mechanism_effect' => trim($ix['mechanism_effect']),
            ':recommended_action' => trim($ix['recommended_action'])
        ]);
    }
    $pdo->commit();
    logSuccess("Imported " . count($interactions) . " drug interaction pairs.", $isCli);

    // 5. Import Renal Rules (renal_rules.csv)
    logInfo("Importing renal_rules.csv...", $isCli);
    $renals = $openCsv('renal_rules.csv');
    $stmtRenal = $pdo->prepare("
        INSERT INTO renal_rules (generic_name, egfr_below, severity, recommended_action)
        VALUES (:generic_name, :egfr_below, :severity, :recommended_action)
    ");
    $pdo->beginTransaction();
    foreach ($renals as $r) {
        $stmtRenal->execute([
            ':generic_name' => strtolower(trim($r['generic_name'])),
            ':egfr_below' => (int)$r['egfr_below'],
            ':severity' => strtolower(trim($r['severity'])),
            ':recommended_action' => trim($r['recommended_action'])
        ]);
    }
    $pdo->commit();
    logSuccess("Imported " . count($renals) . " renal dosing tiers.", $isCli);

    // 6. Import Duplicate Therapy Groups (duplicate_therapy_groups.csv)
    logInfo("Importing duplicate_therapy_groups.csv...", $isCli);
    $dups = $openCsv('duplicate_therapy_groups.csv');
    $stmtDup = $pdo->prepare("
        INSERT INTO duplicate_therapy_groups (duplicate_group, group_name, severity, recommended_action, member_generics)
        VALUES (:duplicate_group, :group_name, :severity, :recommended_action, :member_generics)
    ");
    $pdo->beginTransaction();
    foreach ($dups as $d) {
        $stmtDup->execute([
            ':duplicate_group' => trim($d['duplicate_group']),
            ':group_name' => trim($d['group_name']),
            ':severity' => strtolower(trim($d['severity'])),
            ':recommended_action' => trim($d['recommended_action']),
            ':member_generics' => trim($d['member_generics'])
        ]);
    }
    $pdo->commit();
    logSuccess("Imported " . count($dups) . " duplicate therapy groups.", $isCli);

    // 7. Import Drug Disease Contraindications (drug_disease_contraindications.csv)
    logInfo("Importing drug_disease_contraindications.csv...", $isCli);
    $contras = $openCsv('drug_disease_contraindications.csv');
    $stmtContra = $pdo->prepare("
        INSERT INTO drug_disease_contraindications (condition_id, condition_name, generic_name, severity, reason, recommended_action)
        VALUES (:condition_id, :condition_name, :generic_name, :severity, :reason, :recommended_action)
    ");
    $pdo->beginTransaction();
    foreach ($contras as $c) {
        $stmtContra->execute([
            ':condition_id' => trim($c['condition_id']),
            ':condition_name' => trim($c['condition_name']),
            ':generic_name' => strtolower(trim($c['generic_name'])),
            ':severity' => strtolower(trim($c['severity'])),
            ':reason' => trim($c['reason']),
            ':recommended_action' => trim($c['recommended_action'])
        ]);
    }
    $pdo->commit();
    logSuccess("Imported " . count($contras) . " drug-disease contraindications.", $isCli);

    // 8. Import Conditions (conditions.csv)
    logInfo("Importing conditions.csv...", $isCli);
    $conditions = $openCsv('conditions.csv');
    $stmtCond = $pdo->prepare("
        INSERT INTO conditions (condition_id, condition_name, kind, description, example_terms)
        VALUES (:condition_id, :condition_name, :kind, :description, :example_terms)
    ");
    $pdo->beginTransaction();
    foreach ($conditions as $c) {
        $stmtCond->execute([
            ':condition_id' => trim($c['condition_id']),
            ':condition_name' => trim($c['condition_name']),
            ':kind' => trim($c['kind']),
            ':description' => trim($c['description']),
            ':example_terms' => trim($c['example_terms'])
        ]);
    }
    $pdo->commit();
    logSuccess("Imported " . count($conditions) . " condition references.", $isCli);

    // 9. Load Near-Miss Patient IDs from train_labels.csv
    logInfo("Analyzing train labels for near-miss cases...", $isCli);
    $trainLabels = $openCsv('train_labels.csv');
    $nearMissPids = [];
    foreach ($trainLabels as $tl) {
        if (!empty($tl['near_miss']) && (int)$tl['near_miss'] === 1) {
            $nearMissPids[trim($tl['patient_id'])] = true;
        }
    }

    // 10. Import Patients (patients_train.csv and patients_test.csv)
    logInfo("Importing patients_train.csv...", $isCli);
    $trainPats = $openCsv('patients_train.csv');
    $testPats = $openCsv('patients_test.csv');

    $stmtPat = $pdo->prepare("
        INSERT INTO patients (
            patient_id, patient_mrn, full_name, cohort, age, sex, gender,
            weight_kg, egfr, egfr_ml_min_1_73m2, pregnant, diagnoses, conditions,
            allergies, medication_list, prescriber_count, is_near_miss_case
        ) VALUES (
            :patient_id, :patient_mrn, :full_name, :cohort, :age, :sex, :gender,
            :weight_kg, :egfr, :egfr_ml_min_1_73m2, :pregnant, :diagnoses, :conditions,
            :allergies, :medication_list, :prescriber_count, :is_near_miss_case
        )
    ");

    $stmtMed = $pdo->prepare("
        INSERT INTO patient_medications (
            patient_id, medicine_id, raw_medicine_name, prescribed_generic_name,
            dosage, dose_amount_mg, frequency, route, prescribing_doctor,
            prescription_date, start_date, status, indication
        ) VALUES (
            :patient_id, :medicine_id, :raw_medicine_name, :prescribed_generic_name,
            :dosage, :dose_amount_mg, :frequency, :route, :prescribing_doctor,
            :prescription_date, :start_date, :status, :indication
        )
    ");

    // First names / surnames for human-readable display of synthetic patient IDs
    $firstNamesM = ['Aarav', 'Vivaan', 'Aditya', 'Vihaan', 'Arjun', 'Sai', 'Reyansh', 'Ayaan', 'Krishna', 'Ishaan', 'Shaurya', 'Rohan', 'Dhruv', 'Kabir', 'Ramesh', 'Suresh', 'Rajesh', 'Anil', 'Sunil', 'Vijay'];
    $firstNamesF = ['Saanvi', 'Aanya', 'Aadhya', 'Aaradhya', 'Ananya', 'Pari', 'Diya', 'Myra', 'Isha', 'Avani', 'Sneha', 'Pooja', 'Priya', 'Sunita', 'Anita', 'Kavita', 'Meena', 'Geeta', 'Rekha', 'Lakshmi'];
    $lastNames = ['Sharma', 'Verma', 'Patel', 'Gupta', 'Singh', 'Kumar', 'Mishra', 'Joshi', 'Rao', 'Nair', 'Reddy', 'Deshmukh', 'Kulkarni', 'Bhat', 'Mehta', 'Chopra', 'Malhotra', 'Saxena', 'Pandey', 'Yadav'];

    $generateName = function(string $pid, string $sex) use ($firstNamesM, $firstNamesF, $lastNames): string {
        $hash = crc32($pid);
        $fnPool = ($sex === 'M') ? $firstNamesM : $firstNamesF;
        $fn = $fnPool[abs($hash) % count($fnPool)];
        $ln = $lastNames[abs($hash >> 8) % count($lastNames)];
        return $fn . ' ' . $ln;
    };

    $doctors = [
        'Dr. Ashok Sharma (Senior Physician)',
        'Dr. Sunita Patel (Cardiologist)',
        'Dr. Rajesh Verma (OPD Clinic)',
        'Dr. Meenakshi Rao (Nephrology)',
        'Dr. Vikram Singh (Orthopaedics)'
    ];

    $importPatientsList = function(array $pats, string $cohort) use ($pdo, $stmtPat, $stmtMed, $generateName, $doctors, $nearMissPids, $drugIdMap) {
        $pdo->beginTransaction();
        foreach ($pats as $p) {
            $pid = trim($p['patient_id']);
            $sex = trim($p['sex']);
            $gender = ($sex === 'M') ? 'male' : (($sex === 'F') ? 'female' : 'other');
            $fullName = $generateName($pid, $sex);
            $weight = ($p['weight_kg'] !== '') ? (float)$p['weight_kg'] : null;
            $egfr = ($p['egfr_ml_min_1_73m2'] !== '') ? (int)$p['egfr_ml_min_1_73m2'] : null;
            $isNearMiss = isset($nearMissPids[$pid]) ? 1 : 0;
            $prescCount = max(1, (int)($p['prescriber_count'] ?? 1));

            $stmtPat->execute([
                ':patient_id' => $pid,
                ':patient_mrn' => $pid,
                ':full_name' => $fullName,
                ':cohort' => $cohort,
                ':age' => (int)$p['age'],
                ':sex' => in_array($sex, ['M', 'F'], true) ? $sex : 'NA',
                ':gender' => $gender,
                ':weight_kg' => $weight,
                ':egfr' => $egfr,
                ':egfr_ml_min_1_73m2' => $egfr,
                ':pregnant' => in_array(trim($p['pregnant']), ['Y', 'N'], true) ? trim($p['pregnant']) : 'NA',
                ':diagnoses' => trim($p['diagnoses']),
                ':conditions' => trim($p['diagnoses']),
                ':allergies' => trim($p['allergies']),
                ':medication_list' => trim($p['medication_list']),
                ':prescriber_count' => $prescCount,
                ':is_near_miss_case' => $isNearMiss
            ]);
            $patPk = (int)$pdo->lastInsertId();

            // Populate parsed entries for active medication locker / timeline
            $medTokens = preg_split('/[;\n]+/', (string)$p['medication_list']);
            $docIdx = 0;
            $dateOffset = 700; // ~2 years ago
            foreach ($medTokens as $tok) {
                $tok = trim($tok);
                if (empty($tok)) continue;

                $assignedDoctor = $doctors[$docIdx % min($prescCount, count($doctors))];
                $docIdx++;
                $rxDate = date('Y-m-d', strtotime("-{$dateOffset} days"));
                $dateOffset = max(10, $dateOffset - 90);

                // Check if stopped/discontinued
                $isDiscontinued = (bool)preg_match('/\b(stopped|stop|discontinued|withheld)\b/i', $tok);

                $stmtMed->execute([
                    ':patient_id' => $patPk,
                    ':medicine_id' => null,
                    ':raw_medicine_name' => $tok,
                    ':prescribed_generic_name' => null,
                    ':dosage' => 'As prescribed',
                    ':dose_amount_mg' => null,
                    ':frequency' => 'Per regimen',
                    ':route' => 'oral',
                    ':prescribing_doctor' => $assignedDoctor,
                    ':prescription_date' => $rxDate,
                    ':start_date' => $rxDate,
                    ':status' => $isDiscontinued ? 'discontinued' : 'active',
                    ':indication' => null
                ]);
            }
        }
        $pdo->commit();
    };

    logInfo("Importing 1,500 train patients and parsing medication timelines...", $isCli);
    $importPatientsList($trainPats, 'train');
    logSuccess("Train cohort imported successfully.", $isCli);

    logInfo("Importing 3,500 test patients...", $isCli);
    $importPatientsList($testPats, 'test');
    logSuccess("Test cohort imported successfully.", $isCli);

    // 11. Create Demo Citizen and Admin Accounts
    logInfo("Creating demo authentication accounts...", $isCli);
    $stmtUser = $pdo->prepare("
        INSERT INTO users (patient_id, email, password_hash, full_name, role)
        VALUES (:patient_id, :email, :password_hash, :full_name, :role)
    ");

    // Pick first 5 train patients as demo citizen accounts
    $firstPats = $pdo->query("SELECT id, patient_id, full_name, diagnoses, egfr FROM patients WHERE cohort = 'train' LIMIT 5")->fetchAll();
    $demoUsers = [
        [
            'patient_id' => null,
            'email' => 'admin@dosewise.com',
            'full_name' => 'Dr. Chandrapuri Chief Medical Officer',
            'role' => 'admin',
            'password' => 'admin123'
        ]
    ];

    $demoEmails = ['citizen1@example.com', 'citizen2@example.com', 'citizen3@example.com', 'citizen4@example.com', 'citizen5@example.com'];
    foreach ($firstPats as $idx => $fp) {
        $demoUsers[] = [
            'patient_id' => (int)$fp['id'],
            'email' => $demoEmails[$idx],
            'full_name' => $fp['full_name'],
            'role' => 'citizen',
            'password' => 'citizen123'
        ];
    }

    foreach ($demoUsers as $du) {
        $stmtUser->execute([
            ':patient_id' => $du['patient_id'],
            ':email' => $du['email'],
            ':password_hash' => password_hash($du['password'], PASSWORD_DEFAULT),
            ':full_name' => $du['full_name'],
            ':role' => $du['role']
        ]);
        logInfo("Account created: {$du['email']} ({$du['role']})", $isCli);
    }

    logSuccess("Database Migration & H4 Import Completed Successfully!", $isCli);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "[ERROR] " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}

