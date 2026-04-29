<?php
require_once 'config.php';

/**
 * Peixinho AI - Assistente de Inteligência Total Cetusg Plus
 * Agora com análise histórica, orçamentos, RH e eventos.
 */
class PeixinhoBrain {
    private $pdo;
    private $allowed_menus;
    
    public function __construct($pdo, $allowed_menus = []) {
        $this->pdo = $pdo;
        $this->allowed_menus = $allowed_menus;
    }

    public function process($message) {
        $msg = mb_strtolower($message, 'UTF-8');
        $responses = [];

        // 1. SAUDAÇÕES
        if ($this->match($msg, ['olá', 'ola', 'oi', 'bom dia', 'boa tarde', 'boa noite', 'ei'])) {
            $responses[] = $this->getGreeting();
        }

        // 2. CHAMADOS E HISTÓRICO (PRODUTIVIDADE)
        if ($this->match($msg, ['chamado', 'ticket', 'suporte', 'solucionado', 'concluído', 'concluido', 'resolvido', 'hoje', 'ontem'])) {
            $responses[] = $this->getTicketsIntelligence($msg);
        }

        // 3. ORÇAMENTOS E COMPRAS
        if ($this->match($msg, ['orçamento', 'orcamento', 'compra', 'cotação', 'cotacao', 'pedido'])) {
            $responses[] = $this->getBudgetIntelligence($msg);
        }

        // 4. RH, FÉRIAS E ANIVERSÁRIOS
        if ($this->match($msg, ['férias', 'ferias', 'quem é', 'colaborador', 'aniversário', 'rh', 'setor'])) {
            $responses[] = $this->getRHIntelligence($msg);
        }

        // 5. PATRIMÔNIO E EMPRÉSTIMOS
        if ($this->match($msg, ['patrimônio', 'patrimonio', 'estoque', 'valor', 'equipamento', 'emprest', 'devol', 'pendente'])) {
            $responses[] = $this->getPatrimonioIntelligence($msg);
        }

        // 6. EVENTOS E SEMANADA
        if ($this->match($msg, ['evento', 'reunião', 'reuniao', 'agenda', 'semanada', 'mural'])) {
            $responses[] = $this->getSocialIntelligence($msg);
        }

        if (empty($responses)) {
            return "Puxa, que pergunta interessante! 🐠 Estou mergulhando fundo em todos os menus do sistema (Chamados, Orçamentos, RH, Patrimônio e Social) para encontrar o que você precisa. Poderia me dar um pouco mais de detalhe sobre o que busca? 🌊";
        }

        return implode("\n\n", array_unique($responses)) . "\n\nEstou aqui para o que você precisar! 🐠✨";
    }

    private function getGreeting() {
        return "Olá! Que alegria poder te ajudar hoje. 🐠 Tenho acesso a todo o histórico de chamados, orçamentos, patrimônio e RH do sistema. O que você gostaria que eu analisasse para você agora? 🌊";
    }

    private function getTicketsIntelligence($msg) {
        $hoje = date('Y-m-d');
        
        // Estatísticas de HOJE
        if ($this->match($msg, ['hoje', 'solucionado', 'concluído', 'concluido', 'resolvido'])) {
            $solucionadosHoje = $this->queryValue("SELECT COUNT(*) FROM tickets WHERE status = 'Concluído' AND (DATE(resolved_at) = ? OR DATE(closed_at) = ?)", [$hoje, $hoje]);
            $novosHoje = $this->queryValue("SELECT COUNT(*) FROM tickets WHERE DATE(created_at) = ?", [$hoje]);
            
            return "🛠️ **Análise de Chamados de Hoje:**\n" .
                   "- Foram **solucionados $solucionadosHoje chamados** até agora! 🐠✨\n" .
                   "- Recebemos **$novosHoje novos chamados** hoje.\n" .
                   "O time de suporte está em pleno movimento! 🌊";
        }

        // Estatísticas GERAIS
        $abertos = $this->queryValue("SELECT COUNT(*) FROM tickets WHERE status = 'Aberto'");
        $atrasados = $this->queryValue("SELECT COUNT(*) FROM tickets WHERE status != 'Concluído' AND sla_deadline < NOW()");
        
        $res = "🛠️ **Status Geral de Chamados:**\n";
        $res .= "Atualmente temos **$abertos chamados aguardando** início.";
        if ($atrasados > 0) $res .= "\n⚠️ Atenção: **$atrasados chamados** estão com o SLA atrasado. 🐠";
        return $res;
    }

