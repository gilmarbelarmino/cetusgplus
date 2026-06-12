<?php
require_once 'config.php';
require_once 'auth.php';

$user = getCurrentUser();
if (!$user) {
    die("Acesso Negado.");
}

$user_id = $_GET['user_id'] ?? '';
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

$compId = getCurrentUserCompanyId();

// Fetch Employee
$stmt = $pdo->prepare("SELECT u.name, u.role, u.unit_id, rh.salary, rh.start_date, rh.use_transport, rh.transport_value 
                       FROM users u 
                       LEFT JOIN rh_employee_details rh ON u.id = rh.user_id 
                       WHERE u.id = ? AND u.company_id = ?");
$stmt->execute([$user_id, $compId]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$emp) die("Funcionário não encontrado.");

// Fetch Company
$stmtComp = $pdo->prepare("SELECT company_name FROM company_settings WHERE company_id = ?");
$stmtComp->execute([$compId]);
$comp = $stmtComp->fetch(PDO::FETCH_ASSOC);
$companyName = $comp ? $comp['company_name'] : 'Empresa';

// Mês em extenso
$meses = ["01"=>"Janeiro", "02"=>"Fevereiro", "03"=>"Março", "04"=>"Abril", "05"=>"Maio", "06"=>"Junho", "07"=>"Julho", "08"=>"Agosto", "09"=>"Setembro", "10"=>"Outubro", "11"=>"Novembro", "12"=>"Dezembro"];
$mesExtenso = $meses[str_pad($month, 2, '0', STR_PAD_LEFT)];

// Cálculos
$base_salary = (float)$emp['salary'];
$items = [];

// 1. Salário Base
$items[] = [
    'code' => '001',
    'desc' => 'Salário Base',
    'ref'  => '30 dias',
    'vencimento' => $base_salary,
    'desconto' => 0
];

// 2. INSS Calc (2024 Tabela)
$inss = 0;
if ($base_salary <= 1412.00) {
    $inss = $base_salary * 0.075;
} else if ($base_salary <= 2666.68) {
    $inss = (1412.00 * 0.075) + (($base_salary - 1412.00) * 0.09);
} else if ($base_salary <= 4000.03) {
    $inss = (1412.00 * 0.075) + ((2666.68 - 1412.00) * 0.09) + (($base_salary - 2666.68) * 0.12);
} else if ($base_salary <= 7786.02) {
    $inss = (1412.00 * 0.075) + ((2666.68 - 1412.00) * 0.09) + ((4000.03 - 2666.68) * 0.12) + (($base_salary - 4000.03) * 0.14);
} else {
    $inss = 908.85; // Teto
}

if ($inss > 0) {
    $items[] = [
        'code' => '101',
        'desc' => 'INSS',
        'ref'  => '',
        'vencimento' => 0,
        'desconto' => $inss
    ];
}

// 3. Vale Transporte
$vt = 0;
if ($emp['use_transport'] == 'Sim') {
    $max_discount = $base_salary * 0.06;
    $vt = min($max_discount, (float)$emp['transport_value']);
    if ($vt > 0) {
        $items[] = [
            'code' => '102',
            'desc' => 'Vale Transporte',
            'ref'  => '6%',
            'vencimento' => 0,
            'desconto' => $vt
        ];
    }
}

// 4. IRRF Calc
$base_irrf = $base_salary - $inss;
$irrf = 0;
if ($base_irrf > 2259.20) {
    if ($base_irrf <= 2826.65) {
        $irrf = ($base_irrf * 0.075) - 169.44;
    } else if ($base_irrf <= 3751.05) {
        $irrf = ($base_irrf * 0.15) - 381.44;
    } else if ($base_irrf <= 4664.68) {
        $irrf = ($base_irrf * 0.225) - 662.77;
    } else {
        $irrf = ($base_irrf * 0.275) - 896.00;
    }
    if ($irrf < 0) $irrf = 0;
}

if ($irrf > 0) {
    $items[] = [
        'code' => '103',
        'desc' => 'IRRF',
        'ref'  => '',
        'vencimento' => 0,
        'desconto' => $irrf
    ];
}

$fgts = $base_salary * 0.08;

$total_vencimentos = 0;
$total_descontos = 0;
foreach($items as $i) {
    $total_vencimentos += $i['vencimento'];
    $total_descontos += $i['desconto'];
}
$liquido = $total_vencimentos - $total_descontos;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Holerite - <?= htmlspecialchars($emp['name']) ?> (<?= $month ?>/<?= $year ?>)</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #e2e8f0;
            padding: 2rem;
            margin: 0;
            font-size: 12px;
        }
        .holerite-container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 0;
            border: 1px solid #000;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td, th {
            border: 1px solid #000;
            padding: 4px 8px;
            text-align: left;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bg-light { background-color: #f1f5f9; }
        
        .header-section {
            display: flex;
            border-bottom: 1px solid #000;
        }
        .header-left {
            width: 70%;
            padding: 10px;
            border-right: 1px solid #000;
        }
        .header-right {
            width: 30%;
            padding: 10px;
            text-align: center;
        }
        
        .emp-section {
            display: flex;
            border-bottom: 1px solid #000;
        }
        .emp-col {
            padding: 5px;
            border-right: 1px solid #000;
        }
        
        .main-table {
            border: none;
            height: 350px;
        }
        .main-table td {
            border: none;
            border-left: 1px solid #000;
            border-right: 1px solid #000;
            vertical-align: top;
        }
        .main-table th {
            border-top: none;
        }
        
        .totals-section td {
            border: 1px solid #000;
        }
        
        .footer-bases td {
            font-size: 10px;
            border: 1px solid #000;
            text-align: center;
        }
        
        .signature-section {
            padding: 20px 10px;
            text-align: center;
            font-size: 11px;
        }
        
        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none !important; }
            .holerite-container { border: 2px solid #000; width: 100%; }
        }
    </style>
