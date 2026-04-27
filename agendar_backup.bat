@echo off
set PHP_BIN=C:\xampp\php\php.exe
set SCRIPT_PATH=C:\xampp\htdocs\cetusg\process_full_backup.php

echo Iniciando Backup do Sistema Cetusg Plus...
"%PHP_BIN%" "%SCRIPT_PATH%"

if %ERRORLEVEL% EQU 0 (
    echo Backup concluido com sucesso!
) else (
    echo ERRO AO REALIZAR BACKUP. Verifique os logs.
    pause
)
