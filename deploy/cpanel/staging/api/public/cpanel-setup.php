<?php

/**
 * One-time cPanel helper (no SSH). Open in browser once, then DELETE this file.
 */
declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

$base = dirname(__DIR__);
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain; charset=utf-8');

$steps = [];

try {
    @chmod($base.'/storage', 0775);
    @chmod($base.'/bootstrap/cache', 0775);
    @chmod($base.'/database', 0775);
    $sqlite = $base.'/database/database.sqlite';
    if (! is_file($sqlite)) {
        touch($sqlite);
    }
    @chmod($sqlite, 0664);

    if (! is_link($base.'/public/storage') && ! is_dir($base.'/public/storage')) {
        Artisan::call('storage:link');
        $steps[] = trim(Artisan::output()) ?: 'storage:link OK';
    } else {
        $steps[] = 'storage link already present';
    }

    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('config:cache');
    Artisan::call('route:cache');
    $steps[] = 'caches rebuilt';

    $steps[] = 'APP_URL='.(string) config('app.url');
    $steps[] = 'DB='.(string) config('database.default');
    $steps[] = 'DONE — delete public/cpanel-setup.php now.';
} catch (Throwable $e) {
    http_response_code(500);
    $steps[] = 'ERROR: '.$e->getMessage();
}

echo implode("\n", $steps)."\n";
