# FileZilla Deployment to Free Hosting - Complete Guide

**Purpose**: Deploy USMS to free hosting using FileZilla (FTP/SFTP)  
**Target Audience**: Demo/testing environment  
**Created**: May 7, 2026  
**Status**: Ready to execute

---

## 📋 Prerequisites

### Step 1: Choose a Free Hosting Provider

**Recommended Options** (PHP 8.2+ with MySQL):

| Host | PHP | MySQL | Storage | Upload Method | Free Tier |
|------|-----|-------|---------|----------------|-----------|
| **Infinityfree** | 8.0+ | 5.7 | 5GB | FTP | Forever free |
| **000webhost** | 8.0+ | 5.7 | 1GB | FTP/Admin | Forever free |
| **Railway** | 8.2+ | Latest | 5GB | GitHub/CLI | ~$5 free/month |
| **Render** | 8.2+ | PostgreSQL | 0.5GB | GitHub | Limited free |

**Best Choice**: **Infinityfree** or **000webhost** (simplest FTP, no credit card)

---

## 🚀 Complete Deployment Workflow

### Phase 1: Host Registration & Setup

#### 1.1 Register on Free Hosting (Example: Infinityfree)
1. Go to **infinityfree.net** or **000webhost.com**
2. Sign up with email
3. Create new account (free plan)
4. Choose domain: `your-sacco.infinityfree.net` or upload to existing domain
5. **Activate account** (check email confirmation)

#### 1.2 Get FTP Credentials
- After activation, go to **Account Dashboard**
- Look for **FTP Credentials** or **File Manager**
- Note these details:
  ```
  FTP Host: [something like ftpupload.net or files.000webhost.com]
  FTP Username: [your_username_1234]
  FTP Password: [auto-generated or set by you]
  Remote Path: /public_html/  (usually default)
  ```
- Save these in a text file for reference

#### 1.3 Create Remote MySQL Database
- In Dashboard, find **MySQL** or **Database Manager**
- Create new database:
  ```
  Database Name: usms_live (or similar)
  Username: [auto-generated like u123456_usms]
  Password: [set a strong password]
  ```
- Copy credentials to your notes

#### 1.4 Import Database Schema
- In Dashboard, find **phpMyAdmin** link
- Log in with credentials from Step 1.3
- Select your database (`usms_live`)
- Go to **Import** tab
- Upload [schema.sql](../database/schema.sql) from your local machine
- Click **Go**
- **Result**: Tables created successfully ✓

---

### Phase 2: FileZilla Setup & Connection

#### 2.1 Install FileZilla (if not already installed)
- Download: https://filezilla-project.org/
- Install for your OS (Windows/Mac/Linux)
- Open FileZilla

#### 2.2 Add New Site in FileZilla
1. **File Menu** → **Site Manager** (or press `Ctrl+S`)
2. Click **New Site** button
3. Fill in credentials:
   ```
   Site Name: USMS Production
   Protocol: FTP (or SFTP if host supports)
   Host: [FTP host from Step 1.2]
   Port: 21 (FTP) or 22 (SFTP)
   Logon Type: Normal
   User: [FTP username from Step 1.2]
   Password: [FTP password from Step 1.2]
   ```
4. **Advanced Tab**:
   - Remote Path: `/public_html/` (verify this is correct)
   - Transfer Mode: **Binary**
5. Click **Connect**
6. **First time only**: Accept host key if prompted

#### 2.3 Verify Connection
- Left pane: Your local files should appear
- Right pane: Remote `/public_html/` directory (should be empty or near-empty)
- **Status bar**: `Connected` message

---

### Phase 3: File Upload Strategy

#### 3.1 Organize Local Files for Upload

**DO UPLOAD** (required):
```
admin/                    # Admin pages and logic
member/                   # Member pages
public/                   # Public assets (CSS, JS, images)
config/                   # Database & app configuration
core/                     # Core classes (Services, Repositories, etc.)
inc/                      # Utility functions (auth, email, helpers)
index.php                 # Entry point
.env.local (modified)     # Environment config (see 3.3)
composer.json             # For reference (optional)
```

**SKIP UPLOADING** (not needed on remote):
```
vendor/                   # Will be installed on server
tests/                    # Not needed in production
backups/                  # Keep local only
docker/                   # Development only
```

#### 3.2 Create .env.production File Locally

Create new file: `c:\xampp\htdocs\usms\.env.production`

