# Production Deployment Guide - USMS

Complete guide for deploying USMS to production with all features enabled.

## Table of Contents
1. [Environment Setup](#environment-setup)
2. [M-Pesa Configuration](#m-pesa-configuration)
3. [Email Configuration](#email-configuration)
4. [PDF Export Setup](#pdf-export-setup)
5. [Pre-Deployment Checklist](#pre-deployment-checklist)

---

## Environment Setup

### Step 1: Set APP_ENV to Production

```bash
# In your Railway dashboard or deployment platform:
APP_ENV=production
APP_SECRET=your-very-long-random-secret-key-minimum-32-chars
```

### Step 2: Verify Database Connection

Ensure your database credentials are set:

```bash
# Railway (auto-configured):
MYSQLHOST=your-railway-db-host
MYSQLUSER=your-db-user
MYSQLPASSWORD=your-db-password
MYSQLDATABASE=your-db-name

# OR locally in .env.production:
DB_HOST=your-db-host
DB_USER=your-db-user
DB_PASS=your-db-password
DB_NAME=your-db-name
```

---

## M-Pesa Configuration

### Step 1: Get Your Production Credentials

1. Log in to [M-Pesa Daraja Portal](https://developer.safaricom.co.ke)
2. Go to "My Apps" → Select your app
3. Click "View Details" to get your production credentials
4. You'll need:
   - Consumer Key (MPESA_LIVE_CONSUMER_KEY)
   - Consumer Secret (MPESA_LIVE_CONSUMER_SECRET)
   - Shortcode (MPESA_LIVE_SHORTCODE)
   - Passkey (MPESA_LIVE_PASSKEY)
   - B2C Shortcode (MPESA_LIVE_B2C_SHORTCODE)
   - B2C Security Credential (MPESA_LIVE_B2C_SECURITY_CREDENTIAL)

### Step 2: Configure Environment Variables

Set these in your deployment platform (Railway, Docker, etc.):

```bash
# M-Pesa Production Configuration
MPESA_ENV=production
MPESA_LIVE_BASE_URL=https://api.safaricom.co.ke
MPESA_LIVE_CONSUMER_KEY=your-production-consumer-key
MPESA_LIVE_CONSUMER_SECRET=your-production-consumer-secret
MPESA_LIVE_SHORTCODE=your-production-shortcode
MPESA_LIVE_PASSKEY=your-production-passkey
MPESA_LIVE_CALLBACK_URL=https://your-production-domain/public/member/mpesa_callback.php
MPESA_LIVE_B2C_SHORTCODE=your-production-b2c-shortcode
MPESA_LIVE_B2C_INITIATOR_NAME=your-b2c-initiator-name
MPESA_LIVE_B2C_SECURITY_CREDENTIAL=your-production-b2c-credential
MPESA_LIVE_B2C_TIMEOUT_URL=https://your-production-domain/public/api/b2c_timeout.php
MPESA_LIVE_B2C_RESULT_URL=https://your-production-domain/public/api/b2c_result.php
```

### Step 3: Test M-Pesa STK Push

1. Admin logs in
2. Go to Dashboard
3. Test Payment Gateway
4. Initiate a payment - should show STK push on phone
5. Confirm payment on phone
6. Check admin dashboard for confirmation

### Step 4: Enable M-Pesa B2C (Withdrawal) [Optional]

For member withdrawals to work:

1. Get B2C Security Credential from Daraja Portal
2. Set `MPESA_LIVE_B2C_SECURITY_CREDENTIAL` environment variable
3. Ensure `MPESA_LIVE_B2C_SHORTCODE` is configured

---

## Email Configuration

### Option 1: Gmail (Simple but Limited)

⚠️ **Note**: Gmail has strict rate limits. Not recommended for production.

```bash
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=your-gmail@gmail.com
SMTP_PASSWORD=your-16-char-app-password
SMTP_FROM_NAME=Umoja Drivers Sacco
```

**Steps:**
1. Enable 2-Factor Authentication on Gmail
2. Go to [Gmail App Passwords](https://myaccount.google.com/apppasswords)
3. Select "Mail" and "Windows" (or your OS)
4. Generate a 16-character password
5. Copy and paste as `SMTP_PASSWORD`

### Option 2: SendGrid (Recommended for Production)

SendGrid offers better deliverability and is ideal for production:

```bash
SMTP_HOST=smtp.sendgrid.net
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=apikey
SMTP_PASSWORD=your-sendgrid-api-key
SMTP_FROM_NAME=Umoja Drivers Sacco
```

**Steps:**
1. Sign up at [SendGrid](https://sendgrid.com)
2. Create API Key in Settings → API Keys
3. Set `SMTP_PASSWORD` to your API key
4. Set `SMTP_USERNAME` to `apikey` (literal string)

### Option 3: AWS SES (Enterprise)

AWS SES is highly reliable and cost-effective:

```bash
SMTP_HOST=email-smtp.us-east-1.amazonaws.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=your-ses-smtp-username
SMTP_PASSWORD=your-ses-smtp-password
SMTP_FROM_NAME=Umoja Drivers Sacco
```

**Steps:**
1. Set up AWS SES in your region
2. Verify your sending domain
3. Create SMTP credentials in SES console
4. Whitelist sender email in SES settings

### Option 4: Your Company Mail Server

If you have your own mail server:

```bash
SMTP_HOST=mail.yourdomain.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=noreply@yourdomain.com
SMTP_PASSWORD=your-mail-password
SMTP_FROM_NAME=Umoja Drivers Sacco
```

### Testing Email Configuration

Run the email test:

```bash
php test_email.php
```

Expected output:
```
✓ SMTP Connection Successful
✓ Test Email Sent Successfully
```

---

## PDF Export Setup

PDF exports are already configured with FPDF and DOMPDF.

### Verify PDF Dependencies

```bash
composer install
```

### Test PDF Export

1. Admin Dashboard → Reports
2. Select date range
3. Click "Export PDF"
4. Should download PDF file

### PDF Troubleshooting

**Issue**: PDF exports fail
**Solution**:
```bash
# Ensure memory limit is high enough
php -i | grep memory_limit

# If needed, increase in .env:
PHP_MEMORY_LIMIT=512M
```

---

## Admin Reports Page

### Verify Reports Page

1. Admin logs in
2. Go to Dashboard → Reports
3. Page should load with chart and data
4. Should show:
   - Cash Flow Summary
   - Trends Chart
   - Transaction Distribution
   - Member Statistics

### Troubleshooting

**Issue**: Reports page won't load
**Solution**: Check logs
```bash
tail -f /var/log/usms.log
# Look for database query errors
```

**Issue**: Charts not displaying
**Solution**: 
1. Check JavaScript console for errors
2. Ensure Chart.js is loaded from CDN
3. Check that data API responds correctly

---

## Production Verification Checklist

### Before Going Live

- [ ] **M-Pesa Configuration**
  - [ ] MPESA_ENV set to production
  - [ ] MPESA_LIVE_CONSUMER_KEY set
  - [ ] MPESA_LIVE_CONSUMER_SECRET set
  - [ ] MPESA_LIVE_SHORTCODE set
  - [ ] MPESA_LIVE_PASSKEY set
  - [ ] Test STK push works
  - [ ] Test B2C withdrawal works (if enabled)

- [ ] **Email Configuration**
  - [ ] SMTP_HOST set correctly
  - [ ] SMTP_USERNAME set correctly
  - [ ] SMTP_PASSWORD set correctly
  - [ ] Test email sends successfully
  - [ ] Check email delivery to spam folder

- [ ] **PDF Exports**
  - [ ] Test member statement export
  - [ ] Test admin report export
  - [ ] Test loan export
  - [ ] Test savings export

- [ ] **Admin Reports**
  - [ ] Reports page loads
  - [ ] Charts display correctly
  - [ ] Data refreshes correctly
  - [ ] Export buttons work

- [ ] **Database**
  - [ ] Connection string correct
  - [ ] All tables imported
  - [ ] Test queries run fast

- [ ] **Security**
  - [ ] HTTPS enabled
  - [ ] APP_SECRET is strong (32+ chars)
  - [ ] Database credentials in env (not code)
  - [ ] API keys in env (not code)

---

## Environment Variables Summary

```bash
# Application
APP_ENV=production
APP_SECRET=your-strong-secret-key-32-chars-minimum

# Database
MYSQLHOST=your-db-host
MYSQLUSER=your-db-user
MYSQLPASSWORD=your-db-password
MYSQLDATABASE=your-db-name

# Email
SMTP_HOST=smtp.sendgrid.net
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=apikey
SMTP_PASSWORD=your-sendgrid-api-key
SMTP_FROM_NAME=Umoja Drivers Sacco

# M-Pesa
MPESA_ENV=production
MPESA_LIVE_CONSUMER_KEY=your-consumer-key
MPESA_LIVE_CONSUMER_SECRET=your-consumer-secret
MPESA_LIVE_SHORTCODE=your-shortcode
MPESA_LIVE_PASSKEY=your-passkey
MPESA_LIVE_CALLBACK_URL=https://your-domain/public/member/mpesa_callback.php
MPESA_LIVE_B2C_SHORTCODE=your-b2c-shortcode
MPESA_LIVE_B2C_INITIATOR_NAME=your-initiator
MPESA_LIVE_B2C_SECURITY_CREDENTIAL=your-credential
MPESA_LIVE_B2C_TIMEOUT_URL=https://your-domain/public/api/b2c_timeout.php
MPESA_LIVE_B2C_RESULT_URL=https://your-domain/public/api/b2c_result.php
```

---

## Deployment Steps

### 1. On Railway Dashboard

1. Go to your project
2. Select "Variables" tab
3. Add all environment variables
4. Redeploy your application

### 2. On Docker

```bash
# Create .env.production file with all variables above
docker-compose -f docker-compose.yml --env-file .env.production up -d
```

### 3. On Your Server

```bash
# Copy variables to your server
ssh user@your-server
cp .env.production /path/to/usms/.env

# Restart PHP-FPM
sudo systemctl restart php-fpm
```

### 4. Verify Deployment

```bash
# Check that all systems work:

# 1. Database
curl https://your-domain/test_db.php

# 2. M-Pesa
curl https://your-domain/test_mpesa.php

# 3. Email
curl https://your-domain/test_email.php

# 4. Logs
tail -f /var/log/usms.log
```

---

## Support

If you encounter issues during production deployment:

1. Check the error logs: `/var/log/usms.log`
2. Verify all environment variables are set: `env | grep MPESA`
3. Test each component individually
4. Contact support with error details

---

**Last Updated**: May 12, 2026
**Status**: Production Ready
