<?php
/**
 * Detailed missed-alert diagnostic: shows patient diagnoses/conditions
 * for each missed case so we can identify missing condition patterns.
 */
define('APP_INIT', 1);
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/database.php';

// Build PDO directly
$dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

// Load train labels
$labels = [];
$fh = fopen(__DIR__ . '/../data/train_labels.csv', 'r');
$hdr = fgetcsv($fh);
while ($row = fgetcsv($fh)) {
    $row = array_combine($hdr, $row);
    $labels[] = $row;
}
fclose($fh);

// Load our predictions
$preds = [];
$fh = fopen(__DIR__ . '/../submission_train/H4_predictions.csv', 'r');
$hdr2 = fgetcsv($fh);
while ($row = fgetcsv($fh)) {
    $row = array_combine($hdr2, $row);
    $key = $row['patient_id'] . '||' . $row['issue_type'] . '||' . $row['drugs_involved'];
    $preds[$key] = $row;
}
fclose($fh);

// Find missed by type
$missedByType = [];
foreach ($labels as $lbl) {
    // Try to find a match
    $pid   = $lbl['patient_id'];
    $atype = $lbl['issue_type'];
    $drugs = $lbl['drugs_involved'];

    // Check if we generated ANY alert for this patient+type+drug combo
    $found = false;
    foreach ($preds as $k => $p) {
        if ($p['patient_id'] !== $pid) continue;
        if ($p['issue_type'] !== $atype) continue;
        // Drug match: at least one drug in common
        $pdrugs = explode(';', strtolower($p['drugs_involved']));
        $ldrugs = explode(';', strtolower($drugs));
        if (array_intersect($pdrugs, $ldrugs)) { $found = true; break; }
    }
    if (!$found) {
        $missedByType[$atype][] = ['patient_id' => $pid, 'drugs' => $drugs, 'severity' => $lbl['severity']];
    }
}

echo "=== MISSED BY TYPE ===\n";
foreach ($missedByType as $type => $items) {
    echo "\n[$type] " . count($items) . " missed\n";
}

// For contraindications: fetch patient diagnoses for missed ones
echo "\n=== MISSED CONTRAINDICATIONS (with diagnoses) ===\n";
$contras = $missedByType['contraindication'] ?? [];
$limit = 40;
foreach (array_slice($contras, 0, $limit) as $m) {
    $stmt = $pdo->prepare('SELECT diagnoses, medication_list FROM patients WHERE patient_id=?');
    $stmt->execute([$m['patient_id']]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    $diag = $p['diagnoses'] ?? 'N/A';
    $meds = $p['medication_list'] ?? 'N/A';
    echo "  {$m['patient_id']} | {$m['severity']} | drug={$m['drugs']} | diag=" . substr($diag, 0, 120) . "\n";
}

// For duplicate_therapy: show medication_list
echo "\n=== MISSED DUPLICATE THERAPY (with meds) ===\n";
$dups = $missedByType['duplicate_therapy'] ?? [];
foreach (array_slice($dups, 0, 30) as $m) {
    $stmt = $pdo->prepare('SELECT medication_list FROM patients WHERE patient_id=?');
    $stmt->execute([$m['patient_id']]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    $meds = $p['medication_list'] ?? 'N/A';
    echo "  {$m['patient_id']} | drug={$m['drugs']} | meds=" . substr($meds, 0, 150) . "\n";
}

// For interactions: show medication_list
echo "\n=== MISSED INTERACTIONS (with meds) ===\n";
$ints = $missedByType['interaction'] ?? [];
foreach (array_slice($ints, 0, 30) as $m) {
    $stmt = $pdo->prepare('SELECT medication_list FROM patients WHERE patient_id=?');
    $stmt->execute([$m['patient_id']]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    $meds = $p['medication_list'] ?? 'N/A';
    echo "  {$m['patient_id']} | drug={$m['drugs']} | meds=" . substr($meds, 0, 150) . "\n";
}

// For renal: show eGFR + meds
echo "\n=== MISSED RENAL (with eGFR+meds) ===\n";
$renals = $missedByType['renal_dose'] ?? [];
foreach (array_slice($renals, 0, 25) as $m) {
    $stmt = $pdo->prepare('SELECT egfr_ml_min_1_73m2, medication_list FROM patients WHERE patient_id=?');
    $stmt->execute([$m['patient_id']]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    $egfr = $p['egfr_ml_min_1_73m2'] ?? 'NULL';
    $meds = $p['medication_list'] ?? 'N/A';
    echo "  {$m['patient_id']} | drug={$m['drugs']} | eGFR=$egfr | meds=" . substr($meds, 0, 120) . "\n";
}
