# ARC Inventory

Open-source **amateur radio club inventory** system.

Track club assets (radios, kits, books, station gear, and more), loan items to members with a second-member **Witness**, keep an append-only ledger, and run simple admin reports. Built to be simple on phones and desktops.

Each club runs **its own install** (own database and config). The same codebase can power multiple clubs — for example one instance for Halifax and another for Dartmouth.

**Repository:** https://github.com/VE1PAT/ARC-inventory  
**Design notes:** open `design-spec.html` in a browser.  
**Production deploy:** see [DEPLOY.md](DEPLOY.md) (Halifax / Linux hosting).

## Features (target)

- Callsign + password login (no personal membership data stored here)
- Roles: Member, Admin, Superuser (multiple superusers supported)
- Hybrid loan verification (same-device witness primary; remote pending expires in 48 hours)
- Kits / Go-Kits with include checklist confirmation
- Optional photos, CSV import/export, admin reports
- Sold/disposed tracking without money fields
- Items marked **Not for loan** for fixed station gear

## Stack

- PHP 8.x
- MySQL or MariaDB
- Local development: Windows + XAMPP (or any PHP/MySQL stack)
- Production: Linux hosting (same codebase)

---

## One-time setup (laptop with XAMPP)

Adjust paths if your project folder or XAMPP location differs.

### 1) Copy local config

```powershell
cd "C:\Users\home\Projects\Amateur Radio Inventory"
copy config\config.example.php config\config.php
notepad config\config.php
```

Set database credentials for your machine. Typical XAMPP defaults:

- `db.host` = `127.0.0.1`
- `db.name` = `arc_inventory`
- `db.user` = `root`
- `db.pass` = `` (empty unless you set a MySQL password)
- `base_url` = `http://localhost/arc-inventory/public`

Club display name is **not** set in this file — it is collected in the first-time web setup.

### 2) Point Apache at this project (optional junction)

```powershell
cmd /c mklink /J "C:\xampp\htdocs\arc-inventory" "C:\Users\home\Projects\Amateur Radio Inventory"
dir C:\xampp\htdocs\arc-inventory
```

### 3) Create the database

**phpMyAdmin:** Import `sql/001_schema.sql`  
If you already imported an older schema, also import newer files in order:
`sql/002_settings.sql`, then `sql/003_witness_subject.sql`.

**Command line:**

```powershell
cd "C:\Users\home\Projects\Amateur Radio Inventory"
C:\xampp\mysql\bin\mysql.exe -u root < sql\001_schema.sql
C:\xampp\mysql\bin\mysql.exe -u root < sql\002_settings.sql
C:\xampp\mysql\bin\mysql.exe -u root < sql\003_witness_subject.sql
```

### 4) Open the app and run first-time setup

1. http://localhost/arc-inventory/public/ → redirects to install when needed  
2. http://localhost/arc-inventory/public/install.php  

The installer asks for:

- Club name (shown in the app header)
- Club website URL (e.g. https://example-arc.org)
- App base URL (where this inventory app is hosted)
- At least one superuser callsign + password (a second superuser is strongly recommended)

### 5) Log in

http://localhost/arc-inventory/public/login.php

- 3 failed attempts lock the account
- Admin+ see lockouts under **Alerts** and can unlock
- Home is the signed-in landing page
- Admin+: **Members**, **CSV Import**, **Reports** (including AGM summary)

### 6) Push to GitHub (maintainers)

```powershell
cd "C:\Users\home\Projects\Amateur Radio Inventory"
git add .
git status
git commit -m "Describe your change"
git push
```

---

## Installing for another club

1. Clone this repository onto that club’s host (or copy a release).
2. Create a **new** MySQL/MariaDB database.
3. Copy `config/config.example.php` → `config/config.php` with that host’s DB settings.
4. Import `sql/001_schema.sql` (and `002_settings.sql` if needed).
5. Point the web server document root at `public/`.
6. Open `/install.php` and enter **that** club’s name, website, app URL, and superusers.
7. Remove or lock `install.php` / `setup_superuser.php` on the public internet after setup.

Halifax and Dartmouth (or any other clubs) each get their own database and `config.php`. They share code via this public repo.

---

## Day-to-day development

```powershell
cd "C:\Users\home\Projects\Amateur Radio Inventory"
# edit code, test locally, then:
git add .
git commit -m "Describe why you changed something"
git push
```

On a Linux host:

```bash
git clone https://github.com/VE1PAT/ARC-inventory.git
cp config/config.example.php config/config.php
# edit config.php for production DB
mysql -u USER -p DBNAME < sql/001_schema.sql
# point HTTPS vhost document root to /public
# complete /install.php once
```

---

## Important paths

| Path | Purpose |
|------|---------|
| `public/` | Web root (expose only this on the internet) |
| `public/install.php` | First-time club setup wizard |
| `config/config.php` | Local DB secrets (not committed) |
| `sql/` | Database schema |
| `design-spec.html` | Product design notes |
| `storage/uploads/` | Item photos |

## Security notes

- Never commit `config/config.php`
- After setup on a live site, remove or block `install.php` and `setup_superuser.php`
- Keep the database off the public internet; use HTTPS on the website
- Account lockout after 3 failed logins (Admin+ notified in-app)
