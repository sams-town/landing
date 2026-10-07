# 🐾 BeethovenPets — Complete Deployment Guide
# Premium Pet Relocation Platform | PHP Native + MySQL | No Composer / No SSH Required

---

## 📋 Pre-Requisites (Any Shared Hosting or VPS)

| Requirement | Minimum |
|-------------|---------|
| PHP Version | **7.4+** (PHP 8.0 / 8.1 / 8.2 recommended) |
| MySQL / MariaDB | **5.7+** (or 10.3+) |
| Required PHP Extensions | `pdo_mysql`, `mbstring`, `json`, `session`, `file_uploads=On` |
| Required Write Permissions | `/includes/`, `/uploads/`, `/logs/` |
| Disk Space | ~20 MB (very light!) |

---

## 🚀 DEPLOYMENT PATH A — EASY MODE (RECOMMENDED)
# Just use the browser installer. ~90 seconds. No manual phpMyAdmin needed.

### Step A-1. Zip & upload ALL project files
1. Open the project folder `pet-rec/`.
2. Select **ALL files and folders** (index.php, admin/, includes/, database/, uploads/, logs/, .htaccess, deploy/ etc).
3. Right-click → **Send to → Compressed (Zipped) folder** → save as `beethovenpets.zip`.
4. Login to your hosting panel:
   - **cPanel (Rumahweb etc)**: `https://yourdomain.com:2083` or cPanel link from provider email
   - **aaPanel**: `http://your-server-ip:8888` (or custom port)
5. Open **File Manager** → navigate to your site's root folder:
   - cPanel  → `/public_html/`
   - aaPanel → `/www/wwwroot/yoursite.com/`
6. Click **Upload** → select `beethovenpets.zip` → wait for upload.
7. Right-click the zip → **Extract** → OK.
8. ✅ Verify the files are directly in your site root (NOT inside a nested `pet-rec` subfolder!). You should see `index.php` directly in `/public_html/` or `/www/wwwroot/yoursite.com/`.

### Step A-2. Run the Browser Installer
✅ Run **ONE** of these — whichever matches your hosting panel:

#### For cPanel / Rumahweb users:
👉 **Open in browser**: `https://yoursite.com/deploy/cpanel-install.php`

#### For aaPanel VPS users:
👉 **Open in browser**: `https://yoursite.com/deploy/aapanel-install.php`

Then:
1. **Step ① Environment Check** — all green = good. Fix any red (see Troubleshooting below).
2. **Step ② Enter Credentials:**
   - 🗄️ **MySQL** — create a DB + DB user in your panel's **Database** menu first (see Step B-2 below), then copy+paste the credentials into the form.
     * Host = `localhost` (cPanel) or `127.0.0.1` (aaPanel usually)
     * Port = `3306` (default)
     * DB Name = e.g. `myuser_beethovenpets` (cPanel auto-prefixes with username!)
     * DB Username = e.g. `myuser_paw`
     * DB Password = the one you created
   - 🏢 Site Name = e.g. "BeethovenPets Indonesia"
   - 🔐 Admin Email = your email (for admin login)
   - 🔑 Admin Password = min 4 chars (you can change later)
3. Click **Run Installation / Install BeethovenPets**.
4. ✅ **Step ③ Success screen appears** with your admin URLs.

### Step A-3. ⚠️ CRITICAL — DELETE /deploy/ FOLDER
For security, go back to **File Manager** and **delete the entire `/deploy/` folder**.
Never leave installer scripts on a live site.

### Step A-4. Verify!
- 🌐 Open homepage → `https://yoursite.com/` — you should see the luxury pet travel landing page.
- 🔒 Open admin → `https://yoursite.com/admin/` — login with the admin email + password you just set.
- 📊 Go to admin Dashboard → stats should populate with seed sample data (4 bookings, 4 customers, routes etc).

**DONE! 🎉** Skip to the Post-Deploy Checklist at the bottom.

