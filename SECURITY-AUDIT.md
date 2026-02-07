# Security Audit Report - CleanPlate

**Date:** February 6, 2026  
**Severity Levels:** 🔴 Critical | 🟠 High | 🟡 Medium | 🔵 Low

---

## 🔴 CRITICAL VULNERABILITIES

### 1. SSL Certificate Verification Disabled
**File:** `includes/RecipeParser.php:111`  
**Issue:** `CURLOPT_SSL_VERIFYPEER => false` disables SSL certificate validation  
**Risk:** Man-in-the-Middle (MITM) attacks, credential theft, data interception  
**Impact:** Attacker can intercept and modify recipe data, inject malicious content

```php
// VULNERABLE CODE:
CURLOPT_SSL_VERIFYPEER => false,  // Many recipe sites have cert issues
```

**Fix:**
```php
CURLOPT_SSL_VERIFYPEER => true,
CURLOPT_SSL_VERIFYHOST => 2,
// Handle cert errors gracefully in exception handling instead
```

---

## 🟠 HIGH SEVERITY VULNERABILITIES

### 2. SSRF Protection Bypass
**File:** `parser.php:79-82`  
**Issue:** Inadequate SSRF protection using `gethostbyname()`  
**Risk:** Can be bypassed via DNS rebinding, doesn't block all internal ranges  
**Attack Vectors:**
- DNS rebinding attacks
- IPv6 loopback (::1)
- localhost variations (127.0.0.1-127.255.255.255)
- Link-local addresses (169.254.0.0/16, fe80::/10)
- IPv6 private ranges

```php
// VULNERABLE CODE:
$ip = gethostbyname($parsedUrl['host']);
if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
    respondWithError(403, 'FORBIDDEN', 'Cannot access internal or private network addresses.');
}
```

**Fix:** Implement comprehensive SSRF protection:
```php
function isInternalIP($host) {
    // Resolve all IPs (IPv4 and IPv6)
    $ips = array_merge(
        @gethostbynamel($host) ?: [],
        @dns_get_record($host, DNS_AAAA) ?: []
    );
    
    foreach ($ips as $ip) {
        // Block private, reserved, and loopback ranges
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }
        
        // Additional checks for edge cases
        if (preg_match('/^(127\.|169\.254\.|::1|fe80:)/i', $ip)) {
            return true;
        }
    }
    
    return false;
}
```

### 3. Overly Permissive CORS Policy
**File:** `parser.php:36`  
**Issue:** `Access-Control-Allow-Origin: *` allows any domain  
**Risk:** API can be abused from any malicious website  

**Fix:**
```php
// Whitelist specific domains in production
$allowedOrigins = ['https://yourdomain.com', 'https://www.yourdomain.com'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
}
```

---

## 🟡 MEDIUM SEVERITY VULNERABILITIES

### 4. Rate Limiting Bypass
**File:** `parser.php:90-109`  
**Issue:** Session-based rate limiting can be bypassed by clearing cookies  
**Risk:** API abuse, DoS attacks  

**Fix:** Implement IP-based rate limiting:
```php
// Use IP address + user agent hash for rate limiting
$identifier = hash('sha256', $_SERVER['REMOTE_ADDR'] . $_SERVER['HTTP_USER_AGENT']);
// Store in database or Redis for production
```

### 5. Information Disclosure in Error Logs
**File:** `parser.php:124-125`  
**Issue:** Full stack traces logged with sensitive information  

**Fix:**
```php
// Log sanitized errors only
error_log('Recipe parsing error for domain: ' . parse_url($url, PHP_URL_HOST));
// Don't log full stack traces in production
if (getenv('APP_ENV') !== 'production') {
    error_log('Stack trace: ' . $e->getTraceAsString());
}
```

### 6. Missing Security Headers
**File:** `parser.php` (missing)  
**Issue:** No CSP, X-Frame-Options, etc.  

**Fix:**
```php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\'; style-src \'self\' fonts.googleapis.com');
```

### 7. Insecure Cookie File Storage
**File:** `includes/RecipeParser.php:100-101`  
**Issue:** Predictable cookie file names in shared temp directory  

**Fix:**
```php
$this->cookieFile = sys_get_temp_dir() . '/cleanplate_' . bin2hex(random_bytes(16)) . '.txt';
// Clean up cookie file after request
register_shutdown_function(function() {
    if ($this->cookieFile && file_exists($this->cookieFile)) {
        @unlink($this->cookieFile);
    }
});
```

### 8. No Input Length Validation
**File:** `parser.php:60`  
**Issue:** URL length not validated, could cause buffer issues  

**Fix:**
```php
if (strlen($url) > 2048) {
    respondWithError(400, 'INVALID_URL', 'URL is too long (max 2048 characters).');
}
```

---

## 🔵 LOW SEVERITY ISSUES

### 9. No CSRF Protection
**Note:** API is stateless but session-based rate limiting introduces state  
**Recommendation:** Add CSRF tokens if adding authentication

### 10. Deprecated Session Storage
**File:** `includes/RecipeParser.php:806-812`  
**Issue:** $_SESSION usage in stateless API  
**Recommendation:** Migrate to Redis/Memcached for scalability

---

## RECOMMENDED FIXES PRIORITY

1. **Immediate (Critical):**
   - [ ] Enable SSL certificate verification
   - [ ] Enhance SSRF protection
   
2. **High Priority (This Week):**
   - [ ] Restrict CORS policy
   - [ ] Implement IP-based rate limiting
   - [ ] Add security headers
   
3. **Medium Priority (This Month):**
   - [ ] Secure cookie file storage
   - [ ] Sanitize error logging
   - [ ] Add input length validation
   
4. **Low Priority (Future):**
   - [ ] Add CSRF protection if needed
   - [ ] Migrate to Redis for rate limiting

---

## TESTING CHECKLIST

- [ ] Test SSL verification with self-signed certs
- [ ] Attempt DNS rebinding attacks
- [ ] Test rate limiting bypass with multiple IPs
- [ ] Verify CORS policy with different origins
- [ ] Check error messages don't leak sensitive info
- [ ] Test with various malicious URLs
- [ ] Verify XSS escaping in all outputs

---

## SECURE CONFIGURATION

### Production Environment Variables
```bash
APP_ENV=production
CONFIDENCE_DEBUG=0
error_reporting=E_ALL & ~E_DEPRECATED & ~E_STRICT
display_errors=0
log_errors=1
```

### Recommended php.ini Settings
```ini
expose_php=Off
session.cookie_httponly=1
session.cookie_secure=1
session.use_strict_mode=1
session.cookie_samesite=Strict
```
