@echo off
rem Menu - run every check (same as CI, minus browser tests) after pulling a branch.
rem Run from the repository root:  .\check.bat
rem Output: check-log.txt (not committed).
setlocal
cd /d "%~dp0"
set "LOG=%~dp0check-log.txt"
echo Menu checks %date% %time%> "%LOG%"
git rev-parse --abbrev-ref HEAD>> "%LOG%" 2>&1
git log --oneline -1>> "%LOG%" 2>&1
echo.>> "%LOG%"
echo ################ Start containers ################>> "%LOG%"
echo == Start containers ==
echo   start...
echo.>> "%LOG%"
echo ===== start =====>> "%LOG%"
pushd "%~dp0"
(docker compose up -d app web db mail) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   wait for MySQL...
echo.>> "%LOG%"
echo ===== wait for MySQL =====>> "%LOG%"
pushd "%~dp0"
(docker compose exec -T db sh -c "i=0; until mysql -uroot -proot -e 'select 1' >/dev/null 2>&1; do i=$((i+1)); [ $i -gt 90 ] && exit 1; sleep 2; done; echo MySQL is ready") >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo.>> "%LOG%"
echo ################ Backend (in Docker) ################>> "%LOG%"
echo == Backend (in Docker) ==
echo   laravel/fortify (approved, Partition 2)...
echo.>> "%LOG%"
echo ===== laravel/fortify (approved, Partition 2) =====>> "%LOG%"
pushd "%~dp0"
(findstr /c:"laravel/fortify" backend\composer.json >nul && (echo already required) || (docker compose exec -T app composer require laravel/fortify --no-interaction --no-progress)) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   composer install...
echo.>> "%LOG%"
echo ===== composer install =====>> "%LOG%"
pushd "%~dp0"
(docker compose exec -T app composer install --no-interaction --no-progress) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo   migrate:fresh...
echo.>> "%LOG%"
echo ===== migrate:fresh =====>> "%LOG%"
pushd "%~dp0"
(docker compose exec -T app php artisan migrate:fresh --force) >> "%LOG%" 2>&1
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
echo ################ Frontend (on Windows) ################>> "%LOG%"
echo == Frontend (on Windows) ==
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
(git status --short) >> "%LOG%" 2>&1
echo [exit code %errorlevel%]>> "%LOG%"
popd
echo.
echo Done. Tell Claude: "checks finished" (the log is check-log.txt).
endlocal
