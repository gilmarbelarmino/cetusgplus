<?php
require_once 'access_control.php';

// ─── Garantir tabela de histórico existe ──────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS volunteer_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    volunteer_id VARCHAR(50) NOT NULL,
    company_id INT NOT NULL DEFAULT 1,
    start_date DATE,
    end_date DATE,
    total_hours DECIMAL(10,2) DEFAULT 0,
    points INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    edited_by VARCHAR(50) NULL,
    edited_at TIMESTAMP NULL
)");
try { $pdo->exec("ALTER TABLE volunteer_history ADD COLUMN company_id INT NOT NULL DEFAULT 1"); } catch(Exception $e) {}

// ─── Garantir colunas na tabela volunteers ──────────────────────────────
try { $pdo->exec("ALTER TABLE volunteers ADD COLUMN company_id INT NOT NULL DEFAULT 1"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE volunteers ADD COLUMN points INT DEFAULT 0"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE volunteers ADD COLUMN end_date DATE NULL"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE volunteer_history ADD COLUMN points INT DEFAULT 0"); } catch(Exception $e) {}

// ─── AÇÕES POST ───────────────────────────────────────────────────────────────

// Cadastrar novo voluntário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_volunteer') {
    $months = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
    $total = 0;
    foreach ($months as $m) { $total += floatval($_POST["hours_$m"] ?? 0); }
    
    $vid = 'V'.time();
    $avatar_url = null;
    if (!empty($_FILES['avatar']['name'])) {
        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_vol_' . $vid . '.' . $ext;
        $dest = __DIR__ . '/../uploads/' . $filename;
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
            $avatar_url = 'uploads/' . $filename;
        }
    }

    $compId = getCurrentUserCompanyId();
    $stmt = $pdo->prepare("INSERT INTO volunteers (id, name, cpf, avatar_url, gender, email, phone, unit_id, sector_id, volunteering_sector, action_type, location, profession, hourly_rate, start_date, work_area, hours_jan, hours_feb, hours_mar, hours_apr, hours_may, hours_jun, hours_jul, hours_aug, hours_sep, hours_oct, hours_nov, hours_dec, total_hours, points, status, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Ativo', ?)");
    $stmt->execute([$vid, $_POST['name'], $_POST['cpf'], $avatar_url, $_POST['gender'] ?? 'Outro', $_POST['email'], $_POST['phone'], $_POST['unit_id'], $_POST['sector_id'], $_POST['volunteering_sector'], $_POST['action_type'] ?? '', implode(', ', $_POST['location'] ?? []), $_POST['profession'], $_POST['hourly_rate'], $_POST['start_date'], $_POST['work_area'],
        floatval($_POST['hours_jan']??0), floatval($_POST['hours_feb']??0), floatval($_POST['hours_mar']??0), floatval($_POST['hours_apr']??0),
        floatval($_POST['hours_may']??0), floatval($_POST['hours_jun']??0), floatval($_POST['hours_jul']??0), floatval($_POST['hours_aug']??0),
        floatval($_POST['hours_sep']??0), floatval($_POST['hours_oct']??0), floatval($_POST['hours_nov']??0), floatval($_POST['hours_dec']??0),
        $total, floor($total), $compId
    ]);
    header('Location: ?page=voluntariado&success=1'); exit;
}

// Inativar voluntário (salva histórico)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'inativar') {
    $compId = getCurrentUserCompanyId();
    $vid = $_POST['volunteer_id'];
    $vol = $pdo->prepare("SELECT * FROM volunteers WHERE id = ? AND company_id = ?");
    $vol->execute([$vid, $compId]);
    $vol = $vol->fetch();
    if ($vol) {
        $pdo->prepare("INSERT INTO volunteer_history (volunteer_id, start_date, end_date, total_hours, points, edited_by, edited_at, company_id) VALUES (?, ?, CURDATE(), ?, ?, ?, NOW(), ?)")
            ->execute([$vid, $vol['start_date'], $vol['total_hours'], $vol['points'], $user['id'], $compId]);
        $pdo->prepare("UPDATE volunteers SET status = 'Inativo', end_date = CURDATE() WHERE id = ? AND company_id = ?")
            ->execute([$vid, $compId]);
    }
    header('Location: ?page=voluntariado&cert='.$vid.'&inativado=1'); exit;
}

// Reativar voluntário (zera horas, mantém histórico)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reativar') {
    $compId = getCurrentUserCompanyId();
    $vid = $_POST['volunteer_id'];
    // Reativar com bônus de 20 pontos
    $pdo->prepare("UPDATE volunteers SET status = 'Ativo', end_date = NULL, start_date = CURDATE(), total_hours = 0, points = 20,
        hours_jan=0,hours_feb=0,hours_mar=0,hours_apr=0,hours_may=0,hours_jun=0,
        hours_jul=0,hours_aug=0,hours_sep=0,hours_oct=0,hours_nov=0,hours_dec=0,
        last_edited_by = ?, last_edited_at = NOW() WHERE id = ? AND company_id = ?")
        ->execute([$user['id'], $vid, $compId]);
    
    // Registrar reativação com início de ciclo (sem horas, mas com pontos de bônus registrados se necessário)
    $pdo->prepare("INSERT INTO volunteer_history (volunteer_id, start_date, total_hours, points, edited_by, edited_at, company_id) VALUES (?, CURDATE(), 0, 20, ?, NOW(), ?)")
        ->execute([$vid, $user['id'], $compId]);
        
    header('Location: ?page=voluntariado&reativado=1'); exit;
}

// Excluir voluntário permanentemente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'excluir') {
    if ($user['role'] === 'Administrador') {
        $compId = getCurrentUserCompanyId();
        $vid = $_POST['volunteer_id'];
        $pdo->prepare("DELETE FROM volunteer_history WHERE volunteer_id = ? AND company_id = ?")->execute([$vid, $compId]);
        $pdo->prepare("DELETE FROM volunteers WHERE id = ? AND company_id = ?")->execute([$vid, $compId]);
    }
    header('Location: ?page=voluntariado&excluido=1'); exit;
}

