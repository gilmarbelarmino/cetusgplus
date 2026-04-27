# CETUSG Plus - Versão PHP

Sistema de Gestão Patrimonial convertido de Node.js/React para PHP puro, mantendo o layout e paletas de cores originais.

## 🎨 Características

- **Layout Idêntico**: Mantém o design moderno com sidebar, cards e tabelas
- **Paleta de Cores Original**: 
  - Roxo Principal: #5B21B6
  - Amarelo/Dourado: #FBBF24
  - Cinza Claro: #F1F5F9
- **Responsivo**: Interface adaptável para diferentes dispositivos
- **Ícones Lucide**: Mesma biblioteca de ícones do sistema original

## 📋 Requisitos

- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Servidor Web (Apache/Nginx)
- Extensões PHP: PDO, pdo_mysql

## 🚀 Instalação

### 1. Configurar Banco de Dados

```bash
# Importar o arquivo database.sql no MySQL
mysql -u root -p < database.sql
```

### 2. Configurar Conexão

Edite o arquivo `config.php` com suas credenciais:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'cetusg_plus');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');
```

### 3. Configurar Servidor Web

#### Apache (.htaccess)
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

#### Nginx
```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### 4. Acessar o Sistema

```
http://localhost/cetusg-plus/php/
```

**Credenciais padrão:**
- Login: `andre.mendes`
- Senha: `123`

## 📁 Estrutura de Arquivos

```
php/
├── assets/
│   └── css/
│       └── style.css          # Estilos com cores originais
├── pages/
│   ├── dashboard.php          # Página inicial
│   ├── patrimonio.php         # Gestão de ativos
│   ├── usuarios.php           # Gestão de usuários
│   ├── chamados.php           # Sistema de tickets
│   ├── emprestimos.php        # Controle de empréstimos
│   ├── orcamentos.php         # Gestão de orçamentos
│   └── voluntariado.php       # Controle de voluntários
├── config.php                 # Configuração do banco
├── auth.php                   # Sistema de autenticação
├── index.php                  # Arquivo principal
├── login.php                  # Página de login
├── logout.php                 # Logout
└── database.sql               # Script de criação do banco
```

## 🎯 Funcionalidades Implementadas

### ✅ Completas
- [x] Sistema de Login/Logout
- [x] Dashboard com estatísticas
- [x] Gestão de Patrimônio (listagem e filtros)
- [x] Gestão de Usuários (listagem e filtros)
- [x] Layout responsivo
- [x] Paleta de cores original
- [x] Ícones Lucide

### 🔄 Em Desenvolvimento
- [ ] CRUD completo de Patrimônio
- [ ] CRUD completo de Usuários
- [ ] Sistema de Chamados
- [ ] Gestão de Empréstimos
- [ ] Gestão de Orçamentos
- [ ] Controle de Voluntariado
- [ ] Relatórios em PDF
- [ ] Exportação para Excel

## 🎨 Paleta de Cores

```css
--crm-purple: #5B21B6;        /* Roxo principal */
--crm-purple-dark: #4C1D95;   /* Roxo escuro */
--crm-yellow: #FBBF24;        /* Amarelo/Dourado */
--crm-black: #020617;         /* Preto */
--crm-white: #FFFFFF;         /* Branco */
--crm-gray-light: #F1F5F9;    /* Cinza claro */
--crm-gray-border: #E2E8F0;   /* Cinza borda */
```

## 🔐 Segurança

- Senhas criptografadas com `password_hash()`
- Proteção contra SQL Injection (PDO Prepared Statements)
- Validação de sessões
- Escape de HTML com `htmlspecialchars()`

## 📝 Próximos Passos

1. Implementar CRUD completo para todas as entidades
2. Adicionar validações de formulário
3. Implementar upload de imagens
4. Criar sistema de permissões por role
5. Adicionar geração de relatórios PDF
6. Implementar exportação Excel
7. Adicionar gráficos (Chart.js)
8. Sistema de notificações

## 🤝 Suporte

Para dúvidas ou problemas, entre em contato com a equipe de desenvolvimento.

## 📄 Licença

Sistema proprietário - CETUSG Plus © 2024
