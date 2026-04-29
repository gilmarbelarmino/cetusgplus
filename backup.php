<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'SimpleXLSXGen.php';

$user = getCurrentUser($pdo);
if (!$user || ($user['role'] != 'Administrador' && $user['role'] != 'Suporte Técnico')) {
    die('Acesso negado');
}

// Criar diretório de backups se não existir
if (!file_exists('backups')) {
    mkdir('backups', 0777, true);
}

$timestamp = date('Y-m-d_H-i-s');
$filename = "backup_cetusg_{$timestamp}.xlsx";
$filepath = "backups/{$filename}";

// Configuração de Tabelas e Nomes das Abas (Organização Profissional)
$tables = [
    // Core e Configurações
    'company_settings'    => 'Config Gerais',
    'units'               => 'Unidades',
    'sectors'             => 'Setores',
    'rh_positions'        => 'Cargos',
    'users'               => 'Usuários',
    'user_menus'          => 'Permissões Menus',
    
    // Recursos Humanos (RH)
    'rh_employee_details' => 'RH - Detalhes',
    'rh_vacations'        => 'RH - Férias',
    'rh_certificates'     => 'RH - Atestados',
    'rh_notes'            => 'RH - Observações',
    
    // Patrimônio e Operações
    'assets'              => 'Patrimônio',
    'loans'               => 'Empréstimos',
    'tickets'             => 'Chamados',
    
    // Compras e Orçamentos
    'budget_requests'     => 'Orçamentos',
    'budget_quotes'       => 'Cotações',
    
    // Voluntariado
    'volunteers'          => 'Voluntários',
    'volunteer_hours'     => 'Voluntariado - Horas',
    'volunteer_history'   => 'Voluntariado - Histórico',
    
    // Salas e Espaços
    'rooms'               => 'Salas',
    'room_bookings'       => 'Reservas de Salas',
    
    // Semanada
    'semanada_uploads'    => 'Semanada - Arquivos',
    'semanada_comments'   => 'Semanada - Mural',
    
    // Tecnologia e Outros
    'tech_cameras'        => 'Tec - Câmeras',
    'tech_emails'         => 'Tec - E-mails',
    'tech_remote_access'  => 'Tec - Acessos Remotos',
    'info_messages'       => 'Info - Mensagens',
    'info_links'          => 'Info - Links',
    'announcements'       => 'Avisos Gerais',
    
    // Sistema e Auditoria
    'chat_messages'       => 'Chat - Mensagens',
    'login_logs'          => 'Logs de Acesso'
];

// Criar arquivo Excel
$xlsx = new SimpleXLSXGen();

$count = 0;
foreach ($tables as $table => $sheet_name) {
    try {
        // Verificar se a tabela existe antes de tentar o Select
        $checkTable = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($checkTable->rowCount() == 0) continue;

        // Buscar dados da tabela
        $stmt = $pdo->query("SELECT * FROM {$table}");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Se a tabela estiver vazia, adicionamos apenas os cabeçalhos para manter a estrutura
        if (empty($data)) {
            // Pegar nomes das colunas via DESCRIBE
            $colsStmt = $pdo->query("DESCRIBE {$table}");
            $headers = array_column($colsStmt->fetchAll(), 'Field');
            $sheetData = [$headers];
        } else {
            // Preparar dados para a planilha
            $sheetData = [];
            // Cabeçalhos (Keys do primeiro array)
            $sheetData[] = array_keys($data[0]);
            // Dados (Values de cada linha)
            foreach ($data as $row) {
                $sheetData[] = array_values($row);
            }
        }
        
        // Adicionar aba
        $xlsx->addSheet($sheetData, $sheet_name);
        $count++;
    } catch (Exception $e) {
        continue;
    }
}

if ($count === 0) {
    die('Nenhum dado encontrado para backup.');
}

// Salvar arquivo
$xlsx->saveAs($filepath);

// Download
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;
?>
