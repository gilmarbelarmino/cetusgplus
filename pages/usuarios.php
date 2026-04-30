<?php
// Protecao contra erros fatais - exibir em vez de tela branca
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Auto-migrate: colunas extras na tabela users
try { $pdo->exec("ALTER TABLE users ADD COLUMN gender VARCHAR(20) DEFAULT ''"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN position VARCHAR(100) DEFAULT ''"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN access_number VARCHAR(100) DEFAULT ''"); } catch(Exception $e) {}

// Permitir e-mail nulo para evitar erro de duplicidade quando não preenchido
try { $pdo->exec("ALTER TABLE users MODIFY COLUMN email VARCHAR(255) NULL"); } catch(Exception $e) {}
try { $pdo->exec("UPDATE users SET email = NULL WHERE email = ''"); } catch(Exception $e) {}

// Tabela para contas de e-mail extras
try { 
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        email VARCHAR(255) NOT NULL,
        password VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )"); 
} catch(Exception $e) {}

$all_menus = [
    'dashboard'     => 'Dashboard',
    'patrimonio'    => 'Patrimônio',
    'chamados'      => 'Chamados',
    'emprestimos'   => 'Empréstimos',
    'orcamentos'    => 'Orçamentos',
    'voluntariado'  => 'Voluntariado',
    'relatorios'    => 'Relatórios',
    'locacao_salas' => 'Locação de Sala',
    'usuarios'      => 'Usuários',
    'rh'            => 'Recursos Humanos',
    'configuracoes' => 'Configurações',
    'tecnologia'    => 'Tecnologia',
    'semanada'      => 'Semanada',
    'peixinho'      => 'Peixinho (Assistente IA)',
];

// Garantir tabela user_menus existe
try { $pdo->exec("CREATE TABLE IF NOT EXISTS user_menus (user_id VARCHAR(50) NOT NULL, menu VARCHAR(50) NOT NULL, PRIMARY KEY (user_id, menu))"); } catch(Exception $e) {}



$search = $_GET['search'] ?? '';
$unit_filter = $_GET['unit'] ?? '';
$all_users = [];
$sectors_list = [];
$units = [];
$sectors = [];
$history_loans = [];
$history_tickets = [];

    $compId = getCurrentUserCompanyId();
    $isSuperAdmin = ($user['is_super_admin'] ?? 0) == 1 || ($user['login_name'] ?? '') === 'superadmin';
    
    // 1. BUSCA PRINCIPAL DE USUÁRIOS
    try {
        $query = "SELECT u.* FROM users u WHERE 1=1";
        $params = [];
        if (!$isSuperAdmin) {
            $query .= " AND (u.company_id = ? OR u.company_id IS NULL OR u.company_id = 0)";
            $params[] = ($compId ?: 1);
        }
        $query .= " ORDER BY u.name ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $all_users = $stmt->fetchAll();
    } catch(Exception $e) {
        $all_users = [];
        echo "<!-- Erro Query Principal: " . $e->getMessage() . " -->";
    }

    // 2. BUSCA DE CONTAS EXTRAS
    $user_accounts_map = [];
    try {
        $stmt_acc = $pdo->prepare("SELECT ua.* FROM user_accounts ua JOIN users u ON ua.user_id = u.id WHERE u.company_id = ?");
        $stmt_acc->execute([$compId ?: 1]);
        $accounts_raw = $stmt_acc->fetchAll();
        foreach ($accounts_raw as $acc) {
            $user_accounts_map[$acc['user_id']][] = $acc;
        }
    } catch(Exception $e) {
        echo "<!-- Erro Query Contas: " . $e->getMessage() . " -->";
    }

    // 3. PROCESSAMENTO FINAL
    foreach ($all_users as $usr) {
        $usr['unit_name'] = ''; 
        $usr['tenant_name'] = '';
        $usr['menus'] = getUserMenus($pdo, $usr['id'] ?? null);
        $usr['accounts'] = $user_accounts_map[$usr['id']] ?? [];
        $sectors_list[$usr['sector'] ?? 'Sem Setor'][] = $usr;
    }
    echo "<!-- DEBUG: Total processado: " . count($all_users) . " usuários -->";
} catch(Exception $e) { echo "<!-- Erro Geral: " . $e->getMessage() . " -->"; }

try { 
    $stmt_units = $pdo->prepare("SELECT * FROM units WHERE company_id = ?");
    $stmt_units->execute([$compId]);
    $units = $stmt_units->fetchAll(); 
} catch(Exception $e) { $units = []; }

try { 
    $stmt_sects = $pdo->prepare("SELECT s.id, s.name, s.unit_id FROM sectors s WHERE s.company_id = ? ORDER BY s.name");
    $stmt_sects->execute([$compId]);
    $sectors = $stmt_sects->fetchAll(); 
} catch(Exception $e) { $sectors = []; }

try { 
    $stmt_pos = $pdo->prepare("SELECT * FROM rh_positions WHERE company_id = ? ORDER BY name ASC");
    $stmt_pos->execute([$compId]);
    $all_positions = $stmt_pos->fetchAll(); 
} catch(Exception $e) { $all_positions = []; }

