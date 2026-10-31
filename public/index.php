<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Folder program. Bila isi folder public disalin ke public_html (PANDUAN-CPANEL.md bagian 4,
// Pilihan 2), ubah baris ini menjadi: $root = __DIR__.'/../pranata';
$root = __DIR__.'/..';

// Pemeriksaan awal agar salah pasang di hosting tidak berakhir sebagai halaman 500 kosong.
$setupProblem = match (true) {
    PHP_VERSION_ID < 80300 => 'Versi PHP situs ini '.PHP_VERSION.'. PRANATA membutuhkan PHP 8.3 atau lebih baru (cPanel › MultiPHP Manager).',
    ! is_file($root.'/vendor/autoload.php') => 'Folder vendor belum ada atau belum lengkap. Jalankan composer install --no-dev --optimize-autoloader, atau ekstrak paket pranata-cpanel.zip yang sudah berisi vendor.',
    ! is_file($root.'/bootstrap/cache/config.php') && ! is_file($root.'/.env') => 'File .env belum ada. Salin .env.example menjadi .env lalu isi pengaturannya (PANDUAN-CPANEL.md bagian 5).',
    ! is_file($root.'/bootstrap/cache/config.php') && ! getenv('APP_KEY') && ! preg_match('/^APP_KEY=[ \t]*["\']?[^\s"\']+/m', (string) file_get_contents($root.'/.env')) => 'APP_KEY di file .env masih kosong. Jalankan php artisan key:generate --force (PANDUAN-CPANEL.md bagian 6).',
    ! is_writable($root.'/storage') || ! is_writable($root.'/bootstrap/cache') => 'Folder storage atau bootstrap/cache tidak dapat ditulis. Ubah izinnya menjadi 755 di File Manager.',
    default => null,
};
if ($setupProblem !== null) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>PRANATA belum siap</title>'
        .'<body style="font-family:system-ui,sans-serif;background:#142c38;color:#20353d;display:grid;place-items:center;min-height:100vh;margin:0;padding:24px">'
        .'<main style="background:#fff;border-radius:10px;padding:36px;max-width:520px"><h1 style="margin-top:0">PRANATA belum siap</h1><p>'
        .htmlspecialchars($setupProblem, ENT_QUOTES).'</p><p style="color:#667a80;font-size:13px">Pesan ini hanya muncul selama instalasi belum lengkap.</p></main></body></html>';
    exit;
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $root.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $root.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $root.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
