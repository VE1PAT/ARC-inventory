# ARC Inventory

Amateur Radio Club inventory system for [halifax-arc.org](https://halifax-arc.org).  
Callsign login, witness-based loans, kits, photos, CSV import, and admin reports.

**Repo:** https://github.com/VE1PAT/ARC-inventory  
**Design:** open `design-spec.html` in a browser.

## Stack

- PHP 8.x
- MySQL / MariaDB
- Local: Windows 11 + XAMPP
- Production: Linux (same codebase)

---

## One-time setup on your laptop (run these yourself)

Open **PowerShell**. Commands below assume:

- Project folder: `C:\Users\home\Projects\Amateur Radio Inventory`
- XAMPP installed at `C:\xampp`
- Apache and MySQL already started in the XAMPP Control Panel

### 1) Copy local config

```powershell
cd "C:\Users\home\Projects\Amateur Radio Inventory"
copy config\config.example.php config\config.php
notepad config\config.php
```

In `config.php`, confirm at least:

- `base_url` → `http://localhost/arc-inventory/public`
- `db.user` → `root`
- `db.pass` → `` (empty is normal for XAMPP unless you set a password)
- `db.name` → `arc_inventory`

Save and close Notepad.

### 2) Point XAMPP at this project (junction)

This makes `http://localhost/arc-inventory/` serve your project folder without moving files into `htdocs`.

```powershell
# Run PowerShell as your normal user (Admin usually not required for htdocs)
cmd /c mklink /J "C:\xampp\htdocs\arc-inventory" "C:\Users\home\Projects\Amateur Radio Inventory"
```

Check it worked:

```powershell
dir C:\xampp\htdocs\arc-inventory
```

You should see `public`, `config`, `sql`, etc.

### 3) Create the database

**Option A — phpMyAdmin (easiest)**

1. Browser: http://localhost/phpmyadmin  
2. Click **Import**  
3. Choose file:  
   `C:\Users\home\Projects\Amateur Radio Inventory\sql\001_schema.sql`  
4. Click **Go**

**Option B — command line**

```powershell
cd "C:\Users\home\Projects\Amateur Radio Inventory"
C:\xampp\mysql\bin\mysql.exe -u root < sql\001_schema.sql
```

If MySQL has a password:

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -p < sql\001_schema.sql
```

### 4) Open the app in a browser

http://localhost/arc-inventory/public/

You should see **Database connection: OK**.

### 5) Create your first superuser

http://localhost/arc-inventory/public/setup_superuser.php

- Callsign: `VE1PAT` (or another)
- Password: choose a strong one (8+ characters)
- Submit

Create a **second** superuser the same way (different callsign) so you are not a single point of failure.

---

## Connect this folder to GitHub (run these yourself)

If Git is not installed: https://git-scm.com/download/win  
If GitHub CLI helps with login: https://cli.github.com/

### A) Initialize git in the project folder

```powershell
cd "C:\Users\home\Projects\Amateur Radio Inventory"
git status
```

If it says **not a git repository**, run:

```powershell
git init
git branch -M main
```

### B) Add the GitHub remote

```powershell
git remote remove origin
git remote add origin https://github.com/VE1PAT/ARC-inventory.git
git remote -v
```

(`remote remove` may error if origin did not exist — that is fine.)

### C) First commit and push

```powershell
git add .
git status
git commit -m "Initial PHP/MySQL scaffold for ARC Inventory"
git push -u origin main
```

If GitHub asks you to sign in, complete the browser / token login, then run `git push -u origin main` again.

If the GitHub repo already has a README commit and push is rejected:

```powershell
git pull origin main --allow-unrelated-histories
git push -u origin main
```

---

## Day-to-day workflow

```powershell
cd "C:\Users\home\Projects\Amateur Radio Inventory"
# ... edit code, test at http://localhost/arc-inventory/public/ ...
git add .
git commit -m "Describe why you changed something"
git push
```

Later on Linux hosting:

```bash
git clone https://github.com/VE1PAT/ARC-inventory.git
# copy config.example.php -> config.php with production DB settings
# point Apache/Nginx document root at /public
# import sql/001_schema.sql
# run setup_superuser.php once, then remove/protect it
```

---

## Important paths

| Path | Purpose |
|------|---------|
| `public/` | Web root (only this should be exposed on the internet) |
| `config/config.php` | Local secrets (not committed) |
| `sql/001_schema.sql` | Database schema |
| `design-spec.html` | Locked product design |
| `storage/uploads/` | Future item photos |

## Security notes

- Never commit `config/config.php`
- On the live site, remove or block `public/setup_superuser.php` after setup
- Production DB should not be open to the public internet
