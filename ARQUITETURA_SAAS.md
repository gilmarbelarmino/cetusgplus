# Arquitetura do Cetusg Plus (SaaS Multi-Tenant)

Este documento serve como a **"Memória Principal"** para a IA. Sempre que iniciar um novo chat, leia este arquivo para entender o contexto do sistema.

## 1. Visão Geral
O Cetusg Plus foi transformado em uma plataforma **SaaS (Software as a Service) Multi-Tenant**. 
O sistema hospeda várias empresas simultaneamente, mantendo isolamento total de dados e configurações de marca.

## 2. Isolamento de Dados (Cadeado de Empresa)
*   **Regra de Ouro**: Nenhum usuário pode ver dados de outra empresa.
*   **Implementação**: A função `getCurrentUserCompanyId()` no `config.php` resgata o `company_id` da sessão atual.
*   **APIs**: Todas as APIs (ex: `api_patrimonio.php`, `api_chamados.php`) utilizam obrigatoriamente a cláusula `WHERE company_id = ?` em consultas e `INSERT` para garantir a blindagem.

## 3. Painel do Administrador Master (Gestão SaaS)
*   **Usuário Exclusivo**: Existe um usuário supremo com o login `superadmin` (Senha fixada em hash seguro no DB local e Web).
*   **Controle de Acesso**: O menu "Gestão SaaS" (`super_admin.php`) só é visível e acessível se `login_name === 'superadmin'`. Nenhuma outra conta pode gerenciar pagamentos.
*   **Funcionalidades**: 
    *   Listagem de todas as empresas clientes (`tenants`).
    *   Definição de valor de contrato mensal/anual.
    *   Registro de pagamento manual (Extensão de dias via `expires_at` e registro de `last_amount_paid`).

## 4. Estrutura de Menus e Landing Page
*   **Vitrine**: Usuários não logados que acessam `index.php` caem na apresentação comercial `landing.php` (Landing Page Premium com Glassmorphism).
*   **Sidebar do Super Admin**: Ao logar como `superadmin`, a função `getUserMenus` no `config.php` libera o array completo de módulos, garantindo acesso global.
*   **Sidebar do Cliente**: Usuários comuns veem apenas os módulos vinculados à sua empresa e têm os dados filtrados.
