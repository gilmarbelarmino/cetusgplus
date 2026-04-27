<?php
// Migração segura
try { $pdo->exec("ALTER TABLE assets ADD COLUMN estimated_value DECIMAL(12,2) DEFAULT 0"); } catch(Exception $e) {}

// === DADOS EMPRESA E USUÁRIO (PARA IMPRESSÃO) ===
$company = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch() ?: ['company_name' => 'Cetusg Plus', 'logo_url' => ''];

// === DADOS VISÃO GERAL ===
$totalAssets = $pdo->query("SELECT COUNT(*) as total FROM assets")->fetch()['total'];
$totalTickets = $pdo->query("SELECT COUNT(*) as total FROM tickets")->fetch()['total'];
$totalUsers = $pdo->query("SELECT COUNT(*) as total FROM users")->fetch()['total'];
$totalUnits = $pdo->query("SELECT COUNT(*) as total FROM units")->fetch()['total'];
$totalLoans = $pdo->query("SELECT COUNT(*) FROM loans")->fetchColumn();
$totalVolunteers = $pdo->query("SELECT COUNT(*) as total FROM volunteers")->fetch()['total'];

// === DADOS CHAMADOS ===
// Agregação robusta de status
$chRaw = $pdo->query("SELECT CASE WHEN status IS NULL OR TRIM(status)='' THEN 'Concluído' ELSE status END as status, COUNT(*) as count FROM tickets GROUP BY status")->fetchAll();
$chAllStatus = [];
foreach ($chRaw as $row) {
    $s = trim($row['status']);
    $c = (int)$row['count'];
    if (empty($s)) $s = 'Concluído';
    $chAllStatus[$s] = ($chAllStatus[$s] ?? 0) + $c;
}

$totalChamados = array_sum($chAllStatus) ?: 0;
$chAbertos     = $chAllStatus['Aberto'] ?? 0;
$chPendentes   = ($chAllStatus['Pendente'] ?? 0) + ($chAllStatus['Pendência'] ?? 0) + ($chAllStatus['Pendenciado'] ?? 0);
$chSolucionados= ($chAllStatus['Concluído'] ?? 0) + ($chAllStatus['Concluido'] ?? 0) + ($chAllStatus['Solucionado'] ?? 0) + ($chAllStatus['Solucionados'] ?? 0) + ($chAllStatus['Finalizado'] ?? 0) + ($chAllStatus['Fechado'] ?? 0);
$chSemSolucao  = ($chAllStatus['Sem Solução'] ?? 0) + ($chAllStatus['Sem Solucao'] ?? 0);


$chBySector = $pdo->query("SELECT sector, COUNT(*) as count FROM tickets GROUP BY sector ORDER BY count DESC")->fetchAll();

$chCurrentYear = array_fill(1, 12, 0);
$chCyQuery = $pdo->query("SELECT MONTH(created_at) as month, COUNT(*) as count FROM tickets WHERE YEAR(created_at) = YEAR(CURDATE()) GROUP BY MONTH(created_at)")->fetchAll();
foreach ($chCyQuery as $r) { $chCurrentYear[intval($r['month'])] = intval($r['count']); }

$chLastYear = array_fill(1, 12, 0);
$chLyQuery = $pdo->query("SELECT MONTH(created_at) as month, COUNT(*) as count FROM tickets WHERE YEAR(created_at) = YEAR(CURDATE()) - 1 GROUP BY MONTH(created_at)")->fetchAll();
foreach ($chLyQuery as $r) { $chLastYear[intval($r['month'])] = intval($r['count']); }

$currentDayOfYear = date('z') + 1;
$currentMonth = date('n');
$volThisYear = array_sum($chCurrentYear);
$chAvgDay = $currentDayOfYear > 0 ? $volThisYear / $currentDayOfYear : 0;
$chAvgMonth = $currentMonth > 0 ? $volThisYear / $currentMonth : 0;
$chEstYear = $chAvgMonth * 12;

$chTopUsers = $pdo->query("SELECT u.name, COUNT(*) as count FROM tickets t JOIN users u ON BINARY t.requester_id = BINARY u.id WHERE YEAR(t.created_at) = YEAR(CURDATE()) GROUP BY u.name ORDER BY count DESC LIMIT 10")->fetchAll();

