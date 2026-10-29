# Git Auto-Sync Script for Re-Entry Visa Application
# This script monitors for local changes, automatically commits, and pushes to GitHub.

$IntervalSeconds = 30
$RepoDir = "c:\xampp\htdocs\rentryvisas"

Set-Location $RepoDir
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host " Git Auto-Sync is Running in Background " -ForegroundColor Green
Write-Host " Monitoring: $RepoDir" -ForegroundColor Yellow
Write-Host " Check Interval: $IntervalSeconds seconds" -ForegroundColor Yellow
Write-Host " Press Ctrl+C to stop" -ForegroundColor Gray
Write-Host "=========================================" -ForegroundColor Cyan

while ($true) {
    try {
        # Check for uncommitted changes
        $status = (git status --porcelain 2>&1) | Out-String
        $hasChanges = -not [string]::IsNullOrWhiteSpace($status.Trim())

        # Check if local commits need to be pushed to remote
        $ahead = (git log "@{u}..HEAD" --oneline 2>&1) | Out-String
        $needsPush = -not [string]::IsNullOrWhiteSpace($ahead.Trim()) -and -not ($ahead -match "fatal:|error:")

        if ($hasChanges) {
            $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
            Write-Host "[$timestamp] Changes detected. Auto-committing..." -ForegroundColor Yellow
            git add .
            git commit -m "Auto-save: $timestamp"
            $needsPush = $true
        }

        if ($needsPush) {
            $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
            Write-Host "[$timestamp] Pushing to GitHub (origin/main)..." -ForegroundColor Cyan
            $pushResult = (git push origin main 2>&1) | Out-String
            if ($LASTEXITCODE -eq 0) {
                Write-Host "[$timestamp] Successfully pushed to GitHub!" -ForegroundColor Green
            } else {
                Write-Host "[$timestamp] Push status: $pushResult" -ForegroundColor DarkYellow
            }
        }
    }
    catch {
        Write-Host "Error during auto-sync: $_" -ForegroundColor Red
    }

    Start-Sleep -Seconds $IntervalSeconds
}
