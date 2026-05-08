# 🚀 Railway Deployment Guide - Umoja Sacco

This guide walks you through deploying the Umoja Sacco application on Railway.app.

## Prerequisites

- ✅ GitHub account (to push your code)
- ✅ Railway account (https://railway.app)
- ✅ Project already on GitHub

## Step 1: Push Your Project to GitHub

If not already done:

```bash
cd c:\xampp\htdocs\usms
git init
git add .
git commit -m "Initial commit: Ready for Railway deployment"
git remote add origin https://github.com/YOUR-USERNAME/umoja-sacco.git
git branch -M main
git push -u origin main
```

## Step 2: Create Railway Account & New Project

1. Go to https://railway.app
2. Click **"Create New Project"**
3. Select **"Deploy from GitHub repo"**
4. Authorize Railway to access your GitHub
5. Select the `umoja-sacco` repository
6. Railway will auto-detect PHP and create a build

## Step 3: Add MySQL Database

In your Railway project dashboard:

1. Click **"+ New"** button
2. Select **"Database"** → **"MySQL"**
3. Railway provisions a MySQL instance (takes ~1-2 minutes)

## Step 4: Link MySQL to PHP Service

1. In Railway dashboard, click on the **PHP service**
2. Go to the **"Variables"** tab
3. Click **"+ New Variable"** or **"Generate"**
4. Railway will automatically populate from the MySQL service:
   - `MYSQL_HOST`
   - `MYSQL_PORT`
   - `MYSQL_USER`
   - `MYSQL_PASSWORD`
   - `MYSQL_DATABASE`

Your app will automatically read these! ✨

## Step 5: Set Required Environment Variables

In the **PHP service → Variables** tab, add:

```
APP_ENV=production
APP_SECRET=your-very-long-random-secret-key-here-minimum-32-chars
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-gmail-app-password
SMTP_FROM_NAME=Umoja Drivers Sacco
```

### For Gmail SMTP:
1. Enable 2FA on Gmail
2. Generate an **App Password**: https://myaccount.google.com/apppasswords
3. Use the 16-character password above

## Step 6: Import Database

Once MySQL is running:

1. Click on the **MySQL service** in Railway
2. Go to the **"Data"** or **"Connect"** tab
3. You'll see connection details (host, port, user, password)
4. Use TablePlus, DBeaver, or MySQL CLI to connect and import your schema:

```bash
mysql -h MYSQL_HOST -u MYSQL_USER -p MYSQL_PASSWORD MYSQL_DATABASE < database/schema.sql
```

Or manually run the SQL in Railway's query interface.

## Step 7: Get Your Live URL

1. Click the **PHP service**
2. Go to **"Settings"** → **"Networking"**
3. Click **"Generate Domain"** (or use Railway's assigned domain)
4. You'll get: `https://your-app-name.railway.app`

Visit it! 🎉

## Step 8: Deploy & Auto-Redeploy

- **First Deploy**: Happens automatically after Step 2
- **Auto-Redeploy**: Every `git push` to `main` triggers a new deploy
- **Check Deployment**: Go to **"Deployments"** tab in Railway

## Troubleshooting

### Database Connection Error

**Error**: "Can't connect to MySQL server"

**Solutions**:
1. Verify `MYSQL_HOST`, `MYSQL_USER`, `MYSQL_PASSWORD` are set
2. Check that MySQL service is **Running** (green status in Railway)
3. Ensure database is created: `CREATE DATABASE umoja_drivers_sacco;`
4. Check Railway logs: Click PHP service → **"Logs"** tab

### Missing Environment Variables

**Error**: "Missing required environment variables: SMTP_USERNAME..."

**Solutions**:
1. Go to PHP service → **Variables** tab
2. Verify all required vars are set (see Step 5)
3. Check for typos (case-sensitive!)
4. Redeploy after adding vars: Push empty commit or use Railway **"Redeploy"** button

### 404 on PHP Pages

**Error**: Pages like `/member/dashboard.php` return 404

**Check**:
1. PHP service → **"Settings"** → **"Build Command"**
   - Should run: `composer install`
2. PHP service → **"Settings"** → **"Start Command"**
   - Should be: `vendor/bin/heroku-php-apache2 -C docker/apache.conf public/`
3. If not, manually set these and redeploy

### Slow First Load

**Normal**: Cold starts can take 10-30 seconds on free tier
**Solution**: Upgrade to paid Railway plan for faster boots

## Monitoring & Logs

- **Deploy Logs**: Railway → Deployments tab
- **Runtime Logs**: PHP service → Logs tab
- **Error Logs**: Check `/tmp/usms.log` via SSH or logs tab
- **Database Queries**: Enable in `config/app.php` (development only)

## Security Checklist

- ✅ Never commit `.env.local` (in .gitignore)
- ✅ Use strong `APP_SECRET` (32+ chars)
- ✅ Use Gmail App Passwords (not main password)
- ✅ Set `APP_ENV=production` in production
- ✅ Enable HTTPS (Railway provides free SSL)
- ✅ Regularly update dependencies: `composer update`

## Database Backups

Railway provides automated backups. To manually export:

1. MySQL service → Data tab
2. Use TablePlus/DBeaver "Export" feature
3. Or run: `mysqldump -h MYSQL_HOST -u MYSQL_USER -p MYSQL_DATABASE > backup.sql`

## Next Steps

1. **Custom Domain**: Go to PHP service → Settings → Networking → Add custom domain
2. **Environment Config**: Adjust `APP_ENV`, logging, etc. per environment
3. **CI/CD**: Set up GitHub Actions for automated tests before deploy
4. **Monitoring**: Integrate with Sentry or DataDog for error tracking

## Support

- **Railway Docs**: https://docs.railway.app
- **PHP on Railway**: https://docs.railway.app/guides/php
- **Issues**: Check Railway status page or GitHub issues

---

**Happy deploying!** 🚀
