@echo off
:: ============================================================
:: SecureCAT — Task Scheduler Setup
:: Run this script as Administrator (right-click → Run as admin)
:: Creates a Windows Scheduled Task that runs Laravel's
:: scheduler every minute, surviving reboots automatically.
:: ============================================================

echo.
echo   SecureCAT — Task Scheduler Setup
echo   =================================
echo.

:: Check for admin privileges
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo   ERROR: This script must be run as Administrator.
    echo   Right-click setup-scheduler.bat and select "Run as administrator"
    echo.
    pause
    exit /b 1
)

:: Detect PHP path
for /f "tokens=*" %%i in ('where php 2^>nul') do set PHP_PATH=%%i
if "%PHP_PATH%"=="" (
    echo   ERROR: PHP not found in PATH.
    echo   Make sure Laragon is running and PHP is in your system PATH.
    pause
    exit /b 1
)

:: Use the script's directory as the project path
set PROJECT_PATH=%~dp0

echo   PHP:     %PHP_PATH%
echo   Project: %PROJECT_PATH%
echo.

:: Delete existing task if it exists (for re-runs)
schtasks /delete /tn "SecureCAT Laravel Scheduler" /f >nul 2>&1

:: Create the scheduled task
:: Runs every minute, starts on boot, runs whether user is logged in or not
schtasks /create ^
    /tn "SecureCAT Laravel Scheduler" ^
    /tr "\"%PHP_PATH%\" \"%PROJECT_PATH%artisan\" schedule:run --no-ansi" ^
    /sc minute /mo 1 ^
    /f ^
    /rl highest

if %errorlevel% equ 0 (
    echo.
    echo   +---------------------------------------+
    echo   ^|  Task created successfully!           ^|
    echo   ^|                                       ^|
    echo   ^|  The scheduler will now run every     ^|
    echo   ^|  minute, even after system restarts.  ^|
    echo   +---------------------------------------+
    echo.
    echo   Scheduled tasks:
    "%PHP_PATH%" "%PROJECT_PATH%artisan" schedule:list
    echo.
) else (
    echo.
    echo   ERROR: Failed to create scheduled task.
    echo   Try running this script as Administrator.
    echo.
)

pause
