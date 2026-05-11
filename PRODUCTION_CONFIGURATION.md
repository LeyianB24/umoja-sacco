# Production Configuration Guide

## Fixed Issues (May 11, 2026)

### 1. ✅ Sandbox Keys Fallback
- **Status**: Fixed - Production automatically falls back to sandbox keys when live keys are not configured
- **Configuration**: Set `APP_ENV=production` in your `.env.local`
- **Fallback Logic**: If `MPESA_LIVE_CONSUMER_KEY` is not set, system will use `MPESA_SANDBOX_CONSUMER_KEY`

### 2. ✅ Admin Sidebar Visibility
- **Status**: Fixed - Added explicit visibility and display rules to sidebar CSS
- **File**: `admin/layouts/sidebar.php`
- **Improvement**: Sidebar now displays correctly on page load with visibility controls

### 3. ✅ Profile Picture Upload
- **Status**: Fixed - Changed from BLOB storage to file-based storage
- **New Path**: `uploads/admin_profiles/`
- **Backward Compatibility**: Still supports legacy BLOB-stored pictures
- **Auto-Detection**: System detects whether image is file or BLOB and retrieves accordingly

### 4. ✅ Mobile Responsiveness - Member Loans Page
- **Status**: Fixed - Added mobile-specific table CSS
- **Improvements**:
  - Table padding reduced on small screens (<576px)
  - Font sizes adjusted for mobile
  - Responsive button layout
  - Better spacing on tablets

### 5. ✅ Mobile Responsiveness - Member Shares Page
- **Status**: Fixed - Added mobile-specific table CSS
- **Improvements**:
  - Table cells optimized for mobile
  - Reference chips and status badges scaled down
  - Date and amount cells made compact
  - Better DataTables filtering on small screens

### 6. ✅ Email Configuration
- **Status**: Fixed - Now loads from environment configuration
- **Configuration Source**: `config/environment.php`
- **Required Environment Variables**:
  - `SMTP_HOST` (default: smtp.gmail.com)
  - `SMTP_USERNAME` (your email address)
  - `SMTP_PASSWORD` (app password or SMTP password)
  - `SMTP_PORT` (default: 587 for TLS, 465 for SMTPS)
  - `SMTP_FROM_NAME` (display name - default: Umoja Drivers Sacco)

## Environment Setup for Production

### Step 1: Set Application Environment
```bash
# In your .env.local file
APP_ENV=production
```

### Step 2: Configure Sandbox M-Pesa Keys (Until Live Keys Ready)
```bash
# In your .env.local file
MPESA_SANDBOX_CONSUMER_KEY=your_sandbox_key
MPESA_SANDBOX_CONSUMER_SECRET=your_sandbox_secret
MPESA_SANDBOX_SHORTCODE=174379
MPESA_SANDBOX_PASSKEY=your_sandbox_passkey
MPESA_SANDBOX_B2C_SHORTCODE=600981
MPESA_SANDBOX_B2C_SECURITY_CREDENTIAL=your_credential

# Leave live keys empty - system will automatically fall back to sandbox
# When ready to switch to live, just update these:
# MPESA_LIVE_CONSUMER_KEY=...
# MPESA_LIVE_CONSUMER_SECRET=...
# etc.
```

### Step 3: Configure Email (SMTP)
```bash
# Gmail Example (with App Password)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
SMTP_FROM_NAME=Umoja Drivers Sacco

# OR other SMTP provider
# Ensure SMTP_PORT is 587 (TLS) or 465 (SMTPS)
```

### Step 4: Create Required Upload Directory
```bash
# Create the admin profiles upload directory
mkdir -p uploads/admin_profiles
chmod 755 uploads/admin_profiles
```

## Testing Production Configuration

### Test PDF Export
1. Login as member
2. Go to Loans page
3. Click "Export" dropdown
4. Select "Export PDF"
5. Should generate and download successfully

### Test Mobile Responsiveness
1. Open member portal on mobile device (or use browser DevTools)
2. Navigate to:
   - Loans page: Tables should be readable with proper scaling
   - Shares page: All columns should be visible and accessible
3. All buttons and forms should be touch-friendly

### Test Profile Picture Upload
1. Login as admin
2. Go to "My Profile"
3. Upload a JPG/PNG image (max 1MB)
4. Image should display in profile card
5. Should be stored in `uploads/admin_profiles/` directory

### Test Email
1. Make a transaction (loan payment, contribution, etc.)
2. Check recipient's email for notification
3. Email should contain:
   - Professional branding with logo
   - Transaction details
   - Correct sender name and contact info
   - Footer with company information

## Troubleshooting

### Sidebar Not Visible
- Clear browser cache (Ctrl+Shift+Delete)
- Check that sidebar element has `class="hd-sidebar"`
- Verify JavaScript `sidebar.js` is loading correctly (check Network tab)

### Profile Pictures Not Uploading
- Verify `uploads/admin_profiles/` directory exists and is writable
- Check PHP `file_uploads` is enabled
- Verify `upload_max_filesize` and `post_max_size` in php.ini are >= 1MB
- Check server permissions: `chmod 755 uploads/admin_profiles`

### Emails Not Sending
- Verify SMTP credentials in `.env.local`
- Check that SMTP_PORT is correct (587 for TLS, 465 for SMTPS)
- For Gmail: Enable "Less secure apps" or use App Password
- Check PHP error logs: `php_errors.log`
- Test with: `php -r "require 'inc/email.php'; sendEmail('test@example.com', 'Test', 'Test email');"`

### PDF Export Failing
- Verify dompdf library is installed: `composer install`
- Check that `core/Services/` directory exists and is readable
- Verify PDF output directory has write permissions

### Mobile Layout Issues
- Clear browser cache
- Check Bootstrap CSS is loading: https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css
- Verify media queries in page CSS (should have `@media (max-width: 576px)` rules)
- Test with different mobile devices/screen sizes

## Switching from Sandbox to Live Keys

When you're ready to use live M-Pesa keys:

1. Update `.env.local`:
```bash
MPESA_LIVE_CONSUMER_KEY=your_live_key
MPESA_LIVE_CONSUMER_SECRET=your_live_secret
MPESA_LIVE_SHORTCODE=your_live_shortcode
MPESA_LIVE_PASSKEY=your_live_passkey
MPESA_LIVE_B2C_SHORTCODE=your_live_b2c_shortcode
MPESA_LIVE_B2C_SECURITY_CREDENTIAL=your_live_credential
```

2. The system will automatically detect live keys and switch from sandbox
3. No code changes required - fallback logic handles the transition

## Security Considerations

1. **Never commit `.env.local` to version control** - it contains sensitive credentials
2. **Use strong SMTP passwords** - consider app-specific passwords for Gmail
3. **Restrict file upload directory** - ensure `uploads/` directory is not publicly accessible
4. **Enable HTTPS** - all production deployments should use HTTPS
5. **Regularly rotate API keys** - especially M-Pesa and SMTP credentials

## Support

For issues or questions about production setup:
- Check `var/logs/error.log` for detailed error messages
- Enable debug mode temporarily: Set `APP_DEBUG=true` in `.env.local`
- Review recent git changes: `git log --oneline -10`
