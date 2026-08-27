<?php
// Migrações SaaS
try { $pdo->exec("ALTER TABLE assets ADD COLUMN company_id INT NOT NULL DEFAULT 1"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE assets ADD COLUMN estimated_value DECIMAL(12,2) DEFAULT 0"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE assets ADD COLUMN image_url VARCHAR(255) DEFAULT NULL"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE assets MODIFY responsible_id VARCHAR(50) NULL"); } catch(Exception $e) {}
try { $pdo->exec("UPDATE assets SET responsible_id = NULL WHERE responsible_id = '0'"); } catch(Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'import_excel') {
    $compId = getCurrentUserCompanyId();
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == 0) {
        require_once __DIR__ . '/../SimpleXLSX.php';
        
        try {
            $xlsx = new SimpleXLSX($_FILES['excel_file']['tmp_name']);
            $sheets = $xlsx->getSheets();
            $pdo->beginTransaction();
            
            foreach ($sheets as $sheetName => $rows) {
                if (strtoupper(trim($sheetName)) === 'RESULTADO FINAL') continue;
                
                $sectorName = $sheetName;
                    
                    // skip header
                    for ($i = 1; $i < count($rows); $i++) {
                        $row = $rows[$i];
                        
                        // Check if empty row or TOTAL DO SETOR
                        if (empty($row[0]) && empty($row[1])) continue;
                        if (strtoupper(trim($row[4] ?? '')) === 'TOTAL DO SETOR:') break;
                        
                        $pat_id = trim($row[0] ?? '');
                        $name = trim($row[1] ?? '');
                        $category = trim($row[2] ?? '');
                        $resp_name = trim($row[3] ?? '');
                        $status = trim($row[4] ?? 'Ativo');
                        
                        $val_str = trim($row[5] ?? '0');
                        $val_str = str_replace(['R$', ' ', '.'], '', $val_str);
                        $val_str = str_replace(',', '.', $val_str);
                        $estimated_value = floatval($val_str);
                        
                        if (empty($name)) continue;
                        
                        $asset_id = null;
                        if (!empty($pat_id)) {
                            $stmt = $pdo->prepare("SELECT id FROM assets WHERE patrimony_id = ? AND company_id = ?");
                            $stmt->execute([$pat_id, $compId]);
                            $asset_id = $stmt->fetchColumn();
                        }
                        
                        if ($asset_id) {
                            $upd = $pdo->prepare("UPDATE assets SET name = ?, category = ?, sector = ?, status = ?, responsible_name = ?, estimated_value = ? WHERE id = ? AND company_id = ?");
                            $upd->execute([$name, $category, $sectorName, $status, $resp_name, $estimated_value, $asset_id, $compId]);
                        } else {
                            $new_id = 'A' . uniqid();
                            $ins = $pdo->prepare("INSERT INTO assets (id, name, category, patrimony_id, sector, status, responsible_name, estimated_value, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $ins->execute([$new_id, $name, $category, $pat_id, $sectorName, $status, $resp_name, $estimated_value, $compId]);
                        }
                    }
                }
                $pdo->commit();
                header('Location: ?page=patrimonio&success=import');
                exit;
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                header('Location: ?page=patrimonio&error=invalid_file');
                exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_asset') {
    $compId = getCurrentUserCompanyId();

    // Prevenção de duplicidade por patrimony_id (se fornecido)
    if (!empty($_POST['patrimony_id'])) {
        $check = $pdo->prepare("SELECT id FROM assets WHERE patrimony_id = ? AND company_id = ?");
        $check->execute([$_POST['patrimony_id'], $compId]);
        if ($check->fetch()) {
            header('Location: ?page=patrimonio&error=duplicate_patrimony');
            exit;
        }
    }

    $image_name = null;
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $image_name = 'asset_' . time() . '.' . pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
        move_uploaded_file($_FILES['product_image']['tmp_name'], __DIR__ . '/../uploads/' . $image_name);
    }
    
    $stmt = $pdo->prepare("INSERT INTO assets (id, name, category, patrimony_id, sector, unit_id, status, responsible_name, responsible_id, estimated_value, image_url, company_id) VALUES (?, ?, ?, ?, ?, ?, 'Ativo', ?, ?, ?, ?, ?)");
    $estimated = floatval(str_replace(['.', ','], ['', '.'], $_POST['estimated_value'] ?? '0'));
    $patrimony_id = !empty($_POST['patrimony_id']) ? $_POST['patrimony_id'] : null;
    $responsible_id = !empty($_POST['responsible_select']) ? $_POST['responsible_select'] : null;
    $stmt->execute(['A' . uniqid(), $_POST['name'], $_POST['category'], $patrimony_id, $_POST['sector'], $_POST['unit_id'], $_POST['responsible_name'], $responsible_id, $estimated, $image_name, $compId]);
    header('Location: ?page=patrimonio&success=1');
    exit;
}

// Handler para Exclusão de Categorias
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_category') {
    $compId = getCurrentUserCompanyId();
    $catToDelete = $_POST['category_name'];
    $stmt = $pdo->prepare("UPDATE assets SET category = NULL WHERE category = ? AND company_id = ?");
    $stmt->execute([$catToDelete, $compId]);
    header('Location: ?page=patrimonio&success=3');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_asset') {
    $compId = getCurrentUserCompanyId();
    $estimated = floatval(str_replace(['.', ','], ['', '.'], $_POST['estimated_value'] ?? '0'));
    
    $image_update = "";
    $patrimony_id = !empty($_POST['patrimony_id']) ? $_POST['patrimony_id'] : null;
    $responsible_id = !empty($_POST['responsible_select']) ? $_POST['responsible_select'] : null;
    $params = [$_POST['name'], $_POST['category'], $patrimony_id, $_POST['sector'], $_POST['unit_id'], $_POST['status'], $_POST['responsible_name'], $responsible_id, $estimated];
    
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $image_name = 'asset_' . time() . '.' . pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
        move_uploaded_file($_FILES['product_image']['tmp_name'], __DIR__ . '/../uploads/' . $image_name);
        $image_update = ", image_url = ?";
        $params[] = $image_name;
    }
    
    $params[] = $_POST['asset_id'];
    $params[] = $compId;
    
    $stmt = $pdo->prepare("UPDATE assets SET name = ?, category = ?, patrimony_id = ?, sector = ?, unit_id = ?, status = ?, responsible_name = ?, responsible_id = ?, estimated_value = ? $image_update WHERE id = ? AND company_id = ?");
    $stmt->execute($params);
    header('Location: ?page=patrimonio&success=2');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_asset') {
    $compId = getCurrentUserCompanyId();
    try {
        $stmt = $pdo->prepare("DELETE FROM assets WHERE id = ? AND company_id = ?");
        $stmt->execute([$_POST['asset_id'], $compId]);
        header('Location: ?page=patrimonio&success=deleted');
        exit;
    } catch (PDOException $e) {
        $errorMsg = 'Erro: Este ativo não pode ser excluído pois está vinculado a chamados, histórico ou empréstimos.';
        header('Location: ?page=patrimonio&error=' . urlencode($errorMsg));
        exit;
    }
}

$search = $_GET['search'] ?? '';
$unit_filter = $_GET['unit'] ?? '';
$sector_filter = $_GET['sector'] ?? '';
$compId = getCurrentUserCompanyId();

// Filtro baseado no perfil do usuário - Agora liberado se tiver acesso ao menu
$query = "SELECT a.*, u.name as unit_name, res.avatar_url as resp_avatar, res.name as resp_name 
          FROM assets a 
          LEFT JOIN units u ON CONVERT(a.unit_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(u.id USING utf8mb4) COLLATE utf8mb4_unicode_ci 
          LEFT JOIN users res ON a.responsible_id = res.id
          WHERE a.company_id = ?";
$params = [$compId];

if ($search) {
    $query .= " AND (a.name LIKE ? OR a.patrimony_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($unit_filter) {
    $query .= " AND a.unit_id = ?";
    $params[] = $unit_filter;
}

if ($sector_filter) {
    $query .= " AND a.sector = ?";
    $params[] = $sector_filter;
}

$query .= " ORDER BY a.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);

$stmt_units = $pdo->prepare("SELECT * FROM units WHERE company_id = ?");
$stmt_units->execute([$compId]);
$units = $stmt_units->fetchAll();

$stmt_cats = $pdo->prepare("SELECT DISTINCT category FROM assets WHERE category IS NOT NULL AND category != '' AND company_id = ? ORDER BY category");
$stmt_cats->execute([$compId]);
$categories = $stmt_cats->fetchAll();

$stmt_sects = $pdo->prepare("SELECT DISTINCT sector FROM assets WHERE sector IS NOT NULL AND sector != '' AND company_id = ? ORDER BY sector");
$stmt_sects->execute([$compId]);
$sectors = $stmt_sects->fetchAll();

$stmt_users = $pdo->prepare("SELECT u.id, u.name, u.email, u.phone, u.sector, u.role, u.unit_id, un.name as unit_name FROM users u LEFT JOIN units un ON CONVERT(u.unit_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(un.id USING utf8mb4) COLLATE utf8mb4_unicode_ci WHERE u.company_id = ? ORDER BY u.name");
$stmt_users->execute([$compId]);
$all_users = $stmt_users->fetchAll();

// Ativo para edição
$editAsset = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $stmt_edit = $pdo->prepare("SELECT * FROM assets WHERE id = ? AND company_id = ?");
    $stmt_edit->execute([$_GET['id'], $compId]);
    $editAsset = $stmt_edit->fetch();
}
?>

<div class="page-header">
    <div class="page-header-info">
        <div class="page-header-icon">
            <i class="fa-solid fa-vault"></i>
        </div>
        <div class="page-header-text">
            <h2>Gestão de Ativos & Patrimônio</h2>
            <p>Controle detalhado de inventário, movimentações e valores.</p>
        </div>
    </div>
    <div class="page-header-actions" style="display: flex; gap: 1rem; align-items: center;">
        <div style="flex: 1; min-width: 250px; position: relative;">
            <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.875rem;"></i>
            <input type="text" id="search-patrimonio" class="form-input" placeholder="Filtrar ativos..." style="padding-left: 2.5rem; border-radius: 0.75rem; background: #fff;" onkeyup="filterTable('search-patrimonio', 'table-patrimonio')" autocomplete="off">
        </div>
        <div style="display: flex; gap: 1rem;">
            <button class="btn-primary" style="background: #3b82f6; color: white;" onclick="document.getElementById('importModal').style.display='flex'">
                <i class="fa-solid fa-file-import"></i>
                Importar Excel
            </button>
            <a href="pages/export_patrimonio.php" target="_blank" class="btn-primary" style="background: #10b981; color: white; text-decoration: none;">
                <i class="fa-solid fa-file-excel"></i>
                Exportar Excel
            </a>
            <button class="btn-primary" onclick="window.location.href='?page=patrimonio&action=novo'">
                <i class="fa-solid fa-plus"></i>
                Cadastrar Ativo
            </button>
        </div>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<div style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(16, 185, 129, 0.05) 100%); border: 1px solid rgba(16, 185, 129, 0.3); color: #059669; padding: 1rem; border-radius: 1rem; margin-bottom: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem;">
    <i class="fa-solid fa-circle-check"></i>
    <?= $_GET['success'] == '1' ? 'Ativo cadastrado com sucesso!' : ($_GET['success'] == '2' ? 'Ativo atualizado com sucesso!' : ($_GET['success'] == 'deleted' ? 'Ativo excluído com sucesso!' : ($_GET['success'] == 'import' ? 'Planilha importada e banco de dados atualizado com sucesso!' : 'Categoria excluída com sucesso!'))) ?>
