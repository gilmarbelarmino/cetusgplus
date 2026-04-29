<?php
$pdo = \App\Core\Model::getConnection();
$company = $pdo->query("SELECT * FROM company_settings WHERE id = 1")->fetch();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($company['name'] ?? 'Cetusg Plus') ?> - SaaS Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= URL_BASE ?>/public/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --crm-purple: #6366f1;
            --crm-purple-dark: #4f46e5;
            --crm-bg: #f8fafc;
            --sidebar-width: 280px;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--crm-bg); margin: 0; display: flex; }
        .sidebar { width: var(--sidebar-width); height: 100vh; background: white; border-right: 1px solid #e2e8f0; position: fixed; left: 0; top: 0; display: flex; flex-direction: column; z-index: 1000; }
        .main-content { margin-left: var(--sidebar-width); flex: 1; padding: 2rem; min-height: 100vh; }
        
        .sidebar-header { padding: 1.5rem 2rem; display: flex; align-items: center; gap: 0.75rem; border-bottom: 1px solid #f1f5f9; }
        .logo-box { width: 40px; height: 40px; background: var(--crm-purple); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; overflow: hidden; }
        .logo-box img { width: 100%; height: 100%; object-fit: contain; background: white; }
        
        .nav-links { padding: 1.5rem; flex: 1; display: flex; flex-direction: column; gap: 0.25rem; overflow-y: auto; }
        .nav-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: #64748b; text-decoration: none; border-radius: 0.75rem; font-weight: 600; transition: all 0.2s; font-size: 0.9rem; }
        .nav-link:hover { background: #f1f5f9; color: var(--crm-purple); }
        .nav-link.active { background: #e0e7ff; color: var(--crm-purple); }
        
        .user-footer { padding: 1.5rem; border-top: 1px solid #f1f5f9; display: flex; align-items: center; gap: 0.75rem; }
        .avatar { width: 40px; height: 40px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-weight: 800; color: #64748b; }
        
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.active { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 1rem; }
        }
    </style>
    <link rel="stylesheet" href="<?= URL_BASE ?>/assets/css/widget_assistant.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo-box">
                <?php if (!empty($company['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($company['logo_url']) ?>" alt="Logo">
                <?php else: ?>
                    <?= substr($company['name'] ?? 'C', 0, 1) ?>
                <?php endif; ?>
            </div>
            <div style="font-weight: 800; font-size: 1.1rem; color: #1e293b; line-height: 1.2;">
                <?= htmlspecialchars($company['name'] ?? 'Cetusg Plus') ?>
            </div>
        </div>
        
        <nav class="nav-links">
            <a href="<?= URL_BASE ?>/dashboard" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'dashboard') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-pie"></i> Dashboard
            </a>
            <a href="<?= URL_BASE ?>/informacoes" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'informacoes') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-circle-info"></i> Informações
            </a>
            <a href="<?= URL_BASE ?>/chamados" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'chamados') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-ticket"></i> Chamados
            </a>
            <a href="<?= URL_BASE ?>/rh" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/rh') !== false && strpos($_SERVER['REQUEST_URI'], 'voluntariado') === false ? 'active' : '' ?>">
                <i class="fa-solid fa-user-tie"></i> Recursos Humanos
            </a>
            <a href="<?= URL_BASE ?>/rh/voluntariado" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'voluntariado') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-hand-holding-heart"></i> Voluntariado
            </a>
            <a href="<?= URL_BASE ?>/patrimonio" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'patrimonio') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-vault"></i> Patrimônio
            </a>
            <a href="<?= URL_BASE ?>/emprestimos" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'emprestimos') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-handshake-angle"></i> Empréstimos
            </a>
            <a href="<?= URL_BASE ?>/orcamentos" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'orcamentos') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-file-invoice-dollar"></i> Orçamentos
            </a>
            <a href="<?= URL_BASE ?>/locacao_salas" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'locacao_salas') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-building-circle-check"></i> Locação Salas
            </a>
            <a href="<?= URL_BASE ?>/semanada" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'semanada') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-week"></i> Semanada
            </a>
            <a href="<?= URL_BASE ?>/relatorios" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'relatorios') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-line"></i> Relatórios BI
            </a>
            <a href="<?= URL_BASE ?>/tecnologia" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'tecnologia') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-microchip"></i> Tecnologia
            </a>
            
            <div style="margin-top: 1rem; font-size: 0.7rem; font-weight: 800; color: #cbd5e1; text-transform: uppercase; padding-left: 1rem;">Administração</div>
            <a href="<?= URL_BASE ?>/usuarios" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'usuarios') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-users"></i> Usuários
            </a>
            <a href="<?= URL_BASE ?>/configuracoes" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], '/configuracoes') !== false && strpos($_SERVER['REQUEST_URI'], 'roles') === false ? 'active' : '' ?>">
                <i class="fa-solid fa-sliders"></i> Configurações
            </a>
            <a href="<?= URL_BASE ?>/configuracoes/roles" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'roles') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-shield-halved"></i> Permissões
            </a>
        </nav>
        
        <div class="user-footer">
            <div class="avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?></div>
            <div style="flex: 1;">
                <div style="font-weight: 700; font-size: 0.85rem; color: #1e293b;"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuário') ?></div>
                <div style="font-size: 0.7rem; color: #64748b;"><?= \App\Core\Auth::roleName() ?></div>
            </div>
            <a href="<?= URL_BASE ?>/logout" style="color: #94a3b8;"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </aside>

    <main class="main-content">
        <?php require $viewContent; ?>
    </main>

    <!-- Widget Assistente Cetusg (Bottom Left) -->
    <div id="widget-launcher" title="Assistente Cetusg">
        <div class="widget-badge"></div>
        <i class="fa-solid fa-robot"></i>
    </div>

    <div id="widget-panel">
        <div class="widget-header">
            <div class="bot-info">
                <img src="<?= URL_BASE ?>/assets/img/peixinho.png" alt="Bot">
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

    <!-- Chat Arrastão Tech Launcher (Bottom Right) -->
    <div id="chatPinnedBar" onclick="ChatCore.toggle()" class="chat-pinned-bar" title="Chat Corporativo">
        <i class="fa-solid fa-comments"></i>
        <div id="minimizedBadge" class="chat-badge" style="display: none;">0</div>
    </div>

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

    <script src="<?= URL_BASE ?>/assets/js/chat_core.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            ChatCore.init('<?= $_SESSION['user_id'] ?>');
        });
    </script>
</body>
</html>