// Editar voluntário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'editar_volunteer') {
    $months = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
    $total = 0;
    foreach ($months as $m) { $total += floatval($_POST["hours_$m"] ?? 0); }
    
    $avatar_url = $_POST['current_avatar'] ?? null;
    if (!empty($_FILES['avatar']['name'])) {
        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_vol_' . $_POST['volunteer_id'] . '.' . $ext;
        $dest = __DIR__ . '/../uploads/' . $filename;
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dest)) {
            $avatar_url = 'uploads/' . $filename;
        }
    }

    $compId = getCurrentUserCompanyId();
    $pdo->prepare("UPDATE volunteers SET name=?, cpf=?, avatar_url=?, gender=?, email=?, phone=?, volunteering_sector=?, work_area=?, location=?, profession=?, hourly_rate=?,
        hours_jan=?,hours_feb=?,hours_mar=?,hours_apr=?,hours_may=?,hours_jun=?,hours_jul=?,hours_aug=?,hours_sep=?,hours_oct=?,hours_nov=?,hours_dec=?,
        total_hours=?, points=?, last_edited_by=?, last_edited_at=NOW() WHERE id=? AND company_id=?")
        ->execute([$_POST['name'], $_POST['cpf'], $avatar_url, $_POST['gender'] ?? 'Outro', $_POST['email'], $_POST['phone'], $_POST['volunteering_sector'], $_POST['work_area'], implode(', ', $_POST['location'] ?? []), $_POST['profession'], floatval($_POST['hourly_rate']),
        floatval($_POST['hours_jan']??0), floatval($_POST['hours_feb']??0), floatval($_POST['hours_mar']??0), floatval($_POST['hours_apr']??0),
        floatval($_POST['hours_may']??0), floatval($_POST['hours_jun']??0), floatval($_POST['hours_jul']??0), floatval($_POST['hours_aug']??0),
        floatval($_POST['hours_sep']??0), floatval($_POST['hours_oct']??0), floatval($_POST['hours_nov']??0), floatval($_POST['hours_dec']??0),
        $total, floor($total), $user['id'], $_POST['volunteer_id'], $compId
    ]);
    header('Location: ?page=voluntariado&editado=1'); exit;
}

// ─── CARREGAR DADOS ───────────────────────────────────────────────────────────
$compId = getCurrentUserCompanyId();
$query = "SELECT v.*, u.name as unit_name FROM volunteers v LEFT JOIN units u ON BINARY v.unit_id = BINARY u.id WHERE v.company_id = ?";
$params = [$compId];
$query .= " ORDER BY v.created_at DESC";
$stmt = $pdo->prepare($query); $stmt->execute($params);
$volunteers = $stmt->fetchAll();

$units_stmt = $pdo->prepare("SELECT * FROM units WHERE company_id = ?");
$units_stmt->execute([$compId]);
$units = $units_stmt->fetchAll();

$users_stmt = $pdo->prepare("SELECT u.id, u.name, u.email, u.phone, u.sector, u.unit_id, un.name as unit_name FROM users u LEFT JOIN units un ON BINARY u.unit_id = BINARY un.id WHERE u.company_id = ? ORDER BY u.name");
$users_stmt->execute([$compId]);
$users = $users_stmt->fetchAll();

$sectors_stmt = $pdo->prepare("SELECT s.id, s.name, s.unit_id FROM sectors s WHERE s.company_id = ? ORDER BY s.name");
$sectors_stmt->execute([$compId]);
$sectors = $sectors_stmt->fetchAll();

// Voluntário para edição
$editVol = null;
if (isset($_GET['edit'])) {
    $s = $pdo->prepare("SELECT * FROM volunteers WHERE id = ? AND company_id = ?");
    $s->execute([$_GET['edit'], $compId]);
    $editVol = $s->fetch();
}

// Voluntário para certificado
$certVol = null;
if (isset($_GET['cert'])) {
    $s = $pdo->prepare("SELECT v.*, u.name as unit_name FROM volunteers v LEFT JOIN units u ON BINARY v.unit_id = BINARY u.id WHERE v.id = ? AND v.company_id = ?");
    $s->execute([$_GET['cert'], $compId]);
    $certVol = $s->fetch();
}
$stmt_comp = $pdo->prepare("SELECT * FROM company_settings WHERE id = ?");
$stmt_comp->execute([$compId]);
$company = $stmt_comp->fetch();

$monthFields = ['jan'=>'Janeiro','feb'=>'Fevereiro','mar'=>'Março','apr'=>'Abril','may'=>'Maio','jun'=>'Junho','jul'=>'Julho','aug'=>'Agosto','sep'=>'Setembro','oct'=>'Outubro','nov'=>'Novembro','dec'=>'Dezembro'];
?>

<!-- ─── CABEÇALHO ──────────────────────────────────────────── -->
<style>
.vol-actions { display: flex; align-items: center; justify-content: center; gap: 8px; flex-wrap: nowrap; }
.vol-btn { 
    display: inline-flex; 
    align-items: center; 
    justify-content: center; 
    width: 38px; 
    height: 38px; 
    border-radius: 12px; 
    border: 2px solid transparent; 
    cursor: pointer; 
    font-size: 14px; 
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); 
    text-decoration: none; 
    position: relative;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.vol-btn:hover { 
    transform: translateY(-2px) scale(1.05); 
    box-shadow: 0 6px 15px rgba(0,0,0,0.1); 
}
.vol-btn:active { transform: translateY(0) scale(0.95); }

