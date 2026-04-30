<?php
if ($user['role'] !== 'Administrador' && $user['role'] !== 'Suporte Técnico') {
    header('Location: ?page=dashboard');
    exit;
}

// Migrações SaaS
try { $pdo->exec("ALTER TABLE company_settings ADD COLUMN company_id INT NOT NULL DEFAULT 1"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE units ADD COLUMN company_id INT NOT NULL DEFAULT 1"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE sectors ADD COLUMN company_id INT NOT NULL DEFAULT 1"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE rh_positions ADD COLUMN company_id INT NOT NULL DEFAULT 1"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE login_logs ADD COLUMN company_id INT NOT NULL DEFAULT 1"); } catch(Exception $e) {}

// Auto-migrate: Tabela de Cargos
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS rh_positions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        company_id INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    // Migrar cargos já existentes no RH para a tabela de cargos (com filtro de empresa)
    $compId = getCurrentUserCompanyId();
    $pdo->exec("INSERT IGNORE INTO rh_positions (name, company_id) SELECT DISTINCT role_name, company_id FROM rh_employee_details WHERE role_name IS NOT NULL AND role_name != ''");
} catch(Exception $e) {}

// Auto-migrate: Colunas de mensagens de aniversário
try { $pdo->exec("ALTER TABLE company_settings ADD COLUMN birthday_message_all TEXT"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE company_settings ADD COLUMN birthday_message_self TEXT"); } catch(Exception $e) {}

// Tabela de controle de envio de aniversário
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS birthday_sent_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        sent_year YEAR NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_user_year (user_id, sent_year)
    )");
} catch(Exception $e) {}

// Salvar configurações da empresa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_company') {
    $logo_url = $_POST['current_logo'] ?? null;
    if (!empty($_FILES['logo']['name'])) {
        $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $filename = 'company_logo.' . $ext;
        $dest = __DIR__ . '/../uploads/' . $filename;
        if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
            $logo_url = 'uploads/' . $filename;
        }
    }

    $sig_url = $_POST['current_signature'] ?? null;
    if (!empty($_FILES['signature']['name'])) {
        $ext = pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION);
        $filename = 'certificate_signature.' . $ext;
        $dest = __DIR__ . '/../uploads/' . $filename;
        if (move_uploaded_file($_FILES['signature']['tmp_name'], $dest)) {
            $sig_url = 'uploads/' . $filename;
        }
    }

    $announcement_image_url = $_POST['current_announcement_image'] ?? null;
    if (!empty($_FILES['announcement_image']['name'])) {
        $ext = pathinfo($_FILES['announcement_image']['name'], PATHINFO_EXTENSION);
        $filename = 'announcement_image_' . time() . '.' . $ext;
        $dest = __DIR__ . '/../uploads/announcements/' . $filename;
        if (!is_dir(__DIR__ . '/../uploads/announcements/')) mkdir(__DIR__ . '/../uploads/announcements/', 0777, true);
        if (move_uploaded_file($_FILES['announcement_image']['tmp_name'], $dest)) {
            $announcement_image_url = 'uploads/announcements/' . $filename;
        }
    }

    $compId = getCurrentUserCompanyId();
    $pdo->prepare("UPDATE company_settings SET company_name = ?, logo_url = ?, certificate_signature_url = ?, certificate_global_text = ?, login_announcement = ?, announcement_image_url = ? WHERE id = ?")
        ->execute([$_POST['company_name'], $logo_url, $sig_url, $_POST['certificate_global_text'], $_POST['login_announcement'], $announcement_image_url, $compId]);
    header('Location: ?page=configuracoes&success=company');
    exit;
}

// Atualizar Senha Tecnologia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_tech_password') {
    $compId = getCurrentUserCompanyId();
    $pdo->prepare("UPDATE company_settings SET tech_password = ? WHERE id = ?")->execute([$_POST['tech_password'], $compId]);
    header('Location: ?page=configuracoes&success=tech_pass');
    exit;
}

// Salvar mensagens de aniversário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_birthday_messages') {
    $compId = getCurrentUserCompanyId();
    $pdo->prepare("UPDATE company_settings SET birthday_message_all = ?, birthday_message_self = ? WHERE id = ?")
        ->execute([$_POST['birthday_message_all'] ?? '', $_POST['birthday_message_self'] ?? '', $compId]);
    header('Location: ?page=configuracoes&success=birthday&tab=aniversarios');
    exit;
}

// Adicionar Setor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_sector') {
    $compId = getCurrentUserCompanyId();
    $stmt = $pdo->prepare("INSERT INTO sectors (id, name, unit_id, company_id) VALUES (?, ?, ?, ?)");
    $stmt->execute(['S' . time(), $_POST['sector_name'], $_POST['unit_id'], $compId]);
    header('Location: ?page=configuracoes&success=sector');
    exit;
}

// Adicionar Unidade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_unit') {
    $compId = getCurrentUserCompanyId();
    $stmt = $pdo->prepare("INSERT INTO units (id, name, address, cnpj, responsible_name, contact, company_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['U' . time(), $_POST['name'], $_POST['address'], $_POST['cnpj'], $_POST['responsible_name'], $_POST['contact'], $compId]);
    header('Location: ?page=configuracoes&success=unit');
    exit;
}

// Editar Unidade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_unit') {
    $compId = getCurrentUserCompanyId();
    $stmt = $pdo->prepare("UPDATE units SET name = ?, address = ?, cnpj = ?, responsible_name = ?, contact = ? WHERE id = ? AND company_id = ?");
    $stmt->execute([$_POST['name'], $_POST['address'], $_POST['cnpj'], $_POST['responsible_name'], $_POST['contact'], $_POST['unit_id'], $compId]);
    header('Location: ?page=configuracoes&success=unit_edit');
    exit;
}

