@echo off
setlocal enabledelayedexpansion

:: ─────────────────────────────────────────────────────────────────────────────
:: Sarah AI — Phase 4.4.1 Chat Runtime Test
:: Usage: double-click or run from cmd
:: ─────────────────────────────────────────────────────────────────────────────

:: ── CONFIGURATION ────────────────────────────────────────────────────────────
set BASE_URL=http://sarah-server.local/wp-json/sarah-ai-server/v1
set ACCOUNT_KEY=3e928cf79fc83453cd8447ef98ba395f0c8de38a11a7a957827f5ba6e8e1466d
set SITE_KEY=0741fc0800b9ed96152213226f69dabdad2b6527cbc70af05ea88f714b5a3da3
set PLATFORM_KEY=www.BurstNET.com.au
set MESSAGE_1=Hello! What services do you offer?
set MESSAGE_2=Can you tell me more about pricing?
:: ─────────────────────────────────────────────────────────────────────────────

:: Output folder = same folder as this script
set SCRIPT_DIR=%~dp0
set OUT=%SCRIPT_DIR%

:: Log file with timestamp
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value') do set DT=%%I
set TIMESTAMP=%DT:~0,4%-%DT:~4,2%-%DT:~6,2%_%DT:~8,2%-%DT:~10,2%-%DT:~12,2%
set LOGFILE=%OUT%test-chat-%TIMESTAMP%.log

echo. > "%LOGFILE%"

call :log "========================================"
call :log " Sarah AI — Chat Runtime Test"
call :log " %DATE% %TIME%"
call :log "========================================"
call :log " BASE_URL   : %BASE_URL%"
call :log " ACCOUNT_KEY: %ACCOUNT_KEY%"
call :log " SITE_KEY   : %SITE_KEY%"
call :log "========================================"
call :log ""

:: ── STEP 1: New Session ───────────────────────────────────────────────────────
call :log "[STEP 1] Starting new session..."
echo [STEP 1] Starting new session...

curl -s -X POST "%BASE_URL%/chat" ^
  -H "Content-Type: application/json" ^
  -d "{\"account_key\":\"%ACCOUNT_KEY%\",\"site_key\":\"%SITE_KEY%\",\"message\":\"%MESSAGE_1%\"}" ^
  -o "%OUT%step1_response.json"

if not exist "%OUT%step1_response.json" (
    call :log "[ERROR] Step 1 failed — no response file created."
    echo [ERROR] Step 1 failed — no response file created.
    goto :done
)

:: Check for empty response
for %%A in ("%OUT%step1_response.json") do set SIZE=%%~zA
if %SIZE%==0 (
    call :log "[ERROR] Step 1 response is empty."
    echo [ERROR] Step 1 response is empty.
    goto :done
)

call :log "[STEP 1] Response saved to step1_response.json"
call :log "[STEP 1] Raw response:"
call :logfile "%OUT%step1_response.json"

:: Extract session_uuid using PowerShell
for /f "delims=" %%G in ('powershell -NoProfile -Command ^
    "try { $j = Get-Content '%OUT%step1_response.json' -Raw | ConvertFrom-Json; $j.session_uuid } catch { '' }"') do (
    set SESSION_UUID=%%G
)

if "!SESSION_UUID!"=="" (
    call :log "[ERROR] Could not extract session_uuid from Step 1 response."
    echo [ERROR] Could not extract session_uuid. Check step1_response.json
    goto :done
)

call :log "[STEP 1] session_uuid = !SESSION_UUID!"
echo [STEP 1] session_uuid = !SESSION_UUID!
call :log ""

:: ── STEP 2: Continue Session ──────────────────────────────────────────────────
call :log "[STEP 2] Continuing session..."
echo [STEP 2] Continuing session...

curl -s -X POST "%BASE_URL%/chat" ^
  -H "Content-Type: application/json" ^
  -d "{\"account_key\":\"%ACCOUNT_KEY%\",\"site_key\":\"%SITE_KEY%\",\"message\":\"%MESSAGE_2%\",\"session_uuid\":\"!SESSION_UUID!\"}" ^
  -o "%OUT%step2_response.json"

