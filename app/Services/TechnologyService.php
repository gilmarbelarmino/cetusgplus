<?php
namespace App\Services;

use PDO;

class TechnologyService {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // --- Câmeras ---
    public function listCameras() {
        return $this->pdo->query("SELECT * FROM tech_cameras ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveCamera($data) {
        if (!empty($data['id'])) {
            $stmt = $this->pdo->prepare("UPDATE tech_cameras SET name = ?, ip_address = ?, quantity = ? WHERE id = ?");
            $stmt->execute([$data['name'], $data['ip_address'], $data['quantity'], $data['id']]);
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO tech_cameras (name, ip_address, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$data['name'], $data['ip_address'], $data['quantity']]);
        }
    }

    public function deleteCamera($id) {
        $stmt = $this->pdo->prepare("DELETE FROM tech_cameras WHERE id = ?");
        $stmt->execute([$id]);
    }

    // --- Acessos Remotos ---
    public function listRemotes() {
        $sql = "SELECT tr.*, u.name as user_name, u.avatar_url, u.email as user_email 
                FROM tech_remote_access tr 
                LEFT JOIN users u ON tr.user_id = u.id 
                ORDER BY u.name";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveRemote($data) {
        if (!empty($data['id'])) {
            $stmt = $this->pdo->prepare("UPDATE tech_remote_access SET user_id = ?, pc_password = ?, email_password = ?, pc_name = ?, observations = ? WHERE id = ?");
            $stmt->execute([$data['user_id'], $data['pc_password'], $data['email_password'], $data['pc_name'], $data['observations'], $data['id']]);
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO tech_remote_access (user_id, pc_password, email_password, pc_name, observations) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$data['user_id'], $data['pc_password'], $data['email_password'], $data['pc_name'], $data['observations']]);
        }
    }

    public function deleteRemote($id) {
        $stmt = $this->pdo->prepare("DELETE FROM tech_remote_access WHERE id = ?");
        $stmt->execute([$id]);
    }

    // --- E-mails ---
    public function listEmails() {
        $sql = "SELECT te.*, u.name as user_name 
                FROM tech_emails te 
                LEFT JOIN users u ON te.remote_user_id = u.id 
                ORDER BY te.email";
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveEmail($data) {
        if (!empty($data['id'])) {
            $stmt = $this->pdo->prepare("UPDATE tech_emails SET email = ?, password = ?, type = ?, remote_user_id = ?, usage_date = ? WHERE id = ?");
            $stmt->execute([$data['email'], $data['password'], $data['type'], $data['remote_user_id'], $data['usage_date'], $data['id']]);
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO tech_emails (email, password, type, remote_user_id, usage_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$data['email'], $data['password'], $data['type'], $data['remote_user_id'], $data['usage_date']]);
        }
    }

    public function deleteEmail($id) {
        $stmt = $this->pdo->prepare("DELETE FROM tech_emails WHERE id = ?");
        $stmt->execute([$id]);
    }
}
