<?php
require_once 'config.php';
// Ativar erros para depurar tela branca na web
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);
ob_start();
require_once 'auth.php';

// Inicialização Global
$openTickets = 0;

// Verificar login
$user = getCurrentUser();
if (!$user) {
    include 'landing.php';
    exit;
}

// --- LOGICA SAAS: Verificar Licença da Empresa ---
$tenant_id = $user['company_id'] ?: 1;
$stmt_tenant = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
$stmt_tenant->execute([$tenant_id]);
$tenant = $stmt_tenant->fetch();

$is_license_active = true;
$license_error_msg = "";

if (!$tenant) {
    $is_license_active = false;
    $license_error_msg = "Empresa não vinculada a uma licença válida.";
} elseif ($tenant['status'] !== 'active') {
    $is_license_active = false;
    $license_error_msg = "A licença desta empresa foi bloqueada pelo administrador.";
} elseif ($tenant['license_type'] !== 'lifetime' && strtotime($tenant['expires_at']) < time()) {
    $is_license_active = false;
    $license_error_msg = "Sua licença expirou em " . date('d/m/Y', strtotime($tenant['expires_at'])) . ".";
}

// Bloquear acesso se não for Super Admin e a licença estiver inválida
if (!$is_license_active && $user['is_super_admin'] != 1) {
    session_destroy();
    echo "<div style='height:100vh; display:flex; align-items:center; justify-content:center; font-family:Arial; background:#020617; color:white; text-align:center; padding:2rem;'>
            <div style='max-width:500px;'>
                <i class='fa-solid fa-lock' style='font-size:4rem; color:#EF4444; margin-bottom:2rem;'></i>
                <h1 style='font-size:2rem; margin-bottom:1rem;'>Acesso Bloqueado</h1>
                <p style='font-size:1.2rem; color:#94A3B8; margin-bottom:2rem;'>$license_error_msg</p>
                <p style='background:rgba(255,255,255,0.05); padding:1rem; border-radius:1rem; border:1px solid rgba(255,255,255,0.1);'>Entre em contato com o suporte para renovar sua assinatura.</p>
                <br><a href='login.php' style='color:#FBBF24; text-decoration:none; font-weight:bold;'>Voltar ao Login</a>
            </div>
          </div>";
    exit;
}

// --- Migração Automática de Tabelas SaaS ---
try {
    $cols_to_check = [
        'deleted_at' => "DATETIME NULL",
        'purge_after' => "DATE NULL",
        'access_liberation_date' => "DATETIME NULL",
        'last_payment_date' => "DATETIME NULL",
        'subscription_value' => "DECIMAL(10,2) DEFAULT 0",
        'last_amount_paid' => "DECIMAL(10,2) DEFAULT 0"
    ];
    foreach ($cols_to_check as $col => $definition) {
        $check = $pdo->query("SHOW COLUMNS FROM tenants LIKE '$col'")->fetch();
        if (!$check) {
            $pdo->exec("ALTER TABLE tenants ADD COLUMN $col $definition");
        }
    }
} catch(Exception $e) {}

// Buscar configurações da empresa do usuário logado
$user_company_id = $user['company_id'] ?: 1;
$company_stmt = $pdo->prepare("SELECT * FROM company_settings WHERE id = ?");
$company_stmt->execute([$user_company_id]);
$company = $company_stmt->fetch();

$user_menus = getUserMenus($user);

