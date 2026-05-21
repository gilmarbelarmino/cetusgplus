<div class="page-header">
    <h1 class="page-title"><i class="fa-solid fa-clock"></i> Ponto Eletrônico</h1>
</div>

<div class="card" style="max-width: 1200px; margin: 0 auto; display: flex; flex-wrap: wrap; gap: 20px;">
    <!-- Coluna Esquerda: Câmera e Mapa -->
    <div style="flex: 1; min-width: 300px; display: flex; flex-direction: column; gap: 20px;">
        <!-- Container Câmera -->
        <div style="position: relative; width: 100%; border-radius: 12px; overflow: hidden; background: #000; display: flex; justify-content: center; align-items: center; aspect-ratio: 4/3;">
            <video id="videoElement" autoplay muted playsinline style="width: 100%; height: 100%; object-fit: cover;"></video>
            <canvas id="overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;"></canvas>
            
            <div id="cameraStatus" style="position: absolute; bottom: 10px; left: 10px; background: rgba(0,0,0,0.7); color: white; padding: 5px 10px; border-radius: 5px; font-size: 0.8rem; z-index: 10;">Iniciando Câmera...</div>
        </div>

        <!-- Container Mapa Leaflet -->
        <div id="mapContainer" style="width: 100%; height: 250px; border-radius: 12px; border: 1px solid #e2e8f0; z-index: 1;"></div>
    </div>

    <!-- Coluna Direita: Controles e Histórico -->
    <div style="flex: 1; min-width: 300px; display: flex; flex-direction: column; gap: 20px;">
        
        <!-- Status da Localização -->
        <div id="locationStatus" style="padding: 15px; border-radius: 8px; background: #f8fafc; border: 1px solid #e2e8f0; font-size: 0.9rem;">
            <div style="font-weight: 700; color: #1e293b; margin-bottom: 5px;">📍 Localização Atual</div>
            <div id="addressText" style="color: #64748b;">Obtendo localização...</div>
        </div>

        <!-- Painel de Treinamento Facial (só aparece se não tem rosto salvo) -->
        <div id="setupFacePanel" style="display: none; padding: 15px; border-radius: 8px; background: #fffbeb; border: 1px solid #fde68a;">
            <h3 style="color: #d97706; margin-bottom: 10px; font-size: 1rem;"><i class="fa-solid fa-face-viewfinder"></i> Cadastro Facial Necessário</h3>
            <p style="font-size: 0.85rem; color: #b45309; margin-bottom: 15px;">Antes de bater o ponto, você precisa registrar sua face. Olhe para a câmera com boa iluminação.</p>
            <button class="btn-primary" onclick="registerBaseFace()" style="width: 100%;">Registrar Minha Face</button>
        </div>

        <!-- Botões de Ação do Ponto -->
        <div id="punchPanel" style="display: flex; flex-direction: column; gap: 10px; display: none;">
            <h3 style="font-size: 1rem; color: #1e293b;">Bater Ponto (Reconhecimento Facial)</h3>
            <p style="font-size: 0.8rem; color: #64748b; margin-bottom: 5px;">Olhe para a câmera até a caixa ficar verde e selecione a ação abaixo:</p>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                <button class="btn-primary" onclick="registerPunch('Entrada')" style="background: #10b981; border-color: #10b981;">Entrada</button>
                <button class="btn-primary" onclick="registerPunch('Saida Almoco')" style="background: #f59e0b; border-color: #f59e0b;">Saída Almoço</button>
                <button class="btn-primary" onclick="registerPunch('Retorno Almoco')" style="background: #3b82f6; border-color: #3b82f6;">Retorno Almoço</button>
                <button class="btn-primary" onclick="registerPunch('Saida')" style="background: #ef4444; border-color: #ef4444;">Saída Final</button>
                <button class="btn-secondary" onclick="registerPunch('Pausa')" style="grid-column: span 2;">Pausa Extra</button>
            </div>
            
            <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 10px 0;">
            <button class="btn-secondary" onclick="toggleFallbackModal()" style="font-size: 0.8rem;">Não consegue usar o facial? Bater por clique.</button>
        </div>

        <!-- Histórico do Dia -->
        <div style="background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; padding: 15px; flex: 1;">
            <h3 style="font-size: 1rem; color: #1e293b; margin-bottom: 10px;">Registros de Hoje</h3>
            <div id="todayRecords" style="display: flex; flex-direction: column; gap: 8px;">
                <!-- Via JS -->
            </div>
        </div>

    </div>