/* Cores vibrantes e profissionais */
.vol-btn-edit   { background: #EFF6FF; color: #1D4ED8; border-color: #DBEAFE; }
.vol-btn-edit:hover { background: #1D4ED8; color: #fff; }

.vol-btn-dl     { background: #ECFDF5; color: #059669; border-color: #D1FAE5; }
.vol-btn-dl:hover { background: #059669; color: #fff; }

.vol-btn-hist   { background: #F8FAFC; color: #475569; border-color: #F1F5F9; }
.vol-btn-hist:hover { background: #475569; color: #fff; }

.vol-btn-inativ { background: #FFF7ED; color: #EA580C; border-color: #FFEDD5; }
.vol-btn-inativ:hover { background: #EA580C; color: #fff; }

.vol-btn-react  { background: #F5F3FF; color: #7C3AED; border-color: #EDE9FE; }
.vol-btn-react:hover { background: #7C3AED; color: #fff; }

.vol-btn-del    { background: #FEF2F2; color: #DC2626; border-color: #FEE2E2; }
.vol-btn-del:hover { background: #DC2626; color: #fff; }
</style>

<div class="page-header">
    <div class="page-header-info">
        <div class="page-header-icon">
            <i class="fa-solid fa-hand-holding-heart"></i>
        </div>
        <div class="page-header-text">
            <h2>Programa de Voluntariado</h2>
            <p>Gestão de impacto social, horas dedicadas e certificações.</p>
        </div>
    </div>
    <div class="page-header-actions">
        <button class="btn-primary" onclick="document.getElementById('volunteerModal').style.display='flex'">
            <i class="fa-solid fa-plus"></i> Novo Voluntário
        </button>
    </div>
</div>

<!-- ─── SISTEMA DE ABAS ────────────────────────────────────────── -->
<div style="display:flex; gap:1rem; border-bottom: 2px solid #e2e8f0; margin-bottom: 2rem; padding-bottom: 0.5rem;">
    <button onclick="switchVolTab('gestao')" id="tab-gestao" class="tab-btn active">Gestão de Voluntários</button>
    <button onclick="switchVolTab('ranking')" id="tab-ranking" class="tab-btn">Ranking de Engajamento</button>
</div>

<style>
    .tab-btn { background: none; border: none; font-weight: 700; color: var(--text-soft); cursor: pointer; padding: 0.5rem 1rem; border-radius: 0.5rem; transition: all 0.3s; }
    .tab-btn.active { color: var(--crm-purple); background: rgba(91, 33, 182, 0.1); }
    .vol-content { display: none; }
    .vol-content.active { display: block; animation: fadeIn 0.3s ease-out; }
</style>

<div id="content-gestao" class="vol-content active">

<!-- ─── ALERTAS ───────────────────────────────────────────────── -->
<?php foreach ([
    'success'  => ['check','Voluntário cadastrado com sucesso!','#059669','rgba(16,185,129,0.15)','rgba(16,185,129,0.3)'],
    'editado'  => ['pen','Voluntário atualizado com sucesso!','#2563eb','rgba(59,130,246,0.15)','rgba(59,130,246,0.3)'],
    'reativado'=> ['rotate-left','Voluntário reativado! As horas foram zeradas e o histórico preservado.','#7c3aed','rgba(139,92,246,0.15)','rgba(139,92,246,0.3)'],
    'excluido' => ['trash','Voluntário excluído permanentemente.','#dc2626','rgba(239,68,68,0.12)','rgba(239,68,68,0.3)'],
] as $key => $d): if (isset($_GET[$key])): ?>
<div style="background:<?=$d[3]?>;border:1px solid <?=$d[4]?>;color:<?=$d[2]?>;padding:1rem;border-radius:1rem;margin-bottom:1.5rem;font-weight:700;display:flex;align-items:center;gap:.75rem;">
    <i class="fa-solid fa-<?=$d[0]?>"></i> <?=$d[1]?>
</div>
<?php endif; endforeach; ?>

<!-- ─── TABELA ────────────────────────────────────────────────── -->
<div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th style="width: 80px; text-align: center;">Participante</th>
                <th>Nome do Voluntário</th>
                <th>Área de Atuação</th>
                <th>Tipo de Trabalho</th>
                <th>Total Horas</th>
                <th>Status</th>
                <th style="text-align:center;">Ações</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($volunteers as $v): ?>
            <tr>
                <td style="text-align: center;">
                    <?php if (!empty($v['avatar_url'])): ?>
                        <img src="<?= htmlspecialchars($v['avatar_url']) ?>" alt="Foto" style="width:48px;height:48px;border-radius:12px;object-fit:cover;border:2px solid var(--brand-soft); box-shadow: var(--shadow-sm);">
                    <?php else: ?>
                        <div style="width:48px;height:48px;border-radius:12px;background:var(--brand-soft);display:flex;align-items:center;justify-content:center;color:var(--brand-primary);font-size:1.2rem;font-weight:800;">
                            <?= strtoupper(substr($v['name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td style="font-weight:700; color: var(--text-main); font-size: 0.95rem;">
                    <?= htmlspecialchars($v['name']) ?>
                    <div style="font-size: 0.75rem; color: var(--text-soft); font-weight: 400;"><?= htmlspecialchars($v['email']) ?></div>
                </td>
                <td><?= htmlspecialchars($v['work_area']) ?></td>
                <td>
                    <div style="display:flex; gap:0.25rem; flex-wrap:wrap;">
                    <?php 
                    $locs = explode(', ', $v['location'] ?? '');
                    foreach($locs as $loc): 
                        $loc = trim($loc);
                        if(empty($loc)) continue;
                        $lColor = $loc == 'Presencial' ? '#ef4444' : ($loc == 'Remoto' ? '#3b82f6' : '#8b5cf6');
                        $lIcon = $loc == 'Presencial' ? 'fa-house-user' : ($loc == 'Remoto' ? 'fa-laptop-house' : 'fa-circle-nodes');
                    ?>
                        <span style="font-size:0.65rem; font-weight:800; color:<?=$lColor?>; background:<?= $lColor.'0D' ?>; padding:2px 6px; border-radius:4px; border:1px solid <?=$lColor?>33; display: flex; align-items: center; gap: 3px;">
                            <i class="fa-solid <?=$lIcon?>"></i> <?=$loc?>
                        </span>
                    <?php endforeach; ?>
                    </div>
                </td>
                <td style="font-weight:700;color:var(--crm-purple);"><?= $v['total_hours'] ?>h <span style="font-size:0.75rem; color:#94a3b8; font-weight:400;">(<?= $v['points'] ?> pts)</span></td>
                <td>
                    <?php if ($v['status'] == 'Ativo'): ?>
                        <span class="badge badge-success">Ativo</span>
                    <?php else: ?>
                        <span class="badge badge-warning">Inativo</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="vol-actions">

                        <!-- ✏️ Editar — todos -->
                        <a href="?page=voluntariado&edit=<?= $v['id'] ?>" class="vol-btn vol-btn-edit" title="Editar cadastro">
                            <i class="fa-solid fa-user-pen"></i>
                        </a>

                        <!-- ⬇️ Download Certificado — todos -->
                        <a href="?page=voluntariado&cert=<?= $v['id'] ?>" class="vol-btn vol-btn-dl" title="Ver / Imprimir Certificado PDF">
                            <i class="fa-solid fa-file-contract"></i>
                        </a>

                        <!-- 📋 Histórico — todos -->
                        <button type="button" class="vol-btn vol-btn-hist" title="Ver histórico de períodos"
                            onclick="verHistorico('<?= $v['id'] ?>','<?= htmlspecialchars(addslashes($v['name'])) ?>')">
                            <i class="fa-solid fa-list-check"></i>
                        </button>

                        <?php if ($v['status'] == 'Ativo'): ?>
                        <!-- 🟠 Inativar — só ativo -->
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Inativar voluntário \'<?= htmlspecialchars(addslashes($v['name'])) ?>\'?\nSeu certificado será gerado e o histórico registrado.')">
                            <input type="hidden" name="action" value="inativar">
                            <input type="hidden" name="volunteer_id" value="<?= $v['id'] ?>">
                            <button type="submit" class="vol-btn vol-btn-inativ" title="Inativar e gerar certificado">
                                <i class="fa-solid fa-circle-xmark"></i>
                            </button>
                        </form>
                        <?php else: ?>
                        <!-- 🔁 Reativar — só inativo -->
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Reativar \'<?= htmlspecialchars(addslashes($v['name'])) ?>\'?\nAs horas serão zeradas, mas o histórico anterior será preservado.')">
                            <input type="hidden" name="action" value="reativar">
                            <input type="hidden" name="volunteer_id" value="<?= $v['id'] ?>">
                            <button type="submit" class="vol-btn vol-btn-react" title="Reativar voluntário">
                                <i class="fa-solid fa-circle-play"></i>
                            </button>
                        </form>
                        <!-- 🗑️ Excluir — só inativo, só admin -->
                        <?php if ($user['role'] === 'Administrador'): ?>
                        <form method="POST" style="display:inline;"
                            onsubmit="return confirm('⚠️ ATENÇÃO!\nExcluir PERMANENTEMENTE \'<?= htmlspecialchars(addslashes($v['name'])) ?>\'?\nTODO o histórico será apagado e não poderá ser recuperado.') && confirm('Confirmar exclusão permanente?')">
                            <input type="hidden" name="action" value="excluir">
                            <input type="hidden" name="volunteer_id" value="<?= $v['id'] ?>">
                            <button type="submit" class="vol-btn vol-btn-del" title="Excluir permanentemente">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                        <?php endif; ?>

                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($volunteers)): ?>
            <tr><td colspan="7" style="text-align:center;padding:3rem;color:#94a3b8;">
                <i class="fa-solid fa-heart" style="font-size:2rem;opacity:.3;display:block;margin-bottom:.75rem;"></i>
                Nenhum voluntário cadastrado
            </td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</div>

<!-- ─── ABA RANKING ────────────────────────────────────────────── -->
<div id="content-ranking" class="vol-content">
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:1.5rem;">
        <?php 
        $activeVolunteers = array_filter($volunteers, fn($v) => $v['status'] == 'Ativo');
        usort($activeVolunteers, fn($a, $b) => $b['points'] <=> $a['points']);
        $rank = 1;
        foreach ($activeVolunteers as $v): 
            $medalColor = $rank == 1 ? '#FBBF24' : ($rank == 2 ? '#94A3B8' : ($rank == 3 ? '#B45309' : 'transparent'));
        ?>
        <div class="glass-panel" style="display:flex; align-items:center; gap:1.5rem; position:relative; overflow:hidden;">
            <?php if($rank <= 3): ?>
                <div style="position:absolute; top:-10px; right:-10px; background:<?=$medalColor?>; width:40px; height:40px; transform:rotate(45deg); display:flex; align-items:flex-end; justify-content:center; padding-bottom:5px; color:white; font-weight:900; font-size:12px;">
                    <i class="fa-solid fa-crown" style="transform:rotate(-45deg)"></i>
                </div>
            <?php endif; ?>
            
            <div style="position:relative;">
                <div style="width:24px; height:24px; background:var(--crm-purple); color:white; border-radius:50%; display:flex; align-items:center; justify-content:center; position:absolute; top:-8px; left:-8px; font-size:0.75rem; font-weight:900; z-index:2; border:2px solid white;">
                    <?=$rank?>º
                </div>
                <?php if (!empty($v['avatar_url'])): ?>
                    <img src="<?= htmlspecialchars($v['avatar_url']) ?>" style="width:70px;height:70px;border-radius:1.25rem;object-fit:cover; border:3px solid #fff; box-shadow:0 4px 10px rgba(0,0,0,0.1);">
                <?php else: ?>
                    <div style="width:70px;height:70px;border-radius:1.25rem;background:var(--brand-soft);display:flex;align-items:center;justify-content:center;color:var(--brand-primary);font-size:1.8rem;font-weight:900;">
                        <?= strtoupper(substr($v['name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
            </div>

            <div style="flex:1;">
                <h4 style="font-weight:900; color: var(--text-main); margin-bottom:0.25rem;"><?=htmlspecialchars($v['name'])?></h4>
                <div style="display:flex; gap:0.5rem; flex-wrap:wrap; margin-bottom:0.5rem;">
                    <?php 
                    $locations = explode(', ', $v['location']);
                    foreach($locations as $loc): 
                        $lColor = $loc == 'Presencial' ? '#ef4444' : ($loc == 'Remoto' ? '#3b82f6' : '#8b5cf6');
                    ?>
                        <span style="font-size:0.65rem; font-weight:800; color:<?=$lColor?>; background:<?= $lColor.'0D' ?>; padding:2px 6px; border-radius:4px; border:1px solid <?=$lColor?>33;">
                            <i class="fa-solid fa-location-dot" style="font-size:0.6rem;"></i> <?=$loc?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <div style="display:flex; align-items:baseline; gap:0.25rem;">
                    <span style="font-size:1.5rem; font-weight:900; color:var(--crm-purple);"><?=number_format($v['points'], 0)?></span>
                    <span style="font-size:0.75rem; font-weight:700; color:#64748b; text-transform:uppercase;">Pontos</span>
                </div>
            </div>
        </div>
        <?php $rank++; endforeach; ?>
    </div>
</div>

<!-- ─── MODAL: EDITAR VOLUNTÁRIO ─────────────────────────────────────────── -->
<?php if ($editVol): ?>
<div id="editModal" style="display:flex;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(8px);z-index:1000;align-items:center;justify-content:center;padding:2rem;overflow-y:auto;">
    <div class="glass-panel" style="max-width:1000px;width:100%;margin:2rem auto;max-height:90vh;overflow-y:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;">
            <h3 style="font-size:1.25rem;font-weight:900;color:var(--crm-purple);">
                <i class="fa-solid fa-pen"></i> Editar Voluntário
            </h3>
            <a href="?page=voluntariado" style="background:none;border:none;cursor:pointer;font-size:1.5rem;text-decoration:none;color:#64748b;">&times;</a>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="editar_volunteer">
            <input type="hidden" name="volunteer_id" value="<?= $editVol['id'] ?>">
            <input type="hidden" name="current_avatar" value="<?= $editVol['avatar_url'] ?>">
            
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:1.5rem;">
                <!-- Foto do Voluntário -->
                <div class="form-group" style="grid-column: span 2; display:flex; align-items:center; gap:1.5rem; background:var(--crm-gray-light); padding:1.25rem; border-radius:1rem; border:1px solid #e2e8f0;">
                    <div style="width:70px; height:70px; border-radius:50%; overflow:hidden; border:3px solid #fff; box-shadow:0 8px 20px rgba(0,0,0,0.1); background:var(--crm-purple); display:flex; align-items:center; justify-content:center;">
                        <?php if (!empty($editVol['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($editVol['avatar_url']) ?>" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <i class="fa-solid fa-user" style="font-size:1.8rem; color:white;"></i>
                        <?php endif; ?>
                    </div>
                    <div style="flex:1;">
                        <label class="form-label" style="margin-bottom:.4rem; display:block; font-weight:800;">Alterar Foto do Voluntário</label>
                        <input type="file" name="avatar" class="form-input" accept="image/*" style="padding:.4rem; background: var(--bg-main); color: var(--text-main); border: 1px solid var(--border-color);">
                    </div>
                </div>
                <?php foreach ([
                    ['name','Nome Completo','text',$editVol['name']],
                    ['cpf','CPF','text',$editVol['cpf']??''],
                    ['email','E-mail','email',$editVol['email']],
                    ['phone','Telefone','text',$editVol['phone']??''],
                    ['profession','Profissão','text',$editVol['profession']??''],
                    ['hourly_rate','Valor Hora (R$)','number',$editVol['hourly_rate']??''],
                    ['volunteering_sector','Setor do Voluntariado','text',$editVol['volunteering_sector']??''],
                ] as [$n,$l,$t,$val]): ?>
                <div class="form-group">
                    <label class="form-label"><?= $l ?></label>
                    <input type="<?= $t ?>" name="<?= $n ?>" class="form-input" value="<?= htmlspecialchars($val) ?>"
                        <?= in_array($n,['name','email','profession','volunteering_sector']) ? 'required' : '' ?>
                        <?= $t==='number' ? 'step=0.01 min=0' : '' ?>>
                </div>
                <?php endforeach; ?>
                <div class="form-group">
                    <label class="form-label">Sexo</label>
                    <select name="gender" class="form-select">
                        <option value="Masculino" <?= ($editVol['gender']??'')=='Masculino'?'selected':'' ?>>Masculino</option>
                        <option value="Feminino" <?= ($editVol['gender']??'')=='Feminino'?'selected':'' ?>>Feminino</option>
                        <option value="Outro" <?= ($editVol['gender']??'')=='Outro'?'selected':'' ?>>Outro</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Área de Atuação</label>
                    <select name="work_area" class="form-select">
                        <?php foreach (['Administração','Assistência Social','Comunicação','Contabilidade','Design','Educação','Engenharia','Eventos','Jurídico','Marketing','Psicologia','Recursos Humanos','Saúde','Tecnologia da Informação','Outros'] as $opt): ?>
                            <option value="<?=$opt?>" <?= $editVol['work_area']==$opt?'selected':'' ?>><?=$opt?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Tipo de Trabalho</label>
                    <div style="display:flex; gap:1rem; flex-wrap:wrap; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main);">
                        <?php 
                        $currentLocs = explode(', ', $editVol['location']);
                        foreach (['Presencial','Remoto','Híbrido'] as $opt): 
                        ?>
                            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:700; font-size:0.85rem; color:#475569;">
                                <input type="checkbox" name="location[]" value="<?=$opt?>" <?= in_array($opt, $currentLocs)?'checked':'' ?> style="width:18px;height:18px; accent-color:var(--crm-purple);"> <?=$opt?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <h4 style="font-size:1rem;font-weight:900;margin:2rem 0 1rem;border-top:2px solid var(--crm-gray-light);padding-top:1.5rem;">Horas Mensais</h4>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:1rem;">
                <?php foreach ($monthFields as $key => $label): ?>
                <div class="form-group">
                    <label class="form-label"><?=$label?></label>
                    <input type="number" name="hours_<?=$key?>" class="form-input" min="0" step="0.5"
                        value="<?= floatval($editVol["hours_$key"] ?? 0) ?>" onchange="calcEditTotal()">
                </div>
                <?php endforeach; ?>
            </div>
            <div style="background:linear-gradient(135deg,rgba(91,33,182,.1),rgba(251,191,36,.05));padding:1.5rem;border-radius:1rem;margin-top:1rem;border:2px solid var(--crm-purple);display:flex;gap:2rem;align-items:center;">
                <div>
                    <span style="font-weight:900;display:block;margin-bottom:.25rem;">Total de Horas:</span>
                    <span id="editTotalHours" style="font-size:1.5rem;font-weight:900;color:var(--crm-purple);"><?= $editVol['total_hours'] ?>h</span>
                </div>
            </div>
            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:2rem;">
                <a href="?page=voluntariado" class="btn-secondary" style="text-decoration:none;">Cancelar</a>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>
<script>
function calcEditTotal() {
    const m = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
    let t = 0;
    m.forEach(k => { t += parseFloat(document.querySelector(`input[name="hours_${k}"]`)?.value || 0); });
    document.getElementById('editTotalHours').textContent = t.toFixed(1) + 'h';
}
</script>
<?php endif; ?>

<!-- ─── MODAL: NOVO VOLUNTÁRIO ─────────────────────────────────────────────── -->
<div id="volunteerModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);backdrop-filter:blur(8px);z-index:1000;align-items:center;justify-content:center;padding:2rem;overflow-y:auto;">
    <div class="glass-panel" style="max-width:1000px;width:100%;margin:2rem auto;max-height:90vh;overflow-y:auto;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;">
            <h3 style="font-size:1.25rem;font-weight:900;">Cadastrar Voluntário</h3>
            <button onclick="document.getElementById('volunteerModal').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:1.5rem;">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_volunteer">
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:1.5rem;">
                <!-- Foto do Voluntário -->
                <div class="form-group" style="grid-column: span 2; display:flex; align-items:center; gap:1.5rem; background:var(--crm-gray-light); padding:1.25rem; border-radius:1rem; border:1px solid #e2e8f0;">
                    <div style="width:60px; height:60px; border-radius:50%; background:var(--crm-purple); display:flex; align-items:center; justify-content:center; color:white; box-shadow:0 8px 20px rgba(0,0,0,0.1);">
                        <i class="fa-solid fa-camera" style="font-size:1.4rem;"></i>
                    </div>
                    <div style="flex:1;">
                        <label class="form-label" style="margin-bottom:.4rem; display:block; font-weight:800;">Foto do Voluntário (Opcional)</label>
                        <input type="file" name="avatar" class="form-input" accept="image/*" style="padding:.4rem; background: var(--bg-main); color: var(--text-main); border: 1px solid var(--border-color);">
                    </div>
                </div>
                <div class="form-group" style="grid-column: span 2;">
                    <label class="form-label" style="margin-bottom: 0.75rem;">Origem do Voluntário</label>
                    <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                        <button type="button" id="btnFromSystem" onclick="setVolunteerMode('system')" class="btn-secondary" style="flex:1; padding: 0.75rem; font-weight: 700; border: 2px solid var(--crm-purple); color: var(--crm-purple); background: rgba(91,33,182,0.05);">
                            <i class="fa-solid fa-users"></i> Usuário do Sistema
                        </button>
                        <button type="button" id="btnManual" onclick="setVolunteerMode('manual')" class="btn-secondary" style="flex:1; padding: 0.75rem; font-weight: 700;">
                            <i class="fa-solid fa-user-plus"></i> Cadastro Manual
                        </button>
                    </div>
                </div>
                <!-- Seletor de usuário do sistema -->
                <div class="form-group" id="systemUserGroup">
                    <label class="form-label">Selecionar Usuário *</label>
                    <select id="userSelect" class="form-select" onchange="fillUserData()" style="background: var(--bg-main); color: var(--text-main); border: 1px solid var(--border-color);">
                        <option value="" style="background: var(--bg-card); color: var(--text-main);">Selecione um usuário</option>
                        <?php foreach ($users as $u): ?>
                        <option value="<?=$u['id']?>" style="background: var(--bg-card); color: var(--text-main);" data-name="<?=htmlspecialchars($u['name'])?>" data-email="<?=htmlspecialchars($u['email'])?>" data-phone="<?=htmlspecialchars($u['phone']??'')?>" data-sector="<?=htmlspecialchars($u['sector']??'')?>" data-unit="<?=$u['unit_id']?>" data-unit-name="<?=htmlspecialchars($u['unit_name']??'')?>">
                            <?=htmlspecialchars($u['name'])?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Nome Completo *</label>
                    <input type="text" name="name" id="volunteerName" class="form-input" placeholder="Nome do voluntário" required>
                </div>
                <div class="form-group">
                    <label class="form-label">CPF</label>
                    <input type="text" name="cpf" class="form-input" placeholder="000.000.000-00">
                </div>
                <div class="form-group">
                    <label class="form-label">Sexo *</label>
                    <select name="gender" class="form-select" required style="background: var(--bg-main); color: var(--text-main); border: 1px solid var(--border-color);">
                        <option value="Masculino" style="background: var(--bg-card); color: var(--text-main);">Masculino</option>
                        <option value="Feminino" style="background: var(--bg-card); color: var(--text-main);">Feminino</option>
                        <option value="Outro" style="background: var(--bg-card); color: var(--text-main);">Outro</option>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">E-mail *</label><input type="email" name="email" id="volunteerEmail" class="form-input" required></div>
                <div class="form-group"><label class="form-label">Telefone</label><input type="text" name="phone" id="volunteerPhone" class="form-input"></div>
                <div class="form-group">
                    <label class="form-label">Unidade *</label>
                    <!-- Modo sistema: exibe texto readonly -->
                    <input type="text" id="volunteerUnitDisplay" class="form-input" readonly style="display:block;">
                    <!-- Modo manual: exibe select -->
                    <select name="unit_id" id="volunteerUnit" class="form-select" required style="display:none; background: var(--bg-main); color: var(--text-main); border: 1px solid var(--border-color);">
                        <?php foreach ($units as $u): ?><option value="<?=$u['id']?>" style="background: var(--bg-card); color: var(--text-main);"><?=htmlspecialchars($u['name'])?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Setor Responsável *</label>
                    <input type="text" name="sector_id" id="volunteerSector" class="form-input" required>
                </div>
                <div class="form-group"><label class="form-label">Setor do Voluntariado *</label><input type="text" name="volunteering_sector" class="form-input" required></div>
                <div class="form-group">
                    <label class="form-label">Área de Atuação *</label>
                    <select name="work_area" class="form-select" required style="background: var(--bg-main); color: var(--text-main); border: 1px solid var(--border-color);">
                        <?php foreach (['Administração','Assistência Social','Comunicação','Contabilidade','Design','Educação','Engenharia','Eventos','Jurídico','Marketing','Psicologia','Recursos Humanos','Saúde','Tecnologia da Informação','Outros'] as $opt): ?>
                            <option value="<?=$opt?>" style="background: var(--bg-card); color: var(--text-main);"><?=$opt?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Tipo de Trabalho *</label>
                    <div style="display:flex; gap:1rem; flex-wrap:wrap; background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main);">
                        <?php foreach (['Presencial','Remoto','Híbrido'] as $opt): ?>
                            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:700; font-size:0.85rem; color:#475569;">
                                <input type="checkbox" name="location[]" value="<?=$opt?>" style="width:18px;height:18px; accent-color:var(--crm-purple);"> <?=$opt?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-group"><label class="form-label">Profissão *</label><input type="text" name="profession" class="form-input" required></div>
                <div class="form-group"><label class="form-label">Valor Hora (R$) *</label><input type="number" name="hourly_rate" class="form-input" step="0.01" required onchange="calcTotal()"></div>
                <div class="form-group"><label class="form-label">Data de Início *</label><input type="date" name="start_date" class="form-input" value="<?=date('Y-m-d')?>" required></div>
            </div>
            <h4 style="font-size:1rem;font-weight:900;margin:2rem 0 1rem;border-top:2px solid var(--crm-gray-light);padding-top:1.5rem;">Horas Mensais de Voluntariado</h4>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:1rem;">
                <?php foreach ($monthFields as $key => $label): ?>
                <div class="form-group"><label class="form-label"><?=$label?></label><input type="number" name="hours_<?=$key?>" class="form-input" min="0" value="0" onchange="calcTotal()"></div>
                <?php endforeach; ?>
            </div>
            <div style="background:linear-gradient(135deg,rgba(91,33,182,.1),rgba(251,191,36,.05));padding:1.5rem;border-radius:1rem;margin-top:1rem;border:2px solid var(--crm-purple);display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;">
                <div><span style="font-weight:900;display:block;margin-bottom:.5rem;">Total de Horas:</span><span id="totalHours" style="font-size:1.5rem;font-weight:900;color:var(--crm-purple);">0h</span></div>
                <div><span style="font-weight:900;display:block;margin-bottom:.5rem;">Valor Total:</span><span id="totalValue" style="font-size:1.5rem;font-weight:900;color:var(--crm-yellow);">R$ 0,00</span></div>
            </div>
            <div style="display:flex;gap:1rem;justify-content:flex-end;margin-top:2rem;">
                <button type="button" onclick="document.getElementById('volunteerModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-floppy-disk"></i> Cadastrar</button>
            </div>
        </form>
    </div>
</div>

<!-- ─── MODAL: HISTÓRICO ────────────────────────────────────────────────────── -->
<div id="histModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(8px);z-index:2000;align-items:center;justify-content:center;padding:1rem;">
    <div style="background:#fff;max-width:500px;width:100%;border-radius:1rem;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:1.5rem;border-bottom:1px solid #e2e8f0;">
            <div>
                <h4 style="font-weight:900;color:#020617;margin-bottom:.25rem;">Histórico de Voluntariado</h4>
                <p id="histName" style="font-size:.875rem;color:#64748b;"></p>
            </div>
            <button onclick="document.getElementById('histModal').style.display='none'" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#94a3b8;">&times;</button>
        </div>
        <div id="histBody" style="padding:1.5rem;max-height:60vh;overflow-y:auto;"></div>
    </div>
</div>

<!-- ─── CERTIFICADO ─────────────────────────────────────────────────────────── -->
<?php if ($certVol): ?>
<div id="certOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,.85);backdrop-filter:blur(10px);z-index:9000;display:flex;align-items:center;justify-content:center;padding:2rem;overflow-y:auto;">
    <div style="background: var(--bg-main); color: var(--text-main); border: 1px solid var(--border-color);max-width:1150px;width:100%;padding:1rem;border-radius:1.5rem;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);position:relative;margin:auto; display:flex; flex-direction:column; align-items:center;">
        <button onclick="window.location.href='?page=voluntariado'" class="close-btn" style="position:absolute;top:1rem;right:1rem;background:none;border:none;cursor:pointer;font-size:1.5rem;color:#64748b;z-index:9100;">&times;</button>
        <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400..800;1,400..800&family=Outfit:wght@100..900&display=swap" rel="stylesheet">
        
        <script>
            // Garantir que o certificado esteja na raiz do body para impressão perfeita
            document.addEventListener('DOMContentLoaded', function() {
                const cert = document.getElementById('certOverlay');
                if (cert && cert.parentElement !== document.body) {
                    document.body.appendChild(cert);
                }
            });
            // Caso seja carregado via AJAX ou depois do DOMContentLoaded
            setTimeout(() => {
                const cert = document.getElementById('certOverlay');
                if (cert && cert.parentElement !== document.body) {
                    document.body.appendChild(cert);
                }
            }, 500);
        </script>
        
        <div id="certPrintPaper" style="width: 297mm; height: 210mm; display: grid; grid-template-rows: 1.2fr 2fr 1fr 1fr; padding: 20mm; box-sizing: border-box; text-align: center; background: #fff; position: relative; border: 18px solid transparent; border-image: linear-gradient(45deg, #af8c30, #f7e08b, #d4af37, #f7e08b, #af8c30) 1; outline: 1.5pt solid #d4af37; outline-offset: -10px; font-family: 'Outfit', sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; zoom: 0.85; transform-origin: top center;">
            <!-- Selo de Garantia -->
            <div style="position: absolute; top: -20px; left: 50%; transform: translateX(-50%); background: #fff; padding: 0 25px; z-index: 10;">
                 <i class="fa-solid fa-award" style="font-size: 3rem; color: #d4af37; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.15));"></i>
            </div>

            <!-- SECTION 1: CABEÇALHO -->
            <div style="display: flex; flex-direction: column; justify-content: center; align-items: center;">
                <?php if (!empty($company['logo_url'])): ?>
                    <img src="<?=htmlspecialchars($company['logo_url'])?>" alt="Logo" style="max-height: 75px; max-width: 240px; object-fit: contain; margin-bottom: 0.5rem;">
                <?php else: ?>
                    <h2 style="font-size: 1.6rem; font-weight: 900; color: var(--text-main); margin: 0;"><?=htmlspecialchars($company['company_name']??'CETUSG')?></h2>
                <?php endif; ?>
                <h1 style="font-size: 3.5rem; font-weight: 900; color: var(--text-main); margin: 0; letter-spacing: 5px; line-height: 1;">CERTIFICADO</h1>
                <h2 style="font-size: 1.65rem; font-weight: 400; color: #d4af37; margin: 0.25rem 0 0; font-family: 'EB Garamond', serif; font-style: italic;">Agradecemos pelo seu Empenho</h2>
            </div>
            
            <!-- SECTION 2: TEXTO -->
            <div style="display: flex; flex-direction: column; justify-content: center; align-items: center;">
                <p style="font-size: 1.3rem; line-height: 1.55; color: var(--text-main); margin: 0; max-width: 900px; font-family: 'Inter', sans-serif;">
                    A <strong style="color: var(--text-main);"><?=htmlspecialchars($company['company_name']??'CETUSG')?></strong> confere o presente certificado a
                    <br>
                    <strong style="font-size: 2.1rem; color: var(--text-main); display: block; margin: 0.8rem 0; font-family: 'Outfit', sans-serif;"><?=htmlspecialchars($certVol['name'])?></strong>
                    portador do CPF <strong><?=htmlspecialchars($certVol['cpf'] ?? '---')?></strong>, 
                    pela valiosa contribuição voluntária realizada entre <strong style="color: var(--text-main);"><?=date('d/m/Y',strtotime($certVol['start_date']))?></strong> 
                    e <strong style="color: var(--text-main);"><?=$certVol['end_date'] ? date('d/m/Y',strtotime($certVol['end_date'])) : date('d/m/Y')?></strong>, 
                    atuando na área de <strong><?=htmlspecialchars($certVol['work_area'])?></strong>, 
                    setor <strong><?=htmlspecialchars($certVol['volunteering_sector'])?></strong>,
                    na modalidade <strong><?=htmlspecialchars($certVol['location'])?></strong>.
                </p>
            </div>

            <!-- SECTION 3: DADOS -->
            <div style="display: flex; justify-content: center; align-items: center;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; width: 100%; max-width: 800px; padding: 1.25rem; background: #fdfdfd; border: 1.5px solid #f1f5f9; border-radius: 1rem; box-shadow: 0 4px 10px rgba(0,0,0,0.02);">
                    <div style="text-align: center;">
                        <div style="font-size: 0.8rem; color: var(--text-soft); font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 0.25rem;">Carga Horária Total</div>
                        <div style="font-size: 2.5rem; font-weight: 900; color: var(--text-main); line-height: 1;"><?=number_format($certVol['total_hours'], 1, ',', '')?> <span style="font-size: 1.1rem; color: var(--text-soft); font-weight: 600;">Horas</span></div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 0.8rem; color: var(--text-soft); font-weight: 800; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 0.25rem;">Unidade de Exercício</div>
                        <div style="font-size: 1.3rem; font-weight: 700; color: var(--text-main); margin-top: 0.5rem; line-height: 1.2;"><?=htmlspecialchars($certVol['unit_name']??'Sede Central')?></div>
                    </div>
                </div>
            </div>

            <!-- SECTION 4: ASSINATURA -->
            <div style="display: flex; flex-direction: column; justify-content: flex-end; align-items: center;">
                <?php if (!empty($company['certificate_signature_url'])): ?>
                    <img src="<?=htmlspecialchars($company['certificate_signature_url'])?>" alt="Assinatura" style="max-height: 85px; width: auto; margin-bottom: 0;">
                <?php else: ?>
                    <div style="height: 50px;"></div>
                <?php endif; ?>
                <div style="width: 320px; border-top: 2px solid #334155; margin-top: 0; padding-top: 6px;">
                    <p style="font-size: 1rem; font-weight: 800; color: var(--text-main); margin: 0; line-height: 1.2;"><?= htmlspecialchars($company['certificate_global_text'] ?? 'Diretora Geral') ?></p>
                    <p style="font-size: 0.8rem; color: var(--text-soft); margin: 0; font-weight: 600; text-transform: uppercase;"><?=htmlspecialchars($company['company_name']??'CETUSG')?></p>
                </div>
            </div>
        </div>
        
        <div style="text-align:center;margin-top:1.5rem;display:flex;gap:1.25rem;justify-content:center; padding-bottom: 1rem;">
            <button onclick="window.print()" class="btn-primary" style="padding: 0.75rem 2rem;"><i class="fa-solid fa-print"></i> Imprimir / Salvar PDF</button>
            <a href="?page=voluntariado" class="btn-secondary" style="text-decoration:none; padding: 0.75rem 2rem;">Fechar</a>
        </div>
    </div>
</div>

<style>
@media print {
    @page { 
        size: 297mm 210mm; 
        margin: 0; 
    }
    
    html, body { 
        width: 297mm !important;
        height: 210mm !important;
        margin: 0 !important; 
        padding: 0 !important; 
        background: #fff !important; 
        overflow: hidden !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    body > *:not(#certOverlay) { display: none !important; }
    
    #certOverlay { 
        display: flex !important; 
        visibility: visible !important;
        position: absolute !important; 
        top: 0 !important;
        left: 0 !important;
        width: 297mm !important; 
        height: 210mm !important; 
        background: #fff !important; 
        z-index: 9999999 !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    #certOverlay * { visibility: visible !important; }
    #certOverlay button, #certOverlay a, #certOverlay .close-btn { display: none !important; }
    
    #certOverlay > div { 
        display: flex !important;
        width: 100% !important; 
        height: 100% !important; 
        max-width: none !important; 
        background: #fff !important; 
        align-items: center !important;
        justify-content: center !important;
        border: none !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    #certPrintPaper {
        width: 282mm !important; 
        height: 195mm !important; 
        zoom: 1 !important;
        transform: none !important;
        border: 20px solid transparent !important;
        border-image: linear-gradient(45deg, #af8c30, #f7e08b, #d4af37, #f7e08b, #af8c30) 1 !important;
        padding: 12mm !important; 
        box-sizing: border-box !important;
        display: grid !important;
        grid-template-rows: 1.2fr 2fr 1fr 1fr !important;
        background: #fff !important;
        position: relative !important;
        box-shadow: none !important;
        outline: 1.5pt solid #d4af37 !important;
        outline-offset: -12px !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        margin: 0 !important; /* Centralizado pelo flex do pai */
    }
}
</style>
<?php endif; ?>

<!-- ─── SCRIPTS ─────────────────────────────────────────────────────────────── -->
<script>
function setVolunteerMode(mode) {
    const btnSystem = document.getElementById('btnFromSystem');
    const btnManual = document.getElementById('btnManual');
    const systemGroup = document.getElementById('systemUserGroup');
    const unitDisplay = document.getElementById('volunteerUnitDisplay');
    const unitSelect = document.getElementById('volunteerUnit');
    const sectorInput = document.getElementById('volunteerSector');
    
    // Reset fields
    document.getElementById('volunteerName').value = '';
    document.getElementById('volunteerEmail').value = '';
    document.getElementById('volunteerPhone').value = '';
    document.getElementById('volunteerSector').value = '';
    document.getElementById('userSelect').value = '';
    
    if (mode === 'system') {
        btnSystem.style.border = '2px solid var(--crm-purple)';
        btnSystem.style.color = 'var(--crm-purple)';
        btnSystem.style.background = 'rgba(91,33,182,0.05)';
        btnManual.style.border = '';
        btnManual.style.color = '';
        btnManual.style.background = '';
        
        systemGroup.style.display = 'block';
        unitDisplay.style.display = 'block';
        unitSelect.style.display = 'none';
        sectorInput.readOnly = true;
    } else {
        btnManual.style.border = '2px solid var(--crm-purple)';
        btnManual.style.color = 'var(--crm-purple)';
        btnManual.style.background = 'rgba(91,33,182,0.05)';
        btnSystem.style.border = '';
        btnSystem.style.color = '';
        btnSystem.style.background = '';
        
        systemGroup.style.display = 'none';
        unitDisplay.style.display = 'none';
        unitSelect.style.display = 'block';
        sectorInput.readOnly = false;
    }
}

function fillUserData() {
    const sel = document.getElementById('userSelect');
    const opt = sel.options[sel.selectedIndex];
    if (opt.value) {
        document.getElementById('volunteerName').value        = opt.getAttribute('data-name');
        document.getElementById('volunteerEmail').value       = opt.getAttribute('data-email');
        document.getElementById('volunteerPhone').value       = opt.getAttribute('data-phone');
        document.getElementById('volunteerSector').value      = opt.getAttribute('data-sector');
        document.getElementById('volunteerUnit').value        = opt.getAttribute('data-unit');
        document.getElementById('volunteerUnitDisplay').value = opt.getAttribute('data-unit-name');
    }
}

function calcTotal() {
    const m = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];
    let t = 0;
    m.forEach(k => { t += parseFloat(document.querySelector(`input[name="hours_${k}"]`)?.value || 0); });
    document.getElementById('totalHours').textContent = t.toFixed(1) + 'h';
    const rate = parseFloat(document.querySelector('input[name="hourly_rate"]')?.value) || 0;
    document.getElementById('totalValue').textContent = 'R$ ' + (t * rate).toFixed(2).replace('.', ',');
}

function verHistorico(id, nome) {
    document.getElementById('histName').textContent = nome;
    document.getElementById('histBody').innerHTML = '<p style="text-align:center;color:#94a3b8;padding:2rem;">Carregando...</p>';
    document.getElementById('histModal').style.display = 'flex';

    fetch(`?page=voluntariado&hist_json=${id}`)
        .then(r => r.json())
        .then(data => {
            if (!data.length) {
                document.getElementById('histBody').innerHTML = '<p style="text-align:center;color:#94a3b8;padding:2rem;">Nenhum histórico encontrado.</p>';
                return;
            }
            let html = '';
            data.reverse().forEach((h, i) => {
                const dateRange = h.end_date ? `${h.start_date} → ${h.end_date}` : `${h.start_date} → (Em Aberto/Reativado)`;
                html += `<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:.75rem;padding:1rem;margin-bottom:.75rem;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
                        <span style="font-size:.75rem;font-weight:700;color:#7c3aed;text-transform:uppercase;letter-spacing:.05em;">Período ${data.length - i}</span>
                        <span style="font-size:.75rem;color:#94a3b8;">${dateRange}</span>
                    </div>
                    <div style="display:flex;gap:2rem;align-items:center;">
                        <div><p style="font-size:.7rem;color:#64748b;">Total de Horas</p><p style="font-size:1.25rem;font-weight:900;color:#f59e0b;">${h.total_hours}h</p></div>
                        ${h.editor_name ? `
                        <div style="margin-left:auto; display:flex; align-items:center; gap:.5rem; border-left:1px solid #e2e8f0; padding-left:1rem;">
                            ${h.editor_avatar ? `<img src="${h.editor_avatar}" style="width:24px;height:24px;border-radius:50%;object-fit:cover;">` : `<div style="width:24px;height:24px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;font-size:10px;">👤</div>`}
                            <div>
                                <p style="font-size:.65rem;color:#64748b;margin:0;">Responsável:</p>
                                <p style="font-size:.75rem;font-weight:700;color:#334155;margin:0;">${h.editor_name}</p>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>`;
            });
            document.getElementById('histBody').innerHTML = html;
        })
        .catch(() => {
            document.getElementById('histBody').innerHTML = '<p style="text-align:center;color:#ef4444;padding:2rem;">Erro ao carregar histórico.</p>';
        });
}

function switchVolTab(tab) {
    document.querySelectorAll('.vol-content').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('content-' + tab).classList.add('active');
    document.getElementById('tab-' + tab).classList.add('active');
}

<?php if (isset($_GET['edit'])): ?>
document.getElementById('editModal').style.display = 'flex';
<?php endif; ?>
</script>

<?php
// ─── JSON endpoint para histórico via AJAX ─────────────────────────────────
if (isset($_GET['hist_json'])) {
    $s = $pdo->prepare("SELECT vh.*, u.name as editor_name, u.avatar_url as editor_avatar 
                        FROM volunteer_history vh 
                        LEFT JOIN users u ON BINARY vh.edited_by = BINARY u.id 
                        WHERE vh.volunteer_id = ? ORDER BY vh.created_at ASC");
    $s->execute([$_GET['hist_json']]);
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($s->fetchAll(PDO::FETCH_ASSOC));
    exit;
}
?>
