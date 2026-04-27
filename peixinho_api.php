<?php
require_once 'config.php';

/**
 * Peixinho AI - Assistente Inteligente Cetusg
 * Desenvolvido para ser gentil, prestativo e ter acesso total aos dados do sistema.
 */
class PeixinhoBrain {
    private $pdo;
    private $allowed_menus;
    private $greetings = [
        "Olá! Tudo bem? Eu sou o **Peixinho**, seu assistente. 🐠 Como posso facilitar seu trabalho agora?",
        "Oiê! Que bom te ver por aqui. 🌊 Em que posso te ajudar hoje?",
        "Olá, colega! 🐠 Estou mergulhando nos dados para encontrar o que você precisa. O que buscamos?",
        "Oi! Sou o Peixinho. 🐠 Pronto para mais um dia de produtividade? O que você quer saber?"
    ];
    private $closings = [
        "Estou à disposição se precisar de mais alguma coisa! 🐠",
        "Espero ter ajudado! Se tiver outra dúvida, é só chamar. 🌊",
        "Qualquer outra informação que precisar, estou por aqui! 🐠",
        "Sempre um prazer ajudar! O que mais vamos descobrir? 🐠"
    ];

    public function __construct($pdo, $allowed_menus = []) {
        $this->pdo = $pdo;
        $this->allowed_menus = $allowed_menus;
    }

    private function can($menu) {
        return in_array($menu, $this->allowed_menus);
    }

    public function process($message) {
        $msg = mb_strtolower($message, 'UTF-8');
        $responses = [];

        // --- SISTEMA DE AJUDA / COMO USAR ---
        if ($this->match($msg, ['como usar', 'ajuda', 'socorro', 'o que você faz', 'o que voce faz', 'funcionalidades'])) {
            return $this->getHelpOverview();
        }

        // --- RESUMO GERAL (RELATÓRIOS) ---
        if ($this->match($msg, ['resumo', 'status', 'geral', 'dashboard', 'relatório', 'relatorio', 'como estamos'])) {
            if ($this->can('relatorios')) {
                $responses[] = $this->getSystemReports();
            } else {
                $responses[] = "No momento, não tenho autorização para acessar os relatórios gerais para você. 🐠";
            }
        }

        // --- VOLUNTARIADO (ACESSO TOTAL) ---
        if ($this->match($msg, ['voluntar', 'voluntário', 'voluntariado', 'horas', 'quem são os voluntários', 'quem sao os voluntarios'])) {
            if ($this->can('voluntariado')) {
                $responses[] = $this->getDetailedVoluntariado($msg);
            } else {
                $responses[] = "Sinto muito, mas não tenho permissão para acessar os dados do Voluntariado. 🐠";
            }
        }

        // --- LOCAÇÃO DE SALAS (ACESSO TOTAL) ---
        if ($this->match($msg, ['locação', 'locacao', 'sala', 'reserv', 'agendamento', 'disponível', 'livre', 'quais salas'])) {
            if ($this->can('locacao_salas')) {
                $responses[] = $this->getDetailedLocacao($msg);
            } else {
                $responses[] = "Ops! Não tenho permissão para verificar as salas agora. 🐠";
            }
        }

        // --- SEMANADA / MURAL (ACESSO TOTAL) ---
        if ($this->match($msg, ['semanada', 'mural', 'comentário', 'mensagem', 'reunião', 'eventos', 'novidade', 'comunicação', 'comunicado'])) {
            if ($this->can('semanada')) {
                $responses[] = $this->getSemanadaFeed();
            } else {
                $responses[] = "Não tenho acesso ao mural da Semanada para te passar as novidades. 🐠";
            }
        }

        // --- USUÁRIOS E RH ---
        if ($this->match($msg, ['usuário', 'usuario', 'colaborador', 'quem é', 'quem e', 'aniversário', 'aniversariantes'])) {
            if ($this->can('rh') || $this->can('usuarios')) {
                if ($this->match($msg, ['aniversário', 'aniversariantes'])) {
                    $responses[] = $this->getBirthdays();
                } else {
                    $responses[] = $this->getUserInfo($msg);
                }
            } else {
                $responses[] = "Não tenho autorização para buscar informações de colaboradores. 🐠";
            }
        }

        // --- PATRIMÔNIO E CHAMADOS ---
        if ($this->match($msg, ['patrimônio', 'patrimonio', 'estoque', 'equipamento', 'asset'])) {
            if ($this->can('patrimonio')) {
                $responses[] = $this->getPatrimonioStats();
            } else {
                $responses[] = "Os dados de patrimônio estão restritos para mim no seu perfil. 🐠";
            }
        }
        if ($this->match($msg, ['chamado', 'ticket', 'suporte', 'estragado', 'problema'])) {
            if ($this->can('chamados')) {
                $responses[] = $this->getTicketsSummary();
            } else {
                $responses[] = "Não tenho permissão para ver o status dos chamados técnicos. 🐠";
            }
        }

        // --- RESPOSTA FINAL ---
        if (empty($responses)) {
            // Cumprimentos genéricos se não houver contexto
            if ($this->match($msg, ['olá', 'ola', 'oi', 'bom dia', 'boa tarde', 'boa noite', 'ei'])) {
                return $this->greetings[array_rand($this->greetings)];
            }
            return "Puxa, ainda não sei responder sobre isso... 🐠 Mas estou aprendendo rápido! Você pode me perguntar sobre **Voluntários, Salas, Relatórios, Semanada ou Patrimônio** (conforme seus acessos). O que acha?";
        }

        $intro = (count($responses) > 1) ? "Claro! Reuni algumas informações para você: 🐠\n\n" : "";
        return $intro . implode("\n\n", $responses) . "\n\n" . $this->closings[array_rand($this->closings)];
    }

