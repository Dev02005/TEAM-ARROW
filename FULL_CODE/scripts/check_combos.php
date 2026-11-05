<?php
$h = fopen(__DIR__ . '/../data/combination_brands.csv', 'r');
$hdr = fgetcsv($h);
for ($i = 0; $i < 20; $i++) {
    $r = fgetcsv($h);
    if (!$r) break;
    echo "Brand: " . str_pad($r[0], 25) . " Strength: " . str_pad($r[1], 10) . " Generics: " . $r[4] . "\n";
}
fclose($h);