$chTopTechnicians = $pdo->query("
    SELECT COALESCE(u.name, TRIM(t.closed_by)) as tech_name, u.avatar_url, COUNT(*) as count 
    FROM tickets t 
    LEFT JOIN users u ON t.closed_by = u.name 
    WHERE t.status IN ('Concluído', 'Solucionado', 'Finalizado', 'Fechado') 
      AND t.closed_by IS NOT NULL 
      AND TRIM(t.closed_by) != ''
    GROUP BY tech_name, u.avatar_url 
    ORDER BY count DESC 
    LIMIT 15
")->fetchAll();

// === SLA DE CHAMADOS ===
// SQL expression to calculate net SLA minutes (Total - Pauses)
$slaExpr = "
    (
        TIMESTAMPDIFF(MINUTE, created_at, closed_at)
        - COALESCE((
            SELECT SUM(TIMESTAMPDIFF(MINUTE, tp.paused_at, COALESCE(tp.resumed_at, closed_at)))
            FROM ticket_pauses tp WHERE tp.ticket_id = tickets.id
          ), 0)
    )
";

$slaAvgTotal = $pdo->query("
    SELECT ROUND(AVG($slaExpr) / 60, 1) as avg_hours
    FROM tickets
    WHERE closed_at IS NOT NULL AND status IN ('Concluído','Solucionado','Finalizado','Fechado')
")->fetchColumn();

$slaBySector = $pdo->query("
    SELECT sector, 
           COUNT(*) as total,
           ROUND(AVG($slaExpr) / 60, 1) as avg_hours
    FROM tickets
    WHERE closed_at IS NOT NULL AND status IN ('Concluído','Solucionado','Finalizado','Fechado')
      AND sector IS NOT NULL AND TRIM(sector) != ''
    GROUP BY sector
    ORDER BY avg_hours ASC
")->fetchAll();

$slaByMonth = array_fill(1, 12, null);
$slaMonthQuery = $pdo->query("
    SELECT MONTH(closed_at) as month,
           COUNT(*) as total,
           ROUND(AVG($slaExpr) / 60, 1) as avg_hours
    FROM tickets
    WHERE closed_at IS NOT NULL
      AND YEAR(closed_at) = YEAR(CURDATE())
      AND status IN ('Concluído','Solucionado','Finalizado','Fechado')
    GROUP BY MONTH(closed_at)
");
foreach ($slaMonthQuery as $r) { $slaByMonth[intval($r['month'])] = (float)$r['avg_hours']; }

$slaFastest = $pdo->query("
    SELECT ROUND(MIN($slaExpr) / 60, 1) as min_hours
    FROM tickets
    WHERE closed_at IS NOT NULL AND status IN ('Concluído','Solucionado','Finalizado','Fechado')
")->fetchColumn();

$slaSlowest = $pdo->query("
    SELECT ROUND(MAX($slaExpr) / 60, 1) as max_hours
    FROM tickets
    WHERE closed_at IS NOT NULL AND status IN ('Concluído','Solucionado','Finalizado','Fechado')
")->fetchColumn();

// === DADOS PATRIMÔNIO ===
$assetsByStatus = $pdo->query("SELECT status, COUNT(*) as count FROM assets GROUP BY status")->fetchAll();
$assetsBySector = $pdo->query("SELECT COALESCE(NULLIF(sector, ''), 'Sem Setor') as sector, COUNT(*) as count FROM assets GROUP BY sector ORDER BY count DESC")->fetchAll();
$assetsByCategory = $pdo->query("SELECT category, COUNT(*) as count FROM assets GROUP BY category ORDER BY count DESC LIMIT 8")->fetchAll();
$totalAssetValue = $pdo->query("SELECT COALESCE(SUM(estimated_value), 0) as total FROM assets")->fetch()['total'];
$assetValueBySector = $pdo->query("SELECT COALESCE(NULLIF(sector, ''), 'Sem Setor') as sector, COUNT(*) as count, COALESCE(SUM(estimated_value), 0) as total_value FROM assets GROUP BY sector ORDER BY total_value DESC")->fetchAll();
$assetValueByCategory = $pdo->query("SELECT category, COUNT(*) as count, COALESCE(SUM(estimated_value), 0) as total_value FROM assets GROUP BY category ORDER BY total_value DESC")->fetchAll();

// === DADOS EMPRÉSTIMOS ===
// Ocorrências de atraso por usuário
$loanOccurrences = $pdo->query("
    SELECT l.borrower_id, l.borrower_name, u.avatar_url, l.asset_name,
        l.loan_date, l.expected_return_date, l.return_date, l.status
    FROM loans l
    LEFT JOIN users u ON BINARY l.borrower_id = BINARY u.id OR (l.borrower_id = '' AND BINARY l.borrower_name = BINARY u.name)
    WHERE l.expected_return_date IS NOT NULL
    AND (
        (l.status = 'Devolvido' AND l.return_date IS NOT NULL AND l.return_date > l.expected_return_date)
        OR (l.status = 'Ativo' AND l.expected_return_date < NOW())
    )
    ORDER BY l.borrower_name, l.loan_date DESC
")->fetchAll();

$occurrencesByUser = [];
foreach ($loanOccurrences as $oc) {
    try {
        $expected = new DateTime($oc['expected_return_date']);
        if ($oc['status'] === 'Ativo') {
            $diff = $expected->diff(new DateTime());
        } else {
            $diff = $expected->diff(new DateTime($oc['return_date']));
        }
        
        $days = $diff->days;
        $hours = $diff->h;
        $mins = $diff->i;
        
        if ($days > 0) $oc['late_string'] = "{$days} dia(s) e {$hours}h";
        elseif ($hours > 0) $oc['late_string'] = "{$hours} hora(s) e {$mins}m";
        else $oc['late_string'] = "{$mins} min(s)";
    } catch(Exception $e) {
        $oc['late_string'] = "N/A";
    }

    $uid = $oc['borrower_id'] ?: $oc['borrower_name'];
    if (!isset($occurrencesByUser[$uid])) {
        $occurrencesByUser[$uid] = ['name' => $oc['borrower_name'], 'avatar_url' => $oc['avatar_url'], 'loans' => []];
    }
    $occurrencesByUser[$uid]['loans'][] = $oc;
}

$loansBySector = $pdo->query("SELECT COALESCE(NULLIF(sector, ''), 'Sem Setor') as sector, COUNT(*) as count FROM loans GROUP BY sector ORDER BY count DESC")->fetchAll();
$loansByCategory = $pdo->query("SELECT COALESCE(NULLIF(a.category, ''), 'Sem Categoria') as category, COUNT(l.id) as count FROM loans l JOIN assets a ON l.asset_id = a.id GROUP BY a.category ORDER BY count DESC")->fetchAll();

$loansCurrentYear = array_fill(1, 12, 0);
$lcQuery = $pdo->query("SELECT MONTH(loan_date) as month, COUNT(*) as count FROM loans WHERE YEAR(loan_date) = YEAR(CURDATE()) GROUP BY MONTH(loan_date)")->fetchAll();
foreach ($lcQuery as $r) { $loansCurrentYear[intval($r['month'])] = intval($r['count']); }

$loansLastYear = array_fill(1, 12, 0);
$llQuery = $pdo->query("SELECT MONTH(loan_date) as month, COUNT(*) as count FROM loans WHERE YEAR(loan_date) = YEAR(CURDATE()) - 1 GROUP BY MONTH(loan_date)")->fetchAll();
foreach ($llQuery as $r) { $loansLastYear[intval($r['month'])] = intval($r['count']); }

$loansByUserCurrentYear = $pdo->query("
    SELECT borrower_name, borrower_id, COUNT(*) as count_current
    FROM loans WHERE YEAR(loan_date) = YEAR(CURDATE())
    GROUP BY borrower_id, borrower_name ORDER BY count_current DESC LIMIT 20
")->fetchAll();
$totalCurrentYear = array_sum(array_column($loansByUserCurrentYear, 'count_current')) ?: 1;

$userLoanMonthly = [];
foreach ($loansByUserCurrentYear as $ubr) {
    $uid = $ubr['borrower_id'] ?: $ubr['borrower_name'];
    $userLoanMonthly[$uid] = ['name' => $ubr['borrower_name'], 'months' => array_fill(1,12,0), 'total' => $ubr['count_current']];
}
if (!empty($userLoanMonthly)) {
    $ids = array_keys($userLoanMonthly);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $monthlyStmt = $pdo->prepare("
        SELECT borrower_id, borrower_name, MONTH(loan_date) as month, COUNT(*) as cnt
        FROM loans WHERE YEAR(loan_date) = YEAR(CURDATE())
        AND (borrower_id IN ($placeholders) OR borrower_name IN ($placeholders))
        GROUP BY borrower_id, borrower_name, MONTH(loan_date)
    ");
    $monthlyStmt->execute(array_merge($ids, $ids));
    foreach ($monthlyStmt->fetchAll() as $mr) {
        $uid = $mr['borrower_id'] ?: $mr['borrower_name'];
        if (isset($userLoanMonthly[$uid])) {
            $userLoanMonthly[$uid]['months'][intval($mr['month'])] = intval($mr['cnt']);
        }
    }
}

$loansByUserLastYear = $pdo->query("
    SELECT borrower_name, borrower_id, COUNT(*) as count_last
    FROM loans WHERE YEAR(loan_date) = YEAR(CURDATE()) - 1
    GROUP BY borrower_id, borrower_name ORDER BY count_last DESC LIMIT 20
")->fetchAll();
$loansByUserLastYearMap = [];
foreach ($loansByUserLastYear as $r) {
    $uid = $r['borrower_id'] ?: $r['borrower_name'];
    $loansByUserLastYearMap[$uid] = $r['count_last'];
}

// === DADOS VOLUNTARIADO ===
$totalHours = $pdo->query("SELECT SUM(total_hours) as total FROM volunteers")->fetch()['total'] ?? 0;
$volBySex = $pdo->query("SELECT gender as sex, COUNT(*) as count FROM volunteers GROUP BY gender")->fetchAll();
$volByStatus = $pdo->query("SELECT status, COUNT(*) as count FROM volunteers GROUP BY status")->fetchAll();
$volByType = $pdo->query("SELECT location as work_type, COUNT(*) as count FROM volunteers GROUP BY location")->fetchAll();

// === DADOS FINANCEIRO ===
$financialStats = $pdo->query("SELECT 
    SUM(hours_jan * hourly_rate) as `jan`, SUM(hours_feb * hourly_rate) as `feb`, 
    SUM(hours_mar * hourly_rate) as `mar`, SUM(hours_apr * hourly_rate) as `apr`,
    SUM(hours_may * hourly_rate) as `may`, SUM(hours_jun * hourly_rate) as `jun`,
    SUM(hours_jul * hourly_rate) as `jul`, SUM(hours_aug * hourly_rate) as `aug`,
    SUM(hours_sep * hourly_rate) as `sep`, SUM(hours_oct * hourly_rate) as `oct`,
    SUM(hours_nov * hourly_rate) as `nov`, SUM(hours_dec * hourly_rate) as `dec`,
    SUM(total_hours * hourly_rate) as `total`
FROM volunteers")->fetch();

// Preparar arrays para JS
$sexLabels = []; $sexData = [];
foreach ($volBySex as $v) { $sexLabels[] = $v['sex']; $sexData[] = $v['count']; }
$statusLabels = []; $statusData = [];
foreach ($volByStatus as $v) { 
    $statusLabels[] = ($v['status'] == 'Ativo') ? 'Ativo' : 'Desativo';
    $statusData[] = $v['count']; 
}
$typeLabels = []; $typeData = [];
foreach ($volByType as $v) { $typeLabels[] = $v['work_type']; $typeData[] = $v['count']; }
$financialMonths = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];

// === DADOS RH ===
$rhContractTypes = $pdo->query("SELECT contract_type, COUNT(*) as count FROM rh_employee_details GROUP BY contract_type ORDER BY count DESC")->fetchAll();
$rhTransportCount = $pdo->query("SELECT COUNT(*) FROM rh_employee_details WHERE use_transport = 'Sim'")->fetchColumn();
$rhGenders = $pdo->query("SELECT gender, COUNT(*) as count FROM rh_employee_details GROUP BY gender ORDER BY count DESC")->fetchAll();
$rhRoles = $pdo->query("SELECT COALESCE(NULLIF(role_name, ''), 'Não Definido') as role_name, COUNT(*) as count FROM rh_employee_details GROUP BY role_name ORDER BY count DESC LIMIT 10")->fetchAll();

$rhAdmissions = $pdo->query("SELECT YEAR(start_date) as yr, COUNT(*) as count FROM rh_employee_details WHERE start_date IS NOT NULL GROUP BY yr ORDER BY yr ASC")->fetchAll();
$rhTerminations = $pdo->query("SELECT YEAR(end_date) as yr, COUNT(*) as count FROM rh_employee_details WHERE end_date IS NOT NULL GROUP BY yr ORDER BY yr ASC")->fetchAll();

$rhSectorStats = $pdo->query("
    SELECT 
        u.sector, 
        COUNT(u.id) as total_users, 
        SUM(CASE WHEN u.status = 'Ativo' THEN 1 ELSE 0 END) as active_users,
        SUM(COALESCE(rh.salary, 0)) as total_salary
    FROM users u
    LEFT JOIN rh_employee_details rh ON BINARY u.id = BINARY rh.user_id
    GROUP BY u.sector
    ORDER BY total_salary DESC
")->fetchAll();

$rhContractLabels = []; $rhContractData = [];
foreach ($rhContractTypes as $c) { $rhContractLabels[] = $c['contract_type'] ?: 'Indefinido'; $rhContractData[] = $c['count']; }

$rhGenderLabels = []; $rhGenderData = [];
foreach ($rhGenders as $g) { $rhGenderLabels[] = $g['gender'] ?: 'Não Informado'; $rhGenderData[] = $g['count']; }

$rhYears = [];
$rhYearMap = [];
foreach ($rhAdmissions as $a) { $rhYears[] = $a['yr']; $rhYearMap[$a['yr']]['adm'] = $a['count']; }
foreach ($rhTerminations as $t) { $rhYears[] = $t['yr']; $rhYearMap[$t['yr']]['term'] = $t['count']; }
$rhYears = array_unique($rhYears);
sort($rhYears);
$rhAdmSeries = []; $rhTermSeries = [];
foreach ($rhYears as $y) { $rhAdmSeries[] = $rhYearMap[$y]['adm'] ?? 0; $rhTermSeries[] = $rhYearMap[$y]['term'] ?? 0; }

// === DADOS ADICIONAIS RH ===
$rhVacationsPending = $pdo->query("SELECT COUNT(*) FROM rh_vacations WHERE status = 'Programada'")->fetchColumn();
$rhVacationsNext30 = $pdo->query("SELECT COUNT(*) FROM rh_vacations WHERE start_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetchColumn();
$rhCertificatesTotal = $pdo->query("SELECT COUNT(*) FROM rh_certificates")->fetchColumn();
$rhNotesTotal = $pdo->query("SELECT COUNT(*) FROM rh_notes")->fetchColumn();

// Aniversariantes do Mês
$rhBirthdaysMonth = $pdo->query("
    SELECT u.name, rh.birth_date, u.sector 
    FROM users u 
    JOIN rh_employee_details rh ON BINARY u.id = BINARY rh.user_id 
    WHERE MONTH(rh.birth_date) = MONTH(CURDATE()) 
    ORDER BY DAY(rh.birth_date) ASC
")->fetchAll();

// Salário Médio por Setor
$rhAvgSalarySector = $pdo->query("
    SELECT u.sector, AVG(rh.salary) as avg_salary 
    FROM users u 
    JOIN rh_employee_details rh ON BINARY u.id = BINARY rh.user_id 
    WHERE rh.salary > 0 
    GROUP BY u.sector 
    ORDER BY avg_salary DESC
")->fetchAll();

// Tendência de Atestados (Últimos 6 meses)
$rhCertTrendQuery = $pdo->query("
    SELECT DATE_FORMAT(issue_date, '%b') as m_name, COUNT(*) as count 
    FROM rh_certificates 
    WHERE issue_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) 
    GROUP BY m_name 
    ORDER BY MIN(issue_date) ASC
")->fetchAll();
$rhCertLabels = array_column($rhCertTrendQuery, 'm_name');
$rhCertData = array_column($rhCertTrendQuery, 'count');

$financialData = [
    $financialStats['jan']??0, $financialStats['feb']??0, $financialStats['mar']??0, $financialStats['apr']??0,
    $financialStats['may']??0, $financialStats['jun']??0, $financialStats['jul']??0, $financialStats['aug']??0,
    $financialStats['sep']??0, $financialStats['oct']??0, $financialStats['nov']??0, $financialStats['dec']??0
];

$userChartData = [];
foreach ($userLoanMonthly as $uid => $ud) {
    $userChartData[] = [
        'id' => $uid,
        'name' => $ud['name'],
        'total' => $ud['total'],
        'months' => array_values($ud['months']),
        'last_year' => $loansByUserLastYearMap[$uid] ?? 0
    ];
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="page-header" style="margin-bottom: 2rem;">
    <div class="page-header-info">
        <div class="page-header-icon">
            <i class="fa-solid fa-chart-line"></i>
        </div>
        <div class="page-header-text">
            <h2>Inteligência de Dados</h2>
            <p>Indicadores de performance, estatísticas e métricas gerenciais.</p>
        </div>
    </div>
    <div class="page-header-actions no-print">
        <button class="btn-secondary" onclick="printCurrentTab()">
            <i class="fa-solid fa-print"></i> Imprimir Relatório
        </button>
    </div>
</div>

<!-- Sistema de Abas -->
<div style="display: flex; gap: 1rem; border-bottom: 2px solid #e2e8f0; margin-bottom: 2rem; padding-bottom: 0.5rem; flex-wrap: wrap;" class="no-print">
    <button onclick="switchTab('geral')" id="tab-geral" class="tab-btn active">Visão Geral</button>
    <button onclick="switchTab('chamados')" id="tab-chamados" class="tab-btn">Chamados</button>
    <button onclick="switchTab('patrimonio')" id="tab-patrimonio" class="tab-btn">Patrimônio</button>
    <button onclick="switchTab('emprestimos')" id="tab-emprestimos" class="tab-btn">Empréstimos</button>
    <button onclick="switchTab('voluntariado')" id="tab-voluntariado" class="tab-btn">Voluntariado</button>
    <button onclick="switchTab('rh')" id="tab-rh" class="tab-btn">RH</button>
</div>

<style>
    .tab-btn { background: none; border: none; font-weight: 700; color: #64748b; cursor: pointer; padding: 0.5rem 1rem; border-radius: 0.5rem; transition: all 0.3s; }
    .tab-btn.active { color: var(--crm-purple); background: rgba(91, 33, 182, 0.1); }
    .tab-content { display: none; }
    .tab-content.active { display: block; animation: fadeIn 0.3s ease-out; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @media print { body * { display: none !important; } }
</style>

<script>
// ======== SISTEMA DE IMPRESSÃO PROFISSIONAL ========
const printCompanyLogo   = <?= json_encode($company['logo_url'] ?? '') ?>;
const printCompanyName   = <?= json_encode($company['company_name'] ?? 'Cetusg Plus') ?>;
const printUserName      = <?= json_encode($user['name'] ?? '') ?>;
const printUserAvatar    = <?= json_encode($user['avatar_url'] ?? '') ?>;

const tabTitles = {
    'geral':         'Visão Geral',
    'chamados':      'Relatório de Chamados',
    'patrimonio':    'Relatório de Patrimônio',
    'emprestimos':   'Relatório de Empréstimos',
    'voluntariado':  'Relatório de Voluntariado',
    'rh':            'Relatório de RH'
};

function getActiveTabId() {
    const active = document.querySelector('.tab-content.active');
    return active ? active.id.replace('content-', '') : 'geral';
}

function captureAllCharts() {
    // Converts all visible Chart.js canvases to base64 images
    const result = {};
    document.querySelectorAll('canvas').forEach(canvas => {
        if (canvas.id) {
            try { result[canvas.id] = canvas.toDataURL('image/png', 0.95); } catch(e) {}
        }
    });
    return result;
}

function printCurrentTab() {
    const tabId = getActiveTabId();
    const tabTitle = tabTitles[tabId] || 'Relatório';
    const now = new Date();
    const dateStr = now.toLocaleDateString('pt-BR', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
    const timeStr = now.toLocaleTimeString('pt-BR', { hour:'2-digit', minute:'2-digit' });
    const charts = captureAllCharts();

    // Clone the active tab content
    const activeContent = document.getElementById('content-' + tabId);
    if (!activeContent) return;
    const contentClone = activeContent.cloneNode(true);

    // Replace all canvas elements in clone with their image equivalents
    contentClone.querySelectorAll('canvas').forEach(canvas => {
        if (charts[canvas.id]) {
            const img = document.createElement('img');
            img.src = charts[canvas.id];
            img.style.cssText = 'max-width:100%; height:auto; border-radius:8px; display:block; margin:0 auto;';
            canvas.parentNode.replaceChild(img, canvas);
        }
    });

    // Remove interactive buttons that shouldn't appear in print
    contentClone.querySelectorAll('button, select, .no-print').forEach(el => el.remove());

    // Build header HTML
    const logoHtml = printCompanyLogo
        ? `<img src="${printCompanyLogo}" style="height:64px; max-width:180px; object-fit:contain;" alt="Logo" />`
        : `<div style="width:64px;height:64px;background:linear-gradient(135deg,#5B21B6,#3B82F6);border-radius:12px;display:flex;align-items:center;justify-content:center;"><span style="color:#fff;font-size:1.5rem;font-weight:900;">${printCompanyName.charAt(0)}</span></div>`;

    const avatarHtml = printUserAvatar
        ? `<img src="${printUserAvatar}" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid #5B21B6;" />`
        : `<div style="width:40px;height:40px;border-radius:50%;background:#5B21B6;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900;font-size:1rem;">${printUserName.charAt(0).toUpperCase()}</div>`;

    const printHtml = `<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>${tabTitle} — ${printCompanyName}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #fff; color: #0F172A; font-size: 13px; }

        /* ── CAPA DO RELATÓRIO ── */
        .print-header {
            background: linear-gradient(135deg, #5B21B6 0%, #1E40AF 100%);
            color: white;
            padding: 2rem 2.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            page-break-after: avoid;
        }
        .print-header .brand { display:flex; align-items:center; gap:1rem; }
        .print-header .brand-name { font-size:1.5rem; font-weight:900; color:#fff; }
        .print-header .brand-sub { font-size:0.85rem; color:rgba(255,255,255,0.75); margin-top:0.2rem; }
        .print-header .report-info { text-align:right; }
        .print-header .report-title { font-size:1.2rem; font-weight:800; color:#fff; margin-bottom:0.25rem; }
        .print-header .report-meta { font-size:0.8rem; color:rgba(255,255,255,0.8); }

        /* ── LINHA DO USUÁRIO ── */
        .print-user-bar {
            background:#f8fafc;
            border-bottom:2px solid #e2e8f0;
            padding:0.75rem 2.5rem;
            display:flex;
            align-items:center;
            gap:0.75rem;
        }
        .print-user-bar span { font-size:0.85rem; color:#334155; }
        .print-user-bar strong { color:#5B21B6; }

        /* ── CONTEÚDO ── */
        .print-body { padding: 1.5rem 2.5rem 2rem; }

        /* ── STAT CARDS ── */
        .stat-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin-bottom:1.5rem; }
        .stat-card { background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:1rem 1.25rem; }
        .stat-label { font-size:0.7rem; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:0.25rem; }
        .stat-value { font-size:1.75rem; font-weight:900; color:#0F172A; }
        .stat-icon { display:none; }

        /* ── PANELS ── */
        .glass-panel { background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:1.25rem; margin-bottom:1.25rem; page-break-inside:avoid; }
        .glass-panel h3 { font-size:0.95rem; font-weight:800; margin-bottom:1rem; color:#0F172A; }

        /* ── TABLES ── */
        table { width:100%; border-collapse:collapse; font-size:0.8rem; }
        th { text-align:left; padding:0.5rem 0.75rem; font-size:0.65rem; font-weight:700; color:#64748b; text-transform:uppercase; border-bottom:2px solid #e2e8f0; }
        td { padding:0.5rem 0.75rem; border-bottom:1px solid #f1f5f9; }
        tfoot td, tfoot th { font-weight:900; background:#f8fafc; border-top:2px solid #e2e8f0; }

        /* ── BADGES ── */
        .badge, span[style*="border-radius"] { display:inline-block; }

        /* ── GRIDS ── */
        [style*="display: grid"] { display:grid !important; }
        [style*="grid-template-columns:repeat(2"] { grid-template-columns:repeat(2,1fr) !important; gap:1rem !important; }
        [style*="grid-template-columns:repeat(3"] { grid-template-columns:repeat(3,1fr) !important; gap:1rem !important; }
        [style*="grid-template-columns:repeat(4"] { grid-template-columns:repeat(4,1fr) !important; gap:1rem !important; }
        [style*="grid-template-columns: 1fr 1fr"] { grid-template-columns:1fr 1fr !important; gap:1rem !important; }
        [style*="grid-template-columns: 1fr 1fr 1fr"] { grid-template-columns:repeat(3,1fr) !important; gap:1rem !important; }

        /* ── CHARTS ── */
        canvas { display:none !important; }
        img { max-width:100%; height:auto; }

        /* ── RODAPÉ ── */
        .print-footer { margin-top:2rem; padding-top:1rem; border-top:2px solid #e2e8f0; text-align:center; font-size:0.7rem; color:#94a3b8; }
    </style>
</head>
<body>
    <div class="print-header">
        <div class="brand">
            ${logoHtml}
            <div>
                <div class="brand-name">${printCompanyName}</div>
                <div class="brand-sub">Sistema de Gestão Integrado</div>
            </div>
        </div>
        <div class="report-info">
            <div class="report-title"><i class="fa-solid fa-chart-line"></i> ${tabTitle}</div>
            <div class="report-meta">Emitido em ${dateStr} às ${timeStr}</div>
        </div>
    </div>

    <div class="print-user-bar">
        ${avatarHtml}
        <span>Relatório gerado por: <strong>${printUserName}</strong></span>
        <span style="margin-left:auto;color:#94a3b8;font-size:0.75rem;">${dateStr} — ${timeStr}</span>
    </div>

    <div class="print-body">
        ${contentClone.innerHTML}
    </div>

    <div class="print-footer">
        ${printCompanyName} &nbsp;|&nbsp; ${tabTitle} &nbsp;|&nbsp; ${dateStr} às ${timeStr} &nbsp;|&nbsp; Gerado por ${printUserName}
    </div>

    <script>
        window.onload = function() {
            window.print();
        };
    <\/script>
</body>
</html>`;

    const win = window.open('', '_blank', 'width=1000,height=750');
    win.document.open();
    win.document.write(printHtml);
    win.document.close();
}
</script>

<!-- ======================== ABA VISÃO GERAL ======================== -->
<div id="content-geral" class="tab-content active">
    <div class="stat-grid">
        <div class="stat-card"><div class="stat-icon purple"><i class="fa-solid fa-database"></i></div><div class="stat-label">Total de Ativos</div><div class="stat-value"><?= $totalAssets ?></div></div>
        <div class="stat-card"><div class="stat-icon yellow"><i class="fa-solid fa-right-left"></i></div><div class="stat-label">Total de Empréstimos</div><div class="stat-value"><?= $totalLoans ?></div></div>
        <div class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-users"></i></div><div class="stat-label">Total de Usuários</div><div class="stat-value"><?= $totalUsers ?></div></div>
        <div class="stat-card"><div class="stat-icon blue"><i class="fa-solid fa-building"></i></div><div class="stat-label">Unidades Ativas</div><div class="stat-value"><?= $totalUnits ?></div></div>
    </div>
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 2rem; margin-top: 2rem;">
        <div class="glass-panel">
            <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1rem;"><i class="fa-solid fa-heart" style="color:#EC4899;"></i> Voluntários: <?= $totalVolunteers ?></h3>
            <p style="color:#64748b; font-size:0.9rem;">Horas totais: <strong><?= number_format($totalHours, 1, ',', '.') ?>h</strong></p>
            <p style="color:#64748b; font-size:0.9rem;">Impacto Financeiro: <strong style="color:var(--crm-purple);">R$ <?= number_format($financialStats['total']??0, 2, ',', '.') ?></strong></p>
        </div>
        <div class="glass-panel">
            <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1rem;"><i class="fa-solid fa-coins" style="color:#F59E0B;"></i> Valor Patrimonial Estimado</h3>
            <p style="font-size: 1.75rem; font-weight: 900; color: var(--crm-purple);">R$ <?= number_format($totalAssetValue, 2, ',', '.') ?></p>
            <p style="color:#64748b; font-size:0.9rem;">Chamados registrados: <strong><?= $totalTickets ?></strong></p>
        </div>
    </div>
</div>

<!-- ======================== ABA CHAMADOS ======================== -->
<div id="content-chamados" class="tab-content">
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fa-solid fa-headset"></i></div>
            <div class="stat-label">Total de Chamados</div>
            <div class="stat-value"><?= $totalChamados ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fa-solid fa-check-double"></i></div>
            <div class="stat-label">Solucionados</div>
            <div class="stat-value"><?= $chSolucionados ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow"><i class="fa-solid fa-pause"></i></div>
            <div class="stat-label">Pendenciados</div>
            <div class="stat-value"><?= $chPendentes ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red" style="background: rgba(239, 68, 68, 0.1); color: #EF4444;"><i class="fa-solid fa-circle-xmark"></i></div>
            <div class="stat-label">Sem Solução</div>
            <div class="stat-value"><?= $chSemSolucao ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue" style="background: rgba(59, 130, 246, 0.1); color: #3B82F6;"><i class="fa-solid fa-envelope-open"></i></div>
            <div class="stat-label">Abertos</div>
            <div class="stat-value"><?= $chAbertos ?></div>
        </div>
    </div>

    <!-- Novas Métricas de Gestão e Estimativas -->
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
        <div class="stat-card" style="padding: 1.25rem;">
            <div style="font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Média Diária (Este Ano)</div>
            <div style="font-size: 1.5rem; font-weight: 900; color: #3B82F6;"><?= number_format($chAvgDay, 1, ',', '.') ?> <span style="font-size:0.8rem; font-weight:700;">chamados/dia</span></div>
        </div>
        <div class="stat-card" style="padding: 1.25rem;">
            <div style="font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Média Mensal (Este Ano)</div>
            <div style="font-size: 1.5rem; font-weight: 900; color: #8B5CF6;"><?= number_format($chAvgMonth, 1, ',', '.') ?> <span style="font-size:0.8rem; font-weight:700;">chamados/mês</span></div>
        </div>
        <div class="stat-card" style="padding: 1.25rem;">
            <div style="font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Estimativa Anual (<span style="color:var(--crm-purple)"><?= date('Y') ?></span>)</div>
            <div style="font-size: 1.5rem; font-weight: 900; color: #10B981;"><?= number_format($chEstYear, 0, '', '.') ?> <span style="font-size:0.8rem; font-weight:700;">tickets totais estim.</span></div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 2rem; margin-bottom: 2rem;">
        <div class="glass-panel">
            <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem;"><i class="fa-solid fa-chart-pie"></i> Proporção de Status</h3>
            <div style="height: 300px;"><canvas id="chamadosStatusChart"></canvas></div>
        </div>
        <div class="glass-panel" style="overflow: hidden;">
            <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem;"><i class="fa-solid fa-user-tag"></i> Top Usuários Solicitantes (Este Ano)</h3>
            <div style="height: 300px;"><canvas id="chamadosUsersChart"></canvas></div>
        </div>
    </div>

    <div class="glass-panel" style="margin-bottom: 2rem;">
        <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem;"><i class="fa-solid fa-chart-line"></i> Comparativo de Volume (Mês a Mês / Ano a Ano)</h3>
        <div style="height: 350px;"><canvas id="chamadosTrendChart"></canvas></div>
    </div>

    <div class="glass-panel">
        <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem;"><i class="fa-solid fa-building-user"></i> Requisições por Departamento (Setor)</h3>
        <div style="height: <?= max(250, count($chBySector) * 40) ?>px;"><canvas id="chamadosSectorChart"></canvas></div>
    </div>

    <div class="glass-panel" style="margin-top: 2rem;">
        <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-user-gear" style="color: #10B981;"></i>
            Técnicos × Soluções (Desempenho)
        </h3>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align: left; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; width: 60px;">Técnico</th>
                        <th style="text-align: left; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">Nome</th>
                        <th style="text-align: center; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">Soluções</th>
                        <th style="text-align: right; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">Taxa de Contribuição</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($chTopTechnicians)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; padding: 2rem; color: #94a3b8;">Nenhum dado de solução registrado ainda.</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($chTopTechnicians as $tech): 
                            $pct = $chSolucionados > 0 ? ($tech['count'] / $chSolucionados) * 100 : 0;
                        ?>
                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background-color 0.2s;">
                            <td style="padding: 0.75rem 1rem;">
                                <?php if ($tech['avatar_url']): ?>
                                    <img src="<?= htmlspecialchars($tech['avatar_url']) ?>" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid #e2e8f0;">
                                <?php else: ?>
                                    <div style="width: 36px; height: 36px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 14px; color: #94a3b8; border: 1px solid #e2e8f0;">👤</div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0F172A;"><?= htmlspecialchars($tech['tech_name']) ?></td>
                            <td style="padding: 0.75rem 1rem; text-align: center; font-weight: 900; color: #10B981; font-size: 1.1rem;"><?= $tech['count'] ?></td>
                            <td style="padding: 0.75rem 1rem; text-align: right;">
                                <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem;">
                                    <div style="width: 100px; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden;">
                                        <div style="height: 100%; width: <?= min(100, $pct) ?>%; background: #10B981;"></div>
                                    </div>
                                    <span style="background: rgba(16, 185, 129, 0.1); color: #10B981; padding: 2px 6px; border-radius: 6px; font-size: 0.75rem; font-weight: 800; min-width: 45px; text-align: center;"><?= number_format($pct, 1) ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ======================== SLA CHAMADOS ======================== -->
    <div class="glass-panel" style="margin-top: 2rem; border: 2px solid rgba(245, 158, 11, 0.2); background: linear-gradient(135deg, rgba(245,158,11,0.03), rgba(239,68,68,0.03));">
        <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem;">
            <div style="width:42px;height:42px;border-radius:12px;background:linear-gradient(135deg,#f59e0b,#ef4444);display:flex;align-items:center;justify-content:center;">
                <i class="fa-regular fa-clock" style="color:white;font-size:1.1rem;"></i>
            </div>
            <div>
                <h3 style="font-size:1.1rem;font-weight:900;color:#1e293b;margin:0;">SLA de Chamados</h3>
                <p style="font-size:0.8rem;color:#64748b;margin:0;">Tempo médio de resolução por setor e mês &mdash; <?= date('Y') ?></p>
            </div>
        </div>

        <!-- KPI cards SLA -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1.25rem;margin-bottom:2rem;">
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:1rem;padding:1.25rem;text-align:center;">
                <div style="font-size:0.7rem;font-weight:800;color:#64748b;text-transform:uppercase;margin-bottom:.5rem;"><i class="fa-solid fa-stopwatch" style="color:#f59e0b;"></i> Média Geral</div>
                <div style="font-size:2rem;font-weight:900;color:#f59e0b;"><?= $slaAvgTotal !== null ? $slaAvgTotal.'h' : '—' ?></div>
                <div style="font-size:0.7rem;color:#94a3b8;">tempo médio para fechar</div>
            </div>
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:1rem;padding:1.25rem;text-align:center;">
                <div style="font-size:0.7rem;font-weight:800;color:#64748b;text-transform:uppercase;margin-bottom:.5rem;"><i class="fa-solid fa-bolt" style="color:#10b981;"></i> Mais Rápido</div>
                <div style="font-size:2rem;font-weight:900;color:#10b981;"><?= $slaFastest !== null ? $slaFastest.'h' : '—' ?></div>
                <div style="font-size:0.7rem;color:#94a3b8;">menor tempo registrado</div>
            </div>
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:1rem;padding:1.25rem;text-align:center;">
                <div style="font-size:0.7rem;font-weight:800;color:#64748b;text-transform:uppercase;margin-bottom:.5rem;"><i class="fa-solid fa-hourglass-half" style="color:#ef4444;"></i> Mais Lento</div>
                <div style="font-size:2rem;font-weight:900;color:#ef4444;"><?= $slaSlowest !== null ? $slaSlowest.'h' : '—' ?></div>
                <div style="font-size:0.7rem;color:#94a3b8;">maior tempo registrado</div>
            </div>
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:1rem;padding:1.25rem;text-align:center;">
                <div style="font-size:0.7rem;font-weight:800;color:#64748b;text-transform:uppercase;margin-bottom:.5rem;"><i class="fa-solid fa-building-columns" style="color:#8b5cf6;"></i> Setores com SLA</div>
                <div style="font-size:2rem;font-weight:900;color:#8b5cf6;"><?= count($slaBySector) ?></div>
                <div style="font-size:0.7rem;color:#94a3b8;">setores monitorados</div>
            </div>
        </div>

        <!-- Gráficos SLA -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;">
            <!-- Gráfico de linha: SLA mês a mês -->
            <div>
                <h4 style="font-size:0.9rem;font-weight:800;color:#475569;margin-bottom:1rem;"><i class="fa-solid fa-chart-line" style="color:#f59e0b;"></i> Evolução Mensal do SLA (horas)</h4>
                <div style="height:260px;"><canvas id="slaMonthChart"></canvas></div>
            </div>
            <!-- Gráfico de barras horizontal: SLA por setor -->
            <div>
                <h4 style="font-size:0.9rem;font-weight:800;color:#475569;margin-bottom:1rem;"><i class="fa-solid fa-building-user" style="color:#8b5cf6;"></i> SLA Médio por Setor (horas)</h4>
                <div style="height:260px;"><canvas id="slaSectorChart"></canvas></div>
            </div>
        </div>

        <!-- Tabela detalhada por setor -->
        <?php if (!empty($slaBySector)): ?>
        <div style="margin-top:2rem;overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:0.85rem;">
                <thead>
                    <tr style="background:#f8fafc;">
                        <th style="text-align:left;padding:.75rem 1rem;font-size:0.7rem;font-weight:800;color:#64748b;text-transform:uppercase;border-bottom:2px solid #e2e8f0;">Setor</th>
                        <th style="text-align:center;padding:.75rem 1rem;font-size:0.7rem;font-weight:800;color:#64748b;text-transform:uppercase;border-bottom:2px solid #e2e8f0;">Chamados Fechados</th>
                        <th style="text-align:center;padding:.75rem 1rem;font-size:0.7rem;font-weight:800;color:#64748b;text-transform:uppercase;border-bottom:2px solid #e2e8f0;">Média SLA</th>
                        <th style="text-align:left;padding:.75rem 1rem;font-size:0.7rem;font-weight:800;color:#64748b;text-transform:uppercase;border-bottom:2px solid #e2e8f0;">Performance</th>
                    </tr>
                </thead>
                <tbody>
                <?php 
                $maxSla = max(array_column($slaBySector, 'avg_hours') ?: [1]);
                foreach ($slaBySector as $sl):
                    $pct = $maxSla > 0 ? ($sl['avg_hours'] / $maxSla) * 100 : 0;
                    $barColor = $sl['avg_hours'] <= 4 ? '#10b981' : ($sl['avg_hours'] <= 12 ? '#f59e0b' : '#ef4444');
                ?>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:.75rem 1rem;font-weight:700;color:#1e293b;"><?= htmlspecialchars($sl['sector']) ?></td>
                    <td style="padding:.75rem 1rem;text-align:center;font-weight:700;color:#475569;"><?= $sl['total'] ?></td>
                    <td style="padding:.75rem 1rem;text-align:center;">
                        <span style="font-size:1rem;font-weight:900;color:<?= $barColor ?>;"><?= $sl['avg_hours'] ?>h</span>
                    </td>
                    <td style="padding:.75rem 1rem;">
                        <div style="display:flex;align-items:center;gap:.75rem;">
                            <div style="flex:1;height:8px;background:#f1f5f9;border-radius:4px;overflow:hidden;">
                                <div style="height:100%;width:<?= min(100, $pct) ?>%;background:<?= $barColor ?>;border-radius:4px;transition:width .5s;"></div>
                            </div>
                            <span style="font-size:0.7rem;font-weight:800;color:<?= $barColor ?>;min-width:40px;"><?= number_format($pct, 0) ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>


<div id="content-patrimonio" class="tab-content">
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fa-solid fa-database"></i></div>
            <div class="stat-label">Total de Equipamentos</div>
            <div class="stat-value"><?= $totalAssets ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fa-solid fa-check-circle"></i></div>
            <div class="stat-label">Ativos</div>
            <div class="stat-value"><?php $ac=0; foreach($assetsByStatus as $s) if($s['status']=='Ativo') $ac=$s['count']; echo $ac; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow"><i class="fa-solid fa-wrench"></i></div>
            <div class="stat-label">Em Manutenção</div>
            <div class="stat-value"><?php $mc=0; foreach($assetsByStatus as $s) if($s['status']=='Manutenção') $mc=$s['count']; echo $mc; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fa-solid fa-coins"></i></div>
            <div class="stat-label">Valor Total Estimado</div>
            <div class="stat-value" style="font-size:1.25rem;">R$ <?= number_format($totalAssetValue, 2, ',', '.') ?></div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 2rem; margin-bottom: 2rem;">
        <div class="glass-panel">
            <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem;"><i class="fa-solid fa-chart-pie"></i> Equipamentos por Status</h3>
            <div style="height: 300px;"><canvas id="patrimonioStatusChart"></canvas></div>
        </div>
        <div class="glass-panel">
            <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem;"><i class="fa-solid fa-tags"></i> Equipamentos por Categoria</h3>
            <div style="height: 300px;"><canvas id="patrimonioCategoryChart"></canvas></div>
        </div>
    </div>

    <div class="glass-panel" style="margin-bottom: 2rem;">
        <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem;"><i class="fa-solid fa-chart-bar"></i> Equipamentos por Setor</h3>
        <div style="height: <?= max(250, count($assetsBySector) * 40) ?>px;"><canvas id="patrimonioSectorChart"></canvas></div>
    </div>

    <!-- Tabela de Valor por Setor -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
        <div class="glass-panel">
            <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-money-bill-trend-up" style="color: #3B82F6;"></i>
                Equipamentos por Setor
            </h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">Setor</th>
                            <th style="padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; text-align: center;">Qtd.</th>
                            <th style="text-align: right; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">Valor (R$)</th>
                            <th style="text-align: right; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assetValueBySector as $i => $sv): 
                            $pct = $totalAssetValue > 0 ? ($sv['total_value'] / $totalAssetValue) * 100 : 0;
                        ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0F172A;"><?= htmlspecialchars($sv['sector'] ?: 'Sem Setor') ?></td>
                            <td style="padding: 0.75rem 1rem; text-align: center; font-weight: 900; color: var(--crm-purple);"><?= $sv['count'] ?></td>
                            <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 700; color: #334155;"><?= number_format($sv['total_value'], 2, ',', '.') ?></td>
                            <td style="padding: 0.75rem 1rem; text-align: right;">
                                <span style="background: rgba(59, 130, 246, 0.1); color: #3B82F6; padding: 2px 6px; border-radius: 6px; font-size: 0.75rem; font-weight: 800;"><?= number_format($pct, 1) ?>%</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="glass-panel">
            <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-tags" style="color: #EC4899;"></i>
                Equipamentos por Categoria
            </h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">Categoria</th>
                            <th style="padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; text-align: center;">Qtd.</th>
                            <th style="text-align: right; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">Valor (R$)</th>
                            <th style="text-align: right; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assetValueByCategory as $i => $cv): 
                            $pct = $totalAssetValue > 0 ? ($cv['total_value'] / $totalAssetValue) * 100 : 0;
                        ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0F172A;"><?= htmlspecialchars($cv['category'] ?: 'Sem Categoria') ?></td>
                            <td style="padding: 0.75rem 1rem; text-align: center; font-weight: 900; color: #EC4899;"><?= $cv['count'] ?></td>
                            <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 700; color: #334155;"><?= number_format($cv['total_value'], 2, ',', '.') ?></td>
                            <td style="padding: 0.75rem 1rem; text-align: right;">
                                <span style="background: rgba(236, 72, 153, 0.1); color: #EC4899; padding: 2px 6px; border-radius: 6px; font-size: 0.75rem; font-weight: 800;"><?= number_format($pct, 1) ?>%</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ======================== ABA EMPRÉSTIMOS ======================== -->
<div id="content-emprestimos" class="tab-content">
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fa-solid fa-right-left"></i></div>
            <div class="stat-label">Total de Empréstimos</div>
            <div class="stat-value"><?= $totalLoans ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fa-solid fa-clock"></i></div>
            <div class="stat-label">Empréstimos Este Mês</div>
            <div class="stat-value"><?= $loansCurrentYear[intval(date('n'))] ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fa-solid fa-check-to-slot"></i></div>
            <div class="stat-label">Taxa de Retorno</div>
            <div class="stat-value">
                <?php 
                    $returned = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'Devolvido'")->fetchColumn();
                    echo $totalLoans > 0 ? round(($returned / $totalLoans) * 100) : 0;
                ?>%
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow"><i class="fa-solid fa-building-user"></i></div>
            <div class="stat-label">Setor mais Ativo</div>
            <div class="stat-value" style="font-size: 1.25rem;"><?= $loansBySector[0]['sector'] ?? 'N/A' ?></div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 2rem; margin-bottom: 2rem;">
        <div class="glass-panel">
            <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem;"><i class="fa-solid fa-chart-bar"></i> Empréstimos por Setor</h3>
            <div style="height: 300px;"><canvas id="loansBySectorChart"></canvas></div>
        </div>
        <div class="glass-panel">
            <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem;"><i class="fa-solid fa-tags"></i> Empréstimos por Categoria</h3>
            <div style="height: 300px;"><canvas id="loansByCategoryChart"></canvas></div>
        </div>
    </div>

    <div class="glass-panel" style="margin-bottom: 2rem;">
        <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem;"><i class="fa-solid fa-chart-line"></i> Tendência de Empréstimos (Por Mês)</h3>
        <div style="height: 350px;"><canvas id="loansTrendChart"></canvas></div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
        <div class="glass-panel">
            <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-building" style="color: #6366F1;"></i>
                Empréstimos por Setor
            </h3>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align: left; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">Setor</th>
                        <th style="padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; text-align: center;">Qtd.</th>
                        <th style="text-align: right; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">%</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loansBySector as $i => $ls): 
                        $pct = $totalLoans > 0 ? ($ls['count'] / $totalLoans) * 100 : 0;
                    ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0F172A;"><?= htmlspecialchars($ls['sector'] ?: 'Sem Setor') ?></td>
                        <td style="padding: 0.75rem 1rem; text-align: center; font-weight: 900; color: #6366F1;"><?= $ls['count'] ?></td>
                        <td style="padding: 0.75rem 1rem; text-align: right;">
                            <span style="background: rgba(99, 102, 241, 0.1); color: #6366F1; padding: 2px 6px; border-radius: 6px; font-size: 0.75rem; font-weight: 800;"><?= number_format($pct, 1) ?>%</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="glass-panel">
            <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-list-check" style="color: #F59E0B;"></i>
                Empréstimos por Categoria
            </h3>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align: left; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">Categoria</th>
                        <th style="padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; text-align: center;">Qtd.</th>
                        <th style="text-align: right; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">%</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($loansByCategory as $i => $lc): 
                        $pct = $totalLoans > 0 ? ($lc['count'] / $totalLoans) * 100 : 0;
                    ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0F172A;"><?= htmlspecialchars($lc['category'] ?: 'Sem Categoria') ?></td>
                        <td style="padding: 0.75rem 1rem; text-align: center; font-weight: 900; color: #F59E0B;"><?= $lc['count'] ?></td>
                        <td style="padding: 0.75rem 1rem; text-align: right;">
                            <span style="background: rgba(245, 158, 11, 0.1); color: #F59E0B; padding: 2px 6px; border-radius: 6px; font-size: 0.75rem; font-weight: 800;"><?= number_format($pct, 1) ?>%</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Acordeão de Ocorrências (Atrasos) por Usuário -->
    <div class="glass-panel" style="margin-bottom: 2rem;">
        <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-triangle-exclamation" style="color: #EF4444;"></i>
            Ocorrências de Atraso por Usuário
        </h3>
        
        <?php if (empty($occurrencesByUser)): ?>
            <div style="text-align: center; padding: 2rem; color: #64748b; background: rgba(239, 68, 68, 0.05); border-radius: 0.75rem;">Nenhuma ocorrência de atraso computada no sistema. Todos estão em dia!</div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php foreach ($occurrencesByUser as $uid => $uData): ?>
                    <div style="border: 1px solid #e2e8f0; border-radius: 0.75rem; overflow: hidden; background: #f8fafc;">
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; cursor: pointer; background: #fff;" onclick="const content = this.nextElementSibling; const icon = this.querySelector('.chevron-icon'); if(content.style.display==='none'){content.style.display='block';icon.style.transform='rotate(180deg)';}else{content.style.display='none';icon.style.transform='rotate(0deg)';}">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <?php if ($uData['avatar_url']): ?>
                                    <img src="<?= htmlspecialchars($uData['avatar_url']) ?>" style="width:40px; height:40px; border-radius:50%; object-fit:cover;">
                                <?php else: ?>
                                    <div style="width:40px; height:40px; border-radius:50%; background:#e2e8f0; display:flex; align-items:center; justify-content:center; color:#64748b; font-size:16px;">👤</div>
                                <?php endif; ?>
                                <div>
                                    <div style="font-weight: 800; color: #0F172A;"><?= htmlspecialchars($uData['name']) ?></div>
                                    <div style="font-size: 0.75rem; color: #EF4444; font-weight: 700; margin-top: 0.15rem;"><?= count($uData['loans']) ?> ocorrência(s) registrada(s)</div>
                                </div>
                            </div>
                            <div style="color: #94a3b8; transition: transform 0.3s; transform: rotate(0deg);" class="chevron-icon">
                                <i class="fa-solid fa-chevron-down"></i>
                            </div>
                        </div>
                        <div style="display: none; border-top: 1px solid #e2e8f0; padding: 1rem; background: #f8fafc;">
                            <div style="overflow-x: auto;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <thead>
                                        <tr>
                                            <th style="text-align: left; padding: 0.5rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Equipamento</th>
                                            <th style="text-align: center; padding: 0.5rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Datas (Retirada &rarr; Devolução)</th>
                                            <th style="text-align: center; padding: 0.5rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Atraso Confirmado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($uData['loans'] as $it): ?>
                                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                                <td style="padding: 0.75rem 1rem; font-weight: 700; color: #334155;">
                                                    <?= htmlspecialchars($it['asset_name']) ?>
                                                    <div style="font-size: 0.65rem; font-weight: 600; margin-top: 0.2rem; color: <?= $it['status'] == 'Ativo' ? '#F59E0B' : '#10B981' ?>;">Status: <?= htmlspecialchars($it['status']) ?></div>
                                                </td>
                                                <td style="padding: 0.75rem 1rem; text-align: center; color: #64748b; font-size: 0.75rem;">
                                                    <div style="color:var(--crm-purple); font-weight:700; margin-bottom:0.15rem;">Retirado: <?= date('d/m/Y H:i', strtotime($it['loan_date'])) ?></div>
                                                    <div style="margin-bottom:0.15rem; font-weight:700;">Prazo: <?= date('d/m/Y H:i', strtotime($it['expected_return_date'])) ?></div>
                                                    <?php if ($it['return_date']): ?>
                                                        <div style="color:#10B981; font-weight:700;">Entregue: <?= date('d/m/Y H:i', strtotime($it['return_date'])) ?></div>
                                                    <?php else: ?>
                                                        <div style="color:#F59E0B; font-weight:700;">Ainda com Funcionário</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td style="padding: 0.75rem 1rem; text-align: center;">
                                                    <span style="font-size: 0.75rem; font-weight: 800; color: #EF4444; background: rgba(239, 68, 68, 0.1); padding: 0.2rem 0.5rem; border-radius: 4px; display: inline-block;">
                                                    <i class="fa-solid fa-circle-exclamation"></i> <?= $it['late_string'] ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Ranking de Usuários por Empréstimos -->
    <div class="glass-panel">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <h3 style="font-size: 1.1rem; font-weight: 800; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid fa-ranking-star" style="color: var(--crm-purple);"></i>
                Usuários com Mais Empréstimos
            </h3>
            <div style="display: flex; gap: 0.75rem;">
                <button onclick="filterUserChart('month')" id="btn-month" class="btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 1rem; border-color: var(--crm-purple); color: var(--crm-purple); background: rgba(91,33,182,0.05);">Por Mês</button>
                <button onclick="filterUserChart('year')" id="btn-year" class="btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 1rem;">Por Ano</button>
                <select id="month-selector" onchange="filterUserChart('month')" class="form-select" style="font-size: 0.8rem; padding: 0.4rem 0.75rem; width: auto;">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m == intval(date('n')) ? 'selected' : '' ?>>
                        <?= ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'][$m] ?>
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div id="userRankingTable" style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th style="text-align: left; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">#</th>
                        <th style="text-align: left; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">Usuário</th>
                        <th style="padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">Qtd.</th>
                        <th style="text-align: left; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">%</th>
                        <th style="text-align: left; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">vs Ano Anterior</th>
                        <th style="text-align: left; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">Volume</th>
                    </tr>
                </thead>
                <tbody id="userRankingBody"></tbody>
            </table>
        </div>
        <div style="height: 400px; margin-top: 2rem;"><canvas id="loansByUserChart"></canvas></div>
    </div>
</div>

<!-- ======================== ABA VOLUNTARIADO ======================== -->
<div id="content-voluntariado" class="tab-content">
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fa-solid fa-heart"></i></div>
            <div class="stat-label">Total Voluntários</div>
            <div class="stat-value"><?= $totalVolunteers ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fa-solid fa-clock"></i></div>
            <div class="stat-label">Horas Totais</div>
            <div class="stat-value"><?= number_format($totalHours, 1, ',', '.') ?>h</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fa-solid fa-user-check"></i></div>
            <div class="stat-label">Voluntários Ativos</div>
            <div class="stat-value">
                <?php $activeCount = 0; foreach($volByStatus as $vs) if($vs['status'] == 'Ativo') $activeCount = $vs['count']; echo $activeCount; ?>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
        <div class="glass-panel"><h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem;"><i class="fa-solid fa-venus-mars"></i> Sexo</h3><div style="height: 250px;"><canvas id="sexChart"></canvas></div></div>
        <div class="glass-panel"><h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem;"><i class="fa-solid fa-briefcase"></i> Tipo de Trabalho</h3><div style="height: 250px;"><canvas id="workTypeChart"></canvas></div></div>
        <div class="glass-panel"><h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem;"><i class="fa-solid fa-toggle-on"></i> Status (Ativo/Desativo)</h3><div style="height: 250px;"><canvas id="volStatusChart"></canvas></div></div>
    </div>

    <div class="glass-panel">
        <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--crm-black); margin-bottom: 1.5rem;">Resumo de Atuação</h3>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 2rem;">
            <div>
                <p style="font-weight: 700; margin-bottom: 1rem; color: #64748b; text-transform: uppercase; font-size: 0.75rem;">Status dos Voluntários</p>
                <?php foreach ($volByStatus as $v): 
                    $label = ($v['status'] == 'Ativo') ? 'Ativo' : 'Desativo';
                    $perc = $totalVolunteers > 0 ? ($v['count'] / $totalVolunteers) * 100 : 0;
                    $color = $v['status'] == 'Ativo' ? '#10B981' : '#EF4444';
                ?>
                <div style="margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem; font-weight: 600;"><span><?= $label ?></span><span><?= $v['count'] ?> (<?= number_format($perc, 1) ?>%)</span></div>
                    <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;"><div style="width: <?= $perc ?>%; height: 100%; background: <?= $color ?>;"></div></div>
                </div>
                <?php endforeach; ?>
            </div>
            <div>
                <p style="font-weight: 700; margin-bottom: 1rem; color: #64748b; text-transform: uppercase; font-size: 0.75rem;">Impacto por Sexo</p>
                <?php foreach ($volBySex as $v): 
                    $perc = $totalVolunteers > 0 ? ($v['count'] / $totalVolunteers) * 100 : 0;
                    $color = $v['sex'] == 'Masculino' ? '#3B82F6' : ($v['sex'] == 'Feminino' ? '#EC4899' : '#94A3B8');
                ?>
                <div style="margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem; font-weight: 600;"><span><?= $v['sex'] ?></span><span><?= $v['count'] ?> (<?= number_format($perc, 1) ?>%)</span></div>
                    <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;"><div style="width: <?= $perc ?>%; height: 100%; background: <?= $color ?>;"></div></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <!-- ======================== IMPACTO FINANCEIRO (INTEGRADO) ======================== -->
    <div style="margin-top: 3rem; border-top: 2px solid #e2e8f0; padding-top: 2rem;">
        <h3 style="font-size: 1.5rem; font-weight: 900; color: var(--crm-black); margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-coins" style="color: #F59E0B;"></i>
            Impacto Financeiro do Voluntariado
        </h3>
        
        <div class="stat-card" style="margin-bottom: 2rem; background: linear-gradient(135deg, #5B21B6 0%, #1E40AF 100%); color: white;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div class="stat-label" style="color: rgba(255,255,255,0.8); text-transform: uppercase;">Total Arrecadado (Economia Estimada)</div>
                    <div class="stat-value" style="font-size: 2.5rem;">R$ <?= number_format($financialStats['total']??0, 2, ',', '.') ?></div>
                </div>
                <i class="fa-solid fa-hand-holding-dollar" style="font-size: 3rem; opacity: 0.3;"></i>
            </div>
        </div>

        <div class="glass-panel" style="margin-bottom: 2rem;">
            <h3 style="font-size: 1.25rem; font-weight: 900; margin-bottom: 1.5rem;"><i class="fa-solid fa-chart-area"></i> Evolução Financeira Mensal</h3>
            <div style="height: 350px;"><canvas id="financeChart"></canvas></div>
        </div>

        <div class="glass-panel">
            <h3 style="font-size: 1.25rem; font-weight: 900; margin-bottom: 1.5rem;"><i class="fa-solid fa-table"></i> Detalhamento por Mês</h3>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                <?php foreach ($financialMonths as $i => $m): ?>
                <div style="padding: 1rem; background: #f8fafc; border-radius: 1rem; border: 1px solid #e2e8f0; text-align: center;">
                    <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;"><?= $m ?></div>
                    <div style="font-size: 1.1rem; font-weight: 900; color: var(--crm-purple);">R$ <?= number_format($financialData[$i]??0, 2, ',', '.') ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
</div>

<!-- ======================== ABA RH ======================== -->
<div id="content-rh" class="tab-content">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--crm-black); display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-users-gear" style="color: var(--crm-purple);"></i>
            Gestão de Recursos Humanos
        </h3>
        <button onclick="location.reload()" class="btn-secondary" style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; padding: 0.5rem 1rem;">
            <i class="fa-solid fa-arrows-rotate"></i>
            Sincronizar Dados (Real-time)
        </button>
    </div>
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fa-solid fa-users-gear"></i></div>
            <div class="stat-label">Total de Colaboradores</div>
            <div class="stat-value"><?= $totalUsers ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fa-solid fa-bus"></i></div>
            <div class="stat-label">Usam Vale Transporte</div>
            <div class="stat-value"><?= $rhTransportCount ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fa-solid fa-money-bill-wave"></i></div>
            <div class="stat-label">Custo Mensal Estimado</div>
            <div class="stat-value" style="font-size:1.25rem;">R$ <?= number_format(array_sum(array_column($rhSectorStats, 'total_salary')), 2, ',', '.') ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow"><i class="fa-solid fa-briefcase"></i></div>
            <div class="stat-label">Cargos Ativos</div>
            <div class="stat-value"><?= count($rhRoles) ?></div>
        </div>
    </div>

    <!-- Novas Métricas de Gestão -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
        <div class="stat-card" style="padding: 1.25rem;">
            <div style="font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Férias (Próx. 30 dias)</div>
            <div style="font-size: 1.5rem; font-weight: 900; color: #F59E0B;"><?= $rhVacationsNext30 ?></div>
        </div>
        <div class="stat-card" style="padding: 1.25rem;">
            <div style="font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Atestados (Total)</div>
            <div style="font-size: 1.5rem; font-weight: 900; color: #EF4444;"><?= $rhCertificatesTotal ?></div>
        </div>
        <div class="stat-card" style="padding: 1.25rem;">
            <div style="font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Anotações Dossier</div>
            <div style="font-size: 1.5rem; font-weight: 900; color: #3B82F6;"><?= $rhNotesTotal ?></div>
        </div>
        <div class="stat-card" style="padding: 1.25rem;">
            <div style="font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Média Salarial</div>
            <div style="font-size: 1.5rem; font-weight: 900; color: #10B981;">R$ <?= number_format(count($rhAvgSalarySector) > 0 ? (array_sum(array_column($rhAvgSalarySector, 'avg_salary')) / count($rhAvgSalarySector)) : 0, 2, ',', '.') ?></div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 2rem; margin-bottom: 2rem;">
        <div class="glass-panel">
            <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem;"><i class="fa-solid fa-file-contract"></i> Tipos de Contrato (%)</h3>
            <div style="height: 250px;"><canvas id="rhContractChart"></canvas></div>
        </div>
        <div class="glass-panel">
            <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem;"><i class="fa-solid fa-venus-mars"></i> Distribuição por Sexo</h3>
            <div style="height: 250px;"><canvas id="rhGenderChart"></canvas></div>
        </div>
        <div class="glass-panel">
            <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem;"><i class="fa-solid fa-user-tag"></i> Top 10 Cargos</h3>
            <div style="max-height: 250px; overflow-y:auto;">
                <table style="width:100%; border-collapse: collapse; font-size:0.85rem;">
                    <?php foreach($rhRoles as $role): ?>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:0.5rem 0; font-weight:700; color:#334155;"><?= htmlspecialchars($role['role_name']) ?></td>
                        <td style="padding:0.5rem 0; text-align:right; font-weight:900; color:var(--crm-purple);"><?= $role['count'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 2rem; margin-bottom: 2rem;">
        <div class="glass-panel">
            <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem;"><i class="fa-solid fa-money-bill-trend-up"></i> Média Salarial por Setor</h3>
            <div style="height: 300px;"><canvas id="rhAvgSalaryChart"></canvas></div>
        </div>
        <div class="glass-panel">
            <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem;"><i class="fa-solid fa-notes-medical"></i> Tendência de Atestados</h3>
            <div style="height: 300px;"><canvas id="rhCertTrendChart"></canvas></div>
        </div>
    </div>

    <!-- Aniversariantes e Top 10 -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
        <div class="glass-panel">
            <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem;"><i class="fa-solid fa-cake-candles" style="color: #EC4899;"></i> Aniversariantes de <?= date('F') ?></h3>
            <?php if (empty($rhBirthdaysMonth)): ?>
                <p style="color: #64748b; text-align: center; padding: 2rem;">Nenhum aniversário este mês.</p>
            <?php else: ?>
                <table style="width: 100%; border-collapse: collapse;">
                    <?php foreach ($rhBirthdaysMonth as $bday): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 0.75rem 0; font-weight: 700; color: #0F172A;"><?= htmlspecialchars($bday['name']) ?></td>
                        <td style="padding: 0.75rem 0; color: #64748b; font-size: 0.85rem;"><?= htmlspecialchars($bday['sector']) ?></td>
                        <td style="padding: 0.75rem 0; text-align: right; font-weight: 900; color: #EC4899;"><?= date('d/m', strtotime($bday['birth_date'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
             <?php endif; ?>
        </div>
        <div class="glass-panel">
            <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem;"><i class="fa-solid fa-user-tag"></i> Top 10 Cargos</h3>
            <div style="max-height: 300px; overflow-y:auto;">
                <table style="width:100%; border-collapse: collapse; font-size:0.85rem;">
                    <?php foreach($rhRoles as $role): ?>
                    <tr style="border-bottom:1px solid #f1f5f9;">
                        <td style="padding:0.5rem 0; font-weight:700; color:#334155;"><?= htmlspecialchars($role['role_name']) ?></td>
                        <td style="padding:0.5rem 0; text-align:right; font-weight:900; color:var(--crm-purple);"><?= $role['count'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

    <div class="glass-panel" style="margin-bottom: 2rem;">
        <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem;"><i class="fa-solid fa-chart-line"></i> Fluxo de Pessoas (Admissões vs Demissões)</h3>
        <div style="height: 300px;"><canvas id="rhFlowChart"></canvas></div>
    </div>

    <div class="glass-panel">
        <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem;"><i class="fa-solid fa-building-user"></i> Visão por Setor (Pessoas e Custos)</h3>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="text-align: left; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">Setor</th>
                    <th style="padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; text-align:center;">Total</th>
                    <th style="padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; text-align:center;">Ativos</th>
                    <th style="text-align: right; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; border-bottom: 2px solid #e2e8f0;">Custo Mensal ($)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rhSectorStats as $st): ?>
                <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0F172A;"><?= htmlspecialchars($st['sector'] ?: 'Sem Setor') ?></td>
                    <td style="padding: 0.75rem 1rem; text-align: center; font-weight: 700; color: #64748b;"><?= $st['total_users'] ?></td>
                    <td style="padding: 0.75rem 1rem; text-align: center;">
                        <span style="background: rgba(16, 185, 129, 0.1); color: #10B981; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 700;">
                            <?= $st['active_users'] ?> ativos
                        </span>
                    </td>
                    <td style="padding: 0.75rem 1rem; text-align: right; font-weight: 900; color: var(--crm-purple);">R$ <?= number_format($st['total_salary'], 2, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f8fafc;">
                    <td style="padding: 1rem; font-weight: 900; color: #0F172A;">TOTAL GERAL</td>
                    <td style="text-align: center; font-weight: 900;"><?= $totalUsers ?></td>
                    <td></td>
                    <td style="text-align: right; font-weight: 900; color: var(--crm-purple); font-size: 1.1rem;">R$ <?= number_format(array_sum(array_column($rhSectorStats, 'total_salary')), 2, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- ======================== SCRIPTS ======================== -->
<script>
    function switchTab(tab) {
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('content-' + tab).classList.add('active');
        document.getElementById('tab-' + tab).classList.add('active');
    }

    const chartOptions = {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } }
    };

    // ===== GRÁFICOS CHAMADOS =====
    if(document.getElementById('chamadosStatusChart')) new Chart(document.getElementById('chamadosStatusChart'), {
        type: 'doughnut',
        data: {
            labels: ['Solucionados', 'Pendenciados', 'Abertos', 'Sem Solução'],
            datasets: [{ 
                data: [<?= $chSolucionados ?>, <?= $chPendentes ?>, <?= $chAbertos ?>, <?= $chSemSolucao ?>], 
                backgroundColor: ['#10B981', '#F59E0B', '#3B82F6', '#EF4444'] 
            }]
        },
        options: { ...chartOptions, plugins: { legend: { position: 'right' } } }
    });

    if(document.getElementById('chamadosSectorChart')) new Chart(document.getElementById('chamadosSectorChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($chBySector, 'sector')) ?: '[]' ?>,
            datasets: [{
                label: 'Chamados',
                data: <?= json_encode(array_column($chBySector, 'count')) ?: '[]' ?>,
                backgroundColor: '#6366F1',
                borderRadius: 4
            }]
        },
        options: { ...chartOptions, indexAxis: 'y', plugins: { legend: { display: false } } }
    });

    if(document.getElementById('chamadosUsersChart')) new Chart(document.getElementById('chamadosUsersChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($chTopUsers, 'name')) ?: '[]' ?>,
            datasets: [{
                label: 'Chamados',
                data: <?= json_encode(array_column($chTopUsers, 'count')) ?: '[]' ?>,
                backgroundColor: '#8B5CF6',
                borderRadius: 4
            }]
        },
        options: { ...chartOptions, indexAxis: 'y', plugins: { legend: { display: false } } }
    });

    if(document.getElementById('chamadosTrendChart')) new Chart(document.getElementById('chamadosTrendChart'), {
        type: 'line',
        data: {
            labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
            datasets: [
                { label: 'Ano Atual (<?= date('Y') ?>)', data: <?= json_encode(array_values($chCurrentYear)) ?>, borderColor: '#3B82F6', backgroundColor: 'rgba(59, 130, 246, 0.1)', fill: true, tension: 0.4 },
                { label: 'Ano Anterior (<?= date('Y')-1 ?>)', data: <?= json_encode(array_values($chLastYear)) ?>, borderColor: '#94a3b8', borderDash: [5, 5], fill: false, tension: 0.4 }
            ]
        },
        options: chartOptions
    });

    // ===== GRÁFICOS PATRIMÔNIO =====
    if(document.getElementById('patrimonioStatusChart')) new Chart(document.getElementById('patrimonioStatusChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($assetsByStatus, 'status')) ?>,
            datasets: [{ data: <?= json_encode(array_column($assetsByStatus, 'count')) ?>, backgroundColor: ['#10B981', '#F59E0B', '#64748b', '#6366F1'] }]
        },
        options: {
            ...chartOptions,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let value = context.parsed;
                            let total = context.dataset.data.reduce((a, b) => a + b, 0);
                            let percentage = ((value / total) * 100).toFixed(1);
                            return context.label + ': ' + value + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });

    if(document.getElementById('patrimonioCategoryChart')) new Chart(document.getElementById('patrimonioCategoryChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($assetsByCategory, 'category')) ?>,
            datasets: [{ data: <?= json_encode(array_column($assetsByCategory, 'count')) ?>, backgroundColor: ['#5B21B6','#3B82F6','#10B981','#F59E0B','#EF4444','#6366F1','#EC4899','#14B8A6'] }]
        },
        options: {
            ...chartOptions,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let value = context.parsed;
                            let total = context.dataset.data.reduce((a, b) => a + b, 0);
                            let percentage = ((value / total) * 100).toFixed(1);
                            return context.label + ': ' + value + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });

    if(document.getElementById('patrimonioSectorChart')) new Chart(document.getElementById('patrimonioSectorChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($assetsBySector, 'sector')) ?>,
            datasets: [{
                label: 'Equipamentos',
                data: <?= json_encode(array_column($assetsBySector, 'count')) ?>,
                backgroundColor: <?= json_encode(array_map(function($i) { $c = ['#5B21B6','#3B82F6','#10B981','#F59E0B','#EF4444','#6366F1','#EC4899','#14B8A6']; return $c[$i % count($c)]; }, array_keys($assetsBySector))) ?>,
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: { 
            ...chartOptions, 
            indexAxis: 'y', 
            plugins: { 
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            let value = context.parsed.x;
                            let total = context.dataset.data.reduce((a, b) => a + b, 0);
                            let percentage = ((value / total) * 100).toFixed(1);
                            return label + ': ' + value + ' (' + percentage + '%)';
                        }
                    }
                }
            } 
        }
    });

    // ===== GRÁFICOS VOLUNTARIADO =====
    if(document.getElementById('sexChart')) new Chart(document.getElementById('sexChart'), { type: 'pie', data: { labels: <?= json_encode($sexLabels) ?>, datasets: [{ data: <?= json_encode($sexData) ?>, backgroundColor: ['#3B82F6', '#EC4899', '#94A3B8'] }] }, options: chartOptions });
    if(document.getElementById('workTypeChart')) new Chart(document.getElementById('workTypeChart'), { type: 'pie', data: { labels: <?= json_encode($typeLabels) ?>, datasets: [{ data: <?= json_encode($typeData) ?>, backgroundColor: ['#5B21B6', '#10B981', '#F59E0B'] }] }, options: chartOptions });
    if(document.getElementById('volStatusChart')) new Chart(document.getElementById('volStatusChart'), { type: 'doughnut', data: { labels: <?= json_encode($statusLabels) ?>, datasets: [{ data: <?= json_encode($statusData) ?>, backgroundColor: ['#10B981', '#EF4444'] }] }, options: chartOptions });

    // ===== GRÁFICO FINANCEIRO =====
    if(document.getElementById('financeChart')) new Chart(document.getElementById('financeChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode($financialMonths) ?>,
            datasets: [{
                label: 'Valor Arrecadado (R$)',
                data: <?= json_encode($financialData) ?>,
                borderColor: '#10B981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                fill: true, tension: 0.4, pointRadius: 6, pointHoverRadius: 8
            }]
        },
        options: { ...chartOptions, scales: { y: { beginAtZero: true, ticks: { callback: (val) => 'R$ ' + val.toLocaleString('pt-BR') } } } }
    });

    // ===== GRÁFICOS EMPRÉSTIMOS =====
    if(document.getElementById('loansBySectorChart')) new Chart(document.getElementById('loansBySectorChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($loansBySector, 'sector')) ?>,
            datasets: [{ label: 'Quantidade', data: <?= json_encode(array_column($loansBySector, 'count')) ?>, backgroundColor: '#6366F1' }]
        },
        options: { 
            ...chartOptions, 
            indexAxis: 'y',
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let value = context.parsed.x;
                            let total = context.dataset.data.reduce((a, b) => a + b, 0);
                            let percentage = ((value / total) * 100).toFixed(1);
                            return 'Quantidade: ' + value + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });

    if(document.getElementById('loansByCategoryChart')) new Chart(document.getElementById('loansByCategoryChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($loansByCategory, 'category')) ?>,
            datasets: [{ data: <?= json_encode(array_column($loansByCategory, 'count')) ?>, backgroundColor: ['#5B21B6', '#10B981', '#F59E0B', '#3B82F6', '#EF4444', '#64748b'] }]
        },
        options: {
            ...chartOptions,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let value = context.parsed;
                            let total = context.dataset.data.reduce((a, b) => a + b, 0);
                            let percentage = ((value / total) * 100).toFixed(1);
                            return context.label + ': ' + value + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });

    if(document.getElementById('loansTrendChart')) new Chart(document.getElementById('loansTrendChart'), {
        type: 'line',
        data: {
            labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
            datasets: [
                { label: 'Ano Atual (<?= date('Y') ?>)', data: <?= json_encode(array_values($loansCurrentYear)) ?>, borderColor: '#5B21B6', backgroundColor: 'rgba(91, 33, 182, 0.1)', fill: true, tension: 0.4 },
                { label: 'Ano Anterior (<?= date('Y')-1 ?>)', data: <?= json_encode(array_values($loansLastYear)) ?>, borderColor: '#94a3b8', borderDash: [5, 5], fill: false, tension: 0.4 }
            ]
        },
        options: chartOptions
    });

    // ===== CHART USUÁRIOS POR EMPRÉSTIMO =====
    const userChartRaw = <?= json_encode($userChartData) ?>;
    const totalCurrentYearLoans = <?= $totalCurrentYear ?>;
    const monthNames = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
    let userBarChart = null;

    function filterUserChart(mode) {
        const btnMonth = document.getElementById('btn-month');
        const btnYear = document.getElementById('btn-year');
        const monthSel = document.getElementById('month-selector');

        if (mode === 'month') {
            btnMonth.style.borderColor = 'var(--crm-purple)'; btnMonth.style.color = 'var(--crm-purple)'; btnMonth.style.background = 'rgba(91,33,182,0.05)';
            btnYear.style.borderColor = ''; btnYear.style.color = ''; btnYear.style.background = '';
            monthSel.style.display = 'inline-block';
        } else {
            btnYear.style.borderColor = 'var(--crm-purple)'; btnYear.style.color = 'var(--crm-purple)'; btnYear.style.background = 'rgba(91,33,182,0.05)';
            btnMonth.style.borderColor = ''; btnMonth.style.color = ''; btnMonth.style.background = '';
            monthSel.style.display = 'none';
        }

        const selectedMonth = parseInt(monthSel.value);
        let data = userChartRaw.map(u => ({
            name: u.name,
            value: mode === 'month' ? u.months[selectedMonth - 1] : u.total,
            total: u.total,
            last_year: u.last_year
        })).filter(u => u.value > 0).sort((a, b) => b.value - a.value).slice(0, 15);

        const total = data.reduce((s, u) => s + u.value, 0) || 1;
        const tbody = document.getElementById('userRankingBody');
        tbody.innerHTML = '';
        data.forEach((u, i) => {
            const pct = ((u.value / total) * 100).toFixed(1);
            let trend = '';
            if (mode === 'year') {
                const diff = u.total - u.last_year;
                trend = diff > 0 ? `<span style="color:#10B981;font-weight:700;">▲ +${diff}</span>` : (diff < 0 ? `<span style="color:#ef4444;font-weight:700;">▼ ${diff}</span>` : `<span style="color:#64748b;">= 0</span>`);
            } else {
                trend = '<span style="color:#94a3b8;font-size:0.75rem;">—</span>';
            }
            const rankColor = i===0?'#EAB308':i===1?'#94A3B8':i===2?'#CD7C2F':'var(--crm-purple)';
            tbody.innerHTML += `
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:0.75rem 1rem; font-weight:900; color:${rankColor}; font-size:${i<3?'1.1rem':'0.9rem'}">${i+1}</td>
                    <td style="padding:0.75rem 1rem; font-weight:700; color:#0F172A;">${u.name}</td>
                    <td style="padding:0.75rem 1rem; text-align:center; font-weight:900; color:var(--crm-purple); font-size:1.1rem;">${u.value}</td>
                    <td style="padding:0.75rem 1rem; font-weight:700; color:#334155;">${pct}%</td>
                    <td style="padding:0.75rem 1rem;">${trend}</td>
                    <td style="padding:0.75rem 1rem; min-width:120px;">
                        <div style="background:#e2e8f0;border-radius:4px;height:8px;overflow:hidden;">
                            <div style="width:${pct}%;height:100%;background:linear-gradient(90deg,#5B21B6,#7C3AED);border-radius:4px;transition:width 0.6s ease;"></div>
                        </div>
                    </td>
                </tr>`;
        });

        const chartEl = document.getElementById('loansByUserChart');
        if (chartEl) {
            if (userBarChart) userBarChart.destroy();
            const colors = data.map((_, i) => `hsl(${260 - i * 14}, 70%, ${45 + i * 2}%)`);
            userBarChart = new Chart(chartEl, {
                type: 'bar',
                data: {
                    labels: data.map(u => u.name),
                    datasets: [{
                        label: mode === 'month' ? `Empréstimos em ${monthNames[selectedMonth-1]}` : 'Empréstimos no Ano',
                        data: data.map(u => u.value),
                        backgroundColor: colors,
                        borderRadius: 6, borderSkipped: false
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, indexAxis: 'y',
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => { const p = ((ctx.raw / total) * 100).toFixed(1); return ` ${ctx.raw} empréstimos (${p}%)`; } } } },
                    scales: { x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: 'rgba(0,0,0,0.05)' } }, y: { ticks: { font: { weight: '700' } } } }
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (document.getElementById('loansByUserChart')) filterUserChart('month');

        // ===== GRÁFICOS RH (Inicializados após o DOM) =====
        if(document.getElementById('rhContractChart')) new Chart(document.getElementById('rhContractChart'), {
            type: 'pie',
            data: {
                labels: <?= json_encode($rhContractLabels) ?>,
                datasets: [{ data: <?= json_encode($rhContractData) ?>, backgroundColor: ['#5B21B6','#3B82F6','#10B981','#F59E0B','#EF4444','#6366F1'] }]
            },
            options: chartOptions
        });

        if(document.getElementById('rhGenderChart')) new Chart(document.getElementById('rhGenderChart'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($rhGenderLabels) ?>,
                datasets: [{ data: <?= json_encode($rhGenderData) ?>, backgroundColor: ['#3B82F6','#EC4899','#94A3B8'] }]
            },
            options: chartOptions
        });

        if(document.getElementById('rhFlowChart')) new Chart(document.getElementById('rhFlowChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_values($rhYears)) ?>,
                datasets: [
                    { label: 'Admissões', data: <?= json_encode($rhAdmSeries) ?>, backgroundColor: '#10B981', borderRadius: 4 },
                    { label: 'Demissões', data: <?= json_encode($rhTermSeries) ?>, backgroundColor: '#EF4444', borderRadius: 4 }
                ]
            },
            options: { ...chartOptions, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
        });

        if(document.getElementById('rhAvgSalaryChart')) new Chart(document.getElementById('rhAvgSalaryChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($rhAvgSalarySector, 'sector')) ?>,
                datasets: [{ label: 'Média Salarial (R$)', data: <?= json_encode(array_column($rhAvgSalarySector, 'avg_salary')) ?>, backgroundColor: '#5B21B6', borderRadius: 6 }]
            },
            options: { ...chartOptions, indexAxis: 'y', scales: { x: { ticks: { callback: (v) => 'R$ ' + v } } } }
        });

        if(document.getElementById('rhCertTrendChart')) new Chart(document.getElementById('rhCertTrendChart'), {
            type: 'line',
            data: { labels: <?= json_encode($rhCertLabels) ?>, datasets: [{ label: 'Atestados', data: <?= json_encode($rhCertData) ?>, borderColor: '#EF4444', backgroundColor: 'rgba(239, 68, 68, 0.1)', fill: true, tension: 0.4 }] },
            options: chartOptions
        });

        // ===== GRÁFICOS SLA CHAMADOS =====
        const slaMonthData = <?= json_encode(array_values($slaByMonth)) ?>;
        const slaMonthLabels = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];

        if(document.getElementById('slaMonthChart')) new Chart(document.getElementById('slaMonthChart'), {
            type: 'line',
            data: {
                labels: slaMonthLabels,
                datasets: [{
                    label: 'SLA Médio (horas)',
                    data: slaMonthData,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245,158,11,0.1)',
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: slaMonthData.map(v => v === null ? 'transparent' : (v <= 4 ? '#10b981' : v <= 12 ? '#f59e0b' : '#ef4444')),
                    pointBorderColor: slaMonthData.map(v => v === null ? 'transparent' : (v <= 4 ? '#10b981' : v <= 12 ? '#f59e0b' : '#ef4444')),
                    pointRadius: 6,
                    spanGaps: true
                }]
            },
            options: {
                ...chartOptions,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ctx.raw !== null ? `SLA: ${ctx.raw}h` : 'Sem dados'
                        }
                    }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: v => v + 'h' }, grid: { color: 'rgba(0,0,0,0.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });

        const slaSectorLabels = <?= json_encode(array_column($slaBySector, 'sector')) ?>;
        const slaSectorValues = <?= json_encode(array_column($slaBySector, 'avg_hours')) ?>;
        const slaSectorColors = slaSectorValues.map(v => v <= 4 ? '#10b981' : v <= 12 ? '#f59e0b' : '#ef4444');

        if(document.getElementById('slaSectorChart')) new Chart(document.getElementById('slaSectorChart'), {
            type: 'bar',
            data: {
                labels: slaSectorLabels,
                datasets: [{
                    label: 'SLA Médio (horas)',
                    data: slaSectorValues,
                    backgroundColor: slaSectorColors,
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                ...chartOptions,
                indexAxis: 'y',
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => `SLA: ${ctx.raw}h` } }
                },
                scales: {
                    x: { beginAtZero: true, ticks: { callback: v => v + 'h' }, grid: { color: 'rgba(0,0,0,0.05)' } },
                    y: { ticks: { font: { weight: '700', size: 11 } } }
                }
            }
        });

    });

</script>
