<?php
// Permitir acesso global ao Dashboard (compatibilidade)
$isAdmin = true;
$isSectorManager = true;
$isSupport = true;
$isCollaborator = false;

// Preparação de Dados para KPIs e Gráficos

// 1. Locação de Salas (Soliicitado pelo Usuário)
try {
    $totalRooms = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn() ?: 0;
    $totalBookings = $pdo->query("SELECT COUNT(*) FROM room_bookings")->fetchColumn() ?: 0;
    $bookingsByRoom = $pdo->query("SELECT r.name, COUNT(b.id) as count FROM room_bookings b JOIN rooms r ON b.room_id = r.id GROUP BY r.id LIMIT 5")->fetchAll();
    
    // Percentual de ocupação (simplificado: reservas / (salas * 30 dias)) ou similar
    // Vamos usar (Reservas Realizadas / (Salas * 10)) como uma métrica de densidade para o gráfico/valor %
    $occupancyRate = $totalRooms > 0 ? ($totalBookings / ($totalRooms * 5)) * 100 : 0;
    if ($occupancyRate > 100) $occupancyRate = 100;
} catch(Exception $e) { 
    $totalRooms = 0; 
    $totalBookings = 0; 
    $bookingsByRoom = []; 
    $occupancyRate = 0;
}

// 2. Voluntariado / Retorno Financeiro (Solicitado pelo Usuário)
try {
    $totalVolunteers = $pdo->query("SELECT COUNT(*) FROM volunteers")->fetchColumn() ?: 0;
    $totalHours = $pdo->query("SELECT SUM(total_hours) as total FROM volunteers")->fetchColumn() ?: 0;
    $financialReturn = $pdo->query("SELECT SUM(total_hours * hourly_rate) as total FROM volunteers")->fetchColumn() ?: 0;
} catch(Exception $e) { 
    $totalVolunteers = 0; 
    $totalHours = 0; 
    $financialReturn = 0; 
}

// 3. Chamados (Mínimo necessário para contexto ou conforme solicitado para manter o que "já está no sistema" mas focar no novo)
$openTickets = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'Aberto'")->fetchColumn() ?: 0;

?>

<!-- Header -->
<div class="card" style="margin-bottom: 24px; background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: white; border: none; box-shadow: 0 10px 30px rgba(79, 70, 229, 0.3);">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="font-size: 2.25rem; font-weight: 900; display: flex; align-items: center; gap: 16px; margin: 0; letter-spacing: -1px;">
                <i class="fa-solid fa-gauge-high"></i>
                Dashboard Gerencial
            </h1>
            <p style="opacity: 0.9; font-size: 1rem; margin-top: 8px; font-weight: 500;">Indicadores de impacto e utilização de recursos.</p>
        </div>
        <div style="background: rgba(255,255,255,0.2); padding: 12px 24px; border-radius: 16px; backdrop-filter: blur(10px); font-weight: 700; font-size: 0.875rem;">
            <?= date('d/m/Y') ?>
        </div>
    </div>
</div>

