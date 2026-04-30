<?php
// ============================================================
// SEMANADA - PDF Viewer + Comentários Interativos
// ============================================================

// Migrações SaaS
try { $pdo->exec("ALTER TABLE semanada_uploads ADD COLUMN company_id INT NOT NULL DEFAULT 1"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE semanada_comments ADD COLUMN company_id INT NOT NULL DEFAULT 1"); } catch(Exception $e) {}

// --- LIMPEZA AUTOMÁTICA (30 DIAS) - Isolado por empresa ---
$compId = getCurrentUserCompanyId();
$thirtyDaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
$toDelete = $pdo->prepare("SELECT filename FROM semanada_uploads WHERE is_history = 1 AND uploaded_at < ? AND company_id = ?");
$toDelete->execute([$thirtyDaysAgo, $compId]);
$filesToDelete = $toDelete->fetchAll(PDO::FETCH_COLUMN);
foreach ($filesToDelete as $f) {
    if (file_exists(__DIR__ . '/../uploads/semanada/' . $f)) unlink(__DIR__ . '/../uploads/semanada/' . $f);
}
$pdo->prepare("DELETE FROM semanada_uploads WHERE is_history = 1 AND uploaded_at < ? AND company_id = ?")->execute([$thirtyDaysAgo, $compId]);

$uploadDir = __DIR__ . '/../uploads/semanada/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// --- PROCESSAMENTO DE AÇÕES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // UPLOAD DE PDF
    if ($_POST['action'] === 'upload_pdf' && isset($_FILES['pdf_file'])) {
        $file = $_FILES['pdf_file'];
        $expiryDate = $_POST['expiry_date'] ?? null;
        
        if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'pdf') {
            header('Location: ?page=semanada&error=tipo');
            exit;
        }
        
        // Mover PDFs anteriores para o histórico - filtrado por empresa
        $pdo->prepare("UPDATE semanada_uploads SET is_history = 1 WHERE is_history = 0 AND company_id = ?")->execute([$compId]);
        
        // Salvar novo arquivo
        $filename = 'semanada_' . date('Ymd_His') . '.pdf';
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            $stmt = $pdo->prepare("INSERT INTO semanada_uploads (filename, original_name, uploaded_by, expiry_date, is_history, company_id) VALUES (?, ?, ?, ?, 0, ?)");
            $stmt->execute([$filename, $file['name'], $user['id'], $expiryDate, $compId]);
            header('Location: ?page=semanada&success=1');
        } else {
            header('Location: ?page=semanada&error=upload');
        }
        exit;
    }
    
    // DELETAR PDF
    if ($_POST['action'] === 'delete_pdf') {
        $compId = getCurrentUserCompanyId();
        // Deletar arquivos físicos da empresa (baseado nos registros do banco)
        $stmt_f = $pdo->prepare("SELECT filename FROM semanada_uploads WHERE company_id = ?");
        $stmt_f->execute([$compId]);
        $files = $stmt_f->fetchAll(PDO::FETCH_COLUMN);
        foreach ($files as $f) {
            if (file_exists($uploadDir . $f)) unlink($uploadDir . $f);
        }
        $pdo->prepare("DELETE FROM semanada_comments WHERE company_id = ?")->execute([$compId]);
        $pdo->prepare("DELETE FROM semanada_uploads WHERE company_id = ?")->execute([$compId]);
        header('Location: ?page=semanada&success=2');
        exit;
    }
    
    // NOVO COMENTÁRIO
    if ($_POST['action'] === 'add_comment') {
        $compId = getCurrentUserCompanyId();
        $text = trim($_POST['comment_text'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
        if ($text !== '') {
            $stmt = $pdo->prepare("INSERT INTO semanada_comments (user_id, comment_text, parent_id, company_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user['id'], $text, $parentId, $compId]);
        }
        header('Location: ?page=semanada&success=3#comments');
        exit;
    }
    
    // DELETAR COMENTÁRIO
    if ($_POST['action'] === 'delete_comment') {
        $compId = getCurrentUserCompanyId();
        $cid = intval($_POST['comment_id']);
        $stmt = $pdo->prepare("DELETE FROM semanada_comments WHERE id = ? AND user_id = ? AND company_id = ?");
        $stmt->execute([$cid, $user['id'], $compId]);
        header('Location: ?page=semanada#comments');
        exit;
    }
}

// --- BUSCAR DADOS ---
$compId = getCurrentUserCompanyId();
// Verificar se o PDF atual expirou
$pdo->prepare("UPDATE semanada_uploads SET is_history = 1 WHERE is_history = 0 AND expiry_date IS NOT NULL AND expiry_date < CURDATE() AND company_id = ?")
    ->execute([$compId]);

$stmt_curr = $pdo->prepare("
    SELECT su.*, u.name as uploader_name, u.avatar_url as uploader_avatar 
    FROM semanada_uploads su 
    LEFT JOIN users u ON BINARY su.uploaded_by = BINARY u.id 
    WHERE su.is_history = 0 AND su.company_id = ?
    ORDER BY su.uploaded_at DESC LIMIT 1
");
$stmt_curr->execute([$compId]);
$uploadInfo = $stmt_curr->fetch(PDO::FETCH_ASSOC);

// Buscar Histórico
$stmt_hist = $pdo->prepare("
    SELECT su.*, u.name as uploader_name 
    FROM semanada_uploads su 
    LEFT JOIN users u ON BINARY su.uploaded_by = BINARY u.id 
    WHERE su.is_history = 1 AND su.company_id = ?
    ORDER BY su.uploaded_at DESC
");
$stmt_hist->execute([$compId]);
$history = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);

$currentPdf = $uploadInfo ? $uploadInfo['filename'] : null;
$pdfUrl = $currentPdf ? 'uploads/semanada/' . $currentPdf : null;

// Buscar comentários com dados do usuário - Isolado por empresa
$stmt_comm = $pdo->prepare("
    SELECT c.*, u.name as user_name, u.avatar_url as user_avatar
    FROM semanada_comments c
    LEFT JOIN users u ON BINARY c.user_id = BINARY u.id
    WHERE c.company_id = ?
    ORDER BY c.created_at ASC
");
$stmt_comm->execute([$compId]);
$comments = $stmt_comm->fetchAll(PDO::FETCH_ASSOC);

// Organizar comentários em árvore (pais e respostas)
$rootComments = [];
$childComments = [];
foreach ($comments as $c) {
    if ($c['parent_id']) {
        $childComments[$c['parent_id']][] = $c;
    } else {
        $rootComments[] = $c;
    }
}
?>

<style>
    .semanada-layout {
        display: flex;
        gap: 1.5rem;
        align-items: flex-start;
    }
    .semanada-main {
        flex: 1;
        min-width: 0;
    }
    .semanada-sidebar {
        width: 360px;
        flex-shrink: 0;
        position: sticky;
        top: 1rem;
        max-height: calc(100vh - 2rem);
        display: flex;
        flex-direction: column;
    }

    .semanada-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .semanada-actions {
        display: flex;
        gap: 0.75rem;
        align-items: center;
    }

    /* Upload info card */
    .upload-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        border: 1px solid #bae6fd;
        border-radius: 0.75rem;
        padding: 0.75rem 1.25rem;
        margin-bottom: 1.5rem;
    }
    .upload-info .avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--crm-purple);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        font-size: 0.8rem;
        flex-shrink: 0;
        overflow: hidden;
        border: 2px solid #fff;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .upload-info .avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .upload-info .details {
        font-size: 0.8rem;
        color: #334155;
    }
    .upload-info .details strong {
        color: #0369a1;
    }
    .upload-info .details .date {
        font-size: 0.7rem;
        color: #64748b;
        margin-top: 2px;
    }

    /* PDF Viewer */
    .pdf-viewer-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1.5rem;
    }
    .pdf-page-wrapper {
        width: 100%;
        background: #fff;
        border-radius: 1rem;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }
    .pdf-page-wrapper canvas {
        display: block;
        width: 100% !important;
        height: auto !important;
    }
    .pdf-page-label {
        background: #f8fafc;
        padding: 0.5rem 1rem;
        font-size: 0.7rem;
        font-weight: 700;
        color: #94a3b8;
        text-align: center;
        border-top: 1px solid #e2e8f0;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* Estado vazio */
    .empty-state {
        text-align: center;
        padding: 6rem 2rem;
        color: #94a3b8;
        background: #f8fafc;
        border-radius: 1.5rem;
        border: 2px dashed #e2e8f0;
    }
    .empty-state i { font-size: 3rem; margin-bottom: 1.5rem; display: block; color: #cbd5e1; }
    .empty-state h3 { font-size: 1.25rem; font-weight: 900; color: #64748b; margin-bottom: 0.5rem; }

    /* Sidebar de comentários */
    .comments-panel {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        flex: 1;
        min-height: 0;
    }
    .comments-header {
        background: #1e293b;
        color: #fff;
        padding: 1rem 1.25rem;
        font-weight: 900;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-shrink: 0;
    }
    .comments-list {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .comment-item {
        display: flex;
        gap: 0.6rem;
        align-items: flex-start;
    }
    .comment-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--crm-purple);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        font-size: 0.65rem;
        flex-shrink: 0;
        overflow: hidden;
    }
    .comment-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .comment-body {
        flex: 1;
        min-width: 0;
    }
    .comment-meta {
        display: flex;
        align-items: baseline;
        gap: 0.4rem;
        margin-bottom: 0.2rem;
        flex-wrap: wrap;
    }
    .comment-name {
        font-weight: 800;
        font-size: 0.75rem;
        color: #1e293b;
    }
    .comment-date {
        font-size: 0.65rem;
        color: #94a3b8;
    }
    .comment-text {
        font-size: 0.8rem;
        color: #334155;
        line-height: 1.4;
        word-wrap: break-word;
    }
    .comment-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 0.3rem;
    }
    .comment-actions button {
        background: none;
        border: none;
        font-size: 0.65rem;
        color: #94a3b8;
        cursor: pointer;
        font-weight: 700;
        padding: 0;
    }
    .comment-actions button:hover { color: var(--crm-purple); }
    .comment-replies {
        margin-top: 0.75rem;
        padding-left: 0.5rem;
        border-left: 2px solid #f1f5f9;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .comment-form {
        border-top: 1px solid #e2e8f0;
        padding: 0.75rem 1rem;
        display: flex;
        gap: 0.5rem;
        flex-shrink: 0;
        background: #f8fafc;
    }
    .comment-form input {
        flex: 1;
        border: 1px solid #e2e8f0;
        border-radius: 2rem;
        padding: 0.5rem 1rem;
        font-size: 0.8rem;
        outline: none;
        transition: border-color 0.2s;
    }
    .comment-form input:focus { border-color: var(--crm-purple); }
    .comment-form button {
        background: var(--crm-purple);
        color: #fff;
        border: none;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: transform 0.15s;
    }
    .comment-form button:hover { transform: scale(1.1); }

    .reply-form {
        margin-top: 0.5rem;
        display: flex;
        gap: 0.4rem;
    }
    .reply-form input {
        flex: 1;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        padding: 0.35rem 0.75rem;
        font-size: 0.75rem;
        outline: none;
    }
    .reply-form button {
        background: var(--crm-purple);
        color: #fff;
        border: none;
        border-radius: 50%;
        width: 28px;
        height: 28px;
        cursor: pointer;
        font-size: 0.7rem;
        flex-shrink: 0;
    }

    .pdf-loading {
        text-align: center;
        padding: 4rem;
    }
    .spinner {
        width: 48px; height: 48px;
        border: 4px solid #f1f5f9;
        border-top-color: var(--crm-purple);
        border-radius: 50%;
        display: inline-block;
        animation: spin 1s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    .no-comments {
        text-align: center;
        padding: 2rem 1rem;
        color: #94a3b8;
        font-size: 0.8rem;
    }

    @media (max-width: 1024px) {
        .semanada-layout { flex-direction: column; }
        .semanada-sidebar { width: 100%; position: static; max-height: 500px; }
    }
</style>

<!-- CABEÇALHO -->
<div class="semanada-header">
    <div>
        <h2 style="font-size: 1.5rem; font-weight: 900; color: var(--crm-black); display: flex; align-items: center; gap: 0.75rem; margin: 0;">
            <i class="fa-solid fa-calendar-week" style="color: var(--crm-purple);"></i>
            Semanada
        </h2>
        <p style="color: #64748b; font-size: 0.875rem; margin: 0.25rem 0 0 0;">Programação semanal importada via PDF</p>
    </div>
    <div class="semanada-actions">
        <button class="btn-secondary" onclick="toggleHistoryModal()" title="Histórico Semanada">
            <i class="fa-solid fa-clock-rotate-left"></i> Histórico
        </button>

        <form method="POST" enctype="multipart/form-data" id="uploadForm" style="display:none;">
            <input type="hidden" name="action" value="upload_pdf">
            <input type="date" name="expiry_date" id="expiryDateInput">
            <input type="file" name="pdf_file" id="pdfFileInput" accept="application/pdf"
                   onchange="openUploadDialog()">
        </form>

        <button class="btn-primary" style="background: var(--crm-yellow); color: var(--crm-black);"
                onclick="document.getElementById('pdfFileInput').click()">
            <i class="fa-solid fa-file-import"></i> Importar PDF
        </button>
        <?php if ($currentPdf): ?>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Remover o PDF atual e todos os comentários?');">
            <input type="hidden" name="action" value="delete_pdf">
            <button type="submit" class="btn-secondary" style="color: #ef4444; border-color: #fecaca;">
                <i class="fa-solid fa-trash"></i> Remover
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- MENSAGENS -->
<?php if (isset($_GET['success'])): ?>
<div style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10B981; color: #059669; padding: 0.75rem 1rem; border-radius: 0.75rem; margin-bottom: 1rem; font-weight: 700; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem;">
    <i class="fa-solid fa-circle-check"></i>
    <?php
    if ($_GET['success'] == '1') echo 'PDF importado com sucesso! Comentários anteriores foram limpos.';
    elseif ($_GET['success'] == '2') echo 'PDF e comentários removidos.';
    elseif ($_GET['success'] == '3') echo 'Comentário adicionado.';
    ?>
</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div style="background: rgba(239, 68, 68, 0.1); border: 1px solid #EF4444; color: #DC2626; padding: 0.75rem 1rem; border-radius: 0.75rem; margin-bottom: 1rem; font-weight: 700; font-size: 0.85rem;">
    <i class="fa-solid fa-circle-xmark"></i> Erro ao processar. Verifique o arquivo.
</div>
<?php endif; ?>

<!-- LAYOUT PRINCIPAL -->
<div class="semanada-layout">

    <!-- COLUNA PRINCIPAL (PDF) -->
    <div class="semanada-main">

        <?php if ($currentPdf && $uploadInfo): ?>

            <!-- Quem importou -->
            <div class="upload-info">
                <div class="avatar">
                    <?php if (!empty($uploadInfo['uploader_avatar'])): ?>
                        <img src="<?= htmlspecialchars($uploadInfo['uploader_avatar']) ?>" alt="">
                    <?php else: ?>
                        <?= strtoupper(substr($uploadInfo['uploader_name'] ?? '?', 0, 2)) ?>
                    <?php endif; ?>
                </div>
                <div class="details">
                    <strong><?= htmlspecialchars($uploadInfo['uploader_name'] ?? 'Usuário') ?></strong> importou este arquivo
                    <div class="date">
                        <i class="fa-regular fa-clock"></i>
                        <?= date('d/m/Y \à\s H:i', strtotime($uploadInfo['uploaded_at'])) ?>
                        &nbsp;•&nbsp;
                        <i class="fa-solid fa-file-pdf" style="color:#ef4444;"></i>
                        <?= htmlspecialchars($uploadInfo['original_name'] ?? $currentPdf) ?>
                    </div>
                </div>
            </div>

            <!-- PDF Viewer -->
            <div id="pdfLoading" class="pdf-loading">
                <div class="spinner"></div>
                <p style="margin-top: 1rem; font-weight: 700; color: #64748b;">Carregando PDF...</p>
            </div>
            <div id="pdfViewer" class="pdf-viewer-container" style="display: none;"></div>

            <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
            <script>
                pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                (async function() {
                    try {
                        const pdf = await pdfjsLib.getDocument('<?= $pdfUrl ?>').promise;
                        const viewer = document.getElementById('pdfViewer');
                        for (let i = 1; i <= pdf.numPages; i++) {
                            const page = await pdf.getPage(i);
                            const scale = 2;
                            const viewport = page.getViewport({ scale });
                            const wrapper = document.createElement('div');
                            wrapper.className = 'pdf-page-wrapper';
                            const canvas = document.createElement('canvas');
                            canvas.width = viewport.width;
                            canvas.height = viewport.height;
                            await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
                            wrapper.appendChild(canvas);
                            if (pdf.numPages > 1) {
                                const label = document.createElement('div');
                                label.className = 'pdf-page-label';
                                label.textContent = `Página ${i} de ${pdf.numPages}`;
                                wrapper.appendChild(label);
                            }
                            viewer.appendChild(wrapper);
                        }
                        document.getElementById('pdfLoading').style.display = 'none';
                        viewer.style.display = 'flex';
                    } catch (err) {
                        console.error(err);
                        document.getElementById('pdfLoading').innerHTML = '<p style="color:#ef4444;font-weight:700;">Erro ao carregar PDF.</p>';
                    }
                })();
            </script>

        <?php else: ?>

            <div class="empty-state">
                <i class="fa-solid fa-file-pdf"></i>
                <h3>Nenhum PDF importado</h3>
                <p style="font-size:0.875rem; max-width:400px; margin:0 auto; line-height:1.6;">
                    Clique em <strong>"Importar PDF"</strong> para enviar a programação semanal.
                </p>
                <button class="btn-primary" style="margin-top: 1.5rem;" onclick="document.getElementById('pdfFileInput').click()">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Selecionar PDF
                </button>
            </div>

        <?php endif; ?>
    </div>

    <!-- SIDEBAR DE COMENTÁRIOS -->
    <div class="semanada-sidebar" id="comments">
        <div class="comments-panel">
            <div class="comments-header">
                <i class="fa-solid fa-comments"></i>
                Comentários
                <span id="commentCount" style="margin-left:auto; font-size:0.7rem; opacity:0.7; font-weight:600;">0</span>
            </div>
            <div class="comments-list" id="commentsList"></div>
            <div class="comment-form" id="mainCommentForm">
                <input type="text" id="mainCommentInput" placeholder="Escreva um comentário..." autocomplete="off">
                <button onclick="sendComment()"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
        </div>
    </div>

</div>

<script>
    const CURRENT_USER_ID = '<?= $user['id'] ?>';
    const API_URL = 'semanada_api.php';
    let lastCommentCount = 0;

    function avatarHtml(avatar, name, size = 32) {
        const initials = (name || '?').substring(0, 2).toUpperCase();
        if (avatar) {
            return `<div class="comment-avatar" style="width:${size}px;height:${size}px;font-size:${size*0.2}rem;"><img src="${avatar}" alt=""></div>`;
        }
        return `<div class="comment-avatar" style="width:${size}px;height:${size}px;font-size:${size*0.2}rem;">${initials}</div>`;
    }

    function formatDate(dt) {
        const d = new Date(dt);
        return d.toLocaleDateString('pt-BR', {day:'2-digit',month:'2-digit'}) + ' ' + d.toLocaleTimeString('pt-BR', {hour:'2-digit',minute:'2-digit'});
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML.replace(/\n/g, '<br>');
    }

    function renderComments(comments) {
        const list = document.getElementById('commentsList');
        const wasAtBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 50;

        const roots = comments.filter(c => !c.parent_id);
        const children = {};
        comments.filter(c => c.parent_id).forEach(c => {
            if (!children[c.parent_id]) children[c.parent_id] = [];
            children[c.parent_id].push(c);
        });

        document.getElementById('commentCount').textContent = comments.length;

        if (roots.length === 0) {
            list.innerHTML = `<div class="no-comments"><i class="fa-regular fa-comment-dots" style="font-size:1.5rem;display:block;margin-bottom:0.5rem;"></i>Nenhum comentário ainda.<br>Seja o primeiro!</div>`;
            return;
        }

        let html = '';
        roots.forEach(c => {
            const replies = children[c.id] || [];
            html += `<div class="comment-item" data-id="${c.id}">
                ${avatarHtml(c.user_avatar, c.user_name)}
                <div class="comment-body">
                    <div class="comment-meta">
                        <span class="comment-name">${escapeHtml(c.user_name || 'Usuário')}</span>
                        <span class="comment-date">${formatDate(c.created_at)}</span>
                    </div>
                    <div class="comment-text">${escapeHtml(c.comment_text)}</div>
                    <div class="comment-actions">
                        <button onclick="toggleReply(${c.id})"><i class="fa-solid fa-reply"></i> Responder</button>
                        ${c.user_id === CURRENT_USER_ID ? `<button onclick="deleteComment(${c.id})" style="color:#ef4444;"><i class="fa-solid fa-trash"></i> Excluir</button>` : ''}
                    </div>
                    <div class="reply-form" id="reply-${c.id}" style="display:none;">
                        <input type="text" placeholder="Responder..." onkeydown="if(event.key==='Enter')sendReply(${c.id},this)">
                        <button onclick="sendReply(${c.id},this.previousElementSibling)"><i class="fa-solid fa-paper-plane"></i></button>
                    </div>
                    ${replies.length > 0 ? `<div class="comment-replies">${replies.map(r => `
                        <div class="comment-item" data-id="${r.id}">
                            ${avatarHtml(r.user_avatar, r.user_name, 26)}
                            <div class="comment-body">
                                <div class="comment-meta">
                                    <span class="comment-name">${escapeHtml(r.user_name || 'Usuário')}</span>
                                    <span class="comment-date">${formatDate(r.created_at)}</span>
                                </div>
                                <div class="comment-text">${escapeHtml(r.comment_text)}</div>
                                ${r.user_id === CURRENT_USER_ID ? `<div class="comment-actions"><button onclick="deleteComment(${r.id})" style="color:#ef4444;"><i class="fa-solid fa-trash"></i></button></div>` : ''}
                            </div>
                        </div>
                    `).join('')}</div>` : ''}
                </div>
            </div>`;
        });

        list.innerHTML = html;
        if (wasAtBottom) list.scrollTop = list.scrollHeight;
    }

    async function loadComments() {
        try {
            const res = await fetch(API_URL + '?action=list');
            const data = await res.json();
            if (data.success) {
                if (data.comments.length !== lastCommentCount) {
                    renderComments(data.comments);
                    lastCommentCount = data.comments.length;
                }
            }
        } catch (e) { console.error('Erro ao carregar comentários:', e); }
    }

    async function sendComment() {
        const input = document.getElementById('mainCommentInput');
        const text = input.value.trim();
        if (!text) return;
        input.value = '';
        input.disabled = true;

        try {
            const form = new FormData();
            form.append('action', 'add');
            form.append('comment_text', text);
            const res = await fetch(API_URL, { method: 'POST', body: form });
            const data = await res.json();
            if (data.success) {
                lastCommentCount = 0; // Forçar refresh
                await loadComments();
                document.getElementById('commentsList').scrollTop = document.getElementById('commentsList').scrollHeight;
            }
        } catch (e) { console.error(e); }
        input.disabled = false;
        input.focus();
    }

    async function sendReply(parentId, inputEl) {
        const text = inputEl.value.trim();
        if (!text) return;
        inputEl.value = '';

        try {
            const form = new FormData();
            form.append('action', 'add');
            form.append('comment_text', text);
            form.append('parent_id', parentId);
            await fetch(API_URL, { method: 'POST', body: form });
            lastCommentCount = 0;
            await loadComments();
        } catch (e) { console.error(e); }
    }

    async function deleteComment(id) {
        if (!confirm('Excluir este comentário?')) return;
        try {
            const form = new FormData();
            form.append('action', 'delete');
            form.append('comment_id', id);
            await fetch(API_URL, { method: 'POST', body: form });
            lastCommentCount = 0;
            await loadComments();
        } catch (e) { console.error(e); }
    }

    function toggleReply(id) {
        const el = document.getElementById('reply-' + id);
        el.style.display = el.style.display === 'none' ? 'flex' : 'none';
        if (el.style.display === 'flex') el.querySelector('input').focus();
    }

    // Enter para enviar no campo principal
    document.getElementById('mainCommentInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') sendComment();
    });

    // Carregar comentários e polling a cada 3s
    loadComments();
    setInterval(loadComments, 3000);

    // Funções de Modal
    function openUploadDialog() {
        const expiry = prompt("Defina a data de exclusão automática (AAAA-MM-DD):", "<?= date('Y-m-d', strtotime('+7 days')) ?>");
        if (expiry) {
            document.getElementById('expiryDateInput').value = expiry;
            document.getElementById('uploadForm').submit();
        }
    }

    function toggleHistoryModal() {
        const modal = document.getElementById('historyModal');
        modal.style.display = modal.style.display === 'none' ? 'flex' : 'none';
    }
