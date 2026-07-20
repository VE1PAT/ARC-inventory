# Deploy ARC Inventory — beginner walkthrough

This guide assumes you have **never deployed a PHP + MySQL app** before.  
Take it one step at a time. You do **not** need to finish in one sitting.

**Privacy:** Put real database names, usernames, and passwords only in `config.php` **on the server**.  
Do **not** commit those to GitHub. Club domain examples below are placeholders you replace locally.

**Radio Nets note:** that app stores data in **JSON files**.  
**Inventory is different:** it needs a **MySQL / MariaDB database** on the host.  
Your hosting almost certainly already has MySQL available (most club sites do); we just have to create a database and point the app at it.

---

## What you will end up with

| Piece | Example (you substitute your real values) |
|--------|---------|
| App URL | `https://www.YOUR-CLUB-SITE.org/inventory/public/` |
| Code folder on server | same area as `radio-nets`, named `inventory` |
| Database | a MySQL database (separate from Radio Nets — Nets does not use MySQL) |

Local XAMPP on your laptop stays for development. Live site is a **copy** of the code + a **new empty** database on the host.

---

## Download the code from GitHub (no Release required)

You do **not** need to create a GitHub Release to get a ZIP.

1. Open: https://github.com/VE1PAT/ARC-inventory  
2. Click the green **Code** button (near the top of the page, above the file list).  
3. Click **Download ZIP**.  
4. Save and unzip on your laptop.

That ZIP is the current `main` branch — enough for deploy.

(A **Release** is optional later if you want a named version like `v1.0` for other clubs. Skip it for now.)

**MySQL users tip:** the app only needs **one** database user in `config.php`.  
If the cPanel wizard created two users, pick **one**, make sure it has **ALL PRIVILEGES** on the inventory database, and use that user’s name/password in `config.php`. You can leave or delete the extra user later.

---

## What you need before starting

1. Login to your **website hosting control panel** (often called cPanel, Plesk, or “Hosting Dashboard”).  
   This is whatever you use to manage `YOUR-CLUB-SITE.org` files — the same place you uploaded Radio Nets.
2. Ability to:
   - Browse/upload files (File Manager or FTP)
   - Create a MySQL database (look for “MySQL Databases”, “MariaDB”, or “Databases”)
3. About 30–60 minutes the first time.

If you are unsure which panel you have: log in, take a screenshot of the home icons, and we can match the labels.

---

## Big picture (6 stages)

```text
1. Create MySQL database + user on the host
2. Upload / clone the Inventory code into an "inventory" folder
3. Create config.php with the database password
4. Import sql/001_schema.sql into that database
5. Run install.php in the browser (club name + 2 superusers)
6. Disable install.php so strangers cannot re-run setup
```

---

# Stage 1 — Create a MySQL database

Radio Nets did not need this. Inventory does.

### 1.1 Open the database tool

In your hosting panel, find one of these:

- **MySQL Databases**
- **MySQL Database Wizard**
- **MariaDB Databases**
- **Databases → MySQL**

### 1.2 Create the database

1. Create a new database.  
2. Suggested name: `inventory` or `arc_inventory`.  
3. The panel may automatically prefix it, e.g. `halifax_inventory` or `user123_inventory`.  
4. **Copy the full name exactly** into Notepad — you will need it later.

### 1.3 Create a database user

1. Create a new MySQL user.  
2. Choose a **long random password** (use the panel’s password generator).  
3. **Copy username and password into Notepad.**  
   You will not see the password again easily.

### 1.4 Attach the user to the database

1. Add the user to the database (often “Add User To Database”).  
2. Privileges: choose **ALL PRIVILEGES** (or check all boxes).  
3. Save.

### 1.5 Note the database host

Usually this is simply:

```text
localhost
```

Some hosts show a different host name — if they do, copy that instead.

### 1.6 Your notepad should now have

```text
DB host: localhost
DB name: ______________________
DB user: ______________________
DB pass: ______________________
```

---

# Stage 2 — Put the Inventory code on the website

You already did something like this for Radio Nets. Same idea: a folder under the website files.

### 2.1 Find where Radio Nets lives

In **File Manager** (or FTP), open the web root. Common names:

- `public_html`
- `www`
- `httpdocs`

