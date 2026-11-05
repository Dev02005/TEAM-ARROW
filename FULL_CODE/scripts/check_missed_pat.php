<?php
$h = fopen(__DIR__ . '/../data/patients_train.csv', 'r');
$hdr = fgetcsv($h);
while (($r = fgetcsv($h)) !== false) {
    if ($r[0] === 'H4P-07UCF83P' || $r[0] === 'H4P-0JBD2VWD' || $r[0] === 'H4P-1ZYOI7V4' || $r[0] === 'H4P-5TCTJFNP') {
        echo "PID: {$r[0]}\n  Diagnoses: {$r[6]}\n  Allergies: {$r[7]}\n  Pregnant: {$r[5]}\n  Meds: {$r[8]}\n\n";
    }
}
fclose($h);

