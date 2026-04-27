@echo off
echo Liberando Apache no Firewall do Windows...
netsh advfirewall firewall add rule name="Apache XAMPP" dir=in action=allow protocol=TCP localport=80
netsh advfirewall firewall add rule name="MySQL XAMPP" dir=in action=allow protocol=TCP localport=3306
echo.
echo Firewall configurado!
echo Agora outros dispositivos podem acessar o sistema.
pause
