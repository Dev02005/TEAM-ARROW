<?php
require_once __DIR__ . '/../config/database.php';
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET),
    DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$tables = [
    'medicines', 'combination_brands', 'drug_interactions', 'renal_rules',
    'duplicate_therapy_groups', 'drug_disease_contraindications', 'conditions',
    'patients', 'patient_medications', 'medicine_aliases', 'users'
];
foreach ($tables as $t) {
    $c = $pdo->query("SELECT count(*) FROM `{$t}`")->fetchColumn();
    echo str_pad($t, 32) . ": " . $c . " rows\n";
}

