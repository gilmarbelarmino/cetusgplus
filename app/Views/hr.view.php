<?php
/**
 * RH MANAGEMENT VIEW
 */
?>
<div class="page-header">
    <div class="page-header-info">
        <div class="page-header-icon">
            <i class="fa-solid fa-user-tie"></i>
        </div>
        <div class="page-header-text">
            <h2>Gestão de Equipe</h2>
            <p>Administração de contratos, férias e acompanhamento funcional.</p>
        </div>
    </div>
    <div class="page-header-actions">
        <a href="<?= URL_BASE ?>/rh/voluntariado" class="btn-secondary">
            <i class="fa-solid fa-hand-holding-heart"></i> Voluntários
        </a>
    </div>
</div>

<div class="glass-panel" style="padding: 1.5rem; margin-bottom: 2rem;">
    <form method="GET" action="<?= URL_BASE ?>/rh" style="display: flex; gap: 1rem;">
        <input type="text" name="search" class="form-input" placeholder="Buscar funcionário..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn-primary">Filtrar</button>
    </form>
</div>

<div class="table-responsive glass-panel" style="padding: 1rem;">
    <table>
        <thead>
            <tr>
                <th>Funcionário</th>
                <th>Setor</th>
                <th>Contrato</th>
                <th>Escala</th>
                <th>Salário</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 36px; height: 36px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-weight: 800;">
                            <?= strtoupper(substr($u['name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div style="font-weight: 700;"><?= htmlspecialchars($u['name']) ?></div>
                            <div style="font-size: 0.7rem; color: #64748b;"><?= htmlspecialchars($u['email']) ?></div>
                        </div>
                    </div>
                </td>
                <td><span class="badge badge-info"><?= htmlspecialchars($u['sector']) ?></span></td>
                <td><?= htmlspecialchars($u['contract_type'] ?: 'N/A') ?></td>
                <td><?= htmlspecialchars($u['work_days'] ?: 'N/A') ?></td>
                <td style="font-weight: 700;">R$ <?= number_format($u['salary'] ?? 0, 2, ',', '.') ?></td>
                <td>
                    <div style="display: flex; gap: 0.5rem;">
                        <button class="btn-icon" onclick="openContractModal('<?= $u['id'] ?>')"><i class="fa-solid fa-file-signature"></i></button>
                        <button class="btn-icon" style="color: #f59e0b;"><i class="fa-solid fa-plane-departure"></i></button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Contrato (Simplificado) -->
<div id="contractModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
    <div class="glass-panel" style="max-width: 500px; width: 100%;">
        <h3>Dados Contratuais</h3>
        <form method="POST" action="<?= URL_BASE ?>/rh">
            <input type="hidden" name="action" value="save_contract">
            <input type="hidden" name="user_id" id="modal_user_id">
            <?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label class="form-label">Tipo de Contrato</label>
                <select name="contract_type" class="form-select">
                    <option value="CLT">CLT</option>
                    <option value="PJ">PJ</option>
                    <option value="Estágio">Estágio</option>
                    <option value="Autônomo">Autônomo</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Salário Base</label>
                <input type="number" step="0.01" name="salary" class="form-input">
            </div>
            <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                <button type="button" onclick="document.getElementById('contractModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openContractModal(id) {
        document.getElementById('modal_user_id').value = id;
        document.getElementById('contractModal').style.display = 'flex';
    }
</script>
