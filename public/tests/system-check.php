<?php
/**
 * CleanPlate System Check
 * Diagnostic tool to verify all dependencies and functionality
 */

// Configuration
$requiredPHPVersion = '7.4.0';
$requiredExtensions = ['curl', 'dom', 'json', 'mbstring', 'libxml'];
$requiredFiles = [
    '../api/parser.php',
    '../../includes/RecipeParser.php',
    '../../includes/IngredientFilter.php',
    '../index.html',
    '../js/app.js',
    '../css/style.css'
];
$requiredDirs = [
    '../api',
    '../../includes',
    '..',
    '../js',
    '../css',
    '../tests'
];

// Start output
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CleanPlate System Check</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Helvetica', 'Arial', sans-serif;
            background: #f5f5f5;
            padding: 2rem;
            line-height: 1.6;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c2418;
            margin-bottom: 0.5rem;
            font-size: 2rem;
        }
        .subtitle {
            color: #6b5d4f;
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }
        .section {
            margin-bottom: 2rem;
        }
        .section h2 {
            color: #2c2418;
            font-size: 1.4rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #d4a574;
        }
        .check-item {
            display: flex;
            align-items: flex-start;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            background: #f9f9f9;
            border-radius: 4px;
            border-left: 4px solid #ddd;
        }
        .check-item.pass {
            border-left-color: #6b8e23;
            background: #f0f8e8;
        }
        .check-item.fail {
            border-left-color: #c44536;
            background: #fff5f5;
        }
        .check-item.warn {
            border-left-color: #f39c12;
            background: #fff9e6;
        }
        .icon {
            font-size: 1.5rem;
            margin-right: 1rem;
            min-width: 30px;
        }
        .icon.pass { color: #6b8e23; }
        .icon.fail { color: #c44536; }
        .icon.warn { color: #f39c12; }
        .check-content {
            flex: 1;
        }
        .check-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: #2c2418;
        }
        .check-detail {
            font-size: 0.9rem;
            color: #6b5d4f;
        }
        .summary {
            background: #e8f4f8;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 2rem;
            border: 2px solid #3498db;
        }
        .summary.success {
            background: #f0f8e8;
            border-color: #6b8e23;
        }
        .summary.error {
            background: #fff5f5;
            border-color: #c44536;
        }
        .summary h3 {
            margin-bottom: 0.5rem;
            color: #2c2418;
        }
        .summary-stats {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
            font-size: 1.1rem;
        }
        .stat {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .code {
            background: #f4f4f4;
            padding: 0.2rem 0.5rem;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
        }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #d4a574;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 1rem;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #c89456;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 CleanPlate System Check</h1>
        <p class="subtitle">Verifying all dependencies and functionality</p>

<?php

$checks = [];
$passCount = 0;
$failCount = 0;
$warnCount = 0;

// Check 1: PHP Version
$section = 'PHP Environment';
$currentVersion = PHP_VERSION;
$versionCheck = version_compare($currentVersion, $requiredPHPVersion, '>=');
$checks[$section][] = [
    'status' => $versionCheck ? 'pass' : 'fail',
    'title' => 'PHP Version',
    'detail' => $versionCheck 
        ? "✓ PHP $currentVersion (required: $requiredPHPVersion+)"
        : "✗ PHP $currentVersion installed, but $requiredPHPVersion+ is required"
];

if ($versionCheck) $passCount++; else $failCount++;

// Check 2: PHP Extensions
foreach ($requiredExtensions as $ext) {
    $loaded = extension_loaded($ext);
    $checks[$section][] = [
        'status' => $loaded ? 'pass' : 'fail',
        'title' => "Extension: $ext",
        'detail' => $loaded ? "✓ Loaded" : "✗ Not loaded - Install php-$ext"
    ];
    if ($loaded) $passCount++; else $failCount++;
}

// Check 3: Important PHP Settings
$settings = [
    'allow_url_fopen' => ['recommended' => true, 'critical' => false],
    'file_uploads' => ['recommended' => true, 'critical' => false],
];

foreach ($settings as $setting => $config) {
    $value = ini_get($setting);
    $isEnabled = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    
    if (!$isEnabled && $config['critical']) {
        $status = 'fail';
        $failCount++;
    } elseif (!$isEnabled && $config['recommended']) {
        $status = 'warn';
        $warnCount++;
    } else {
        $status = 'pass';
        $passCount++;
    }
    
    $checks[$section][] = [
        'status' => $status,
        'title' => "Setting: $setting",
        'detail' => $isEnabled ? "✓ Enabled" : "⚠ Disabled (may affect functionality)"
    ];
}

// Check 4: File Structure
$section = 'File Structure';

foreach ($requiredDirs as $dir) {
    $exists = is_dir(__DIR__ . '/' . $dir);
    $checks[$section][] = [
        'status' => $exists ? 'pass' : 'fail',
        'title' => "Directory: $dir/",
        'detail' => $exists ? "✓ Exists" : "✗ Missing directory"
    ];
    if ($exists) $passCount++; else $failCount++;
}

foreach ($requiredFiles as $file) {
    $path = __DIR__ . '/' . $file;
    $exists = file_exists($path);
    $readable = $exists && is_readable($path);
    
    if (!$exists) {
        $status = 'fail';
        $detail = "✗ File not found";
        $failCount++;
    } elseif (!$readable) {
        $status = 'fail';
        $detail = "✗ File exists but not readable (check permissions)";
        $failCount++;
    } else {
        $status = 'pass';
        $detail = "✓ Exists and readable (" . formatBytes(filesize($path)) . ")";
        $passCount++;
    }
    
    $checks[$section][] = [
        'status' => $status,
        'title' => "File: $file",
        'detail' => $detail
    ];
}

// Check 5: Class Loading
$section = 'Core Functionality';

$recipeParserPath = __DIR__ . '/../../includes/RecipeParser.php';
if (file_exists($recipeParserPath)) {
    try {
        require_once $recipeParserPath;
        $classExists = class_exists('RecipeParser');
        $checks[$section][] = [
            'status' => $classExists ? 'pass' : 'fail',
            'title' => 'RecipeParser Class',
            'detail' => $classExists ? "✓ Class loaded successfully" : "✗ Class not found in file"
        ];
        if ($classExists) $passCount++; else $failCount++;
        
        // Check if methods exist
        if ($classExists) {
            $parser = new RecipeParser();
            $hasParseMethod = method_exists($parser, 'parse');
            $checks[$section][] = [
                'status' => $hasParseMethod ? 'pass' : 'fail',
                'title' => 'Parser Methods',
                'detail' => $hasParseMethod ? "✓ parse() method exists" : "✗ parse() method missing"
            ];
            if ($hasParseMethod) $passCount++; else $failCount++;
        }
    } catch (Exception $e) {
        $checks[$section][] = [
            'status' => 'fail',
            'title' => 'RecipeParser Class',
            'detail' => "✗ Error loading class: " . $e->getMessage()
        ];
        $failCount++;
    }
} else {
    $checks[$section][] = [
        'status' => 'fail',
        'title' => 'RecipeParser Class',
        'detail' => "✗ ../includes/RecipeParser.php not found"
    ];
    $failCount++;
}

// Check 6: Network Connectivity
$section = 'Network Capabilities';

if (function_exists('curl_init')) {
    // Test a simple HTTPS connection
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://httpbin.org/get',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_NOBODY => true,
    ]);
    
    $result = @curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($result !== false && $httpCode == 200) {
        $checks[$section][] = [
            'status' => 'pass',
            'title' => 'HTTPS Connectivity',
            'detail' => "✓ Can connect to external websites"
        ];
        $passCount++;
    } else {
        $checks[$section][] = [
            'status' => 'warn',
            'title' => 'HTTPS Connectivity',
            'detail' => "⚠ Test connection failed (may be firewall/network issue)"
        ];
        $warnCount++;
    }
    
    // Check if SSL verification works
    $sslInfo = curl_version();
    $hasSSL = isset($sslInfo['ssl_version']) && !empty($sslInfo['ssl_version']);
    $checks[$section][] = [
        'status' => $hasSSL ? 'pass' : 'warn',
        'title' => 'SSL/TLS Support',
        'detail' => $hasSSL 
            ? "✓ SSL supported (" . $sslInfo['ssl_version'] . ")" 
            : "⚠ SSL support unclear"
    ];
    if ($hasSSL) $passCount++; else $warnCount++;
}

// Check 7: Session Support
$section = 'Session Support';

$sessionStarted = false;
$sessionPath = session_save_path();

if (empty($sessionPath)) {
    $checks[$section][] = [
        'status' => 'warn',
        'title' => 'Session Save Path',
        'detail' => "⚠ Session path not configured (using system default)"
    ];
    $warnCount++;
} else {
    $sessionWritable = is_writable($sessionPath);
    $checks[$section][] = [
        'status' => $sessionWritable ? 'pass' : 'fail',
        'title' => 'Session Save Path',
        'detail' => $sessionWritable 
            ? "✓ Writable: $sessionPath" 
            : "✗ Not writable: $sessionPath (rate limiting won't work)"
    ];
    if ($sessionWritable) $passCount++; else $failCount++;
}

// Try starting a session
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        $sessionStarted = true;
    } else {
        $sessionStarted = true;
    }
    
    $checks[$section][] = [
        'status' => 'pass',
        'title' => 'Session Functionality',
        'detail' => "✓ Sessions working (ID: " . substr(session_id(), 0, 8) . "...)"
    ];
    $passCount++;
} catch (Exception $e) {
    $checks[$section][] = [
        'status' => 'fail',
        'title' => 'Session Functionality',
        'detail' => "✗ Cannot start sessions: " . $e->getMessage()
    ];
    $failCount++;
}