// --- Birthday Logic ---
$birthdayPeople = [];
$compId = getCurrentUserCompanyId();
if (!isset($_SESSION['bd_shown_v3'])) {
    try {
        $bdStmt = $pdo->prepare("
            SELECT u.id, u.name, u.avatar_url, rh.birth_date 
            FROM users u 
            JOIN rh_employee_details rh ON BINARY u.id = BINARY rh.user_id 
            WHERE MONTH(rh.birth_date) = MONTH(CURDATE()) 
            AND DAY(rh.birth_date) = DAY(CURDATE())
            AND u.status = 'Ativo'
            AND u.company_id = ?
        ");
        $bdStmt->execute([$compId]);
        $birthdayPeople = $bdStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
    }
}

// Auto-enviar mensagem de aniversario via chat (uma vez por ano)
if (!empty($birthdayPeople) && !empty($company['birthday_message_self'])) {
    $currentYear = date('Y');
    $bdMsgSelf = $company['birthday_message_self'];
    // Buscar ID de um admin para ser o remetente da mensagem
    $adminSender = null;
    try {
        $stmt_admin = $pdo->prepare("SELECT id FROM users WHERE role = 'Administrador' AND status = 'Ativo' AND company_id = ? LIMIT 1");
        $stmt_admin->execute([$compId]);
        $adminSender = $stmt_admin->fetchColumn();
    } catch(Exception $e) {}
    
    foreach ($birthdayPeople as $bdPerson) {
        try {
            // Verificar se já enviamos esse ano
            $alreadySent = $pdo->prepare("SELECT COUNT(*) FROM birthday_sent_log WHERE user_id = ? AND sent_year = ?");
            $alreadySent->execute([$bdPerson['id'], $currentYear]);
            if ($alreadySent->fetchColumn() == 0 && $adminSender && $adminSender !== $bdPerson['id']) {
                // Enviar mensagem
                $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, content, type) VALUES (?, ?, ?, 'text')")
                    ->execute([$adminSender, $bdPerson['id'], $bdMsgSelf]);
                // Registrar envio
                $pdo->prepare("INSERT INTO birthday_sent_log (user_id, sent_year) VALUES (?, ?) ON DUPLICATE KEY UPDATE id = id")
                    ->execute([$bdPerson['id'], $currentYear]);
            }
        } catch(Exception $e) {}
    }
}

// --- Announcements Logic (Empresa) ---
$loginAnnouncement = null;
if (!empty($company['login_announcement']) && !isset($_SESSION['announcement_dismissed'])) {
    $loginAnnouncement = $company['login_announcement'];
}

$page = $_GET['page'] ?? 'dashboard';
if (!in_array($page, $user_menus) && $page !== 'dashboard') {
    $page = 'dashboard';
}

