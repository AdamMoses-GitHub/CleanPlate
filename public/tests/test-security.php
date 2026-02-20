#!/usr/bin/env php
<?php
/**
 * Security Validation Test Suite
 * Tests security fixes applied to CleanPlate
 * 
 * Usage:
 *   CLI: php test-security.php
 *   Web: http://localhost:8080/tests/test-security.php
 */

// Detect execution mode
$isCLI = php_sapi_name() === 'cli';

// Set headers for web mode
if (!$isCLI) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Validation Test Suite</title>
    <style>
        body { font-family: "Courier New", monospace; background: #1e1e1e; color: #d4d4d4; padding: 2rem; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; }
        .pass { color: #4ec9b0; }
        .fail { color: #f48771; }
        pre { background: #252526; padding: 1rem; border-radius: 4px; overflow-x: auto; }
        a { color: #4fc1ff; }
    </style>
</head>
<body>
<div class="container">
<pre>';
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "  CleanPlate Security Validation\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$passed = 0;
$failed = 0;

// Test 1: SSRF Protection - Localhost
echo "Test 1: SSRF Protection (localhost)\n";
$result = testUrl('http://localhost/admin');
if ($result['blocked']) {
    echo "  ✓ PASS: localhost blocked\n";
    $passed++;
} else {
    echo "  ✗ FAIL: localhost not blocked\n";
    $failed++;
}
echo "\n";

// Test 2: SSRF Protection - 127.0.0.1
echo "Test 2: SSRF Protection (127.0.0.1)\n";
$result = testUrl('http://127.0.0.1/internal');
if ($result['blocked']) {
    echo "  ✓ PASS: 127.0.0.1 blocked\n";
    $passed++;
} else {
    echo "  ✗ FAIL: 127.0.0.1 not blocked\n";
    $failed++;
}
echo "\n";

// Test 3: SSRF Protection - Private IP
echo "Test 3: SSRF Protection (192.168.1.1)\n";
$result = testUrl('http://192.168.1.1/router');
if ($result['blocked']) {
    echo "  ✓ PASS: Private IP blocked\n";
    $passed++;
} else {
    echo "  ✗ FAIL: Private IP not blocked\n";
    $failed++;
}
echo "\n";

// Test 4: SSRF Protection - Link-local
echo "Test 4: SSRF Protection (169.254.1.1)\n";
$result = testUrl('http://169.254.1.1/metadata');
if ($result['blocked']) {
    echo "  ✓ PASS: Link-local IP blocked\n";
    $passed++;
} else {
    echo "  ✗ FAIL: Link-local IP not blocked\n";
    $failed++;
}
echo "\n";

// Test 5: Valid URL should not be blocked
echo "Test 5: Valid URL Allowed (example.com)\n";
$result = testUrl('https://example.com/recipe');
if (!$result['blocked']) {
    echo "  ✓ PASS: Valid URL allowed\n";
    $passed++;
} else {
    echo "  ✗ FAIL: Valid URL incorrectly blocked\n";
    $failed++;
}
echo "\n";

// Test 6: URL Length Validation
echo "Test 6: URL Length Validation\n";
$longUrl = 'https://example.com/' . str_repeat('a', 3000);
$result = testUrl($longUrl);
if ($result['blocked']) {
    echo "  ✓ PASS: Overly long URL rejected\n";
    $passed++;
} else {
    echo "  ✗ FAIL: Long URL not validated\n";
    $failed++;
}
echo "\n";

// Test 7: Invalid URL Scheme
echo "Test 7: Invalid URL Scheme\n";
$result = testUrl('ftp://example.com/recipe');
if ($result['blocked']) {
    echo "  ✓ PASS: FTP scheme blocked\n";
    $passed++;
} else {
    echo "  ✗ FAIL: FTP scheme allowed\n";
    $failed++;
}
echo "\n";

// Test 8: Check Security Headers
echo "Test 8: Security Headers Present\n";
$headers = checkSecurityHeaders();
$requiredHeaders = [
    'X-Content-Type-Options' => 'nosniff',
    'X-Frame-Options' => 'DENY',
    'X-XSS-Protection' => '1; mode=block',
];

$headersPassed = true;
foreach ($requiredHeaders as $header => $expectedValue) {
    if (!isset($headers[$header])) {
        echo "  ✗ Missing header: $header\n";
        $headersPassed = false;
    } elseif (stripos($headers[$header], $expectedValue) === false) {
        echo "  ✗ Incorrect header: $header = {$headers[$header]}\n";
        $headersPassed = false;
    }
}

if ($headersPassed) {
    echo "  ✓ PASS: All security headers present\n";
    $passed++;
} else {
    $failed++;
}
echo "\n";

// Results
echo "═══════════════════════════════════════════════════════════════\n";
echo "  Test Results\n";
echo "═══════════════════════════════════════════════════════════════\n";
echo "  Passed: $passed\n";
echo "  Failed: $failed\n";
$total = $passed + $failed;
$percentage = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
echo "  Success Rate: $percentage%\n";
echo "═══════════════════════════════════════════════════════════════\n";

// Close HTML for web mode
if (!$isCLI) {
    echo '</pre>
<p><a href="index.html">← Back to Test Suite</a></p>
</div>
</body>
</html>';
}

exit($failed > 0 ? 1 : 0);

/**
 * Test URL validation logic
 */
function testUrl($url) {
    $ch = curl_init('http://localhost:8080/parser.php');
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['url' => $url]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 5,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    // Check if blocked (403 or 400 error)
    $blocked = false;
    if ($httpCode === 403 || $httpCode === 400) {
        $blocked = true;
    }
    
    if (isset($data['status']) && $data['status'] === 'error') {
        if (isset($data['code']) && in_array($data['code'], ['FORBIDDEN', 'INVALID_URL'])) {
            $blocked = true;
        }
    }
    
    return [
        'blocked' => $blocked,
        'httpCode' => $httpCode,
        'response' => $data,
    ];
}

/**
 * Check security headers
 */
function checkSecurityHeaders() {
    $ch = curl_init('http://localhost:8080/parser.php');
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => false,
        CURLOPT_CUSTOMREQUEST => 'OPTIONS',
        CURLOPT_TIMEOUT => 5,
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $headers = [];
    $headerLines = explode("\r\n", $response);
    
    foreach ($headerLines as $line) {
        if (strpos($line, ':') !== false) {
            list($key, $value) = explode(':', $line, 2);
            $headers[trim($key)] = trim($value);
        }
    }
    
    return $headers;
}
