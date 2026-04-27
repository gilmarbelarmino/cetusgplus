<?php
require_once 'config.php';
require_once 'auth.php';

session_start();
$user = getCurrentUser();
if (!$user || ($user['role'] != 'Administrador' && $user['role'] != 'Suporte Técnico')) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Acesso negado.']));
}

header('Content-Type: application/json; charset=utf-8');
set_time_limit(600); // 10 minutos
ini_set('memory_limit', '512M');
error_reporting(0);

// ─── CONFIGURAÇÕES ────────────────────────────────────────────────────────────
$backupDir   = 'D:/SISTEMA REDE ARRASTAO';
$timestamp   = date('Y-m-d_H-i-s');
$backupName  = "backup_cetusg_{$timestamp}";
$sqlFile     = "{$backupDir}/{$backupName}.sql";
$zipFile     = "{$backupDir}/{$backupName}.zip";
$guideFile   = "{$backupDir}/{$backupName}_COMO_RESTAURAR.txt";

// ─── 1. CRIAR DIRETÓRIO DE DESTINO ────────────────────────────────────────────
if (!file_exists($backupDir)) {
    if (!mkdir($backupDir, 0777, true)) {
        echo json_encode([
            'success' => false,
            'message' => "ERRO: Não foi possível criar a pasta de destino:\n{$backupDir}\n\nVerifique se a unidade D: existe e se você tem permissão de escrita."
        ]);
        exit;
    }
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

// ─── 3. CRIAR GUIA DE RESTAURAÇÃO ─────────────────────────────────────────────
$guide = <<<GUIDE
=============================================================
  GUIA COMPLETO DE RESTAURAÇÃO DO SISTEMA CETUSG / NETUS
  Backup gerado em: {$timestamp}
=============================================================

CONTEÚDO DESTE BACKUP
----------------------
- Todos os arquivos PHP do sistema
- Pasta uploads/ (fotos, avatares, assinaturas, logos)
- database_backup.sql (banco de dados COMPLETO)

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
if (!class_exists('ZipArchive')) {
    echo json_encode([
        'success' => false,
        'message' => "ERRO: A extensão 'ZipArchive' não está habilitada no PHP.\nAbra o php.ini e remova o ';' da linha: ;extension=zip"
    ]);
    exit;
}

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
$dbStatus   = $dbDumpOk ? "✅ Banco de dados exportado com sucesso" : "⚠️ Banco de dados com problemas: {$dbError}";
$backupsLeft = count(glob("{$backupDir}/backup_cetusg_*.zip"));

echo json_encode([
    'success' => true,
    'message' => implode("\n", [
        "✅ BACKUP COMPLETO REALIZADO COM SUCESSO!",
        "",
        "📁 Arquivo: " . basename($zipFile),
        "📦 Tamanho: {$sizeMb} MB",
        "📄 Arquivos do sistema incluídos: {$fileCount}",
        $dbStatus,
        "📖 Guia de restauração incluído: COMO_RESTAURAR.txt",
        "",
        "📍 Destino: D:\\SISTEMA REDE ARRASTAO",
        "🗂️ Backups disponíveis no destino: {$backupsLeft}",
        "",
        "💡 Dica: O arquivo ZIP contém o guia completo de",
        "   restauração em outro computador."
    ])
]);
?>
