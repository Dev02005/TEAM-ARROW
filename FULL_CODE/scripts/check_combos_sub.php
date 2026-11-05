<?php
$h = fopen(__DIR__ . '/../data/combination_brands.csv', 'r');
$hdr = fgetcsv($h);
while (($r = fgetcsv($h)) !== false) {
    $b = strtolower($r[0]);
    if (str_contains($b, 'dytor') || str_contains($b, 'veltam') || str_contains($b, 'anlopres') || str_contains($b, 'glemestar') || str_contains($b, 'insulin')) {
        echo "{$r[0]} ({$r[1]}) -> {$r[4]}\n";
    }
}
fclose($h);

