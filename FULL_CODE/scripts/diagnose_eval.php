<?php
require_once __DIR__ . '/../pipeline/evaluate.php';

$truthRows = readCsvAssoc(__DIR__ . '/../data/train_labels.csv');
$predRows = readCsvAssoc(__DIR__ . '/../submission_train/H4_predictions.csv');

$predMap = [];
foreach ($predRows as $p) {
    $pid = strtoupper(trim($p['patient_id']));
    $type = strtolower(trim($p['issue_type']));
    $drugs = normDrugsKey($p['drugs_involved']);
    $predMap["{$pid}|{$type}|{$drugs}"] = $p;
}

$missedSerious = [];
$missedTypes = [];
foreach ($truthRows as $t) {
    $pid = strtoupper(trim($t['patient_id']));
    $type = strtolower(trim($t['issue_type']));
    $drugs = normDrugsKey($t['drugs_involved']);
    $sev = strtolower(trim($t['severity']));
    $nm = (int)($t['near_miss'] ?? 0);
    $key = "{$pid}|{$type}|{$drugs}";

    if (!isset($predMap[$key])) {
        $missedTypes[$type] = ($missedTypes[$type] ?? 0) + 1;
        if (in_array($sev, ['contraindicated', 'major'], true)) {
            if (count($missedSerious) < 20) {
                $missedSerious[] = [
                    'pid' => $pid,
                    'type' => $type,
                    'drugs' => $drugs,
                    'sev' => $sev,
                    'nm' => $nm,
                    'action' => $t['suggested_action']
                ];
            }
        }
    }
}

echo "Missed issues by type:\n";
foreach ($missedTypes as $type => $cnt) {
    echo "  {$type}: {$cnt}\n";
}

echo "\nSample missed serious issues:\n";
foreach ($missedSerious as $ms) {
    echo "  [{$ms['type']} | {$ms['sev']} | NM:{$ms['nm']}] {$ms['pid']} : {$ms['drugs']} -- Action: {$ms['action']}\n";
}

