<?php
// Migrações SaaS
try { $pdo->exec("ALTER TABLE assets ADD COLUMN company_id INT NOT NULL DEFAULT 1"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE assets ADD COLUMN estimated_value DECIMAL(12,2) DEFAULT 0"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE assets ADD COLUMN image_url VARCHAR(255) DEFAULT NULL"); } catch(Exception $e) {}

try {
    $pdo->exec("ALTER TABLE assets MODIFY patrimony_id VARCHAR(255) NULL");
} catch(Exception $e) { /* falha silenciosa se houver erro ao modificar */ }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_asset') {
    $compId = getCurrentUserCompanyId();
    $image_name = null;
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $image_name = 'asset_' . time() . '.' . pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
        move_uploaded_file($_FILES['product_image']['tmp_name'], __DIR__ . '/../uploads/' . $image_name);
    }
    
    $stmt = $pdo->prepare("INSERT INTO assets (id, name, category, patrimony_id, sector, unit_id, status, responsible_name, estimated_value, image_url, company_id) VALUES (?, ?, ?, ?, ?, ?, 'Ativo', ?, ?, ?, ?)");
    $estimated = floatval(str_replace(['.', ','], ['', '.'], $_POST['estimated_value'] ?? '0'));
    $patrimony_id = !empty($_POST['patrimony_id']) ? $_POST['patrimony_id'] : null;
    $stmt->execute(['A' . time(), $_POST['name'], $_POST['category'], $patrimony_id, $_POST['sector'], $_POST['unit_id'], $_POST['responsible_name'], $estimated, $image_name, $compId]);
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
    $params = [$_POST['name'], $_POST['category'], $patrimony_id, $_POST['sector'], $_POST['unit_id'], $_POST['status'], $_POST['responsible_name'], $estimated];
    
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $image_name = 'asset_' . time() . '.' . pathinfo($_FILES['product_image']['name'], PATHINFO_EXTENSION);
        move_uploaded_file($_FILES['product_image']['tmp_name'], __DIR__ . '/../uploads/' . $image_name);
        $image_update = ", image_url = ?";
        $params[] = $image_name;
    }
    
    $params[] = $_POST['asset_id'];
    $params[] = $compId;
    
    $stmt = $pdo->prepare("UPDATE assets SET name = ?, category = ?, patrimony_id = ?, sector = ?, unit_id = ?, status = ?, responsible_name = ?, estimated_value = ? $image_update WHERE id = ? AND company_id = ?");
    $stmt->execute($params);
    header('Location: ?page=patrimonio&success=2');
    exit;
}

$search = $_GET['search'] ?? '';
$unit_filter = $_GET['unit'] ?? '';
$compId = getCurrentUserCompanyId();

// Filtro baseado no perfil do usuário - Agora liberado se tiver acesso ao menu
$query = "SELECT a.*, u.name as unit_name FROM assets a 
          LEFT JOIN units u ON BINARY a.unit_id = BINARY u.id WHERE a.company_id = ?";
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

$stmt_users = $pdo->prepare("SELECT u.id, u.name, u.email, u.phone, u.sector, u.role, u.unit_id, un.name as unit_name FROM users u LEFT JOIN units un ON BINARY u.unit_id = BINARY un.id WHERE u.company_id = ? ORDER BY u.name");
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
    <div class="page-header-actions">
        <button class="btn-primary" onclick="window.location.href='?page=patrimonio&action=novo'">
            <i class="fa-solid fa-plus"></i>
            Cadastrar Ativo
        </button>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<div style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(16, 185, 129, 0.05) 100%); border: 1px solid rgba(16, 185, 129, 0.3); color: #059669; padding: 1rem; border-radius: 1rem; margin-bottom: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem;">
    <i class="fa-solid fa-circle-check"></i>
    <?= $_GET['success'] == '1' ? 'Ativo cadastrado com sucesso!' : ($_GET['success'] == '2' ? 'Ativo atualizado com sucesso!' : 'Categoria excluída com sucesso!') ?>
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
        
        <button type="submit" class="btn-primary">
            <i class="fa-solid fa-magnifying-glass"></i>
            Filtrar
        </button>
    </form>
</div>

<div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>Ativo</th>
                <th>Unidade / Setor</th>
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
                    <div style="font-size: 0.625rem; color: #94a3b8; text-transform: uppercase; font-weight: 700;">
                        <?= htmlspecialchars($asset['sector']) ?>
                    </div>
                </td>
                <td style="font-family: monospace; font-size: 0.75rem; color: var(--text-soft);">
                    <?= htmlspecialchars($asset['patrimony_id'] ?? 'N/A') ?>
                </td>
                <td style="font-weight: 600;">
                    <?= htmlspecialchars($asset['responsible_name'] ?? 'Não atribuído') ?>
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
                    <div style="display: flex; gap: 0.5rem; justify-content: center;">
                        <a href="?page=patrimonio&hist_id=<?= $asset['id'] ?>" class="btn-icon" title="Histórico">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </a>
                        <a href="?page=patrimonio&action=edit&id=<?= $asset['id'] ?>" class="btn-icon" title="Editar">
                            <i class="fa-solid fa-pen"></i>
                        </a>
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
        <form method="POST" enctype="multipart/form-data">
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
                    <input type="hidden" name="responsible_name" id="responsibleName">
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
        <form method="POST" enctype="multipart/form-data">
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
                                <?= $editAsset['responsible_name'] == $u['name'] ? 'selected' : '' ?>
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
    
    function addCategory() {
        const newCat = document.getElementById('new_category').value.trim();
        if (!newCat) {
            alert('Digite o nome da categoria');
            return;
        }
        useCategory(newCat);
    }

    function useCategory(name) {
        // Tenta preencher no campo de novo ativo ou editivo
        const inputNovo = document.getElementById('category_input');
        const inputEdit = document.getElementById('edit_category_input');
        
        if (inputNovo) inputNovo.value = name;
        if (inputEdit) inputEdit.value = name;
        
        document.getElementById('categoryModal').style.display = 'none';
        document.getElementById('new_category').value = '';
    }

    function deleteCategory(name) {
        if (confirm('Deseja realmente excluir a categoria "' + name + '"?\nEsta ação desvinculada a categoria de todos os ativos associados.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_category">
                <input type="hidden" name="category_name" value="${name}">
            `;
            document.body.appendChild(form);
            form.submit();
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
</script>

