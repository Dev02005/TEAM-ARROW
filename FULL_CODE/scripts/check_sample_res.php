<?php
$h = fopen(__DIR__ . '/../data/train_resolution.csv', 'r');
$hdr = fgetcsv($h);
for ($i = 0; $i < 30; $i++) {
    $r = fgetcsv($h);
    if (!$r) break;
    echo "Written: " . str_pad($r[1], 35) . " -> Resolved: " . str_pad($r[2], 25) . " Kind: " . $r[3] . "\n";
}
fclose($h);

