# FileZilla Free Hosting Deployment - Quick Checklist

**Deployment Date**: _______________  
**Hosting Provider**: _______________  
**Domain**: _______________  

---

## ✅ Phase 1: Host Registration (5 min)

- [ ] Created account on free hosting provider (Infinityfree, 000webhost, etc.)
- [ ] Confirmed email and activated account
- [ ] Selected/created domain name
- [ ] Noted FTP credentials:
  ```
  FTP Host: ________________
  FTP Username: ________________
  FTP Password: ________________
  ```
- [ ] Created MySQL database
- [ ] Noted database credentials:
  ```
  DB Host: ________________
  DB Username: ________________
  DB Password: ________________
  DB Name: ________________
  ```

---

## ✅ Phase 2: Local Preparation (10 min)

- [ ] Located local files at: `c:\xampp\htdocs\usms`
- [ ] Opened `.env.production` file (created automatically)
- [ ] Filled in `.env.production` with values from Phase 1:
  - DB_HOST, DB_USER, DB_PASS, DB_NAME
  - SMTP credentials (Gmail app password)
  - YOUR_DOMAIN in M-PESA callbacks
- [ ] Verified `.env.production` saved locally (DO NOT UPLOAD YET)
- [ ] Copied credentials to secure location (password manager, etc.)

---

## ✅ Phase 3: Database Setup (5 min)

- [ ] Opened host's phpMyAdmin
- [ ] Selected newly created database
- [ ] Went to Import tab
- [ ] Uploaded `database/schema.sql` from local machine
- [ ] Clicked Go/Execute
- [ ] **Result**: Tables created successfully ✓
- [ ] Verified tables exist: members, admin, transactions, etc.

---

## ✅ Phase 4: FileZilla Connection (5 min)

- [ ] Installed FileZilla (if not already installed)
- [ ] Opened FileZilla
- [ ] File → Site Manager
- [ ] Created new site with details:
  - Protocol: FTP (or SFTP if available)
  - Host: [FTP Host from Phase 1]
  - User: [FTP Username]
  - Password: [FTP Password]
  - Remote Path: `/public_html/`
- [ ] Clicked Connect
- [ ] **Result**: Connected successfully (status shows "Connected") ✓
- [ ] Verified remote `/public_html/` directory visible on right panel

---

## ✅ Phase 5: File Upload (15-30 min)

**Upload these folders** (right-click → Upload):
- [ ] `admin/`
- [ ] `member/`
- [ ] `public/`
- [ ] `config/`
- [ ] `core/`
- [ ] `inc/`

**Upload these files** (right-click → Upload):
- [ ] `index.php`
- [ ] `.env.production` (will rename after upload)

**Verify uploads completed**:
- [ ] All files show green checkmarks in FileZilla queue
- [ ] Right-click `/public_html/` → Refresh
- [ ] All 7 items visible on remote side (6 folders + index.php)

---

## ✅ Phase 6: Server Configuration (10 min)

**Via SSH (if available)** or **File Manager**:
- [ ] Created folders:
  - [ ] `storage/`
  - [ ] `storage/cache/`
  - [ ] `uploads/`
  - [ ] `backups/`
- [ ] Set folder permissions to `755`
- [ ] Set file permissions to `644` (or `600` for `.env`)

**Rename .env file**:
- [ ] In FileZilla (or File Manager), right-click `.env.production`
- [ ] Renamed to `.env`

**Install Composer** (if not pre-installed):
- [ ] Via SSH: `composer install --no-dev`
- [ ] OR: Download pre-built vendor.zip, uploaded, unzipped on server

---

## ✅ Phase 7: Site Test (10 min)

- [ ] Opened browser
- [ ] Navigated to: `https://YOUR_DOMAIN/usms/`
- [ ] **Result**: Login page loads without errors ✓
- [ ] Clicked on Register
- [ ] Created test member account
- [ ] **Result**: No database errors ✓
- [ ] Logged in as test member
- [ ] **Result**: Dashboard loads ✓
- [ ] Checked Admin login (if admin user exists)
- [ ] **Result**: Admin dashboard loads ✓

---

## ✅ Phase 8: Production Configuration (5 min)

- [ ] Verified `.env` has:
  - APP_ENV=production ✓
  - Correct DB credentials ✓
  - SMTP_PASSWORD set ✓
  - MPESA_SANDBOX_CALLBACK_URL updated with domain ✓
- [ ] Checked HTTPS is enabled:
  - [ ] Site accessible via `https://` not just `http://`
  - [ ] SSL certificate installed (usually auto on free hosts)
- [ ] Monitored error logs:
  - [ ] No PHP errors in `storage/logs/`
  - [ ] No database connection errors

---

## ⚠️ Common Issues & Quick Fixes

| Issue | Check | Fix |
|-------|-------|-----|
| "Can't connect" on upload | FTP credentials | Verify Host, User, Pass in FileZilla Site Manager |
| "Permission denied" errors | File permissions | Right-click file → Permissions → 644 (files) / 755 (folders) |
| "500 Internal Error" | PHP version | Contact host; request PHP 8.2 |
| "Database connection failed" | DB credentials | Verify in phpMyAdmin: can you log in? |
| "Can't find admin" on login | No admin user | Insert via phpMyAdmin (see deployment guide Step 4.2) |
| "Uploaded files missing" | FTP transfer incomplete | Check for green checkmarks; retry failed files |

---

## 📋 After Deployment

- [ ] Test member registration
- [ ] Test file upload (member avatar)
- [ ] Monitor `storage/logs/` for errors
- [ ] **Backup database weekly** (download from phpMyAdmin)
- [ ] **Monitor uptime** (free hosts can have outages)
- [ ] Note: This is DEMO environment, not production-grade

---

## 🔐 Security Reminder

- [ ] `.env` file NOT publicly accessible (correct permissions)
- [ ] Change admin default password immediately
- [ ] Remove test accounts before going live
- [ ] Update M-PESA callbacks when switching from Sandbox to Live
- [ ] Enable HTTPS for all payment transactions

---

## 📞 Host Support Info

- [ ] Host name: ___________________
- [ ] Support email: ___________________
- [ ] Support phone: ___________________
- [ ] Dashboard URL: ___________________
- [ ] phpMyAdmin URL: ___________________

---

**Completed**: ✓ Ready for testing!
