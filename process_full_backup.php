<?php
require_once 'config.php';
require_once 'auth.php';
require_once 'SimpleXLSXGen.php';

if (php_sapi_name() !== 'cli') {
    $user = getCurrentUser();
    if (!$user || ($user['role'] != 'Administrador' && $user['role'] != 'Suporte Técnico')) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Acesso negado.']));
    }
}

// Iniciar buffer para evitar que qualquer erro/warning quebre o JSON
ob_start();

header('Content-Type: application/json; charset=utf-8');
set_time_limit(900); // 15 minutos
ini_set('memory_limit', '1024M');
error_reporting(0); // Silenciar avisos para não quebrar o JSON
ini_set('display_errors', 0);

// Verificar extensão ZIP logo no início
if (!class_exists('ZipArchive')) {
    echo json_encode([
        'success' => false,
        'message' => "ERRO: A extensão 'ZipArchive' não está habilitada no PHP.\nAbra o php.ini e remova o ';' da linha: ;extension=zip"
    ]);
    exit;
}

try {
    // ─── CONFIGURAÇÕES ────────────────────────────────────────────────────────────
    // Buscar caminho do banco de dados
    $compId = getCurrentUserCompanyId();
    $stmt = $pdo->prepare("SELECT backup_full_path FROM company_settings WHERE id = ?");
    $stmt->execute([$compId]);
    $dbPath = $stmt->fetchColumn();

    // Fallback caso não esteja configurado (Forçando para o OneDrive detectado)
    $backupDir   = $dbPath ?: 'D:/OneDrive - Arrastão Movimento de Promoção Humana/BACKUP_SISTEMA_CETUSG';
    $timestamp   = date('Y-m-d_H-i-s');
    $backupName  = "backup_cetusg_{$timestamp}";
    $sqlFile     = "{$backupDir}/{$backupName}.sql";
    $zipFile     = "{$backupDir}/{$backupName}.zip";
    $guideFile   = "{$backupDir}/{$backupName}_COMO_RESTAURAR.txt";

    // ─── 1. CRIAR DIRETÓRIO DE DESTINO ────────────────────────────────────────────
    if (!file_exists($backupDir)) {
        if (!@mkdir($backupDir, 0777, true)) {
            if (ob_get_length()) ob_clean();
            echo json_encode([
                'success' => false,
                'message' => "ERRO: Não foi possível criar a pasta de destino:\n{$backupDir}\n\nVerifique se o OneDrive está ativo e se você tem permissão de escrita."
            ]);
            exit;
        }
    }

    // ... (restante do código original) ...
    // Note: I will only replace the top and bottom to avoid repeating the whole file, 
    // but since I'm in a tool that needs exact matches, I'll be careful.
    
} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode([
        'success' => false,
        'message' => "ERRO CRÍTICO NO BACKUP:\n" . $e->getMessage()
    ]);
}

// ─── 2. EXPORTAR BANCO DE DADOS ───────────────────────────────────────────────
$mysqldumpPaths = [
    'C:\\xampp\\mysql\\bin\\mysqldump.exe',
    realpath(__DIR__ . '/../../mysql/bin/mysqldump.exe'),
    'mysqldump', // fallback PATH do sistema
];

$mysqldumpPath = null;
foreach ($mysqldumpPaths as $path) {
    if ($path && (file_exists($path) || $path === 'mysqldump')) {
        $mysqldumpPath = $path;
        break;
    }
}

$dbDumpOk = false;
$dbError   = '';

if ($mysqldumpPath) {
    $dbHost = DB_HOST;
    $dbName = DB_NAME;
    $dbUser = DB_USER;
    $dbPass = DB_PASS;

    $passArg = $dbPass ? "--password=" . escapeshellarg($dbPass) : '';
    $cmd = "\"$mysqldumpPath\" --host=$dbHost --user=$dbUser $passArg --single-transaction --routines --triggers $dbName > \"$sqlFile\" 2>&1";

    $outputLines = [];
    $retCode = -1;
    exec($cmd, $outputLines, $retCode);

    if ($retCode === 0 && file_exists($sqlFile) && filesize($sqlFile) > 100) {
        $dbDumpOk = true;
    } else {
        $dbError = implode("\n", $outputLines) ?: "Código de retorno: $retCode";
        // Fallback: gerar SQL via PDO manualmente
        try {
            $sqlContent = generateSqlDumpViaPdo($pdo, $dbName);
            file_put_contents($sqlFile, $sqlContent);
            $dbDumpOk = true;
            $dbError   = '';
        } catch (Exception $e) {
            $dbError = $e->getMessage();
        }
    }
} else {
    // Sem mysqldump: usar PDO
    try {
        $sqlContent = generateSqlDumpViaPdo($pdo, DB_NAME);
        file_put_contents($sqlFile, $sqlContent);
        $dbDumpOk = true;
    } catch (Exception $e) {
        $dbError = $e->getMessage();
    }
}