if not exist "%OUT%step2_response.json" (
    call :log "[ERROR] Step 2 failed — no response file created."
    echo [ERROR] Step 2 failed.
    goto :done
)

call :log "[STEP 2] Response saved to step2_response.json"
call :log "[STEP 2] Raw response:"
call :logfile "%OUT%step2_response.json"
call :log ""

:: ── STEP 3: Get Session ───────────────────────────────────────────────────────
call :log "[STEP 3] Fetching session..."
echo [STEP 3] Fetching session...

curl -s -X GET "%BASE_URL%/sessions/!SESSION_UUID!?account_key=%ACCOUNT_KEY%&site_key=%SITE_KEY%" ^
  -H "X-Sarah-Platform-Key: %PLATFORM_KEY%" ^
  -o "%OUT%session.json"

call :log "[STEP 3] Response saved to session.json"
call :log "[STEP 3] Raw response:"
call :logfile "%OUT%session.json"
call :log ""

:: ── STEP 4: Get Messages ──────────────────────────────────────────────────────
call :log "[STEP 4] Fetching messages..."
echo [STEP 4] Fetching messages...

curl -s -X GET "%BASE_URL%/sessions/!SESSION_UUID!/messages?account_key=%ACCOUNT_KEY%&site_key=%SITE_KEY%" ^
  -H "X-Sarah-Platform-Key: %PLATFORM_KEY%" ^
  -o "%OUT%messages.json"

call :log "[STEP 4] Response saved to messages.json"
call :log "[STEP 4] Raw response:"
call :logfile "%OUT%messages.json"
call :log ""

:: ── SUMMARY ──────────────────────────────────────────────────────────────────
call :log "========================================"
call :log " SUMMARY"
call :log "========================================"
call :log " session_uuid : !SESSION_UUID!"

for /f "delims=" %%G in ('powershell -NoProfile -Command ^
    "try { $j = Get-Content '%OUT%step1_response.json' -Raw | ConvertFrom-Json; if ($j.success) { 'OK' } else { 'FAIL: ' + $j.message } } catch { 'PARSE ERROR' }"') do (
    call :log " Step 1 /chat (new)     : %%G"
    echo  Step 1 /chat (new)     : %%G
)

for /f "delims=" %%G in ('powershell -NoProfile -Command ^
    "try { $j = Get-Content '%OUT%step2_response.json' -Raw | ConvertFrom-Json; if ($j.success) { 'OK' } else { 'FAIL: ' + $j.message } } catch { 'PARSE ERROR' }"') do (
    call :log " Step 2 /chat (continue): %%G"
    echo  Step 2 /chat (continue): %%G
)

for /f "delims=" %%G in ('powershell -NoProfile -Command ^
    "try { $j = Get-Content '%OUT%session.json' -Raw | ConvertFrom-Json; if ($j.success) { 'OK — status: ' + $j.data.status } else { 'FAIL: ' + $j.message } } catch { 'PARSE ERROR' }"') do (
    call :log " Step 3 GET /sessions   : %%G"
    echo  Step 3 GET /sessions   : %%G
)

for /f "delims=" %%G in ('powershell -NoProfile -Command ^
    "try { $j = Get-Content '%OUT%messages.json' -Raw | ConvertFrom-Json; if ($j.success) { 'OK — ' + $j.data.Count + ' messages' } else { 'FAIL: ' + $j.message } } catch { 'PARSE ERROR' }"') do (
    call :log " Step 4 GET /messages   : %%G"
    echo  Step 4 GET /messages   : %%G
)

call :log "========================================"
call :log " Log file: %LOGFILE%"
call :log "========================================"

echo.
echo Log saved to: %LOGFILE%
echo.

:done
echo.
echo Done. Press any key to exit.
pause > nul
exit /b 0

:: ── Helpers ───────────────────────────────────────────────────────────────────
:log
echo %~1 >> "%LOGFILE%"
exit /b 0

:logfile
for /f "usebackq delims=" %%L in ("%~1") do (
    echo %%L >> "%LOGFILE%"
)
exit /b 0
