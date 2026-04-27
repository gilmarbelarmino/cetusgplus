<?php
if (!isset($_SESSION)) session_start();
require_once 'config.php';
require_once 'auth.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: login.php');
    exit;
}

// Verifica se o usuário tem privilégios totais de edição e visão no módulo de Informações
$isAdminInfo = in_array($user['role'], ['Administrador', 'Suporte Técnico', 'Recursos Humanos']);

// ======================== MIGRAÇÕES E TABELAS ========================
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS info_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(50) NOT NULL, -- 'general' ou 'sector'
        sector VARCHAR(100) DEFAULT '',
        content TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS info_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sector VARCHAR(100) NOT NULL,
        title VARCHAR(150) NOT NULL,
        url TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

// Inicializar Mensagem Geral caso não exista
$checkGen = $pdo->query("SELECT id FROM info_messages WHERE type = 'general' LIMIT 1")->fetch();
if (!$checkGen) {
    $pdo->exec("INSERT INTO info_messages (type, content) VALUES ('general', 'Bem-vindo ao Quadro de Informações Geral do Cetus.')");
}

// ======================== MANUSEIO DE POST (Ações) ========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAdminInfo) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_general') {
        $stmt = $pdo->prepare("UPDATE info_messages SET content = ? WHERE type = 'general'");
        $stmt->execute([$_POST['content']]);
        header('Location: ?page=informacoes&success=1'); exit;
    }

    if ($action === 'update_sector') {
        $sector = $_POST['sector'];
        $content = $_POST['content'];
        $checkSec = $pdo->prepare("SELECT id FROM info_messages WHERE type = 'sector' AND sector = ? LIMIT 1");
        $checkSec->execute([$sector]);
        if ($checkSec->fetch()) {
            $stmt = $pdo->prepare("UPDATE info_messages SET content = ? WHERE type = 'sector' AND sector = ?");
            $stmt->execute([$content, $sector]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO info_messages (type, sector, content) VALUES ('sector', ?, ?)");
            $stmt->execute([$sector, $content]);
        }
        $tab = urlencode($sector);
        header("Location: ?page=informacoes&tab=$tab&success=1"); exit;
    }

    if ($action === 'add_link') {
        $stmt = $pdo->prepare("INSERT INTO info_links (sector, title, url) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['sector'], $_POST['title'], $_POST['url']]);
        $tab = urlencode($_POST['sector']);
        header("Location: ?page=informacoes&tab=$tab&success=1"); exit;
    }

    if ($action === 'delete_link') {
        $stmt = $pdo->prepare("DELETE FROM info_links WHERE id = ?");
        $stmt->execute([$_POST['link_id']]);
        $tab = urlencode($_POST['tab_sector']);
        header("Location: ?page=informacoes&tab=$tab&success=1"); exit;
    }
}

// ======================== BUSCA DE DADOS ========================
// 1. Setores Disponíveis
if ($isAdminInfo) {
    $sectorsResult = $pdo->query("SELECT DISTINCT name FROM sectors ORDER BY name ASC")->fetchAll();
    $sectorsList = array_column($sectorsResult, 'name');
} else {
    // Colaborador comum vê apenas o seu setor
    $sectorsList = [$user['sector']];
}

// 2. Mensagem Geral
$generalMsg = $pdo->query("SELECT content FROM info_messages WHERE type = 'general' LIMIT 1")->fetchColumn();

// 3. Usuários por Setor (Com Foto e Nome)
$allUsersResult = $pdo->query("SELECT u.id, u.name, u.avatar_url, rh.role_name as position, u.sector 
                               FROM users u 
                               LEFT JOIN rh_employee_details rh ON BINARY u.id = BINARY rh.user_id 
                               ORDER BY u.name ASC")->fetchAll();

$usersBySector = [];
foreach ($allUsersResult as $u) {
    $sec = $u['sector'] ?: 'Sem Setor';
    $usersBySector[$sec][] = $u;
}

// 4. Mensagens Setoriais
$sectorMessagesResult = $pdo->query("SELECT sector, content FROM info_messages WHERE type = 'sector'")->fetchAll();
$sectorMessages = [];
foreach ($sectorMessagesResult as $sm) {
    $sectorMessages[$sm['sector']] = $sm['content'];
}

// 5. Links Setoriais
$sectorLinksResult = $pdo->query("SELECT * FROM info_links ORDER BY created_at DESC")->fetchAll();
$sectorLinks = [];
foreach ($sectorLinksResult as $sl) {
    $sectorLinks[$sl['sector']][] = $sl;
}

// Qual a aba ativa por padrão?
$activeTab = $_GET['tab'] ?? ($sectorsList[0] ?? '');
?>

<div style="padding: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 800; color: #0F172A; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-circle-info" style="color: var(--crm-purple);"></i>
                Informações e Quadro de Equipe
            </h1>
            <p style="color: #64748b; font-size: 0.9rem; margin-top: 0.25rem;">Quadro de avisos geral, informações do setor e lista de colaboradores da equipe.</p>
        </div>
    </div>

    <!-- ABAS DOS SETORES (Apenas se tiver mais de 1 para não poluir quem só tem o próprio) -->
    <?php if (count($sectorsList) > 1): ?>
    <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; overflow-x: auto; padding-bottom: 0.5rem; border-bottom: 2px solid #e2e8f0; pointer-events: auto;">
        <?php foreach ($sectorsList as $sec): ?>
            <button onclick="switchInfoTab('<?= md5($sec) ?>')" id="tab-btn-<?= md5($sec) ?>" class="tab-btn <?= $activeTab === $sec ? 'active' : '' ?>" style="flex-shrink: 0; min-width: max-content; pointer-events: auto;">
                <?= htmlspecialchars($sec) ?>
            </button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- MENSAGEM GERAL FIXA NO TOPO -->
    <div class="glass-panel" style="margin-bottom: 2rem; border-left: 4px solid var(--crm-purple); position: relative;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--crm-purple);"><i class="fa-solid fa-bullhorn"></i> Quadro de Avisos Gerais</h3>
            <?php if ($isAdminInfo): ?>
            <button class="btn-icon" onclick="openGenModal()"><i class="fa-solid fa-pen"></i> Editar</button>
            <?php endif; ?>
        </div>
        <div style="font-size: 1rem; color: #1e293b; line-height: 1.6; white-space: pre-wrap;"><?= htmlspecialchars($generalMsg) ?></div>
    </div>

    <!-- CONTEÚDO DE CADA SETOR -->
    <?php foreach ($sectorsList as $sec): ?>
    <?php 
        $secMd5 = md5($sec);
        $secMsg = $sectorMessages[$sec] ?? 'Nenhuma informação específica cadastrada para o setor.';
        $secLks = $sectorLinks[$sec] ?? [];
        $secUsrs = $usersBySector[$sec] ?? [];
    ?>
    <div id="content-<?= $secMd5 ?>" class="info-content <?= $activeTab === $sec || count($sectorsList) === 1 ? 'active' : '' ?>" style="display: <?= $activeTab === $sec || count($sectorsList) === 1 ? 'block' : 'none' ?>;">
        
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
            
            <!-- COLUNA ESQUERDA: Mensagem Setorial e Links -->
            <div style="display: flex; flex-direction: column; gap: 2rem;">
                
                <!-- Mensagem Setorial -->
                <div class="glass-panel" style="border-top: 4px solid #3B82F6; position: relative;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h3 style="font-size: 1.1rem; font-weight: 800; color: #3B82F6;"><i class="fa-solid fa-circle-exclamation"></i> Informações: <?= htmlspecialchars($sec) ?></h3>
                        <?php if ($isAdminInfo): ?>
                        <button class="btn-icon" onclick="openSecModal('<?= htmlspecialchars(addslashes($sec)) ?>')"><i class="fa-solid fa-pen"></i></button>
                        <?php endif; ?>
                    </div>
                    <!-- Span para capturar no JS ao editar -->
                    <div id="sec-text-<?= md5($sec) ?>" style="font-size: 0.95rem; color: #334155; line-height: 1.6; white-space: pre-wrap;"><?= htmlspecialchars($secMsg) ?></div>
                </div>

                <!-- Painel de Links Rápidos -->
                <div class="glass-panel">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h3 style="font-size: 1.1rem; font-weight: 800; color: #1e293b;"><i class="fa-solid fa-link"></i> Acessos Rápidos e Planilhas</h3>
                        <?php if ($isAdminInfo): ?>
                        <button class="btn-primary" style="padding: 0.5rem 1rem; font-size: 0.75rem;" onclick="openLinkModal('<?= htmlspecialchars(addslashes($sec)) ?>')"><i class="fa-solid fa-plus"></i> Novo Link</button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if(empty($secLks)): ?>
                        <p style="color: #94a3b8; font-size: 0.85rem;">Nenhum link cadastrado para este setor.</p>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;">
                            <?php foreach($secLks as $link): ?>
                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1rem; position: relative; transition: all 0.2s; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; height: 100px;">
                                    
                                    <?php if ($isAdminInfo): ?>
                                    <form method="POST" style="position: absolute; top: 5px; right: 5px;" onsubmit="return confirm('Deseja excluir este link?')">
                                        <input type="hidden" name="action" value="delete_link">
                                        <input type="hidden" name="link_id" value="<?= $link['id'] ?>">
                                        <input type="hidden" name="tab_sector" value="<?= htmlspecialchars($sec) ?>">
                                        <button type="submit" class="btn-icon" style="color: #ef4444; width: 24px; height: 24px; padding: 0;"><i class="fa-solid fa-xmark"></i></button>
                                    </form>
                                    <?php endif; ?>

                                    <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" style="text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; color: #0f172a; width: 100%;">
                                        <i class="fa-solid fa-arrow-up-right-from-square" style="color: var(--crm-purple); font-size: 1.25rem;"></i>
                                        <span style="font-weight: 700; font-size: 0.85rem; word-break: break-all; max-width: 100%;">- <?= htmlspecialchars($link['title']) ?> -</span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
            
            <!-- COLUNA DIREITA: Membros da Equipe -->
            <div class="glass-panel" style="align-self: start;">
                <h3 style="font-size: 1.1rem; font-weight: 800; color: #1e293b; margin-bottom: 1.5rem;"><i class="fa-solid fa-users"></i> Equipe do Setor</h3>
                
                <?php if(empty($secUsrs)): ?>
                    <p style="color: #94a3b8; font-size: 0.85rem;">Nenhum colaborador registrado neste setor.</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach($secUsrs as $usr): ?>
                        <div style="display: flex; align-items: center; gap: 1rem; padding: 0.75rem; background: #f8fafc; border-radius: 1rem; border: 1px solid #f1f5f9;">
                            <!-- Avatar Redondo -->
                            <?php if (!empty($usr['avatar_url'])): ?>
                                <img src="<?= htmlspecialchars($usr['avatar_url']) ?>" style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <?php else: ?>
                                <div style="width: 48px; height: 48px; border-radius: 50%; background: linear-gradient(135deg, #cbd5e1, #94a3b8); color: white; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: 800; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                                    <?= strtoupper(substr($usr['name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Nomes -->
                            <div>
                                <div style="font-weight: 800; color: #0f172a; font-size: 0.95rem;"><?= htmlspecialchars($usr['name']) ?></div>
                                <div style="font-size: 0.75rem; color: #64748b; font-weight: 600; text-transform: uppercase; margin-top: 2px;">
                                    <?= htmlspecialchars($usr['position'] ?: 'Colaborador') ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ================= MODAIS DE EDICAO (Só abrem se IsAdmin) ================= -->
<?php if ($isAdminInfo): ?>
<!-- Modal Aviso Geral -->
<div id="genModal" class="modal" style="display: none; align-items: center; justify-content: center;">
    <div class="modal-content" style="max-width: 600px;">
        <h2 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.5rem; color: var(--crm-purple);">Editar Quadro de Avisos Gerais</h2>
        <form method="POST">
            <input type="hidden" name="action" value="update_general">
            <div class="form-group">
                <label class="form-label">Conteúdo (Visível para todos da empresa)</label>
                <textarea name="content" class="form-textarea" rows="6" required><?= htmlspecialchars($generalMsg) ?></textarea>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('genModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Aviso Setorial -->
<div id="secModal" class="modal" style="display: none; align-items: center; justify-content: center;">
    <div class="modal-content" style="max-width: 600px;">
        <h2 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.5rem; color: #3B82F6;">Editar Informações do Setor</h2>
        <form method="POST">
            <input type="hidden" name="action" value="update_sector">
            <input type="hidden" name="sector" id="secModalSector">
            <div class="form-group">
                <label class="form-label">Conteúdo (Visível apenas para quem possui acesso ao setor: <span id="secModalTitle"></span>)</label>
                <textarea name="content" id="secModalContent" class="form-textarea" rows="6" required></textarea>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('secModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary" style="background:#3B82F6;">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Novo Link -->
<div id="linkModal" class="modal" style="display: none; align-items: center; justify-content: center;">
    <div class="modal-content" style="max-width: 500px;">
        <h2 style="font-size: 1.25rem; font-weight: 800; margin-bottom: 1.5rem; color: #1e293b;">Novo Hiperlink / Planilha</h2>
        <form method="POST">
            <input type="hidden" name="action" value="add_link">
            <input type="hidden" name="sector" id="linkModalSector">
            <div class="form-group">
                <label class="form-label">Título Visível</label>
                <input type="text" name="title" class="form-input" placeholder="Ex: Planilha de Reuniões" required>
            </div>
            <div class="form-group">
                <label class="form-label">URL de Acesso</label>
                <input type="url" name="url" class="form-input" placeholder="https://..." required>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('linkModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary">Salvar Link</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openGenModal() {
        document.getElementById('genModal').style.display = 'flex';
    }
    
    function openSecModal(sectorName) {
        document.getElementById('secModalSector').value = sectorName;
        document.getElementById('secModalTitle').innerText = sectorName;
        // Fetch current content from the spans
        const md5Sec = CryptoJS.MD5(sectorName).toString();
        const contentDiv = document.getElementById('sec-text-' + md5Sec);
        let currentText = contentDiv ? contentDiv.innerText : '';
        if (currentText.includes('Nenhuma informação específica')) currentText = '';
        document.getElementById('secModalContent').value = currentText;

        document.getElementById('secModal').style.display = 'flex';
    }

    function openLinkModal(sectorName) {
        document.getElementById('linkModalSector').value = sectorName;
        document.getElementById('linkModal').style.display = 'flex';
    }

    // Required CDNs for Javascript MD5 so we can sync the javascript selector with the PHP md5 loop
    if (typeof CryptoJS === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js';
        document.head.appendChild(script);
    }
</script>
<?php endif; ?>

<script>
    function switchInfoTab(tabMd5Hash) {
        document.querySelectorAll('.info-content').forEach(c => {
            c.style.display = 'none';
        });
        document.querySelectorAll('.tab-btn').forEach(b => {
            b.classList.remove('active');
        });
        
        const content = document.getElementById('content-' + tabMd5Hash);
        const btn = document.getElementById('tab-btn-' + tabMd5Hash);
        
        if(content) content.style.display = 'block';
        if(btn) btn.classList.add('active');
    }
    
    window.onclick = function(event) {
        const modals = ['genModal', 'secModal', 'linkModal'];
        modals.forEach(function(m) {
            const modalEl = document.getElementById(m);
            if (modalEl && event.target === modalEl) {
                modalEl.style.display = "none";
            }
        });
    }
</script>
