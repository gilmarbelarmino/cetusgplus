<?php
session_start();
ob_start();
require_once 'config.php';
require_once 'auth.php';

// Verificar login
$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}

$company = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch();
$user_menus = getUserMenus($user);

// --- Birthday Logic ---
$birthdayPeople = [];
if (!isset($_SESSION['bd_shown_v3'])) {
    try {
        $bdStmt = $pdo->query("
            SELECT u.id, u.name, u.avatar_url, rh.birth_date 
            FROM users u 
            JOIN rh_employee_details rh ON BINARY u.id = BINARY rh.user_id 
            WHERE MONTH(rh.birth_date) = MONTH(CURDATE()) 
            AND DAY(rh.birth_date) = DAY(CURDATE())
            AND u.status = 'Ativo'
        ");
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
        $adminSender = $pdo->query("SELECT id FROM users WHERE role = 'Administrador' AND status = 'Ativo' LIMIT 1")->fetchColumn();
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
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetusg - <?= ucfirst($page) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php if (!empty($company['logo_url'])): ?>
        <link rel="icon" type="image/png" href="<?= htmlspecialchars($company['logo_url']) ?>">
        <link rel="shortcut icon" href="<?= htmlspecialchars($company['logo_url']) ?>">
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
</head>

<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <?php if (!empty($company['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($company['logo_url']) ?>" alt="Logo" style="max-height: 50px;">
                <?php else: ?>
                    <div class="logo">CETUSG</div>
                <?php endif; ?>
            </div>
            <nav class="sidebar-nav">
                <span class="sidebar-category">Principal</span>
                <a href="index.php?page=dashboard" class="sidebar-item <?= $page === 'dashboard' ? 'sidebar-active' : '' ?>">
                    <i class="fa-solid fa-gauge-high"></i>
                    Dashboard
                </a>
                <?php if (in_array('rh', $user_menus)): ?>
                    <a href="index.php?page=rh" class="sidebar-item <?= $page === 'rh' ? 'sidebar-active' : '' ?>">
                        <i class="fa-solid fa-user-tie"></i>
                        Recursos Humanos
                    </a>
                <?php endif; ?>
                <?php if (in_array('voluntariado', $user_menus)): ?>
                    <a href="index.php?page=voluntariado" class="sidebar-item <?= $page === 'voluntariado' ? 'sidebar-active' : '' ?>">
                        <i class="fa-solid fa-hand-holding-heart"></i>
                        Voluntariado
                    </a>
                <?php endif; ?>

                <span class="sidebar-category">Gestão</span>
                <?php if (in_array('patrimonio', $user_menus)): ?>
                    <a href="index.php?page=patrimonio" class="sidebar-item <?= $page === 'patrimonio' ? 'sidebar-active' : '' ?>">
                        <i class="fa-solid fa-vault"></i>
                        Patrimônio
                    </a>
                <?php endif; ?>
                <?php if (in_array('emprestimos', $user_menus)): ?>
                    <a href="index.php?page=emprestimos" class="sidebar-item <?= $page === 'emprestimos' ? 'sidebar-active' : '' ?>">
                        <i class="fa-solid fa-handshake-angle"></i>
                        Empréstimos
                    </a>
                <?php endif; ?>
                <?php if (in_array('chamados', $user_menus)): ?>
                    <a href="index.php?page=chamados" class="sidebar-item <?= $page === 'chamados' ? 'sidebar-active' : '' ?>">
                        <i class="fa-solid fa-headset"></i>
                        Chamados
                    </a>
                <?php endif; ?>
                <?php if (in_array('orcamentos', $user_menus)): ?>
                    <a href="index.php?page=orcamentos" class="sidebar-item <?= $page === 'orcamentos' ? 'sidebar-active' : '' ?>">
                        <i class="fa-solid fa-file-invoice-dollar"></i>
                        Orçamentos
                    </a>
                <?php endif; ?>
                <?php if (in_array('salas', $user_menus)): ?>
                    <a href="index.php?page=salas" class="sidebar-item <?= $page === 'salas' ? 'sidebar-active' : '' ?>">
                        <i class="fa-solid fa-door-open"></i>
                        Salas
                    </a>
                <?php endif; ?>

                <span class="sidebar-category">Sistema</span>
                <?php if (in_array('relatorios', $user_menus)): ?>
                    <a href="index.php?page=relatorios" class="sidebar-item <?= $page === 'relatorios' ? 'sidebar-active' : '' ?>">
                        <i class="fa-solid fa-chart-line"></i>
                        Relatórios
                    </a>
                <?php endif; ?>
                <?php if (in_array('tecnologia', $user_menus)): ?>
                    <a href="index.php?page=tecnologia" class="sidebar-item <?= $page === 'tecnologia' ? 'sidebar-active' : '' ?>">
                        <i class="fa-solid fa-laptop-code"></i>
                        Tecnologia
                    </a>
                <?php endif; ?>
                <?php if (in_array('usuarios', $user_menus)): ?>
                    <a href="index.php?page=usuarios" class="sidebar-item <?= $page === 'usuarios' ? 'sidebar-active' : '' ?>">
                        <i class="fa-solid fa-users-gear"></i>
                        Usuários
                    </a>
                <?php endif; ?>
                <?php if (in_array('configuracoes', $user_menus)): ?>
                    <a href="index.php?page=configuracoes" class="sidebar-item <?= $page === 'configuracoes' ? 'sidebar-active' : '' ?>">
                        <i class="fa-solid fa-sliders"></i>
                        Configurações
                    </a>
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
                <div class="page-title-spacer"></div>
                <div class="user-menu">
                    <button class="peixinho-btn" onclick="openPeixinhoChat()">
                        <img src="assets/img/peixinho.png" alt="Peixinho AI">
                    </button>
                    <div style="position: relative; cursor: pointer;" onclick="toggleChatPanel()" id="chatGlobalIcon">
                        <i class="fa-solid fa-comment-dots" style="font-size: 1.5rem; color: var(--brand-primary);"></i>
                        <span id="globalChatBadge"
                            style="position: absolute; top: -8px; right: -8px; background: #EF4444; color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 0.65rem; display: none; align-items: center; justify-content: center; font-weight: 900;">0</span>
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

    <!-- Chat Panel -->
    <div id="chatPinnedBar" onclick="toggleChatPanel()"
        style="position: fixed; bottom: 0; right: 20px; width: 320px; height: 45px; background: #f0f2f5; border: 1px solid #e2e8f0; border-bottom: none; border-radius: 8px 8px 0 0; display: none; align-items: center; padding: 0 15px; cursor: pointer; z-index: 10001; box-shadow: 0 -2px 10px rgba(0,0,0,0.1);">
        <div style="flex: 1; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-comments" style="color: var(--brand-primary);"></i>
            <span
                style="font-weight: 700; font-size: 0.9rem; color: #1e293b;"><?= htmlspecialchars($company['company_name']) ?></span>
        </div>
        <div id="minimizedBadge"
            style="background: #ef4444; color: white; border-radius: 10px; padding: 2px 6px; font-size: 0.7rem; font-weight: 900; display: none;">
            0</div>
    </div>

    <div id="chatPanel" class="chat-panel"
        style="width: 1000px; max-width: 95vw; height: 80vh; bottom: 20px; border-radius: 12px; box-shadow: 0 20px 50px rgba(0,0,0,0.3); border: none; overflow: hidden; display: none; flex-direction: row; background: #fff; z-index: 10000;">

        <!-- User List / Contacts (Left) -->
        <div
            style="width: 320px; border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; background: #fff;">
            <div class="chat-header"
                style="height: 60px; padding: 0 15px; display: flex; align-items: center; justify-content: space-between; background: #f0f2f5; border-bottom: 1px solid #e2e8f0;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div
                        style="width: 35px; height: 35px; border-radius: 50%; background: var(--brand-primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 900;">
                        <?= substr($user['name'], 0, 1) ?></div>
                    <span
                        style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($company['company_name']) ?></span>
                </div>
                <div style="display: flex; gap: 15px; color: #54656f;">
                    <i class="fa-solid fa-minus" onclick="minimizeChat()" style="cursor: pointer;"
                        title="Minimizar"></i>
                    <i class="fa-solid fa-xmark" onclick="toggleChatPanel()" style="cursor: pointer;"
                        title="Fechar"></i>
                </div>
            </div>
            <!-- Search -->
            <div style="padding: 10px; background: #fff;">
                <div
                    style="background: #f0f2f5; border-radius: 8px; padding: 6px 12px; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-magnifying-glass" style="color: #64748b; font-size: 0.9rem;"></i>
                    <input type="text" placeholder="Pesquisar ou começar uma nova conversa"
                        style="background: transparent; border: none; outline: none; font-size: 0.85rem; width: 100%;">
                </div>
            </div>
            <div id="chatUserList" style="flex: 1; overflow-y: auto;"></div>
        </div>

        <!-- Conversation Area (Right) -->
        <div style="flex: 1; display: flex; flex-direction: column; background: #efeae2; position: relative;">
            <!-- Chat Convo Header -->
            <div id="chatConvoHeader"
                style="height: 60px; padding: 0 20px; display: flex; align-items: center; background: #f0f2f5; border-bottom: 1px solid #e2e8f0; z-index: 10; display: none;">
                <div id="convoUserAvatar"
                    style="width: 40px; height: 40px; border-radius: 50%; background: #ddd; margin-right: 15px; overflow: hidden; display: flex; align-items: center; justify-content: center; font-weight: 900; color: white;">
                </div>
                <div style="flex: 1;">
                    <div id="convoUserName" style="font-weight: 700; color: #1e293b; font-size: 0.95rem;"></div>
                    <div id="convoUserStatus" style="font-size: 0.75rem; color: #64748b;"></div>
                </div>
                <div style="display: flex; gap: 20px; color: #54656f;">
                    <i class="fa-solid fa-magnifying-glass" style="cursor: pointer;"></i>
                    <i class="fa-solid fa-ellipsis-vertical" style="cursor: pointer;" onclick="toggleChatPanel()"></i>
                </div>
            </div>

            <!-- Messages Container -->
            <div id="chatMessages" class="chat-messages"
                style="flex: 1; padding: 20px; overflow-y: auto; background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png'); background-repeat: repeat; background-opacity: 0.4;">
                <div
                    style="height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #64748b; text-align: center;">
                    <div
                        style="background: rgba(255,255,255,0.8); padding: 2rem; border-radius: 2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                        <i class="fa-solid fa-shield-halved"
                            style="font-size: 3rem; color: #10b981; margin-bottom: 1rem;"></i>
                        <h3 style="margin-bottom: 0.5rem; color: #1e293b;">Cetusg Web</h3>
                        <p style="font-size: 0.85rem;">Suas mensagens são protegidas por criptografia.<br>Selecione um
                            contato para começar.</p>
                    </div>
                </div>
            </div>
            <!-- Input Area -->
            <div id="chatInputArea" style="padding: 10px 20px; background: #f0f2f5; display: none; position: relative;">
                <div id="emojiPicker"
                    style="display: none; position: absolute; bottom: 70px; left: 10px; background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 220px; z-index: 1000;">
                    <div
                        style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; font-size: 1.4rem; text-align: center;">
                        <span onclick="insertEmoji('😊')" style="cursor:pointer">😊</span>
                        <span onclick="insertEmoji('😂')" style="cursor:pointer">😂</span>
                        <span onclick="insertEmoji('😍')" style="cursor:pointer">😍</span>
                        <span onclick="insertEmoji('👍')" style="cursor:pointer">👍</span>
                        <span onclick="insertEmoji('🙏')" style="cursor:pointer">🙏</span>
                        <span onclick="insertEmoji('🤔')" style="cursor:pointer">🤔</span>
                        <span onclick="insertEmoji('👏')" style="cursor:pointer">👏</span>
                        <span onclick="insertEmoji('🔥')" style="cursor:pointer">🔥</span>
                        <span onclick="insertEmoji('🚀')" style="cursor:pointer">🚀</span>
                        <span onclick="insertEmoji('✅')" style="cursor:pointer">✅</span>
                    </div>
                </div>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <i class="fa-solid fa-face-smile" onclick="toggleEmojiPicker()"
                        style="color: #54656f; font-size: 1.5rem; cursor: pointer;"></i>
                    <i class="fa-solid fa-plus" onclick="document.getElementById('chatFilePicker').click()"
                        style="color: #54656f; font-size: 1.5rem; cursor: pointer;"></i>
                    <input type="file" id="chatFilePicker" style="display: none;" onchange="handleFileUpload(this)">

                    <div style="flex: 1; background: #fff; border-radius: 8px; padding: 5px 12px;">
                        <input type="text" id="chatInput" placeholder="Digite uma mensagem"
                            style="width: 100%; border: none; outline: none; font-size: 0.95rem; padding: 5px 0;"
                            onkeypress="if(event.key==='Enter') sendChatMessage()">
                    </div>

                    <i class="fa-solid fa-paper-plane" onclick="sendChatMessage()"
                        style="color: #54656f; font-size: 1.5rem; cursor: pointer;"></i>
                </div>
            </div>
        </div>
    </div>
    </div>

    <!-- Modals -->
    <?php if (!empty($birthdayPeople)): ?>
        <div id="birthdayModal" class="modal" style="z-index: 20000;">
            <div class="modal-content"
                style="text-align: center; max-width: 700px; border: 4px solid var(--brand-primary);">
                <div id="confettiContainer" style="position: absolute; inset: 0; pointer-events: none; overflow: hidden;">
                </div>
                <div id="bdContentContainer"></div>
            </div>
        </div>
    <?php endif; ?>

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

    <!-- Sons do Chat -->
    <audio id="audioSent" src="https://cdn.pixabay.com/audio/2022/01/18/audio_82137996f0.mp3" preload="auto"></audio>
    <audio id="audioReceived" src="https://cdn.pixabay.com/audio/2021/08/04/audio_0625c1539c.mp3" preload="auto"></audio>

    <script>
        // --- ESTADO GLOBAL ---
        const chatState = {
            isOpen: JSON.parse(localStorage.getItem('chat_open') || 'false'),
            isMinimized: JSON.parse(localStorage.getItem('chat_minimized') || 'false'),
            activeUserId: localStorage.getItem('chat_active_user'),
            lastTotalUnread: 0,
            isInitialLoad: true
        };

        const birthdayPeople = <?= json_encode($birthdayPeople) ?>;
        const currentUserId = '<?= $user['id'] ?>';
        const hasAnnouncement = <?= ($loginAnnouncement) ? 'true' : 'false' ?>;

        // --- FUNÇÕES DE CHAT (GLOBAIS) ---
        window.toggleChatPanel = function () {
            chatState.isOpen = !chatState.isOpen;
            const panel = document.getElementById('chatPanel');
            panel.style.display = chatState.isOpen ? 'flex' : 'none';
            localStorage.setItem('chat_open', chatState.isOpen);
            if (chatState.isOpen) {
                chatState.isMinimized = false;
                panel.classList.remove('chat-minimized');
                localStorage.setItem('chat_minimized', 'false');
                syncChatSystem(true);
            }
        };

        window.minimizeChat = function () {
            chatState.isMinimized = !chatState.isMinimized;
            const panel = document.getElementById('chatPanel');
            if (chatState.isMinimized) panel.classList.add('chat-minimized');
            else panel.classList.remove('chat-minimized');
            localStorage.setItem('chat_minimized', chatState.isMinimized);
        };

        async function loadUserList() {
            try {
                const r = await fetch('chat_api.php?action=list_users');
                if (!r.ok) return;
                const users = await r.json();
                const container = document.getElementById('chatUserList');
                let totalUnread = 0;
                container.innerHTML = users.map(u => {
                    totalUnread += parseInt(u.unread_count);
                    const isOnline = parseInt(u.is_online);
                    return `
                    <div onclick="openChatWith('${u.id}', '${u.name}', '${u.avatar_url}', ${isOnline})" style="padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #f5f5f5; display: flex; align-items: center; gap: 12px; background: ${chatState.activeUserId === u.id ? '#f0f2f5' : 'transparent'};">
                        <div style="width: 45px; height: 45px; border-radius: 50%; overflow: hidden; background: #ddd; position: relative; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-weight: 900; color: white;">
                            ${u.avatar_url ? `<img src="${u.avatar_url}" style="width:100%;height:100%;object-fit:cover;">` : u.name.substring(0, 1).toUpperCase()}
                            ${isOnline ? '<div style="position: absolute; bottom: 2px; right: 2px; width: 10px; height: 10px; border-radius: 50%; background: #10b981; border: 2px solid #fff;"></div>' : ''}
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                                <div style="font-weight: 600; font-size: 0.95rem; color: ${isOnline ? '#10b981' : '#475569'}; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${u.name}</div>
                                ${parseInt(u.unread_count) > 0 ? `<div style="background: #25d366; color: white; border-radius: 10px; padding: 2px 7px; font-size: 0.7rem; font-weight: 700;">${u.unread_count}</div>` : ''}
                            </div>
                            <div style="font-size: 0.8rem; color: #667781; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                ${isOnline ? 'Online' : 'Visto por último recentemente'}
                            </div>
                        </div>
                    </div>
                `;
                }).join('');
                updateBadge(totalUnread);
            } catch (e) { }
        }

        window.openChatWith = function (id, name, avatar, isOnline) {
            chatState.activeUserId = id;
            localStorage.setItem('chat_active_user', id);

            // Update Header
            document.getElementById('chatConvoHeader').style.display = 'flex';
            document.getElementById('convoUserName').innerText = name;
            document.getElementById('convoUserStatus').innerText = isOnline ? 'Online' : 'Visto por último recentemente';
            const avatarDiv = document.getElementById('convoUserAvatar');
            avatarDiv.innerHTML = avatar ? `<img src="${avatar}" style="width:100%;height:100%;object-fit:cover;">` : name.substring(0, 1).toUpperCase();
            avatarDiv.style.background = avatar ? 'transparent' : 'var(--brand-primary)';

            document.getElementById('chatInputArea').style.display = 'block';
            loadUserList();
            loadMessages();
        };

        async function loadMessages() {
            if (!chatState.activeUserId) return;
            try {
                const r = await fetch(`chat_api.php?action=get_messages&other_id=${chatState.activeUserId}`);
                if (!r.ok) return;
                const msgs = await r.json();

                // Marcar como lido e atualizar badge
                const hasUnread = msgs.some(m => m.sender_id !== currentUserId && m.is_read == 0);
                if (hasUnread) {
                    await fetch(`chat_api.php?action=mark_as_read&other_id=${chatState.activeUserId}`);
                    const ru = await fetch('chat_api.php?action=total_unread');
                    if (ru.ok) { const d = await ru.json(); updateBadge(d.count, true); }
                }

                const container = document.getElementById('chatMessages');
                container.innerHTML = msgs.map(m => {
                    let content = '';
                    if (m.type === 'image') {
                        content = `<img src="uploads/chat_files/${m.content}" style="max-width: 280px; border-radius: 8px; cursor: pointer;" onclick="window.open('uploads/chat_files/${m.content}')">`;
                    } else if (m.type === 'file') {
                        content = `<a href="uploads/chat_files/${m.content}" target="_blank" style="display: flex; align-items: center; gap: 8px; color: #000; text-decoration: none; background: rgba(0,0,0,0.05); padding: 12px; border-radius: 8px; border: 1px solid rgba(0,0,0,0.1);"><i class="fa-solid fa-file-pdf" style="font-size: 1.5rem; color: #ef4444;"></i> <div style="font-size: 0.8rem; overflow: hidden; text-overflow: ellipsis;">${m.content.substring(15)}</div></a>`;
                    } else {
                        content = `<div style="font-size: 0.95rem; color: #111b21; line-height: 1.4; word-break: break-all;">${m.content}</div>`;
                    }

                    const isSent = m.sender_id === currentUserId;
                    return `
                    <div style="display: flex; flex-direction: column; align-items: ${isSent ? 'flex-end' : 'flex-start'}; margin-bottom: 12px;">
                        <div style="max-width: 70%; padding: 8px 12px; border-radius: 8px; background: ${isSent ? '#d9fdd3' : '#fff'}; box-shadow: 0 1px 1px rgba(0,0,0,0.1); position: relative;">
                            ${content}
                            <div style="display: flex; justify-content: flex-end; align-items: center; gap: 4px; margin-top: 4px;">
                                <span style="font-size: 0.65rem; color: #667781;">${m.time_formatted.split(' às ')[1] || ''}</span>
                                ${isSent ? `<i class="fa-solid fa-check${m.is_read == 1 ? '-double' : ''}" style="font-size: 0.7rem; color: ${m.is_read == 1 ? '#53bdeb' : '#667781'};"></i>` : ''}
                            </div>
                        </div>
                    </div>
                `;
                }).join('');
                container.scrollTop = container.scrollHeight;
            } catch (e) { }
        }

        window.sendChatMessage = async function () {
            const input = document.getElementById('chatInput');
            const msg = input.value.trim();
            if (!msg || !chatState.activeUserId) return;
            try {
                const fd = new FormData();
                fd.append('receiver_id', chatState.activeUserId);
                fd.append('content', msg);
                input.value = '';
                document.getElementById('emojiPicker').style.display = 'none';
                document.getElementById('audioSent').play().catch(()=>{});
                await fetch('chat_api.php?action=send', { method: 'POST', body: fd });
                loadMessages();
            } catch (e) { }
        };

        window.toggleEmojiPicker = function () {
            const p = document.getElementById('emojiPicker');
            p.style.display = p.style.display === 'none' ? 'block' : 'none';
        };

        window.insertEmoji = function (emoji) {
            const input = document.getElementById('chatInput');
            input.value += emoji;
            input.focus();
        };

        window.handleFileUpload = async function (input) {
            if (!input.files || !input.files[0] || !chatState.activeUserId) return;
            const file = input.files[0];
            try {
                const fd = new FormData();
                fd.append('receiver_id', chatState.activeUserId);
                fd.append('file', file);
                document.getElementById('audioSent').play().catch(()=>{});
                await fetch('chat_api.php?action=upload_file', { method: 'POST', body: fd });
                input.value = '';
                loadMessages();
            } catch (e) { }
        };

        window.toggleChatPanel = function () {
            chatState.isOpen = !chatState.isOpen;
            if (chatState.isOpen) chatState.isMinimized = false;
            saveChatState();
            applyChatVisibility();
            if (chatState.isOpen) syncChatSystem(true);
        };

        window.minimizeChat = function () {
            chatState.isMinimized = true;
            chatState.isOpen = false;
            saveChatState();
            applyChatVisibility();
        };

        function saveChatState() {
            localStorage.setItem('chat_open', chatState.isOpen);
            localStorage.setItem('chat_minimized', chatState.isMinimized);
        }

        function applyChatVisibility() {
            const panel = document.getElementById('chatPanel');
            const bar = document.getElementById('chatPinnedBar');
            if (!panel || !bar) return;

            if (chatState.isOpen) {
                panel.style.display = 'flex';
                bar.style.display = 'none';
            } else if (chatState.isMinimized) {
                panel.style.display = 'none';
                bar.style.display = 'flex';
            } else {
                panel.style.display = 'none';
                bar.style.display = 'none';
            }
        }

        function updateBadge(count, skipSound = false) {
            const badge = document.getElementById('globalChatBadge');
            const minBadge = document.getElementById('minimizedBadge');
            if (badge) {
                badge.innerText = count;
                badge.style.display = count > 0 ? 'flex' : 'none';
            }
            if (minBadge) {
                minBadge.innerText = count;
                minBadge.style.display = count > 0 ? 'flex' : 'none';
            }

            // Som e vibração só quando realmente chega mensagem nova e não está lendo a conversa
            if (!chatState.isInitialLoad && !skipSound && count > chatState.lastTotalUnread) {
                document.getElementById('audioReceived').play().catch(()=>{});
                const icon = document.getElementById('chatGlobalIcon');
                if (icon) {
                    icon.classList.add('vibrar');
                    setTimeout(() => icon.classList.remove('vibrar'), 2000);
                }
            }

            chatState.lastTotalUnread = count;
            chatState.isInitialLoad = false;
        }

        async function syncChatSystem(force = false) {
            try {
                if (chatState.isOpen || force) {
                    await loadUserList();
                    if (chatState.activeUserId) await loadMessages();
                } else {
                    const r = await fetch('chat_api.php?action=total_unread');
                    if (r.ok) {
                        const d = await r.json();
                        updateBadge(d.count);
                    }
                }
            } catch (e) { }
        }

        // --- SEQUENCIAMENTO DE MODAIS ---
        window.showBirthdaySequence = function () {
            if (birthdayPeople.length > 0) {
                console.log("Iniciando timer de 7 segundos para aniversário...");
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
            let message = '';
            if (isMyBirthday) {
                message = `<h2 style="font-family:'Outfit'; margin-bottom:1.5rem; color: #EC4899; font-weight: 900; font-size: 2rem;"><?= addslashes(!empty($company['birthday_message_self']) ? $company['birthday_message_self'] : 'Parabéns! Sua existência é importante — que seu dia seja excelente! 🥳') ?></h2>`;
            } else {
                const plural = birthdayPeople.length > 1 ? 's' : '';
                const pluralVerbo = birthdayPeople.length > 1 ? 'fazem' : 'faz';
                const msgAll = `<?= addslashes(!empty($company['birthday_message_all']) ? $company['birthday_message_all'] : 'Hoje nosso{plural} colega{plural} {verbo} aniversário — vamos dar um parabéns! 🎉') ?>`
                    .replace('{plural}', plural).replace('{plural}', plural).replace('{verbo}', pluralVerbo);
                message = `<h2 style="font-family:'Outfit'; margin-bottom:1.5rem; color: var(--brand-primary); font-weight: 900; font-size: 1.8rem;">${msgAll}</h2>`;
            }

            let photosHtml = '<div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 24px; margin-bottom: 2rem;">';
            birthdayPeople.forEach(p => {
                const isMe = String(p.id) === String(currentUserId);
                photosHtml += `
                <div style="text-align: center;">
                    <div style="width:140px; height:140px; border-radius:50%; overflow:hidden; margin: 0 auto 1rem; border:4px solid ${isMe ? '#EC4899' : 'var(--brand-primary)'}; box-shadow: 0 15px 30px rgba(0,0,0,0.2); position: relative;">
                        ${p.avatar_url ? '<img src="' + p.avatar_url + '" style="width:100%;height:100%;object-fit:cover;">' : '<div style="background:var(--brand-soft);height:100%;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:3rem;color:var(--brand-primary);">' + p.name.substring(0, 2).toUpperCase() + '</div>'}
                        ${isMe ? '<div style="position:absolute; bottom:0; padding:4px 8px; background:#EC4899; color:white; font-size:0.7rem; font-weight:900; width:100%;">VOCÊ!</div>' : ''}
                    </div>
                    <div style="font-weight: 900; color: #0F172A; font-size: 1.1rem; letter-spacing: -0.5px;">${p.name}</div>
                </div>
            `;
            });
            photosHtml += '</div>';

            container.innerHTML = `
            <div style="padding: 1rem;">
                ${message}
                ${photosHtml}
                <div style="margin-top: 2rem;">
                    <button onclick="closeBirthdayModal()" class="btn-primary" style="padding: 16px 60px; border-radius: 100px; font-weight: 900; font-size: 1.1rem; letter-spacing: 2px;">FECHAR</button>
                </div>
            </div>
        `;
        };

        window.closeBirthdayModal = () => {
            document.getElementById('birthdayModal').classList.remove('active');
            const fd = new FormData();
            fd.append('action', 'dismiss_birthday');
            fetch('ajax_birthday.php', { method: 'POST', body: fd });
        };

        window.openPeixinhoChat = function () {
            if (!chatState.isOpen) toggleChatPanel();
            openChatWith('U_PEIXINHO', 'Peixinho (IA)', 'assets/img/peixinho.png');
        };

        // --- INICIALIZAÇÃO ---
        document.addEventListener('DOMContentLoaded', () => {
            applyChatVisibility();

            // Chat init
            setInterval(() => syncChatSystem(), 5000);
            syncChatSystem(true);

            // Modal sequence init
            if (!hasAnnouncement) {
                showBirthdaySequence();
            }
        });

        // --- SISTEMA DE PERFIL ---
        window.openMyProfile = async function() {
            document.getElementById('profileModal').classList.add('active');
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
                avatarDiv.innerHTML = p.avatar_url ? `<img src="${p.avatar_url}" style="width:100%;height:100%;object-fit:cover;">` : p.name.substring(0, 1).toUpperCase();
            } catch(e) {}
        };

        window.closeProfileModal = function() {
            document.getElementById('profileModal').classList.remove('active');
        };

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
            } catch(e) {
                alert('Erro ao salvar telefone.');
            } finally {
                btn.innerText = 'Salvar Telefone';
                btn.disabled = false;
            }
        };
    </script>
</body>

</html>