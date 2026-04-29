<?php
/**
 * INFORMACOES VIEW
 */
?>
<div class="page-header">
    <div class="page-header-info">
        <div class="page-header-icon">
            <i class="fa-solid fa-circle-info"></i>
        </div>
        <div class="page-header-text">
            <h2>Quadro de Informações</h2>
            <p>Avisos gerais, informativos setoriais e diretório da equipe.</p>
        </div>
    </div>
</div>

<!-- Quadro Geral -->
<div class="glass-panel" style="margin-bottom: 2rem; border-left: 5px solid var(--crm-purple);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h3 style="font-size: 1.1rem; font-weight: 800; color: var(--crm-purple);"><i class="fa-solid fa-bullhorn"></i> Comunicados da Empresa</h3>
        <?php if ($isAdmin): ?>
            <button class="btn-icon" onclick="document.getElementById('genModal').style.display='flex'"><i class="fa-solid fa-pen"></i></button>
        <?php endif; ?>
    </div>
    <div style="line-height: 1.6; color: #1e293b; font-size: 1rem;"><?= nl2br(htmlspecialchars($generalMsg)) ?></div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
    <!-- Informativos Setoriais -->
    <div>
        <?php foreach ($sectors as $sec): ?>
        <div class="glass-panel" style="margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h3 style="font-size: 1rem; font-weight: 900;"><i class="fa-solid fa-layer-group"></i> <?= htmlspecialchars($sec['name']) ?></h3>
            </div>
            
            <div style="background: #f8fafc; padding: 1rem; border-radius: 0.75rem; margin-bottom: 1.5rem; font-size: 0.9rem; color: #475569;">
                <?= nl2br(htmlspecialchars($sectorMsgs[$sec['name']] ?? 'Sem avisos para este setor.')) ?>
            </div>

            <h4 style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; margin-bottom: 1rem;">Links e Documentos</h4>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <?php foreach (($links[$sec['name']] ?? []) as $lk): ?>
                <a href="<?= htmlspecialchars($lk['url']) ?>" target="_blank" class="nav-link" style="background: white; border: 1px solid #e2e8f0; justify-content: space-between;">
                    <span><?= htmlspecialchars($lk['title']) ?></span>
                    <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 0.7rem; opacity: 0.5;"></i>
                </a>
                <?php endforeach; ?>
                <?php if (empty($links[$sec['name']])): ?>
                    <p style="font-size: 0.8rem; color: #cbd5e1;">Nenhum link cadastrado.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Diretório da Equipe -->
    <div>
        <div class="glass-panel">
            <h3 style="font-size: 1rem; font-weight: 900; margin-bottom: 1.5rem;"><i class="fa-solid fa-users"></i> Membros da Equipe</h3>
            <?php foreach ($sectors as $sec): ?>
                <div style="margin-bottom: 1.5rem;">
                    <div style="font-size: 0.7rem; font-weight: 800; color: var(--crm-purple); text-transform: uppercase; margin-bottom: 0.75rem;"><?= htmlspecialchars($sec['name']) ?></div>
                    <?php foreach (($team[$sec['name']] ?? []) as $u): ?>
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem;">
                        <div style="width: 36px; height: 36px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 800;">
                            <?= strtoupper(substr($u['name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div style="font-weight: 700; font-size: 0.85rem;"><?= htmlspecialchars($u['name']) ?></div>
                            <div style="font-size: 0.7rem; color: #64748b;">Membro</div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Modal Edit General -->
<div id="genModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
    <div class="glass-panel" style="max-width: 500px; width: 100%;">
        <h3>Editar Comunicado Geral</h3>
        <form method="POST" action="<?= URL_BASE ?>/informacoes">
            <input type="hidden" name="action" value="update_general">
            <?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label class="form-label">Conteúdo</label>
                <textarea name="content" class="form-textarea" rows="10"><?= htmlspecialchars($generalMsg) ?></textarea>
            </div>
            <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                <button type="button" onclick="document.getElementById('genModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>
