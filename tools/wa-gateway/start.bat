@echo off
chcp 65001 >nul
cd /d "%~dp0"
title Makana WhatsApp

where node >nul 2>nul
if errorlevel 1 (
  echo.
  echo  Node.js is not installed.
  echo  Download the LTS version from https://nodejs.org then run this file again.
  echo.
  start https://nodejs.org
  pause
  exit /b 1
)

if not exist node_modules (
  echo Installing for the first time, please wait...
  call npm install --omit=dev --no-audit --no-fund
  if errorlevel 1 (
    echo Installation failed. Check the internet connection and try again.
    pause
    exit /b 1
  )
)

:loop
node index.js
echo.
echo The program stopped. Restarting in 10 seconds... (close this window to stop it)
timeout /t 10 /nobreak >nul
goto loop
