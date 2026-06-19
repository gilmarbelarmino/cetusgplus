<?php
namespace App\Services;

use PDO;
use Exception;

class HrService {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Retorna todos os dados para alimentar o Dashboard de RH
     */
    public function getFullDashboardData($companyId) {
        // 1. Relatório Geral de Funcionários
        $query = "
            SELECT 
                u.id, u.name, u.email, u.sector, u.unit_id, u.avatar_url, u.status, u.role, u.phone,
                un.name as unit_name,
                rh.contract_type, rh.role_name, rh.work_days, rh.work_hours, rh.salary, rh.use_transport, rh.transport_value, rh.gender, rh.birth_date, rh.start_date, rh.end_date 
            FROM users u
            LEFT JOIN units un ON CONVERT(u.unit_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(un.id USING utf8mb4) COLLATE utf8mb4_unicode_ci
            LEFT JOIN rh_employee_details rh ON CONVERT(u.id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(rh.user_id USING utf8mb4) COLLATE utf8mb4_unicode_ci
            WHERE u.company_id = ?
            ORDER BY u.sector ASC, u.name ASC
        ";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$companyId]);
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Férias
        $stmtVacations = $this->pdo->prepare("SELECT * FROM rh_vacations WHERE company_id = ? ORDER BY start_date DESC");
        $stmtVacations->execute([$companyId]);
        $vacations = $stmtVacations->fetchAll(PDO::FETCH_ASSOC);

        // 3. Atestados
        $stmtCertificates = $this->pdo->prepare("SELECT * FROM rh_certificates WHERE company_id = ? ORDER BY issue_date DESC");
        $stmtCertificates->execute([$companyId]);
        $certificates = $stmtCertificates->fetchAll(PDO::FETCH_ASSOC);
        
        // 4. Anotações (com tratamento de erro silencioso mantendo o comportamento legado)
        $notes = [];
        try { 
            $stmtNotes = $this->pdo->prepare("SELECT * FROM rh_notes WHERE company_id = ? ORDER BY created_at DESC");
            $stmtNotes->execute([$companyId]);
            $notes = $stmtNotes->fetchAll(PDO::FETCH_ASSOC); 
        } catch(Exception $e) {}

        // 5. Comunicados (com tratamento de erro silencioso mantendo o comportamento legado)
        $announcements = [];
        try { 
            $stmtAnnouncements = $this->pdo->prepare("SELECT a.*, (SELECT COUNT(*) FROM announcement_views v WHERE v.announcement_id = a.id) as views FROM announcements a WHERE a.company_id = ? ORDER BY a.created_at DESC");
            $stmtAnnouncements->execute([$companyId]);
            $announcements = $stmtAnnouncements->fetchAll(PDO::FETCH_ASSOC); 
        } catch(Exception $e) {}

        return [
            'employees' => $employees,
            'vacations' => $vacations,
            'certificates' => $certificates,
            'notes' => $notes,
            'announcements' => $announcements
        ];
    }

    /**
     * Salva um novo comunicado no mural
     */
    public function addAnnouncement($companyId, $message, $createdBy) {
        $stmt = $this->pdo->prepare("INSERT INTO announcements (message, created_by, company_id, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$message, $createdBy, $companyId]);
    }
}