```env
# =============================================================================
# USMS PRODUCTION ENVIRONMENT (FREE HOSTING)
# =============================================================================

# APPLICATION
APP_ENV=production
APP_SECRET=RE+MQYoU29b3VqqjO4XFv4cYGJUk3IIk0XL/QsSUoc4=

# DATABASE (from Step 1.3)
DB_HOST=[your-host.remotemysql.com or freemysqlhosting.net]
DB_USER=[u123456_usms or whatever assigned]
DB_PASS=[strong password you set]
DB_NAME=[usms_live or database name]
DB_CHARSET=utf8mb4

# EMAIL (Gmail with App Password)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=your-sacco-email@gmail.com
SMTP_PASSWORD=[Gmail App Password - see setup below]
SMTP_FROM_NAME=Umoja Drivers Sacco

# M-PESA (Keep Sandbox for testing, or switch to Live)
MPESA_SANDBOX_BASE_URL=https://sandbox.safaricom.co.ke
MPESA_SANDBOX_CONSUMER_KEY=GHIhl6RDankAZKubSkAg6EFBZrt5qZHzLH4HTGVvqOpadEK9
MPESA_SANDBOX_CONSUMER_SECRET=jb3WEDJ2zDvBGG0U43VM7B9XyZdRAFrWVS4r3CjV9OLkikqnEVEZBpKknq45e3gp
MPESA_SANDBOX_SHORTCODE=174379
MPESA_SANDBOX_PASSKEY=bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919
MPESA_SANDBOX_CALLBACK_URL=https://your-domain.infinityfree.net/usms/public/member/mpesa_callback.php
MPESA_SANDBOX_B2C_SHORTCODE=600981
MPESA_SANDBOX_B2C_INITIATOR_NAME=testapi
MPESA_SANDBOX_B2C_SECURITY_CREDENTIAL=[from your M-PESA sandbox]
MPESA_SANDBOX_B2C_TIMEOUT_URL=https://your-domain.infinityfree.net/usms/public/api/b2c_timeout.php
MPESA_SANDBOX_B2C_RESULT_URL=https://your-domain.infinityfree.net/usms/public/api/b2c_result.php

# PRODUCTION M-PESA (leave empty until ready)
MPESA_LIVE_BASE_URL=https://api.safaricom.co.ke
```

**Action**: Replace placeholders with your actual values

#### 3.3 Upload Files via FileZilla

1. **In FileZilla - Left Panel (Local)**:
   - Navigate to `c:\xampp\htdocs\usms`

2. **In FileZilla - Right Panel (Remote)**:
   - Should show `/public_html/`
   - If empty, you're ready to upload

3. **Upload Core Directories**:
   ```
   Right-click admin/ → Upload
   Right-click member/ → Upload
   Right-click public/ → Upload
   Right-click config/ → Upload
   Right-click core/ → Upload
   Right-click inc/ → Upload
   ```

4. **Upload Individual Files**:
   ```
   index.php → Upload
   .env.production → Upload (rename to .env after upload)
   ```

5. **Monitor Upload Progress**:
   - Bottom panel shows transfer queue
   - Green checkmark = uploaded successfully
   - Typical time: 2-5 minutes (depends on connection)

6. **Verify Upload**:
   - Right-click on remote `/public_html/` → Refresh
   - Should see: `admin/`, `member/`, `public/`, `config/`, `core/`, `inc/`, `index.php`

---

### Phase 4: Server Configuration

#### 4.1 Install Composer Dependencies on Server

**Via SSH** (if host allows):
```bash
cd public_html
curl -sS https://getcomposer.org/installer | php
php composer.phar install --no-dev
```

**If no SSH access**:
- Many free hosts provide **one-click installers** for Composer
- Check Dashboard → Tools → Package Managers
- Or manually download pre-built vendor from local: compress vendor.zip, upload, unzip on server

#### 4.2 Create Required Directories

Via **File Manager** (if no SSH):
- Dashboard → File Manager
- In `/public_html/`:
  - Create `storage/` folder
  - Create `storage/cache/` subfolder
  - Create `uploads/` folder
  - Create `backups/` folder
  - Set permissions: `755` for all

#### 4.3 Rename .env File

**Via File Manager**:
- Right-click `.env.production` → **Rename**
- Change to `.env`

**Via SSH**:
```bash
mv .env.production .env
```

#### 4.4 Set File Permissions

**Critical for security**:
- `.env` → `600` (read/write owner only)
- `config/` → `755`
- `storage/` → `755`
- Other files → `644`

Via File Manager (right-click → Permissions):
```
.env: 600
config/, storage/, uploads/: 755
Everything else: 644
```

---

### Phase 5: Test Deployment

#### 5.1 Access Your Site
```
URL: https://your-domain.infinityfree.net/usms/
Login Page: https://your-domain.infinityfree.net/usms/login.php
```

#### 5.2 Test Login
- **Admin**: Use credentials created during schema import
  - If no admin user exists yet, use phpMyAdmin to insert one:
    ```sql
    INSERT INTO admin (email, password, name, status) 
    VALUES ('admin@usms.local', SHA2('password123', 256), 'Admin', 'active');
    ```
- **Member**: Register new member on login page

#### 5.3 Test Database Connectivity
- Dashboard should load without errors
- Members table should display
- Navigate to **Admin** → **Manage Members**
- Should see database records

