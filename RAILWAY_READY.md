# ✅ Railway Deployment Checklist

Your Umoja Sacco application is now **ready for Railway deployment**!

## What Was Done

### 1. **Configuration Updates** ✅
- ✅ Updated `config/db_connect.php` to read environment variables
- ✅ Enhanced `config/EnvLoader.php` to support Railway's system env vars
- ✅ Updated `.env.example` with Railway-specific instructions
- ✅ Ensured `.gitignore` protects sensitive files

### 2. **Railway-Specific Files Created** ✅
- ✅ **`Procfile`** - Tells Railway how to start the PHP app
- ✅ **`railway.json`** - Railway build configuration
- ✅ **`RAILWAY_DEPLOYMENT.md`** - Complete step-by-step deployment guide

### 3. **Git & GitHub** ✅
- ✅ Code committed with clear Railway deployment message
- ✅ Changes pushed to GitHub main branch
- ✅ Repository ready for Railway auto-deploy

---

## Quick Start: Deploy on Railway

### 1️⃣ Go to Railway
```
https://railway.app
```

### 2️⃣ Click "New Project"
- Select "Deploy from GitHub repo"
- Choose `LeyianB24/umoja-sacco`
- Railway auto-builds PHP + detects buildpacks

### 3️⃣ Add MySQL Database
```
Project Dashboard → + New → Database → MySQL
```
Wait 1-2 minutes for provisioning.

### 4️⃣ Railway Auto-Links MySQL
Railway automatically connects the MySQL database to your PHP app via environment variables:
- `MYSQL_HOST`
- `MYSQL_PORT`
- `MYSQL_USER`
- `MYSQL_PASSWORD`
- `MYSQL_DATABASE`

Your app reads these! ✨

### 5️⃣ Add Additional Environment Variables
In **PHP Service → Variables**, add:
```
APP_ENV=production
APP_SECRET=<your-32-char-random-key>
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=<your-16-char-app-password>
SMTP_FROM_NAME=Umoja Drivers Sacco
```

### 6️⃣ Import Database Schema
Using TablePlus, DBeaver, or MySQL CLI:
```bash
mysql -h <MYSQL_HOST> -u <MYSQL_USER> -p<MYSQL_PASSWORD> \
  <MYSQL_DATABASE> < database/schema.sql
```

### 7️⃣ Visit Your Live App
```
https://your-app-name.railway.app
```

---

## Environment Variables Reference

Railway automatically sets:
```
MYSQL_HOST          (from MySQL service)
MYSQL_PORT          (default 3306)
MYSQL_USER          (from MySQL service)
MYSQL_PASSWORD      (from MySQL service)
MYSQL_DATABASE      (from MySQL service)
```

You set in Railway dashboard:
```
APP_ENV             production
APP_SECRET          <32-char random string>
SMTP_HOST           smtp.gmail.com
SMTP_PORT           587
SMTP_SECURE         tls
SMTP_USERNAME       your-email@gmail.com
SMTP_PASSWORD       <Gmail app password>
SMTP_FROM_NAME      Umoja Drivers Sacco
```

---

## How It Works

### Local Development
```bash
# Copy .env.example to .env.local
cp .env.example .env.local

# Edit .env.local with your local MySQL credentials
# App reads: .env.local → $_ENV → getenv() (priority order)
```

### Railway Production
```
# .env.local doesn't exist on Railway
# Railway sets system environment variables automatically
# App reads: getenv() → $_ENV → defaults (priority order)
```

### Docker
```bash
# Create .env.docker if using docker-compose
# App reads same way (system env > file vars > defaults)
```

---

## File Changes Summary

| File | Change | Impact |
|------|--------|--------|
| `config/db_connect.php` | Now uses EnvLoader | Flexible DB connection |
| `config/EnvLoader.php` | Prioritizes system env vars | Railway-compatible |
| `.env.example` | Added Railway instructions | Clear deployment path |
| `Procfile` | NEW | Railway knows how to start app |
| `railway.json` | NEW | Railway build configuration |
| `RAILWAY_DEPLOYMENT.md` | NEW | Step-by-step guide |

---

## Troubleshooting

### "Missing required environment variables"
→ Check Railway Variables tab, ensure all SMTP/DB vars are set

### "Can't connect to MySQL"
→ Wait for MySQL service to start (green status), check credentials in Railway

### "404 Not Found"
→ Check Procfile start command, ensure `docker/apache.conf` exists

### "Slow first load"
→ Normal on free tier (cold start). Use paid plan for faster boots

For detailed troubleshooting, see **`RAILWAY_DEPLOYMENT.md`**

---

## Next Steps

1. ✅ Review `RAILWAY_DEPLOYMENT.md` for detailed instructions
2. ✅ Create Railway account at https://railway.app
3. ✅ Connect your GitHub repository
4. ✅ Add MySQL database
5. ✅ Set environment variables
6. ✅ Import database schema
7. ✅ Visit your live app URL!

---

## Security Tips

- 🔒 Use strong `APP_SECRET` (32+ random characters)
- 🔒 Use Gmail App Password, not main password
- 🔒 Never commit `.env.local`
- 🔒 Set `APP_ENV=production` in production
- 🔒 Railway provides free HTTPS/SSL
- 🔒 Regular `composer update` for security patches

---

**Questions?** See `RAILWAY_DEPLOYMENT.md` or Railway docs: https://docs.railway.app
