<?php
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SCRIPT_NAME'] = '/medication/test.php';

$pages = [
    'index.php' => [],
    'medicines.php' => [],
    'patients.php' => [],
    'patient_view.php' => ['id' => 1],
    'attach_prescription.php' => ['patient_id' => 1],
];

foreach ($pages as $p => $params) {
    $_GET = $params;
    $_POST = [];
    ob_start();
    try {
        include __DIR__ . '/../' . $p;
        $out = ob_get_clean();
        echo "[OK] {$p} executed successfully (" . strlen($out) . " bytes)\n";
    } catch (Throwable $e) {
        ob_end_clean();
        echo "[ERROR] {$p}: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}