// Excluir Unidade
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_unit') {
    $compId = getCurrentUserCompanyId();
    if ($user['role'] === 'Administrador') {
        $pdo->prepare("DELETE FROM units WHERE id = ? AND company_id = ?")->execute([$_POST['unit_id'], $compId]);
    }
    header('Location: ?page=configuracoes&success=unit_del');
    exit;
}

// Adicionar Cargo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_position') {
    $compId = getCurrentUserCompanyId();
    $posName = trim($_POST['position_name'] ?? '');
    if ($posName) {
        try {
            $stmt = $pdo->prepare("INSERT INTO rh_positions (name, company_id) VALUES (?, ?)");
            $stmt->execute([$posName, $compId]);
        } catch(Exception $e) {}
    }
    header('Location: ?page=configuracoes&success=position');
    exit;
}

// Excluir Cargo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_position') {
    $compId = getCurrentUserCompanyId();
    if ($user['role'] === 'Administrador') {
        $pdo->prepare("DELETE FROM rh_positions WHERE id = ? AND company_id = ?")->execute([$_POST['position_id'], $compId]);
    }
    header('Location: ?page=configuracoes&success=position_del');
    exit;
}

$units = $pdo->prepare("SELECT * FROM units WHERE company_id = ? ORDER BY name");
$units->execute([$compId]);
$units = $units->fetchAll();

$sectors = $pdo->prepare("SELECT s.*, u.name as unit_name FROM sectors s LEFT JOIN units u ON BINARY s.unit_id = BINARY u.id WHERE s.company_id = ? ORDER BY s.name");
$sectors->execute([$compId]);
$sectors = $sectors->fetchAll();

$positions = $pdo->prepare("SELECT * FROM rh_positions WHERE company_id = ? ORDER BY name ASC");
$positions->execute([$compId]);
$positions = $positions->fetchAll();

$company = $pdo->prepare("SELECT * FROM company_settings WHERE id = ?");
$company->execute([$compId]);
$company = $company->fetch();

// Buscar Logs de Acesso (Filtrado por empresa)
$logs = $pdo->prepare("SELECT * FROM login_logs WHERE company_id = ? ORDER BY login_at DESC LIMIT 100");
$logs->execute([$compId]);
$logs = $logs->fetchAll();
?>

