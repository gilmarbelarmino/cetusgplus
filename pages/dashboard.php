<?php
/**
 * CETUSG - Dashboard Operacional (Command Center)
 * Refactored for SaaS Enterprise Experience
 */

// 1. Data Preparation
// 1. Data Preparation
try {
    $cid = getCurrentUserCompanyId();
    $isSuper = (isset($user['is_super_admin']) && $user['is_super_admin'] == 1) || (isset($_SESSION['login_name']) && $_SESSION['login_name'] === 'superadmin');
    
    // Tickets Stats
    if ($isSuper) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status IN ('Aberto', 'Em Progresso')");
        $openTickets = $stmt->fetchColumn() ?: 0;
        $stmt = $pdo->query("SELECT * FROM tickets WHERE status IN ('Aberto', 'Em Progresso') AND (priority = 'Crítica' OR sla_status = 'Atrasado') ORDER BY priority DESC, sla_deadline ASC LIMIT 4");
        $criticalTickets = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE status IN ('Aberto', 'Em Progresso') AND company_id = ?");
        $stmt->execute([$cid]);
        $openTickets = $stmt->fetchColumn() ?: 0;
        $stmt = $pdo->prepare("SELECT * FROM tickets WHERE status IN ('Aberto', 'Em Progresso') AND (priority = 'Crítica' OR sla_status = 'Atrasado') AND company_id = ? ORDER BY priority DESC, sla_deadline ASC LIMIT 4");
        $stmt->execute([$cid]);
        $criticalTickets = $stmt->fetchAll();
    }
    
    // Rooms Stats
    if ($isSuper) {
        $totalRooms = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn() ?: 0;
        $totalBookings = $pdo->query("SELECT COUNT(*) FROM room_bookings")->fetchColumn() ?: 0;
        $bookingsByRoom = $pdo->query("SELECT r.name, COUNT(b.id) as count FROM room_bookings b JOIN rooms r ON b.room_id = r.id GROUP BY r.id LIMIT 5")->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE company_id = ?"); $stmt->execute([$cid]);
        $totalRooms = $stmt->fetchColumn() ?: 0;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM room_bookings WHERE company_id = ?"); $stmt->execute([$cid]);
        $totalBookings = $stmt->fetchColumn() ?: 0;
        $stmt = $pdo->prepare("SELECT r.name, COUNT(b.id) as count FROM room_bookings b JOIN rooms r ON b.room_id = r.id WHERE b.company_id = ? GROUP BY r.id LIMIT 5"); $stmt->execute([$cid]);
        $bookingsByRoom = $stmt->fetchAll();
    }
    $occupancyRate = $totalRooms > 0 ? ($totalBookings / ($totalRooms * 10)) * 100 : 0;
    if ($occupancyRate > 100) $occupancyRate = 100;

    // Volunteering Stats
    if ($isSuper) {
        $totalVolunteers = $pdo->query("SELECT COUNT(*) FROM volunteers WHERE status = 'Ativo'")->fetchColumn() ?: 0;
        $financialReturn = $pdo->query("SELECT SUM(total_hours * hourly_rate) as total FROM volunteers")->fetchColumn() ?: 0;
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM volunteers WHERE status = 'Ativo' AND company_id = ?"); $stmt->execute([$cid]);
        $totalVolunteers = $stmt->fetchColumn() ?: 0;
        $stmt = $pdo->prepare("SELECT SUM(total_hours * hourly_rate) as total FROM volunteers WHERE company_id = ?"); $stmt->execute([$cid]);
        $financialReturn = $stmt->fetchColumn() ?: 0;
    }

    // Recent Activity
    if ($isSuper) {
        $recentActivity = $pdo->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 6")->fetchAll();
    } else {
        $stmt_audit = $pdo->prepare("SELECT * FROM audit_logs WHERE company_id = ? ORDER BY created_at DESC LIMIT 6");
        $stmt_audit->execute([$cid]);
        $recentActivity = $stmt_audit->fetchAll();
    }

    $birthdayCount = count($birthdayPeople ?? []);

} catch(Exception $e) {
    $openTickets = 0; $criticalTickets = []; $totalRooms = 0; $totalBookings = 0;
    $occupancyRate = 0; $totalVolunteers = 0; $financialReturn = 0; $recentActivity = [];
}

