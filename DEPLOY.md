# Deploy ARC Inventory (production)

Use this when putting the app on club Linux hosting (e.g. halifax-arc.org).  
Local XAMPP development stays as in [README.md](README.md).

**Recommended live URL (matches Radio Nets style):**  
`https://www.halifax-arc.org/inventory/public/`

(You can change the folder name; keep the `/public/` web entry point.)

---

## Before you start (checklist)

- [ ] GitHub repo is up to date (`git push` from your laptop)
- [ ] Hosting has **PHP 8.x** and **MySQL or MariaDB**
- [ ] You can create a database (cPanel / Plesk / host panel)
- [ ] You can upload files (FTP/SFTP) **or** use SSH + `git clone`
- [ ] HTTPS already works on the club site

---

## Step 1 — Create the database

In the host control panel:

1. Create a new database, e.g. `halifax_arc_inventory` (name may get a prefix like `user_halifax_arc_inventory`)
2. Create a DB user with a **strong password**
3. Grant that user **all privileges** on that database only
4. Write down:
   - Database host (often `localhost`)
   - Database name
   - Username
   - Password

---

## Step 2 — Put the code on the server

### Option A — SSH + Git (preferred if you have SSH)

```bash
cd ~/public_html
# or wherever the site web root lives, same level style as radio-nets
git clone https://github.com/VE1PAT/ARC-inventory.git inventory
cd inventory
cp config/config.example.php config/config.php
nano config/config.php
```

### Option B — Upload zip (no SSH)

1. On GitHub: **Code → Download ZIP** (or download a Release if you make one)
2. Unzip locally
3. Upload the folder to the web space as `inventory` (so you have `.../inventory/public/`, `.../inventory/config/`, etc.)
4. On the server, copy `config/config.example.php` to `config/config.php` (File Manager rename/copy)

---

## Step 3 — Edit `config/config.php` on the server

Set production values (examples — use **your** host’s real names):

```php
return [
    'app_name' => 'ARC Inventory',
    'base_url' => 'https://www.halifax-arc.org/inventory/public',

    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'YOUR_DB_NAME',
        'user' => 'YOUR_DB_USER',
        'pass' => 'YOUR_DB_PASSWORD',
        'charset' => 'utf8mb4',
    ],

    'security' => [
        'max_failed_logins' => 3,
        'session_name' => 'arc_inventory_session',
    ],
];
```

Save the file. Never commit this file to GitHub.

---

## Step 4 — Import the schema

**Fresh install:** import only:

`sql/001_schema.sql`

(That file already includes settings + witness subject columns.)

### phpMyAdmin

1. Select your new database  
2. **Import** → choose `001_schema.sql` → Go  

If the file’s `CREATE DATABASE` / `USE arc_inventory` lines conflict with a panel-created DB name:

- Either edit those two lines at the top of the SQL to your real DB name before import, **or**
- Remove the `CREATE DATABASE` / `USE` lines and import while that database is selected in phpMyAdmin

### SSH

```bash
cd ~/public_html/inventory
mysql -u YOUR_DB_USER -p YOUR_DB_NAME < sql/001_schema.sql
```

---

## Step 5 — Folder permissions for photos

The app stores photos under `public/uploads/`.

```bash
cd ~/public_html/inventory
chmod 755 public/uploads
# If the host needs the web user to write:
chmod 775 public/uploads
```

In File Manager: ensure `public/uploads` is writable by PHP.

---

## Step 6 — First-time web setup

1. Open:  
   `https://www.halifax-arc.org/inventory/public/`
2. You should reach login or be sent to install
3. Open:  
   `https://www.halifax-arc.org/inventory/public/install.php`
4. Enter:
   - Club name (e.g. Halifax Amateur Radio Club)
   - Club website: `https://www.halifax-arc.org`
   - App base URL: `https://www.halifax-arc.org/inventory/public`
   - **Two** superuser callsigns + passwords

5. Log in and smoke-test: search, add item, loan/return, reports

---

## Step 7 — Lock down install pages (required)

After setup works, on the server **rename or delete**:

- `public/install.php`
- `public/setup_superuser.php`

Example (SSH):

```bash
cd ~/public_html/inventory/public
mv install.php install.php.off
mv setup_superuser.php setup_superuser.php.off
```

To change club branding later, temporarily rename them back, use as a logged-in superuser, then lock again.

---

## Step 8 — Optional: hide sensitive folders

If the host serves the whole `inventory/` tree (not only `public/`), the repo includes [`.htaccess`](.htaccess) at the project root to block web access to `config/`, `src/`, `sql/`, etc.

Confirm in a browser that these **fail** (403/404), not download:

- `https://www.halifax-arc.org/inventory/config/config.php`
- `https://www.halifax-arc.org/inventory/sql/001_schema.sql`

---

## Updating the live app later

```bash
cd ~/public_html/inventory
git pull
# If a new sql/00x_*.sql appears in the release notes, import it in phpMyAdmin
```

Do **not** overwrite `config/config.php` or `public/uploads/`.

---

## Dartmouth (second club)

Repeat this whole process with a **new** folder, **new** database, and **new** `config.php`.  
Same GitHub code; separate install.

---

## If something breaks

| Symptom | Likely fix |
|---------|------------|
| Blank page | Check host PHP error log; confirm PHP 8+ |
| Database connection failed | Fix `config/config.php` host/name/user/pass |
| 404 on `/inventory/public/` | Folder path wrong; confirm upload location vs `radio-nets` |
| Cannot upload photos | `public/uploads` not writable |
| Install page still public | Rename `install.php` as in Step 7 |

---

## Done when

- [ ] HTTPS login works
- [ ] Two superusers can sign in
- [ ] Item add + search work
- [ ] Loan/return with witness works
- [ ] `install.php` / `setup_superuser.php` are disabled
- [ ] `config.php` is not downloadable via the web