// ─── FUNÇÃO: DUMP VIA PDO ─────────────────────────────────────────────────────
function generateSqlDumpViaPdo(PDO $pdo, string $dbName): string
{
    $sql  = "-- Backup do Banco de Dados: {$dbName}\n";
    $sql .= "-- Gerado em: " . date('d/m/Y H:i:s') . "\n";
    $sql .= "-- Sistema: Cetusg / Netus\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        // Estrutura
        $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sql .= $create[1] . ";\n\n";

        // Dados
        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $values = array_map(function ($v) use ($pdo) {
                    return $v === null ? 'NULL' : $pdo->quote($v);
                }, $row);
                $cols = implode('`, `', array_keys($row));
                $vals = implode(', ', $values);
                $sql .= "INSERT INTO `{$table}` (`{$cols}`) VALUES ({$vals});\n";
            }
            $sql .= "\n";
        }
    }
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $sql;
}

// ─── FUNÇÃO: GERAR PLANILHA EXCEL (NOVO) ──────────────────────────────────────
function generateExcelBackup($pdo, $filePath) {
    $tables = [
        'company_settings'    => 'Config Gerais',
        'units'               => 'Unidades',
        'sectors'             => 'Setores',
        'rh_positions'        => 'Cargos',
        'users'               => 'Usuários',
        'user_menus'          => 'Permissões Menus',
        'rh_employee_details' => 'RH - Detalhes',
        'rh_vacations'        => 'RH - Férias',
        'rh_certificates'     => 'RH - Atestados',
        'rh_notes'            => 'RH - Observações',
        'assets'              => 'Patrimônio',
        'loans'               => 'Empréstimos',
        'tickets'             => 'Chamados',
        'budget_requests'     => 'Orçamentos',
        'budget_quotes'       => 'Cotações',
        'volunteers'          => 'Voluntários',
        'volunteer_hours'     => 'Voluntariado - Horas',
        'volunteer_history'   => 'Voluntariado - Histórico',
        'rooms'               => 'Salas',
        'room_bookings'       => 'Reservas de Salas',
        'semanada_uploads'    => 'Semanada - Arquivos',
        'semanada_comments'   => 'Semanada - Mural',
        'tech_cameras'        => 'Tec - Câmeras',
        'tech_emails'         => 'Tec - E-mails',
        'tech_remote_access'  => 'Tec - Acessos Remotos',
        'info_messages'       => 'Info - Mensagens',
        'info_links'          => 'Info - Links',
        'announcements'       => 'Avisos Gerais',
        'chat_messages'       => 'Chat - Mensagens',
        'login_logs'          => 'Logs de Acesso'
    ];

    $xlsx = new SimpleXLSXGen();
    $count = 0;

    foreach ($tables as $table => $sheet_name) {
        try {
            $checkTable = $pdo->query("SHOW TABLES LIKE '{$table}'");
            if ($checkTable->rowCount() == 0) continue;

            $stmt = $pdo->query("SELECT * FROM {$table}");
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($data)) {
                $colsStmt = $pdo->query("DESCRIBE {$table}");
                $headers = array_column($colsStmt->fetchAll(), 'Field');
                $sheetData = [$headers];
            } else {
                $sheetData = [];
                $sheetData[] = array_keys($data[0]);
                foreach ($data as $row) {
                    $sheetData[] = array_values($row);
                }
            }
            $xlsx->addSheet($sheetData, $sheet_name);
            $count++;
        } catch (Exception $e) { continue; }
    }
    return $xlsx->saveAs($filePath);
}