try { 
    $stmt_loans = $pdo->prepare("SELECT l.*, a.name as asset_full_name FROM loans l LEFT JOIN assets a ON BINARY l.asset_id = BINARY a.id WHERE l.company_id = ? ORDER BY l.loan_date DESC");
    $stmt_loans->execute([$compId]);
    $history_loans = $stmt_loans->fetchAll(); 
} catch(Exception $e) { $history_loans = []; }

try { 
    $stmt_tix = $pdo->prepare("SELECT t.*, u.name as unit_name FROM tickets t LEFT JOIN units u ON BINARY t.unit_id = BINARY u.id WHERE t.company_id = ? ORDER BY t.created_at DESC");
    $stmt_tix->execute([$compId]);
    $history_tickets = $stmt_tix->fetchAll(); 
} catch(Exception $e) { $history_tickets = []; }
?>
<script>
    // Dados consolidados no topo para evitar atrasos de carregamento
    const sectorsData = <?= json_encode($sectors ?: []) ?>;
    const usersData = <?= json_encode($all_users ?: []) ?>;
    
    // Cache de dados para modais de histórico (já processados no PHP)
    let allLoansData = <?= json_encode($history_loans ?: []) ?>;
    let allTicketsData = <?= json_encode($history_tickets ?: []) ?>;

    // Funções de Interface
    function editUser(userId) {
        const user = usersData.find(u => u.id == userId);
        if (!user) return;
        
        document.getElementById('edit_user_id').value = user.id;
        document.getElementById('edit_current_avatar').value = user.avatar_url || '';
        document.getElementById('edit_name').value = user.name;
        document.getElementById('edit_email').value = user.email;
        document.getElementById('edit_unit').value = user.unit_id;
        document.getElementById('edit_role').value = user.role;
        document.getElementById('edit_phone').value = user.phone || '';
        document.getElementById('edit_gender').value = user.gender || user.rh_gender || '';
        document.getElementById('edit_position').value = user.position || user.rh_role_name || '';
        document.getElementById('edit_access_number').value = user.access_number || '';

        // Carregar contas de e-mail extras
        const accountsContainer = document.getElementById('edit_accounts_container');
        accountsContainer.innerHTML = '';
        if (user.accounts && user.accounts.length > 0) {
            user.accounts.forEach(acc => {
                addAccountRow('edit_accounts_container', acc.email, acc.password);
            });
        }

        const preview = document.getElementById('edit_avatar_preview');
        if (user.avatar_url) {
            preview.innerHTML = `<img src="${user.avatar_url}" style="width:100%;height:100%;object-fit:cover;">`;
        } else {
            preview.innerHTML = '<i class="fa-solid fa-user"></i>';
        }

        document.querySelectorAll('[id^="edit_menu_"]').forEach(cb => {
            cb.checked = user.menus && user.menus.includes(cb.value);
        });
        
        if (typeof filterSectors === 'function') filterSectors('edit');
        setTimeout(() => {
            document.getElementById('edit_sector').value = user.sector;
        }, 150);
        
        document.getElementById('editModal').style.display = 'flex';
    }

    function resetPassword(userId) {
        const user = usersData.find(u => u.id == userId);
        if (!user) return;
        document.getElementById('password_user_id').value = user.id;
        document.getElementById('password_new_login').value = user.login_name || '';
        document.getElementById('passwordModal').style.display = 'flex';
    }

    function deleteUser(userId, userName) {
        document.getElementById('delete_user_id').value = userId;
        document.getElementById('delete_user_name').textContent = userName;
        document.getElementById('deleteModal').style.display = 'flex';
    }

    function showUserLoanHistory(userId, userName) {
        const userLoans = allLoansData.filter(l => l.borrower_id == userId);
        const modal = document.getElementById('userLoanHistoryModal');
        document.getElementById('userLoanHistoryTitle').textContent = 'Empréstimos de ' + userName;
        const content = document.getElementById('userLoanHistoryContent');
        
        if (userLoans.length === 0) {
            content.innerHTML = '<div style="text-align:center; padding: 3rem; color:#94a3b8;"><i class="fa-solid fa-box-open" style="font-size:2rem; margin-bottom:1rem; display:block;"></i>Nenhum empréstimo registrado.</div>';
        } else {
            let html = '<div style="display: grid; gap: 0.75rem;">';
            userLoans.forEach(l => {
                const statusColor = l.status === 'Ativo' ? '#FBBF24' : '#10B981';
                html += `<div style="padding:1rem; border-radius:0.75rem; border-left: 4px solid ${statusColor}; background:var(--bg-main); border: 1px solid var(--border-color); margin-bottom: 0.5rem;">
                    <div style="font-weight:900; color:var(--text-main);">${l.asset_full_name || 'Item'}</div>
                    <div style="font-size:0.75rem; color:var(--text-soft);">Data: ${l.loan_date} | Status: ${l.status}</div>
                </div>`;
            });
            html += '</div>';
            content.innerHTML = html;
        }
        modal.style.display = 'flex';
    }

    function showUserTicketHistory(userId, userName) {
        const userTickets = allTicketsData.filter(t => t.requester_id == userId);
        const modal = document.getElementById('userTicketHistoryModal');
        document.getElementById('userTicketHistoryTitle').textContent = 'Chamados de ' + userName;
        const content = document.getElementById('userTicketHistoryContent');
        
        if (userTickets.length === 0) {
            content.innerHTML = '<div style="text-align:center; padding: 3rem; color:#94a3b8;"><i class="fa-solid fa-headset" style="font-size:2rem; margin-bottom:1rem; display:block;"></i>Nenhum chamado registrado.</div>';
        } else {
            let html = '<div style="display: grid; gap: 0.75rem;">';
            userTickets.forEach(t => {
                const sc = t.status === 'Concluído' ? '#10B981' : '#3B82F6';
                html += `<div style="padding:1rem; border-radius:0.75rem; border-left: 4px solid ${sc}; background:var(--bg-main); border: 1px solid var(--border-color); margin-bottom: 0.5rem;">
                    <div style="font-weight:900; color:var(--text-main);">${t.title}</div>
                    <div style="font-size:0.75rem; color:var(--text-soft);">Prioridade: ${t.priority} | Status: ${t.status}</div>
                </div>`;
            });
            html += '</div>';
            content.innerHTML = html;
        }
        modal.style.display = 'flex';
    }

    function filterSectors(mode) {
        const unitId = document.getElementById(mode === 'edit' ? 'edit_unit' : 'add_unit_id').value;
        const sectorSelect = document.getElementById(mode + '_sector');
        sectorSelect.innerHTML = '<option value="">Selecione o setor</option>';
        sectorsData.forEach(s => {
            if (s.unit_id == unitId) {
                const opt = document.createElement('option');
                opt.value = s.name;
                opt.textContent = s.name;
                sectorSelect.appendChild(opt);
            }
        });
    }

    // Gerenciamento de Contas de E-mail Dinâmicas
    function addAccountRow(containerId, email = '', password = '') {
        const container = document.getElementById(containerId);
        const rowId = 'acc_' + Date.now() + Math.random().toString(36).substr(2, 5);
        const row = document.createElement('div');
        row.id = rowId;
        row.style = 'display: grid; grid-template-columns: 1fr 1fr 40px; gap: 0.5rem; margin-bottom: 0.5rem;';
        row.innerHTML = `
            <input type="email" name="extra_emails[]" class="form-input" placeholder="E-mail" value="${email}">
            <input type="text" name="extra_passwords[]" class="form-input" placeholder="Senha do E-mail" value="${password}">
            <button type="button" onclick="removeAccountRow('${rowId}')" class="btn-icon" style="color:#ef4444; border-color:#fee2e2; background:#fef2f2;">
                <i class="fa-solid fa-trash-can"></i>
            </button>
        `;
        container.appendChild(row);
    }

    function removeAccountRow(rowId) {
        const row = document.getElementById(rowId);
        if (row) row.remove();
    }
