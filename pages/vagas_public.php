<?php
// pages/vagas_public.php
require_once __DIR__ . '/../config.php';

$company_id = isset($_GET['c']) ? (int)$_GET['c'] : 0;
if ($company_id === 0) {
    die("Acesso inválido. O link da empresa não foi fornecido.");
}

// Obter dados da empresa
$stmt = $pdo->prepare("SELECT name FROM tenants WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch();
if (!$company) {
    die("Empresa não encontrada.");
}

// Processar candidatura
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply') {
    $job_id = (int)$_POST['job_id'];
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // Verificar se já aplicou
    $stmt_check = $pdo->prepare("SELECT id FROM rh_candidates WHERE job_id = ? AND email = ?");
    $stmt_check->execute([$job_id, $email]);
    if ($stmt_check->fetch()) {
        $error_msg = 'Você já se candidatou para esta vaga com este e-mail.';
    } else {
        $resume_url = '';
        if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                $filename = 'cv_' . time() . '_' . rand(1000, 9999) . '.pdf';
                $dest = __DIR__ . '/../uploads/resumes/' . $filename;
                if (move_uploaded_file($_FILES['resume']['tmp_name'], $dest)) {
                    $resume_url = 'uploads/resumes/' . $filename;
                }
            } else {
                $error_msg = 'Por favor, envie o currículo apenas em formato PDF.';
            }
        } else {
            $error_msg = 'O envio do currículo é obrigatório.';
        }

        if (empty($error_msg)) {
            $stmt_insert = $pdo->prepare("INSERT INTO rh_candidates (job_id, company_id, name, email, phone, resume_url) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt_insert->execute([$job_id, $company_id, $name, $email, $phone, $resume_url])) {
                $success_msg = 'Sua candidatura foi enviada com sucesso! O RH entrará em contato.';
            } else {
                $error_msg = 'Ocorreu um erro ao enviar sua candidatura. Tente novamente.';
            }
        }
    }
}

