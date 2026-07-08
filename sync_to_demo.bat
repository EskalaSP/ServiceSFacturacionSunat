@echo off
setlocal enabledelayedexpansion

echo ============================================================
echo  SYNC TO DEMO - API SUNAT (Arquitectura Segura)
echo ============================================================
echo.

set "SRC=C:\laragon\www\plataform-api-sunat"
set "DEST=C:\laragon\www\SUNAT_WEB_DEMO"
set "CORE_DEST=%DEST%\demo-sunat_core"
set "PUBLIC_DEST=%DEST%\demo-sunat"

REM 1. Compilar frontend en la carpeta origen
echo [1/4] Compilando frontend (npm run build) en origen...
cd /d "%SRC%"
call npm run build
if errorlevel 1 (
    echo ERROR: Fallo la compilacion del frontend.
    pause
    exit /b 1
)
echo OK.
echo.

REM 2. Preparar carpetas limpias
echo [2/4] Limpiando carpetas de demo...
if exist "%DEST%" (
    rmdir /s /q "%DEST%"
)
mkdir "%CORE_DEST%"
mkdir "%PUBLIC_DEST%"
echo OK.
echo.

REM 3. Copiar CORE (Backend) excluyendo public y basura
echo [3/4] Copiando archivos del backend a demo-sunat_core...
robocopy "%SRC%" "%CORE_DEST%" /E /XD .git node_modules tests storage\logs public /XF .env .env.* *.bat > nul
echo OK.
echo.

REM 4. Copiar PUBLIC (Frontend) a demo-sunat
echo [4/4] Copiando frontend publico a demo-sunat...
robocopy "%SRC%\public" "%PUBLIC_DEST%" /E /XF hot > nul
echo OK.
echo.

REM 5. Modificar index.php para la nueva ruta (../../demo-sunat_core)
echo [5/5] Ajustando rutas en index.php...
set "INDEX_FILE=%PUBLIC_DEST%\index.php"
powershell -Command "(Get-Content '%INDEX_FILE%') -replace '__DIR__\.''/../storage', '__DIR__''/../../demo-sunat_core/storage' -replace '__DIR__\.''/../vendor', '__DIR__''/../../demo-sunat_core/vendor' -replace '__DIR__\.''/../bootstrap', '__DIR__''/../../demo-sunat_core/bootstrap' | Set-Content '%INDEX_FILE%'"

REM Opcional: Agregar enlace para decirle a Laravel donde esta public
powershell -Command "$content = Get-Content '%INDEX_FILE%'; $content = $content -replace '\\$app->handleRequest\\(', \"`$app->usePublicPath(__DIR__);`n`$app->handleRequest(\"; Set-Content '%INDEX_FILE%' $content"
echo OK.
echo.

REM 6. Instalar dependencias de produccion
echo [Extra] Instalando dependencias PHP de produccion...
cd /d "%CORE_DEST%"
call composer install --no-dev --optimize-autoloader
echo OK.
echo.

REM 7. Generar APP_KEY aleatoria
echo Generando clave de cifrado (APP_KEY) para la API Demo...
for /f "tokens=*" %%a in ('powershell -Command "[Convert]::ToBase64String((1..32 | ForEach-Object { Get-Random -Minimum 0 -Maximum 256 }))"') do set "RAND_KEY=%%a"
echo Clave generada: base64:!RAND_KEY!
echo.

REM 8. Escribir .env definitivo para la API de SUNAT Demo
echo Escribiendo .env definitivo para la API Demo...
(
echo APP_NAME="API SUNAT (DEMO)"
echo APP_ENV=production
echo APP_KEY=base64:!RAND_KEY!
echo APP_DEBUG=false
echo APP_URL=https://demo-sunat.lu-tec.net
echo.
echo DB_CONNECTION=mysql
echo DB_HOST=127.0.0.1
echo DB_PORT=3306
echo DB_DATABASE=u230880442_demo_sunat
echo DB_USERNAME=u230880442_demo_sunat
echo DB_PASSWORD=Demo_sunat1
echo.
echo SESSION_DRIVER=database
echo QUEUE_CONNECTION=database
echo CACHE_STORE=file
echo FILESYSTEM_DISK=local
) > "%CORE_DEST%\.env"
echo OK.
echo.

echo ============================================================
echo  EXITO. Estructura API SUNAT DEMO generada en: %DEST%
echo ============================================================
echo.
echo  INSTRUCCIONES PARA FILEZILLA (API DEMO):
echo  1. Sube la carpeta "demo-sunat_core" a: /home/u230880442/domains/lu-tec.net/demo-sunat_core/
echo  2. Sube la carpeta "demo-sunat" a:      /home/u230880442/domains/lu-tec.net/public_html/demo-sunat/
echo.
echo  El archivo .env ya está creado y configurado dentro de demo-sunat_core/.
echo ============================================================
pause