</div>

<!-- Modal Fallback -->
<div id="fallbackModal" class="modal" style="z-index: 20000;">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <h2 style="font-size: 1.25rem; margin-bottom: 10px;">Ponto por Clique</h2>
        <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 15px;">Ao registrar por clique, será anexada uma foto simples como evidência e um registro de auditoria poderá ser aberto.</p>
        <select id="fallbackType" style="width: 100%; padding: 10px; margin-bottom: 15px; border-radius: 5px; border: 1px solid #ccc;">
            <option value="Entrada">Entrada</option>
            <option value="Saida Almoco">Saída Almoço</option>
            <option value="Retorno Almoco">Retorno Almoço</option>
            <option value="Saida">Saída Final</option>
        </select>
        <div style="display: flex; gap: 10px;">
            <button class="btn-secondary" onclick="toggleFallbackModal()" style="flex: 1;">Cancelar</button>
            <button class="btn-primary" onclick="registerPunchFallback()" style="flex: 1;">Registrar</button>
        </div>
    </div>
</div>

<!-- Dependências: Leaflet & FaceAPI -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>

<script>
    // Variables
    let map, marker, circle;
    let currentLat = null, currentLng = null, currentAddress = '';
    let companySettings = null;
    let hasBaseFace = false;
    let baseFaceDescriptor = null;
    let modelsLoaded = false;
    let isFaceMatch = false;

    const video = document.getElementById('videoElement');
    const overlay = document.getElementById('overlay');
    const camStatus = document.getElementById('cameraStatus');

    document.addEventListener('DOMContentLoaded', async () => {
        await fetchInitData();
        initMap();
        getLocation();
        await loadFaceApiModels();
        startCamera();
    });

    async function fetchInitData() {
        try {
            const res = await fetch('api_ponto.php?action=init');
            const data = await res.json();
            if (data.success) {
                companySettings = data.company;
                hasBaseFace = data.has_face_registered;
                renderTodayRecords(data.records);

                if (!hasBaseFace) {
                    document.getElementById('setupFacePanel').style.display = 'block';
                    document.getElementById('punchPanel').style.display = 'none';
                } else {
                    document.getElementById('setupFacePanel').style.display = 'none';
                    document.getElementById('punchPanel').style.display = 'flex';
                    const r2 = await fetch('api_ponto.php?action=get_face_descriptor');
                    const d2 = await r2.json();
                    if (d2.success && d2.descriptor) {
                        baseFaceDescriptor = new Float32Array(JSON.parse(d2.descriptor));
                    }
                }
            }
        } catch(e) { console.error('Erro init API', e); }
    }

    function initMap() {
        map = L.map('mapContainer').setView([-23.5505, -46.6333], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
    }

    function getLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                async (position) => {
                    currentLat = position.coords.latitude;
                    currentLng = position.coords.longitude;
                    
                    map.setView([currentLat, currentLng], 17);
                    
                    if (marker) map.removeLayer(marker);
                    marker = L.marker([currentLat, currentLng]).addTo(map).bindPopup("Você está aqui").openPopup();

                    if (companySettings && companySettings.latitude && companySettings.longitude) {
                        if (circle) map.removeLayer(circle);
                        circle = L.circle([companySettings.latitude, companySettings.longitude], {
                            color: 'red',
                            fillColor: '#f03',
                            fillOpacity: 0.2,
                            radius: companySettings.radius_meters || 100
                        }).addTo(map);
                        
                        const dist = map.distance([currentLat, currentLng], [companySettings.latitude, companySettings.longitude]);
                        if (dist > (companySettings.radius_meters || 100)) {
                            document.getElementById('locationStatus').style.border = '1px solid #ef4444';
                            document.getElementById('locationStatus').style.background = '#fef2f2';
                        } else {
                            document.getElementById('locationStatus').style.border = '1px solid #10b981';
                            document.getElementById('locationStatus').style.background = '#ecfdf5';
                        }
                    }

                    try {
                        const r = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${currentLat}&lon=${currentLng}`);
                        const d = await r.json();
                        currentAddress = d.display_name;
                        document.getElementById('addressText').innerText = currentAddress;
                    } catch(e) { document.getElementById('addressText').innerText = `Lat: ${currentLat}, Lng: ${currentLng}`; }
                },
                (error) => {
                    document.getElementById('addressText').innerText = "Erro ao obter GPS. Permita no navegador.";
                },
                { enableHighAccuracy: true }
            );
        }
    }

    async function loadFaceApiModels() {
        camStatus.innerText = "Carregando IA Facial (Pode demorar um pouco)...";
        try {
            const MODEL_URL = 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/master/weights';
            await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);
            await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
            await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
            modelsLoaded = true;
            camStatus.innerText = "Modelos Carregados. Iniciando Câmera...";
        } catch(e) {
            console.error("Erro carregando modelos faceapi", e);
            camStatus.innerText = "Erro ao carregar IA. Use o clique manual.";
        }
    }

    async function startCamera() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" } });
            video.srcObject = stream;
            
            video.addEventListener('play', () => {
                camStatus.innerText = "Câmera Ativa. Analisando face...";
                const canvas = overlay;
                
                setInterval(async () => {
                    if (!modelsLoaded) return;
                    
                    const dSize = { width: video.videoWidth || video.clientWidth, height: video.videoHeight || video.clientHeight };
                    if(dSize.width === 0) return;
                    faceapi.matchDimensions(canvas, dSize);

                    const detections = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks().withFaceDescriptor();
                    
                    const ctx = canvas.getContext('2d');
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    
                    if (detections) {
                        const resizedDetections = faceapi.resizeResults(detections, dSize);
                        
                        let boxColor = 'rgba(239, 68, 68, 1)'; // Red
                        isFaceMatch = false;

                        if (hasBaseFace && baseFaceDescriptor) {
                            const distance = faceapi.euclideanDistance(detections.descriptor, baseFaceDescriptor);
                            if (distance < 0.5) {
                                boxColor = 'rgba(16, 185, 129, 1)'; // Green
                                isFaceMatch = true;
                                camStatus.innerText = "Rosto Autenticado! Pode bater o ponto.";
                                camStatus.style.background = 'rgba(16, 185, 129, 0.9)';
                            } else {
                                camStatus.innerText = "Rosto não reconhecido.";
                                camStatus.style.background = 'rgba(239, 68, 68, 0.9)';
                            }
                        } else {
                            boxColor = 'rgba(245, 158, 11, 1)'; // Yellow
                            camStatus.innerText = "Rosto Detectado. Aguardando registro base.";
                            camStatus.style.background = 'rgba(245, 158, 11, 0.9)';
                        }

                        const box = resizedDetections.detection.box;
                        const drawBox = new faceapi.draw.DrawBox(box, { label: isFaceMatch ? 'Usuário Valido' : 'Desconhecido', boxColor: boxColor });
                        drawBox.draw(canvas);
                    } else {
                        camStatus.innerText = "Nenhum rosto detectado.";
                        camStatus.style.background = 'rgba(0,0,0,0.7)';
                        isFaceMatch = false;
                    }
                }, 500);
            });
        } catch(e) {
            console.error("Camera error", e);
            camStatus.innerText = "Erro ao acessar câmera. Verifique permissões.";
        }
    }

    function capturePhoto() {
        const can = document.createElement('canvas');
        can.width = video.videoWidth;
        can.height = video.videoHeight;
        can.getContext('2d').drawImage(video, 0, 0, can.width, can.height);
        return can.toDataURL('image/jpeg', 0.7);
    }

    async function registerBaseFace() {
        if (!modelsLoaded) return alert("Aguarde o carregamento da Inteligência Artificial.");
        const detections = await faceapi.detectSingleFace(video, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks().withFaceDescriptor();
        if (!detections) return alert("Rosto não detectado. Olhe diretamente para a câmera com boa iluminação.");
        
        const descriptorArray = Array.from(detections.descriptor);
        camStatus.innerText = "Salvando rosto base...";
        const res = await fetch('api_ponto.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'register_face', descriptor: JSON.stringify(descriptorArray) })
        });
        
        const data = await res.json();
        if (data.success) {
            alert("Rosto cadastrado com sucesso! Agora você pode bater o ponto.");
            window.location.reload();
        } else {
            alert("Erro ao cadastrar: " + data.error);
        }
    }

    async function registerPunch(type) {
        if (!isFaceMatch) {
            alert("Autenticação Facial falhou. O sistema não reconheceu seu rosto, ou você está fora de foco. Aguarde a caixa verde.");
            return;
        }
        executePunch(type);
    }

    window.toggleFallbackModal = () => {
        document.getElementById('fallbackModal').classList.toggle('active');
    };

    async function registerPunchFallback() {
        const type = document.getElementById('fallbackType').value;
        toggleFallbackModal();
        executePunch(type, true);
    }

    async function executePunch(type, isFallback = false) {
        if (!currentLat || !currentLng) {
            alert("Aguardando Geolocalização. Verifique se o GPS está ativo.");
            return;
        }
        const photo = capturePhoto();
        camStatus.innerText = `Registrando ${type}...`;
        const res = await fetch('api_ponto.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'register_punch',
                type: type,
                latitude: currentLat,
                longitude: currentLng,
                address: currentAddress,
                photo: photo,
                isFallback: isFallback
            })
        });
        const data = await res.json();
        if (data.success) {
            if (data.status === 'Ocorrencia') {
                alert(`Ponto registrado, mas gerou uma Ocorrência (Ex: Fora do raio permitido). O RH será notificado.`);
            } else {
                alert(`Ponto (${type}) registrado com sucesso!`);
            }
            fetchInitData(); 
        } else {
            alert("Erro ao registrar: " + data.error);
        }
    }

    function renderTodayRecords(records) {
        const container = document.getElementById('todayRecords');
        container.innerHTML = '';
        if (!records || records.length === 0) {
            container.innerHTML = '<div style="color: #94a3b8; font-size: 0.85rem; text-align: center;">Nenhum registro hoje.</div>';
            return;
        }
        records.forEach(r => {
            const timeStr = new Date(r.record_time).toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'});
            let color = '#10b981';
            if (r.status === 'Ocorrencia') color = '#ef4444';
            if (r.status === 'Rejeitado') color = '#64748b';
            
            const div = document.createElement('div');
            div.style.display = 'flex';
            div.style.justifyContent = 'space-between';
            div.style.padding = '10px';
            div.style.background = '#fff';
            div.style.borderRadius = '6px';
            div.style.border = '1px solid #e2e8f0';
            
            div.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 10px; height: 10px; border-radius: 50%; background: ${color};"></div>
                    <span style="font-weight: 700; color: #1e293b; font-size: 0.9rem;">${r.record_type}</span>
                </div>
                <div style="color: #64748b; font-size: 0.85rem; font-family: monospace;">${timeStr}</div>
            `;
            container.appendChild(div);
        });
    }
</script>
