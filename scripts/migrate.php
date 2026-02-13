<?php
declare(strict_types=1);

$dbPath = __DIR__ . '/../data/app.db';
$sqlPath = __DIR__ . '/../migrations/001_init.sql';

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$sql = file_get_contents($sqlPath);
$pdo->exec($sql);

echo "Migration applied\n";