</script>

<style>
    .user-card-content {
        display: grid;
        grid-template-columns: 1.5fr 1fr 1fr 1fr 0.8fr 180px;
        gap: 1rem;
        align-items: center;
        width: 100%;
    }

    @media (max-width: 1200px) {
        .user-card-content {
            grid-template-columns: 1.5fr 1fr 1fr 1fr 0.8fr;
        }
        .user-actions-container {
            grid-column: span 5;
            justify-content: flex-start !important;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px dashed #e2e8f0;
        }
    }

    @media (max-width: 768px) {
        .user-card-content {
            grid-template-columns: 1fr 1fr;
        }
        .user-actions-container {
            grid-column: span 2;
        }
    }

    @media (max-width: 480px) {
        .user-card-content {
            grid-template-columns: 1fr;
        }
        .user-actions-container {
            grid-column: span 1;
        }
        .user-card-main {
            flex-direction: column;
            text-align: center;
        }
        .user-card-main img, .user-card-main .avatar-placeholder {
            margin: 0 auto;
        }
    }
</style>
<?php


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $compId = getCurrentUserCompanyId();
    $del_id = $_POST['user_id'];
    if ($del_id !== $user['id']) {
        // Nullificar FKs antes de excluir - filtrados por company_id
        $pdo->prepare("UPDATE tickets SET requester_id = NULL WHERE requester_id = ? AND company_id = ?")->execute([$del_id, $compId]);
        $pdo->prepare("UPDATE budget_requests SET requester_id = NULL WHERE requester_id = ? AND company_id = ?")->execute([$del_id, $compId]);
        $pdo->prepare("UPDATE volunteers SET user_id = NULL WHERE user_id = ? AND company_id = ?")->execute([$del_id, $compId]);
        // Deletes manuais do modulo RH (substituto à FOREIGN KEY nativa)
        $pdo->prepare("DELETE FROM rh_employee_details WHERE user_id = ? AND company_id = ?")->execute([$del_id, $compId]);
        $pdo->prepare("DELETE FROM rh_vacations WHERE user_id = ? AND company_id = ?")->execute([$del_id, $compId]);
        $pdo->prepare("DELETE FROM rh_certificates WHERE user_id = ? AND company_id = ?")->execute([$del_id, $compId]);
        // Menus e Usuário Base
        $pdo->prepare("DELETE FROM user_menus WHERE user_id = ?")->execute([$del_id]); // user_id é único por menu
        $pdo->prepare("DELETE FROM users WHERE id = ? AND company_id = ?")->execute([$del_id, $compId]);
    }
    header('Location: ?page=usuarios&success=4');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_user') {
    $avatar_url = $_POST['current_avatar'] ?? null;
    if (!empty($_FILES['avatar']['name'])) {
        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . $_POST['user_id'] . '.' . $ext;
        $dest = __DIR__ . '/../uploads/' . $filename;
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
            $avatar_url = 'uploads/' . $filename;
        }
    }
    $compId = getCurrentUserCompanyId();
    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, sector = ?, unit_id = ?, role = ?, phone = ?, avatar_url = ?, gender = ?, position = ?, access_number = ? WHERE id = ? AND company_id = ?");
    $stmt->execute([$_POST['name'], $email, $_POST['sector'], $_POST['unit_id'], $_POST['role'], $_POST['phone'], $avatar_url, $gender, $position, $access_number, $_POST['user_id'], $compId]);
    
    // Sincronizar contas extras
    $user_id = $_POST['user_id'];
    $pdo->prepare("DELETE FROM user_accounts WHERE user_id = ?")->execute([$user_id]);
    $extra_emails = $_POST['extra_emails'] ?? [];
    $extra_passwords = $_POST['extra_passwords'] ?? [];
    foreach ($extra_emails as $i => $e_email) {
        if (!empty($e_email)) {
            $e_pass = $extra_passwords[$i] ?? '';
            $pdo->prepare("INSERT INTO user_accounts (user_id, email, password) VALUES (?, ?, ?)")->execute([$user_id, $e_email, $e_pass]);
        }
    }

    // Sync automático com RH
    try {
        $rhCheck = $pdo->prepare("SELECT COUNT(*) FROM rh_employee_details WHERE user_id = ? AND company_id = ?");
        $rhCheck->execute([$_POST['user_id'], $compId]);
        if ($rhCheck->fetchColumn() > 0) {
            $pdo->prepare("UPDATE rh_employee_details SET gender = ?, role_name = ? WHERE user_id = ? AND company_id = ?")->execute([$gender, $position, $_POST['user_id'], $compId]);
        } else {
            $pdo->prepare("INSERT INTO rh_employee_details (user_id, gender, role_name, company_id) VALUES (?, ?, ?, ?)")->execute([$_POST['user_id'], $gender, $position, $compId]);
        }
    } catch(Exception $e) {}

    $pdo->prepare("DELETE FROM user_menus WHERE user_id = ?")->execute([$_POST['user_id']]);
    foreach ($_POST['menus'] ?? [] as $menu) {
        $pdo->prepare("INSERT IGNORE INTO user_menus (user_id, menu) VALUES (?, ?)")->execute([$_POST['user_id'], $menu]);
    }

    header('Location: ?page=usuarios&success=2');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $compId = getCurrentUserCompanyId();
    $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ?, login_name = ? WHERE id = ? AND company_id = ?");
    $stmt->execute([$new_password, $_POST['new_login'], $_POST['user_id'], $compId]);
    header('Location: ?page=usuarios&success=3');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_user') {
    $compId = getCurrentUserCompanyId();
    $email = !empty($_POST['email']) ? $_POST['email'] : null;
    $login_name = $_POST['login_name'];
    
    // Verificações globais (login e email devem ser únicos no sistema ou pelo menos por empresa?)
    // Aqui vamos manter verificação por empresa para permitir re-uso se for o caso, ou global para segurança.
    // O ideal no SaaS é login único global.
    $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND email != ''");
    $check->execute([$email]);
    if (!empty($email) && $check->fetchColumn() > 0) { header('Location: ?page=usuarios&error=email_exists'); exit; }
    
    $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE login_name = ?");
    $check->execute([$login_name]);
    if ($check->fetchColumn() > 0) { header('Location: ?page=usuarios&error=login_exists'); exit; }
    
    $new_id = 'U' . time() . rand(100,999);
    $avatar_url = null;
    if (!empty($_FILES['avatar']['name'])) {
        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . $new_id . '.' . $ext;
        $dest = __DIR__ . '/../uploads/' . $filename;
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
            $avatar_url = 'uploads/' . $filename;
        }
    }
    
    $name = $_POST['name'];
    $sector = $_POST['sector'];
    $unit_id = $_POST['unit_id'];
    $role = $_POST['role'];
    $phone = $_POST['phone'];
    $gender = $_POST['gender'] ?? '';
    $position = $_POST['position'] ?? '';
    $access_number = $_POST['access_number'] ?? '';
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO users (id, name, email, sector, unit_id, role, phone, login_name, password, avatar_url, gender, position, status, access_number, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Ativo', ?, ?)");
    $stmt->execute([$new_id, $name, $email, $sector, $unit_id, $role, $phone, $login_name, $password, $avatar_url, $gender, $position, $access_number, $compId]);

    // Salvar contas extras
    $extra_emails = $_POST['extra_emails'] ?? [];
    $extra_passwords = $_POST['extra_passwords'] ?? [];
    foreach ($extra_emails as $i => $e_email) {
        if (!empty($e_email)) {
            $e_pass = $extra_passwords[$i] ?? '';
            $pdo->prepare("INSERT INTO user_accounts (user_id, email, password) VALUES (?, ?, ?)")->execute([$new_id, $e_email, $e_pass]);
        }
    }

    // Sync automático com RH
    try {
        $pdo->prepare("INSERT INTO rh_employee_details (user_id, gender, role_name, company_id) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE gender = VALUES(gender), role_name = VALUES(role_name), company_id = VALUES(company_id)")->execute([$new_id, $gender, $position, $compId]);
    } catch(Exception $e) {}

    $pdo->prepare("DELETE FROM user_menus WHERE user_id = ?")->execute([$new_id]);
    foreach ($_POST['menus'] ?? [] as $menu) {
        $pdo->prepare("INSERT IGNORE INTO user_menus (user_id, menu) VALUES (?, ?)")->execute([$new_id, $menu]);
    }

    header('Location: ?page=usuarios&success=1');
    exit;
}

