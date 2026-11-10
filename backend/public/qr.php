<?php

/*
 * /q/{code} in one step, without Laravel or the database (PLAN.md §15.2.11).
 * The map is a plain PHP array written by App\Support\Qr\QrMap and kept in
 * OPcache. Anything it can't answer goes to Laravel, which rebuilds the map.
 */
$code = strtoupper(substr((string) ($_GET['c'] ?? ''), 0, 8));
$map = is_file(__DIR__.'/../storage/app/qr-map.php') ? include __DIR__.'/../storage/app/qr-map.php' : null;

if (is_array($map) && isset($map[$code])) {
    header('Cache-Control: no-store');
    header('Location: '.$map[$code], true, 302);
    exit;
}

require __DIR__.'/index.php';
