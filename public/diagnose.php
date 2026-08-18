<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('LARAVEL_START', microtime(true));
require __DIR__.'/../vendor/autoload.php';

try {
    $app = require_once __DIR__.'/../bootstrap/app.php';
    echo "✅ Bootstrap OK<br>";

    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    echo "✅ Kernel OK<br>";

    $emp = new \App\Models\Employee();
    echo "✅ Employee model loaded OK<br>";

    echo "<br><b>PHP Version: " . PHP_VERSION . "</b><br>";
    echo "OPcache: " . (function_exists('opcache_reset') ? 'enabled' : 'disabled') . "<br>";

} catch (\Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine();
}
