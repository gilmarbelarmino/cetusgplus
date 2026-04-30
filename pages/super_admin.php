<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Segurança Máxima: Apenas o usuário 'superadmin' tem acesso
if (!$user || $user['login_name'] !== 'superadmin') {
    header("Location: index.php?page=dashboard");
    exit();
}

// Processar Ações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_tenant') {
        $name = $_POST['name'];
        $expires = $_POST['expires_at'];
        $type = $_POST['license_type'];
        $value = $_POST['subscription_value'] ?: 0;
        $admin_login = $_POST['admin_login'];
        $admin_password = $_POST['admin_password'];
        $admin_name = $_POST['admin_name'];
        
        // 1. Criar a empresa
        $stmt = $pdo->prepare("INSERT INTO tenants (name, expires_at, license_type, status, subscription_value, last_amount_paid, created_at, access_liberation_date) VALUES (?, ?, ?, 'active', ?, ?, NOW(), NOW())");
        $stmt->execute([$name, $expires, $type, $value, $value]);
        $newCompanyId = $pdo->lastInsertId();
        
        // 2. Criar company_settings para a nova empresa
        $pdo->prepare("INSERT IGNORE INTO company_settings (id, company_name) VALUES (?, ?)")->execute([$newCompanyId, $name]);
        
        // 3. Criar o usuário administrador da empresa
        $hashedPass = password_hash($admin_password, PASSWORD_DEFAULT);
        $userId = 'U' . time() . rand(100,999);
        $stmtUser = $pdo->prepare("INSERT INTO users (id, login_name, name, password, company_id, status, is_super_admin) VALUES (?, ?, ?, ?, ?, 'Ativo', 0)");
        $stmtUser->execute([$userId, $admin_login, $admin_name, $hashedPass, $newCompanyId]);
        
        // 4. Liberar todos os menus para o admin da empresa
        $allMenus = ['rh','voluntariado','semanada','patrimonio','emprestimos','chamados','orcamentos','locacao_salas','relatorios','tecnologia','informacoes','usuarios','configuracoes'];
        foreach ($allMenus as $menu) {
            $pdo->prepare("INSERT IGNORE INTO user_menus (user_id, menu) VALUES (?, ?)")->execute([$userId, $menu]);
        }
        
        $success = "Empresa '$name' cadastrada! Admin: $admin_login";
    }
    
    if ($_POST['action'] === 'renew') {
        $id = $_POST['tenant_id'];
        $days = $_POST['days'];
        $paid = $_POST['amount_paid'] ?: 0;
        $stmt = $pdo->prepare("UPDATE tenants SET 
            expires_at = DATE_ADD(IF(expires_at > NOW(), expires_at, NOW()), INTERVAL ? DAY), 
            status = 'active',
            last_amount_paid = ?,
            last_payment_date = NOW(),
            access_liberation_date = NOW()
            WHERE id = ?");
        $stmt->execute([$days, $paid, $id]);
        $success = "Pagamento registrado e licença renovada com sucesso!";
    }

    if ($_POST['action'] === 'toggle_status') {
        $id = $_POST['tenant_id'];
        $status = $_POST['status'] === 'active' ? 'inactive' : 'active';
        $stmt = $pdo->prepare("UPDATE tenants SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
    }
}

// Buscar Tenants
$stmt = $pdo->query("SELECT t.*, (SELECT COUNT(*) FROM users WHERE company_id = t.id) as user_count FROM tenants t ORDER BY t.id DESC");
$tenants = $stmt->fetchAll();
?>

<div class="main-content-header" style="margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="font-size: 1.8rem; font-weight: 900; color: #FBBF24; margin-bottom: 0.5rem;"><i class="fa-solid fa-shield-halved"></i> Painel Master SaaS</h2>
            <p style="color: var(--text-soft); font-size: 0.9rem;">Acesso restrito ao Administrador Mestre do Cetusg Plus.</p>
        </div>
        <button onclick="document.getElementById('addTenantModal').style.display='flex'" class="btn-primary" style="padding: 0.8rem 1.5rem; border-radius: 12px; font-weight: 800;">
            <i class="fa-solid fa-building-circle-check"></i> Cadastrar Nova Empresa
        </button>
    </div>
</div>

<?php if (isset($success)): ?>
    <div style="background: rgba(16,185,129,0.1); color: #10B981; padding: 1.2rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(16,185,129,0.2); display: flex; align-items: center; gap: 10px;">
        <i class="fa-solid fa-circle-check" style="font-size: 1.5rem;"></i> <?= $success ?>
    </div>
<?php endif; ?>

<div style="overflow-x: auto; background: var(--bg-secondary); border-radius: 20px; border: 1px solid rgba(255,255,255,0.05);">
    <table style="width: 100%; border-collapse: collapse; min-width: 1000px;">
        <thead>
            <tr style="background: rgba(0,0,0,0.2); text-align: left;">
                <th style="padding: 1.5rem; color: var(--text-soft); font-size: 0.75rem; text-transform: uppercase;">Empresa / ID</th>
                <th style="padding: 1.5rem; color: var(--text-soft); font-size: 0.75rem; text-transform: uppercase;">Início / Vencimento</th>
                <th style="padding: 1.5rem; color: var(--text-soft); font-size: 0.75rem; text-transform: uppercase;">Último Pagamento</th>
                <th style="padding: 1.5rem; color: var(--text-soft); font-size: 0.75rem; text-transform: uppercase;">Liberação</th>
                <th style="padding: 1.5rem; color: var(--text-soft); font-size: 0.75rem; text-transform: uppercase;">Status</th>
                <th style="padding: 1.5rem; color: var(--text-soft); font-size: 0.75rem; text-transform: uppercase;">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tenants as $tenant): 
                $isExpired = strtotime($tenant['expires_at']) < time() && $tenant['license_type'] !== 'lifetime';
            ?>
                <tr style="border-bottom: 1px solid rgba(255,255,255,0.03); transition: all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.02)'" onmouseout="this.style.background='transparent'">
                    <td style="padding: 1.5rem;">
                        <div style="font-weight: 800; color: var(--text-main); font-size: 1rem;"><?= htmlspecialchars($tenant['name']) ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-soft);">#<?= $tenant['id'] ?> • <?= $tenant['user_count'] ?> usuários</div>
                    </td>
                    <td style="padding: 1.5rem;">
                        <div style="font-size: 0.85rem; color: var(--text-main);"><i class="fa-solid fa-calendar-plus" style="color: #10B981; width: 20px;"></i> <?= date('d/m/Y', strtotime($tenant['created_at'])) ?></div>
                        <div style="font-size: 0.85rem; color: <?= $isExpired ? '#EF4444' : '#FBBF24' ?>; font-weight: 700;">
                            <i class="fa-solid fa-calendar-xmark" style="width: 20px;"></i> <?= $tenant['license_type'] === 'lifetime' ? 'Vitalício' : date('d/m/Y', strtotime($tenant['expires_at'])) ?>
                        </div>
                    </td>
                    <td style="padding: 1.5rem;">
                        <div style="font-weight: 800; color: var(--brand-primary);">R$ <?= number_format($tenant['last_amount_paid'], 2, ',', '.') ?></div>
                        <div style="font-size: 0.7rem; color: var(--text-soft);">
                            <?= $tenant['last_payment_date'] ? date('d/m/Y H:i', strtotime($tenant['last_payment_date'])) : 'Sem registro' ?>
                        </div>
                    </td>
                    <td style="padding: 1.5rem;">
                        <div style="font-size: 0.85rem; color: #10B981; font-weight: 600;">
                            <i class="fa-solid fa-unlock-keyhole"></i> <?= $tenant['access_liberation_date'] ? date('d/m/Y H:i', strtotime($tenant['access_liberation_date'])) : '---' ?>
                        </div>
                    </td>
                    <td style="padding: 1.5rem;">
                        <span class="badge" style="background: <?= $tenant['status'] === 'active' ? ($isExpired ? 'rgba(239,68,68,0.1)' : 'rgba(16,185,129,0.1)') : 'rgba(100,116,139,0.1)' ?>; color: <?= $tenant['status'] === 'active' ? ($isExpired ? '#EF4444' : '#10B981') : '#64748B' ?>; padding: 0.5rem 1rem; border-radius: 100px;">
                            <?= $isExpired ? 'Expirado' : ($tenant['status'] === 'active' ? 'Ativo' : 'Bloqueado') ?>
                        </span>
                    </td>
                    <td style="padding: 1.5rem;">
                        <div style="display: flex; gap: 0.5rem;">
                            <button onclick="openRenewModal(<?= $tenant['id'] ?>, '<?= htmlspecialchars($tenant['name']) ?>', <?= $tenant['subscription_value'] ?>)" class="btn-primary" style="padding: 0.5rem 0.8rem; font-size: 0.75rem; background: #FBBF24; color: #000;" title="Renovar/Pagar">
                                <i class="fa-solid fa-money-bill-transfer"></i>
                            </button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                                <input type="hidden" name="status" value="<?= $tenant['status'] ?>">
                                <button type="submit" class="btn-secondary" style="padding: 0.5rem 0.8rem; font-size: 0.75rem; background: rgba(255,255,255,0.05);" title="<?= $tenant['status'] === 'active' ? 'Bloquear' : 'Desbloquear' ?>">
                                    <i class="fa-solid <?= $tenant['status'] === 'active' ? 'fa-ban' : 'fa-check' ?>"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Renovar -->
