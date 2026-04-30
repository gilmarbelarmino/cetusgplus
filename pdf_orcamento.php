<?php
require_once 'config.php';
require_once 'auth.php';

if (!isset($_GET['id'])) {
    die('ID do orçamento não fornecido');
}

$compId = getCurrentUserCompanyId();

// Restaurado BINARY para evitar erro de collation (mix of utf8mb4_general_ci and utf8mb4_unicode_ci)
$budget_stmt = $pdo->prepare("SELECT br.*, u.name as unit_name, us.name as requester_name, ap.name as approver_name FROM budget_requests br LEFT JOIN units u ON BINARY br.unit_id = BINARY u.id LEFT JOIN users us ON BINARY br.requester_id = BINARY us.id LEFT JOIN users ap ON BINARY br.approved_by = BINARY ap.id WHERE br.id = ? AND br.company_id = ?");
$budget_stmt->execute([$_GET['id'], $compId]);
$budget = $budget_stmt->fetch();

if (!$budget) {
    die('Orçamento não encontrado ou acesso negado');
}

$quotes_stmt = $pdo->prepare("SELECT * FROM budget_quotes WHERE budget_id = ? AND company_id = ? ORDER BY total ASC");
$quotes_stmt->execute([$_GET['id'], $compId]);
$quotes = $quotes_stmt->fetchAll();

