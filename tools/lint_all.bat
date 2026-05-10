@echo off
setlocal EnableExtensions EnableDelayedExpansion
set "PHP=D:\Windows\xampp\php\php.exe"
set "ROOT=%~dp0.."
set "ERR=0"
cd /d "%ROOT%"

if not exist "%PHP%" (
  echo ERROR: PHP not found at %PHP%
  exit /b 1
)

echo Komodo lint_all.bat — repo root %ROOT%
echo.

for %%F in (
  "%ROOT%\index.php"
  "%ROOT%\public\index.php"
  "%ROOT%\app\config\pages.php"
  "%ROOT%\app\config\database.php"
  "%ROOT%\app\config\local.example.php"
  "%ROOT%\app\lib\dashboard_queries.php"
  "%ROOT%\app\lib\label_helpers.php"
  "%ROOT%\app\lib\view_helpers.php"
  "%ROOT%\app\lib\request_helpers.php"
  "%ROOT%\app\lib\web_error_handlers.php"
  "%ROOT%\app\lib\page_context.php"
  "%ROOT%\app\lib\event_queries.php"
  "%ROOT%\app\lib\company_queries.php"
  "%ROOT%\app\lib\market_data_queries.php"
  "%ROOT%\app\lib\dashboard_context.php"
  "%ROOT%\app\partials\layout.php"
  "%ROOT%\app\partials\sidebar.php"
  "%ROOT%\app\partials\footer.php"
  "%ROOT%\app\partials\market_import_triage_table.php"
  "%ROOT%\app\partials\market_security_queue_table.php"
  "%ROOT%\app\partials\price_import_readiness_section.php"
  "%ROOT%\app\partials\market_import_triage_next_batch_list.php"
  "%ROOT%\app\pages\dashboard.php"
  "%ROOT%\app\pages\companies.php"
  "%ROOT%\app\pages\company.php"
  "%ROOT%\app\pages\dataset.php"
  "%ROOT%\app\pages\events.php"
  "%ROOT%\app\pages\market-data.php"
  "%ROOT%\app\pages\price-import-queue.php"
  "%ROOT%\app\pages\price-coverage.php"
  "%ROOT%\app\pages\price-audit.php"
  "%ROOT%\app\pages\research-quality.php"
  "%ROOT%\app\pages\data-gaps.php"
  "%ROOT%\app\pages\not-found.php"
  "%ROOT%\tools\komodo_smoke.php"
  "%ROOT%\tools\komodo_css_check.php"
  "%ROOT%\tools\komodo_db_check.php"
  "%ROOT%\tools\komodo_security_scan.php"
  "%ROOT%\tools\import_index_prices.php"
  "%ROOT%\tools\import_security_prices.php"
  "%ROOT%\tools\cleanup_stale_import_csvs.php"
) do (
  "%PHP%" -l "%%~F"
  if errorlevel 1 set ERR=1
)

echo.
if "!ERR!"=="0" (
  echo All lint checks passed.
) else (
  echo One or more lint checks FAILED.
)

exit /b %ERR%
