<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Model;
use App\Core\Auth;
use App\Core\Logger;

class InfoController extends Controller {
    
    public function index() {
        Auth::requirePermission('informacoes.view');
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();
        $user = Auth::user();

        // Admin/RH see all sectors, collaborators see only theirs
        $isAdmin = in_array($user['role'], ['Administrador', 'Recursos Humanos']);
        
        if ($isAdmin) {
            $sectors = $pdo->prepare("SELECT * FROM sectors WHERE company_id = ? ORDER BY name");
            $sectors->execute([$companyId]);
        } else {
            $sectors = $pdo->prepare("SELECT * FROM sectors WHERE company_id = ? AND name = ?");
            $sectors->execute([$companyId, $user['sector']]);
        }
        $sectors = $sectors->fetchAll();

        $generalMsg = $pdo->prepare("SELECT content FROM info_messages WHERE company_id = ? AND type = 'general' LIMIT 1");
        $generalMsg->execute([$companyId]);
        $generalMsg = $generalMsg->fetchColumn() ?: 'Bem-vindo ao Quadro Geral.';

        $sectorMsgs = $pdo->prepare("SELECT sector, content FROM info_messages WHERE company_id = ? AND type = 'sector'");
        $sectorMsgs->execute([$companyId]);
        $sectorMsgs = $sectorMsgs->fetchAll(\PDO::FETCH_KEY_PAIR);

        $links = $pdo->prepare("SELECT * FROM info_links WHERE company_id = ? ORDER BY title");
        $links->execute([$companyId]);
        $links = $links->fetchAll(\PDO::FETCH_GROUP);

        $team = $pdo->prepare("SELECT u.id, u.name, u.avatar_url, u.sector FROM users u WHERE u.company_id = ?");
        $team->execute([$companyId]);
        $team = $team->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_ASSOC);

        return $this->view('informacoes', [
            'sectors' => $sectors,
            'generalMsg' => $generalMsg,
            'sectorMsgs' => $sectorMsgs,
            'links' => $links,
            'team' => $team,
            'isAdmin' => $isAdmin
        ]);
    }

    public function store() {
        Auth::requirePermission('informacoes.edit');
        $pdo = Model::getConnection();
        $companyId = Model::getCompanyId();
        $action = $_POST['action'] ?? '';

        if ($action === 'update_general') {
            $stmt = $pdo->prepare("REPLACE INTO info_messages (type, company_id, content) VALUES ('general', ?, ?)");
            $stmt->execute([$companyId, $_POST['content']]);
            return $this->redirect('/informacoes?success=1');
        }

        return $this->redirect('/informacoes');
    }
}