---

## 🛠️ DEPLOYMENT PATH B — MANUAL MODE
# If you don't want to use the installer, or if installer file-permissions fail.

### Step B-1. Upload the files (same as Step A-1)
Zip everything → upload to site root → extract.
Verify `index.php` is directly in the root, not nested.

### Step B-2. Create Database + User
#### cPanel:
1. Login cPanel → search **MySQL® Database Wizard** (easiest).
2. Step 1 — New Database Name: `beethovenpets` → cPanel will auto-prefix it to `youraccount_beethovenpets` → save this name.
3. Step 2 — New User: username = `paw` (becomes `youraccount_paw`), password = generate + SAVE → Create User.
4. Step 3 — **ALL PRIVILEGES** checked → Make Changes.
5. ✅ Save: DB Name, DB User, DB Pass in a notepad.

#### aaPanel:
1. Open aaPanel → sidebar **Database** → **Add database**.
2. DB Name = `beethovenpets`, Username = auto or `paw`, Password = generate + SAVE.
3. Access = `localhost` (default, safest).
4. ✅ Submit. Save the 3 credentials.

### Step B-3. Import SQL Schema via phpMyAdmin
1. Open **phpMyAdmin**:
   - cPanel → search **phpMyAdmin** icon in Databases section
   - aaPanel → sidebar **Database** → phpMyAdmin button (near top)
2. Select your new database from left sidebar (e.g. `youraccount_beethovenpets`).
3. Top menu → **Import** tab → click **Choose file**.
4. Browse to the unzipped project on your local → choose: `database/beethovenpets_schema.sql`
5. Click **Go** (bottom) → wait for green "Import has been successfully finished" message.
6. ✅ You should see 10 tables on the left: `admins`, `bookings`, `customers`, `pets`, `routes`, `addons`, `tracking_logs`, `site_settings` etc.

### Step B-4. Edit Database Credentials
In File Manager (or via downloaded then re-uploaded):
1. Navigate to `/includes/config.php` → right-click → **Edit** / **Code Edit**.
2. Replace the four DB lines with your saved credentials:
   ```php
   define("DB_HOST", "localhost");      // or 127.0.0.1
   define("DB_PORT", "3306");
   define("DB_NAME", "youraccount_beethovenpets");   // ← yours here
   define("DB_USER", "youraccount_paw");         // ← yours here
   define("DB_PASS", "your password here");      // ← yours here
   ```
3. Also change the `SITE_NAME` constant if you want a different brand name.
4. Save the file → close.

### Step B-5. aaPanel users: Flush PHP caches
aaPanel's PHP-FPM pools often cache config files aggressively:
1. aaPanel → sidebar **App Store** → installed → your PHP version (e.g. PHP 8.1) → **Settings**.
2. Tab **Service** → click **Reload** (or **Restart**).
3. Alternatively, the installer already does this automatically if you used Path A.

### Step B-6. Default Admin Login
SQL schema ships with a seeded admin account for first install:
```
🔐 URL:       https://yoursite.com/admin/
👤 Email:     admin@beethovenpets.com
🔑 Password:  admin123
```
⚠️ **CHANGE THE PASSWORD IMMEDIATELY after first login!**
Go to Admin → Settings → Staff Users → delete or change the default account.

### Step B-7. Verify
Same as Step A-4. Open homepage, open admin, check dashboard has data.

---

## 🔑 Default Seed Data (Instant Test Data After Import)
After importing the SQL schema, you get this sample data to play with:

| Type | Count | Examples |
|------|-------|----------|
| Admins | 1 | `admin@beethovenpets.com` / `admin123` (Super Admin) |
| Customers | 4 | Amita, Rizky, Maya, Budi |
| Pets | 4 | Golden Retriever, Persian Cat, French Bulldog, Rabbit |
| Routes | 5 | Jakarta→Surabaya, Jakarta→Bali, Jakarta→Singapore, etc. |
| Add-ons | 3 | Door-to-Door, Vet Documents, Airport Meet & Greet |
| Bookings | 4 | PV-20261005-001 through 004 |
| Tracking logs | ~12 | Full sample journey pipelines |
| Site Settings | ~10 | Price multipliers, contact info |

