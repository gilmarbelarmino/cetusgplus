<?php
$compId = getCurrentUserCompanyId();
$userId = $_SESSION['user_id'];
$isAllowedToManage = ($user['role'] === 'Administrador' || $user['role'] === 'RH' || $user['role'] === 'Suporte Técnico' || $user['login_name'] === 'superadmin');
$isAdmin = ($user['role'] === 'Administrador' || $user['login_name'] === 'superadmin');

// --- AUTO MIGRATION: Garantir tabelas de Pesquisa ---
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS surveys (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        company_id INT NOT NULL,
        created_by VARCHAR(50),
        status ENUM('Ativa', 'Encerrada') DEFAULT 'Ativa',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS survey_questions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        survey_id INT NOT NULL,
        question_text TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS survey_options (
        id INT AUTO_INCREMENT PRIMARY KEY,
        question_id INT NOT NULL,
        option_text VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS survey_responses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        survey_id INT NOT NULL,
        question_id INT NOT NULL,
        option_id INT NOT NULL,
        user_id VARCHAR(50) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
} catch (Exception $e) {}

// Processar Ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create_survey' && $isAllowedToManage) {
            $title = $_POST['title'];
            $desc = $_POST['description'];
            
            $stmt = $pdo->prepare("INSERT INTO surveys (title, description, company_id, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $desc, $compId, $userId]);
            $surveyId = $pdo->lastInsertId();
            
            if (isset($_POST['questions'])) {
                foreach ($_POST['questions'] as $q) {
                    $stmtQ = $pdo->prepare("INSERT INTO survey_questions (survey_id, question_text) VALUES (?, ?)");
                    $stmtQ->execute([$surveyId, $q['text']]);
                    $questionId = $pdo->lastInsertId();
                    
                    if (isset($q['options'])) {
                        foreach ($q['options'] as $opt) {
                            $stmtO = $pdo->prepare("INSERT INTO survey_options (question_id, option_text) VALUES (?, ?)");
                            $stmtO->execute([$questionId, $opt]);
                        }
                    }
                }
            }
            header("Location: index.php?page=pesquisa&msg=created");
            exit;
        }
        
        if ($_POST['action'] === 'submit_response') {
            $surveyId = $_POST['survey_id'];
            foreach ($_POST['answers'] as $questionId => $optionId) {
                $stmt = $pdo->prepare("INSERT INTO survey_responses (survey_id, question_id, option_id, user_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$surveyId, $questionId, $optionId, $userId]);
            }
            header("Location: index.php?page=pesquisa&msg=submitted");
            exit;
        }

        if ($_POST['action'] === 'delete_survey' && $isAdmin) {
            $surveyId = $_POST['survey_id'];
            $stmt = $pdo->prepare("DELETE FROM surveys WHERE id = ? AND company_id = ?");
            $stmt->execute([$surveyId, $compId]);
            header("Location: index.php?page=pesquisa&msg=deleted");
            exit;
        }
    }
}

// Buscar Pesquisas
$stmt = $pdo->prepare("SELECT s.*, (SELECT COUNT(*) FROM survey_responses r WHERE CONVERT(r.survey_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(s.id USING utf8mb4) COLLATE utf8mb4_unicode_ci AND CONVERT(r.user_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = ?) as responded 
                       FROM surveys s WHERE company_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId, $compId]);
$surveys = $stmt->fetchAll();

$view = $_GET['view'] ?? 'list';
$activeSurveyId = $_GET['id'] ?? null;
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Sistema de Pesquisas</h1>
        <p class="page-subtitle">Participe e ajude-nos a melhorar</p>
    </div>
    <?php if ($isAllowedToManage): ?>
        <button class="btn-primary" onclick="showCreateModal()">
            <i class="fa-solid fa-plus"></i> Nova Pesquisa
        </button>
    <?php endif; ?>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="glass-panel" style="background: rgba(16, 185, 129, 0.1); border-color: #10B981; color: #065F46; padding: 1rem; margin-bottom: 1.5rem;">
        <?php 
            if($_GET['msg'] === 'created') echo "Pesquisa criada com sucesso!";
            if($_GET['msg'] === 'submitted') echo "Sua resposta foi enviada. Obrigado!";
            if($_GET['msg'] === 'deleted') echo "Pesquisa removida.";
        ?>
    </div>
<?php endif; ?>