// ─── 3. GERAR ARQUIVOS ADICIONAIS ─────────────────────────────────────────────
$xlsxFile = "{$backupDir}/{$backupName}_DADOS.xlsx";
$excelOk = generateExcelBackup($pdo, $xlsxFile);

// ─── 4. CRIAR GUIA DE RESTAURAÇÃO ─────────────────────────────────────────────
$guide = <<<GUIDE
=============================================================
  GUIA COMPLETO DE RESTAURAÇÃO DO SISTEMA CETUSG / NETUS
  Backup gerado em: {$timestamp}
=============================================================

CONTEÚDO DESTE BACKUP
----------------------
- Todos os arquivos PHP do sistema
- Pasta uploads/ (fotos, avatares, assinaturas, logos)
- database_backup.sql (banco de dados COMPLETO em formato SQL)
- dados_sistema_excel.xlsx (Planilha com TODOS os dados organizados em abas)

PRÉ-REQUISITOS NO NOVO COMPUTADOR
----------------------------------
1. Windows 10 ou 11
2. XAMPP instalado (baixe em: https://www.apachefriends.org/)
   - Versão recomendada: XAMPP 8.2+

PASSO A PASSO DE RESTAURAÇÃO
------------------------------

PARTE 1 — Instalar o XAMPP
  1. Instale o XAMPP normalmente
  2. Abra o XAMPP Control Panel
  3. Inicie os serviços: Apache e MySQL
  4. Teste acessando: http://localhost — deve abrir o painel do XAMPP

PARTE 2 — Restaurar os Arquivos do Sistema
  1. Extraia este arquivo ZIP em qualquer lugar
  2. Copie a pasta extraída para:
     C:\xampp\htdocs\cetusg\
  3. Certifique-se que a pasta ficou em:
     C:\xampp\htdocs\cetusg\index.php  ← deve existir este arquivo

PARTE 3 — Restaurar o Banco de Dados
  1. Abra o navegador e acesse: http://localhost/phpmyadmin
  2. No painel da esquerda, clique em "Novo" (New)
  3. No campo "Nome do banco de dados", escreva exatamente:
     cetusg_plus
  4. Clique em "Criar"
  5. Com o banco selecionado, clique na aba "Importar"
  6. Clique em "Procurar" e selecione o arquivo:
     database_backup.sql  (está dentro deste ZIP)
  7. Clique em "Executar" — aguarde a importação (pode demorar alguns minutos)
  8. Se aparecer "Importação realizada com sucesso" — FUNCIONOU!

PARTE 4 — Verificar Configurações de Conexão
  1. Abra o arquivo: C:\xampp\htdocs\cetusg\config.php
  2. Verifique os dados de conexão:
     - DB_HOST: localhost
     - DB_NAME: cetusg_plus
     - DB_USER: root
     - DB_PASS: (vazio por padrão no XAMPP)
  3. Se necessário, ajuste as credenciais

PARTE 5 — Testar o Sistema
  1. No XAMPP Control Panel, certifique-se que Apache e MySQL estão rodando
  2. Abra o navegador e acesse: http://localhost/cetusg/
  3. Faça login com suas credenciais normais
  4. Todos os dados devem estar presentes!

PROBLEMAS COMUNS
-----------------
- "Acesso negado ao banco": Verifique config.php (DB_USER e DB_PASS)
- "Página não encontrada": Verifique se o Apache está rodando no XAMPP
- "Erro 500": Verifique se a extensão pdo_mysql está habilitada no php.ini
- "Importação muito lenta": Aumente max_execution_time=600 no php.ini

CONFIGURAR PARA REDE LOCAL (OPCIONAL)
---------------------------------------
Para que outros computadores acessem o sistema na mesma rede:
  1. No Apache (httpd.conf), altere: Listen 80 para Listen 0.0.0.0:80
  2. Libere a porta 80 no Firewall do Windows
  3. Descubra o IP do servidor: execute "ipconfig" no CMD
  4. Nos outros computadores, acesse: http://IP_DO_SERVIDOR/cetusg/

=============================================================
  Suporte: Sistema Cetusg/Netus
  Backup gerado automaticamente pelo sistema
=============================================================
GUIDE;

file_put_contents($guideFile, $guide);

// ─── 4. GERAR O ARQUIVO ZIP ───────────────────────────────────────────────────

$zip = new ZipArchive();
$zipResult = $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
if ($zipResult !== TRUE) {
    echo json_encode([
        'success' => false,
        'message' => "ERRO: Não foi possível criar o arquivo ZIP.\nCódigo de erro: {$zipResult}\nDestino: {$zipFile}"
    ]);
    exit;
}

// Adicionar arquivos do sistema
$rootPath  = realpath(__DIR__);
$fileCount = 0;
$skipDirs  = ['backups', '.git', 'node_modules'];

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $file) {
    if ($file->isDir()) continue;

    $filePath     = $file->getRealPath();
    $relativePath = substr($filePath, strlen($rootPath) + 1);

    // Pular pastas desnecessárias
    $skip = false;
    foreach ($skipDirs as $skipDir) {
        if (strpos($relativePath, $skipDir . DIRECTORY_SEPARATOR) === 0 || strpos($relativePath, $skipDir . '/') === 0) {
            $skip = true;
            break;
        }
    }
    if ($skip) continue;

    if ($zip->addFile($filePath, 'sistema/' . $relativePath)) {
        $fileCount++;
    }
}

