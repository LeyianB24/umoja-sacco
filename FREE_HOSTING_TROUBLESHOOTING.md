# Free Hosting Troubleshooting Guide

**For**: USMS Deployment via FileZilla to Free Hosting  
**Updated**: May 7, 2026

---

## 🔴 Critical Issues & Solutions

### 1. "500 Internal Server Error" on Homepage

**Common Causes**:
- PHP version < 8.0
- Missing PHP extensions (xml, json, mysqli)
- Corrupted `.env` file syntax
- Invalid file permissions

**Troubleshooting**:

**Step 1**: Check PHP Version
```
Host Dashboard → PHP Info / Settings
Required: PHP 8.0+ (ideally 8.2+)
If lower, contact host support to upgrade
```

**Step 2**: Check `.env` File
```
Via FileZilla:
1. Right-click .env → Edit with...
2. Check for syntax errors:
   - All values should have = separator
   - No extra spaces or quotes
   - Save with CRLF line endings (Windows)
   
Example correct format:
DB_HOST=localhost
DB_USER=root
(NOT: DB_HOST = localhost)
```

**Step 3**: Check File Permissions
```
Via FileZilla:
1. Right-click each file → Permissions
2. Set: 644 (files) or 755 (folders)
3. Especially check:
   - index.php: 644
   - config/: 755
   - storage/: 755
```

**Step 4**: Enable Error Logging
```
Via File Manager or FTP:
1. Create storage/logs/ folder if missing
2. Edit config/app.php, set:
   'debug' => true (temporary only)
3. Reload page
4. Check storage/logs/error.log for specific error
```

**Step 5**: Contact Host
```
If still failing:
- Email: support@freehostname.com
- Mention: "PHP 8.2 app, getting 500 error"
- Ask for: error logs, PHP version confirmation
```

---

### 2. "Fatal Error: Class 'mysqli' not found"

**Cause**: MySQL extension not enabled

**Solution**:
```
Host Dashboard → Installed Extensions / Modules
Look for: mysqli, mysql (should be checked/enabled)
If missing, request host to enable them
OR check if you're using wrong PHP version
```

---

### 3. "Can't Connect to MySQL" (SQLSTATE[HY000])

**Common Causes**:
- Wrong DB_HOST (some hosts use remote MySQL)
- Wrong DB credentials
- Database not created yet
- Network access not allowed

**Troubleshooting**:

**Step 1**: Test Credentials Directly
```
1. Open host's phpMyAdmin
2. Try logging in with your DB credentials
3. If fails → credentials are wrong
4. If succeeds → issue is in code
```

**Step 2**: Verify DB_HOST Value
```
Common hosts use different DB hosts:
- Infinityfree: remotemysql.com or [specific]
- 000webhost: [auto-assigned]
- Check host docs for exact hostname
```

**Step 3**: Verify Database Created
```
In phpMyAdmin:
1. Left sidebar → Look for your database name
2. Click on it
3. You should see tables (members, admin, etc.)
4. If empty, re-import schema.sql
```

**Step 4**: Check DB Credentials in .env
```
Verify exact format (copy-paste from host):
DB_HOST=remotemysql.com
DB_USER=a1b2c3_username
DB_PASS=your_password_here
DB_NAME=a1b2c3_database
(Note: Most hosts add prefix like "a1b2c3_")
```

---

### 4. "404 Not Found" on Login Page

**Cause**: Files not uploaded correctly or remote path wrong

**Troubleshooting**:

**Step 1**: Verify Files Uploaded
```
FileZilla (right panel):
1. Should see: admin/, member/, public/, config/, core/, inc/, index.php
2. If missing, re-upload
```

**Step 2**: Check Remote Path
```
FileZilla Site Manager:
1. Check "Remote Path" setting
2. Should be: /public_html/ (with trailing slash)
3. Some hosts use: / or /www/
4. Ask host support for correct path
```

**Step 3**: Try Direct URL
```
Instead of: https://domain.net/usms/
Try: https://domain.net/usms/index.php
If works → issue is with mod_rewrite (not critical for demo)
```

---

### 5. "Call to undefined function password_hash()"

**Cause**: PHP version or openssl extension missing

**Troubleshooting**:
```
1. Verify PHP >= 7.0 (should have password_hash)
2. Check: Host Dashboard → Extensions
3. Look for: openssl (should be enabled)
4. Contact host if missing
```

---

### 6. Uploaded Files Not Visible in FileZilla

**Cause**: Incomplete upload or permissions issue

**Troubleshooting**:

**Step 1**: Check FileZilla Queue
```
Bottom panel should show:
✓ Success (green checkmark)
If shows ✗ or empty → transfer failed
```

**Step 2**: Refresh Remote Directory
```
In FileZilla:
1. Right-click /public_html/
2. Select Refresh (F5)
3. Wait 5 seconds
4. Files should appear
```

**Step 3**: Re-Upload**
```
If still missing:
1. Right-click admin/ folder locally
2. Select Upload
3. Wait for green checkmark
4. Refresh remote directory
```

**Step 4**: Check Connection Speed
```
If uploads are very slow:
1. Try connecting at different time
2. Consider uploading smaller files individually
3. Contact host if consistently slow
```

---

### 7. "SMTP Connection Timeout" (Email Not Sending)

**Cause**: Gmail authentication or port blocked

**Troubleshooting**:

