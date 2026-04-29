<?php
/**
 * CHAMADOS VIEW
 */
?>
<div class="page-header">
    <div class="page-header-info">
        <div class="page-header-icon">
            <i class="fa-solid fa-headset"></i>
        </div>
        <div class="page-header-text">
            <h2>Central de Suporte</h2>
            <p>Gestão ágil de tickets e solicitações técnicas.</p>
        </div>
    </div>
    <div class="page-header-actions">
        <button class="btn-primary" onclick="document.getElementById('ticketModal').style.display='flex'">
            <i class="fa-solid fa-plus"></i> Novo Chamado
        </button>
    </div>
</div>

<div style="margin-bottom: 2rem; display: flex; gap: 1rem; justify-content: center;">
    <a href="<?= URL_BASE ?>/chamados" class="btn-secondary <?= !$show_all ? 'active' : '' ?>">Abertos / Pendentes</a>
    <a href="<?= URL_BASE ?>/chamados?all=1" class="btn-secondary <?= $show_all ? 'active' : '' ?>">Todo o Histórico</a>
</div>

<div class="table-responsive glass-panel" style="padding: 1rem;">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Título</th>
                <th>Solicitante</th>
                <th>Prioridade</th>
                <th>Status</th>
                <th>SLA / Data</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tickets as $t): ?>
            <tr>
                <td style="font-family: monospace; font-size: 0.75rem;"><?= $t['id'] ?></td>
                <td>
                    <div style="font-weight: 700;"><?= htmlspecialchars($t['title']) ?></div>
                    <?php if($t['asset_name']): ?>
                        <div style="font-size: 0.7rem; color: #64748b;"><i class="fa-solid fa-laptop"></i> <?= htmlspecialchars($t['asset_name']) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="font-weight: 600;"><?= htmlspecialchars($t['requester_name']) ?></span>
                    </div>
                </td>
                <td>
                    <span class="badge badge-<?= $t['priority'] == 'Crítica' ? 'danger' : ($t['priority'] == 'Alta' ? 'warning' : 'info') ?>">
                        <?= $t['priority'] ?>
                    </span>
                </td>
                <td>
                    <span class="badge badge-<?= $t['status'] == 'Concluído' ? 'success' : ($t['status'] == 'Pendente' ? 'warning' : 'info') ?>">
                        <?= $t['status'] ?>
                    </span>
                </td>
                <td>
                    <?php if($t['status'] == 'Concluído' || $t['status'] == 'Sem Solução'): ?>
                        <div style="font-size: 0.75rem; color: #10b981; font-weight: 700;">
                            Resolvido em <?= floor($t['sla_minutes'] / 60) ?>h <?= $t['sla_minutes'] % 60 ?>m
                        </div>
                    <?php else: ?>
                        <?php 
                            $deadline = $t['sla_deadline'] ? strtotime($t['sla_deadline']) : null;
                            $isLate = $deadline && time() > $deadline;
                        ?>
                        <?php if($deadline): ?>
                            <div style="font-size: 0.75rem; color: <?= $isLate ? '#ef4444' : '#64748b' ?>; font-weight: 600;">
                                <i class="fa-solid fa-hourglass-<?= $isLate ? 'end' : 'half' ?>"></i>
                                Prazo: <?= date('d/m H:i', $deadline) ?>
                            </div>
                        <?php endif; ?>
                        <div style="font-size: 0.7rem; color: #94a3b8;">
                            Aberto há <?= floor((time() - strtotime($t['created_at'])) / 3600) ?>h
                        </div>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="display: flex; gap: 0.35rem;">
                        <?php if($t['status'] == 'Aberto' && \App\Core\Auth::can('chamados.edit')): ?>
                            <button class="btn-icon" onclick="fecharChamado('<?= $t['id'] ?>', '<?= $t['title'] ?>')"><i class="fa-solid fa-check"></i></button>
                            <button class="btn-icon" onclick="pendenciarChamado('<?= $t['id'] ?>', '<?= $t['title'] ?>')"><i class="fa-solid fa-clock"></i></button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Novo Chamado (Simplificado) -->
<div id="ticketModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
    <div class="glass-panel" style="max-width: 500px; width: 100%;">
        <h3>Novo Chamado</h3>
        <form method="POST" action="<?= URL_BASE ?>/chamados">
            <input type="hidden" name="action" value="add_ticket">
            <?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label class="form-label">Título</label>
                <input type="text" name="title" class="form-input" required>
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
                <label class="form-label">Descrição</label>
                <textarea name="description" class="form-textarea" required></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Prioridade</label>
                <select name="priority" class="form-select">
                    <option value="Baixa">Baixa</option>
                    <option value="Média" selected>Média</option>
                    <option value="Alta">Alta</option>
                    <option value="Crítica">Crítica</option>
                </select>
            </div>
            <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                <button type="button" onclick="document.getElementById('ticketModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary">Criar</button>
            </div>
        </form>
    </div>
</div>

<script>
    function fecharChamado(id, title) {
        if(confirm(`Fechar o chamado: ${title}?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?= URL_BASE ?>/chamados';
            form.innerHTML = `<input type="hidden" name="action" value="close_ticket"><input type="hidden" name="ticket_id" value="${id}"><input type="hidden" name="resolution" value="solucionado"><?= \App\Core\Csrf::field() ?>`;
            document.body.appendChild(form);
            form.submit();
        }
    }
    function pendenciarChamado(id, title) {
        let reason = prompt(`Motivo da pendência para: ${title}`);
        if(reason) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?= URL_BASE ?>/chamados';
            form.innerHTML = `<input type="hidden" name="action" value="pendenciar_ticket"><input type="hidden" name="ticket_id" value="${id}"><input type="hidden" name="reason" value="${reason}"><?= \App\Core\Csrf::field() ?>`;
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>
