# ARC Inventory

Open-source **amateur radio club inventory** system.

Track club assets (radios, kits, books, station gear, and more), loan items to members with a second-member **Witness**, keep an append-only ledger, and run simple admin reports. Built to be simple on phones and desktops.

Each club runs **its own install** (own database and config). The same codebase can power many clubs worldwide.

**Repository:** https://github.com/VE1PAT/ARC-inventory  
**Design notes:** open `design-spec.html` in a browser.  
**Production deploy:** see [DEPLOY.md](DEPLOY.md) for a step-by-step hosting guide.

## Features

- Callsign + password login (no personal membership data stored here)
- Roles: Member, Admin, Superuser (multiple superusers supported)
- Hybrid loan verification (same-device witness primary; remote pending expires in 48 hours)
- Kits / Go-Kits with include checklist confirmation
- Optional photos, CSV import/export, admin reports
- Sold/disposed tracking without money fields
- Items marked **Not for loan** for fixed station gear
- Forced password change on first login after an admin sets a temporary password

## Stack

- PHP 8.1+ (required)
- MySQL or MariaDB
- Local development: any PHP/MySQL stack (e.g. XAMPP on Windows)
- Production: typical Linux shared hosting or a VPS (same codebase)

---

## Local development (quick start)

Adjust paths for your machine.

### 1) Copy local config

```powershell
cd "path\to\ARC-inventory"
copy config\config.example.php config\config.php
```

Edit `config/config.php` for your local database and `base_url`.

Club display name is collected in the web installer (`install.php`), not in this file.

### 2) Create the database and import schema

Import `sql/001_schema.sql` into an existing local database (phpMyAdmin or command line).  
Select your database first; the schema file creates tables only (no `CREATE DATABASE`).

### 3) Open the app and run first-time setup

1. Point your web server at the project (or use a local URL into `/public/`).  
2. Open `/public/install.php`  
3. Enter club name, club website, app base URL, and **two** superusers  

### 4) Log in

Open `/public/login.php`

- 3 failed attempts lock the account (Admin+ can unlock under Alerts)
- Admin+: Members, CSV Import, Reports, Ledger

---

## Production install

Follow **[DEPLOY.md](DEPLOY.md)** end to end (database, upload, config, schema import, installer, lockdown).

Summary:

1. Create a MySQL database and user on the host.  
2. Upload this project into an `inventory` folder (web entry point is `public/`).  
3. Copy `config/config.example.php` → `config/config.php` with host DB settings and HTTPS `base_url`.  
4. Import `sql/001_schema.sql` with that database selected.  
5. Complete `/install.php`, then disable `install.php` and `setup_superuser.php`.  

Each club: separate database + separate `config.php`. Same GitHub code.

---

## Updating an existing install

1. Import any new `sql/00x_….sql` files listed in the release notes / commit messages (database selected first).  
2. Replace code folders (`public/`, `src/`, `templates/`, …).  
3. **Do not overwrite** `config/config.php` or `public/uploads/`.  

---

## Important paths

| Path | Purpose |
|------|---------|
| `public/` | Web root (expose this on the internet) |
| `public/install.php` | First-time club setup wizard |
| `config/config.php` | Server secrets (not committed) |
| `sql/` | Database schema and migrations |
| `design-spec.html` | Product design notes |
| `public/uploads/` | Item photos |

## Security notes

- Never commit `config/config.php`
- After setup, remove or rename `install.php` and `setup_superuser.php`
- Keep the database off the public internet; use HTTPS
- Account lockout after 3 failed logins (Admin+ notified in-app)
- Require PHP 8.1+ on the host