// Contagem de tickets abertos para o badge da sidebar
try {
    $compId = getCurrentUserCompanyId();
    if ($user['login_name'] === 'superadmin') {
        // Superadmin vê total de todas as empresas
        $stmt_open = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'Aberto'");
    } else {
        $stmt_open = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE status = 'Aberto' AND company_id = ?");
        $stmt_open->execute([$compId]);
    }
    $openTickets = $stmt_open->fetchColumn() ?: 0;
} catch (Exception $e) { $openTickets = 0; }
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0, shrink-to-fit=no">
    <title>Cetusg - <?= ucfirst($page) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php if (!empty($company['logo_url'])): ?>
        <link rel="icon" type="image/png" href="<?= htmlspecialchars($company['logo_url']) ?>">
        <link rel="shortcut icon" href="<?= htmlspecialchars($company['logo_url']) ?>">
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/theme_manager.js"></script>
    <script src="assets/js/command_palette.js"></script>
    <style>
        /* Animação de Vibração para o Chat */
        @keyframes vibrar {
            0% { transform: rotate(0deg); }
            25% { transform: rotate(5deg); }
            50% { transform: rotate(-5deg); }
            75% { transform: rotate(5deg); }
            100% { transform: rotate(0deg); }
        }
        .vibrar { animation: vibrar 0.3s linear infinite; }

        /* Estilos do Modal de Perfil */
        .profile-modal {
            display: none; position: fixed; inset: 0; z-index: 20000;
            background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(10px);
            align-items: center; justify-content: center;
        }
        .profile-modal.active { display: flex; }
        .profile-panel {
            background: white; width: 95%; max-width: 500px; border-radius: 1.5rem;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); overflow: hidden;
            animation: modalIn 0.3s ease-out;
        }
        @keyframes modalIn { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .profile-header {
            background: var(--brand-primary); padding: 2.5rem 2rem; text-align: center; color: white; position: relative;
        }
        .profile-avatar-large {
            width: 120px; height: 120px; border-radius: 50%; border: 4px solid white;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2); margin: 0 auto 1rem;
            background: white; display: flex; align-items: center; justify-content: center;
            font-size: 3rem; font-weight: 900; color: var(--brand-primary); overflow: hidden;
        }
        .profile-body { padding: 2rem; }
        .profile-field { margin-bottom: 1.5rem; }
        .profile-field label { display: block; font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 0.5rem; }
        .profile-field input { width: 100%; padding: 0.75rem 1rem; border-radius: 0.75rem; border: 1px solid #e2e8f0; background: #f8fafc; font-weight: 600; color: #1e293b; }
        .profile-field input:focus { outline: none; border-color: var(--brand-primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .profile-field input:read-only { background: #f1f5f9; color: #64748b; cursor: not-allowed; }
    </style>
    <link rel="stylesheet" href="assets/css/widget_assistant.css">
</head>

<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <?php if (!empty($company['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($company['logo_url']) ?>" alt="Logo" style="max-height: 40px;">
                <?php else: ?>
                    <div class="logo">CETUSG<span style="color: var(--brand-primary);">+</span></div>
                <?php endif; ?>
            </div>
            
            <nav class="sidebar-nav">
                <div class="sidebar-group">
                    <span class="sidebar-category">Principal</span>
                    <a href="index.php?page=dashboard" class="sidebar-item <?= $page === 'dashboard' ? 'sidebar-active' : '' ?>">
                        <i class="fa-solid fa-house"></i>
                        <span>Dashboard</span>
                    </a>
                </div>

                <?php if (in_array('rh', $user_menus) || in_array('voluntariado', $user_menus) || in_array('semanada', $user_menus)): ?>
                <div class="sidebar-group">
                    <span class="sidebar-category">Operacional</span>
                    <?php if (in_array('rh', $user_menus)): ?>
                        <a href="index.php?page=rh" class="sidebar-item <?= $page === 'rh' ? 'sidebar-active' : '' ?>">
                            <i class="fa-solid fa-users"></i>
                            <span>Recursos Humanos</span>
                        </a>
                    <?php endif; ?>
                    <?php if (in_array('voluntariado', $user_menus)): ?>
                        <a href="index.php?page=voluntariado" class="sidebar-item <?= $page === 'voluntariado' ? 'sidebar-active' : '' ?>">
                            <i class="fa-solid fa-heart"></i>
                            <span>Voluntariado</span>
                        </a>
                    <?php endif; ?>
                    <?php if (in_array('semanada', $user_menus)): ?>
                        <a href="index.php?page=semanada" class="sidebar-item <?= $page === 'semanada' ? 'sidebar-active' : '' ?>">
                            <i class="fa-solid fa-calendar-check"></i>
                            <span>Semanada</span>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (in_array('patrimonio', $user_menus) || in_array('emprestimos', $user_menus) || in_array('chamados', $user_menus)): ?>
                <div class="sidebar-group">
                    <span class="sidebar-category">Gestão de Ativos</span>
                    <?php if (in_array('patrimonio', $user_menus)): ?>
                        <a href="index.php?page=patrimonio" class="sidebar-item <?= $page === 'patrimonio' ? 'sidebar-active' : '' ?>">
                            <i class="fa-solid fa-box-archive"></i>
                            <span>Patrimônio</span>
                        </a>
                    <?php endif; ?>
                    <?php if (in_array('emprestimos', $user_menus)): ?>
                        <a href="index.php?page=emprestimos" class="sidebar-item <?= $page === 'emprestimos' ? 'sidebar-active' : '' ?>">
                            <i class="fa-solid fa-handshake"></i>
                            <span>Empréstimos</span>
                        </a>
                    <?php endif; ?>
                    <?php if (in_array('chamados', $user_menus)): ?>
                        <a href="index.php?page=chamados" class="sidebar-item <?= $page === 'chamados' ? 'sidebar-active' : '' ?>">
                            <i class="fa-solid fa-ticket"></i>
                            <span>Chamados</span>
                            <?php if ($openTickets > 0): ?>
                                <span class="nav-badge"><?= $openTickets ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (in_array('orcamentos', $user_menus) || in_array('locacao_salas', $user_menus)): ?>
                <div class="sidebar-group">
                    <span class="sidebar-category">Infraestrutura</span>
                    <?php if (in_array('orcamentos', $user_menus)): ?>
                        <a href="index.php?page=orcamentos" class="sidebar-item <?= $page === 'orcamentos' ? 'sidebar-active' : '' ?>">
                            <i class="fa-solid fa-receipt"></i>
                            <span>Orçamentos</span>
                        </a>
                    <?php endif; ?>
                    <?php if (in_array('locacao_salas', $user_menus)): ?>
                        <a href="index.php?page=locacao_salas" class="sidebar-item <?= $page === 'locacao_salas' ? 'sidebar-active' : '' ?>">
                            <i class="fa-solid fa-door-closed"></i>
                            <span>Salas</span>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (in_array('relatorios', $user_menus) || in_array('tecnologia', $user_menus) || in_array('informacoes', $user_menus)): ?>
                <div class="sidebar-group">
                    <span class="sidebar-category">Sistemas</span>
                    <?php if (in_array('relatorios', $user_menus)): ?>
                        <a href="index.php?page=relatorios" class="sidebar-item <?= $page === 'relatorios' ? 'sidebar-active' : '' ?>">
                            <i class="fa-solid fa-chart-line"></i>
                            <span>Relatórios</span>
                        </a>
                    <?php endif; ?>
                    <?php if (in_array('tecnologia', $user_menus)): ?>
                        <a href="index.php?page=tecnologia" class="sidebar-item <?= $page === 'tecnologia' ? 'sidebar-active' : '' ?>">
                            <i class="fa-solid fa-laptop-code"></i>
                            <span>Tecnologia</span>
                        </a>
                    <?php endif; ?>
                    <?php if (in_array('informacoes', $user_menus)): ?>
                        <a href="index.php?page=informacoes" class="sidebar-item <?= $page === 'informacoes' ? 'sidebar-active' : '' ?>">
                            <i class="fa-solid fa-circle-info"></i>
                            <span>Informações</span>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (in_array('usuarios', $user_menus) || in_array('configuracoes', $user_menus)): ?>
                <div class="sidebar-group">
                    <span class="sidebar-category">Ajustes</span>
                    <?php if (in_array('usuarios', $user_menus)): ?>
                        <a href="index.php?page=usuarios" class="sidebar-item <?= $page === 'usuarios' ? 'sidebar-active' : '' ?>">
                            <i class="fa-solid fa-user-gear"></i>
                            <span>Usuários</span>
                        </a>
                    <?php endif; ?>
                    <?php if (in_array('configuracoes', $user_menus)): ?>
                        <a href="index.php?page=configuracoes" class="sidebar-item <?= $page === 'configuracoes' ? 'sidebar-active' : '' ?>">
                            <i class="fa-solid fa-gear"></i>
                            <span>Configurações</span>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (in_array('super_admin', $user_menus)): ?>
                <div class="sidebar-group">
                    <span class="sidebar-category">Administrador Master</span>
                    <a href="index.php?page=super_admin" class="sidebar-item <?= $page === 'super_admin' ? 'sidebar-active' : '' ?>" style="color: #FBBF24;">
                        <i class="fa-solid fa-crown" style="color: #FBBF24;"></i>
                        <span>Gestão SaaS</span>
                    </a>
                </div>
                <?php endif; ?>
            </nav>
            <div class="sidebar-footer">
                <a href="logout.php" class="sidebar-item">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    Sair do Sistema
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-bar">
                <button class="hamburger" onclick="toggleSidebar()">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="page-title-spacer"></div>
                <div class="user-menu">
                    <button class="theme-toggle" onclick="toggleTheme()" title="Alternar Tema">
                        <i class="fa-solid fa-circle-half-stroke"></i>
                    </button>
                    <button class="peixinho-btn" onclick="openPeixinhoChat()">
                        <img src="assets/img/peixinho.png" alt="Peixinho AI">
                    </button>
                    <div style="position: relative; cursor: pointer;" onclick="toggleChatPanel()" id="chatGlobalIcon">
                        <i class="fa-solid fa-comment-dots" style="font-size: 1.5rem; color: var(--brand-primary);"></i>
                        <span id="globalChatBadge"
                            style="position: absolute; top: -8px; right: -8px; background: #EF4444; color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 0.65rem; display: flex; align-items: center; justify-content: center; font-weight: 900;">0</span>
                    </div>
                    <div class="user-avatar" onclick="openMyProfile()" style="cursor: pointer; overflow: hidden; border: 2px solid var(--brand-soft); transition: all 0.3s;">
                        <?php if (!empty($user['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($user['avatar_url']) ?>" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <?= strtoupper(substr($user['name'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <span class="user-name" onclick="openMyProfile()" style="cursor: pointer;"><?= htmlspecialchars($user['name']) ?></span>
                </div>
            </header>

            <div class="content-area">
                <?php include "pages/{$page}.php"; ?>
            </div>
        </main>
    </div>

    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <!-- Chat Arrastão Tech Launcher (Bottom Right) -->

    <!-- Chat Arrastão Tech Launcher (Bottom Right) -->
    <div id="chatPinnedBar" onclick="ChatCore.toggle()" class="chat-pinned-bar" title="Chat Corporativo">
        <i class="fa-solid fa-comments"></i>
        <div id="minimizedBadge" class="chat-badge" style="display: none;">0</div>
    </div>

    <!-- Chat Arrastão Tech Panel -->
    <div id="chatPanel" class="chat-panel" style="display: none;">
        <!-- User List View -->
        <div class="chat-user-list">
            <div class="chat-header-list" style="padding: 15px 20px; background: linear-gradient(135deg, #2c4a7c, #1a3560); color: white; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <?php if (!empty($company['logo_url'])): ?>
                        <img src="<?= htmlspecialchars($company['logo_url']) ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: contain; background: white; padding: 2px;">
                    <?php endif; ?>
                    <div style="font-weight: 800; font-size: 1rem; letter-spacing: -0.5px;"><?= htmlspecialchars($company['name'] ?? 'Mensagens') ?></div>
                </div>
                <i class="fa-solid fa-xmark" onclick="ChatCore.toggle()" style="cursor: pointer; opacity: 0.8;"></i>
            </div>
            <div id="chatUserList" class="chat-user-list-container" style="height: calc(100% - 60px); overflow-y: auto;">
                <!-- Usuários via JS -->
            </div>
        </div>

        <!-- Conversation View -->
        <div class="chat-main">
            <div class="chat-header-main" style="padding: 15px 20px; background: white; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; gap: 15px;">
                <i class="fa-solid fa-chevron-left" onclick="ChatCore.backToList()" style="cursor: pointer; color: #64748b;"></i>
                <div id="convoUserAvatar" class="chat-avatar-wrapper" style="width: 40px; height: 40px;"></div>
                <div style="flex: 1;">
                    <div id="convoUserName" style="font-weight: 700; color: #1e293b; font-size: 0.9rem;">Usuário</div>
                    <div id="convoUserStatus" style="font-size: 0.7rem; color: #22c55e;">Online</div>
                </div>
            </div>

            <div id="chatMessages" class="chat-messages-container" style="flex: 1; padding: 20px; overflow-y: auto; background: #f8fafc; display: flex; flex-direction: column; gap: 10px;">
                <!-- Mensagens via JS -->
            </div>

            <div class="chat-footer-main" style="padding: 15px; background: white; border-top: 1px solid #f1f5f9;">
                <div class="chat-input-actions" style="margin-bottom: 10px; display: flex; gap: 10px;">
                    <div class="action-btn" onclick="ChatCore.triggerFileUpload()" title="Enviar Arquivo">
                        <i class="fa-solid fa-paperclip"></i>
                    </div>
                    <div class="action-btn" onclick="ChatCore.toggleEmojiPicker()" title="Emojis">
                        <i class="fa-regular fa-face-smile"></i>
                    </div>
                    <input type="file" id="chatFileAnchor" style="display: none;" onchange="ChatCore.handleFileUpload(this)">
                </div>
                <div class="widget-input-group" style="display: flex; gap: 10px;">
                    <input type="text" id="chatInput" placeholder="Sua mensagem..." style="flex: 1; padding: 12px 18px; border-radius: 100px; border: 1px solid #e2e8f0; background: #f8fafc; outline: none;">
                    <button onclick="ChatCore.sendMessage()" style="width: 45px; height: 45px; border-radius: 50%; border: none; background: #2c4a7c; color: white; cursor: pointer;">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="birthdayModal" class="modal" style="z-index: 20000;">
            <div class="modal-content"
                style="text-align: center; max-width: 700px; border: 4px solid var(--brand-primary);">
                <div id="confettiContainer" style="position: absolute; inset: 0; pointer-events: none; overflow: hidden;">
                </div>
                <div id="bdContentContainer"></div>
            </div>
        </div>

    <?php if ($loginAnnouncement && !isset($_SESSION['announcement_dismissed'])): ?>
        <div id="announcementModal" class="modal active"
            style="position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,0.9); display: flex; align-items: center; justify-content: center;">
            <div class="glass-panel"
                style="max-width: 900px; width: 95%; border-radius: 2rem; overflow: hidden; position: relative; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1);">
                <div style="display: flex; flex-direction: column; padding: 4rem; text-align: center;">
                    <h2
                        style="font-size: 3rem; font-weight: 900; margin-bottom: 2rem; color: #38bdf8; letter-spacing: -1px;">
                        AVISO IMPORTANTE</h2>
                    <div
                        style="color: rgba(255,255,255,0.9); font-size: 1.25rem; line-height: 1.8; margin-bottom: 3rem; max-height: 300px; overflow-y: auto; padding-right: 1.5rem; text-align: left;">
                        <?= nl2br($loginAnnouncement) ?>
                    </div>
                    <button onclick="dismissAnnouncement()" class="btn-primary"
                        style="padding: 18px 60px; font-size: 1.25rem; border-radius: 100px; font-weight: 800; background: #38bdf8; color: #000;">ENTENDI</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Modal de Perfil -->
    <div id="profileModal" class="profile-modal">
        <div class="profile-panel">
            <div class="profile-header">
                <i class="fa-solid fa-xmark" onclick="closeProfileModal()" style="position: absolute; top: 1.5rem; right: 1.5rem; font-size: 1.5rem; cursor: pointer; color: rgba(255,255,255,0.7);"></i>
                <div id="selfProfileAvatar" class="profile-avatar-large"></div>
                <h3 id="selfProfileName" style="font-size: 1.5rem; font-weight: 900;">Carregando...</h3>
                <p id="selfProfileEmail" style="font-size: 0.85rem; color: rgba(255,255,255,0.8);"></p>
            </div>
            <div class="profile-body">
                <div class="profile-field">
                    <label>Telefone</label>
                    <input type="text" id="selfProfilePhone" placeholder="(00) 00000-0000">
                </div>
                <div class="profile-field">
                    <label>Endereço</label>
                    <input type="text" id="selfProfileAddress" readonly>
                </div>
                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                    <button class="btn-secondary" onclick="closeProfileModal()" style="flex: 1; padding: 0.85rem; border-radius: 0.75rem; font-weight: 800;">Fechar</button>
                    <button class="btn-primary" id="saveProfileBtn" onclick="saveMyPhone()" style="flex: 1; padding: 0.85rem; border-radius: 0.75rem; font-weight: 800;">Salvar Telefone</button>
                </div>
            </div>
        </div>
    </div>

    <audio id="audioSent" src="https://cdn.pixabay.com/audio/2022/01/18/audio_82137996f0.mp3" preload="auto"></audio>
    <audio id="audioReceived" src="https://cdn.pixabay.com/audio/2021/08/04/audio_0625c1539c.mp3" preload="auto"></audio>

    <script src="assets/js/chat_core.js"></script>
    <script>
        const birthdayPeople = <?= json_encode($birthdayPeople) ?>;
        const currentUserId = '<?= $user['id'] ?>';
        const hasAnnouncement = <?= ($loginAnnouncement) ? 'true' : 'false' ?>;

        // Inicializar Chat Corporativo
        document.addEventListener('DOMContentLoaded', () => {
            ChatCore.init(currentUserId);
            if (!hasAnnouncement) showBirthdaySequence();
        });

        // --- SISTEMA DE PERFIL ---
        window.openMyProfile = async function() {
            const modal = document.getElementById('profileModal');
            if (modal) modal.classList.add('active');
            try {
                const r = await fetch('api_user_profile.php?action=get_profile');
                const p = await r.json();
                if (p.error) return;

                document.getElementById('selfProfileName').innerText = p.name;
                document.getElementById('selfProfileEmail').innerText = p.email;
                document.getElementById('selfProfilePhone').value = p.phone || '';
                
                const addr = [p.address_street, p.address_number, p.address_neighborhood, p.address_city, p.address_state]
                    .filter(i => i && i.trim() !== '').join(', ');
                document.getElementById('selfProfileAddress').value = addr || 'Endereço não cadastrado';

                const avatarDiv = document.getElementById('selfProfileAvatar');
                if (avatarDiv) avatarDiv.innerHTML = p.avatar_url ? `<img src="${p.avatar_url}" style="width:100%;height:100%;object-fit:cover;">` : p.name.substring(0, 1).toUpperCase();
            } catch(e) {}
        };

        window.closeProfileModal = () => document.getElementById('profileModal').classList.remove('active');

        window.saveMyPhone = async function() {
            const btn = document.getElementById('saveProfileBtn');
            const phone = document.getElementById('selfProfilePhone').value;
            btn.innerText = 'Salvando...';
            btn.disabled = true;
            try {
                const fd = new FormData();
                fd.append('action', 'update_phone');
                fd.append('phone', phone);
                await fetch('api_user_profile.php', { method: 'POST', body: fd });
                alert('Telefone atualizado com sucesso!');
            } catch(e) { alert('Erro ao salvar telefone.'); }
            finally { btn.innerText = 'Salvar Telefone'; btn.disabled = false; }
        };

        // --- SEQUENCIAMENTO DE MODAIS ---
        window.showBirthdaySequence = function () {
            if (birthdayPeople.length > 0) {
                setTimeout(() => {
                    const modal = document.getElementById('birthdayModal');
                    if (modal) {
                        modal.classList.add('active');
                        renderBirthdayContent();
                    }
                }, 7000);
            }
        };

        window.dismissAnnouncement = function () {
            const modal = document.getElementById('announcementModal');
            if (modal) modal.style.display = 'none';
            const fd = new FormData();
            fd.append('action', 'dismiss_announcement');
            fetch('announcements_api.php', { method: 'POST', body: fd });
            showBirthdaySequence();
        };

        window.renderBirthdayContent = function () {
            if (birthdayPeople.length === 0) return;
            const container = document.getElementById('bdContentContainer');
            if (!container) return;
            const isMyBirthday = birthdayPeople.some(p => String(p.id) === String(currentUserId));
            let message = isMyBirthday ? '<h2>Feliz Aniversário! 🥳</h2>' : '<h2>Hoje temos aniversariantes! 🎉</h2>';
            container.innerHTML = `<div style="padding: 1rem;">${message}<button onclick="closeBirthdayModal()" class="btn-primary">FECHAR</button></div>`;
        };

        window.closeBirthdayModal = () => {
            document.getElementById('birthdayModal').classList.remove('active');
            const fd = new FormData();
            fd.append('action', 'dismiss_birthday');
            fetch('ajax_birthday.php', { method: 'POST', body: fd });
        };

        // Mobile Sidebar Toggle
        function toggleSidebar() { document.body.classList.toggle('sidebar-open'); }
        
        // Compatibilidade Chat
        window.toggleChatPanel = () => ChatCore.toggle();
        window.backToUserList = () => ChatCore.backToList();
    </script>
    <!-- Widget Assistente Cetusg -->
    <div id="widget-launcher" title="Assistente Cetusg">
        <div class="widget-badge"></div>
        <i class="fa-solid fa-robot"></i>
    </div>
    <div id="widget-panel">
        <div class="widget-header">
            <div class="bot-info">
                <img src="assets/img/peixinho.png" alt="Bot">
                <div>
                    <div style="font-weight: 700; font-size: 1rem;">Assistente Cetusg</div>
                    <div style="font-size: 0.75rem; opacity: 0.8;">Online agora</div>
                </div>
            </div>
            <i class="fa-solid fa-xmark" id="widget-close" style="cursor: pointer;"></i>
        </div>
        <div id="widget-messages" class="widget-messages"></div>
        <div class="widget-footer">
            <div class="widget-input-group">
                <input type="text" id="widget-input-text" placeholder="Digite sua dúvida...">
                <button id="widget-send-btn"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
        </div>
    </div>
    <script src="assets/js/widget_assistant.js"></script>
</body>
</html>