    private function match($text, $keywords) {
        foreach ($keywords as $k) {
            if (strpos($text, $k) !== false) return true;
        }
        return false;
    }

    private function queryValue($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn() ?: 0;
        } catch(Exception $e) { return 0; }
    }

    private function getHelpOverview() {
        return "Eu sou o **Peixinho**, sua IA de suporte aqui no Cetusg! 🐠\n\nEu posso te ajudar a:\n" .
               "- 📊 **Analisar Relatórios**: Pergunte sobre o valor do patrimônio ou resumo de chamados.\n" .
               "- ❤️ **Gerir Voluntários**: Saiba quem são, quantas horas doaram e onde atuam.\n" .
               "- 📅 **Reservar Salas**: Verifique quais salas estão livres ou quem reservou hoje.\n" .
               "- 📢 **Acompanhar a Semanada**: Veja as últimas novidades e o que o time está comentando.\n" .
               "- 👥 **Achar Colegas**: Busque por nome para saber o cargo ou setor de alguém.\n\n" .
               "Sou programado para ser gentil e trazer a informação mais fresquinha possível para você! 🐠";
    }

    private function getSystemReports() {
        $totalAssets = $this->queryValue("SELECT COUNT(*) FROM assets");
        $totalValue = $this->queryValue("SELECT SUM(estimated_value) FROM assets");
        $openTickets = $this->queryValue("SELECT COUNT(*) FROM tickets WHERE status != 'Concluído'");
        $activeUsers = $this->queryValue("SELECT COUNT(*) FROM users WHERE status = 'Ativo'");
        
        $formattedValue = 'R$ ' . number_format($totalValue, 2, ',', '.');
        
        return "📊 **Relatório Instantâneo do Sistema:**\n" .
               "- Nosso patrimônio conta com **$totalAssets itens**, avaliados em **$formattedValue**.\n" .
               "- Temos **$activeUsers colaboradores** ativos fazendo a mágica acontecer.\n" .
               "- No momento, a equipe de suporte está cuidando de **$openTickets chamados** abertos. 🐠";
    }

    private function getDetailedVoluntariado($msg) {
        $count = $this->queryValue("SELECT COUNT(*) FROM volunteers WHERE status = 'Ativo'");
        $totalHours = $this->queryValue("SELECT SUM(total_hours) FROM volunteers");
        
        // Tentar ver se pediu por setor específico
        $stmt = $this->pdo->query("SELECT volunteering_sector, COUNT(*) as cnt FROM volunteers GROUP BY volunteering_sector ORDER BY cnt DESC LIMIT 3");
        $sectors = $stmt->fetchAll();
        $sectorText = "";
        foreach ($sectors as $s) { $sectorText .= "\n  • " . ($s['volunteering_sector'] ?: 'Geral') . " (" . $s['cnt'] . " pessoas)"; }

        $res = "❤️ **Sobre nossos Voluntários:**\n" .
               "Atualmente possuímos **$count voluntários ativos**. Juntos, eles já dedicaram incríveis **" . number_format($totalHours, 1, ',', '.') . " horas**! 🐠\n" .
               "As áreas com mais atuação são: $sectorText";
               
        if ($this->match($msg, ['quem', 'lista'])) {
            $stmt = $this->pdo->query("SELECT name FROM volunteers WHERE status = 'Ativo' LIMIT 5");
            $names = array_column($stmt->fetchAll(), 'name');
            $res .= "\n\nAlguns dos nossos heróis ativos: " . implode(', ', $names) . "...";
        }
        
        return $res;
    }

    private function getDetailedLocacao($msg) {
        $hoje = date('Y-m-d');
        $totalSalas = $this->queryValue("SELECT COUNT(*) FROM rooms");
        
        $stmt = $this->pdo->prepare("
            SELECT r.name, b.start_time, b.end_time, u.name as user 
            FROM room_bookings b 
            JOIN rooms r ON b.room_id = r.id 
            JOIN users u ON b.user_id = u.id 
            WHERE b.booking_date = ? AND b.status = 'Aprovado'
            ORDER BY b.start_time ASC
        ");
        $stmt->execute([$hoje]);
        $bookings = $stmt->fetchAll();

        if (empty($bookings)) {
            return "📅 **Locação de Salas:**\nDas **$totalSalas salas** cadastradas, todas parecem estar livres hoje! Que tal agendar uma reunião? 🐠";
        }

        $res = "📅 **Reservas para Hoje ($hoje):**\n";
        foreach ($bookings as $b) {
            $res .= "• **" . $b['name'] . "**: " . substr($b['start_time'], 0, 5) . " às " . substr($b['end_time'], 0, 5) . " (Reservado por " . explode(' ', $b['user'])[0] . ")\n";
        }
        
        if ($this->match($msg, ['disponível', 'livre', 'quais'])) {
            $stmt = $this->pdo->query("SELECT name FROM rooms WHERE id NOT IN (SELECT room_id FROM room_bookings WHERE booking_date = CURDATE())");
            $free = array_column($stmt->fetchAll(), 'name');
            if (!empty($free)) $res .= "\nSalas totalmente livres hoje: " . implode(', ', $free) . ". 🐠";
        }

        return $res;
    }

    private function getSemanadaFeed() {
        try {
            $stmt = $this->pdo->query("SELECT comment_text, user_id, created_at FROM semanada_comments ORDER BY created_at DESC LIMIT 3");
            $comments = $stmt->fetchAll();
            
            $annCount = $this->queryValue("SELECT COUNT(*) FROM announcements WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            
            $res = "📢 **Novidades da Semanada (Mural):**\n";
            $res .= "Nos últimos 7 dias, tivemos **$annCount novos comunicados** importantes.\n\n";
            
            if (!empty($comments)) {
                $res .= "O que andam comentando:\n";
                foreach ($comments as $c) {
                    $userName = $this->queryValue("SELECT name FROM users WHERE id = ?", [$c['user_id']]);
                    $res .= "• \"" . (strlen($c['comment_text']) > 50 ? substr($c['comment_text'], 0, 47) . '...' : $c['comment_text']) . "\" - *" . explode(' ', $userName)[0] . "*\n";
                }
            }
            return $res . "🐠";
        } catch(Exception $e) { return "O mural da Semanada está um pouco calmo agora! 🐠"; }
    }

    private function getUserInfo($msg) {
        $search = str_replace(['quem é', 'quem e', 'sobre o usuário', 'sobre o usuario', 'usuário', 'usuario', 'colaborador'], '', $msg);
        $search = trim($search);
        if (strlen($search) < 2) return "Para falar sobre um colega, por favor me diga o nome dele! 🐠";
        
        $stmt = $this->pdo->prepare("SELECT name, sector, role, last_activity, phone, email FROM users WHERE name LIKE ? OR login_name LIKE ? LIMIT 1");
        $stmt->execute(['%' . $search . '%', '%' . $search . '%']);
        $user = $stmt->fetch();
        
        if ($user) {
            $status = (strtotime($user['last_activity']) > strtotime('-10 minutes')) ? 'On-line agora! 🟢' : 'Off-line no momento 🔴';
            return "👤 **Perfil Encontrado:**\n" .
                   "**Nome:** " . $user['name'] . "\n" .
                   "**Setor:** " . ($user['sector'] ?: 'Não definido') . "\n" .
                   "**Cargo:** " . ($user['role'] ?: 'Colaborador') . "\n" .
                   "**Contato:** " . ($user['phone'] ?: $user['email']) . "\n" .
                   "**Status:** $status 🐠";
        }
        return "Humm, não encontrei nenhum colega com o nome '$search'. Tente usar apenas o primeiro nome! 🐠";
    }

    private function getBirthdays() {
        try {
            $stmt = $this->pdo->query("
                SELECT u.name, DATE_FORMAT(rh.birth_date, '%d/%m') as dia 
                FROM users u 
                JOIN rh_employee_details rh ON u.id = rh.user_id 
                WHERE MONTH(rh.birth_date) = MONTH(CURDATE())
                ORDER BY DAY(rh.birth_date) ASC
            ");
            $list = $stmt->fetchAll();
            if (empty($list)) return "Ninguém faz aniversário este mês? Estranho... Mas assim temos mais bolo para o próximo! 🎂 🐠";
            
            $res = "🎂 **Aniversariantes do Mês:**\n";
            foreach ($list as $b) {
                $res .= "- " . $b['name'] . " (" . $b['dia'] . ")\n";
            }
            return $res . "Parabéns a todos! 🎉 🐠";
        } catch(Exception $e) { return "Animação total para o mês que vem! 🐠"; }
    }

    private function getPatrimonioStats() {
        $total = $this->queryValue("SELECT COUNT(*) FROM assets");
        $manutencao = $this->queryValue("SELECT COUNT(*) FROM assets WHERE status = 'Manutenção'");
        return "📦 **Patrimônio:** Temos **$total bens** registrados. Desses, **$manutencao estão em manutenção** técnica. Posso te ajudar a localizar algum item pelo ID de patrimônio? 🐠";
    }

    private function getTicketsSummary() {
        $abertos = $this->queryValue("SELECT COUNT(*) FROM tickets WHERE status = 'Aberto'");
        $urgentes = $this->queryValue("SELECT COUNT(*) FROM tickets WHERE priority IN ('Alta', 'Crítica') AND status != 'Concluído'");
        return "🛠️ **Suporte:** No momento existem **$abertos chamados aguardando início**. Atenção: **$urgentes são considerados urgentes**! Precisamos de foco neles. 🐠";
    }
}
?>
