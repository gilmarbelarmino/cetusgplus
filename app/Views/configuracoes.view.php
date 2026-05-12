<?php
/**
 * CONFIGURACOES VIEW - COMPLETA
 */
?>

<style>
    .tab-btn { background: none; border: none; font-weight: 700; color: #64748b; cursor: pointer; padding: 0.5rem 1rem; border-radius: 0.5rem; transition: all 0.3s; }
    .tab-btn.active { color: var(--crm-purple); background: rgba(99, 102, 241, 0.1); }
    .tab-content { display: none; }
    .tab-content.active { display: block; animation: fadeIn 0.3s ease-out; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

    .log-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
    .log-table th { text-align: left; padding: 1rem; background: #f8fafc; color: #64748b; font-size: 0.75rem; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
    .log-table td { padding: 1rem; border-bottom: 1px solid #e2e8f0; font-size: 0.875rem; color: #334155; }
</style>

<div class="page-header" style="margin-bottom: 2rem;">
    <div class="page-header-info">
        <div class="page-header-icon">
            <i class="fa-solid fa-sliders"></i>
        </div>
        <div class="page-header-text">
            <h2>Definições do Sistema</h2>
            <p>Customização de regras de negócio e parâmetros globais.</p>
        </div>
    </div>
</div>

<!-- Sistema de Abas -->
<div style="display: flex; gap: 1.5rem; border-bottom: 2px solid #e2e8f0; margin-bottom: 2rem; padding-bottom: 0.5rem;">
    <button onclick="switchTab('geral')" id="tab-geral" class="tab-btn active">Geral</button>
    <button onclick="switchTab('certificados')" id="tab-certificados" class="tab-btn">Certificados</button>
    <button onclick="switchTab('aniversarios')" id="tab-aniversarios" class="tab-btn">Aniversários</button>
    <button onclick="switchTab('cargos')" id="tab-cargos" class="tab-btn">Cargos</button>
    <button onclick="switchTab('logs')" id="tab-logs" class="tab-btn">Logs de Acesso</button>
</div>

<!-- Mensagens de Sucesso/Erro -->
<?php if (isset($_GET['success'])): ?>
<div style="background: linear-gradient(135deg, rgba(16, 185, 129, 0.15) 0%, rgba(16, 185, 129, 0.05) 100%); border: 1px solid rgba(16, 185, 129, 0.3); color: #059669; padding: 1rem; border-radius: 1rem; margin-bottom: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.75rem;">
    <i class="fa-solid fa-circle-check"></i>
    <?php
    $s = $_GET['success'];
    if ($s === 'company') echo 'Configurações da empresa salvas com sucesso!';
    elseif ($s === 'sector') echo 'Setor cadastrado com sucesso!';
    elseif ($s === 'unit') echo 'Unidade cadastrada com sucesso!';
    elseif ($s === 'unit_edit') echo 'Unidade atualizada com sucesso!';
    elseif ($s === 'unit_del') echo 'Unidade excluída com sucesso!';
    elseif ($s === 'position') echo 'Cargo cadastrado com sucesso!';
    elseif ($s === 'position_del') echo 'Cargo excluído com sucesso!';
    elseif ($s === 'tech_pass') echo 'Senha do módulo Tecnologia atualizada com sucesso!';
    elseif ($s === 'birthday') echo 'Mensagens de aniversário salvas com sucesso!';
    ?>
</div>
<?php endif; ?>

<?php if (isset($_GET['tab'])): ?>
<script>document.addEventListener('DOMContentLoaded', () => switchTab('<?= htmlspecialchars($_GET['tab']) ?>'));</script>
<?php endif; ?>

<!-- ABA GERAL -->
<div id="content-geral" class="tab-content active">
    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 2rem; margin-bottom: 2rem;">
        <!-- Identidade da Empresa -->
        <div class="glass-panel" style="grid-column: span 2;">
            <h3 style="font-size: 1.25rem; font-weight: 900; color: #1e293b; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-building" style="color: var(--crm-purple);"></i>
                Identidade da Empresa
            </h3>
            <form method="POST" action="<?= URL_BASE ?>/configuracoes" enctype="multipart/form-data">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="action" value="save_company">
                <input type="hidden" name="current_logo" value="<?= htmlspecialchars($company['logo_url'] ?? '') ?>">
                <input type="hidden" name="current_signature" value="<?= htmlspecialchars($company['certificate_signature_url'] ?? '') ?>">
                <input type="hidden" name="certificate_global_text" value="<?= htmlspecialchars($company['certificate_global_text'] ?? '') ?>">
                <input type="hidden" name="current_announcement_image" value="<?= htmlspecialchars($company['announcement_image_url'] ?? '') ?>">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div class="form-group">
                        <label class="form-label">Nome da Empresa</label>
                        <input type="text" name="company_name" class="form-input" value="<?= htmlspecialchars($company['company_name'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Logo Principal</label>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div id="logo_preview" style="width: 48px; height: 48px; border-radius: 8px; border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #fff;">
                                <?php if (!empty($company['logo_url'])): ?>
                                    <img src="<?= htmlspecialchars($company['logo_url']) ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                <?php else: ?>
                                    <i class="fa-solid fa-image" style="color: #cbd5e1;"></i>
                                <?php endif; ?>
                            </div>
                            <input type="file" name="logo" accept="image/*" class="form-input" onchange="previewImage(this, 'logo_preview')">
                        </div>
                    </div>
                </div>

                <div style="grid-column: span 2; display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; background: #f8fafc; padding: 1.5rem; border-radius: 1rem; border: 1px solid #e2e8f0; margin-top: 1rem;">
                    <div style="text-align: center;">
                        <label class="form-label" style="margin-bottom: 0.5rem; display: block;">Imagem do Comunicado</label>
                        <div id="announcement_preview" style="width: 100%; height: 160px; border: 2px dashed rgba(99,102,241,0.3); border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #fff; margin-bottom: 0.75rem;">
                            <?php if (!empty($company['announcement_image_url'])): ?>
                                <img src="<?= htmlspecialchars($company['announcement_image_url']) ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                            <?php else: ?>
                                <i class="fa-solid fa-bullhorn" style="font-size: 2rem; color: #cbd5e1;"></i>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="announcement_image" accept="image/*" style="font-size: 0.75rem;" onchange="previewImage(this, 'announcement_preview')">
                    </div>

                    <div>
                        <div class="form-group">
                            <label class="form-label">Aviso de Login Global (Comunicado)</label>
                            <textarea name="login_announcement" class="form-input" style="height: 120px; resize: none;" placeholder="Mensagem que aparecerá para todos ao logar..."><?= htmlspecialchars($company['login_announcement'] ?? '') ?></textarea>
                            <p style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">Deixe vazio se não quiser exibir nenhum comunicado.</p>
                        </div>
                    </div>
                </div>

                <div style="grid-column: span 2; margin-top: 1rem; background: #fffbeb; padding: 1.5rem; border-radius: 1rem; border: 1px solid #fef3c7;">
                    <div class="form-group" style="margin-bottom: 0.5rem;">
                        <label class="form-label" style="color: #92400e; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            Caminho de Destino do Backup (OneDrive / Local)
                        </label>
                        <input type="text" name="backup_full_path" class="form-input" value="<?= htmlspecialchars($company['backup_full_path'] ?? 'D:\OneDrive - Arrastão Movimento de Promoção Humana\BACKUP_SISTEMA_CETUSG') ?>" placeholder="Ex: D:\OneDrive - Empresa\Backup">
                    </div>
                </div>

                <div style="grid-column: span 2; margin-top: 1rem;">
                    <button type="submit" class="btn-primary">
                        <i class="fa-solid fa-floppy-disk"></i> Salvar Identidade e Comunicado
                    </button>
                </div>
            </form>
        </div>

        <!-- Segurança: Tecnologia -->
        <div class="glass-panel" style="grid-column: span 2;">
            <h3 style="font-size: 1.25rem; font-weight: 900; color: #1e293b; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-lock" style="color: var(--crm-purple);"></i>
                Segurança: Módulo Tecnologia
            </h3>
            <form method="POST" action="<?= URL_BASE ?>/configuracoes">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="action" value="update_tech_password">
                <div style="display: flex; gap: 1rem; align-items: flex-end; max-width: 400px;">
                    <div class="form-group" style="flex: 1; margin-bottom: 0;">
                        <label class="form-label">Senha Global de Acesso *</label>
                        <input type="text" name="tech_password" class="form-input" value="<?= htmlspecialchars($company['tech_password'] ?? '1968') ?>" required autocomplete="off">
                    </div>
                    <button type="submit" class="btn-primary">Atualizar Senha</button>
                </div>
            </form>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 2rem;">
        <!-- Gerenciar Unidades -->
        <div class="glass-panel">
            <h3 style="font-size: 1.25rem; font-weight: 900; color: #1e293b; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-building" style="color: var(--crm-purple);"></i>
                Unidades / Matriz / Sede
            </h3>
            <form method="POST" action="<?= URL_BASE ?>/configuracoes" style="margin-bottom: 2rem;">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="action" value="add_unit">
                <div style="display: grid; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Nome da Unidade *</label>
                        <input type="text" name="name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">CNPJ</label>
                        <input type="text" name="cnpj" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Endereço</label>
                        <input type="text" name="address" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Responsável</label>
                        <input type="text" name="responsible_name" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contato</label>
                        <input type="text" name="contact" class="form-input">
                    </div>
                    <button type="submit" class="btn-primary"><i class="fa-solid fa-plus"></i> Adicionar Unidade</button>
                </div>
            </form>
            
            <div style="max-height: 400px; overflow-y: auto;">
                <?php foreach ($units as $u): ?>
                <div style="padding: 1rem; background: #f8fafc; border-radius: 1rem; margin-bottom: 0.75rem; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: start;">
                    <div style="flex: 1;">
                        <div style="font-weight: 800; color: var(--crm-purple);"><?= htmlspecialchars($u['name']) ?></div>
                        <div style="font-size: 0.75rem; color: #64748b;">
                            CNPJ: <?= htmlspecialchars($u['cnpj'] ?: 'N/A') ?><br>
                            Resp: <?= htmlspecialchars($u['responsible_name'] ?: 'N/A') ?>
                        </div>
                    </div>
                    <div style="display:flex;gap:6px;">
                        <button onclick="editUnit(<?= htmlspecialchars(json_encode($u)) ?>)" class="btn-icon" style="color:var(--crm-purple);"><i class="fa-solid fa-pen"></i></button>
                        <form method="POST" action="<?= URL_BASE ?>/configuracoes" style="display:inline;" onsubmit="return confirm('Excluir unidade?')">
                            <?= \App\Core\Csrf::field() ?>
                            <input type="hidden" name="action" value="delete_unit">
                            <input type="hidden" name="unit_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn-icon" style="color:#ef4444;"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Gerenciar Setores -->
        <div class="glass-panel">
            <h3 style="font-size: 1.25rem; font-weight: 900; color: #1e293b; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                <i class="fa-solid fa-layer-group" style="color: #f59e0b;"></i>
                Setores / Departamentos
            </h3>
            <form method="POST" action="<?= URL_BASE ?>/configuracoes" style="margin-bottom: 2rem;">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="action" value="add_sector">
                <div style="display: grid; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Nome do Setor *</label>
                        <input type="text" name="sector_name" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Unidade *</label>
                        <select name="unit_id" class="form-select" required>
                            <?php foreach ($units as $u): ?>
                                <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary"><i class="fa-solid fa-plus"></i> Adicionar Setor</button>
                </div>
            </form>
            
            <div style="max-height: 400px; overflow-y: auto;">
                <?php foreach ($sectors as $s): ?>
                <div style="padding: 1rem; background: #f8fafc; border-radius: 1rem; margin-bottom: 0.75rem; border: 1px solid #e2e8f0;">
                    <div style="font-weight: 800; color: #f59e0b;"><?= htmlspecialchars($s['name']) ?></div>
                    <div style="font-size: 0.75rem; color: #64748b;">Unidade: <?= htmlspecialchars($s['unit_name']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Backup e Importação -->
    <div class="glass-panel" style="margin-top: 2rem;">
        <h3 style="font-size: 1.25rem; font-weight: 900; color: #1e293b; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-cloud-arrow-up" style="color: var(--crm-purple);"></i>
            Centro de Backup e Sincronização
        </h3>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 2rem;">
            <!-- Exportação -->
            <div style="padding: 1.5rem; background: #f8fafc; border-radius: 1.25rem; border: 1px solid #e2e8f0;">
                <h4 style="font-weight: 900; margin-bottom: 1rem;">Exportação Completa (Excel)</h4>
                <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 1.5rem;">Gera um arquivo XLSX com todos os módulos do sistema para auditoria externa.</p>
                <a href="<?= URL_BASE ?>/backup.php" class="btn-primary" style="display: block; text-align: center; text-decoration: none; padding: 0.75rem;">Gerar Backup Profissional</a>
            </div>
            
            <!-- Importação -->
            <div style="padding: 1.5rem; background: #f8fafc; border-radius: 1.25rem; border: 1px solid #e2e8f0;">
                <h4 style="font-weight: 900; margin-bottom: 1rem;">Importação Inteligente</h4>
                <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 1.5rem;">Sincronize dados de um arquivo Excel sem apagar as informações atuais.</p>
                <form action="<?= URL_BASE ?>/import.php" method="POST" enctype="multipart/form-data">
                    <input type="file" name="backup_file" class="form-input" style="font-size: 0.75rem; margin-bottom: 0.5rem;" required>
                    <button type="submit" class="btn-primary" style="width: 100%; background: #059669; border-color: #059669;">Iniciar Sincronização</button>
                </form>
            </div>

            <!-- Backup Total (Layout Mestre) -->
            <div style="grid-column: span 2; padding: 2rem; background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: 1.5rem; color: white;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h4 style="font-weight: 900; font-size: 1.25rem; margin-bottom: 0.5rem;"><i class="fa-solid fa-shield-halved" style="color:#38bdf8;"></i> Cópia de Segurança Total (ZIP)</h4>
                        <p style="font-size: 0.85rem; opacity: 0.8; margin-bottom: 0;">Backup completo incluindo Banco de Dados, Arquivos PHP e Uploads.</p>
                    </div>
                    <button onclick="runFullBackup()" class="btn-primary" style="background: #38bdf8; color: #0f172a;">Fazer Backup Agora</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Perfis de Acesso -->
    <div class="glass-panel" style="margin-top: 2rem;">
        <h3 style="font-size: 1.25rem; font-weight: 900; color: #1e293b; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fa-solid fa-shield-halved" style="color: var(--crm-purple);"></i>
            Perfis de Acesso e Permissões
        </h3>
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem;">
            <div style="padding: 1.5rem; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border-radius: 1rem; color: white;">
                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">👑</div>
                <div style="font-weight: 900;">Administrador</div>
            </div>
            <div style="padding: 1.5rem; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); border-radius: 1rem; color: white;">
                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">👔</div>
                <div style="font-weight: 900;">Setor</div>
            </div>
            <div style="padding: 1.5rem; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border-radius: 1rem; color: white;">
                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">🛠️</div>
                <div style="font-weight: 900;">Suporte</div>
            </div>
            <div style="padding: 1.5rem; background: linear-gradient(135deg, #64748b 0%, #475569 100%); border-radius: 1rem; color: white;">
                <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">👤</div>
                <div style="font-weight: 900;">Colaborador</div>
            </div>
        </div>
    </div>
</div>

<!-- ABA CERTIFICADOS -->
<div id="content-certificados" class="tab-content">
    <div class="glass-panel">
        <h3 style="font-size: 1.25rem; font-weight: 900; color: #1e293b; margin-bottom: 1.5rem;">Configuração de Certificados</h3>
        <form method="POST" action="<?= URL_BASE ?>/configuracoes" enctype="multipart/form-data">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="action" value="save_company">
            <input type="hidden" name="company_name" value="<?= htmlspecialchars($company['company_name'] ?? '') ?>">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div class="form-group">
                    <label class="form-label">Imagem da Assinatura (Campo Inferior)</label>
                    <div id="signature_preview" style="width: 100%; height: 120px; border: 2px dashed #e2e8f0; border-radius: 12px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #fff; margin-bottom: 1rem;">
                        <?php if (!empty($company['certificate_signature_url'])): ?>
                            <img src="<?= htmlspecialchars($company['certificate_signature_url']) ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                        <?php else: ?>
                            <i class="fa-solid fa-pen-nib" style="font-size: 2rem; color: #cbd5e1;"></i>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="signature" accept="image/*" class="form-input" onchange="previewImage(this, 'signature_preview')">
                </div>
                <div class="form-group">
                    <label class="form-label">Texto Global do Certificado</label>
                    <textarea name="certificate_global_text" class="form-input" style="height: 156px; resize: none;"><?= htmlspecialchars($company['certificate_global_text'] ?? '') ?></textarea>
                </div>
            </div>
            <button type="submit" class="btn-primary" style="margin-top: 1rem;">Salvar Configurações</button>
        </form>
    </div>
</div>

<!-- ABA ANIVERSÁRIOS -->
<div id="content-aniversarios" class="tab-content">
    <div class="glass-panel">
        <h3 style="font-size: 1.25rem; font-weight: 900; color: #1e293b; margin-bottom: 1.5rem;">Mensagens de Aniversário</h3>
        <form method="POST" action="<?= URL_BASE ?>/configuracoes">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="action" value="save_birthday_messages">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div class="form-group">
                    <label class="form-label">Mensagem para Todos (Modal)</label>
                    <textarea name="birthday_message_all" class="form-input" style="height: 150px;"><?= htmlspecialchars($company['birthday_message_all'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Mensagem Privada (Chat)</label>
                    <textarea name="birthday_message_self" class="form-input" style="height: 150px;"><?= htmlspecialchars($company['birthday_message_self'] ?? '') ?></textarea>
                </div>
            </div>
            <button type="submit" class="btn-primary" style="margin-top: 1rem;">Salvar Mensagens</button>
        </form>
    </div>
</div>

<!-- ABA CARGOS -->
<div id="content-cargos" class="tab-content">
    <div class="glass-panel">
        <h3 style="font-size: 1.25rem; font-weight: 900; color: #1e293b; margin-bottom: 1.5rem;">Gerenciar Cargos</h3>
        <form method="POST" action="<?= URL_BASE ?>/configuracoes" style="display: flex; gap: 1rem; align-items: flex-end; margin-bottom: 2rem;">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="action" value="add_position">
            <div class="form-group" style="flex: 1; margin-bottom: 0;">
                <label class="form-label">Nome do Cargo</label>
                <input type="text" name="position_name" class="form-input" required>
            </div>
            <button type="submit" class="btn-primary">Adicionar</button>
        </form>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
            <?php foreach ($positions as $pos): ?>
                <div style="padding: 1rem; background: #f8fafc; border-radius: 1rem; border: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 700;"><?= htmlspecialchars($pos['name']) ?></span>
                    <form method="POST" action="<?= URL_BASE ?>/configuracoes" onsubmit="return confirm('Excluir?')">
                        <?= \App\Core\Csrf::field() ?>
                        <input type="hidden" name="action" value="delete_position">
                        <input type="hidden" name="position_id" value="<?= $pos['id'] ?>">
                        <button type="submit" class="btn-icon" style="color:#ef4444;"><i class="fa-solid fa-trash"></i></button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ABA LOGS -->
<div id="content-logs" class="tab-content">
    <div class="glass-panel">
        <h3 style="font-size: 1.25rem; font-weight: 900; color: #1e293b; margin-bottom: 1.5rem;">Auditoria de Acesso</h3>
        <div style="overflow-x: auto;">
            <table class="log-table">
                <thead>
                    <tr><th>Usuário</th><th>Data/Hora</th><th>IP</th><th>MAC</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td style="font-weight: 800; color: var(--crm-purple);"><?= htmlspecialchars($log['user_name']) ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($log['login_at'])) ?></td>
                            <td style="font-family: monospace;"><?= htmlspecialchars($log['ip_address'] ?: '--') ?></td>
                            <td style="font-size: 0.7rem; color: #94a3b8;"><?= htmlspecialchars($log['mac_address'] ?: '--') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Editar Unidade -->
<div id="editUnitModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
    <div class="glass-panel" style="width: 90%; max-width: 500px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="font-weight: 900;">Editar Unidade</h3>
            <button onclick="closeEditUnit()" class="btn-icon"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="<?= URL_BASE ?>/configuracoes">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="action" value="edit_unit">
            <input type="hidden" name="unit_id" id="edit_unit_id">
            <div style="display: grid; gap: 1rem;">
                <div class="form-group"><label class="form-label">Nome</label><input type="text" name="name" id="edit_name" class="form-input" required></div>
                <div class="form-group"><label class="form-label">CNPJ</label><input type="text" name="cnpj" id="edit_cnpj" class="form-input"></div>
                <div class="form-group"><label class="form-label">Endereço</label><input type="text" name="address" id="edit_address" class="form-input"></div>
                <div class="form-group"><label class="form-label">Responsável</label><input type="text" name="responsible_name" id="edit_responsible_name" class="form-input"></div>
                <div class="form-group"><label class="form-label">Contato</label><input type="text" name="contact" id="edit_contact" class="form-input"></div>
                <button type="submit" class="btn-primary">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('content-' + tab).classList.add('active');
    document.getElementById('tab-' + tab).classList.add('active');
}

function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.innerHTML = `<img src="${e.target.result}" style="max-width:100%;max-height:100%;object-fit:contain;">`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function editUnit(unit) {
    document.getElementById('edit_unit_id').value = unit.id;
    document.getElementById('edit_name').value = unit.name;
    document.getElementById('edit_cnpj').value = unit.cnpj || '';
    document.getElementById('edit_address').value = unit.address || '';
    document.getElementById('edit_responsible_name').value = unit.responsible_name || '';
    document.getElementById('edit_contact').value = unit.contact || '';
    document.getElementById('editUnitModal').style.display = 'flex';
}

function closeEditUnit() { document.getElementById('editUnitModal').style.display = 'none'; }

function runFullBackup() {
    if (!confirm('Iniciar backup total ZIP (Banco + Arquivos)?')) return;
    fetch('<?= URL_BASE ?>/process_full_backup.php')
        .then(r => r.json())
        .then(data => alert(data.message))
        .catch(err => alert('Erro no processo de backup.'));
}
</script>
