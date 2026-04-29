<?php
/**
 * LOCACAO DE SALAS VIEW
 */
?>
<div class="page-header">
    <div class="page-header-info">
        <div class="page-header-icon">
            <i class="fa-solid fa-building-circle-check"></i>
        </div>
        <div class="page-header-text">
            <h2>Locação de Salas</h2>
            <p>Gerencie o uso compartilhado dos espaços da instituição.</p>
        </div>
    </div>
    <div class="page-header-actions">
        <button class="btn-primary" onclick="document.getElementById('bookingModal').style.display='flex'">
            <i class="fa-solid fa-calendar-plus"></i> Reservar Sala
        </button>
        <?php if (\App\Core\Auth::can('configuracoes.edit')): ?>
        <button class="btn-secondary" onclick="document.getElementById('roomModal').style.display='flex'">
            <i class="fa-solid fa-plus"></i> Nova Sala
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="table-responsive glass-panel" style="padding: 1rem;">
    <table>
        <thead>
            <tr>
                <th>Sala</th>
                <th>Data</th>
                <th>Horário</th>
                <th>Responsável</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bookings as $b): ?>
            <tr>
                <td style="font-weight: 700;"><?= htmlspecialchars($b['room_name']) ?></td>
                <td><?= date('d/m/Y', strtotime($b['booking_date'])) ?></td>
                <td><?= date('H:i', strtotime($b['start_time'])) ?> - <?= date('H:i', strtotime($b['end_time'])) ?></td>
                <td><?= htmlspecialchars($b['user_name']) ?></td>
                <td><span class="badge badge-success"><?= $b['status'] ?></span></td>
                <td>
                    <?php if ($_SESSION['user_id'] == $b['user_id'] || \App\Core\Auth::can('configuracoes.edit')): ?>
                        <button class="btn-icon" style="color: #ef4444;"><i class="fa-solid fa-trash"></i></button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal Nova Reserva -->
<div id="bookingModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
    <div class="glass-panel" style="max-width: 450px; width: 100%;">
        <h3>Nova Reserva</h3>
        <form method="POST" action="<?= URL_BASE ?>/locacao_salas">
            <input type="hidden" name="action" value="add_booking">
            <?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label class="form-label">Sala</label>
                <select name="room_id" class="form-select" required>
                    <?php foreach($rooms as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Data</label>
                <input type="date" name="booking_date" class="form-input" required>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Início</label>
                    <input type="time" name="start_time" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Fim</label>
                    <input type="time" name="end_time" class="form-input" required>
                </div>
            </div>
            <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                <button type="button" onclick="document.getElementById('bookingModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary">Reservar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Nova Sala -->
<div id="roomModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
    <div class="glass-panel" style="max-width: 400px; width: 100%;">
        <h3>Adicionar Sala</h3>
        <form method="POST" action="<?= URL_BASE ?>/locacao_salas">
            <input type="hidden" name="action" value="add_room">
            <?= \App\Core\Csrf::field() ?>
            <div class="form-group">
                <label class="form-label">Nome da Sala</label>
                <input type="text" name="name" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Descrição</label>
                <textarea name="description" class="form-textarea"></textarea>
            </div>
            <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                <button type="button" onclick="document.getElementById('roomModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary">Adicionar</button>
            </div>
        </form>
    </div>
</div>
