<?php
// Acesso controlado via index.php (tabela user_menus)

// Auto-migrate: Colunas de Auditoria no Empréstimo (quem realizou o empréstimo)
try { $pdo->exec("ALTER TABLE loans ADD COLUMN loaned_by_name VARCHAR(100) DEFAULT ''"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE loans ADD COLUMN loaned_by_id VARCHAR(50) DEFAULT ''"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE loans MODIFY COLUMN loan_date DATETIME"); } catch(Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'return_loan') {
    // Buscar asset_id antes de devolver
    $l = $pdo->prepare("SELECT asset_id FROM loans WHERE id = ?");
    $l->execute([$_POST['loan_id']]);
    $loan_data = $l->fetch();

    $stmt = $pdo->prepare("UPDATE loans SET status = 'Devolvido', return_date = NOW(), received_by = ?, received_by_id = ? WHERE id = ?");
    $stmt->execute([$user['name'], $user['id'], $_POST['loan_id']]);

    // Atualizar status do patrimônio para Ativo (disponível)
    if ($loan_data && $loan_data['asset_id']) {
        $stmt_asset = $pdo->prepare("UPDATE assets SET status = 'Ativo' WHERE id = ?");
        $stmt_asset->execute([$loan_data['asset_id']]);
    }

    header('Location: ?page=emprestimos&success=2');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_loan') {
    $asset_stmt = $pdo->prepare("SELECT name FROM assets WHERE id = ?");
    $asset_stmt->execute([$_POST['asset_id']]);
    $asset = $asset_stmt->fetch();
    
    $borrower_stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $borrower_stmt->execute([$_POST['borrower_id']]);
    $borrower = $borrower_stmt->fetch();
    
    if (!$asset || !$borrower) {
        header('Location: ?page=emprestimos&error=1');
        exit;
    }
    
    // loan_date agora recebe data e hora (datetime-local). Normalizar para o MySQL.
    $loan_date = !empty($_POST['loan_date']) ? date('Y-m-d H:i:s', strtotime($_POST['loan_date'])) : date('Y-m-d H:i:s');
    $expected_date = !empty($_POST['expected_return_date']) ? date('Y-m-d H:i:s', strtotime($_POST['expected_return_date'])) : date('Y-m-d 23:59:59');
    
    $stmt = $pdo->prepare("INSERT INTO loans (id, asset_id, borrower_id, asset_name, borrower_name, sector, unit_id, loan_date, expected_return_date, observations, status, loaned_by_name, loaned_by_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Ativo', ?, ?)");
    $stmt->execute([
        'L' . time(), 
        $_POST['asset_id'], 
        $_POST['borrower_id'], 
        $asset['name'], 
        $borrower['name'], 
        $_POST['sector'], 
        $_POST['unit_id'], 
        $loan_date, 
        $expected_date,
        $_POST['observations'],
        $user['name'], // Quem realizou o empréstimo (logado)
        $user['id']
    ]);

    // Marcar item do patrimônio como Emprestado para não aparecer mais na lista de disponíveis
    $pdo->prepare("UPDATE assets SET status = 'Emprestado' WHERE id = ?")->execute([$_POST['asset_id']]);

    header('Location: ?page=emprestimos&success=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_loan') {
    $borrower_stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $borrower_stmt->execute([$_POST['borrower_id']]);
    $borrower = $borrower_stmt->fetch();

    if (!$borrower) {
        header('Location: ?page=emprestimos&error=2');
        exit;
    }

    $expected_date = !empty($_POST['expected_return_date']) ? date('Y-m-d H:i:s', strtotime($_POST['expected_return_date'])) : date('Y-m-d 23:59:59');
    $return_date = !empty($_POST['return_date']) ? date('Y-m-d H:i:s', strtotime($_POST['return_date'])) : null;

    // Buscar dados atuais para verificar transição de status
    $current = $pdo->prepare("SELECT status, asset_id FROM loans WHERE id = ?");
    $current->execute([$_POST['loan_id']]);
    $loan_info = $current->fetch();

    $new_status = $loan_info['status'];
    $sql_update = "UPDATE loans SET borrower_id = ?, borrower_name = ?, expected_return_date = ?, return_date = ?";
    $params = [$_POST['borrower_id'], $borrower['name'], $expected_date, $return_date];

    // Se inseriu data de devolução e estava ativo, muda para Devolvido
    if ($return_date && $loan_info['status'] === 'Ativo') {
        $new_status = 'Devolvido';
        $sql_update .= ", status = ?, received_by = ?, received_by_id = ?";
        array_push($params, $new_status, $user['name'], $user['id']);

        // Liberar o patrimônio
        if ($loan_info['asset_id']) {
            $pdo->prepare("UPDATE assets SET status = 'Ativo' WHERE id = ?")->execute([$loan_info['asset_id']]);
        }
    } 
    // Se removeu a data de devolução e estava devolvido, volta para Ativo (caso o usuário queira desfazer)
    elseif (!$return_date && $loan_info['status'] === 'Devolvido') {
        $new_status = 'Ativo';
        $sql_update .= ", status = ?, received_by = '', received_by_id = ''";
        array_push($params, $new_status);

        // Bloquear o patrimônio novamente
        if ($loan_info['asset_id']) {
            $pdo->prepare("UPDATE assets SET status = 'Emprestado' WHERE id = ?")->execute([$loan_info['asset_id']]);
        }
    }

    $sql_update .= " WHERE id = ?";
    array_push($params, $_POST['loan_id']);

    $stmt = $pdo->prepare($sql_update);
    $stmt->execute($params);

    header('Location: ?page=emprestimos&success=3');
    exit;
}

// Filtro para os estados dos empréstimos
$view = $_GET['view'] ?? 'ativos';
$query = "SELECT l.*, u.name as unit_name, 
          rec.name as receiver_name, rec.avatar_url as receiver_avatar,
          bor.avatar_url as borrower_avatar,
          len.name as lender_name, len.avatar_url as lender_avatar
          FROM loans l 
          LEFT JOIN units u ON BINARY l.unit_id = BINARY u.id
          LEFT JOIN users rec ON (BINARY l.received_by_id = BINARY rec.id OR (l.received_by_id = '' AND BINARY l.received_by = BINARY rec.name))
          LEFT JOIN users bor ON (BINARY l.borrower_id = BINARY bor.id OR (l.borrower_id = '' AND BINARY l.borrower_name = BINARY bor.name))
          LEFT JOIN users len ON (BINARY l.loaned_by_id = BINARY len.id OR (l.loaned_by_id = '' AND BINARY l.loaned_by_name = BINARY len.name))";

if ($view === 'ativos') {
    $query .= " WHERE l.status = 'Ativo'";
} elseif ($view === 'fechados') {
    $query .= " WHERE l.status = 'Devolvido'";
} elseif ($view === 'ocorrencias') {
    $query .= " WHERE (l.status = 'Ativo' AND l.expected_return_date < NOW()) OR (l.status = 'Devolvido' AND l.return_date > l.expected_return_date)";
}
// Se view for 'historico', não aplica filtro de status

$query .= " ORDER BY l.created_at DESC";
$loans = $pdo->query($query)->fetchAll();
$assets = $pdo->query("SELECT * FROM assets WHERE status = 'Ativo'")->fetchAll();
$units = $pdo->query("SELECT * FROM units")->fetchAll();
$users = $pdo->query("SELECT u.id, u.name, u.sector, u.unit_id, u.avatar_url, un.name as unit_name FROM users u LEFT JOIN units un ON BINARY u.unit_id = BINARY un.id ORDER BY u.name")->fetchAll();
?>

<div class="page-header">
    <div class="page-header-info">
        <div class="page-header-icon">
            <i class="fa-solid fa-handshake-angle"></i>
        </div>
        <div class="page-header-text">
            <h2>Controle de Comodatos</h2>
            <p>Rastreamento de equipamentos, termos de responsabilidade e devoluções.</p>
        </div>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<div style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(16, 185, 129, 0.05) 100%); border: 1px solid rgba(16, 185, 129, 0.3); color: #059669; padding: 1rem; border-radius: 1rem; margin-bottom: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem;">
    <i class="fa-solid fa-circle-check"></i>
    <?php 
        if ($_GET['success'] == '1') echo 'Empréstimo registrado com sucesso!';
        elseif ($_GET['success'] == '2') echo 'Devolução registrada com sucesso!';
        elseif ($_GET['success'] == '3') echo 'Empréstimo atualizado com sucesso!';
    ?>