<style>
    .tab-btn { background: none; border: none; font-weight: 700; color: #64748b; cursor: pointer; padding: 0.5rem 1rem; border-radius: 0.5rem; transition: all 0.3s; }
    .tab-btn.active { color: var(--crm-purple); background: rgba(91, 33, 182, 0.1); }
    .tab-content { display: none; }
    .tab-content.active { display: block; animation: fadeIn 0.3s ease-out; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    .log-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
    .log-table th { text-align: left; padding: 1rem; background: #f8fafc; color: #64748b; font-size: 0.75rem; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
    .log-table td { padding: 1rem; border-bottom: 1px solid #e2e8f0; font-size: 0.875rem; color: #334155; }
</style>

<div class="page-header" style="margin-bottom: 2rem;">
    <div class="page-header-info">
        <div class="page-header-icon">
            <i class="fa-solid fa-sliders"></i>
        </div>
        <div class="page-header-text">
            <h2>Definições do Sistema</h2>
            <p>Customização de regras de negócio e parâmetros globais.</p>
        </div>
    </div>
</div>

<!-- Sistema de Abas -->
<div style="display: flex; gap: 1.5rem; border-bottom: 2px solid #e2e8f0; margin-bottom: 2rem; padding-bottom: 0.5rem;">
    <button onclick="switchTab('geral')" id="tab-geral" class="tab-btn active">Geral</button>
    <button onclick="switchTab('certificados')" id="tab-certificados" class="tab-btn">Certificados</button>
    <button onclick="switchTab('aniversarios')" id="tab-aniversarios" class="tab-btn">Aniversários</button>
    <button onclick="switchTab('cargos')" id="tab-cargos" class="tab-btn">Cargos</button>
    <button onclick="switchTab('logs')" id="tab-logs" class="tab-btn">Logs de Acesso</button>
</div>

<!-- ABA GERAL -->
<div id="content-geral" class="tab-content active">

<?php if (isset($_GET['success'])): ?>
<div style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(16, 185, 129, 0.05) 100%); border: 1px solid rgba(16, 185, 129, 0.3); color: #059669; padding: 1rem; border-radius: 1rem; margin-bottom: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem;">
    <i class="fa-solid fa-circle-check"></i>
    <?php
    if ($_GET['success'] === 'company') echo 'Configurações da empresa salvas com sucesso!';
    elseif ($_GET['success'] === 'sector') echo 'Setor cadastrado com sucesso!';
    elseif ($_GET['success'] === 'unit') echo 'Unidade cadastrada com sucesso!';
    elseif ($_GET['success'] === 'unit_edit') echo 'Unidade atualizada com sucesso!';
    elseif ($_GET['success'] === 'unit_del') echo 'Unidade excluída com sucesso!';
    elseif ($_GET['success'] === 'import') echo 'Backup importado com sucesso!';
    elseif ($_GET['success'] === 'position') echo 'Cargo cadastrado com sucesso!';
    elseif ($_GET['success'] === 'position_del') echo 'Cargo excluído com sucesso!';
    elseif ($_GET['success'] === 'tech_pass') echo 'Senha do módulo Tecnologia atualizada com sucesso!';
    elseif ($_GET['success'] === 'birthday') echo 'Mensagens de aniversário salvas com sucesso!';
    ?>
</div>
<?php endif; ?>
<?php if (isset($_GET['tab'])): ?>
<script>document.addEventListener('DOMContentLoaded', () => switchTab('<?= htmlspecialchars($_GET['tab']) ?>'));</script>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(239, 68, 68, 0.05) 100%); border: 1px solid rgba(239, 68, 68, 0.3); color: #DC2626; padding: 1rem; border-radius: 1rem; margin-bottom: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem;">
    <i class="fa-solid fa-circle-exclamation"></i>
    <?php
    if ($_GET['error'] === 'upload') echo 'Erro ao fazer upload do arquivo!';
    elseif ($_GET['error'] === 'import') echo 'Erro ao importar backup!';
    ?>
</div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 2rem; margin-bottom: 2rem;">
    <!-- Identidade da Empresa -->
    <div class="glass-panel" style="grid-column: span 2;">
        <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--crm-black); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-building" style="color: var(--crm-purple);"></i>
            Identidade da Empresa
        </h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_company">
            <input type="hidden" name="current_logo" value="<?= htmlspecialchars($company['logo_url'] ?? '') ?>">
            <input type="hidden" name="current_signature" value="<?= htmlspecialchars($company['certificate_signature_url'] ?? '') ?>">
            <input type="hidden" name="certificate_global_text" value="<?= htmlspecialchars($company['certificate_global_text'] ?? '') ?>">
            <input type="hidden" name="current_announcement_image" value="<?= htmlspecialchars($company['announcement_image_url'] ?? '') ?>">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div class="form-group">
                    <label class="form-label">Nome da Empresa</label>
                    <input type="text" name="company_name" class="form-input" value="<?= htmlspecialchars($company['company_name'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Logo Principal</label>
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div id="logo_preview" style="width: 48px; height: 48px; border-radius: 8px; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #fff;">
                            <?php if (!empty($company['logo_url'])): ?>
                                <img src="<?= htmlspecialchars($company['logo_url']) ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                            <?php else: ?>
                                <i class="fa-solid fa-image" style="color: #cbd5e1;"></i>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="logo" accept="image/*" class="form-input" onchange="previewImage(this, 'logo_preview')">
                    </div>
                </div>
            </div>

            <div style="grid-column: span 2; display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; background: #f8fafc; padding: 1.5rem; border-radius: 1rem; border: 1px solid #e2e8f0; margin-top: 1rem;">
                <!-- Announcement Image Preview -->
                <div style="text-align: center;">
                    <label class="form-label" style="margin-bottom: 0.5rem; display: block;">Imagem do Comunicado</label>
                    <div id="announcement_preview" style="width: 100%; height: 160px; border: 2px dashed rgba(16,185,129,0.3); border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #fff; margin-bottom: 0.75rem;">
                        <?php if (!empty($company['announcement_image_url'])): ?>
                            <img src="<?= htmlspecialchars($company['announcement_image_url']) ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                        <?php else: ?>
                            <i class="fa-solid fa-bullhorn" style="font-size: 2rem; color: #cbd5e1;"></i>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="announcement_image" accept="image/*" style="font-size: 0.75rem;" onchange="previewImage(this, 'announcement_preview')">
                </div>

                <div>
                    <div class="form-group">
                        <label class="form-label">Aviso de Login Global (Comunicado)</label>
                        <textarea name="login_announcement" class="form-input" style="height: 120px; resize: none;" placeholder="Mensagem que aparecerá para todos ao logar..."><?= htmlspecialchars($company['login_announcement'] ?? '') ?></textarea>
                        <p style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">Deixe vazio se não quiser exibir nenhum comunicado.</p>
                    </div>
                </div>
            </div>

            <div style="grid-column: span 2; margin-top: 1rem;">
                <button type="submit" class="btn-primary" style="width: fit-content;">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar Identidade e Comunicado
                </button>
            </div>
        </form>
    </div>

    <!-- Segurança: Tecnologia -->
    <div class="glass-panel" style="grid-column: span 2;">
        <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--crm-black); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-lock" style="color: var(--crm-purple);"></i>
            Segurança: Módulo Tecnologia
        </h3>
        <p style="font-size: 0.875rem; color: #64748b; margin-bottom: 1rem;">
            Configure a senha global exigida para acessar as abas do módulo "Tecnologia".
        </p>
        <form method="POST">
            <input type="hidden" name="action" value="update_tech_password">
            <div style="display: flex; gap: 1rem; align-items: flex-end; max-width: 400px;">
                <div class="form-group" style="flex: 1; margin-bottom: 0;">
                    <label class="form-label">Senha Global de Acesso *</label>
                    <input type="text" name="tech_password" class="form-input" value="<?= htmlspecialchars($company['tech_password'] ?? '1968') ?>" required autocomplete="off">
                </div>
                <button type="submit" class="btn-primary" style="white-space: nowrap;">
                    <i class="fa-solid fa-floppy-disk"></i> Atualizar Senha
                </button>
            </div>
        </form>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 2rem;">
    <!-- Gerenciar Unidades -->
    <div class="glass-panel">
        <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--crm-black); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-building" style="color: var(--crm-purple);"></i>
            Unidades / Matriz / Sede
        </h3>
        
        <form method="POST" style="margin-bottom: 2rem;">
            <input type="hidden" name="action" value="add_unit">
            <div style="display: grid; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Nome da Unidade *</label>
                    <input type="text" name="name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">CNPJ</label>
                    <input type="text" name="cnpj" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Endereço</label>
                    <input type="text" name="address" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Responsável</label>
                    <input type="text" name="responsible_name" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Contato</label>
                    <input type="text" name="contact" class="form-input">
                </div>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-plus"></i> Adicionar Unidade</button>
            </div>
        </form>
        
        <div style="max-height: 400px; overflow-y: auto;">
            <?php foreach ($units as $u): ?>
            <div style="padding: 1rem; background: linear-gradient(135deg, rgba(91, 33, 182, 0.05) 0%, rgba(251, 191, 36, 0.02) 100%); border-radius: 1rem; margin-bottom: 0.75rem; border: 1px solid rgba(91, 33, 182, 0.1); display: flex; justify-content: space-between; align-items: start;">
                <div style="flex: 1;">
                    <div style="font-weight: 900; color: var(--crm-purple); margin-bottom: 0.5rem;"><?= htmlspecialchars($u['name']) ?></div>
                    <div style="font-size: 0.75rem; color: #64748b;">
                        <?= htmlspecialchars($u['address'] ?: 'Sem endereço') ?><br>
                        CNPJ: <?= htmlspecialchars($u['cnpj'] ?: 'N/A') ?><br>
                        Responsável: <?= htmlspecialchars($u['responsible_name'] ?: 'N/A') ?><br>
                        Contato: <?= htmlspecialchars($u['contact'] ?: 'N/A') ?>
                    </div>
                </div>
                <div style="display:flex;gap:6px;flex-shrink:0;">
                    <button onclick="editUnit(<?= htmlspecialchars(json_encode($u)) ?>)" style="background:#5B21B6;color:white;border:none;width:34px;height:34px;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;transition:all .18s;" title="Editar unidade" onmouseover="this.style.background='#4C1D95'" onmouseout="this.style.background='#5B21B6'">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <?php if ($user['role'] === 'Administrador'): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Excluir unidade \'<?= htmlspecialchars(addslashes($u['name'])) ?>\'?\nTodos os setores e dados vinculados podem ser afetados.') && confirm('Confirmar exclusão permanente?')">
                        <input type="hidden" name="action" value="delete_unit">
                        <input type="hidden" name="unit_id" value="<?= $u['id'] ?>">
                        <button type="submit" style="background:#ef4444;color:white;border:none;width:34px;height:34px;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:13px;transition:all .18s;" title="Excluir unidade permanentemente" onmouseover="this.style.background='#dc2626'" onmouseout="this.style.background='#ef4444'">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Gerenciar Setores -->
    <div class="glass-panel">
        <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--crm-black); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-layer-group" style="color: var(--crm-yellow);"></i>
            Setores / Departamentos
        </h3>
        
        <form method="POST" style="margin-bottom: 2rem;">
            <input type="hidden" name="action" value="add_sector">
            <div style="display: grid; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Nome do Setor *</label>
                    <input type="text" name="sector_name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Unidade *</label>
                    <select name="unit_id" class="form-select" required>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-plus"></i> Adicionar Setor</button>
            </div>
        </form>
        
        <div style="max-height: 400px; overflow-y: auto;">
            <?php foreach ($sectors as $s): ?>
            <div style="padding: 1rem; background: linear-gradient(135deg, rgba(251, 191, 36, 0.05) 0%, rgba(91, 33, 182, 0.02) 100%); border-radius: 1rem; margin-bottom: 0.75rem; border: 1px solid rgba(251, 191, 36, 0.1);">
                <div style="font-weight: 900; color: var(--crm-yellow); margin-bottom: 0.25rem;"><?= htmlspecialchars($s['name']) ?></div>
                <div style="font-size: 0.75rem; color: #64748b;">Unidade: <?= htmlspecialchars($s['unit_name']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Backup e Importação -->
<div class="glass-panel" style="margin-top: 2rem;">
    <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--crm-black); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem;">
        <i class="fa-solid fa-cloud-arrow-up" style="color: var(--crm-purple);"></i>
        Centro de Backup e Sincronização
    </h3>
    <p style="color: #64748b; font-size: 0.875rem; margin-bottom: 2rem;">Gerencie a segurança dos seus dados com exportações abrangentes e importações inteligentes.</p>
    
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 2rem;">
        <!-- Backup -->
        <div style="padding: 2rem; background: #fff; border-radius: 1.25rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <div style="width: 48px; height: 48px; background: rgba(91, 33, 182, 0.1); border-radius: 1rem; display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem;">
                <i class="fa-solid fa-file-export" style="font-size: 1.5rem; color: var(--crm-purple);"></i>
            </div>
            <h4 style="font-weight: 900; color: var(--crm-black); margin-bottom: 0.5rem;">Exportação Completa</h4>
            <p style="color: #64748b; font-size: 0.875rem; margin-bottom: 1.5rem; line-height: 1.5;">
                Gera um arquivo consolidado com todos os módulos do sistema: Usuários, RH, Patrimônio, Chamados, Voluntariado, Orçamentos e Configurações.
            </p>
            <div style="background: #f8fafc; padding: 1rem; border-radius: 0.75rem; margin-bottom: 1.5rem;">
                <div style="font-size: 0.7rem; font-weight: 900; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.5rem;">Estrutura do Arquivo</div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                    <span style="font-size: 0.75rem; color: #475569;"><i class="fa-solid fa-circle-check" style="color: #10B981; font-size: 0.6rem;"></i> Multi-abas</span>
                    <span style="font-size: 0.75rem; color: #475569;"><i class="fa-solid fa-circle-check" style="color: #10B981; font-size: 0.6rem;"></i> Metadados</span>
                    <span style="font-size: 0.75rem; color: #475569;"><i class="fa-solid fa-circle-check" style="color: #10B981; font-size: 0.6rem;"></i> Relatórios</span>
                    <span style="font-size: 0.75rem; color: #475569;"><i class="fa-solid fa-circle-check" style="color: #10B981; font-size: 0.6rem;"></i> Formato XLSX</span>
                </div>
            </div>
            <a href="backup.php" class="btn-primary" style="width: 100%; text-align: center; display: block; text-decoration: none; padding: 1rem;">
                <i class="fa-solid fa-download"></i> Gerar Backup Profissional
            </a>
        </div>
        
        <!-- Importação -->
        <div style="padding: 2rem; background: #fff; border-radius: 1.25rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
            <div style="width: 48px; height: 48px; background: rgba(251, 191, 36, 0.1); border-radius: 1rem; display: flex; align-items: center; justify-content: center; margin-bottom: 1.5rem;">
                <i class="fa-solid fa-wand-magic-sparkles" style="font-size: 1.5rem; color: #D97706;"></i>
            </div>
            <h4 style="font-weight: 900; color: var(--crm-black); margin-bottom: 0.5rem;">Importação Inteligente</h4>
            <p style="color: #64748b; font-size: 0.875rem; margin-bottom: 1.5rem; line-height: 1.5;">
                Alimenta o sistema com novos dados e atualiza registros existentes. **Nenhuma informação atual será excluída.**
            </p>
            <div style="background: #ECFDF5; padding: 1rem; border-radius: 0.75rem; margin-bottom: 1.5rem; border: 1px solid #A7F3D0;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.4rem;">
                    <i class="fa-solid fa-shield-check" style="color: #059669;"></i>
                    <span style="font-weight: 900; color: #065F46; font-size: 0.75rem; text-transform: uppercase;">Processamento Seguro</span>
                </div>
                <p style="font-size: 0.75rem; color: #064E3B; margin: 0; line-height: 1.4;">
                    O sistema identifica duplicatas pelo ID e realiza apenas a sincronização dos campos alterados.
                </p>
            </div>
            <form action="import.php" method="POST" enctype="multipart/form-data" onsubmit="return confirm('Deseja iniciar a Sincronização de Dados? Novos registros serão criados e os existentes serão atualizados.')">
                <div class="form-group">
                    <label class="form-label">Arquivo de Backup (.xlsx)</label>
                    <input type="file" name="backup_file" class="form-input" accept=".xlsx" required>
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; background: #059669; border-color: #059669; padding: 1rem;">
                    <i class="fa-solid fa-sync"></i> Iniciar Sincronização Inteligente
                </button>
            </form>
        </div>

        <!-- Backup de Segurança Total -->
        <div id="fullBackupCard" style="grid-column: span 2; padding: 2.5rem; background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: 1.5rem; border: 2px solid #334155; color: white;">
            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 2rem; flex-wrap: wrap;">
                <div style="max-width: 65%;">
                    <h4 style="font-weight: 900; color: #f1f5f9; font-size: 1.5rem; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.75rem;">
                        <i class="fa-solid fa-shield-halved" style="color: #38bdf8;"></i>
                        Cópia de Segurança do Sistema (Total)
                    </h4>
                    <p style="color: #94a3b8; font-size: 0.9rem; line-height: 1.6; margin-bottom: 1rem;">
                        Gera um backup <strong style="color: #f1f5f9;">completo e inteligente</strong> contendo todos os arquivos do sistema, fotos, uploads e o banco de dados completo.
                        Salvo automaticamente em: <code style="background: #334155; padding: 2px 8px; border-radius: 4px; color: #38bdf8; font-size: 0.8rem;">D:\SISTEMA REDE ARRASTAO</code>
                    </p>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; color: #94a3b8; font-size: 0.8rem;"><i class="fa-solid fa-check" style="color: #10b981;"></i> Todos os arquivos PHP</div>
                        <div style="display: flex; align-items: center; gap: 0.5rem; color: #94a3b8; font-size: 0.8rem;"><i class="fa-solid fa-check" style="color: #10b981;"></i> Banco de dados (SQL completo)</div>
                        <div style="display: flex; align-items: center; gap: 0.5rem; color: #94a3b8; font-size: 0.8rem;"><i class="fa-solid fa-check" style="color: #10b981;"></i> Fotos e uploads</div>
                        <div style="display: flex; align-items: center; gap: 0.5rem; color: #94a3b8; font-size: 0.8rem;"><i class="fa-solid fa-check" style="color: #10b981;"></i> Guia de restauração incluído</div>
                        <div style="display: flex; align-items: center; gap: 0.5rem; color: #94a3b8; font-size: 0.8rem;"><i class="fa-solid fa-check" style="color: #10b981;"></i> Formato ZIP portátil</div>
                        <div style="display: flex; align-items: center; gap: 0.5rem; color: #94a3b8; font-size: 0.8rem;"><i class="fa-solid fa-check" style="color: #10b981;"></i> Mantém últimos 5 backups</div>
                    </div>
                </div>
                <div style="display: flex; flex-direction: column; align-items: center; gap: 1rem;">
                    <button onclick="runFullBackup()" id="btnFullBackup" style="background: linear-gradient(135deg, #38bdf8, #0ea5e9); color: #0f172a; padding: 1rem 2rem; font-size: 1rem; font-weight: 900; border: none; border-radius: 1rem; cursor: pointer; display: flex; align-items: center; gap: 0.75rem; transition: all 0.3s; white-space: nowrap; box-shadow: 0 4px 20px rgba(56,189,248,0.3);">
                        <i class="fa-solid fa-server" id="iconFullBackup"></i>
                        <span id="textFullBackup">Fazer Backup Agora</span>
                    </button>
                    <div id="backupProgressBar" style="display:none; width: 200px;">
                        <div style="height: 6px; background: #334155; border-radius: 3px; overflow: hidden;">
                            <div id="backupBar" style="height: 100%; width: 0%; background: linear-gradient(90deg, #38bdf8, #7c3aed); border-radius: 3px; transition: width 0.5s ease; animation: backupPulse 1.5s infinite;"></div>
                        </div>
                        <p style="text-align: center; font-size: 0.7rem; color: #64748b; margin-top: 0.5rem;" id="backupProgressText">Processando...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Perfis de Acesso -->
