<!-- ABA: VAGAS EM ABERTO -->
<div id="tab-vagas" class="rh-tab-content">
    <div style="display: flex; gap: 1rem; align-items: stretch;">
        <!-- Formulário de Criação -->
        <div class="glass-panel" style="flex: 1; min-width: 300px;">
            <h3 style="font-size: 1.25rem; font-weight: 900; margin-bottom: 1.5rem;"><i class="fa-solid fa-plus-circle" style="color: var(--brand-primary);"></i> Nova Vaga</h3>
            <form method="POST">
                <input type="hidden" name="action" value="save_job">
                <div style="margin-bottom: 1rem;">
                    <label style="display:block; font-weight:600; margin-bottom:0.5rem; font-size:0.85rem;">Cargo/Título *</label>
                    <input type="text" name="title" required style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; background: var(--bg-color); color: var(--text-main);">
                </div>
                <div style="display:flex; gap:1rem; margin-bottom:1rem;">
                    <div style="flex:1;">
                        <label style="display:block; font-weight:600; margin-bottom:0.5rem; font-size:0.85rem;">Setor *</label>
                        <input type="text" name="sector" required style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; background: var(--bg-color); color: var(--text-main);">
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-weight:600; margin-bottom:0.5rem; font-size:0.85rem;">Tipo de Contrato</label>
                        <select name="contract_type" style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; background: var(--bg-color); color: var(--text-main);">
                            <option value="CLT">CLT</option>
                            <option value="PJ">PJ</option>
                            <option value="Estágio">Estágio</option>
                            <option value="Temporário">Temporário</option>
                        </select>
                    </div>
                </div>
                <div style="display:flex; gap:1rem; margin-bottom:1rem;">
                    <div style="flex:1;">
                        <label style="display:block; font-weight:600; margin-bottom:0.5rem; font-size:0.85rem;">Salário (R$)</label>
                        <input type="number" step="0.01" name="salary" style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; background: var(--bg-color); color: var(--text-main);">
                    </div>
                    <div style="flex:1; display:flex; align-items:center;">
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; font-weight:600; font-size:0.85rem; margin-top:1.5rem;">
                            <input type="checkbox" name="show_salary" value="1"> Mostrar Salário no Link Público
                        </label>
                    </div>
                </div>
                <div style="display:flex; gap:1rem; margin-bottom:1rem;">
                    <div style="flex:1;">
                        <label style="display:block; font-weight:600; margin-bottom:0.5rem; font-size:0.85rem;">Dias Trabalhados</label>
                        <input type="text" name="work_days" placeholder="Ex: Seg a Sex" style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; background: var(--bg-color); color: var(--text-main);">
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-weight:600; margin-bottom:0.5rem; font-size:0.85rem;">Horário</label>
                        <input type="text" name="work_hours" placeholder="Ex: 08:00 às 17:00" style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; background: var(--bg-color); color: var(--text-main);">
                    </div>
                </div>
                <div style="display:flex; gap:1rem; margin-bottom:1rem;">
                    <div style="flex:1;">
                        <label style="display:block; font-weight:600; margin-bottom:0.5rem; font-size:0.85rem;">Carga Horária Semanal</label>
                        <input type="text" name="workload" placeholder="Ex: 40h" style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; background: var(--bg-color); color: var(--text-main);">
                    </div>
                    <div style="flex:1;">
                        <label style="display:block; font-weight:600; margin-bottom:0.5rem; font-size:0.85rem;">Data de Início Prevista</label>
                        <input type="date" name="start_date" style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; background: var(--bg-color); color: var(--text-main);">
                    </div>
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display:block; font-weight:600; margin-bottom:0.5rem; font-size:0.85rem;">Ações a serem realizadas</label>
                    <textarea name="responsibilities" rows="3" style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; background: var(--bg-color); color: var(--text-main);"></textarea>
                </div>
                <div style="margin-bottom: 1.5rem;">
                    <label style="display:block; font-weight:600; margin-bottom:0.5rem; font-size:0.85rem;">Benefícios Oferecidos</label>
                    <textarea name="benefits" rows="3" style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid #cbd5e1; background: var(--bg-color); color: var(--text-main);"></textarea>
                </div>
                <button type="submit" class="btn-primary" style="width:100%; padding:0.8rem; font-size:1rem;"><i class="fa-solid fa-save"></i> Publicar Vaga</button>
            </form>
        </div>

        <!-- Lista de Vagas -->
        <div class="glass-panel" style="flex: 2; overflow: hidden; display: flex; flex-direction: column;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.25rem; font-weight: 900;"><i class="fa-solid fa-list" style="color: var(--brand-primary);"></i> Vagas Publicadas</h3>
                <a href="https://<?= $_SERVER['HTTP_HOST'] ?>/index.php?page=vagas_public&c=<?= $compId ?>" target="_blank" class="btn-secondary" style="font-size:0.85rem; padding:0.5rem 1rem;">
                    <i class="fa-solid fa-external-link-alt"></i> Ver Página Pública
                </a>
            </div>
            
            <div style="overflow-x: auto; flex:1;">
                <table class="data-table" style="width: 100%; min-width: 600px;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cargo</th>
                            <th>Setor</th>
                            <th>Salário</th>
                            <th>Status</th>
                            <th style="text-align:right;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($ats_jobs as $job): ?>
                        <tr>
                            <td>#<?= $job['id'] ?></td>
                            <td style="font-weight:700;"><?= htmlspecialchars($job['title']) ?></td>
                            <td><?= htmlspecialchars($job['sector']) ?></td>
                            <td><?= $job['salary'] > 0 ? 'R$ ' . number_format($job['salary'],2,',','.') : '-' ?></td>
                            <td>
                                <?php if($job['status'] == 'Aberta'): ?>
                                    <span style="background:#d1fae5; color:#065f46; padding:0.2rem 0.5rem; border-radius:12px; font-size:0.8rem; font-weight:700;">Aberta</span>
                                <?php else: ?>
                                    <span style="background:#fee2e2; color:#991b1b; padding:0.2rem 0.5rem; border-radius:12px; font-size:0.8rem; font-weight:700;">Fechada</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right; display:flex; gap:0.5rem; justify-content:flex-end;">
                                <?php if($job['status'] == 'Aberta'): ?>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="action" value="close_job">
                                    <input type="hidden" name="job_id" value="<?= $job['id'] ?>">
                                    <button type="submit" class="btn-secondary" style="padding:0.4rem 0.8rem; font-size:0.8rem; background:#fee2e2; color:#991b1b; border:none;" onclick="return confirm('Fechar esta vaga? Não receberá novos candidatos.')">
                                        <i class="fa-solid fa-ban"></i> Fechar
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(count($ats_jobs) === 0): ?>
                        <tr><td colspan="6" style="text-align:center; padding:2rem; color:var(--text-soft);">Nenhuma vaga registrada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top:1.5rem; padding:1rem; background:rgba(59,130,246,0.1); border-left:4px solid var(--primary); border-radius:8px;">
                <p style="font-size:0.9rem; font-weight:600; color:var(--text-main); margin-bottom:0.5rem;">Link de Divulgação da sua Empresa:</p>
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <input type="text" readonly value="https://<?= $_SERVER['HTTP_HOST'] ?>/index.php?page=vagas_public&c=<?= $compId ?>" style="flex:1; padding:0.5rem; border-radius:4px; border:1px solid #cbd5e1; background:white; font-family:monospace; font-size:0.85rem;" id="link_vagas_<?= $compId ?>">
                    <button type="button" class="btn-primary" style="padding:0.5rem 1rem;" onclick="navigator.clipboard.writeText(document.getElementById('link_vagas_<?= $compId ?>').value); alert('Link copiado!')">Copiar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ABA: CANDIDATOS -->