</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(239, 68, 68, 0.05) 100%); border: 1px solid rgba(239, 68, 68, 0.3); color: #DC2626; padding: 1rem; border-radius: 1rem; margin-bottom: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem;">
    <i class="fa-solid fa-circle-xmark"></i>
    <?= $_GET['error'] == '1' ? 'Erro ao processar: Equipamento ou usuário não encontrado.' : 'Erro ao atualizar: Usuário não encontrado.' ?>
</div>
<?php endif; ?>

<div style="margin-bottom: 2rem; display: flex; gap: 1.5rem; justify-content: center; flex-wrap: wrap; align-items: center;">
    <button class="btn-primary" onclick="document.getElementById('loanModal').style.display='flex'">
        <i class="fa-solid fa-plus"></i>
        Novo Empréstimo
    </button>
    <a href="?page=emprestimos&view=ativos" class="btn-secondary" style="text-decoration: none; display: flex; align-items: center; gap: 0.5rem; <?= $view === 'ativos' ? 'border-color: var(--crm-purple); color: var(--crm-purple); background: rgba(91, 33, 182, 0.05);' : '' ?>">
        <i class="fa-solid fa-clock"></i> Empréstimos Ativos
    </a>
    <a href="?page=emprestimos&view=fechados" class="btn-secondary" style="text-decoration: none; display: flex; align-items: center; gap: 0.5rem; <?= $view === 'fechados' ? 'border-color: var(--crm-purple); color: var(--crm-purple); background: rgba(91, 33, 182, 0.05);' : '' ?>">
        <i class="fa-solid fa-box-archive"></i> Empréstimos Fechados
    </a>
    <a href="?page=emprestimos&view=ocorrencias" class="btn-secondary" style="text-decoration: none; display: flex; align-items: center; gap: 0.5rem; <?= $view === 'ocorrencias' ? 'border-color: #EF4444; color: #EF4444; background: rgba(239, 68, 68, 0.05);' : '' ?>">
        <i class="fa-solid fa-triangle-exclamation"></i> Ocorrências
    </a>
    <a href="?page=emprestimos&view=historico" class="btn-secondary" style="text-decoration: none; display: flex; align-items: center; gap: 0.5rem; <?= $view === 'historico' ? 'border-color: var(--crm-purple); color: var(--crm-purple); background: rgba(91, 33, 182, 0.05);' : '' ?>">
        <i class="fa-solid fa-list-ul"></i> Todo o Histórico
    </a>