<div id="renewModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(10px); z-index: 3000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 450px; width: 100%; padding: 2.5rem;">
        <h3 id="renewTitle" style="font-size: 1.5rem; font-weight: 900; color: #FBBF24; margin-bottom: 0.5rem;">Renovar Licença</h3>
        <p style="color: var(--text-soft); font-size: 0.85rem; margin-bottom: 2rem;">Registre o pagamento para liberar o acesso.</p>
        <form method="POST">
            <input type="hidden" name="action" value="renew">
            <input type="hidden" name="tenant_id" id="renewTenantId">
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-soft); margin-bottom: 0.5rem;">Tipo de Renovação</label>
                <select name="days" class="form-input">
                    <option value="30">Mensal (+30 dias)</option>
                    <option value="365">Anual (+365 dias)</option>
                </select>
            </div>
            <div style="margin-bottom: 2rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-soft); margin-bottom: 0.5rem;">Valor Recebido (R$)</label>
                <input type="number" step="0.01" name="amount_paid" id="renewAmount" required class="form-input" style="font-size: 1.2rem; font-weight: 800; color: #10B981;">
            </div>
            <div style="display: flex; gap: 1rem;">
                <button type="button" onclick="document.getElementById('renewModal').style.display='none'" class="btn-secondary" style="flex: 1; padding: 1rem; border-radius: 12px;">Cancelar</button>
                <button type="submit" class="btn-primary" style="flex: 1; padding: 1rem; border-radius: 12px; background: #10B981; color: white;">Confirmar e Liberar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Novo Cliente -->
