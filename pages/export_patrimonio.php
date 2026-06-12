<?php
session_start();
require_once '../config.php';
require_once '../auth.php'; // Ensure user is logged in
require_once '../SimpleXLSXGen.php';

if (!isset($_SESSION['user_id'])) {
    die("Acesso negado.");
}

$compId = $_SESSION['company_id'] ?? 1;

// Fetch all assets
$stmt = $pdo->prepare("SELECT * FROM assets WHERE company_id = ? ORDER BY sector ASC, name ASC");
$stmt->execute([$compId]);
$assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

$xlsx = new SimpleXLSXGen();

// Data arrays for calculations
$sectorsData = [];
$totalValues = 0;
$totalQty = count($assets);
$responsibleStats = [];
$categoryStats = [];

// Group by sector
foreach ($assets as $asset) {
    $sec = !empty($asset['sector']) ? $asset['sector'] : 'Sem Setor';
    $val = floatval($asset['estimated_value'] ?? 0);
    $resp = !empty($asset['responsible_name']) ? $asset['responsible_name'] : 'Sem Responsável';
    $cat = !empty($asset['category']) ? $asset['category'] : 'Sem Categoria';
    
    // Sector data
    if (!isset($sectorsData[$sec])) {
        $sectorsData[$sec] = [
            'assets' => [],
            'total_qty' => 0,
            'total_value' => 0
        ];
    }
    $sectorsData[$sec]['assets'][] = $asset;
    $sectorsData[$sec]['total_qty']++;
    $sectorsData[$sec]['total_value'] += $val;
    
    // Global stats
    $totalValues += $val;
    
    // Responsible stats
    if (!isset($responsibleStats[$resp])) {
        $responsibleStats[$resp] = ['qty' => 0, 'val' => 0];
    }
    $responsibleStats[$resp]['qty']++;
    $responsibleStats[$resp]['val'] += $val;
    
    // Category stats
    if (!isset($categoryStats[$cat])) {
        $categoryStats[$cat] = ['qty' => 0, 'val' => 0];
    }
    $categoryStats[$cat]['qty']++;
    $categoryStats[$cat]['val'] += $val;
}

// Add tabs for each sector
foreach ($sectorsData as $secName => $secData) {
    // Excel sheet names max 31 chars and no invalid chars like *?:/\
    $sheetName = substr(str_replace(['*', '?', ':', '/', '\\'], '_', $secName), 0, 31);
    
    $sheetContent = [
        ['ID Patrimônio', 'Nome', 'Categoria', 'Responsável', 'Status', 'Valor Estimado']
    ];
    
    foreach ($secData['assets'] as $a) {
        $sheetContent[] = [
            $a['patrimony_id'] ?? '',
            $a['name'] ?? '',
            $a['category'] ?? '',
            $a['responsible_name'] ?? '',
            $a['status'] ?? '',
            'R$ ' . number_format(floatval($a['estimated_value'] ?? 0), 2, ',', '.')
        ];
    }
    
    $sheetContent[] = [];
    // Add totals row for the sector
    $sheetContent[] = ['', '', '', '', 'TOTAL DO SETOR:', 'R$ ' . number_format($secData['total_value'], 2, ',', '.')];
    $sheetContent[] = ['', '', '', '', 'QUANTIDADE:', $secData['total_qty']];
    
    $xlsx->addSheet($sheetContent, $sheetName);
}

// Final Result Tab
$finalData = [];
$finalData[] = ['RESULTADO FINAL'];
$finalData[] = [];
$finalData[] = ['RESUMO GERAL', ''];
$finalData[] = ['Quantidade Total de Equipamentos', $totalQty];
$finalData[] = ['Valor Total Geral', 'R$ ' . number_format($totalValues, 2, ',', '.')];
$finalData[] = ['Total de Setores', count($sectorsData)];
$finalData[] = [];

// Resumo por Setores
$finalData[] = ['RESUMO POR SETOR', 'QUANTIDADE', '% DO TOTAL', 'VALOR TOTAL'];
// Sort by quantity desc
uasort($sectorsData, function($a, $b) {
    return $b['total_qty'] <=> $a['total_qty'];
});
foreach ($sectorsData as $secName => $secData) {
    $pct = $totalQty > 0 ? ($secData['total_qty'] / $totalQty) * 100 : 0;
    $finalData[] = [
        $secName,
        $secData['total_qty'],
        number_format($pct, 2, ',', '.') . ' %',
        'R$ ' . number_format($secData['total_value'], 2, ',', '.')
    ];
}
$finalData[] = [];

// Resumo por Responsável
$finalData[] = ['ACÚMULO POR RESPONSÁVEL', 'QUANTIDADE', 'VALOR TOTAL'];
uasort($responsibleStats, function($a, $b) {
    return $b['qty'] <=> $a['qty'];
});
foreach ($responsibleStats as $respName => $rData) {
    $finalData[] = [
        $respName,
        $rData['qty'],
        'R$ ' . number_format($rData['val'], 2, ',', '.')
    ];
}
$finalData[] = [];

// Resumo por Categoria
$finalData[] = ['RESUMO POR CATEGORIA', 'QUANTIDADE', 'VALOR TOTAL'];
uasort($categoryStats, function($a, $b) {
    return $b['qty'] <=> $a['qty'];
});
foreach ($categoryStats as $catName => $cData) {
    $finalData[] = [
        $catName,
        $cData['qty'],
        'R$ ' . number_format($cData['val'], 2, ',', '.')
    ];
}

$xlsx->addSheet($finalData, 'Resultado Final');

// Export to output
$tmpFile = tempnam(sys_get_temp_dir(), 'pat_export');
$xlsx->saveAs($tmpFile);

if(file_exists($tmpFile)) {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="Patrimonio_Report_'.date('Ymd_Hi').'.xlsx"');
    header('Content-Length: ' . filesize($tmpFile));
    header('Cache-Control: max-age=0');
    
    readfile($tmpFile);
    unlink($tmpFile);
} else {
    echo "Erro ao gerar arquivo XLSX.";
}
exit;
?>
