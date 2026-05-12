<?php
/**
 * SURVEY VIEW (MVC)
 * =================
 */
$compId = $_SESSION['company_id'];
$userId = $_SESSION['user_id'];
?>

<div class="page-header" style="background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%); padding: 2rem; border-radius: 1.5rem; color: white; margin-bottom: 2rem; box-shadow: 0 10px 30px rgba(99, 102, 241, 0.15);">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="font-size: 2rem; font-weight: 900; margin: 0; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-square-poll-vertical"></i>
                Pesquisas e Opinião
            </h1>
            <p style="opacity: 0.9; font-size: 1rem; margin-top: 0.3rem;">Sua voz é fundamental para construirmos um ambiente melhor.</p>
        </div>
        <?php if ($isAllowedToManage): ?>
            <button class="btn-primary" onclick="showCreateModal()" style="background: white; color: #6366f1; border: none; padding: 0.75rem 1.5rem; font-weight: 700;">
                <i class="fa-solid fa-plus"></i> Nova Pesquisa
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['msg'])): ?>
    <div class="glass-panel" style="background: rgba(16, 185, 129, 0.1); border-color: #10B981; color: #065F46; padding: 1rem; margin-bottom: 1.5rem; border-radius: 1rem; display: flex; align-items: center; gap: 0.75rem;">
        <i class="fa-solid fa-circle-check"></i>
        <span>
            <?php 
                if($_GET['msg'] === 'created') echo "Pesquisa criada com sucesso!";
                if($_GET['msg'] === 'submitted') echo "Sua resposta foi enviada. Obrigado!";
                if($_GET['msg'] === 'deleted') echo "Pesquisa removida.";
            ?>
        </span>
    </div>
<?php endif; ?>

