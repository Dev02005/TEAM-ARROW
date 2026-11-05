<?php
$h = fopen(__DIR__ . '/../data/patients_train.csv', 'r');
$hdr = fgetcsv($h);
while (($r = fgetcsv($h)) !== false) {
    if ($r[0] === 'H4P-4CMFGCCG' || $r[0] === 'H4P-4F6IGCUA' || $r[0] === 'H4P-55QDKSDE') {
        echo "PID: {$r[0]}\n  Meds: {$r[8]}\n\n";
    }
}
fclose($h);

