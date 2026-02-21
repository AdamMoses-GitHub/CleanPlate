<?php
/**
 * TEMPORARY DIAGNOSTIC — DELETE THIS FILE AFTER USE
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo '<pre>';

$root = __DIR__ . '/../../';

require_once $root . 'includes/Config.php';
Config::load();

// Show exactly what values PHP resolved for the DB connection
$host    = Config::get('connections.mysql.host',     '(not set)');
$port    = Config::get('connections.mysql.port',     '(not set)');
$dbname  = Config::get('connections.mysql.database', '(not set)');
$user    = Config::get('connections.mysql.username', '(not set)');
$pass    = Config::get('connections.mysql.password', '(not set)');

echo "Resolved DB config:\n";
echo "  host     : " . $host   . "\n";
echo "  port     : " . $port   . "\n";
echo "  database : " . $dbname . "\n";
echo "  username : " . $user   . "\n";
echo "  password : " . (strlen((string)$pass) > 0 ? str_repeat('*', strlen((string)$pass)) : '(empty!)') . "\n\n";

// Also show raw env values so we can see if .env is being read at all
echo "Raw env values (getenv):\n";
foreach (['DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD'] as $k) {
    $v = getenv($k);
    if ($k === 'DB_PASSWORD') {
        echo "  $k = " . ($v !== false && $v !== '' ? str_repeat('*', strlen($v)) : '(empty or not set)') . "\n";
    } else {
        echo "  $k = " . ($v !== false ? $v : '(not set — using default)') . "\n";
    }
}
echo "\n";

// Now attempt the connection with those values
$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
echo "DSN: $dsn\n\n";

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "PDO connection: OK\n";
} catch (Throwable $e) {
    echo "PDO connection: FAILED\n";
    echo $e->getMessage() . "\n";
}

echo '</pre>';
echo '<p style="color:red;font-weight:bold">DELETE THIS FILE FROM THE SERVER NOW.</p>';
