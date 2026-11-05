<?php
/**
 * Automated H4 Clinical Decision Support Pipeline Runner
 * Runs Token Resolution & Safety Rule Engine across Patient Cohorts
 * Generates:
 *   - <out>/H4_predictions.csv
 *   - <out>/H4_resolution.csv
 *
 * Usage:
 *   php pipeline/run_h4_pipeline.php --split train --out submission_train
 *   php pipeline/run_h4_pipeline.php --split test --out submission
 */

declare(strict_types=1);

require_once __DIR__ . '/SafetyEngineH4.php';

$opts = getopt('', ['split:', 'out:']);
$split = $opts['split'] ?? 'test';
$outDir = $opts['out'] ?? ($split === 'train' ? 'submission_train' : 'submission');

$dataDir = __DIR__ . '/../data';
$patCsv = $dataDir . "/patients_{$split}.csv";

if (!file_exists($patCsv)) {
    fwrite(STDERR, "Patient CSV not found: {$patCsv}\n");
    exit(1);
}

if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

echo "Initializing H4 Clinical Safety Engine & Resolver...\n";
$engine = new SafetyEngineH4($dataDir);
$resolver = $engine->getResolver();

$hPat = fopen($patCsv, 'r');
$header = fgetcsv($hPat);

$pidIdx = array_search('patient_id', $header, true);
$medIdx = array_search('medication_list', $header, true);
$egfrIdx = array_search('egfr_ml_min_1_73m2', $header, true);
$diagIdx = array_search('diagnoses', $header, true);
$allgIdx = array_search('allergies', $header, true);
$pregIdx = array_search('pregnant', $header, true);

$resRows = [];
$alerts = [];
$patientCount = 0;
$totalTokens = 0;
$resolvedTokens = 0;

$t0 = microtime(true);

while (($r = fgetcsv($hPat)) !== false) {
    $patientCount++;
    $pid = trim($r[$pidIdx] ?? '');
    if ($pid === '') continue;

    $medList = $r[$medIdx] ?? '';
    $egfrRaw = trim($r[$egfrIdx] ?? '');
    $egfr = ($egfrRaw !== '') ? (int)$egfrRaw : null;
    $diag = $r[$diagIdx] ?? '';
    $allg = $r[$allgIdx] ?? '';
    $preg = $r[$pregIdx] ?? 'NA';

    // 1. Resolution
    $rawTokens = preg_split('/[;\n]+/', (string)$medList);
    foreach ($rawTokens as $tok) {
        $tokTrimmed = trim($tok);
        if ($tokTrimmed === '') continue;

        $totalTokens++;
        $gens = $resolver->resolve($tokTrimmed);
        sort($gens);
        $genStr = implode(';', $gens);

        if ($genStr !== '') {
            $resolvedTokens++;
        }

        $resRows[] = [
            'patient_id' => $pid,
            'written_token' => $tokTrimmed,
            'resolved_generic' => $genStr
        ];
    }

    // 2. Safety Checking
    $patAlerts = $engine->evaluatePatient($pid, $medList, $egfr, $diag, $allg, $preg);
    foreach ($patAlerts as $a) {
        $alerts[] = $a;
    }
}
fclose($hPat);

// Write H4_predictions.csv
$predFile = $outDir . '/H4_predictions.csv';
$fpPred = fopen($predFile, 'w');
fputcsv($fpPred, ['patient_id', 'issue_type', 'severity', 'drugs_involved', 'suggested_action', 'confidence']);
foreach ($alerts as $a) {
    fputcsv($fpPred, [
        $a['patient_id'],
        $a['issue_type'],
        $a['severity'],
        $a['drugs_involved'],
        $a['suggested_action'],
        number_format((float)$a['confidence'], 2)
    ]);
}
fclose($fpPred);

// Write H4_resolution.csv
$resFile = $outDir . '/H4_resolution.csv';
$fpRes = fopen($resFile, 'w');
fputcsv($fpRes, ['patient_id', 'written_token', 'resolved_generic']);
foreach ($resRows as $r) {
    fputcsv($fpRes, [
        $r['patient_id'],
        $r['written_token'],
        $r['resolved_generic']
    ]);
}
fclose($fpRes);

$dt = round(microtime(true) - $t0, 2);

echo "==================================================================\n";
echo "  H4 Pipeline Execution Completed in {$dt}s                      \n";
echo "==================================================================\n";
echo "Split processed            : {$split} ({$patientCount} patients)\n";
echo "Written tokens resolved    : {$totalTokens} entries ({$resolvedTokens} non-empty)\n";
echo "Clinical alerts generated  : " . count($alerts) . " alerts\n";
echo "Predictions written to     : {$predFile}\n";
echo "Resolutions written to     : {$resFile}\n";
echo "==================================================================\n";