$company_stmt = $pdo->prepare("SELECT * FROM company_settings WHERE id = ?");
$company_stmt->execute([$compId]);
$company = $company_stmt->fetch() ?: ['company_name' => 'Cetusg Plus', 'logo_url' => ''];

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Orçamento - <?= htmlspecialchars($budget['product_name']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #5B21B6; padding-bottom: 20px; }
        .header h1 { color: #5B21B6; margin: 0; }
        .info-box { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .label { font-weight: bold; color: #64748b; }
        .value { color: #000; }
        .quotes { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 30px; }
        .quote-card { border: 2px solid #5B21B6; border-radius: 8px; padding: 15px; }
        .quote-card.best { border-color: #10B981; background: #f0fdf4; }
        .quote-title { font-weight: bold; color: #5B21B6; text-align: center; margin-bottom: 15px; font-size: 18px; }
        .quote-row { display: flex; justify-content: space-between; margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #e5e7eb; }
        .quote-total { font-size: 20px; font-weight: bold; color: #5B21B6; text-align: center; margin-top: 15px; padding-top: 15px; border-top: 2px solid #5B21B6; }
        .status { padding: 8px 16px; border-radius: 20px; display: inline-block; font-weight: bold; margin-top: 10px; }
        .status.aprovado { background: #10B981; color: white; }
        .status.rejeitado { background: #EF4444; color: white; }
        .status.pendente { background: #FBBF24; color: white; }
        .footer { margin-top: 40px; text-align: center; color: #64748b; font-size: 12px; border-top: 1px solid #e5e7eb; padding-top: 20px; }
        @media print {
            body { margin: 20px; }
            .no-print { display: none; }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
</head>
<body>
    <div class="header">
        <?php if (!empty($company['logo_url'])): ?>
            <img src="<?= htmlspecialchars($company['logo_url']) ?>" style="max-height: 80px; margin-bottom: 10px;">
        <?php else: ?>
            <h2 style="color: #5B21B6; margin: 0;"><?= htmlspecialchars($company['company_name']) ?></h2>
        <?php endif; ?>
        <h1 style="margin-top: 10px;">ORÇAMENTO DE COMPRA</h1>
        <p style="color: #64748b; margin: 5px 0 0 0;">ID: <?= htmlspecialchars($budget['id']) ?></p>
    </div>

    <div class="info-box">
        <div class="info-row">
            <span class="label">Produto:</span>
            <span class="value" style="font-size: 1.1rem; font-weight: bold;"><?= htmlspecialchars($budget['product_name']) ?></span>
        </div>
        <div class="info-row">
            <span class="label">Quantidade:</span>
            <span class="value"><?= $budget['quantity'] ?> unidades</span>
        </div>
        <div style="margin: 15px 0; border-top: 1px solid #e5e7eb; padding-top: 15px;">
            <div class="info-row">
                <span class="label">Solicitante:</span>
                <span class="value"><?= htmlspecialchars($budget['requester_name']) ?></span>
            </div>
            <div class="info-row">
                <span class="label">Setor / Unidade:</span>
                <span class="value"><?= htmlspecialchars($budget['sector']) ?> - <?= htmlspecialchars($budget['unit_name']) ?></span>
            </div>
            <div class="info-row">
                <span class="label">Data de Solicitação:</span>
                <span class="value"><?= date('d/m/Y H:i', strtotime($budget['created_at'])) ?></span>
            </div>
        </div>
        
        <div class="info-row">
            <span class="label">Status Atual:</span>
            <span class="status <?= strtolower($budget['status']) ?>">
                <?= $budget['status'] == 'Rejeitado' ? 'REPROVADO' : strtoupper($budget['status']) ?>
            </span>
        </div>
        
        <?php if ($budget['status'] == 'Aprovado'): ?>
        <div style="margin-top: 15px; padding: 15px; background: rgba(16, 185, 129, 0.1); border-radius: 8px; border-left: 5px solid #10B981;">
            <div class="info-row">
                <span class="label" style="color: #065F46;">Aprovado por:</span>
                <span class="value" style="font-weight: bold;"><?= htmlspecialchars($budget['approver_name']) ?></span>
            </div>
            <div class="info-row" style="margin-top: 5px;">
                <span class="label" style="color: #065F46;">Data e Hora da Aprovação:</span>
                <span class="value"><?= date('d/m/Y H:i:s', strtotime($budget['approved_at'])) ?></span>
            </div>
        </div>
        <?php elseif ($budget['status'] == 'Rejeitado'): ?>
        <div style="margin-top: 15px; padding: 15px; background: rgba(239, 68, 68, 0.1); border-radius: 8px; border-left: 5px solid #EF4444;">
            <div class="info-row">
                <span class="label" style="color: #991B1B;">Reprovado por:</span>
                <span class="value" style="font-weight: bold;"><?= htmlspecialchars($budget['approver_name']) ?></span>
            </div>
            <div class="info-row" style="margin-top: 5px;">
                <span class="label" style="color: #991B1B;">Data e Hora da Reprovação:</span>
                <span class="value"><?= date('d/m/Y H:i:s', strtotime($budget['rejected_at'])) ?></span>
            </div>
            <div class="info-row" style="margin-top: 5px;">
                <span class="label" style="color: #991B1B;">Motivo:</span>
                <span class="value" style="color: #B91C1C; font-weight: bold;"><?= htmlspecialchars($budget['rejection_reason']) ?></span>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if (isset($quotes) && count($quotes) > 0): ?>
    <h2 style="color: #5B21B6; margin-top: 30px;">Cotações</h2>
    <div class="quotes">
        <?php foreach ($quotes as $idx => $q): ?>
        <div class="quote-card <?= $idx == 0 ? 'best' : '' ?>">
            <div class="quote-title">
                Cotação <?= $idx + 1 ?>
                <?= $idx == 0 ? '<br><span style="color: #10B981; font-size: 14px;">★ MELHOR PREÇO ★</span>' : '' ?>
            </div>
            
            <div class="quote-row">
                <span>Valor Unitário:</span>
                <span>R$ <?= number_format($q['price'], 2, ',', '.') ?></span>
            </div>
            
            <div class="quote-row">
                <span>Quantidade:</span>
                <span><?= $budget['quantity'] ?> un</span>
            </div>
            
            <div class="quote-row">
                <span>Subtotal Produtos:</span>
                <span>R$ <?= number_format($q['price'] * $budget['quantity'], 2, ',', '.') ?></span>
            </div>
            
            <div class="quote-row">
                <span>Valor do Frete:</span>
                <span>R$ <?= number_format($q['shipping_cost'], 2, ',', '.') ?></span>
            </div>
            
            <div class="quote-total">
                TOTAL: R$ <?= number_format($q['total'], 2, ',', '.') ?>
            </div>
            
            <?php if ($q['link']): ?>
            <div style="margin-top: 10px; font-size: 12px; word-break: break-all;">
                <strong>Link:</strong><br>
                <a href="<?= htmlspecialchars($q['link']) ?>"><?= htmlspecialchars($q['link']) ?></a>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="footer">
        <p>Documento gerado em <?= date('d/m/Y H:i:s') ?></p>
        <p><?= htmlspecialchars($company['company_name']) ?></p>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 30px;">
        <button onclick="window.print()" style="background: #5B21B6; color: white; padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: bold;">
            Imprimir / Salvar PDF
        </button>
        <button onclick="window.close()" style="background: #64748b; color: white; padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: bold; margin-left: 10px;">
            Fechar
        </button>
    </div>
</body>
</html>
