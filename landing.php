<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CetusgTech - Gestão Inteligente</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --landing-bg: #020617;
            --accent-glow: rgba(37, 99, 235, 0.15);
        }

        body, html {
            margin: 0; padding: 0;
            font-family: 'Arial', sans-serif;
            background: var(--landing-bg);
            color: white;
            overflow-x: hidden;
            scroll-behavior: smooth;
        }

        /* Fundo Animado */
        .bg-glow {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: radial-gradient(circle at 50% 50%, #1e1b4b 0%, #020617 100%);
            z-index: -1;
        }

        .blob {
            position: absolute; width: 500px; height: 500px;
            background: var(--brand-primary);
            filter: blur(150px); opacity: 0.1;
            border-radius: 50%;
            animation: move 20s infinite alternate;
        }

        @keyframes move {
            from { transform: translate(-10%, -10%); }
            to { transform: translate(20%, 20%); }
        }

        /* Header */
        header {
            padding: 2rem 5%;
            display: flex; justify-content: space-between; align-items: center;
            position: fixed; top: 0; width: 90%; z-index: 1000;
            backdrop-filter: blur(10px);
        }

        .logo-tech {
            font-size: 1.8rem; font-weight: 900; letter-spacing: -1px;
            color: white; text-decoration: none;
        }
        .logo-tech span { color: #FBBF24; }

        .btn-login-top {
            background: white; color: #020617;
            padding: 0.8rem 2rem; border-radius: 100px;
            text-decoration: none; font-weight: 800;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        .btn-login-top:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(251, 191, 36, 0.3);
            background: #FBBF24;
        }

        /* Hero Section */
        .hero {
            height: 100vh; display: flex; align-items: center; justify-content: center;
            text-align: center; padding: 0 10%;
        }

        .hero-content { max-width: 900px; animation: fadeInUp 1s ease-out; }

        h1 {
            font-size: 4rem; font-weight: 900; line-height: 1.1; margin-bottom: 1.5rem;
            background: linear-gradient(to right, #fff, #94a3b8);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        }

        p.subtitle {
            font-size: 1.25rem; color: #94a3b8; line-height: 1.6;
            margin-bottom: 3rem; max-width: 700px; margin-inline: auto;
        }

        /* Grid de Funcionalidades */
        .features {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem; padding: 5rem 10%;
        }

        .feature-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.05);
            padding: 2.5rem; border-radius: 2rem;
            transition: all 0.3s;
            text-align: left;
        }
        .feature-card:hover {
            background: rgba(255,255,255,0.06);
            transform: translateY(-10px);
            border-color: rgba(251, 191, 36, 0.3);
        }

        .feature-card i {
            font-size: 2.5rem; color: #FBBF24; margin-bottom: 1.5rem;
        }

        .feature-card h3 { font-size: 1.5rem; margin-bottom: 1rem; }
        .feature-card p { color: #94a3b8; line-height: 1.5; font-size: 0.95rem; }

        /* Footer Admin */
        footer {
            padding: 3rem 5%;
            display: flex; justify-content: space-between; align-items: center;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        .btn-admin-bottom {
            color: #4b5563; text-decoration: none;
            font-size: 0.85rem; font-weight: 600;
            display: flex; align-items: center; gap: 8px;
            transition: color 0.3s;
        }
        .btn-admin-bottom:hover { color: #FBBF24; }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            h1 { font-size: 2.5rem; }
            .hero { padding-top: 100px; height: auto; padding-bottom: 5rem; }
        }
    </style>
</head>
<body>
    <div class="bg-glow"></div>
    <div class="blob"></div>

    <header>
        <a href="#" class="logo-tech">CETUSG<span>TECH</span></a>
        <a href="login.php" class="btn-login-top">Acessar Sistema</a>
    </header>

    <section class="hero">
        <div class="hero-content">
            <h1>Gestão Inteligente para um Mundo Conectado.</h1>
            <p class="subtitle">A <strong>CetusgTech</strong> entrega soluções robustas e intuitivas para otimizar processos, gerir ativos e potencializar o capital humano de organizações modernas.</p>
            <div style="display: flex; gap: 1rem; justify-content: center;">
                <a href="#about" class="btn-primary" style="padding: 1rem 2.5rem; border-radius: 100px; text-decoration: none;">Conheça os Módulos</a>
            </div>
        </div>
    </section>

    <section class="features" id="about">
        <div class="feature-card">
            <i class="fa-solid fa-box-archive"></i>
            <h3>Patrimônio & Ativos</h3>
            <p>Controle total de inventário, movimentações e manutenção de ativos com rastreabilidade completa.</p>
        </div>
        <div class="feature-card">
            <i class="fa-solid fa-users"></i>
            <h3>Capital Humano</h3>
            <p>Gestão de RH simplificada, desde registros de funcionários até controle de benefícios e documentação.</p>
        </div>
        <div class="feature-card">
            <i class="fa-solid fa-heart"></i>
            <h3>Voluntariado</h3>
            <p>Plataforma dedicada ao engajamento social, controle de horas e certificação de voluntários.</p>
        </div>
        <div class="feature-card">
            <i class="fa-solid fa-laptop-code"></i>
            <h3>Suporte & TI</h3>
            <p>Sistema de chamados inteligente com SLA dinâmico para garantir a continuidade do seu negócio.</p>
        </div>
    </section>

    <footer>
        <div style="color: #4b5563; font-size: 0.85rem;">&copy; <?= date('Y') ?> CetusgTech. Todos os direitos reservados.</div>
        <a href="login.php?type=admin" class="btn-admin-bottom">
            <i class="fa-solid fa-shield-halved"></i> Área do Administrador
        </a>
    </footer>

    <script>
        // Micro-interação no scroll
        window.addEventListener('scroll', () => {
            const header = document.querySelector('header');
            if (window.scrollY > 50) {
                header.style.background = 'rgba(2, 6, 23, 0.8)';
                header.style.padding = '1rem 5%';
            } else {
                header.style.background = 'transparent';
                header.style.padding = '2rem 5%';
            }
        });
    </script>
</body>
</html>