You should see a folder like `radio-nets`.  
We will create a sister folder called `inventory` next to it.

```text
public_html/
  radio-nets/          ← already there (JSON app)
  inventory/           ← you will create this (PHP + MySQL app)
```

### 2.2 Get the code from GitHub

On your laptop browser, open:

https://github.com/VE1PAT/ARC-inventory

Click the green **Code** button → **Download ZIP**.

Unzip it. You will get a folder that may be named `ARC-inventory-main`.

Inside it you should see folders such as:

- `public`
- `config`
- `src`
- `sql`
- `DEPLOY.md`
- `README.md`

### 2.3 Upload to the server

**Using File Manager (easiest for first time):**

1. In File Manager, go to `public_html` (or wherever `radio-nets` is).  
2. Create a new folder named `inventory`.  
3. Open that `inventory` folder (it should be empty).  
4. Upload the **contents** of the unzipped project into `inventory`  
   (so that `inventory/public` exists, not `inventory/ARC-inventory-main/public`).

**Correct structure:**

```text
public_html/inventory/public/login.php
public_html/inventory/config/config.example.php
public_html/inventory/sql/001_schema.sql
public_html/inventory/src/...
```

**Wrong structure (avoid):**

```text
public_html/inventory/ARC-inventory-main/public/login.php
```

If you accidentally nested it, move everything up one level with File Manager.

### 2.4 Optional: SSH + git (only if you already use SSH)

```bash
cd ~/public_html
git clone https://github.com/VE1PAT/ARC-inventory.git inventory
```

If you have never used SSH, skip this and use the ZIP upload method.

---

# Stage 3 — Create `config.php` (tells the app how to reach MySQL)

### 3.1 Copy the example config

In File Manager, open:

`inventory/config/`

You will see `config.example.php`.

1. Copy/duplicate it.  
2. Rename the copy to **`config.php`**.

You must end up with **both**:

- `config.example.php` (template, stays in git)
- `config.php` (secrets for this server — never put real passwords on GitHub)

### 3.2 Edit `config.php`

Open `config.php` in the File Manager editor. Change it to match your Notepad values from Stage 1.

Example (replace with **your** real values):

