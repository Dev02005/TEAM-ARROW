<?php
$h = fopen(__DIR__ . '/../data/conditions.csv', 'r');
$hdr = fgetcsv($h);
while (($r = fgetcsv($h)) !== false) {
    echo "ID: " . str_pad($r[0], 25) . " | Kind: " . str_pad($r[2], 12) . " | Terms: {$r[4]}\n";
}
fclose($h);

