# TeamCollab — Complete Installation Guide
### Windows 11 + XAMPP + VS Code

---

## Prerequisites

| Requirement | Version | Download |
|-------------|---------|----------|
| PHP | 8.3+ | XAMPP includes it |
| MySQL | 8.0+ | XAMPP includes it |
| Composer | Latest | https://getcomposer.org |
| Node.js | 20 LTS | https://nodejs.org |
| Redis | Latest | See step below |
| Git | Latest | https://git-scm.com |

---

## STEP 1 — Install XAMPP

1. Download XAMPP from https://www.apachefriends.org
2. Install it (default path: `C:\xampp`)
3. Open **XAMPP Control Panel**
4. Start **Apache** and **MySQL**
5. Verify PHP version:
```bash
php -v
# Should show PHP 8.3.x
```

---

## STEP 2 — Install Composer

1. Download Composer installer from https://getcomposer.org/Composer-Setup.exe
2. Run the installer (it auto-detects PHP from XAMPP)
3. Verify:
```bash
composer -V
# Composer version 2.x.x
```

---

## STEP 3 — Install Node.js

1. Download Node.js LTS (v20) from https://nodejs.org
2. Install with default settings
3. Verify:
```bash
node -v   # v20.x.x
npm -v    # 10.x.x
```

---

## STEP 4 — Install Redis on Windows

Redis does not have an official Windows build. Use one of these:

### Option A — Memurai (Recommended for Windows)
1. Download from https://www.memurai.com/get-memurai
2. Install and start the service
3. It runs on `127.0.0.1:6379` by default

### Option B — WSL2 Redis
```bash
# In WSL2 terminal
sudo apt update && sudo apt install redis-server
sudo service redis-server start
redis-cli ping   # Should reply PONG
```

### Option C — Docker Desktop
```bash
docker run -d -p 6379:6379 redis:alpine
```

---

## STEP 5 — Create MySQL Database

