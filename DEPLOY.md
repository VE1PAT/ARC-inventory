# Deploy ARC Inventory — beginner walkthrough

This guide is for putting ARC Inventory on typical shared web hosting (cPanel or similar) with **PHP 8.1+** and **MySQL / MariaDB**.  
It assumes you may never have deployed a PHP + MySQL app before. Take it one step at a time.

**Privacy:** Put real database names, usernames, and passwords only in `config.php` **on the server**.  
Do **not** commit those to GitHub.

---

## What you will end up with

| Piece | Example (replace with your values) |
|--------|--------------------------------------|
| App URL | `https://www.example-club.org/inventory/public/` |
| Code on the server | a folder such as `inventory` under your site’s web root |
| Database | a MySQL/MariaDB database created in the host panel |

Your laptop can keep a separate local copy for development. The live site is a **copy of the code** plus a **new database** on the host.

**PHP version:** the host must run **PHP 8.1 or newer** for this app (8.0 may work; 7.4 will not).

---

## Download the code from GitHub (no Release required)

You do **not** need to create a GitHub Release to get a ZIP.

1. Open the project repository on GitHub.  
2. Click the green **Code** button (near the top of the file list).  
3. Click **Download ZIP**.  
4. Save and unzip on your computer.

That ZIP is the current `main` branch — enough for deploy.

(A **Release** is optional later if you want a named version like `v1.0`.)

**MySQL users tip:** the app only needs **one** database user in `config.php`.  
If the panel wizard created two users, pick **one**, grant it **ALL PRIVILEGES** on the inventory database, and use that user in `config.php`.

---

## What you need before starting

1. Login to your **website hosting control panel** (often cPanel, Plesk, or similar).  
2. Ability to:
   - Browse/upload files (File Manager, FTP, or SFTP)
   - Create a MySQL database  
3. About 30–60 minutes the first time.

If you are unsure which panel you have: log in, note the icon names, and match them to the steps below.

---

## Big picture

```text
1. Create MySQL database + user on the host
2. Upload / clone the Inventory code into an "inventory" folder
3. Create config.php with the database password and live URL
4. Import sql/001_schema.sql into that database
5. Run install.php in the browser (club name + 2 superusers)
6. Disable install.php so strangers cannot re-run setup
```

---

# Stage 1 — Create a MySQL database

### 1.1 Open the database tool

In your hosting panel, find one of these:

- **MySQL Databases**
- **MySQL Database Wizard**
- **MariaDB Databases**
- **Databases → MySQL**

### 1.2 Create the database

1. Create a new database.  
2. Suggested name: `inventory` or `arc_inventory`.  
3. The panel may automatically prefix it, e.g. `user123_inventory`.  
4. **Copy the full name exactly** into a text file — you will need it later.

### 1.3 Create a database user

1. Create a new MySQL user.  
2. Choose a **long random password** (use the panel’s password generator).  
3. **Copy username and password into your text file.**

### 1.4 Attach the user to the database

1. Add the user to the database (often “Add User To Database”).  
2. Privileges: **ALL PRIVILEGES**.  
3. Save.

### 1.5 Note the database host

Usually:

```text
localhost
```

If the panel shows a different host name, copy that instead.

### 1.6 Your notes should include

```text
DB host: localhost
DB name: ______________________
DB user: ______________________
DB pass: ______________________
```

---

# Stage 2 — Put the code on the website

### 2.1 Find your web root

In **File Manager** (or FTP/SFTP), open the site web root. Common names:

- `public_html`
- `www`
- `httpdocs`

### 2.2 Create an `inventory` folder

Create a new folder named `inventory` inside the web root.

```text
public_html/
  inventory/     ← app goes here
```

### 2.3 Upload the code

**File Manager / FTP (typical):**

1. Unzip the GitHub download on your computer.  
2. You should see folders such as `public`, `config`, `src`, `sql`.  
3. Upload those **contents** into `inventory`  
   (so `inventory/public` exists, not `inventory/ARC-inventory-main/public`).

**Correct:**

```text
…/inventory/public/login.php
…/inventory/config/config.example.php
…/inventory/sql/001_schema.sql
```

**Wrong:**

```text
…/inventory/ARC-inventory-main/public/login.php
```

**SSH + git (optional):**

```bash
cd ~/public_html
git clone https://github.com/VE1PAT/ARC-inventory.git inventory
```