</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(239, 68, 68, 0.05) 100%); border: 1px solid rgba(239, 68, 68, 0.3); color: #dc2626; padding: 1rem; border-radius: 1rem; margin-bottom: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem;">
    <i class="fa-solid fa-circle-exclamation"></i>
    <?= in_array($_GET['error'], ['duplicate_patrimony', 'invalid_file']) 
        ? ($_GET['error'] === 'duplicate_patrimony' ? 'Erro: Já existe um ativo cadastrado com este Número de Acesso.' : 'Erro: O arquivo enviado não é um Excel válido ou está corrompido.') 
        : htmlspecialchars($_GET['error']) ?>
</div>
<?php endif; ?>


<div class="glass-panel" style="padding: 1.5rem; margin-bottom: 1.5rem;">
    <form method="GET" style="display: flex; gap: 1rem; align-items: end;">
        <input type="hidden" name="page" value="patrimonio">
        
        <div style="flex: 1;">
            <label class="form-label">Buscar</label>
            <input type="text" name="search" class="form-input" placeholder="Nome ou patrimônio..." value="<?= htmlspecialchars($search) ?>">
        </div>
        
        <div style="width: 250px;">
            <label class="form-label">Unidade</label>
            <select name="unit" class="form-select">
                <option value="">Todas as Unidades</option>
                <?php foreach ($units as $unit): ?>
                    <option value="<?= $unit['id'] ?>" <?= $unit_filter == $unit['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($unit['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="width: 250px;">
            <label class="form-label">Setor</label>
            <select name="sector" class="form-select">
                <option value="">Todos os Setores</option>
                <?php foreach ($sectors as $s): ?>
                    <option value="<?= htmlspecialchars($s['sector']) ?>" <?= $sector_filter == $s['sector'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['sector']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <button type="submit" class="btn-primary">
            <i class="fa-solid fa-magnifying-glass"></i>
            Filtrar
        </button>
    </form>
</div>

<div class="table-responsive">
    <table id="table-patrimonio">
        <thead>
            <tr>
                <th>Ativo</th>
                <th>Unidade</th>
                <th>Setor</th>
                <th>Nº Acesso</th>
                <th>Responsável</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($asset = $stmt->fetch()): ?>
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <?php if (!empty($asset['image_url'])): ?>
                            <img src="uploads/<?= htmlspecialchars($asset['image_url']) ?>" style="width: 40px; height: 40px; border-radius: 0.75rem; object-fit: cover;">
                        <?php else: ?>
                            <div style="width: 40px; height: 40px; background: var(--crm-gray-light); border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; color: var(--text-soft);">
                                <i class="fa-solid fa-desktop"></i>
                            </div>
                        <?php endif; ?>
                        <span style="font-weight: 700;"><?= htmlspecialchars($asset['name']) ?></span>
                    </div>
                </td>
                <td>
                    <div style="font-weight: 700; color: var(--crm-purple); font-size: 0.75rem;">
                        <?= htmlspecialchars($asset['unit_name']) ?>
                    </div>
                </td>
                <td>
                    <div style="font-size: 0.75rem; color: var(--text-soft); font-weight: 700; text-transform: uppercase;">
                        <?= htmlspecialchars($asset['sector']) ?>
                    </div>
                </td>
                <td style="font-family: monospace; font-size: 0.75rem; color: var(--text-soft);">
                    <?= htmlspecialchars($asset['patrimony_id'] ?? 'N/A') ?>
                </td>
                <td>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--crm-purple); color: white; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; overflow: hidden; flex-shrink: 0;">
                            <?php if (!empty($asset['resp_avatar'])): ?>
                                <img src="<?= htmlspecialchars($asset['resp_avatar']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <?= strtoupper(substr($asset['responsible_name'] ?? 'U', 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <span style="font-weight: 600; font-size: 0.85rem; color: var(--text-main);"><?= htmlspecialchars($asset['responsible_name'] ?? 'Não atribuído') ?></span>
                    </div>
                </td>
                <td>
                    <span class="badge badge-<?= 
                        $asset['status'] == 'Ativo' ? 'success' : 
                        ($asset['status'] == 'Manutenção' ? 'warning' : 'info') 
                    ?>">
                        <?= htmlspecialchars($asset['status']) ?>
                    </span>
                </td>
                <td>
                    <div style="display: flex; gap: 0.5rem; justify-content: center;">
                        <a href="?page=patrimonio&hist_id=<?= $asset['id'] ?>" class="btn-icon" title="Histórico">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </a>
                        <a href="?page=patrimonio&action=edit&id=<?= $asset['id'] ?>" class="btn-icon" title="Editar">
                            <i class="fa-solid fa-pen"></i>
                        </a>
                        <button onclick="deleteAsset('<?= $asset['id'] ?>', '<?= htmlspecialchars(addslashes($asset['name'])) ?>')" class="btn-icon" title="Excluir" style="color: #ef4444; border: none; background: none; cursor: pointer;">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<?php if (isset($_GET['hist_id'])): 
    $hist_id = $_GET['hist_id'];
    $asset_info = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
    $asset_info->execute([$hist_id]);
    $asset_data = $asset_info->fetch();
    
    // Buscar Histórico de Empréstimos
    $loan_hist = $pdo->prepare("SELECT * FROM loans WHERE asset_id = ? ORDER BY created_at DESC");
    $loan_hist->execute([$hist_id]);
    $loans = $loan_hist->fetchAll();
    
    // Buscar Histórico de Chamados
    $ticket_hist = $pdo->prepare("SELECT t.*, u.name as req_name FROM tickets t LEFT JOIN users u ON t.requester_id = u.id WHERE t.asset_id = ? ORDER BY t.created_at DESC");
    $ticket_hist->execute([$hist_id]);
    $tickets = $ticket_hist->fetchAll();
?>
<div style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 900px; width: 100%; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 1rem;">
            <div>
                <h3 style="font-size: 1.25rem; font-weight: 900;">Histórico do Ativo: <?= htmlspecialchars($asset_data['name']) ?></h3>
                <p style="color: var(--text-soft); font-size: 0.875rem;">Patrimônio: <?= htmlspecialchars($asset_data['patrimony_id']) ?></p>
            </div>
            <button onclick="window.location.href='?page=patrimonio'" style="background: none; border: none; cursor: pointer; font-size: 1.5rem;">&times;</button>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <!-- Coluna Empréstimos -->
            <div>
                <h4 style="margin-bottom: 1rem; color: var(--crm-purple); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-right-left"></i> Empréstimos
                </h4>
                <?php if (empty($loans)): ?>
                    <p style="color: var(--text-soft); font-size: 0.875rem; font-style: italic;">Nenhum empréstimo registrado.</p>
                <?php else: ?>
                    <?php foreach ($loans as $l): ?>
                    <div style="padding: 1rem; border-left: 3px solid #5B21B6; background: #f8fafc; border-radius: 0 1rem 1rem 0; margin-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--text-soft); margin-bottom: 0.25rem;">
                            <span><i class="fa-solid fa-calendar"></i> <?= date('d/m/Y', strtotime($l['loan_date'])) ?></span>
                            <span class="badge badge-<?= $l['status'] == 'Ativo' ? 'warning' : 'success' ?>"><?= $l['status'] ?></span>
                        </div>
                        <div style="font-weight: 700; margin-bottom: 0.25rem;">Para: <?= htmlspecialchars($l['borrower_name']) ?></div>
                        <?php if ($l['return_date']): ?>
                            <div style="font-size: 0.75rem; color: #059669;">
                                <i class="fa-solid fa-check-double"></i> Devolvido em: <?= date('d/m/Y H:i', strtotime($l['return_date'])) ?>
                                <br><small>Recebido por: <?= htmlspecialchars($l['received_by'] ?? 'N/A') ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Coluna Chamados -->
            <div>
                <h4 style="margin-bottom: 1rem; color: #ef4444; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-headset"></i> Atendimentos / Manutenção
                </h4>
                <?php if (empty($tickets)): ?>
                    <p style="color: var(--text-soft); font-size: 0.875rem; font-style: italic;">Nenhum chamado registrado.</p>
                <?php else: ?>
                    <?php foreach ($tickets as $t): ?>
                    <div style="padding: 1rem; border-left: 3px solid #ef4444; background: #fef2f2; border-radius: 0 1rem 1rem 0; margin-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--text-soft); margin-bottom: 0.25rem;">
                            <span><i class="fa-solid fa-calendar"></i> <?= date('d/m/Y', strtotime($t['created_at'])) ?></span>
                            <span class="badge badge-<?= 
                                $t['status'] == 'Concluído' ? 'success' : 
                                ($t['status'] == 'Sem Solução' ? 'warning' : 'info') 
                            ?>"><?= $t['status'] ?></span>
                        </div>
                        <div style="font-weight: 700; margin-bottom: 0.25rem;"><?= htmlspecialchars($t['title']) ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-soft);">Solicitado por: <?= htmlspecialchars($t['req_name'] ?? 'N/A') ?></div>
                        <?php if ($t['closed_at']): ?>
                            <div style="font-size: 0.75rem; color: #059669; margin-top: 0.5rem; border-top: 1px dashed rgba(0,0,0,0.1); padding-top: 0.25rem;">
                                <i class="fa-solid fa-lock"></i> Finalizado em: <?= date('d/m/Y H:i', strtotime($t['closed_at'])) ?>
                                <br><small>Técnico: <?= htmlspecialchars($t['closed_by'] ?? 'N/A') ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['action']) && $_GET['action'] === 'novo'): ?>
