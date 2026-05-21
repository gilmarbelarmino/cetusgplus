<?php
$currentUser = getCurrentUser();
$compId = getCurrentUserCompanyId();

$stmt_u = $pdo->prepare("SELECT u.name, u.email, u.avatar_url, u.sector, rh.role_name 
    FROM users u 
    LEFT JOIN rh_employee_details rh ON CONVERT(u.id USING utf8mb4) = CONVERT(rh.user_id USING utf8mb4)
    WHERE u.id = ?");
$stmt_u->execute([$currentUser['id']]);
$userData = $stmt_u->fetch(PDO::FETCH_ASSOC);

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_face_descriptors (
        user_id VARCHAR(50) PRIMARY KEY,
        descriptor LONGTEXT NOT NULL,
        photo_base64 LONGTEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    try { $pdo->exec("ALTER TABLE user_face_descriptors ADD COLUMN photo_base64 LONGTEXT"); } catch(Exception $e){}
} catch(Exception $e) {}

$stmt_f = $pdo->prepare("SELECT descriptor, photo_base64 FROM user_face_descriptors WHERE user_id = ?");
$stmt_f->execute([$currentUser['id']]);
$faceData = $stmt_f->fetch(PDO::FETCH_ASSOC);
$hasFace = !empty($faceData['descriptor']);

$stmt_r = $pdo->prepare("SELECT record_type, record_time, address, status FROM time_records WHERE user_id = ? AND company_id = ? AND DATE(record_time) = CURDATE() ORDER BY record_time ASC");
$stmt_r->execute([$currentUser['id'], $compId]);
$todayRecords = $stmt_r->fetchAll(PDO::FETCH_ASSOC);

$stmt_c = $pdo->prepare("SELECT latitude, longitude, radius_meters, allow_remote_work FROM company_settings WHERE id = ?");
$stmt_c->execute([$compId]);
$companySettings = $stmt_c->fetch(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

<style>
.ponto-wrap { display: grid; grid-template-columns: 1fr 380px; gap: 20px; max-width: 1280px; margin: 0 auto; }
@media(max-width:1024px){ .ponto-wrap { grid-template-columns:1fr; } }

.pt-card { background:var(--bg-card,#fff); border:1px solid var(--border-color,#e2e8f0); border-radius:16px; padding:20px; margin-bottom:16px; }
.pt-title { font-size:.72rem; font-weight:900; text-transform:uppercase; letter-spacing:.1em; color:#64748b; margin-bottom:14px; display:flex; align-items:center; gap:8px; }
.pt-title i { color:#5B21B6; }

/* Perfil */
.user-bar { display:flex; align-items:center; gap:14px; padding:16px 20px; background:linear-gradient(135deg,rgba(91,33,182,.08),rgba(91,33,182,.02)); border:1px solid rgba(91,33,182,.15); border-radius:14px; margin-bottom:16px; }
.user-bar-avatar { width:52px; height:52px; border-radius:12px; object-fit:cover; border:2px solid rgba(91,33,182,.25); flex-shrink:0; }
.user-bar-init { width:52px; height:52px; border-radius:12px; background:linear-gradient(135deg,#5B21B6,#7C3AED); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:900; font-size:1.2rem; flex-shrink:0; }
.face-badge { padding:4px 10px; border-radius:20px; font-size:.72rem; font-weight:700; }
.face-ok { background:#ecfdf5; color:#059669; border:1px solid #6ee7b7; }
.face-no { background:#fef3c7; color:#d97706; border:1px solid #fde68a; }

/* Câmera */
.cam-box { position:relative; width:100%; aspect-ratio:4/3; background:#0f172a; border-radius:14px; overflow:hidden; margin-bottom:12px; }
.cam-box video { width:100%; height:100%; object-fit:cover; display:block; }
.cam-box canvas { position:absolute; inset:0; width:100%; height:100%; }
.cam-bar { position:absolute; bottom:0; left:0; right:0; padding:10px 14px; display:flex; align-items:center; gap:8px; font-size:.8rem; font-weight:700; color:#fff; background:linear-gradient(transparent,rgba(0,0,0,.75)); backdrop-filter:blur(4px); }
.cam-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; animation:pdot 1.5s infinite; }
@keyframes pdot{ 0%,100%{opacity:1}50%{opacity:.3} }
.cam-dot.red{background:#ef4444} .cam-dot.green{background:#10b981} .cam-dot.yellow{background:#f59e0b} .cam-dot.blue{background:#3b82f6}

/* Manual mode banner (quando camera desligada) */
.manual-mode-banner { display:none; flex-direction:column; align-items:center; justify-content:center; background:#f8fafc; border:2px dashed #cbd5e1; border-radius:14px; padding:32px 20px; margin-bottom:12px; text-align:center; gap:10px; }
.manual-mode-banner i { font-size:2.5rem; color:#94a3b8; }

/* Toggle */
.toggle-bar { display:flex; align-items:center; justify-content:space-between; padding:12px 16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; margin-bottom:12px; }
.sw { position:relative; width:46px; height:26px; flex-shrink:0; }
.sw input { opacity:0; width:0; height:0; }
.sw-slider { position:absolute; inset:0; background:#cbd5e1; border-radius:26px; cursor:pointer; transition:.3s; }
.sw-slider:before { content:''; position:absolute; width:20px; height:20px; left:3px; top:3px; background:#fff; border-radius:50%; transition:.3s; box-shadow:0 1px 4px rgba(0,0,0,.2); }
.sw input:checked+.sw-slider { background:#5B21B6; }
.sw input:checked+.sw-slider:before { transform:translateX(20px); }

/* Punch buttons */
.punch-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px; }
.punch-btn { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; padding:16px 10px; border-radius:12px; border:2px solid transparent; cursor:pointer; font-weight:800; font-size:.82rem; transition:all .2s; }
.punch-btn:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(0,0,0,.1); }
.punch-btn i { font-size:1.5rem; }
.punch-btn.entrada   { border-color:#10b981; color:#059669; background:#ecfdf5; }
.punch-btn.entrada:hover   { background:#10b981; color:#fff; }
.punch-btn.s-almoco  { border-color:#f59e0b; color:#d97706; background:#fffbeb; }
.punch-btn.s-almoco:hover  { background:#f59e0b; color:#fff; }
.punch-btn.r-almoco  { border-color:#3b82f6; color:#2563eb; background:#eff6ff; }
.punch-btn.r-almoco:hover  { background:#3b82f6; color:#fff; }
.punch-btn.saida     { border-color:#ef4444; color:#dc2626; background:#fef2f2; }
.punch-btn.saida:hover     { background:#ef4444; color:#fff; }
.punch-btn.pausa     { border-color:#8b5cf6; color:#7c3aed; background:#f5f3ff; grid-column:span 2; flex-direction:row; padding:12px 16px; }

/* Cadastro facial */
.face-register-card { border:2px dashed #f59e0b; background:#fffbeb; border-radius:14px; padding:20px; text-align:center; margin-bottom:12px; }

/* Registros */
.rec-item { display:flex; align-items:center; gap:12px; padding:10px 12px; border-radius:10px; background:#f8fafc; border:1px solid #e2e8f0; margin-bottom:8px; }
.rec-type { font-weight:800; font-size:.88rem; flex:1; }
.rec-time { font-family:monospace; font-size:.88rem; color:#64748b; }

/* Mapa */
#pontoMap { width:100%; height:260px; border-radius:12px; border:1px solid #e2e8f0; }
.gps-line { display:flex; align-items:flex-start; gap:8px; font-size:.8rem; color:#475569; padding:8px 12px; background:#f8fafc; border-radius:8px; margin-top:8px; border:1px solid #e2e8f0; }
.gps-line i { color:#5B21B6; margin-top:1px; flex-shrink:0; }

/* Loading */
.ld-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:99999; align-items:center; justify-content:center; backdrop-filter:blur(4px); }
.ld-overlay.show { display:flex; }
.ld-box { background:#fff; border-radius:20px; padding:30px 40px; text-align:center; box-shadow:0 20px 60px rgba(0,0,0,.3); }
.spinner { width:48px; height:48px; border:4px solid #e2e8f0; border-top-color:#5B21B6; border-radius:50%; animation:spin .8s linear infinite; margin:0 auto 16px; }
@keyframes spin { to{transform:rotate(360deg)} }

/* Modal */
.modal-pt { display:none; position:fixed; inset:0; background:rgba(15,23,42,.65); z-index:10000; align-items:center; justify-content:center; padding:20px; backdrop-filter:blur(6px); }
.modal-pt.show { display:flex; }
.modal-pt-box { background:#fff; border-radius:20px; padding:28px; max-width:420px; width:100%; box-shadow:0 20px 60px rgba(0,0,0,.3); }
</style>

<!-- LOADING -->
<div class="ld-overlay" id="ldOverlay">
    <div class="ld-box">
        <div class="spinner"></div>
        <div style="font-weight:800;color:#1e293b;" id="ldText">Processando...</div>
        <div style="font-size:.8rem;color:#64748b;margin-top:6px;" id="ldSub">Aguarde</div>
    </div>
</div>

<!-- MODAL MANUAL -->
<div class="modal-pt" id="manualModal">
    <div class="modal-pt-box">
        <div style="font-size:1.1rem;font-weight:900;color:#0f172a;margin-bottom:6px;">
            <i class="fa-solid fa-hand-pointer" style="color:#5B21B6;margin-right:8px;"></i> Registro Manual
        </div>
        <div style="font-size:.82rem;color:#64748b;margin-bottom:20px;line-height:1.5;">
            Selecione o tipo. Foto, localização e IP serão capturados como evidência.
        </div>
        <div class="punch-grid" style="margin-bottom:16px;">
            <button class="punch-btn entrada"  onclick="execPunch('Entrada',true)"><i class="fa-solid fa-arrow-right-to-bracket"></i>Entrada</button>
            <button class="punch-btn s-almoco" onclick="execPunch('Saida Almoco',true)"><i class="fa-solid fa-utensils"></i>Saída Almoço</button>
            <button class="punch-btn r-almoco" onclick="execPunch('Retorno Almoco',true)"><i class="fa-solid fa-arrow-rotate-left"></i>Retorno Almoço</button>
            <button class="punch-btn saida"    onclick="execPunch('Saida',true)"><i class="fa-solid fa-arrow-right-from-bracket"></i>Saída Final</button>
            <button class="punch-btn pausa"    onclick="execPunch('Pausa',true)"><i class="fa-solid fa-pause"></i> Pausa</button>
        </div>
        <button onclick="closeModal()" style="width:100%;padding:12px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;cursor:pointer;font-weight:700;color:#64748b;">Cancelar</button>
    </div>
</div>

<!-- HEADER -->
<div class="page-header" style="margin-bottom:16px;">
    <div class="page-header-info">
        <div class="page-header-icon"><i class="fa-solid fa-clock"></i></div>
        <div class="page-header-text">
            <h2>Ponto Eletrônico</h2>
            <p id="ptClock">Carregando...</p>
        </div>
    </div>
    <button onclick="document.getElementById('manualModal').classList.add('show')"
            style="display:flex;align-items:center;gap:8px;padding:10px 18px;border-radius:10px;border:1px solid #e2e8f0;background:#fff;cursor:pointer;font-weight:700;font-size:.85rem;color:#475569;">
        <i class="fa-solid fa-hand-pointer" style="color:#5B21B6;"></i> Registro Manual
    </button>
</div>

<!-- BARRA DO USUÁRIO -->
<div class="user-bar">
    <?php if (!empty($userData['avatar_url'])): ?>
        <img src="<?= htmlspecialchars($userData['avatar_url']) ?>" class="user-bar-avatar" alt="">
    <?php else: ?>
        <div class="user-bar-init"><?= strtoupper(substr($userData['name'] ?? 'U', 0, 2)) ?></div>
    <?php endif; ?>
    <div style="flex:1;">
        <div style="font-weight:900;font-size:1rem;color:var(--crm-black,#0f172a);"><?= htmlspecialchars($userData['name'] ?? 'Usuário') ?></div>
        <div style="font-size:.78rem;color:#64748b;margin-top:2px;">
            <?= htmlspecialchars($userData['role_name'] ?? 'Cargo não definido') ?>
            <?php if (!empty($userData['sector'])): ?> · <?= htmlspecialchars($userData['sector']) ?><?php endif; ?>
        </div>
    </div>
    <span class="face-badge <?= $hasFace ? 'face-ok' : 'face-no' ?>">
        <i class="fa-solid <?= $hasFace ? 'fa-face-smile' : 'fa-face-meh' ?>"></i>
        <?= $hasFace ? 'Face Cadastrada' : 'Sem Face' ?>
    </span>
</div>

<!-- LAYOUT -->
<div class="ponto-wrap">

    <!-- COLUNA ESQUERDA -->
    <div>
        <!-- Card cadastro facial -->
        <?php if (!$hasFace): ?>
        <div class="face-register-card">
            <i class="fa-solid fa-camera-retro" style="font-size:2.5rem;color:#f59e0b;display:block;margin-bottom:12px;"></i>
            <h3 style="font-size:1rem;font-weight:900;color:#92400e;margin-bottom:6px;">Cadastro de Face Necessário</h3>
            <p style="font-size:.83rem;color:#b45309;margin-bottom:16px;line-height:1.5;">
                Olhe diretamente para a câmera com boa iluminação e clique no botão abaixo para registrar seu rosto.
            </p>
            <button onclick="registerFaceClick()" style="background:#f59e0b;color:#fff;border:none;border-radius:10px;padding:12px 24px;font-weight:800;font-size:.9rem;cursor:pointer;">
                <i class="fa-solid fa-face-grin-beam"></i> Cadastrar Minha Face Agora
            </button>
        </div>
        <?php else: ?>
        <div class="pt-card" style="display:flex;align-items:center;gap:14px;padding:14px 20px;background:linear-gradient(135deg,#ecfdf5,#f0fdf4);border-color:#6ee7b7;">
            <?php if (!empty($faceData['photo_base64'])): ?>
                <img src="<?= $faceData['photo_base64'] ?>" style="width:50px;height:50px;border-radius:50%;object-fit:cover;border:2px solid #10b981;" alt="Face">
            <?php else: ?>
                <div style="width:50px;height:50px;border-radius:50%;background:#10b981;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-check" style="color:#fff;font-size:1.3rem;"></i></div>
            <?php endif; ?>
            <div style="flex:1;">
                <div style="font-weight:900;color:#065f46;font-size:.9rem;">Face Cadastrada ✓</div>
                <div style="font-size:.78rem;color:#047857;">Reconhecimento facial ativo. Fique na frente da câmera.</div>
            </div>
            <button onclick="registerFaceClick()" style="background:none;border:1px solid #10b981;color:#059669;border-radius:8px;padding:6px 12px;cursor:pointer;font-size:.75rem;font-weight:700;">
                <i class="fa-solid fa-arrows-rotate"></i> Atualizar
            </button>
        </div>
        <?php endif; ?>

        <!-- CÂMERA -->
        <div class="pt-card" style="padding:16px;">
            <div class="pt-title">
                <i class="fa-solid fa-video"></i> Câmera
                <span id="faceLabel" style="margin-left:auto;font-size:.72rem;color:#5B21B6;">Iniciando...</span>
            </div>

            <!-- Toggle -->
            <div class="toggle-bar">
                <div>
                    <div style="font-weight:700;font-size:.88rem;color:#334155;">
                        <i class="fa-solid fa-face-viewfinder" style="color:#5B21B6;margin-right:6px;"></i>
                        Reconhecimento Facial
                    </div>
                    <div style="font-size:.75rem;color:#94a3b8;margin-top:2px;">Ativa detecção de rosto em tempo real</div>
                </div>
                <label class="sw">
                    <input type="checkbox" id="facialToggle" checked onchange="toggleFacial(this.checked)">
                    <span class="sw-slider"></span>
                </label>
            </div>

            <!-- Câmera ao vivo (mostra quando facial ATIVO) -->
            <div id="cameraCard">
                <div class="cam-box">
                    <video id="videoEl" autoplay muted playsinline></video>
                    <canvas id="ovCanvas"></canvas>
                    <div class="cam-bar">
                        <div class="cam-dot blue" id="camDot"></div>
                        <span id="camTxt">Iniciando câmera...</span>
                    </div>
                </div>
                <div id="faceAuthMsg" style="display:none;padding:10px 14px;border-radius:10px;font-weight:700;font-size:.85rem;text-align:center;"></div>
            </div>

            <!-- Modo manual (mostra quando facial DESATIVADO) -->
            <div class="manual-mode-banner" id="manualBanner">
                <i class="fa-solid fa-video-slash"></i>
                <div style="font-weight:700;color:#64748b;">Câmera desligada</div>
                <div style="font-size:.8rem;color:#94a3b8;">Use os botões de registro manual ao lado.</div>
                <button onclick="document.getElementById('manualModal').classList.add('show')"
                        style="margin-top:8px;background:#5B21B6;color:#fff;border:none;border-radius:10px;padding:10px 20px;font-weight:800;cursor:pointer;font-size:.85rem;">
                    <i class="fa-solid fa-hand-pointer"></i> Abrir Registro Manual
                </button>
            </div>
        </div>

        <!-- MAPA GPS -->
        <div class="pt-card" style="padding:16px;">
            <div class="pt-title">
                <i class="fa-solid fa-map-location-dot"></i> Localização GPS — Dispositivo Real
                <span id="gpsAccBadge" style="margin-left:auto;"></span>
            </div>
            <div id="pontoMap"></div>
            <div class="gps-line" id="gpsAddrBox">
                <i class="fa-solid fa-location-dot"></i>
                <span id="gpsAddr">Aguardando GPS do dispositivo...</span>
            </div>
            <div class="gps-line" style="margin-top:4px;">
                <i class="fa-solid fa-satellite-dish"></i>
                <span id="gpsCrd">—</span>
            </div>
            <div class="gps-line" style="margin-top:4px;">
                <i class="fa-solid fa-signal"></i>
                <span id="gpsSt">Solicitando permissão de localização...</span>
            </div>
        </div>
    </div>

    <!-- COLUNA DIREITA -->
    <div>
        <!-- BOTÕES DE PONTO -->
        <div class="pt-card">
            <div class="pt-title"><i class="fa-solid fa-clock-rotate-left"></i> Bater Ponto</div>

            <div id="faceWarn" style="display:none;background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:10px 12px;margin-bottom:12px;font-size:.8rem;font-weight:700;color:#92400e;">
                <i class="fa-solid fa-triangle-exclamation" style="color:#f59e0b;"></i>
                Aguardando reconhecimento facial. Posicione seu rosto na câmera.<br>
                <span style="font-weight:400;margin-top:4px;display:block;">Ou desative o facial para registro manual.</span>
            </div>

            <div class="punch-grid" id="punchGrid">
                <button class="punch-btn entrada"  onclick="clickPunch('Entrada')"><i class="fa-solid fa-arrow-right-to-bracket"></i>Entrada</button>
                <button class="punch-btn s-almoco" onclick="clickPunch('Saida Almoco')"><i class="fa-solid fa-utensils"></i>Saída Almoço</button>
                <button class="punch-btn r-almoco" onclick="clickPunch('Retorno Almoco')"><i class="fa-solid fa-arrow-rotate-left"></i>Retorno Almoço</button>
                <button class="punch-btn saida"    onclick="clickPunch('Saida')"><i class="fa-solid fa-arrow-right-from-bracket"></i>Saída Final</button>
                <button class="punch-btn pausa"    onclick="clickPunch('Pausa')"><i class="fa-solid fa-pause"></i> Pausa Extra</button>
            </div>

            <div style="text-align:center;margin-top:8px;">
                <button onclick="document.getElementById('manualModal').classList.add('show')"
                        style="background:none;border:none;cursor:pointer;color:#94a3b8;font-size:.78rem;font-weight:600;text-decoration:underline;">
                    <i class="fa-solid fa-hand-pointer"></i> Preferir registro manual (sem câmera)
                </button>
            </div>
        </div>

        <!-- HISTÓRICO HOJE -->
        <div class="pt-card">
            <div class="pt-title">
                <i class="fa-solid fa-list-check"></i> Registros de Hoje
                <span style="margin-left:auto;background:#f1f5f9;border-radius:20px;padding:2px 10px;font-size:.7rem;font-weight:800;color:#5B21B6;"><?= date('d/m/Y') ?></span>
            </div>
            <?php if (empty($todayRecords)): ?>
                <div style="text-align:center;padding:30px;color:#94a3b8;">
                    <i class="fa-solid fa-clock" style="font-size:2.5rem;color:#e2e8f0;display:block;margin-bottom:10px;"></i>
                    <div style="font-weight:700;">Nenhum registro hoje</div>
                    <div style="font-size:.78rem;margin-top:4px;">Bata o ponto para iniciar o dia.</div>
                </div>
            <?php else:
                $colorMap = ['Entrada'=>'#10b981','Saida Almoco'=>'#f59e0b','Retorno Almoco'=>'#3b82f6','Saida'=>'#ef4444','Pausa'=>'#8b5cf6'];
                $iconMap  = ['Entrada'=>'fa-arrow-right-to-bracket','Saida Almoco'=>'fa-utensils','Retorno Almoco'=>'fa-arrow-rotate-left','Saida'=>'fa-arrow-right-from-bracket','Pausa'=>'fa-pause'];
                foreach ($todayRecords as $rec):
                    $color = $colorMap[$rec['record_type']] ?? '#64748b';
                    $icon  = $iconMap[$rec['record_type']] ?? 'fa-clock';
                    $time  = date('H:i', strtotime($rec['record_time']));
            ?>
                <div class="rec-item">
                    <i class="fa-solid <?= $icon ?>" style="color:<?= $color ?>;font-size:1.1rem;width:20px;text-align:center;"></i>
                    <div class="rec-type"><?= htmlspecialchars($rec['record_type']) ?></div>
                    <?php if ($rec['status'] === 'Ocorrencia'): ?>
                        <span style="font-size:.7rem;font-weight:700;color:#ef4444;">⚠ Ocorrência</span>
                    <?php endif; ?>
                    <div class="rec-time"><?= $time ?></div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- DADOS CAPTURADOS -->
        <div class="pt-card" style="font-size:.78rem;color:#64748b;line-height:1.9;">
            <div class="pt-title"><i class="fa-solid fa-shield-halved"></i> Dados Capturados</div>
            <div><i class="fa-solid fa-check" style="color:#10b981;margin-right:6px;"></i>Data e Hora exata</div>
            <div><i class="fa-solid fa-check" style="color:#10b981;margin-right:6px;"></i>Coordenadas GPS reais (Lat/Lng)</div>
            <div><i class="fa-solid fa-check" style="color:#10b981;margin-right:6px;"></i>Endereço via OpenStreetMap</div>
            <div><i class="fa-solid fa-check" style="color:#10b981;margin-right:6px;"></i>Precisão do GPS em metros</div>
            <div><i class="fa-solid fa-check" style="color:#10b981;margin-right:6px;"></i>Foto facial como evidência</div>
            <div><i class="fa-solid fa-check" style="color:#10b981;margin-right:6px;"></i>IP e navegador/dispositivo</div>
        </div>
    </div>
</div>

<script>
// ──── DADOS PHP ────
const HAS_FACE  = <?= $hasFace ? 'true' : 'false' ?>;
const COMPANY   = <?= json_encode($companySettings ?: []) ?>;

// ──── ESTADO ────
let curLat = null, curLng = null, curAddr = '', curAcc = null;
let map, uMarker, accCircle;
let modelsOk = false, facialOn = true, faceOk = false;
let detectTimer = null, camStream = null;
let savedDesc = null;

const video   = document.getElementById('videoEl');
const canvas  = document.getElementById('ovCanvas');
const dot     = document.getElementById('camDot');
const camTxt  = document.getElementById('camTxt');
const authMsg = document.getElementById('faceAuthMsg');
const fWarn   = document.getElementById('faceWarn');

// ──── RELÓGIO ────
(function clock(){ const n=new Date(); document.getElementById('ptClock').textContent=n.toLocaleDateString('pt-BR',{weekday:'long',day:'2-digit',month:'long',year:'numeric'})+' — '+n.toLocaleTimeString('pt-BR'); setTimeout(clock,1000); })();

function closeModal(){ document.getElementById('manualModal').classList.remove('show'); }

// ──── TOGGLE FACIAL ────
function toggleFacial(on){
    facialOn = on;
    const lbl    = document.getElementById('faceLabel');
    const camCrd = document.getElementById('cameraCard');
    const mnBnr  = document.getElementById('manualBanner');

    if(on){
        lbl.textContent='● Ativo'; lbl.style.color='#10b981';
        camCrd.style.display='block'; mnBnr.style.display='none';
        startCam().then(()=>{ if(modelsOk) startDetect(); else loadModels().then(startDetect); });
    } else {
        lbl.textContent='○ Câmera Desligada'; lbl.style.color='#94a3b8';
        faceOk=false;
        clearDetect(); clearOv(); setAuthMsg(null);
        fWarn.style.display='none';
        // Desligar câmera fisicamente
        if(camStream){ camStream.getTracks().forEach(t=>t.stop()); camStream=null; video.srcObject=null; }
        camCrd.style.display='none'; mnBnr.style.display='flex';
    }
    updateBtns();
}

// ──── CÂMERA ────
async function startCam(){
    try {
        const s = await navigator.mediaDevices.getUserMedia({video:{facingMode:'user',width:{ideal:640},height:{ideal:480}}});
        camStream=s; video.srcObject=s;
        await new Promise(r=>{ video.onloadedmetadata=r; });
        await video.play();
        setDot('blue','Câmera ativa — carregando IA...');
    } catch(e){
        setDot('red','Câmera bloqueada — use registro manual');
        facialOn=false;
        document.getElementById('facialToggle').checked=false;
        toggleFacial(false);
    }
}

// ──── MODELOS ────
async function loadModels(){
    if(!facialOn) return;
    try {
        const URL='https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights';
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(URL)
        ]);
        modelsOk=true;
        setDot('green','IA carregada — posicione seu rosto');
        document.getElementById('faceLabel').textContent='● Ativo';
        document.getElementById('faceLabel').style.color='#10b981';
    } catch(e){ setDot('red','Erro IA — use registro manual'); modelsOk=false; }
}

async function loadDesc(){
    try {
        const r=await fetch('api_ponto.php?action=get_face_descriptor');
        const d=await r.json();
        if(d.success&&d.descriptor) savedDesc=new Float32Array(JSON.parse(d.descriptor));
    } catch(e){}
}

// ──── DETECÇÃO ────
function startDetect(){
    clearDetect();
    if(!modelsOk){ setTimeout(startDetect,600); return; }
    detectTimer=setInterval(async()=>{
        if(!facialOn||!modelsOk||!camStream||video.paused||video.readyState<2) return;
        const dz={width:video.videoWidth||video.clientWidth,height:video.videoHeight||video.clientHeight};
        if(!dz.width) return;
        faceapi.matchDimensions(canvas,dz);
        const det=await faceapi.detectSingleFace(video,new faceapi.TinyFaceDetectorOptions({inputSize:320,scoreThreshold:0.5})).withFaceLandmarks().withFaceDescriptor();
        const ctx=canvas.getContext('2d'); ctx.clearRect(0,0,canvas.width,canvas.height);
        if(!det){ faceOk=false; setDot('yellow','Nenhum rosto detectado'); setAuthMsg(null); updateBtns(); return; }
        const res=faceapi.resizeResults(det,dz);
        if(!HAS_FACE||!savedDesc){
            drawBox(ctx,res.detection.box,'#f59e0b','Cadastre sua face');
            setDot('yellow','Rosto detectado — cadastre sua face'); setAuthMsg('pending'); faceOk=false;
        } else {
            const dist=faceapi.euclideanDistance(det.descriptor,savedDesc);
            if(dist<0.5){
                drawBox(ctx,res.detection.box,'#10b981','✓ Autenticado');
                setDot('green','Autenticado! Pode bater o ponto.'); setAuthMsg('ok'); faceOk=true;
            } else {
                drawBox(ctx,res.detection.box,'#ef4444','✗ Não reconhecido');
                setDot('red','Rosto não reconhecido'); setAuthMsg('fail'); faceOk=false;
            }
        }
        updateBtns();
    },600);
}

function clearDetect(){ if(detectTimer){ clearInterval(detectTimer); detectTimer=null; } }
function clearOv(){ const c=canvas.getContext('2d'); if(c) c.clearRect(0,0,canvas.width,canvas.height); }
function drawBox(ctx,box,color,lbl){
    ctx.strokeStyle=color; ctx.lineWidth=3; ctx.beginPath(); ctx.rect(box.x,box.y,box.width,box.height); ctx.stroke();
    ctx.fillStyle=color; ctx.font='bold 13px Inter,sans-serif';
    const tw=ctx.measureText(lbl).width; ctx.fillRect(box.x,box.y-22,tw+16,22);
    ctx.fillStyle='white'; ctx.fillText(lbl,box.x+8,box.y-6);
}
function setDot(c,t){ dot.className='cam-dot '+c; camTxt.textContent=t; }
function setAuthMsg(s){
    if(!s){authMsg.style.display='none'; fWarn.style.display=(facialOn&&HAS_FACE)?'block':'none'; return;}
    fWarn.style.display='none'; authMsg.style.display='block';
    const styles={ok:'background:#ecfdf5;color:#059669;border:1px solid #6ee7b7',fail:'background:#fef2f2;color:#dc2626;border:1px solid #fca5a5',pending:'background:#fffbeb;color:#d97706;border:1px solid #fde68a'};
    const msgs={ok:'<i class="fa-solid fa-circle-check"></i> Autenticado! Clique no tipo de registro.',fail:'<i class="fa-solid fa-circle-xmark"></i> Rosto não reconhecido. Ajuste a iluminação.',pending:'<i class="fa-solid fa-triangle-exclamation"></i> Cadastre sua face para usar o reconhecimento.'};
    authMsg.style.cssText='display:block;padding:10px 14px;border-radius:10px;font-weight:700;font-size:.85rem;text-align:center;'+styles[s];
    authMsg.innerHTML=msgs[s]||'';
}
function updateBtns(){
    const locked=facialOn&&HAS_FACE&&!faceOk;
    document.querySelectorAll('#punchGrid .punch-btn').forEach(b=>{
        b.style.opacity=locked?'0.4':'1'; b.style.cursor=locked?'not-allowed':'pointer'; b.style.filter=locked?'grayscale(.4)':'none';
    });
    fWarn.style.display=(facialOn&&HAS_FACE&&!faceOk)?'block':'none';
}

// ──── CADASTRO FACIAL ────
async function registerFaceClick(){
    if(!facialOn||!camStream){ alert('Ative o reconhecimento facial e aguarde a câmera ligar.'); return; }
    if(!modelsOk){ alert('Aguarde o carregamento da IA.'); return; }
    showLd('Mapeando rosto...','Olhe para a câmera com boa iluminação');
    await new Promise(r=>setTimeout(r,800));
    const det=await faceapi.detectSingleFace(video,new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks().withFaceDescriptor();
    hideLd();
    if(!det){ alert('Rosto não detectado. Centralize o rosto na câmera.'); return; }
    const desc=Array.from(det.descriptor), photo=capPhoto();
    showLd('Salvando...','Quase pronto!');
    try {
        const r=await fetch('api_ponto.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'register_face',descriptor:JSON.stringify(desc),photo})});
        const d=await r.json(); hideLd();
        if(d.success){ alert('✅ Face cadastrada!'); window.location.reload(); }
        else alert('Erro: '+(d.error||'Tente novamente'));
    } catch(e){ hideLd(); alert('Erro de conexão.'); }
}

// ──── PONTO ────
function clickPunch(type){
    if(facialOn&&HAS_FACE&&!faceOk){
        authMsg.style.cssText='display:block;padding:10px 14px;border-radius:10px;font-weight:700;font-size:.85rem;text-align:center;background:#fef2f2;color:#dc2626;border:1px solid #fca5a5;';
        authMsg.innerHTML='<i class="fa-solid fa-circle-xmark"></i> Autenticação facial necessária! Aguarde caixa verde ou use "Registro Manual".';
        return;
    }
    execPunch(type,false);
}

async function execPunch(type,isManual=false){
    closeModal();
    if(!curLat&&!curLng){ if(!confirm('GPS ainda não obtido. Registrar assim mesmo?')) return; }
    showLd('Registrando '+type+'...','Capturando evidências...');
    const photo=capPhoto();
    try {
        const r=await fetch('api_ponto.php',{method:'POST',headers:{'Content-Type':'application/json'},
            body:JSON.stringify({action:'register_punch',type,latitude:curLat,longitude:curLng,accuracy:curAcc,address:curAddr,photo,isFallback:isManual,facialUsed:facialOn&&!isManual})});
        const d=await r.json(); hideLd();
        if(d.success){
            if(d.status==='Ocorrencia') alert('⚠ Ponto registrado com OCORRÊNCIA (fora do raio). RH notificado.');
            else showToast(type);
            setTimeout(()=>window.location.reload(),1500);
        } else alert('Erro: '+(d.error||'Tente novamente'));
    } catch(e){ hideLd(); alert('Erro de conexão.'); }
}

function showToast(type){
    const t=document.createElement('div');
    t.style.cssText='position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:#10b981;color:#fff;padding:14px 28px;border-radius:50px;font-weight:800;font-size:1rem;z-index:99999;box-shadow:0 8px 30px rgba(16,185,129,.4);';
    t.innerHTML='<i class="fa-solid fa-check-circle" style="margin-right:8px;"></i>'+type+' registrado!';
    document.body.appendChild(t); setTimeout(()=>t.remove(),2500);
}

function capPhoto(){
    try{
        if(!camStream||video.readyState<2) return '';
        const c=document.createElement('canvas'); c.width=video.videoWidth||320; c.height=video.videoHeight||240;
        c.getContext('2d').drawImage(video,0,0,c.width,c.height); return c.toDataURL('image/jpeg',.65);
    } catch(e){ return ''; }
}

// ──── MAPA ────
function initMap(){
    map=L.map('pontoMap',{zoomControl:true,attributionControl:false}).setView([-23.5505,-46.6333],13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(map);
    if(COMPANY&&COMPANY.latitude&&COMPANY.longitude){
        L.circle([COMPANY.latitude,COMPANY.longitude],{color:'#5B21B6',fillColor:'#5B21B6',fillOpacity:.12,radius:COMPANY.radius_meters||100}).addTo(map);
        L.marker([COMPANY.latitude,COMPANY.longitude],{icon:L.divIcon({className:'',html:'<div style="background:#5B21B6;color:#fff;border-radius:50%;width:30px;height:30px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(91,33,182,.5);"><i class="fa-solid fa-building" style="font-size:.8rem;"></i></div>',iconSize:[30,30],iconAnchor:[15,15]})}).addTo(map).bindPopup('📍 Sede da Empresa');
    }
}

// ──── GPS — APENAS LOCALIZAÇÃO REAL DO DISPOSITIVO ────
function getLocation(){
    document.getElementById('gpsSt').textContent='Solicitando permissão de localização do dispositivo...';
    if(!navigator.geolocation){ setGpsErr('Geolocalização não suportada neste navegador.'); return; }

    // 1ª tentativa: GPS de alta precisão
    navigator.geolocation.getCurrentPosition(
        pos=>onGPS(pos,'GPS do Dispositivo'),
        ()=>{
            document.getElementById('gpsSt').textContent='Alta precisão indisponível, tentando via Wi-Fi/Rede...';
            // 2ª tentativa: rede WiFi / celular (ainda localização REAL do dispositivo)
            navigator.geolocation.getCurrentPosition(
                pos=>onGPS(pos,'Wi-Fi / Rede Celular'),
                err=>setGpsErr(gpsErr(err)),
                {enableHighAccuracy:false,timeout:12000,maximumAge:30000}
            );
        },
        {enableHighAccuracy:true,timeout:15000,maximumAge:0}
    );

    // Monitoramento contínuo: atualiza se vier leitura mais precisa
    navigator.geolocation.watchPosition(
        pos=>onGPS(pos,'GPS ao Vivo ↻'),
        ()=>{},
        {enableHighAccuracy:true,maximumAge:0}
    );
}

function gpsErr(e){
    return e.code===1?'❌ Permissão negada. Clique no ícone de cadeado 🔒 na barra do navegador e permita a localização.':
           e.code===2?'❌ Posição indisponível. Verifique se o GPS do dispositivo está ativado.':
           e.code===3?'⏱ Tempo esgotado. Recarregue e permita a localização.':'❌ Erro ao obter localização.';
}

function setGpsErr(msg){
    document.getElementById('gpsAddr').textContent=msg;
    document.getElementById('gpsSt').textContent='Localização não disponível.';
    document.getElementById('gpsCrd').textContent='—';
    const b=document.getElementById('gpsAccBadge');
    b.textContent='Sem GPS'; b.style.cssText='font-size:.7rem;font-weight:700;padding:2px 10px;border-radius:20px;background:#fef2f2;color:#dc2626;border:1px solid #fca5a5';
}

function onGPS(pos,method){
    // Ignorar se leitura nova for menos precisa
    if(curAcc&&pos.coords.accuracy>=curAcc) return;
    curLat=pos.coords.latitude; curLng=pos.coords.longitude; curAcc=pos.coords.accuracy;
    const accM=Math.round(curAcc), accTxt='±'+accM+'m';
    const badge=document.getElementById('gpsAccBadge');
    badge.textContent=accTxt;
    badge.style.cssText='font-size:.7rem;font-weight:700;padding:2px 10px;border-radius:20px;background:'+(
        accM<30  ?'#ecfdf5;color:#059669;border:1px solid #6ee7b7':
        accM<150 ?'#fffbeb;color:#d97706;border:1px solid #fde68a':
                  '#fef2f2;color:#dc2626;border:1px solid #fca5a5');
    document.getElementById('gpsCrd').textContent=`Lat: ${curLat.toFixed(6)}, Lng: ${curLng.toFixed(6)} (${accTxt})`;
    document.getElementById('gpsSt').textContent=`${method} · ${new Date().toLocaleTimeString('pt-BR')}`;

    if(uMarker)    map.removeLayer(uMarker);
    if(accCircle)  map.removeLayer(accCircle);
    uMarker=L.marker([curLat,curLng],{icon:L.divIcon({className:'',html:'<div style="background:#10b981;color:#fff;border-radius:50%;width:34px;height:34px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 10px rgba(16,185,129,.6);border:3px solid #fff;"><i class="fa-solid fa-person" style="font-size:1rem;"></i></div>',iconSize:[34,34],iconAnchor:[17,17]})}).addTo(map).bindPopup('📍 Você está aqui').openPopup();
    accCircle=L.circle([curLat,curLng],{color:'#10b981',fillColor:'#10b981',fillOpacity:.06,radius:curAcc,weight:1}).addTo(map);
    map.setView([curLat,curLng],Math.max(16,map.getZoom()));

    if(COMPANY&&COMPANY.latitude&&COMPANY.longitude){
        const dist=map.distance([curLat,curLng],[COMPANY.latitude,COMPANY.longitude]);
        const ab=document.getElementById('gpsAddrBox');
        if(dist>(COMPANY.radius_meters||100)){ ab.style.background='#fef2f2'; ab.style.border='1px solid #fca5a5'; ab.querySelector('i').style.color='#ef4444'; }
        else { ab.style.background='#ecfdf5'; ab.style.border='1px solid #6ee7b7'; ab.querySelector('i').style.color='#10b981'; }
    }
    geocode(curLat,curLng);
}

async function geocode(lat,lng){
    document.getElementById('gpsAddr').textContent='Buscando endereço completo...';
    try {
        const r=await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`,{headers:{'Accept-Language':'pt-BR,pt'}});
        const d=await r.json();
        if(d && d.address){
            const a = d.address;
            const rua = a.road || a.pedestrian || a.street || '';
            const num = a.house_number ? `, ${a.house_number}` : '';
            const bairro = a.suburb || a.neighbourhood || a.city_district || '';
            const cidade = a.city || a.town || a.village || a.municipality || '';
            
            let parts = [];
            if(rua) parts.push(rua + num);
            if(bairro) parts.push(bairro);
            if(cidade) parts.push(cidade);
            
            curAddr = parts.length > 0 ? parts.join(' - ') : (d.display_name || `${lat.toFixed(6)}, ${lng.toFixed(6)}`);
            document.getElementById('gpsAddr').textContent=curAddr;
        } else {
            curAddr=d&&d.display_name?d.display_name:`${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            document.getElementById('gpsAddr').textContent=curAddr;
        }
    } catch(e){ curAddr=`${lat.toFixed(6)}, ${lng.toFixed(6)}`; document.getElementById('gpsAddr').textContent=curAddr; }
}

// ──── LOADING ────
function showLd(t,s){ document.getElementById('ldText').textContent=t; document.getElementById('ldSub').textContent=s; document.getElementById('ldOverlay').classList.add('show'); }
function hideLd(){ document.getElementById('ldOverlay').classList.remove('show'); }

// ──── INIT ────
document.addEventListener('DOMContentLoaded', async()=>{
    initMap();
    getLocation();
    await startCam();
    await loadModels();
    if(HAS_FACE) await loadDesc();
    if(facialOn&&modelsOk) startDetect();
    document.getElementById('faceLabel').textContent='● Ativo';
    document.getElementById('faceLabel').style.color='#10b981';
    updateBtns();
});
</script>
