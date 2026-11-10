<?php

// Creates the local databases (menu, menu_test) without needing the mysql client.
// Usage: php scripts/create-databases.php [host] [user] [password]
$host = $argv[1] ?? '127.0.0.1';
$user = $argv[2] ?? 'root';
$pass = $argv[3] ?? '';

try {
    $pdo = new PDO("mysql:host={$host};port=3306", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo 'MySQL server: '.$pdo->query('SELECT VERSION()')->fetchColumn().PHP_EOL;
    foreach (['menu', 'menu_test'] as $db) {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "Database ready: {$db}".PHP_EOL;
    }
} catch (PDOException $e) {
    fwrite(STDERR, 'Could not connect to MySQL: '.$e->getMessage().PHP_EOL.'Start MySQL in Laragon (Start All) and run the script again.'.PHP_EOL);
    exit(1);
}