<!-- KPIs Principais -->
<div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
    
    <!-- Locação de Salas -->
    <div class="stat-card" style="border-bottom: 4px solid #3b82f6;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
            <div class="stat-label" style="color: #1d4ed8; font-weight: 800; text-transform: uppercase; font-size: 0.75rem;">Locação de Salas</div>
            <div style="padding: 10px; border-radius: 12px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; font-size: 1.25rem;">
                <i class="fa-solid fa-building-circle-check"></i>
            </div>
        </div>
        <div style="display: flex; align-items: baseline; gap: 8px;">
            <div class="stat-value" style="font-size: 2.5rem; color: #1e293b;"><?= $totalBookings ?></div>
            <div style="font-size: 0.875rem; color: #64748b; font-weight: 600;">locações</div>
        </div>
        
        <div style="margin-top: 1.5rem;">
            <div style="display: flex; justify-content: space-between; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.5rem; color: #475569;">
                <span>OCUPAÇÃO / USO</span>
                <span><?= number_format($occupancyRate, 1) ?>%</span>
            </div>
            <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                <div style="width: <?= $occupancyRate ?>%; height: 100%; background: #3b82f6; border-radius: 4px; transition: width 1s ease-in-out;"></div>
            </div>
            <div style="display: flex; justify-content: space-between; margin-top: 10px; font-size: 0.8rem; color: #64748b;">
                <span>Total de Salas: <strong><?= $totalRooms ?></strong></span>
            </div>
        </div>
    </div>

    <!-- Retorno Financeiro Voluntariado -->
    <div class="stat-card" style="border-bottom: 4px solid #10b981; background: linear-gradient(to bottom right, #ffffff, #f0fdf4);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
            <div class="stat-label" style="color: #047857; font-weight: 800; text-transform: uppercase; font-size: 0.75rem;">Retorno Financeiro Voluntariado</div>
            <div style="padding: 10px; border-radius: 12px; background: rgba(16, 185, 129, 0.1); color: #10b981; font-size: 1.25rem;">
                <i class="fa-solid fa-hand-holding-dollar"></i>
            </div>
        </div>
        <div class="stat-value" style="font-size: 2.25rem; color: #047857;">R$ <?= number_format((float)$financialReturn, 2, ',', '.') ?></div>
        
        <div style="background: rgba(16, 185, 129, 0.1); padding: 12px; border-radius: 12px; margin-top: 1.5rem;">
            <div style="font-size: 0.7rem; color: #065f46; font-weight: 700; text-transform: uppercase; margin-bottom: 4px;">Valor Economizado</div>
            <div style="font-size: 0.9rem; color: #047857; font-weight: 500;">
                Impacto de <strong><?= number_format((float)$totalHours, 1, ',', '.') ?></strong> horas de trabalho voluntário.
            </div>
        </div>
    </div>

    <!-- Voluntários Ativos (Extra para preencher grid e dar % ) -->
    <div class="stat-card" style="border-bottom: 4px solid #ec4899;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
            <div class="stat-label" style="color: #be185d; font-weight: 800; text-transform: uppercase; font-size: 0.75rem;">Corpo de Voluntários</div>
            <div style="padding: 10px; border-radius: 12px; background: rgba(236, 72, 153, 0.1); color: #ec4899; font-size: 1.25rem;">
                <i class="fa-solid fa-heart"></i>
            </div>
        </div>
        <div class="stat-value" style="font-size: 2.5rem; color: #be185d;"><?= $totalVolunteers ?></div>
        <div style="margin-top: 1.5rem;">
            <?php 
                $activeVol = $pdo->query("SELECT COUNT(*) FROM volunteers WHERE status = 'Ativo'")->fetchColumn() ?: 0;
                $activePerc = $totalVolunteers > 0 ? ($activeVol / $totalVolunteers) * 100 : 0;
            ?>
            <div style="display: flex; justify-content: space-between; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.5rem; color: #475569;">
                <span>VOLUNTÁRIOS ATIVOS</span>
                <span><?= number_format($activePerc, 1) ?>%</span>
            </div>
            <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                <div style="width: <?= $activePerc ?>%; height: 100%; background: #ec4899; border-radius: 4px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Gráficos de Detalhamento -->
<div style="display: grid; grid-template-columns: 1fr; gap: 2rem; margin-top: 2rem;">
    <div class="card">
        <h3 style="font-size: 1.1rem; font-weight: 800; margin-bottom: 1.5rem; color: #1e293b; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-chart-simple" style="color: #3b82f6;"></i> 
            Reservas por Sala
        </h3>
        <div style="height: 350px;"><canvas id="salasChart"></canvas></div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    padding: 12,
                    titleFont: { size: 14, weight: 'bold' },
                    bodyFont: { size: 13 }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { font: { weight: '600' } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' }
                }
            }
        };

        // Salas Chart
        <?php
            $salaLabels = array_column($bookingsByRoom, 'name');
            $salaData = array_column($bookingsByRoom, 'count');
        ?>
        new Chart(document.getElementById('salasChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($salaLabels) ?: '[]' ?>,
                datasets: [{ 
                    label: 'Reservas',
                    data: <?= json_encode($salaData) ?: '[]' ?>, 
                    backgroundColor: 'linear-gradient(180deg, #3b82f6, #2563eb)',
                    backgroundColor: '#3b82f6',
                    borderRadius: 8,
                    maxBarThickness: 50
                }]
            },
            options: chartOptions
        });
    });
</script>
