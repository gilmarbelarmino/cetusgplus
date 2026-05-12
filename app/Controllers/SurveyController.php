<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Logger;

class SurveyController extends Controller {

    public function index() {
        Auth::check();
        $user = Auth::user();
        $compId = $_SESSION['company_id'];
        $userId = $_SESSION['user_id'];
        
        $isAllowedToManage = ($user['role'] === 'Administrador' || $user['role'] === 'RH' || $user['role'] === 'Suporte Técnico' || $user['login_name'] === 'superadmin');

        // Buscar Pesquisas
        $surveys = $this->db()->prepare("
            SELECT s.*, 
            (SELECT COUNT(*) FROM survey_responses r WHERE r.survey_id = s.id AND r.user_id = ?) as responded 
            FROM surveys s 
            WHERE company_id = ? 
            ORDER BY created_at DESC
        ");
        $surveys->execute([$userId, $compId]);
        $surveys = $surveys->fetchAll();

        return $this->view('survey', [
            'surveys' => $surveys,
            'isAllowedToManage' => $isAllowedToManage,
            'view' => $_GET['view'] ?? 'list',
            'activeSurveyId' => $_GET['id'] ?? null,
            'user' => $user
        ]);
    }

    public function store() {
        Auth::check();
        $user = Auth::user();
        $compId = $_SESSION['company_id'];
        $userId = $_SESSION['user_id'];
        $isAllowedToManage = ($user['role'] === 'Administrador' || $user['role'] === 'RH' || $user['role'] === 'Suporte Técnico' || $user['login_name'] === 'superadmin');

        $action = $_POST['action'] ?? '';

        if ($action === 'create_survey' && $isAllowedToManage) {
            $title = $_POST['title'];
            $desc = $_POST['description'];
            
            $stmt = $this->db()->prepare("INSERT INTO surveys (title, description, company_id, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $desc, $compId, $userId]);
            $surveyId = $this->db()->lastInsertId();
            
            if (isset($_POST['questions'])) {
                foreach ($_POST['questions'] as $q) {
                    $stmtQ = $this->db()->prepare("INSERT INTO survey_questions (survey_id, question_text) VALUES (?, ?)");
                    $stmtQ->execute([$surveyId, $q['text']]);
                    $questionId = $this->db()->lastInsertId();
                    
                    if (isset($q['options'])) {
                        foreach ($q['options'] as $opt) {
                            $stmtO = $this->db()->prepare("INSERT INTO survey_options (question_id, option_text) VALUES (?, ?)");
                            $stmtO->execute([$questionId, $opt]);
                        }
                    }
                }
            }
            Logger::log('Criou uma nova pesquisa: ' . $title, 'Pesquisa');
            return $this->redirect('/pesquisa?msg=created');
        }
        
        if ($action === 'submit_response') {
            $surveyId = $_POST['survey_id'];
            foreach ($_POST['answers'] as $questionId => $optionId) {
                $stmt = $this->db()->prepare("INSERT INTO survey_responses (survey_id, question_id, option_id, user_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$surveyId, $questionId, $optionId, $userId]);
            }
            Logger::log('Respondeu à pesquisa ID: ' . $surveyId, 'Pesquisa');
            return $this->redirect('/pesquisa?msg=submitted');
        }

        if ($action === 'delete_survey' && $isAllowedToManage) {
            $surveyId = $_POST['survey_id'];
            $stmt = $this->db()->prepare("DELETE FROM surveys WHERE id = ? AND company_id = ?");
            $stmt->execute([$surveyId, $compId]);
            Logger::log('Excluiu a pesquisa ID: ' . $surveyId, 'Pesquisa');
            return $this->redirect('/pesquisa?msg=deleted');
        }

        return $this->redirect('/pesquisa');
    }
}
