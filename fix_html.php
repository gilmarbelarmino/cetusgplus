<?php
$file = 'C:\xampp\htdocs\cetusg\pages\rh.php';
$content = file_get_contents($file);
$content = preg_replace('/htmlspecialchars\(\$([a-zA-Z0-9_\'\[\]]+)\)/', 'htmlspecialchars(\$$1 ?? \'\')', $content);
file_put_contents($file, $content);
echo "Fix aplicado!";
