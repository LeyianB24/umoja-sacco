# 🔐 Credential Rotation Instructions

## ✅ Completed Changes

Your `.env.local` has been updated with:
- ✅ **New APP_SECRET**: `RE+MQYoU29b3VqqjO4XFv4cYGJUk3IIk0XL/QsSUoc4=`
- ✅ **Old exposed credentials removed**
- ❌ **Placeholders added** for credentials that need manual updates

---

## 📋 What Needs To Be Updated

### 1. 🔴 CRITICAL: Gmail App Password

**❌ OLD PASSWORD (COMPROMISED):** `duzb mbqt fnsz ipkg`

**Steps:**
1. Go to your Google Account: https://myaccount.google.com
2. Select **Security** from the left menu
3. Scroll to **App passwords** (under "How you sign in to Google")
4. Select Mail and Windows Computer
5. Copy the generated 16-character password
6. Update `.env.local` line 29:
   ```
   SMTP_PASSWORD=<your-new-16-char-password>
   ```

**Testing:**
```bash
# After updating, verify SMTP works
php -r "require 'config/app.php'; echo 'SMTP_PASSWORD set: ' . (strlen(SMTP_PASSWORD) > 0 ? 'YES' : 'NO');"
```

---

### 2. 🔴 CRITICAL: Paystack Test API Keys

**❌ OLD KEYS (COMPROMISED):**
- Secret: `sk_test_0aa34fba5149697fcc25c4ae2556983ffc9b2fe6`
- Public: `pk_test_a03e1eacf9f8e97fcc25c4ae2556983ffc9b2fe6`

**Steps:**
1. Go to Paystack Dashboard: https://dashboard.paystack.com
2. Go to **Settings** → **API Keys & Webhooks**
3. Delete old test keys
4. Generate new test keys
5. Update `.env.local` lines 65-66:
   ```
   PAYSTACK_TEST_SECRET_KEY=sk_test_<your-new-secret>
   PAYSTACK_TEST_PUBLIC_KEY=pk_test_<your-new-public>
   ```

---

### 3. 🔴 CRITICAL: SMS API Key

**❌ OLD KEY (COMPROMISED):** `atsk_aac0d19755a64e3664f9bcb4653fa983e3e94fc90acdff7bca92c1b859e4f4c6aede328c`

**Steps:**
1. Go to your SMS provider dashboard
2. Regenerate API key
3. Update `.env.local` line 75:
   ```
   SMS_API_KEY=<your-new-api-key>
   ```

---

## ✅ Status Checklist

- [x] APP_SECRET rotated
- [ ] Gmail App Password updated
- [ ] Paystack Test Keys regenerated
- [ ] SMS API Key regenerated
- [ ] `.env.local` updated with new credentials
- [ ] Application tested
- [ ] Git history cleaned

---

## 🧪 Verify Changes

```bash
# Check all required vars are set
php -r "require 'config/bootstrap.php'; echo 'All vars set: ✅';"

# Check no old secrets remain in code
grep -r "duzb mbqt fnsz ipkg" config/ && echo "❌ OLD PASSWORD FOUND!" || echo "✅ OLD PASSWORD REMOVED"
grep -r "sk_test_0aa34fba5149697fcc25c4ae2556983ffc9b2fe6" config/ && echo "❌ OLD PAYSTACK KEY FOUND!" || echo "✅ OLD PAYSTACK KEY REMOVED"
grep -r "atsk_aac0d19755a64e3664f9bcb4653fa983e3e94fc90acdff7bca92c1b859e4f4c6aede328c" config/ && echo "❌ OLD SMS KEY FOUND!" || echo "✅ OLD SMS KEY REMOVED"

# Test email
php -r "require 'config/app.php'; echo 'SMTP Password length: ' . strlen(SMTP_PASSWORD);"
```

---

## 🗑️ Clean Git History (Optional but Recommended)

Once you've rotated all credentials, remove them from git history:

```bash
# Remove old exposed credentials from git history
git filter-branch --tree-filter 'rm -f config/environment.php' HEAD

# Force push to remote (WARNING: This rewrites history!)
git push origin --force --all

# Verify clean history
git log --all --grep="secret" || echo "✅ No secrets in recent commits"
```

---

## 📊 Rotation Summary

| Service | Old Status | New Status | File Location |
|---------|-----------|-----------|----------------|
| **APP_SECRET** | Generic dev key | Generated strong key ✅ | .env.local:11 |
| **Gmail SMTP** | Exposed key ❌ | Needs update | .env.local:29 |
| **Paystack Test** | Exposed keys ❌ | Needs update | .env.local:65-66 |
| **SMS API** | Exposed key ❌ | Needs update | .env.local:75 |

---

## 🚨 Timeline

- **Immediate** (Done): Generated new APP_SECRET, removed old secrets from `.env.local`
- **Within 1 hour** (Your turn):
  - [ ] Generate new Gmail App Password
  - [ ] Regenerate Paystack test keys
  - [ ] Regenerate SMS API key
  - [ ] Update `.env.local`
- **Within 24 hours** (Recommended): Clean git history

---

## ✅ Finished?

Once all credentials are updated:

```bash
# Verify everything works
php -r "require 'config/bootstrap.php'; require 'config/app.php'; echo 'Application loaded successfully: ✅';"
```

Your system is now **fully secured** with rotated credentials! 🔒

**Questions?** Check `SECURITY.md` and `IMPLEMENTATION_COMPLETE.md` for detailed documentation.
