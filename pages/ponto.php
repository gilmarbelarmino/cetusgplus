<?php
$currentUser = getCurrentUser();
$compId = getCurrentUserCompanyId();

$stmt_u = $pdo->prepare("SELECT u.name, u.email, u.avatar_url, u.sector, rh.role_name 
    FROM users u 
    LEFT JOIN rh_employee_details rh ON CONVERT(u.id USING utf8mb4) = CONVERT(rh.user_id USING utf8mb4)
    WHERE u.id = ?");
$stmt_u->execute([$currentUser['id']]);
$userData = $stmt_u->fetch(PDO::FETCH_ASSOC);

// Garantir que as tabelas existam no banco de dados da hospedagem web (Hostinger)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS time_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        company_id INT NOT NULL,
        record_type ENUM('Entrada','Saida Almoco','Retorno Almoco','Saida','Pausa') NOT NULL,
        record_time DATETIME NOT NULL,
        status ENUM('Aprovado','Pendente','Rejeitado','Ocorrencia') DEFAULT 'Pendente',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $colunas = [
        "latitude DECIMAL(10,8) DEFAULT NULL",
        "longitude DECIMAL(11,8) DEFAULT NULL",
        "address TEXT DEFAULT NULL",
        "ip_address VARCHAR(50) DEFAULT NULL",
        "device_info VARCHAR(255) DEFAULT NULL",
        "gps_accuracy FLOAT DEFAULT NULL",
        "photo_base64 LONGTEXT DEFAULT NULL",
        "confidence_score FLOAT DEFAULT NULL",
        "facial_used TINYINT(1) DEFAULT 0",
        "is_manual TINYINT(1) DEFAULT 0"
    ];
    foreach($colunas as $col) {
        try {
            $pdo->exec("ALTER TABLE time_records ADD COLUMN $col");
        } catch (Exception $e) { } // ignorar se a coluna ja existe (erro 1060)
    }

    $colunas_company = [
        "latitude DECIMAL(10,8) DEFAULT NULL",
        "longitude DECIMAL(11,8) DEFAULT NULL",
        "radius_meters INT DEFAULT 100",
        "allow_remote_work TINYINT(1) DEFAULT 0"
    ];
    foreach($colunas_company as $col) {
        try {
            $pdo->exec("ALTER TABLE company_settings ADD COLUMN $col");
        } catch (Exception $e) { } 
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS time_incidents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(50) NOT NULL,
        company_id INT NOT NULL,
        record_id INT DEFAULT NULL,
        incident_date DATE NOT NULL,
        incident_type ENUM('Atraso','Falta','Hora Extra','Saida Antecipada','Fraude','Fora do Raio','Outro') NOT NULL,
        description TEXT DEFAULT NULL,
        time_amount_minutes INT DEFAULT 0,
        status ENUM('Pendente','Justificado','Descontado') DEFAULT 'Pendente',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (record_id) REFERENCES time_records(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {}

$stmt_r = $pdo->prepare("SELECT record_type, record_time, address, status FROM time_records WHERE user_id = ? AND company_id = ? AND DATE(record_time) = CURDATE() ORDER BY record_time ASC");
$stmt_r->execute([$currentUser['id'], $compId]);
$todayRecords = $stmt_r->fetchAll(PDO::FETCH_ASSOC);

$stmt_c = $pdo->prepare("SELECT latitude, longitude, radius_meters, allow_remote_work FROM company_settings WHERE id = ?");
$stmt_c->execute([$compId]);
$companySettings = $stmt_c->fetch(PDO::FETCH_ASSOC);
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

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

/* Punch buttons */
.punch-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:12px; }
.punch-btn { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; padding:18px 10px; border-radius:12px; border:2px solid transparent; cursor:pointer; font-weight:800; font-size:.82rem; transition:all .2s; }
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

/* Registros */
.rec-item { display:flex; align-items:center; gap:12px; padding:10px 12px; border-radius:10px; background:#f8fafc; border:1px solid #e2e8f0; margin-bottom:8px; }
.rec-type { font-weight:800; font-size:.88rem; flex:1; }
.rec-time { font-family:monospace; font-size:.88rem; color:#64748b; }

/* Mapa */
#pontoMap { width:100%; height:320px; border-radius:12px; border:1px solid #e2e8f0; }
.gps-line { display:flex; align-items:flex-start; gap:8px; font-size:.8rem; color:#475569; padding:8px 12px; background:#f8fafc; border-radius:8px; margin-top:8px; border:1px solid #e2e8f0; }
.gps-line i { color:#5B21B6; margin-top:1px; flex-shrink:0; }

/* Loading */
.ld-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:99999; align-items:center; justify-content:center; backdrop-filter:blur(4px); }
.ld-overlay.show { display:flex; }
.ld-box { background:#fff; border-radius:20px; padding:30px 40px; text-align:center; box-shadow:0 20px 60px rgba(0,0,0,.3); }
.spinner { width:48px; height:48px; border:4px solid #e2e8f0; border-top-color:#5B21B6; border-radius:50%; animation:spin .8s linear infinite; margin:0 auto 16px; }
@keyframes spin { to{transform:rotate(360deg)} }
</style>

<!-- LOADING -->
<div class="ld-overlay" id="ldOverlay">
    <div class="ld-box">
        <div class="spinner"></div>
        <div style="font-weight:800;color:#1e293b;" id="ldText">Processando...</div>
        <div style="font-size:.8rem;color:#64748b;margin-top:6px;" id="ldSub">Aguarde</div>
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
</div>

<!-- LAYOUT -->
<div class="ponto-wrap">

    <!-- COLUNA ESQUERDA -->
    <div>
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
            <div class="pt-title"><i class="fa-solid fa-hand-pointer"></i> Registrar Horário</div>
            <div style="font-size:0.8rem;color:#64748b;margin-bottom:16px;line-height:1.5;">
                Sua localização atual será vinculada ao registro. Confirme o tipo da batida de ponto abaixo:
            </div>
            <div class="punch-grid" id="punchGrid">
                <button class="punch-btn entrada"  onclick="execPunch('Entrada')"><i class="fa-solid fa-arrow-right-to-bracket"></i>Entrada</button>
                <button class="punch-btn s-almoco" onclick="execPunch('Saida Almoco')"><i class="fa-solid fa-utensils"></i>Saída Almoço</button>
                <button class="punch-btn r-almoco" onclick="execPunch('Retorno Almoco')"><i class="fa-solid fa-arrow-rotate-left"></i>Retorno Almoço</button>
                <button class="punch-btn saida"    onclick="execPunch('Saida')"><i class="fa-solid fa-arrow-right-from-bracket"></i>Saída Final</button>
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
                $colorMap = ['Entrada'=>'#10b981','Saida Almoco'=>'#f59e0b','Retorno Almoco'=>'#3b82f6','Saida'=>'#ef4444'];
                $iconMap  = ['Entrada'=>'fa-arrow-right-to-bracket','Saida Almoco'=>'fa-utensils','Retorno Almoco'=>'fa-arrow-rotate-left','Saida'=>'fa-arrow-right-from-bracket'];
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
            <div><i class="fa-solid fa-check" style="color:#10b981;margin-right:6px;"></i>Data e Hora exata do Servidor</div>
            <div><i class="fa-solid fa-check" style="color:#10b981;margin-right:6px;"></i>Coordenadas GPS (Lat/Lng)</div>
            <div><i class="fa-solid fa-check" style="color:#10b981;margin-right:6px;"></i>Endereço via Mapa (O.S.M)</div>
            <div><i class="fa-solid fa-check" style="color:#10b981;margin-right:6px;"></i>Precisão do Satélite (Metros)</div>
            <div><i class="fa-solid fa-check" style="color:#10b981;margin-right:6px;"></i>IP e Dispositivo de Acesso</div>
        </div>
    </div>
</div>

<script>
// ──── DADOS PHP ────
const COMPANY = <?= json_encode($companySettings ?: []) ?>;

// ──── ESTADO ────
let curLat = null, curLng = null, curAddr = '', curAcc = null;
let map, uMarker, accCircle;

// ──── RELÓGIO ────
(function clock(){ 
    const n=new Date(); 
    document.getElementById('ptClock').textContent=n.toLocaleDateString('pt-BR',{weekday:'long',day:'2-digit',month:'long',year:'numeric'})+' — '+n.toLocaleTimeString('pt-BR'); 
    setTimeout(clock,1000); 
})();

// ──── PONTO ────
async function execPunch(type) {
    if(!curLat && !curLng) { 
        if(!confirm('GPS ainda não obtido com precisão. Registrar assim mesmo? Apenas o IP e Hora serão salvos.')) return; 
    }
    showLd('Registrando '+type+'...','Enviando dados de localização...');
    try {
        const r = await fetch('api_ponto.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'register_punch',
                type: type,
                latitude: curLat,
                longitude: curLng,
                accuracy: curAcc,
                address: curAddr
            })
        });
        const d = await r.json(); 
        hideLd();
        if(d.success) {
            if(d.status === 'Ocorrencia') alert('⚠ Ponto registrado com OCORRÊNCIA (fora do raio permitido). O RH foi notificado.');
            else showToast(type);
            setTimeout(() => window.location.reload(), 1500);
        } else {
            alert('Erro: ' + (d.error || 'Falha ao registrar, tente novamente'));
        }
    } catch(e) { 
        hideLd(); 
        alert('Erro de conexão com o servidor.'); 
    }
}

function showToast(type){
    const t=document.createElement('div');
    t.style.cssText='position:fixed;bottom:30px;left:50%;transform:translateX(-50%);background:#10b981;color:#fff;padding:14px 28px;border-radius:50px;font-weight:800;font-size:1rem;z-index:99999;box-shadow:0 8px 30px rgba(16,185,129,.4);';
    t.innerHTML='<i class="fa-solid fa-check-circle" style="margin-right:8px;"></i>'+type+' registrado com sucesso!';
    document.body.appendChild(t); setTimeout(()=>t.remove(),2500);
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
            // 2ª tentativa: rede WiFi / celular
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
document.addEventListener('DOMContentLoaded', ()=>{
    initMap();
    getLocation();
});
</script>
