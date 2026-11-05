<?php
$h = fopen(__DIR__ . '/../data/train_resolution.csv', 'r');
$hdr = fgetcsv($h);
$counts = [];
while (($r = fgetcsv($h)) !== false) {
    $kind = $r[3] ?? 'unknown';
    $counts[$kind] = ($counts[$kind] ?? 0) + 1;
}
fclose($h);
arsort($counts);
foreach ($counts as $k => $c) {
    echo str_pad($k, 25) . ": " . $c . "\n";
}

