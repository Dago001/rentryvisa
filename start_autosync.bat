@echo off
title Git Auto-Sync (rentryvisa)
echo Starting Git Auto-Sync...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0auto_git_sync.ps1"
pause
