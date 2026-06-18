<?php
require_once 'config.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Check user login
if (!isset($_SESSION['user_id'])) {
    die("Acesso negado.");
}

$compId = getCurrentUserCompanyId();

$search = $_GET['search'] ?? '';
$status_filter = $_GET['status_filter'] ?? 'Ativo';

$query = "
    SELECT 
        u.id, u.name, u.email, u.sector, u.unit_id, u.avatar_url, u.status, u.role, u.phone,
        un.name as unit_name,
        rh.contract_type, rh.role_name, rh.work_days, rh.work_hours, rh.daily_work_hours, rh.salary, rh.use_transport, rh.transport_value, rh.gender, rh.birth_date, rh.start_date, rh.end_date, rh.dismissal_reason, rh.benefits, rh.lunch_start, rh.lunch_end, rh.allow_overtime, rh.overtime_message
    FROM users u
    LEFT JOIN units un ON CONVERT(u.unit_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(un.id USING utf8mb4) COLLATE utf8mb4_unicode_ci
    LEFT JOIN rh_employee_details rh ON CONVERT(u.id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(rh.user_id USING utf8mb4) COLLATE utf8mb4_unicode_ci
    WHERE u.company_id = ? AND u.status = ?
";
$params = [$compId, $status_filter];
if ($search) {
    $query .= " AND (u.name LIKE ? OR u.sector LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
$query .= " ORDER BY u.name ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($users)) {
    die("Nenhum funcionário encontrado para exportação.");
}

$spreadsheet = new Spreadsheet();
$firstSheet = true;

foreach ($users as $index => $u) {
    if ($firstSheet) {
        $sheet = $spreadsheet->getActiveSheet();
        $firstSheet = false;
    } else {
        $sheet = $spreadsheet->createSheet();
    }
    
    // Nome da aba limitado a 31 caracteres (regra do Excel)
    $safeName = substr(str_replace(['*', ':', '/', '\\', '?', '[', ']'], '', $u['name']), 0, 31);
    if (empty($safeName)) $safeName = "Usuario_" . $u['id'];
    $sheet->setTitle($safeName);

    $sheet->getColumnDimension('A')->setWidth(30);
    $sheet->getColumnDimension('B')->setWidth(50);
    $sheet->getColumnDimension('C')->setWidth(20);
    
    $currentRow = 1;

    // Foto do Funcionário
    if (!empty($u['avatar_url']) && file_exists(__DIR__ . '/' . $u['avatar_url'])) {
        $drawing = new Drawing();
        $drawing->setName('Avatar');
        $drawing->setDescription('Foto do Funcionário');
        $drawing->setPath(__DIR__ . '/' . $u['avatar_url']);
        $drawing->setCoordinates('C' . $currentRow);
        $drawing->setHeight(100);
        $drawing->setOffsetX(10);
        $drawing->setOffsetY(10);
        $drawing->setWorksheet($sheet);
        // Aumenta a altura da linha para acomodar a foto
        $sheet->getRowDimension($currentRow)->setRowHeight(85);
    }

    $sheet->setCellValue('A' . $currentRow, 'NOME:');
    $sheet->setCellValue('B' . $currentRow, $u['name']);
    $sheet->getStyle('A'.$currentRow.':B'.$currentRow)->getFont()->setBold(true);
    $currentRow++;

    $sheet->setCellValue('A' . $currentRow, 'SETOR:');
    $sheet->setCellValue('B' . $currentRow, $u['sector'] ?? 'Não definido');
    $currentRow+=2;

    // --- Dados Contratuais ---
    $sheet->setCellValue('A' . $currentRow, 'DADOS CONTRATUAIS');
    $sheet->getStyle('A'.$currentRow)->getFont()->setBold(true);
    $sheet->getStyle('A'.$currentRow.':B'.$currentRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9E1F2');
    $currentRow++;
    
    $dados_contratuais = [
        'Cargo' => $u['role_name'] ?? 'Não definido',
        'Tipo de Contrato' => $u['contract_type'] ?? 'Não definido',
        'Data de Admissão' => $u['start_date'] ? date('d/m/Y', strtotime($u['start_date'])) : 'Não definida',
        'Data de Nascimento' => $u['birth_date'] ? date('d/m/Y', strtotime($u['birth_date'])) : 'Não definida',
        'Dias de Trabalho' => $u['work_days'] ?? '',
        'Horários' => $u['work_hours'] ?? '',
        'Salário (R$)' => number_format((float)($u['salary'] ?? 0), 2, ',', '.'),
        'Usa Vale Transporte?' => $u['use_transport'] ?? 'Não',
        'Permite Hora Extra?' => $u['allow_overtime'] ?? 'Não',
    ];
    
    foreach ($dados_contratuais as $label => $value) {
        $sheet->setCellValue('A' . $currentRow, $label);
        $sheet->setCellValue('B' . $currentRow, $value);
        $currentRow++;
    }
    $currentRow++;

    // --- Atestados Médicos ---
    $sheet->setCellValue('A' . $currentRow, 'ATESTADOS MÉDICOS');
    $sheet->getStyle('A'.$currentRow)->getFont()->setBold(true);
    $sheet->getStyle('A'.$currentRow.':B'.$currentRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9E1F2');
    $currentRow++;

    try {
        $stmt_cert = $pdo->prepare("SELECT issue_date, days_off, reason FROM rh_certificates WHERE user_id = ? AND company_id = ? ORDER BY issue_date DESC");
        $stmt_cert->execute([$u['id'], $compId]);
        $certs = $stmt_cert->fetchAll(PDO::FETCH_ASSOC);

        if (count($certs) > 0) {
            $sheet->setCellValue('A' . $currentRow, 'Data do Atestado');
            $sheet->setCellValue('B' . $currentRow, 'Motivo / Dias de Afastamento');
            $sheet->getStyle('A'.$currentRow.':B'.$currentRow)->getFont()->setItalic(true);
            $currentRow++;
            foreach ($certs as $cert) {
                $sheet->setCellValue('A' . $currentRow, date('d/m/Y', strtotime($cert['issue_date'])));
                $sheet->setCellValue('B' . $currentRow, $cert['reason'] . ' (' . $cert['days_off'] . ' dias)');
                $currentRow++;
            }
        } else {
            $sheet->setCellValue('A' . $currentRow, 'Nenhum atestado registrado.');
            $currentRow++;
        }
    } catch (Exception $e) {}
    $currentRow++;

    // --- Escala de Férias ---
    $sheet->setCellValue('A' . $currentRow, 'ESCALA DE FÉRIAS');
    $sheet->getStyle('A'.$currentRow)->getFont()->setBold(true);
    $sheet->getStyle('A'.$currentRow.':B'.$currentRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9E1F2');
    $currentRow++;

    try {
        $stmt_vac = $pdo->prepare("SELECT start_date, end_date, status FROM rh_vacations WHERE user_id = ? AND company_id = ? ORDER BY start_date DESC");
        $stmt_vac->execute([$u['id'], $compId]);
        $vacs = $stmt_vac->fetchAll(PDO::FETCH_ASSOC);

        if (count($vacs) > 0) {
            $sheet->setCellValue('A' . $currentRow, 'Período');
            $sheet->setCellValue('B' . $currentRow, 'Status');
            $sheet->getStyle('A'.$currentRow.':B'.$currentRow)->getFont()->setItalic(true);
            $currentRow++;
            foreach ($vacs as $vac) {
                $period = date('d/m/Y', strtotime($vac['start_date'])) . ' a ' . date('d/m/Y', strtotime($vac['end_date']));
                $sheet->setCellValue('A' . $currentRow, $period);
                $sheet->setCellValue('B' . $currentRow, $vac['status']);
                $currentRow++;
            }
        } else {
            $sheet->setCellValue('A' . $currentRow, 'Nenhuma férias registrada.');
            $currentRow++;
        }
    } catch (Exception $e) {}
    $currentRow++;

    // --- Informações e Anotações ---
    $sheet->setCellValue('A' . $currentRow, 'INFORMAÇÕES E ANOTAÇÕES');
    $sheet->getStyle('A'.$currentRow)->getFont()->setBold(true);
    $sheet->getStyle('A'.$currentRow.':B'.$currentRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9E1F2');
    $currentRow++;

    try {
        $stmt_notes = $pdo->prepare("SELECT created_at, note_text FROM rh_notes WHERE user_id = ? AND company_id = ? ORDER BY created_at DESC");
        $stmt_notes->execute([$u['id'], $compId]);
        $notes = $stmt_notes->fetchAll(PDO::FETCH_ASSOC);
        if (count($notes) > 0) {
            foreach ($notes as $note) {
                $sheet->setCellValue('A' . $currentRow, date('d/m/Y H:i', strtotime($note['created_at'])));
                $sheet->setCellValue('B' . $currentRow, $note['note_text']);
                $currentRow++;
            }
        } else {
            $sheet->setCellValue('A' . $currentRow, 'Nenhuma anotação.');
            $currentRow++;
        }
    } catch (Exception $e) {}
    $currentRow++;

    // --- Registro de Ponto Detalhado ---
    $sheet->setCellValue('A' . $currentRow, 'REGISTRO DE PONTO DETALHADO');
    $sheet->getStyle('A'.$currentRow)->getFont()->setBold(true);
    $sheet->getStyle('A'.$currentRow.':C'.$currentRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9E1F2');
    $currentRow++;

    try {
        $stmt_ponto = $pdo->prepare("SELECT record_date, entry_time, exit_time, status FROM rh_timesheet WHERE user_id = ? AND company_id = ? ORDER BY record_date ASC");
        $stmt_ponto->execute([$u['id'], $compId]);
        $pontos = $stmt_ponto->fetchAll(PDO::FETCH_ASSOC);

        if (count($pontos) > 0) {
            $sheet->setCellValue('A' . $currentRow, 'Data');
            $sheet->setCellValue('B' . $currentRow, 'Entrada - Saída');
            $sheet->setCellValue('C' . $currentRow, 'Status');
            $sheet->getStyle('A'.$currentRow.':C'.$currentRow)->getFont()->setItalic(true);
            $currentRow++;
            foreach ($pontos as $p) {
                $sheet->setCellValue('A' . $currentRow, date('d/m/Y', strtotime($p['record_date'])));
                $time_str = ($p['entry_time'] ? substr($p['entry_time'],0,5) : '--') . ' às ' . ($p['exit_time'] ? substr($p['exit_time'],0,5) : '--');
                $sheet->setCellValue('B' . $currentRow, $time_str);
                $sheet->setCellValue('C' . $currentRow, $p['status'] ?? '');
                $currentRow++;
            }
        } else {
            $sheet->setCellValue('A' . $currentRow, 'Nenhum registro de ponto encontrado.');
            $currentRow++;
        }
    } catch (Exception $e) {}
}

if (ob_get_length()) ob_end_clean();

$writer = new Xlsx($spreadsheet);
$filename = 'Gestao_Funcionarios_' . date('Y-m-d_H-i') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'. urlencode($filename).'"');
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;
