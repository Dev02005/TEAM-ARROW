<?php
require_once __DIR__ . '/../config/database.php';
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET),
    DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$stmt = $pdo->query('SHOW COLUMNS FROM patients');
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $r['Field'] . " (" . $r['Type'] . ") Null:" . $r['Null'] . " Default:" . var_export($r['Default'], true) . "\n";
}