<div class="glass-panel">
    <?php if ($view === 'list'): ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Status</th>
                        <th>Data</th>
                        <th>Sua Participação</th>
                        <th style="text-align: right;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($surveys as $s): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($s['title']) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-soft);"><?= htmlspecialchars($s['description']) ?></div>
                            </td>
                            <td>
                                <span class="badge" style="background: <?= $s['status'] === 'Ativa' ? '#10B98120' : '#EF444420' ?>; color: <?= $s['status'] === 'Ativa' ? '#10B981' : '#EF4444' ?>;">
                                    <?= $s['status'] ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($s['created_at'])) ?></td>
                            <td>
                                <?php if ($s['responded'] > 0): ?>
                                    <span style="color: #10B981; font-weight: 700;"><i class="fa-solid fa-check"></i> Respondido</span>
                                <?php else: ?>
                                    <span style="color: var(--text-soft);">Pendente</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <?php if ($s['responded'] == 0 && $s['status'] === 'Ativa'): ?>
                                    <a href="index.php?page=pesquisa&view=answer&id=<?= $s['id'] ?>" class="btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">Responder</a>
                                <?php endif; ?>
                                
                                <?php if ($isAllowedToManage): ?>
                                    <a href="index.php?page=pesquisa&view=analysis&id=<?= $s['id'] ?>" class="btn-secondary" title="Análise">
                                        <i class="fa-solid fa-chart-pie"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($isAdmin): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir esta pesquisa?')">
                                        <input type="hidden" name="action" value="delete_survey">
                                        <input type="hidden" name="survey_id" value="<?= $s['id'] ?>">
                                        <button type="submit" class="btn-secondary" style="color: #EF4444; border-color: rgba(239, 68, 68, 0.2);" title="Excluir Pesquisa">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($view === 'answer' && $activeSurveyId): 
        $stmt = $pdo->prepare("SELECT * FROM surveys WHERE id = ? AND company_id = ?");
        $stmt->execute([$activeSurveyId, $compId]);
        $survey = $stmt->fetch();
        
        $stmtQ = $pdo->prepare("SELECT * FROM survey_questions WHERE survey_id = ?");
        $stmtQ->execute([$activeSurveyId]);
        $questions = $stmtQ->fetchAll();
    ?>
        <div style="max-width: 800px; margin: 0 auto; padding: 2rem;">
            <div style="margin-bottom: 2rem;">
                <a href="index.php?page=pesquisa" style="color: var(--brand-primary); text-decoration: none; font-weight: 600;">
                    <i class="fa-solid fa-arrow-left"></i> Voltar
                </a>
                <h2 style="margin-top: 1rem; color: var(--text-main);"><?= htmlspecialchars($survey['title']) ?></h2>
                <p style="color: var(--text-soft);"><?= htmlspecialchars($survey['description']) ?></p>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="submit_response">
                <input type="hidden" name="survey_id" value="<?= $activeSurveyId ?>">
                
                <?php foreach ($questions as $index => $q): 
                    $stmtO = $pdo->prepare("SELECT * FROM survey_options WHERE question_id = ?");
                    $stmtO->execute([$q['id']]);
                    $options = $stmtO->fetchAll();
                ?>
                    <div class="glass-panel" style="margin-bottom: 1.5rem; border-left: 4px solid var(--brand-primary);">
                        <p style="font-weight: 700; margin-bottom: 1rem; font-size: 1.1rem; color: var(--text-main);">
                            <?= ($index + 1) ?>. <?= htmlspecialchars($q['question_text']) ?>
                        </p>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <?php foreach ($options as $o): ?>
                                <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; padding: 0.75rem; background: var(--brand-primary-soft); border-radius: 8px; transition: 0.2s;" class="option-label">
                                    <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $o['id'] ?>" required style="accent-color: var(--brand-primary); width: 1.2rem; height: 1.2rem;">
                                    <span style="color: var(--text-main);"><?= htmlspecialchars($o['option_text']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div style="text-align: center; margin-top: 2rem;">
                    <button type="submit" class="btn-primary" style="padding: 1rem 3rem; font-size: 1.1rem;">Enviar Respostas</button>
                </div>
            </form>
        </div>

    <?php elseif ($view === 'analysis' && $activeSurveyId && $isAllowedToManage): 
        $stmt = $pdo->prepare("SELECT * FROM surveys WHERE id = ? AND company_id = ?");
        $stmt->execute([$activeSurveyId, $compId]);
        $survey = $stmt->fetch();

        $stmtQ = $pdo->prepare("SELECT * FROM survey_questions WHERE survey_id = ?");
        $stmtQ->execute([$activeSurveyId]);
        $questions = $stmtQ->fetchAll();
        
        // Buscar Respostas Detalhadas (Quem respondeu o que)
        $stmtR = $pdo->prepare("
            SELECT r.*, u.name, u.avatar_url, o.option_text, q.question_text
            FROM survey_responses r
            JOIN users u ON CONVERT(r.user_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(u.id USING utf8mb4) COLLATE utf8mb4_unicode_ci
            JOIN survey_options o ON r.option_id = o.id
            JOIN survey_questions q ON r.question_id = q.id
            WHERE r.survey_id = ?
            ORDER BY r.created_at DESC
        ");
        $stmtR->execute([$activeSurveyId]);
        $responses = $stmtR->fetchAll();
    ?>
        <div style="padding: 1rem;">
            <div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: end;">
                <div>
                    <a href="index.php?page=pesquisa" style="color: var(--brand-primary); text-decoration: none; font-weight: 600;">
                        <i class="fa-solid fa-arrow-left"></i> Voltar
                    </a>
                    <h2 style="margin-top: 1rem; color: var(--text-main);">Análise: <?= htmlspecialchars($survey['title']) ?></h2>
                    <p style="color: var(--text-soft);">Total de participações: <?= count(array_unique(array_column($responses, 'user_id'))) ?></p>
                </div>
                <button class="btn-secondary" onclick="window.print()"><i class="fa-solid fa-print"></i> Imprimir Relatório</button>
            </div>

            <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));">
                <?php foreach ($questions as $q): 
                    $stmtStats = $pdo->prepare("
                        SELECT o.option_text, COUNT(r.id) as total
                        FROM survey_options o
                        LEFT JOIN survey_responses r ON o.id = r.option_id
                        WHERE o.question_id = ?
                        GROUP BY o.id
                    ");
                    $stmtStats->execute([$q['id']]);
                    $stats = $stmtStats->fetchAll();
                    $labels = array_column($stats, 'option_text');
                    $data = array_column($stats, 'total');
                ?>
                    <div class="glass-panel" style="padding: 1.5rem;">
                        <h3 style="font-size: 1rem; margin-bottom: 1.5rem; color: var(--text-main); height: 3rem; overflow: hidden;"><?= htmlspecialchars($q['question_text']) ?></h3>
                        <div style="height: 300px;">
                            <canvas id="chart-<?= $q['id'] ?>"></canvas>
                        </div>
                                  <script>
                    (function() {
                        // Função para inicializar o gráfico com segurança
                        function initChart(qId, labels, data) {
                            const ctx = document.getElementById('chart-' + qId);
                            if (!ctx) return;

                            // Verificar se o Chart.js está disponível
                            if (typeof Chart === 'undefined') {
                                console.error('Chart.js não carregado');
                                return;
                            }

                            // Configurações de cores dinâmicas baseadas no tema
                            const style = getComputedStyle(document.documentElement);
                            const textColor = style.getPropertyValue('--text-main').trim() || '#333';

                            const config = {
                                type: 'bar',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        data: data,
                                        backgroundColor: ['#6366F1', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899'],
                                        borderRadius: 8,
                                        borderSkipped: false,
                                        barThickness: 32
                                    }]
                                },
                                options: {
                                    indexAxis: 'x', // Barras verticais com nomes abaixo
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: { display: false },
                                        tooltip: {
                                            backgroundColor: '#0F172A',
                                            padding: 12,
                                            titleFont: { size: 14, weight: 'bold' },
                                            bodyFont: { size: 13 }
                                        }
                                    },
                                    scales: {
                                        x: {
                                            grid: { display: false, drawBorder: false },
                                            ticks: { color: textColor, font: { weight: '700' } }
                                        },
                                        y: {
                                            beginAtZero: true,
                                            grid: { color: 'rgba(148, 163, 184, 0.1)', drawBorder: false },
                                            ticks: { color: textColor, font: { weight: '600' } }
                                        }
                                    }
                                }
                            };

                            // Adicionar datalabels apenas se o plugin estiver carregado
                            if (typeof ChartDataLabels !== 'undefined') {
                                try {
                                    Chart.register(ChartDataLabels);
                                    config.options.plugins.datalabels = {
                                        color: textColor,
                                        anchor: 'end',
                                        align: 'top',
                                        offset: 4,
                                        font: { weight: 'bold', size: 12 },
                                        formatter: (val, context) => {
                                            let sum = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                            return sum > 0 ? (val * 100 / sum).toFixed(0) + "%" : "";
                                        },
                                        display: function(context) {
                                            return context.dataset.data[context.dataIndex] > 0;
                                        }
                                    };
                                } catch(e) {
                                    console.warn('Erro ao registrar ChartDataLabels:', e);
                                }
                            }

                            new Chart(ctx, config);
                        }

                        document.addEventListener('DOMContentLoaded', () => {
                            setTimeout(() => {
                                initChart('<?= $q['id'] ?>', <?= json_encode($labels) ?>, <?= json_encode($data) ?>);
                            }, 100);
                        });
                    })();
                    </script>
                <?php endforeach; ?>
            </div>

            <div class="glass-panel" style="margin-top: 2rem;">
                <h3 style="margin-bottom: 1.5rem; color: var(--text-main);">Detalhamento de Respostas</h3>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Usuário</th>
                                <th>Pergunta</th>
                                <th>Resposta</th>
                                <th>Data/Horário</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($responses as $r): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <div class="user-avatar" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                                <?php if($r['avatar_url']): ?>
                                                    <img src="<?= $r['avatar_url'] ?>" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">
                                                <?php else: ?>
                                                    <?= strtoupper(substr($r['name'], 0, 1)) ?>
                                                <?php endif; ?>
                                            </div>
                                            <span style="font-weight: 600; color: var(--text-main);"><?= htmlspecialchars($r['name']) ?></span>
                                        </div>
                                    </td>
                                    <td style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-soft);"><?= htmlspecialchars($r['question_text']) ?></td>
                                    <td><span style="color: var(--brand-primary); font-weight: 700;"><?= htmlspecialchars($r['option_text']) ?></span></td>
                                    <td style="color: var(--text-muted);"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Criar Pesquisa -->
