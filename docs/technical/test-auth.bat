@echo off
:: ─────────────────────────────────────────────────────────────────
:: test-auth.bat
:: Tests the Sarah AI Server credential validation chain.
:: Calls /client/auth-test and shows which step passes or fails.
::
:: USAGE:
::   1. Fill in the variables below.
::   2. Double-click or run from command prompt.
:: ─────────────────────────────────────────────────────────────────

:: ── Config ────────────────────────────────────────────────────────
set SERVER_URL=http://sarah-server.local/wp-json/sarah-ai-server/v1
set PLATFORM_KEY=www.BurstNET.com.au
set ACCOUNT_KEY=1b646204ea191017201822bdc6abbea49b50dad08dc6868c54e2fa8ddccea50f
set SITE_KEY=885a974ec2f8ccd8aa6280917d6106eced234220e7576b862ff35f1d1d687755
:: ─────────────────────────────────────────────────────────────────

echo.
echo Sarah AI - Auth Test
echo ════════════════════════════════════════
echo Server:      %SERVER_URL%
echo Account Key: %ACCOUNT_KEY%
echo Site Key:    %SITE_KEY%
echo.

curl -s -X POST "%SERVER_URL%/client/auth-test" ^
  -H "Content-Type: application/json" ^
  -H "X-Sarah-Platform-Key: %PLATFORM_KEY%" ^
  -d "{\"account_key\":\"%ACCOUNT_KEY%\",\"site_key\":\"%SITE_KEY%\"}"

echo.
echo.
pause
