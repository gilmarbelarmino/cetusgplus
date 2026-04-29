<?php
/**
 * TECNOLOGIA VIEW
 */
?>
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>

<div class="page-header">
    <div class="page-header-info">
        <div class="page-header-icon">
            <i class="fa-solid fa-laptop-code"></i>
        </div>
        <div class="page-header-text">
            <h2>Gestão de TI</h2>
            <p>Monitoramento de ativos tecnológicos e infraestrutura digital.</p>
        </div>
    </div>
</div>

<div class="glass-panel" style="margin-bottom: 2rem; display: flex; gap: 1rem; padding: 0.75rem;">
    <button onclick="switchTab('cameras')" class="btn-secondary <?= $activeTab === 'cameras' ? 'active' : '' ?>" id="btn-cameras">Câmeras</button>
    <button onclick="switchTab('remotos')" class="btn-secondary <?= $activeTab === 'remotos' ? 'active' : '' ?>" id="btn-remotos">Acessos Remotos</button>
    <button onclick="switchTab('emails')" class="btn-secondary <?= $activeTab === 'emails' ? 'active' : '' ?>" id="btn-emails">E-mails</button>
    <button onclick="switchTab('anotacoes')" class="btn-secondary <?= $activeTab === 'anotacoes' ? 'active' : '' ?>" id="btn-anotacoes">Anotações</button>
</div>

<div id="tab-cameras" class="tab-content" style="<?= $activeTab === 'cameras' ? '' : 'display:none;' ?>">
    <div class="glass-panel">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3><i class="fa-solid fa-video"></i> Monitoramento CCTV</h3>
            <button class="btn-primary" onclick="document.getElementById('cameraModal').style.display='flex'">+ Nova Câmera</button>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>IP</th>
                        <th>DOC</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cameras as $cam): ?>
                    <tr>
                        <td style="font-weight: 700;"><?= htmlspecialchars($cam['name']) ?></td>
                        <td><code style="background: #f1f5f9; padding: 0.2rem 0.5rem; border-radius: 4px;"><?= htmlspecialchars($cam['ip_address']) ?></code></td>
                        <td><?= htmlspecialchars($cam['doc']) ?></td>
                        <td><button class="btn-icon"><i class="fa-solid fa-pen"></i></button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="tab-remotos" class="tab-content" style="<?= $activeTab === 'remotos' ? '' : 'display:none;' ?>">
    <div class="glass-panel">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3><i class="fa-solid fa-desktop"></i> Credenciais de Rede</h3>
            <button class="btn-primary" onclick="document.getElementById('remoteModal').style.display='flex'">+ Novo Acesso</button>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Senha PC</th>
                        <th>Senha Email</th>
                        <th>Estação</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($remotes as $rem): ?>
                    <tr>
                        <td style="font-weight: 700;"><?= htmlspecialchars($rem['user_name']) ?></td>
                        <td><code style="background: #f1f5f9; padding: 0.2rem 0.5rem; border-radius: 4px;"><?= htmlspecialchars($rem['pc_password']) ?></code></td>
                        <td><code style="background: #f1f5f9; padding: 0.2rem 0.5rem; border-radius: 4px;"><?= htmlspecialchars($rem['email_password']) ?></code></td>
                        <td><?= htmlspecialchars($rem['pc_name']) ?></td>
                        <td><button class="btn-icon"><i class="fa-solid fa-pen"></i></button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="tab-anotacoes" class="tab-content" style="<?= $activeTab === 'anotacoes' ? '' : 'display:none;' ?>">
    <div style="display: grid; grid-template-columns: 250px 1fr; gap: 2rem; height: 600px;">
        <div class="glass-panel" style="padding: 1rem; overflow-y: auto;">
            <h4 style="font-size: 0.8rem; text-transform: uppercase; color: #64748b; margin-bottom: 1rem;">Seções</h4>
            <?php foreach ($noteSections as $sec): ?>
            <div style="padding: 0.75rem; border-radius: 0.5rem; margin-bottom: 0.5rem; cursor: pointer; display: flex; align-items: center; gap: 0.75rem; background: #f8fafc; border-left: 4px solid <?= $sec['color'] ?>;">
                <i class="fa-solid <?= $sec['icon'] ?>" style="color: <?= $sec['color'] ?>"></i>
                <span style="font-weight: 700; font-size: 0.9rem;"><?= htmlspecialchars($sec['name']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="glass-panel" style="display: flex; flex-direction: column;">
            <div id="toolbar-container"></div>
            <div id="editor" style="flex: 1;"></div>
        </div>
    </div>
</div>

<!-- Modal Camera -->
<div id="cameraModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
    <div class="glass-panel" style="max-width: 400px; width: 100%;">
        <h3>Nova Câmera</h3>
        <form method="POST" action="<?= URL_BASE ?>/tecnologia">
            <input type="hidden" name="action" value="add_camera">
            <?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label class="form-label">Nome</label>
                <input type="text" name="name" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">IP</label>
                <input type="text" name="ip_address" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Quantidade</label>
                <input type="number" name="quantity" class="form-input" value="1">
            </div>
            <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                <button type="button" onclick="document.getElementById('cameraModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary">Adicionar</button>
            </div>
        </form>
    </div>
</div>

<script>
    function switchTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
        document.getElementById('tab-' + tabId).style.display = 'block';
        
        document.querySelectorAll('.btn-secondary').forEach(el => el.classList.remove('active'));
        document.getElementById('btn-' + tabId).classList.add('active');
    }

    // Initialize Quill if on notes tab
    var quill = new Quill('#editor', {
        theme: 'snow',
        placeholder: 'Comece a escrever...'
    });
</script>
