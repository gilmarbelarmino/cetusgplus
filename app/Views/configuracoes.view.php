<?php
/**
 * CONFIGURACOES VIEW
 */
?>
<div class="page-header">
    <div class="page-header-info">
        <div class="page-header-icon">
            <i class="fa-solid fa-sliders"></i>
        </div>
        <div class="page-header-text">
            <h2>Configurações do Sistema</h2>
            <p>Gerenciamento de unidades, setores e identidade visual.</p>
        </div>
    </div>
</div>

<div class="glass-panel" style="margin-bottom: 2rem;">
    <h3 style="font-size: 1rem; font-weight: 900; margin-bottom: 1.5rem;"><i class="fa-solid fa-building"></i> Identidade da Empresa</h3>
    <form method="POST" action="<?= URL_BASE ?>/configuracoes" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save_company">
        <input type="hidden" name="current_logo" value="<?= htmlspecialchars($company['logo_url'] ?? '') ?>">
        <?= \App\Core\Csrf::field() ?>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label class="form-label">Nome da Instituição</label>
                <input type="text" name="company_name" class="form-input" value="<?= htmlspecialchars($company['name'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Logo Principal</label>
                <input type="file" name="logo" class="form-input" accept="image/*">
            </div>
        </div>
        <button type="submit" class="btn-primary" style="margin-top: 1rem;">Salvar Alterações</button>
    </form>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
    <div class="glass-panel">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1rem; font-weight: 900;"><i class="fa-solid fa-map-location-dot"></i> Unidades</h3>
            <button class="btn-secondary" style="font-size: 0.75rem;" onclick="document.getElementById('unitModal').style.display='flex'">+ Nova</button>
        </div>
        <div style="max-height: 300px; overflow-y: auto;">
            <?php foreach ($units as $u): ?>
            <div style="padding: 0.75rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between;">
                <div>
                    <div style="font-weight: 700;"><?= htmlspecialchars($u['name']) ?></div>
                    <div style="font-size: 0.7rem; color: #64748b;"><?= htmlspecialchars($u['cnpj'] ?: 'S/ CNPJ') ?></div>
                </div>
                <button class="btn-icon" style="color: #ef4444;"><i class="fa-solid fa-trash"></i></button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="glass-panel">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1rem; font-weight: 900;"><i class="fa-solid fa-layer-group"></i> Setores</h3>
            <button class="btn-secondary" style="font-size: 0.75rem;">+ Novo</button>
        </div>
        <div style="max-height: 300px; overflow-y: auto;">
            <?php foreach ($sectors as $s): ?>
            <div style="padding: 0.75rem; border-bottom: 1px solid #e2e8f0;">
                <div style="font-weight: 700;"><?= htmlspecialchars($s['name']) ?></div>
                <div style="font-size: 0.7rem; color: #64748b;">Unidade: <?= htmlspecialchars($s['unit_name']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Modal Unidade -->
<div id="unitModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
    <div class="glass-panel" style="max-width: 400px; width: 100%;">
        <h3>Adicionar Unidade</h3>
        <form method="POST" action="<?= URL_BASE ?>/configuracoes">
            <input type="hidden" name="action" value="add_unit">
            <?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label class="form-label">Nome</label>
                <input type="text" name="name" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">CNPJ</label>
                <input type="text" name="cnpj" class="form-input">
            </div>
            <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                <button type="button" onclick="document.getElementById('unitModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary">Adicionar</button>
            </div>
        </form>
    </div>
</div>
