<?php
require_once 'access_control.php';

// Verificação de Acesso (Módulo liberado via configuração de usuário)
if (function_exists('canAccess') && !canAccess('tecnologia')) {
    echo '<div style="text-align:center;padding:4rem;color:#64748b;"><i class="fa-solid fa-lock" style="font-size:3rem;color:#e2e8f0;"></i><p style="margin-top:1rem;font-weight:700;">Acesso restrito. Módulo não liberado para o seu usuário.</p></div>';
    return;
}

// Função auxiliar para cores
if (!function_exists('hexToRgb')) {
    function hexToRgb($hex) {
        $hex = str_replace("#", "", $hex);
        if(strlen($hex) == 3) {
            $r = hexdec(substr($hex,0,1).substr($hex,0,1));
            $g = hexdec(substr($hex,1,1).substr($hex,1,1));
            $b = hexdec(substr($hex,2,1).substr($hex,2,1));
        } else {
            $r = hexdec(substr($hex,0,2));
            $g = hexdec(substr($hex,2,2));
            $b = hexdec(substr($hex,4,2));
        }
        return "$r, $g, $b";
    }
}

// Assets de terceiros para o Editor
$editor_assets = '
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
';

// Handler de Ações POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Ações para Câmeras
    if ($action === 'add_camera') {
        $stmt = $pdo->prepare("INSERT INTO tech_cameras (name, quantity, ip_address, doc) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['name'], $_POST['quantity'], $_POST['ip_address'], $_POST['doc']]);
        header('Location: ?page=tecnologia&tab=cameras&success=1'); exit;
    }
    if ($action === 'edit_camera') {
        $stmt = $pdo->prepare("UPDATE tech_cameras SET name = ?, quantity = ?, ip_address = ?, doc = ? WHERE id = ?");
        $stmt->execute([$_POST['name'], $_POST['quantity'], $_POST['ip_address'], $_POST['doc'], $_POST['id']]);
        header('Location: ?page=tecnologia&tab=cameras&success=2'); exit;
    }
    if ($action === 'delete_camera') {
        $stmt = $pdo->prepare("DELETE FROM tech_cameras WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        header('Location: ?page=tecnologia&tab=cameras&success=3'); exit;
    }

    // Ações para Acessos Remotos
    if ($action === 'add_remote') {
        $stmt = $pdo->prepare("INSERT INTO tech_remote_access (user_id, pc_password, email_password, pc_name, observations) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['user_id'], $_POST['pc_password'], $_POST['email_password'], $_POST['pc_name'], $_POST['observations']]);
        header('Location: ?page=tecnologia&tab=remotos&success=4'); exit;
    }
    if ($action === 'edit_remote') {
        $stmt = $pdo->prepare("UPDATE tech_remote_access SET user_id = ?, pc_password = ?, email_password = ?, pc_name = ?, observations = ? WHERE id = ?");
        $stmt->execute([$_POST['user_id'], $_POST['pc_password'], $_POST['email_password'], $_POST['pc_name'], $_POST['observations'], $_POST['id']]);
        header('Location: ?page=tecnologia&tab=remotos&success=5'); exit;
    }
    if ($action === 'delete_remote') {
        $stmt = $pdo->prepare("DELETE FROM tech_remote_access WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        header('Location: ?page=tecnologia&tab=remotos&success=6'); exit;
    }

    // Ações para E-mails
    if ($action === 'add_email') {
        $stmt = $pdo->prepare("INSERT INTO tech_emails (email, password, type, remote_user_id, usage_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['email'], $_POST['password'], $_POST['type'], $_POST['remote_user_id'], $_POST['usage_date']]);
        header('Location: ?page=tecnologia&tab=emails&success=7'); exit;
    }
    if ($action === 'edit_email') {
        $stmt = $pdo->prepare("UPDATE tech_emails SET email = ?, password = ?, type = ?, remote_user_id = ?, usage_date = ? WHERE id = ?");
        $stmt->execute([$_POST['email'], $_POST['password'], $_POST['type'], $_POST['remote_user_id'], $_POST['usage_date'], $_POST['id']]);
        header('Location: ?page=tecnologia&tab=emails&success=8'); exit;
    }
    if ($action === 'delete_email') {
        $stmt = $pdo->prepare("DELETE FROM tech_emails WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        header('Location: ?page=tecnologia&tab=emails&success=9'); exit;
    }

    // Ações para Anotações (Seções)
    if ($action === 'add_note_section') {
        $stmt = $pdo->prepare("INSERT INTO tech_note_sections (name, color, icon) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['name'], $_POST['color'], $_POST['icon']]);
        header('Location: ?page=tecnologia&tab=anotacoes&success=10'); exit;
    }
    if ($action === 'edit_note_section') {
        $stmt = $pdo->prepare("UPDATE tech_note_sections SET name = ?, color = ?, icon = ? WHERE id = ?");
        $stmt->execute([$_POST['name'], $_POST['color'], $_POST['icon'], $_POST['id']]);
        header('Location: ?page=tecnologia&tab=anotacoes&success=11'); exit;
    }
    if ($action === 'delete_note_section') {
        $stmt = $pdo->prepare("DELETE FROM tech_note_sections WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        header('Location: ?page=tecnologia&tab=anotacoes&success=12'); exit;
    }

    // Ações para Anotações (Páginas)
    if ($action === 'save_note') {
        if (!empty($_POST['id'])) {
            $stmt = $pdo->prepare("UPDATE tech_notes SET title = ?, content = ? WHERE id = ?");
            $stmt->execute([$_POST['title'], $_POST['content'], $_POST['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO tech_notes (section_id, title, content) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['section_id'], $_POST['title'], $_POST['content']]);
        }
        header('Location: ?page=tecnologia&tab=anotacoes&section_id='.$_POST['section_id'].'&success=13'); exit;
    }
    if ($action === 'delete_note') {
        $stmt = $pdo->prepare("DELETE FROM tech_notes WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        header('Location: ?page=tecnologia&tab=anotacoes&section_id='.$_POST['section_id'].'&success=14'); exit;
    }
}

// Busca de dados
$cameras = $pdo->query("SELECT * FROM tech_cameras ORDER BY name")->fetchAll();
$remotes = $pdo->query("SELECT tr.*, u.name as user_name, u.avatar_url, u.email as user_email FROM tech_remote_access tr LEFT JOIN users u ON tr.user_id = u.id ORDER BY u.name")->fetchAll();
$emails = $pdo->query("SELECT te.*, u.name as user_name FROM tech_emails te LEFT JOIN users u ON te.remote_user_id = u.id ORDER BY te.email")->fetchAll();
$all_users = $pdo->query("SELECT id, name, avatar_url FROM users ORDER BY name")->fetchAll();

// Dados de Anotações
$note_sections = $pdo->query("SELECT * FROM tech_note_sections ORDER BY name")->fetchAll();
$active_section_id = $_GET['section_id'] ?? ($note_sections[0]['id'] ?? null);
$notes = [];
if ($active_section_id) {
    $stmt = $pdo->prepare("SELECT * FROM tech_notes WHERE section_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$active_section_id]);
    $notes = $stmt->fetchAll();
}
$active_note_id = $_GET['note_id'] ?? null;
$active_note = null;
if ($active_note_id) {
    $stmt = $pdo->prepare("SELECT * FROM tech_notes WHERE id = ?");
    $stmt->execute([$active_note_id]);
    $active_note = $stmt->fetch();
}

$activeTab = $_GET['tab'] ?? 'cameras';
$tech_pass = $pdo->query("SELECT tech_password FROM company_settings WHERE id = 1")->fetchColumn() ?: '1968';
?>

<div class="page-header">
    <div class="page-header-info">
        <div class="page-header-icon">
            <i class="fa-solid fa-laptop-code"></i>
        </div>
        <div class="page-header-text">
            <h2>Gestão de TI</h2>
            <p>Monitoramento de ativos tecnológicos e infraestrutura digital.</p>
        </div>
    </div>
</div>

<!-- Alertas -->
<?php if (isset($_GET['success'])): ?>
    <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #10B981; padding: 1rem; border-radius: 0.75rem; margin-bottom: 2rem; font-weight: 600;">
        <i class="fa-solid fa-circle-check"></i>
        Ação realizada com sucesso!
    </div>
<?php endif; ?>

<?= $editor_assets ?>

<!-- Sistema de Abas -->
<div class="tabs-container" style="display: flex; gap: 1rem; border-bottom: 2px solid #e2e8f0; margin-bottom: 2rem; padding-bottom: 0.5rem; flex-wrap: wrap;">
    <button onclick="unlockTab('cameras')" id="tab-btn-cameras" class="tab-btn <?= $activeTab == 'cameras' ? 'active' : '' ?>">
        <i class="fa-solid fa-video"></i> Cameras Arrastão
    </button>
    <button onclick="unlockTab('remotos')" id="tab-btn-remotos" class="tab-btn <?= $activeTab == 'remotos' ? 'active' : '' ?>">
        <i class="fa-solid fa-desktop"></i> Acessos Remotos
    </button>
    <button onclick="unlockTab('emails')" id="tab-btn-emails" class="tab-btn <?= $activeTab == 'emails' ? 'active' : '' ?>">
        <i class="fa-solid fa-envelope"></i> E-mails
    </button>
    <button onclick="unlockTab('anotacoes')" id="tab-btn-anotacoes" class="tab-btn <?= $activeTab == 'anotacoes' ? 'active' : '' ?>">
        <i class="fa-solid fa-book"></i> Anotações
    </button>
</div>

<style>
    .tab-btn { background: none; border: none; font-weight: 700; color: #64748b; cursor: pointer; padding: 0.5rem 1rem; border-radius: 0.5rem; transition: all 0.3s; display: flex; align-items: center; gap: 0.5rem; }
    .tab-btn i { font-size: 1rem; }
    .tab-btn.active { color: var(--crm-purple); background: rgba(91, 33, 182, 0.1); }
    .tech-content { display: none; }
    .tech-content.active { display: block; animation: fadeIn 0.3s ease-out; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    
    .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }

    /* Notes System Styles */
    .notes-layout { display: flex; gap: 0; height: calc(100vh - 220px); min-height: 600px; background: #fff; border-radius: 1rem; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
    .notes-sections { width: 220px; background: #f8fafc; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; overflow-y: auto; height: 100%; }
    .notes-pages { width: 280px; background: #fff; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; overflow-y: auto; height: 100%; }
    .notes-editor { flex: 1; display: flex; flex-direction: column; background: #fff; overflow: hidden; min-width: 0; min-height: 0; height: 100%; }
    
    .notes-header { padding: 1rem; border-bottom: 1px solid #e2e8f0; font-weight: 800; font-size: 0.875rem; color: #64748b; display: flex; justify-content: space-between; align-items: center; }
    .notes-list { flex: 1; overflow-y: auto; padding: 0.5rem; }
    
    .note-section-item { padding: 0.75rem 1rem; border-radius: 0.5rem; cursor: pointer; transition: all 0.2s; margin-bottom: 0.25rem; display: flex; align-items: center; gap: 0.75rem; font-weight: 600; color: #475569; position: relative; }
    .note-section-item:hover { background: #f1f5f9; }
    .note-section-item.active { background: #eff6ff; color: #2563eb; }
    .note-section-item.active::before { content: ''; position: absolute; left: 0; top: 0.5rem; bottom: 0.5rem; width: 4px; background: currentColor; border-radius: 0 4px 4px 0; }
    
    .note-page-item { padding: 1rem; border-radius: 0.5rem; cursor: pointer; transition: all 0.2s; margin-bottom: 0.5rem; border: 1px solid transparent; }
    .note-page-item:hover { background: #f8fafc; border-color: #e2e8f0; }
    .note-page-item.active { background: #fff; border-color: #e2e8f0; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
    .note-page-title { font-weight: 700; color: #1e293b; margin-bottom: 0.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .note-page-preview { font-size: 0.75rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    
    .editor-container { flex: 1; display: flex; flex-direction: column; padding: 2rem 3rem; overflow-y: auto; min-height: 0; height: 100%; }
    .editor-title { border: none; font-size: 2.2rem; font-weight: 900; color: #1e293b; margin-bottom: 1.5rem; width: 100%; outline: none; background: transparent; flex-shrink: 0; }
    .editor-title::placeholder { color: #cbd5e1; font-weight: 800; }
    .editor-content { border: none; flex: 1; display: flex; flex-direction: column; font-size: 1.05rem; line-height: 1.7; color: #334155; width: 100%; outline: none; min-height: 0; }
    
    /* Quill Customization */
    .ql-toolbar.ql-snow { border: none !important; border-bottom: 1px solid #f1f5f9 !important; background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); padding: 0.75rem 1.5rem !important; position: sticky; top: 0; z-index: 10; display: flex; flex-wrap: wrap; gap: 0.5rem; flex-shrink: 0; }
    .ql-container.ql-snow { border: none !important; font-family: 'Inter', sans-serif !important; font-size: 1.05rem !important; flex: 1; display: flex; flex-direction: column; min-height: 0; overflow-y: auto; }
    .ql-editor { padding: 1rem 0 3rem 0 !important; flex: 1; min-height: 400px; height: 100%; }
    .ql-editor.ql-blank::before { left: 0 !important; font-style: normal !important; color: #cbd5e1 !important; font-weight: 500; }

    /* Emoji Picker Simple Style */
    .emoji-drawer { display: none; position: absolute; bottom: 100%; right: 0; background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); padding: 1rem; grid-template-columns: repeat(6, 1fr); gap: 0.5rem; z-index: 100; max-height: 250px; overflow-y: auto; width: 250px; }
    .emoji-drawer.active { display: grid; }
    .emoji-item { cursor: pointer; font-size: 1.5rem; padding: 0.25rem; border-radius: 6px; transition: background 0.2s; text-align: center; }
    .emoji-item:hover { background: #f1f5f9; }

    .sticker-drawer { display: none; position: absolute; bottom: 100%; right: 40px; background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); padding: 1rem; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; z-index: 100; max-height: 300px; overflow-y: auto; width: 280px; }
    .sticker-drawer.active { display: grid; }
    .sticker-item { cursor: pointer; border-radius: 8px; transition: transform 0.2s; background: #f8fafc; padding: 0.5rem; display: flex; align-items: center; justify-content: center; }
    .sticker-item:hover { transform: scale(1.1); background: #f1f5f9; }
    .sticker-item img { max-width: 100%; height: auto; }

    @media (max-width: 1024px) {
        .notes-layout { flex-direction: column; height: calc(100vh - 120px); min-height: auto; }
        .notes-sections, .notes-pages { width: 100%; border-right: none; border-bottom: 1px solid #e2e8f0; flex-shrink: 0; max-height: 150px; }
        .notes-editor { flex: 1; }
        .editor-container { padding: 1.5rem; }
        .editor-title { font-size: 1.5rem; }
        .hide-mobile { display: none; }
    }
</style>

<!-- ABA 1: CAMERAS -->
<div id="content-cameras" class="tech-content <?= $activeTab == 'cameras' ? 'active' : '' ?>">
    <div style="background: #fff; padding: 1.5rem; border-radius: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);">
        <div style="display: flex; justify-content: space-between; margin-bottom: 2rem; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <div>
                <h3 style="font-weight: 800; color: #1e293b; margin-bottom: 0.25rem;">Monitoramento Arrastão</h3>
                <p style="font-size: 0.875rem; color: #64748b;">Gestão de câmeras e endereçamento IP da rede.</p>
            </div>
            <div style="display: flex; gap: 1rem; flex: 1; max-width: 600px; justify-content: flex-end;">
                <div style="flex: 1; max-width: 300px; position: relative;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.875rem;"></i>
                    <input type="text" id="search-cameras" class="form-input" placeholder="Filtrar câmeras..." style="padding-left: 2.5rem; border-radius: 0.75rem; background: #f8fafc;" onkeyup="filterTable('search-cameras', 'table-cameras')">
                </div>
                <button class="btn-primary" onclick="openCameraModal()" style="border-radius: 0.75rem; padding: 0.6rem 1.25rem; font-weight: 700;">
                    <i class="fa-solid fa-plus"></i> <span class="hide-mobile">Nova Câmera</span>
                </button>
            </div>
        </div>
        
        <div class="table-responsive">
            <table id="table-cameras" style="border-collapse: separate; border-spacing: 0 0.5rem; margin-top: -0.5rem;">
                <thead>
                    <tr style="background: none;">
                        <th style="background: #f8fafc; border-radius: 0.5rem 0 0 0.5rem; padding: 1rem;">NOME DA CÂMERA</th>
                        <th style="background: #f8fafc; padding: 1rem;">IP ADDRESS</th>
                        <th style="background: #f8fafc; padding: 1rem;">DOC</th>
                        <th style="background: #f8fafc; padding: 1rem;">QUANTIDADE</th>
                        <th style="background: #f8fafc; border-radius: 0 0.5rem 0.5rem 0; padding: 1rem; text-align:right;">AÇÕES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cameras as $cam): ?>
                    <tr style="background: #fff; transition: all 0.2s;">
                        <td style="padding: 1.25rem 1rem; border-bottom: 1px solid #f1f5f9; font-weight: 700; color: #1e293b;"><?= htmlspecialchars($cam['name']) ?></td>
                        <td style="padding: 1.25rem 1rem; border-bottom: 1px solid #f1f5f9;"><span style="background: #e0f2fe; color: #0369a1; padding: 0.35rem 0.75rem; border-radius: 0.5rem; font-weight: 700; font-family: monospace; font-size: 0.85rem;"><?= htmlspecialchars($cam['ip_address']) ?></span></td>
                        <td style="padding: 1.25rem 1rem; border-bottom: 1px solid #f1f5f9; color: #64748b; font-size: 0.875rem;"><?= htmlspecialchars($cam['doc'] ?? '-') ?></td>
                        <td style="padding: 1.25rem 1rem; border-bottom: 1px solid #f1f5f9;"><span style="font-weight: 800; color: #1e293b;"><?= $cam['quantity'] ?></span> <small style="color: #94a3b8;">un.</small></td>
                        <td style="padding: 1.25rem 1rem; border-bottom: 1px solid #f1f5f9; text-align:right;">
                            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                <button class="btn-icon" style="background: #f1f5f9; width: 32px; height: 32px;" onclick='openCameraModal(<?= json_encode($cam) ?>)' title="Editar"><i class="fa-solid fa-pen-to-square" style="font-size: 0.85rem;"></i></button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Deseja excluir esta câmera?')">
                                    <input type="hidden" name="action" value="delete_camera">
                                    <input type="hidden" name="id" value="<?= $cam['id'] ?>">
                                    <button type="submit" class="btn-icon" style="background: #fef2f2; color:#ef4444; width: 32px; height: 32px; border: 1px solid #fee2e2;"><i class="fa-solid fa-trash-can" style="font-size: 0.85rem;"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($cameras)): ?>
                    <tr><td colspan="5" style="text-align:center;padding:4rem;color:#94a3b8;"><i class="fa-solid fa-video-slash" style="font-size: 3rem; opacity: 0.1; margin-bottom: 1rem; display: block;"></i> Nenhuma câmera cadastrada.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ABA 2: ACESSOS REMOTOS -->
<div id="content-remotos" class="tech-content <?= $activeTab == 'remotos' ? 'active' : '' ?>">
    <div style="background: #fff; padding: 1.5rem; border-radius: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);">
        <div style="display: flex; justify-content: space-between; margin-bottom: 2rem; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <div>
                <h3 style="font-weight: 800; color: #1e293b; margin-bottom: 0.25rem;">Acessos Remotos</h3>
                <p style="font-size: 0.875rem; color: #64748b;">Credenciais e senhas de acesso às estações de trabalho.</p>
            </div>
            <div style="display: flex; gap: 1rem; flex: 1; max-width: 600px; justify-content: flex-end;">
                <div style="flex: 1; max-width: 300px; position: relative;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.875rem;"></i>
                    <input type="text" id="search-remotos" class="form-input" placeholder="Filtrar acessos..." style="padding-left: 2.5rem; border-radius: 0.75rem; background: #f8fafc;" onkeyup="filterTable('search-remotos', 'table-remotos')">
                </div>
                <button class="btn-primary" onclick="openRemoteModal()" style="border-radius: 0.75rem; padding: 0.6rem 1.25rem; font-weight: 700;">
                    <i class="fa-solid fa-plus"></i> <span class="hide-mobile">Novo Acesso</span>
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table id="table-remotos" style="border-collapse: separate; border-spacing: 0 0.5rem; margin-top: -0.5rem;">
                <thead>
                    <tr style="background: none;">
                        <th style="background: #f8fafc; border-radius: 0.5rem 0 0 0.5rem; padding: 1rem;">USUÁRIO</th>
                        <th style="background: #f8fafc; padding: 1rem;">LOGIN / EMAIL</th>
                        <th style="background: #f8fafc; padding: 1rem;">SENHA PC</th>
                        <th style="background: #f8fafc; padding: 1rem;">SENHA EMAIL</th>
                        <th style="background: #f8fafc; padding: 1rem;">REDE / PC</th>
                        <th style="background: #f8fafc; border-radius: 0 0.5rem 0.5rem 0; padding: 1rem; text-align:right;">AÇÕES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($remotes as $rem): ?>
                    <tr style="background: #fff; transition: all 0.2s;">
                        <td style="padding: 1.25rem 1rem; border-bottom: 1px solid #f1f5f9;">
                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                <?php if ($rem['avatar_url']): ?>
                                    <img src="<?= htmlspecialchars($rem['avatar_url']) ?>" style="width: 36px; height: 36px; border-radius: 10px; object-fit: cover; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                <?php else: ?>
                                    <div style="width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, var(--crm-purple), #9333ea); color: white; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 800;">
                                        <?= strtoupper(substr($rem['user_name'], 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <div style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($rem['user_name']) ?></div>
                            </div>
                        </td>
                        <td style="padding: 1.25rem 1rem; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem; color: #64748b;"><?= htmlspecialchars($rem['user_email']) ?></td>
                        <td style="padding: 1.25rem 1rem; border-bottom: 1px solid #f1f5f9;">
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <code style="background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 4px; color: #475569; font-weight: 600;"><?= htmlspecialchars($rem['pc_password'] ?? '') ?></code>
                                <?php if(!empty($rem['pc_password'])): ?>
                                <button class="btn-icon" style="padding:0; min-width:auto; width:28px; height:28px; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px;" onclick="copyText(this, '<?= htmlspecialchars(addslashes($rem['pc_password'])) ?>')" title="Copiar"><i class="fa-regular fa-copy" style="font-size: 0.75rem;"></i></button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="padding: 1.25rem 1rem; border-bottom: 1px solid #f1f5f9;">
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <code style="background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 4px; color: #475569; font-weight: 600;"><?= htmlspecialchars($rem['email_password'] ?? '') ?></code>
                                <?php if(!empty($rem['email_password'])): ?>
                                <button class="btn-icon" style="padding:0; min-width:auto; width:28px; height:28px; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px;" onclick="copyText(this, '<?= htmlspecialchars(addslashes($rem['email_password'])) ?>')" title="Copiar"><i class="fa-regular fa-copy" style="font-size: 0.75rem;"></i></button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="padding: 1.25rem 1rem; border-bottom: 1px solid #f1f5f9;">
                            <div style="display:flex; align-items:center; gap:0.5rem; font-weight: 800; color: var(--crm-purple);">
                                <span><?= htmlspecialchars($rem['pc_name'] ?? '') ?></span>
                                <?php if(!empty($rem['pc_name'])): ?>
                                <button class="btn-icon" style="padding:0; min-width:auto; width:28px; height:28px; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px;" onclick="copyText(this, '<?= htmlspecialchars(addslashes($rem['pc_name'])) ?>')" title="Copiar"><i class="fa-regular fa-copy" style="font-size: 0.75rem;"></i></button>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td style="padding: 1.25rem 1rem; border-bottom: 1px solid #f1f5f9; text-align:right;">
                            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                <button class="btn-icon" style="background: #f1f5f9; width: 32px; height: 32px;" onclick='openRemoteModal(<?= json_encode($rem) ?>)' title="Editar"><i class="fa-solid fa-pen-to-square" style="font-size: 0.85rem;"></i></button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Deseja excluir este acesso?')">
                                    <input type="hidden" name="action" value="delete_remote">
                                    <input type="hidden" name="id" value="<?= $rem['id'] ?>">
                                    <button type="submit" class="btn-icon" style="background: #fef2f2; color:#ef4444; width: 32px; height: 32px; border: 1px solid #fee2e2;"><i class="fa-solid fa-trash-can" style="font-size: 0.85rem;"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($remotes)): ?>
                    <tr><td colspan="6" style="text-align:center;padding:4rem;color:#94a3b8;"><i class="fa-solid fa-user-slash" style="font-size: 3rem; opacity: 0.1; margin-bottom: 1rem; display: block;"></i> Nenhum acesso remoto cadastrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ABA 3: EMAILS -->
<div id="content-emails" class="tech-content <?= $activeTab == 'emails' ? 'active' : '' ?>">
    <div style="background: #fff; padding: 1.5rem; border-radius: 1rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05);">
        <div style="display: flex; justify-content: space-between; margin-bottom: 2rem; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <div>
                <h3 style="font-weight: 800; color: #1e293b; margin-bottom: 0.25rem;">Contas de E-mail</h3>
                <p style="font-size: 0.875rem; color: #64748b;">Gestão de contas corporativas e provedores.</p>
            </div>
            <div style="display: flex; gap: 1rem; flex: 1; max-width: 600px; justify-content: flex-end;">
                <div style="flex: 1; max-width: 300px; position: relative;">
                    <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.875rem;"></i>
                    <input type="text" id="search-emails" class="form-input" placeholder="Filtrar e-mails..." style="padding-left: 2.5rem; border-radius: 0.75rem; background: #f8fafc;" onkeyup="filterTable('search-emails', 'table-emails')">
                </div>
                <button class="btn-primary" onclick="openEmailModal()" style="border-radius: 0.75rem; padding: 0.6rem 1.25rem; font-weight: 700;">
                    <i class="fa-solid fa-plus"></i> <span class="hide-mobile">Novo E-mail</span>
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table id="table-emails" style="border-collapse: separate; border-spacing: 0 0.5rem; margin-top: -0.5rem;">
                <thead>
                    <tr style="background: none;">
                        <th style="background: #f8fafc; border-radius: 0.5rem 0 0 0.5rem; padding: 1rem;">E-MAIL CORPORATIVO</th>
                        <th style="background: #f8fafc; padding: 1rem;">SENHA</th>
                        <th style="background: #f8fafc; padding: 1rem;">PROVEDOR</th>
                        <th style="background: #f8fafc; padding: 1rem;">USUÁRIO RESP.</th>
                        <th style="background: #f8fafc; padding: 1rem;">ÚLTIMO USO</th>
                        <th style="background: #f8fafc; border-radius: 0 0.5rem 0.5rem 0; padding: 1rem; text-align:right;">AÇÕES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($emails as $em): ?>
                    <tr style="background: #fff; transition: all 0.2s;">
                        <td style="padding: 1.25rem 1rem; border-bottom: 1px solid #f1f5f9; font-weight: 700; color: var(--crm-purple);"><?= htmlspecialchars($em['email']) ?></td>
                        <td style="padding: 1.25rem 1rem; border-bottom: 1px solid #f1f5f9;"><code style="background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 4px; color: #475569;"><?= htmlspecialchars($em['password']) ?></code></td>
                        <td style="padding: 1.25rem 1rem; border-bottom: 1px solid #f1f5f9;">
                            <?php
                                $typeColor = '#64748b';
                                if ($em['type'] == 'Google') $typeColor = '#4285F4';
                                elseif ($em['type'] == 'Outlook') $typeColor = '#0078D4';
                            ?>
                            <span style="background: rgba(<?= hexToRgb($typeColor) ?>, 0.1); color: <?= $typeColor ?>; padding: 0.35rem 0.75rem; border-radius: 0.5rem; font-weight: 800; font-size: 0.75rem;"><?= htmlspecialchars($em['type']) ?></span>
                        </td>
                        <td style="padding: 1.25rem 1rem; border-bottom: 1px solid #f1f5f9; color: #475569; font-weight: 600;"><?= htmlspecialchars($em['user_name'] ?? 'Não atribuído') ?></td>
                        <td style="padding: 1.25rem 1rem; border-bottom: 1px solid #f1f5f9; color: #94a3b8; font-size: 0.85rem;"><?= $em['usage_date'] ? date('d/m/Y', strtotime($em['usage_date'])) : '-' ?></td>
                        <td style="padding: 1.25rem 1rem; border-bottom: 1px solid #f1f5f9; text-align:right;">
                            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                <button class="btn-icon" style="background: #f1f5f9; width: 32px; height: 32px;" onclick='openEmailModal(<?= json_encode($em) ?>)' title="Editar"><i class="fa-solid fa-pen-to-square" style="font-size: 0.85rem;"></i></button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Deseja excluir este e-mail?')">
                                    <input type="hidden" name="action" value="delete_email">
                                    <input type="hidden" name="id" value="<?= $em['id'] ?>">
                                    <button type="submit" class="btn-icon" style="background: #fef2f2; color:#ef4444; width: 32px; height: 32px; border: 1px solid #fee2e2;"><i class="fa-solid fa-trash-can" style="font-size: 0.85rem;"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($emails)): ?>
                    <tr><td colspan="6" style="text-align:center;padding:4rem;color:#94a3b8;"><i class="fa-solid fa-envelope-open-text" style="font-size: 3rem; opacity: 0.1; margin-bottom: 1rem; display: block;"></i> Nenhum e-mail cadastrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ABA 4: ANOTAÇÕES (OneNote Style) -->
<div id="content-anotacoes" class="tech-content <?= $activeTab == 'anotacoes' ? 'active' : '' ?>">
    <div class="notes-layout">
        <!-- Seções (Abas Laterais) -->
        <div class="notes-sections">
            <div class="notes-header" style="background: #f1f5f9; color: #1e293b; border-bottom: 2px solid #e2e8f0;">
                <span style="font-weight: 900; letter-spacing: 0.05em; font-size: 0.75rem;">SEÇÕES</span>
                <button class="btn-icon" style="padding:0; min-width:auto; width:24px; height:24px; background: white; border: 1px solid #e2e8f0; border-radius: 6px;" onclick="openNoteSectionModal()" title="Nova Seção"><i class="fa-solid fa-plus" style="font-size: 0.7rem;"></i></button>
            </div>
            <div class="notes-list">
                <?php foreach ($note_sections as $sec): ?>
                    <div class="note-section-item <?= $active_section_id == $sec['id'] ? 'active' : '' ?>" 
                         onclick="window.location.href='?page=tecnologia&tab=anotacoes&section_id=<?= $sec['id'] ?>'"
                         style="<?= $active_section_id == $sec['id'] ? 'color:'.$sec['color'].'; background: rgba('.hexToRgb($sec['color']).', 0.1); border-left: 4px solid '.$sec['color'].';' : '' ?>">
                        <i class="fa-solid <?= htmlspecialchars($sec['icon']) ?>"></i>
                        <span style="flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($sec['name']) ?></span>
                        <button class="btn-icon section-edit-btn" style="padding:0; min-width:auto; display:none;" onclick='event.stopPropagation(); openNoteSectionModal(<?= json_encode($sec) ?>)'><i class="fa-solid fa-ellipsis-vertical"></i></button>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($note_sections)): ?>
                    <div style="padding: 2rem 1rem; font-size: 0.75rem; color: #94a3b8; text-align: center;">
                        <i class="fa-solid fa-folder-open" style="font-size: 2rem; opacity: 0.2; margin-bottom: 0.5rem;"></i>
                        <p>Nenhuma seção</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Páginas (Lista de Notas) -->
        <div class="notes-pages">
            <div class="notes-header" style="background: white; color: #1e293b; border-bottom: 1px solid #f1f5f9;">
                <span style="font-weight: 900; letter-spacing: 0.05em; font-size: 0.75rem; color: #64748b;">PÁGINAS</span>
                <?php if ($active_section_id): ?>
                    <button class="btn-icon" style="padding:0; min-width:auto; width:24px; height:24px; border: 1px solid #e2e8f0; border-radius: 6px;" onclick="createNewNote()" title="Nova Página"><i class="fa-solid fa-plus" style="font-size: 0.7rem;"></i></button>
                <?php endif; ?>
            </div>
            <div class="notes-list" style="padding: 0.75rem;">
                <?php foreach ($notes as $note): ?>
                    <div class="note-page-item <?= $active_note_id == $note['id'] ? 'active' : '' ?>"
                         onclick="window.location.href='?page=tecnologia&tab=anotacoes&section_id=<?= $active_section_id ?>&note_id=<?= $note['id'] ?>'">
                        <div class="note-page-title"><?= htmlspecialchars($note['title'] ?: 'Sem título') ?></div>
                        <div class="note-page-preview"><?= htmlspecialchars(substr(strip_tags($note['content']), 0, 80)) ?>...</div>
                        <div style="font-size: 0.65rem; color: #94a3b8; margin-top: 0.5rem; display: flex; align-items: center; gap: 0.35rem;">
                            <i class="fa-regular fa-clock"></i> <?= date('d/m/y H:i', strtotime($note['updated_at'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($notes) && $active_section_id): ?>
                    <div style="padding: 3rem 1rem; font-size: 0.75rem; color: #94a3b8; text-align: center;">
                        <i class="fa-regular fa-file-lines" style="font-size: 2rem; opacity: 0.2; margin-bottom: 0.5rem;"></i>
                        <p>Nenhuma nota aqui</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Editor (Conteúdo) -->
        <div class="notes-editor">
            <?php if ($active_section_id): ?>
                <form method="POST" id="noteForm" style="flex: 1; display: flex; flex-direction: column; min-height: 0; height: 100%;">
                    <input type="hidden" name="action" value="save_note">
                    <input type="hidden" name="id" id="note_id" value="<?= $active_note['id'] ?? '' ?>">
                    <input type="hidden" name="section_id" value="<?= $active_section_id ?>">
                    
                    <div class="notes-header" style="background: white; border-bottom: 1px solid #f1f5f9; padding: 0.75rem 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                            <div style="width: 8px; height: 8px; border-radius: 50%; background: #22c55e;"></div>
                            <span style="font-weight: 800; font-size: 0.75rem; color: #1e293b;"><?= $active_note ? 'MODO EDIÇÃO' : 'RASCUNHO' ?></span>
                        </div>
                        <div style="display: flex; gap: 0.75rem; align-items: center;">
                            <div style="position: relative;">
                                <button type="button" class="btn-icon" style="background: #f8fafc; width: 36px; height: 36px;" onclick="toggleEmojiDrawer()" title="Inserir Emoji"><i class="fa-regular fa-face-smile"></i></button>
                                <div id="emojiDrawer" class="emoji-drawer">
                                    <?php 
                                    $emojis = ['😀','😃','😄','😁','😆','😅','😂','🤣','😊','😇','🙂','🙃','😉','😌','😍','🥰','😘','😗','😙','😚','😋','😛','😝','😜','🤪','🤨','🧐','🤓','😎','🤩','🥳','😏','😒','😞','😔','😟','😕','🙁','☹️','😣','😖','😫','😩','🥺','😢','😭','😤','😠','😡','🤬','🤯','😳','🥵','🥶','😱','😨','😰','😥','😓','🤗','🤔','🤭','🤫','🤥','😶','😐','😑','😬','🙄','😯','😦','😧','😮','😲','🥱','😴','🤤','😪','😵','🤐','🥴','🤢','🤮','🤧','😷','🤒','🤕','🤑','🤠','😈','👿','👹','👺','🤡','💩','👻','💀','☠️','👽','👾','🤖','🎃','😺','😸','😻','😼','😽','🙀','😿','😾'];
                                    foreach($emojis as $e) echo '<span class="emoji-item" onclick="insertEmoji(\''.$e.'\')">'.$e.'</span>';
                                    ?>
                                </div>
                            </div>
                            <div style="position: relative;">
                                <button type="button" class="btn-icon" style="background: #f8fafc; width: 36px; height: 36px;" onclick="toggleStickerDrawer()" title="Inserir Adesivo"><i class="fa-solid fa-note-sticky"></i></button>
                                <div id="stickerDrawer" class="sticker-drawer">
                                    <?php 
                                    $stickers = [
                                        'https://cdn-icons-png.flaticon.com/512/2584/2584606.png', // Rocket
                                        'https://cdn-icons-png.flaticon.com/512/2584/2584644.png', // Star
                                        'https://cdn-icons-png.flaticon.com/512/2584/2584602.png', // Lightbulb
                                        'https://cdn-icons-png.flaticon.com/512/2584/2584610.png', // Target
                                        'https://cdn-icons-png.flaticon.com/512/2584/2584614.png', // Trophy
                                        'https://cdn-icons-png.flaticon.com/512/2584/2584652.png', // Gem
                                        'https://cdn-icons-png.flaticon.com/512/4727/4727424.png', // Cat
                                        'https://cdn-icons-png.flaticon.com/512/4727/4727393.png', // Dog
                                        'https://cdn-icons-png.flaticon.com/512/4727/4727506.png'  // Panda
                                    ];
                                    foreach($stickers as $s) echo '<div class="sticker-item" onclick="insertSticker(\''.$s.'\')"><img src="'.$s.'" style="width:50px;"></div>';
                                    ?>
                                </div>
                            </div>
                            <?php if ($active_note): ?>
                                <button type="button" class="btn-icon" style="color: #ef4444; width: 32px; height: 32px; border: 1px solid #fee2e2; background: #fef2f2;" onclick="deleteNote(<?= $active_note['id'] ?>)" title="Excluir Nota"><i class="fa-solid fa-trash-can" style="font-size: 0.8rem;"></i></button>
                            <?php endif; ?>
                            <button type="submit" class="btn-primary" style="padding: 0.5rem 1.25rem; border-radius: 8px; font-weight: 700; font-size: 0.85rem;"><i class="fa-solid fa-check"></i> Salvar</button>
                        </div>
                    </div>
                    
                    <!-- Toolbar customizada estilo OneNote -->
                    <div id="toolbar-container">
                        <span class="ql-formats">
                            <select class="ql-header">
                                <option value="1">Título 1</option>
                                <option value="2">Título 2</option>
                                <option selected>Texto Normal</option>
                            </select>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-bold"></button>
                            <button class="ql-italic"></button>
                            <button class="ql-underline"></button>
                            <button class="ql-strike"></button>
                        </span>
                        <span class="ql-formats">
                            <select class="ql-color"></select>
                            <select class="ql-background"></select>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-list" value="ordered"></button>
                            <button class="ql-list" value="bullet"></button>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-link"></button>
                            <button class="ql-image"></button>
                            <button class="ql-video"></button>
                        </span>
                        <span class="ql-formats">
                            <button class="ql-clean"></button>
                        </span>
                    </div>

                    <div class="editor-container">
                        <input type="text" name="title" id="noteTitleInput" class="editor-title" placeholder="Título da nota..." value="<?= htmlspecialchars($active_note['title'] ?? '') ?>" required autocomplete="off">
                        <div style="height: 1px; background: #f1f5f9; margin-bottom: 1rem; flex-shrink: 0;"></div>
                        
                        <input type="hidden" name="content" id="hiddenContent">
                        <div id="editor" class="editor-content"><?= $active_note['content'] ?? '' ?></div>
                    </div>
                </form>
            <?php else: ?>
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #94a3b8; padding: 2rem; text-align: center; background: radial-gradient(circle at center, #fff, #f8fafc);">
                    <div style="width: 120px; height: 120px; background: #fff; border-radius: 30px; box-shadow: 0 20px 40px rgba(0,0,0,0.05); display: flex; align-items: center; justify-content: center; margin-bottom: 2rem;">
                        <i class="fa-solid fa-pen-nib" style="font-size: 3rem; color: var(--crm-purple); opacity: 0.4;"></i>
                    </div>
                    <h3 style="font-weight: 900; color: #1e293b; font-size: 1.5rem; margin-bottom: 0.5rem;">Suas Notas</h3>
                    <p style="max-width: 300px; line-height: 1.6;">Selecione uma seção à esquerda ou crie uma nova para começar a organizar suas ideias.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .note-section-item:hover .section-edit-btn { display: block !important; }
    .note-section-item.active { box-shadow: inset 0 0 10px rgba(0,0,0,0.02); }
</style>

<!-- MODAL: SENHA DE ACESSO -->
<div id="passModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 2000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 400px; width: 100%; text-align: center;">
        <i class="fa-solid fa-shield-halved" style="font-size: 3rem; color: var(--crm-purple); margin-bottom: 1.5rem;"></i>
        <h3 style="font-size: 1.25rem; font-weight: 900; margin-bottom: 1rem;">Área Restrita</h3>
        <p style="color: #64748b; margin-bottom: 1.5rem;">Insira a senha de acesso para esta aba:</p>
        <div style="margin-bottom: 1.5rem;">
            <input type="password" id="unlockPass" class="form-input" style="text-align:center; font-size: 1.5rem; letter-spacing: 0.5rem;" placeholder="****">
        </div>
        <div style="display: flex; gap: 1rem;">
            <button onclick="document.getElementById('passModal').style.display='none'" class="btn-secondary" style="flex:1;">Cancelar</button>
            <button onclick="checkUnlock()" class="btn-primary" style="flex:1;">Acessar</button>
        </div>
    </div>
</div>

<!-- MODAIS DE CRUD -->
<!-- Modal Seção de Nota -->
<div id="noteSectionModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 2000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 400px; width: 100%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 id="noteSectionTitle" style="font-size: 1.25rem; font-weight: 900;">Nova Seção</h3>
            <button onclick="document.getElementById('noteSectionModal').style.display='none'" style="background: none; border: none; cursor: pointer; font-size: 1.5rem;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" id="noteSectionAction" value="add_note_section">
            <input type="hidden" name="id" id="note_section_id">
            <div class="form-group">
                <label class="form-label">Nome da Seção *</label>
                <input type="text" name="name" id="noteSectionName" class="form-input" required>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Ícone (FontAwesome)</label>
                    <input type="text" name="icon" id="noteSectionIcon" class="form-input" placeholder="fa-folder" value="fa-folder">
                </div>
                <div class="form-group">
                    <label class="form-label">Cor</label>
                    <input type="color" name="color" id="noteSectionColor" class="form-input" style="height: 45px; padding: 5px;" value="#6366f1">
                </div>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="button" onclick="deleteNoteSection()" id="btnDeleteSection" class="btn-secondary" style="color:#ef4444; border-color:#ef4444; display:none;">Excluir</button>
                <button type="button" onclick="document.getElementById('noteSectionModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Câmera -->
<div id="cameraModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 500px; width: 100%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 id="cameraTitle" style="font-size: 1.25rem; font-weight: 900;">Nova Câmera</h3>
            <button onclick="document.getElementById('cameraModal').style.display='none'" style="background: none; border: none; cursor: pointer; font-size: 1.5rem;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" id="cameraAction" value="add_camera">
            <input type="hidden" name="id" id="camera_id">
            <div class="form-group">
                <label class="form-label">Nome da Câmera *</label>
                <input type="text" name="name" id="cameraName" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">IP Address</label>
                <input type="text" name="ip_address" id="cameraIP" class="form-input" placeholder="0.0.0.0">
            </div>
            <div class="form-group">
                <label class="form-label">DOC</label>
                <input type="text" name="doc" id="cameraDoc" class="form-input" placeholder="Informações da câmera...">
            </div>
            <div class="form-group">
                <label class="form-label">Quantidade</label>
                <input type="number" name="quantity" id="cameraQty" class="form-input" value="1" min="1">
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="button" onclick="document.getElementById('cameraModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Remoto -->
<div id="remoteModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 600px; width: 100%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 id="remoteTitle" style="font-size: 1.25rem; font-weight: 900;">Novo Acesso Remoto</h3>
            <button onclick="document.getElementById('remoteModal').style.display='none'" style="background: none; border: none; cursor: pointer; font-size: 1.5rem;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" id="remoteAction" value="add_remote">
            <input type="hidden" name="id" id="remote_id">
            <div class="form-group">
                <label class="form-label">Usuário *</label>
                <select name="user_id" id="remoteUser" class="form-select" required>
                    <option value="">Selecione um usuário...</option>
                    <?php foreach ($all_users as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Senha PC</label>
                    <input type="text" name="pc_password" id="remotePcPass" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Senha Email</label>
                    <input type="text" name="email_password" id="remoteEmailPass" class="form-input">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Nome PC (Rede)</label>
                <input type="text" name="pc_name" id="remotePcName" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Observações</label>
                <textarea name="observations" id="remoteObs" class="form-textarea" rows="3"></textarea>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="button" onclick="document.getElementById('remoteModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Email -->
<div id="emailModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 600px; width: 100%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 id="emailTitle" style="font-size: 1.25rem; font-weight: 900;">Novo E-mail</h3>
            <button onclick="document.getElementById('emailModal').style.display='none'" style="background: none; border: none; cursor: pointer; font-size: 1.5rem;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" id="emailAction" value="add_email">
            <input type="hidden" name="id" id="email_id">
            <div class="form-group">
                <label class="form-label">E-mail *</label>
                <input type="email" name="email" id="emailAddr" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Senha</label>
                <input type="text" name="password" id="emailPass" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Tipo</label>
                <select name="type" id="emailType" class="form-select">
                    <option value="Google">Google (Workspace/Gmail)</option>
                    <option value="Outlook">Outlook / Office 365</option>
                    <option value="Bol">Bol</option>
                    <option value="Uol">Uol</option>
                    <option value="Yahoo">Yahoo</option>
                    <option value="Outro">Outro</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Usuário Home Office</label>
                <select name="remote_user_id" id="emailUser" class="form-select">
                    <option value="">Nenhum</option>
                    <?php foreach ($all_users as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Data de Utilização</label>
                <input type="date" name="usage_date" id="emailDate" class="form-input">
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="button" onclick="document.getElementById('emailModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
    let pendingTab = '';
    const systemTechPass = '<?= addslashes($tech_pass) ?>';
    const tabPasswords = {
        'cameras': systemTechPass,
        'remotos': systemTechPass,
        'emails': systemTechPass,
        'anotacoes': systemTechPass
    };
    const unlockedTabs = {
        'cameras': <?= $activeTab == 'cameras' ? 'true' : 'false' ?>,
        'remotos': <?= $activeTab == 'remotos' ? 'true' : 'false' ?>,
        'emails': <?= $activeTab == 'emails' ? 'true' : 'false' ?>,
        'anotacoes': <?= $activeTab == 'anotacoes' ? 'true' : 'false' ?>
    };

    function unlockTab(tab) {
        if (unlockedTabs[tab]) {
            switchTab(tab);
            return;
        }
        pendingTab = tab;
        document.getElementById('passModal').style.display = 'flex';
        document.getElementById('unlockPass').value = '';
        document.getElementById('unlockPass').focus();
    }

    function checkUnlock() {
        const pass = document.getElementById('unlockPass').value;
        if (pass === tabPasswords[pendingTab]) {
            unlockedTabs[pendingTab] = true;
            document.getElementById('passModal').style.display = 'none';
            switchTab(pendingTab);
        } else {
            alert('Senha incorreta!');
            document.getElementById('unlockPass').value = '';
        }
    }

    function switchTab(tab) {
        document.querySelectorAll('.tech-content').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('content-' + tab).classList.add('active');
        document.getElementById('tab-btn-' + tab).classList.add('active');
        
        // Update URL without refresh
        const url = new URL(window.location);
        url.searchParams.set('tab', tab);
        window.history.pushState({}, '', url);
    }

    // Modal control functions
    function openCameraModal(cam = null) {
        const modal = document.getElementById('cameraModal');
        document.getElementById('cameraAction').value = cam ? 'edit_camera' : 'add_camera';
        document.getElementById('cameraTitle').innerText = cam ? 'Editar Câmera' : 'Nova Câmera';
        document.getElementById('camera_id').value = cam ? cam.id : '';
        document.getElementById('cameraName').value = cam ? cam.name : '';
        document.getElementById('cameraIP').value = cam ? cam.ip_address : '';
        document.getElementById('cameraDoc').value = cam ? cam.doc : '';
        document.getElementById('cameraQty').value = cam ? cam.quantity : '1';
        modal.style.display = 'flex';
    }

    function openRemoteModal(rem = null) {
        const modal = document.getElementById('remoteModal');
        document.getElementById('remoteAction').value = rem ? 'edit_remote' : 'add_remote';
        document.getElementById('remoteTitle').innerText = rem ? 'Editar Acesso Remoto' : 'Novo Acesso Remoto';
        document.getElementById('remote_id').value = rem ? rem.id : '';
        document.getElementById('remoteUser').value = rem ? rem.user_id : '';
        document.getElementById('remotePcPass').value = rem ? rem.pc_password : '';
        document.getElementById('remoteEmailPass').value = rem ? rem.email_password : '';
        document.getElementById('remotePcName').value = rem ? rem.pc_name : '';
        document.getElementById('remoteObs').value = rem ? rem.observations : '';
        modal.style.display = 'flex';
    }

    function openEmailModal(em = null) {
        const modal = document.getElementById('emailModal');
        document.getElementById('emailAction').value = em ? 'edit_email' : 'add_email';
        document.getElementById('emailTitle').innerText = em ? 'Editar E-mail' : 'Novo E-mail';
        document.getElementById('email_id').value = em ? em.id : '';
        document.getElementById('emailAddr').value = em ? em.email : '';
        document.getElementById('emailPass').value = em ? em.password : '';
        document.getElementById('emailType').value = em ? em.type : 'Google';
        document.getElementById('emailUser').value = em ? em.remote_user_id : '';
        document.getElementById('emailDate').value = em ? em.usage_date : '';
        modal.style.display = 'flex';
    }

    // Notes Logic
    function openNoteSectionModal(sec = null) {
        const modal = document.getElementById('noteSectionModal');
        document.getElementById('noteSectionAction').value = sec ? 'edit_note_section' : 'add_note_section';
        document.getElementById('noteSectionTitle').innerText = sec ? 'Editar Seção' : 'Nova Seção';
        document.getElementById('note_section_id').value = sec ? sec.id : '';
        document.getElementById('noteSectionName').value = sec ? sec.name : '';
        document.getElementById('noteSectionIcon').value = sec ? sec.icon : 'fa-folder';
        document.getElementById('noteSectionColor').value = sec ? sec.color : '#6366f1';
        document.getElementById('btnDeleteSection').style.display = sec ? 'block' : 'none';
        modal.style.display = 'flex';
    }

    // Editor Logic
    let quill = null;
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('editor')) {
            quill = new Quill('#editor', {
                theme: 'snow',
                modules: {
                    toolbar: '#toolbar-container'
                },
                placeholder: 'Comece a escrever suas anotações aqui...'
            });

            // Sync hidden input on submit
            const noteForm = document.getElementById('noteForm');
            if (noteForm) {
                noteForm.onsubmit = function() {
                    document.getElementById('hiddenContent').value = quill.root.innerHTML;
                };
            }
        }
    });

    function toggleEmojiDrawer() {
        document.getElementById('emojiDrawer').classList.toggle('active');
    }

    function insertEmoji(emoji) {
        if (quill) {
            const range = quill.getSelection(true);
            quill.insertText(range.index, emoji);
            quill.setSelection(range.index + emoji.length);
        }
        toggleEmojiDrawer();
    }

    function toggleStickerDrawer() {
        document.getElementById('stickerDrawer').classList.toggle('active');
    }

    function insertSticker(url) {
        if (quill) {
            const range = quill.getSelection(true);
            quill.insertEmbed(range.index, 'image', url);
            quill.setSelection(range.index + 1);
        }
        toggleStickerDrawer();
    }

    function createNewNote() {
        if (quill) quill.root.innerHTML = '';
        document.getElementById('note_id').value = '';
        document.getElementById('noteTitleInput').value = '';
        document.getElementById('noteTitleInput').focus();
        
        // Remove active class from pages list
        document.querySelectorAll('.note-page-item').forEach(p => p.classList.remove('active'));
        
        // Update URL to show we are creating a new note
        const url = new URL(window.location);
        url.searchParams.delete('note_id');
        window.history.pushState({}, '', url);
    }

    // Close drawers on outside click
    window.addEventListener('click', function(e) {
        const eDrawer = document.getElementById('emojiDrawer');
        const sDrawer = document.getElementById('stickerDrawer');
        
        if (eDrawer && !e.target.closest('.emoji-drawer') && !e.target.closest('button[onclick="toggleEmojiDrawer()"]')) {
            eDrawer.classList.remove('active');
        }
        if (sDrawer && !e.target.closest('.sticker-drawer') && !e.target.closest('button[onclick="toggleStickerDrawer()"]')) {
            sDrawer.classList.remove('active');
        }
    });

    function deleteNote(id) {
        if (confirm('Deseja realmente excluir esta nota?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_note">
                <input type="hidden" name="id" value="${id}">
                <input type="hidden" name="section_id" value="<?= $active_section_id ?>">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function deleteNoteSection() {
        const id = document.getElementById('note_section_id').value;
        if (confirm('Deseja realmente excluir esta seção e TODAS as suas notas?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_note_section">
                <input type="hidden" name="id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Global listeners for modals
    window.onclick = function(event) {
        ['cameraModal', 'remoteModal', 'emailModal', 'passModal'].forEach(id => {
            const m = document.getElementById(id);
            if (event.target == m) m.style.display = "none";
        });
    }

    // Tabela Filter
    function filterTable(inputId, tableId) {
        const input = document.getElementById(inputId);
        const filter = input.value.toLowerCase();
        const table = document.getElementById(tableId);
        const tr = table.getElementsByTagName("tr");

        for (let i = 1; i < tr.length; i++) {
            tr[i].style.display = "none";
            const td = tr[i].getElementsByTagName("td");
            for (let j = 0; j < td.length; j++) {
                if (td[j]) {
                    const txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toLowerCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                        break;
                    }
                }
            }
        }
    }

    // Copiar Texto
    function copyText(btn, text) {
        if (!text) return;

        const successAction = () => {
            const icon = btn.querySelector('i');
            if (icon) {
                const oldClass = icon.className;
                icon.className = 'fa-solid fa-check';
                icon.style.color = '#10B981';
                setTimeout(() => { 
                    icon.className = oldClass; 
                    icon.style.color = '';
                }, 1500);
            }
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(successAction).catch(err => {
                console.error('Falha ao copiar (Clipboard API)', err);
            });
        } else {
            // Fallback method para conexões sem HTTPS (rede local)
            const textArea = document.createElement("textarea");
            textArea.value = text;
            // Evitar rolar a página
            textArea.style.top = "0";
            textArea.style.left = "0";
            textArea.style.position = "fixed";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                const successful = document.execCommand('copy');
                if (successful) successAction();
            } catch (err) {
                console.error('Falha ao copiar (Fallback)', err);
            }
            document.body.removeChild(textArea);
        }
    }
</script>
