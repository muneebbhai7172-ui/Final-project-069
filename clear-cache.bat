@echo off
echo ========================================
echo  CLEARING CACHE - Muneeb Drug House
echo ========================================
echo.
echo Step 1: Stopping Apache...
cd C:\xampp
.\apache_stop.bat 2>nul
timeout /t 2 /nobreak >nul

echo Step 2: Starting Apache...
.\apache_start.bat
timeout /t 3 /nobreak >nul

echo.
echo ========================================
echo  Server Restarted Successfully!
echo ========================================
echo.
echo IMPORTANT: Clear your browser cache:
echo.
echo Chrome/Edge:
echo   Press Ctrl + Shift + Delete
echo   Select "Cached images and files"
echo   Click "Clear data"
echo.
echo OR use Hard Refresh:
echo   Press Ctrl + F5 (Windows)
echo   Press Ctrl + Shift + R (Alternative)
echo.
echo Firefox:
echo   Press Ctrl + Shift + Delete
echo   Select "Cache"
echo   Click "Clear Now"
echo.
echo ========================================
echo.
echo Your application is now ready with fresh cache!
echo Visit: http://localhost/waqar/modules/shop/cart.html
echo.
pause
