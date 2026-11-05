<?php
/**
 * Official Evaluation Scorer (PHP Implementation of evaluate.py)
 * Computes:
 *   - Serious Recall (weight 0.40)
 *   - Near-Miss Recall (weight 0.15)
 *   - Precision (weight 0.15)
 *   - Graded Recall (weight 0.15)
 *   - Resolution Accuracy (weight 0.15)
 *   - Alert Fatigue Multiplier (tolerance 2.0, minor weight 0.25)
 *   - Primary Score (Base * Fatigue Multiplier)
 */

declare(strict_types=1);

$W_SERIOUS_RECALL = 0.40;
$W_NEAR_MISS_RECALL = 0.15;
$W_PRECISION = 0.15;
$W_GRADED_RECALL = 0.15;
$W_RESOLUTION = 0.15;
$NOISE_TOLERANCE = 2.0;
$MINOR_NOISE_WEIGHT = 0.25;

function normDrugs(string $s): array {
    $parts = explode(';', $s);
    $set = [];
    foreach ($parts as $p) {
        $p = strtolower(trim($p));
        if ($p !== '') {
            $set[$p] = true;
        }
    }
    $keys = array_keys($set);
    sort($keys);
    return $keys;
}

function normDrugsKey(string $s): string {
    return implode(';', normDrugs($s));
}

function readCsvAssoc(string $path): array {
    if (!file_exists($path)) {
        throw new RuntimeException("File not found: " . $path);
    }
    $h = fopen($path, 'r');
    $header = fgetcsv($h);
    $rows = [];
    while (($d = fgetcsv($h)) !== false) {
        $r = [];
        foreach ($header as $i => $col) {
            $r[$col] = $d[$i] ?? '';
        }
        $rows[] = $r;
    }
    fclose($h);
    return $rows;
}

