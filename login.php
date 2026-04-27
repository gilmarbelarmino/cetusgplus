<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

// Buscar configurações da empresa
$company = $pdo->query("SELECT * FROM company_settings LIMIT 1")->fetch();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (login($_POST['login_name'], $_POST['password'])) {
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
    <title>Login - <?= htmlspecialchars($company['company_name']) ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.0/css/all.min.css">
    <?php if (!empty($company['logo_url'])): ?>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($company['logo_url']) ?>">
    <link rel="shortcut icon" href="<?= htmlspecialchars($company['logo_url']) ?>">
    <?php endif; ?>
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at center, #020617 0%, #000000 100%);
            position: relative;
            overflow: hidden;
            padding: 1.5rem;
        }
        #particles-js {
            position: absolute;
            inset: 0;
            z-index: 1;
        }
        .tech-glow {
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 50% 50%, rgba(79, 70, 229, 0.1) 0%, transparent 70%);
            z-index: 2;
            pointer-events: none;
        }
        .login-box {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            padding: 3.5rem 2.5rem;
            border-radius: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 40px rgba(79, 70, 229, 0.2);
            width: 100%;
            max-width: 440px;
            animation: fadeIn 0.8s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            z-index: 3;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-logo-container {
            text-align: center;
            margin-bottom: 3rem;
        }
        .login-logo-img {
            max-height: 80px;
            margin-bottom: 1.5rem;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));
        }
        .login-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.875rem;
            font-weight: 800;
            color: #1e293b;
            letter-spacing: -0.5px;
        }
        .error-alert {
            background: #FEE2E2;
            color: #DC2626;
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 2rem;
            font-size: 0.875rem;
            text-align: center;
            font-weight: 600;
            border: 1px solid rgba(220, 38, 38, 0.1);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div id="particles-js"></div>
        <div class="tech-glow"></div>
        
        <div class="login-box">
            <div class="login-logo-container">
                <?php if (!empty($company['logo_url'])): ?>
                    <img src="<?= htmlspecialchars($company['logo_url']) ?>" alt="Logo" class="login-logo-img">
                <?php endif; ?>
                <h1 class="login-title"><?= htmlspecialchars($company['company_name']) ?></h1>
                <p style="color: #64748b; font-size: 0.9rem; margin-top: 0.5rem; font-weight: 500;">Acesse sua conta para continuar</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error-alert">
                    <i class="fa-solid fa-circle-exclamation" style="margin-right: 8px;"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Usuario</label>
                    <input type="text" name="login_name" class="form-input" placeholder="Seu usuario" required autofocus>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Senha</label>
                    <input type="password" name="password" class="form-input" placeholder="Sua senha" required>
                </div>
                
                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 1rem; border-radius: 100px; padding: 1rem;">
                    Entrar no Sistema <i class="fa-solid fa-arrow-right-to-bracket" style="margin-left: 8px;"></i>
                </button>
            </form>
            
            <div style="margin-top: 2.5rem; text-align: center;">
                <p style="font-size: 0.75rem; color: #94a3b8; font-weight: 600;">CETUSG SYSTEM &copy; <?= date('Y') ?></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <script>
        particlesJS('particles-js', {
          "particles": {
            "number": { "value": 80, "density": { "enable": true, "value_area": 800 } },
            "color": { "value": ["#3b82f6", "#8b5cf6", "#6366f1"] },
            "shape": { "type": "circle" },
            "opacity": { "value": 0.5, "random": true },
            "size": { "value": 3, "random": true },
            "line_linked": { "enable": true, "distance": 150, "color": "#6366f1", "opacity": 0.2, "width": 1 },
            "move": { "enable": true, "speed": 1.5, "direction": "none", "random": false, "straight": false, "out_mode": "out", "bounce": false }
          },
          "interactivity": {
            "detect_on": "canvas",
            "events": { "onhover": { "enable": true, "mode": "grab" }, "onclick": { "enable": true, "mode": "push" }, "resize": true },
            "modes": { "grab": { "distance": 140, "line_linked": { "opacity": 1 } }, "push": { "particles_nb": 4 } }
          },
          "retina_detect": true
        });
    </script>
</body>
</html>