<div class="glass-panel" style="margin-top: 2rem;">
    <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--crm-black); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
        <i class="fa-solid fa-shield-halved" style="color: var(--crm-purple);"></i>
        Perfis de Acesso e Permissões
    </h3>
    
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem;">
        <div style="padding: 1.5rem; background: linear-gradient(135deg, #5B21B6 0%, #7C3AED 100%); border-radius: 1rem; color: white;">
            <div style="font-size: 2rem; margin-bottom: 0.5rem;">👑</div>
            <div style="font-weight: 900; margin-bottom: 0.5rem;">Administrador</div>
            <div style="font-size: 0.75rem; opacity: 0.9;">Acesso total ao sistema</div>
        </div>
        
        <div style="padding: 1.5rem; background: linear-gradient(135deg, #3B82F6 0%, #60A5FA 100%); border-radius: 1rem; color: white;">
            <div style="font-size: 2rem; margin-bottom: 0.5rem;">👔</div>
            <div style="font-weight: 900; margin-bottom: 0.5rem;">Responsável de Setor</div>
            <div style="font-size: 0.75rem; opacity: 0.9;">Acesso ao seu setor e unidade</div>
        </div>
        
        <div style="padding: 1.5rem; background: linear-gradient(135deg, #10B981 0%, #34D399 100%); border-radius: 1rem; color: white;">
            <div style="font-size: 2rem; margin-bottom: 0.5rem;">🛠️</div>
            <div style="font-weight: 900; margin-bottom: 0.5rem;">Suporte Técnico</div>
            <div style="font-size: 0.75rem; opacity: 0.9;">Acesso a todos os arquivos</div>
        </div>
        
        <div style="padding: 1.5rem; background: linear-gradient(135deg, #64748b 0%, #94a3b8 100%); border-radius: 1rem; color: white;">
            <div style="font-size: 2rem; margin-bottom: 0.5rem;">👤</div>
            <div style="font-weight: 900; margin-bottom: 0.5rem;">Colaborador</div>
            <div style="font-size: 0.75rem; opacity: 0.9;">Acesso limitado ao seu departamento</div>
        </div>
    </div>
    </div>
