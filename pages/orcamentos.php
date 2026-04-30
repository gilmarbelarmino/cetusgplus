<?php
require_once 'access_control.php';

// Migrações SaaS - Lista de Desejos
try { $pdo->exec("ALTER TABLE wishlist_requests ADD COLUMN company_id INT NOT NULL DEFAULT 1"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE wishlist_items ADD COLUMN company_id INT NOT NULL DEFAULT 1"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE budget_requests ADD COLUMN wishlist_id VARCHAR(50) NULL"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE budget_quotes ADD COLUMN company_id INT NOT NULL DEFAULT 1"); } catch(Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $compId = getCurrentUserCompanyId();
    if ($_POST['action'] === 'approve_budget') {
        $stmt = $pdo->prepare("UPDATE budget_requests SET status = 'Aprovado', approved_by = ?, approved_at = NOW() WHERE id = ? AND company_id = ?");
        $stmt->execute([$user['id'], $_POST['budget_id'], $compId]);
        header('Location: ?page=orcamentos&success=2');
        exit;
    }

    if ($_POST['action'] === 'reject_budget') {
        $stmt = $pdo->prepare("UPDATE budget_requests SET status = 'Rejeitado', approved_by = ?, rejection_reason = ?, rejected_at = NOW() WHERE id = ? AND company_id = ?");
        $stmt->execute([$user['id'], $_POST['rejection_reason'], $_POST['budget_id'], $compId]);
        header('Location: ?page=orcamentos&success=3');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_budget') {
    $compId = getCurrentUserCompanyId();
    $budget_id = 'B' . time();
    $stmt = $pdo->prepare("INSERT INTO budget_requests (id, product_name, description, quantity, sector, unit_id, requester_id, company_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pendente', NOW())");
    $stmt->execute([$budget_id, $_POST['product_name'], $_POST['description'], $_POST['quantity'], $_POST['sector'], $_POST['unit_id'], $_POST['requester_id'], $compId]);
    header('Location: ?page=orcamentos&add_quotes=' . $budget_id);
    exit;
}

// Handlers Lista de Desejos PRO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_wishlist') {
        $isEdit = !empty($_POST['request_id']);
        $request_id = $isEdit ? $_POST['request_id'] : 'WR' . time();
        
        $pdo->beginTransaction();
        try {
            $compId = getCurrentUserCompanyId();
            if ($isEdit) {
                $stmt = $pdo->prepare("UPDATE wishlist_requests SET sector_id = ? WHERE id = ? AND company_id = ? AND status = 'Pendente'");
                $stmt->execute([$_POST['sector_id'], $request_id, $compId]);
                $stmt_del = $pdo->prepare("DELETE wi FROM wishlist_items wi JOIN wishlist_requests wr ON wi.request_id = wr.id WHERE wi.request_id = ? AND wr.company_id = ?");
                $stmt_del->execute([$request_id, $compId]);
            } else {
                // Gerar número do pedido (Ex: #2024001) - filtrado por empresa
                $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM wishlist_requests WHERE DATE(created_at) = CURDATE() AND company_id = ?");
                $stmt_count->execute([$compId]);
                $count = $stmt_count->fetchColumn();
                $request_number = date('Ymd') . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
                $stmt = $pdo->prepare("INSERT INTO wishlist_requests (id, request_number, requester_id, sector_id, company_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$request_id, $request_number, $user['id'], $_POST['sector_id'], $compId]);
            }
            
            $items = $_POST['items']; 
            $stmt_item = $pdo->prepare("INSERT INTO wishlist_items (id, request_id, item_name, item_type, quantity, company_id) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($items as $idx => $item) {
                if (empty($item['name'])) continue;
                $stmt_item->execute(['WI' . time() . $idx, $request_id, $item['name'], $item['type'], $item['qty'], $compId]);
            }
            
            $pdo->commit();
            header('Location: ?page=orcamentos&tab=wishlist&success=' . ($isEdit ? '6' : '5'));
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Erro ao salvar lista: " . $e->getMessage());
        }
        exit;
    }

    if ($_POST['action'] === 'approve_wishlist') {
        $compId = getCurrentUserCompanyId();
        $pdo->prepare("UPDATE wishlist_requests SET status = 'Aprovado', approved_by = ?, approved_at = NOW() WHERE id = ? AND company_id = ?")
            ->execute([$user['id'], $_POST['request_id'], $compId]);
        header('Location: ?page=orcamentos&tab=wishlist&success=2');
        exit;
    }

    if ($_POST['action'] === 'reject_wishlist') {
        $compId = getCurrentUserCompanyId();
        $pdo->prepare("UPDATE wishlist_requests SET status = 'Reprovado', approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ? AND company_id = ?")
            ->execute([$user['id'], $_POST['rejection_reason'], $_POST['request_id'], $compId]);
        header('Location: ?page=orcamentos&tab=wishlist&success=3');
        exit;
    }

    if ($_POST['action'] === 'generate_budgets') {
        $compId = getCurrentUserCompanyId();
        $request_id = $_POST['request_id'];
        
        $stmt_w = $pdo->prepare("SELECT * FROM wishlist_requests WHERE id = ? AND company_id = ?");
        $stmt_w->execute([$request_id, $compId]);
        $request = $stmt_w->fetch();
        
        $stmt_i = $pdo->prepare("SELECT * FROM wishlist_items WHERE request_id = ? AND company_id = ?");
        $stmt_i->execute([$request_id, $compId]);
        $items = $stmt_i->fetchAll();
        
        $stmt_u = $pdo->prepare("SELECT unit_id FROM users WHERE id = ? AND company_id = ?");
        $stmt_u->execute([$request['requester_id'], $compId]);
        $unit_id = $stmt_u->fetchColumn();
        
        $stmt_s = $pdo->prepare("SELECT name FROM sectors WHERE id = ? AND company_id = ?");
        $stmt_s->execute([$request['sector_id'], $compId]);
        $sector_name = $stmt_s->fetchColumn();

        $pdo->beginTransaction();
        try {
            foreach ($items as $item) {
                $budget_id = 'B' . time() . rand(10,99);
                $stmt = $pdo->prepare("INSERT INTO budget_requests (id, product_name, description, quantity, sector, unit_id, requester_id, wishlist_id, company_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pendente', NOW())");
                $stmt->execute([
                    $budget_id, 
                    $item['item_name'], 
                    "Gerado a partir da Lista de Desejos #{$request['request_number']}. Modelo/Tipo: " . $item['item_type'], 
                    $item['quantity'], 
                    $sector_name, 
                    $unit_id, 
                    $request['requester_id'],
                    $request['id'],
                    $compId
                ]);
                
                $pdo->prepare("UPDATE wishlist_items SET converted_to_budget_id = ? WHERE id = ? AND company_id = ?")
                   ->execute([$budget_id, $item['id'], $compId]);
            }
            $pdo->prepare("UPDATE wishlist_requests SET status = 'Convertido' WHERE id = ? AND company_id = ?")->execute([$request_id, $compId]);
            $pdo->commit();
            header('Location: ?page=orcamentos&success=1');
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Erro ao gerar orçamentos: " . $e->getMessage());
        }
        exit;
    }

    if ($_POST['action'] === 'delete_wishlist') {
        $compId = getCurrentUserCompanyId();
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM wishlist_items WHERE request_id = ? AND company_id = ?")->execute([$_POST['request_id'], $compId]);
        $pdo->prepare("DELETE FROM wishlist_requests WHERE id = ? AND company_id = ?")->execute([$_POST['request_id'], $compId]);
        $pdo->commit();
        header('Location: ?page=orcamentos&tab=wishlist&success=7');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_budget') {
    $compId = getCurrentUserCompanyId();
    $pdo->prepare("UPDATE budget_requests SET product_name = ?, description = ?, quantity = ?, sector = ?, unit_id = ?, edited_by = ?, edited_at = NOW() WHERE id = ? AND company_id = ? AND status = 'Pendente'")
        ->execute([$_POST['product_name'], $_POST['description'], $_POST['quantity'], $_POST['sector'], $_POST['unit_id'], $user['id'], $_POST['budget_id'], $compId]);
    header('Location: ?page=orcamentos&success=4');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_quotes') {
    $compId = getCurrentUserCompanyId();
    $budget_id = $_POST['budget_id'];
    $stmt_b = $pdo->prepare("SELECT quantity FROM budget_requests WHERE id = ? AND company_id = ?");
    $stmt_b->execute([$budget_id, $compId]);
    $budget = $stmt_b->fetch();
    $quantity = $budget['quantity'] ?? 0;
    
    for ($i = 1; $i <= 3; $i++) {
        $product_price = floatval($_POST["product_price_$i"]);
        $delivery_price = floatval($_POST["delivery_price_$i"]);
        $total_price = ($product_price * $quantity) + $delivery_price;
        
        $image_name = null;
        if (isset($_FILES["product_image_$i"]) && $_FILES["product_image_$i"]['error'] == 0) {
            $image_name = $budget_id . "_quote$i_" . time() . "." . pathinfo($_FILES["product_image_$i"]['name'], PATHINFO_EXTENSION);
            move_uploaded_file($_FILES["product_image_$i"]['tmp_name'], "uploads/" . $image_name);
        }
        
        $stmt = $pdo->prepare("INSERT INTO budget_quotes (id, budget_id, supplier_name, price, shipping_cost, total, link, attachment_url, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['Q' . time() . $i, $budget_id, 'Fornecedor ' . $i, $product_price, $delivery_price, $total_price, $_POST["product_link_$i"], $image_name, $compId]);
    }
    
    $stmt_best = $pdo->prepare("SELECT id FROM budget_quotes WHERE budget_id = ? AND company_id = ? ORDER BY total ASC LIMIT 1");
    $stmt_best->execute([$budget_id, $compId]);
    $best = $stmt_best->fetch();
    if ($best) {
        $pdo->prepare("UPDATE budget_requests SET best_quote_id = ? WHERE id = ? AND company_id = ?")->execute([$best['id'], $budget_id, $compId]);
    }
    
    header('Location: ?page=orcamentos&success=1');
    exit;
}

$compId = getCurrentUserCompanyId();
// Filtro baseado no perfil do usuário
$query = "SELECT br.*, u.name as unit_name, 
          us.name as requester_name, us.avatar_url as requester_avatar,
          ap.name as approver_name, ap.avatar_url as approver_avatar,
          ed.name as editor_name, ed.avatar_url as editor_avatar,
          (SELECT COUNT(*) FROM budget_quotes bq WHERE bq.budget_id = br.id AND bq.company_id = br.company_id) as quotes_count,
          wa.name as wishlist_approver_name, wa.avatar_url as wishlist_approver_avatar, wr.approved_at as wishlist_approved_at, wr.request_number as wishlist_number
          FROM budget_requests br 
          LEFT JOIN units u ON BINARY br.unit_id = BINARY u.id 
          LEFT JOIN users us ON BINARY br.requester_id = BINARY us.id 
          LEFT JOIN users ap ON BINARY br.approved_by = BINARY ap.id 
          LEFT JOIN users ed ON BINARY br.edited_by = BINARY ed.id
          LEFT JOIN wishlist_requests wr ON BINARY br.wishlist_id = BINARY wr.id
          LEFT JOIN users wa ON BINARY wr.approved_by = BINARY wa.id
          WHERE br.company_id = ?";
$params = [$compId];

// 100% Liberado conforme definido pela gestão de menus
// Para todos os cargos, se tiverem acesso ao menu, veem tudo.
// Para outros cargos (Admin, Compras, Suporte, Responsável de Setor, etc), se tiverem acesso ao menu, veem tudo.

$query .= " ORDER BY br.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$budgets = $stmt->fetchAll();
$stmt_u = $pdo->prepare("SELECT * FROM units WHERE company_id = ? ORDER BY name");
$stmt_u->execute([$compId]);
$units = $stmt_u->fetchAll();

$stmt_us = $pdo->prepare("SELECT id, name, sector, unit_id FROM users WHERE company_id = ? ORDER BY name");
$stmt_us->execute([$compId]);
$users = $stmt_us->fetchAll();

$stmt_s = $pdo->prepare("SELECT * FROM sectors WHERE company_id = ? ORDER BY name");
$stmt_s->execute([$compId]);
$sectors = $stmt_s->fetchAll();

// Buscar wishlist PRO
$stmt_w = $pdo->prepare("SELECT w.*, s.name as sector_name, u.name as requester_name, u.avatar_url as requester_avatar,
                        ap.name as approver_name, ap.avatar_url as approver_avatar,
                        (SELECT COUNT(*) FROM wishlist_items wi WHERE wi.request_id = w.id AND wi.company_id = w.company_id) as item_count
                        FROM wishlist_requests w
                        JOIN sectors s ON BINARY w.sector_id = BINARY s.id
                        JOIN users u ON BINARY w.requester_id = BINARY u.id
                        LEFT JOIN users ap ON BINARY w.approved_by = BINARY ap.id
                        WHERE w.company_id = ?
                        ORDER BY w.created_at DESC");
$stmt_w->execute([$compId]);
$wishlist = $stmt_w->fetchAll();

// Buscar todos os itens para o detalhamento
$stmt_wi = $pdo->prepare("SELECT * FROM wishlist_items WHERE company_id = ?");
$stmt_wi->execute([$compId]);
$wishlist_items_raw = $stmt_wi->fetchAll();
$wishlist_items = [];
foreach ($wishlist_items_raw as $wi) {
    $wishlist_items[$wi['request_id']][] = $wi;
}

$activeTab = $_GET['tab'] ?? 'orcamentos';
?>

<style>
    .orc-container { padding: 1rem; }
    .orc-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; }
    
    .tab-nav { display: flex; gap: 1rem; margin-bottom: 2rem; padding: 0.5rem; background: rgba(255,255,255,0.5); backdrop-filter: blur(10px); border-radius: 1rem; border: 1px solid rgba(255,255,255,0.2); width: fit-content; }
    .tab-btn { background: none; border: none; font-weight: 700; color: var(--text-soft); cursor: pointer; padding: 0.75rem 1.5rem; border-radius: 0.75rem; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; align-items: center; gap: 0.5rem; }
    .tab-btn i { font-size: 1.1rem; }
    .tab-btn:hover { background: rgba(91, 33, 182, 0.05); color: var(--crm-purple); }
    .tab-btn.active { color: white; background: var(--crm-purple); box-shadow: 0 4px 12px rgba(91, 33, 182, 0.3); }
    
    .tab-content { display: none; }
    .tab-content.active { display: block; animation: slideUp 0.4s ease-out; }
    
    .budget-card { 
        background: white; 
        border-radius: 1rem; 
        padding: 1.5rem; 
        border: 1px solid #e2e8f0; 
        transition: all 0.3s; 
        display: grid; 
        grid-template-columns: 80px 2fr 1fr 1fr 120px 180px; 
        align-items: center; 
        gap: 1.5rem;
        margin-bottom: 1rem;
    }
    .budget-card:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(0,0,0,0.05); border-color: var(--crm-purple); }
    
    .status-pill { padding: 0.4rem 0.8rem; border-radius: 2rem; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }
    .status-pendente { background: #fef3c7; color: #92400e; }
    .status-aprovado { background: #d1fae5; color: #065f46; }
    .status-comprado { background: #e0f2fe; color: #075985; }
    .status-rejeitado { background: #fee2e2; color: #991b1b; }

    @keyframes slideUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
</style>

<div class="orc-container">
    <div class="page-header">
        <div class="page-header-info">
            <div class="page-header-icon">
                <i class="fa-solid fa-file-invoice-dollar"></i>
            </div>
            <div class="page-header-text">
                <h2>Processos de Compra</h2>
                <p>Controle de cotações, orçamentos e aprovações financeiras.</p>
            </div>
        </div>
        <div class="page-header-actions" style="display: flex; gap: 1rem;">
            <button class="btn-secondary" style="border-radius: 0.75rem; padding: 0.8rem 1.5rem;" onclick="document.getElementById('wishlistModal').style.display='flex'">
                <i class="fa-solid fa-note-sticky"></i>
                Lista de Desejos
            </button>
            <button class="btn-primary" style="border-radius: 0.75rem; padding: 0.8rem 1.5rem; background: var(--crm-purple);" onclick="document.getElementById('budgetModal').style.display='flex'">
                <i class="fa-solid fa-plus"></i>
                Novo Orçamento
            </button>
        </div>
    </div>

    <!-- Sistema de Abas Modernizado -->
    <div class="tab-nav">
        <button onclick="switchTab('orcamentos')" id="tab-orcamentos" class="tab-btn <?= $activeTab == 'orcamentos' ? 'active' : '' ?>">
            <i class="fa-solid fa-list-check"></i> Orçamentos Ativos
        </button>
        <button onclick="switchTab('wishlist')" id="tab-wishlist" class="tab-btn <?= $activeTab == 'wishlist' ? 'active' : '' ?>">
            <i class="fa-solid fa-heart"></i> Lista de Desejos
        </button>
    </div>

<?php if (isset($_GET['success'])): ?>
<div style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(16, 185, 129, 0.05) 100%); border: 1px solid rgba(16, 185, 129, 0.3); color: #059669; padding: 1rem; border-radius: 1rem; margin-bottom: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem;">
    <i class="fa-solid fa-circle-check"></i>
    <?php
        if ($_GET['success'] == '1') echo 'Operação realizada com sucesso!';
        elseif ($_GET['success'] == '2') echo 'Orçamento aprovado!';
        elseif ($_GET['success'] == '3') echo 'Orçamento rejeitado!';
        elseif ($_GET['success'] == '4') echo 'Orçamento editado com sucesso!';
        elseif ($_GET['success'] == '5') echo 'Nova Lista de Desejos criada com sucesso!';
        elseif ($_GET['success'] == '6') echo 'Protocolo da Lista de Desejos atualizado!';
        elseif ($_GET['success'] == '7') echo 'Protocolo removido com sucesso!';
    ?>
</div>
<?php endif; ?>

<div class="glass-panel">
<div id="content-orcamentos" class="tab-content <?= $activeTab == 'orcamentos' ? 'active' : '' ?>">
    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        <?php if (count($budgets) > 0): ?>
            <?php foreach ($budgets as $b): ?>
            <div class="budget-card">
                <!-- ID & Date -->
                <div>
                    <div style="font-family: monospace; font-size: 0.7rem; color: #94a3b8; margin-bottom: 0.25rem;">#<?= htmlspecialchars($b['id']) ?></div>
                    <div style="font-size: 0.75rem; font-weight: 600; color: var(--text-soft);"><?= date('d/m/y', strtotime($b['created_at'])) ?></div>
                </div>

                <!-- Product -->
                <div>
                    <div style="font-size: 1.05rem; font-weight: 800; color: var(--text-main);"><?= htmlspecialchars($b['product_name']) ?></div>
                    <div style="font-size: 0.8rem; color: var(--text-soft); margin-top: 2px;">Qtd: <strong><?= $b['quantity'] ?></strong> | <?= htmlspecialchars($b['unit_name']) ?></div>
                </div>

                <!-- Requester -->
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 35px; height: 35px; border-radius: 50%; overflow: hidden; background: #e2e8f0; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                        <?php if ($b['requester_avatar']): ?>
                            <img src="<?= htmlspecialchars($b['requester_avatar']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-weight:900; color:#64748b; font-size:0.8rem;"><?= strtoupper(substr($b['requester_name'], 0, 1)) ?></div>
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 0.85rem; font-weight: 600; color: #475569;"><?= htmlspecialchars($b['requester_name']) ?></div>
                </div>

                <!-- Status -->
                <div>
                    <span class="status-pill status-<?= strtolower($b['status'] == 'Rejeitado' ? 'rejeitado' : $b['status']) ?>">
                        <?= $b['status'] == 'Rejeitado' ? 'REPROVADO' : $b['status'] ?>
                    </span>
                    <div style="font-size: 0.65rem; color: #94a3b8; margin-top: 0.4rem; padding-left: 0.25rem;">
                        <?php if ($b['status'] == 'Aprovado' || $b['status'] == 'Comprado'): ?>
                            <?= $b['status'] == 'Comprado' ? 'Comp.' : 'Apr.' ?> por <?= explode(' ', $b['approver_name'])[0] ?>
                        <?php elseif ($b['status'] == 'Rejeitado'): ?>
                            Repr. por <?= explode(' ', $b['approver_name'])[0] ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quotes Info -->
                <div style="text-align: center;">
                    <div style="font-size: 1.15rem; font-weight: 900; color: var(--crm-purple);"><?= $b['quotes_count'] ?></div>
                    <div style="font-size: 0.65rem; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Cotações</div>
                </div>

                <!-- Actions -->
                <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                    <?php if ($b['status'] == 'Pendente'): ?>
                    <button onclick="editBudget(<?= htmlspecialchars(json_encode($b)) ?>)" class="btn-icon" title="Editar" style="background: var(--brand-soft); color: var(--brand-primary); width: 38px; height: 38px; font-size: 0.9rem; border-radius: 50%;">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <?php endif; ?>

                    <?php if ($b['quotes_count'] > 0): ?>
                    <a href="?page=orcamentos&view=<?= $b['id'] ?>" class="btn-icon" title="Ver Cotações" style="background: rgba(139, 92, 246, 0.1); color: #8B5CF6; width: 38px; height: 38px; font-size: 0.9rem; border-radius: 50%;">
                        <i class="fa-solid fa-eye"></i>
                    </a>
                    <a href="pdf_orcamento.php?id=<?= $b['id'] ?>" class="btn-icon" title="PDF" target="_blank" style="background: rgba(16, 185, 129, 0.1); color: #10B981; width: 38px; height: 38px; font-size: 0.9rem; border-radius: 50%;">
                        <i class="fa-solid fa-file-pdf"></i>
                    </a>
                    <?php else: ?>
                    <a href="?page=orcamentos&add_quotes=<?= $b['id'] ?>" class="btn-icon" title="Adicionar Cotações" style="background: rgba(245, 158, 11, 0.1); color: #F59E0B; width: 38px; height: 38px; font-size: 0.9rem; border-radius: 50%;">
                        <i class="fa-solid fa-plus"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="padding: 4rem; text-align: center; background: white; border-radius: 1rem; border: 2px dashed #e2e8f0; color: #94a3b8;">
                <i class="fa-solid fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                <p style="font-size: 1.1rem; font-weight: 600;">Nenhum orçamento cadastrado para exibição</p>
                <button class="btn-primary" style="margin-top: 1.5rem;" onclick="document.getElementById('budgetModal').style.display='flex'">Criar Primeiro Orçamento</button>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ABA LISTA DE DESEJOS PRO -->
<div id="content-wishlist" class="tab-content <?= $activeTab == 'wishlist' ? 'active' : '' ?>">
    <style>
        .wish-card {
            background: #fff;
            border-radius: 12px;
            padding: 1.25rem;
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            transition: all 0.3s;
            position: relative;
        }
        .wish-card:hover { border-color: var(--crm-purple); shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .wish-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; }
    </style>
    
    <div class="wish-grid">
        <?php if (count($wishlist) > 0): ?>
            <?php foreach ($wishlist as $w): ?>
            <div class="wish-card">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div>
                        <div style="font-family: monospace; font-weight: 800; color: var(--crm-purple); font-size: 0.9rem;">#<?= htmlspecialchars($w['request_number']) ?></div>
                        <div style="font-size: 0.7rem; color: #94a3b8;"><?= date('d/m/Y H:i', strtotime($w['created_at'])) ?></div>
                    </div>
                    <span class="status-pill status-<?= 
                        $w['status'] == 'Pendente' ? 'pendente' : 
                        ($w['status'] == 'Aprovado' ? 'aprovado' : 
                        ($w['status'] == 'Convertido' ? 'comprado' : 'rejeitado'))
                    ?>">
                        <?= $w['status'] ?>
                    </span>
                </div>

                <div style="padding: 0.75rem; background: var(--bg-main); border-radius: 8px; border: 1px dashed var(--border-color);">
                    <div style="font-size: 0.75rem; color: var(--text-soft); margin-bottom: 0.25rem;">Setor Solicitante:</div>
                    <div style="font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($w['sector_name']) ?></div>
                </div>

                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 32px; height: 32px; border-radius: 50%; overflow: hidden; background: #e2e8f0;">
                        <?php if ($w['requester_avatar']): ?>
                            <img src="<?= htmlspecialchars($w['requester_avatar']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php endif; ?>
                    </div>
                    <div style="font-size: 0.85rem; color: #475569;">Por <strong><?= htmlspecialchars($w['requester_name']) ?></strong></div>
                </div>

                <div style="border-top: 1px solid #f1f5f9; padding-top: 1rem; margin-top: auto; display: flex; justify-content: space-between; align-items: center;">
                    <div style="font-size: 0.85rem; font-weight: 700; color: var(--crm-purple);">
                        <?= $w['item_count'] ?> itens inclusos
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <button onclick='viewWishlistDetails(<?= json_encode($w) ?>, <?= json_encode($wishlist_items[$w['id']] ?? []) ?>)' class="btn-icon" style="background: rgba(139, 92, 246, 0.1); color: #8B5CF6;" title="Ver Detalhes">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                        
                        <?php if ($w['status'] === 'Aprovado'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="generate_budgets">
                            <input type="hidden" name="request_id" value="<?= $w['id'] ?>">
                            <button type="submit" class="btn-icon" style="background: rgba(16, 185, 129, 0.1); color: #10B981;" title="Gerar Orçamentos" onclick="return confirm('Converter itens aprovados em orçamentos?')">
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                            </button>
                        </form>
                        <?php endif; ?>

                        <?php if ($w['status'] === 'Pendente'): ?>
                            <button onclick='openWishlistEdit(<?= json_encode($w) ?>, <?= json_encode($wishlist_items[$w['id']] ?? []) ?>)' class="btn-icon" style="background: rgba(59, 130, 246, 0.1); color: #3B82F6;" title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                        <?php endif; ?>

                        <form method="POST" style="display: inline;" onsubmit="return confirm('Excluir este protocolo?')">
                            <input type="hidden" name="action" value="delete_wishlist">
                            <input type="hidden" name="request_id" value="<?= $w['id'] ?>">
                            <button type="submit" class="btn-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;" title="Excluir">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="grid-column: 1 / -1; padding: 4rem; text-align: center; background: white; border-radius: 1rem; border: 2px dashed #e2e8f0; color: #94a3b8;">
                <i class="fa-solid fa-heart-crack" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                <p style="font-size: 1.1rem; font-weight: 600;">Nenhuma lista de desejos pendente</p>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>

<div id="budgetModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(12px); z-index: 1000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 700px; width: 100%; border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 1rem;">
            <h3 style="font-size: 1.4rem; font-weight: 900; color: var(--text-main);"><i class="fa-solid fa-plus-circle" style="color: var(--crm-purple);"></i> Novo Orçamento</h3>
            <button onclick="document.getElementById('budgetModal').style.display='none'" style="background: #f1f5f9; border: none; cursor: pointer; font-size: 1.25rem; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--text-soft);">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_budget">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                <div class="form-group">
                    <label class="form-label">Solicitante *</label>
                    <select name="requester_id" class="form-select" required onchange="updateBudgetSector(this)" style="border-radius: 0.75rem;">
                        <option value="">Selecione o usuário</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" data-sector="<?= htmlspecialchars($u['sector']) ?>" data-unit="<?= $u['unit_id'] ?>">
                                <?= htmlspecialchars($u['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Setor</label>
                    <input type="text" name="sector" id="budget_sector" class="form-input" readonly style="border-radius: 0.75rem; background: #f8fafc;">
                </div>
                <div class="form-group">
                    <label class="form-label">Unidade *</label>
                    <select name="unit_id" id="budget_unit" class="form-select" required style="border-radius: 0.75rem;">
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Nome do Produto *</label>
                    <input type="text" name="product_name" class="form-input" required style="border-radius: 0.75rem;" placeholder="Ex: Cadeira de Escritório">
                </div>
                <div class="form-group">
                    <label class="form-label">Quantidade *</label>
                    <input type="number" name="quantity" class="form-input" min="1" required style="border-radius: 0.75rem;">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Descrição</label>
                    <textarea name="description" class="form-textarea" style="border-radius: 0.75rem;" placeholder="Detalhes adicionais, marca, modelo..."></textarea>
                </div>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <button type="button" onclick="document.getElementById('budgetModal').style.display='none'" class="btn-secondary" style="border-radius: 0.75rem;">Cancelar</button>
                <button type="submit" class="btn-primary" style="background: var(--crm-purple); border-radius: 0.75rem;"><i class="fa-solid fa-floppy-disk"></i> Criar Orçamento</button>
            </div>
        </form>
    </div>
</div>

<div id="statusInfoModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); z-index: 3000; align-items: center; justify-content: center;">
    <div class="glass-panel" style="max-width: 500px; width: 100%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.25rem; font-weight: 900;">Detalhes do Orçamento</h3>
            <button onclick="document.getElementById('statusInfoModal').style.display='none'" style="background: none; border: none; cursor: pointer; font-size: 1.5rem;">&times;</button>
        </div>
        <div id="statusInfoContent"></div>
    </div>
</div>

<?php if (isset($_GET['add_quotes'])): 
    $compId = getCurrentUserCompanyId();
    $stmt_b = $pdo->prepare("SELECT * FROM budget_requests WHERE id = ? AND company_id = ?");
    $stmt_b->execute([$_GET['add_quotes'], $compId]);
    $budget = $stmt_b->fetch();
    if (!$budget) { echo "<script>window.location.href='?page=orcamentos';</script>"; exit; }
?>
<div style="position: fixed; inset: 0; background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(12px); z-index: 2000; display: flex; align-items: center; justify-content: center; padding: 1rem; overflow-y: auto;">
    <div class="glass-panel" style="max-width: 1200px; width: 100%; margin: 2rem auto; max-height: 90vh; overflow-y: auto; border: 1px solid rgba(255,255,255,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 1rem;">
            <div>
                <h3 style="font-size: 1.4rem; font-weight: 900; color: var(--text-main);"><i class="fa-solid fa-file-invoice-dollar" style="color: var(--crm-purple);"></i> Adicionar 3 Cotações</h3>
                <p style="color: var(--text-soft); font-size: 0.9rem; font-weight: 500;">Item: <strong style="color: var(--crm-purple);"><?= htmlspecialchars($budget['product_name']) ?></strong> | Quantidade: <strong><?= $budget['quantity'] ?></strong></p>
            </div>
            <button onclick="window.location.href='?page=orcamentos'" style="background: #f1f5f9; border: none; cursor: pointer; font-size: 1.25rem; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--text-soft);">&times;</button>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_quotes">
            <input type="hidden" name="budget_id" value="<?= $budget['id'] ?>">
            
            <div style="background: rgba(91, 33, 182, 0.05); padding: 1.25rem; border-radius: 1rem; margin-bottom: 2rem; border: 1px solid rgba(91, 33, 182, 0.1); display: flex; align-items: center; gap: 1rem;">
                <i class="fa-solid fa-circle-info" style="color: var(--crm-purple); font-size: 1.25rem;"></i>
                <div style="font-size: 0.9rem; color: #475569; font-weight: 600;">Defina os valores unitários e o frete para cada fornecedor. O sistema calculará o melhor custo automaticamente.</div>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem;">
                <?php for ($i = 1; $i <= 3; $i++): ?>
                <div style="background: #fdfdfd; padding: 1.75rem; border-radius: 1.25rem; border: 2px solid #e2e8f0; transition: all 0.3s;" onmouseover="this.style.borderColor='var(--crm-purple)'" onmouseout="this.style.borderColor='#e2e8f0'">
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem;">
                        <span style="width: 30px; height: 30px; background: var(--crm-purple); color: white; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: 900; font-size: 0.85rem;"><?= $i ?></span>
                        <h4 style="font-weight: 900; color: var(--text-main);">Fornecedor <?= $i ?></h4>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" style="font-size: 0.8rem;">Valor Unitário (R$) *</label>
                        <input type="number" name="product_price_<?= $i ?>" id="price_<?= $i ?>" class="form-input" step="0.01" required onchange="calcTotal(<?= $i ?>)" style="border-radius: 0.75rem;">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" style="font-size: 0.8rem;">Custo de Frete (R$) *</label>
                        <input type="number" name="delivery_price_<?= $i ?>" id="delivery_<?= $i ?>" class="form-input" step="0.01" required onchange="calcTotal(<?= $i ?>)" style="border-radius: 0.75rem;">
                    </div>
                    
                    <div style="background: var(--bg-main); border: 1px solid var(--border-color); margin-bottom: 1.5rem;">
                        <label class="form-label" style="margin-bottom: 0.25rem; font-size: 0.75rem; color: #94a3b8;">Total do Fornecedor</label>
                        <input type="text" id="total_<?= $i ?>" class="form-input" readonly style="border: none; background: transparent; padding: 0; font-weight: 900; color: var(--crm-purple); font-size: 1.6rem;" value="R$ 0,00">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" style="font-size: 0.8rem;">Link para Compra *</label>
                        <input type="url" name="product_link_<?= $i ?>" class="form-input" required style="border-radius: 0.75rem;" placeholder="https://...">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 0.8rem;">Anexar Comprovante / Print</label>
                        <input type="file" name="product_image_<?= $i ?>" class="form-input" accept="image/*" style="border-radius: 0.75rem; font-size: 0.8rem;">
                    </div>
                </div>
                <?php endfor; ?>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2.5rem; border-top: 1px solid #e2e8f0; padding-top: 1.5rem;">
                <button type="button" onclick="window.location.href='?page=orcamentos'" class="btn-secondary" style="border-radius: 0.75rem; padding-left: 2rem; padding-right: 2rem;">Cancelar</button>
                <button type="submit" class="btn-primary" style="background: var(--crm-purple); border-radius: 0.75rem; padding-left: 2.5rem; padding-right: 2.5rem;"><i class="fa-solid fa-cloud-arrow-up"></i> Finalizar e Salvar Cotações</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['view'])): 
    $compId = getCurrentUserCompanyId();
    $stmt_v = $pdo->prepare("SELECT br.*, u.name as unit_name, us.name as requester_name, ap.name as approver_name,
                          wa.name as wishlist_approver_name, wa.avatar_url as wishlist_approver_avatar, wr.approved_at as wishlist_approved_at, wr.request_number as wishlist_number
                          FROM budget_requests br 
                          LEFT JOIN units u ON BINARY br.unit_id = BINARY u.id 
                          LEFT JOIN users us ON BINARY br.requester_id = BINARY us.id 
                          LEFT JOIN users ap ON BINARY br.approved_by = BINARY ap.id 
                          LEFT JOIN wishlist_requests wr ON BINARY br.wishlist_id = BINARY wr.id
                          LEFT JOIN users wa ON BINARY wr.approved_by = BINARY wa.id
                          WHERE br.id = ? AND br.company_id = ?");
    $stmt_v->execute([$_GET['view'], $compId]);
    $budget = $stmt_v->fetch();
    if (!$budget) { echo "<script>window.location.href='?page=orcamentos';</script>"; exit; }
    
    $stmt_q = $pdo->prepare("SELECT * FROM budget_quotes WHERE budget_id = ? AND company_id = ? ORDER BY total ASC");
    $stmt_q->execute([$_GET['view'], $compId]);
    $quotes = $stmt_q->fetchAll();
?>
<div style="position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); z-index: 2000; display: flex; align-items: center; justify-content: center; padding: 1rem; overflow-y: auto;">
    <div class="glass-panel" style="max-width: 1200px; width: 100%; margin: 2rem auto; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h3 style="font-size: 1.25rem; font-weight: 900;">Cotações - <?= htmlspecialchars($budget['product_name']) ?></h3>
                <p style="color: var(--text-soft); font-size: 0.875rem;">Solicitante: <?= htmlspecialchars($budget['requester_name']) ?> | Qtd: <?= $budget['quantity'] ?></p>
            </div>
            <div style="text-align: right; display: flex; align-items: center; gap: 1rem;">
                <span class="badge badge-<?= $budget['status'] == 'Pendente' ? 'warning' : ($budget['status'] == 'Aprovado' ? 'success' : 'danger') ?>">
                    <?= strtoupper($budget['status']) ?>
                </span>
                <button onclick="window.location.href='?page=orcamentos'" style="background: none; border: none; cursor: pointer; font-size: 1.5rem; color: var(--text-soft);">&times;</button>
            </div>
        </div>

        <?php if ($budget['status'] != 'Pendente'): ?>
        <!-- Bloco de Aprovação do Orçamento (Final) -->
        <div style="margin-bottom: 2rem; padding: 1.5rem; border-radius: 1rem; border: 2px solid <?= $budget['status'] == 'Aprovado' ? '#10B981' : '#EF4444' ?>; background: <?= $budget['status'] == 'Aprovado' ? 'rgba(16, 185, 129, 0.05)' : 'rgba(239, 68, 68, 0.05)' ?>;">
            <h4 style="font-weight: 900; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fa-solid <?= $budget['status'] == 'Aprovado' ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                Detalhes da <?= $budget['status'] == 'Aprovado' ? 'Aprovação Final' : 'Reprovação Final' ?>
            </h4>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                <div>
                    <label style="font-size: 0.75rem; color: var(--text-soft); display: block;">Responsável:</label>
                    <span style="font-weight: 900;"><?= htmlspecialchars($budget['approver_name']) ?></span>
                </div>
                <div>
                    <label style="font-size: 0.75rem; color: var(--text-soft); display: block;">Data e Horário:</label>
                    <span style="font-weight: 700;">
                        <?= $budget['status'] == 'Aprovado' ? date('d/m/Y H:i', strtotime($budget['approved_at'])) : date('d/m/Y H:i', strtotime($budget['rejected_at'])) ?>
                    </span>
                </div>
                <?php if ($budget['status'] == 'Rejeitado'): ?>
                <div>
                    <label style="font-size: 0.75rem; color: var(--text-soft); display: block;">Motivo:</label>
                    <span style="font-weight: 900; color: #EF4444;"><?= htmlspecialchars($budget['rejection_reason']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($budget['wishlist_id']): ?>
        <!-- Bloco de Auditoria da Lista de Desejos -->
        <div style="margin-bottom: 2rem; padding: 1.5rem; border-radius: 1rem; border: 2px solid var(--crm-purple); background: rgba(91, 33, 182, 0.03);">
            <h4 style="font-weight: 900; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; color: var(--crm-purple);">
                <i class="fa-solid fa-note-sticky"></i>
                Autorização Original (Lista de Desejos #<?= $budget['wishlist_number'] ?>)
            </h4>
            <div style="display: flex; align-items: center; gap: 1.5rem;">
                <img src="<?= $budget['wishlist_approver_avatar'] ?: 'assets/images/default-avatar.png' ?>" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div>
                    <p style="margin: 0; font-size: 0.875rem; color: var(--text-soft);">Desejo aprovado por:</p>
                    <p style="margin: 0; font-weight: 900; color: var(--crm-black);"><?= htmlspecialchars($budget['wishlist_approver_name']) ?></p>
                    <p style="margin: 0; font-size: 0.75rem; color: var(--text-soft);"><?= date('d/m/Y H:i', strtotime($budget['wishlist_approved_at'])) ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
            <?php foreach ($quotes as $idx => $q): ?>
            <div style="background: <?= $idx == 0 ? 'linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05))' : 'linear-gradient(135deg, rgba(91, 33, 182, 0.05), rgba(251, 191, 36, 0.02))' ?>; padding: 1.5rem; border-radius: 1rem; border: 2px solid <?= $idx == 0 ? '#10B981' : 'var(--crm-purple)' ?>; position: relative;">
                <?php if ($idx == 0): ?>
                <div style="position: absolute; top: -10px; right: 10px; background: #10B981; color: white; padding: 0.25rem 0.75rem; border-radius: 0.5rem; font-size: 0.75rem; font-weight: 900;">MELHOR PREÇO</div>
                <?php endif; ?>
                
                <h4 style="font-weight: 900; color: var(--crm-purple); margin-bottom: 1rem; text-align: center;">Cotação <?= $idx + 1 ?></h4>
                
                <?php if ($q['attachment_url']): ?>
                <img src="uploads/<?= htmlspecialchars($q['attachment_url']) ?>" style="width: 100%; height: 150px; object-fit: cover; border-radius: 0.5rem; margin-bottom: 1rem;">
                <?php endif; ?>
                
                <div style="margin-bottom: 0.5rem;">
                    <span style="font-size: 0.75rem; color: var(--text-soft);">Valor Produto:</span>
                    <span style="font-weight: 700; float: right;">R$ <?= number_format($q['price'], 2, ',', '.') ?></span>
                </div>
                <div style="margin-bottom: 0.5rem;">
                    <span style="font-size: 0.75rem; color: var(--text-soft);">Frete:</span>
                    <span style="font-weight: 700; float: right;">R$ <?= number_format($q['shipping_cost'], 2, ',', '.') ?></span>
                </div>
                <div style="border-top: 2px solid var(--crm-purple); padding-top: 0.5rem; margin-top: 0.5rem;">
                    <span style="font-weight: 900; color: var(--crm-black);">TOTAL:</span>
                    <span style="font-weight: 900; color: var(--crm-purple); float: right; font-size: 1.25rem;">R$ <?= number_format($q['total'], 2, ',', '.') ?></span>
                </div>
                
                <?php if ($q['link']): ?>
                <a href="<?= htmlspecialchars($q['link']) ?>" target="_blank" class="btn-secondary" style="width: 100%; margin-top: 1rem; text-align: center; display: block;">Ver Produto</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($budget['status'] == 'Pendente' && ($user['role'] == 'Administrador' || $user['role'] == 'Responsável de Setor')): ?>
        <div style="display: flex; gap: 1rem; justify-content: center; padding: 1.5rem; background: var(--crm-gray-light); border-radius: 1rem;">
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="approve_budget">
                <input type="hidden" name="budget_id" value="<?= $budget['id'] ?>">
                <button type="submit" class="btn-primary" onclick="return confirm('Deseja aprovar este orçamento?')">
                    <i class="fa-solid fa-check"></i> Aprovar Orçamento
                </button>
            </form>
            
            <button class="btn-secondary" onclick="document.getElementById('rejectModal').style.display='flex'" style="background: #EF4444; color: white;">
                <i class="fa-solid fa-xmark"></i> Rejeitar Orçamento
            </button>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="rejectModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.9); z-index: 3000; align-items: center; justify-content: center;">
    <div class="glass-panel" style="max-width: 500px; width: 100%;">
        <h3 style="font-size: 1.25rem; font-weight: 900; margin-bottom: 1.5rem;">Motivo da Rejeição</h3>
        <form method="POST">
            <input type="hidden" name="action" value="reject_budget">
            <input type="hidden" name="budget_id" value="<?= $budget['id'] ?>">
            <div class="form-group">
                <select name="rejection_reason" class="form-select" required>
                    <option value="">Selecione o motivo</option>
                    <option value="Valor incompatível">Valor incompatível</option>
                    <option value="Projeto não cobre">Projeto não cobre</option>
                    <option value="Erro no produto">Erro no produto</option>
                </select>
            </div>
            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="button" onclick="document.getElementById('rejectModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary" style="background: #EF4444;">Confirmar Rejeição</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Modal Editar Orçamento -->
<div id="editBudgetModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 700px; width: 100%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 style="font-size: 1.25rem; font-weight: 900;"><i class="fa-solid fa-pen"></i> Editar Orçamento</h3>
            <button onclick="document.getElementById('editBudgetModal').style.display='none'" style="background: none; border: none; cursor: pointer; font-size: 1.5rem;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_budget">
            <input type="hidden" name="budget_id" id="edit_budget_id">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Nome do Produto *</label>
                    <input type="text" name="product_name" id="edit_product_name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Quantidade *</label>
                    <input type="number" name="quantity" id="edit_quantity" class="form-input" min="1" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Setor</label>
                    <input type="text" name="sector" id="edit_budget_sector" class="form-input">
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Unidade *</label>
                    <select name="unit_id" id="edit_budget_unit" class="form-select" required>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label">Descrição</label>
                    <textarea name="description" id="edit_description" class="form-textarea"></textarea>
                </div>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                <button type="button" onclick="document.getElementById('editBudgetModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Novo Protocolo de Desejos -->
<div id="wishlistModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 800px; width: 100%; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 id="wishlistModalTitle" style="font-size: 1.25rem; font-weight: 900;"><i class="fa-solid fa-note-sticky"></i> Novo Protocolo de Desejos</h3>
            <button onclick="document.getElementById('wishlistModal').style.display='none'" style="background: none; border: none; cursor: pointer; font-size: 1.5rem;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" id="wishlistAction" value="add_wishlist">
            <input type="hidden" name="request_id" id="editRequestId">
            
            <div class="form-group">
                <label class="form-label">Setor Solicitante *</label>
                <select name="sector_id" id="wishSectorId" class="form-select" required>
                    <option value="">Selecione o setor...</option>
                    <?php foreach ($sectors as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="wishlistItemsContainer">
                <div style="display: grid; grid-template-columns: 2fr 2fr 1fr 40px; gap: 1rem; margin-bottom: 0.5rem; align-items: center;">
                    <label class="form-label" style="margin: 0;">Item</label>
                    <label class="form-label" style="margin: 0;">Tipo/Modelo</label>
                    <label class="form-label" style="margin: 0;">Quantidade</label>
                    <div></div>
                </div>
                <div class="wishlist-item-row" style="display: grid; grid-template-columns: 2fr 2fr 1fr 40px; gap: 1rem; margin-bottom: 1rem;">
                    <input type="text" name="items[0][name]" class="form-input" placeholder="Ex: Tonner impressora" required>
                    <input type="text" name="items[0][type]" class="form-input" placeholder="Ex: Preto CF 450">
                    <input type="number" name="items[0][qty]" class="form-input" value="1" min="1" required>
                    <button type="button" class="btn-icon" style="background: #e2e8f0; color: var(--text-soft); cursor: not-allowed;" disabled><i class="fa-solid fa-trash"></i></button>
                </div>
            </div>

            <button type="button" onclick="addWishlistItemRow()" class="btn-secondary" style="width: 100%; border: 2px dashed #cbd5e1; background: transparent; margin-top: 0.5rem;">
                <i class="fa-solid fa-plus"></i> Adicionar Outro Item
            </button>

            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem; border-top: 1px solid #e2e8f0; padding-top: 1.5rem;">
                <button type="button" onclick="document.getElementById('wishlistModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary">Criar Lista de Desejos</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Detalhes do Protocolo -->
<div id="wishlistDetailsModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 900px; width: 100%; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <div>
                <h3 id="detTitle" style="font-size: 1.5rem; font-weight: 900;">#000000</h3>
                <p id="detSector" style="color: var(--text-soft);"></p>
            </div>
            <button onclick="document.getElementById('wishlistDetailsModal').style.display='none'" style="background: none; border: none; cursor: pointer; font-size: 1.5rem;">&times;</button>
        </div>

        <div id="detApproverInfo" style="margin-bottom: 2rem; display: none;">
             <div style="display: flex; align-items: center; gap: 1.5rem; padding: 1.5rem; border-radius: 1rem; border: 2px solid #e2e8f0;" id="detAlertBox">
                <img id="detApproverPhoto" src="" style="width: 60px; height: 60px; border-radius: 50%; object-fit: cover; border: 3px solid white; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <div>
                    <h4 id="detStatusText" style="font-weight: 900; font-size: 1.1rem; margin: 0;"></h4>
                    <p style="margin: 0; color: var(--text-soft); font-size: 0.9rem;">Processado por <strong id="detApproverName"></strong> em <span id="detApproveTime"></span></p>
                    <p id="detRejectionReason" style="margin-top: 0.5rem; color: #ef4444; font-weight: 700; display: none;"></p>
                </div>
             </div>
        </div>

        <div style="margin-bottom: 2rem;">
            <h4 style="font-weight: 900; margin-bottom: 1rem;">Itens Solicitados</h4>
            <div class="glass-panel" style="padding: 0; border: 1px solid #e2e8f0;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead style="background: #f8fafc;">
                        <tr>
                            <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #e2e8f0;">Item</th>
                            <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #e2e8f0;">Tipo/Modelo</th>
                            <th style="padding: 1rem; text-align: center; border-bottom: 1px solid #e2e8f0;">Quantidade</th>
                            <th style="padding: 1rem; text-align: center; border-bottom: 1px solid #e2e8f0;">Status</th>
                        </tr>
                    </thead>
                    <tbody id="detItemsTable"></tbody>
                </table>
            </div>
        </div>

        <div id="detActions" style="display: flex; gap: 1rem; justify-content: space-between; border-top: 1px solid #e2e8f0; padding-top: 1.5rem;">
            <div>
                <?php if ($user['role'] === 'Administrador' || $user['role'] === 'Compras' || $user['role'] === 'Responsável de Setor'): ?>
                <div id="gestaoButtons" style="display: flex; gap: 1rem;">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="approve_wishlist">
                        <input type="hidden" name="request_id" id="detReqIdA">
                        <button type="submit" class="btn-primary" style="background: #10B981;" onclick="return confirm('Aprovar este protocolo?')"><i class="fa-solid fa-check"></i> Aprovar</button>
                    </form>
                    <button class="btn-secondary" style="background: #ef4444; color: white;" onclick="showRejectWishlist()"><i class="fa-solid fa-xmark"></i> Reprovar</button>
                </div>
                <?php endif; ?>

                <form method="POST" id="convertForm" style="display: none;">
                    <input type="hidden" name="action" value="generate_budgets">
                    <input type="hidden" name="request_id" id="detReqIdC">
                    <button type="submit" class="btn-primary" style="background: var(--crm-purple);" onclick="return confirm('Deseja converter todos os itens aprovados em orçamentos?')"><i class="fa-solid fa-wand-magic-sparkles"></i> Gerar Orçamentos Ativos</button>
                </form>
            </div>
            <button type="button" onclick="document.getElementById('wishlistDetailsModal').style.display='none'" class="btn-secondary">Fechar</button>
        </div>

        <div id="rejectWishlistForm" style="display: none; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px dashed #ef4444;">
             <h4 style="font-weight: 900; color: #ef4444; margin-bottom: 1rem;">Motivo da Reprovação</h4>
             <form method="POST">
                 <input type="hidden" name="action" value="reject_wishlist">
                 <input type="hidden" name="request_id" id="detReqIdR">
                 <textarea name="rejection_reason" class="form-textarea" placeholder="Explique o motivo..." required></textarea>
                 <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                     <button type="submit" class="btn-primary" style="background: #ef4444;">Confirmar Reprovação</button>
                     <button type="button" onclick="hideRejectWishlist()" class="btn-secondary">Cancelar</button>
                 </div>
             </form>
        </div>
    </div>
</div>

<script>
    const budgetsData = <?= json_encode($budgets) ?>;
    
    let wishItemCount = 1;
    function addWishlistItemRow(name = '', type = '', qty = 1) {
        const container = document.getElementById('wishlistItemsContainer');
        const row = document.createElement('div');
        row.className = 'wishlist-item-row';
        row.style = 'display: grid; grid-template-columns: 2fr 2fr 1fr 40px; gap: 1rem; margin-bottom: 1rem;';
        row.innerHTML = `
            <input type="text" name="items[${wishItemCount}][name]" class="form-input" placeholder="Ex: Item" required value="${name}">
            <input type="text" name="items[${wishItemCount}][type]" class="form-input" placeholder="Modelo" value="${type}">
            <input type="number" name="items[${wishItemCount}][qty]" class="form-input" value="${qty}" min="1" required>
            <button type="button" class="btn-icon" style="background: #fee2e2; color: #ef4444;" onclick="this.parentElement.remove()"><i class="fa-solid fa-trash"></i></button>
        `;
        container.appendChild(row);
        wishItemCount++;
    }

    function openWishlistEdit(w, items) {
        document.getElementById('wishlistModalTitle').innerText = 'Editar Protocolo #' + w.request_number;
        document.getElementById('wishlistAction').value = 'add_wishlist'; // Reuso o add_wishlist que limpa e reinsere
        document.getElementById('editRequestId').value = w.id;
        document.getElementById('wishSectorId').value = w.sector_id;
        
        const container = document.getElementById('wishlistItemsContainer');
        container.innerHTML = `
            <div style="display: grid; grid-template-columns: 2fr 2fr 1fr 40px; gap: 1rem; margin-bottom: 0.5rem; align-items: center;">
                <label class="form-label" style="margin: 0;">Item</label>
                <label class="form-label" style="margin: 0;">Tipo/Modelo</label>
                <label class="form-label" style="margin: 0;">Quantidade</label>
                <div></div>
            </div>
        `;
        
        wishItemCount = 0;
        items.forEach(item => {
            addWishlistItemRow(item.item_name, item.item_type, item.quantity);
        });
        
        document.getElementById('wishlistModal').style.display = 'flex';
    }

    function viewWishlistDetails(w, items) {
        document.getElementById('detTitle').innerText = 'Protocolo #' + w.request_number;
        document.getElementById('detSector').innerText = 'Setor: ' + w.sector_name;
        document.getElementById('detReqIdA').value = w.id;
        document.getElementById('detReqIdR').value = w.id;
        document.getElementById('detReqIdC').value = w.id;

        const table = document.getElementById('detItemsTable');
        table.innerHTML = '';
        items.forEach(item => {
            table.innerHTML += `
                <tr>
                    <td style="padding: 1rem; border-bottom: 1px solid #f1f5f9;"><strong>${item.item_name}</strong></td>
                    <td style="padding: 1rem; border-bottom: 1px solid #f1f5f9;">${item.item_type || '-'}</td>
                    <td style="padding: 1rem; border-bottom: 1px solid #f1f5f9; text-align: center;">${item.quantity}</td>
                    <td style="padding: 1rem; border-bottom: 1px solid #f1f5f9; text-align: center;">
                        ${item.converted_to_budget_id ? '<span class="badge badge-info">Convertido</span>' : '<span class="badge badge-warning">Pendente</span>'}
                    </td>
                </tr>
            `;
        });

        // Approver Info
        const info = document.getElementById('detApproverInfo');
        const alertBox = document.getElementById('detAlertBox');
        if (w.status !== 'Pendente') {
            info.style.display = 'block';
            document.getElementById('detApproverName').innerText = w.approver_name;
            document.getElementById('detApproverPhoto').src = w.approver_avatar || 'assets/images/default-avatar.png';
            document.getElementById('detApproveTime').innerText = new Date(w.approved_at).toLocaleString('pt-BR');
            
            if (w.status === 'Aprovado' || w.status === 'Convertido') {
                document.getElementById('detStatusText').innerText = 'SOLICITAÇÃO APROVADA';
                document.getElementById('detStatusText').style.color = '#10B981';
                alertBox.style.borderColor = '#10B981';
                alertBox.style.background = 'rgba(16, 185, 129, 0.05)';
                document.getElementById('detRejectionReason').style.display = 'none';
                document.getElementById('convertForm').style.display = w.status === 'Aprovado' ? 'block' : 'none';
                if(document.getElementById('gestaoButtons')) document.getElementById('gestaoButtons').style.display = 'none';
            } else {
                document.getElementById('detStatusText').innerText = 'SOLICITAÇÃO REPROVADA';
                document.getElementById('detStatusText').style.color = '#ef4444';
                alertBox.style.borderColor = '#ef4444';
                alertBox.style.background = 'rgba(239, 68, 68, 0.05)';
                document.getElementById('detRejectionReason').innerText = 'Motivo: ' + w.rejection_reason;
                document.getElementById('detRejectionReason').style.display = 'block';
                document.getElementById('convertForm').style.display = 'none';
                if(document.getElementById('gestaoButtons')) document.getElementById('gestaoButtons').style.display = 'none';
            }
        } else {
            info.style.display = 'none';
            document.getElementById('convertForm').style.display = 'none';
            if(document.getElementById('gestaoButtons')) document.getElementById('gestaoButtons').style.display = 'flex';
        }

        document.getElementById('wishlistDetailsModal').style.display = 'flex';
        hideRejectWishlist();
    }

    function showRejectWishlist() {
        document.getElementById('rejectWishlistForm').style.display = 'block';
        document.getElementById('detActions').style.display = 'none';
    }

    function hideRejectWishlist() {
        document.getElementById('rejectWishlistForm').style.display = 'none';
        document.getElementById('detActions').style.display = 'flex';
    }

    function switchTab(tab) {
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('content-' + tab).classList.add('active');
        document.getElementById('tab-' + tab).classList.add('active');
    }

    function openWishlistModal(w = null) {
        if (w) {
            document.getElementById('wishlistTitle').innerText = 'Gerenciar Item';
            document.getElementById('wishlistAction').value = 'edit_wishlist';
            document.getElementById('edit_wishlist_id').value = w.id;
            document.getElementById('wishItem').value = w.item_name;
            document.getElementById('wishType').value = w.item_type;
            document.getElementById('wishQty').value = w.quantity;
            document.getElementById('wishSector').value = w.sector_id;
            if (document.getElementById('wishMsg')) {
                document.getElementById('wishMsg').value = w.admin_message || '';
                document.getElementById('adminMsgGroup').style.display = 'block';
            }
        } else {
            document.getElementById('wishlistTitle').innerText = 'Novo Item na Lista';
            document.getElementById('wishlistAction').value = 'add_wishlist';
            document.getElementById('edit_wishlist_id').value = '';
            document.getElementById('wishItem').value = '';
            document.getElementById('wishType').value = '';
            document.getElementById('wishQty').value = '1';
            if (document.getElementById('adminMsgGroup')) {
                document.getElementById('adminMsgGroup').style.display = 'none';
            }
        }
        document.getElementById('wishlistModal').style.display = 'flex';
    }

    function editBudget(budget) {
        document.getElementById('edit_budget_id').value = budget.id;
        document.getElementById('edit_product_name').value = budget.product_name;
        document.getElementById('edit_quantity').value = budget.quantity;
        document.getElementById('edit_budget_sector').value = budget.sector;
        document.getElementById('edit_budget_unit').value = budget.unit_id;
        document.getElementById('edit_description').value = budget.description;
        document.getElementById('editBudgetModal').style.display = 'flex';
    }

    function showStatusInfo(budgetId) {
        const budget = budgetsData.find(b => b.id === budgetId);
        if (!budget) return;
        
        let content = '';
        if (budget.status === 'Aprovado') {
            content = `
                <div style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.05)); padding: 1.5rem; border-radius: 1rem; border: 2px solid #10B981;">
                    <div style="text-align: center; margin-bottom: 1rem;">
                        <i class="fa-solid fa-circle-check" style="width: 48px; height: 48px; color: #10B981;"></i>
                    </div>
                    <h4 style="font-weight: 900; color: #10B981; text-align: center; margin-bottom: 1rem;">ORÇAMENTO APROVADO</h4>
                    <div style="background: white; padding: 1rem; border-radius: 0.5rem;">
                        <div style="margin-bottom: 0.75rem;">
                            <span style="font-size: 0.75rem; color: var(--text-soft); display: block;">Aprovado por:</span>
                            <span style="font-weight: 900; color: var(--crm-black);">${budget.approver_name || 'N/A'}</span>
                        </div>
                        <div>
                            <span style="font-size: 0.75rem; color: var(--text-soft); display: block;">Data e Hora:</span>
                            <span style="font-weight: 900; color: var(--crm-black);">${budget.approved_at ? new Date(budget.approved_at).toLocaleString('pt-BR') : 'N/A'}</span>
                        </div>
                    </div>
                </div>
            `;
        } else if (budget.status === 'Rejeitado') {
            content = `
                <div style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(239, 68, 68, 0.05)); padding: 1.5rem; border-radius: 1rem; border: 2px solid #EF4444;">
                    <div style="text-align: center; margin-bottom: 1rem;">
                        <i class="fa-solid fa-xmark" style="width: 48px; height: 48px; color: #EF4444;"></i>
                    </div>
                    <h4 style="font-weight: 900; color: #EF4444; text-align: center; margin-bottom: 1rem;">ORÇAMENTO REJEITADO</h4>
                    <div style="background: white; padding: 1rem; border-radius: 0.5rem;">
                        <div style="margin-bottom: 0.75rem;">
                            <span style="font-size: 0.75rem; color: var(--text-soft); display: block;">Rejeitado por:</span>
                            <span style="font-weight: 900; color: var(--crm-black);">${budget.approver_name || 'N/A'}</span>
                        </div>
                        <div style="margin-bottom: 0.75rem;">
                            <span style="font-size: 0.75rem; color: var(--text-soft); display: block;">Data e Hora:</span>
                            <span style="font-weight: 900; color: var(--crm-black);">${budget.rejected_at ? new Date(budget.rejected_at).toLocaleString('pt-BR') : 'N/A'}</span>
                        </div>
                        <div>
                            <span style="font-size: 0.75rem; color: var(--text-soft); display: block;">Motivo:</span>
                            <span style="font-weight: 900; color: #EF4444;">${budget.rejection_reason || 'N/A'}</span>
                        </div>
                    </div>
                </div>
            `;
        }
        
        document.getElementById('statusInfoContent').innerHTML = content;
        document.getElementById('statusInfoModal').style.display = 'flex';
        
    }
    
    function calcTotal(num) {
        const price = parseFloat(document.getElementById('price_' + num).value) || 0;
        const delivery = parseFloat(document.getElementById('delivery_' + num).value) || 0;
        const qty = parseFloat(document.getElementById('globalQty').value) || 1;
        const total = (price * qty) + delivery;
        document.getElementById('total_' + num).value = 'R$ ' + total.toFixed(2).replace('.', ',');
    }
    
    function updateBudgetSector(select) {
        const option = select.options[select.selectedIndex];
        const sector = option.getAttribute('data-sector');
        const unit = option.getAttribute('data-unit');
        document.getElementById('budget_sector').value = sector || '';
        document.getElementById('budget_unit').value = unit || '';
    }
    
</script>