```php
<?php
declare(strict_types=1);

return [
    'app_name' => 'ARC Inventory',

    // Exact public URL of the app (no trailing slash required; either is fine)
    'base_url' => 'https://www.YOUR-CLUB-SITE.org/inventory/public',

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

Save the file.

**Tips:**

- Keep the quotes around each value.  
- Do not share this file or commit it to GitHub.  
- `base_url` must be the HTTPS address members will type.

---

# Stage 4 — Import the database tables

This creates empty tables (users, items, loans, ledger, etc.).  
It does **not** copy your laptop’s test data unless you export that separately (for a first live deploy, empty is correct).

### 4.1 Open phpMyAdmin

In the hosting panel, open **phpMyAdmin**.

### 4.2 Select your database

In the left sidebar, click the database you created in Stage 1  
(the full name from Notepad).

### 4.3 Import the schema file

**Important:** use the latest `001_schema.sql` from GitHub (it no longer tries to create a database named `arc_inventory`).  
If you still have an old ZIP, either re-download, or delete any `CREATE DATABASE` / `USE …` lines at the top of the file before importing.

1. In the **left sidebar**, click **your** database first (the one you created in cPanel).  
2. Click the **Import** tab.  
3. **Choose file** from your laptop: `sql/001_schema.sql`  
   (C: drive / local file is normal — many hosts do not offer “load from server directory”.)  
4. Click **Go**.

You should see a success message and then tables listed under your database.

### 4.4 If you still get “Access denied … to database arc_inventory”

That means the SQL file is still the **old** version that tries to create `arc_inventory`.

1. Open `001_schema.sql` in Notepad.  
2. Delete any lines that say `CREATE DATABASE …` or `USE …`.  
3. Save.  
4. In phpMyAdmin, select **your** database on the left again.  
5. Import the edited file.

(Or download a fresh ZIP / pull latest from GitHub — current `001_schema.sql` is already fixed.)

### 4.5 Confirm it worked

In phpMyAdmin, with your DB selected, you should see tables such as:

- `users`
- `items`
- `loans`
- `ledger`
- `settings`
- `witness_requests`
- `security_alerts`
- `kit_includes`

If those exist, Stage 4 is done.

---

# Stage 5 — Make photo uploads writable

Photos go in `inventory/public/uploads/`.

In File Manager:

1. Open `inventory/public/`.  
2. Ensure folder `uploads` exists (it should).  
3. Permissions: often **755** or **775**.  
   If photo upload fails later, set `uploads` to **775** or ask the host “make this folder writable by PHP”.

---

# Stage 6 — First browser setup (install wizard)

### 6.1 Open the app

In your browser go to:

https://www.YOUR-CLUB-SITE.org/inventory/public/

(Adjust if your folder name or domain spelling differs.)

What you might see:

- A setup / status page, or  
- Redirect toward login/install, or  
- An error message (read it carefully)

### 6.2 Run the installer

Open:

https://www.YOUR-CLUB-SITE.org/inventory/public/install.php

Fill in:

| Field | Example |
|--------|---------|
| Club name | Halifax Amateur Radio Club |
| Club website | https://www.YOUR-CLUB-SITE.org |
| Inventory app base URL | https://www.YOUR-CLUB-SITE.org/inventory/public |
| Superuser 1 callsign + password | your callsign + strong password |
| Superuser 2 callsign + password | a second trusted member (important!) |

Submit.

### 6.3 Log in

https://www.YOUR-CLUB-SITE.org/inventory/public/login.php

Use a superuser callsign and password.

Smoke-test:

1. Add one item  
2. Search for it  
3. (Optional) loan/return with a second login as Witness  

---

# Stage 7 — Lock the installer (important)

As long as `install.php` is public, someone could try to mess with setup.

In File Manager, open `inventory/public/` and **rename**:

- `install.php` → `install.php.off`  
- `setup_superuser.php` → `setup_superuser.php.off`

Only rename them back temporarily if you need recovery, then rename off again.

---

# Stage 8 — Quick security checks

In the browser, these should **NOT** show your secrets (expect Forbidden / 404 / blank error — not readable PHP source or passwords):

- https://www.YOUR-CLUB-SITE.org/inventory/config/config.php  
- https://www.YOUR-CLUB-SITE.org/inventory/sql/001_schema.sql  

If `config.php` downloads or displays, stop and tell me — we will tighten hosting path / permissions.

---

## How this differs from Radio Nets

| | Radio Nets | Inventory |
|--|------------|-----------|
| Data storage | JSON files on disk | MySQL database |
| Setup | Upload files, edit JSON/config as you did | Upload files **+** create DB **+** import SQL **+** install.php |
| Backups | Copy JSON files | Export MySQL (phpMyAdmin → Export) **and** keep `public/uploads` |

For backups later: once a month (or before big changes), in phpMyAdmin use **Export** on the inventory database and save the `.sql` file somewhere safe.

---

## Common first-time problems

### “Database connection failed”
- Wrong DB name/user/password in `config.php`  
- Extra space or missing quote in `config.php`  
- DB user not granted privileges on that database  

### 404 Not Found on `/inventory/public/`
- Folder not named `inventory`, or nested wrong (`inventory/ARC-inventory-main/...`)  
- You are not in the same web root as `radio-nets`  

### Blank white page
- PHP version too old (need PHP 8+) — ask host to set PHP 8.1/8.2 for this folder  
- Check Errors / Error Log in the hosting panel  

### Import SQL failed
- Select your cPanel database on the left **before** Import  
- Use the latest `001_schema.sql` (no `CREATE DATABASE` / `USE` lines)  

### Photos will not upload
- `public/uploads` not writable  

---

## When you are “done”

- [ ] https://www.YOUR-CLUB-SITE.org/inventory/public/login.php works on your phone and laptop  
- [ ] Two superusers can log in  
- [ ] You can add and search an item  
- [ ] `install.php` is renamed to `.off`  
- [ ] `config.php` is not publicly readable  

---

## What to send me if you get stuck

Reply with:

1. Which stage number you were on (1–7)  
2. The **exact** error message or a screenshot description  
3. Whether File Manager shows `inventory/public/login.php`  

Do **not** paste your real database password into chat; say “password is set in config” instead.
