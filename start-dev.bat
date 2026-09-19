@echo off
setlocal EnableExtensions EnableDelayedExpansion
title TeamCollab - Launcher
cd /d "%~dp0"

echo.
echo  ==================================================
echo   TeamCollab - local development launcher
echo  ==================================================
echo.

:: ------------------------------------------------------------------
:: Tooling
:: ------------------------------------------------------------------
set "PHP=php"
where php >nul 2>nul
if errorlevel 1 (
    if exist "C:\xampp6\php\php.exe" (
        set "PHP=C:\xampp6\php\php.exe"
    ) else (
        echo [ERROR] php not found on PATH and C:\xampp6\php\php.exe is missing.
        goto :fail
    )
)
where npm >nul 2>nul || (echo [ERROR] npm not found on PATH. Install Node.js first. & goto :fail)

:: ------------------------------------------------------------------
:: Project files
:: ------------------------------------------------------------------
if not exist ".env" (
    echo [ERROR] .env is missing. Run:  copy .env.example .env  ^&^&  php artisan key:generate
    echo         then set DB_*, REVERB_* and re-run this script.
    goto :fail
)

if not exist "vendor\autoload.php" (
    echo [....] vendor\ missing - running composer install
    where composer >nul 2>nul || (echo [ERROR] composer not found on PATH. & goto :fail)
    call composer install || goto :fail
)

if not exist "node_modules" (
    echo [....] node_modules\ missing - running npm install
    call npm install --legacy-peer-deps || goto :fail
)

:: ------------------------------------------------------------------
:: Ports - read from .env so serve/reverb match APP_URL and REVERB_PORT
:: ------------------------------------------------------------------
set "APP_URL="
set "REVERB_PORT="
for /f "usebackq tokens=1* delims==" %%a in (`findstr /B /C:"APP_URL=" .env`) do set "APP_URL=%%b"
for /f "usebackq tokens=1* delims==" %%a in (`findstr /B /C:"REVERB_PORT=" .env`) do set "REVERB_PORT=%%b"
set "APP_URL=%APP_URL:"=%"
set "REVERB_PORT=%REVERB_PORT:"=%"

:: APP_URL like http://localhost:8001 -> APP_PORT=8001 (default 8000)
set "APP_PORT=8000"
set "HOSTPART=%APP_URL:*//=%"
for /f "tokens=2 delims=:" %%p in ("%HOSTPART%") do set "APP_PORT=%%p"
if "%APP_PORT%"=="" set "APP_PORT=8000"
if "%REVERB_PORT%"=="" set "REVERB_PORT=8080"

:: ------------------------------------------------------------------
:: MySQL (XAMPP)
:: ------------------------------------------------------------------
netstat -an | findstr /R /C:":3306 .*LISTENING" >nul
if errorlevel 1 (
    echo [....] MySQL is not running - starting XAMPP MySQL
    if exist "C:\xampp6\mysql_start.bat" (
        start "TeamCollab - MySQL" /min cmd /c "C:\xampp6\mysql_start.bat"
        ping -n 6 127.0.0.1 >nul
    ) else (
        echo [WARN] Could not find C:\xampp6\mysql_start.bat - start MySQL from the XAMPP Control Panel.
    )
) else (
    echo [ ok ] MySQL is running on :3306
)

:: ------------------------------------------------------------------
:: Already running? (a previous start-dev without stop-dev)
:: ------------------------------------------------------------------
netstat -an | findstr /R /C:":%APP_PORT% .*LISTENING" >nul && (
    echo [WARN] Port %APP_PORT% is already in use. Run stop-dev.bat first if TeamCollab is already running.
)

:: A stale Vite hot-file makes the app load assets from a dead dev server.
if exist "public\hot" del /q "public\hot"

:: ------------------------------------------------------------------
:: Start the four processes, each in its own window.
:: The leading `title TeamCollab - ...` names the window AND leaves a
:: marker in the process command line that stop-dev.bat searches for.
:: ------------------------------------------------------------------
echo [....] Starting Laravel  (http://localhost:%APP_PORT%)
start "TeamCollab - Laravel" cmd /k "title TeamCollab - Laravel :%APP_PORT% & %PHP% artisan serve --host=127.0.0.1 --port=%APP_PORT%"

echo [....] Starting Reverb   (ws://localhost:%REVERB_PORT%)
start "TeamCollab - Reverb" cmd /k "title TeamCollab - Reverb :%REVERB_PORT% & %PHP% artisan reverb:start --port=%REVERB_PORT%"

echo [....] Starting queue worker
start "TeamCollab - Queue" cmd /k "title TeamCollab - Queue & %PHP% artisan queue:work --queue=default,notifications,activity,scheduled-messages --tries=3 --timeout=120"

echo [....] Starting Vite
start "TeamCollab - Vite" cmd /k "title TeamCollab - Vite & npm run dev"

:: ------------------------------------------------------------------
:: Wait for Laravel, then open the browser
:: ------------------------------------------------------------------
set /a tries=0
:waitloop
set /a tries+=1
netstat -an | findstr /R /C:":%APP_PORT% .*LISTENING" >nul && goto :ready
if %tries% geq 30 (
    echo [WARN] Laravel did not come up on :%APP_PORT% within 30s - check the "TeamCollab - Laravel" window.
    goto :summary
)
ping -n 2 127.0.0.1 >nul
goto :waitloop

:ready
start "" "http://localhost:%APP_PORT%"

:summary
echo.
echo  --------------------------------------------------
echo   Central app : http://localhost:%APP_PORT%
echo   Workspace   : http://{workspace}.localhost:%APP_PORT%
echo   Reverb      : ws://localhost:%REVERB_PORT%
echo   Windows     : Laravel / Reverb / Queue / Vite
echo.
echo   To stop everything: stop-dev.bat
echo  --------------------------------------------------
echo.
echo  The servers keep running after this window closes.
pause
exit /b 0

:fail
echo.
echo  Launch aborted.
pause
exit /b 1
