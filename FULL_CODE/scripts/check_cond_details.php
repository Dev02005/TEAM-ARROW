<?php
$h = fopen(__DIR__ . '/../data/conditions.csv', 'r');
$hdr = fgetcsv($h);
while (($r = fgetcsv($h)) !== false) {
    if ($r[2] === 'allergy' || str_contains($r[0], 'heart') || str_contains($r[0], 'fail') || str_contains($r[0], 'peptic') || str_contains($r[0], 'cirrhosis')) {
        echo "ID: {$r[0]} | Name: {$r[1]} | Kind: {$r[2]} | Terms: {$r[4]}\n";
    }
}
fclose($h);