?>

<div class="page-header">
    <div class="page-header-info">
        <div class="page-header-icon">
            <i class="fa-solid fa-users-gear"></i>
        </div>
        <div class="page-header-text">
            <h2>Administração de Acessos</h2>
            <p>Controle de perfis, permissões e segurança operacional.</p>
        </div>
    </div>
    <div class="page-header-actions">
        <?php 
        // Lógica resiliente para exibir o botão de inclusão
        $userMenus = getUserMenus($pdo, $user['id']);
        $hasUserPermission = is_array($userMenus) && in_array('usuarios', $userMenus);
        
        if ($user['role'] === 'Administrador' || $user['login_name'] === 'superadmin' || ($compId > 1 && $hasUserPermission)): 
        ?>
        <button class="btn-primary" onclick="document.getElementById('formModal').style.display='flex'">
            <i class="fa-solid fa-plus"></i>
            Incluir Usuário
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<div style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(16, 185, 129, 0.05) 100%); border: 1px solid rgba(16, 185, 129, 0.3); color: #059669; padding: 1rem; border-radius: 1rem; margin-bottom: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem;">
    <i class="fa-solid fa-circle-check"></i>
    <?= $_GET['success'] == '1' ? 'Usuário cadastrado com sucesso!' : ($_GET['success'] == '2' ? 'Usuário atualizado com sucesso!' : ($_GET['success'] == '3' ? 'Senha resetada com sucesso!' : 'Usuário excluído com sucesso!')) ?>
