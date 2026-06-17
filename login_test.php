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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>Login - <?= htmlspecialchars($company['company_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php if (!empty($company['logo_url'])): ?>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($company['logo_url']) ?>">
    <link rel="shortcut icon" href="<?= htmlspecialchars($company['logo_url']) ?>">
    <?php endif; ?>
    
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #000;
            overflow-x: hidden;
            color: #fff;
        }

        /* Hero Wrapper */
        #hero-section {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 10;
            transition: background-color 0.5s;
        }

        /* Background Image Fixed behind everything */
        #hero-bg {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background-image: url('ocean_bg.png');
            background-size: cover;
            background-position: center;
            z-index: -1;
            transition: opacity 0.1s linear;
        }
        #hero-bg-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.1);
            z-index: -1;
        }

        /* Dynamic Media Box */
        #media-box {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            border-radius: 1.5rem;
            overflow: hidden;
            box-shadow: 0 0 50px rgba(0,0,0,0.3);
            /* Starts at width 300px, height 400px (desktop) */
            /* updated via JS */
            z-index: 5;
            background: #000;
            will-change: width, height, transform;
        }

        #media-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        #media-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.7);
            z-index: 6;
            will-change: opacity;
        }

        /* Titles that split */
        #title-container {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            z-index: 10;
            width: 100%;
            pointer-events: none;
            mix-blend-mode: difference;
            flex-direction: column;
        }
        .split-title {
            font-family: 'Outfit', sans-serif;
            font-size: 4rem;
            font-weight: 800;
            color: #bfdbfe; /* blue-200 */
            white-space: nowrap;
            will-change: transform;
        }
        @media (min-width: 768px) {
            .split-title { font-size: 6rem; }
        }

        /* Subtexts inside the media box */
        #media-text-content {
            position: absolute;
            bottom: 2rem;
            left: 0; right: 0;
            text-align: center;
            z-index: 7;
        }
        .media-subtitle {
            font-size: 1.5rem;
            color: #bfdbfe;
            margin-bottom: 0.5rem;
            will-change: transform;
            display: inline-block;
        }
        .media-instruction {
            font-weight: 500;
            color: #bfdbfe;
            will-change: transform;
            display: inline-block;
        }

        /* Content Area (Login Form) */
        #login-content-area {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            z-index: 20;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.7s ease;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }

        .login-box-ui {
            background: #ffffff;
            padding: 3rem;
            border-radius: 1.5rem;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
            width: 100%;
            max-width: 450px;
            color: #0f172a;
            transform: translateY(20px);
            transition: transform 0.7s ease;
        }

        #login-content-area.show {
            opacity: 1;
            pointer-events: auto;
        }
        #login-content-area.show .login-box-ui {
            transform: translateY(0);
        }

        .login-title {
            font-family: 'Outfit', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        .form-group { margin-bottom: 1.5rem; }
        .form-label {
            display: block; font-size: 0.875rem; font-weight: 600;
            color: #475569; margin-bottom: 0.5rem;
        }
        .form-input {
            width: 100%; padding: 1rem 1.25rem; border: 1px solid #cbd5e1;
            border-radius: 0.75rem; background: #f8fafc; color: #0f172a;
            font-size: 1rem; outline: none; transition: all 0.2s;
        }
        .form-input:focus {
            background: #fff; border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }
        .btn-login {
            width: 100%; padding: 1.1rem; background: #2563eb; color: #fff;
            border: none; border-radius: 0.75rem; font-size: 1rem; font-weight: 600;
            cursor: pointer; transition: background 0.2s, transform 0.1s;
        }
        .btn-login:hover { background: #1d4ed8; }

        .error-alert {
            background: #fef2f2; color: #b91c1c; padding: 1rem;
            border-radius: 0.75rem; margin-bottom: 1.5rem; font-size: 0.875rem;
            font-weight: 500; border: 1px solid #fecaca; display: flex; gap: 8px;
        }
        
        .login-logo-img { max-height: 50px; margin-bottom: 1.5rem; }
    </style>
</head>
<body>

    <div id="hero-section">
        <!-- Background Layer fading out -->
        <div id="hero-bg"></div>
        <div id="hero-bg-overlay"></div>

        <!-- The expanding media box -->
        <div id="media-box">
            <img src="ocean_bg.png" id="media-img" alt="CetusG BG">
            <div id="media-overlay"></div>
            
            <div id="media-text-content">
                <div class="media-subtitle" id="date-text">Soluções Corporativas</div><br>
                <div class="media-instruction" id="scroll-text">Role o mouse para entrar</div>
            </div>
        </div>

        <!-- Split Titles -->
        <div id="title-container">
            <h2 class="split-title" id="title-word-1"><?= explode(' ', $company['company_name'])[0] ?? 'CetusG' ?></h2>
            <h2 class="split-title" id="title-word-2"><?= implode(' ', array_slice(explode(' ', $company['company_name']), 1)) ?: 'System' ?></h2>
        </div>
    </div>

    <div id="login-content-area">
        <div class="login-box-ui">
            <?php if (!empty($company['logo_url'])): ?>
                <img src="<?= htmlspecialchars($company['logo_url']) ?>" alt="Logo" class="login-logo-img">
            <?php endif; ?>
            <h2 class="login-title">Bem-vindo</h2>
            <p style="color: #64748b; margin-bottom: 2rem;">Acesse sua conta para continuar no <?= htmlspecialchars($company['company_name']) ?></p>

            <?php if ($error): ?>
                <div class="error-alert">
                    <i class="fa-solid fa-circle-exclamation" style="margin-top: 3px;"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" autocomplete="off" action="login_test.php">
                <div class="form-group">
                    <label class="form-label">Usuário</label>
                    <input type="text" name="login_name" class="form-input" placeholder="Digite seu usuário" required autofocus>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Senha</label>
                    <input type="password" name="access_code" class="form-input" placeholder="Digite sua senha" required autocomplete="off">
                </div>
                
                <button type="submit" class="btn-login">
                    Acessar Sistema <i class="fa-solid fa-arrow-right-to-bracket"></i>
                </button>
            </form>
            <div style="margin-top: 2rem; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 1.5rem;">
                <p style="font-size: 0.8rem; color: #94a3b8;">&copy; <?= date('Y') ?> CETUSG SYSTEM.</p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            let scrollProgress = 0;
            let mediaFullyExpanded = false;
            let touchStartY = 0;
            let isMobile = window.innerWidth < 768;

            const mediaBox = document.getElementById('media-box');
            const mediaOverlay = document.getElementById('media-overlay');
            const heroBg = document.getElementById('hero-bg');
            const word1 = document.getElementById('title-word-1');
            const word2 = document.getElementById('title-word-2');
            const dateText = document.getElementById('date-text');
            const scrollText = document.getElementById('scroll-text');
            const loginArea = document.getElementById('login-content-area');

            window.addEventListener('resize', () => {
                isMobile = window.innerWidth < 768;
                updateVisuals();
            });

            function updateVisuals() {
                // Background opacity
                heroBg.style.opacity = 1 - scrollProgress;

                // Media Box dimensions
                const maxWidth = isMobile ? window.innerWidth * 0.95 : window.innerWidth * 0.95;
                const maxHeight = isMobile ? window.innerHeight * 0.85 : window.innerHeight * 0.85;

                const baseWidth = 300;
                const expandW = isMobile ? 650 : 1250;
                let currentWidth = baseWidth + (scrollProgress * expandW);
                if(currentWidth > maxWidth) currentWidth = maxWidth;

                const baseHeight = 400;
                const expandH = isMobile ? 200 : 400;
                let currentHeight = baseHeight + (scrollProgress * expandH);
                if(currentHeight > maxHeight) currentHeight = maxHeight;

                mediaBox.style.width = `${currentWidth}px`;
                mediaBox.style.height = `${currentHeight}px`;

                // Media Overlay opacity
                const overlayOp = 0.7 - (scrollProgress * 0.3);
                mediaOverlay.style.opacity = overlayOp < 0 ? 0 : overlayOp;

                // Text Translations
                const textTranslateX = scrollProgress * (isMobile ? 180 : 150); // vw
                word1.style.transform = `translateX(-${textTranslateX}vw)`;
                word2.style.transform = `translateX(${textTranslateX}vw)`;
                dateText.style.transform = `translateX(-${textTranslateX}vw)`;
                scrollText.style.transform = `translateX(${textTranslateX}vw)`;

                if (scrollProgress >= 1) {
                    loginArea.classList.add('show');
                } else {
                    loginArea.classList.remove('show');
                }
            }

            function handleScrollDelta(deltaY) {
                if (mediaFullyExpanded && deltaY < -20) {
                    mediaFullyExpanded = false;
                } else if (!mediaFullyExpanded) {
                    let scrollFactor = isMobile ? (deltaY < 0 ? 0.008 : 0.005) : 0.0009;
                    if(isMobile === false) scrollFactor = 0.0009; // wheel
                    
                    let newProgress = scrollProgress + (deltaY * scrollFactor);
                    if(newProgress < 0) newProgress = 0;
                    if(newProgress > 1) newProgress = 1;

                    scrollProgress = newProgress;

                    if (newProgress >= 1) {
                        mediaFullyExpanded = true;
                    }

                    updateVisuals();
                }
            }

            window.addEventListener('wheel', (e) => {
                if(!mediaFullyExpanded) e.preventDefault();
                handleScrollDelta(e.deltaY);
            }, { passive: false });

            window.addEventListener('touchstart', (e) => {
                touchStartY = e.touches[0].clientY;
            }, { passive: false });

            window.addEventListener('touchmove', (e) => {
                if (!touchStartY) return;
                const touchY = e.touches[0].clientY;
                const deltaY = touchStartY - touchY;

                if (!mediaFullyExpanded) {
                    e.preventDefault();
                }
                
                handleScrollDelta(deltaY);
                touchStartY = touchY;
            }, { passive: false });

            window.addEventListener('touchend', () => {
                touchStartY = 0;
            });

            // Init
            updateVisuals();
        });
    </script>
</body>
</html>
