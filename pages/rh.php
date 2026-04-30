<?php
require_once 'access_control.php';

// Criação dinâmica das tabelas de Recursos Humanos se elas não existirem
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS rh_employee_details (
        user_id VARCHAR(50) PRIMARY KEY,
        company_id INT NOT NULL DEFAULT 1,
        contract_type VARCHAR(100) DEFAULT '',
        work_days VARCHAR(100) DEFAULT '',
        work_hours VARCHAR(100) DEFAULT '',
        start_date DATE NULL,
        end_date DATE NULL
    )");
    
    // Auto-update schema for new RH fields (Ignored if already exists)
    try { $pdo->exec("ALTER TABLE rh_employee_details ADD COLUMN gender VARCHAR(20) DEFAULT ''"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE rh_employee_details ADD COLUMN role_name VARCHAR(100) DEFAULT ''"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE rh_employee_details ADD COLUMN salary DECIMAL(15,2) DEFAULT 0.00"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE rh_employee_details ADD COLUMN use_transport VARCHAR(10) DEFAULT 'Não'"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE rh_employee_details ADD COLUMN transport_value DECIMAL(15,2) DEFAULT 0.00"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE rh_employee_details ADD COLUMN birth_date DATE NULL"); } catch(Exception $e){}

    $pdo->exec("CREATE TABLE IF NOT EXISTS rh_vacations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        company_id INT NOT NULL DEFAULT 1,
        reference_year INT NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        limit_date DATE NOT NULL,
        status VARCHAR(50) DEFAULT 'Programada'
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS rh_certificates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        company_id INT NOT NULL DEFAULT 1,
        issue_date DATE NOT NULL,
        days_off INT NOT NULL,
        reason VARCHAR(255),
        file_url VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS rh_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        company_id INT NOT NULL DEFAULT 1,
        note_text TEXT NOT NULL,
        created_by VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    // Garantir colunas company_id existem nas tabelas já criadas
    try { $pdo->exec("ALTER TABLE rh_employee_details ADD COLUMN company_id INT NOT NULL DEFAULT 1"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE rh_vacations ADD COLUMN company_id INT NOT NULL DEFAULT 1"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE rh_certificates ADD COLUMN company_id INT NOT NULL DEFAULT 1"); } catch(Exception $e){}
    try { $pdo->exec("ALTER TABLE rh_notes ADD COLUMN company_id INT NOT NULL DEFAULT 1"); } catch(Exception $e){}
} catch (Exception $e) {}

// Garantir tabela de cargos existe
$compId = getCurrentUserCompanyId();
$all_positions = [];
try { 
    $stmt_pos = $pdo->prepare("SELECT * FROM rh_positions WHERE company_id = ? ORDER BY name ASC");
    $stmt_pos->execute([$compId]);
    $all_positions = $stmt_pos->fetchAll(); 
} catch(Exception $e) {}

// Auto-migrate: colunas gender e position na tabela users
try { $pdo->exec("ALTER TABLE users ADD COLUMN gender VARCHAR(20) DEFAULT ''"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN position VARCHAR(100) DEFAULT ''"); } catch(Exception $e) {}

// Processamento dos Formulários do RH
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // 1. Dados Contratais
    if ($action === 'save_contract') {
        $compId = getCurrentUserCompanyId();
        $stmt = $pdo->prepare("REPLACE INTO rh_employee_details (user_id, company_id, contract_type, role_name, work_days, work_hours, salary, use_transport, transport_value, gender, birth_date, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['user_id'], $compId,
            $_POST['contract_type'], 
            $_POST['role_name'] ?? '', 
            $_POST['work_days'], 
            $_POST['work_hours'], 
            !empty($_POST['salary']) ? $_POST['salary'] : 0, 
            $_POST['use_transport'] ?? 'Não', 
            !empty($_POST['transport_value']) ? $_POST['transport_value'] : 0, 
            $_POST['gender'] ?? '',
            !empty($_POST['birth_date']) ? $_POST['birth_date'] : null,
            !empty($_POST['start_date']) ? $_POST['start_date'] : null, 
            !empty($_POST['end_date']) ? $_POST['end_date'] : null
        ]);
        // Sync bidirecional: atualizar tabela users com gender e position
        try {
            $pdo->prepare("UPDATE users SET gender = ?, position = ? WHERE id = ? AND company_id = ?")->execute([
                $_POST['gender'] ?? '', $_POST['role_name'] ?? '', $_POST['user_id'], $compId
            ]);
        } catch(Exception $e) {}
        header('Location: ?page=rh&success=contract'); exit;
    }
    
    // 2. Férias
    if ($action === 'add_vacation') {
        $compId = getCurrentUserCompanyId();
        $stmt = $pdo->prepare("INSERT INTO rh_vacations (user_id, company_id, reference_year, start_date, end_date, limit_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['user_id'], $compId, $_POST['reference_year'], $_POST['start_date'], $_POST['end_date'], $_POST['limit_date'], $_POST['status']
        ]);
        header('Location: ?page=rh&success=vacation_added'); exit;
    }
    if ($action === 'delete_vacation') {
        $compId = getCurrentUserCompanyId();
        $stmt = $pdo->prepare("DELETE FROM rh_vacations WHERE id=? AND company_id=?");
        $stmt->execute([$_POST['vacation_id'], $compId]);
        header('Location: ?page=rh&success=vacation_deleted'); exit;
    }

    // 3. Atestados
    if ($action === 'add_certificate') {
        $compId = getCurrentUserCompanyId();
        $file_url = null;
        if (!empty($_FILES['certificate_file']['name'])) {
            $ext = pathinfo($_FILES['certificate_file']['name'], PATHINFO_EXTENSION);
            $filename = 'cert_' . time() . '_' . rand(100,999) . '.' . $ext;
            $dest = __DIR__ . '/../uploads/' . $filename;
            if (move_uploaded_file($_FILES['certificate_file']['tmp_name'], $dest)) {
                $file_url = 'uploads/' . $filename;
            }
        }
        $stmt = $pdo->prepare("INSERT INTO rh_certificates (user_id, company_id, issue_date, days_off, reason, file_url) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['user_id'], $compId, $_POST['issue_date'], $_POST['days_off'], $_POST['reason'], $file_url]);
        header('Location: ?page=rh&success=certificate_added'); exit;
    }
    if ($action === 'delete_certificate') {
        $compId = getCurrentUserCompanyId();
        $stmt = $pdo->prepare("DELETE FROM rh_certificates WHERE id=? AND company_id=?");
        $stmt->execute([$_POST['certificate_id'], $compId]);
        header('Location: ?page=rh&success=certificate_deleted'); exit;
    }

    // 4. Anotações Gerais do RH
    if ($action === 'add_note') {
        $compId = getCurrentUserCompanyId();
        $stmt = $pdo->prepare("INSERT INTO rh_notes (user_id, company_id, note_text, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['user_id'], $compId, $_POST['note_text'], $user['name']]);
        header('Location: ?page=rh&success=note_added'); exit;
    }
    if ($action === 'delete_note') {
        $compId = getCurrentUserCompanyId();
        $stmt = $pdo->prepare("DELETE FROM rh_notes WHERE id=? AND company_id=?");
        $stmt->execute([$_POST['note_id'], $compId]);
        header('Location: ?page=rh&success=note_deleted'); exit;
    }

    // 5. Reativar Usuário
    if ($action === 'reactivate_user') {
        $compId = getCurrentUserCompanyId();
        $uid = $_POST['user_id'];
        $pdo->prepare("UPDATE rh_employee_details SET end_date = NULL WHERE user_id = ? AND company_id = ?")->execute([$uid, $compId]);
        $pdo->prepare("UPDATE users SET status = 'Ativo' WHERE id = ? AND company_id = ?")->execute([$uid, $compId]);
        header('Location: ?page=rh&success=reactivated'); exit;
    }

    // 6. Recados Geral
    if ($action === 'add_announcement') {
        $compId = getCurrentUserCompanyId();
        $image_url = null;
        if (!empty($_FILES['announcement_image']['name'])) {
            $ext = pathinfo($_FILES['announcement_image']['name'], PATHINFO_EXTENSION);
            $filename = 'ann_' . time() . '_' . rand(100,999) . '.' . $ext;
            $dest = __DIR__ . '/../uploads/' . $filename;
            if (move_uploaded_file($_FILES['announcement_image']['tmp_name'], $dest)) {
                $image_url = 'uploads/' . $filename;
            }
        }
        $stmt = $pdo->prepare("INSERT INTO announcements (message, image_url, created_by, company_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['message'], $image_url, $user['name'], $compId]);
        header('Location: ?page=rh&success=announcement_added&tab=recados'); exit;
    }
    if ($action === 'delete_announcement') {
        $compId = getCurrentUserCompanyId();
        $stmt = $pdo->prepare("DELETE FROM announcements WHERE id=? AND company_id=?");
        $stmt->execute([$_POST['announcement_id'], $compId]);
        header('Location: ?page=rh&success=announcement_deleted&tab=recados'); exit;
    }
    if ($action === 'edit_announcement') {
        $compId = getCurrentUserCompanyId();
        $id = $_POST['announcement_id'];
        $msg = $_POST['message'];
        $image_url = $_POST['current_image'];
        
        if (!empty($_FILES['announcement_image']['name'])) {
            $ext = pathinfo($_FILES['announcement_image']['name'], PATHINFO_EXTENSION);
            $filename = 'ann_' . time() . '_' . rand(100,999) . '.' . $ext;
            $dest = __DIR__ . '/../uploads/' . $filename;
            if (move_uploaded_file($_FILES['announcement_image']['tmp_name'], $dest)) {
                $image_url = 'uploads/' . $filename;
            }
        }
        $stmt = $pdo->prepare("UPDATE announcements SET message = ?, image_url = ? WHERE id = ? AND company_id = ?");
        $stmt->execute([$msg, $image_url, $id, $compId]);
        header('Location: ?page=rh&success=announcement_updated&tab=recados'); exit;
    }
}

// Resgate de Dados para a UI e injeção do JS Dashboard
$search = $_GET['search'] ?? '';
$query = "
    SELECT 
        u.id, u.name, u.email, u.sector, u.unit_id, u.avatar_url, u.status, u.role, u.phone,
        un.name as unit_name,
        rh.contract_type, rh.role_name, rh.work_days, rh.work_hours, rh.salary, rh.use_transport, rh.transport_value, rh.gender, rh.birth_date, rh.start_date, rh.end_date 
    FROM users u
    LEFT JOIN units un ON BINARY u.unit_id = BINARY un.id
    LEFT JOIN rh_employee_details rh ON BINARY u.id = BINARY rh.user_id
    WHERE u.company_id = ?
";
$compId = getCurrentUserCompanyId();
$params = [$compId];
if ($search) {
    $query .= " AND (u.name LIKE ? OR u.sector LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
$query .= " ORDER BY u.sector ASC, u.name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sectors_list = [];
foreach ($users_data as $usr) {
    $sectors_list[$usr['sector'] ?? 'Sem Setor'][] = $usr;
}

$stmt_vac = $pdo->prepare("SELECT * FROM rh_vacations WHERE company_id = ? ORDER BY start_date DESC");
$stmt_vac->execute([$compId]);
$all_vacations = $stmt_vac->fetchAll(PDO::FETCH_ASSOC);

$stmt_cert = $pdo->prepare("SELECT * FROM rh_certificates WHERE company_id = ? ORDER BY issue_date DESC");
$stmt_cert->execute([$compId]);
$all_certificates = $stmt_cert->fetchAll(PDO::FETCH_ASSOC);

try {
    $stmt_notes = $pdo->prepare("SELECT * FROM rh_notes WHERE company_id = ? ORDER BY created_at DESC");
    $stmt_notes->execute([$compId]);
    $all_notes = $stmt_notes->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $all_notes = [];
}

// Resgate de Recados
$stmt_ann = $pdo->prepare("
    SELECT a.*, (SELECT COUNT(*) FROM announcement_views v WHERE v.announcement_id = a.id) as views 
    FROM announcements a 
    WHERE a.company_id = ?
    ORDER BY a.created_at DESC
");
$stmt_ann->execute([$compId]);
$all_announcements = $stmt_ann->fetchAll(PDO::FETCH_ASSOC);
?>

<script>
    const usersData = <?= json_encode($users_data ?: []) ?>;
    const vacationsData = <?= json_encode($all_vacations ?: []) ?>;
    const certsData = <?= json_encode($all_certificates ?: []) ?>;
    const notesData = <?= json_encode($all_notes ?: []) ?>;

    // Ações Férias
    function openContractModal(userId) {
        const user = usersData.find(u => u.id == userId);
        if(!user) return;
        document.getElementById('c_user_id').value = user.id;
        document.getElementById('c_modal_title').innerText = 'Contrato: ' + user.name;
        document.getElementById('c_contract_type').value = user.contract_type || '';
        document.getElementById('c_role_name').value = user.role_name || '';
        document.getElementById('c_salary').value = user.salary || '';
        document.getElementById('c_use_transport').value = user.use_transport || 'Não';
        document.getElementById('c_transport_value').value = user.transport_value || '';
        document.getElementById('c_gender').value = user.gender || '';
        document.getElementById('c_birth_date').value = user.birth_date || '';
        document.getElementById('c_work_days').value = user.work_days || '';
        document.getElementById('c_work_hours').value = user.work_hours || '';
        document.getElementById('c_start_date').value = user.start_date || '';
        document.getElementById('c_end_date').value = user.end_date || '';
        
        toggleTransportValue();
        document.getElementById('contractModal').style.display = 'flex';
    }

    function toggleTransportValue() {
        const useT = document.getElementById('c_use_transport').value;
        const valDiv = document.getElementById('div_transport_value');
        if (useT === 'Sim') {
            valDiv.style.display = 'block';
        } else {
            valDiv.style.display = 'none';
        }
    }

    function addCustomContractType() {
        const newType = prompt("Digite o novo TIPO DE CONTRATAÇÃO (ex: Menor Aprendiz, Terceirizado):");
        if(newType && newType.trim() !== '') {
            const select = document.getElementById('c_contract_type');
            const option = document.createElement('option');
            option.value = newType.trim();
            option.text = newType.trim();
            select.add(option);
            select.value = newType.trim();
        }
    }

    function addCustomCargo() {
        const newVal = prompt("Digite o novo CARGO (ex: Analista, Coordenador, Auxiliar):");
        if(newVal && newVal.trim() !== '') {
            const select = document.getElementById('c_role_name');
            const option = document.createElement('option');
            option.value = newVal.trim();
            option.text = newVal.trim();
            select.add(option);
            select.value = newVal.trim();
        }
    }

    function addCustomGender() {
        const newVal = prompt("Digite o novo SEXO / GÊNERO:");
        if(newVal && newVal.trim() !== '') {
            const select = document.getElementById('c_gender');
            const option = document.createElement('option');
            option.value = newVal.trim();
            option.text = newVal.trim();
            select.add(option);
            select.value = newVal.trim();
        }
    }

    function addCustomWorkDays() {
        const newVal = prompt("Digite a nova ESCALA DE DIAS (ex: Segunda a Sábado, Escala 6x1):");
        if(newVal && newVal.trim() !== '') {
            const select = document.getElementById('c_work_days');
            const option = document.createElement('option');
            option.value = newVal.trim();
            option.text = newVal.trim();
            select.add(option);
            select.value = newVal.trim();
        }
    }

    function addCustomWorkHours() {
        const newVal = prompt("Digite o novo HORÁRIO DE TRABALHO (ex: 07:00 às 16:00):");
        if(newVal && newVal.trim() !== '') {
            const select = document.getElementById('c_work_hours');
            const option = document.createElement('option');
            option.value = newVal.trim();
            option.text = newVal.trim();
            select.add(option);
            select.value = newVal.trim();
        }
    }

    function openVacationModal(userId, userName) {
        document.getElementById('v_user_id').value = userId;
        document.getElementById('v_modal_title').innerText = 'Gestão de Férias: ' + userName;
        const userVacations = vacationsData.filter(v => v.user_id === userId);
        
        let html = '';
        if (userVacations.length === 0) {
            html = '<tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:2rem;">Nenhum registro de férias.</td></tr>';
        } else {
            userVacations.forEach(v => {
                const partsSt = v.start_date.split('-');
                const st = `${partsSt[2]}/${partsSt[1]}/${partsSt[0]}`;
                const partsEd = v.end_date.split('-');
                const ed = `${partsEd[2]}/${partsEd[1]}/${partsEd[0]}`;
                const partsLi = v.limit_date.split('-');
                const li = `${partsLi[2]}/${partsLi[1]}/${partsLi[0]}`;
                
                const limitDateObj = new Date(v.limit_date);
                const today = new Date();
                const diffTime = limitDateObj - today;
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                let limitColor = '#10b981';
                let alertIcon = '';
                if (diffDays <= 30 && v.status !== 'Concluída' && v.status !== 'Cancelada') {
                    limitColor = '#ef4444'; // Vencendo ou vencida!
                    alertIcon = '<i class="fa-solid fa-triangle-exclamation" style="color:#ef4444;margin-left:5px;" title="Atenção! Período limite próximo"></i>';
                }

                const badgeMap = {
                    'Programada': 'badge-warning',
                    'Concluída': 'badge-success',
                    'Cancelada': 'badge-danger'
                };

                html += `<tr>
                    <td style="font-weight:900;">${v.reference_year}</td>
                    <td>${st} até ${ed}</td>
                    <td style="color:${limitColor};font-weight:700;">${li} ${alertIcon}</td>
                    <td><span class="badge ${badgeMap[v.status] || 'badge-info'}">${v.status}</span></td>
                    <td style="text-align:right;">
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Deseja excluir este registro de férias?');">
                            <input type="hidden" name="action" value="delete_vacation">
                            <input type="hidden" name="vacation_id" value="${v.id}">
                            <button type="submit" class="btn-icon" style="border:none;background:red;color:white;width:28px;height:28px;"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>`;
            });
        }
        document.getElementById('vacations_table_body').innerHTML = html;
        document.getElementById('vacationModal').style.display = 'flex';
    }

    function openCertModal(userId, userName) {
        document.getElementById('cert_user_id').value = userId;
        document.getElementById('cert_modal_title').innerText = 'Atestados: ' + userName;
        const userCerts = certsData.filter(c => c.user_id === userId);
        
        let html = '';
        if (userCerts.length === 0) {
            html = '<tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:2rem;">Nenhum atestado registrado.</td></tr>';
        } else {
            userCerts.forEach(c => {
                const partsIssue = c.issue_date.split('-');
                const issue = `${partsIssue[2]}/${partsIssue[1]}/${partsIssue[0]}`;
                const fileLink = c.file_url ? `<a href="${c.file_url}" target="_blank" style="color:var(--crm-purple);"><i class="fa-solid fa-file-pdf"></i> Acessar Arquivo</a>` : '<span style="color:#94a3b8">Sem anexo</span>';
                html += `<tr>
                    <td style="font-weight:700;">${issue}</td>
                    <td><span class="badge badge-warning">${c.days_off} dias</span></td>
                    <td style="font-size:0.8rem;color:#64748b;max-width:200px;overflow:hidden;text-overflow:ellipsis;">${c.reason || 'Sem descrição'}</td>
                    <td>${fileLink}</td>
                    <td style="text-align:right;">
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Deseja excluir este atestado judicial/médico permanentemente?');">
                            <input type="hidden" name="action" value="delete_certificate">
                            <input type="hidden" name="certificate_id" value="${c.id}">
                            <button type="submit" class="btn-icon" style="border:none;background:red;color:white;width:28px;height:28px;"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </td>
                </tr>`;
            });
        }
        document.getElementById('certs_table_body').innerHTML = html;
        document.getElementById('certModal').style.display = 'flex';
    }

    function openNotesModal(userId, userName) {
        document.getElementById('n_user_id').value = userId;
        document.getElementById('n_modal_title').innerText = 'Informações de ' + userName;
        const userNotes = notesData.filter(n => n.user_id === userId);
        
        let html = '';
        if (userNotes.length === 0) {
            html = '<div style="text-align:center;color:#94a3b8;padding:2rem;">Nenhuma informação registrada.</div>';
        } else {
            userNotes.forEach(n => {
                const dt = new Date(n.created_at).toLocaleString('pt-BR');
                html += `
                <div style="background: var(--bg-main); color: var(--text-main); border: 1px solid var(--border-color); border-radius:0.75rem; padding:1.25rem; margin-bottom:1rem; position:relative;">
                    <form method="POST" style="position:absolute; top:1rem; right:1rem;" onsubmit="return confirm('Excluir esta anotação?');">
                        <input type="hidden" name="action" value="delete_note">
                        <input type="hidden" name="note_id" value="${n.id}">
                        <button type="submit" style="background:none; border:none; cursor:pointer; color:#ef4444;"><i class="fa-solid fa-trash"></i></button>
                    </form>
                    <div style="font-size:0.75rem; color:#64748b; margin-bottom:0.75rem; font-weight:700;">
                        <i class="fa-solid fa-clock"></i> ${dt} &bull; <i class="fa-solid fa-user-pen"></i> ${n.created_by}
                    </div>
                    <div style="color:#334155; line-height:1.5; white-space:pre-wrap;">${n.note_text}</div>
                </div>`;
            });
        }
        document.getElementById('notes_content_body').innerHTML = html;
        document.getElementById('notesModal').style.display = 'flex';
    }

    // Navegação de Abas RH
    function switchRhTab(tabId, btn) {
        document.querySelectorAll('.rh-tab-content').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.rh-tab-btn').forEach(b => b.classList.remove('active'));
        
        document.getElementById('tab-' + tabId).classList.add('active');
        btn.classList.add('active');
        
        // Salvar aba na URL para manter ao recarregar
        const url = new URL(window.location);
        url.searchParams.set('tab', tabId);
        window.history.pushState({}, '', url);
    }

    // Auto-carregar aba pela URL
    document.addEventListener('DOMContentLoaded', () => {
        const params = new URLSearchParams(window.location.search);
        const tab = params.get('tab');
        if (tab === 'recados') {
            const btn = document.getElementById('tab-btn-recados');
            if (btn) switchRhTab('recados', btn);
        }
    });

    function openEditAnnModal(id, message, imageUrl) {
        document.getElementById('edit_ann_id').value = id;
        document.getElementById('edit_ann_message').value = message;
        document.getElementById('edit_ann_current_img').value = imageUrl;
        document.getElementById('editAnnModal').style.display = 'flex';
    }
</script>

<style>
/* Utilities internas de botões RH */
.rh-btn-contract { background: #EEF2FF; color: #4F46E5; border-color: #E0E7FF; }
.rh-btn-contract:hover { background: #4F46E5; color: #fff; }

.rh-btn-vacation { background: #FFFBEB; color: #D97706; border-color: #FEF3C7; }
.rh-btn-vacation:hover { background: #D97706; color: #fff; }

.rh-btn-cert { background: #F0FDF4; color: #16A34A; border-color: #DCFCE7; }
.rh-btn-cert:hover { background: #16A34A; color: #fff; }

.rh-btn-notes { background: #F8FAFC; color: #475569; border-color: #E2E8F0; }
.rh-btn-notes:hover { background: #475569; color: #fff; }

.table-modal { width: 100%; border-collapse: collapse; margin-top: 1rem; }
.table-modal th { background: #f8fafc; padding: 0.75rem; text-align: left; font-size: 0.75rem; color: var(--text-soft); font-weight: 700; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
.table-modal td { padding: 1rem 0.75rem; font-size: 0.85rem; border-bottom: 1px solid #e2e8f0; }

.user-grid-info { 
    flex: 1; 
    display: grid; 
    grid-template-columns: 1.5fr 1fr 1.2fr 160px; 
    gap: 1rem; 
    align-items: center; 
}

@media (max-width: 992px) {
    .user-grid-info {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 600px) {
    .user-grid-info {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    .rh-employee-card-flex {
        flex-direction: column;
        align-items: flex-start !important;
    }
        .rh-employee-avatar {
        width: 100% !important;
        height: 120px !important;
        border-radius: 1rem !important;
    }
}

/* Sistema de Abas RH */
.rh-tabs {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 1px;
}
.rh-tab-btn {
    padding: 0.75rem 1.5rem;
    border: none;
    background: none;
    font-weight: 700;
    font-size: 0.875rem;
    color: var(--text-soft);
    cursor: pointer;
    position: relative;
    transition: all 0.2s;
}
.rh-tab-btn:hover {
    color: var(--crm-purple);
}
.rh-tab-btn.active {
    color: var(--crm-purple);
}
.rh-tab-btn.active::after {
    content: '';
    position: absolute;
    bottom: -1px;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--crm-purple);
}
.rh-tab-content {
    display: none;
}
.rh-tab-content.active {
    display: block;
}
</style>

<div class="page-header">
    <div class="page-header-info">
        <div class="page-header-icon">
            <i class="fa-solid fa-user-tie"></i>
        </div>
        <div class="page-header-text">
            <h2>Gestão de Equipe & Capital Humano</h2>
            <p>Administração estruturada de contratos, benefícios e acompanhamento funcional.</p>
        </div>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <?php
    $msgs = [
        'contract' => 'Dados contratuais salvos com sucesso.',
        'vacation_added' => 'Férias programadas com sucesso.',
        'vacation_deleted' => 'Registro de férias foi removido com sucesso.',
        'certificate_added' => 'Atestado emitido com sucesso ao painel do funcionário.',
        'certificate_deleted' => 'Atestado excluído definitivamente do histórico.',
        'note_added' => 'Informação/Anotação arquivada no dossiê do colaborador.',
        'note_deleted' => 'Anotação removida com sucesso.',
        'reactivated' => 'Vínculo do funcionário reativado! O acesso foi restaurado e a data de demissão removida.',
        'announcement_added' => 'Recado publicado com sucesso para todos os usuários!',
        'announcement_updated' => 'Recado atualizado com sucesso!',
        'announcement_deleted' => 'Recado removido do sistema.'
    ];
    $success_msg = $msgs[$_GET['success']] ?? 'Ação realizada com sucesso.';
    ?>
    </div>
<?php endif; ?>

<!-- Navegação por Abas -->
<div class="rh-tabs">
    <button class="rh-tab-btn active" id="tab-btn-funcionarios" onclick="switchRhTab('funcionarios', this)">
        <i class="fa-solid fa-users"></i> Gestão de Funcionários
    </button>
    <button class="rh-tab-btn" id="tab-btn-recados" onclick="switchRhTab('recados', this)">
        <i class="fa-solid fa-bullhorn"></i> Recados Geral
    </button>
</div>

<div id="tab-funcionarios" class="rh-tab-content active">
<!-- Barra de Procurar e Filtros -->
<div class="glass-panel" style="padding: 1.5rem; margin-bottom: 1.5rem;">
    <form method="GET" style="display: flex; gap: 1rem; align-items: end;">
        <input type="hidden" name="page" value="rh">
        <div style="flex: 1;">
            <label class="form-label">Buscar Funcionário</label>
            <input type="text" name="search" class="form-input" placeholder="Buscar por Nome ou Setor..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <button type="submit" class="btn-primary">
            <i class="fa-solid fa-magnifying-glass"></i>
            Filtrar
        </button>
    </form>
</div>

<!-- Listagem por Setor -->
<?php foreach ($sectors_list as $sector => $users_in_sector): ?>
<div style="margin-bottom: 3rem;">
    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
        <div style="height: 2px; flex: 1; background: linear-gradient(90deg, transparent, var(--crm-purple), transparent);"></div>
        <h3 style="font-size: 0.75rem; font-weight: 900; color: var(--crm-purple); text-transform: uppercase; letter-spacing: 0.2em; padding: 0 1rem;">
            <?= htmlspecialchars($sector) ?>
        </h3>
        <div style="height: 2px; flex: 1; background: linear-gradient(90deg, transparent, var(--crm-purple), transparent);"></div>
    </div>
    
    <?php foreach ($users_in_sector as $usr): ?>
    <div class="glass-panel" style="margin-bottom: 1rem; border-left: 4px solid var(--crm-purple);">
        <div class="rh-employee-card-flex" style="display: flex; align-items: center; gap: 1.5rem;">
            
            <div class="rh-employee-avatar" style="width: 58px; height: 58px; border-radius: 1rem; overflow: hidden; border: 2px solid rgba(91, 33, 182, 0.2); flex-shrink: 0;">
                <?php if (!empty($usr['avatar_url'])): ?>
                    <img src="<?= htmlspecialchars($usr['avatar_url']) ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <div style="width: 100%; height: 100%; background: linear-gradient(135deg, rgba(91, 33, 182, 0.15), rgba(251, 191, 36, 0.1)); display: flex; align-items: center; justify-content: center; color: var(--crm-purple); font-weight: 900; font-size: 1.25rem;">
                        <?= strtoupper(substr($usr['name'], 0, 2)) ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="user-grid-info">
                <div>
                    <h4 style="font-weight: 900; color: var(--crm-black); font-size: 1.125rem;">
                        <?= htmlspecialchars($usr['name']) ?>
                        <?php if (!empty($usr['end_date']) && $usr['end_date'] <= date('Y-m-d') && $usr['end_date'] !== '0000-00-00'): ?>
                            <span style="font-size: 0.65rem; background: #ef4444; color: white; padding: 2px 6px; border-radius: 4px; margin-left: 8px; vertical-align: middle; text-transform: uppercase;">Não Faz Mais Parte da Equipe</span>
                        <?php endif; ?>
                    </h4>
                    <p style="font-size: 0.75rem; color: var(--text-soft); font-weight: 600;">
                        <i class="fa-regular fa-envelope"></i> <?= htmlspecialchars($usr['email']) ?>
                    </p>
                </div>
                
                <!-- Info Status Contrato Compacto -->
                <div>
                    <p style="font-size:0.625rem; font-weight:900; color:#94a3b8; text-transform:uppercase; margin-bottom:0.25rem;">Contrato</p>
                    <p style="font-size:0.875rem; font-weight:700; color: <?= empty($usr['contract_type']) ? '#ef4444' : 'var(--crm-purple)' ?>;">
                        <?= empty($usr['contract_type']) ? 'Não Configurado' : htmlspecialchars($usr['contract_type']) ?>
                    </p>
                    <?php if(!empty($usr['start_date'])): ?>
                        <p style="font-size:0.7rem; color:#64748b;">Desde <?= date('d/m/Y', strtotime($usr['start_date'])) ?></p>
                    <?php endif; ?>
                </div>

                <div>
                    <p style="font-size:0.625rem; font-weight:900; color:#94a3b8; text-transform:uppercase; margin-bottom:0.25rem;">Horários</p>
                    <p style="font-size:0.85rem; font-weight:700; color:#334155;">
                        <?= empty($usr['work_days']) ? 'Sem escala' : htmlspecialchars($usr['work_days']) ?><br>
                        <?= empty($usr['work_hours']) ? '' : htmlspecialchars($usr['work_hours']) ?>
                    </p>
                </div>
                
                <div style="display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center;">
                    <!-- Botões de Ação do RH -->
                    <?php if (!empty($usr['end_date']) && $usr['end_date'] <= date('Y-m-d') && $usr['end_date'] !== '0000-00-00'): ?>
                        <form method="POST" style="margin:0;" onsubmit="return confirm('Tem certeza que deseja REATIVAR este usuário? O contrato voltará a ser permanente e o status voltará a ser Ativo no sistema.');">
                            <input type="hidden" name="action" value="reactivate_user">
                            <input type="hidden" name="user_id" value="<?= $usr['id'] ?>">
                            <button type="submit" class="btn-icon" style="background:#10B981; color:#fff;" title="Reativar Funcionário">
                                <i class="fa-solid fa-user-check"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <button class="btn-icon rh-btn-contract" onclick="event.stopPropagation(); openContractModal('<?= $usr['id'] ?>')" title="Dados Contratuais e Admissão">
                        <i class="fa-solid fa-file-signature"></i>
                    </button>
                    <button class="btn-icon rh-btn-vacation" onclick="event.stopPropagation(); openVacationModal('<?= $usr['id'] ?>', '<?= htmlspecialchars(addslashes($usr['name'])) ?>')" title="Escala de Férias">
                        <i class="fa-solid fa-plane-departure"></i>
                    </button>
                    <button class="btn-icon rh-btn-cert" onclick="event.stopPropagation(); openCertModal('<?= $usr['id'] ?>', '<?= htmlspecialchars(addslashes($usr['name'])) ?>')" title="Atestados Médicos">
                        <i class="fa-solid fa-notes-medical"></i>
                    </button>
                    <button class="btn-icon rh-btn-notes" onclick="event.stopPropagation(); openNotesModal('<?= $usr['id'] ?>', '<?= htmlspecialchars(addslashes($usr['name'])) ?>')" title="Informações/Anotações">
                        <i class="fa-solid fa-address-card"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php if(empty($sectors_list)): ?>
    <div style="text-align:center;padding:4rem;color:#94a3b8;">
        <i class="fa-solid fa-users-slash" style="font-size:4rem;color:#e2e8f0;margin-bottom:1.5rem;display:block;"></i>
        Nenhum funcionário encontrado nos registros do sistema.
    </div>
<?php endif; ?>
</div><!-- Fim tab-funcionarios -->

<!-- ABA: RECADOS GERAL -->
<div id="tab-recados" class="rh-tab-content">
    <div class="glass-panel" style="margin-bottom: 2rem;">
        <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--crm-black); margin-bottom: 1.5rem;">
            <i class="fa-solid fa-bullhorn" style="color: var(--crm-purple);"></i>
            Publicar Novo Recado
        </h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_announcement">
            
            <div class="form-group">
                <label class="form-label">Mensagem do Recado</label>
                <textarea name="message" class="form-textarea" rows="4" placeholder="Escreva aqui o comunicado para todos os usuários..." required></textarea>
            </div>
            
            <div style="display: flex; gap: 1.5rem; align-items: flex-end;">
                <div class="form-group" style="flex: 1; margin-bottom: 0;">
                    <label class="form-label">Imagem Opcional (Banner)</label>
                    <input type="file" name="announcement_image" class="form-input" accept="image/*">
                </div>
                <button type="submit" class="btn-primary" style="height: 45px;">
                    <i class="fa-solid fa-paper-plane"></i>
                    Enviar para Todos
                </button>
            </div>
        </form>
    </div>

    <h3 style="font-size: 1.1rem; font-weight: 900; color: #334155; margin-bottom: 1rem;">Recados Enviados Recentemente</h3>
    
    <?php if (empty($all_announcements)): ?>
        <div style="text-align:center; padding:3rem; color:#64748b; background:#f8fafc; border-radius:1rem; border:1px solid #e2e8f0;">
            Nenhum recado enviado ainda.
        </div>
    <?php else: ?>
        <?php foreach ($all_announcements as $ann): ?>
            <div class="glass-panel" style="margin-bottom: 1.5rem; position: relative; border-left: 4px solid var(--crm-purple);">
                <div style="display: flex; gap: 1.5rem;">
                    <?php if ($ann['image_url']): ?>
                        <div style="width: 120px; height: 120px; border-radius: 0.75rem; overflow: hidden; flex-shrink: 0;">
                            <img src="<?= $ann['image_url'] ?>" style="width: 100%; height: 100%; object-fit: cover; cursor: pointer;" onclick="window.open(this.src)">
                        </div>
                    <?php endif; ?>
                    <div style="flex: 1;">
                        <div style="font-size: 0.75rem; color: var(--text-soft); margin-bottom: 0.5rem; font-weight: 700; display: flex; justify-content: space-between;">
                            <span>
                                <i class="fa-solid fa-clock"></i> <?= date('d/m/Y H:i', strtotime($ann['created_at'])) ?> 
                                &bull; <i class="fa-solid fa-user"></i> <?= htmlspecialchars($ann['created_by']) ?>
                            </span>
                            <span style="color: var(--crm-purple);"><i class="fa-solid fa-eye"></i> <?= $ann['views'] ?> visualizações</span>
                        </div>
                        <div style="color: var(--text-main); line-height: 1.6; white-space: pre-wrap; font-size: 0.95rem;"><?= htmlspecialchars($ann['message']) ?></div>
                    </div>
                </div>
                <div style="position: absolute; top: 1rem; right: 1rem; display: flex; gap: 0.5rem;">
                    <button type="button" class="btn-icon" style="background:#EEF2FF; color:#4F46E5; border:none;" onclick="openEditAnnModal('<?= $ann['id'] ?>', '<?= addslashes($ann['message']) ?>', '<?= $ann['image_url'] ?>')" title="Editar Recado">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <form method="POST" style="margin:0;" onsubmit="return confirm('Deseja excluir este recado permanentemente? ele deixará de aparecer para quem não viu.');">
                        <input type="hidden" name="action" value="delete_announcement">
                        <input type="hidden" name="announcement_id" value="<?= $ann['id'] ?>">
                        <button type="submit" style="background:none; border:none; cursor:pointer; color:#ef4444; font-size:1.1rem;"><i class="fa-solid fa-trash-can"></i></button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal de Edição de Recado -->
<div id="editAnnModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 600px; width: 100%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--crm-black);">Editar Recado</h3>
            <button onclick="document.getElementById('editAnnModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: var(--text-soft); font-size: 1.5rem;">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit_announcement">
            <input type="hidden" name="announcement_id" id="edit_ann_id">
            <input type="hidden" name="current_image" id="edit_ann_current_img">
            
            <div class="form-group">
                <label class="form-label">Mensagem do Recado</label>
                <textarea name="message" id="edit_ann_message" class="form-textarea" rows="4" required></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Trocar Imagem (Opcional)</label>
                <input type="file" name="announcement_image" class="form-input" accept="image/*">
                <p style="font-size:0.7rem; color:#64748b; margin-top:0.25rem;">Deixe em branco para manter a imagem atual.</p>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <button type="button" onclick="document.getElementById('editAnnModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Atualizar Recado</button>
            </div>
        </form>
    </div>
</div>


<!-- ─── MODAL DE CONTRATOS E ADMISSÃO ────────────────────────────────────────────────── -->
<div id="contractModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 600px; width: 100%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 id="c_modal_title" style="font-size: 1.25rem; font-weight: 900; color: var(--crm-black);">Dados Contratuais</h3>
            <button onclick="document.getElementById('contractModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: var(--text-soft); font-size: 1.5rem;">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="save_contract">
            <input type="hidden" name="user_id" id="c_user_id">
            
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                
                <div class="form-group">
                    <label class="form-label" style="display:flex; justify-content:space-between;">
                        Tipo de Contratação
                        <button type="button" onclick="addCustomContractType()" style="background:none;border:none;color:var(--crm-purple);cursor:pointer;font-weight:900;" title="Adicionar Novo Tipo"><i class="fa-solid fa-plus"></i></button>
                    </label>
                    <select name="contract_type" id="c_contract_type" class="form-select">
                        <option value="">-- Selecione --</option>
                        <option value="CLT Integral">CLT Integral</option>
                        <option value="PJ">PJ</option>
                        <option value="Estágio">Estágio</option>
                        <option value="Voluntário">Voluntário</option>
                        <option value="Jovem Aprendiz">Jovem Aprendiz</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" style="display:flex; justify-content:space-between;">
                        Cargo
                        <button type="button" onclick="addCustomCargo()" style="background:none;border:none;color:var(--crm-purple);cursor:pointer;font-weight:900;" title="Adicionar Novo Cargo"><i class="fa-solid fa-plus"></i></button>
                    </label>
                    <select name="role_name" id="c_role_name" class="form-select">
                        <option value="">-- Selecione --</option>
                        <?php foreach ($all_positions as $pos): ?>
                            <option value="<?= htmlspecialchars($pos['name']) ?>"><?= htmlspecialchars($pos['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Salário Bruto (R$)</label>
                    <input type="number" step="0.01" name="salary" id="c_salary" class="form-input" placeholder="0.00">
                </div>

                <div class="form-group">
                    <label class="form-label" style="display:flex; justify-content:space-between;">
                        Sexo / Gênero
                        <button type="button" onclick="addCustomGender()" style="background:none;border:none;color:var(--crm-purple);cursor:pointer;font-weight:900;" title="Adicionar Novo Gênero"><i class="fa-solid fa-plus"></i></button>
                    </label>
                    <select name="gender" id="c_gender" class="form-select">
                        <option value="">-- Selecione --</option>
                        <option value="Masculino">Masculino</option>
                        <option value="Feminino">Feminino</option>
                        <option value="Outro">Outro / Preferiu não dizer</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Data de Nascimento (Aniversário)</label>
                    <input type="date" name="birth_date" id="c_birth_date" class="form-input">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                    <div class="form-group">
                        <label class="form-label">Usa Vale Transporte?</label>
                        <select name="use_transport" id="c_use_transport" class="form-select" onchange="toggleTransportValue()">
                            <option value="Não">Não</option>
                            <option value="Sim">Sim</option>
                        </select>
                    </div>
                    <div class="form-group" id="div_transport_value" style="display:none;">
                        <label class="form-label">Valor (R$)</label>
                        <input type="number" step="0.01" name="transport_value" id="c_transport_value" class="form-input" placeholder="0.00">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label" style="display:flex; justify-content:space-between;">
                        Dias Trabalhados
                        <button type="button" onclick="addCustomWorkDays()" style="background:none;border:none;color:var(--crm-purple);cursor:pointer;font-weight:900;" title="Adicionar Nova Escala"><i class="fa-solid fa-plus"></i></button>
                    </label>
                    <select name="work_days" id="c_work_days" class="form-select">
                        <option value="">-- Selecione --</option>
                        <option value="Segunda a Sexta">Segunda a Sexta</option>
                        <option value="Segunda a Sábado">Segunda a Sábado</option>
                        <option value="Escala 12x36">Escala 12x36</option>
                        <option value="Escala 6x1">Escala 6x1</option>
                        <option value="Escala 5x2">Escala 5x2</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" style="display:flex; justify-content:space-between;">
                        Horário de Trabalho
                        <button type="button" onclick="addCustomWorkHours()" style="background:none;border:none;color:var(--crm-purple);cursor:pointer;font-weight:900;" title="Adicionar Novo Horário"><i class="fa-solid fa-plus"></i></button>
                    </label>
                    <select name="work_hours" id="c_work_hours" class="form-select">
                        <option value="">-- Selecione --</option>
                        <option value="08:00 às 17:00">08:00 às 17:00</option>
                        <option value="09:00 às 18:00">09:00 às 18:00</option>
                        <option value="07:00 às 16:00">07:00 às 16:00</option>
                        <option value="Comercial Padrão">Comercial Padrão</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Data de Início/Admissão</label>
                    <input type="date" name="start_date" id="c_start_date" class="form-input">
                </div>

                <div class="form-group">
                    <label class="form-label">Data de Término <span style="font-weight:400;text-transform:none;font-size:.7rem;">(opcional/demissão)</span></label>
                    <input type="date" name="end_date" id="c_end_date" class="form-input">
                </div>
                
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <button type="button" onclick="document.getElementById('contractModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>


<!-- ─── MODAL DE ESCALAS DE FÉRIAS ────────────────────────────────────────────────── -->
<div id="vacationModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 800px; width: 100%; max-height:85vh; overflow-y:auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 id="v_modal_title" style="font-size: 1.25rem; font-weight: 900; color: var(--crm-black);">Gestão de Férias</h3>
            <button onclick="document.getElementById('vacationModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: var(--text-soft); font-size: 1.5rem;">&times;</button>
        </div>
        
        <!-- Formulário para Criar/Agendar Novas Férias -->
        <form method="POST" style="background:#f8fafc; padding:1.5rem; border-radius:1rem; border:1px solid #e2e8f0; margin-bottom: 2rem;">
            <h4 style="font-size:.9rem;font-weight:900;color:var(--crm-purple);margin-bottom:1rem;">Agendar Novo Período</h4>
            <input type="hidden" name="action" value="add_vacation">
            <input type="hidden" name="user_id" id="v_user_id">
            
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem;">
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Ano Ref.</label>
                    <input type="number" name="reference_year" class="form-input" value="<?= date('Y') ?>" required>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Início</label>
                    <input type="date" name="start_date" class="form-input" required>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Fim</label>
                    <input type="date" name="end_date" class="form-input" required>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Data Limite CLT</label>
                    <input type="date" name="limit_date" class="form-input" required title="Prazo máximo para concessão das férias antes da multa por atraso." style="border-left:3px solid #f59e0b;">
                </div>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem;">
                <select name="status" class="form-select" style="width:200px;">
                    <option value="Programada">Programada</option>
                    <option value="Concluída">Concluída</option>
                    <option value="Cancelada">Cancelada</option>
                </select>
                <button type="submit" class="btn-primary" style="background:#D97706;"><i class="fa-solid fa-plus"></i> Inserir Férias</button>
            </div>
        </form>

        <h4 style="font-size:1rem;font-weight:900;margin-bottom:0.5rem;">Histórico de Férias do Funcionário</h4>
        <div class="table-responsive">
        <table class="table-modal">
            <thead>
                <tr>
                    <th>Ref.</th>
                    <th>Período</th>
                    <th>Data Limite</th>
                    <th>Status</th>
                    <th style="text-align:right;">Excluir</th>
                </tr>
            </thead>
            <tbody id="vacations_table_body">
                <!-- Javascript will inject -->
            </tbody>
        </table>
        </div>
    </div>
</div>


<!-- ─── MODAL DE ATESTADOS MÉDICOS ────────────────────────────────────────────────── -->
<div id="certModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 800px; width: 100%; max-height:85vh; overflow-y:auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 id="cert_modal_title" style="font-size: 1.25rem; font-weight: 900; color: var(--crm-black);">Atestados Médicos</h3>
            <button onclick="document.getElementById('certModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: #64748b; font-size: 1.5rem;">&times;</button>
        </div>
        
        <!-- Formulário para Inserir Atestado -->
        <form method="POST" enctype="multipart/form-data" style="background:#f8fafc; padding:1.5rem; border-radius:1rem; border:1px solid #e2e8f0; margin-bottom: 2rem;">
            <h4 style="font-size:.9rem;font-weight:900;color:#16A34A;margin-bottom:1rem;">Cadastrar Novo Atestado</h4>
            <input type="hidden" name="action" value="add_certificate">
            <input type="hidden" name="user_id" id="cert_user_id">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Data de Emissão *</label>
                    <input type="date" name="issue_date" class="form-input" required>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">Dias de Afastamento *</label>
                    <input type="number" name="days_off" min="1" class="form-input" placeholder="ex: 3" required>
                </div>
                <div class="form-group" style="grid-column:span 2; margin-bottom:0">
                    <label class="form-label">Causa / Motivo / CID</label>
                    <input type="text" name="reason" class="form-input" placeholder="Descrição ou CID do atestado...">
                </div>
                <div class="form-group" style="grid-column: span 2; margin-bottom:0;">
                    <label class="form-label">Anexar Atestado (Foto/PDF)</label>
                    <input type="file" name="certificate_file" class="form-input" accept="image/*,.pdf" style="background:white;">
                </div>
            </div>
            
            <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="submit" class="btn-primary" style="background:#16A34A;"><i class="fa-solid fa-notes-medical"></i> Salvar Atestado</button>
            </div>
        </form>

        <h4 style="font-size:1rem;font-weight:900;margin-bottom:0.5rem;">Histórico de Atestados</h4>
        <table class="table-modal">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Dias Dispensa</th>
                    <th>Motivo/CID</th>
                    <th>Anexo</th>
                    <th style="text-align:right;">Excluir</th>
                </tr>
            </thead>
            <tbody id="certs_table_body">
                <!-- Javascript will inject -->
            </tbody>
        </table>
    </div>
</div>

<!-- ─── MODAL DE INFORMAÇÕES E ANOTAÇÕES ────────────────────────────────────────────────── -->
<div id="notesModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 600px; width: 100%; max-height:85vh; overflow-y:auto; background:#f8fafc;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 id="n_modal_title" style="font-size: 1.25rem; font-weight: 900; color: var(--crm-black);">Informações</h3>
            <button onclick="document.getElementById('notesModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: #64748b; font-size: 1.5rem;">&times;</button>
        </div>
        
        <!-- Formulário para Inserir Anotação -->
        <form method="POST" style="margin-bottom: 2rem;">
            <input type="hidden" name="action" value="add_note">
            <input type="hidden" name="user_id" id="n_user_id">
            
            <div class="form-group">
                <label class="form-label" style="display:none;">Nova Anotação</label>
                <textarea name="note_text" class="form-textarea" placeholder="Escreva aqui observações, advertências ou elogios do funcionário..." style="min-height:120px;" required></textarea>
            </div>
            
            <div style="display: flex; justify-content: flex-end;">
                <button type="submit" class="btn-primary" style="background:#475569; border-color:#475569;"><i class="fa-solid fa-floppy-disk"></i> Gravar Informação</button>
            </div>
        </form>

        <h4 style="font-size:.9rem;font-weight:900;color:#64748b;margin-bottom:1rem;text-transform:uppercase;">Histórico de Informações</h4>
        <div id="notes_content_body">
            <!-- Javascript will inject -->
        </div>
    </div>
</div>
