<?php
/**
 * Rigorous Submission File Validator
 * Validates against all evaluate.py constraints before final packaging
 */

declare(strict_types=1);

// Find submission/: next to the code (working folder) or one level up (packaged zip: FULL_CODE/ + submission/).
// Override with: php pipeline/validate_submission.php --dir path/to/submission
$opts = getopt('', ['dir:']);
$subDir = $opts['dir'] ?? null;
if ($subDir === null) {
    foreach ([__DIR__ . '/../submission', __DIR__ . '/../../submission'] as $cand) {
        if (file_exists($cand . '/H4_predictions.csv')) {
            $subDir = $cand;
            break;
        }
    }
    $subDir ??= __DIR__ . '/../submission';
}
$subPred = $subDir . '/H4_predictions.csv';
$subRes = $subDir . '/H4_resolution.csv';

$errors = [];

// 1. Validate Predictions
if (!file_exists($subPred)) {
    die("[FATAL] Predictions file missing: {$subPred}\n");
}

$h = fopen($subPred, 'r');
$header = fgetcsv($h);
$reqPredCols = ['patient_id', 'issue_type', 'severity', 'drugs_involved', 'suggested_action', 'confidence'];
if ($header !== $reqPredCols) {
    $errors[] = "Predictions header mismatch: got " . implode(',', $header) . " expected " . implode(',', $reqPredCols);
}

$allowedTypes = ['interaction', 'duplicate_therapy', 'renal_dose', 'contraindication'];
$allowedSevs = ['contraindicated', 'major', 'moderate', 'minor'];
$predKeys = [];
$predRowCount = 0;

while (($row = fgetcsv($h)) !== false) {
    $predRowCount++;
    $pid = trim($row[0] ?? '');
    $type = trim($row[1] ?? '');
    $sev = trim($row[2] ?? '');
    $drugs = trim($row[3] ?? '');
    $act = trim($row[4] ?? '');
    $conf = trim($row[5] ?? '');

    if ($pid === '') $errors[] = "Row {$predRowCount}: empty patient_id";
    if (!in_array($type, $allowedTypes, true)) $errors[] = "Row {$predRowCount}: invalid issue_type '{$type}'";
    if (!in_array($sev, $allowedSevs, true)) $errors[] = "Row {$predRowCount}: invalid severity '{$sev}'";
    if ($drugs === '') $errors[] = "Row {$predRowCount}: empty drugs_involved";
    if (str_contains($drugs, ',')) $errors[] = "Row {$predRowCount}: comma found in drugs_involved '{$drugs}'";
    if ($act === '') $errors[] = "Row {$predRowCount}: empty suggested_action";
    $confVal = (float)$conf;
    if ($conf === '' || $confVal < 0 || $confVal > 1) $errors[] = "Row {$predRowCount}: invalid confidence '{$conf}'";

    $drugParts = array_filter(array_map('trim', explode(';', strtolower($drugs))));
    sort($drugParts);
    $drugKey = implode(';', $drugParts);
    $key = "{$pid}|{$type}|{$drugKey}";
    if (isset($predKeys[$key])) {
        $errors[] = "Row {$predRowCount}: duplicate alert for key {$key}";
    }
    $predKeys[$key] = true;
}
fclose($h);

// 2. Validate Resolutions
if (!file_exists($subRes)) {
    die("[FATAL] Resolution file missing: {$subRes}\n");
}

$h = fopen($subRes, 'r');
$header = fgetcsv($h);
$reqResCols = ['patient_id', 'written_token', 'resolved_generic'];
if ($header !== $reqResCols) {
    $errors[] = "Resolution header mismatch: got " . implode(',', $header) . " expected " . implode(',', $reqResCols);
}

$resKeys = [];
$resRowCount = 0;
while (($row = fgetcsv($h)) !== false) {
    $resRowCount++;
    $pid = trim($row[0] ?? '');
    $tok = trim($row[1] ?? '');
    $gen = trim($row[2] ?? '');

    if ($pid === '') $errors[] = "Resolution Row {$resRowCount}: empty patient_id";
    if ($tok === '') $errors[] = "Resolution Row {$resRowCount}: empty written_token";
    if (str_contains($gen, ',')) $errors[] = "Resolution Row {$resRowCount}: comma found in resolved_generic '{$gen}'";

    $key = "{$pid}|" . strtolower($tok);
    if (isset($resKeys[$key])) {
        $errors[] = "Resolution Row {$resRowCount}: duplicate token key {$key}";
    }
    $resKeys[$key] = true;
}
fclose($h);

echo "==================================================================\n";
echo "  Submission Validation Summary                                   \n";
echo "==================================================================\n";
echo "Predictions rows validated : {$predRowCount}\n";
echo "Resolution rows validated  : {$resRowCount}\n";
if (empty($errors)) {
    echo "[PASSED] ALL VALIDATION CHECKS PASSED WITH 0 ERRORS!\n";
} else {
    echo "[FAILED] Found " . count($errors) . " errors:\n";
    foreach (array_slice($errors, 0, 10) as $e) {
        echo "  - {$e}\n";
    }
}
echo "==================================================================\n";

