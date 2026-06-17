<?php
require_once 'config.php';
require_once 'auth.php';

// Buscar configurações da empresa
$company = $pdo->query("SELECT * FROM company_settings LIMIT 1")->fetch();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (login($_POST['login_name'], $_POST['access_code'])) {
            header('Location: index.php?page=dashboard');
            exit;
        } else {
            $error = 'Credenciais invalidas';
        }
    } catch(Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($company['company_name']) ?> (3D Interactive)</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php if (!empty($company['logo_url'])): ?>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($company['logo_url']) ?>">
    <?php endif; ?>
    
    <!-- Spline 3D Viewer Script -->
    <script type="module" src="https://unpkg.com/@splinetool/viewer@1.0.94/build/spline-viewer.js"></script>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
        }
        body {
            background-color: #000;
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            overflow: hidden;
        }
        
        /* Background Glow (Aceternity Spotlight equivalent) */
        .bg-spotlight {
            position: absolute;
            top: -20%; left: -10%;
            width: 80%; height: 120%;
            background: radial-gradient(ellipse at center, rgba(255,255,255,0.08) 0%, rgba(0,0,0,0) 60%);
            pointer-events: none;
            z-index: 0;
            transform: rotate(-15deg);
        }

        /* The Main Card */
        .card-container {
            position: relative;
            width: 100%;
            max-width: 1100px;
            height: 600px;
            background: rgba(10, 10, 10, 0.96);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
            display: flex;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5), 0 0 40px rgba(255,255,255,0.05);
            z-index: 10;
        }

        /* Interactive Spotlight tracking mouse */
        .interactive-spotlight {
            position: absolute;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.06) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            transform: translate(-50%, -50%);
            z-index: 1;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .card-container:hover .interactive-spotlight {
            opacity: 1;
        }

        /* Layout */
        .content-left {
            flex: 1;
            padding: 4rem;
            position: relative;
            z-index: 10;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .content-right {
            flex: 1;
            position: relative;
            z-index: 5;
            background: transparent;
        }

        /* Spline Viewer Override */
        spline-viewer {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* Typography & Forms */
        .login-logo {
            max-height: 40px;
            margin-bottom: 2rem;
            filter: brightness(0) invert(1); /* Makes logo white if it's dark */
        }
        .login-title {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(to bottom, #f9fafb, #9ca3af);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
            letter-spacing: -1px;
            line-height: 1.1;
        }
        .login-desc {
            color: #d1d5db;
            margin-bottom: 2.5rem;
            line-height: 1.6;
            font-size: 1.05rem;
        }
        
        .form-group {
            margin-bottom: 1.25rem;
        }
        .form-input {
            width: 100%;
            padding: 1rem 1.25rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #fff;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        .form-input:focus {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.3);
            box-shadow: 0 0 0 4px rgba(255,255,255,0.05);
        }
        .form-input::placeholder {
            color: #6b7280;
        }

        .btn-login {
            width: 100%;
            padding: 1.1rem;
            background: #fff;
            color: #000;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 1rem;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255,255,255,0.1);
        }

        .error-alert {
            background: rgba(220, 38, 38, 0.1);
            color: #fca5a5;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(220, 38, 38, 0.2);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        /* Mobile Adjustments */
        @media (max-width: 900px) {
            body { padding: 1rem; overflow-y: auto; align-items: flex-start; }
            .card-container {
                flex-direction: column;
                height: auto;
                min-height: 800px;
            }
            .content-left { padding: 2.5rem; }
            .content-right { height: 400px; flex: none; }
            .login-title { font-size: 2.5rem; }
        }
    </style>
</head>
<body>

    <!-- Background global spotlight -->
    <div class="bg-spotlight"></div>

    <!-- Main Aceternity/Shadcn inspired Card -->
    <div class="card-container" id="interactive-card">
        <!-- Interactive Mouse Spotlight -->
        <div class="interactive-spotlight" id="mouse-spotlight"></div>

        <!-- Left Content: Login Form -->
        <div class="content-left">
            <?php if (!empty($company['logo_url'])): ?>
                <!-- A img filter ensures dark logos become white on this dark theme -->
                <img src="<?= htmlspecialchars($company['logo_url']) ?>" alt="Logo" class="login-logo" style="filter: brightness(0) invert(1);">
            <?php endif; ?>
            
            <h1 class="login-title">Acesso Seguro</h1>
            <p class="login-desc">
                Traga sua operação para o futuro com ambientes interativos e imersivos. Insira suas credenciais abaixo.
            </p>

            <?php if ($error): ?>
                <div class="error-alert">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off" action="login_test.php">
                <div class="form-group">
                    <input type="text" name="login_name" class="form-input" placeholder="Usuário" required autofocus>
                </div>
                
                <div class="form-group">
                    <input type="password" name="access_code" class="form-input" placeholder="Senha" required>
                </div>
                
                <button type="submit" class="btn-login">
                    Entrar <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>
        </div>

        <!-- Right Content: Spline 3D Scene -->
        <div class="content-right">
            <!-- Scene URL from user's prompt -->
            <spline-viewer url="https://prod.spline.design/kZDDjO5HuC9GJUM2/scene.splinecode"></spline-viewer>
        </div>
    </div>

    <!-- Mouse Tracking Script for Spotlight -->
    <script>
        const card = document.getElementById('interactive-card');
        const spotlight = document.getElementById('mouse-spotlight');

        card.addEventListener('mousemove', (e) => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            spotlight.style.left = `${x}px`;
            spotlight.style.top = `${y}px`;
        });
    </script>
</body>
</html>