</head>
<body onload="window.print()">

    <div class="no-print" style="text-align:center; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer; background:#3b82f6; color:#fff; border:none; border-radius:5px;">Imprimir Holerite</button>
    </div>

    <div class="holerite-container">
        
        <!-- HEADER -->
        <div class="header-section">
            <div class="header-left">
                <h3 style="margin: 0; font-size: 16px;"><?= htmlspecialchars($companyName) ?></h3>
                <!-- CNPJ pode vir aqui futuramente -->
            </div>
            <div class="header-right">
                <h2 style="margin: 0; font-size: 16px;">RECIBO DE PAGAMENTO DE SALÁRIO</h2>
                <p style="margin: 5px 0 0 0;"><strong>Referência:</strong> <?= str_pad($month, 2, '0', STR_PAD_LEFT) ?>/<?= $year ?></p>
            </div>
        </div>
        
        <!-- FUNCIONARIO INFO -->
        <table style="border: none;">
            <tr>
                <td width="10%"><strong>Cód.</strong><br><?= htmlspecialchars($user_id) ?></td>
                <td width="50%"><strong>Nome do Funcionário</strong><br><?= htmlspecialchars($emp['name']) ?></td>
                <td width="20%"><strong>CBO / Cargo</strong><br><?= htmlspecialchars($emp['role'] ?? 'Colaborador') ?></td>
                <td width="20%"><strong>Admissão</strong><br><?= $emp['start_date'] ? date('d/m/Y', strtotime($emp['start_date'])) : '--' ?></td>
            </tr>
        </table>
        
        <!-- ITENS -->
        <table class="main-table">
            <thead>
                <tr class="bg-light">
                    <th width="10%" class="text-center">Cód.</th>
                    <th width="50%">Descrição</th>
                    <th width="10%" class="text-center">Ref.</th>
                    <th width="15%" class="text-right">Vencimentos</th>
                    <th width="15%" class="text-right">Descontos</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($items as $i): ?>
                <tr>
                    <td class="text-center"><?= $i['code'] ?></td>
                    <td><?= $i['desc'] ?></td>
                    <td class="text-center"><?= $i['ref'] ?></td>
                    <td class="text-right"><?= $i['vencimento'] > 0 ? number_format($i['vencimento'], 2, ',', '.') : '' ?></td>
                    <td class="text-right"><?= $i['desconto'] > 0 ? number_format($i['desconto'], 2, ',', '.') : '' ?></td>
                </tr>
                <?php endforeach; ?>
                <!-- Preencher espaço em branco -->
                <?php for($x=0; $x<10; $x++): ?>
                <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>
                <?php endfor; ?>
            </tbody>
        </table>
        
        <!-- TOTAIS -->
        <table class="totals-section">
            <tr>
                <td width="70%" rowspan="2" style="border:none; border-right: 1px solid #000; vertical-align:top; padding:10px;">
                    <strong>Mensagem:</strong><br>
                    Pagamento referente ao mês de <?= $mesExtenso ?> de <?= $year ?>.
                </td>
                <td width="15%" class="text-right bg-light"><strong>Total Vencimentos</strong></td>
                <td width="15%" class="text-right bg-light"><strong>Total Descontos</strong></td>
            </tr>
            <tr>
                <td class="text-right"><?= number_format($total_vencimentos, 2, ',', '.') ?></td>
                <td class="text-right"><?= number_format($total_descontos, 2, ',', '.') ?></td>
            </tr>
            <tr>
                <td style="border:none; border-right:1px solid #000;"></td>
                <td class="text-right bg-light" style="font-size:14px;"><strong>Líquido a Receber</strong></td>
                <td class="text-right" style="font-size:14px; font-weight:bold;"><span>R$ </span> <?= number_format($liquido, 2, ',', '.') ?></td>
            </tr>
        </table>
        
        <!-- BASES -->
        <table class="footer-bases">
            <tr class="bg-light">
                <td width="20%">Salário Base</td>
                <td width="20%">Base Cálc. INSS</td>
                <td width="20%">Base Cálc. FGTS</td>
                <td width="20%">FGTS do Mês</td>
                <td width="20%">Base Cálc. IRRF</td>
            </tr>
            <tr>
                <td><?= number_format($base_salary, 2, ',', '.') ?></td>
                <td><?= number_format($base_salary, 2, ',', '.') ?></td>
                <td><?= number_format($base_salary, 2, ',', '.') ?></td>
                <td><?= number_format($fgts, 2, ',', '.') ?></td>
                <td><?= number_format($base_irrf, 2, ',', '.') ?></td>
            </tr>
        </table>
        
        <!-- ASSINATURA -->
        <div class="signature-section">
            <p style="text-align: justify; margin-bottom: 30px;">
                Declaro ter recebido a importância líquida discriminada neste recibo, 
                referente ao salário do mês de <strong><?= $mesExtenso ?>/<?= $year ?></strong>.
            </p>
            <div style="display:flex; justify-content:space-between; align-items:flex-end;">
                <div style="width: 30%; border-bottom: 1px solid #000; text-align:center;">
                    Data: ___/___/______
                </div>
                <div style="width: 60%; border-bottom: 1px solid #000; text-align:center;">
                    Assinatura do Funcionário
                </div>
            </div>
        </div>

    </div>

</body>
</html>
