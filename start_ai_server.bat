@echo off
title Hackathon AI Prediction Server
color 0A
echo.
echo  ============================================================
echo   Hackathon Career Readiness - AI Prediction Server
echo  ============================================================
echo.
echo  Starting Python Flask API on http://127.0.0.1:5001
echo  Keep this window OPEN while using the web app.
echo.
cd /d "%~dp0"
python predict_api.py
if %errorlevel% neq 0 (
    if exist ".venv\Scripts\python.exe" (.venv\Scripts\python.exe predict_api.py) else if exist "venv\Scripts\python.exe" (venv\Scripts\python.exe predict_api.py)
)
pause
