<?php
/**
 * USUARIOS VIEW
 * =============
 */
?>
<div class="page-header">
    <div class="page-header-info">
        <div class="page-header-icon">
            <i class="fa-solid fa-users-gear"></i>
        </div>
        <div class="page-header-text">
            <h2>Administração de Acessos</h2>
            <p>Controle de perfis, permissões e segurança operacional.</p>
        </div>
    </div>
    <div class="page-header-actions">
        <?php if (\App\Core\Auth::can('usuarios.create')): ?>
        <button class="btn-primary" onclick="document.getElementById('formModal').style.display='flex'">
            <i class="fa-solid fa-plus"></i>
            Incluir Usuário
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success">
    <i class="fa-solid fa-circle-check"></i>
    <?php
        $msgs = [
            '1' => 'Usuário cadastrado com sucesso!',
            '2' => 'Usuário atualizado com sucesso!',
            '3' => 'Senha resetada com sucesso!',
            '4' => 'Usuário excluído com sucesso!'
        ];
        echo $msgs[$_GET['success']] ?? 'Operação realizada!';
    ?>
</div>
<?php endif; ?>

<div class="glass-panel" style="padding: 1.5rem; margin-bottom: 1.5rem;">
    <form method="GET" action="<?= URL_BASE ?>/usuarios" style="display: flex; gap: 1rem; align-items: end;">
        <div style="flex: 1;">
            <label class="form-label">Buscar</label>
            <input type="text" name="search" class="form-input" placeholder="Nome, e-mail ou setor..." value="<?= htmlspecialchars($search) ?>">
        </div>
        
        <div style="width: 250px;">
            <label class="form-label">Unidade</label>
            <select name="unit" class="form-select">
                <option value="">Todas as Unidades</option>
                <?php foreach ($units as $unit): ?>
                    <option value="<?= $unit['id'] ?>" <?= $unit_filter == $unit['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($unit['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <button type="submit" class="btn-primary">
            <i class="fa-solid fa-magnifying-glass"></i>
            Filtrar
        </button>
    </form>
</div>

<?php foreach ($sectors_list as $sector => $users_in_sector): ?>
<div style="margin-bottom: 3rem;">
    <div class="sector-divider" style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
        <h3 style="font-size: 0.75rem; font-weight: 900; color: #6366f1; text-transform: uppercase; letter-spacing: 0.2em;">
            <?= htmlspecialchars($sector) ?>
        </h3>
        <div style="height: 1px; flex: 1; background: #e2e8f0;"></div>
    </div>
    
    <?php foreach ($users_in_sector as $usr): ?>
    <div class="glass-panel user-card" style="margin-bottom: 1rem; border-left: 4px solid #6366f1;">
        <div style="display: flex; align-items: center; gap: 1.5rem;">
            <div class="avatar-wrapper" style="width: 56px; height: 56px; border-radius: 12px; overflow: hidden; background: #f1f5f9;">
                <?php if (!empty($usr['avatar_url'])): ?>
                    <img src="<?= URL_BASE ?>/public/<?= htmlspecialchars($usr['avatar_url']) ?>" alt="" style="width:100%; height:100%; object-fit:cover;">
                <?php else: ?>
                    <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-weight:900;">
                        <?= strtoupper(substr($usr['name'], 0, 2)) ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="flex: 1; display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 1rem;">
                <div>
                    <div style="font-weight: 800; color: #1e293b;"><?= htmlspecialchars($usr['name']) ?></div>
                    <div style="font-size: 0.75rem; color: #64748b;"><?= htmlspecialchars($usr['email']) ?></div>
                </div>
                <div>
                    <div style="font-size: 0.65rem; color: #94a3b8; font-weight: 800; text-transform: uppercase;">Setor</div>
                    <div style="font-size: 0.875rem; font-weight: 600; color: #334155;"><?= htmlspecialchars($usr['sector'] ?: '—') ?></div>
                </div>
                <div>
                    <div style="font-size: 0.65rem; color: #94a3b8; font-weight: 800; text-transform: uppercase;">Perfil</div>
                    <div style="font-size: 0.875rem; font-weight: 600; color: #6366f1;"><?= htmlspecialchars($usr['role']) ?></div>
                </div>
                <div style="text-align: right; display: flex; gap: 0.5rem; justify-content: flex-end;">
                    <?php if (\App\Core\Auth::can('usuarios.edit')): ?>
                        <button class="btn-icon" onclick="editUser(<?= htmlspecialchars(json_encode($usr)) ?>)"><i class="fa-solid fa-pen"></i></button>
                    <?php endif; ?>
                    <?php if (\App\Core\Auth::can('usuarios.delete') && $usr['id'] !== $_SESSION['user_id']): ?>
                        <button class="btn-icon btn-danger-soft" onclick="deleteUser('<?= $usr['id'] ?>', '<?= htmlspecialchars($usr['name']) ?>')"><i class="fa-solid fa-trash"></i></button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<!-- Modais (Add/Edit) omitidos por brevidade nesta demonstração, mas seguiriam o mesmo padrão -->

<script>
    function editUser(user) {
        // Implementar preenchimento do modal de edição
        console.log("Edit user:", user);
    }
    function deleteUser(id, name) {
        if(confirm(`Deseja realmente excluir o usuário ${name}?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?= URL_BASE ?>/usuarios';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" value="${id}">
                <?= \App\Core\Csrf::field() ?>
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>
