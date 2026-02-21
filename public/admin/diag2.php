<?php
/**
 * TEMPORARY DIAGNOSTIC — DELETE THIS FILE AFTER USE
 * Upload to /admin/diag2.php, visit it once while logged in, then delete it.
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo '<pre>';

$root = __DIR__ . '/../../';

// 1. Load config + DB (we know these work from diag.php)
try {
    require_once $root . 'includes/Config.php';
    Config::load();
    require_once $root . 'includes/Database.php';
    $db = Database::getInstance();
    echo "Config + DB     : OK\n\n";
} catch (Throwable $e) {
    echo "Config/DB FAILED: " . $e->getMessage() . "\n";
    exit;
}

// 2. Check required tables exist
$tables = ['recipe_extractions', 'site_visits'];
foreach ($tables as $table) {
    try {
        $db->query("SELECT 1 FROM `{$table}` LIMIT 1");
        echo "Table {$table}: EXISTS\n";
    } catch (Throwable $e) {
        echo "Table {$table}: MISSING !!!  ← run the SQL migration for this table\n";
    }
}
echo "\n";

// 3. Load each admin include individually
$includes = [
    'AdminAuth.php',
    'ExtractionRepository.php',
    'VisitRepository.php',
    'VisitTracker.php',
    'IngredientFilter.php',
    'RecipeParser.php',
    'ConfigValidator.php',
];
foreach ($includes as $file) {
    $path = $root . 'includes/' . $file;
    if (!file_exists($path)) {
        echo "Include {$file}: MISSING !!!\n";
        continue;
    }
    try {
        require_once $path;
        echo "Include {$file}: OK\n";
    } catch (Throwable $e) {
        echo "Include {$file}: ERROR — " . $e->getMessage() . "\n";
    }
}
echo "\n";

// 4. Try instantiating the repositories
try {
    $repo = new ExtractionRepository($db);
    $stats = $repo->getDashboardStats();
    echo "ExtractionRepository::getDashboardStats() : OK\n";
} catch (Throwable $e) {
    echo "ExtractionRepository::getDashboardStats() : FAILED — " . $e->getMessage() . "\n";
}

try {
    $vrepo = new VisitRepository($db);
    $vrepo->getSummaryStats();
    echo "VisitRepository::getSummaryStats()        : OK\n";
} catch (Throwable $e) {
    echo "VisitRepository::getSummaryStats()        : FAILED — " . $e->getMessage() . "\n";
}

// 5. Session / AdminAuth check
try {
    require_once $root . 'includes/AdminAuth.php';
    echo "\nAdminAuth loaded  : OK\n";
    echo "Session status    : " . (session_status() === PHP_SESSION_ACTIVE ? 'active' : 'not started') . "\n";
    echo "Admin logged in   : " . (AdminAuth::isLoggedIn() ? 'YES' : 'NO — session may not carry across to this file') . "\n";
} catch (Throwable $e) {
    echo "AdminAuth FAILED  : " . $e->getMessage() . "\n";
}

echo '</pre>';
echo '<p style="color:red;font-weight:bold">DELETE THIS FILE FROM THE SERVER NOW.</p>';
