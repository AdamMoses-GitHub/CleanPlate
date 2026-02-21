<?php
/**
 * TEMPORARY DIAGNOSTIC — DELETE THIS FILE AFTER USE
 * Upload to /admin/diag.php, visit it once, then delete it.
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo '<pre>';

// 1. PHP version
echo 'PHP version : ' . PHP_VERSION . "\n";
echo 'PHP SAPI    : ' . PHP_SAPI . "\n\n";

// 2. Required extensions
$required = ['pdo', 'pdo_mysql', 'curl', 'mbstring', 'json'];
foreach ($required as $ext) {
    echo 'Extension ' . str_pad($ext, 12) . ': ' . (extension_loaded($ext) ? 'OK' : 'MISSING !!!') . "\n";
}
echo "\n";

// 3. Key paths
$root    = __DIR__ . '/../../';
$envPath = realpath($root . '.env');
$incPath = realpath($root . 'includes/Config.php');
echo '.env path   : ' . ($envPath ?: 'NOT FOUND at ' . $root . '.env') . "\n";
echo 'Config.php  : ' . ($incPath ?: 'NOT FOUND at ' . $root . 'includes/Config.php') . "\n\n";

// 4. Storage writability
$storage = [
    $root . 'storage/cache',
    $root . 'storage/logs',
    $root . 'storage/temp',
];
foreach ($storage as $dir) {
    $rp = realpath($dir);
    echo 'Writable ' . str_pad(basename($dir), 8) . ': ' . ($rp && is_writable($rp) ? 'OK' : 'NOT WRITABLE or missing — ' . $dir) . "\n";
}
echo "\n";

// 5. Try loading config + DB
try {
    require_once $root . 'includes/Config.php';
    Config::load();
    echo "Config::load()  : OK\n";
} catch (Throwable $e) {
    echo "Config::load()  : FAILED — " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit;
}

// 6. Try DB connection
try {
    require_once $root . 'includes/Database.php';
    $db = Database::getInstance();
    $db->query('SELECT 1');
    echo "DB connection   : OK\n";
} catch (Throwable $e) {
    echo "DB connection   : FAILED — " . $e->getMessage() . "\n";
}

echo '</pre>';
echo '<p style="color:red;font-weight:bold">DELETE THIS FILE FROM THE SERVER NOW.</p>';