</div>
<!-- ABA CERTIFICADOS -->
<div id="content-certificados" class="tab-content">
    <div class="glass-panel">
        <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--crm-black); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-certificate" style="color: var(--crm-purple);"></i>
            Configuração de Certificados
        </h3>
        <p style="color: #64748b; font-size: 0.875rem; margin-bottom: 2rem;">Personalize as informações que aparecerão nos certificados de voluntariado gerados pelo sistema.</p>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_company">
            <input type="hidden" name="company_name" value="<?= htmlspecialchars($company['company_name'] ?? '') ?>">
            <input type="hidden" name="current_logo" value="<?= htmlspecialchars($company['logo_url'] ?? '') ?>">
            <input type="hidden" name="current_signature" value="<?= htmlspecialchars($company['certificate_signature_url'] ?? '') ?>">
            <input type="hidden" name="login_announcement" value="<?= htmlspecialchars($company['login_announcement'] ?? '') ?>">
            <input type="hidden" name="current_announcement_image" value="<?= htmlspecialchars($company['announcement_image_url'] ?? '') ?>">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div class="form-group">
                    <label class="form-label">Imagem da Assinatura (Campo Inferior)</label>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div id="signature_preview" style="width: 100%; height: 120px; border: 2px dashed #e2e8f0; border-radius: 12px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #fff;">
                            <?php if (!empty($company['certificate_signature_url'])): ?>
                                <img src="<?= htmlspecialchars($company['certificate_signature_url']) ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                            <?php else: ?>
                                <i class="fa-solid fa-pen-nib" style="font-size: 2rem; color: #cbd5e1;"></i>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="signature" accept="image/*" class="form-input" onchange="previewImage(this, 'signature_preview')">
                        <p style="font-size: 0.75rem; color: #94a3b8;">PNG transparente recomendado (aprox. 300x150px).</p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Texto Global do Certificado</label>
                    <textarea name="certificate_global_text" class="form-input" style="height: 156px; resize: none;" placeholder="Este texto aparecerá abaixo da assinatura em todos os certificados..."><?= htmlspecialchars($company['certificate_global_text'] ?? '') ?></textarea>
                    <p style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.5rem;">Ex: "Este certificado é emitido digitalmente e validado pela Cetusg."</p>
                </div>
            </div>

            <div style="margin-top: 1rem;">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar Configurações de Certificado
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ABA ANIVERSÁRIOS -->
<div id="content-aniversarios" class="tab-content">
    <div class="glass-panel">
        <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--crm-black); margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-cake-candles" style="color: #EC4899;"></i>
            Mensagens de Aniversário
        </h3>
        <p style="color: #64748b; font-size: 0.875rem; margin-bottom: 2rem;">Configure as mensagens exibidas e enviadas automaticamente nos aniversários dos colaboradores.</p>

        <form method="POST">
            <input type="hidden" name="action" value="save_birthday_messages">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">

                <!-- Mensagem para Todos -->
                <div style="padding: 1.5rem; background: linear-gradient(135deg, rgba(236,72,153,0.06), rgba(236,72,153,0.02)); border: 1px solid rgba(236,72,153,0.2); border-radius: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                        <div style="width: 44px; height: 44px; background: rgba(236,72,153,0.12); border-radius: 0.75rem; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-bullhorn" style="color: #EC4899; font-size: 1.2rem;"></i>
                        </div>
                        <div>
                            <div style="font-weight: 900; color: #1e293b; font-size: 1rem;">Mensagem para Todos</div>
                            <div style="font-size: 0.75rem; color: #64748b;">Aparece no modal de aniversário para todos os usuários</div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mensagem Geral de Aniversário</label>
                        <textarea name="birthday_message_all" class="form-input" style="height: 150px; resize: none;" placeholder="Ex: Hoje nosso colega faz aniversário! Vamos parabenizar! 🎉"><?= htmlspecialchars($company['birthday_message_all'] ?? '') ?></textarea>
                        <p style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">Se vazio, usa a mensagem padrão do sistema.</p>
                    </div>
                </div>

                <!-- Mensagem para o Aniversariante -->
                <div style="padding: 1.5rem; background: linear-gradient(135deg, rgba(168,85,247,0.06), rgba(168,85,247,0.02)); border: 1px solid rgba(168,85,247,0.2); border-radius: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                        <div style="width: 44px; height: 44px; background: rgba(168,85,247,0.12); border-radius: 0.75rem; display: flex; align-items: center; justify-content: center;">
                            <i class="fa-solid fa-comment-dots" style="color: #a855f7; font-size: 1.2rem;"></i>
                        </div>
                        <div>
                            <div style="font-weight: 900; color: #1e293b; font-size: 1rem;">Mensagem para o Aniversariante</div>
                            <div style="font-size: 0.75rem; color: #64748b;">Enviada via chat direto para o aniversariante (uma única vez por ano)</div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mensagem Privada de Aniversário</label>
                        <textarea name="birthday_message_self" class="form-input" style="height: 150px; resize: none;" placeholder="Ex: Parabéns! A equipe deseja um excelente aniversário! 🎂"><?= htmlspecialchars($company['birthday_message_self'] ?? '') ?></textarea>
                        <p style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">Será enviada automaticamente via chat no dia do aniversário, direto para o colaborador.</p>
                    </div>
                </div>

            </div>
            <div style="margin-top: 1.5rem;">
                <button type="submit" class="btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar Mensagens de Aniversário
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ABA CARGOS -->
<div id="content-cargos" class="tab-content">
    <div class="glass-panel">
        <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--crm-black); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-briefcase" style="color: var(--crm-purple);"></i>
            Gerenciar Cargos
        </h3>
        <p style="color: #64748b; font-size: 0.875rem; margin-bottom: 1.5rem;">Os cargos cadastrados aqui ficam disponíveis no cadastro de Usuários e no módulo de Recursos Humanos.</p>
        
        <form method="POST" style="display: flex; gap: 1rem; align-items: end; margin-bottom: 2rem; background: #f8fafc; padding: 1.25rem; border-radius: 1rem; border: 1px solid #e2e8f0;">
            <input type="hidden" name="action" value="add_position">
            <div style="flex: 1;">
                <label class="form-label">Nome do Cargo *</label>
                <input type="text" name="position_name" class="form-input" placeholder="Ex: Analista de RH, Desenvolvedor, Assistente..." required>
            </div>
            <button type="submit" class="btn-primary">
                <i class="fa-solid fa-plus"></i> Adicionar Cargo
            </button>
        </form>
        
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
            <?php if (empty($positions)): ?>
                <div style="grid-column: span 3; text-align: center; padding: 3rem; color: #94a3b8;">
                    <i class="fa-solid fa-briefcase" style="font-size: 2rem; margin-bottom: 1rem; display: block; opacity: 0.5;"></i>
                    Nenhum cargo cadastrado. Adicione o primeiro acima.
                </div>
            <?php else: ?>
                <?php foreach ($positions as $pos): ?>
                <div style="padding: 1rem; background: linear-gradient(135deg, rgba(91, 33, 182, 0.05) 0%, rgba(251, 191, 36, 0.02) 100%); border-radius: 1rem; border: 1px solid rgba(91, 33, 182, 0.1); display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <i class="fa-solid fa-user-tie" style="color: var(--crm-purple); margin-right: 0.5rem;"></i>
                        <span style="font-weight: 700; color: #334155;"><?= htmlspecialchars($pos['name']) ?></span>
                    </div>
                    <?php if ($user['role'] === 'Administrador'): ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Excluir o cargo \'<?= htmlspecialchars(addslashes($pos['name'])) ?>\'?')">
                        <input type="hidden" name="action" value="delete_position">
                        <input type="hidden" name="position_id" value="<?= $pos['id'] ?>">
                        <button type="submit" style="background: #ef4444; color: white; border: none; width: 28px; height: 28px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 12px; transition: all .18s;" title="Excluir cargo">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ABA LOGS -->
