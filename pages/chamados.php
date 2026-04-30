<?php
// Migrações SaaS
try { $pdo->exec("ALTER TABLE tickets ADD COLUMN company_id INT NOT NULL DEFAULT 1"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE ticket_responses ADD COLUMN company_id INT NOT NULL DEFAULT 1"); } catch(Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_ticket') {
    $compId = getCurrentUserCompanyId();
    $stmt = $pdo->prepare("INSERT INTO tickets (id, asset_id, title, description, priority, requester_id, sector, unit_id, status, company_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Aberto', ?, NOW())");
    $stmt->execute(['T' . time(), $_POST['asset_id'] ?: null, $_POST['title'], $_POST['description'], $_POST['priority'], $_POST['requester_id'], $_POST['sector'], $_POST['unit_id'], $compId]);
    header('Location: ?page=chamados&success=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'close_ticket') {
    $ticket_id = $_POST['ticket_id'];
    $resolution = $_POST['resolution']; // 'solucionado', 'pendente', 'sem_solucao'
    $technician_name = $_POST['technician_name'] ?? '';

    $compId = getCurrentUserCompanyId();
    // Buscar asset_id
    $t = $pdo->prepare("SELECT asset_id FROM tickets WHERE id = ? AND company_id = ?");
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
          u.name as requester_name, u.avatar_url as requester_avatar,
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
          LEFT JOIN users u ON BINARY t.requester_id = BINARY u.id 
          LEFT JOIN units un ON BINARY t.unit_id = BINARY un.id 
          LEFT JOIN assets a ON t.asset_id = a.id
          LEFT JOIN users c_user ON BINARY t.closed_by = BINARY c_user.name
          WHERE " . implode(" AND ", $conditions) . " 
          ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$users_stmt = $pdo->prepare("SELECT u.id, u.name, u.sector, u.role, u.unit_id, u.avatar_url, un.name as unit_name FROM users u LEFT JOIN units un ON BINARY u.unit_id = BINARY un.id WHERE u.company_id = ? ORDER BY u.name");
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


<div style="margin-bottom: 2rem; display: flex; gap: 1.5rem; justify-content: center; flex-wrap: wrap; align-items: center;">
    <button class="btn-primary" onclick="document.getElementById('ticketModal').style.display='flex'">
        <i class="fa-solid fa-plus"></i>
        Novo Chamado
    </button>
    <a href="?page=chamados" class="btn-secondary" style="text-decoration: none; display: flex; align-items: center; gap: 0.5rem; <?= !$show_all ? 'border-color: #5B21B6; color: #5B21B6;' : '' ?>">
        <i class="fa-solid fa-filter"></i> Chamados Abertos
    </a>
    <a href="?page=chamados&all=1" class="btn-secondary" style="text-decoration: none; display: flex; align-items: center; gap: 0.5rem; <?= $show_all ? 'border-color: #5B21B6; color: #5B21B6;' : '' ?>">
        <i class="fa-solid fa-list"></i> Todo o Histórico
    </a>
</div>

<div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Título</th>
                <th>Solicitante</th>
                <th>Unidade</th>
                <th>Prioridade</th>
                <th>Status</th>
                <th>Data / Fechamento</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tickets as $ticket): ?>
            <tr>
                <td style="font-family: monospace; font-size: 0.75rem; color: var(--text-soft);"><?= htmlspecialchars($ticket['id']) ?></td>
                <td style="font-weight: 700;">
                    <?php if ($ticket['asset_id']): ?>
                        <i class="fa-solid fa-laptop-code" title="Equipamento Vinculado" style="color: var(--text-soft); font-size: 0.75rem; margin-right: 0.5rem;"></i>
                    <?php endif; ?>
                    <span title="<?= htmlspecialchars($ticket['description']) ?>" style="cursor: help; border-bottom: 1px dotted #cbd5e1;">
                        <?= htmlspecialchars($ticket['title']) ?>
                    </span>
                </td>
                <td>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <?php if ($ticket['requester_avatar']): ?>
                            <img src="<?= htmlspecialchars($ticket['requester_avatar']) ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color);">
                        <?php else: ?>
                            <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--bg-main); display: flex; align-items: center; justify-content: center; font-size: 12px; color: var(--text-main); border: 1px solid var(--border-color);">👤</div>
                        <?php endif; ?>
                        <span style="font-weight: 600; color: var(--text-main);"><?= htmlspecialchars($ticket['requester_name']) ?></span>
                    </div>
                </td>
                <td style="font-size: 0.75rem; color: var(--text-soft);"><?= htmlspecialchars($ticket['unit_name']) ?></td>
                <td>
                    <span class="badge badge-<?= 
                        $ticket['priority'] == 'Crítica' ? 'danger' : 
                        ($ticket['priority'] == 'Alta' ? 'warning' : 'info') 
                    ?>">
                        <i class="fa-solid <?= 
                            $ticket['priority'] == 'Crítica' ? 'fa-fire-flame-curved' : 
                            ($ticket['priority'] == 'Alta' ? 'fa-triangle-exclamation' : 'fa-circle-info') 
                        ?>"></i>
                        <?= htmlspecialchars($ticket['priority']) ?>
                    </span>
                </td>
                <td>
                    <span class="badge badge-<?= 
                        $ticket['status'] == 'Aberto' ? 'info' : 
                        ($ticket['status'] == 'Concluído' ? 'success' : 
                        ($ticket['status'] == 'Pendente' ? 'danger' : 'warning')) 
                    ?>">
                        <i class="fa-solid <?= 
                            $ticket['status'] == 'Aberto' ? 'fa-envelope-open' : 
                            ($ticket['status'] == 'Concluído' ? 'fa-check-double' : 
                            ($ticket['status'] == 'Pendente' ? 'fa-triangle-exclamation' : 'fa-circle-xmark')) 
                        ?>"></i>
                        <?= htmlspecialchars($ticket['status']) ?>
                    </span>
                </td>
                <td>
                    <?php if ($ticket['status'] !== 'Aberto'): ?>
                        <?php
                            $slaMin = $ticket['sla_minutes'];
                            if ($slaMin !== null) {
                                $slaH = floor(abs($slaMin) / 60);
                                $slaM = abs($slaMin) % 60;
                                if ($slaH >= 24) { $slaDays = floor($slaH/24); $slaHRem = $slaH % 24; $slaStr = "{$slaDays}d {$slaHRem}h"; }
                                else { $slaStr = "{$slaH}h {$slaM}min"; }
                            } else { $slaStr = null; }
                        ?>
                        <?php if ($ticket['status'] === 'Pendente'): ?>
                            <div style="background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);border-radius:0.5rem;padding:0.4rem 0.6rem;display:inline-flex;flex-direction:column;gap:0.3rem;min-width:140px;">
                                <span style="font-size:0.65rem;font-weight:800;color:#F59E0B;">⏸ PENDENTE</span>
                                <?php if ($ticket['pending_reason']): ?>
                                    <span style="font-size:0.65rem;color:#92400e;font-weight:600;"><?= htmlspecialchars($ticket['pending_reason']) ?></span>
                                <?php endif; ?>
                                <?php if ($ticket['pending_since']): ?>
                                    <span style="font-size:0.6rem;color:#64748b;">Desde <?= date('d/m H:i', strtotime($ticket['pending_since'])) ?></span>
                                <?php endif; ?>
                                <span style="font-size:0.6rem;color:#94a3b8;border-top:1px dashed rgba(245,158,11,0.3);padding-top:0.2rem;"><i class="fa-regular fa-clock" style="color:#f59e0b;"></i> SLA pausado</span>
                            </div>
                        <?php else: ?>
                            <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 0.5rem; padding: 0.35rem 0.5rem; display: inline-flex; flex-direction: column; gap: 0.35rem; min-width: 140px;">
                                <span style="font-size: 0.65rem; font-weight: 800; color: #10B981; letter-spacing: 0.05em; line-height: 1;">FECHADO</span>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <?php if ($ticket['closer_avatar']): ?>
                                        <img src="<?= htmlspecialchars($ticket['closer_avatar']) ?>" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover; border: 1px solid rgba(16, 185, 129, 0.4);">
                                    <?php else: ?>
                                        <div style="width: 24px; height: 24px; border-radius: 50%; background: #10B981; display: flex; align-items: center; justify-content: center; font-size: 10px; color: white; font-weight: 700;">
                                            <?= strtoupper(substr($ticket['closed_by'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div style="display: flex; flex-direction: column;">
                                        <span style="font-size: 0.70rem; font-weight: 700; color: #334155; line-height: 1.2; text-wrap: nowrap;"><?= htmlspecialchars(explode(' ', $ticket['closed_by'])[0]) ?></span>
                                        <span style="font-size: 0.65rem; color: var(--text-soft); line-height: 1.2;"><?= $ticket['closed_at'] ? date('d/m/y H:i', strtotime($ticket['closed_at'])) : '' ?></span>
                                    </div>
                                </div>
                                <?php if ($slaStr): ?>
                                <div style="display:flex; align-items:center; gap:0.3rem; border-top: 1px dashed rgba(16,185,129,0.3); padding-top:0.25rem; margin-top:0.1rem;">
                                    <i class="fa-regular fa-clock" style="color:#f59e0b; font-size:0.65rem;"></i>
                                    <span style="font-size:0.65rem; font-weight:800; color:#f59e0b;">Resolvido em: <?= $slaStr ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div style="font-size: 0.75rem; color: var(--text-soft); font-weight: 600; padding: 0.5rem 0;">    
                            <div>Criado em</div>
                            <div style="color: #334155;"><?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></div>
                        </div>
                    <?php endif; ?>
                </td>

                <td>
                    <?php if (($ticket['status'] == 'Aberto' || $ticket['status'] == 'Pendente') && ($user['role'] == 'Administrador' || $user['role'] == 'Suporte Técnico')): ?>
                        <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                            <?php if ($ticket['status'] == 'Aberto'): ?>
                                <!-- Editar -->
                                <button type="button" class="btn-icon" style="background: #EEF2FF; color: #4F46E5;" title="Editar Chamado" 
                                    onclick="openEditModal('<?= $ticket['id'] ?>', '<?= htmlspecialchars(addslashes($ticket['title'])) ?>', '<?= htmlspecialchars(addslashes($ticket['description'])) ?>', '<?= $ticket['priority'] ?>', '<?= $ticket['asset_id'] ?>', '<?= htmlspecialchars(addslashes($ticket['asset_name'] ?? '')) ?>')">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <!-- Pendenciar -->
                                <button type="button" class="btn-icon" style="background: #FFF7ED; color: #F59E0B; border:1px solid #FDE68A;" title="Pendenciar (pausar SLA)"
                                    onclick="openPendenciarModal('<?= $ticket['id'] ?>', '<?= htmlspecialchars(addslashes($ticket['title'])) ?>')">
                                    <i class="fa-solid fa-clock"></i>
                                </button>
                            <?php endif; ?>

                            <?php if ($ticket['status'] == 'Pendente'): ?>
                                <!-- Reativar da pendência -->
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="reativar_ticket">
                                    <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                                    <button type="submit" class="btn-icon" style="background: #F5F3FF; color: #7C3AED; border:1px solid #DDD6FE;" title="Reativar (retomar SLA)">
                                        <i class="fa-solid fa-circle-play"></i>
                                    </button>
                                </form>
                            <?php endif; ?>

                            <!-- Fechar -->
                            <button type="button" class="btn-icon" title="Fechar Chamado" onclick="openCloseModal('<?= $ticket['id'] ?>', '<?= htmlspecialchars(addslashes($ticket['title'])) ?>')">
                                <i class="fa-solid fa-circle-check"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

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
    function openCloseModal(id, title) {
        document.getElementById('closeTicketId').value = id;
        document.getElementById('closeTicketTitle').innerText = title;
        // Auto-preencher o técnico logado. Se não foi ele, ele pode apagar e buscar por outro.
        document.getElementById('tech_autocomplete').value = <?= json_encode($user['name'] ?? '') ?>;
        document.getElementById('closeTicketModal').style.display = 'flex';
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
        } else {
            document.getElementById('sector').value = '';
            document.getElementById('user_role').value = '';
            document.getElementById('unit_id').value = '';
            document.getElementById('unit_display').value = '';
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