<div class="glass-panel" style="border-radius: 1.5rem; padding: 1.5rem;">
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
                                <div style="font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($s['title']) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-soft);"><?= htmlspecialchars($s['description']) ?></div>
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
                                    <a href="<?= URL_BASE ?>/pesquisa?view=answer&id=<?= $s['id'] ?>" class="btn-primary" style="padding: 0.5rem 1rem; font-size: 0.85rem; border-radius: 0.75rem;">Responder</a>
                                <?php endif; ?>
                                
                                <?php if ($isAllowedToManage): ?>
                                    <a href="<?= URL_BASE ?>/pesquisa?view=analysis&id=<?= $s['id'] ?>" class="btn-secondary" title="Análise" style="border-radius: 0.75rem; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                                        <i class="fa-solid fa-chart-pie"></i>
                                    </a>
                                    <form action="<?= URL_BASE ?>/pesquisa" method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir esta pesquisa?')">
                                        <input type="hidden" name="action" value="delete_survey">
                                        <input type="hidden" name="survey_id" value="<?= $s['id'] ?>">
                                        <button type="submit" class="btn-secondary" style="color: #EF4444; border-color: rgba(239, 68, 68, 0.2); border-radius: 0.75rem; width: 36px; height: 36px; padding: 0;">
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
        // No MVC, as consultas auxiliares podem ser feitas aqui ou passadas pelo controller.
        // Como o SurveyController não passou tudo, vou pegar direto via $pdo (que é global no framework cetusg)
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM surveys WHERE id = ? AND company_id = ?");
        $stmt->execute([$activeSurveyId, $compId]);
        $survey = $stmt->fetch();
        
        $stmtQ = $pdo->prepare("SELECT * FROM survey_questions WHERE survey_id = ?");
        $stmtQ->execute([$activeSurveyId]);
        $questions = $stmtQ->fetchAll();
    ?>
        <div style="max-width: 800px; margin: 0 auto; padding: 1rem;">
            <div style="margin-bottom: 2rem;">
                <a href="<?= URL_BASE ?>/pesquisa" style="color: #6366f1; text-decoration: none; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-solid fa-arrow-left"></i> Voltar para a lista
                </a>
                <h2 style="margin-top: 1.5rem; color: var(--text-main); font-size: 1.75rem; font-weight: 900;"><?= htmlspecialchars($survey['title']) ?></h2>
                <p style="color: var(--text-soft); font-size: 1.1rem;"><?= htmlspecialchars($survey['description']) ?></p>
            </div>

            <form action="<?= URL_BASE ?>/pesquisa" method="POST">
                <input type="hidden" name="action" value="submit_response">
                <input type="hidden" name="survey_id" value="<?= $activeSurveyId ?>">
                
                <?php foreach ($questions as $index => $q): 
                    $stmtO = $pdo->prepare("SELECT * FROM survey_options WHERE question_id = ?");
                    $stmtO->execute([$q['id']]);
                    $options = $stmtO->fetchAll();
                ?>
                    <div class="glass-panel" style="margin-bottom: 1.5rem; border-left: 6px solid #6366f1; border-radius: 1.25rem; padding: 2rem;">
                        <p style="font-weight: 800; margin-bottom: 1.5rem; font-size: 1.25rem; color: var(--text-main); line-height: 1.4;">
                            <span style="background: #6366f1; color: white; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 0.9rem; margin-right: 0.5rem;"><?= ($index + 1) ?></span>
                            <?= htmlspecialchars($q['question_text']) ?>
                        </p>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($options as $o): ?>
                                <label class="survey-option" style="display: flex; align-items: center; gap: 1rem; cursor: pointer; padding: 1.25rem; background: var(--bg-main); border: 2px solid var(--border-color); border-radius: 1rem; transition: all 0.2s;">
                                    <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $o['id'] ?>" required style="accent-color: #6366f1; width: 1.3rem; height: 1.3rem;">
                                    <span style="color: var(--text-main); font-weight: 600; font-size: 1rem;"><?= htmlspecialchars($o['option_text']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div style="text-align: center; margin-top: 3rem;">
                    <button type="submit" class="btn-primary" style="padding: 1.25rem 4rem; font-size: 1.25rem; border-radius: 1.25rem; box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);">Enviar Minha Resposta</button>
                </div>
            </form>
        </div>

    <?php elseif ($view === 'analysis' && $activeSurveyId && $isAllowedToManage): 
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM surveys WHERE id = ? AND company_id = ?");
        $stmt->execute([$activeSurveyId, $compId]);
        $survey = $stmt->fetch();

        $stmtQ = $pdo->prepare("SELECT * FROM survey_questions WHERE survey_id = ?");
        $stmtQ->execute([$activeSurveyId]);
        $questions = $stmtQ->fetchAll();
        
        $stmtR = $pdo->prepare("
            SELECT r.*, u.name, u.avatar_url, o.option_text, q.question_text
            FROM survey_responses r
            JOIN users u ON r.user_id = u.id
            JOIN survey_options o ON r.option_id = o.id
            JOIN survey_questions q ON r.question_id = q.id
            WHERE r.survey_id = ?
            ORDER BY r.created_at DESC
        ");
        $stmtR->execute([$activeSurveyId]);
        $responses = $stmtR->fetchAll();
    ?>
        <div style="padding: 0.5rem;">
            <div style="margin-bottom: 2.5rem; display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <a href="<?= URL_BASE ?>/pesquisa" style="color: #6366f1; text-decoration: none; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-arrow-left"></i> Voltar
                    </a>
                    <h2 style="margin-top: 1rem; color: var(--text-main); font-size: 2rem; font-weight: 900;">Análise de Resultados</h2>
                    <p style="color: var(--text-soft); font-weight: 600;">Participantes: <span style="color: #6366f1;"><?= count(array_unique(array_column($responses, 'user_id'))) ?> colaboradores</span></p>
                </div>
                <button class="btn-secondary" onclick="window.print()" style="border-radius: 0.75rem;"><i class="fa-solid fa-print"></i> PDF / Imprimir</button>
            </div>

            <div class="stat-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 1.5rem;">
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
                    <div class="glass-panel" style="padding: 2rem; border-radius: 1.5rem;">
                        <h3 style="font-size: 1.1rem; margin-bottom: 2rem; color: var(--text-main); font-weight: 800; min-height: 2.5rem;"><?= htmlspecialchars($q['question_text']) ?></h3>
                        <div style="height: 320px;">
                            <canvas id="chart-<?= $q['id'] ?>"></canvas>
                        </div>
                    </div>
                    <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        new Chart(document.getElementById('chart-<?= $q['id'] ?>'), {
                            type: 'doughnut',
                            data: {
                                labels: <?= json_encode($labels) ?>,
                                datasets: [{
                                    data: <?= json_encode($data) ?>,
                                    backgroundColor: ['#6366F1', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#06B6D4', '#F97316']
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { size: 12, weight: '600' } } },
                                    tooltip: { backgroundColor: '#1e293b', padding: 12, cornerRadius: 10 },
                                    datalabels: {
                                        color: '#fff',
                                        font: { weight: '900', size: 12 },
                                        formatter: (val, ctx) => {
                                            let sum = ctx.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                            return sum > 0 ? (val * 100 / sum).toFixed(0) + "%" : "0%";
                                        }
                                    }
                                }
                            }
                        });
                    });
                    </script>
                <?php endforeach; ?>
            </div>

            <div class="glass-panel" style="margin-top: 2.5rem; border-radius: 1.5rem; padding: 2rem;">
                <h3 style="margin-bottom: 1.5rem; color: var(--text-main); font-weight: 900;">Histórico de Respostas</h3>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Colaborador</th>
                                <th>Pergunta</th>
                                <th>Resposta Escolhida</th>
                                <th>Enviado em</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($responses as $r): ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                            <div style="width: 36px; height: 36px; background: #6366f1; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.8rem; overflow: hidden; border: 2px solid #e2e8f0;">
                                                <?php if($r['avatar_url']): ?>
                                                    <img src="<?= $r['avatar_url'] ?>" style="width:100%; height:100%; object-fit:cover;">
                                                <?php else: ?>
                                                    <?= strtoupper(substr($r['name'], 0, 1)) ?>
                                                <?php endif; ?>
                                            </div>
                                            <span style="font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($r['name']) ?></span>
                                        </div>
                                    </td>
                                    <td style="color: var(--text-soft); max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= htmlspecialchars($r['question_text']) ?></td>
                                    <td><span style="color: #6366f1; font-weight: 800;"><?= htmlspecialchars($r['option_text']) ?></span></td>
                                    <td style="color: var(--text-muted); font-size: 0.85rem;"><?= date('d/m/Y \à\s H:i', strtotime($r['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal Criar Pesquisa (Adaptado para MVC) -->
<div id="createModal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.6); backdrop-filter:blur(8px); z-index:3000; align-items:center; justify-content:center; padding: 1.5rem;">
    <div class="glass-panel" style="width: 100%; max-width: 800px; max-height: 90vh; overflow-y: auto; padding: 2.5rem; border-radius: 2rem; border: 1px solid rgba(255,255,255,0.4);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2 style="font-size: 1.75rem; font-weight: 900; color: var(--text-main); margin: 0;">Nova Pesquisa de Opinião</h2>
            <button type="button" onclick="hideCreateModal()" style="background: var(--bg-main); border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; color: var(--text-soft);"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form action="<?= URL_BASE ?>/pesquisa" method="POST" id="createForm">
            <input type="hidden" name="action" value="create_survey">
            <div style="margin-bottom: 2rem;">
                <label style="display:block; margin-bottom:0.75rem; font-weight:800; color: var(--text-main);">Título da Pesquisa</label>
                <input type="text" name="title" required class="form-input" placeholder="Ex: Avaliação de Café da Manhã" style="padding: 1rem; border-radius: 1rem;">
            </div>
            <div style="margin-bottom: 2.5rem;">
                <label style="display:block; margin-bottom:0.75rem; font-weight:800; color: var(--text-main);">Objetivo / Descrição</label>
                <textarea name="description" class="form-input" style="height: 100px; padding: 1rem; border-radius: 1rem;" placeholder="Explique brevemente o motivo desta pesquisa para os colaboradores..."></textarea>
            </div>

            <div id="questionsContainer">
                <!-- Perguntas serão injetadas aqui -->
            </div>

            <button type="button" class="btn-secondary" onclick="addQuestion()" style="width: 100%; margin-bottom: 3rem; padding: 1.25rem; border-radius: 1.25rem; border: 2px dashed #cbd5e1; background: #f8fafc; color: #64748b; font-weight: 700;">
                <i class="fa-solid fa-plus-circle"></i> Adicionar Nova Pergunta
            </button>

            <div style="display: flex; gap: 1rem; justify-content: flex-end; position: sticky; bottom: 0; background: var(--bg-card); padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                <button type="button" class="btn-secondary" onclick="hideCreateModal()" style="padding: 1rem 2rem; border-radius: 1rem;">Cancelar</button>
                <button type="submit" class="btn-primary" style="padding: 1rem 3rem; border-radius: 1rem; font-weight: 800; box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);">Publicar Pesquisa AGORA</button>
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
        <div class="question-block" style="margin-bottom: 2.5rem; background: #f8fafc; padding: 2rem; border-radius: 1.5rem; border: 1px solid #e2e8f0; position: relative;" id="q-block-${id}">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                <span style="font-weight:900; color:#6366f1; text-transform: uppercase; letter-spacing: 1px; font-size: 0.8rem;">Questão #${id+1}</span>
                <button type="button" onclick="removeQuestion(${id})" style="background:#fee2e2; border:none; width: 32px; height: 32px; border-radius: 8px; color:#ef4444; cursor:pointer;"><i class="fa-solid fa-trash"></i></button>
            </div>
            <label style="display:block; margin-bottom:0.5rem; font-weight:700; font-size: 0.9rem;">Enunciado da Pergunta</label>
            <input type="text" name="questions[${id}][text]" required class="form-input" placeholder="Ex: O que você acha do novo horário?" style="margin-bottom:2rem; padding: 0.75rem; border-radius: 0.75rem;">
            
            <div id="opts-container-${id}">
                <label style="display:block; margin-bottom:0.75rem; font-weight:700; font-size: 0.9rem; color: #64748b;">Opções de Resposta</label>
                <div style="display:flex; gap:0.5rem; margin-bottom:1rem;">
                    <input type="text" name="questions[${id}][options][]" required class="form-input" placeholder="Alternativa A" style="padding: 0.75rem; border-radius: 0.75rem;">
                </div>
                <div style="display:flex; gap:0.5rem; margin-bottom:1.5rem;">
                    <input type="text" name="questions[${id}][options][]" required class="form-input" placeholder="Alternativa B" style="padding: 0.75rem; border-radius: 0.75rem;">
                </div>
            </div>
            <button type="button" class="btn-secondary" style="font-size:0.8rem; padding:0.5rem 1rem; border-radius: 0.75rem; width: 100%; border: 1px solid #e2e8f0;" onclick="addOption(${id})">+ Adicionar Alternativa</button>
        </div>
    `;
    document.getElementById('questionsContainer').insertAdjacentHTML('beforeend', html);
}

function removeQuestion(id) {
    document.getElementById(`q-block-${id}`).remove();
}

function addOption(qId) {
    const html = `
        <div style="display:flex; gap:0.5rem; margin-bottom:1rem; animation: slideIn 0.2s ease;">
            <input type="text" name="questions[${qId}][options][]" required class="form-input" placeholder="Nova Alternativa" style="padding: 0.75rem; border-radius: 0.75rem;">
            <button type="button" onclick="this.parentElement.remove()" style="background:none; border:none; color:#EF4444; cursor:pointer;"><i class="fa-solid fa-xmark"></i></button>
        </div>
    `;
    document.getElementById(`opts-container-${qId}`).insertAdjacentHTML('beforeend', html);
}
</script>

<style>
.survey-option:hover {
    border-color: #6366f1 !important;
    background: #eef2ff !important;
    transform: translateX(5px);
}
.modal-overlay {
    animation: fadeIn 0.3s ease;
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

@media print {
    .sidebar, .top-bar, .btn-secondary, .btn-primary, .page-header { display: none !important; }
    .glass-panel { border: none !important; box-shadow: none !important; }
    body { background: white !important; }
}
</style>
