<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Model;
use App\Core\Auth;
use App\Core\Logger;

class BookingController extends Controller {
    
    public function index() {
        Auth::requirePermission('locacao_salas.view');
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();

        $rooms = $pdo->prepare("SELECT * FROM rooms WHERE company_id = ? ORDER BY name");
        $rooms->execute([$companyId]);
        $rooms = $rooms->fetchAll();

        $bookings = $pdo->prepare("SELECT b.*, r.name as room_name, u.name as user_name, u.avatar_url as user_avatar, 
                                  ed.name as editor_name, ed.avatar_url as editor_avatar
                                  FROM room_bookings b 
                                  JOIN rooms r ON b.room_id = r.id
                                  JOIN users u ON b.user_id = u.id
                                  LEFT JOIN users ed ON b.last_edited_by = ed.id
                                  WHERE b.company_id = ?
                                  ORDER BY b.booking_date DESC, b.start_time ASC");
        $bookings->execute([$companyId]);
        $bookings = $bookings->fetchAll();

        return $this->view('locacao_salas', [
            'rooms' => $rooms,
            'bookings' => $bookings
        ]);
    }

    public function store() {
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();
        $action = $_POST['action'] ?? '';

        if ($action === 'add_booking') {
            Auth::requirePermission('locacao_salas.create');
            
            // Lógica de conflito simplificada
            $check = $pdo->prepare("SELECT COUNT(*) FROM room_bookings 
                                   WHERE room_id = ? AND booking_date = ? 
                                   AND start_time < ? AND end_time > ? AND status = 'Aprovado' AND company_id = ?");
            $check->execute([$_POST['room_id'], $_POST['booking_date'], $_POST['end_time'], $_POST['start_time'], $companyId]);
            
            if ($check->fetchColumn() > 0) {
                return $this->redirect('/locacao_salas?error=conflito');
            }

            $stmt = $pdo->prepare("INSERT INTO room_bookings (id, company_id, room_id, user_id, booking_date, start_time, end_time, observations, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Aprovado')");
            $stmt->execute([
                'B' . time(), $companyId, $_POST['room_id'], $_SESSION['user_id'], 
                $_POST['booking_date'], $_POST['start_time'], $_POST['end_time'], $_POST['observations']
            ]);

            Logger::audit('create_booking', 'locacao_salas', 'Sala ID: ' . $_POST['room_id']);
            return $this->redirect('/locacao_salas?success=4');
        }

        if ($action === 'add_room') {
            Auth::requirePermission('configuracoes.edit');
            $stmt = $pdo->prepare("INSERT INTO rooms (id, company_id, name, description) VALUES (?, ?, ?, ?)");
            $stmt->execute(['R' . time(), $companyId, $_POST['name'], $_POST['description']]);
            
            Logger::audit('add_room', 'configuracoes', 'Sala: ' . $_POST['name']);
            return $this->redirect('/locacao_salas?success=1');
        }

        return $this->redirect('/locacao_salas');
    }
}
