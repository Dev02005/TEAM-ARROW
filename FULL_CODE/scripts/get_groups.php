<?php
require_once __DIR__ . '/../config/database.php';
$pdo = new PDO(
    sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET),
    DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$stmt = $pdo->query('SELECT therapeutic_group, count(*) as c FROM medicines GROUP BY therapeutic_group ORDER BY c DESC');
while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $r['therapeutic_group'] . " (" . $r['c'] . ")\n";
}