---

# Stage 3 — Create `config.php`

### 3.1 Copy the example config

In `inventory/config/`:

1. Copy `config.example.php`  
2. Rename the copy to **`config.php`**

Keep both files: the example (template) and `config.php` (secrets for this server).

### 3.2 Edit `config.php`

Use your notes from Stage 1 and your real HTTPS URL:

```php
<?php
declare(strict_types=1);

return [
    'app_name' => 'ARC Inventory',

    'base_url' => 'https://www.example-club.org/inventory/public',

    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'PASTE_FULL_DB_NAME_HERE',
        'user' => 'PASTE_DB_USER_HERE',
        'pass' => 'PASTE_DB_PASSWORD_HERE',
        'charset' => 'utf8mb4',
    ],

    'security' => [
        'max_failed_logins' => 3,
        'session_name' => 'arc_inventory_session',
    ],
];
```

Save. Never commit real passwords to GitHub.

---

# Stage 4 — Import the database tables

This creates empty tables. It does **not** copy data from another computer unless you export/import that separately.

### 4.1 Open phpMyAdmin

### 4.2 Select your database

In the left sidebar, click the database you created in Stage 1.

### 4.3 Import the schema

1. **Import** tab.  
2. Choose file from your computer: `sql/001_schema.sql`  
   (Choosing a local file is normal.)  
3. **Go**.

`001_schema.sql` does **not** create a new database name — it creates tables inside the database you selected. That works with prefixed cPanel names.

### 4.4 Confirm success

You should see tables such as:

- `users`, `items`, `loans`, `ledger`, `settings`, `witness_requests`, `security_alerts`, `kit_includes`

---

# Stage 5 — Photo uploads folder

Photos go in `inventory/public/uploads/`.

Ensure that folder exists and is writable by PHP (often permissions **755** or **775**).

---

# Stage 6 — First browser setup

### 6.1 Open the app

```text
https://www.example-club.org/inventory/public/
```

(Use your real domain.)

### 6.2 Run the installer

```text
https://www.example-club.org/inventory/public/install.php
```

| Field | Example |
|--------|---------|
| Club name | Example Amateur Radio Club |
| Club website | https://www.example-club.org |
| Inventory app base URL | https://www.example-club.org/inventory/public |
| Superuser 1 | your callsign + strong password |
| Superuser 2 | a second trusted member (recommended) |

### 6.3 Log in and smoke-test

```text
https://www.example-club.org/inventory/public/login.php
```

Try: add an item, search, optional loan/return with a second login as Witness.

**Note:** Members created later with a temporary password must change it on first login.

---

# Stage 7 — Lock the installer

Rename or remove:

- `public/install.php` → e.g. `install.php.off`  
- `public/setup_superuser.php` → e.g. `setup_superuser.php.off`

---

# Stage 8 — Security checks

These should **not** show readable secrets (expect Forbidden / 404):

- `…/inventory/config/config.php`  
- `…/inventory/sql/001_schema.sql`

---

## Updating the live site later

1. If a new `sql/00x_….sql` file was added: import it in phpMyAdmin with your DB selected.  
2. Download a fresh ZIP (or `git pull` if you use git on the server).  
3. Overwrite `public/`, `src/`, `templates/`, and `sql/` as needed.  
4. **Do not overwrite** `config/config.php` or `public/uploads/`.  
5. Smoke-test login and a simple search.

---

## Multiple clubs

Each club needs its **own** folder (or server), **own** database, and **own** `config.php`.  
They can all use the same public GitHub codebase.

---

## Common problems

| Symptom | Likely fix |
|---------|------------|
| Database connection failed | Fix `config.php` host/name/user/pass |
| 404 on `/inventory/public/` | Wrong folder path or nested ZIP folder |
| HTTP 500 / blank page | Set PHP to **8.1+** in the host panel |
| Import SQL failed | Select your DB on the left before Import |
| Photos will not upload | Make `public/uploads` writable |

---

## Done when

- [ ] HTTPS login works on phone and computer  
- [ ] Two superusers can sign in  
- [ ] Add item + search works  
- [ ] Installer scripts are disabled (`.off` or removed)  
- [ ] `config.php` is not publicly readable  

---

## If you get stuck

Note which stage (1–7) you were on and the **exact** error message.  
Do **not** paste real database passwords into public chats or GitHub issues.
