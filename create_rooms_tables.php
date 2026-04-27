<?php
require 'config.php';
try {
    // Tabela de Salas
    $pdo->exec("CREATE TABLE IF NOT EXISTS rooms (
        id VARCHAR(50) PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Tabela de Reservas
    $pdo->exec("CREATE TABLE IF NOT EXISTS room_bookings (
        id VARCHAR(50) PRIMARY KEY,
        room_id VARCHAR(50) NOT NULL,
        user_id VARCHAR(50) NOT NULL,
        booking_date DATE NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        observations TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_edited_by VARCHAR(50) NULL,
        last_edited_at DATETIME NULL,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Inserir salas padrão se não houver nenhuma
    $count = $pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
    if ($count == 0) {
        $rooms = [
            ['R1', 'Sala CJ 01', 'Sala de reuniões pequena'],
            ['R2', 'Sala CJ 02', 'Sala de reuniões média'],
            ['R3', 'Sala CJ 03', 'Sala de reuniões grande'],
            ['R4', 'Auditório', 'Espaço para grandes eventos'],
            ['R5', 'Sala de Reunião', 'Sala de reunião oficial'],
            ['R6', 'Sala Cultural', 'Espaço dedicado a eventos culturais']
        ];
        $stmt = $pdo->prepare("INSERT INTO rooms (id, name, description) VALUES (?, ?, ?)");
        foreach ($rooms as $room) {
            $stmt->execute($room);
        }
    }

    echo "Tabelas rooms e room_bookings criadas com sucesso!\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
?>
