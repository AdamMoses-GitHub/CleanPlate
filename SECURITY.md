# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.0.x   | :white_check_mark: |

## Reporting a Vulnerability

If you discover a security vulnerability in CleanPlate, please report it responsibly:

### ✉️ Contact

**Email:** [Your security contact email]  
**Subject:** `[SECURITY] CleanPlate Vulnerability Report`

### 📋 What to Include

1. **Description** - Clear description of the vulnerability
2. **Steps to Reproduce** - Detailed steps to reproduce the issue
3. **Impact** - Potential security impact and severity
4. **Affected Versions** - Which versions are affected
5. **Proof of Concept** - Code or screenshots demonstrating the issue
6. **Suggested Fix** - Optional: Your suggested remediation

### ⏱️ Response Timeline

- **Initial Response:** Within 48 hours
- **Status Update:** Within 7 days
- **Fix Target:** Critical issues within 14 days, others within 30 days

### 🛡️ Security Measures

CleanPlate implements several security controls:

- **SSL/TLS Verification** - Enforced certificate validation
- **SSRF Protection** - Comprehensive internal IP blocking
- **Input Validation** - URL scheme, length, and format validation
- **XSS Prevention** - All user-controlled output is HTML-escaped
- **Security Headers** - X-Frame-Options, CSP, X-XSS-Protection, etc.
- **Rate Limiting** - Request throttling to prevent abuse
- **Error Sanitization** - Production logs exclude sensitive details

### 🔍 Recent Security Audits

- **February 6, 2026** - Comprehensive security audit completed
  - See [SECURITY-AUDIT.md](SECURITY-AUDIT.md) for full report
  - See [SECURITY-FIXES.md](SECURITY-FIXES.md) for applied fixes

### 🚫 Out of Scope

The following are **not** considered security vulnerabilities:

- Bot detection by recipe websites (intended behavior)
- Missing support for specific recipe sites
- SSL certificate errors from target websites
- Rate limiting preventing legitimate high-volume use
- Cloudflare or other WAF blocking (cannot be bypassed)
- JavaScript-required sites (fundamental limitation)

### 🎖️ Recognition

Security researchers who responsibly disclose vulnerabilities will be:

- Credited in release notes (unless you prefer to remain anonymous)
- Listed in our security hall of fame
- Sent a thank you note

### 📚 Security Resources

- **Security Audit:** [SECURITY-AUDIT.md](SECURITY-AUDIT.md)
- **Security Fixes:** [SECURITY-FIXES.md](SECURITY-FIXES.md)
- **Test Suite:** `php test-security.php`
- **Configuration Guide:** See "Production Checklist" in README.md

### ⚠️ Disclaimer

This project is provided "as is" without warranty. Users are responsible for:

- Complying with website Terms of Service
- Respecting robots.txt files
- Following rate limits and not abusing target websites
- Ensuring legal compliance in their jurisdiction

---

**Last Updated:** February 6, 2026