Open XAMPP phpMyAdmin (http://localhost/phpmyadmin) or MySQL CLI:

```sql
-- Create the central database
CREATE DATABASE teamcollab_central CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Verify
SHOW DATABASES;
```

> Tenant databases are **created automatically** by Stancl Tenancy.

---

## STEP 6 — Clone / Navigate to Project

If you cloned from Git:
```bash
git clone <your-repo-url> teamcollab
cd teamcollab
```

Or if you have the folder already:
```bash
cd "C:\Users\LAPTOPS HUB\Documents\Oddo\teamcollab"
```

---

## STEP 7 — Install PHP Dependencies

```bash
composer install
```

This installs: Laravel 12, Stancl Tenancy, Spatie Permission, Inertia, Reverb, Sanctum, etc.

---

## STEP 8 — Configure Environment

```bash
# Copy environment file
copy .env.example .env

# Generate application key
php artisan key:generate
```

Now open `.env` in VS Code and update these values:

```env
APP_NAME="TeamCollab"
APP_URL=http://localhost:8000

# Database (central)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=teamcollab_central
DB_USERNAME=root
DB_PASSWORD=          # leave blank for XAMPP default

# Cache & Session (Redis)
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
CACHE_STORE=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Broadcasting (Reverb)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=teamcollab-app
REVERB_APP_KEY=teamcollab-key-123
REVERB_APP_SECRET=teamcollab-secret-456
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# Mail (log for development)
MAIL_MAILER=log
```

---

## STEP 9 — Install Node Dependencies

```bash
npm install --legacy-peer-deps
```

---

## STEP 10 — Run Central Migrations

```bash
# Creates: users, tenants, domains, plans, subscriptions tables
php artisan migrate
```

Expected output:
```
Running migrations...
✓ 0001_01_01_000000_create_users_table
✓ 2019_09_15_000010_create_tenants_table
✓ 2019_09_15_000020_create_domains_table
✓ create_permission_tables
✓ create_personal_access_tokens_table
✓ create_plans_table
✓ create_subscriptions_table
```

---

## STEP 11 — Seed the Database

```bash
php artisan db:seed
```

This creates:
- **3 Plans**: Free, Pro, Enterprise
- **Demo admin**: `admin@teamcollab.app` / `password`
- **Demo member**: `john@teamcollab.app` / `password`
- **Demo tenant**: `acme` (domain: `acme.localhost`)

---

## STEP 12 — Create Tenant Database & Migrate

```bash
# Migrate all existing tenant databases
php artisan tenants:migrate
```

This creates the `tenant_acme` database with:
- channels, messages, conversations, files
- message_reactions, message_reads, channel_members
- activity_logs, notifications, workspace_settings, message_threads

---

## STEP 13 — Create Storage Link

```bash
php artisan storage:link
```

---

## STEP 14 — Build Frontend Assets

```bash
# Production build
npm run build

# OR for development (with hot reload)
npm run dev
```

---

## STEP 15 — Running the Application

You need **4 terminal windows** running simultaneously:

### Terminal 1 — Laravel Web Server
```bash
php artisan serve
# App running at: http://localhost:8000
```

### Terminal 2 — Reverb WebSocket Server
```bash
php artisan reverb:start --debug
# WebSocket server running at: ws://localhost:8080
```

### Terminal 3 — Queue Worker
```bash
php artisan queue:work redis --queue=default,activity,files,notifications --tries=3
```

### Terminal 4 — Vite Dev Server (development only)
```bash
npm run dev
# Hot reload at: http://localhost:5173
```

---

## STEP 16 — Add Hosts Entry (for tenant subdomains)

Open `C:\Windows\System32\drivers\etc\hosts` as Administrator and add:

```
127.0.0.1   localhost
127.0.0.1   acme.localhost
```

Now you can access:
- Central app: http://localhost:8000
- Acme workspace: http://acme.localhost:8000

---

## STEP 17 — First Login

1. Open http://localhost:8000
2. Login with: `admin@teamcollab.app` / `password`
3. You'll be redirected to create/join a workspace
4. The `acme` workspace is already seeded

---

## Creating a New Workspace (via app)

1. Register at http://localhost:8000/register
2. Verify your email
3. Go to http://localhost:8000/workspaces/create
4. Fill in workspace name & slug
5. Tenant database is created **automatically**
6. Default channels (#general, #announcements, #random) are created
7. Start inviting team members

---

## Useful Artisan Commands

```bash
# List all tenants
php artisan tenants:list

# Run command for specific tenant
php artisan tenants:run "migrate" --tenants=acme

# Create a new tenant manually
php artisan tinker
>>> $tenant = App\Models\Tenant::create(['id' => 'myapp', 'name' => 'My App', 'slug' => 'myapp', 'owner_id' => 1]);
>>> $tenant->domains()->create(['domain' => 'myapp.localhost']);

# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# View failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Monitor queues (requires horizon)
php artisan horizon
```

---

## VS Code Extensions (Recommended)

- **PHP Intelephense** — PHP intelligence
- **Laravel Blade Snippets** — Blade templates
- **Tailwind CSS IntelliSense** — Tailwind autocomplete
- **Vue - Official** — Vue 3 support
- **GitLens** — Git history
- **Thunder Client** — API testing

---

## Troubleshooting

### ❌ "Class not found" errors
```bash
composer dump-autoload
php artisan clear-compiled
```

### ❌ Redis connection refused
Make sure Redis/Memurai is running. Test with:
```bash
redis-cli ping
# Should return: PONG
```

### ❌ Tenant database not created
Check `DB_USERNAME` has CREATE DATABASE privileges in MySQL:
```sql
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost';
FLUSH PRIVILEGES;
```

### ❌ Vite assets not loading
```bash
npm run build
php artisan optimize:clear
```

### ❌ WebSocket not connecting
- Verify Reverb is running: `php artisan reverb:start --debug`
- Check `.env` REVERB keys match `VITE_REVERB_*` keys
- Check browser console for WebSocket errors

### ❌ Emails not sending (development)
Set `MAIL_MAILER=log` — emails are written to `storage/logs/laravel.log`

---

## Environment Summary

| Service | Address |
|---------|---------|
| Laravel App | http://localhost:8000 |
| Reverb WebSocket | ws://localhost:8080 |
| Vite Dev Server | http://localhost:5173 |
| MySQL | localhost:3306 |
| Redis | localhost:6379 |
| phpMyAdmin | http://localhost/phpmyadmin |