<div style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 800px; width: 100%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 style="font-size: 1.25rem; font-weight: 900;">Cadastrar Novo Ativo</h3>
            <button onclick="window.location.href='?page=patrimonio'" style="background: none; border: none; cursor: pointer; font-size: 1.5rem;">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data" onsubmit="return handleAssetSubmit(this);">
            <input type="hidden" name="action" value="add_asset">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Nome do Produto *</label>
                    <input type="text" name="name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Categoria *</label>
                    <input type="text" name="category" id="category_input" class="form-input" list="categories" required>
                    <datalist id="categories">
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= htmlspecialchars($c['category']) ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <button type="button" onclick="document.getElementById('categoryModal').style.display='flex'" style="margin-top: 0.5rem; padding: 0.5rem; background: var(--crm-yellow); color: var(--crm-black); border: none; border-radius: 0.5rem; cursor: pointer; font-weight: 700; width: 100%;">
                        <i class="fa-solid fa-plus" style="width: 16px; height: 16px;"></i> Nova Categoria
                    </button>
                </div>
                <div class="form-group">
                    <label class="form-label">Número de Acesso</label>
                    <input type="text" name="patrimony_id" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Foto do Produto</label>
                    <input type="file" name="product_image" class="form-input" accept="image/*">
                </div>
                <div class="form-group">
                    <label class="form-label">Valor Aproximado (R$)</label>
                    <input type="text" name="estimated_value" class="form-input" placeholder="0,00" oninput="this.value=this.value.replace(/[^0-9.,]/g,'')">
                </div>
                <div class="form-group">
                    <label class="form-label">Responsável *</label>
                    <select name="responsible_select" id="responsibleSelect" class="form-select" onchange="fillResponsibleData()" required>
                        <option value="">Selecione o responsável</option>
                        <?php foreach ($all_users as $u): ?>
                            <option value="<?= $u['id'] ?>" 
                                <?= ($editAsset && $editAsset['responsible_id'] == $u['id']) ? 'selected' : '' ?>
                                data-name="<?= htmlspecialchars($u['name']) ?>"
                                data-email="<?= htmlspecialchars($u['email']) ?>"
                                data-phone="<?= htmlspecialchars($u['phone']) ?>"
                                data-sector="<?= htmlspecialchars($u['sector']) ?>"
                                data-role="<?= htmlspecialchars($u['role']) ?>"
                                data-unit="<?= $u['unit_id'] ?>"
                                data-unit-name="<?= htmlspecialchars($u['unit_name']) ?>">
                                <?= htmlspecialchars($u['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="responsible_name" id="responsibleName" value="<?= htmlspecialchars($editAsset['responsible_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">E-mail</label>
                    <input type="email" id="responsibleEmail" class="form-input" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Telefone</label>
                    <input type="text" id="responsiblePhone" class="form-input" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Perfil</label>
                    <input type="text" id="responsibleRole" class="form-input" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Unidade *</label>
                    <input type="text" id="responsibleUnitDisplay" class="form-input" readonly>
                    <select name="unit_id" id="responsibleUnit" style="display:none;" required>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Setor *</label>
                    <input type="text" name="sector" id="responsibleSector" class="form-input" readonly required>
                </div>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <button type="button" onclick="window.location.href='?page=patrimonio'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
            </div>
        </form>
<?php endif; ?>

<?php if ($editAsset): ?>
<div style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 800px; width: 100%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 style="font-size: 1.25rem; font-weight: 900;">Editar Ativo: <?= htmlspecialchars($editAsset['name']) ?></h3>
            <button onclick="window.location.href='?page=patrimonio'" style="background: none; border: none; cursor: pointer; font-size: 1.5rem;">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data" onsubmit="return handleAssetSubmit(this);">
            <input type="hidden" name="action" value="edit_asset">
            <input type="hidden" name="asset_id" value="<?= $editAsset['id'] ?>">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Nome do Produto *</label>
                    <input type="text" name="name" class="form-input" value="<?= htmlspecialchars($editAsset['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Categoria *</label>
                    <input type="text" name="category" id="edit_category_input" class="form-input" list="categories" value="<?= htmlspecialchars($editAsset['category']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Número de Acesso</label>
                    <input type="text" name="patrimony_id" class="form-input" value="<?= htmlspecialchars($editAsset['patrimony_id'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Foto do Produto</label>
                    <input type="file" name="product_image" class="form-input" accept="image/*">
                    <?php if (!empty($editAsset['image_url'])): ?>
                        <div style="margin-top: 0.5rem; font-size: 0.8rem; color: var(--text-soft);">
                            <img src="uploads/<?= htmlspecialchars($editAsset['image_url']) ?>" style="height: 40px; border-radius: 4px; vertical-align: middle;"> Imagem atual
                        </div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label class="form-label">Valor Aproximado (R$)</label>
                    <input type="text" name="estimated_value" class="form-input" value="<?= number_format($editAsset['estimated_value'] ?? 0, 2, ',', '.') ?>" oninput="this.value=this.value.replace(/[^0-9.,]/g,'')">
                </div>
                <div class="form-group">
                    <label class="form-label">Status *</label>
                    <select name="status" class="form-select" required>
                        <option value="Ativo" <?= $editAsset['status'] == 'Ativo' ? 'selected' : '' ?>>Ativo</option>
                        <option value="Manutenção" <?= $editAsset['status'] == 'Manutenção' ? 'selected' : '' ?>>Manutenção</option>
                        <option value="Inativo" <?= $editAsset['status'] == 'Inativo' ? 'selected' : '' ?>>Inativo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Responsável *</label>
                    <select name="responsible_select" id="editResponsibleSelect" class="form-select" onchange="fillEditResponsibleData()" required>
                        <option value="">Selecione o responsável</option>
                        <?php foreach ($all_users as $u): ?>
                            <option value="<?= $u['id'] ?>" 
                                <?= $editAsset['responsible_id'] == $u['id'] ? 'selected' : '' ?>
                                data-name="<?= htmlspecialchars($u['name']) ?>"
                                data-email="<?= htmlspecialchars($u['email']) ?>"
                                data-phone="<?= htmlspecialchars($u['phone']) ?>"
                                data-sector="<?= htmlspecialchars($u['sector']) ?>"
                                data-role="<?= htmlspecialchars($u['role']) ?>"
                                data-unit="<?= $u['unit_id'] ?>"
                                data-unit-name="<?= htmlspecialchars($u['unit_name']) ?>">
                                <?= htmlspecialchars($u['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="responsible_name" id="editResponsibleName" value="<?= htmlspecialchars($editAsset['responsible_name']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Unidade *</label>
                    <select name="unit_id" id="editResponsibleUnit" class="form-select" required>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $editAsset['unit_id'] == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Setor *</label>
                    <input type="text" name="sector" id="editResponsibleSector" class="form-input" value="<?= htmlspecialchars($editAsset['sector']) ?>" required>
                </div>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <button type="button" onclick="window.location.href='?page=patrimonio'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Atualizar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Modal Gerenciar Categorias -->
<div id="categoryModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); z-index: 2000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 550px; width: 100%; max-height: 80vh; display: flex; flex-direction: column;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 style="font-size: 1.25rem; font-weight: 900;">Gerenciar Categorias</h3>
            <button onclick="document.getElementById('categoryModal').style.display='none'" style="background: none; border: none; cursor: pointer; font-size: 1.5rem;">&times;</button>
        </div>
        
        <div style="margin-bottom: 2rem; padding: 1rem; background: var(--bg-main); color: var(--text-main);">
            <label class="form-label">Cadastrar Nova Categoria</label>
            <div style="display: flex; gap: 0.5rem;">
                <input type="text" id="new_category" class="form-input" placeholder="Digite o nome...">
                <button type="button" onclick="addCategory()" class="btn-primary" style="padding: 0.5rem 1rem;"><i class="fa-solid fa-plus"></i></button>
            </div>
        </div>

        <div style="flex: 1; overflow-y: auto;">
            <label class="form-label">Categorias Existentes</label>
            <div style="display: grid; gap: 0.5rem;">
                <?php if (empty($categories)): ?>
                    <p style="text-align: center; color: var(--text-soft); font-style: italic; padding: 1rem;">Nenhuma categoria cadastrada.</p>
                <?php else: ?>
                    <?php foreach ($categories as $c): 
                        // Contar ativos nesta categoria
                        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE category = ?");
                        $countStmt->execute([$c['category']]);
                        $count = $countStmt->fetchColumn();
                    ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main);">
                            <span style="font-weight: 700; color: var(--crm-text);"><?= htmlspecialchars($c['category']) ?> <small style="color: #94a3b8; font-weight: 500;">(<?= $count ?> ativos)</small></span>
                            <div style="display: flex; gap: 0.5rem;">
                                <button type="button" onclick="useCategory('<?= addslashes($c['category']) ?>')" class="btn-icon" title="Usar esta" style="color: #10B981;"><i class="fa-solid fa-check"></i></button>
                                <button type="button" onclick="deleteCategory('<?= addslashes($c['category']) ?>')" class="btn-icon" title="Excluir" style="color: #ef4444;"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Importação Excel -->
<div id="importModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); z-index: 2000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 500px; width: 100%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 style="font-size: 1.25rem; font-weight: 900;"><i class="fa-solid fa-file-import" style="color: #3b82f6;"></i> Importar Patrimônio</h3>
            <button onclick="document.getElementById('importModal').style.display='none'" style="background: none; border: none; cursor: pointer; font-size: 1.5rem;">&times;</button>
        </div>
        
        <p style="color: var(--text-soft); font-size: 0.875rem; margin-bottom: 1.5rem; line-height: 1.5;">
            Anexe aqui a mesma planilha gerada pelo botão "Exportar Excel". O sistema identificará os setores pelas abas e usará a coluna "ID Patrimônio" para atualizar o que já existe ou cadastrar novos itens.
        </p>

        <form method="POST" enctype="multipart/form-data" onsubmit="return handleAssetSubmit(this);">
            <input type="hidden" name="action" value="import_excel">
            <div class="form-group" style="margin-bottom: 2rem;">
                <label class="form-label">Arquivo Excel (.xlsx)</label>
                <input type="file" name="excel_file" class="form-input" accept=".xlsx" required style="padding: 1rem;">
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" onclick="document.getElementById('importModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary" style="background: #3b82f6;"><i class="fa-solid fa-upload"></i> Processar Importação</button>
            </div>
        </form>
    </div>
</div>

<script>
    function fillResponsibleData() {
        const select = document.getElementById('responsibleSelect');
        const option = select.options[select.selectedIndex];
        
        if (option.value) {
            document.getElementById('responsibleName').value = option.getAttribute('data-name');
            document.getElementById('responsibleEmail').value = option.getAttribute('data-email');
            document.getElementById('responsiblePhone').value = option.getAttribute('data-phone');
            document.getElementById('responsibleSector').value = option.getAttribute('data-sector');
            document.getElementById('responsibleRole').value = option.getAttribute('data-role');
            document.getElementById('responsibleUnit').value = option.getAttribute('data-unit');
            document.getElementById('responsibleUnitDisplay').value = option.getAttribute('data-unit-name');
        } else {
            document.getElementById('responsibleName').value = '';
            document.getElementById('responsibleEmail').value = '';
            document.getElementById('responsiblePhone').value = '';
            document.getElementById('responsibleSector').value = '';
            document.getElementById('responsibleRole').value = '';
            document.getElementById('responsibleUnit').value = '';
            document.getElementById('responsibleUnitDisplay').value = '';
        }
    }

    function fillEditResponsibleData() {
        const select = document.getElementById('editResponsibleSelect');
        const option = select.options[select.selectedIndex];
        
        if (option.value) {
            document.getElementById('editResponsibleName').value = option.getAttribute('data-name');
            document.getElementById('editResponsibleSector').value = option.getAttribute('data-sector');
            document.getElementById('editResponsibleUnit').value = option.getAttribute('data-unit');
        }
    }

    function addCategory() {
        const newCat = document.getElementById('new_category').value.trim();
        if (!newCat) {
            alert('Digite o nome da categoria');
            return;
        }
        useCategory(newCat);
    }

    function useCategory(name) {
        const inputNovo = document.getElementById('category_input');
        const inputEdit = document.getElementById('edit_category_input');
        if (inputNovo) inputNovo.value = name;
        if (inputEdit) inputEdit.value = name;
        document.getElementById('categoryModal').style.display = 'none';
        document.getElementById('new_category').value = '';
    }

    function deleteCategory(name) {
        if (confirm('Deseja realmente excluir a categoria "' + name + '"?\nEsta ação desvinculada a categoria de todos os ativos associados.')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="delete_category"><input type="hidden" name="category_name" value="' + name + '">';
            document.body.appendChild(form);
            form.submit();
        }
    }

    function handleAssetSubmit(form) {
        var btn = form.querySelector('button[type="submit"]');
        if (btn.disabled) return false;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Salvando...';
        return true;
    }

    function filterTable(inputId, tableId) {
        var input = document.getElementById(inputId);
        var filter = input.value.toLowerCase();
        var table = document.getElementById(tableId);
        var tr = table.getElementsByTagName("tr");

        for (var i = 1; i < tr.length; i++) {
            tr[i].style.display = "none";
            var td = tr[i].getElementsByTagName("td");
            for (var j = 0; j < td.length; j++) {
                if (td[j]) {
                    var txtValue = td[j].textContent || td[j].innerText;
                    if (txtValue.toLowerCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                        break;
                    }
                }
            }
        }
    }

    function deleteAsset(id, name) {
        if (confirm('Deseja realmente excluir o ativo "' + name + '"?\nEsta ação não pode ser desfeita.')) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="delete_asset">' +
                             '<input type="hidden" name="asset_id" value="' + id + '">';
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>
