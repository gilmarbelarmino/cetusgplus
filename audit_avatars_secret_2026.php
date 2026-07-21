<?php
require_once 'config.php';

echo "<h2>Auditoria de Avatares - Voluntários e Usuários</h2>\n";
echo "<table border='1' cellpadding='6' style='border-collapse:collapse;font-family:sans-serif;'>\n";
echo "<tr>
    <th>ID Vol</th>
    <th>Nome Vol</th>
    <th>Email Vol</th>
    <th>Vol avatar_url (Tipo / Tamanho / Início)</th>
    <th>Vol isValidAvatar()?</th>
    <th>file_exists(__DIR__ . '/../' . ltrim)?</th>
    <th>User Matched Email?</th>
    <th>User avatar_url</th>
    <th>User isValidAvatar()?</th>
</tr>\n";

function checkValid($url) {
    if (empty(trim((string)$url))) return "FALSE (empty)";
    if (strpos($url, 'http') === 0 || strpos($url, 'data:image') === 0) return "TRUE (http/data)";
    $path = __DIR__ . '/' . ltrim($url, '/');
    if (file_exists($path)) {
        return "TRUE (file exists: $path)";
    } else {
        return "FALSE (file not found: $path)";
    }
}

try {
    $stmt = $pdo->query("SELECT v.id as vol_id, v.name as vol_name, v.email as vol_email, v.avatar_url as vol_avatar, 
                                u.id as user_id, u.avatar_url as user_avatar 
                         FROM volunteers v 
                         LEFT JOIN users u ON v.email = u.email 
                         ORDER BY v.id DESC");
    $rows = $stmt->fetchAll();

    foreach ($rows as $r) {
        $volAvatar = (string)$r['vol_avatar'];
        $volInfo = "Vazio/NULL";
        if (!empty(trim($volAvatar))) {
            if (strpos($volAvatar, 'data:image') === 0) {
                $volInfo = "BASE64 data:image (Len: " . strlen($volAvatar) . ") - " . substr($volAvatar, 0, 40) . "...";
            } elseif (strpos($volAvatar, 'http') === 0) {
                $volInfo = "HTTP URL: " . $volAvatar;
            } else {
                $volInfo = "FILE PATH: " . $volAvatar . " (Len: " . strlen($volAvatar) . ")";
            }
        }

        $volValid = checkValid($volAvatar);
        $fileCheck = file_exists(__DIR__ . '/' . ltrim($volAvatar, '/')) ? "SIM" : "NAO";

        $userAvatar = (string)$r['user_avatar'];
        $userInfo = "Vazio/NULL";
        if (!empty(trim($userAvatar))) {
            if (strpos($userAvatar, 'data:image') === 0) {
                $userInfo = "BASE64 (Len: " . strlen($userAvatar) . ")";
            } elseif (strpos($userAvatar, 'http') === 0) {
                $userInfo = "HTTP: " . $userAvatar;
            } else {
                $userInfo = "FILE: " . $userAvatar;
            }
        }
        $userValid = checkValid($userAvatar);

        echo "<tr>";
        echo "<td>" . htmlspecialchars($r['vol_id']) . "</td>";
        echo "<td>" . htmlspecialchars($r['vol_name']) . "</td>";
        echo "<td>" . htmlspecialchars($r['vol_email']) . "</td>";
        echo "<td>" . htmlspecialchars($volInfo) . "</td>";
        echo "<td>" . htmlspecialchars($volValid) . "</td>";
        echo "<td>" . htmlspecialchars($fileCheck) . "</td>";
        echo "<td>" . ($r['user_id'] ? "SIM (ID " . $r['user_id'] . ")" : "NÃO") . "</td>";
        echo "<td>" . htmlspecialchars($userInfo) . "</td>";
        echo "<td>" . htmlspecialchars($userValid) . "</td>";
        echo "</tr>\n";
    }
} catch (Exception $e) {
    echo "<tr><td colspan='9'>Erro: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
}
echo "</table>";
