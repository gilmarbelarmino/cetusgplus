<?php

namespace App\Core;

/**
 * RBAC - Role Based Access Control
 * =================================
 * Verifica permissões do usuário logado com base em:
 *   - Papel (role): admin, supervisor, ti, rh, etc.
 *   - Permissão granular: tecnologia.view, usuarios.delete, etc.
 */
class Auth {
    private static $pdo;
    private static $permissions = null;
    private static $role = null;

    public static function setConnection($pdo) {
        self::$pdo = $pdo;
    }

    /**
     * Retorna o usuário logado da sessão
     */
    public static function user() {
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'] ?? $_SESSION['nome'] ?? 'Anônimo',
            'role_id' => $_SESSION['role_id'] ?? null,
        ];
    }

    /**
     * Verifica se o usuário está logado
     */
    public static function check() {
        return isset($_SESSION['user_id']);
    }

    /**
     * Carrega o papel e permissões do usuário (lazy loading)
     */
    private static function loadPermissions() {
        if (self::$permissions !== null) return;
        
        self::$permissions = [];
        self::$role = null;

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId || !self::$pdo) return;

        try {
            // Buscar role do usuário
            $stmt = self::$pdo->prepare("
                SELECT r.id, r.name, r.display_name, r.level
                FROM users u 
                JOIN roles r ON u.role_id = r.id 
                WHERE u.id = ?
            ");
            $stmt->execute([$userId]);
            self::$role = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!self::$role) return;

            // Buscar permissões do papel
            $stmt = self::$pdo->prepare("
                SELECT p.name 
                FROM permissions p 
                JOIN role_permission rp ON p.id = rp.permission_id 
                WHERE rp.role_id = ?
            ");
            $stmt->execute([self::$role['id']]);
            self::$permissions = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'name');
        } catch (\Exception $e) {
            Logger::error('Falha ao carregar permissões RBAC', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Verifica se o usuário tem uma permissão específica
     * Ex: Auth::can('tecnologia.edit')
     */
    public static function can($permission) {
        self::loadPermissions();
        
        // Admin tem tudo
        if (self::$role && self::$role['name'] === 'admin') {
            return true;
        }

        return in_array($permission, self::$permissions);
    }

    /**
     * Verifica se o usuário tem um papel específico
     * Ex: Auth::hasRole('admin')
     */
    public static function hasRole($roleName) {
        self::loadPermissions();
        return self::$role && self::$role['name'] === $roleName;
    }

    /**
     * Retorna o nível hierárquico do papel (para comparações)
     */
    public static function level() {
        self::loadPermissions();
        return self::$role['level'] ?? 0;
    }

    /**
     * Retorna o nome do papel atual
     */
    public static function roleName() {
        self::loadPermissions();
        return self::$role['display_name'] ?? 'Sem Papel';
    }

    /**
     * Bloqueia acesso se não tiver a permissão
     */
    public static function requirePermission($permission) {
        if (!self::can($permission)) {
            http_response_code(403);
            die('<div style="text-align:center;padding:4rem;font-family:sans-serif;">
                <i class="fa-solid fa-shield-halved" style="font-size:3rem;color:#ef4444;opacity:0.5;"></i>
                <h2 style="color:#1e293b;margin-top:1rem;">Acesso Negado</h2>
                <p style="color:#64748b;">Você não tem permissão para acessar este recurso.</p>
                <p style="color:#94a3b8;font-size:0.85rem;">Permissão necessária: <code>' . htmlspecialchars($permission) . '</code></p>
                <a href="javascript:history.back()" style="color:#6366f1;text-decoration:none;font-weight:700;">← Voltar</a>
            </div>');
        }
    }

    /**
     * Retorna o ID da empresa do usuário logado
     */
    public static function companyId() {
        if (isset($_SESSION['company_id'])) {
            return (int)$_SESSION['company_id'];
        }
        
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId || !self::$pdo) return 0;

        $stmt = self::$pdo->prepare("SELECT company_id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $compId = $stmt->fetchColumn();
        $_SESSION['company_id'] = ($compId !== false && $compId !== null) ? (int)$compId : 0;
        return $_SESSION['company_id'];
    }
}
