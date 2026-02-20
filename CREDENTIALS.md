# CleanPlate — Credentials & Secrets Audit

> **Last updated:** February 20, 2026  
> ⚠️ Change all credentials before deploying to production.

---

## 1. Admin Dashboard Login

| Field    | Value      |
|----------|------------|
| Username | `admin`    |
| Password | `changeme` |

**Set in:**
- `docker-compose.yml` — `ADMIN_USERNAME: admin` / `ADMIN_PASSWORD: changeme` (live runtime source)
- `.env.example` — template documentation (`ADMIN_USERNAME=admin` / `ADMIN_PASSWORD=changeme`)

**Read by:**
- `config/app.php` — triple-fallback: `getenv()` → `$_SERVER` → `$_ENV` → hardcoded default `'admin'`/`'changeme'`
- `includes/AdminAuth.php` — reads via `Config::get('admin.username')` / `Config::get('admin.password')`, compares with `hash_equals()` (timing-safe)

---

## 2. MySQL App User (used by PHP)

| Field    | Value        |
|----------|--------------|
| Username | `cleanplate` |
| Password | `cleanplate` |
| Database | `cleanplate` |

**Set in:**
- `docker-compose.yml` (app service) — `DB_USERNAME: cleanplate` / `DB_PASSWORD: cleanplate`
- `docker-compose.yml` (db service) — `MYSQL_USER: cleanplate` / `MYSQL_PASSWORD: cleanplate` (creates the MySQL user)
- `.env.example` — template documentation

**Read by:**
- `config/database.php` — via `_db_env('DB_USERNAME', 'root')` / `_db_env('DB_PASSWORD', '')` helper (getenv → `$_SERVER` → `$_ENV` fallback chain)
- `includes/Database.php` — reads via `Config::get('connections.mysql.username')` / `Config::get('connections.mysql.password')`, passed to PDO constructor

---

## 3. MySQL Root Password

| Field    | Value          |
|----------|----------------|
| Username | `root`         |
| Password | `rootpassword` |

**Set in:**
- `docker-compose.yml` — `MYSQL_ROOT_PASSWORD: rootpassword` (MySQL service)
- `docker-compose.yml` — also used in the `healthcheck` command (`mysqladmin ping ... -prootpassword`)
- `.env.example` — `DB_ROOT_PASSWORD=rootpassword`

**Used by:** Docker/MySQL internals and phpMyAdmin only — the PHP app never connects as root.

---

## 4. Placeholder / Future Credentials (currently empty / unused)

These exist in config files but have no real values set and are not wired to any active code path.

| Credential              | Config file            | Env var                  |
|-------------------------|------------------------|--------------------------|
| Redis password          | `config/cache.php`     | `REDIS_PASSWORD`         |
| SMTP username           | `config/mail.php`      | `MAIL_USERNAME`          |
| SMTP password           | `config/mail.php`      | `MAIL_PASSWORD`          |
| Mailgun secret          | `config/mail.php`      | `MAILGUN_SECRET`         |
| AWS secret key          | `config/services.php`  | `AWS_SECRET_ACCESS_KEY`  |
| OpenAI API key          | `.env.example`         | `OPENAI_API_KEY`         |
| Nutritionix API key     | `.env.example`         | `NUTRITION_API_KEY`      |
| Spoonacular API key     | `.env.example`         | `SPOONACULAR_API_KEY`    |
| SendGrid API key        | `.env.example`         | `SENDGRID_API_KEY`       |
| Sentry DSN              | `.env.example`         | `SENTRY_DSN`             |

---

## How to Change Credentials for Production

1. Edit `docker-compose.yml` — update all values in sections **1**, **2**, and **3** above.
2. If changing MySQL passwords, destroy and recreate the volume so MySQL re-initialises:
   ```bash
   docker compose down -v
   docker compose up -d
   ```
3. If only changing the admin dashboard credentials (no DB change needed):
   ```bash
   docker compose up -d app
   ```
4. Optionally copy `.env.example` to `.env` and set values there — the app reads `.env` via `env_file` in `docker-compose.yml`.