<div id="createModal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:2000; align-items:center; justify-content:center;">
    <div class="glass-panel" style="width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; padding: 2rem;">
        <h2 style="margin-bottom: 1.5rem;">Criar Nova Pesquisa</h2>
        <form method="POST" id="createForm">
            <input type="hidden" name="action" value="create_survey">
            <div style="margin-bottom: 1.5rem;">
                <label style="display:block; margin-bottom:0.5rem; font-weight:700;">Título da Pesquisa</label>
                <input type="text" name="title" required class="form-input" placeholder="Ex: Pesquisa de Clima Organizacional">
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display:block; margin-bottom:0.5rem; font-weight:700;">Descrição</label>
                <textarea name="description" class="form-input" style="height: 80px;" placeholder="Explique o objetivo desta pesquisa..."></textarea>
            </div>

            <div id="questionsContainer">
                <!-- Perguntas serão adicionadas aqui -->
            </div>

            <button type="button" class="btn-secondary" onclick="addQuestion()" style="width: 100%; margin-bottom: 2rem;">
                <i class="fa-solid fa-plus"></i> Adicionar Pergunta
            </button>

            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" class="btn-secondary" onclick="hideCreateModal()">Cancelar</button>
                <button type="submit" class="btn-primary">Criar Pesquisa</button>
            </div>
        </form>
    </div>
