<?php
/**
 * PATRIMONIO VIEW
 */
?>
<div class="page-header">
    <div class="page-header-info">
        <div class="page-header-icon">
            <i class="fa-solid fa-vault"></i>
        </div>
        <div class="page-header-text">
            <h2>Gestão de Ativos & Patrimônio</h2>
            <p>Controle de inventário e movimentações.</p>
        </div>
    </div>
    <div class="page-header-actions">
        <?php if (\App\Core\Auth::can('patrimonio.edit')): ?>
        <button class="btn-primary" onclick="document.getElementById('assetModal').style.display='flex'">
            <i class="fa-solid fa-plus"></i> Novo Ativo
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="glass-panel" style="padding: 1.5rem; margin-bottom: 1.5rem;">
    <form method="GET" action="<?= URL_BASE ?>/patrimonio" style="display: flex; gap: 1rem; align-items: end;">
        <div style="flex: 1;">
            <label class="form-label">Buscar</label>
            <input type="text" name="search" class="form-input" placeholder="Nome ou patrimônio..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <button type="submit" class="btn-primary">Filtrar</button>
    </form>
</div>

<div class="table-responsive glass-panel" style="padding: 1rem;">
    <table>
        <thead>
            <tr>
                <th>Ativo</th>
                <th>Patrimônio</th>
                <th>Status</th>
                <th>Responsável</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($assets as $a): ?>
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: #e2e8f0; overflow: hidden;">
                            <?php if ($a['image_url']): ?>
                                <img src="<?= URL_BASE ?>/public/<?= htmlspecialchars($a['image_url']) ?>" style="width:100%; height:100%; object-fit:cover;">
                            <?php else: ?>
                                <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#94a3b8;"><i class="fa-solid fa-desktop"></i></div>
                            <?php endif; ?>
                        </div>
                        <div style="font-weight: 700;"><?= htmlspecialchars($a['name']) ?></div>
                    </div>
                </td>
                <td style="font-family: monospace;"><?= htmlspecialchars($a['patrimony_id'] ?: '—') ?></td>
                <td>
                    <span class="badge badge-<?= $a['status'] == 'Ativo' ? 'success' : 'warning' ?>">
                        <?= $a['status'] ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($a['responsible_name']) ?></td>
                <td>
                    <div style="display: flex; gap: 0.35rem;">
                        <button class="btn-icon" onclick="window.location.href='<?= URL_BASE ?>/patrimonio/historico?id=<?= $a['id'] ?>'"><i class="fa-solid fa-clock-rotate-left"></i></button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Novo Ativo (Simplificado) -->
<div id="assetModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
    <div class="glass-panel" style="max-width: 500px; width: 100%;">
        <h3>Novo Ativo</h3>
        <form method="POST" action="<?= URL_BASE ?>/patrimonio" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_asset">
            <?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label class="form-label">Nome do Produto</label>
                <input type="text" name="name" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Patrimônio / Acesso</label>
                <input type="text" name="patrimony_id" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Responsável</label>
                <input type="text" name="responsible_name" class="form-input" required>
            </div>
            <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                <button type="button" onclick="document.getElementById('assetModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>
