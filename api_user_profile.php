<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$user = getCurrentUser();
$current_id = $user['id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Auto-migrate: add columns if not exist
try { $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(30) DEFAULT ''"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN address_street VARCHAR(255) DEFAULT ''"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN address_number VARCHAR(30) DEFAULT ''"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN address_complement VARCHAR(100) DEFAULT ''"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN address_neighborhood VARCHAR(100) DEFAULT ''"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN address_city VARCHAR(100) DEFAULT ''"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN address_state VARCHAR(50) DEFAULT ''"); } catch(Exception $e) {}
try { $pdo->exec("ALTER TABLE users ADD COLUMN address_zip VARCHAR(20) DEFAULT ''"); } catch(Exception $e) {}

switch ($action) {
    case 'get_profile':
        $target_id = $_GET['user_id'] ?? $current_id;
        // Only admins/RH can view other user profiles
        if ($target_id !== $current_id && !in_array($user['role'], ['Administrador', 'RH', 'Gestor'])) {
            echo json_encode(['error' => 'Sem permissão']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT id, name, email, sector, role, phone, address_street, address_number, address_complement, address_neighborhood, address_city, address_state, address_zip, avatar_url FROM users WHERE id = ?");
        $stmt->execute([$target_id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($profile ?: ['error' => 'Usuário não encontrado']);
        break;

    case 'update_phone':
        // Any user can update their own phone
        $phone = trim($_POST['phone'] ?? '');
        $stmt = $pdo->prepare("UPDATE users SET phone = ? WHERE id = ?");
        $stmt->execute([$phone, $current_id]);
        echo json_encode(['success' => true]);
        break;

    case 'update_full_profile':
        // Only Admins / RH can update full profile of any user
        if (!in_array($user['role'], ['Administrador', 'RH'])) {
            echo json_encode(['error' => 'Sem permissão']);
            exit;
        }
        $target_id = $_POST['user_id'] ?? $current_id;
        $fields = [
            'phone'                 => trim($_POST['phone'] ?? ''),
            'address_street'        => trim($_POST['address_street'] ?? ''),
            'address_number'        => trim($_POST['address_number'] ?? ''),
            'address_complement'    => trim($_POST['address_complement'] ?? ''),
            'address_neighborhood'  => trim($_POST['address_neighborhood'] ?? ''),
            'address_city'          => trim($_POST['address_city'] ?? ''),
            'address_state'         => trim($_POST['address_state'] ?? ''),
            'address_zip'           => trim($_POST['address_zip'] ?? ''),
        ];
        $setClauses = implode(', ', array_map(fn($k) => "$k = ?", array_keys($fields)));
        $stmt = $pdo->prepare("UPDATE users SET $setClauses WHERE id = ?");
        $stmt->execute([...array_values($fields), $target_id]);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Ação inválida']);
}
?>
