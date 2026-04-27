<?php
// Pega todos os IPs da máquina e filtra o da rede local
$ips = [];
exec('ipconfig', $output);
foreach ($output as $line) {
    if (preg_match('/IPv4.*?:\s*(192\.168\.\d+\.\d+|10\.\d+\.\d+\.\d+|172\.(1[6-9]|2\d|3[01])\.\d+\.\d+)/', $line, $m)) {
        $ips[] = $m[1];
    }
}
$ip = !empty($ips) ? $ips[0] : gethostbyname(gethostname());
$url = 'http://' . $ip . '/cetusg/';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso CETUSG Plus - Rede Local</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; background: linear-gradient(135deg, #5B21B6 0%, #FBBF24 100%); }
        .container { background: white; padding: 3rem; border-radius: 2rem; box-shadow: 0 25px 50px rgba(0,0,0,0.2); text-align: center; max-width: 500px; }
        h1 { color: #5B21B6; font-size: 2rem; margin-bottom: 1rem; }
        .link-box { background: #f1f5f9; padding: 1.5rem; border-radius: 1rem; margin: 1.5rem 0; }
        a { color: #5B21B6; font-size: 1.25rem; font-weight: bold; text-decoration: none; display: block; padding: 1rem; background: white; border-radius: 0.75rem; margin: 0.5rem 0; transition: all 0.3s; }
        a:hover { background: #5B21B6; color: white; transform: translateY(-2px); }
        .info { color: #64748b; font-size: 0.875rem; margin-top: 1rem; }
        .copy-btn { background: #5B21B6; color: white; border: none; padding: 0.5rem 1.5rem; border-radius: 0.5rem; cursor: pointer; font-size: 0.875rem; margin-top: 0.5rem; }
        .copy-btn:hover { background: #4c1d95; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🌐 CETUSG Plus - Acesso em Rede</h1>

        <div class="link-box">
            <p style="color: #5B21B6; font-weight: bold; margin-bottom: 1rem;">Acesse de qualquer dispositivo na mesma rede:</p>
            <a href="<?= $url ?>" target="_blank">📱 <?= $url ?></a>
            <button class="copy-btn" onclick="navigator.clipboard.writeText('<?= $url ?>').then(()=>this.textContent='✅ Copiado!').catch(()=>{})">📋 Copiar link</button>
        </div>

        <div class="info">
            <p><strong>Login:</strong> andre.mendes</p>
            <p><strong>Senha:</strong> 123</p>
            <hr style="margin: 1rem 0; border: none; border-top: 1px solid #e2e8f0;">
            <p>⚠️ XAMPP deve estar rodando (Apache e MySQL)</p>
            <p>📡 Dispositivos devem estar na mesma rede Wi-Fi</p>
        </div>
    </div>
</body>
</html>
