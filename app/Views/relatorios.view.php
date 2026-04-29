<?php
/**
 * RELATORIOS VIEW
 */
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="page-header">
    <div class="page-header-info">
        <div class="page-header-icon">
            <i class="fa-solid fa-chart-line"></i>
        </div>
        <div class="page-header-text">
            <h2>Inteligência de Dados</h2>
            <p>Indicadores de performance, estatísticas e métricas gerenciais em tempo real.</p>
        </div>
    </div>
    <div class="page-header-actions no-print">
        <button class="btn-secondary" onclick="window.print()">
            <i class="fa-solid fa-print"></i> Imprimir Relatório
        </button>
    </div>
</div>

<div class="stat-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
    <div class="glass-panel" style="border-left: 4px solid #6366f1;">
        <div style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Total de Ativos</div>
        <div style="font-size: 1.5rem; font-weight: 900;"><?= $totalAssets ?></div>
    </div>
    <div class="glass-panel" style="border-left: 4px solid #f59e0b;">
        <div style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Chamados</div>
        <div style="font-size: 1.5rem; font-weight: 900;"><?= $totalTickets ?></div>
    </div>
    <div class="glass-panel" style="border-left: 4px solid #10b981;">
        <div style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Voluntários</div>
        <div style="font-size: 1.5rem; font-weight: 900;"><?= $totalVolunteers ?></div>
    </div>
    <div class="glass-panel" style="border-left: 4px solid #3b82f6;">
        <div style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase;">Horas Vol.</div>
        <div style="font-size: 1.5rem; font-weight: 900;"><?= number_format($totalHours, 0) ?>h</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
    <div class="glass-panel" style="padding: 1.5rem;">
        <h3 style="font-size: 1rem; font-weight: 900; margin-bottom: 1.5rem;"><i class="fa-solid fa-stopwatch" style="color: #f59e0b;"></i> Performance SLA (Média)</h3>
        <div style="text-align: center; padding: 2rem;">
            <div style="font-size: 3rem; font-weight: 900; color: #f59e0b;"><?= $slaAvgTotal ?: '0' ?>h</div>
            <p style="color: #64748b; font-size: 0.85rem;">Tempo médio para solução de chamados técnicos.</p>
        </div>
    </div>
    <div class="glass-panel" style="padding: 1.5rem;">
        <h3 style="font-size: 1rem; font-weight: 900; margin-bottom: 1.5rem;"><i class="fa-solid fa-chart-pie" style="color: #6366f1;"></i> Distribuição de Ativos</h3>
        <canvas id="assetsChart" height="200"></canvas>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('assetsChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Ativos', 'Manutenção', 'Baixados'],
                datasets: [{
                    data: [<?= $totalAssets ?>, 0, 0],
                    backgroundColor: ['#6366f1', '#f59e0b', '#ef4444'],
                    borderWidth: 0
                }]
            },
            options: {
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    });
</script>