// Obter Vagas Abertas
$stmt_jobs = $pdo->prepare("SELECT * FROM rh_jobs WHERE company_id = ? AND status = 'Aberta' ORDER BY id DESC");
$stmt_jobs->execute([$company_id]);
$jobs = $stmt_jobs->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vagas Abertas - <?= htmlspecialchars($company['name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #f8fafc;
            --text-color: #1e293b;
            --primary: #3b82f6;
            --card-bg: #ffffff;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg-color); color: var(--text-color); line-height: 1.6; }
        .header { background: #0f172a; padding: 3rem 2rem; text-align: center; color: white; }
        .header h1 { font-size: 2.5rem; font-weight: 900; margin-bottom: 0.5rem; }
        .header p { color: #94a3b8; font-size: 1.1rem; }
        .container { max-width: 1000px; margin: 0 auto; padding: 2rem; }
        
        .job-card { background: var(--card-bg); border-radius: 16px; padding: 2rem; margin-bottom: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; transition: transform 0.2s; }
        .job-card:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .job-title { font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-bottom: 0.5rem; }
        .job-meta { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; color: #64748b; font-size: 0.9rem; font-weight: 500; }
        .job-meta span { display: flex; align-items: center; gap: 0.4rem; background: #f1f5f9; padding: 0.3rem 0.8rem; border-radius: 20px; }
        .job-desc { margin-bottom: 1.5rem; color: #475569; }
        .job-desc strong { color: #1e293b; }
        .btn-apply { display: inline-block; background: var(--primary); color: white; border: none; padding: 0.8rem 1.5rem; border-radius: 8px; font-weight: 700; cursor: pointer; transition: 0.2s; text-decoration: none; }
        .btn-apply:hover { background: #2563eb; }

        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 2rem; font-weight: 600; text-align: center; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-error { background: #fee2e2; color: #991b1b; }

        /* Modal Formulário */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: none; justify-content: center; align-items: center; z-index: 1000; padding: 1rem; }
        .modal-content { background: white; width: 100%; max-width: 500px; border-radius: 16px; padding: 2rem; position: relative; max-height: 90vh; overflow-y: auto; }
        .modal-close { position: absolute; top: 1.5rem; right: 1.5rem; font-size: 1.5rem; cursor: pointer; color: #94a3b8; }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #334155; }
        .form-group input { width: 100%; padding: 0.8rem; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit; font-size: 1rem; }
        .form-group input[type="file"] { padding: 0.5rem; }
    </style>
</head>
<body>

<div class="header">
    <h1><?= htmlspecialchars($company['name']) ?></h1>
    <p>Trabalhe Conosco - Vagas em Aberto</p>
</div>

<div class="container">
    <?php if ($success_msg): ?>
        <div class="alert alert-success"><i class="fa-solid fa-check-circle"></i> <?= $success_msg ?></div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= $error_msg ?></div>
    <?php endif; ?>

    <?php if (count($jobs) === 0): ?>
        <div style="text-align: center; padding: 4rem 1rem; color: #64748b;">
            <i class="fa-solid fa-folder-open" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
            <h3>Nenhuma vaga aberta no momento</h3>
            <p>Fique de olho, em breve teremos novas oportunidades.</p>
        </div>
    <?php else: ?>
        <?php foreach ($jobs as $job): ?>
            <div class="job-card">
                <h2 class="job-title"><?= htmlspecialchars($job['title']) ?></h2>
                <div class="job-meta">
                    <?php if(!empty($job['sector'])): ?><span><i class="fa-solid fa-building"></i> <?= htmlspecialchars($job['sector']) ?></span><?php endif; ?>
                    <?php if(!empty($job['contract_type'])): ?><span><i class="fa-solid fa-file-signature"></i> <?= htmlspecialchars($job['contract_type']) ?></span><?php endif; ?>
                    <?php if($job['show_salary'] && $job['salary'] > 0): ?><span><i class="fa-solid fa-money-bill-wave"></i> R$ <?= number_format($job['salary'], 2, ',', '.') ?></span><?php endif; ?>
                    <?php if(!empty($job['work_days'])): ?><span><i class="fa-solid fa-calendar-week"></i> <?= htmlspecialchars($job['work_days']) ?></span><?php endif; ?>
                </div>
                
                <div class="job-desc">
                    <?php if(!empty($job['responsibilities'])): ?>
                        <strong>Principais Atividades:</strong><br>
                        <?= nl2br(htmlspecialchars($job['responsibilities'])) ?><br><br>
                    <?php endif; ?>
                    
                    <?php if(!empty($job['benefits'])): ?>
                        <strong>Benefícios:</strong><br>
                        <?= nl2br(htmlspecialchars($job['benefits'])) ?>
                    <?php endif; ?>
                </div>

                <button class="btn-apply" onclick="openModal(<?= $job['id'] ?>, '<?= htmlspecialchars(addslashes($job['title'])) ?>')">
                    Participar do Processo
                </button>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Modal Candidatura -->
<div class="modal-overlay" id="applyModal">
    <div class="modal-content">
        <i class="fa-solid fa-times modal-close" onclick="closeModal()"></i>
        <h2 style="margin-bottom: 0.5rem; color: #0f172a; font-weight: 800;">Candidatar-se</h2>
        <p style="margin-bottom: 1.5rem; color: #64748b;" id="modalJobTitle">Vaga</p>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="apply">
            <input type="hidden" name="job_id" id="modalJobId" value="">
            
            <div class="form-group">
                <label>Nome Completo *</label>
                <input type="text" name="name" required placeholder="Digite seu nome">
            </div>
            <div class="form-group">
                <label>E-mail *</label>
                <input type="email" name="email" required placeholder="Digite seu melhor e-mail">
            </div>
            <div class="form-group">
                <label>Celular / WhatsApp *</label>
                <input type="text" name="phone" required placeholder="(11) 99999-9999">
            </div>
            <div class="form-group">
                <label>Anexar Currículo (Apenas PDF) *</label>
                <input type="file" name="resume" accept=".pdf" required>
            </div>
            
            <button type="submit" class="btn-apply" style="width: 100%; margin-top: 1rem; font-size: 1.1rem;">
                Enviar Candidatura
            </button>
        </form>
    </div>
</div>

<script>
    function openModal(jobId, jobTitle) {
        document.getElementById('modalJobId').value = jobId;
        document.getElementById('modalJobTitle').innerText = 'Vaga: ' + jobTitle;
        document.getElementById('applyModal').style.display = 'flex';
    }
    
    function closeModal() {
        document.getElementById('applyModal').style.display = 'none';
    }

    // Fechar clicando fora
    window.onclick = function(event) {
        if (event.target == document.getElementById('applyModal')) {
            closeModal();
        }
    }
</script>

</body>
</html>