    private function getBudgetIntelligence($msg) {
        $pedidos = $this->queryValue("SELECT COUNT(*) FROM budget_requests WHERE status = 'Pendente'");
        $cotacoes = $this->queryValue("SELECT COUNT(*) FROM budget_quotes");
        
        return "💰 **Menu Orçamentos:**\n" .
               "Encontrei **$pedidos pedidos de orçamento pendentes** de aprovação. 🐠\n" .
               "Já temos um histórico de **$cotacoes cotações** registradas no sistema. Se precisar de detalhes sobre algum fornecedor ou valor, é só me chamar! 🌊";
    }

    private function getRHIntelligence($msg) {
        if ($this->match($msg, ['férias', 'ferias'])) {
            $stmt = $this->pdo->query("SELECT u.name, v.end_date FROM rh_vacations v JOIN users u ON v.user_id = u.id WHERE CURDATE() BETWEEN v.start_date AND v.end_date");
            $emFerias = $stmt->fetchAll();
            if (empty($emFerias)) return "🌴 **RH:** Não temos nenhum colaborador em férias hoje. Time completo! 🐠";
            $res = "🌴 **Colaboradores em Férias:**\n";
            foreach ($emFerias as $f) { $res .= "• " . $p = explode(' ', $f['name'])[0] . " (até " . date('d/m', strtotime($f['end_date'])) . ")\n"; }
            return $res . "🐠";
        }
        
        if ($this->match($msg, ['aniversário', 'niver'])) {
            $stmt = $this->pdo->query("SELECT u.name, DATE_FORMAT(rh.birth_date, '%d/%m') as dia FROM users u JOIN rh_employee_details rh ON u.id = rh.user_id WHERE MONTH(rh.birth_date) = MONTH(CURDATE()) ORDER BY DAY(rh.birth_date) ASC");
            $list = $stmt->fetchAll();
            $res = "🎂 **Aniversariantes do Mês:**\n";
            foreach ($list as $b) { $res .= "• " . $b['name'] . " (" . $b['dia'] . ")\n"; }
            return $res . "🐠";
        }

        $name = trim(str_replace(['quem é', 'quem e', 'colaborador'], '', $msg));
        if (strlen($name) > 2) {
            $stmt = $this->pdo->prepare("SELECT name, sector, role FROM users WHERE name LIKE ? LIMIT 1");
            $stmt->execute(['%' . $name . '%']);
            $u = $stmt->fetch();
            if ($u) return "👤 **" . $u['name'] . "** atua no setor de **" . $u['sector'] . "** como **" . $u['role'] . "**. 🐠";
        }
        return "Posso te ajudar com informações de RH, Férias e Aniversários! 🌊";
    }

    private function getPatrimonioIntelligence($msg) {
        if ($this->match($msg, ['devol', 'emprest', 'pendente', 'pegou'])) {
            $stmt = $this->pdo->query("SELECT borrower_name, asset_name, expected_return_date FROM loans WHERE status = 'Ativo'");
            $pendentes = $stmt->fetchAll();
            if (empty($pendentes)) return "Não temos nenhum empréstimo pendente no momento! 🐠";
            $res = "🔍 **Itens com Colaboradores:**\n";
            foreach ($pendentes as $p) {
                $atraso = ($p['expected_return_date'] < date('Y-m-d H:i:s')) ? " ⚠️ **(ATRASADO)**" : "";
                $res .= "• **" . $p['borrower_name'] . "**: " . $p['asset_name'] . " (Devolução: " . date('d/m', strtotime($p['expected_return_date'])) . ")$atraso\n";
            }
            return $res . "🐠";
        }
        $total = $this->queryValue("SELECT COUNT(*) FROM assets");
        $valor = $this->queryValue("SELECT SUM(estimated_value) FROM assets");
        return "📦 **Patrimônio:** Temos **$total itens** cadastrados (Valor total: R$ " . number_format($valor, 2, ',', '.') . "). 🐠";
    }

    private function getSocialIntelligence($msg) {
        $eventos = $this->queryValue("SELECT COUNT(*) FROM semanada_events WHERE event_date >= CURDATE()");
        $voluntarios = $this->queryValue("SELECT COUNT(*) FROM volunteers WHERE status = 'Ativo'");
        return "📢 **Social & Semanada:**\n" .
               "- Temos **$eventos próximos eventos** agendados no mural. 🐠\n" .
               "- Nossa rede conta com **$voluntarios voluntários** ativos transformando vidas! ❤️";
    }

    private function match($text, $keywords) {
        foreach ($keywords as $k) { if (mb_stripos($text, $k) !== false) return true; }
        return false;
    }

    private function queryValue($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn() ?: 0;
        } catch(Exception $e) { return 0; }
    }
}
