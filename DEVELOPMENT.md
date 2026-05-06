# USMS Development Setup Guide

## Prerequisites

- PHP 8.1+
- MySQL 8.0+
- Composer
- Docker & Docker Compose (optional)

---

## Local Development (XAMPP)

### 1. Install Dependencies
```bash
cd c:\xampp\htdocs\usms
composer install
```

### 2. Create Storage Directories
```bash
mkdir -p storage\cache storage\logs uploads
```

### 3. Configure Database
Edit `config/db_connect.php`:
```php
$dbname = 'umoja_drivers_sacco';
$user   = 'root';
$pass   = '';
```

### 4. Import Database Schema
```bash
# Using XAMPP Control Panel - phpMyAdmin
# Or via MySQL command line
mysql -u root umoja_drivers_sacco < database/schema.sql
```

### 5. Start XAMPP
- Start Apache & MySQL from XAMPP Control Panel
- Access: `http://localhost/usms/`

---

## Docker Development (Recommended)

### 1. Build and Start
```bash
docker-compose up -d
```

### 2. Access Services
- **App**: http://localhost:8080
- **phpMyAdmin**: http://localhost:8081
- **Database**: localhost:3306

### 3. Run Migrations
```bash
docker exec usms-app php database/run_migration.php
```

### 4. View Logs
```bash
docker-compose logs -f php-app
```

---

## New Features Usage

### Using the Cache Manager
```php
require 'config/app.php';

$cache = new \USMS\Cache\CacheManager();

// Set cache
$cache->set('my_key', ['data' => 'value'], 3600);

// Get cache
$data = $cache->get('my_key');

// Remember pattern
$users = $cache->remember('all_users', function() {
    return DB_QUERY_HERE();
}, 600);
```

### Using Query Builder
```php
use USMS\Database\QueryBuilder;
use USMS\Cache\CacheManager;

$cache = new CacheManager();
$results = QueryBuilder::select($conn, $cache)
    ->from('members m')
    ->join('accounts a ON m.member_id = a.member_id')
    ->where('m.status = ?', 'active')
    ->cache('active_members', 300)
    ->get();
```

### Input Validation
```php
use USMS\Validator;

// Validate email
if (!Validator::email($_POST['email'])) {
    echo "Invalid email";
}

// Validate phone (East African)
if (!Validator::phone($_POST['phone'])) {
    echo "Invalid phone number";
}

// Sanitize input
$clean_email = Validator::sanitizeEmail($_POST['email']);
$safe_string = Validator::sanitizeString($_POST['name']);

// Check password strength
$strength = Validator::passwordStrength($_POST['password']);
// Returns: ['score' => 4, 'max' => 5, 'percent' => 80, 'feedback' => [...]]
```

### Monitor Performance
```php
use USMS\Database\QueryLogger;

QueryLogger::initialize();

// After queries...

$stats = QueryLogger::getStats();
// Returns: ['total_queries' => 5, 'total_time' => 0.045, ...]

$n1_problems = QueryLogger::detectN1Problems();
// Shows similar queries executed multiple times

$slowest = QueryLogger::getSlowestQueries(5);
// Top 5 slowest queries
```

---

## Password Migration

### Automatic (Recommended)
Passwords are automatically upgraded from SHA256 to bcrypt when admins log in. No manual action needed.

### Check Status
```php
$migration = new \USMS\Services\PasswordMigrationService($conn);
$status = $migration->getStatus();
echo "Migrated: {$status['migrated']}/{$status['total_admins']}";
```

### Manual Migration (if needed)
```php
$migration = new \USMS\Services\PasswordMigrationService($conn);

// Upgrade password on login
if ($migration->verifyPassword($password, $currentHash)) {
    $migration->upgradePasswordOnLogin($adminId, $password);
}
```

---

## Testing

### Run Unit Tests
```bash
vendor/bin/phpunit
```

### Code Style Check
```bash
vendor/bin/phpcs --standard=PSR12 core/ inc/
```

### Performance Check
```php
// Call the performance API
// GET http://localhost/usms/api/v1/admin/performance

// Or programmatically
$controller = new \USMS\Http\PerformanceController($conn);
$metrics = $controller->index();
```

---

## Debugging

### Enable Query Logging
```php
require 'config/app.php';

\USMS\Database\QueryLogger::setEnabled(true);
\USMS\Database\QueryLogger::initialize();

// Run queries...

// Check logs
$slowest = QueryLogger::getSlowestQueries(10);
print_r($slowest);
```

### Check Cache Stats
```php
$cache = new \USMS\Cache\CacheManager();
print_r($cache->stats());
// Output: ['memory_items' => 5, 'file_count' => 12, 'file_size_mb' => 0.35]
```

### Monitor System Health
```php
GET http://localhost/usms/api/v1/admin/performance

// Returns comprehensive system metrics
```

---

## Deployment

### Development Environment
```bash
# Update dependencies
composer install

# Create directories
mkdir -p storage/{cache,logs}

# Set permissions
chmod 755 storage/cache storage/logs uploads
```

### Production with Docker
```bash
# Build image
docker build -t usms:prod .

# Run container
docker run -d \
  -e APP_ENV=production \
  -e DB_HOST=mysql \
  -e DB_USER=usms_user \
  -e DB_PASSWORD=secure_password \
  -p 80:80 \
  usms:prod
```

### Environment Variables
Create `.env` file:
```
APP_ENV=production
APP_DEBUG=0
DB_HOST=localhost
DB_USER=usms_user
DB_PASSWORD=secure_password
DB_NAME=umoja_drivers_sacco
CACHE_TTL=3600
```

---

## Common Issues

### "Cache directory not writable"
```bash
chmod 755 storage/cache storage/logs
chown www-data:www-data storage/cache storage/logs
```

### "MySQL connection failed"
- Verify credentials in `config/db_connect.php`
- Check MySQL is running
- Confirm database exists

### "Migrations not found"
- Ensure `database/migrations` directory exists
- Check file permissions
- Verify migration file names follow pattern

### "Performance API returns 404"
- Check `.htaccess` for rewrite rules
- Verify database connection
- Check logs in `storage/logs/`

---

## File Locations

- **Cache**: `storage/cache/`
- **Logs**: `storage/logs/`
- **Uploads**: `uploads/`
- **Config**: `config/`
- **Core Classes**: `core/`
- **Database Migrations**: `database/migrations/`

---

## Useful Commands

```bash
# Install dependencies
composer install

# Update dependencies
composer update

# Run tests
vendor/bin/phpunit

# Check code style
vendor/bin/phpcs core/ inc/

# Start Docker
docker-compose up -d

# Stop Docker
docker-compose down

# View Docker logs
docker-compose logs -f php-app

# MySQL access in Docker
docker exec -it usms-mysql mysql -uroot -proot_password

# Composer autoload optimization
composer dump-autoload --optimize
```

---

For detailed information on each enhancement, see `ENHANCEMENTS.md`
