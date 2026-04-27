<?php
/**
 * DASHBOARD VIEW
 * ===============
 */
?>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<div class="page-header" style="background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); padding: 2.5rem; border-radius: 1.5rem; color: white; margin-bottom: 2.5rem; box-shadow: 0 20px 40px rgba(99, 102, 241, 0.2);">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="font-size: 2.5rem; font-weight: 900; margin: 0; letter-spacing: -1px; display: flex; align-items: center; gap: 1rem;">
                <i class="fa-solid fa-chart-line"></i>
                Dashboard Estratégico
            </h1>
            <p style="opacity: 0.9; font-size: 1.1rem; margin-top: 0.5rem;">Bem-vindo ao centro de inteligência do Cetusg.</p>
        </div>
        <div style="background: rgba(255,255,255,0.2); padding: 1rem 1.5rem; border-radius: 1rem; backdrop-filter: blur(10px); text-align: right;">
            <div style="font-size: 0.8rem; text-transform: uppercase; font-weight: 800; opacity: 0.8;">Data de Hoje</div>
            <div style="font-size: 1.25rem; font-weight: 900;"><?= date('d/m/Y') ?></div>
        </div>
    </div>
</div>

<div class="stat-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
    
    <!-- KPI 1: Financeiro Voluntariado -->
    <div class="glass-panel" style="border-left: 6px solid #10b981;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <div style="color: #64748b; font-weight: 800; font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.5rem;">Retorno Financeiro</div>
                <div style="font-size: 1.75rem; font-weight: 900; color: #065f46;">R$ <?= number_format($financialReturn, 2, ',', '.') ?></div>
            </div>
            <div style="width: 48px; height: 48px; background: #ecfdf5; color: #10b981; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                <i class="fa-solid fa-coins"></i>
            </div>
        </div>
        <div style="margin-top: 1.5rem; font-size: 0.875rem; color: #64748b;">
            <i class="fa-solid fa-arrow-up" style="color: #10b981;"></i> Economia gerada por <strong><?= number_format($totalHours, 1) ?>h</strong> voluntárias.
        </div>
    </div>

    <!-- KPI 2: Ocupação de Salas -->
    <div class="glass-panel" style="border-left: 6px solid #3b82f6;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <div style="color: #64748b; font-weight: 800; font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.5rem;">Ocupação de Salas</div>
                <div style="font-size: 1.75rem; font-weight: 900; color: #1e40af;"><?= number_format($occupancyRate, 1) ?>%</div>
            </div>
            <div style="width: 48px; height: 48px; background: #eff6ff; color: #3b82f6; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                <i class="fa-solid fa-door-open"></i>
            </div>
        </div>
        <div style="margin-top: 1.5rem;">
            <div style="width: 100%; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden;">
                <div style="width: <?= $occupancyRate ?>%; height: 100%; background: #3b82f6;"></div>
            </div>
            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;">Total de <strong><?= $totalBookings ?></strong> locações registradas.</div>
        </div>
    </div>

    <!-- KPI 3: Chamados Abertos -->
    <div class="glass-panel" style="border-left: 6px solid #f59e0b;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <div style="color: #64748b; font-weight: 800; font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.5rem;">Chamados Ativos</div>
                <div style="font-size: 1.75rem; font-weight: 900; color: #b45309;"><?= $openTickets ?></div>
            </div>
            <div style="width: 48px; height: 48px; background: #fffbeb; color: #f59e0b; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                <i class="fa-solid fa-ticket"></i>
            </div>
        </div>
        <div style="margin-top: 1.5rem; font-size: 0.875rem; color: #64748b;">
            Aguardando atendimento técnico imediato.
        </div>
    </div>

</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; align-items: start;">
    
    <!-- Gráfico Principal -->
    <div class="glass-panel" style="padding: 2rem;">
        <h3 style="font-size: 1.25rem; font-weight: 900; margin-bottom: 2rem; color: #1e293b; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-chart-simple" style="color: #6366f1;"></i>
            Demanda por Salas (Top 5)
        </h3>
        <div id="roomsChart"></div>
    </div>

    <!-- Timeline de Atividades Recentes -->
    <div class="glass-panel" style="padding: 2rem;">
        <h3 style="font-size: 1.25rem; font-weight: 900; margin-bottom: 2rem; color: #1e293b; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-clock-rotate-left" style="color: #a855f7;"></i>
            Atividades Recentes
        </h3>
        <div class="activity-timeline">
            <?php foreach ($recentLogs as $log): ?>
                <div style="padding-left: 1.5rem; border-left: 2px solid #e2e8f0; position: relative; padding-bottom: 1.5rem;">
                    <div style="width: 12px; height: 12px; background: #fff; border: 3px solid #6366f1; border-radius: 50%; position: absolute; left: -7px; top: 0;"></div>
                    <div style="font-size: 0.75rem; color: #94a3b8; font-weight: 700; margin-bottom: 0.25rem;"><?= date('H:i', strtotime($log['created_at'])) ?> - <?= htmlspecialchars($log['user_name']) ?></div>
                    <div style="font-size: 0.9rem; color: #334155; font-weight: 600;"><?= htmlspecialchars($log['action']) ?></div>
                    <div style="font-size: 0.8rem; color: #64748b;"><?= htmlspecialchars($log['module']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <button class="btn-secondary" style="width: 100%; margin-top: 1rem; font-size: 0.8rem;">Ver todos os logs</button>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Dados do gráfico de salas
        const salaLabels = <?= json_encode(array_column($bookingsByRoom, 'name')) ?>;
        const salaData = <?= json_encode(array_column($bookingsByRoom, 'count')) ?>;

        var options = {
            series: [{
                name: 'Locações',
                data: salaData
            }],
            chart: {
                type: 'bar',
                height: 350,
                toolbar: { show: false },
                fontFamily: 'inherit'
            },
            plotOptions: {
                bar: {
                    borderRadius: 10,
                    columnWidth: '50%',
                    distributed: true
                }
            },
            dataLabels: { enabled: false },
            colors: ['#6366f1', '#8b5cf6', '#a855f7', '#d946ef', '#f43f5e'],
            xaxis: {
                categories: salaLabels,
                axisBorder: { show: false },
                axisTicks: { show: false }
            },
            grid: {
                borderColor: '#f1f5f9',
                strokeDashArray: 4
            },
            legend: { show: false }
        };

        var chart = new ApexCharts(document.querySelector("#roomsChart"), options);
        chart.render();
    });
</script>