// Check 8: Write Permissions (for logs if needed)
$section = 'Permissions';

$testFile = __DIR__ . '/test_write_' . time() . '.tmp';
$canWrite = @file_put_contents($testFile, 'test');

if ($canWrite !== false) {
    @unlink($testFile);
    $checks[$section][] = [
        'status' => 'pass',
        'title' => 'Root Directory Write Access',
        'detail' => "✓ Can write to application directory"
    ];
    $passCount++;
} else {
    $checks[$section][] = [
        'status' => 'warn',
        'title' => 'Root Directory Write Access',
        'detail' => "⚠ Cannot write to root (logs may not work)"
    ];
    $warnCount++;
}

// Display all checks
foreach ($checks as $sectionName => $sectionChecks) {
    echo "<div class='section'>\n";
    echo "<h2>$sectionName</h2>\n";
    
    foreach ($sectionChecks as $check) {
        $statusClass = $check['status'];
        $icon = $statusClass === 'pass' ? '✓' : ($statusClass === 'fail' ? '✗' : '⚠');
        
        echo "<div class='check-item $statusClass'>\n";
        echo "<div class='icon $statusClass'>$icon</div>\n";
        echo "<div class='check-content'>\n";
        echo "<div class='check-title'>{$check['title']}</div>\n";
        echo "<div class='check-detail'>{$check['detail']}</div>\n";
        echo "</div>\n";
        echo "</div>\n";
    }
    
    echo "</div>\n";
}

