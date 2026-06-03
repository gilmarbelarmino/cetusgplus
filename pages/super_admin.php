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
        try {
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
            $pdo->prepare("INSERT IGNORE INTO company_settings (id, company_name, company_id) VALUES (?, ?, ?)")->execute([$newCompanyId, $name, $newCompanyId]);
            
            // 2.1 Criar Unidade e Setor padrão
            $defaultUnitId = 'UNI' . time() . rand(100,999);
            $pdo->prepare("INSERT INTO units (id, company_id, name) VALUES (?, ?, 'Matriz')")->execute([$defaultUnitId, $newCompanyId]);
            
            $defaultSectorId = 'SEC' . time() . rand(100,999);
            $pdo->prepare("INSERT INTO sectors (id, company_id, name, unit_id) VALUES (?, ?, 'Geral', ?)")->execute([$defaultSectorId, $newCompanyId, $defaultUnitId]);
            
            // 2.2 Copiar Roles e Permissões da Empresa 1
            $roles = $pdo->query("SELECT * FROM roles WHERE company_id = 1")->fetchAll(PDO::FETCH_ASSOC);
            $adminRoleId = null;
            
            foreach ($roles as $r) {
                $stmtR = $pdo->prepare("INSERT INTO roles (company_id, name, display_name, description, level) VALUES (?, ?, ?, ?, ?)");
                $stmtR->execute([$newCompanyId, $r['name'], $r['display_name'], $r['description'], $r['level']]);
                $newRoleId = $pdo->lastInsertId();
                
                if ($r['name'] === 'admin') {
                    $adminRoleId = $newRoleId;
                }
                
                // Copiar permissões
                $perms = $pdo->prepare("SELECT permission_id FROM role_permission WHERE role_id = ?");
                $perms->execute([$r['id']]);
                while ($p = $perms->fetch(PDO::FETCH_ASSOC)) {
                    $pdo->prepare("INSERT INTO role_permission (role_id, permission_id) VALUES (?, ?)")->execute([$newRoleId, $p['permission_id']]);
                }
            }
            
            // 3. Criar o usuário administrador da empresa
            $hashedPass = password_hash($admin_password, PASSWORD_DEFAULT);
            $userId = 'U' . time() . rand(100,999);
            $stmtUser = $pdo->prepare("INSERT INTO users (id, login_name, name, password, company_id, status, is_super_admin, role, role_id, unit_id, sector) VALUES (?, ?, ?, ?, ?, 'Ativo', 0, 'Administrador', ?, ?, 'Geral')");
            $stmtUser->execute([$userId, $admin_login, $admin_name, $hashedPass, $newCompanyId, $adminRoleId, $defaultUnitId]);
            
            // 4. Liberar todos os menus para o admin da empresa (Pacote Completo Cetusg Plus)
            $allMenus = [
                'dashboard', 'rh', 'voluntariado', 'semanada', 'patrimonio', 
                'emprestimos', 'chamados', 'orcamentos', 'locacao_salas', 
                'relatorios', 'tecnologia', 'informacoes', 'usuarios', 
                'configuracoes', 'peixinho'
            ];
            foreach ($allMenus as $menu) {
                $pdo->prepare("INSERT IGNORE INTO user_menus (user_id, menu) VALUES (?, ?)")->execute([$userId, $menu]);
            }
            
            $success = "Empresa '$name' cadastrada! Admin: $admin_login";
        } catch (Exception $e) {
            $error = "Erro ao cadastrar: " . $e->getMessage();
        }
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

    if ($_POST['action'] === 'delete_tenant') {
        $id = $_POST['tenant_id'];
        $purgeDate = date('Y-m-d', strtotime('+3 months'));
        // Soft delete: marca data de exclusão e data de purga (3 meses)
        $pdo->prepare("UPDATE tenants SET status = 'inactive', deleted_at = NOW(), purge_after = ? WHERE id = ?")->execute([$purgeDate, $id]);
        // Bloquear todos os usuários da empresa
        $pdo->prepare("UPDATE users SET status = 'Inativo' WHERE company_id = ?")->execute([$id]);
        $success = "Empresa excluída. Dados serão mantidos até $purgeDate.";
    }

    if ($_POST['action'] === 'reactivate_tenant') {
        $id = $_POST['tenant_id'];
        // Reativar empresa e limpar datas de exclusão
        $pdo->prepare("UPDATE tenants SET status = 'active', deleted_at = NULL, purge_after = NULL WHERE id = ?")->execute([$id]);
        // Reativar usuários da empresa
        $pdo->prepare("UPDATE users SET status = 'Ativo' WHERE company_id = ?")->execute([$id]);
        $success = "Empresa reativada com sucesso! Todos os dados foram restaurados.";
    }

    if ($_POST['action'] === 'edit_tenant') {
        try {
            $id = $_POST['tenant_id'];
            $name = $_POST['name'];
            $expires = $_POST['expires_at'];
            $type = $_POST['license_type'];
            $value = $_POST['subscription_value'] ?: 0;
            
            $stmt = $pdo->prepare("UPDATE tenants SET name = ?, expires_at = ?, license_type = ?, subscription_value = ? WHERE id = ?");
            $stmt->execute([$name, $expires, $type, $value, $id]);
            
            // Atualizar também o nome da empresa nas configurações
            $pdo->prepare("UPDATE company_settings SET company_name = ? WHERE id = ?")->execute([$name, $id]);
            
            $success = "Dados da empresa '$name' atualizados com sucesso!";
        } catch (Exception $e) {
            $error = "Erro ao atualizar: " . $e->getMessage();
        }
    }

    if ($_POST['action'] === 'reset_admin_password') {
        try {
            $userId = $_POST['user_id'];
            $newLogin = $_POST['new_login'];
            $newPass = $_POST['new_password'];
            $hashed = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET login_name = ?, password = ? WHERE id = ?");
            $stmt->execute([$newLogin, $hashed, $userId]);
            $success = "Acesso do administrador atualizado! Novo Login: $newLogin";
        } catch (Exception $e) {
            $error = "Erro ao resetar: " . $e->getMessage();
        }
    }

    if ($_POST['action'] === 'add_company_admin') {
        try {
            $companyId = $_POST['tenant_id'];
            $admin_name = $_POST['admin_name'];
            $admin_login = $_POST['admin_login'];
            $admin_password = $_POST['admin_password'];
            
            $hashedPass = password_hash($admin_password, PASSWORD_DEFAULT);
            $userId = 'U' . time() . rand(100,999);
            
            // Obter role_id e unit_id padrao
            $stmtRole = $pdo->prepare("SELECT id FROM roles WHERE company_id = ? AND name = 'admin' LIMIT 1");
            $stmtRole->execute([$companyId]);
            $adminRoleId = $stmtRole->fetchColumn();
            
            $stmtUnit = $pdo->prepare("SELECT id FROM units WHERE company_id = ? LIMIT 1");
            $stmtUnit->execute([$companyId]);
            $defaultUnitId = $stmtUnit->fetchColumn();
            
            $stmtUser = $pdo->prepare("INSERT INTO users (id, login_name, name, password, company_id, status, is_super_admin, role, role_id, unit_id, sector) VALUES (?, ?, ?, ?, ?, 'Ativo', 0, 'Administrador', ?, ?, 'Geral')");
            $stmtUser->execute([$userId, $admin_login, $admin_name, $hashedPass, $companyId, $adminRoleId ?: null, $defaultUnitId ?: null]);
            
            $allMenus = [
                'dashboard', 'rh', 'voluntariado', 'semanada', 'patrimonio', 
                'emprestimos', 'chamados', 'orcamentos', 'locacao_salas', 
                'relatorios', 'tecnologia', 'informacoes', 'usuarios', 
                'configuracoes', 'peixinho'
            ];
            foreach ($allMenus as $menu) {
                $pdo->prepare("INSERT IGNORE INTO user_menus (user_id, menu) VALUES (?, ?)")->execute([$userId, $menu]);
            }
            
            $success = "Administrador '$admin_login' cadastrado com sucesso para a empresa!";
        } catch (Exception $e) {
            $error = "Erro ao cadastrar administrador: " . $e->getMessage();
        }
    }
}

