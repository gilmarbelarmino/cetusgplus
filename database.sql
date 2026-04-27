CREATE DATABASE IF NOT EXISTS cetusg_plus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cetusg_plus;

CREATE TABLE units (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address TEXT,
    cnpj VARCHAR(20),
    responsible_name VARCHAR(255),
    contact VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE users (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    sector VARCHAR(255),
    unit_id VARCHAR(50),
    role ENUM('Administrador', 'Responsável de Setor', 'Colaborador', 'Coordenador', 'Setor de Compras', 'Suporte Técnico', 'Voluntário') DEFAULT 'Colaborador',
    status ENUM('Ativo', 'Inativo') DEFAULT 'Ativo',
    avatar_url TEXT,
    phone VARCHAR(50),
    login_name VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES units(id)
);

CREATE TABLE assets (
    id VARCHAR(50) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100),
    patrimony_id VARCHAR(100) UNIQUE NOT NULL,
    sector VARCHAR(255),
    unit_id VARCHAR(50),
    status ENUM('Ativo', 'Manutenção', 'Inativo', 'Estoque') DEFAULT 'Ativo',
    responsible_name VARCHAR(255),
    last_maintenance DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES units(id)
);

CREATE TABLE tickets (
    id VARCHAR(50) PRIMARY KEY,
    asset_id VARCHAR(50),
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('Aberto', 'Em Progresso', 'Concluído', 'Cancelado') DEFAULT 'Aberto',
    priority ENUM('Baixa', 'Média', 'Alta', 'Crítica') DEFAULT 'Média',
    requester_id VARCHAR(50),
    sector VARCHAR(255),
    unit_id VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id),
    FOREIGN KEY (requester_id) REFERENCES users(id),
    FOREIGN KEY (unit_id) REFERENCES units(id)
);

CREATE TABLE loans (
    id VARCHAR(50) PRIMARY KEY,
    asset_id VARCHAR(50),
    asset_name VARCHAR(255),
    borrower_name VARCHAR(255),
    sector VARCHAR(255),
    unit_id VARCHAR(50),
    loan_date DATE,
    expected_return_date DATE,
    actual_return_date DATETIME,
    returner_name VARCHAR(255),
    observations TEXT,
    status ENUM('Ativo', 'Devolvido') DEFAULT 'Ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id),
    FOREIGN KEY (unit_id) REFERENCES units(id)
);

CREATE TABLE budget_requests (
    id VARCHAR(50) PRIMARY KEY,
    product_name VARCHAR(255) NOT NULL,
    description TEXT,
    sector VARCHAR(255),
    requester_id VARCHAR(50),
    unit_id VARCHAR(50),
    status ENUM('Pendente', 'Aprovado', 'Comprado', 'Cancelado') DEFAULT 'Pendente',
    approved_by VARCHAR(255),
    approval_date DATETIME,
    purchased_by VARCHAR(255),
    purchased_date DATETIME,
    rejected_by VARCHAR(255),
    rejection_date DATETIME,
    rejection_reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (requester_id) REFERENCES users(id),
    FOREIGN KEY (unit_id) REFERENCES units(id)
);

CREATE TABLE budget_quotes (
    id VARCHAR(50) PRIMARY KEY,
    budget_id VARCHAR(50),
    supplier_name VARCHAR(255),
    price DECIMAL(10,2),
    quantity INT,
    shipping_cost DECIMAL(10,2),
    shipping_method VARCHAR(100),
    total DECIMAL(10,2),
    link TEXT,
    attachment_url TEXT,
    FOREIGN KEY (budget_id) REFERENCES budget_requests(id) ON DELETE CASCADE
);

CREATE TABLE volunteers (
    id VARCHAR(50) PRIMARY KEY,
    user_id VARCHAR(50),
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    unit_id VARCHAR(50),
    sector_id VARCHAR(255),
    volunteering_sector VARCHAR(255),
    action_type VARCHAR(255),
    location ENUM('Local', 'Remoto') DEFAULT 'Local',
    profession VARCHAR(255),
    hourly_rate DECIMAL(10,2),
    status ENUM('Ativo', 'Fechado') DEFAULT 'Ativo',
    start_date DATE,
    end_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (unit_id) REFERENCES units(id)
);

CREATE TABLE volunteer_hours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    volunteer_id VARCHAR(50),
    month VARCHAR(10),
    hours INT DEFAULT 0,
    FOREIGN KEY (volunteer_id) REFERENCES volunteers(id) ON DELETE CASCADE
);

-- Inserir dados de exemplo
INSERT INTO units VALUES 
('U1', 'Matriz', 'Av. Paulista, 1000, São Paulo - SP', '12.345.678/0001-01', 'André Mendes', '(11) 98888-7777', NOW()),
('U2', 'Sede A', 'Rua das Flores, 500, Rio de Janeiro - RJ', '12.345.678/0002-02', 'Beatriz Rocha', '(21) 97777-6666', NOW()),
('U3', 'Sede B', 'Av. Beira Mar, 200, Florianópolis - SC', '12.345.678/0003-03', 'Daniela Souza', '(48) 95555-4444', NOW());

-- Senha padrão: 123
INSERT INTO users VALUES 
('1', 'André Mendes', 'andre.mendes@empresa.com.br', 'Tecnologia e Inovação', 'U1', 'Administrador', 'Ativo', NULL, '(11) 98888-7777', 'andre.mendes', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NOW()),
('2', 'Beatriz Rocha', 'beatriz.rocha@empresa.com.br', 'Operações Patrimoniais', 'U2', 'Responsável de Setor', 'Ativo', NULL, '(11) 97777-6666', 'beatriz.rocha', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NOW());

INSERT INTO assets VALUES 
('1', 'Monitor Dell P2419H', 'Hardware', 'TI-MON-081', 'Financeiro', 'U1', 'Ativo', 'Beatriz Rocha', '2023-10-12', NOW()),
('2', 'CPU HP ProDesk 600', 'Hardware', 'TI-CPU-042', 'Administrativo', 'U1', 'Manutenção', 'Carlos Camargo', '2023-11-05', NOW()),
('3', 'HP LaserJet Enterprise', 'Impressoras', 'TI-PRN-015', 'Recepção Central', 'U2', 'Ativo', 'Daniela Souza', '2023-09-20', NOW());

INSERT INTO tickets VALUES 
('T1', '1', 'Monitor piscando', 'O monitor desliga sozinho aleatoriamente', 'Aberto', 'Alta', '2', 'Financeiro', 'U1', NOW()),
('T2', '2', 'Lentidão extrema', 'PC demora 10 min para ligar', 'Em Progresso', 'Média', '1', 'Administrativo', 'U1', NOW());