<div id="content-logs" class="tab-content">
    <div class="glass-panel">
        <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--crm-black); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-list-check" style="color: var(--crm-purple);"></i>
            Auditoria de Acesso (Últimos 100 Logins)
        </h3>
        
        <div style="overflow-x: auto;">
            <table class="log-table">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Data e Hora</th>
                        <th>Endereço IP</th>
                        <th>Endereço MAC</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 2rem; color: #94a3b8;">Nenhum log registrado ainda.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td style="font-weight: 700; color: var(--crm-purple);">
                                    <i class="fa-solid fa-user" style="margin-right: 0.5rem; opacity: 0.5;"></i>
                                    <?= htmlspecialchars($log['user_name']) ?>
                                </td>
                                <td>
                                    <i class="fa-regular fa-clock" style="margin-right: 0.5rem; opacity: 0.5;"></i>
                                    <?= date('d/m/Y H:i:s', strtotime($log['login_at'])) ?>
                                </td>
                                <td style="font-family: monospace; color: #64748b;">
                                    <?= htmlspecialchars($log['ip_address'] ?: 'N/A') ?>
                                </td>
                                <td style="font-family: monospace; color: #64748b; font-size: 0.75rem;">
                                    <?= htmlspecialchars($log['mac_address'] ?: '--') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Editar Unidade -->
