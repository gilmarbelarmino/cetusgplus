<?php

namespace App\Controllers\Api;

use App\Core\ApiController;
use App\Core\Model;
use App\Core\Auth;

class AssetApiController extends ApiController {
    
    /**
     * GET /api/assets
     * Lista todos os patrimônios
     */
    public function index() {
        Auth::requirePermission('patrimonio.view');
        
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();
        
        $search = $_GET['search'] ?? '';
        
        $query = "SELECT a.*, u.name as unit_name 
                  FROM assets a 
                  LEFT JOIN units u ON a.unit_id COLLATE utf8mb4_unicode_ci = u.id COLLATE utf8mb4_unicode_ci 
                  WHERE a.company_id = ?";
        $params = [$companyId];

        if ($search) {
            $query .= " AND (a.name LIKE ? OR a.patrimony_id LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $query .= " ORDER BY a.created_at DESC";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $assets = $stmt->fetchAll();

        return $this->success($assets, "Patrimônios listados com sucesso");
    }

    /**
     * GET /api/assets/{id}
     * Detalhes de um patrimônio específico
     */
    public function show($id) {
        Auth::requirePermission('patrimonio.view');
        
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();

        $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ? AND company_id = ?");
        $stmt->execute([$id, $companyId]);
        $asset = $stmt->fetch();

        if (!$asset) {
            return $this->error("Patrimônio não encontrado", 404);
        }

        return $this->success($asset);
    }
}
