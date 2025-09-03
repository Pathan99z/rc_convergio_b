@echo off
REM Database Export Script for rc_convergio_s
REM This script exports the MySQL database to avoid migration issues

setlocal enabledelayedexpansion

REM Default values
set DATABASE_NAME=rc_convergio_s
set USERNAME=root
set PASSWORD=
set HOST=localhost
set PORT=3306
set OUTPUT_FILE=db_dump.sql

REM Parse command line arguments
:parse_args
if "%~1"=="" goto :main
if /i "%~1"=="--database" (
    set DATABASE_NAME=%~2
    shift
    shift
    goto :parse_args
)
if /i "%~1"=="--username" (
    set USERNAME=%~2
    shift
    shift
    goto :parse_args
)
if /i "%~1"=="--password" (
    set PASSWORD=%~2
    shift
    shift
    goto :parse_args
)
if /i "%~1"=="--host" (
    set HOST=%~2
    shift
    shift
    goto :parse_args
)
if /i "%~1"=="--port" (
    set PORT=%~2
    shift
    shift
    goto :parse_args
)
if /i "%~1"=="--output" (
    set OUTPUT_FILE=%~2
    shift
    shift
    goto :parse_args
)
if /i "%~1"=="--help" (
    echo Usage: %0 [OPTIONS]
    echo Options:
    echo   --database NAME    Database name ^(default: rc_convergio_s^)
    echo   --username NAME    MySQL username ^(default: root^)
    echo   --password PASS    MySQL password ^(default: empty^)
    echo   --host HOST        MySQL host ^(default: localhost^)
    echo   --port PORT        MySQL port ^(default: 3306^)
    echo   --output FILE      Output file name ^(default: db_dump.sql^)
    echo   --help             Show this help message
    exit /b 0
)
shift
goto :parse_args

:main
echo 🚀 Starting database export process...

REM Check if mysqldump is available
mysqldump --version >nul 2>&1
if errorlevel 1 (
    echo ❌ mysqldump not found. Please ensure MySQL is installed and in your PATH.
    echo 💡 If using XAMPP, try: C:\xampp\mysql\bin\mysqldump.exe
    exit /b 1
)

echo ✅ mysqldump found

REM Build the mysqldump command
set MYSQLDUMP_CMD=mysqldump
set MYSQLDUMP_ARGS=--host=%HOST% --port=%PORT% --user=%USERNAME% --single-transaction --routines --triggers --events --add-drop-database --create-options --comments --dump-date --complete-insert --extended-insert --set-charset --default-character-set=utf8mb4 %DATABASE_NAME%

REM Add password if provided
if not "%PASSWORD%"=="" (
    set MYSQLDUMP_ARGS=%MYSQLDUMP_ARGS% --password=%PASSWORD%
)

echo 📋 Executing: %MYSQLDUMP_CMD% %MYSQLDUMP_ARGS%
echo 📁 Output will be saved to: database\%OUTPUT_FILE%

REM Execute the command
%MYSQLDUMP_CMD% %MYSQLDUMP_ARGS% > database\%OUTPUT_FILE% 2>nul

if errorlevel 1 (
    echo ❌ Database export failed
    exit /b 1
)

REM Get file size
for %%A in (database\%OUTPUT_FILE%) do set FILE_SIZE=%%~zA
set /a FILE_SIZE_MB=%FILE_SIZE% / 1024 / 1024

echo ✅ Database export completed successfully!
echo 📊 File size: %FILE_SIZE_MB% MB
echo 📁 Location: database\%OUTPUT_FILE%

REM Add to git
echo 🔄 Adding to git...
git add database\%OUTPUT_FILE%
if errorlevel 1 (
    echo ⚠️  Git add failed. You may need to commit manually.
) else (
    echo ✅ File added to git staging area
    echo 💡 Run 'git commit -m "Update database dump"' to commit the changes
)

echo.
echo 🎉 Process completed! Your team can now:
echo    1. Pull the latest code
echo    2. Go to phpMyAdmin
echo    3. Import database\%OUTPUT_FILE% into %DATABASE_NAME%
echo    4. Skip running php artisan migrate

endlocal
