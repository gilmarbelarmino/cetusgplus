<?php
// API Dinâmica para vários Módulos Simples (Fase Acelerada)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 

require_once 'config.php';

$module = $_GET['module'] ?? '';

try {
    $data = [];
    
    if ($module === 'chamados') {
        $stmt = $pdo->query("SELECT t.*, u.name as unit_name, us.name as requester_name FROM tickets t LEFT JOIN units u ON t.unit_id = u.id LEFT JOIN users us ON t.requester_id = us.id ORDER BY t.created_at DESC LIMIT 100");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } 
    elseif ($module === 'patrimonio') {
        $stmt = $pdo->query("SELECT a.*, u.name as unit_name FROM assets a LEFT JOIN units u ON a.unit_id = u.id ORDER BY a.name ASC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    elseif ($module === 'emprestimos') {
        $stmt = $pdo->query("SELECT l.*, a.name as asset_name, u.name as borrower_name FROM loans l LEFT JOIN assets a ON l.asset_id = a.id LEFT JOIN users u ON l.borrower_id = u.id ORDER BY l.loan_date DESC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    elseif ($module === 'orcamentos') {
        $stmt = $pdo->query("SELECT b.*, u.name as requester_name FROM budget_requests b LEFT JOIN users u ON b.requester_id = u.id ORDER BY b.created_at DESC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    elseif ($module === 'voluntariado') {
        $stmt = $pdo->query("SELECT v.*, u.name as user_name FROM volunteers v LEFT JOIN users u ON v.user_id = u.id ORDER BY v.created_at DESC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    elseif ($module === 'locacao_salas') {
        $stmt = $pdo->query("SELECT r.*, u.name as user_name FROM room_bookings r LEFT JOIN users u ON r.user_id = u.id ORDER BY r.booking_date DESC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    elseif ($module === 'semanada') {
        $stmt = $pdo->query("SELECT s.* FROM semanada s ORDER BY s.date_start DESC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    elseif ($module === 'tecnologia') {
        $stmt = $pdo->query("SELECT t.* FROM tech_passwords t ORDER BY t.system_name ASC");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    // Retorna vazio em caso de erro (tabela pode nao existir se for dump limpo)
    echo json_encode(['success' => true, 'data' => []]);
}
