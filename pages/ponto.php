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
        "is_manual TINYINT(1) DEFAULT 0",
        "justification TEXT DEFAULT NULL"
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
.ponto-wrap { max-width: 800px; margin: 0 auto; padding-bottom: 40px; font-family: 'Inter', sans-serif; }

/* Loading */
.ld-overlay { display:none; position:fixed; inset:0; background:rgba(15,23,42,.6); z-index:99999; align-items:center; justify-content:center; backdrop-filter:blur(4px); }
.ld-overlay.show { display:flex; }
.ld-box { background:#fff; border-radius:20px; padding:30px 40px; text-align:center; box-shadow:0 20px 60px rgba(0,0,0,.3); }
.spinner { width:48px; height:48px; border:4px solid #e2e8f0; border-top-color:#4cd5ed; border-radius:50%; animation:spin .8s linear infinite; margin:0 auto 16px; }
@keyframes spin { to{transform:rotate(360deg)} }

/* Map */
#pontoMap { width: 100%; height: 260px; background: #e2e8f0; border: none; }
@media (min-width: 768px) {
    #pontoMap { height: 450px; border-radius: 8px 8px 0 0; }
}

/* Info Card */
.info-card { background: #fff; text-align: center; padding: 20px 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
.info-date { font-size: 1.2rem; color: #475569; margin-bottom: 8px; }
.info-dist { font-size: 1.05rem; font-weight: 700; color: #64748b; margin-bottom: 8px; }
.info-addr { font-size: 0.85rem; color: #94a3b8; margin-bottom: 24px; line-height: 1.4; min-height: 38px; }

/* Justification */
.just-input { width: 100%; padding: 14px; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 0.95rem; margin-bottom: 16px; outline: none; transition: border-color 0.2s; resize: none; color: #475569; }
.just-input:focus { border-color: #4cd5ed; }
.just-input::placeholder { color: #94a3b8; }

/* Button */
.btn-incluir { width: 100%; background: #4cd5ed; color: #fff; padding: 16px; font-size: 1.1rem; font-weight: 600; border: none; border-radius: 4px; cursor: pointer; transition: background 0.2s; }
.btn-incluir:hover { background: #3bbed4; }

/* History */
.history-card { background: #fff; margin-top: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
.history-title { font-size: 1.15rem; font-weight: 700; color: #475569; padding: 18px 20px; border-bottom: 1px solid #f1f5f9; }
.rec-item { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid #f1f5f9; }
.rec-item:last-child { border-bottom: none; }
.rec-left { display: flex; align-items: center; gap: 14px; color: #475569; font-weight: 500; }
.rec-icon { color: #64748b; font-size: 1.2rem; }
.rec-right { display: flex; align-items: center; gap: 8px; font-size: 0.95rem; color: #64748b; }
.rec-status-icon { color: #22c55e; font-size: 1.2rem; }
.rec-status-icon.ocorrencia { color: #ef4444; }

/* Hide default page header if inside wrap */
.page-header { display: none; }
</style>

<!-- LOADING -->
<div class="ld-overlay" id="ldOverlay">
    <div class="ld-box">
        <div class="spinner"></div>
        <div style="font-weight:800;color:#1e293b;" id="ldText">Processando...</div>
        <div style="font-size:.8rem;color:#64748b;margin-top:6px;" id="ldSub">Aguarde</div>
    </div>
</div>

<!-- TOP BAR -->
<div style="background: #ffffff; text-align: center; padding: 16px 20px; margin-bottom: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
    <?php if (!empty($company['logo_url'])): ?>
        <img src="<?= htmlspecialchars($company['logo_url']) ?>" alt="<?= htmlspecialchars($company['company_name'] ?? 'Empresa') ?>" style="height: 40px; object-fit: contain;">
    <?php else: ?>
        <h2 style="margin:0; font-size:1.5rem; color:#1e293b; font-weight:800;"><?= htmlspecialchars($company['company_name'] ?? 'Empresa') ?></h2>
    <?php endif; ?>
</div>

<!-- LAYOUT -->
<div class="ponto-wrap">
    
    <!-- MAPA -->
    <div id="pontoMap"></div>
    <div id="gpsSt" style="display:none;"></div> <!-- hidden state -->

    <!-- INFO E BOTÃO -->
    <div class="info-card">
        <div class="info-date" id="ptClock">Carregando...</div>
        <div class="info-dist" id="gpsDistBadge">Calculando distância...</div>
        <div class="info-addr" id="gpsAddr">Aguardando GPS do dispositivo...</div>
        
        <textarea id="justification" class="just-input" rows="2" placeholder="Escreva o periodo"></textarea>
        
        <button class="btn-incluir" onclick="execPunch('Auto')">Incluir Ponto</button>
    </div>

    <!-- HISTÓRICO HOJE -->
    <div class="history-card">
        <div class="history-title">Últimos Registros</div>
        
        <?php if (empty($todayRecords)): ?>
            <div style="text-align:center;padding:30px;color:#94a3b8;">
                <div style="font-weight:500;">Nenhum registro hoje</div>
            </div>
        <?php else:
            // Reverse so newest is at top if needed, or keep order
            $reversedRecords = array_reverse($todayRecords);
            foreach ($reversedRecords as $rec):
                $time  = date('d/m - H:i', strtotime($rec['record_time']));
                $isOcorrencia = ($rec['status'] === 'Ocorrencia');
                $statusText = $isOcorrencia ? 'Ocorrência' : 'Aceita';
                $statusClass = $isOcorrencia ? 'ocorrencia' : '';
                $statusIcon = $isOcorrencia ? 'fa-thumbs-down' : 'fa-thumbs-up';
        ?>
            <div class="rec-item">
                <div class="rec-left">
                    <i class="fa-regular fa-file-lines rec-icon"></i>
                    <i class="fa-solid fa-location-dot rec-icon"></i>
                    <span><?= $time ?></span>
                </div>
                <div class="rec-right">
                    <span><?= $statusText ?></span>
                    <i class="fa-solid <?= $statusIcon ?> rec-status-icon <?= $statusClass ?>"></i>
                </div>
            </div>
        <?php endforeach; endif; ?>
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
                address: curAddr,
                justification: document.getElementById('justification') ? document.getElementById('justification').value : ''
            })
        });
        const d = await r.json(); 
        hideLd();
        if(d.success) {
            const proceedWithPonto = () => {
                if(d.status === 'Ocorrencia') alert('⚠ Ponto registrado com OCORRÊNCIA (fora do raio permitido). O RH foi notificado.');
                else showToast(type);
                setTimeout(() => window.location.reload(), 1500);
            };

            if(d.overtime_alert) {
                Swal.fire({
                    title: 'Aviso do RH',
                    text: d.overtime_alert,
                    icon: 'info',
                    confirmButtonText: 'Ciente',
                    confirmButtonColor: '#4cd5ed',
                    allowOutsideClick: false
                }).then(() => {
                    proceedWithPonto();
                });
            } else {
                proceedWithPonto();
            }
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
