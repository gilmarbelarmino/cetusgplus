<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Model;
use App\Core\Auth;
use App\Core\Logger;

class DashboardController extends Controller {
    
    public function index() {
        $pdo = Model::getConnection();
        
        // Log de acesso
        Logger::access('dashboard');

        // 1. Locação de Salas
        try {
            $totalRooms = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn() ?: 0;
            $totalBookings = $pdo->query("SELECT COUNT(*) FROM room_bookings")->fetchColumn() ?: 0;
            $bookingsByRoom = $pdo->query("SELECT r.name, COUNT(b.id) as count FROM room_bookings b JOIN rooms r ON b.room_id = r.id GROUP BY r.id LIMIT 5")->fetchAll();
            $occupancyRate = $totalRooms > 0 ? ($totalBookings / ($totalRooms * 5)) * 100 : 0;
            if ($occupancyRate > 100) $occupancyRate = 100;
        } catch(\Exception $e) { 
            $totalRooms = 0; $totalBookings = 0; $bookingsByRoom = []; $occupancyRate = 0;
        }

        // 2. Voluntariado
        try {
            $totalVolunteers = $pdo->query("SELECT COUNT(*) FROM volunteers")->fetchColumn() ?: 0;
            $totalHours = $pdo->query("SELECT SUM(total_hours) as total FROM volunteers")->fetchColumn() ?: 0;
            $financialReturn = $pdo->query("SELECT SUM(total_hours * hourly_rate) as total FROM volunteers")->fetchColumn() ?: 0;
            $activeVol = $pdo->query("SELECT COUNT(*) FROM volunteers WHERE status = 'Ativo'")->fetchColumn() ?: 0;
            $activePerc = $totalVolunteers > 0 ? ($activeVol / $totalVolunteers) * 100 : 0;
        } catch(\Exception $e) { 
            $totalVolunteers = 0; $totalHours = 0; $financialReturn = 0; $activeVol = 0; $activePerc = 0;
        }

        // 3. Chamados
        $openTickets = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'Aberto'")->fetchColumn() ?: 0;
        
        // 4. Últimas Atividades (Audit Logs)
        $recentLogs = $pdo->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 6")->fetchAll();

        return $this->view('dashboard', [
            'totalRooms' => $totalRooms,
            'totalBookings' => $totalBookings,
            'bookingsByRoom' => $bookingsByRoom,
            'occupancyRate' => $occupancyRate,
            'totalVolunteers' => $totalVolunteers,
            'totalHours' => $totalHours,
            'financialReturn' => $financialReturn,
            'activeVol' => $activeVol,
            'activePerc' => $activePerc,
            'openTickets' => $openTickets,
            'recentLogs' => $recentLogs
        ]);
    }
}