function runEvaluation(string $predPath, string $truthPath, ?string $resPath = null, ?string $truthResPath = null, ?string $outJson = null): array {
    global $W_SERIOUS_RECALL, $W_NEAR_MISS_RECALL, $W_PRECISION, $W_GRADED_RECALL, $W_RESOLUTION, $NOISE_TOLERANCE, $MINOR_NOISE_WEIGHT;

    if ($resPath === null) {
        $resPath = str_replace('predictions', 'resolution', $predPath);
    }
    if ($truthResPath === null) {
        $truthResPath = str_replace('_labels', '_resolution', $truthPath);
    }

    $truthRows = readCsvAssoc($truthPath);
    $tresRows = file_exists($truthResPath) ? readCsvAssoc($truthResPath) : [];
    $predRows = file_exists($predPath) ? readCsvAssoc($predPath) : [];
    $resRows = file_exists($resPath) ? readCsvAssoc($resPath) : [];

    // Scope patients
    $scope = [];
    foreach ($tresRows as $r) {
        $scope[strtoupper(trim($r['patient_id']))] = true;
    }
    if (empty($scope)) {
        foreach ($truthRows as $r) {
            $scope[strtoupper(trim($r['patient_id']))] = true;
        }
    }
    $nPat = count($scope);

    // Build truth map: key = PID|issue_type|drugs_sorted -> [severity, near_miss]
    $tmap = [];
    $seriousCount = 0;
    $nmCount = 0;
    $seriousKeys = [];
    $nmKeys = [];
    $nonminorKeys = [];

    foreach ($truthRows as $tr) {
        $pid = strtoupper(trim($tr['patient_id']));
        if (!isset($scope[$pid])) continue;

        $type = strtolower(trim($tr['issue_type']));
        $sev = strtolower(trim($tr['severity']));
        $drugsKey = normDrugsKey($tr['drugs_involved']);
        $nm = (int)($tr['near_miss'] ?? 0);
        $key = "{$pid}|{$type}|{$drugsKey}";

        $tmap[$key] = ['severity' => $sev, 'near_miss' => $nm];

        $isSerious = in_array($sev, ['contraindicated', 'major'], true);
        if ($isSerious) {
            $seriousCount++;
            $seriousKeys[$key] = true;
        }
        if ($nm === 1) {
            $nmCount++;
            $nmKeys[$key] = true;
        }
        if ($sev !== 'minor') {
            $nonminorKeys[$key] = $sev;
        }
    }

    // Process predictions
    $matchedKeys = [];
    $predSev = [];
    $alertsScored = 0;
    $alertsMatched = 0;
    $falseAlerts = 0;
    $minorAlerts = 0;

    foreach ($predRows as $pr) {
        $pid = strtoupper(trim($pr['patient_id']));
        if (!isset($scope[$pid])) continue;

        $type = strtolower(trim($pr['issue_type']));
        $sev = strtolower(trim($pr['severity']));
        $drugsKey = normDrugsKey($pr['drugs_involved']);
        $key = "{$pid}|{$type}|{$drugsKey}";

        $alertsScored++;
        $predSev[$key] = $sev;

        if (isset($tmap[$key])) {
            $alertsMatched++;
            $matchedKeys[$key] = true;
            if ($tmap[$key]['severity'] === 'minor') {
                $minorAlerts++;
            }
        } else {
            $falseAlerts++;
        }
    }

    // Calculate components
    $seriousMatched = 0;
    foreach (array_keys($seriousKeys) as $sk) {
        if (isset($matchedKeys[$sk])) $seriousMatched++;
    }
    $seriousRecall = ($seriousCount > 0) ? ($seriousMatched / $seriousCount) : 0.0;

    $nmMatched = 0;
    foreach (array_keys($nmKeys) as $nk) {
        if (isset($matchedKeys[$nk])) $nmMatched++;
    }
    $nmRecall = ($nmCount > 0) ? ($nmMatched / $nmCount) : 0.0;

    $precision = ($alertsScored > 0) ? ($alertsMatched / $alertsScored) : 0.0;

    $gradedMatched = 0;
    $nonminorTotal = count($nonminorKeys);
    foreach ($nonminorKeys as $nk => $trueSev) {
        if (isset($matchedKeys[$nk]) && ($predSev[$nk] ?? '') === $trueSev) {
            $gradedMatched++;
        }
    }
    $gradedRecall = ($nonminorTotal > 0) ? ($gradedMatched / $nonminorTotal) : 0.0;

    $noisePerPatient = ($falseAlerts + $MINOR_NOISE_WEIGHT * $minorAlerts) / max($nPat, 1);
    $fatigueMultiplier = max(0.0, 1.0 - $noisePerPatient / $NOISE_TOLERANCE);

    // Resolution accuracy
    $resMap = [];
    foreach ($resRows as $rr) {
        $pid = strtoupper(trim($rr['patient_id']));
        $tok = strtolower(trim($rr['written_token']));
        $resMap["{$pid}|{$tok}"] = normDrugsKey($rr['resolved_generic']);
    }

    $resCorrect = 0;
    $resTotal = count($tresRows);
    $misspelledCorrect = 0;
    $misspelledTotal = 0;

    foreach ($tresRows as $tr) {
        $pid = strtoupper(trim($tr['patient_id']));
        $tok = strtolower(trim($tr['written_token']));
        $trueGenKey = normDrugsKey($tr['resolved_generic']);
        $predGenKey = $resMap["{$pid}|{$tok}"] ?? null;

        $isCorrect = ($predGenKey !== null && $predGenKey === $trueGenKey);
        if ($isCorrect) $resCorrect++;

        $kind = $tr['token_kind'] ?? '';
        if (str_starts_with($kind, 'misspelled')) {
            $misspelledTotal++;
            if ($isCorrect) $misspelledCorrect++;
        }
    }

    $resolutionAcc = ($resTotal > 0) ? ($resCorrect / $resTotal) : 0.0;
    $resMisspelledAcc = ($misspelledTotal > 0) ? ($misspelledCorrect / $misspelledTotal) : 0.0;

    $base = ($W_SERIOUS_RECALL * $seriousRecall +
             $W_NEAR_MISS_RECALL * $nmRecall +
             $W_PRECISION * $precision +
             $W_GRADED_RECALL * $gradedRecall +
             $W_RESOLUTION * $resolutionAcc);

    $primaryScore = min(1.0, max(0.0, $base * $fatigueMultiplier));

    $report = [
        'primary_score' => round($primaryScore, 6),
        'base_score' => round($base, 6),
        'serious_recall' => round($seriousRecall, 6),
        'near_miss_recall' => round($nmRecall, 6),
        'precision' => round($precision, 6),
        'graded_recall' => round($gradedRecall, 6),
        'resolution_accuracy' => round($resolutionAcc, 6),
        'resolution_misspelled' => round($resMisspelledAcc, 6),
        'noise_per_patient' => round($noisePerPatient, 6),
        'fatigue_multiplier' => round($fatigueMultiplier, 6),
        'counts' => [
            'patients_scored' => $nPat,
            'true_serious' => $seriousCount,
            'serious_matched' => $seriousMatched,
            'true_near_miss' => $nmCount,
            'near_miss_matched' => $nmMatched,
            'alerts_scored' => $alertsScored,
            'alerts_matched' => $alertsMatched,
            'false_alerts' => $falseAlerts,
            'minor_alerts' => $minorAlerts,
            'tokens_scored' => $resTotal,
            'tokens_correct' => $resCorrect
        ]
    ];

    if ($outJson) {
        file_put_contents($outJson, json_encode($report, JSON_PRETTY_PRINT));
    }

    return $report;
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $opts = getopt('', ['submission:', 'truth:', 'submission-resolution:', 'truth-resolution:', 'out:']);
    $sub = $opts['submission'] ?? 'submission/H4_predictions.csv';
    $truth = $opts['truth'] ?? 'data/train_labels.csv';
    $subRes = $opts['submission-resolution'] ?? null;
    $truthRes = $opts['truth-resolution'] ?? null;
    $out = $opts['out'] ?? null;

    $rep = runEvaluation($sub, $truth, $subRes, $truthRes, $out);

    echo "==================================================================\n";
    echo "  H4 Medication Safety Checker - Official Evaluation (PHP)        \n";
    echo "==================================================================\n";
    echo "Patients scored            : {$rep['counts']['patients_scored']}\n";
    echo "True Serious Issues        : {$rep['counts']['true_serious']} (Matched: {$rep['counts']['serious_matched']})\n";
    echo "Near-miss Issues           : {$rep['counts']['true_near_miss']} (Matched: {$rep['counts']['near_miss_matched']})\n";
    echo "Alerts scored / matched    : {$rep['counts']['alerts_scored']} / {$rep['counts']['alerts_matched']}\n";
    echo "Serious-issue recall       : " . number_format($rep['serious_recall'], 4) . "   (weight 0.40)\n";
    echo "NEAR-MISS recall           : " . number_format($rep['near_miss_recall'], 4) . "   (weight 0.15)\n";
    echo "Precision (all alerts)     : " . number_format($rep['precision'], 4) . "   (weight 0.15)\n";
    echo "Graded recall (severity)   : " . number_format($rep['graded_recall'], 4) . "   (weight 0.15)\n";
    echo "Resolution accuracy        : " . number_format($rep['resolution_accuracy'], 4) . "   (weight 0.15)\n";
    echo "  ...on misspelled entries : " . number_format($rep['resolution_misspelled'], 4) . "\n";
    echo "Noise alerts per patient   : " . number_format($rep['noise_per_patient'], 4) . "\n";
    echo "Alert-fatigue multiplier   : " . number_format($rep['fatigue_multiplier'], 4) . "\n";
    echo "------------------------------------------------------------------\n";
    echo "PRIMARY SCORE              : " . number_format($rep['primary_score'], 4) . "   (0 to 1)\n";
    echo "==================================================================\n";
}

