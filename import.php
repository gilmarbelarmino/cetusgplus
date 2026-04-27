<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'SimpleXLSX.php';

session_start();
$user = getCurrentUser($pdo);
if (!$user || ($user['role'] != 'Administrador' && $user['role'] != 'Suporte Técnico')) {
    die('Acesso negado');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup_file'])) {
    $file = $_FILES['backup_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        header('Location: ?page=configuracoes&error=upload');
        exit;
    }
    
// Mapeamento reverso: Nome da Aba -> Nome da Tabela
    $tables_map = [
        'Config Gerais'              => 'company_settings',
        'Unidades'                   => 'units',
        'Salas'                      => 'rooms',
        'Cargos'                     => 'rh_positions',
        'Usuários'                   => 'users',
        'Setores'                    => 'sectors',
        'Patrimônio'                 => 'assets',
        'Voluntários'                => 'volunteers',
        'Voluntariado - Horas'       => 'volunteer_hours',
        'Voluntariado - Histórico'   => 'volunteer_history',
        'Reservas de Salas'          => 'room_bookings',
        'Chamados'                   => 'tickets',
        'Empréstimos'                => 'loans',
        'Orçamentos'                 => 'budget_requests',
        'Cotações'                   => 'budget_quotes',
        'Permissões Menus'           => 'user_menus',
        'RH - Detalhes'              => 'rh_employee_details',
        'RH - Férias'                => 'rh_vacations',
        'RH - Atestados'             => 'rh_certificates',
        'RH - Observações'           => 'rh_notes',
        'Semanada - Arquivos'        => 'semanada_uploads',
        'Semanada - Mural'           => 'semanada_comments',
        'Tec - Câmeras'              => 'tech_cameras',
        'Tec - E-mails'              => 'tech_emails',
        'Tec - Acessos Remotos'      => 'tech_remote_access',
        'Info - Mensagens'           => 'info_messages',
        'Info - Links'               => 'info_links',
        'Avisos Gerais'              => 'announcements',
        'Chat - Mensagens'           => 'chat_messages',
        'Logs de Acesso'             => 'login_logs'
    ];

    // Ordem de processamento para respeitar Chaves Estrangeiras (FK)
    $processing_order = array_values($tables_map);
    
    try {
        $xlsx = new SimpleXLSX($file['tmp_name']);
        $allSheets = $xlsx->getSheets();
        
        $pdo->beginTransaction();
        
        // Desabilitar verificação de chaves estrangeiras temporariamente para importação em massa
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        foreach ($processing_order as $target_table) {
            // Encontrar a aba correspondente para esta tabela no array associativo
            $targetSheetName = '';
            foreach ($tables_map as $sheetName => $tableName) {
                if ($tableName === $target_table) {
                    $targetSheetName = $sheetName;
                    break;
                }
            }

            if (!$targetSheetName || !isset($allSheets[$targetSheetName])) continue;
            
            $sheetData = $allSheets[$targetSheetName];
            if (empty($sheetData) || count($sheetData) < 2) continue; // Pelo menos header + 1 linha
            
            // Primeira linha são os cabeçalhos
            $headers = array_shift($sheetData);
            
            // Construir Query Dinâmica (INSERT ... ON DUPLICATE KEY UPDATE)
            $cols = implode('`, `', $headers);
            $placeholders = implode(',', array_fill(0, count($headers), '?'));
            
            // Gerar parte do UPDATE: col1=VALUES(col1), col2=VALUES(col2)...
            $updateParts = [];
            foreach ($headers as $h) {
                $updateParts[] = "`$h` = VALUES(`$h`)";
            }
            $updateSql = implode(', ', $updateParts);
            
            $sql = "INSERT INTO `{$target_table}` (`{$cols}`) VALUES ({$placeholders}) ON DUPLICATE KEY UPDATE {$updateSql}";
            $stmt = $pdo->prepare($sql);
            
            // Inserir/Atualizar dados
            foreach ($sheetData as $row) {
                // Limpar dados e garantir contagem correta
                $rowValues = array_slice($row, 0, count($headers));
                // Preencher valores faltantes se a linha for menor que o header
                while(count($rowValues) < count($headers)) $rowValues[] = null;
                
                try {
                    $stmt->execute($rowValues);
                } catch (Exception $e) {
                    // Log de erro silencioso por linha para não travar o backup todo
                    continue;
                }
            }
        }
        
        // Reativar verificação de chaves estrangeiras
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        $pdo->commit();
        header('Location: ?page=configuracoes&success=import');
        exit;
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        header('Location: ?page=configuracoes&error=import');
        exit;
    }
}
?>
