@echo off
rem Menu - local setup: backend in Docker Desktop, frontend with Node on Windows.
rem Run from the repository root:  .\setup-docker.bat   (safe to run again)
rem Everything is written to setup-log.txt (not committed).
setlocal
cd /d "%~dp0"
set "LOG=%~dp0setup-log.txt"
echo Menu Docker setup %date% %time%> "%LOG%"
echo.>> "%LOG%"
echo ################ Versions ################>> "%LOG%"
echo == Versions ==
echo   docker version...
echo.>> "%LOG%"
echo ===== docker version =====>> "%LOG%"
pushd "%~dp0"
(docker version) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   docker compose version...
echo.>> "%LOG%"
echo ===== docker compose version =====>> "%LOG%"
pushd "%~dp0"
(docker compose version) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   node -v...
echo.>> "%LOG%"
echo ===== node -v =====>> "%LOG%"
pushd "%~dp0"
(node -v) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo.>> "%LOG%"
echo ################ Start containers ################>> "%LOG%"
echo == Start containers ==
echo   build and start...
echo.>> "%LOG%"
echo ===== build and start =====>> "%LOG%"
pushd "%~dp0"
(docker compose up -d --build app web db mail) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   wait for MySQL...
echo.>> "%LOG%"
echo ===== wait for MySQL =====>> "%LOG%"
pushd "%~dp0"
(docker compose exec -T db sh -c "i=0; until mysql -uroot -proot -e 'select 1' >/dev/null 2>&1; do i=$((i+1)); [ $i -gt 90 ] && exit 1; sleep 2; done; echo MySQL is ready") >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   test database...
echo.>> "%LOG%"
echo ===== test database =====>> "%LOG%"
pushd "%~dp0"
(docker compose exec -T db mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS menu_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON menu_test.* TO 'menu'@'%%';") >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo.>> "%LOG%"
echo ################ Backend (in Docker) ################>> "%LOG%"
echo == Backend (in Docker) ==
echo   php -v...
echo.>> "%LOG%"
echo ===== php -v =====>> "%LOG%"
pushd "%~dp0"
(docker compose exec -T app php -v) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   composer install...
echo.>> "%LOG%"
echo ===== composer install =====>> "%LOG%"
pushd "%~dp0"
(docker compose exec -T app composer install --no-interaction --no-progress) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   install:api (Sanctum)...
echo.>> "%LOG%"
echo ===== install:api (Sanctum) =====>> "%LOG%"
pushd "%~dp0"
(if not exist backend\routes\api.php (docker compose exec -T app php artisan install:api --without-migration-prompt --no-interaction) else (echo already installed)) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   composer require dev tools...
echo.>> "%LOG%"
echo ===== composer require dev tools =====>> "%LOG%"
pushd "%~dp0"
(docker compose exec -T app composer require --dev pestphp/pest pestphp/pest-plugin-laravel larastan/larastan --with-all-dependencies --no-interaction --no-progress) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   .env...
echo.>> "%LOG%"
echo ===== .env =====>> "%LOG%"
pushd "%~dp0"
(docker compose exec -T app php -r "file_exists('.env') || copy('.env.example', '.env');") >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   key:generate...
echo.>> "%LOG%"
echo ===== key:generate =====>> "%LOG%"
pushd "%~dp0"
(docker compose exec -T app php artisan key:generate --no-interaction) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   migrate...
echo.>> "%LOG%"
echo ===== migrate =====>> "%LOG%"
pushd "%~dp0"
(docker compose exec -T app php artisan migrate --force) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   migrate:rollback...
echo.>> "%LOG%"
echo ===== migrate:rollback =====>> "%LOG%"
pushd "%~dp0"
(docker compose exec -T app php artisan migrate:rollback --force) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   migrate again...
echo.>> "%LOG%"
echo ===== migrate again =====>> "%LOG%"
pushd "%~dp0"
(docker compose exec -T app php artisan migrate --force) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   pint (fix)...
echo.>> "%LOG%"
echo ===== pint (fix) =====>> "%LOG%"
pushd "%~dp0"
(docker compose exec -T app vendor/bin/pint) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   pint --test...
echo.>> "%LOG%"
echo ===== pint --test =====>> "%LOG%"
pushd "%~dp0"
(docker compose exec -T app vendor/bin/pint --test) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   phpstan...
echo.>> "%LOG%"
echo ===== phpstan =====>> "%LOG%"
pushd "%~dp0"
(docker compose exec -T app vendor/bin/phpstan analyse --no-progress --memory-limit=1G) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   tests...
echo.>> "%LOG%"
echo ===== tests =====>> "%LOG%"
pushd "%~dp0"
(docker compose exec -T app php artisan test) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo.>> "%LOG%"
echo ################ Frontend (on Windows, Node from Laragon) ################>> "%LOG%"
echo == Frontend (on Windows, Node from Laragon) ==
echo   pnpm install...
echo.>> "%LOG%"
echo ===== pnpm install =====>> "%LOG%"
pushd "%~dp0"
(cd frontend && call pnpm install) >> "%LOG%" 2>&1
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
echo Frontend tests run in CI (they need a 180 MB test browser).
echo Done. App: http://localhost:8080  -  Mail: http://localhost:8026
echo Tell Claude: "setup finished" (the log is setup-log.txt).
endlocal
