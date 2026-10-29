@echo off
title Deploy to Live Server (rentryvisa.immigration.gov.ng)
echo ========================================================
echo  Deploying to rentryvisa.immigration.gov.ng via SSH...
echo ========================================================
cd /d "c:\xampp\htdocs\rentryvisas"

echo [1/3] Adding changes and committing...
git add .
git commit -m "Manual deploy: %date% %time%"

echo [2/3] Pushing to GitHub (origin/main)...
git push origin main

echo [3/3] Syncing live server via SSH...
ssh -o ConnectTimeout=10 -p 22 immigrat@server3.nairahost.ng "cd domains/rentryvisa.immigration.gov.ng/public_html && git fetch origin && git reset --hard origin/main"

echo ========================================================
echo  DEPLOYMENT COMPLETE! Check: https://rentryvisa.immigration.gov.ng/
echo ========================================================
pause
