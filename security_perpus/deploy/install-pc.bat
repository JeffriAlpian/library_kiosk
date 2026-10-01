@echo off
:: Jalankan sebagai Administrator di setiap PC lab.
:: Struktur folder (di USB / network share):
::   install-pc.bat
::   LibraryKiosk.exe
::   env\PC-01.env, env\PC-02.env, ...   (nama file = nama komputer)
setlocal
set "SRC=%~dp0"
set "DST=C:\LibraryKiosk"
set "ENVFILE=%SRC%env\%COMPUTERNAME%.env"

if not exist "%SRC%LibraryKiosk.exe" (
  echo [ERROR] LibraryKiosk.exe tidak ditemukan di %SRC%
  pause
  exit /b 1
)
if not exist "%ENVFILE%" (
  echo [ERROR] File env\%COMPUTERNAME%.env tidak ditemukan.
  echo Pastikan nama komputer sama dengan pc_name di CSV / server.
  pause
  exit /b 1
)

mkdir "%DST%" 2>nul
taskkill /f /im LibraryKiosk.exe >nul 2>&1
copy /Y "%SRC%LibraryKiosk.exe" "%DST%\LibraryKiosk.exe" >nul
copy /Y "%ENVFILE%" "%DST%\.env" >nul

:: Auto-start untuk semua akun saat login
reg add "HKLM\Software\Microsoft\Windows\CurrentVersion\Run" /v LibraryKiosk /t REG_SZ /d "\"%DST%\LibraryKiosk.exe\"" /f >nul

:: Akun non-admin hanya boleh membaca dan menjalankan
icacls "%DST%" /inheritance:r /grant:r "Administrators:(OI)(CI)F" "SYSTEM:(OI)(CI)F" "Users:(OI)(CI)RX" >nul

echo.
echo Selesai untuk %COMPUTERNAME%. Kiosk aktif pada login berikutnya (sign out / restart).
pause