?>

<div class="command-center">
    <!-- Header -->
    <div class="page-header">
        <div class="page-header-info">
            <div class="page-header-icon">
                <i class="fa-solid fa-gauge-high"></i>
            </div>
            <div class="page-header-text">
                <h2>Centro de Comando</h2>
                <p>Visão operacional e indicadores em tempo real.</p>
            </div>
        </div>
        <div class="page-header-actions">
            <button class="btn-secondary" onclick="window.location.reload()">
                <i class="fa-solid fa-rotate"></i>
                Atualizar
            </button>
            <button class="btn-primary" onclick="openPalette()">
                <i class="fa-solid fa-magnifying-glass"></i>
                Pesquisar (CTRL+K)
            </button>
        </div>
    </div>

    <!-- Top KPIs -->
    <div class="dashboard-grid-top">
        <div class="stat-card" style="border-left: 4px solid var(--brand-primary);">
            <div class="stat-label">Chamados Pendentes</div>
            <div class="stat-value"><?= $openTickets ?></div>
            <div class="stat-subtext"><i class="fa-solid fa-arrow-trend-up"></i> Fluxo do sistema</div>
            <i class="fa-solid fa-ticket stat-bg-icon"></i>
        </div>

        <div class="stat-card" style="border-left: 4px solid var(--success);">
            <div class="stat-label">Impacto Financeiro</div>
            <div class="stat-value">R$ <?= number_format($financialReturn, 2, ',', '.') ?></div>
            <div class="stat-subtext"><i class="fa-solid fa-heart"></i> Retorno Voluntariado</div>
            <i class="fa-solid fa-hand-holding-dollar stat-bg-icon"></i>
        </div>

        <div class="stat-card" style="border-left: 4px solid var(--info);">
            <div class="stat-label">Ocupação de Salas</div>
            <div class="stat-value"><?= number_format($occupancyRate, 1) ?>%</div>
            <div style="width: 100%; height: 6px; background: var(--border-soft); border-radius: 3px; margin-top: 10px; overflow: hidden;">
                <div style="width: <?= $occupancyRate ?>%; height: 100%; background: var(--info); border-radius: 3px;"></div>
            </div>
            <i class="fa-solid fa-building-circle-check stat-bg-icon"></i>
        </div>

        <div class="stat-card" style="border-left: 4px solid var(--warning);">
            <div class="stat-label">Aniversariantes</div>
            <div class="stat-value"><?= $birthdayCount ?></div>
            <div class="stat-subtext"><i class="fa-solid fa-cake-candles"></i> Celebrando hoje</div>
            <i class="fa-solid fa-gift stat-bg-icon"></i>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="dashboard-grid-main">
        <!-- Left: Charts and Critical Tickets (Col 8) -->
        <div class="grid-col-8">
            <!-- Chart Section -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h3><i class="fa-solid fa-chart-line"></i> Utilização de Salas</h3>
                    <span class="card-subtitle">Volume de reservas por sala cadastrada</span>
                </div>
                <div class="chart-container">
                    <canvas id="dashboardChart"></canvas>
                </div>
            </div>

            <!-- Critical Tickets -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fa-solid fa-triangle-exclamation" style="color: var(--danger);"></i> Chamados Críticos / Atrasados</h3>
                    <a href="index.php?page=chamados" class="btn-text">Ver todos</a>
                </div>
                <div class="table-responsive">
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>Assunto</th>
                                <th>Prioridade</th>
                                <th>SLA / Prazo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($criticalTickets)): ?>
                                <tr>
                                    <td colspan="4" class="text-center" style="padding: 2rem;">Nenhum chamado crítico pendente.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($criticalTickets as $ticket): ?>
                                    <tr>
                                        <td><strong>#<?= substr($ticket['id'], 0, 8) ?></strong></td>
                                        <td><?= htmlspecialchars($ticket['title']) ?></td>
                                        <td>
                                            <span class="badge badge-<?= $ticket['priority'] === 'Crítica' ? 'danger pulse-critical' : 'warning' ?>">
                                                <?= $ticket['priority'] ?>
                                            </span>
                                        </td>
                                        <td class="<?= $ticket['sla_status'] === 'Atrasado' ? 'text-danger font-weight-bold' : '' ?>">
                                            <?= $ticket['sla_deadline'] ? date('d/m H:i', strtotime($ticket['sla_deadline'])) : '--' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right: Activity and Events (Col 4) -->
        <div class="grid-col-4">
            <!-- Recent Activity Timeline -->
            <div class="card" style="margin-bottom: 24px; min-height: 450px;">
                <div class="card-header">
                    <h3><i class="fa-solid fa-bolt"></i> Atividade Recente</h3>
                </div>
                <div class="timeline">
                    <?php if (empty($recentActivity)): ?>
                        <div class="timeline-empty">Nenhuma atividade registrada.</div>
                    <?php else: ?>
                        <?php foreach ($recentActivity as $log): ?>
                            <div class="timeline-item">
                                <div class="timeline-icon">
                                    <i class="fa-solid <?= str_contains(strtolower($log['action']), 'delete') ? 'fa-trash' : (str_contains(strtolower($log['action']), 'update') ? 'fa-pen-to-square' : 'fa-plus') ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-title"><?= htmlspecialchars($log['user_name']) ?></div>
                                    <div class="timeline-desc"><?= htmlspecialchars($log['action']) ?> em <?= htmlspecialchars($log['module']) ?></div>
                                    <div class="timeline-time"><?= date('H:i', strtotime($log['created_at'])) ?> • <?= date('d/m', strtotime($log['created_at'])) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Birthdays / Quick Info -->
            <div class="card" style="background: linear-gradient(135deg, var(--brand-primary) 0%, var(--brand-primary-dark) 100%); color: white;">
                <div class="card-header">
                    <h3 style="color: white;"><i class="fa-solid fa-cake-candles"></i> Celebrações</h3>
                </div>
                <div class="birthday-list">
                    <?php if (empty($birthdayPeople)): ?>
                        <p style="opacity: 0.8; font-size: 0.9rem;">Nenhum aniversário hoje.</p>
                    <?php else: ?>
                        <?php foreach ($birthdayPeople as $p): ?>
                            <div class="birthday-item">
                                <div class="birthday-avatar">
                                    <?php if ($p['avatar_url']): ?>
                                        <img src="<?= $p['avatar_url'] ?>" alt="">
                                    <?php else: ?>
                                        <?= strtoupper(substr($p['name'], 0, 1)) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="birthday-info">
                                    <div class="name"><?= htmlspecialchars($p['name']) ?></div>
                                    <div class="action">Enviar parabéns</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('dashboardChart').getContext('2d');
    
    // Gradient for bars
    const gradient = ctx.createLinearGradient(0, 0, 0, 400);
    gradient.addColorStop(0, 'rgba(79, 70, 229, 0.8)');
    gradient.addColorStop(1, 'rgba(79, 70, 229, 0.2)');

    const chartData = {
        labels: <?= json_encode(array_column($bookingsByRoom, 'name')) ?>,
        datasets: [{
            label: 'Reservas por Sala',
            data: <?= json_encode(array_column($bookingsByRoom, 'count')) ?>,
            backgroundColor: gradient,
            borderColor: '#4F46E5',
            borderWidth: 2,
            borderRadius: 10,
            barThickness: 30
        }]
    };

    new Chart(ctx, {
        type: 'bar',
        data: chartData,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    padding: 12,
                    titleFont: { size: 14, weight: 'bold' },
                    bodyFont: { size: 13 }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)', drawBorder: false },
                    ticks: { color: '#94A3B8' }
                },
                x: {
                    grid: { display: false },
                    ticks: { color: '#64748B', font: { weight: '600' } }
                }
            }
        }
    });
});
</script>
