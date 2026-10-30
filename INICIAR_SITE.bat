@echo off
title Site de Achadinhos - NAO FECHE ESTA JANELA
cd /d "%~dp0"
set PHPEXE=php
where php >nul 2>nul || set PHPEXE=C:\php\php.exe
"%PHPEXE%" -v >nul 2>nul
if errorlevel 1 (
  echo.
  echo [ERRO] O PHP nao foi encontrado.
  echo Instale o PHP em C:\php  ^(veja o passo a passo que o Claude explicou^)
  echo e rode este arquivo de novo.
  echo.
  pause
  exit /b
)
echo.
echo Site rodando em:  http://localhost:8000
echo Para parar: feche esta janela ou aperte Ctrl+C.
echo.
start "" cmd /c "timeout /t 2 >nul & start http://localhost:8000"
"%PHPEXE%" -S localhost:8000 -t public public/index.php
pause
