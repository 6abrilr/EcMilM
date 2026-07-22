@echo off
cd /d "%~dp0"
if exist "%~dp0node.exe" (
  echo Iniciando agente local de escaneo en http://127.0.0.1:3000 ...
  "%~dp0node.exe" server.js 2>"%~dp0error-inicio.log"
  if errorlevel 1 (
    echo.
    echo El agente no pudo iniciarse. Revise error-inicio.log
    type "%~dp0error-inicio.log"
    pause
    exit /b 1
  )
  exit /b 0
)
where node.exe >nul 2>&1
if errorlevel 1 (
  echo No se encontro Node.js en esta computadora.
  echo Instale Node.js 20 o superior y vuelva a ejecutar este archivo.
  pause
  exit /b 1
)
echo Iniciando agente local de escaneo en http://127.0.0.1:3000 ...
node server.js
if errorlevel 1 (
  echo El agente no pudo iniciarse.
  pause
)
