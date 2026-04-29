<?php
/**
 * SEMANADA VIEW
 */
?>
<div class="page-header">
    <div class="page-header-info">
        <div class="page-header-icon">
            <i class="fa-solid fa-calendar-week"></i>
        </div>
        <div class="page-header-text">
            <h2>Semanada</h2>
            <p>Programação semanal oficial da instituição.</p>
        </div>
    </div>
    <div class="page-header-actions">
        <?php if (\App\Core\Auth::can('semanada.edit')): ?>
        <button class="btn-primary" onclick="document.getElementById('uploadModal').style.display='flex'">
            <i class="fa-solid fa-file-import"></i> Importar PDF
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($upload): ?>
    <div class="glass-panel" style="margin-bottom: 1.5rem; padding: 1rem; display: flex; align-items: center; justify-content: space-between;">
        <div style="font-size: 0.85rem; color: #64748b;">
            <i class="fa-solid fa-circle-info" style="color: #6366f1;"></i> 
            Arquivo atual: <strong><?= htmlspecialchars($upload['original_name']) ?></strong> 
            importado por <strong><?= htmlspecialchars($upload['uploader_name']) ?></strong> em <?= date('d/m/Y H:i', strtotime($upload['uploaded_at'])) ?>
        </div>
        <a href="<?= URL_BASE ?>/<?= $upload['filename'] ?>" target="_blank" class="btn-secondary" style="font-size: 0.75rem;">
            <i class="fa-solid fa-expand"></i> Abrir em nova aba
        </a>
    </div>

    <div class="glass-panel" style="height: 800px; padding: 0; overflow: hidden;">
        <iframe src="<?= URL_BASE ?>/<?= $upload['filename'] ?>#toolbar=0" width="100%" height="100%" style="border: none;"></iframe>
    </div>
<?php else: ?>
    <div class="empty-state glass-panel" style="text-align: center; padding: 6rem;">
        <i class="fa-solid fa-file-pdf" style="font-size: 4rem; color: #e2e8f0; margin-bottom: 1.5rem; display: block;"></i>
        <h3 style="color: #64748b;">Nenhuma Semanada disponível</h3>
        <p style="color: #94a3b8;">Importe o PDF com a programação da semana para começar.</p>
    </div>
<?php endif; ?>

<!-- Modal Upload -->
<div id="uploadModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
    <div class="glass-panel" style="max-width: 400px; width: 100%;">
        <h3>Importar Semanada</h3>
        <form method="POST" action="<?= URL_BASE ?>/semanada" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_pdf">
            <?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label class="form-label">Arquivo PDF</label>
                <input type="file" name="pdf_file" class="form-input" accept="application/pdf" required>
            </div>
            <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                <button type="button" onclick="document.getElementById('uploadModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary">Enviar</button>
            </div>
        </form>
    </div>
</div>
