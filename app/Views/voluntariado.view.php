<?php
/**
 * VOLUNTARIADO VIEW
 */
?>
<div class="page-header">
    <div class="page-header-info">
        <div class="page-header-icon">
            <i class="fa-solid fa-hand-holding-heart"></i>
        </div>
        <div class="page-header-text">
            <h2>Programa de Voluntariado</h2>
            <p>Gestão de impacto social e horas dedicadas.</p>
        </div>
    </div>
    <div class="page-header-actions">
        <?php if (\App\Core\Auth::can('voluntariado.edit')): ?>
        <button class="btn-primary" onclick="document.getElementById('volunteerModal').style.display='flex'">
            <i class="fa-solid fa-plus"></i> Novo Voluntário
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="table-responsive glass-panel" style="padding: 1rem;">
    <table>
        <thead>
            <tr>
                <th>Voluntário</th>
                <th>Área</th>
                <th>Tipo</th>
                <th>Horas</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($volunteers as $v): ?>
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 40px; height: 40px; border-radius: 10px; background: #e2e8f0; overflow: hidden;">
                            <?php if ($v['avatar_url']): ?>
                                <img src="<?= URL_BASE ?>/public/<?= htmlspecialchars($v['avatar_url']) ?>" style="width:100%; height:100%; object-fit:cover;">
                            <?php else: ?>
                                <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-weight:700;">
                                    <?= substr($v['name'], 0, 1) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($v['name']) ?></div>
                            <div style="font-size: 0.7rem; color: #64748b;"><?= htmlspecialchars($v['email']) ?></div>
                        </div>
                    </div>
                </td>
                <td><?= htmlspecialchars($v['work_area']) ?></td>
                <td><?= htmlspecialchars($v['location']) ?></td>
                <td style="font-weight: 800; color: #6366f1;"><?= $v['total_hours'] ?>h</td>
                <td>
                    <span class="badge <?= $v['status'] == 'Ativo' ? 'badge-success' : 'badge-warning' ?>">
                        <?= $v['status'] ?>
                    </span>
                </td>
                <td>
                    <div style="display: flex; gap: 0.35rem;">
                        <button class="btn-icon" onclick="window.location.href='<?= URL_BASE ?>/rh/voluntariado/certificado?id=<?= $v['id'] ?>'"><i class="fa-solid fa-file-contract"></i></button>
                        <?php if (\App\Core\Auth::can('voluntariado.edit')): ?>
                            <button class="btn-icon" onclick="inativar('<?= $v['id'] ?>', '<?= $v['name'] ?>')"><i class="fa-solid fa-circle-xmark"></i></button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    function inativar(id, name) {
        if(confirm(`Deseja inativar o voluntário ${name}?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?= URL_BASE ?>/rh/voluntariado';
            form.innerHTML = `
                <input type="hidden" name="action" value="inativar">
                <input type="hidden" name="volunteer_id" value="${id}">
                <?= \App\Core\Csrf::field() ?>
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>
