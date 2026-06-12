<?php
require_once 'config.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['error' => 'Acesso negado.']);
    exit;
}

$compId = getCurrentUserCompanyId();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'get_monthly_report') {
    $target_user_id = $_GET['user_id'] ?? '';
    $month = $_GET['month'] ?? date('m');
    $year = $_GET['year'] ?? date('Y');

    if (!$target_user_id) {
        echo json_encode(['error' => 'ID do usuário não fornecido.']);
        exit;
    }

    // Buscar a meta diária de horas do contrato (default 08:00)
    $stmt_rh = $pdo->prepare("SELECT daily_work_hours, work_days FROM rh_employee_details WHERE user_id = ? AND company_id = ?");
    $stmt_rh->execute([$target_user_id, $compId]);
    $rh = $stmt_rh->fetch(PDO::FETCH_ASSOC);
    $daily_goal_str = $rh['daily_work_hours'] ?? '08:00:00';
    $daily_goal_seconds = strtotime("1970-01-01 $daily_goal_str UTC");
    $work_days_str = strtolower($rh['work_days'] ?? 'segunda a sexta');

    // Buscar registros do mês
    $stmt = $pdo->prepare("
        SELECT * FROM time_records 
        WHERE user_id = ? AND company_id = ? 
        AND MONTH(record_time) = ? AND YEAR(record_time) = ?
        ORDER BY record_time ASC
    ");
    $stmt->execute([$target_user_id, $compId, $month, $year]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Agrupar por dia
    $days = [];
    foreach ($records as $r) {
        $date = date('Y-m-d', strtotime($r['record_time']));
        if (!isset($days[$date])) {
            $days[$date] = [
                'date' => $date,
                'punches' => [],
                'worked_seconds' => 0,
                'balance_seconds' => 0,
                'has_ocorrencia' => false
            ];
        }
        $days[$date]['punches'][] = $r;
        if ($r['status'] === 'Ocorrencia') {
            $days[$date]['has_ocorrencia'] = true;
        }
    }

    $total_worked_month = 0;
    $total_balance_month = 0;
    $total_extra_month = 0;
    $total_negative_month = 0;
    $days_worked = count($days);
    $total_ocorrencias = 0;

    // Calcular horas
    foreach ($days as $date => &$dayData) {
        $punches = $dayData['punches'];
        $entrada = null; $saida_almoco = null; $retorno_almoco = null; $saida = null;
        
        foreach ($punches as $p) {
            $t = strtotime($p['record_time']);
            if ($p['record_type'] === 'Entrada' && !$entrada) $entrada = $t;
            if ($p['record_type'] === 'Saida Almoco' && !$saida_almoco) $saida_almoco = $t;
            if ($p['record_type'] === 'Retorno Almoco' && !$retorno_almoco) $retorno_almoco = $t;
            if ($p['record_type'] === 'Saida' && !$saida) $saida = $t;
            if ($p['status'] === 'Ocorrencia') $total_ocorrencias++;
        }

        $worked = 0;
        
        // Período da Manhã (Entrada até Saída Almoço)
        if ($entrada && $saida_almoco) {
            $worked += ($saida_almoco - $entrada);
        } else if ($entrada && !$saida_almoco && !$retorno_almoco && $saida) {
            // Trabalhou direto (sem almoço registrado)
            $worked += ($saida - $entrada);
        }

        // Período da Tarde (Retorno Almoço até Saída)
        if ($retorno_almoco && $saida) {
            $worked += ($saida - $retorno_almoco);
        }

        $dayData['worked_seconds'] = $worked;
        
        // Calcular Meta Diária baseado no dia da semana
        $dayOfWeek = date('N', strtotime($date)); // 1=Mon, 7=Sun
        $current_goal = $daily_goal_seconds;

        if (strpos($work_days_str, 'segunda a sexta') !== false) {
            if ($dayOfWeek == 6 || $dayOfWeek == 7) $current_goal = 0; // Sab e Dom não tem meta (100% extra se bater ponto)
        } else if (strpos($work_days_str, 'segunda a sabado') !== false) {
            if ($dayOfWeek == 7) $current_goal = 0; // Dom não tem meta
        }

        // Se trabalhou ou se tinha meta no dia, calculamos o saldo
        $is_today = ($date === date('Y-m-d'));
        if ($worked > 0 || ($current_goal > 0 && !$is_today)) {
            $balance = $worked - $current_goal;
            $dayData['balance_seconds'] = $balance;
            
            $total_worked_month += $worked;
            $total_balance_month += $balance;
            
            if ($balance > 0) {
                $total_extra_month += $balance;
            } else if ($balance < 0) {
                $total_negative_month += abs($balance);
            }
        }
    }

    // Helper function to format seconds to HH:MM
    function formatSecs($secs) {
        $sign = $secs < 0 ? '-' : '';
        $secs = abs($secs);
        $h = floor($secs / 3600);
        $m = floor(($secs % 3600) / 60);
        return sprintf("%s%02d:%02d", $sign, $h, $m);
    }

    echo json_encode([
        'success' => true,
        'daily_goal' => substr($daily_goal_str, 0, 5),
        'summary' => [
            'total_worked_hours' => formatSecs($total_worked_month),
            'total_balance' => formatSecs($total_balance_month),
            'extra_hours' => formatSecs($total_extra_month),
            'negative_hours' => formatSecs($total_negative_month),
            'is_balance_positive' => $total_balance_month >= 0,
            'days_worked' => $days_worked,
            'ocorrencias' => $total_ocorrencias
        ],
        'days' => array_values($days)
    ]);
    exit;
}

echo json_encode(['error' => 'Ação inválida.']);
