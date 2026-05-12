<?php
$pdo = \App\Core\Model::getConnection();
$compId = \App\Core\Auth::companyId();
$company_stmt = $pdo->prepare("SELECT * FROM company_settings WHERE id = ?");
$company_stmt->execute([$compId]);
$company = $company_stmt->fetch();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($company['company_name'] ?? 'Cetusg Plus') ?> - SaaS Management</title>
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

        /* ── SIDEBAR HEADER ── */
        .sidebar-header {
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.875rem;
            border-bottom: 1px solid #f1f5f9;
            background: linear-gradient(135deg, #fafbff 0%, #f8fafc 100%);
            min-height: 80px;
        }
        .logo-box {
            width: 48px;
            height: 48px;
            min-width: 48px;
            background: linear-gradient(135deg, var(--crm-purple), var(--crm-purple-dark));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            overflow: hidden;
            font-size: 1.4rem;
            font-weight: 900;
            box-shadow: 0 4px 12px rgba(99,102,241,0.25);
            flex-shrink: 0;
        }
        .logo-box img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 4px;
        }
        .company-info {
            flex: 1;
            min-width: 0;
        }
        .company-name {
            font-weight: 800;
            font-size: 0.975rem;
            color: #1e293b;
            line-height: 1.25;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .company-badge {
            display: inline-block;
            margin-top: 0.2rem;
            font-size: 0.65rem;
            font-weight: 700;
            color: var(--crm-purple);
            background: rgba(99,102,241,0.1);
            padding: 1px 7px;
            border-radius: 99px;
            letter-spacing: 0.03em;
        }

        .nav-links { padding: 1.5rem; flex: 1; display: flex; flex-direction: column; gap: 0.25rem; overflow-y: auto; }
        .nav-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: #64748b; text-decoration: none; border-radius: 0.75rem; font-weight: 600; transition: all 0.2s; font-size: 0.9rem; }
        .nav-link:hover { background: #f1f5f9; color: var(--crm-purple); }
        .nav-link.active { background: #e0e7ff; color: var(--crm-purple); }

        .user-footer { padding: 1.25rem 1.5rem; border-top: 1px solid #f1f5f9; display: flex; align-items: center; gap: 0.75rem; }
        .avatar { width: 40px; height: 40px; min-width: 40px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-weight: 800; color: #64748b; }

        /* ── MOBILE TOPBAR ── */
        .mobile-topbar {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 64px;
            background: white;
            border-bottom: 1px solid #e2e8f0;
            align-items: center;
            padding: 0 1.25rem;
            gap: 0.875rem;
            z-index: 999;
            box-shadow: 0 1px 8px rgba(0,0,0,0.06);
        }
        .mobile-logo-box {
            width: 38px;
            height: 38px;
            min-width: 38px;
            background: linear-gradient(135deg, var(--crm-purple), var(--crm-purple-dark));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            overflow: hidden;
            font-size: 1.1rem;
            font-weight: 900;
            box-shadow: 0 2px 8px rgba(99,102,241,0.25);
        }
        .mobile-logo-box img { width: 100%; height: 100%; object-fit: contain; padding: 3px; }
        .mobile-company-name {
            flex: 1;
            font-weight: 800;
            font-size: 0.9rem;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .hamburger-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 8px;
            color: #64748b;
            font-size: 1.1rem;
            transition: background 0.2s;
        }
        .hamburger-btn:hover { background: #f1f5f9; }
        .sidebar-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.35);
            z-index: 999;
            backdrop-filter: blur(2px);
        }
        .sidebar-overlay.active { display: block; }

        @media (max-width: 1024px) {
            .mobile-topbar { display: flex; }
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s cubic-bezier(0.4,0,0.2,1);
                top: 0;
                z-index: 1001;
            }
            .sidebar.active { transform: translateX(0); box-shadow: 8px 0 32px rgba(0,0,0,0.15); }
            .main-content { margin-left: 0; padding: 1rem; padding-top: calc(64px + 1rem); }
        }
        @media (max-width: 480px) {
            .main-content { padding: 0.75rem; padding-top: calc(64px + 0.75rem); }
        }
    </style>
    <link rel="stylesheet" href="<?= URL_BASE ?>/assets/css/widget_assistant.css">
</head>
<body>

    <!-- Mobile Topbar -->
    <div class="mobile-topbar">
        <button class="hamburger-btn" onclick="toggleSidebar()" aria-label="Abrir menu">
            <i class="fa-solid fa-bars"></i>
        </button>
        <div class="mobile-logo-box">
            <?php if (!empty($company['logo_url'])): ?>
                <img src="<?= htmlspecialchars($company['logo_url']) ?>" alt="Logo">
            <?php else: ?>
                <?= strtoupper(substr($company['company_name'] ?? 'C', 0, 1)) ?>
            <?php endif; ?>
        </div>
        <span class="mobile-company-name"><?= htmlspecialchars($company['company_name'] ?? 'Cetusg Plus') ?></span>
    </div>

    <!-- Overlay para fechar sidebar no mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <aside class="sidebar" id="mainSidebar">
        <div class="sidebar-header">
            <div class="logo-box">
                <?php if (!empty($company['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($company['logo_url']) ?>" alt="Logo">
                <?php else: ?>
                    <?= strtoupper(substr($company['company_name'] ?? 'C', 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div class="company-info">
                <div class="company-name" title="<?= htmlspecialchars($company['company_name'] ?? 'Cetusg Plus') ?>">
                    <?= htmlspecialchars($company['company_name'] ?? 'Cetusg Plus') ?>
                </div>
                <span class="company-badge">Gestão Integrada</span>
            </div>
        </div>

        <nav class="nav-links">
            <a href="<?= URL_BASE ?>/dashboard" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'dashboard') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-pie"></i> Dashboard
            </a>
            <a href="<?= URL_BASE ?>/informacoes" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'informacoes') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-circle-info"></i> Informações
            </a>
            <?php if (in_array('pesquisa', $user_menus)): ?>
            <a href="<?= URL_BASE ?>/pesquisa" class="nav-link <?= strpos($_SERVER['REQUEST_URI'], 'pesquisa') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-square-poll-vertical"></i> Pesquisa
                <span style="background: #6366f1; color: white; font-size: 0.6rem; padding: 2px 6px; border-radius: 10px; margin-left: auto; font-weight: 800;">NOVO</span>
            </a>
            <?php endif; ?>
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
            <div style="flex: 1; min-width: 0;">
                <div style="font-weight: 700; font-size: 0.85rem; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Usuário') ?></div>
                <div style="font-size: 0.7rem; color: #64748b;"><?= \App\Core\Auth::roleName() ?></div>
            </div>
            <a href="<?= URL_BASE ?>/logout" style="color: #94a3b8; flex-shrink: 0;"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </aside>

    <main class="main-content">
        <?php require $viewContent; ?>
    </main>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('mainSidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }
        // Fechar sidebar ao clicar em link (mobile)
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 1024) toggleSidebar();
            });
        });
    </script>

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
                    <div style="font-weight: 800; font-size: 1.1rem; letter-spacing: -0.5px;"><?= htmlspecialchars($company['company_name'] ?? 'Mensagens') ?></div>
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
