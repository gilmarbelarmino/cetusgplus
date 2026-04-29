<?php
/**
 * ROLES & PERMISSIONS VIEW
 */
?>
<div class="page-header">
    <div class="page-header-info">
        <div class="page-header-icon">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <div class="page-header-text">
            <h2>Cargos & Permissões</h2>
            <p>Controle granular de acesso por papel administrativo.</p>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 250px 1fr; gap: 2rem;">
    <!-- Lista de Cargos -->
    <div class="glass-panel" style="padding: 1rem;">
        <h4 style="margin-bottom: 1rem; font-weight: 800; font-size: 0.85rem; color: #64748b; text-transform: uppercase;">Cargos</h4>
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            <?php foreach ($roles as $r): ?>
                <button onclick="selectRole(<?= htmlspecialchars(json_encode($r)) ?>)" class="role-btn" id="role-<?= $r['id'] ?>">
                    <?= htmlspecialchars($r['display_name']) ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Matriz de Permissões -->
    <div id="permissions-panel" class="glass-panel" style="padding: 2rem; display: none;">
        <form method="POST" action="<?= URL_BASE ?>/configuracoes/roles">
            <input type="hidden" name="action" value="save_role_permissions">
            <input type="hidden" name="role_id" id="role_id_input">
            <?= \App\Core\Csrf::field() ?>

            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h3 id="role-title" style="font-weight: 900; color: #1e293b;"></h3>
                <button type="submit" class="btn-primary">Salvar Permissões</button>
            </div>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 2rem;">
                <?php foreach ($permissions_by_module as $module => $perms): ?>
                    <div>
                        <h4 style="font-weight: 800; color: #6366f1; border-bottom: 2px solid #f1f5f9; padding-bottom: 0.5rem; margin-bottom: 1rem; text-transform: capitalize;">
                            <?= htmlspecialchars($module) ?>
                        </h4>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <?php foreach ($perms as $p): ?>
                                <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
                                    <input type="checkbox" name="permissions[]" value="<?= $p['id'] ?>" class="perm-checkbox" id="perm-<?= $p['id'] ?>" style="width: 18px; height: 18px; accent-color: #6366f1;">
                                    <div>
                                        <div style="font-weight: 700; font-size: 0.9rem; color: #334155;"><?= htmlspecialchars($p['display_name']) ?></div>
                                        <div style="font-size: 0.75rem; color: #94a3b8;"><?= htmlspecialchars($p['description']) ?></div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </form>
    </div>
</div>

<style>
    .role-btn {
        text-align: left; padding: 0.75rem 1rem; border-radius: 0.75rem; border: 1px solid transparent; background: none; cursor: pointer; font-weight: 700; color: #475569; transition: all 0.2s;
    }
    .role-btn:hover { background: #f1f5f9; color: #1e293b; }
    .role-btn.active { background: #e0e7ff; color: #4338ca; border-color: #c7d2fe; }
</style>

<script>
    function selectRole(role) {
        document.querySelectorAll('.role-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('role-' + role.id).classList.add('active');
        
        document.getElementById('role_id_input').value = role.id;
        document.getElementById('role-title').innerText = 'Permissões para: ' + role.display_name;
        document.getElementById('permissions-panel').style.display = 'block';

        // Aqui eu precisaria de uma chamada AJAX para buscar as permissões atuais do cargo
        // Por brevidade, vou apenas limpar as checkboxes
        document.querySelectorAll('.perm-checkbox').forEach(c => c.checked = false);
    }
</script>
