<?php
// Migrações SaaS
try { $pdo->exec("ALTER TABLE tickets ADD COLUMN company_id INT NOT NULL DEFAULT 1"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE ticket_responses ADD COLUMN company_id INT NOT NULL DEFAULT 1"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE ticket_pauses ADD COLUMN company_id INT NOT NULL DEFAULT 1"); } catch(Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_ticket') {
    $compId = getCurrentUserCompanyId();
    $customDate = !empty($_POST['created_at']) ? $_POST['created_at'] : date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("INSERT INTO tickets (id, asset_id, title, description, priority, requester_id, sector, unit_id, status, company_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Aberto', ?, ?)");
    $stmt->execute(['T' . time(), $_POST['asset_id'] ?: null, $_POST['title'], $_POST['description'], $_POST['priority'], $_POST['requester_id'], $_POST['sector'], $_POST['unit_id'], $compId, $customDate]);
    header('Location: ?page=chamados&success=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'close_ticket') {
    $ticket_id = $_POST['ticket_id'];
    $resolution = $_POST['resolution']; // 'solucionado', 'pendente', 'sem_solucao'
    $technician_name = $_POST['technician_name'] ?? '';

    $compId = getCurrentUserCompanyId();
    // Buscar asset_id e dados do solicitante para o E-mail
    $t = $pdo->prepare("
        SELECT t.asset_id, t.title, t.description, u.name as requester_name, u.email as requester_email, a.name as asset_name
        FROM tickets t
        LEFT JOIN users u ON CONVERT(t.requester_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(u.id USING utf8mb4) COLLATE utf8mb4_unicode_ci
        LEFT JOIN assets a ON t.asset_id = a.id
        WHERE t.id = ? AND t.company_id = ?
    ");
    $t->execute([$ticket_id, $compId]);
    $ticket_data = $t->fetch();

    $new_status = 'Concluído';
    $release_asset = false;

    if ($resolution === 'solucionado') {
        $new_status = 'Concluído';
        $release_asset = true;
    } elseif ($resolution === 'pendente') {
        $new_status = 'Pendente';
        $release_asset = false;
    } elseif ($resolution === 'sem_solucao') {
        $new_status = 'Sem Solução';
        $release_asset = true;
    }

    $final_closer = !empty($technician_name) ? trim($technician_name) : $user['name'];

    $stmt = $pdo->prepare("UPDATE tickets SET status = ?, closed_by = ?, closed_at = NOW() WHERE id = ? AND company_id = ?");
    $stmt->execute([$new_status, $final_closer, $ticket_id, $compId]);

    if ($release_asset && $ticket_data && $ticket_data['asset_id']) {
        $pdo->prepare("UPDATE assets SET status = 'Ativo' WHERE id = ? AND company_id = ?")->execute([$ticket_data['asset_id'], $compId]);
    }

    // Disparo de E-mail Automático em Segundo Plano
    if (($resolution === 'solucionado' || $resolution === 'sem_solucao') && $ticket_data && !empty($ticket_data['requester_email'])) {
        try {
            if(!class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
                require_once __DIR__ . '/../vendor/autoload.php';
            }
            
            // Buscar informações da empresa
            $stmtC = $pdo->prepare("SELECT company_name, logo_url FROM company_settings WHERE id = ?");
            $stmtC->execute([$compId]);
            $comp = $stmtC->fetch();
            $companyName = $comp['company_name'] ?? 'CetusG';
            $logoUrl = $comp['logo_url'] ? 'https://support.cetusg.com/' . ltrim($comp['logo_url'], '/') : '';

            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'tecnologia.arrastao@gmail.com';
            $mail->Password   = 'xjadihsbebkssjzh'; // Senha de App
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom('tecnologia.arrastao@gmail.com', $companyName);
            $mail->addAddress($ticket_data['requester_email'], $ticket_data['requester_name']);

            $mail->isHTML(true);
            $closedAt = date('d/m/Y H:i');
            
            // Assunto: Nome da pessoa - Chamado Tecnico - data terminação
            $mail->Subject = "{$ticket_data['requester_name']} - Chamado Técnico - {$closedAt}";
            
            $statusText = $new_status === 'Concluído' ? '<span style="color: #28a745; font-weight: bold;">✅ Concluído</span>' : '<span style="color: #ffc107; font-weight: bold;">⚠️ Sem Solução</span>';
            
            $logoHtml = $logoUrl ? "<div style='text-align: center; margin-bottom: 20px;'><img src='{$logoUrl}' alt='{$companyName}' style='max-height: 80px;' /></div>" : "";
            
            $assetItem = !empty($ticket_data['asset_name']) ? "<li style='margin-bottom: 12px;'><strong>💻 Produto Vinculado:</strong> {$ticket_data['asset_name']}</li>" : "";

            $mail->Body = "
                <div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; background-color: #f4f6f9; padding: 20px; border-radius: 10px;'>
                    {$logoHtml}
                    <div style='background-color: #ffffff; border-top: 4px solid #0056b3; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.05);'>
                        <div style='padding: 30px;'>
                            <h2 style='margin-top: 0; color: #333; font-size: 22px; text-align: center;'>Atualização de Chamado</h2>
                            <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                            <p style='font-size: 16px; color: #555;'>Olá, <strong>{$ticket_data['requester_name']}</strong>!</p>
                            <p style='font-size: 15px; color: #666; margin-bottom: 25px;'>O seu chamado no setor de tecnologia acaba de ser atualizado no nosso sistema. Confira os detalhes abaixo:</p>
                            
                            <div style='background-color: #f8f9fa; padding: 20px; border-radius: 6px; border-left: 4px solid #17a2b8;'>
                                <ul style='list-style-type: none; padding: 0; margin: 0; font-size: 15px;'>
                                    <li style='margin-bottom: 12px;'><strong>🎫 Título:</strong> {$ticket_data['title']}</li>
                                    <li style='margin-bottom: 12px;'><strong>📝 Descrição:</strong> " . nl2br(htmlspecialchars($ticket_data['description'])) . "</li>
                                    {$assetItem}
                                    <li style='margin-bottom: 12px;'><strong>🔄 Status:</strong> {$statusText}</li>
                                    <li style='margin-bottom: 12px;'><strong>📅 Fechado em:</strong> {$closedAt}</li>
                                    <li style='margin-bottom: 0;'><strong>👨‍💻 Técnico Responsável:</strong> {$final_closer}</li>
                                </ul>
                            </div>
                        </div>
                        <div style='background-color: #e9ecef; padding: 15px 20px; text-align: center; font-size: 13px; color: #6c757d; border-top: 1px solid #dee2e6;'>
                            <p style='margin: 0;'>Esta é uma mensagem automática do sistema <strong>{$companyName}</strong>.<br>Por favor, não responda a este e-mail.</p>
                        </div>
                    </div>
                </div>
            ";

            $mail->send();
        } catch (Exception $e) {
            if(class_exists('ErrorLogger')) ErrorLogger::log("Erro E-mail: " . $mail->ErrorInfo, 'WARNING');
        }
    }

    // WhatsApp é tratado 100% via JavaScript no frontend

    header('Location: ?page=chamados&success=' . ($new_status == 'Pendente' ? '3' : '2'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_ticket') {
    $ticket_id = $_POST['ticket_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $priority = $_POST['priority'];
    $asset_id = $_POST['asset_id'] ?: null;

    $compId = getCurrentUserCompanyId();
    $stmt = $pdo->prepare("UPDATE tickets SET title = ?, description = ?, priority = ?, asset_id = ? WHERE id = ? AND company_id = ?");
    $stmt->execute([$title, $description, $priority, $asset_id, $ticket_id, $compId]);
    
    header('Location: ?page=chamados&success=4');
    exit;
}

// ─ Pendenciar chamado (pausa o SLA)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pendenciar_ticket') {
    $compId = getCurrentUserCompanyId();
    $ticket_id = $_POST['ticket_id'];
    $reason    = trim($_POST['reason'] ?? 'Aguardando peça/informação');
    $pdo->prepare("UPDATE tickets SET status = 'Pendente' WHERE id = ? AND company_id = ?")->execute([$ticket_id, $compId]);
    $pdo->prepare("INSERT INTO ticket_pauses (ticket_id, paused_at, reason, paused_by, company_id) VALUES (?, NOW(), ?, ?, ?)")
        ->execute([$ticket_id, $reason, $user['name'], $compId]);
    header('Location: ?page=chamados&success=5'); exit;
}

// ─ Reativar da pendência (retoma o SLA)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reativar_ticket') {
    $compId = getCurrentUserCompanyId();
    $ticket_id = $_POST['ticket_id'];
    $pdo->prepare("UPDATE tickets SET status = 'Aberto' WHERE id = ? AND company_id = ?")->execute([$ticket_id, $compId]);
    $pdo->prepare("UPDATE ticket_pauses SET resumed_at = NOW(), resumed_by = ? WHERE ticket_id = ? AND resumed_at IS NULL AND company_id = ?")
        ->execute([$user['name'], $ticket_id, $compId]);
    header('Location: ?page=chamados&success=6'); exit;
}

$compId = getCurrentUserCompanyId();

// Filtro baseado no perfil do usuário e status - Agora livre se tiver acesso ao menu
$conditions = ["t.company_id = ?"];
$params = [$compId];

$show_all = isset($_GET['all']) && $_GET['all'] == '1';
if (!$show_all) {
    $conditions[] = "(t.status = 'Aberto' OR t.status = 'Pendente')";
}

$query = "SELECT t.*,
          u.name as requester_name, u.avatar_url as requester_avatar, u.access_number as requester_phone,
          un.name as unit_name, a.name as asset_name, c_user.avatar_url as closer_avatar,
          COALESCE(
            TIMESTAMPDIFF(MINUTE, t.created_at, t.closed_at)
            - COALESCE((
                SELECT SUM(TIMESTAMPDIFF(MINUTE, tp.paused_at, COALESCE(tp.resumed_at, NOW())))
                FROM ticket_pauses tp WHERE tp.ticket_id = t.id
              ), 0),
            TIMESTAMPDIFF(MINUTE, t.created_at, t.closed_at)
          ) as sla_minutes,
          (SELECT reason FROM ticket_pauses WHERE ticket_id = t.id AND resumed_at IS NULL ORDER BY paused_at DESC LIMIT 1) as pending_reason,
          (SELECT paused_at FROM ticket_pauses WHERE ticket_id = t.id AND resumed_at IS NULL ORDER BY paused_at DESC LIMIT 1) as pending_since
          FROM tickets t 
          LEFT JOIN users u ON CONVERT(t.requester_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(u.id USING utf8mb4) COLLATE utf8mb4_unicode_ci 
          LEFT JOIN units un ON CONVERT(t.unit_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(un.id USING utf8mb4) COLLATE utf8mb4_unicode_ci 
          LEFT JOIN assets a ON t.asset_id = a.id
          LEFT JOIN users c_user ON CONVERT(t.closed_by USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(c_user.name USING utf8mb4) COLLATE utf8mb4_unicode_ci
          WHERE " . implode(" AND ", $conditions) . " 
          ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$users_stmt = $pdo->prepare("SELECT u.id, u.name, u.email, u.sector, u.role, u.unit_id, u.avatar_url, un.name as unit_name FROM users u LEFT JOIN units un ON CONVERT(u.unit_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(un.id USING utf8mb4) COLLATE utf8mb4_unicode_ci WHERE u.company_id = ? ORDER BY u.name");
$users_stmt->execute([$compId]);
$users = $users_stmt->fetchAll();

$units_stmt = $pdo->prepare("SELECT * FROM units WHERE company_id = ? ORDER BY name");
$units_stmt->execute([$compId]);
$units = $units_stmt->fetchAll();

$sectors_stmt = $pdo->prepare("SELECT s.id, s.name, s.unit_id FROM sectors s WHERE s.company_id = ? ORDER BY s.name");
$sectors_stmt->execute([$compId]);
$sectors = $sectors_stmt->fetchAll();

$assets_stmt = $pdo->prepare("SELECT id, name, patrimony_id FROM assets WHERE company_id = ? ORDER BY name");
$assets_stmt->execute([$compId]);
$assets = $assets_stmt->fetchAll();
?>

<style>
.autocomplete-suggestion-item {
    padding: 0.75rem 1rem;
    cursor: pointer;
    font-size: 0.85rem;
    transition: background-color 0.2s, color 0.2s;
    border-bottom: 1px solid rgba(226, 232, 240, 0.5);
}
.autocomplete-suggestion-item:last-child {
    border-bottom: none;
}
.autocomplete-suggestion-item:hover {
    background-color: rgba(91, 33, 182, 0.05);
    color: var(--crm-purple, #5B21B6);
}
.autocomplete-suggestions::-webkit-scrollbar {
    width: 6px;
}
.autocomplete-suggestions::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 4px;
}

/* --- Novo Layout de Chamados --- */
.ch-stats-bar { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
.ch-stat-card { flex:1; min-width: 120px; background: var(--bg-card, #fff); border: 1px solid var(--border-color, #e2e8f0); border-radius: 1rem; padding: 1rem 1.25rem; text-align: center; transition: all 0.2s; cursor: pointer; }
.ch-stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.ch-stat-card.active { border-width: 2px; }
.ch-stat-num { font-size: 1.75rem; font-weight: 900; line-height: 1; }
.ch-stat-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 0.25rem; }

.ch-search-bar { display: flex; gap: 0.75rem; margin-bottom: 1.5rem; flex-wrap: wrap; align-items: center; }
.ch-search-input { flex: 1; min-width: 200px; padding: 0.65rem 1rem 0.65rem 2.5rem; border: 1px solid var(--border-color, #e2e8f0); border-radius: 0.75rem; font-size: 0.85rem; background: var(--bg-card, #fff); color: var(--text-main); outline: none; transition: border-color 0.2s; }
.ch-search-input:focus { border-color: #5B21B6; }
.ch-filter-select { padding: 0.65rem 1rem; border: 1px solid var(--border-color, #e2e8f0); border-radius: 0.75rem; font-size: 0.8rem; background: var(--bg-card, #fff); color: var(--text-main); cursor: pointer; min-width: 130px; }

.ch-ticket-list { display: flex; flex-direction: column; gap: 0.5rem; }
.ch-ticket-row { display: grid; grid-template-columns: 50px 1fr 180px 110px 130px 180px 100px; gap: 0.75rem; align-items: center; background: var(--bg-card, #fff); border: 1px solid var(--border-color, #e2e8f0); border-radius: 0.85rem; padding: 0.85rem 1rem; transition: all 0.15s; }
.ch-ticket-row:hover { border-color: #c4b5fd; box-shadow: 0 2px 8px rgba(91, 33, 182, 0.06); }
.ch-ticket-row.status-concluido { opacity: 0.7; }
.ch-ticket-row.status-concluido:hover { opacity: 1; }

.ch-ticket-id { font-family: monospace; font-size: 0.7rem; color: var(--text-soft); text-align: center; background: var(--bg-main, #f8fafc); padding: 0.3rem; border-radius: 0.4rem; }
.ch-ticket-title { font-weight: 700; font-size: 0.88rem; color: var(--text-main); line-height: 1.3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ch-ticket-title small { display: block; font-weight: 500; font-size: 0.72rem; color: var(--text-soft); margin-top: 0.15rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ch-ticket-requester { display: flex; align-items: center; gap: 0.5rem; }
.ch-ticket-requester img { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); flex-shrink:0; }
.ch-ticket-requester .ch-avatar-placeholder { width: 30px; height: 30px; border-radius: 50%; background: var(--bg-main); display: flex; align-items: center; justify-content: center; font-size: 11px; color: var(--text-main); border: 1px solid var(--border-color); flex-shrink:0; }
.ch-ticket-requester span { font-weight: 600; font-size: 0.8rem; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.ch-badge { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.3rem 0.65rem; border-radius: 0.5rem; font-size: 0.72rem; font-weight: 700; white-space: nowrap; }
.ch-badge-aberto { background: #EEF2FF; color: #4F46E5; }
.ch-badge-concluido { background: #ECFDF5; color: #059669; }
.ch-badge-pendente { background: #FFF7ED; color: #D97706; }
.ch-badge-semsolucao { background: #FEF2F2; color: #DC2626; }
.ch-badge-baixa { background: #F0FDF4; color: #16A34A; }
.ch-badge-media { background: #EFF6FF; color: #2563EB; }
.ch-badge-alta { background: #FFF7ED; color: #EA580C; }
.ch-badge-critica { background: #FEF2F2; color: #DC2626; }

.ch-ticket-date { font-size: 0.72rem; color: var(--text-soft); line-height: 1.4; }
.ch-ticket-date strong { color: var(--text-main); font-weight: 700; }
.ch-ticket-actions { display: flex; gap: 0.3rem; justify-content: center; }

.ch-pagination { display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 1.5rem; flex-wrap: wrap; }
.ch-page-btn { padding: 0.4rem 0.85rem; border: 1px solid var(--border-color, #e2e8f0); border-radius: 0.5rem; background: var(--bg-card, #fff); color: var(--text-main); font-size: 0.8rem; cursor: pointer; font-weight: 600; transition: all 0.15s; }
.ch-page-btn:hover { border-color: #5B21B6; color: #5B21B6; }
.ch-page-btn.active { background: #5B21B6; color: #fff; border-color: #5B21B6; }
.ch-page-info { font-size: 0.78rem; color: var(--text-soft); font-weight: 600; }
.ch-empty { text-align: center; padding: 3rem; color: var(--text-soft); font-size: 0.9rem; }

.ch-thead { display: grid; grid-template-columns: 50px 1fr 180px 110px 130px 180px 100px; gap: 0.75rem; padding: 0.5rem 1rem; font-size: 0.68rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; }

@media (max-width: 1200px) {
    .ch-ticket-row, .ch-thead { grid-template-columns: 45px 1fr 160px 95px 110px 160px 90px; }
}
@media (max-width: 900px) {
    .ch-ticket-row, .ch-thead { grid-template-columns: 1fr; }
    .ch-thead { display: none; }
    .ch-ticket-row { gap: 0.5rem; padding: 1rem; }
    .ch-ticket-id { justify-self: start; display: inline-block; }
}
</style>

<div class="page-header">
    <div class="page-header-info">
        <div class="page-header-icon">
            <i class="fa-solid fa-headset"></i>
        </div>
        <div class="page-header-text">
            <h2>Central de Suporte</h2>
            <p>Gestão ágil de tickets, incidentes e solicitações técnicas.</p>
        </div>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<div style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(16, 185, 129, 0.05) 100%); border: 1px solid rgba(16, 185, 129, 0.3); color: #059669; padding: 1rem; border-radius: 1rem; margin-bottom: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem;">
    <i class="fa-solid fa-circle-check"></i>
    <?php
        $msg = 'Ação realizada com sucesso!';
        if ($_GET['success'] == '1') $msg = 'Chamado criado com sucesso!';
        elseif ($_GET['success'] == '2') $msg = 'Chamado finalizado e arquivado!';
        elseif ($_GET['success'] == '3') $msg = 'Chamado marcado como pendente!';
        elseif ($_GET['success'] == '4') $msg = 'Chamado atualizado com sucesso!';
        elseif ($_GET['success'] == '5') $msg = 'Chamado pendenciado! SLA pausado até reativação.';
        elseif ($_GET['success'] == '6') $msg = 'Chamado reativado! SLA retomado.';
        echo $msg;
    ?>
</div>
<?php endif; ?>

<?php
// Contadores por status
$countAberto = 0; $countConcluido = 0; $countPendente = 0; $countSemSol = 0;
foreach ($tickets as $tk) {
    if ($tk['status'] == 'Aberto') $countAberto++;
    elseif ($tk['status'] == 'Concluído' || $tk['status'] == 'Solucionado' || $tk['status'] == 'Finalizado' || $tk['status'] == 'Fechado') $countConcluido++;
    elseif ($tk['status'] == 'Pendente') $countPendente++;
    else $countSemSol++;
}
$countTotal = count($tickets);
?>

<!-- Contadores -->
<div class="ch-stats-bar">
    <div class="ch-stat-card active" style="border-color: #6366F1;" onclick="filterByStatus('todos')">
        <div class="ch-stat-num" style="color: #6366F1;"><?= $countTotal ?></div>
        <div class="ch-stat-label" style="color: #6366F1;">Total</div>
    </div>
    <div class="ch-stat-card" style="border-color: #4F46E5;" onclick="filterByStatus('Aberto')">
        <div class="ch-stat-num" style="color: #4F46E5;"><?= $countAberto ?></div>
        <div class="ch-stat-label" style="color: #4F46E5;">Abertos</div>
    </div>
    <div class="ch-stat-card" style="border-color: #D97706;" onclick="filterByStatus('Pendente')">
        <div class="ch-stat-num" style="color: #D97706;"><?= $countPendente ?></div>
        <div class="ch-stat-label" style="color: #D97706;">Pendentes</div>
    </div>
    <div class="ch-stat-card" style="border-color: #059669;" onclick="filterByStatus('Concluído')">
        <div class="ch-stat-num" style="color: #059669;"><?= $countConcluido ?></div>
        <div class="ch-stat-label" style="color: #059669;">Concluídos</div>
    </div>
    <?php if ($countSemSol > 0): ?>
    <div class="ch-stat-card" style="border-color: #DC2626;" onclick="filterByStatus('Sem Solução')">
        <div class="ch-stat-num" style="color: #DC2626;"><?= $countSemSol ?></div>
        <div class="ch-stat-label" style="color: #DC2626;">Sem Solução</div>
    </div>
    <?php endif; ?>
</div>

<!-- Barra de ações -->
<div class="ch-search-bar">
    <div style="position: relative; flex: 1; min-width: 200px;">
        <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--text-soft); font-size: 0.8rem;"></i>
        <input type="text" class="ch-search-input" id="chSearchInput" placeholder="Buscar por título, solicitante ou ID..." oninput="applyFilters()">
    </div>
    <select class="ch-filter-select" id="chFilterPriority" onchange="applyFilters()">
        <option value="">Prioridade</option>
        <option value="Baixa">Baixa</option>
        <option value="Média">Média</option>
        <option value="Alta">Alta</option>
        <option value="Crítica">Crítica</option>
    </select>
    <select class="ch-filter-select" id="chFilterSetor" onchange="applyFilters()">
        <option value="">Setor</option>
        <?php
        $setoresUnicos = [];
        foreach ($tickets as $tk) {
            $s = trim($tk['sector'] ?? '');
            if ($s && !in_array($s, $setoresUnicos)) $setoresUnicos[] = $s;
        }
        sort($setoresUnicos);
        foreach ($setoresUnicos as $s): ?>
        <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn-primary" onclick="document.getElementById('ticketModal').style.display='flex'" style="white-space: nowrap;">
        <i class="fa-solid fa-plus"></i> Novo Chamado
    </button>
    <a href="?page=chamados" class="btn-secondary" style="text-decoration: none; display: flex; align-items: center; gap: 0.5rem; white-space: nowrap; <?= !$show_all ? 'border-color: #5B21B6; color: #5B21B6;' : '' ?>">
        <i class="fa-solid fa-filter"></i> Abertos
    </a>
    <a href="?page=chamados&all=1" class="btn-secondary" style="text-decoration: none; display: flex; align-items: center; gap: 0.5rem; white-space: nowrap; <?= $show_all ? 'border-color: #5B21B6; color: #5B21B6;' : '' ?>">
        <i class="fa-solid fa-list"></i> Histórico
    </a>
</div>

<!-- Cabeçalho da lista -->
<div class="ch-thead">
    <span>ID</span>
    <span>Título / Descrição</span>
    <span>Solicitante</span>
    <span>Prioridade</span>
    <span>Status</span>
    <span>Data / SLA</span>
    <span style="text-align:center;">Ações</span>
</div>

<!-- Lista de chamados -->
<div class="ch-ticket-list" id="chTicketList">
    <?php foreach ($tickets as $ticket):
        $statusClass = '';
        if (in_array($ticket['status'], ['Concluído','Solucionado','Finalizado','Fechado'])) $statusClass = 'status-concluido';

        $statusBadge = 'ch-badge-aberto';
        if (in_array($ticket['status'], ['Concluído','Solucionado','Finalizado','Fechado'])) $statusBadge = 'ch-badge-concluido';
        elseif ($ticket['status'] == 'Pendente') $statusBadge = 'ch-badge-pendente';
        elseif ($ticket['status'] == 'Sem Solução') $statusBadge = 'ch-badge-semsolucao';

        $prioBadge = 'ch-badge-media';
        if ($ticket['priority'] == 'Baixa') $prioBadge = 'ch-badge-baixa';
        elseif ($ticket['priority'] == 'Alta') $prioBadge = 'ch-badge-alta';
        elseif ($ticket['priority'] == 'Crítica') $prioBadge = 'ch-badge-critica';

        $statusIcon = 'fa-envelope-open';
        if (in_array($ticket['status'], ['Concluído','Solucionado','Finalizado','Fechado'])) $statusIcon = 'fa-check-double';
        elseif ($ticket['status'] == 'Pendente') $statusIcon = 'fa-pause-circle';
        elseif ($ticket['status'] == 'Sem Solução') $statusIcon = 'fa-circle-xmark';

        $slaStr = null;
        $slaMin = $ticket['sla_minutes'];
        if ($slaMin !== null) {
            $slaH = floor(abs($slaMin) / 60);
            $slaM = abs($slaMin) % 60;
            if ($slaH >= 24) { $slaDays = floor($slaH/24); $slaHRem = $slaH % 24; $slaStr = "{$slaDays}d {$slaHRem}h"; }
            else { $slaStr = "{$slaH}h {$slaM}min"; }
        }
    ?>
    <div class="ch-ticket-row <?= $statusClass ?>"
         data-title="<?= htmlspecialchars(strtolower($ticket['title'])) ?>"
         data-requester="<?= htmlspecialchars(strtolower($ticket['requester_name'] ?? '')) ?>"
         data-id="<?= htmlspecialchars(strtolower($ticket['id'])) ?>"
         data-status="<?= htmlspecialchars($ticket['status']) ?>"
         data-priority="<?= htmlspecialchars($ticket['priority']) ?>"
         data-sector="<?= htmlspecialchars($ticket['sector'] ?? '') ?>">
        <!-- ID -->
        <div class="ch-ticket-id"><?= htmlspecialchars($ticket['id']) ?></div>
        <!-- Título -->
        <div class="ch-ticket-title" title="<?= htmlspecialchars($ticket['description']) ?>">
            <?php if ($ticket['asset_id']): ?><i class="fa-solid fa-laptop-code" style="color: var(--text-soft); font-size: 0.7rem; margin-right: 0.3rem;" title="Equipamento vinculado"></i><?php endif; ?>
            <?= htmlspecialchars($ticket['title']) ?>
            <small><?= htmlspecialchars($ticket['sector'] ?? '') ?><?= $ticket['unit_name'] ? ' · ' . htmlspecialchars($ticket['unit_name']) : '' ?></small>
        </div>
        <!-- Solicitante -->
        <div class="ch-ticket-requester">
            <?php if ($ticket['requester_avatar']): ?>
                <img src="<?= htmlspecialchars($ticket['requester_avatar']) ?>" alt="">
            <?php else: ?>
                <div class="ch-avatar-placeholder">👤</div>
            <?php endif; ?>
            <span><?= htmlspecialchars($ticket['requester_name'] ?? '') ?></span>
        </div>
        <!-- Prioridade -->
        <div><span class="ch-badge <?= $prioBadge ?>"><?= htmlspecialchars($ticket['priority']) ?></span></div>
        <!-- Status -->
        <div><span class="ch-badge <?= $statusBadge ?>"><i class="fa-solid <?= $statusIcon ?>"></i> <?= htmlspecialchars($ticket['status']) ?></span></div>
        <!-- Data / SLA -->
        <div class="ch-ticket-date">
            <?php if ($ticket['status'] == 'Pendente'): ?>
                <div style="color: #D97706; font-weight: 700;"><i class="fa-solid fa-pause"></i> Pendente</div>
                <?php if ($ticket['pending_reason']): ?>
                    <div style="font-size: 0.68rem; color: #92400e;"><?= htmlspecialchars($ticket['pending_reason']) ?></div>
                <?php endif; ?>
                <?php if ($ticket['pending_since']): ?>
                    <div>Desde <?= date('d/m H:i', strtotime($ticket['pending_since'])) ?></div>
                <?php endif; ?>
            <?php elseif (in_array($ticket['status'], ['Concluído','Solucionado','Finalizado','Fechado','Sem Solução'])): ?>
                <div><strong><?= htmlspecialchars(explode(' ', $ticket['closed_by'] ?? '')[0]) ?></strong></div>
                <div><?= $ticket['closed_at'] ? date('d/m/y H:i', strtotime($ticket['closed_at'])) : '' ?></div>
                <?php if ($slaStr): ?>
                    <div style="color: #F59E0B; font-weight: 700;"><i class="fa-regular fa-clock"></i> <?= $slaStr ?></div>
                <?php endif; ?>
            <?php else: ?>
                <div>Criado em</div>
                <div><strong><?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></strong></div>
            <?php endif; ?>
        </div>
        <!-- Ações -->
        <div class="ch-ticket-actions">
            <?php if ($ticket['status'] == 'Aberto' || $ticket['status'] == 'Pendente'): ?>
                <?php if ($ticket['status'] == 'Aberto'): ?>
                    <button type="button" class="btn-icon" style="background: #EEF2FF; color: #4F46E5;" title="Editar"
                        onclick="openEditModal('<?= $ticket['id'] ?>', '<?= htmlspecialchars(addslashes($ticket['title'])) ?>', '<?= htmlspecialchars(addslashes($ticket['description'])) ?>', '<?= $ticket['priority'] ?>', '<?= $ticket['asset_id'] ?>', '<?= htmlspecialchars(addslashes($ticket['asset_name'] ?? '')) ?>')">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <button type="button" class="btn-icon" style="background: #FFF7ED; color: #F59E0B; border:1px solid #FDE68A;" title="Pendenciar"
                        onclick="openPendenciarModal('<?= $ticket['id'] ?>', '<?= htmlspecialchars(addslashes($ticket['title'])) ?>')">
                        <i class="fa-solid fa-clock"></i>
                    </button>
                <?php endif; ?>
                <?php if ($ticket['status'] == 'Pendente'): ?>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="reativar_ticket">
                        <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                        <button type="submit" class="btn-icon" style="background: #F5F3FF; color: #7C3AED; border:1px solid #DDD6FE;" title="Reativar">
                            <i class="fa-solid fa-circle-play"></i>
                        </button>
                    </form>
                <?php endif; ?>
                <button type="button" class="btn-icon" title="Fechar Chamado" onclick="openCloseModal('<?= $ticket['id'] ?>', '<?= htmlspecialchars(addslashes($ticket['title'])) ?>', '<?= htmlspecialchars($ticket['requester_phone'] ?? '') ?>', '<?= htmlspecialchars($ticket['requester_name'] ?? '') ?>')">
                    <i class="fa-solid fa-circle-check"></i>
                </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="ch-empty" id="chEmptyMsg" style="display:none;">
    <i class="fa-solid fa-inbox" style="font-size: 2rem; margin-bottom: 0.75rem; display: block; opacity: 0.4;"></i>
    Nenhum chamado encontrado com os filtros selecionados.
</div>

<!-- Paginação -->
<div class="ch-pagination" id="chPagination"></div>

<script>
(function(){
    const perPage = 20;
    let currentPage = 1;
    let currentStatusFilter = 'todos';
    let filteredRows = [];

    function getRows() {
        return Array.from(document.querySelectorAll('.ch-ticket-row'));
    }

    window.filterByStatus = function(status) {
        currentStatusFilter = status;
        currentPage = 1;
        // Update stat card active states
        document.querySelectorAll('.ch-stat-card').forEach(c => c.classList.remove('active'));
        event.currentTarget.classList.add('active');
        applyFilters();
    };

    window.applyFilters = function() {
        const search = (document.getElementById('chSearchInput').value || '').toLowerCase();
        const prio = document.getElementById('chFilterPriority').value;
        const setor = document.getElementById('chFilterSetor').value;
        const rows = getRows();

        filteredRows = rows.filter(r => {
            const title = r.dataset.title || '';
            const requester = r.dataset.requester || '';
            const id = r.dataset.id || '';
            const status = r.dataset.status || '';
            const priority = r.dataset.priority || '';
            const sector = r.dataset.sector || '';

            // Search filter
            if (search && !title.includes(search) && !requester.includes(search) && !id.includes(search)) return false;
            // Status filter
            if (currentStatusFilter !== 'todos') {
                if (currentStatusFilter === 'Concluído') {
                    if (!['Concluído','Solucionado','Finalizado','Fechado'].includes(status)) return false;
                } else {
                    if (status !== currentStatusFilter) return false;
                }
            }
            // Priority filter
            if (prio && priority !== prio) return false;
            // Setor filter
            if (setor && sector !== setor) return false;
            return true;
        });

        currentPage = 1;
        renderPage();
    };

    function renderPage() {
        const rows = getRows();
        const total = filteredRows.length;
        const totalPages = Math.max(1, Math.ceil(total / perPage));
        if (currentPage > totalPages) currentPage = totalPages;

        const start = (currentPage - 1) * perPage;
        const end = start + perPage;

        // Hide all, show only filtered & paginated
        rows.forEach(r => r.style.display = 'none');
        filteredRows.forEach((r, i) => {
            r.style.display = (i >= start && i < end) ? '' : 'none';
        });

        // Empty message
        document.getElementById('chEmptyMsg').style.display = total === 0 ? '' : 'none';

        // Pagination
        const pag = document.getElementById('chPagination');
        if (totalPages <= 1) { pag.innerHTML = '<span class="ch-page-info">Mostrando ' + total + ' chamado(s)</span>'; return; }

        let html = '<span class="ch-page-info">Página ' + currentPage + ' de ' + totalPages + ' (' + total + ' chamados)</span>';
        if (currentPage > 1) html += '<button class="ch-page-btn" onclick="goToPage(' + (currentPage - 1) + ')"><i class="fa-solid fa-chevron-left"></i></button>';
        const maxBtns = 5;
        let startP = Math.max(1, currentPage - Math.floor(maxBtns/2));
        let endP = Math.min(totalPages, startP + maxBtns - 1);
        if (endP - startP < maxBtns - 1) startP = Math.max(1, endP - maxBtns + 1);
        for (let p = startP; p <= endP; p++) {
            html += '<button class="ch-page-btn' + (p === currentPage ? ' active' : '') + '" onclick="goToPage(' + p + ')">' + p + '</button>';
        }
        if (currentPage < totalPages) html += '<button class="ch-page-btn" onclick="goToPage(' + (currentPage + 1) + ')"><i class="fa-solid fa-chevron-right"></i></button>';
        pag.innerHTML = html;
    }

    window.goToPage = function(p) {
        currentPage = p;
        renderPage();
        document.getElementById('chTicketList').scrollIntoView({behavior:'smooth', block:'start'});
    };

    // Initial render
    filteredRows = getRows();
    renderPage();
})();
</script>

<div id="ticketModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 650px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; position: sticky; top: 0; background: inherit; z-index: 11; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
            <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--text-main);">Novo Chamado</h3>
            <button onclick="document.getElementById('ticketModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: var(--text-soft); font-size: 1.5rem;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_ticket">
            <div class="form-group" style="position: relative;">
                <label class="form-label">Solicitante *</label>
                <i class="fa-solid fa-user" style="position: absolute; left: 1rem; top: 2.35rem; font-size: 0.8rem; color: var(--text-soft); z-index: 10;"></i>
                <input type="text" id="requester_autocomplete" placeholder="Digite o nome do solicitante..." class="form-input" style="padding-left: 2.5rem; background: var(--bg-main); border-color: var(--border-color); color: var(--text-main);" autocomplete="off" required>
                <input type="hidden" name="requester_id" id="requester_id" required>
                <div id="requester_suggestions" class="autocomplete-suggestions glass-panel" style="display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 9999; margin-top: 0.25rem; padding: 0; max-height: 250px; overflow-y: auto; border-radius: 0.75rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);"></div>
            </div>
            <div class="form-group">
                <label class="form-label">Unidade</label>
                <input type="text" id="unit_display" class="form-input" readonly>
                <input type="hidden" name="unit_id" id="unit_id">
            </div>
            <div class="form-group">
                <label class="form-label">Setor</label>
                <input type="text" name="sector" id="sector" class="form-input" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Perfil de Acesso</label>
                <input type="text" id="user_role" class="form-input" readonly>
            </div>
            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-envelope" style="color: var(--crm-purple); margin-right: 0.25rem;"></i> E-mail do Solicitante</label>
                <input type="email" id="user_email" class="form-input" readonly style="background: var(--bg-main); color: var(--text-soft); cursor: default;">
            </div>
            <div class="form-group" style="position: relative;">
                <label class="form-label">Produto Vinculado</label>
                <i class="fa-solid fa-laptop-code" style="position: absolute; left: 1rem; top: 2.35rem; font-size: 0.8rem; color: var(--text-soft); z-index: 10;"></i>
                <input type="text" id="asset_autocomplete" placeholder="Digite o nome ou número de patrimônio..." class="form-input" style="padding-left: 2.5rem; background: var(--bg-main); border-color: var(--border-color); color: var(--text-main);" autocomplete="off">
                <input type="hidden" name="asset_id" id="asset_id">
                <div id="asset_suggestions" class="autocomplete-suggestions glass-panel" style="display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 9999; margin-top: 0.25rem; padding: 0; max-height: 250px; overflow-y: auto; border-radius: 0.75rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);"></div>
            </div>
            <div class="form-group">
                <label class="form-label">Título *</label>
                <input type="text" name="title" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label"><i class="fa-regular fa-calendar" style="color: var(--text-soft); margin-right: 0.25rem;"></i> Data do Chamado (Opcional)</label>
                <input type="datetime-local" name="created_at" class="form-input" style="color: var(--text-main);">
                <small style="color: var(--text-soft); font-size: 0.75rem; margin-top: 0.25rem; display: block;">Se deixar em branco, preencheremos com a data e hora atual.</small>
            </div>
            <div class="form-group">
                <label class="form-label">Descrição *</label>
                <textarea name="description" class="form-textarea" required></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Prioridade *</label>
                <select name="priority" class="form-select" required>
                    <option value="Baixa">Baixa</option>
                    <option value="Média" selected>Média</option>
                    <option value="Alta">Alta</option>
                    <option value="Crítica">Crítica</option>
                </select>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <button type="button" onclick="document.getElementById('ticketModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Criar Chamado</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Edição de Chamado -->
<div id="editTicketModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 650px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; position: sticky; top: 0; background: inherit; z-index: 11; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color);">
            <h3 style="font-size: 1.25rem; font-weight: 900; color: var(--text-main);">Editar Chamado</h3>
            <button onclick="document.getElementById('editTicketModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: var(--text-soft); font-size: 1.5rem;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_ticket">
            <input type="hidden" name="ticket_id" id="edit_ticket_id">
            
            <div class="form-group" style="position: relative;">
                <label class="form-label">Produto Vinculado</label>
                <i class="fa-solid fa-laptop-code" style="position: absolute; left: 1rem; top: 2.35rem; font-size: 0.8rem; color: var(--text-soft); z-index: 10;"></i>
                <input type="text" id="edit_asset_autocomplete" placeholder="Digite o nome ou número de patrimônio..." class="form-input" style="padding-left: 2.5rem; background: var(--bg-main); border-color: var(--border-color); color: var(--text-main);" autocomplete="off">
                <input type="hidden" name="asset_id" id="edit_asset_id">
                <div id="edit_asset_suggestions" class="autocomplete-suggestions glass-panel" style="display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 9999; margin-top: 0.25rem; padding: 0; max-height: 250px; overflow-y: auto; border-radius: 0.75rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);"></div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Título *</label>
                <input type="text" name="title" id="edit_title" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Descrição *</label>
                <textarea name="description" id="edit_description" class="form-textarea" rows="4" required></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Prioridade *</label>
                <select name="priority" id="edit_priority" class="form-select" required>
                    <option value="Baixa">Baixa</option>
                    <option value="Média">Média</option>
                    <option value="Alta">Alta</option>
                    <option value="Crítica">Crítica</option>
                </select>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <button type="button" onclick="document.getElementById('editTicketModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Resolução de Chamado -->

<div id="closeTicketModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 2000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 450px; width: 100%; border-top: 4px solid var(--crm-purple); max-height: 90vh; overflow-y: auto;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="width: 60px; height: 60px; background: rgba(91, 33, 182, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;">
                <i class="fa-solid fa-headset" style="font-size: 1.5rem; color: var(--crm-purple);"></i>
            </div>
            <h3 style="font-size: 1.25rem; font-weight: 900; margin-bottom: 0.5rem; color: var(--text-main);">Finalização de Chamado</h3>
            <p id="closeTicketTitle" style="color: var(--text-soft); font-size: 0.875rem; font-weight: 600;"></p>
        </div>

        <form method="POST" id="closeTicketForm">
            <input type="hidden" name="action" value="close_ticket">
            <input type="hidden" name="ticket_id" id="closeTicketId">
            <input type="hidden" name="resolution" id="closeTicketResolution">

            <div class="form-group" style="position: relative; margin-bottom: 1.5rem; text-align: left;">
                <label class="form-label" style="display: flex; gap: 0.5rem; align-items: center;"><i class="fa-solid fa-user-gear" style="color: var(--crm-purple);"></i> Técnico Responsável</label>
                <input type="text" id="tech_autocomplete" name="technician_name" placeholder="Buscar quem executou/resolveu..." class="form-input" style="background: var(--bg-main); border-color: var(--border-color); color: var(--text-main);" autocomplete="off">
                <div id="tech_suggestions" class="autocomplete-suggestions glass-panel" style="display: none; position: absolute; top: 100%; left: 0; right: 0; z-index: 9999; margin-top: 0.25rem; padding: 0; max-height: 200px; overflow-y: auto; border-radius: 0.75rem; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);"></div>
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <button type="button" onclick="submitResolution('solucionado')" class="btn-primary" style="background: #10B981; border-color: #10B981; display: flex; justify-content: space-between; align-items: center;">
                    <span><i class="fa-solid fa-check-double"></i> Solucionado</span>
                    <span style="font-size: 0.75rem; opacity: 0.8;">Arquivar Histórico</span>
                </button>
                
                <button type="button" onclick="submitResolution('pendente')" class="btn-primary" style="background: #EF4444; border-color: #EF4444; display: flex; justify-content: space-between; align-items: center;">
                    <span><i class="fa-solid fa-triangle-exclamation"></i> Pendenciar</span>
                    <span style="font-size: 0.75rem; opacity: 0.8;">Manter em Aberto</span>
                </button>

                <button type="button" onclick="submitResolution('sem_solucao')" class="btn-primary" style="background: #F59E0B; border-color: #F59E0B; display: flex; justify-content: space-between; align-items: center;">
                    <span><i class="fa-solid fa-circle-xmark"></i> Sem Solução</span>
                    <span style="font-size: 0.75rem; opacity: 0.8;">Arquivar Histórico</span>
                </button>

                <button type="button" onclick="document.getElementById('closeTicketModal').style.display='none'" class="btn-secondary" style="margin-top: 1rem;">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ─── Modal de Pendência de Chamado ─── -->
<div id="pendenciarModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:2rem;">
    <div class="glass-panel" style="max-width:450px;width:100%;border-top:4px solid #F59E0B; max-height: 90vh; overflow-y: auto;">
        <div style="text-align:center;margin-bottom:1.5rem;">
            <div style="width:56px;height:56px;background:rgba(245,158,11,0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
                <i class="fa-solid fa-clock" style="font-size:1.4rem;color:#F59E0B;"></i>
            </div>
            <h3 style="font-size:1.15rem;font-weight:900;color:var(--text-main);margin-bottom:0.25rem;">Pendenciar Chamado</h3>
            <p id="pendenciarTicketTitle" style="color:var(--text-soft);font-size:0.85rem;font-weight:600;"></p>
            <p style="color:#92400e;font-size:0.78rem;background:#FEF3C7;padding:0.5rem 1rem;border-radius:0.5rem;margin-top:0.75rem;"><i class="fa-solid fa-triangle-exclamation"></i> O <strong>SLA será pausado</strong> até que o chamado seja reativado.</p>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="pendenciar_ticket">
            <input type="hidden" name="ticket_id" id="pendenciarTicketId">
            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-comment-dots" style="color:#F59E0B;"></i> Motivo da Pendência *</label>
                <textarea name="reason" class="form-textarea" rows="3" placeholder="Ex: Aguardando peça, aguardando aprovação, aguardando informação do usuário..." required style="resize:vertical;"></textarea>
            </div>
            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:1.5rem;">
                <button type="button" onclick="document.getElementById('pendenciarModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary" style="background:#F59E0B;border-color:#F59E0B;"><i class="fa-solid fa-clock"></i> Pendenciar e Pausar SLA</button>
            </div>
        </form>
    </div>
</div>

<script>
    var _waPhone = '';
    var _waRequester = '';
    var _waTitle = '';
    function openCloseModal(id, title, phone, requester) {
        document.getElementById('closeTicketId').value = id;
        document.getElementById('closeTicketTitle').innerText = title;
        document.getElementById('tech_autocomplete').value = <?= json_encode($user['name'] ?? '') ?>;
        document.getElementById('closeTicketModal').style.display = 'flex';
        _waPhone = (phone || '').replace(/[^0-9]/g, '');
        _waRequester = requester || '';
        _waTitle = title || '';
    }

    function openPendenciarModal(id, title) {
        document.getElementById('pendenciarTicketId').value = id;
        document.getElementById('pendenciarTicketTitle').innerText = title;
        document.getElementById('pendenciarModal').style.display = 'flex';
    }

    function submitResolution(res) {
        if (res === 'solucionado') {
            const tech = document.getElementById('tech_autocomplete').value;
            if (!tech || tech.trim() === '') {
                alert('Obrigatório informar o Técnico Responsável antes de registrar como solucionado.');
                document.getElementById('tech_autocomplete').focus();
                return;
            }
        }
        document.getElementById('closeTicketResolution').value = res;
        document.getElementById('closeTicketForm').submit();
    }

    function openEditModal(id, title, description, priority, assetId, assetName) {
        document.getElementById('edit_ticket_id').value = id;
        document.getElementById('edit_title').value = title;
        document.getElementById('edit_description').value = description;
        document.getElementById('edit_priority').value = priority;
        document.getElementById('edit_asset_id').value = assetId || '';
        document.getElementById('edit_asset_autocomplete').value = assetName || '';
        
        document.getElementById('editTicketModal').style.display = 'flex';
    }
</script>

<script>
    const usersData = <?= json_encode($users) ?>;
    const assetsData = <?= json_encode($assets) ?>;

    document.addEventListener('DOMContentLoaded', () => {
        const inputTech = document.getElementById('tech_autocomplete');
        const suggTech = document.getElementById('tech_suggestions');
        
        inputTech.addEventListener('input', () => {
            const val = inputTech.value.toLowerCase();
            if (!val) { suggTech.style.display = 'none'; return; }
            
            const filtered = usersData.filter(u => u.name.toLowerCase().includes(val)).slice(0, 10);
            suggTech.innerHTML = '';
            if (filtered.length === 0) {
                suggTech.innerHTML = '<div style="padding: 0.75rem 1rem; color: var(--text-soft); font-size: 0.85rem; text-align: center;">Nenhum usuário encontrado</div>';
                suggTech.style.display = 'block';
                return;
            }

            filtered.forEach(u => {
                const div = document.createElement('div');
                div.className = 'autocomplete-suggestion-item';
                div.style.display = 'flex';
                div.style.alignItems = 'center';
                div.style.gap = '0.75rem';
                
                const avatarHtml = u.avatar_url 
                    ? `<img src="${u.avatar_url}" style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:1px solid #e2e8f0;">` 
                    : `<div style="width:32px;height:32px;border-radius:50%;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:12px;color:#94a3b8;border:1px solid #e2e8f0;">👤</div>`;
                
                div.innerHTML = `${avatarHtml} <div style="font-weight:700;color:#0F172A;">${u.name}</div>`;
                
                div.onclick = () => {
                    inputTech.value = u.name;
                    suggTech.style.display = 'none';
                };
                suggTech.appendChild(div);
            });
            suggTech.style.display = 'block';
        });

        document.addEventListener('click', (e) => {
            if (!inputTech.contains(e.target) && !suggTech.contains(e.target)) {
                suggTech.style.display = 'none';
            }
        });
        
        inputTech.addEventListener('focus', () => {
            if (!inputTech.value && usersData.length > 0) {
                inputTech.dispatchEvent(new Event('input'));
            }
        });
    });

    function setupAutocomplete(inputId, hiddenId, suggestionsId, data, displayField, subtitleField, valueField, onSelectCallback = null) {
        const inputElement = document.getElementById(inputId);
        const hiddenElement = document.getElementById(hiddenId);
        const suggestionsElement = document.getElementById(suggestionsId);

        function renderSuggestions(filteredData) {
            suggestionsElement.innerHTML = '';
            if (filteredData.length === 0) {
                suggestionsElement.innerHTML = '<div style="padding: 0.75rem 1rem; color: var(--text-soft); font-size: 0.85rem; text-align: center;">Nenhum resultado encontrado</div>';
                suggestionsElement.style.display = 'block';
                return;
            }

            filteredData.forEach(item => {
                const div = document.createElement('div');
                div.className = 'autocomplete-suggestion-item';
                
                let displayName = item[displayField];
                
                let html = `<div style="font-weight: 700; color: var(--text-main);">${displayName}</div>`;
                if (subtitleField && item[subtitleField]) {
                    html += `<div style="font-size: 0.75rem; color: var(--text-soft); margin-top: 0.15rem;">${subtitleField === 'patrimony_id' ? 'Patrimônio: ' : ''}${item[subtitleField]}</div>`;
                }
                div.innerHTML = html;

                div.addEventListener('click', () => {
                    inputElement.value = displayName;
                    hiddenElement.value = item[valueField];
                    suggestionsElement.style.display = 'none';
                    if (onSelectCallback) onSelectCallback(item);
                });
                suggestionsElement.appendChild(div);
            });
            suggestionsElement.style.display = 'block';
        }

        inputElement.addEventListener('input', () => {
            const val = inputElement.value.toLowerCase();
            hiddenElement.value = ''; // Sempre limpa o valor real quando o usuário digita
            if (onSelectCallback) onSelectCallback(null);

            if (!val) {
                suggestionsElement.style.display = 'none';
                return;
            }

            const filtered = data.filter(item => {
                const mainText = (item[displayField] || '').toLowerCase();
                const subText = subtitleField ? (item[subtitleField] || '').toLowerCase() : '';
                return mainText.includes(val) || subText.includes(val);
            }).slice(0, 15);

            renderSuggestions(filtered);
        });

        inputElement.addEventListener('focus', () => {
            if (inputElement.value || data.length > 0) {
                inputElement.dispatchEvent(new Event('input'));
            }
        });

        document.addEventListener('click', (e) => {
            if (!inputElement.contains(e.target) && !suggestionsElement.contains(e.target)) {
                suggestionsElement.style.display = 'none';
            }
        });
    }

    function updateTicketInfoCallback(item) {
        if (item) {
            document.getElementById('sector').value = item.sector || '';
            document.getElementById('user_role').value = item.role || '';
            document.getElementById('unit_id').value = item.unit_id || '';
            document.getElementById('unit_display').value = item.unit_name || '';
            document.getElementById('user_email').value = item.email || '';
        } else {
            document.getElementById('sector').value = '';
            document.getElementById('user_role').value = '';
            document.getElementById('unit_id').value = '';
            document.getElementById('unit_display').value = '';
            document.getElementById('user_email').value = '';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        setupAutocomplete(
            'requester_autocomplete', 
            'requester_id', 
            'requester_suggestions', 
            usersData, 
            'name', 
            'sector', 
            'id', 
            updateTicketInfoCallback
        );
        setupAutocomplete(
            'asset_autocomplete', 
            'asset_id', 
            'asset_suggestions', 
            assetsData, 
            'name', 
            'patrimony_id', 
            'id', 
            null
        );

        setupAutocomplete(
            'edit_asset_autocomplete', 
            'edit_asset_id', 
            'edit_asset_suggestions', 
            assetsData, 
            'name', 
            'patrimony_id', 
            'id', 
            null
        );
        
        // Bloquear envio se não tiver selecionado um solicitante válido da lista
        const form = document.querySelector('form[method="POST"]');
        if (form) {
            form.addEventListener('submit', function(e) {
                const actionInput = document.querySelector('input[name="action"][value="add_ticket"]');
                if (actionInput) { // Garantir que está no modal de novo chamado
                    const requesterId = document.getElementById('requester_id').value;
                    if (!requesterId) {
                        e.preventDefault();
                        alert('Por favor, selecione um solicitante válido na lista.');
                        document.getElementById('requester_autocomplete').focus();
                    }
                }
            });
        }

    });
</script>
