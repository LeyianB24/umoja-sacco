# 🔐 USMS Security Hardening - Environment Configuration

## Overview

This document explains the security improvements implemented for the USMS system to prevent credential leakage.

## What Changed

### ✅ Completed Security Fixes

1. **Environment Variable System** (`config/EnvLoader.php`)
   - All sensitive credentials now loaded from `.env.local` file
   - `.env.local` is git-ignored and never committed
   - Provides typed accessors: `get()`, `getBool()`, `getInt()`

2. **Secure Configuration Files**
   - `config/environment.php` - Now loads M-Pesa, email, Paystack credentials from `.env.local`
   - `config/app.php` - Updated database and SMTP settings to use environment variables
   - `config/app_config.php` - Updated SMS, SMTP, and notification settings to use environment variables

3. **Git Protection** (`.gitignore`)
   - ✅ `.env` (all environment files)
   - ✅ `.env.local` (local development credentials)
   - ✅ `.env.*.local` (environment-specific files)
   - ✅ `config/environment.php` (configuration file)
   - ✅ `config/database.php` (database configs)

4. **Template Files**
   - `.env.example` - Template for developers (safe to commit)
   - `.env.local` - Your local development credentials (NOT committed)

---

## ⚠️ CRITICAL: Next Steps (Do This Now!)

### 1. **Rotate All Exposed Credentials**

The following credentials were exposed in git history and should be considered **compromised**:

| Service | Compromise | Action |
|---------|-----------|--------|
| **M-Pesa Sandbox** | Consumer Key, Passkey | ✅ Already done (test keys) |
| **Paystack Test Keys** | Secret & Public Keys | ⚠️ Change - they're in git |
| **Gmail SMTP** | Password `duzb mbqt fnsz ipkg` | 🔴 **CRITICAL** - Change immediately |
| **Email Address** | `leyianbeza24@gmail.com` | Review all API instances |

**Action Required:**
```bash
# 1. Change Gmail password immediately
# 2. Update all Paystack API keys
# 3. Generate new M-Pesa sandbox credentials if possible
# 4. Update .env.local with new credentials
```

### 2. **Clean Git History**

To remove exposed credentials from git history, run:

```bash
# Option A: Using git-filter-branch (careful!)
git filter-branch --tree-filter 'rm -f config/environment.php' HEAD

# Option B: Using BFG (faster, simpler)
bfg --delete-files config/environment.php
bfg --replace-text secrets.txt  # if you create a file with old credentials

# Then force push (use with caution!)
git push origin --force --all
```

### 3. **Verify Setup**

Test that your application loads credentials correctly:

```bash
# Check that .env.local exists and is ignored
ls -la .env.local
git status  # Should NOT show .env.local

# Verify EnvLoader loads correctly
php -r "require 'config/EnvLoader.php'; use USMS\Config\EnvLoader; EnvLoader::load(); echo 'Loaded: ' . count(\$_ENV) . ' env vars';"
```

---

## 📝 Setup Instructions for New Developers

### For Local Development:

1. **Copy the template:**
   ```bash
   cp .env.example .env.local
   ```

2. **Fill in your credentials in `.env.local`:**
   ```bash
   # Edit .env.local with your settings
   # DO NOT commit this file!
   ```

3. **Verify it works:**
   ```bash
   # PHP should load without errors
   php public/index.php
   ```

### For Production Deployment:

1. **Set environment variables on your server:**
   ```bash
   # On AWS/Digital Ocean/etc., set these:
   export APP_ENV=production
   export DB_HOST=prod-db.example.com
   export MPESA_LIVE_CONSUMER_KEY=actual-live-key
   export SMTP_PASSWORD=your-app-password
   # ... etc
   ```

2. **Or create `.env.local` on the server:**
   - Copy `.env.example` to `.env.local`
   - Fill with production credentials
   - Ensure file permissions: `chmod 600 .env.local`
   - Never commit to git

3. **Docker/Container deployment:**
   - Use environment variables only
   - Never embed credentials in Docker images
   - Use Docker secrets/Kubernetes Secrets for sensitive data

---

## 🔍 Verification Checklist

- [ ] `.env.local` file created and populated
- [ ] `.env.local` is in `.gitignore`
- [ ] Gmail password changed (old one was exposed: `duzb mbqt fnsz ipkg`)
- [ ] Paystack test keys rotated (exposed in git)
- [ ] Application still loads correctly (no errors in logs)
- [ ] SMTP emails sending correctly with new password
- [ ] M-Pesa callbacks working (test with sandbox)
- [ ] Git history cleaned (credentials removed from commits)

---

## 📚 File Structure

```
.usms/
├── .env.example           # Template (SAFE to commit) ✅
├── .env.local             # Your credentials (NEVER commit) 🔒
├── .gitignore             # Excludes .env.* and config files 🛡️
├── config/
│   ├── EnvLoader.php      # NEW: Loads .env.local securely
│   ├── environment.php    # Updated: Uses EnvLoader
│   ├── app.php            # Updated: Uses EnvLoader
│   └── app_config.php     # Updated: Uses EnvLoader
└── ... (rest of app)
```

---

## 🚨 Emergency: If Credentials Are Compromised

If you suspect someone accessed your `.env.local` file:

1. **Immediately revoke credentials:**
   - Change Gmail password
   - Regenerate Paystack API keys
   - Generate new M-Pesa credentials (if possible)

2. **Audit git repository:**
   - Check `git log --all -- .env.local` (should be empty or only contain the new content)
   - Search for exposed keys in commit history

3. **Clean build on production:**
   - Pull fresh code
   - Regenerate `.env.local` with new credentials
   - Restart services

---

## 🔗 Related Security Topics

- [OWASP: Sensitive Data Exposure](https://owasp.org/www-project-top-ten/)
- [12-Factor App: Configuration](https://12factor.net/config)
- [Environment Variables Best Practices](https://github.com/motdotla/dotenv)

---

## 🛡️ Advanced Security Features

### Session Security Configuration

```php
// In your application bootstrap (e.g., public/index.php)
require 'config/EnvLoader.php';
use USMS\Config\EnvLoader;

// Apply secure session settings
EnvLoader::configureSession();
```

This ensures:
- ✅ Sessions are httponly (no JavaScript access)
- ✅ Secure flag for HTTPS connections in production
- ✅ SameSite=Strict for CSRF protection
- ✅ 1-hour session timeout

### HTTPS Enforcement

```php
// In production, enforce HTTPS redirection
EnvLoader::enforceHttps();  // Redirects HTTP → HTTPS
```

Adds HSTS header to enforce HTTPS for future requests.

### Security Headers

```php
// Add security headers to prevent common attacks
EnvLoader::addSecurityHeaders();
```

Protects against:
- **MIME Type Sniffing** - X-Content-Type-Options
- **XSS Attacks** - X-XSS-Protection
- **Clickjacking** - X-Frame-Options
- **Content Injection** - Content-Security-Policy
- **Referrer Leaking** - Referrer-Policy

### Required Environment Variables Validation

```php
// Ensure critical vars are set before running application
EnvLoader::requireAll([
    'APP_ENV',
    'APP_SECRET',
    'DB_HOST',
    'DB_USER',
    'DB_NAME',
    'SMTP_HOST',
    'SMTP_USERNAME',
    'SMTP_PASSWORD'
]);
```

Throws `RuntimeException` if any required vars are missing.

---

## Questions?

If you encounter issues:
1. Check that `.env.local` exists and has correct permissions
2. Verify EnvLoader is loading: `php config/EnvLoader.php`
3. Check application logs for errors
4. Ensure all required environment variables are set

**Last Updated:** 2026-04-03
