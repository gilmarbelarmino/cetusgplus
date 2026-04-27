<?php
$sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
socket_connect($sock, '8.8.8.8', 80);
socket_getsockname($sock, $ip);
socket_close($sock);
echo $ip;
