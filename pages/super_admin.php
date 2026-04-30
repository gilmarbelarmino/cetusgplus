<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Segurança: Apenas Super Admin pode acessar esta página
if (!$user || $user['is_super_admin'] != 1) {
    header("Location: index.php?page=dashboard");
    exit();
}

require_once 'includes/db.php';

// Processar Ações (Ativar, Bloquear, Adicionar Tenant)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_tenant') {
        $name = $_POST['name'];
        $expires = $_POST['expires_at'];
        $type = $_POST['license_type'];
        
        $stmt = $pdo->prepare("INSERT INTO tenants (name, expires_at, license_type, status) VALUES (?, ?, ?, 'active')");
        $stmt->execute([$name, $expires, $type]);
        $success = "Novo cliente cadastrado com sucesso!";
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
        $stmt = $pdo->prepare("UPDATE tenants SET expires_at = DATE_ADD(IF(expires_at > NOW(), expires_at, NOW()), INTERVAL ? DAY), status = 'active' WHERE id = ?");
        $stmt->execute([$days, $id]);
    }
}

// Buscar Tenants
$stmt = $pdo->query("SELECT t.*, (SELECT COUNT(*) FROM users WHERE company_id = t.id) as user_count FROM tenants t ORDER BY t.id DESC");
$tenants = $stmt->fetchAll();
?>

<div class="main-content-header" style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2 style="font-size: 1.5rem; font-weight: 900; color: var(--text-main); margin-bottom: 0.5rem;">Gestão Master SaaS</h2>
        <p style="color: var(--text-soft); font-size: 0.875rem;">Controle manual de licenças e acesso de clientes.</p>
    </div>
    <button onclick="document.getElementById('addTenantModal').style.display='flex'" class="btn-primary" style="padding: 0.75rem 1.5rem; border-radius: var(--radius-md); display: flex; align-items: center; gap: 0.5rem;">
        <i class="fa-solid fa-plus"></i> Novo Cliente
    </button>
</div>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
    <?php foreach ($tenants as $tenant): 
        $isExpired = strtotime($tenant['expires_at']) < time() && $tenant['license_type'] !== 'lifetime';
    ?>
        <div class="glass-panel" style="padding: 1.5rem; border-top: 4px solid <?= $tenant['status'] === 'active' ? ($isExpired ? '#EF4444' : '#10B981') : '#64748B' ?>;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                <div>
                    <h3 style="font-weight: 800; color: var(--text-main);"><?= htmlspecialchars($tenant['name']) ?></h3>
                    <span style="font-size: 0.75rem; color: var(--text-soft);">ID: #<?= $tenant['id'] ?></span>
                </div>
                <span class="badge" style="background: <?= $tenant['status'] === 'active' ? ($isExpired ? 'rgba(239,68,68,0.1)' : 'rgba(16,185,129,0.1)') : 'rgba(100,116,139,0.1)' ?>; color: <?= $tenant['status'] === 'active' ? ($isExpired ? '#EF4444' : '#10B981') : '#64748B' ?>;">
                    <?= $isExpired ? 'Expirado' : ($tenant['status'] === 'active' ? 'Ativo' : 'Bloqueado') ?>
                </span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <p style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Expira em</p>
                    <p style="font-weight: 700; color: var(--text-main);"><?= $tenant['license_type'] === 'lifetime' ? 'Vitalício' : date('d/m/Y', strtotime($tenant['expires_at'])) ?></p>
                </div>
                <div>
                    <p style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Usuários</p>
                    <p style="font-weight: 700; color: var(--text-main);"><?= $tenant['user_count'] ?></p>
                </div>
            </div>

            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <form method="POST" style="flex: 1;">
                    <input type="hidden" name="action" value="renew">
                    <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                    <select name="days" class="form-input" style="padding: 0.4rem; margin-bottom: 0.5rem; font-size: 0.8rem;">
                        <option value="30">+ 30 Dias (Mensal)</option>
                        <option value="365">+ 365 Dias (Anual)</option>
                    </select>
                    <button type="submit" class="btn-primary" style="width: 100%; font-size: 0.8rem; padding: 0.5rem;">Renovar</button>
                </form>
                
                <form method="POST" style="width: 100%;">
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                    <input type="hidden" name="status" value="<?= $tenant['status'] ?>">
                    <button type="submit" style="width: 100%; font-size: 0.8rem; padding: 0.5rem; background: <?= $tenant['status'] === 'active' ? 'rgba(239,68,68,0.1)' : 'rgba(16,185,129,0.1)' ?>; color: <?= $tenant['status'] === 'active' ? '#EF4444' : '#10B981' ?>; border: none; cursor: pointer; border-radius: 6px;">
                        <?= $tenant['status'] === 'active' ? 'Bloquear Acesso' : 'Desbloquear' ?>
                    </button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal Novo Cliente -->
<div id="addTenantModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 2000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 500px; width: 100%; padding: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--text-main);">Cadastrar Novo Cliente</h3>
            <button onclick="document.getElementById('addTenantModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: var(--text-soft); font-size: 1.5rem;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_tenant">
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-soft); margin-bottom: 0.5rem;">Nome da Empresa/Cliente</label>
                <input type="text" name="name" required class="form-input" placeholder="Ex: Arrastão Tech">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-soft); margin-bottom: 0.5rem;">Primeiro Vencimento</label>
                    <input type="date" name="expires_at" required class="form-input">
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

            <button type="submit" class="btn-primary" style="width: 100%; padding: 1rem; border-radius: 12px; font-weight: 800;">
                Criar Cliente e Ativar
            </button>
        </form>
    </div>
</div>
