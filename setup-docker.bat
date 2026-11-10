@echo off
rem Menu - local setup with Docker Desktop (no Laragon needed).
rem Run from the repository root:  .\setup-docker.bat
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
(docker version) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
echo   docker compose version...
echo.>> "%LOG%"
echo ===== docker compose version =====>> "%LOG%"
(docker compose version) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
echo.>> "%LOG%"
echo ################ Start containers ################>> "%LOG%"
echo == Start containers ==
echo   build and start...
echo.>> "%LOG%"
echo ===== build and start =====>> "%LOG%"
(docker compose up -d --build app web db mail) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
echo   wait for MySQL...
echo.>> "%LOG%"
echo ===== wait for MySQL =====>> "%LOG%"
(docker compose exec -T db mysqladmin ping -uroot -proot --wait=60) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
echo   test database...
echo.>> "%LOG%"
echo ===== test database =====>> "%LOG%"
(docker compose exec -T db mysql -uroot -proot -e "CREATE DATABASE IF NOT EXISTS menu_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON menu_test.* TO 'menu'@'%%';") >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
echo.>> "%LOG%"
echo ################ Backend ################>> "%LOG%"
echo == Backend ==
echo   php -v...
echo.>> "%LOG%"
echo ===== php -v =====>> "%LOG%"
(docker compose exec -T app php -v) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
echo   composer install...
echo.>> "%LOG%"
echo ===== composer install =====>> "%LOG%"
(docker compose exec -T app composer install --no-interaction --no-progress) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
echo   install:api (Sanctum)...
echo.>> "%LOG%"
echo ===== install:api (Sanctum) =====>> "%LOG%"
(docker compose exec -T app php artisan install:api --without-migration-prompt --no-interaction) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
echo   composer require dev tools...
echo.>> "%LOG%"
echo ===== composer require dev tools =====>> "%LOG%"
(docker compose exec -T app composer require --dev pestphp/pest pestphp/pest-plugin-laravel larastan/larastan --with-all-dependencies --no-interaction --no-progress) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
echo   .env...
echo.>> "%LOG%"
echo ===== .env =====>> "%LOG%"
(docker compose exec -T app php -r "file_exists('.env') || copy('.env.example', '.env');") >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
echo   key:generate...
echo.>> "%LOG%"
echo ===== key:generate =====>> "%LOG%"
(docker compose exec -T app php artisan key:generate --no-interaction) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
echo   migrate...
echo.>> "%LOG%"
echo ===== migrate =====>> "%LOG%"
(docker compose exec -T app php artisan migrate --force) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
echo   migrate:rollback...
echo.>> "%LOG%"
echo ===== migrate:rollback =====>> "%LOG%"
(docker compose exec -T app php artisan migrate:rollback --force) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
echo   migrate again...
echo.>> "%LOG%"
echo ===== migrate again =====>> "%LOG%"
(docker compose exec -T app php artisan migrate --force) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
echo   pint (fix)...
echo.>> "%LOG%"
echo ===== pint (fix) =====>> "%LOG%"
(docker compose exec -T app vendor/bin/pint) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
echo   pint --test...
echo.>> "%LOG%"
echo ===== pint --test =====>> "%LOG%"
(docker compose exec -T app vendor/bin/pint --test) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
echo   phpstan...
echo.>> "%LOG%"
echo ===== phpstan =====>> "%LOG%"
(docker compose exec -T app vendor/bin/phpstan analyse --no-progress --memory-limit=1G) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
echo   tests...
echo.>> "%LOG%"
echo ===== tests =====>> "%LOG%"
(docker compose exec -T app php artisan test) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
echo.>> "%LOG%"
echo ################ Frontend ################>> "%LOG%"
echo == Frontend ==
echo   pnpm install + add i18n...
echo.>> "%LOG%"
echo ===== pnpm install + add i18n =====>> "%LOG%"
(docker compose run --rm --no-deps -T vite sh -c "corepack enable && pnpm install && pnpm add i18next react-i18next") >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
echo   pnpm format...
echo.>> "%LOG%"
echo ===== pnpm format =====>> "%LOG%"
(docker compose run --rm --no-deps -T vite sh -c "corepack enable && pnpm format") >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
echo   pnpm lint...
echo.>> "%LOG%"
echo ===== pnpm lint =====>> "%LOG%"
(docker compose run --rm --no-deps -T vite sh -c "corepack enable && pnpm lint") >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
echo   pnpm build...
echo.>> "%LOG%"
echo ===== pnpm build =====>> "%LOG%"
(docker compose run --rm --no-deps -T vite sh -c "corepack enable && pnpm build") >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
echo.>> "%LOG%"
echo ################ Git ################>> "%LOG%"
echo == Git ==
echo   git status...
echo.>> "%LOG%"
echo ===== git status =====>> "%LOG%"
(git status) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
echo.
echo Frontend tests run in GitHub CI (they need a 180 MB test browser).
echo Done. App: http://localhost:8080  -  Mail: http://localhost:8025
echo Tell Claude: "setup finished" (the log is setup-log.txt).
endlocal