// Summary
$totalChecks = $passCount + $failCount + $warnCount;
$allPassed = $failCount === 0;
$summaryClass = $allPassed ? 'success' : 'error';
$summaryIcon = $allPassed ? '✅' : '⚠️';

echo "<div class='summary $summaryClass'>\n";
echo "<h3>$summaryIcon System Check Summary</h3>\n";

if ($allPassed && $warnCount === 0) {
    echo "<p><strong>All checks passed!</strong> CleanPlate is ready to use.</p>\n";
} elseif ($allPassed) {
    echo "<p><strong>Core functionality available.</strong> Some optional features have warnings.</p>\n";
} else {
    echo "<p><strong>Action required.</strong> Please resolve the failed checks above before using CleanPlate.</p>\n";
}

echo "<div class='summary-stats'>\n";
echo "<div class='stat'><strong>✓ Passed:</strong> $passCount</div>\n";
if ($failCount > 0) {
    echo "<div class='stat'><strong>✗ Failed:</strong> $failCount</div>\n";
}
if ($warnCount > 0) {
    echo "<div class='stat'><strong>⚠ Warnings:</strong> $warnCount</div>\n";
}
echo "<div class='stat'><strong>Total:</strong> $totalChecks checks</div>\n";
echo "</div>\n";

if ($allPassed) {
    echo "<a href='../index.html' class='btn'>→ Launch CleanPlate</a>\n";
}

echo "</div>\n";

// Helper function
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

?>

        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #ddd; color: #999; font-size: 0.9rem;">
            <p>System Check completed at <?php echo date('Y-m-d H:i:s'); ?></p>
            <p>Server: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></p>
        </div>
    </div>
</body>
</html>
