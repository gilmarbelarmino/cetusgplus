<?php
// Carregar dados do usuário logado via PHP para injetar na página
$currentUser = getCurrentUser();
$compId = getCurrentUserCompanyId();

// Buscar nome, foto e dados do usuário
$stmt_u = $pdo->prepare("SELECT u.name, u.email, u.avatar_url, u.sector, rh.role_name 
    FROM users u 
    LEFT JOIN rh_employee_details rh ON CONVERT(u.id USING utf8mb4) = CONVERT(rh.user_id USING utf8mb4)
    WHERE u.id = ?");
$stmt_u->execute([$currentUser['id']]);
$userData = $stmt_u->fetch(PDO::FETCH_ASSOC);

// Garantir tabelas necessárias
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_face_descriptors (
        user_id VARCHAR(50) PRIMARY KEY,
        descriptor LONGTEXT NOT NULL,
        photo_base64 LONGTEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    try { $pdo->exec("ALTER TABLE user_face_descriptors ADD COLUMN photo_base64 LONGTEXT"); } catch(Exception $e){}
} catch(Exception $e) {}

// Verificar se já tem face cadastrada
$stmt_f = $pdo->prepare("SELECT descriptor, photo_base64 FROM user_face_descriptors WHERE user_id = ?");
$stmt_f->execute([$currentUser['id']]);
$faceData = $stmt_f->fetch(PDO::FETCH_ASSOC);
$hasFace = !empty($faceData['descriptor']);

// Buscar registros do dia
$stmt_r = $pdo->prepare("SELECT record_type, record_time, address, status, latitude, longitude FROM time_records WHERE user_id = ? AND company_id = ? AND DATE(record_time) = CURDATE() ORDER BY record_time ASC");
$stmt_r->execute([$currentUser['id'], $compId]);
$todayRecords = $stmt_r->fetchAll(PDO::FETCH_ASSOC);

// Buscar configurações da empresa
$stmt_c = $pdo->prepare("SELECT latitude, longitude, radius_meters, allow_remote_work FROM company_settings WHERE id = ?");
$stmt_c->execute([$compId]);
$companySettings = $stmt_c->fetch(PDO::FETCH_ASSOC);
?>

<!-- DEPENDÊNCIAS EXTERNAS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

<style>
/* ========================= PONTO ELETRÔNICO STYLES ========================= */
.ponto-wrapper {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 20px;
    max-width: 1280px;
    margin: 0 auto;
}
@media (max-width: 1024px) {
    .ponto-wrapper { grid-template-columns: 1fr; }
}

/* -- Card genérico de seção -- */
.ponto-card {
    background: var(--bg-card, #fff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 16px;
}
.ponto-card-title {
    font-size: 0.75rem;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: var(--text-soft, #64748b);
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.ponto-card-title i { color: var(--crm-purple, #5B21B6); font-size: 0.85rem; }

/* -- Perfil do usuário -- */
.user-profile-bar {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 20px;
    background: linear-gradient(135deg, rgba(91,33,182,0.08), rgba(91,33,182,0.03));
    border: 1px solid rgba(91,33,182,0.15);
    border-radius: 14px;
    margin-bottom: 16px;
}
.user-profile-avatar {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    object-fit: cover;
    border: 2px solid rgba(91,33,182,0.25);
    flex-shrink: 0;
}
.user-profile-initials {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    background: linear-gradient(135deg, #5B21B6, #7C3AED);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 900;
    font-size: 1.2rem;
    flex-shrink: 0;
}
.user-profile-info { flex: 1; }
.user-profile-name { font-weight: 900; font-size: 1rem; color: var(--crm-black, #0f172a); }
.user-profile-role { font-size: 0.78rem; color: var(--text-soft, #64748b); margin-top: 2px; }
.user-profile-face-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 700;
}
.face-ok { background: #ecfdf5; color: #059669; border: 1px solid #6ee7b7; }
.face-no { background: #fef3c7; color: #d97706; border: 1px solid #fde68a; }

/* -- Câmera -- */
.camera-box {
    position: relative;
    width: 100%;
    aspect-ratio: 4/3;
    background: #0f172a;
    border-radius: 14px;
    overflow: hidden;
    margin-bottom: 12px;
}
.camera-box video {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
.camera-box canvas {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
}
.camera-status-bar {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 10px 14px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.8rem;
    font-weight: 700;
    color: #fff;
    background: linear-gradient(transparent, rgba(0,0,0,0.75));
    backdrop-filter: blur(4px);
}
.cam-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
    animation: pulse-dot 1.5s infinite;
}
@keyframes pulse-dot {
    0%,100% { opacity: 1; }
    50% { opacity: 0.3; }
}
.cam-dot.red { background: #ef4444; }
.cam-dot.green { background: #10b981; }
.cam-dot.yellow { background: #f59e0b; }
.cam-dot.blue { background: #3b82f6; }

/* -- Toggle Facial -- */
.toggle-facial-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    margin-bottom: 12px;
}
.toggle-switch {
    position: relative;
    width: 46px;
    height: 26px;
    flex-shrink: 0;
}
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
    position: absolute;
    inset: 0;
    background: #cbd5e1;
    border-radius: 26px;
    cursor: pointer;
    transition: 0.3s;
}
.toggle-slider:before {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    left: 3px;
    top: 3px;
    background: white;
    border-radius: 50%;
    transition: 0.3s;
    box-shadow: 0 1px 4px rgba(0,0,0,0.2);
}
.toggle-switch input:checked + .toggle-slider { background: #5B21B6; }
.toggle-switch input:checked + .toggle-slider:before { transform: translateX(20px); }

/* -- Botões de Ação do Ponto -- */
.punch-buttons-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 12px;
}
.punch-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 16px 10px;
    border-radius: 12px;
    border: 2px solid transparent;
    cursor: pointer;
    font-weight: 800;
    font-size: 0.82rem;
    transition: all 0.2s;
    background: #f8fafc;
    color: #334155;
}
.punch-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
.punch-btn i { font-size: 1.5rem; }
.punch-btn.entrada { border-color: #10b981; color: #059669; background: #ecfdf5; }
.punch-btn.entrada:hover { background: #10b981; color: white; }
.punch-btn.saida-almoco { border-color: #f59e0b; color: #d97706; background: #fffbeb; }
.punch-btn.saida-almoco:hover { background: #f59e0b; color: white; }
.punch-btn.retorno-almoco { border-color: #3b82f6; color: #2563eb; background: #eff6ff; }
.punch-btn.retorno-almoco:hover { background: #3b82f6; color: white; }
.punch-btn.saida { border-color: #ef4444; color: #dc2626; background: #fef2f2; }
.punch-btn.saida:hover { background: #ef4444; color: white; }
.punch-btn.pausa { border-color: #8b5cf6; color: #7c3aed; background: #f5f3ff; grid-column: span 2; flex-direction: row; padding: 12px 16px; }

/* -- Botão de Cadastrar Face -- */
.register-face-card {
    border: 2px dashed #f59e0b;
    background: #fffbeb;
    border-radius: 14px;
    padding: 20px;
    text-align: center;
    margin-bottom: 12px;
}
.register-face-photo {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    object-fit: cover;
    margin: 0 auto 10px;
    display: block;
    border: 3px solid #f59e0b;
}

/* -- Histórico -- */
.record-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    margin-bottom: 8px;
}
.record-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
}
.record-type { font-weight: 800; font-size: 0.88rem; flex: 1; }
.record-time { font-family: monospace; font-size: 0.88rem; color: #64748b; }
.record-status { font-size: 0.7rem; font-weight: 700; }

/* -- Mapa -- */
#pontoMap {
    width: 100%;
    height: 260px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    z-index: 1;
}
.gps-info-line {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 0.8rem;
    color: #475569;
    padding: 8px 12px;
    background: #f8fafc;
    border-radius: 8px;
    margin-top: 8px;
    border: 1px solid #e2e8f0;
}
.gps-info-line i { color: #5B21B6; margin-top: 1px; flex-shrink: 0; }

/* -- Spinner de carregamento -- */
.loading-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,0.6);
    z-index: 99999;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(4px);
}
.loading-overlay.show { display: flex; }
.loading-box {
    background: white;
    border-radius: 20px;
    padding: 30px 40px;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
.spinner {
    width: 48px;
    height: 48px;
    border: 4px solid #e2e8f0;
    border-top-color: #5B21B6;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 16px;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* -- Modal manual -- */
.modal-ponto {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15,23,42,0.65);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    padding: 20px;
    backdrop-filter: blur(6px);
}
.modal-ponto.show { display: flex; }
.modal-ponto-box {
    background: white;
    border-radius: 20px;
    padding: 28px;
    max-width: 420px;
    width: 100%;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
.modal-ponto-title {
    font-size: 1.1rem;
    font-weight: 900;
    color: #0f172a;
    margin-bottom: 6px;
}
.modal-ponto-sub {
    font-size: 0.82rem;
    color: #64748b;
    margin-bottom: 20px;
    line-height: 1.5;
}
</style>

<!-- SPINNER GLOBAL -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-box">
        <div class="spinner"></div>
        <div style="font-weight:800;color:#1e293b;" id="loadingText">Processando...</div>
        <div style="font-size:0.8rem;color:#64748b;margin-top:6px;" id="loadingSubText">Aguarde</div>
    </div>
</div>

<!-- MODAL PONTO MANUAL -->
<div class="modal-ponto" id="manualModal">
    <div class="modal-ponto-box">
        <div class="modal-ponto-title"><i class="fa-solid fa-hand-pointer" style="color:#5B21B6;margin-right:8px;"></i> Registro Manual</div>
        <div class="modal-ponto-sub">Selecione o tipo de registro. Sua foto, localização e IP serão capturados como evidência.</div>
        
        <div class="punch-buttons-grid" style="margin-bottom:16px;">
            <button class="punch-btn entrada" onclick="executePunch('Entrada', true)">
                <i class="fa-solid fa-arrow-right-to-bracket"></i>Entrada
            </button>
            <button class="punch-btn saida-almoco" onclick="executePunch('Saida Almoco', true)">
                <i class="fa-solid fa-utensils"></i>Saída Almoço
            </button>
            <button class="punch-btn retorno-almoco" onclick="executePunch('Retorno Almoco', true)">
                <i class="fa-solid fa-arrow-rotate-left"></i>Retorno Almoço
            </button>
            <button class="punch-btn saida" onclick="executePunch('Saida', true)">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>Saída Final
            </button>
            <button class="punch-btn pausa" onclick="executePunch('Pausa', true)">
                <i class="fa-solid fa-pause"></i> Pausa Extra
            </button>
        </div>

        <button onclick="document.getElementById('manualModal').classList.remove('show')" style="width:100%;padding:12px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;cursor:pointer;font-weight:700;color:#64748b;">
            Cancelar
        </button>
    </div>
</div>

<!-- CABEÇALHO DA PÁGINA -->
<div class="page-header" style="margin-bottom:16px;">
    <div class="page-header-info">
        <div class="page-header-icon"><i class="fa-solid fa-clock"></i></div>
        <div class="page-header-text">
            <h2>Ponto Eletrônico</h2>
            <p id="currentDateTime">Carregando data/hora...</p>
        </div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <!-- Botão Manual sempre visível -->
        <button onclick="document.getElementById('manualModal').classList.add('show')" 
                title="Registrar Ponto Manual (sem câmera)"
                style="display:flex;align-items:center;gap:8px;padding:10px 16px;border-radius:10px;border:1px solid #e2e8f0;background:#fff;cursor:pointer;font-weight:700;font-size:0.85rem;color:#475569;transition:all 0.2s;">
            <i class="fa-solid fa-hand-pointer" style="color:#5B21B6;"></i>
            Registro Manual
        </button>
    </div>
</div>

<!-- BARRA DO PERFIL DO USUÁRIO -->
<div class="user-profile-bar">
    <?php if (!empty($userData['avatar_url'])): ?>
        <img src="<?= htmlspecialchars($userData['avatar_url']) ?>" class="user-profile-avatar" alt="Foto">
    <?php else: ?>
        <div class="user-profile-initials"><?= strtoupper(substr($userData['name'] ?? 'U', 0, 2)) ?></div>
    <?php endif; ?>
    <div class="user-profile-info">
        <div class="user-profile-name"><?= htmlspecialchars($userData['name'] ?? 'Usuário') ?></div>
        <div class="user-profile-role">
            <?= htmlspecialchars($userData['role_name'] ?? 'Cargo não definido') ?> 
            <?php if(!empty($userData['sector'])): ?> · <?= htmlspecialchars($userData['sector']) ?><?php endif; ?>
        </div>
    </div>
    <span class="user-profile-face-badge <?= $hasFace ? 'face-ok' : 'face-no' ?>">
        <i class="fa-solid <?= $hasFace ? 'fa-face-smile' : 'fa-face-meh' ?>"></i>
        <?= $hasFace ? 'Face Cadastrada' : 'Sem Face Cadastrada' ?>
    </span>
</div>

<!-- LAYOUT PRINCIPAL -->
<div class="ponto-wrapper">

    <!-- COLUNA ESQUERDA: CÂMERA + MAPA -->
    <div>

        <!-- === PAINEL DE CADASTRO DE FACE (se não tem) === -->
        <?php if (!$hasFace): ?>
        <div class="register-face-card" id="registerFaceCard">
            <i class="fa-solid fa-camera-retro" style="font-size:2.5rem;color:#f59e0b;margin-bottom:12px;display:block;"></i>
            <h3 style="font-size:1rem;font-weight:900;color:#92400e;margin-bottom:6px;">Cadastro de Face Necessário</h3>
            <p style="font-size:0.83rem;color:#b45309;margin-bottom:16px;line-height:1.5;">
                Para bater o ponto com reconhecimento facial, precisamos fotografar e mapear seu rosto.<br>
                <strong>Olhe diretamente para a câmera</strong> com boa iluminação e clique em <strong>"Cadastrar Minha Face"</strong>.
            </p>
            <button onclick="registerFaceClick()" 
                    style="background:#f59e0b;color:white;border:none;border-radius:10px;padding:12px 24px;font-weight:800;font-size:0.9rem;cursor:pointer;transition:all 0.2s;"
                    onmouseover="this.style.background='#d97706'" onmouseout="this.style.background='#f59e0b'">
                <i class="fa-solid fa-face-grin-beam"></i> Cadastrar Minha Face Agora
            </button>
        </div>
        <?php else: ?>
        <!-- Face já cadastrada: mostrar preview -->
        <div class="ponto-card" style="display:flex;align-items:center;gap:14px;padding:14px 20px;background:linear-gradient(135deg,#ecfdf5,#f0fdf4);border-color:#6ee7b7;" id="faceRegisteredCard">
            <?php if(!empty($faceData['photo_base64'])): ?>
                <img src="<?= $faceData['photo_base64'] ?>" style="width:50px;height:50px;border-radius:50%;object-fit:cover;border:2px solid #10b981;" alt="Face">
            <?php else: ?>
                <div style="width:50px;height:50px;border-radius:50%;background:#10b981;display:flex;align-items:center;justify-content:center;"><i class="fa-solid fa-check" style="color:white;font-size:1.3rem;"></i></div>
            <?php endif; ?>
            <div style="flex:1;">
                <div style="font-weight:900;color:#065f46;font-size:0.9rem;">Face Cadastrada com Sucesso ✓</div>
                <div style="font-size:0.78rem;color:#047857;">Reconhecimento facial ativo. Fique na frente da câmera para autenticar.</div>
            </div>
            <button onclick="registerFaceClick()" title="Recadastrar face" style="background:none;border:1px solid #10b981;color:#059669;border-radius:8px;padding:6px 12px;cursor:pointer;font-size:0.75rem;font-weight:700;">
                <i class="fa-solid fa-arrows-rotate"></i> Atualizar
            </button>
        </div>
        <?php endif; ?>

        <!-- === CÂMERA === -->
        <div class="ponto-card" style="padding:16px;">
            <div class="ponto-card-title">
                <i class="fa-solid fa-video"></i>
                Câmera ao Vivo
                <span id="faceToggleLabel" style="margin-left:auto;font-size:0.72rem;color:#5B21B6;">Carregando...</span>
            </div>
            
            <!-- Toggle Facial -->
            <div class="toggle-facial-bar">
                <div>
                    <div style="font-weight:700;font-size:0.88rem;color:#334155;">
                        <i class="fa-solid fa-face-viewfinder" style="color:#5B21B6;margin-right:6px;"></i>
                        Reconhecimento Facial
                    </div>
                    <div style="font-size:0.75rem;color:#94a3b8;margin-top:2px;">Ativa análise facial em tempo real</div>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" id="facialToggle" checked onchange="toggleFacialRecognition(this.checked)">
                    <span class="toggle-slider"></span>
                </label>
            </div>

            <!-- Video -->
            <div class="camera-box">
                <video id="videoEl" autoplay muted playsinline></video>
                <canvas id="overlayCanvas"></canvas>
                <div class="camera-status-bar">
                    <div class="cam-dot blue" id="camDot"></div>
                    <span id="camStatusText">Iniciando câmera...</span>
                </div>
            </div>

            <!-- Status de autenticação facial -->
            <div id="faceAuthStatus" style="display:none;padding:10px 14px;border-radius:10px;margin-top:8px;font-weight:700;font-size:0.85rem;text-align:center;transition:all 0.3s;">
            </div>
        </div>

        <!-- === MAPA GPS === -->
        <div class="ponto-card" style="padding:16px;">
            <div class="ponto-card-title">
                <i class="fa-solid fa-map-location-dot"></i>
                Localização GPS
                <span id="gpsAccuracyBadge" style="margin-left:auto;"></span>
            </div>
            <div id="pontoMap"></div>
            <div class="gps-info-line" id="gpsAddressBox">
                <i class="fa-solid fa-location-dot"></i>
                <span id="gpsAddressText">Obtendo localização precisa...</span>
            </div>
            <div class="gps-info-line" style="margin-top:4px;" id="gpsCoordBox">
                <i class="fa-solid fa-satellite-dish"></i>
                <span id="gpsCoordsText">Aguardando GPS do dispositivo...</span>
            </div>
            <div class="gps-info-line" style="margin-top:4px;" id="gpsStatusBox">
                <i class="fa-solid fa-wifi"></i>
                <span id="gpsStatusText">Detectando método de localização...</span>
            </div>
        </div>
    </div>

    <!-- COLUNA DIREITA: BOTÕES + HISTÓRICO -->
    <div>

        <!-- === BOTÕES DE PONTO === -->
        <div class="ponto-card">
            <div class="ponto-card-title">
                <i class="fa-solid fa-clock-rotate-left"></i>
                Bater Ponto
            </div>

            <!-- Aviso quando facial ativo mas não autenticado -->
            <div id="faceWarningBanner" style="display:none;background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:10px 12px;margin-bottom:12px;font-size:0.8rem;font-weight:700;color:#92400e;">
                <i class="fa-solid fa-triangle-exclamation" style="color:#f59e0b;"></i>
                Aguardando autenticação facial. Posicione seu rosto na câmera.
                <br><span style="font-weight:400;margin-top:4px;display:block;">Ou desative o reconhecimento facial para registro manual.</span>
            </div>

            <div class="punch-buttons-grid" id="punchButtonsGrid">
                <button class="punch-btn entrada" id="btnEntrada" onclick="handlePunchClick('Entrada')">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i>
                    Entrada
                </button>
                <button class="punch-btn saida-almoco" id="btnSaidaAlmoco" onclick="handlePunchClick('Saida Almoco')">
                    <i class="fa-solid fa-utensils"></i>
                    Saída Almoço
                </button>
                <button class="punch-btn retorno-almoco" id="btnRetornoAlmoco" onclick="handlePunchClick('Retorno Almoco')">
                    <i class="fa-solid fa-arrow-rotate-left"></i>
                    Retorno Almoço
                </button>
                <button class="punch-btn saida" id="btnSaida" onclick="handlePunchClick('Saida')">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    Saída Final
                </button>
                <button class="punch-btn pausa" id="btnPausa" onclick="handlePunchClick('Pausa')">
                    <i class="fa-solid fa-pause"></i>
                    Pausa Extra
                </button>
            </div>

            <div style="text-align:center;margin-top:8px;">
                <button onclick="document.getElementById('manualModal').classList.add('show')"
                        style="background:none;border:none;cursor:pointer;color:#94a3b8;font-size:0.78rem;font-weight:600;text-decoration:underline;">
                    <i class="fa-solid fa-hand-pointer"></i> Preferir registro manual sem câmera
                </button>
            </div>
        </div>

        <!-- === HISTÓRICO DO DIA === -->
        <div class="ponto-card">
            <div class="ponto-card-title">
                <i class="fa-solid fa-list-check"></i>
                Registros de Hoje
                <span style="margin-left:auto;background:#f1f5f9;border-radius:20px;padding:2px 10px;font-size:0.7rem;font-weight:800;color:#5B21B6;">
                    <?= date('d/m/Y') ?>
                </span>
            </div>

            <div id="todayRecordsList">
                <?php if (empty($todayRecords)): ?>
                    <div style="text-align:center;padding:30px;color:#94a3b8;">
                        <i class="fa-solid fa-clock" style="font-size:2.5rem;color:#e2e8f0;display:block;margin-bottom:10px;"></i>
                        <div style="font-weight:700;">Nenhum registro hoje</div>
                        <div style="font-size:0.78rem;margin-top:4px;">Bata o ponto para iniciar o dia.</div>
                    </div>
                <?php else: ?>
                    <?php
                    $colorMap = ['Entrada'=>'#10b981','Saida Almoco'=>'#f59e0b','Retorno Almoco'=>'#3b82f6','Saida'=>'#ef4444','Pausa'=>'#8b5cf6'];
                    $iconMap  = ['Entrada'=>'fa-arrow-right-to-bracket','Saida Almoco'=>'fa-utensils','Retorno Almoco'=>'fa-arrow-rotate-left','Saida'=>'fa-arrow-right-from-bracket','Pausa'=>'fa-pause'];
                    foreach ($todayRecords as $rec):
                        $color = $colorMap[$rec['record_type']] ?? '#64748b';
                        $icon  = $iconMap[$rec['record_type']] ?? 'fa-clock';
                        $time  = date('H:i', strtotime($rec['record_time']));
                        $st    = $rec['status'] === 'Ocorrencia' ? '⚠ Ocorrência' : '';
                    ?>
                    <div class="record-item">
                        <i class="fa-solid <?= $icon ?>" style="color:<?= $color ?>;font-size:1.1rem;width:20px;text-align:center;"></i>
                        <div class="record-type"><?= htmlspecialchars($rec['record_type']) ?></div>
                        <?php if($st): ?><span style="font-size:0.7rem;font-weight:700;color:#ef4444;"><?= $st ?></span><?php endif; ?>
                        <div class="record-time"><?= $time ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- === INFORMAÇÕES EXTRAS === -->
        <div class="ponto-card" style="font-size:0.78rem;color:#64748b;line-height:1.8;">
            <div class="ponto-card-title"><i class="fa-solid fa-shield-halved"></i> Dados Capturados no Registro</div>
            <div><i class="fa-solid fa-check" style="color:#10b981;margin-right:6px;"></i>Data e Hora exata do servidor</div>
            <div><i class="fa-solid fa-check" style="color:#10b981;margin-right:6px;"></i>Coordenadas GPS (Lat/Lng)</div>
            <div><i class="fa-solid fa-check" style="color:#10b981;margin-right:6px;"></i>Endereço completo via OpenStreetMap</div>
            <div><i class="fa-solid fa-check" style="color:#10b981;margin-right:6px;"></i>Foto facial como evidência</div>
            <div><i class="fa-solid fa-check" style="color:#10b981;margin-right:6px;"></i>Endereço IP da conexão</div>
            <div><i class="fa-solid fa-check" style="color:#10b981;margin-right:6px;"></i>Navegador e dispositivo</div>
            <div><i class="fa-solid fa-check" style="color:#10b981;margin-right:6px;"></i>Precisão GPS em metros</div>
        </div>

    </div>
</div>

<script>
// ========== DADOS DO PHP ==========
const USER_ID = "<?= $currentUser['id'] ?>";
const HAS_FACE = <?= $hasFace ? 'true' : 'false' ?>;
const COMPANY = <?= json_encode($companySettings ?: []) ?>;

// ========== ESTADO GLOBAL ==========
let currentLat = null, currentLng = null, currentAddress = '', gpsAccuracy = null;
let map, userMarker, companyCircle;
let modelsLoaded = false;
let facialActive = true;   // controlado pelo toggle
let isFaceAuthenticated = false;
let detectionInterval = null;

const video       = document.getElementById('videoEl');
const canvas      = document.getElementById('overlayCanvas');
const camDot      = document.getElementById('camDot');
const camStatus   = document.getElementById('camStatusText');
const faceAuthDiv = document.getElementById('faceAuthStatus');
const faceWarning = document.getElementById('faceWarningBanner');

// ========== RELÓGIO ==========
function updateClock() {
    const now = new Date();
    document.getElementById('currentDateTime').textContent =
        now.toLocaleDateString('pt-BR', {weekday:'long', day:'2-digit', month:'long', year:'numeric'}) +
        ' — ' + now.toLocaleTimeString('pt-BR');
}
setInterval(updateClock, 1000);
updateClock();

// ========== TOGGLE FACIAL ==========
function toggleFacialRecognition(enabled) {
    facialActive = enabled;
    const label = document.getElementById('faceToggleLabel');
    if (enabled) {
        label.textContent = '● Ativo';
        label.style.color = '#10b981';
        setCamDot('blue', 'Câmera ativa — analisando face...');
        startFaceDetection();
    } else {
        label.textContent = '○ Desativado';
        label.style.color = '#94a3b8';
        isFaceAuthenticated = false;
        clearFaceDetectionInterval();
        clearCanvas();
        setFaceAuthUI(null);
        faceWarning.style.display = 'none';
        setCamDot('yellow', 'Reconhecimento facial desativado');
    }
    updatePunchButtons();
}

// ========== CÂMERA ==========
async function startCamera() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } } });
        video.srcObject = stream;
        video.onplay = () => {
            setCamDot('blue', 'Câmera ativa — carregando IA...');
            if (facialActive) startFaceDetection();
        };
    } catch(e) {
        setCamDot('red', 'Câmera bloqueada — use registro manual');
        facialActive = false;
        document.getElementById('facialToggle').checked = false;
        toggleFacialRecognition(false);
    }
}

// ========== CARREGAR MODELOS FACE-API ==========
async function loadFaceModels() {
    try {
        const MODEL_URL = 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights';
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
        ]);
        modelsLoaded = true;
        setCamDot('green', 'IA carregada — câmera pronta');
        document.getElementById('faceToggleLabel').textContent = '● Ativo';
        document.getElementById('faceToggleLabel').style.color = '#10b981';
    } catch(e) {
        setCamDot('red', 'Erro ao carregar IA facial. Use o modo manual.');
        modelsLoaded = false;
    }
}

// ========== DETECÇÃO CONTÍNUA ==========
let savedDescriptor = null;

async function loadSavedDescriptor() {
    try {
        const res = await fetch('api_ponto.php?action=get_face_descriptor');
        const d = await res.json();
        if (d.success && d.descriptor) {
            savedDescriptor = new Float32Array(JSON.parse(d.descriptor));
        }
    } catch(e) {}
}

function startFaceDetection() {
    clearFaceDetectionInterval();
    if (!modelsLoaded) { setTimeout(startFaceDetection, 500); return; }

    detectionInterval = setInterval(async () => {
        if (!facialActive || !modelsLoaded || video.paused || video.readyState < 2) return;

        const dSize = { width: video.videoWidth || video.clientWidth, height: video.videoHeight || video.clientHeight };
        if (!dSize.width) return;
        faceapi.matchDimensions(canvas, dSize);

        const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 }))
                                        .withFaceLandmarks().withFaceDescriptor();

        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        if (!detection) {
            isFaceAuthenticated = false;
            setCamDot('yellow', 'Nenhum rosto detectado');
            setFaceAuthUI('none');
            updatePunchButtons();
            return;
        }

        const resized = faceapi.resizeResults(detection, dSize);

        if (!HAS_FACE || !savedDescriptor) {
            // Sem face cadastrada: amarelo
            drawBox(ctx, resized.detection.box, '#f59e0b', 'Cadastre sua face');
            setCamDot('yellow', 'Rosto detectado — cadastre sua face');
            setFaceAuthUI('pending');
            isFaceAuthenticated = false;
        } else {
            const dist = faceapi.euclideanDistance(detection.descriptor, savedDescriptor);
            if (dist < 0.5) {
                drawBox(ctx, resized.detection.box, '#10b981', '✓ Autenticado');
                setCamDot('green', 'Rosto autenticado! Pode bater o ponto.');
                setFaceAuthUI('ok');
                isFaceAuthenticated = true;
            } else {
                drawBox(ctx, resized.detection.box, '#ef4444', '✗ Não reconhecido');
                setCamDot('red', 'Rosto não reconhecido');
                setFaceAuthUI('fail');
                isFaceAuthenticated = false;
            }
        }
        updatePunchButtons();
    }, 600);
}

function clearFaceDetectionInterval() {
    if (detectionInterval) { clearInterval(detectionInterval); detectionInterval = null; }
}

function clearCanvas() {
    const ctx = canvas.getContext('2d');
    if(ctx) ctx.clearRect(0, 0, canvas.width, canvas.height);
}

function drawBox(ctx, box, color, label) {
    ctx.strokeStyle = color;
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.rect(box.x, box.y, box.width, box.height);
    ctx.stroke();

    ctx.fillStyle = color;
    ctx.font = 'bold 13px Inter, sans-serif';
    const tw = ctx.measureText(label).width;
    ctx.fillRect(box.x, box.y - 22, tw + 16, 22);
    ctx.fillStyle = 'white';
    ctx.fillText(label, box.x + 8, box.y - 6);
}

function setCamDot(color, text) {
    camDot.className = 'cam-dot ' + color;
    camStatus.textContent = text;
}

function setFaceAuthUI(state) {
    if (!state || state === 'none') {
        faceAuthDiv.style.display = 'none';
        faceWarning.style.display = facialActive && HAS_FACE ? 'block' : 'none';
        return;
    }
    faceAuthDiv.style.display = 'block';
    faceWarning.style.display = 'none';
    if (state === 'ok') {
        faceAuthDiv.style.background = '#ecfdf5';
        faceAuthDiv.style.color = '#059669';
        faceAuthDiv.style.border = '1px solid #6ee7b7';
        faceAuthDiv.innerHTML = '<i class="fa-solid fa-circle-check"></i> Rosto autenticado com sucesso! Clique no tipo de registro.';
    } else if (state === 'fail') {
        faceAuthDiv.style.background = '#fef2f2';
        faceAuthDiv.style.color = '#dc2626';
        faceAuthDiv.style.border = '1px solid #fca5a5';
        faceAuthDiv.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Rosto não reconhecido. Ajuste a iluminação ou recadastre sua face.';
    } else if (state === 'pending') {
        faceAuthDiv.style.background = '#fffbeb';
        faceAuthDiv.style.color = '#d97706';
        faceAuthDiv.style.border = '1px solid #fde68a';
        faceAuthDiv.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Cadastre sua face primeiro para usar o reconhecimento.';
    }
}

function updatePunchButtons() {
    const locked = facialActive && HAS_FACE && !isFaceAuthenticated;
    const btns = document.querySelectorAll('.punch-btn');
    btns.forEach(b => {
        b.style.opacity = locked ? '0.45' : '1';
        b.style.cursor  = locked ? 'not-allowed' : 'pointer';
    });
    faceWarning.style.display = (facialActive && HAS_FACE && !isFaceAuthenticated) ? 'block' : 'none';
}

// ========== CADASTRO DE FACE ==========
async function registerFaceClick() {
    if (!modelsLoaded) { alert('Aguarde o carregamento da câmera e da IA.'); return; }
    showLoading('Fotografando e mapeando rosto...', 'Olhe para a câmera com rosto centralizado');
    
    await new Promise(r => setTimeout(r, 800));

    const detection = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions())
                                    .withFaceLandmarks().withFaceDescriptor();
    hideLoading();

    if (!detection) {
        alert('Rosto não detectado. Olhe diretamente para a câmera com boa iluminação e tente novamente.');
        return;
    }

    const descriptor = Array.from(detection.descriptor);
    const photo = capturePhoto();

    showLoading('Salvando cadastro facial...', 'Quase pronto!');

    try {
        const res = await fetch('api_ponto.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'register_face', descriptor: JSON.stringify(descriptor), photo: photo })
        });
        const d = await res.json();
        hideLoading();
        if (d.success) {
            alert('✅ Face cadastrada com sucesso! O reconhecimento facial já está ativo.');
            window.location.reload();
        } else {
            alert('Erro ao salvar: ' + (d.error || 'Tente novamente'));
        }
    } catch(e) {
        hideLoading();
        alert('Erro de conexão. Tente novamente.');
    }
}

// ========== BATER PONTO ==========
function handlePunchClick(type) {
    if (facialActive && HAS_FACE && !isFaceAuthenticated) {
        faceAuthDiv.style.display = 'block';
        faceAuthDiv.style.background = '#fef2f2';
        faceAuthDiv.style.color = '#dc2626';
        faceAuthDiv.style.border = '1px solid #fca5a5';
        faceAuthDiv.innerHTML = '<i class="fa-solid fa-circle-xmark"></i> Autenticação facial necessária! Aguarde a caixa verde ou use o registro manual.';
        return;
    }
    executePunch(type, false);
}

async function executePunch(type, isManual = false) {
    document.getElementById('manualModal').classList.remove('show');

    if (!currentLat || !currentLng) {
        if (!confirm('GPS não obtido ainda. Deseja registrar sem localização precisa?')) return;
    }

    showLoading('Registrando ' + type + '...', 'Capturando evidências e salvando');

    const photo = capturePhoto();

    try {
        const res = await fetch('api_ponto.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'register_punch',
                type: type,
                latitude: currentLat,
                longitude: currentLng,
                accuracy: gpsAccuracy,
                address: currentAddress,
                photo: photo,
                isFallback: isManual,
                facialUsed: facialActive && !isManual
            })
        });
        const d = await res.json();
        hideLoading();

        if (d.success) {
            if (d.status === 'Ocorrencia') {
                alert('⚠ Ponto registrado, mas foi gerada uma OCORRÊNCIA (fora do raio permitido). O RH será notificado.');
            } else {
                showSuccessToast(type);
            }
            setTimeout(() => window.location.reload(), 1500);
        } else {
            alert('Erro: ' + (d.error || 'Tente novamente'));
        }
    } catch(e) {
        hideLoading();
        alert('Erro de conexão. Verifique o sistema e tente novamente.');
    }
}

function showSuccessToast(type) {
    const t = document.createElement('div');
    t.style.cssText = 'position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:#10b981;color:white;padding:14px 28px;border-radius:50px;font-weight:800;font-size:1rem;z-index:99999;box-shadow:0 8px 30px rgba(16,185,129,0.4);animation:fadeInUp 0.3s ease;';
    t.innerHTML = '<i class="fa-solid fa-check-circle" style="margin-right:8px;"></i> ' + type + ' registrado com sucesso!';
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 2500);
}

function capturePhoto() {
    try {
        const c = document.createElement('canvas');
        c.width = video.videoWidth || 320;
        c.height = video.videoHeight || 240;
        c.getContext('2d').drawImage(video, 0, 0, c.width, c.height);
        return c.toDataURL('image/jpeg', 0.65);
    } catch(e) { return ''; }
}

// ========== GEOLOCALIZAÇÃO AVANÇADA ==========
function initMap() {
    map = L.map('pontoMap', { zoomControl: true, attributionControl: false }).setView([-23.5505, -46.6333], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

    if (COMPANY && COMPANY.latitude && COMPANY.longitude) {
        companyCircle = L.circle([COMPANY.latitude, COMPANY.longitude], {
            color: '#5B21B6', fillColor: '#5B21B6', fillOpacity: 0.1,
            radius: COMPANY.radius_meters || 100
        }).addTo(map);
        L.marker([COMPANY.latitude, COMPANY.longitude], {
            icon: L.divIcon({ className:'', html:'<div style="background:#5B21B6;color:white;border-radius:50%;width:30px;height:30px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(91,33,182,0.5);"><i class="fa-solid fa-building" style="font-size:0.8rem;"></i></div>', iconSize:[30,30], iconAnchor:[15,15] })
        }).addTo(map).bindPopup('📍 Sede da Empresa');
    }
}

function getLocation() {
    document.getElementById('gpsStatusText').textContent = 'Tentando GPS de alta precisão...';
    
    if (!navigator.geolocation) {
        fallbackToIPLocation();
        return;
    }

    // Tentativa 1: Alta precisão (GPS do dispositivo)
    navigator.geolocation.getCurrentPosition(
        pos => onGPSSuccess(pos, 'GPS do Dispositivo'),
        err => {
            document.getElementById('gpsStatusText').textContent = 'GPS de alta precisão falhou, tentando rede...';
            // Tentativa 2: Baixa precisão (Wi-Fi/rede)
            navigator.geolocation.getCurrentPosition(
                pos => onGPSSuccess(pos, 'Wi-Fi / Rede'),
                () => fallbackToIPLocation(),
                { enableHighAccuracy: false, timeout: 8000, maximumAge: 60000 }
            );
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
}

function onGPSSuccess(position, method) {
    currentLat   = position.coords.latitude;
    currentLng   = position.coords.longitude;
    gpsAccuracy  = position.coords.accuracy;

    const accText = gpsAccuracy ? Math.round(gpsAccuracy) + 'm de precisão' : '';
    const badge   = document.getElementById('gpsAccuracyBadge');
    badge.textContent = accText;
    badge.style.cssText = 'font-size:0.7rem;font-weight:700;padding:2px 10px;border-radius:20px;background:' +
        (gpsAccuracy < 50 ? '#ecfdf5;color:#059669;border:1px solid #6ee7b7' : 
         gpsAccuracy < 200 ? '#fffbeb;color:#d97706;border:1px solid #fde68a' : 
         '#fef2f2;color:#dc2626;border:1px solid #fca5a5');

    document.getElementById('gpsCoordsText').textContent = `Lat: ${currentLat.toFixed(6)}, Lng: ${currentLng.toFixed(6)} (±${Math.round(gpsAccuracy || 0)}m)`;
    document.getElementById('gpsStatusText').textContent = `Método: ${method} — Precisão: ${accText}`;

    // Atualizar mapa
    if (userMarker) map.removeLayer(userMarker);
    userMarker = L.marker([currentLat, currentLng], {
        icon: L.divIcon({ className:'', html:'<div style="background:#10b981;color:white;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(16,185,129,0.5);border:2px solid white;"><i class="fa-solid fa-person" style="font-size:0.9rem;"></i></div>', iconSize:[32,32], iconAnchor:[16,16] })
    }).addTo(map).bindPopup('📍 Você está aqui').openPopup();
    
    map.setView([currentLat, currentLng], 17);

    // Adicionar círculo de precisão
    L.circle([currentLat, currentLng], { color:'#10b981', fillColor:'#10b981', fillOpacity:0.05, radius: gpsAccuracy || 20, weight:1 }).addTo(map);

    // Verificar raio da empresa
    if (COMPANY && COMPANY.latitude && COMPANY.longitude) {
        const dist = map.distance([currentLat, currentLng], [COMPANY.latitude, COMPANY.longitude]);
        const radius = COMPANY.radius_meters || 100;
        const box = document.getElementById('gpsAddressBox');
        if (dist > radius) {
            box.style.background = '#fef2f2';
            box.style.border = '1px solid #fca5a5';
            box.querySelector('i').style.color = '#ef4444';
        } else {
            box.style.background = '#ecfdf5';
            box.style.border = '1px solid #6ee7b7';
            box.querySelector('i').style.color = '#10b981';
        }
    }

    // Geocodificação reversa (endereço real)
    reverseGeocode(currentLat, currentLng);
}

async function reverseGeocode(lat, lng) {
    try {
        const r = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`, {
            headers: { 'Accept-Language': 'pt-BR,pt' }
        });
        const d = await r.json();
        if (d && d.display_name) {
            currentAddress = d.display_name;
            document.getElementById('gpsAddressText').textContent = currentAddress;
        }
    } catch(e) {
        document.getElementById('gpsAddressText').textContent = `Lat: ${lat.toFixed(5)}, Lng: ${lng.toFixed(5)}`;
        currentAddress = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    }
}

async function fallbackToIPLocation() {
    document.getElementById('gpsStatusText').textContent = 'GPS indisponível — usando localização por IP (menos precisa)';
    try {
        const r = await fetch('https://ipapi.co/json/');
        const d = await r.json();
        if (d.latitude && d.longitude) {
            currentLat = d.latitude;
            currentLng = d.longitude;
            currentAddress = [d.city, d.region, d.country_name].filter(Boolean).join(', ');
            gpsAccuracy = 5000; // estimativa

            document.getElementById('gpsCoordsText').textContent = `Lat: ${currentLat.toFixed(4)}, Lng: ${currentLng.toFixed(4)} (por IP — precisão baixa)`;
            document.getElementById('gpsAddressText').textContent = currentAddress;
            document.getElementById('gpsStatusText').textContent = '⚠ Localização por IP (±5km). Para precisão, ative o GPS do dispositivo.';
            document.getElementById('gpsAccuracyBadge').textContent = 'Via IP (impreciso)';
            document.getElementById('gpsAccuracyBadge').style.cssText = 'font-size:0.7rem;font-weight:700;padding:2px 10px;border-radius:20px;background:#fef2f2;color:#dc2626;border:1px solid #fca5a5';

            if (userMarker) map.removeLayer(userMarker);
            userMarker = L.marker([currentLat, currentLng]).addTo(map);
            map.setView([currentLat, currentLng], 10);
        }
    } catch(e) {
        document.getElementById('gpsAddressText').textContent = 'Localização não disponível.';
        document.getElementById('gpsStatusText').textContent = 'Nenhum método de localização funcionou.';
    }
}

// ========== LOADING ==========
function showLoading(text, sub) {
    document.getElementById('loadingText').textContent = text;
    document.getElementById('loadingSubText').textContent = sub;
    document.getElementById('loadingOverlay').classList.add('show');
}
function hideLoading() {
    document.getElementById('loadingOverlay').classList.remove('show');
}

// ========== INIT ==========
document.addEventListener('DOMContentLoaded', async () => {
    initMap();
    getLocation();
    await startCamera();
    await loadFaceModels();
    if (HAS_FACE) await loadSavedDescriptor();
    if (facialActive) startFaceDetection();
    document.getElementById('faceToggleLabel').textContent = '● Ativo';
    document.getElementById('faceToggleLabel').style.color = '#10b981';
    updatePunchButtons();
});
</script>
