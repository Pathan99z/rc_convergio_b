# Database Export Script for rc_convergio_s
# This script exports the MySQL database to avoid migration issues

param(
    [string]$DatabaseName = "rc_convergio_s",
    [string]$Username = "root",
    [string]$Password = "",
    [string]$DbHost = "localhost",
    [string]$Port = "3306",
    [string]$OutputFile = "db_dump.sql"
)

Write-Host "🚀 Starting database export process..." -ForegroundColor Green

# Check if mysqldump is available
$mysqldumpCmd = "mysqldump"
try {
    $mysqldumpVersion = & $mysqldumpCmd --version 2>$null
    if ($LASTEXITCODE -ne 0) {
        # Try XAMPP path
        $xamppMysqldump = "C:\xampp\mysql\bin\mysqldump.exe"
        if (Test-Path $xamppMysqldump) {
            $mysqldumpCmd = $xamppMysqldump
            $mysqldumpVersion = & $mysqldumpCmd --version 2>$null
            Write-Host "✅ mysqldump found (XAMPP): $mysqldumpVersion" -ForegroundColor Green
        } else {
            Write-Host "❌ mysqldump not found. Please ensure MySQL is installed and in your PATH." -ForegroundColor Red
            Write-Host "💡 If using XAMPP, try: C:\xampp\mysql\bin\mysqldump.exe" -ForegroundColor Yellow
            exit 1
        }
    } else {
        Write-Host "✅ mysqldump found: $mysqldumpVersion" -ForegroundColor Green
    }
} catch {
    Write-Host "❌ mysqldump not found. Please ensure MySQL is installed and in your PATH." -ForegroundColor Red
    exit 1
}

# Build the mysqldump command
$mysqldumpArgs = @(
    "--host=$DbHost",
    "--port=$Port",
    "--user=$Username"
)

if ($Password) {
    $mysqldumpArgs += "--password=$Password"
}

$mysqldumpArgs += @(
    "--single-transaction",
    "--routines",
    "--triggers",
    "--events",
    "--add-drop-database",
    "--create-options",
    "--comments",
    "--dump-date",
    "--complete-insert",
    "--extended-insert",
    "--set-charset",
    "--default-character-set=utf8mb4",
    $DatabaseName
)

# Create the full command
$fullCommand = "$mysqldumpCmd $($mysqldumpArgs -join ' ')"

Write-Host "📋 Executing: $fullCommand" -ForegroundColor Cyan
Write-Host "📁 Output will be saved to: database/$OutputFile" -ForegroundColor Cyan

# Execute the command and redirect output to file
try {
    & $mysqldumpCmd @mysqldumpArgs | Out-File -FilePath "database/$OutputFile" -Encoding UTF8
    
    if ($LASTEXITCODE -eq 0) {
        $fileSize = (Get-Item "database/$OutputFile").Length
        $fileSizeMB = [math]::Round($fileSize / 1MB, 2)
        
        Write-Host "✅ Database export completed successfully!" -ForegroundColor Green
        Write-Host "📊 File size: $fileSizeMB MB" -ForegroundColor Green
        Write-Host "📁 Location: database/$OutputFile" -ForegroundColor Green
        
        # Add to git
        Write-Host "🔄 Adding to git..." -ForegroundColor Yellow
        git add "database/$OutputFile"
        
        if ($LASTEXITCODE -eq 0) {
            Write-Host "✅ File added to git staging area" -ForegroundColor Green
            Write-Host "💡 Run 'git commit -m \"Update database dump\"' to commit the changes" -ForegroundColor Yellow
        } else {
            Write-Host "⚠️  Git add failed. You may need to commit manually." -ForegroundColor Yellow
        }
        
    } else {
        Write-Host "❌ Database export failed with exit code: $LASTEXITCODE" -ForegroundColor Red
        exit 1
    }
} catch {
    Write-Host "❌ Error during database export: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

Write-Host ""
Write-Host "🎉 Process completed! Your team can now:" -ForegroundColor Green
Write-Host "   1. Pull the latest code" -ForegroundColor White
Write-Host "   2. Go to phpMyAdmin" -ForegroundColor White
Write-Host "   3. Import database/$OutputFile into $DatabaseName" -ForegroundColor White
Write-Host "   4. Skip running php artisan migrate" -ForegroundColor White
