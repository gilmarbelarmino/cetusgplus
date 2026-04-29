<?php
// Acesso protegido via index.php

// Handlers de Salas (Apenas Admin)
if ($user['role'] === 'Administrador') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'add_room') {
            $stmt = $pdo->prepare("INSERT INTO rooms (id, name, description) VALUES (?, ?, ?)");
            $stmt->execute(['R' . time(), $_POST['name'], $_POST['description']]);
            header('Location: ?page=locacao_salas&success=1');
            exit;
        }
        if ($_POST['action'] === 'edit_room') {
            $stmt = $pdo->prepare("UPDATE rooms SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$_POST['name'], $_POST['description'], $_POST['room_id']]);
            header('Location: ?page=locacao_salas&success=2');
            exit;
        }
        if ($_POST['action'] === 'delete_room') {
            $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
            $stmt->execute([$_POST['room_id']]);
            header('Location: ?page=locacao_salas&success=3');
            exit;
        }
    }
}

// Handlers de Reservas (Todos)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_booking') {
        $check = $pdo->prepare("SELECT COUNT(*) FROM room_bookings 
                               WHERE room_id = ? AND booking_date = ? 
                               AND start_time < ? AND end_time > ? AND status = 'Aprovado'");
        $check->execute([$_POST['room_id'], $_POST['booking_date'], $_POST['end_time'], $_POST['start_time']]);
        
        if ($check->fetchColumn() > 0) {
            $params = http_build_query([
                'page' => 'locacao_salas',
                'error' => 'conflito',
                'room_id' => $_POST['room_id'],
                'booking_date' => $_POST['booking_date'],
                'start_time' => $_POST['start_time'],
                'end_time' => $_POST['end_time'],
                'obs' => $_POST['observations']
            ]);
            header("Location: ?$params");
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO room_bookings (id, room_id, user_id, booking_date, start_time, end_time, observations, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Aprovado')");
        $stmt->execute(['B' . time(), $_POST['room_id'], $user['id'], $_POST['booking_date'], $_POST['start_time'], $_POST['end_time'], $_POST['observations']]);
        header('Location: ?page=locacao_salas&success=4');
        exit;
    }

    if ($_POST['action'] === 'waitlist_booking') {
        $stmt = $pdo->prepare("INSERT INTO room_bookings (id, room_id, user_id, booking_date, start_time, end_time, observations, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Fila de Espera')");
        $stmt->execute(['B' . time(), $_POST['room_id'], $user['id'], $_POST['booking_date'], $_POST['start_time'], $_POST['end_time'], $_POST['observations']]);
        header('Location: ?page=locacao_salas&success=7');
        exit;
    }

    if ($_POST['action'] === 'edit_booking') {
        $check = $pdo->prepare("SELECT COUNT(*) FROM room_bookings 
                               WHERE room_id = ? AND booking_date = ? AND id != ?
                               AND start_time < ? AND end_time > ? AND status = 'Aprovado'");
        $check->execute([$_POST['room_id'], $_POST['booking_date'], $_POST['booking_id'], $_POST['end_time'], $_POST['start_time']]);
        
        if ($check->fetchColumn() > 0) {
            header('Location: ?page=locacao_salas&error=conflito');
            exit;
        }

        $stmt = $pdo->prepare("UPDATE room_bookings SET room_id = ?, booking_date = ?, start_time = ?, end_time = ?, observations = ?, last_edited_by = ?, last_edited_at = NOW() WHERE id = ?");
        $stmt->execute([$_POST['room_id'], $_POST['booking_date'], $_POST['start_time'], $_POST['end_time'], $_POST['observations'], $user['id'], $_POST['booking_id']]);
        header('Location: ?page=locacao_salas&success=5');
        exit;
    }

    if ($_POST['action'] === 'delete_booking') {
        $stmt = $pdo->prepare("SELECT * FROM room_bookings WHERE id = ?");
        $stmt->execute([$_POST['booking_id']]);
        $deleted = $stmt->fetch();

        $stmt = $pdo->prepare("DELETE FROM room_bookings WHERE id = ?");
        $stmt->execute([$_POST['booking_id']]);
        
        // Waitlist logic
        if ($deleted && $deleted['status'] === 'Aprovado') {
            $checkW = $pdo->prepare("SELECT * FROM room_bookings WHERE room_id = ? AND booking_date = ? AND start_time < ? AND end_time > ? AND status = 'Fila de Espera' ORDER BY start_time ASC LIMIT 1");
            $checkW->execute([$deleted['room_id'], $deleted['booking_date'], $deleted['end_time'], $deleted['start_time']]);
            $waitlist = $checkW->fetch();
            
            if ($waitlist) {
                // Aprovar
                $pdo->prepare("UPDATE room_bookings SET status = 'Aprovado' WHERE id = ?")->execute([$waitlist['id']]);
                
                // Mensagem Peixinho
                $roomName = $pdo->prepare("SELECT name FROM rooms WHERE id = ?");
                $roomName->execute([$waitlist['room_id']]);
                $rName = $roomName->fetchColumn();

                $msg = "Olá! A sala **{$rName}** que você estava na fila de espera no dia " . date('d/m/Y', strtotime($waitlist['booking_date'])) . " foi liberada e sua reserva foi **APROVADA** automaticamente! 🐠";
                $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, content, type, is_read, read_at) VALUES ('U_PEIXINHO', ?, ?, 'text', 0, NULL)")->execute([$waitlist['user_id'], $msg]);
            }
        }
        
        header('Location: ?page=locacao_salas&success=6');
        exit;
    }
}

// Buscar dados
$rooms = $pdo->query("SELECT * FROM rooms ORDER BY name")->fetchAll();
$bookings = $pdo->query("SELECT b.*, r.name as room_name, u.name as user_name, u.avatar_url as user_avatar, 
                        ed.name as editor_name, ed.avatar_url as editor_avatar
                        FROM room_bookings b 
                        JOIN rooms r ON b.room_id = r.id
                        JOIN users u ON b.user_id = u.id
                        LEFT JOIN users ed ON b.last_edited_by = ed.id
                        ORDER BY b.booking_date DESC, b.start_time ASC")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h2 style="font-size: 1.5rem; font-weight: 900; color: var(--text-main); display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-building-circle-check" style="color: var(--crm-purple);"></i>
            Locação de Salas
        </h2>
        <p style="color: var(--text-soft); font-size: 0.875rem;">Gerencie o uso compartilhado dos espaços da instituição</p>
    </div>
    <div style="display: flex; gap: 1rem;">
        <button class="btn-primary" onclick="document.getElementById('bookingModal').style.display='flex'">
            <i class="fa-solid fa-calendar-plus"></i> Reservar Sala
        </button>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
    <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #10B981; padding: 1rem; border-radius: 0.75rem; margin-bottom: 2rem; font-weight: 600;">
        <i class="fa-solid fa-circle-check"></i>
        <?php
        switch($_GET['success']) {
            case '1': echo "Sala cadastrada com sucesso!"; break;
            case '2': echo "Sala atualizada com sucesso!"; break;
            case '3': echo "Sala removida com sucesso!"; break;
            case '4': echo "Reserva realizada com sucesso!"; break;
            case '5': echo "Reserva atualizada com sucesso!"; break;
            case '6': echo "Reserva removida com sucesso!"; break;
            case '7': echo "Você entrou na fila de espera. Avisaremos pelo chat IA se a sala for liberada!"; break;
        }
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error']) && $_GET['error'] === 'conflito'): ?>
    <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #EF4444; padding: 1.5rem; border-radius: 0.75rem; margin-bottom: 2rem; font-weight: 600;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-triangle-exclamation" style="font-size: 1.5rem;"></i>
                <div>
                    <h4 style="margin: 0; font-size: 1.1rem;">Ops! Conflito de Horário</h4>
                    <p style="margin: 0.25rem 0 0 0; font-size: 0.9rem; opacity: 0.9;">ESSA SALA ESTA AGENDADA POR OUTRA PESSOA, ENTRE NA FILA DE ESPERA.</p>
                </div>
            </div>
            <?php if (isset($_GET['room_id'])): ?>
            <form method="POST" style="margin: 0;">
                <input type="hidden" name="action" value="waitlist_booking">
                <input type="hidden" name="room_id" value="<?= htmlspecialchars($_GET['room_id']) ?>">
                <input type="hidden" name="booking_date" value="<?= htmlspecialchars($_GET['booking_date']) ?>">
                <input type="hidden" name="start_time" value="<?= htmlspecialchars($_GET['start_time']) ?>">
                <input type="hidden" name="end_time" value="<?= htmlspecialchars($_GET['end_time']) ?>">
                <input type="hidden" name="observations" value="<?= htmlspecialchars($_GET['obs'] ?? '') ?>">
                <button type="submit" class="btn-primary" style="background: #F59E0B; border-color: #F59E0B;">
                    <i class="fa-solid fa-clock"></i> Lista de Espera 
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Sistema de Abas -->
<div style="display: flex; gap: 1.5rem; border-bottom: 2px solid #e2e8f0; margin-bottom: 2rem; padding-bottom: 0.5rem;">
    <button onclick="switchTab('reservas')" id="tab-reservas" class="tab-btn active">Reservas Ativas</button>
    <?php if ($user['role'] === 'Administrador'): ?>
        <button onclick="switchTab('salas')" id="tab-salas" class="tab-btn">Configurações de Salas</button>
    <?php endif; ?>
</div>

<style>
    .tab-btn { background: none; border: none; font-weight: 700; color: var(--text-soft); cursor: pointer; padding: 0.5rem 1rem; border-radius: 0.5rem; transition: all 0.3s; }
    .tab-btn.active { color: var(--crm-purple); background: rgba(91, 33, 182, 0.1); }
    .tab-content { display: none; }
    .tab-content.active { display: block; animation: fadeIn 0.3s ease-out; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    
    .room-card { background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 1rem; padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; transition: all 0.2s; }
    .room-card:hover { transform: translateY(-2px); border-color: var(--crm-purple); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }

    .booking-card { background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); border-radius: 1.25rem; overflow: hidden; margin-bottom: 1.5rem; transition: all 0.2s; border-left: 6px solid var(--crm-purple); }
    .booking-card:hover { box-shadow: 0 12px 20px -5px rgba(0,0,0,0.1); }
    .booking-header { padding: 1.25rem; background: var(--bg-main); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
    .booking-body { padding: 1.25rem; display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }
</style>

<!-- ABA RESERVAS -->
<div id="content-reservas" class="tab-content active">
    <?php if (empty($bookings)): ?>
        <div style="text-align: center; padding: 4rem; color: var(--text-soft);" class="glass-panel">
            <i class="fa-solid fa-calendar-xmark" style="font-size: 3rem; opacity: 0.2; margin-bottom: 1rem;"></i>
            <p>Nenhuma reserva encontrada. Seja o primeiro a reservar!</p>
        </div>
    <?php else: ?>
        <?php foreach ($bookings as $b): ?>
            <div class="booking-card">
                <div class="booking-header">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <span style="font-weight: 800; font-size: 1.1rem; color: var(--text-main);"><?= htmlspecialchars($b['room_name']) ?></span>
                        <span class="badge badge-info"><?= date('d/m/Y', strtotime($b['booking_date'])) ?></span>
                        <?php if (isset($b['status']) && $b['status'] == 'Fila de Espera'): ?>
                            <span class="badge badge-warning" style="background: #FEF3C7; color: #D97706; border: 1px solid #FCD34D;">
                                <i class="fa-solid fa-clock"></i> Espera
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if ($user['role'] === 'Administrador' || $user['id'] === $b['user_id']): ?>
                        <div style="display: flex; gap: 0.5rem;">
                            <button class="btn-icon" onclick='openEditBooking(<?= json_encode($b) ?>)' title="Editar Reserva">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Excluir esta reserva?')">
                                <input type="hidden" name="action" value="delete_booking">
                                <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                                <button type="submit" class="btn-icon" style="color: #EF4444;">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="booking-body">
                    <div>
                        <span style="font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase;">Período</span>
                        <div style="font-weight: 700; color: var(--text-main); margin-top: 0.35rem;">
                            <i class="fa-regular fa-clock" style="color: var(--crm-purple);"></i>
                            <?= date('H:i', strtotime($b['start_time'])) ?> às <?= date('H:i', strtotime($b['end_time'])) ?>
                        </div>
                    </div>
                    <div>
                        <span style="font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase;">Responsável</span>
                        <div style="display: flex; align-items: center; gap: 0.75rem; margin-top: 0.35rem;">
                            <?php if ($b['user_avatar']): ?>
                                <img src="<?= htmlspecialchars($b['user_avatar']) ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <?php else: ?>
                                <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--crm-purple); color: white; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700;">
                                    <?= strtoupper(substr($b['user_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <span style="font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($b['user_name']) ?></span>
                        </div>
                    </div>
                    <div>
                        <span style="font-size: 0.7rem; font-weight: 800; color: #94a3b8; text-transform: uppercase;">Observações</span>
                        <div style="font-weight: 600; color: var(--text-soft); font-size: 0.85rem; margin-top: 0.35rem;">
                            <?= htmlspecialchars($b['observations'] ?: 'Nenhuma observação') ?>
                        </div>
                    </div>
                </div>
                <?php if ($b['last_edited_by']): ?>
                    <div style="padding: 0.75rem 1.25rem; background: var(--bg-card); color: var(--text-main); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; font-size: 0.75rem; color: #92400e;">
                        <span style="display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-user-pen"></i>
                            Alterado por <strong><?= htmlspecialchars($b['editor_name']) ?></strong> em <?= date('d/m/Y \à\s H:i', strtotime($b['last_edited_at'])) ?>
                        </span>
                        <?php if ($b['editor_avatar']): ?>
                            <img src="<?= htmlspecialchars($b['editor_avatar']) ?>" style="width: 20px; height: 20px; border-radius: 50%; object-fit: cover;">
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ABA SALAS (ADMIN) -->
<div id="content-salas" class="tab-content">
    <div style="display: flex; justify-content: flex-end; margin-bottom: 1.5rem;">
        <button class="btn-primary" onclick="document.getElementById('roomModal').style.display='flex'; document.getElementById('roomAction').value='add_room';">
            <i class="fa-solid fa-plus"></i> Adicionar Nova Sala
        </button>
    </div>
    <div id="roomsList">
        <?php foreach ($rooms as $r): ?>
            <div class="room-card">
                <div>
                    <h4 style="font-weight: 800; color: var(--text-main);"><?= htmlspecialchars($r['name']) ?></h4>
                    <p style="font-size: 0.8rem; color: var(--text-soft);"><?= htmlspecialchars($r['description']) ?></p>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn-icon" onclick='openEditRoom(<?= json_encode($r) ?>)'>
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Deseja realmente excluir esta sala?')">
                        <input type="hidden" name="action" value="delete_room">
                        <input type="hidden" name="room_id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn-icon" style="color: #EF4444;">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modais -->
<div id="bookingModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 500px; width: 100%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 id="bookingTitle" style="font-size: 1.25rem; font-weight: 900; color: var(--text-main);">Nova Reserva</h3>
            <button onclick="document.getElementById('bookingModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: var(--text-soft); font-size: 1.5rem;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" id="bookingAction" value="add_booking">
            <input type="hidden" name="booking_id" id="edit_booking_id">
            <div class="form-group">
                <label class="form-label" style="color: var(--text-main);">Sala *</label>
                <select name="room_id" id="bookingRoom" class="form-select" required style="background: var(--bg-main); color: var(--text-main); border: 1px solid var(--border-color);">
                    <?php foreach ($rooms as $r): ?>
                        <option value="<?= $r['id'] ?>" style="background: var(--bg-card); color: var(--text-main);"><?= htmlspecialchars($r['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" style="color: var(--text-main);">Data *</label>
                <input type="date" name="booking_date" id="bookingDate" class="form-input" min="<?= date('Y-m-d') ?>" required style="background: var(--bg-main); color: var(--text-main); border: 1px solid var(--border-color);">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" style="color: var(--text-main);">Horário Início *</label>
                    <input type="time" name="start_time" id="bookingStart" class="form-input" required style="background: var(--bg-main); color: var(--text-main); border: 1px solid var(--border-color);">
                </div>
                <div class="form-group">
                    <label class="form-label" style="color: var(--text-main);">Horário Fim *</label>
                    <input type="time" name="end_time" id="bookingEnd" class="form-input" required style="background: var(--bg-main); color: var(--text-main); border: 1px solid var(--border-color);">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" style="color: var(--text-main);">Observações</label>
                <textarea name="observations" id="bookingObs" class="form-textarea" rows="3" style="background: var(--bg-main); color: var(--text-main); border: 1px solid var(--border-color);"></textarea>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="button" onclick="document.getElementById('bookingModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary">Confirmar Reserva</button>
            </div>
        </form>
    </div>
</div>

<div id="roomModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center; padding: 2rem;">
    <div class="glass-panel" style="max-width: 500px; width: 100%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h3 id="roomTitle" style="font-size: 1.25rem; font-weight: 900; color: var(--text-main);">Gerenciar Sala</h3>
            <button onclick="document.getElementById('roomModal').style.display='none'" style="background: none; border: none; cursor: pointer; color: var(--text-soft); font-size: 1.5rem;">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" id="roomAction" value="add_room">
            <input type="hidden" name="room_id" id="edit_room_id">
            <div class="form-group">
                <label class="form-label" style="color: var(--text-main);">Nome da Sala *</label>
                <input type="text" name="name" id="roomName" class="form-input" placeholder="Ex: Sala CJ 01" required style="background: var(--bg-main); color: var(--text-main); border: 1px solid var(--border-color);">
            </div>
            <div class="form-group">
                <label class="form-label" style="color: var(--text-main);">Descrição</label>
                <textarea name="description" id="roomDesc" class="form-textarea" placeholder="Breve descrição do espaço" rows="3" style="background: var(--bg-main); color: var(--text-main); border: 1px solid var(--border-color);"></textarea>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="button" onclick="document.getElementById('roomModal').style.display='none'" class="btn-secondary">Cancelar</button>
                <button type="submit" class="btn-primary">Salvar Sala</button>
            </div>
        </form>
    </div>
</div>

<script>
    function switchTab(tab) {
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('content-' + tab).classList.add('active');
        document.getElementById('tab-' + tab).classList.add('active');
    }

    function openEditRoom(room) {
        document.getElementById('roomTitle').innerText = 'Editar Sala';
        document.getElementById('roomAction').value = 'edit_room';
        document.getElementById('room_id').value = room.id;
        document.getElementById('edit_room_id').value = room.id;
        document.getElementById('roomName').value = room.name;
        document.getElementById('roomDesc').value = room.description;
        document.getElementById('roomModal').style.display = 'flex';
    }

    function openEditBooking(b) {
        document.getElementById('bookingTitle').innerText = 'Editar Reserva';
        document.getElementById('bookingAction').value = 'edit_booking';
        document.getElementById('edit_booking_id').value = b.id;
        document.getElementById('bookingRoom').value = b.room_id;
        document.getElementById('bookingDate').value = b.booking_date;
        document.getElementById('bookingStart').value = b.start_time;
        document.getElementById('bookingEnd').value = b.end_time;
        document.getElementById('bookingObs').value = b.observations;
        document.getElementById('bookingModal').style.display = 'flex';
    }

    // Validação de horários no front-end
    document.querySelector('#bookingModal form').onsubmit = function(e) {
        const start = document.getElementById('bookingStart').value;
        const end = document.getElementById('bookingEnd').value;
        if (start >= end) {
            alert('Atenção: O horário de término deve ser posterior ao horário de início.');
            e.preventDefault();
            return false;
        }
    };
</script>