</div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'email_exists'): ?>
<div style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(239, 68, 68, 0.05) 100%); border: 1px solid rgba(239, 68, 68, 0.3); color: #DC2626; padding: 1rem; border-radius: 1rem; margin-bottom: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem;">
    <i class="fa-solid fa-circle-exclamation"></i>
    Este e-mail já está cadastrado no sistema!
</div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'login_exists'): ?>
<div style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(239, 68, 68, 0.05) 100%); border: 1px solid rgba(239, 68, 68, 0.3); color: #DC2626; padding: 1rem; border-radius: 1rem; margin-bottom: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem;">
    <i class="fa-solid fa-circle-exclamation"></i>
    Este login já está em uso! Escolha outro nome de usuário.
</div>
<?php endif; ?>

<div class="glass-panel" style="padding: 1.5rem; margin-bottom: 1.5rem;">
    <form method="GET" style="display: flex; gap: 1rem; align-items: end;">
        <input type="hidden" name="page" value="usuarios">
        
        <div style="flex: 1;">
            <label class="form-label">Buscar</label>
            <input type="text" name="search" class="form-input" placeholder="Nome, e-mail ou setor..." value="<?= htmlspecialchars($search) ?>">
        </div>
        
        <div style="width: 250px;">
            <label class="form-label">Unidade</label>
            <select name="unit" class="form-select">
                <option value="">Todas as Unidades</option>
                <?php foreach ($units as $unit): ?>
                    <option value="<?= $unit['id'] ?>" <?= $unit_filter == $unit['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($unit['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <button type="submit" class="btn-primary">
            <i class="fa-solid fa-magnifying-glass"></i>
            Filtrar
        </button>
    </form>
</div>


<?php foreach ($sectors_list as $sector => $users_in_sector): ?>
<div style="margin-bottom: 3rem;">
    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
        <div style="height: 2px; flex: 1; background: linear-gradient(90deg, transparent, var(--crm-purple), transparent);"></div>
        <h3 style="font-size: 0.75rem; font-weight: 900; color: var(--crm-purple); text-transform: uppercase; letter-spacing: 0.2em; padding: 0 1rem;">
            <?= htmlspecialchars($sector ?? '') ?>
        </h3>
        <div style="height: 2px; flex: 1; background: linear-gradient(90deg, transparent, var(--crm-purple), transparent);"></div>
    </div>
    
    <?php foreach ($users_in_sector as $usr): ?>
    <div class="glass-panel" style="margin-bottom: 1rem; border-left: 4px solid var(--crm-purple);">
        <div style="display: flex; align-items: center; gap: 1.5rem;" class="user-card-main">
            <div style="width: 64px; height: 64px; border-radius: 1rem; overflow: hidden; border: 2px solid rgba(91, 33, 182, 0.2); flex-shrink: 0;" class="avatar-placeholder">
                <?php if (!empty($usr['avatar_url'])): ?>
                    <img src="<?= htmlspecialchars($usr['avatar_url']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <div style="width: 100%; height: 100%; background: linear-gradient(135deg, rgba(91, 33, 182, 0.15), rgba(251, 191, 36, 0.1)); display: flex; align-items: center; justify-content: center; color: var(--crm-purple); font-weight: 900; font-size: 1.25rem;">
                        <?= strtoupper(substr($usr['name'], 0, 2)) ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="user-card-content">
                <div>
                    <h4 style="font-weight: 900; color: var(--text-main); font-size: 1.125rem;">
                        <?= htmlspecialchars($usr['name'] ?? '') ?>
                    </h4>
                    <p style="font-size: 0.75rem; color: var(--text-soft); font-weight: 600;">
                        <?= htmlspecialchars($usr['email'] ?? '') ?>
                    </p>
                    <?php if (($isSuperAdmin ?? false) && !empty($usr['tenant_name'])): ?>
                        <div style="font-size: 0.65rem; background: rgba(59, 130, 246, 0.1); color: #3B82F6; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 4px; font-weight: 800;">
                            <i class="fa-solid fa-building"></i> <?= htmlspecialchars($usr['tenant_name']) ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div>
                    <p style="font-size: 0.625rem; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.25rem;">
                        Acesso
                    </p>
                    <p style="font-size: 0.875rem; font-weight: 700; color: var(--crm-purple);">
                        <?= htmlspecialchars(($usr['access_number'] ?? '') ?: '—') ?>
                    </p>
                </div>
                
                <div>
                    <p style="font-size: 0.625rem; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.25rem;">
                        Unidade
                    </p>
                    <p style="font-size: 0.875rem; font-weight: 700; color: var(--crm-purple);">
                        <?= htmlspecialchars($usr['unit_name'] ?? '—') ?>
                    </p>
                </div>
                
                <div>
                    <p style="font-size: 0.625rem; font-weight: 900; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.25rem;">
                        Setor
                    </p>
                    <p style="font-size: 0.875rem; font-weight: 700; color: var(--text-main);">
                        <?= htmlspecialchars($usr['sector'] ?? '—') ?>
                    </p>
                </div>
                
                <div>
                    <span class="badge badge-info" style="white-space: nowrap;">
                        <?= htmlspecialchars($usr['role'] ?? '') ?>
                    </span>
                </div>
                
                <div style="display: flex; gap: 0.35rem; justify-content: flex-end; align-items: center;" class="user-actions-container">
                    <!-- Histórico de Empréstimos -->
                    <button class="btn-icon" onclick="event.stopPropagation(); showUserLoanHistory('<?= $usr['id'] ?>', '<?= htmlspecialchars($usr['name'], ENT_QUOTES) ?>')" title="Histórico de Empréstimos" style="width:32px;height:32px;min-width:32px;color:#5B21B6;border:1px solid rgba(91,33,182,0.3);background:rgba(91,33,182,0.05);display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </button>
                    <!-- Histórico de Chamados -->
                    <button class="btn-icon" onclick="event.stopPropagation(); showUserTicketHistory('<?= $usr['id'] ?>', '<?= htmlspecialchars($usr['name'], ENT_QUOTES) ?>')" title="Histórico de Chamados" style="width:32px;height:32px;min-width:32px;color:#3B82F6;border:1px solid rgba(59,130,246,0.3);background:rgba(59,130,246,0.05);display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-headset"></i>
                    </button>
                    <?php if ($user['role'] == 'Administrador' || $user['role'] == 'Suporte Técnico'): ?>
                    <button class="btn-icon" onclick="event.stopPropagation(); editUser('<?= $usr['id'] ?>')" title="Editar Usuário" style="width:32px;height:32px;min-width:32px;border:1px solid #e2e8f0;display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <button class="btn-icon" onclick="event.stopPropagation(); resetPassword('<?= $usr['id'] ?>')" title="Resetar Senha" style="width:32px;height:32px;min-width:32px;border:1px solid #e2e8f0;display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-key"></i>
                    </button>
                    <?php if ($user['role'] == 'Administrador' && $usr['id'] !== $user['id']): ?>
                    <button class="btn-icon" onclick="event.stopPropagation(); deleteUser('<?= $usr['id'] ?>', '<?= htmlspecialchars($usr['name'] ?? '', ENT_QUOTES) ?>')" title="Excluir Usuário" style="width:32px;height:32px;min-width:32px;color:#fff;background:#ef4444;border:none;display:flex;align-items:center;justify-content:center;">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<!-- Modal Form -->
<div id="formModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 800px; width: 100%; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--text-main);">Cadastrar Novo Usuário</h3>
            <button onclick="document.getElementById('formModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: var(--text-soft); font-size: 1.5rem;">&times;</button>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_user">
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Nome Completo *</label>
                    <input type="text" name="name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">E-mail Principal</label>
                    <input type="email" name="email" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">Número de Acesso</label>
                    <input type="text" name="access_number" class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Unidade *</label>
                    <select name="unit_id" id="add_unit_id" class="form-select" required onchange="filterSectors('add')">
                        <option value="">Selecione a unidade</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Setor *</label>
                    <select name="sector" id="add_sector" class="form-select" required>
                        <option value="">Selecione a unidade primeiro</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Perfil de Acesso *</label>
                    <select name="role" class="form-select" required>
                        <option value="Administrador">Administrador</option>
                        <option value="Responsável de Setor">Responsável de Setor</option>
                        <option value="Colaborador" selected>Colaborador</option>
                        <option value="Setor de Compras">Setor de Compras</option>
                        <option value="Suporte Técnico">Suporte Técnico</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Telefone</label>
                    <input type="text" name="phone" class="form-input" placeholder="(00) 00000-0000">
                </div>

                <div class="form-group">
                    <label class="form-label">Sexo</label>
                    <select name="gender" class="form-select">
                        <option value="">-- Selecione --</option>
                        <option value="Masculino">Masculino</option>
                        <option value="Feminino">Feminino</option>
                        <option value="Outro">Outro / Preferiu não dizer</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Cargo</label>
                    <select name="position" class="form-select">
                        <option value="">-- Selecione --</option>
                        <?php foreach ($all_positions as $pos): ?>
                            <option value="<?= htmlspecialchars($pos['name']) ?>"><?= htmlspecialchars($pos['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Login *</label>
                    <input type="text" name="login_name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Senha *</label>
                    <input type="password" name="password" class="form-input" required>
                </div>

                <div class="form-group" style="grid-column: span 2;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <label class="form-label" style="margin-bottom:0;">Contas de E-mail Extras</label>
                        <button type="button" onclick="addAccountRow('add_accounts_container')" class="btn-primary" style="padding: 0.25rem 0.75rem; font-size: 0.75rem;">
                            <i class="fa-solid fa-plus"></i> Adicionar
                        </button>
                    </div>
                    <div id="add_accounts_container">
                        <!-- Linhas dinâmicas entrarão aqui -->
                    </div>
                </div>

                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Foto do Usuário</label>
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div id="add_avatar_preview" style="width: 64px; height: 64px; border-radius: 1rem; background: linear-gradient(135deg, rgba(91,33,182,0.15), rgba(251,191,36,0.1)); border: 2px dashed rgba(91,33,182,0.3); display: flex; align-items: center; justify-content: center; color: var(--crm-purple); font-size: 1.5rem; overflow: hidden; flex-shrink: 0;">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <input type="file" name="avatar" accept="image/*" class="form-input" onchange="previewAvatar(this, 'add_avatar_preview')" style="flex: 1;">
                    </div>
                </div>

                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Menus Permitidos *</label>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; background: var(--bg-main); border: 1px solid var(--border-color);">
                        <?php foreach ($all_menus as $key => $label): ?>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.875rem; font-weight: 600; color: var(--text-main);">
                            <input type="checkbox" name="menus[]" value="<?= $key ?>" style="accent-color: var(--crm-purple); width: 16px; height: 16px;">
                            <?= $label ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <button type="button" onclick="document.getElementById('formModal').style.display='none'" class="btn-secondary">
                    Cancelar
                </button>
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Salvar Usuário
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar -->
<div id="editModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 800px; width: 100%; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--text-main);">Editar Usuário</h3>
            <button onclick="document.getElementById('editModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: var(--text-soft); font-size: 1.5rem;">&times;</button>
        </div>
        
        <form method="POST" id="editForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit_user">
            <input type="hidden" name="user_id" id="edit_user_id">
            <input type="hidden" name="current_avatar" id="edit_current_avatar">
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Nome Completo *</label>
                    <input type="text" name="name" id="edit_name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">E-mail Principal</label>
                    <input type="email" name="email" id="edit_email" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">Número de Acesso</label>
                    <input type="text" name="access_number" id="edit_access_number" class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Unidade *</label>
                    <select name="unit_id" id="edit_unit" class="form-select" required onchange="filterSectors('edit')">
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Setor *</label>
                    <select name="sector" id="edit_sector" class="form-select" required>
                        <option value="">Selecione a unidade primeiro</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Perfil de Acesso *</label>
                    <select name="role" id="edit_role" class="form-select" required>
                        <option value="Administrador">Administrador</option>
                        <option value="Responsável de Setor">Responsável de Setor</option>
                        <option value="Colaborador">Colaborador</option>
                        <option value="Setor de Compras">Setor de Compras</option>
                        <option value="Suporte Técnico">Suporte Técnico</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Telefone</label>
                    <input type="text" name="phone" id="edit_phone" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">Sexo</label>
                    <select name="gender" id="edit_gender" class="form-select">
                        <option value="">-- Selecione --</option>
                        <option value="Masculino">Masculino</option>
                        <option value="Feminino">Feminino</option>
                        <option value="Outro">Outro / Preferiu não dizer</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Cargo</label>
                    <select name="position" id="edit_position" class="form-select">
                        <option value="">-- Selecione --</option>
                        <?php foreach ($all_positions as $pos): ?>
                            <option value="<?= htmlspecialchars($pos['name']) ?>"><?= htmlspecialchars($pos['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="grid-column: span 2;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <label class="form-label" style="margin-bottom:0;">Contas de E-mail Extras</label>
                        <button type="button" onclick="addAccountRow('edit_accounts_container')" class="btn-primary" style="padding: 0.25rem 0.75rem; font-size: 0.75rem;">
                            <i class="fa-solid fa-plus"></i> Adicionar
                        </button>
                    </div>
                    <div id="edit_accounts_container">
                        <!-- Linhas dinâmicas entrarão aqui -->
                    </div>
                </div>

                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Foto do Usuário</label>
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div id="edit_avatar_preview" style="width: 64px; height: 64px; border-radius: 1rem; background: linear-gradient(135deg, rgba(91,33,182,0.15), rgba(251,191,36,0.1)); border: 2px dashed rgba(91,33,182,0.3); display: flex; align-items: center; justify-content: center; color: var(--crm-purple); font-size: 1.5rem; overflow: hidden; flex-shrink: 0;">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <input type="file" name="avatar" accept="image/*" class="form-input" onchange="previewAvatar(this, 'edit_avatar_preview')" style="flex: 1;">
                    </div>
                </div>

                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Menus Permitidos *</label>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; background: var(--bg-main); border: 1px solid var(--border-color);">
                        <?php foreach ($all_menus as $key => $label): ?>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.875rem; font-weight: 600; color: var(--text-main);">
                            <input type="checkbox" name="menus[]" value="<?= $key ?>" id="edit_menu_<?= $key ?>" style="accent-color: var(--crm-purple); width: 16px; height: 16px;">
                            <?= $label ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <button type="button" onclick="document.getElementById('editModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Excluir -->
<div id="deleteModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 420px; width: 100%;">
        <div style="text-align: center; padding: 1rem 0 2rem;">
            <div style="width: 64px; height: 64px; background: rgba(239,68,68,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                <i class="fa-solid fa-trash" style="color: #ef4444; font-size: 1.5rem;"></i>
            </div>
            <h3 style="font-size: 1.125rem; font-weight: 900; color: var(--text-main); margin-bottom: 0.5rem;">Excluir Usuário</h3>
            <p style="color: var(--text-soft); font-size: 0.875rem;">Tem certeza que deseja excluir <strong id="delete_user_name"></strong>? Esta ação não pode ser desfeita.</p>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="user_id" id="delete_user_id">
            <div style="display: flex; gap: 1rem; justify-content: center;">
                <button type="button" onclick="document.getElementById('deleteModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" style="background: #ef4444; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 0.75rem; font-weight: 700; cursor: pointer;">
                    <i class="fa-solid fa-trash"></i> Excluir
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Resetar Senha -->
<div id="passwordModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 500px; width: 100%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--text-main);">Resetar Login e Senha</h3>
            <button onclick="document.getElementById('passwordModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: var(--text-soft); font-size: 1.5rem;">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="password_user_id">
            
            <div class="form-group">
                <label class="form-label">Novo Login *</label>
                <input type="text" name="new_login" id="password_new_login" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Nova Senha *</label>
                <input type="password" name="new_password" class="form-input" required minlength="3">
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <button type="button" onclick="document.getElementById('passwordModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-key"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Histórico de Empréstimos do Usuário -->
<div id="userLoanHistoryModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); z-index: 2000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 700px; width: 100%; max-height: 88vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.15rem; font-weight: 900; color: var(--text-main); display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-clock-rotate-left" style="color: var(--brand-primary);"></i>
                <span id="userLoanHistoryTitle">Histórico de Empréstimos</span>
            </h3>
            <button onclick="document.getElementById('userLoanHistoryModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: var(--text-soft); font-size: 1.5rem;">&times;</button>
        </div>
        <div id="userLoanHistoryContent"></div>
    </div>
</div>

<!-- Modal: Histórico de Chamados do Usuário -->
<div id="userTicketHistoryModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); z-index: 2000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 700px; width: 100%; max-height: 88vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.15rem; font-weight: 900; color: var(--text-main); display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-headset" style="color: #3B82F6;"></i>
                <span id="userTicketHistoryTitle">Histórico de Chamados</span>
            </h3>
            <button onclick="document.getElementById('userTicketHistoryModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: var(--text-soft); font-size: 1.5rem;">&times;</button>
        </div>
        <div id="userTicketHistoryContent"></div>
    </div>
</div>