<div id="tab-candidatos" class="rh-tab-content">
    <div class="glass-panel" style="margin-bottom: 2rem;">
        <h3 style="font-size: 1.25rem; font-weight: 900; margin-bottom: 1.5rem;">
            <i class="fa-solid fa-address-card" style="color: var(--brand-primary);"></i> Gestão de Candidatos
        </h3>
        
        <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; min-width: 900px;">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Candidato</th>
                        <th>Vaga</th>
                        <th>Contato</th>
                        <th>Currículo</th>
                        <th>Status</th>
                        <th style="text-align:right;">Ações / Contratar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($ats_candidates as $cand): ?>
                    <tr style="<?= $cand['status'] == 'Contratado' ? 'opacity:0.6;' : '' ?>">
                        <td style="font-size:0.85rem;"><?= date('d/m/Y H:i', strtotime($cand['created_at'])) ?></td>
                        <td style="font-weight:700;"><?= htmlspecialchars($cand['name']) ?></td>
                        <td><?= htmlspecialchars($cand['job_title']) ?></td>
                        <td style="font-size:0.85rem;">
                            <i class="fa-solid fa-envelope"></i> <?= htmlspecialchars($cand['email']) ?><br>
                            <i class="fa-solid fa-phone"></i> <?= htmlspecialchars($cand['phone']) ?>
                        </td>
                        <td>
                            <?php if(!empty($cand['resume_url'])): ?>
                                <a href="<?= htmlspecialchars($cand['resume_url']) ?>" target="_blank" class="btn-secondary" style="padding:0.3rem 0.6rem; font-size:0.8rem; display:inline-flex; align-items:center; gap:0.3rem;">
                                    <i class="fa-solid fa-file-pdf" style="color:#ef4444;"></i> Ver CV
                                </a>
                            <?php else: ?>
                                <span style="color:#94a3b8; font-size:0.8rem;">S/ CV</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="action" value="update_candidate_status">
                                <input type="hidden" name="candidate_id" value="<?= $cand['id'] ?>">
                                <select name="status" onchange="this.form.submit()" style="padding:0.3rem; border-radius:4px; font-size:0.8rem; border:1px solid #cbd5e1;" <?= $cand['status'] == 'Contratado' ? 'disabled' : '' ?>>
                                    <option value="Novo" <?= $cand['status'] == 'Novo' ? 'selected' : '' ?>>Novo</option>
                                    <option value="2ª Fase" <?= $cand['status'] == '2ª Fase' ? 'selected' : '' ?>>2ª Fase</option>
                                    <option value="Rejeitado" <?= $cand['status'] == 'Rejeitado' ? 'selected' : '' ?>>Rejeitado</option>
                                    <?php if($cand['status'] == 'Contratado'): ?>
                                    <option value="Contratado" selected>Contratado</option>
                                    <?php endif; ?>
                                </select>
                            </form>
                        </td>
                        <td style="text-align:right;">
                            <?php if($cand['status'] !== 'Contratado'): ?>
                            <form method="POST" style="margin:0;" onsubmit="return confirm('Deseja realmente CONTRATAR <?= htmlspecialchars(addslashes($cand['name'])) ?>?\n\nIsso criará automaticamente um usuário na aba de Funcionários com a senha padrão Cetusg@123.')">
                                <input type="hidden" name="action" value="hire_candidate">
                                <input type="hidden" name="candidate_id" value="<?= $cand['id'] ?>">
                                <button type="submit" class="btn-primary" style="padding:0.4rem 0.8rem; font-size:0.85rem; background:#10b981; border:none;">
                                    <i class="fa-solid fa-handshake"></i> Contratar
                                </button>
                            </form>
                            <?php else: ?>
                                <span style="color:#10b981; font-weight:800; font-size:0.85rem;"><i class="fa-solid fa-check-circle"></i> Efetuado</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(count($ats_candidates) === 0): ?>
                    <tr><td colspan="7" style="text-align:center; padding:2rem; color:var(--text-soft);">Nenhum candidato no momento.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