</div>

<script>
let questionCount = 0;

function showCreateModal() {
    document.getElementById('createModal').style.display = 'flex';
    if (questionCount === 0) addQuestion();
}

function hideCreateModal() {
    document.getElementById('createModal').style.display = 'none';
}

function addQuestion() {
    const id = questionCount++;
    const html = `
        <div class="glass-panel" style="margin-bottom: 1.5rem; background: rgba(255,255,255,0.03);" id="q-block-${id}">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                <span style="font-weight:900; color:var(--brand-primary);">PERGUNTA #${id+1}</span>
                <button type="button" onclick="removeQuestion(${id})" style="background:none; border:none; color:#EF4444; cursor:pointer;"><i class="fa-solid fa-trash"></i></button>
            </div>
            <input type="text" name="questions[${id}][text]" required class="form-input" placeholder="Digite sua pergunta aqui..." style="margin-bottom:1rem;">
            
            <div id="opts-container-${id}">
                <div style="display:flex; gap:0.5rem; margin-bottom:0.5rem;">
                    <input type="text" name="questions[${id}][options][]" required class="form-input" placeholder="Alternativa 1">
                </div>
                <div style="display:flex; gap:0.5rem; margin-bottom:0.5rem;">
                    <input type="text" name="questions[${id}][options][]" required class="form-input" placeholder="Alternativa 2">
                </div>
            </div>
            <button type="button" class="btn-secondary" style="font-size:0.75rem; padding:0.3rem 0.6rem;" onclick="addOption(${id})">+ Adicionar Alternativa</button>
        </div>
    `;
    document.getElementById('questionsContainer').insertAdjacentHTML('beforeend', html);
}

function removeQuestion(id) {
    document.getElementById(`q-block-${id}`).remove();
}

function addOption(qId) {
    const html = `
        <div style="display:flex; gap:0.5rem; margin-bottom:0.5rem;">
            <input type="text" name="questions[${qId}][options][]" required class="form-input" placeholder="Nova Alternativa">
            <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#EF4444; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
    `;
    document.getElementById(`opts-container-${qId}`).insertAdjacentHTML('beforeend', html);
}
</script>

<style>
.option-label:hover {
    background: var(--brand-primary-soft) !important;
    border-color: var(--brand-primary);
}
.modal-overlay {
    animation: fadeIn 0.3s ease;
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
</style>
