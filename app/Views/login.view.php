<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Cetusg Plus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f8fafc; margin: 0; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-card { background: white; padding: 3rem; border-radius: 2rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }
        .logo { width: 64px; height: 64px; background: #6366f1; border-radius: 1.5rem; display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: 800; margin: 0 auto 2rem; }
        .input-group { text-align: left; margin-bottom: 1.5rem; }
        .input-group label { display: block; font-weight: 700; margin-bottom: 0.5rem; color: #475569; font-size: 0.9rem; }
        .form-input { width: 100%; padding: 0.85rem 1rem; border-radius: 0.75rem; border: 1px solid #e2e8f0; box-sizing: border-box; font-family: inherit; font-size: 1rem; }
        .btn-login { width: 100%; padding: 1rem; background: #6366f1; color: white; border: none; border-radius: 0.75rem; font-weight: 800; cursor: pointer; transition: background 0.2s; margin-top: 1rem; }
        .btn-login:hover { background: #4f46e5; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo">C</div>
        <h2 style="font-weight: 900; color: #1e293b;">Acessar Cetusg Plus</h2>
        <p style="color: #64748b; margin-bottom: 2rem;">Entre com suas credenciais SaaS</p>
        
        <form method="POST" action="<?= URL_BASE ?>/login">
            <div class="input-group">
                <label>E-mail</label>
                <input type="email" name="email" class="form-input" placeholder="seu@email.com" required>
            </div>
            <div class="input-group">
                <label>Senha</label>
                <input type="password" name="password" class="form-input" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-login">Entrar no Sistema</button>
        </form>
    </div>
</body>
</html>
