@echo off
cd /d %~dp0

where node >nul 2>nul
if %errorlevel% neq 0 (
    echo Node.js is not installed.
    echo Open Terminal as Administrator and run:
    echo winget install OpenJS.NodeJS.LTS
    pause
    exit /b
)

node scripts\menu.js
pause