// Adicionar o dump SQL ao ZIP
if ($dbDumpOk && file_exists($sqlFile)) {
    $zip->addFile($sqlFile, 'database_backup.sql');
}

// Adicionar a planilha Excel ao ZIP
if ($excelOk && file_exists($xlsxFile)) {
    $zip->addFile($xlsxFile, 'dados_sistema_excel.xlsx');
}

// Adicionar o guia de restauração
if (file_exists($guideFile)) {
    $zip->addFile($guideFile, 'COMO_RESTAURAR.txt');
}

$zip->close();

// ─── 5. LIMPEZA E ROTAÇÃO DE BACKUPS ANTIGOS ──────────────────────────────────
// Manter apenas os 5 backups mais recentes
$allBackups = glob("{$backupDir}/backup_cetusg_*.zip");
if ($allBackups && count($allBackups) > 5) {
    sort($allBackups); // mais antigos primeiro
    $toDelete = array_slice($allBackups, 0, count($allBackups) - 5);
    foreach ($toDelete as $old) {
        @unlink($old);
    }
}

// Remover arquivos temporários
@unlink($sqlFile);
@unlink($xlsxFile);
@unlink($guideFile);

// ─── 6. RESULTADO ─────────────────────────────────────────────────────────────
if (!file_exists($zipFile)) {
    echo json_encode([
        'success' => false,
        'message' => "ERRO: O arquivo ZIP não foi gerado. Verifique espaço em disco e permissões na pasta:\n{$backupDir}"
    ]);
    exit;
}

$sizeMb     = round(filesize($zipFile) / (1024 * 1024), 2);
$dbStatus   = $dbDumpOk ? "✅ Banco de dados exportado com sucesso (SQL)" : "⚠️ Banco de dados com problemas: {$dbError}";
$excelStatus = $excelOk ? "✅ Planilha de dados Excel gerada com sucesso" : "⚠️ Falha ao gerar planilha Excel";
$backupsLeft = count(glob("{$backupDir}/backup_cetusg_*.zip"));

// Limpar qualquer output residual (avisos, etc) antes do JSON
if (ob_get_length()) ob_clean();

    echo json_encode([
        'success' => true,
        'message' => implode("\n", [
            "✅ BACKUP COMPLETO REALIZADO COM SUCESSO!",
            "",
            "📁 Arquivo: " . basename($zipFile),
            "📦 Tamanho: {$sizeMb} MB",
            "📄 Arquivos do sistema incluídos: {$fileCount}",
            $dbStatus,
            $excelStatus,
            "📖 Guia de restauração incluído: COMO_RESTAURAR.txt",
            "",
            "📍 Destino: " . str_replace('/', '\\', $backupDir),
            "🗂️ Backups disponíveis no destino: {$backupsLeft}",
            "",
            "💡 Dica: O arquivo ZIP contém o guia completo de",
            "   restauração em outro computador."
        ])
    ]);
} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode([
        'success' => false,
        'message' => "ERRO CRÍTICO NO BACKUP:\n" . $e->getMessage()
    ]);
}
?>
