<?php
$pdo = new PDO('mysql:host=localhost;dbname=cetusg_plus', 'root', '');
// Encontrar duplicados por nome na mesma empresa
$stmt = $pdo->prepare('SELECT name, company_id, COUNT(*) as count FROM assets GROUP BY name, company_id HAVING count > 1');
$stmt->execute();
$duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$details = [];
foreach ($duplicates as $d) {
    $stmt2 = $pdo->prepare('SELECT id, name, created_at, company_id, responsible_name FROM assets WHERE name = ? AND company_id = ? ORDER BY created_at');
    $stmt2->execute([$d['name'], $d['company_id']]);
    $details[$d['name']] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode(['summary' => $duplicates, 'details' => $details], JSON_PRETTY_PRINT);
?>
