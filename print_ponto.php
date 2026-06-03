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

// Buscar nome do funcionario e empresa
$compId = getCurrentUserCompanyId();
$stmt = $pdo->prepare("SELECT name FROM users WHERE id = ? AND company_id = ?");
$stmt->execute([$user_id, $compId]);
$targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$targetUser) {
    die("Funcionário não encontrado.");
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Ponto - <?= htmlspecialchars($targetUser['name']) ?> (<?= $month ?>/<?= $year ?>)</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #ffffff;
            padding: 2rem;
            color: #1e293b;
        }
        .print-header {
            text-align: center;
            margin-bottom: 2rem;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 1rem;
        }
        .print-header h1 {
            font-size: 1.8rem;
            font-weight: 900;
            color: var(--brand-primary);
            margin: 0 0 0.5rem 0;
        }
        .print-header p {
            color: #64748b;
            font-size: 1rem;
            margin: 0;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .summary-card {
            border: 1px solid #e2e8f0;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        .summary-card h4 {
            font-size: 0.7rem;
            color: #64748b;
            text-transform: uppercase;
            font-weight: 800;
            margin-bottom: 0.5rem;
            margin-top: 0;
        }
        .summary-card div {
            font-size: 1.5rem;
            font-weight: 900;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #e2e8f0;
            padding: 0.75rem;
            text-align: center;
        }
        th {
            background: #f8fafc;
            font-size: 0.75rem;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
        }
        td {
            font-weight: 600;
            color: #334155;
        }
        .punch-badge {
            display: inline-block;
            color: white;
            padding: 3px 8px;
            border-radius: 99px;
            font-size: 0.7rem;
            font-weight: 800;
            margin: 2px;
        }
        @media print {
            body { margin: 0; padding: 0; background: white; }
            @page { margin: 1cm; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div id="loading" style="text-align:center; padding: 3rem; font-size:1.2rem; color:#64748b;">
        <i class="fa-solid fa-spinner fa-spin"></i> Gerando Relatório...
    </div>

    <div id="content" style="display:none;">
        <div class="print-header">
            <h1>Relatório de Ponto Individual</h1>
            <p><strong>Funcionário:</strong> <?= htmlspecialchars($targetUser['name']) ?> | <strong>Competência:</strong> <?= $month ?>/<?= $year ?></p>
        </div>

        <div class="summary-grid">
            <div class="summary-card">
                <h4>Total Trabalhado</h4>
                <div id="lbl_total_worked" style="color: var(--crm-purple);">00:00</div>
            </div>
            <div class="summary-card">
                <h4>Meta Diária</h4>
                <div id="lbl_daily_goal" style="color: #3b82f6;">00:00</div>
            </div>
            <div id="card_balance" class="summary-card">
                <h4>Saldo do Mês (Ext/Def)</h4>
                <div id="lbl_total_balance">00:00</div>
            </div>
            <div class="summary-card">
                <h4>Dias Trabalhados</h4>
                <div id="lbl_days_worked" style="color: #10b981;">0</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="text-align: left;">Data</th>
                    <th>Registro de Batidas</th>
                    <th>Horas Feitas</th>
                    <th>Saldo Diário (Extra/Déficit)</th>
                </tr>
            </thead>
            <tbody id="table_body">
            </tbody>
        </table>
        
        <div style="margin-top:2rem; font-size:0.75rem; color:#94a3b8; text-align:center;">
            Relatório gerado em <?= date('d/m/Y \à\s H:i:s') ?> pelo sistema Cetusg Plus.
        </div>
    </div>

    <script>
        const userId = '<?= htmlspecialchars($user_id) ?>';
        const month = '<?= htmlspecialchars($month) ?>';
        const year = '<?= htmlspecialchars($year) ?>';

        fetch(`api_rh_ponto.php?action=get_monthly_report&user_id=${userId}&month=${month}&year=${year}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById('loading').style.display = 'none';
            
            if(data.error) {
                alert(data.error);
                return;
            }

            document.getElementById('content').style.display = 'block';
            
            // Preencher Resumos
            document.getElementById('lbl_total_worked').innerText = data.summary.total_worked_hours;
            document.getElementById('lbl_daily_goal').innerText = data.daily_goal;
            document.getElementById('lbl_days_worked').innerText = data.summary.days_worked;
            
            const balDiv = document.getElementById('lbl_total_balance');
            balDiv.innerText = data.summary.total_balance;
            
            const cardBal = document.getElementById('card_balance');
            if(data.summary.is_balance_positive && data.summary.total_balance !== '00:00') {
                balDiv.style.color = '#10b981';
                cardBal.style.borderColor = '#10b981';
            } else if (!data.summary.is_balance_positive) {
                balDiv.style.color = '#ef4444';
                cardBal.style.borderColor = '#ef4444';
            } else {
                balDiv.style.color = '#64748b';
            }

            // Preencher Tabela
            const tbody = document.getElementById('table_body');
            tbody.innerHTML = '';
            
            if(data.days.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:3rem; color:#94a3b8;">Nenhuma batida registrada neste mês.</td></tr>`;
            } else {
                data.days.forEach(d => {
                    const parts = d.date.split('-');
                    const displayDate = `${parts[2]}/${parts[1]}/${parts[0]}`;
                    
                    let punchesHtml = '<div style="display:flex; justify-content:center; flex-wrap:wrap;">';
                    d.punches.forEach(p => {
                        const timeOnly = p.record_time.split(' ')[1].substring(0, 5);
                        let color = '#3b82f6';
                        if (p.record_type === 'Entrada') color = '#10b981';
                        if (p.record_type === 'Saida Almoco') color = '#f59e0b';
                        if (p.record_type === 'Retorno Almoco') color = '#f59e0b';
                        if (p.record_type === 'Saida') color = '#ef4444';
                        
                        punchesHtml += `
                            <span class="punch-badge" style="background:${color};">
                                ${p.record_type}: ${timeOnly}
                            </span>
                        `;
                    });
                    punchesHtml += '</div>';

                    const ws = d.worked_seconds;
                    const wh = Math.floor(ws / 3600);
                    const wm = Math.floor((ws % 3600) / 60);
                    const workedStr = `${wh.toString().padStart(2, '0')}:${wm.toString().padStart(2, '0')}`;
                    
                    const bs = d.balance_seconds;
                    const sign = bs < 0 ? '-' : (bs > 0 ? '+' : '');
                    const absBs = Math.abs(bs);
                    const bh = Math.floor(absBs / 3600);
                    const bm = Math.floor((absBs % 3600) / 60);
                    const balStr = `${sign}${bh.toString().padStart(2, '0')}:${bm.toString().padStart(2, '0')}`;
                    const balColor = bs > 0 ? '#10b981' : (bs < 0 ? '#ef4444' : '#64748b');

                    tbody.innerHTML += `
                        <tr>
                            <td style="text-align:left; font-weight:800; color:#1e293b;">${displayDate}</td>
                            <td>${punchesHtml}</td>
                            <td style="color:#334155;">${workedStr}</td>
                            <td style="font-weight:800; color:${balColor};">${balStr}</td>
                        </tr>
                    `;
                });
            }

            // Invoca a janela de impressão automaticamente e fecha após a impressão
            setTimeout(() => {
                window.print();
            }, 500);
        })
        .catch(err => {
            document.getElementById('loading').innerHTML = 'Erro de conexão com o servidor.';
        });
    </script>
</body>
</html>
