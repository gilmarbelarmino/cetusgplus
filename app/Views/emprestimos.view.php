<?php
/**
 * EMPRESTIMOS VIEW
 */
?>
<div class="page-header">
    <div class="page-header-info">
        <div class="page-header-icon">
            <i class="fa-solid fa-handshake-angle"></i>
        </div>
        <div class="page-header-text">
            <h2>Controle de Comodatos</h2>
            <p>Gerenciamento de empréstimos e termos de responsabilidade.</p>
        </div>
    </div>
    <div class="page-header-actions">
        <button class="btn-primary" onclick="document.getElementById('loanModal').style.display='flex'">
            <i class="fa-solid fa-plus"></i> Novo Empréstimo
        </button>
    </div>
</div>

<div class="glass-panel" style="margin-bottom: 2rem; display: flex; gap: 1rem;">
    <a href="<?= URL_BASE ?>/emprestimos?view=ativos" class="btn-secondary <?= $view === 'ativos' ? 'active' : '' ?>">Ativos</a>
    <a href="<?= URL_BASE ?>/emprestimos?view=fechados" class="btn-secondary <?= $view === 'fechados' ? 'active' : '' ?>">Devolvidos</a>
</div>

<div class="table-responsive glass-panel" style="padding: 1rem;">
    <table>
        <thead>
            <tr>
                <th>Equipamento</th>
                <th>Responsável</th>
                <th>Data Saída</th>
                <th>Previsão</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($loans as $l): ?>
            <tr>
                <td style="font-weight: 700;"><?= htmlspecialchars($l['asset_name']) ?></td>
                <td>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="width: 32px; height: 32px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 800;">
                            <?= strtoupper(substr($l['borrower_name'], 0, 1)) ?>
                        </div>
                        <span><?= htmlspecialchars($l['borrower_name']) ?></span>
                    </div>
                </td>
                <td style="font-size: 0.8rem;"><?= date('d/m/Y H:i', strtotime($l['loan_date'])) ?></td>
                <td style="font-size: 0.8rem;"><?= date('d/m/Y', strtotime($l['expected_return_date'])) ?></td>
                <td>
                    <span class="badge badge-<?= $l['status'] === 'Ativo' ? 'warning' : 'success' ?>">
                        <?= $l['status'] ?>
                    </span>
                </td>
                <td>
                    <button class="btn-icon"><i class="fa-solid fa-clock-rotate-left"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Loan -->
<div id="loanModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
    <div class="glass-panel" style="max-width: 500px; width: 100%;">
        <h3>Novo Empréstimo</h3>
        <form method="POST" action="<?= URL_BASE ?>/emprestimos">
            <input type="hidden" name="action" value="add_loan">
            <?= \App\Core\Csrf::field() ?>
            
            <div class="form-group">
                <label class="form-label">Equipamento</label>
                <select name="asset_id" class="form-select" required>
                    <?php foreach ($assets as $a): ?>
                        <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?> (<?= $a['id'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Usuário</label>
                <select name="borrower_id" class="form-select" required>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Data Prevista de Retorno</label>
                <input type="date" name="expected_return_date" class="form-input" required>
            </div>

            <input type="hidden" name="loan_date" value="<?= date('Y-m-d H:i:s') ?>">

            <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                <button type="button" onclick="document.getElementById('loanModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary">Registrar</button>
            </div>
        </form>
    </div>
</div>
