@echo off
setlocal EnableExtensions
title TeamCollab - Stop
cd /d "%~dp0"

echo.
echo  Stopping TeamCollab dev servers...
echo.

:: start-dev.bat launches every server as
::     cmd /k "title TeamCollab - <name> & <command>"
:: so the marker lives in each cmd.exe's command line. Find those and take
:: down the whole tree (cmd -> php / npm -> node). This does not depend on
:: window titles, so it also works for windows started minimized or hidden.
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$hosts = Get-CimInstance Win32_Process | Where-Object { $_.Name -eq 'cmd.exe' -and $_.CommandLine -like '*title TeamCollab - *' };" ^
  "if (-not $hosts) { Write-Host '[ -- ] nothing from start-dev.bat is running'; exit 0 };" ^
  "foreach ($h in $hosts) { $name = ($h.CommandLine -replace '.*title TeamCollab - ([A-Za-z]+).*', '$1'); & taskkill /PID $h.ProcessId /T /F *> $null; Write-Host ('[ ok ] stopped ' + $name) }"

:: Vite writes public\hot while it runs; left behind, the app would try to
:: load assets from a dev server that is no longer there.
if exist "public\hot" del /q "public\hot" && echo [ ok ] removed public\hot

echo.
echo  MySQL (XAMPP) is left running - stop it from the XAMPP Control Panel if you want.
echo.
pause
exit /b 0
