<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $json_data = json_decode(file_get_contents('php://input'), true);
    $action = $_POST['action'] ?? $_GET['action'] ?? $json_data['action'] ?? '';
    
    // Autenticacao via Payload/Query para o React
    $current_id = $_POST['user_id'] ?? $_GET['user_id'] ?? $json_data['user_id'] ?? null;

    if (!$action && isset($json_data['action'])) {
        $_POST = $json_data;
    }

    if ($method === 'GET' && empty($action)) {
        $rooms = $pdo->query("SELECT * FROM rooms ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $bookings = $pdo->query("SELECT b.*, r.name as room_name, u.name as user_name, u.avatar_url as user_avatar, 
                                ed.name as editor_name, ed.avatar_url as editor_avatar
                                FROM room_bookings b 
                                JOIN rooms r ON b.room_id = r.id
                                JOIN users u ON BINARY b.user_id = BINARY u.id
                                LEFT JOIN users ed ON BINARY b.last_edited_by = BINARY ed.id
                                ORDER BY b.booking_date DESC, b.start_time ASC")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => ['rooms' => $rooms, 'bookings' => $bookings]]);
        exit;
    }

    if ($method === 'POST') {
        $data = $json_data ?? $_POST;
        
        // Handlers de Salas (Nao tem auth forte no back pq é API separada pra REACT, confiamos no front ou na query)
        if ($action === 'add_room') {
            $stmt = $pdo->prepare("INSERT INTO rooms (id, name, description) VALUES (?, ?, ?)");
            $stmt->execute(['R' . time(), $data['name'], $data['description']]);
            echo json_encode(['success' => true, 'message' => 'Sala adicionada']);
            exit;
        }

        if ($action === 'edit_room') {
            $stmt = $pdo->prepare("UPDATE rooms SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$data['name'], $data['description'], $data['room_id']]);
            echo json_encode(['success' => true, 'message' => 'Sala atualizada']);
            exit;
        }

        if ($action === 'delete_room') {
            $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
            $stmt->execute([$data['room_id']]);
            echo json_encode(['success' => true, 'message' => 'Sala removida']);
            exit;
        }

        // Handlers de Reservas
        if ($action === 'add_booking') {
            $check = $pdo->prepare("SELECT COUNT(*) FROM room_bookings 
                                   WHERE room_id = ? AND booking_date = ? 
                                   AND start_time < ? AND end_time > ? AND status = 'Aprovado'");
            $check->execute([$data['room_id'], $data['booking_date'], $data['end_time'], $data['start_time']]);
            
            if ($check->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'error' => 'conflito']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO room_bookings (id, room_id, user_id, booking_date, start_time, end_time, observations, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Aprovado')");
            $stmt->execute(['B' . time(), $data['room_id'], $current_id, $data['booking_date'], $data['start_time'], $data['end_time'], $data['observations']]);
            echo json_encode(['success' => true, 'message' => 'Reserva adicionada']);
            exit;
        }

        if ($action === 'waitlist_booking') {
            $stmt = $pdo->prepare("INSERT INTO room_bookings (id, room_id, user_id, booking_date, start_time, end_time, observations, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'Fila de Espera')");
            $stmt->execute(['B' . time(), $data['room_id'], $current_id, $data['booking_date'], $data['start_time'], $data['end_time'], $data['observations']]);
            echo json_encode(['success' => true, 'message' => 'Na fila de espera']);
            exit;
        }

        if ($action === 'edit_booking') {
            $check = $pdo->prepare("SELECT COUNT(*) FROM room_bookings 
                                   WHERE room_id = ? AND booking_date = ? AND id != ?
                                   AND start_time < ? AND end_time > ? AND status = 'Aprovado'");
            $check->execute([$data['room_id'], $data['booking_date'], $data['booking_id'], $data['end_time'], $data['start_time']]);
            
            if ($check->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'error' => 'conflito']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE room_bookings SET room_id = ?, booking_date = ?, start_time = ?, end_time = ?, observations = ?, last_edited_by = ?, last_edited_at = NOW() WHERE id = ?");
            $stmt->execute([$data['room_id'], $data['booking_date'], $data['start_time'], $data['end_time'], $data['observations'], $current_id, $data['booking_id']]);
            echo json_encode(['success' => true, 'message' => 'Reserva atualizada']);
            exit;
        }

        if ($action === 'delete_booking') {
            $stmt = $pdo->prepare("SELECT * FROM room_bookings WHERE id = ?");
            $stmt->execute([$data['booking_id']]);
            $deleted = $stmt->fetch();

            if ($deleted) {
                $stmt = $pdo->prepare("DELETE FROM room_bookings WHERE id = ?");
                $stmt->execute([$data['booking_id']]);
                
                if ($deleted['status'] === 'Aprovado') {
                    $checkW = $pdo->prepare("SELECT * FROM room_bookings WHERE room_id = ? AND booking_date = ? AND start_time < ? AND end_time > ? AND status = 'Fila de Espera' ORDER BY start_time ASC LIMIT 1");
                    $checkW->execute([$deleted['room_id'], $deleted['booking_date'], $deleted['end_time'], $deleted['start_time']]);
                    $waitlist = $checkW->fetch();
                    
                    if ($waitlist) {
                        $pdo->prepare("UPDATE room_bookings SET status = 'Aprovado' WHERE id = ?")->execute([$waitlist['id']]);
                        
                        $roomName = $pdo->prepare("SELECT name FROM rooms WHERE id = ?");
                        $roomName->execute([$waitlist['room_id']]);
                        $rName = $roomName->fetchColumn();

                        require_once 'peixinho_api.php';
                        $msg = "Olá! A sala **{$rName}** que você estava na fila de espera no dia " . date('d/m/Y', strtotime($waitlist['booking_date'])) . " foi liberada e sua reserva foi **APROVADA** automaticamente! 🐠";
                        $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, content, type, is_read, read_at) VALUES ('U_PEIXINHO', ?, ?, 'text', 0, NULL)")->execute([$waitlist['user_id'], $msg]);
                    }
                }
            }
            echo json_encode(['success' => true, 'message' => 'Reserva removida']);
            exit;
        }
    }

    echo json_encode(['success' => false, 'error' => 'Ação inválida']);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