You can edit / delete all of this from the admin panel after login.

---

## ✅ Post-Deploy Checklist (5 Minutes)
1. **[SECURITY]** 🗑️ Delete `/deploy/` folder entirely from File Manager. DO NOT LEAVE IT.
2. **[SECURITY]** 🔑 Login admin → Settings → Staff → create YOUR account as Super Admin → **delete the default `admin@beethovenpets.com`**.
3. **[SECURITY]** Set strong DB password (not 123456). Use unique per-website DB user, not your main cPanel user.
4. **[PRICING]** Admin → **Routes & Pricing** → verify the 5 seeded routes match YOUR real prices. Delete / edit / add new.
5. **[PRICING]** Admin → **Add-ons** → add more upsells (Hotel / Boarding, Customs Broker, Health Certificate, Grooming on arrival etc).
6. **[PRICING]** Admin → **Settings → Pricing Engine** → set 5 weight multipliers (small, medium, large, giant, brachycephalic short-nose breed surcharge).
7. **[CONTACT]** Admin → **Settings → Contact Info** → update WhatsApp, email, office address.
8. **[HTTPS]** Enable SSL:
   - cPanel → SSL/TLS Status → Run AutoSSL (Let's Encrypt, free)
   - aaPanel → Site → yoursite → **SSL** tab → Apply Let's Encrypt (Domain verification)
9. **[SEO]** Change hero copy in `index.php` to match YOUR brand, your service area, your local cities. You may want to translate the public UI to Bahasa Indonesia since this template is English per user feedback.
10. **[EMAIL]** Test a booking: go to homepage → fill quote form → Submit. You should see `booking-success.php` with a **PV-XXXX** booking code, and an entry should appear instantly in Admin → Bookings.
    > *Note on auto-email:* `@mail()` in index.php silently falls back to PHP's internal `mail()`; if this doesn't work on your host, install a SMTP library later (SwitfMailer / PHPMailer single file include) — the core platform works without email (bookings always save to DB regardless).*

---

## 🧭 Public URLs After Deploy

| URL | What it does |
|-----|--------------|
| `https://yoursite.com/` | Luxury landing page, quote engine (AJAX calculator + full booking POST) |
| `https://yoursite.com/booking-success.php?code=PV-XXXX` | Post-booking confirmation page with confetti |
| `https://yoursite.com/track.php` | Public journey tracker (search by booking code) |
| `https://yoursite.com/track.php?code=PV-XXXX` | Deep link for a specific pet's journey timeline |
| `https://yoursite.com/admin/` | 🔒 Admin panel login |

---

## ⚙️ Admin Panel Module Map
All 9 pages you'll see in the sidebar:

| # | Page | File | What you can do |
|---|------|------|-----------------|
| 1 | 📊 Dashboard | `admin/index.php` | Stats, 7-day charts (revenue/booking counts/status), Top routes, Recent bookings, Quick actions |
| 2 | 📦 Bookings | `admin/bookings.php` | List + search + filter + bulk-status + bulk-delete + CSV-ready table |
| 3 | ↳ Single Booking View | `admin/booking-view.php` | Edit every field + pet info + add-ons + finance + **Post Journey Update form (auto-bumps booking status)** + journey pipeline + tracking logs |
| 4 | 👥 Customers | `admin/customers.php` | List + search + add-customer-modal + pet count + booking count + lifetime value |
| 5 | ↳ Customer Profile | `admin/customer-view.php` | Edit contact + **Add Pet modal** + health notes + vaccine dates + pet cards + full booking history |
| 6 | 🛫 Routes & Pricing | `admin/routes.php` | ⭐ CRUD every corridor + base fares + travel type + crate required + active/disabled |
| 7 | ⭐ Add-ons | `admin/addons.php` | CRUD upsell services with emoji icons, prices, descriptions |
| 8 | 📣 Journey Tracking Console | `admin/tracking.php` | GLOBAL log viewer + filter + **Post New Update to any booking** + delete log + direct link to public tracker preview |
| 9 | ⚙️ Settings | `admin/settings.php` | Pricing Engine multipliers → Contact Info → Other Settings → **Add custom setting key-value** → **Create Staff users** → delete staff |

---

## 🐛 Troubleshooting — Common Issues & Fixes

### ❓ Problem 1: Blank white page (500 error)
**Most likely cause:** PHP version too old, or missing `pdo_mysql` extension.

**Fix:**
1. cPanel → search **Select PHP Version** →
   - PHP version = 8.0 or higher
   - Extensions tab → TICK: `pdo`, `pdo_mysql`, `mysqli`, `mbstring`, `json`, `curl`, `zip`, `gd`, `fileinfo`
   - Click **Save**
2. aaPanel → App Store → PHP 8.2 → Install Extensions → add `pdo_mysql`, `fileinfo`, `gd`, `zip`, `curl`.

---

### ❓ Problem 2: "Database connection failed" red card on homepage
**Cause:** Wrong DB credentials in `/includes/config.php`, or the SQL not imported yet.

**Fix:**
- Recheck DB Name, User, Password exactly in `includes/config.php`. Remember cPanel auto-prefixes!
- Re-run the installer (`deploy/cpanel-install.php`) to auto-write correct config.
- In phpMyAdmin confirm the tables actually exist inside the right database.

---

### ❓ Problem 3: Old `index.html` showing instead of new PHP landing
**Cause:** Old index.html leftover / wrong DirectoryIndex order.

**Fix:**
- Should already be removed in this version (we deleted index.html explicitly). If not, delete it via File Manager.
- OR open `.htaccess` at root → ensure first line is: `DirectoryIndex index.php index.html`

---

### ❓ Problem 4: Uploads / images permission denied
**Fix:**
- File Manager → right-click `uploads/` → permissions → **0755**, owner = `nobody` / `www` / `apache` depending on server.
- Same for `logs/` folder if you see errors in admin (admin header writes nothing, but error_log() writes here).

---

### ❓ Problem 5: Session / Login doesn't work (admin login loop)
**Cause:** PHP session path not writable on some shared hosts.

**Fix:**
- cPanel → MultiPHP INI Editor (or Select PHP Version → Options tab) → set `session.save_path` = `/tmp` or a folder that exists + is writable.
- aaPanel → PHP settings → session → `session.save_path` = `/tmp` (default usually works).

---

### ❓ Problem 6: Pages work, but admin dashboard has no stats (empty tables)
**Cause:** You imported schema but not the seed data, or tables are empty.

**Fix:**
- Re-import ONLY the INSERT lines from `database/beethovenpets_schema.sql`. OR, simpler → run installer again (if you haven't deleted deploy/ yet) and it re-imports whole schema safely (IF NOT EXISTS, no data loss).
- OR just use admin panel to manually add your first route, customer, booking — takes 2 minutes.

---

### ❓ Problem 7: Add-ons / Routes not showing on public quote engine
The `calculateEstimate()` in `includes/functions.php` uses hardcoded fallbacks + DB values. To force DB-only: edit that function's `$route = pawDB()->fetch(...)` logic near the top (it already queries routes first). Add-ons array queries DB with fallback hardcoded prices if DB empty.

---

### ❓ Problem 8: aaPanel shows open_basedir restriction warnings
aaPanel → Site → yoursite → Settings → Website directory → **Disable open_basedir** (toggle). Or click the **Anti-XSS attacks (open_basedir)** to OFF.

---

## 🔐 Security Checklist for Production
1. ✅ Delete `/deploy/` — NOW.
2. ✅ Change default admin email + password (or delete that user entirely).
3. ✅ Set `includes/config.php` file permissions to **0644** so it's not writable by world.
4. ✅ Root `.htaccess` blocks direct access to `includes/`, `database/`, `logs/`, `*.sql`, `*.env` — confirm on your server by trying to open `yoursite.com/includes/config.php` (must show 403 Forbidden, not text).
5. ✅ Force HTTPS redirect (optional — add to TOP of `.htaccess`):
   ```apache
   RewriteEngine On
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```
6. ✅ Regularly backup:
   - cPanel → Backup Wizard → Full Backup weekly
   - aaPanel → Cron → add database dump job weekly

---

## 🗂️ File Structure Reference
```
/                               ← Site root (public_html or /www/wwwroot/yoursite.com)
├── index.php                   ← 🚪 Public landing page + quote engine AJAX + booking POST handler
├── booking-success.php         ← ✅ Post-booking confetti confirmation
├── track.php                   ← 🔍 Public journey tracker (unauthenticated)
├── .htaccess                   ← 🔒 Security + DirectoryIndex + optional pretty URLs + GZIP
├── DEPLOYMENT-README.md        ← This file
│
├── admin/                      ← 🔒 ALL ADMIN PAGES (require session)
│   ├── index.php               ← Dashboard
│   ├── login.php               ← bcrypt-auth login split screen
│   ├── logout.php
│   ├── bookings.php            ← List bookings w/ search + bulk
│   ├── booking-view.php        ← Single booking: edit all + track form
│   ├── customers.php           ← List customers w/ add modal
│   ├── customer-view.php       ← Customer + pets profile
│   ├── routes.php              ← ⭐ Pricing routes CRUD
│   ├── addons.php              ← ⭐ Upsell services CRUD
│   ├── tracking.php            ← ⭐ Global tracking console
│   ├── settings.php            ← ⭐ Settings + staff users
│   └── includes/
│       ├── header.php          ← Shared sidebar, nav, auth gate, flash messages, Chart.js CDN
│       └── footer.php          ← Layout closing tags
│
├── includes/                   ← 🔒 Protected (no direct web access)
│   ├── config.php              ← DB credentials + site constants (EDIT THIS)
│   ├── db.php                  ← PDO Singleton with pawDB() helper
│   ├── functions.php           ← Rupiah formatting, calculateEstimate(), status labels, flash, auth
│   └── init.php                ← Bootstrap (requires everything above)
│
├── database/
│   └── beethovenpets_schema.sql    ← 10 tables + seed data → phpMyAdmin import
│
├── uploads/                    ← 📁 Pet photos, docs (protected htaccess, no PHP execution)
│   └── .htaccess
├── logs/                       ← 📁 PHP error logs (protected, no download)
│   └── .htaccess
│
└── deploy/                     ← 🚀 Browser installers (**DELETE AFTER INSTALL!**)
    ├── index.php               ← 403 blocker
    ├── cpanel-install.php      ← 3-step auto installer for cPanel
    └── aapanel-install.php     ← 3-step auto installer for aaPanel (w/ opcache flush)
```

---

## 🆘 Need help?
1. Re-read this README — 95% of issues are covered in Troubleshooting.
2. Check `/logs/php-errors.log` (auto-created by PHP if writable).
3. Enable debug temporarily: in `includes/config.php` change:
   ```php
   error_reporting(E_ALL);
   ini_set("display_errors", 1);
   ```
   → then refresh the failing page — you'll see the specific PHP error line. Remember to turn display_errors OFF again for production!

---

Made with 🐾 for BeethovenPets Premium Pet Relocation Concierge
*All code PHP Native — no Composer, no Node, no build step. Upload & go.*