<div id="editUnitModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="glass-panel" style="width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--crm-black); display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-pen" style="color: var(--crm-purple);"></i>
                Editar Unidade
            </h3>
            <button onclick="closeEditUnit()" style="background: none; border: none; cursor: pointer; color: #64748b;">
                <i class="fa-solid fa-xmark" style="width: 24px; height: 24px;"></i>
            </button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="edit_unit">
            <input type="hidden" name="unit_id" id="edit_unit_id">
            <div style="display: grid; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Nome da Unidade *</label>
                    <input type="text" name="name" id="edit_name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">CNPJ</label>
                    <input type="text" name="cnpj" id="edit_cnpj" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Endereço</label>
                    <input type="text" name="address" id="edit_address" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Responsável</label>
                    <input type="text" name="responsible_name" id="edit_responsible_name" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Contato</label>
                    <input type="text" name="contact" id="edit_contact" class="form-input">
                </div>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('content-' + tab).classList.add('active');
    document.getElementById('tab-' + tab).classList.add('active');
}

function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.innerHTML = `<img src="${e.target.result}" style="max-width:100%;max-height:100%;object-fit:contain;">`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function editUnit(unit) {
    document.getElementById('edit_unit_id').value = unit.id;
    document.getElementById('edit_name').value = unit.name;
    document.getElementById('edit_cnpj').value = unit.cnpj || '';
    document.getElementById('edit_address').value = unit.address || '';
    document.getElementById('edit_responsible_name').value = unit.responsible_name || '';
    document.getElementById('edit_contact').value = unit.contact || '';
    document.getElementById('editUnitModal').style.display = 'flex';
    
}

