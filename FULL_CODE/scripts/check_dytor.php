<?php
$h = fopen(__DIR__ . '/../data/drug_reference.csv', 'r');
$hdr = fgetcsv($h);
while (($r = fgetcsv($h)) !== false) {
    if (str_contains(strtolower($r[5]), 'dytor')) {
        echo "Drug: {$r[1]}, Brands: {$r[5]}\n";
    }
}
fclose($h);

