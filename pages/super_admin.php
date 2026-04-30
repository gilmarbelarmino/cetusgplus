<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Segurança: Apenas Super Admin pode acessar esta página
if (!$user || $user['is_super_admin'] != 1) {
    header("Location: index.php?page=dashboard");
    exit();
}

// Conexão já fornecida pelo index.php

// Processar Ações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_tenant') {
        $name = $_POST['name'];
        $expires = $_POST['expires_at'];
        $type = $_POST['license_type'];
        $value = $_POST['subscription_value'] ?: 0;
        
        $stmt = $pdo->prepare("INSERT INTO tenants (name, expires_at, license_type, status, subscription_value, last_amount_paid) VALUES (?, ?, ?, 'active', ?, ?)");
        $stmt->execute([$name, $expires, $type, $value, $value]);
        $success = "Novo cliente cadastrado e acesso liberado!";
    }
    
    if ($_POST['action'] === 'toggle_status') {
        $id = $_POST['tenant_id'];
        $status = $_POST['status'] === 'active' ? 'inactive' : 'active';
        $stmt = $pdo->prepare("UPDATE tenants SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
    }

    if ($_POST['action'] === 'renew') {
        $id = $_POST['tenant_id'];
        $days = $_POST['days'];
        $paid = $_POST['amount_paid'] ?: 0;
        $stmt = $pdo->prepare("UPDATE tenants SET 
            expires_at = DATE_ADD(IF(expires_at > NOW(), expires_at, NOW()), INTERVAL ? DAY), 
            status = 'active',
            last_amount_paid = ?
            WHERE id = ?");
        $stmt->execute([$days, $paid, $id]);
    }
}

// Buscar Tenants
$stmt = $pdo->query("SELECT t.*, (SELECT COUNT(*) FROM users WHERE company_id = t.id) as user_count FROM tenants t ORDER BY t.id DESC");
$tenants = $stmt->fetchAll();
?>

<div class="main-content-header" style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2 style="font-size: 1.5rem; font-weight: 900; color: var(--text-main); margin-bottom: 0.5rem;">Gestão Financeira SaaS</h2>
        <p style="color: var(--text-soft); font-size: 0.875rem;">Controle de pagamentos e licenciamento manual.</p>
    </div>
    <button onclick="document.getElementById('addTenantModal').style.display='flex'" class="btn-primary" style="padding: 0.75rem 1.5rem; border-radius: var(--radius-md); display: flex; align-items: center; gap: 0.5rem;">
        <i class="fa-solid fa-plus"></i> Novo Cliente
    </button>
</div>

<?php if (isset($success)): ?>
    <div style="background: rgba(16,185,129,0.1); color: #10B981; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid rgba(16,185,129,0.2);">
        <i class="fa-solid fa-check-circle"></i> <?= $success ?>
    </div>
<?php endif; ?>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem;">
    <?php foreach ($tenants as $tenant): 
        $isExpired = strtotime($tenant['expires_at']) < time() && $tenant['license_type'] !== 'lifetime';
    ?>
        <div class="glass-panel" style="padding: 1.5rem; border-top: 4px solid <?= $tenant['status'] === 'active' ? ($isExpired ? '#EF4444' : '#10B981') : '#64748B' ?>;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                <div>
                    <h3 style="font-weight: 800; color: var(--text-main);"><?= htmlspecialchars($tenant['name']) ?></h3>
                    <span style="font-size: 0.75rem; color: var(--text-soft);">Plano: <?= ucfirst($tenant['license_type']) ?></span>
                </div>
                <span class="badge" style="background: <?= $tenant['status'] === 'active' ? ($isExpired ? 'rgba(239,68,68,0.1)' : 'rgba(16,185,129,0.1)') : 'rgba(100,116,139,0.1)' ?>; color: <?= $tenant['status'] === 'active' ? ($isExpired ? '#EF4444' : '#10B981') : '#64748B' ?>;">
                    <?= $isExpired ? 'Expirado' : ($tenant['status'] === 'active' ? 'Ativo' : 'Bloqueado') ?>
                </span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; background: rgba(0,0,0,0.05); padding: 1rem; border-radius: 12px;">
                <div>
                    <p style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase;">Valor do Plano</p>
                    <p style="font-weight: 800; color: var(--brand-primary); font-size: 1.1rem;">R$ <?= number_format($tenant['subscription_value'], 2, ',', '.') ?></p>
                </div>
                <div>
                    <p style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase;">Último Recebido</p>
                    <p style="font-weight: 700; color: var(--text-main);">R$ <?= number_format($tenant['last_amount_paid'], 2, ',', '.') ?></p>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <p style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase;">Expira em</p>
                    <p style="font-weight: 700; color: <?= $isExpired ? '#EF4444' : 'var(--text-main)' ?>;">
                        <?= $tenant['license_type'] === 'lifetime' ? 'Vitalício' : date('d/m/Y', strtotime($tenant['expires_at'])) ?>
                    </p>
                </div>
                <div>
                    <p style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase;">Usuários</p>
                    <p style="font-weight: 700; color: var(--text-main);"><?= $tenant['user_count'] ?></p>
                </div>
            </div>

            <div style="background: rgba(251,191,36,0.05); border: 1px solid rgba(251,191,36,0.1); padding: 1rem; border-radius: 12px; margin-bottom: 1rem;">
                <p style="font-size: 0.8rem; font-weight: 800; color: #FBBF24; margin-bottom: 0.75rem;"><i class="fa-solid fa-money-bill-wave"></i> Registrar Pagamento e Renovar</p>
                <form method="POST">
                    <input type="hidden" name="action" value="renew">
                    <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                    <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <select name="days" class="form-input" style="flex: 1; padding: 0.4rem; font-size: 0.8rem;">
                            <option value="30">Mensal (+30 dias)</option>
                            <option value="365">Anual (+365 dias)</option>
                        </select>
                        <input type="number" step="0.01" name="amount_paid" placeholder="Valor R$" required class="form-input" style="flex: 1; padding: 0.4rem; font-size: 0.8rem;">
                    </div>
                    <button type="submit" class="btn-primary" style="width: 100%; font-size: 0.8rem; padding: 0.6rem; background: #FBBF24; color: #000;">Confirmar Recebimento</button>
                </form>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                <input type="hidden" name="status" value="<?= $tenant['status'] ?>">
                <button type="submit" style="width: 100%; font-size: 0.8rem; padding: 0.6rem; background: <?= $tenant['status'] === 'active' ? 'rgba(239,68,68,0.1)' : 'rgba(16,185,129,0.1)' ?>; color: <?= $tenant['status'] === 'active' ? '#EF4444' : '#10B981' ?>; border: none; cursor: pointer; border-radius: 6px; font-weight: 700;">
                    <?= $tenant['status'] === 'active' ? 'Bloquear Empresa' : 'Ativar Manualmente' ?>
                </button>
            </form>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal Novo Cliente -->
<div id="addTenantModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 2000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 500px; width: 100%; padding: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--text-main);">Configurar Novo Cliente</h3>
            <button onclick="document.getElementById('addTenantModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: var(--text-soft); font-size: 1.5rem;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_tenant">
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-soft); margin-bottom: 0.5rem;">Nome da Empresa</label>
                <input type="text" name="name" required class="form-input" placeholder="Nome do Cliente">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-soft); margin-bottom: 0.5rem;">Valor da Assinatura (R$)</label>
                    <input type="number" step="0.01" name="subscription_value" required class="form-input" placeholder="0,00">
                </div>
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-soft); margin-bottom: 0.5rem;">Tipo de Plano</label>
                    <select name="license_type" class="form-input">
                        <option value="monthly">Mensal</option>
                        <option value="yearly">Anual</option>
                        <option value="lifetime">Vitalício</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 2rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-soft); margin-bottom: 0.5rem;">Primeiro Vencimento</label>
                <input type="date" name="expires_at" required class="form-input" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; padding: 1rem; border-radius: 12px; font-weight: 800; background: var(--brand-primary); color: white;">
                Cadastrar e Liberar Acesso
            </button>
        </form>
    </div>
</div>