function closeEditUnit() {
    document.getElementById('editUnitModal').style.display = 'none';
}


function runFullBackup() {
    const btn  = document.getElementById('btnFullBackup');
    const text = document.getElementById('textFullBackup');
    const icon = document.getElementById('iconFullBackup');
    const bar  = document.getElementById('backupProgressBar');
    const barFill = document.getElementById('backupBar');
    const barTxt  = document.getElementById('backupProgressText');

    if (btn.disabled) return;
    if (!confirm('Iniciar o backup completo do sistema?\n\nO processo pode levar de 30 segundos a alguns minutos,\ndependendo do volume de dados. Aguarde o final.')) return;

    // Estado de carregamento
    btn.disabled = true;
    btn.style.opacity = '0.7';
    btn.style.cursor = 'not-allowed';
    text.innerText = 'Realizando Backup...';
    icon.className = 'fa-solid fa-circle-notch fa-spin';
    if (bar) bar.style.display = 'block';

    // Simular progresso visual enquanto aguarda
    let progress = 5;
    const steps = [
        { pct: 15, msg: 'Iniciando backup...' },
        { pct: 30, msg: 'Exportando banco de dados...' },
        { pct: 55, msg: 'Compactando arquivos PHP...' },
        { pct: 75, msg: 'Adicionando uploads e fotos...' },
        { pct: 88, msg: 'Gerando guia de restauração...' },
        { pct: 95, msg: 'Finalizando arquivo ZIP...' },
    ];
    let stepIdx = 0;
    const progressInterval = setInterval(() => {
        if (stepIdx < steps.length) {
            progress = steps[stepIdx].pct;
            if (barTxt) barTxt.textContent = steps[stepIdx].msg;
            stepIdx++;
        }
        if (barFill) barFill.style.width = progress + '%';
    }, 2500);

    fetch('process_full_backup.php')
        .then(r => r.json())
        .then(data => {
            clearInterval(progressInterval);
            if (barFill) barFill.style.width = '100%';
            if (barTxt) barTxt.textContent = data.success ? 'Concluído!' : 'Erro!';

            setTimeout(() => {
                // Mostrar modal de resultado
                showBackupResult(data.success, data.message);
                // Resetar botão
                btn.disabled = false;
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
                text.innerText = 'Fazer Backup Agora';
                icon.className = 'fa-solid fa-server';
                if (bar) bar.style.display = 'none';
                if (barFill) barFill.style.width = '0%';
            }, 800);
        })
        .catch(err => {
            clearInterval(progressInterval);
            showBackupResult(false, 'Erro de conexão ao executar o backup.\n\nDetalhes: ' + err.message + '\n\nVerifique se o XAMPP está funcionando corretamente.');
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
            text.innerText = 'Fazer Backup Agora';
            icon.className = 'fa-solid fa-server';
            if (bar) bar.style.display = 'none';
        });
}

function showBackupResult(success, message) {
    // Remover modal anterior se existir
    const oldModal = document.getElementById('backupResultModal');
    if (oldModal) oldModal.remove();

    const lines = message.split('\n').map(l => `<div style="padding: 2px 0; ${l.startsWith('✅') ? 'color:#10b981;font-weight:900;font-size:1.1rem;' : l.startsWith('⚠️') ? 'color:#f59e0b;' : l.startsWith('❌') ? 'color:#ef4444;' : l.startsWith('📁') || l.startsWith('📦') || l.startsWith('📄') || l.startsWith('📍') || l.startsWith('🗂️') || l.startsWith('📖') || l.startsWith('💡') ? 'color:#94a3b8;' : 'color:#cbd5e1;'}">${l || '&nbsp;'}</div>`).join('');

    const modal = document.createElement('div');
    modal.id = 'backupResultModal';
    modal.style = 'position:fixed;inset:0;background:rgba(0,0,0,0.85);backdrop-filter:blur(8px);z-index:99999;display:flex;align-items:center;justify-content:center;padding:2rem;';
    modal.innerHTML = `
        <div style="background: linear-gradient(135deg, #1e293b, #0f172a); border: 2px solid ${success ? '#10b981' : '#ef4444'}; border-radius: 1.5rem; max-width: 560px; width: 100%; padding: 2.5rem; box-shadow: 0 25px 60px rgba(0,0,0,0.5);">
            <div style="text-align:center; margin-bottom: 1.5rem;">
                <div style="width: 72px; height: 72px; border-radius: 50%; background: rgba(${success ? '16,185,129' : '239,68,68'},0.15); display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                    <i class="fa-solid fa-${success ? 'shield-check' : 'triangle-exclamation'}" style="font-size: 2rem; color: ${success ? '#10b981' : '#ef4444'};"></i>
                </div>
                <h3 style="color: #f1f5f9; font-size: 1.25rem; font-weight: 900;">${success ? 'Backup Realizado!' : 'Erro no Backup'}</h3>
            </div>
            <div style="background: rgba(0,0,0,0.3); border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.5rem; font-family: monospace; font-size: 0.85rem; line-height: 1.8;">
                ${lines}
            </div>
            <div style="text-align: center;">
                <button onclick="document.getElementById('backupResultModal').remove()" style="background: ${success ? '#10b981' : '#ef4444'}; color: white; border: none; padding: 0.75rem 2.5rem; border-radius: 0.75rem; font-weight: 900; font-size: 1rem; cursor: pointer;">
                    <i class="fa-solid fa-check"></i> Fechar
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });
}
</script>