// Purga automática: apagar dados de empresas com mais de 3 meses de exclusão
try {
    $expired = $pdo->query("SELECT id FROM tenants WHERE purge_after IS NOT NULL AND purge_after < CURDATE()")->fetchAll();
    foreach ($expired as $exp) {
        $cid = $exp['id'];
        $pdo->prepare("DELETE FROM user_menus WHERE user_id IN (SELECT id FROM users WHERE company_id = ?)")->execute([$cid]);
        $pdo->prepare("DELETE FROM users WHERE company_id = ?")->execute([$cid]);
        $pdo->prepare("DELETE FROM company_settings WHERE id = ?")->execute([$cid]);
        $pdo->prepare("DELETE FROM tenants WHERE id = ?")->execute([$cid]);
    }
} catch(Exception $e) {}

// Buscar Tenants (incluindo excluídos para o admin ver)
$stmt = $pdo->query("SELECT t.*, 
    (SELECT COUNT(*) FROM users WHERE company_id = t.id) as user_count,
    (SELECT login_name FROM users WHERE company_id = t.id ORDER BY (role = 'Administrador' OR is_super_admin = 1) DESC, id ASC LIMIT 1) as admin_login,
    (SELECT id FROM users WHERE company_id = t.id ORDER BY (role = 'Administrador' OR is_super_admin = 1) DESC, id ASC LIMIT 1) as admin_user_id
    FROM tenants t ORDER BY t.deleted_at IS NOT NULL, t.id DESC");
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
<?php if (isset($error)): ?>
    <div style="background: rgba(239,68,68,0.1); color: #EF4444; padding: 1.2rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid rgba(239,68,68,0.2); display: flex; align-items: center; gap: 10px;">
        <i class="fa-solid fa-circle-xmark" style="font-size: 1.5rem;"></i> <?= $error ?>
    </div>
<?php endif; ?>

<div style="margin-bottom: 2rem;">
    <div style="display: flex; gap: 1rem; border-bottom: 2px solid rgba(255,255,255,0.1); padding-bottom: 0;">
        <div onclick="switchSaasTab('empresas')" id="tab_empresas" style="padding: 1rem 2rem; cursor: pointer; border-bottom: 3px solid var(--brand-primary); color: white; font-weight: 800; font-size: 1.1rem; transition: 0.3s;">
            <i class="fa-solid fa-building"></i> Empresas
        </div>
        <div onclick="switchSaasTab('relatorios')" id="tab_relatorios" style="padding: 1rem 2rem; cursor: pointer; border-bottom: 3px solid transparent; color: var(--text-soft); font-weight: 800; font-size: 1.1rem; transition: 0.3s;">
            <i class="fa-solid fa-chart-pie"></i> Relatórios
        </div>
    </div>
</div>

<script>
function switchSaasTab(tab) {
    document.getElementById('content_empresas').style.display = tab === 'empresas' ? 'block' : 'none';
    document.getElementById('content_relatorios').style.display = tab === 'relatorios' ? 'block' : 'none';
    
    document.getElementById('tab_empresas').style.borderBottomColor = tab === 'empresas' ? 'var(--brand-primary)' : 'transparent';
    document.getElementById('tab_empresas').style.color = tab === 'empresas' ? 'white' : 'var(--text-soft)';
    
    document.getElementById('tab_relatorios').style.borderBottomColor = tab === 'relatorios' ? 'var(--brand-primary)' : 'transparent';
    document.getElementById('tab_relatorios').style.color = tab === 'relatorios' ? 'white' : 'var(--text-soft)';

    if (tab === 'relatorios' && !window.saasChartsLoaded) {
        loadSaasReports();
    }
}
</script>

<div id="content_empresas" style="display: block;">
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
                        <?php if ($tenant['admin_login']): ?>
                            <div style="font-size: 0.7rem; color: #3B82F6; font-weight: 700; margin-top: 4px;">
                                <i class="fa-solid fa-user-shield"></i> Admin: <?= htmlspecialchars($tenant['admin_login']) ?>
                            </div>
                        <?php endif; ?>
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
                        <?php $isDeleted = !empty($tenant['deleted_at']); ?>
                        <span class="badge" style="background: <?= $isDeleted ? 'rgba(239,68,68,0.1)' : ($tenant['status'] === 'active' ? ($isExpired ? 'rgba(239,68,68,0.1)' : 'rgba(16,185,129,0.1)') : 'rgba(100,116,139,0.1)') ?>; color: <?= $isDeleted ? '#EF4444' : ($tenant['status'] === 'active' ? ($isExpired ? '#EF4444' : '#10B981') : '#64748B') ?>; padding: 0.5rem 1rem; border-radius: 100px;">
                            <?= $isDeleted ? 'Excluído' : ($isExpired ? 'Expirado' : ($tenant['status'] === 'active' ? 'Ativo' : 'Bloqueado')) ?>
                        </span>
                        <?php if ($isDeleted && $tenant['purge_after']): ?>
                            <div style="font-size:0.65rem;color:#EF4444;margin-top:4px">Purga: <?= date('d/m/Y', strtotime($tenant['purge_after'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 1.5rem;">
                        <div style="display: flex; gap: 0.5rem;">
                            <?php if ($isDeleted): ?>
                                <!-- Reativar -->
                                <form method="POST" style="display:inline"><input type="hidden" name="action" value="reactivate_tenant"><input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                                    <button type="submit" style="padding:0.5rem 0.8rem;font-size:0.75rem;background:rgba(16,185,129,0.1);color:#10B981;border:none;cursor:pointer;border-radius:6px;font-weight:700" title="Reativar"><i class="fa-solid fa-rotate-left"></i></button>
                                </form>
                            <?php else: ?>
                                <!-- Renovar -->
                                <button onclick="openRenewModal(<?= $tenant['id'] ?>, '<?= htmlspecialchars($tenant['name']) ?>', <?= $tenant['subscription_value'] ?>)" class="btn-primary" style="padding: 0.5rem 0.8rem; font-size: 0.75rem; background: #FBBF24; color: #000;" title="Renovar/Pagar">
                                    <i class="fa-solid fa-money-bill-transfer"></i>
                                </button>
                                <!-- Editar -->
                                <button onclick="openEditModal(<?= $tenant['id'] ?>, '<?= htmlspecialchars(addslashes($tenant['name'])) ?>', '<?= $tenant['expires_at'] ?>', '<?= $tenant['license_type'] ?>', <?= $tenant['subscription_value'] ?>)" class="btn-secondary" style="padding: 0.5rem 0.8rem; font-size: 0.75rem; background: rgba(59, 130, 246, 0.1); color: #3B82F6; border: none; cursor: pointer; border-radius: 6px;" title="Editar Cadastro">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <!-- Reset de Senha do Admin -->
                                <?php if ($tenant['admin_user_id']): ?>
                                    <button onclick="openResetModal('<?= $tenant['admin_user_id'] ?>', '<?= htmlspecialchars(addslashes($tenant['admin_login'])) ?>')" class="btn-secondary" style="padding: 0.5rem 0.8rem; font-size: 0.75rem; background: rgba(239, 68, 68, 0.05); color: #EF4444; border: none; cursor: pointer; border-radius: 6px;" title="Resetar Senha do Admin">
                                        <i class="fa-solid fa-key"></i>
                                    </button>
                                <?php else: ?>
                                    <button onclick="openAddAdminModal(<?= $tenant['id'] ?>, '<?= htmlspecialchars(addslashes($tenant['name'])) ?>')" class="btn-secondary" style="padding: 0.5rem 0.8rem; font-size: 0.75rem; background: rgba(16, 185, 129, 0.1); color: #10B981; border: none; cursor: pointer; border-radius: 6px;" title="Cadastrar Administrador">
                                        <i class="fa-solid fa-user-plus"></i>
                                    </button>
                                <?php endif; ?>
                                <!-- Bloquear/Desbloquear -->
                                <form method="POST" style="display: inline;"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>"><input type="hidden" name="status" value="<?= $tenant['status'] ?>">
                                    <button type="submit" class="btn-secondary" style="padding: 0.5rem 0.8rem; font-size: 0.75rem; background: rgba(255,255,255,0.05);" title="<?= $tenant['status'] === 'active' ? 'Bloquear' : 'Desbloquear' ?>">
                                        <i class="fa-solid <?= $tenant['status'] === 'active' ? 'fa-ban' : 'fa-check' ?>"></i>
                                    </button>
                                </form>
                                <!-- Excluir -->
                                <form method="POST" style="display:inline" onsubmit="return confirm('Tem certeza? Os dados serão mantidos por 3 meses.')"><input type="hidden" name="action" value="delete_tenant"><input type="hidden" name="tenant_id" value="<?= $tenant['id'] ?>">
                                    <button type="submit" style="padding:0.5rem 0.8rem;font-size:0.75rem;background:rgba(239,68,68,0.1);color:#EF4444;border:none;cursor:pointer;border-radius:6px" title="Excluir"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div> <!-- Fechamento da div content_empresas -->

<div id="content_relatorios" style="display: none;">
    <!-- Cards Superiores -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <div class="glass-panel" style="padding: 1.5rem; border-left: 4px solid #3b82f6;">
            <h4 style="color: var(--text-soft); font-size: 0.85rem; text-transform: uppercase; margin-bottom: 0.5rem; font-weight:800;">Total Usuários SaaS</h4>
            <div id="rep_tot_users" style="font-size: 2rem; font-weight: 900; color: white;">0</div>
        </div>
        <div class="glass-panel" style="padding: 1.5rem; border-left: 4px solid #10b981;">
            <h4 style="color: var(--text-soft); font-size: 0.85rem; text-transform: uppercase; margin-bottom: 0.5rem; font-weight:800;">Total Ativos (Valor Estimado)</h4>
            <div id="rep_tot_assets_val" style="font-size: 2rem; font-weight: 900; color: white;">R$ 0,00</div>
        </div>
        <div class="glass-panel" style="padding: 1.5rem; border-left: 4px solid #f59e0b;">
            <h4 style="color: var(--text-soft); font-size: 0.85rem; text-transform: uppercase; margin-bottom: 0.5rem; font-weight:800;">Voluntariado (Valor Retornado)</h4>
            <div id="rep_tot_vol_val" style="font-size: 2rem; font-weight: 900; color: white;">R$ 0,00</div>
        </div>
        <div class="glass-panel" style="padding: 1.5rem; border-left: 4px solid #ef4444;">
            <h4 style="color: var(--text-soft); font-size: 0.85rem; text-transform: uppercase; margin-bottom: 0.5rem; font-weight:800;">Total Chamados Abertos</h4>
            <div id="rep_tot_tickets" style="font-size: 2rem; font-weight: 900; color: white;">0</div>
        </div>
    </div>

    <!-- Linha 1 de Gráficos -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Gráfico Usuários -->
        <div class="glass-panel" style="padding: 1.5rem;">
            <h3 style="font-size: 1.2rem; font-weight: 800; margin-bottom: 1.5rem; color: white;">Usuários por Empresa</h3>
            <div style="height: 300px; position: relative;">
                <canvas id="chartUsers"></canvas>
            </div>
            <div id="tableUsers" style="margin-top: 1rem; max-height: 200px; overflow-y: auto;"></div>
        </div>

        <!-- Gráfico Ativos / Patrimônio -->
        <div class="glass-panel" style="padding: 1.5rem;">
            <h3 style="font-size: 1.2rem; font-weight: 800; margin-bottom: 1.5rem; color: white;">Patrimônio (Valor) por Empresa</h3>
            <div style="height: 300px; position: relative;">
                <canvas id="chartAssets"></canvas>
            </div>
            <div id="tableAssets" style="margin-top: 1rem; max-height: 200px; overflow-y: auto;"></div>
        </div>
    </div>

    <!-- Linha 2 de Gráficos -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <!-- Gráfico Voluntariado -->
        <div class="glass-panel" style="padding: 1.5rem;">
            <h3 style="font-size: 1.2rem; font-weight: 800; margin-bottom: 1.5rem; color: white;">Horas de Voluntariado por Empresa</h3>
            <div style="height: 300px; position: relative;">
                <canvas id="chartVolunteers"></canvas>
            </div>
            <div id="tableVolunteers" style="margin-top: 1rem; max-height: 200px; overflow-y: auto;"></div>
        </div>

        <!-- Gráfico Voluntariado Sexo/Setor -->
        <div class="glass-panel" style="padding: 1.5rem;">
            <h3 style="font-size: 1.2rem; font-weight: 800; margin-bottom: 1.5rem; color: white;">Voluntários por Gênero</h3>
            <div style="height: 300px; position: relative;">
                <canvas id="chartVolGender"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
window.saasChartsLoaded = false;

function formatCurrency(val) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val);
}

const colorPalette = [
    '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316'
];

function loadSaasReports() {
    window.saasChartsLoaded = true;
    
    fetch('api_saas_reports.php')
    .then(res => res.json())
    .then(data => {
        if (data.error) {
            alert(data.error);
            return;
        }

        document.getElementById('rep_tot_users').innerText = data.users.grand_total;
        document.getElementById('rep_tot_assets_val').innerText = formatCurrency(data.assets.grand_total_value);
        document.getElementById('rep_tot_vol_val').innerText = formatCurrency(data.volunteers.grand_total_returned);
        document.getElementById('rep_tot_tickets').innerText = data.tickets.grand_total;

        Chart.defaults.color = 'rgba(255, 255, 255, 0.7)';
        Chart.defaults.font.family = "'Inter', sans-serif";

        const userLabels = data.users.data.map(d => d.company_name);
        const userData = data.users.data.map(d => d.total_users);
        new Chart(document.getElementById('chartUsers'), {
            type: 'doughnut',
            data: { labels: userLabels, datasets: [{ data: userData, backgroundColor: colorPalette, borderWidth: 0 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
        });
        
        let htmlUsr = '<table style="width:100%; border-collapse:collapse; color:white; font-size:0.85rem;">';
        data.users.data.forEach(d => {
            htmlUsr += `<tr style="border-bottom:1px solid rgba(255,255,255,0.1);"><td style="padding:8px;">${d.company_name}</td><td style="padding:8px; text-align:right;">${d.total_users} (${d.percentage}%)</td></tr>`;
        });
        htmlUsr += '</table>';
        document.getElementById('tableUsers').innerHTML = htmlUsr;

        const assetLabels = data.assets.data.map(d => d.company_name);
        const assetDataVal = data.assets.data.map(d => d.total_value);
        new Chart(document.getElementById('chartAssets'), {
            type: 'bar',
            data: { labels: assetLabels, datasets: [{ label: 'Valor (R$)', data: assetDataVal, backgroundColor: '#10b981', borderRadius: 4 }] },
            options: { responsive: true, maintainAspectRatio: false }
        });

        let htmlAssets = '<table style="width:100%; border-collapse:collapse; color:white; font-size:0.85rem;">';
        data.assets.data.forEach(d => {
            htmlAssets += `<tr style="border-bottom:1px solid rgba(255,255,255,0.1);"><td style="padding:8px;">${d.company_name}</td><td style="padding:8px; text-align:center;">${d.total_assets} itens</td><td style="padding:8px; text-align:right;">${formatCurrency(d.total_value)} (${d.val_percentage}%)</td></tr>`;
        });
        htmlAssets += '</table>';
        document.getElementById('tableAssets').innerHTML = htmlAssets;

        const volLabels = data.volunteers.data.map(d => d.company_name);
        const volDataHs = data.volunteers.data.map(d => d.total_hours);
        new Chart(document.getElementById('chartVolunteers'), {
            type: 'bar',
            data: { labels: volLabels, datasets: [{ label: 'Horas Voluntárias', data: volDataHs, backgroundColor: '#f59e0b', borderRadius: 4 }] },
            options: { responsive: true, maintainAspectRatio: false }
        });

        let htmlVol = '<table style="width:100%; border-collapse:collapse; color:white; font-size:0.85rem;">';
        data.volunteers.data.forEach(d => {
            htmlVol += `<tr style="border-bottom:1px solid rgba(255,255,255,0.1);"><td style="padding:8px;">${d.company_name}</td><td style="padding:8px; text-align:center;">${d.total_volunteers} vols</td><td style="padding:8px; text-align:right;">${d.total_hours}h (${d.hours_percentage}%)<br><span style="color:#10b981;">Retorno: ${formatCurrency(d.returned_value)}</span></td></tr>`;
        });
        htmlVol += '</table>';
        document.getElementById('tableVolunteers').innerHTML = htmlVol;

        const genderLabels = data.volunteers_gender.map(d => d.gender);
        const genderData = data.volunteers_gender.map(d => d.total);
        new Chart(document.getElementById('chartVolGender'), {
            type: 'pie',
            data: { labels: genderLabels, datasets: [{ data: genderData, backgroundColor: ['#3b82f6', '#ec4899', '#8b5cf6'], borderWidth: 0 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });

    }).catch(err => {
        alert("Erro ao carregar relatórios SaaS: " + err);
    });
}
</script>

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

<!-- Modal Editar Cliente -->
<div id="editTenantModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(10px); z-index: 3000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 500px; width: 100%; padding: 2.5rem; max-height: 90vh; overflow-y: auto;">
        <h3 id="editModalTitle" style="font-size: 1.5rem; font-weight: 900; color: var(--text-main); margin-bottom: 2rem;">Editar Empresa</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit_tenant">
            <input type="hidden" name="tenant_id" id="editTenantId">
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-soft); margin-bottom: 0.5rem;">Nome da Empresa</label>
                <input type="text" name="name" id="editTenantName" required class="form-input">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-soft); margin-bottom: 0.5rem;">Valor do Plano (R$)</label>
                    <input type="number" step="0.01" name="subscription_value" id="editTenantValue" required class="form-input">
                </div>
                <div>
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-soft); margin-bottom: 0.5rem;">Tipo</label>
                    <select name="license_type" id="editTenantType" class="form-input">
                        <option value="monthly">Mensal</option>
                        <option value="yearly">Anual</option>
                        <option value="lifetime">Vitalício</option>
                    </select>
                </div>
            </div>
            <div style="margin-bottom: 2rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-soft); margin-bottom: 0.5rem;">Data de Vencimento</label>
                <input type="date" name="expires_at" id="editTenantExpires" required class="form-input">
            </div>
            <button type="submit" class="btn-primary" style="width: 100%; padding: 1.2rem; border-radius: 15px; font-weight: 800; font-size: 1rem; background: #3B82F6; color: white;">Salvar Alterações</button>
        </form>
        <button onclick="document.getElementById('editTenantModal').style.display='none'" style="width: 100%; background: none; border: none; color: var(--text-soft); margin-top: 1rem; cursor: pointer;">Cancelar</button>
    </div>
</div>

<!-- Modal Reset Senha -->
<div id="resetModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(10px); z-index: 3000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 400px; width: 100%; padding: 2.5rem;">
        <h3 style="font-size: 1.25rem; font-weight: 900; color: #EF4444; margin-bottom: 1rem;"><i class="fa-solid fa-key"></i> Resetar Senha Admin</h3>
        <p id="resetModalText" style="color: var(--text-soft); font-size: 0.85rem; margin-bottom: 2rem;"></p>
        <form method="POST">
            <input type="hidden" name="action" value="reset_admin_password">
            <input type="hidden" name="user_id" id="resetUserId">
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-soft); margin-bottom: 0.5rem;">Novo Login</label>
                <input type="text" name="new_login" id="resetLoginInput" required class="form-input" placeholder="Novo nome de usuário">
            </div>
            <div style="margin-bottom: 2rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-soft); margin-bottom: 0.5rem;">Nova Senha</label>
                <input type="text" name="new_password" required class="form-input" placeholder="Nova senha">
            </div>
            <div style="display: flex; gap: 1rem;">
                <button type="button" onclick="document.getElementById('resetModal').style.display='none'" class="btn-secondary" style="flex: 1;">Cancelar</button>
                <button type="submit" class="btn-primary" style="flex: 1; background: #EF4444; color: white;">Resetar Agora</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Cadastrar Admin -->
<div id="addAdminModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(10px); z-index: 3000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 450px; width: 100%; padding: 2.5rem;">
        <h3 id="addAdminModalTitle" style="font-size: 1.5rem; font-weight: 900; color: #10B981; margin-bottom: 2rem;">Cadastrar Administrador</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_company_admin">
            <input type="hidden" name="tenant_id" id="addAdminTenantId">
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-soft); margin-bottom: 0.5rem;">Nome Completo</label>
                <input type="text" name="admin_name" required class="form-input" placeholder="Nome do administrador">
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-soft); margin-bottom: 0.5rem;">Login</label>
                <input type="text" name="admin_login" required class="form-input" placeholder="usuario_admin">
            </div>
            <div style="margin-bottom: 2rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--text-soft); margin-bottom: 0.5rem;">Senha</label>
                <input type="text" name="admin_password" required class="form-input" placeholder="Senha segura">
            </div>
            <div style="display: flex; gap: 1rem;">
                <button type="button" onclick="document.getElementById('addAdminModal').style.display='none'" class="btn-secondary" style="flex: 1; padding: 1rem; border-radius: 12px;">Cancelar</button>
                <button type="submit" class="btn-primary" style="flex: 1; padding: 1rem; border-radius: 12px; background: #10B981; color: white;">Cadastrar</button>
            </div>
        </form>
    </div>
</div>


<script>
function openRenewModal(id, name, value) {
    document.getElementById('renewTenantId').value = id;
    document.getElementById('renewTitle').innerText = 'Renovar: ' + name;
    document.getElementById('renewAmount').value = value;
    document.getElementById('renewModal').style.display = 'flex';
}

function openEditModal(id, name, expires, type, value) {
    document.getElementById('editTenantId').value = id;
    document.getElementById('editTenantName').value = name;
    document.getElementById('editTenantValue').value = value;
    document.getElementById('editTenantType').value = type;
    document.getElementById('editTenantExpires').value = expires.split(' ')[0];
    document.getElementById('editModalTitle').innerText = 'Editar: ' + name;
    document.getElementById('editTenantModal').style.display = 'flex';
}

function openResetModal(userId, login) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('resetLoginInput').value = login;
    document.getElementById('resetModalText').innerText = 'Você está alterando as credenciais de: ' + login;
    document.getElementById('resetModal').style.display = 'flex';
}

function openAddAdminModal(id, name) {
    document.getElementById('addAdminTenantId').value = id;
    document.getElementById('addAdminModalTitle').innerText = 'Admin para: ' + name;
    document.getElementById('addAdminModal').style.display = 'flex';
}
</script>