</div>

<div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>Equipamento</th>
                <th>Responsável</th>
                <th>Setor</th>
                <th>Data Empréstimo</th>
                <th>Data Devolução</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($loans as $loan): ?>
            <tr>
                <td style="font-weight: 700;"><?= htmlspecialchars($loan['asset_name']) ?></td>
                <td>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <?php if ($loan['borrower_avatar']): ?>
                            <img src="<?= htmlspecialchars($loan['borrower_avatar']) ?>" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--crm-purple);">
                        <?php else: ?>
                            <div style="width: 40px; height: 40px; border-radius: 50%; background: #e2e8f0; border: 2px solid #cbd5e1; display: flex; align-items: center; justify-content: center; font-size: 16px; color:#64748b;">👤</div>
                        <?php endif; ?>
                        <span style="font-weight: 700; color:var(--crm-black);"><?= htmlspecialchars($loan['borrower_name']) ?></span>
                    </div>
                </td>
                <td><?= htmlspecialchars($loan['sector']) ?></td>
                <td style="font-size: 0.75rem; color: #64748b;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <?php if ($loan['lender_avatar']): ?>
                            <img src="<?= htmlspecialchars($loan['lender_avatar']) ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;" title="Emprestado por: <?= htmlspecialchars($loan['lender_name'] ?: $loan['loaned_by_name']) ?>">
                        <?php else: ?>
                            <div style="width: 32px; height: 32px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 12px; border: 1px solid #e2e8f0;" title="Emprestado por: <?= htmlspecialchars($loan['loaned_by_name']) ?>">👤</div>
                        <?php endif; ?>
                        <div style="line-height: 1.1;">
                            <span style="font-weight: 700; color:var(--crm-purple);"><?= date('d/m/Y H:i', strtotime($loan['loan_date'])) ?></span><br>
                            <small style="font-size: 0.65rem;">Por: <?= htmlspecialchars($loan['lender_name'] ?: $loan['loaned_by_name'] ?: 'N/A') ?></small>
                        </div>
                    </div>
                </td>
                <td style="font-size: 0.75rem; color: #64748b;">
                    <?php if ($loan['return_date']): ?>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <?php if ($loan['receiver_avatar']): ?>
                                <img src="<?= htmlspecialchars($loan['receiver_avatar']) ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover;" title="Recebido por: <?= htmlspecialchars($loan['receiver_name'] ?: $loan['received_by']) ?>">
                            <?php else: ?>
                                <div style="width: 32px; height: 32px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 12px; border: 1px solid #e2e8f0;" title="Recebido por: <?= htmlspecialchars($loan['received_by']) ?>">👤</div>
                            <?php endif; ?>
                            <div style="line-height: 1.1;">
                                <span style="font-weight: 700; color: #10B981;"><?= date('d/m/Y H:i', strtotime($loan['return_date'])) ?></span><br>
                                <small style="font-size: 0.65rem;">Por: <?= htmlspecialchars($loan['receiver_name'] ?: $loan['received_by']) ?></small>
                            </div>
                        </div>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td>
                    <?php
                    $is_late = false;
                    $late_string = '';
                    if ($loan['expected_return_date']) {
                        $expected = new DateTime($loan['expected_return_date']);
                        
                        if ($loan['status'] == 'Ativo' && $expected < new DateTime()) {
                            $is_late = true;
                            $diff = $expected->diff(new DateTime());
                        } elseif ($loan['status'] == 'Devolvido' && $loan['return_date']) {
                            $returned = new DateTime($loan['return_date']);
                            if ($returned > $expected) {
                                $is_late = true;
                                $diff = $expected->diff($returned);
                            }
                        }
                        
                        if ($is_late && isset($diff)) {
                            $days = $diff->days;
                            $hours = $diff->h;
                            $mins = $diff->i;
                            
                            if ($days > 0) {
                                $late_string = "{$days} dia(s) e {$hours}h";
                            } elseif ($hours > 0) {
                                $late_string = "{$hours} hora(s) e {$mins}m";
                            } else {
                                $late_string = "{$mins} min(s)";
                            }
                        }
                    }
                    ?>
                    <span class="badge badge-<?= $loan['status'] == 'Ativo' ? 'warning' : 'success' ?>">
                        <i class="fa-solid <?= $loan['status'] == 'Ativo' ? 'fa-hand-holding-hand' : 'fa-check-to-slot' ?>"></i>
                        <?= htmlspecialchars($loan['status']) ?>
                    </span>
                    <?php if ($is_late): ?>
                        <div style="margin-top: 0.35rem;">
                            <span style="font-size: 0.65rem; font-weight: 800; color: #EF4444; background: rgba(239, 68, 68, 0.1); padding: 0.2rem 0.5rem; border-radius: 4px; display: inline-block;">
                                <i class="fa-solid fa-circle-exclamation"></i> Atrasado: <?= $late_string ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($loan['status'] == 'Ativo'): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="return_loan">
                        <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                        <button type="submit" class="btn-icon" title="Registrar Devolução" onclick="return confirm('Confirmar devolução do equipamento?')">
                            <i class="fa-solid fa-box"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                    <?php if ($view === 'ocorrencias'): ?>
                    <button class="btn-icon" onclick='openEditModal(<?= json_encode($loan) ?>)' title="Editar Ocorrência">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </button>
                    <?php endif; ?>
                    <button class="btn-icon" onclick="showHistory('<?= $loan['asset_id'] ?>')" title="Ver Histórico">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="loanModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 700px; width: 100%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 style="font-size: 1.25rem; font-weight: 900;">Registrar Empréstimo</h3>
            <button onclick="document.getElementById('loanModal').style.display='none'" style="background: none; border: none; cursor: pointer; font-size: 1.5rem;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_loan">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Equipamento *</label>
                    <div style="position: relative;" id="asset_selector_container">
                        <input type="text" id="asset_search" class="form-input" placeholder="Buscar por nome ou ID do patrimônio..." autocomplete="off" oninput="filterAssets(this.value)" onfocus="document.getElementById('asset_results').style.display='block'">
                        <input type="hidden" name="asset_id" id="selected_asset_id" required>
                        
                        <div id="asset_results" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid var(--crm-border); border-radius: 0.75rem; box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 1100; max-height: 200px; overflow-y: auto; margin-top: 5px;">
                            <?php foreach ($assets as $a): ?>
                                <div class="asset-option" 
                                     style="padding: 0.75rem 1rem; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.2s;" 
                                     data-id="<?= $a['id'] ?>" 
                                     data-name="<?= htmlspecialchars($a['name']) ?>" 
                                     data-patrimony="<?= htmlspecialchars($a['patrimony_id']) ?>"
                                     onclick="selectAsset(this)">
                                    <div style="font-weight: 700; font-size: 0.875rem; color: var(--crm-text);"><?= htmlspecialchars($a['name']) ?></div>
                                    <div style="font-size: 0.75rem; color: var(--crm-text-soft);">ID: <?= htmlspecialchars($a['patrimony_id']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <style>
                        .asset-option:hover { background: var(--crm-purple-soft); }
                        .asset-option.selected { background: var(--crm-purple); color: white; }
                        .asset-option.selected .asset-name { color: white; }
                    </style>
                </div>
                <div class="form-group">
                    <label class="form-label">Usuário *</label>
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <div id="borrower_avatar_preview" style="width: 50px; height: 50px; border-radius: 50%; background: #f1f5f9; border: 2px dashed #cbd5e1; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;">
                            <i class="fa-solid fa-user" style="color: #94a3b8;"></i>
                        </div>
                        <select name="borrower_id" id="borrower_select" class="form-select" required onchange="updateLoanInfo(this)" style="flex: 1;">
                            <option value="">Selecione o usuário</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>" 
                                    data-sector="<?= htmlspecialchars($u['sector']) ?>" 
                                    data-unit="<?= $u['unit_id'] ?>"
                                    data-unit-name="<?= htmlspecialchars($u['unit_name']) ?>"
                                    data-avatar="<?= htmlspecialchars($u['avatar_url'] ?? '') ?>">
                                    <?= htmlspecialchars($u['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
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
                    <label class="form-label">Data do Empréstimo *</label>
                    <input type="datetime-local" name="loan_date" class="form-input" value="<?= date('Y-m-d\TH:i') ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Data de Devolução (Previsão) *</label>
                    <input type="datetime-local" name="expected_return_date" class="form-input" value="<?= date('Y-m-d\T23:59') ?>" required>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Observações</label>
                    <textarea name="observations" class="form-textarea"></textarea>
                </div>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <button type="button" onclick="document.getElementById('loanModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Registrar</button>
            </div>
        </form>
    </div>
</div>

<div id="editLoanModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 500px; width: 100%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 style="font-size: 1.25rem; font-weight: 900;">Editar Empréstimo</h3>
            <button onclick="document.getElementById('editLoanModal').style.display='none'" style="background: none; border: none; cursor: pointer; font-size: 1.5rem;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_loan">
            <input type="hidden" name="loan_id" id="edit_loan_id">
            <div class="form-group">
                <label class="form-label">Equipamento</label>
                <input type="text" id="edit_asset_name" class="form-input" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">Responsável *</label>
                <select name="borrower_id" id="edit_borrower_select" class="form-select" required>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Previsão de Devolução *</label>
                <input type="datetime-local" name="expected_return_date" id="edit_expected_return_date" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Data de Devolução Real (Deixe vazio para manter aberto)</label>
                <input type="datetime-local" name="return_date" id="edit_return_date" class="form-input">
                <small style="color: #64748b;">Ao preencher este campo, o empréstimo será marcado como <b>Devolvido</b>.</small>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <button type="button" onclick="document.getElementById('editLoanModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<div id="historyModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); z-index: 2000; align-items: center; justify-content: center; padding: 2rem; overflow-y: auto;">
    <div class="glass-panel" style="max-width: 900px; width: 100%; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 style="font-size: 1.25rem; font-weight: 900;">Histórico do Equipamento</h3>
            <button onclick="document.getElementById('historyModal').style.display='none'" style="background: none; border: none; cursor: pointer; font-size: 1.5rem;">&times;</button>
        </div>
        <div id="historyContent"></div>
    </div>
</div>

<script>
    // --- BUSCADOR DE EQUIPAMENTOS ---
    function filterAssets(val) {
        const results = document.getElementById('asset_results');
        const options = results.querySelectorAll('.asset-option');
        let count = 0;
        
        val = val.toLowerCase().trim();
        results.style.display = 'block';
        
        options.forEach(opt => {
            const name = opt.getAttribute('data-name').toLowerCase();
            const patrimony = opt.getAttribute('data-patrimony').toLowerCase();
            
            if (name.includes(val) || patrimony.includes(val)) {
                opt.style.display = 'block';
                count++;
            } else {
                opt.style.display = 'none';
            }
        });
        
        if (count === 0) {
            // Se nenhum resultado, mostrar mensagem ou ocultar?
        }
    }

    function selectAsset(opt) {
        const id = opt.getAttribute('data-id');
        const name = opt.getAttribute('data-name');
        const patrimony = opt.getAttribute('data-patrimony');
        
        document.getElementById('selected_asset_id').value = id;
        document.getElementById('asset_search').value = `${name} (${patrimony})`;
        document.getElementById('asset_results').style.display = 'none';
        
        // Estilização do item selecionado
        document.querySelectorAll('.asset-option').forEach(el => el.classList.remove('selected'));
        opt.classList.add('selected');
    }

    // Fechar resultados ao clicar fora
    document.addEventListener('click', (e) => {
        const container = document.getElementById('asset_selector_container');
        if (container && !container.contains(e.target)) {
            document.getElementById('asset_results').style.display = 'none';
        }
    });

    const loansData = <?= json_encode($loans) ?>;
    
    function updateLoanInfo(select) {
        const option = select.options[select.selectedIndex];
        const sector = option.getAttribute('data-sector');
        const unit = option.getAttribute('data-unit');
        const unitName = option.getAttribute('data-unit-name');
        const avatar = option.getAttribute('data-avatar');
        
        document.getElementById('sector').value = sector || '';
        document.getElementById('unit_id').value = unit || '';
        document.getElementById('unit_display').value = unitName || '';

        const preview = document.getElementById('borrower_avatar_preview');
        if (avatar) {
            preview.innerHTML = `<img src="${avatar}" style="width: 100%; height: 100%; object-fit: cover;">`;
            preview.style.borderStyle = 'solid';
            preview.style.borderColor = 'var(--crm-purple)';
        } else {
            preview.innerHTML = `<i class="fa-solid fa-user" style="color: #94a3b8;"></i>`;
            preview.style.borderStyle = 'dashed';
            preview.style.borderColor = '#cbd5e1';
        }
    }
    
    function showHistory(assetId) {
        const history = loansData.filter(l => l.asset_id === assetId);
        
        if (history.length === 0) {
            document.getElementById('historyContent').innerHTML = '<p style="text-align: center; color: #64748b; padding: 2rem;">Nenhum histórico encontrado</p>';
        } else {
            let html = '<div style="display: grid; gap: 1rem;">';
            
            history.forEach((h, idx) => {
                const statusColor = h.status === 'Ativo' ? '#FBBF24' : '#10B981';
                html += `
                    <div style="background: linear-gradient(135deg, rgba(91, 33, 182, 0.05), rgba(251, 191, 36, 0.02)); padding: 1.5rem; border-radius: 1rem; border-left: 4px solid ${statusColor};">
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                            <div>
                                <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; margin-bottom: 0.25rem;">EQUIPAMENTO</div>
                                <div style="font-weight: 900; color: var(--crm-purple);">${h.asset_name}</div>
                            </div>
                            <div>
                                <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; margin-bottom: 0.25rem;">RESPONSÁVEL</div>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    ${h.borrower_avatar 
                                        ? `<img src="${h.borrower_avatar}" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--crm-purple);">`
                                        : `<div style="width: 40px; height: 40px; border-radius: 50%; background: #e2e8f0; border: 2px solid #cbd5e1; display: flex; align-items: center; justify-content: center; font-size: 16px;">👤</div>`
                                    }
                                    <div style="font-weight: 900; color:var(--crm-black); font-size: 1rem;">${h.borrower_name}</div>
                                </div>
                            </div>
                             <div>
                                <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; margin-bottom: 0.25rem;">DATA EMPRÉSTIMO</div>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    ${h.lender_avatar 
                                        ? `<img src="${h.lender_avatar}" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;" title="Emprestado por: ${h.lender_name || h.loaned_by_name}">`
                                        : `<div style="width: 24px; height: 24px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 10px; border: 1px solid #e2e8f0;" title="Emprestado por: ${h.loaned_by_name}">👤</div>`
                                    }
                                    <div style="line-height: 1.1;">
                                        <div style="font-weight: 700; color: #334155;">${new Date(h.loan_date).toLocaleString('pt-BR')}</div>
                                        <small style="font-size: 0.65rem; color: #94a3b8;">Por: ${h.lender_name || h.loaned_by_name || 'N/A'}</small>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; margin-bottom: 0.25rem;">DATA DEVOLUÇÃO</div>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    ${h.return_date 
                                        ? (h.receiver_avatar 
                                            ? `<img src="${h.receiver_avatar}" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover;" title="Recebido por: ${h.receiver_name || h.received_by}">`
                                            : `<div style="width: 24px; height: 24px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 10px; border: 1px solid #e2e8f0;" title="Recebido por: ${h.received_by}">👤</div>`)
                                        : ''
                                    }
                                    <div style="line-height: 1.1;">
                                        <div style="font-weight: 700; color: ${h.return_date ? '#10B981' : '#EF4444'};">
                                            ${h.return_date ? new Date(h.return_date).toLocaleString('pt-BR') : 'Não devolvido'}
                                        </div>
                                        ${h.return_date ? `<small style="font-size: 0.65rem; color: #94a3b8;">Por: ${h.receiver_name || h.received_by}</small>` : ''}
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; margin-bottom: 0.25rem;">SETOR</div>
                                <div style="font-weight: 700;">${h.sector}</div>
                            </div>
                            <div>
                                <div style="font-size: 0.75rem; color: #64748b; font-weight: 700; margin-bottom: 0.25rem;">STATUS</div>
                                <span class="badge badge-${h.status === 'Ativo' ? 'warning' : 'success'}">${h.status}</span>
                            </div>
                        </div>
                        ${h.observations ? `<div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e5e7eb;"><div style="font-size: 0.75rem; color: #64748b; font-weight: 700; margin-bottom: 0.25rem;">OBSERVAÇÕES</div><div style="color: #64748b;">${h.observations}</div></div>` : ''}
                    </div>
                `;
            });
            
            html += '</div>';
            document.getElementById('historyContent').innerHTML = html;
        }
        
        document.getElementById('historyModal').style.display = 'flex';
        
    }
    
    
    function openEditModal(loan) {
        document.getElementById('edit_loan_id').value = loan.id;
        document.getElementById('edit_asset_name').value = loan.asset_name;
        document.getElementById('edit_borrower_select').value = loan.borrower_id;
        
        // Helper function for date formatting
        const formatForInput = (dateStr) => {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            return date.getFullYear() + '-' + 
                   String(date.getMonth() + 1).padStart(2, '0') + '-' + 
                   String(date.getDate()).padStart(2, '0') + 'T' + 
                   String(date.getHours()).padStart(2, '0') + ':' + 
                   String(date.getMinutes()).padStart(2, '0');
        };

        document.getElementById('edit_expected_return_date').value = formatForInput(loan.expected_return_date);
        document.getElementById('edit_return_date').value = formatForInput(loan.return_date);
        
        document.getElementById('editLoanModal').style.display = 'flex';
    }

    // Fechar modais ao clicar fora
    window.onclick = function(event) {
        const historyModal = document.getElementById('historyModal');
        const loanModal = document.getElementById('loanModal');
        const editLoanModal = document.getElementById('editLoanModal');
        if (event.target == historyModal) historyModal.style.display = "none";
        if (event.target == loanModal) loanModal.style.display = "none";
        if (event.target == editLoanModal) editLoanModal.style.display = "none";
    }
</script>

