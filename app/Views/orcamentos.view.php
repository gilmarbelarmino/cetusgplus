<?php
/**
 * ORCAMENTOS VIEW
 */
?>
<div class="page-header">
    <div class="page-header-info">
        <div class="page-header-icon">
            <i class="fa-solid fa-file-invoice-dollar"></i>
        </div>
        <div class="page-header-text">
            <h2>Processos de Compra</h2>
            <p>Controle de cotações e aprovações financeiras.</p>
        </div>
    </div>
    <div class="page-header-actions">
        <button class="btn-primary" onclick="document.getElementById('budgetModal').style.display='flex'">
            <i class="fa-solid fa-plus"></i> Novo Orçamento
        </button>
    </div>
</div>

<div class="tab-nav glass-panel" style="margin-bottom: 2rem; padding: 0.5rem; display: inline-flex; gap: 0.5rem;">
    <a href="<?= URL_BASE ?>/orcamentos" class="tab-btn <?= $activeTab == 'orcamentos' ? 'active' : '' ?>">Orçamentos</a>
    <a href="<?= URL_BASE ?>/orcamentos?tab=wishlist" class="tab-btn <?= $activeTab == 'wishlist' ? 'active' : '' ?>">Lista de Desejos</a>
</div>

<?php if ($activeTab == 'orcamentos'): ?>
    <div class="table-responsive glass-panel" style="padding: 1rem;">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Produto</th>
                    <th>Solicitante</th>
                    <th>Status</th>
                    <th>Cotações</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($budgets as $b): ?>
                <tr>
                    <td style="font-family: monospace;"><?= $b['id'] ?></td>
                    <td>
                        <div style="font-weight: 700;"><?= htmlspecialchars($b['product_name']) ?></div>
                        <div style="font-size: 0.7rem; color: #64748b;">Qtd: <?= $b['quantity'] ?></div>
                    </td>
                    <td><?= htmlspecialchars($b['requester_name']) ?></td>
                    <td>
                        <span class="badge badge-<?= $b['status'] == 'Aprovado' ? 'success' : ($b['status'] == 'Rejeitado' ? 'danger' : 'warning') ?>">
                            <?= $b['status'] ?>
                        </span>
                    </td>
                    <td style="text-align: center; font-weight: 800; color: #6366f1;"><?= $b['quotes_count'] ?></td>
                    <td>
                        <div style="display: flex; gap: 0.35rem;">
                            <?php if ($b['status'] == 'Pendente' && \App\Core\Auth::can('orcamentos.approve')): ?>
                                <button class="btn-icon" onclick="aprovarBudget('<?= $b['id'] ?>', '<?= $b['product_name'] ?>')"><i class="fa-solid fa-check"></i></button>
                            <?php endif; ?>
                            <button class="btn-icon" onclick="window.location.href='<?= URL_BASE ?>/orcamentos/detalhes?id=<?= $b['id'] ?>'"><i class="fa-solid fa-eye"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="table-responsive glass-panel" style="padding: 1rem;">
        <table>
            <thead>
                <tr>
                    <th>Número</th>
                    <th>Setor</th>
                    <th>Itens</th>
                    <th>Status</th>
                    <th>Solicitante</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($wishlist as $w): ?>
                <tr>
                    <td style="font-weight: 700; color: #6366f1;">#<?= $w['request_number'] ?></td>
                    <td><?= htmlspecialchars($w['sector_name']) ?></td>
                    <td><?= $w['item_count'] ?> itens</td>
                    <td><span class="badge badge-info"><?= $w['status'] ?></span></td>
                    <td><?= htmlspecialchars($w['requester_name']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Modal Novo Orçamento (Simplificado) -->
<div id="budgetModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
    <div class="glass-panel" style="max-width: 500px; width: 100%;">
        <h3>Novo Orçamento</h3>
        <form method="POST" action="<?= URL_BASE ?>/orcamentos">
            <input type="hidden" name="action" value="add_budget">
            <?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label class="form-label">Produto</label>
                <input type="text" name="product_name" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Quantidade</label>
                <input type="number" name="quantity" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Solicitante</label>
                <select name="requester_id" class="form-select" required>
                    <?php foreach($users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Unidade</label>
                <select name="unit_id" class="form-select" required>
                    <?php foreach($units as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                <button type="button" onclick="document.getElementById('budgetModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary">Criar</button>
            </div>
        </form>
    </div>
</div>

<script>
    function aprovarBudget(id, name) {
        if(confirm(`Aprovar orçamento para: ${name}?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?= URL_BASE ?>/orcamentos';
            form.innerHTML = `<input type="hidden" name="action" value="approve_budget"><input type="hidden" name="budget_id" value="${id}"><?= \App\Core\Csrf::field() ?>`;
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>
