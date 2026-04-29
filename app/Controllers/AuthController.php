<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Model;
use App\Core\Auth;
use App\Core\Logger;

class AuthController extends Controller {
    
    public function login() {
        if (isset($_SESSION['user_id'])) return $this->redirect('/dashboard');
        return $this->view('login', [], 'blank'); // 'blank' layout would be without sidebar
    }

    public function authenticate() {
        $pdo = Model::getConnection();
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['company_id'] = $user['company_id'];
            $_SESSION['role_id'] = $user['role_id'];
            
            Logger::audit('login', 'auth', 'Usuário logado: ' . $email);
            return $this->redirect('/dashboard');
        }

        return $this->redirect('/login?error=invalid_credentials');
    }

    public function logout() {
        Logger::audit('logout', 'auth', 'Usuário deslogado: ' . ($_SESSION['user_name'] ?? 'Desconhecido'));
        session_destroy();
        return $this->redirect('/login');
    }
}
