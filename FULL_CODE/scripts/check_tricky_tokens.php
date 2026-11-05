<?php
$h = fopen(__DIR__ . '/../data/train_resolution.csv', 'r');
$hdr = fgetcsv($h);
$examples = [];
while (($r = fgetcsv($h)) !== false) {
    $kind = $r[3];
    if (in_array($kind, ['abbreviation', 'synonym', 'misspelled_truncated', 'non_drug'], true)) {
        if (!isset($examples[$kind])) $examples[$kind] = [];
        if (count($examples[$kind]) < 8) {
            $examples[$kind][] = $r[1] . "  ===>  " . $r[2];
        }
    }
}
fclose($h);
foreach ($examples as $k => $list) {
    echo "=== {$k} ===\n";
    foreach ($list as $item) echo "  " . $item . "\n";
}