<div id="addTenantModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(10px); z-index: 3000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 500px; width: 100%; padding: 2.5rem; max-height: 90vh; overflow-y: auto;">
        <h3 style="font-size: 1.5rem; font-weight: 900; color: var(--text-main); margin-bottom: 2rem;">Novo Cliente SaaS</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_tenant">
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-soft); margin-bottom: 0.5rem;">Nome da Empresa</label>
                <input type="text" name="name" required class="form-input" placeholder="Ex: Projeto Arrastão">
            </div>
            
            <div style="background: rgba(251,191,36,0.05); border: 1px solid rgba(251,191,36,0.15); border-radius: 12px; padding: 1.2rem; margin-bottom: 1.5rem;">
                <p style="font-size: 0.8rem; font-weight: 800; color: #FBBF24; margin-bottom: 1rem;"><i class="fa-solid fa-user-shield"></i> Administrador da Empresa</p>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-soft); margin-bottom: 0.5rem;">Nome Completo</label>
                    <input type="text" name="admin_name" required class="form-input" placeholder="Nome do responsável">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-soft); margin-bottom: 0.5rem;">Login</label>
                        <input type="text" name="admin_login" required class="form-input" placeholder="usuario">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-soft); margin-bottom: 0.5rem;">Senha</label>
                        <input type="text" name="admin_password" required class="form-input" placeholder="senha">
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-soft); margin-bottom: 0.5rem;">Valor do Plano (R$)</label>
                    <input type="number" step="0.01" name="subscription_value" required class="form-input" placeholder="0,00">
                </div>
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-soft); margin-bottom: 0.5rem;">Tipo</label>
                    <select name="license_type" class="form-input">
                        <option value="monthly">Mensal</option>
                        <option value="yearly">Anual</option>
                    </select>
                </div>
            </div>
            <div style="margin-bottom: 2rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-soft); margin-bottom: 0.5rem;">Primeiro Vencimento</label>
                <input type="date" name="expires_at" required class="form-input" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
            </div>
            <button type="submit" class="btn-primary" style="width: 100%; padding: 1.2rem; border-radius: 15px; font-weight: 800; font-size: 1rem;">Cadastrar e Ativar Agora</button>
        </form>
        <button onclick="document.getElementById('addTenantModal').style.display='none'" style="width: 100%; background: none; border: none; color: var(--text-soft); margin-top: 1rem; cursor: pointer;">Fechar sem salvar</button>
    </div>
</div>

<script>
function openRenewModal(id, name, value) {
    document.getElementById('renewTenantId').value = id;
    document.getElementById('renewTitle').innerText = 'Renovar: ' + name;
    document.getElementById('renewAmount').value = value;
    document.getElementById('renewModal').style.display = 'flex';
}
</script>