**Step 1**: Verify Gmail App Password
```
1. Go to: https://myaccount.google.com/apppasswords
2. Generate NEW app password (NOT your Gmail password)
3. Copy 16-character password exactly
4. Update SMTP_PASSWORD in .env
5. Restart/reload application
```

**Step 2**: Check Firewall Settings
```
Some free hosts block SMTP port 587
Contact host: "Can you allow outbound SMTP on port 587?"
Alternative: Use different SMTP provider (SendGrid, Mailgun)
```

**Step 3**: Enable Less Secure Apps (if still failing)
```
Google Account → Security → Less secure app access → ON
(Less secure but sometimes needed for some hosts)
```

**Step 4**: Test SMTP Manually
```
Create test file (test_smtp.php):
<?php
require 'vendor/autoload.php';
$mail = new PHPMailer\PHPMailer\PHPMailer();
$mail->isSMTP();
$mail->Host = 'smtp.gmail.com';
$mail->Port = 587;
$mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
$mail->SMTPAuth = true;
$mail->Username = 'your@gmail.com';
$mail->Password = 'APP_PASSWORD_HERE';
$mail->setFrom('your@gmail.com');
$mail->addAddress('recipient@example.com');
$mail->Subject = 'Test';
$mail->Body = 'Test email';
if(!$mail->send()) echo 'Error: ' . $mail->ErrorInfo;
else echo 'Email sent!';
?>
Upload to server, access via browser, check result
```

---

### 8. M-PESA Callback Not Working (Status 404/500)

**Cause**: Callback URL wrong or endpoint not accessible

**Troubleshooting**:

**Step 1**: Verify Callback URL in Code
```
Check MPESA_SANDBOX_CALLBACK_URL in .env:
Should be: https://YOUR_DOMAIN/usms/public/member/mpesa_callback.php
Make sure:
- YOUR_DOMAIN is replaced with actual domain
- Path matches actual file location
- Uses https:// not http://
```

**Step 2**: Test Endpoint Manually
```
Open in browser:
https://YOUR_DOMAIN/usms/public/member/mpesa_callback.php
Should return PHP output (no 404)
```

**Step 3**: Check File Uploaded
```
FileZilla → Navigate to:
public/member/mpesa_callback.php
Should exist
```

**Step 4**: Update M-PESA Portal**
```
Go to: Safaricom Developer Portal
Update callback URL with correct domain
Save and test again
```

---

### 9. Database Backup Issues

**Problem**: Can't export database from phpMyAdmin (timeout)

**Solution**:
```
1. Use host's one-click backup tool (if available)
2. Export smaller tables individually:
   a. Select 1 table at a time
   b. Export to .sql file
   c. Download
3. Or use command line (if SSH available):
   mysqldump -u user -p database_name > backup.sql
```

---

### 10. "Disk Quota Exceeded" Error

**Problem**: Used up storage limit (usually 5GB)

**Troubleshooting**:
```
1. Check storage usage: Host Dashboard → Storage
2. Delete unnecessary files:
   - Old backups in backups/
   - Unused uploaded files in uploads/
   - Log files in storage/logs/
3. Contact host if recurring
4. Compress old data (e.g., archive old transactions)
```

---

## 🛠️ Diagnostic Commands

### Check PHP Version & Extensions
```php
<?php
// Create file: diagnostics.php
echo "PHP Version: " . phpversion() . "\n";
echo "Extensions installed:\n";
$extensions = get_loaded_extensions();
foreach($extensions as $ext) echo "- $ext\n";
echo "\nMissing extensions:\n";
$required = ['mysqli', 'json', 'xml', 'openssl'];
foreach($required as $req) {
    if(!extension_loaded($req)) echo "✗ $req MISSING\n";
    else echo "✓ $req OK\n";
}
echo "\nMySQL Connection Test:\n";
$conn = @mysqli_connect('DB_HOST','DB_USER','DB_PASS','DB_NAME');
if($conn) echo "✓ MySQL connected\n";
else echo "✗ MySQL failed: " . mysqli_connect_error() . "\n";
?>

Upload to server, access via browser
```

### Check File Permissions
```bash
# Via SSH (if available):
ls -la /public_html/
# Should show:
# -rw-r--r-- (644) for files
# drwxr-xr-x (755) for folders
```

---

## 📞 When to Contact Host Support

**Contact if**:
- [ ] PHP version < 8.0
- [ ] Missing extensions (mysqli, json, xml)
- [ ] MySQL connection fails in phpMyAdmin
- [ ] SMTP port 587 blocked
- [ ] Storage quota issues
- [ ] Persistent 500 errors
- [ ] SSH access needed (ask for SSH access)

**Email Template**:
```
Subject: PHP 8.2 Application Support Request

I'm deploying a PHP 8.2 + MySQL application using FileZilla.

Issues:
- [Describe issue clearly]
- [Affected URL if applicable]

Details:
- FTP Account: [your username]
- Database: [your database name]
- Domain: [your domain]

Please advise on:
- PHP version confirmation (need 8.2+)
- MySQL extension (mysqli) status
- Correct database hostname
- Any configuration changes needed

Thank you
```

---

## ✅ Quick Health Check

Run this weekly to catch issues early:

```
☐ Site loads (https://domain/usms/)
☐ Can log in (admin or member account)
☐ Members table has data
☐ Can upload file (test avatar)
☐ No errors in storage/logs/
☐ Database backup taken
☐ Check storage usage
☐ Verify HTTPS working
```

---

**Last updated**: May 7, 2026  
**For issues not covered**: Check host documentation or contact their support team
