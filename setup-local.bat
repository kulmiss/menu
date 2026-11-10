@echo off
rem Menu - local setup for Laragon on Windows.
rem Run from the repository root:  .\setup-local.bat
rem Everything is written to setup-log.txt (not committed).
setlocal
cd /d "%~dp0"
set "LOG=%~dp0setup-log.txt"
echo Menu local setup %date% %time%> "%LOG%"
echo.>> "%LOG%"
echo ################ Versions ################>> "%LOG%"
echo == Versions ==
echo   php -v...
echo.>> "%LOG%"
echo ===== php -v =====>> "%LOG%"
pushd "%~dp0"
(php -v) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   composer -V...
echo.>> "%LOG%"
echo ===== composer -V =====>> "%LOG%"
pushd "%~dp0"
(call composer -V) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   node -v...
echo.>> "%LOG%"
echo ===== node -v =====>> "%LOG%"
pushd "%~dp0"
(node -v) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   git --version...
echo.>> "%LOG%"
echo ===== git --version =====>> "%LOG%"
pushd "%~dp0"
(git --version) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   Create databases...
echo.>> "%LOG%"
echo ===== Create databases =====>> "%LOG%"
pushd "%~dp0"
(php scripts\create-databases.php) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo.>> "%LOG%"
echo ################ Backend ################>> "%LOG%"
echo == Backend ==
echo   composer install...
echo.>> "%LOG%"
echo ===== composer install =====>> "%LOG%"
pushd "%~dp0"
(cd backend && call composer install --no-interaction --no-progress) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   install:api (Sanctum)...
echo.>> "%LOG%"
echo ===== install:api (Sanctum) =====>> "%LOG%"
pushd "%~dp0"
(cd backend && php artisan install:api --without-migration-prompt --no-interaction) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   composer require dev tools...
echo.>> "%LOG%"
echo ===== composer require dev tools =====>> "%LOG%"
pushd "%~dp0"
(cd backend && call composer require --dev pestphp/pest pestphp/pest-plugin-laravel larastan/larastan --with-all-dependencies --no-interaction --no-progress) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   .env...
echo.>> "%LOG%"
echo ===== .env =====>> "%LOG%"
pushd "%~dp0"
(cd backend && if not exist .env copy .env.example .env) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   key:generate...
echo.>> "%LOG%"
echo ===== key:generate =====>> "%LOG%"
pushd "%~dp0"
(cd backend && php artisan key:generate --no-interaction) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   migrate...
echo.>> "%LOG%"
echo ===== migrate =====>> "%LOG%"
pushd "%~dp0"
(cd backend && php artisan migrate --force) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   migrate:rollback...
echo.>> "%LOG%"
echo ===== migrate:rollback =====>> "%LOG%"
pushd "%~dp0"
(cd backend && php artisan migrate:rollback --force) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   migrate again...
echo.>> "%LOG%"
echo ===== migrate again =====>> "%LOG%"
pushd "%~dp0"
(cd backend && php artisan migrate --force) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   pint (fix)...
echo.>> "%LOG%"
echo ===== pint (fix) =====>> "%LOG%"
pushd "%~dp0"
(cd backend && vendor\bin\pint) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   pint --test...
echo.>> "%LOG%"
echo ===== pint --test =====>> "%LOG%"
pushd "%~dp0"
(cd backend && vendor\bin\pint --test) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   phpstan...
echo.>> "%LOG%"
echo ===== phpstan =====>> "%LOG%"
pushd "%~dp0"
(cd backend && vendor\bin\phpstan analyse --no-progress --memory-limit=1G) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   tests...
echo.>> "%LOG%"
echo ===== tests =====>> "%LOG%"
pushd "%~dp0"
(cd backend && php artisan test) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo.>> "%LOG%"
echo ################ Frontend ################>> "%LOG%"
echo == Frontend ==
echo   install pnpm 10.28.0...
echo.>> "%LOG%"
echo ===== install pnpm 10.28.0 =====>> "%LOG%"
pushd "%~dp0"
(call npm install -g pnpm@10.28.0) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   pnpm install...
echo.>> "%LOG%"
echo ===== pnpm install =====>> "%LOG%"
pushd "%~dp0"
(cd frontend && call pnpm install) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   pnpm add i18n...
echo.>> "%LOG%"
echo ===== pnpm add i18n =====>> "%LOG%"
pushd "%~dp0"
(cd frontend && call pnpm add i18next react-i18next) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   pnpm format...
echo.>> "%LOG%"
echo ===== pnpm format =====>> "%LOG%"
pushd "%~dp0"
(cd frontend && call pnpm format) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   pnpm lint...
echo.>> "%LOG%"
echo ===== pnpm lint =====>> "%LOG%"
pushd "%~dp0"
(cd frontend && call pnpm lint) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   pnpm build...
echo.>> "%LOG%"
echo ===== pnpm build =====>> "%LOG%"
pushd "%~dp0"
(cd frontend && call pnpm build) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo.>> "%LOG%"
echo ################ Git ################>> "%LOG%"
echo == Git ==
echo   git status...
echo.>> "%LOG%"
echo ===== git status =====>> "%LOG%"
pushd "%~dp0"
(git status) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo.
echo Frontend tests run in GitHub CI (they need a 180 MB test browser).
echo Done. Tell Claude: "setup finished" (the log is setup-log.txt).
endlocal
