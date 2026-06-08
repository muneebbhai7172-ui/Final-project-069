@echo off
echo ========================================
echo   Muneeb Drug House - Laravel Server
echo ========================================
echo.
echo Starting Laravel development server...
echo.
echo The application will be available at:
echo http://localhost:8000
echo.
echo Press Ctrl+C to stop the server
echo ========================================
echo.

cd /d "%~dp0"
php artisan serve

pause