</script>

<!-- MODAL DE HISTÓRICO -->
<div id="historyModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 10000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 600px; width: 100%; max-height: 80vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.25rem; font-weight: 900;"><i class="fa-solid fa-clock-rotate-left"></i> Histórico Semanada</h3>
            <button onclick="toggleHistoryModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
        </div>
        
        <?php if (empty($history)): ?>
            <p style="text-align:center; color:#64748b; padding:2rem;">Nenhum arquivo no histórico.</p>
        <?php else: ?>
            <table style="width:100%;">
                <thead>
                    <tr>
                        <th style="font-size:0.7rem; text-align:left;">Arquivo</th>
                        <th style="font-size:0.7rem; text-align:left;">Importado em</th>
                        <th style="font-size:0.7rem; text-align:right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                    <tr>
                        <td style="font-size:0.85rem; font-weight:600;"><?= htmlspecialchars($h['original_name']) ?></td>
                        <td style="font-size:0.75rem; color:#64748b;"><?= date('d/m/Y', strtotime($h['uploaded_at'])) ?></td>
                        <td style="text-align:right;">
                            <a href="uploads/semanada/<?= $h['filename'] ?>" target="_blank" class="btn-icon" style="display:inline-flex;" title="Visualizar">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="font-size:0.7rem; color:#ef4444; margin-top:1.5rem; font-weight:700;">* Arquivos com mais de 30 dias são removidos automaticamente.</p>
        <?php endif; ?>
    </div>
</div>
