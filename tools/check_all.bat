@echo off
setlocal EnableExtensions
set "PHP=D:\Windows\xampp\php\php.exe"
set "ROOT=%~dp0.."
cd /d "%ROOT%"

if not exist "%PHP%" (
  echo ERROR: PHP not found at %PHP%
  exit /b 1
)

echo Komodo check_all.bat — repo root %ROOT%
echo.

call "%ROOT%\tools\lint_all.bat"
if errorlevel 1 exit /b 1

"%PHP%" "%ROOT%\tools\komodo_smoke.php"
if errorlevel 1 exit /b 1

"%PHP%" "%ROOT%\tools\komodo_db_check.php"
if errorlevel 1 exit /b 1

"%PHP%" "%ROOT%\tools\komodo_security_scan.php"
if errorlevel 1 exit /b 1

echo.
echo All checks completed.
exit /b 0