#### 5.4 Test File Uploads
- Try member avatar upload
- Check in FileZilla: Right-click → Refresh
- File should appear in `uploads/`

#### 5.5 Check Error Logs
- Dashboard → File Manager
- Enable error logging: In `config/app.php`:
  ```php
  'debug' => false,
  'log_errors' => true,
  'log_path' => 'storage/logs/',
  ```
- Check `storage/logs/` for any issues

---

### Phase 6: Production Adjustments

#### 6.1 Update Configuration
Edit `.env` via FileZilla text editor:

```env
APP_ENV=production                    # No debug mode
SMTP_PASSWORD=[update to real app password]
MPESA_SANDBOX_CALLBACK_URL=[your real domain]
```

#### 6.2 Optional: Switch M-PESA to Production
Update `.env`:
```env
# Comment out sandbox
# MPESA_SANDBOX_BASE_URL=...

# Uncomment and configure live
MPESA_LIVE_BASE_URL=https://api.safaricom.co.ke
MPESA_LIVE_CONSUMER_KEY=[get from M-PESA portal]
MPESA_LIVE_CONSUMER_SECRET=[get from M-PESA portal]
```

#### 6.3 Enable HTTPS
Most free hosts provide free SSL certificates (Let's Encrypt).
- Dashboard → SSL/TLS Certificates
- Click **Install** (usually auto-enabled)
- Update `.env` callback URLs to `https://`

---

## 🔧 Common Free Hosting Issues & Fixes

| Issue | Cause | Fix |
|-------|-------|-----|
| "500 Internal Server Error" | PHP version mismatch or missing extension | Contact host; request PHP 8.2 + xml, json extensions |
| "Can't connect to MySQL" | Wrong hostname or credentials | Test in phpMyAdmin; update `.env` DB_HOST exactly |
| "Vendor folder not found" | Composer not installed | Run `composer install` or download pre-built vendor.zip |
| "Permission denied" on .env | File permissions wrong | Set .env to 644 (or 600 for security) |
| "Uploaded file missing" | Incomplete FTP transfer | Re-upload; verify green checkmarks in FileZilla |
| "Email not sending" | SMTP misconfigured | Test SMTP in phpMyAdmin; verify Gmail app password |
| "Callback fails" | Old ngrok URL in production | Update M-PESA callbacks in Dashboard (Safaricom portal) |

---

## 📞 Free Host Support

| Host | Support | SSH Access | Helpful? |
|------|---------|-----------|----------|
| **Infinityfree** | Forum + Email (slow) | Limited | Medium |
| **000webhost** | Live chat | No | High |
| **Railway** | Docs + Discord | SSH | Very High |

---

## ⚠️ Limitations to Expect

1. **Uptime**: ~99.9% (not 99.99%); occasional downtime
2. **CPU/Bandwidth**: Throttled during high traffic (mutual users on same server)
3. **Database**: Shared MySQL server; may be slow during peak hours
4. **Backups**: Limited automated backups; **BACKUP MANUALLY WEEKLY**
5. **Email**: May be rate-limited (10-20/hour); use queue manager
6. **Storage**: Capped at 5GB; monitor `/storage/` folder size

---

## ✅ Checklist Before Going Live

- [ ] Host account created & activated
- [ ] Database imported via phpMyAdmin
- [ ] FTP credentials saved & tested
- [ ] FileZilla connected successfully
- [ ] All required files uploaded
- [ ] `.env` file renamed from `.env.production`
- [ ] Permissions set (755 for folders, 644 for files)
- [ ] Composer dependencies installed
- [ ] Site loads at `https://your-domain.infinityfree.net/usms/`
- [ ] Admin can log in
- [ ] Member can register & log in
- [ ] Database queries work (check Members table)
- [ ] File uploads work (avatar test)
- [ ] HTTPS enabled
- [ ] Error logs monitored

---

## 🆘 Troubleshooting

### Step 1: Check File Uploads
```
FileZilla → Right-click /public_html/ → Refresh
Verify: admin/, member/, public/, config/ exist
```

### Step 2: Check Database
```
Dashboard → phpMyAdmin → Select usms_live database
Run: SELECT COUNT(*) FROM members;
Should return a number (0 if no members added yet)
```

### Step 3: Check Logs
```
FileZilla → Double-click storage/logs/
Open latest .log file
Look for error messages
```

### Step 4: Test Local Backup
```
If remote fails, verify local still works:
XAMPP → http://localhost/usms
Should load normally
```

---

## 📝 Final Notes

- **This is a DEMO environment**, not production-grade
- **Backup daily** (free hosting backups are unreliable)
- **Don't store sensitive data** (use only sandbox M-PESA initially)
- **Monitor uptime** - free hosts can go down without warning
- **Upgrade to Azure** when ready for serious production use

---

**Questions?** Check your host's documentation or contact their support team.
