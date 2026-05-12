<?php
echo "<h1>Status do Sistema - Verificação de Versão</h1>";
$files = ['index.php', 'config.php', 'pages/pesquisa.php', 'update_web.php'];

echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Arquivo</th><th>Última Modificação</th><th>Tamanho</th></tr>";

foreach ($files as $f) {
    if (file_exists($f)) {
        echo "<tr>";
        echo "<td>$f</td>";
        echo "<td>" . date("d/m/Y H:i:s", filemtime($f)) . "</td>";
        echo "<td>" . filesize($f) . " bytes</td>";
        echo "</tr>";
    } else {
        echo "<tr><td>$f</td><td colspan='2' style='color:red;'>NÃO ENCONTRADO</td></tr>";
    }
}
echo "</table>";

echo "<h2>Debug de Sessão</h2>";
session_start();
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
?>
