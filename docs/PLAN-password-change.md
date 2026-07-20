# Plan: Member password change + live server update

## Goal

1. When an admin creates a member with a temporary password, that member must **change password on first login** before using the app.
2. Any logged-in member can **change their password anytime**.
3. You get a clear checklist to put those changes on the **live** cPanel site (without putting secrets in GitHub).

---

## Product behavior

### First login (forced)

```text
Admin creates VE1XYZ with temp password
  → user logs in with temp password
  → redirected to Change password page (cannot skip)
  → chooses new password (8+ chars, confirm)
  → must_change_password cleared
  → continues to Home
```

- Block access to Search, Loan, etc. until password is changed (except Logout and the change-password page itself).
- Show a clear message: “You must set a new password before continuing.”

### Anytime change (optional)

- Nav link: **Password** (or under Help / account)
- Form: current password + new password + confirm
- On success: update hash, write ledger event `password_changed`, stay logged in

### Admin reset

- When Admin+ sets/resets a password on **Members**, set `must_change_password = 1` again so the member must pick their own password next login.
- Install / recovery superuser creation: `must_change_password = 0` for the people setting up the club (they chose the password themselves), unless we later add a checkbox.

---

## Database change

New column on `users`:

```sql
must_change_password TINYINT(1) NOT NULL DEFAULT 0
```

- New file: `sql/004_must_change_password.sql` (for live + existing local DBs)
- Also add the column to `sql/001_schema.sql` for brand-new installs

**Live server:** import `004_…sql` in phpMyAdmin with `cj9475_inventory` selected (same pattern as before — no `CREATE DATABASE`).

---

## Code changes (local project first)

| Area | Change |
|------|--------|
| [`sql/001_schema.sql`](sql/001_schema.sql) / new `004_…sql` | Add `must_change_password` |
| [`src/Auth.php`](src/Auth.php) | Session includes flag; `requireLogin` redirects to change-password if forced |
| [`src/Users.php`](src/Users.php) | Create/update sets flag when admin sets password; `changeOwnPassword()` method |
| New `public/password.php` | Forced + voluntary change form |
| [`public/members.php`](public/members.php) | Note that temp passwords force change on next login |
| [`templates/layout_header.php`](templates/layout_header.php) | Link “Password”; if forced, strip other nav except Logout |
| [`public/help.php`](public/help.php) | Short help text |
| [`DEPLOY.md`](DEPLOY.md) | “Updating the live site” section for ZIP/File Manager |

Ledger: log `password_changed` (no password text stored).

---

## How you will get changes onto the live server

You deployed with a **ZIP + File Manager** (not git on the server). Use this update method every time:

### A. On your laptop (I do the coding with you)

1. Implement + test on XAMPP  
2. `git commit` / `git push` to GitHub  

### B. On the live server (you do this — guided)

1. **Database first**  
   - phpMyAdmin → select `cj9475_inventory`  
   - Import `sql/004_must_change_password.sql` from your laptop (C: drive)  

2. **Code files**  
   Either:
   - **Download ZIP** again from GitHub → upload/overwrite the changed folders (`public/`, `src/`, `sql/`, `templates/`), **or**
   - Upload **only changed files** via File Manager (I will list exact paths after coding)

3. **Do not overwrite**  
   - `config/config.php` (keeps live DB password)  
   - `public/uploads/` (photos)  

4. **Smoke test**  
   - Create a test member with temp password  
   - Log in as them → must see forced password change  
   - Change password voluntarily from nav  
   - Log in again with new password  

### Optional later

If you want easier updates: install Git in cPanel / SSH once, then `git pull` instead of ZIP. Not required for this feature.

---

## Out of scope (this change)

- Email “forgot password” reset (no member email stored by design)  
- Cross-app single sign-on with other club websites (possible later; not part of this feature)  

Admin can always reset a password in **Members** if someone forgets.

---

## Success criteria

- [ ] New member with admin-set password must change it before using the app  
- [ ] Logged-in users can change password anytime  
- [ ] Admin password reset re-arms “must change”  
- [ ] Live site updated via SQL import + file upload without losing `config.php`  
- [ ] Changes committed on GitHub  

---

## Implementation order when you say go

1. SQL migration + schema update  
2. Auth gate + `password.php`  
3. Members create/reset sets flag  
4. Nav + help  
5. DEPLOY “how to update live” section  
6. Commit/push  
7. Walk you through live import + file upload step by step  
