# Database Update Automation Script
# This script exports the database and commits it automatically

param(
    [string]$CommitMessage = "",
    [switch]$Push = $false
)

Write-Host "🔄 Database Update Automation" -ForegroundColor Cyan
Write-Host "================================" -ForegroundColor Cyan

# Step 1: Export the database
Write-Host "📤 Step 1: Exporting database..." -ForegroundColor Yellow
& "$PSScriptRoot\export_database.ps1" -DbHost "localhost"

if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ Database export failed. Stopping automation." -ForegroundColor Red
    exit 1
}

# Step 2: Generate commit message if not provided
if (-not $CommitMessage) {
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $CommitMessage = "Update database dump - $timestamp"
}

# Step 3: Commit the changes
Write-Host "📝 Step 2: Committing changes..." -ForegroundColor Yellow
git commit -m $CommitMessage

if ($LASTEXITCODE -ne 0) {
    Write-Host "⚠️  Commit failed. You may need to commit manually." -ForegroundColor Yellow
    Write-Host "💡 Run: git commit -m `"$CommitMessage`"" -ForegroundColor Cyan
} else {
    Write-Host "✅ Changes committed successfully!" -ForegroundColor Green
}

# Step 4: Push if requested
if ($Push) {
    Write-Host "📤 Step 3: Pushing to remote..." -ForegroundColor Yellow
    git push
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✅ Changes pushed successfully!" -ForegroundColor Green
    } else {
        Write-Host "⚠️  Push failed. You may need to push manually." -ForegroundColor Yellow
        Write-Host "💡 Run: git push" -ForegroundColor Cyan
    }
}

Write-Host ""
Write-Host "🎉 Database update automation completed!" -ForegroundColor Green
if (-not $Push) {
    Write-Host "💡 Run 'git push' to share with your team" -ForegroundColor Cyan
}
