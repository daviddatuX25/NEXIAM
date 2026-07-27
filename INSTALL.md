# SecureCAT — Installation Guide

> **SecureCAT v2** — Secure Computerized Admission Testing System
> For Windows deployment using **Laragon**

---

## Table of Contents

1. [Prerequisites](#1-prerequisites)
2. [Install Laragon](#2-install-laragon)
3. [Install Node.js](#3-install-nodejs)
4. [Install Google Chrome](#4-install-google-chrome)
5. [Extract SecureCAT](#5-extract-securecat)
6. [Configure Environment](#6-configure-environment)
7. [Create Database](#7-create-database)
8. [Install Dependencies](#8-install-dependencies)
9. [Run Setup](#9-run-setup)
10. [Verify Installation](#10-verify-installation)
11. [Post-Install Configuration](#11-post-install-configuration)
12. [Optional: AI Features](#12-optional-ai-features)
13. [Optional: Email Setup](#13-optional-email-setup)
14. [Optional: Task Scheduler](#14-optional-task-scheduler)
15. [Troubleshooting](#15-troubleshooting)

---

## 1. Prerequisites

You need the following installed on the deployment computer:

| Software | Purpose | Download |
|----------|---------|----------|
| **Laragon Full** | PHP 8.4, MySQL, Apache (all-in-one) | https://laragon.org/download/ |
| **Node.js LTS** | Frontend asset compilation | https://nodejs.org/ |
| **Google Chrome** | PDF generation (result sheets) | https://www.google.com/chrome/ |

> **Minimum System:** Windows 10/11, 4 GB RAM, 2 GB free disk space.

---

## 2. Install Laragon

1. Download **Laragon Full** from https://laragon.org/download/
   - Choose the **Full** edition (includes PHP 8.4, MySQL 8, Apache, HeidiSQL)
2. Run the installer → install to `C:\laragon` (default)
3. Start Laragon by opening the Laragon app
4. Click **"Start All"** to start Apache and MySQL
5. Verify: open http://localhost in your browser — you should see Laragon's welcome page

### Enable Required PHP Extensions

1. In Laragon, click **Menu** (top-right hamburger icon)
2. Go to **PHP** → **Extensions**
3. Make sure these are checked (enabled):
   - ✅ `pdo_mysql`
   - ✅ `mysqli`
   - ✅ `mbstring`
   - ✅ `openssl`
   - ✅ `curl`
   - ✅ `xml`
   - ✅ `zip`
   - ✅ `gd`
   - ✅ `fileinfo`

> **Tip:** Most of these are already enabled by default in Laragon Full.

### Verify PHP Version

Open **Laragon Terminal** (button at bottom of Laragon window) and run:

```
php -v
```

You should see PHP **8.4.x**. If you see an older version:
1. Laragon Menu → PHP → Version → select PHP 8.4
2. Restart Laragon

---

## 3. Install Node.js

1. Download **Node.js LTS** from https://nodejs.org/
2. Run the installer with default settings
3. Verify in Laragon Terminal:

```
node -v
npm -v
```

You should see Node v20+ and npm v10+.

---

## 4. Install Google Chrome

Required for PDF generation (result sheets, admission slips).

1. Download from https://www.google.com/chrome/
2. Install with default settings
3. Note the install path — typically:
   ```
   C:\Program Files\Google\Chrome\Application\chrome.exe
   ```

---

## 5. Extract SecureCAT

1. Extract the SecureCAT ZIP file to Laragon's web directory:
   ```
   C:\laragon\www\securecat
   ```

2. After extraction, the folder structure should look like:
   ```
   C:\laragon\www\securecat\
   ├── app\
   ├── database\
   ├── public\
   ├── resources\
   ├── .env.production
   ├── INSTALL.md          ← this file
   ├── composer.json
   └── ...
   ```

3. Laragon will automatically create the URL **http://securecat.test** for this folder.

> **Important:** Make sure the folder is named exactly `securecat` (lowercase) so the URL works as `securecat.test`.

---

## 6. Configure Environment

### 6.1 Create the .env file

Open **Laragon Terminal** and run:

```
cd C:\laragon\www\securecat
copy .env.production .env
```

### 6.2 Set your Super Admin credentials

Open the `.env` file in a text editor (Notepad, VS Code, etc.) and find these lines:

```ini
# ── Super Admin Account ───────────────────────────────────
# Change these BEFORE running: php artisan migrate --seed
SUPER_ADMIN_EMAIL=admin@example.com
SUPER_ADMIN_PASSWORD=Password1!
SUPER_ADMIN_NAME="Super Admin"
```

**Change them** to your preferred admin login credentials:

```ini
SUPER_ADMIN_EMAIL=youremail@example.com
SUPER_ADMIN_PASSWORD=YourSecurePassword123!
SUPER_ADMIN_NAME="Your Name"
```

### 6.3 Set Institution Details (Optional — can also be done in admin UI)

In the same `.env` file, you can pre-fill these:

```ini
INSTITUTION_NAME="ISPSC"
INSTITUTION_CAMPUS="Tagudin Campus"
INSTITUTION_ADDRESS="your address here"
INSTITUTION_CONTACT_NUMBER="your contact number"
INSTITUTION_EMAIL="your email"
INSTITUTION_GUIDANCE_COUNSELOR="RAVEENA GALOPE, RGC, RPm"
```

> **Note:** All institution settings can also be changed later from the admin UI at **Setup > Institution**.

### 6.4 Verify Chrome Path

Make sure this line in `.env` points to your Chrome installation:

```ini
LARAVEL_PDF_CHROME_PATH="C:/Program Files/Google/Chrome/Application/chrome.exe"
```

If Chrome is installed elsewhere, update this path.

---

## 7. Create Database

### Option A: Using HeidiSQL (Laragon's built-in tool)

1. In Laragon, click **"Database"** button
2. HeidiSQL opens → connect to your local MySQL
3. Right-click in the left panel → **Create New** → **Database**
4. Name: `securecat`
5. Collation: `utf8mb4_unicode_ci`
6. Click **OK**

### Option B: Using Laragon Terminal

```
mysql -u root -e "CREATE DATABASE securecat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

---

## 8. Install Dependencies

Open **Laragon Terminal** and run these commands one at a time:

```bash
cd C:\laragon\www\securecat

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install frontend dependencies
npm install

# Build frontend assets
npm run build

# Generate application encryption key
php artisan key:generate
```

> **Note:** `composer install` may take 2-5 minutes. `npm install` may take 1-3 minutes.

---

## 9. Run Setup

Still in Laragon Terminal:

```bash
# Run database migrations and seed default data
php artisan migrate --seed

# Create storage symlink (for file uploads)
php artisan storage:link
```

This will:
- ✅ Create all database tables (84 migrations)
- ✅ Seed 5 user roles
- ✅ Seed 12 academic courses (BSEd, BEEd, BSIT, etc.)
- ✅ Seed 4 default exam rooms
- ✅ Seed 6 aptitude test areas
- ✅ Seed rating scale, templates, privacy policy
- ✅ Create current academic year (2025-2026)
- ✅ Create your Super Admin account

---

## 10. Verify Installation

1. Make sure Laragon is running (Apache + MySQL started)
2. Open your browser and go to: **http://securecat.test**
3. You should see the SecureCAT login page
4. Log in with the credentials you set in Step 6.2
5. After login, you'll see the Admin Dashboard

### Quick Smoke Test

After logging in, verify these work:
- [ ] **Admin > Courses** — shows 12 programs
- [ ] **Admin > Aptitude Areas** — shows 6 test domains
- [ ] **Admin > Rooms** — shows 4 rooms
- [ ] **Admin > Academic Years** — shows current year (active)
- [ ] **Applications > Apply** (public form) — loads the application form

---

## 11. Post-Install Configuration

After verifying the installation works, configure these in the admin UI:

### Institution Settings
1. Go to **Setup > Institution**
2. Fill in: school name, address, contact info
3. Fill in key personnel names (Guidance Counselor, Registrar, etc.)
4. These appear on result sheets, admission slips, and reports

### Academic Year
1. Go to **Admin > Academic Years**
2. Verify the current academic year is correct
3. Set application open/close dates as needed

### Rooms
1. Go to **Admin > Rooms**
2. Edit the default rooms to match your actual rooms
3. Add or remove rooms as needed

### Courses
1. Go to **Admin > Courses**
2. Verify all programs are listed
3. Deactivate any courses not offered this year

### Rating Scale
1. Go to **Admin > Rating Scales**
2. Review the default scale (Outstanding / Above Average / Average / Below Average / Needs Improvement)
3. Adjust score ranges if needed

---

## 12. Optional: AI Features

SecureCAT has two AI-powered features that require API keys. Both are **optional** — the system works fully without them.

### AI Exam Scheduling Assistant

Uses OpenRouter (free tier available) for intelligent exam scheduling suggestions.

1. Go to https://openrouter.ai/ and create a free account
2. Go to https://openrouter.ai/keys and create an API key
3. Open your `.env` file and set:
   ```ini
   OPENROUTER_API_KEY=sk-or-v1-your-key-here
   ```
4. Restart Laragon (or clear cache: `php artisan config:clear`)

### AI Knowledge Companion

Uses Mixedbread for RAG-powered chatbot that answers applicant questions.

1. Go to https://www.mixedbread.com/ and create a free account
2. Create an API key and a document store
3. Open your `.env` file and set:
   ```ini
   MIXEDBREAD_API_KEY=your-key-here
   MIXEDBREAD_STORE_ID=your-store-id
   ```
4. In the admin UI, go to **Admin > Knowledge Documents** and upload institution info
5. Run sync: `php artisan app:sync-knowledge-docs`

> **Note:** Both features use free tiers. No payment required for basic usage.

---

## 13. Optional: Email Setup

By default, SecureCAT is configured to use Laragon's local mail catcher on port 1025.
Emails will be captured but not actually sent to real recipients.

### For Real Email Delivery

You can use any SMTP provider. Example with Gmail:

1. Enable "App Passwords" in your Google account (requires 2FA)
2. Generate an app password
3. Update `.env`:
   ```ini
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_USERNAME=your-email@gmail.com
   MAIL_PASSWORD=your-app-password
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=your-email@gmail.com
   MAIL_FROM_NAME="ISPSC Admissions"
   ```
4. Clear config cache: `php artisan config:clear`

### Other Free Providers

- **Mailtrap** (free tier: 100 emails/month): https://mailtrap.io/
- **Mailgun** (free tier: 100 emails/day): https://www.mailgun.com/
- **Brevo/Sendinblue** (free: 300 emails/day): https://www.brevo.com/

---

## 14. Optional: Task Scheduler

SecureCAT has automated background tasks:

| Task | What It Does |
|------|-------------|
| Auto-close exam sessions | Closes sessions past their end time |
| Exam reminders | Sends reminders 1 day and 3 days before exams |
| Expire applications | Closes applications after academic year ends |
| Cleanup print jobs | Removes old temporary print files |

### Setup Using Windows Task Scheduler

1. Open **Task Scheduler** (search "Task Scheduler" in Start menu)
2. Click **Create Basic Task**
3. Name: `SecureCAT Scheduler`
4. Trigger: **Daily**, repeat every **1 minute**
5. Action: **Start a program**
   - Program: `C:\laragon\bin\php\php-8.4.x\php.exe` (adjust to your PHP path)
   - Arguments: `artisan schedule:run`
   - Start in: `C:\laragon\www\securecat`
6. Under Settings → check "Run whether user is logged on or not"

### Verify

In Laragon Terminal:
```bash
php artisan schedule:list
```

---

## 15. Troubleshooting

### "Page not found" at securecat.test

- Make sure Laragon is running (Apache started)
- Check that the folder is at `C:\laragon\www\securecat`
- In Laragon, click Menu → Apache → `sites-enabled/auto.securecat.test.conf` should exist
- Try: Laragon Menu → Apache → Reload

### "SQLSTATE: Unknown database 'securecat'"

- You forgot to create the database. See [Step 7](#7-create-database).

### "Class not found" errors

- Run `composer install` again
- Run `php artisan config:clear`

### Blank page or 500 error

- Check `storage/logs/laravel.log` for the actual error
- Make sure `storage/` and `bootstrap/cache/` are writable
- Run: `php artisan config:clear && php artisan cache:clear`

### PDF generation fails

- Make sure Google Chrome is installed
- Verify the Chrome path in `.env`: `LARAVEL_PDF_CHROME_PATH`
- Test Chrome opens: run the path in Windows Explorer

### Frontend looks broken (no styles)

- Run `npm run build` to compile assets
- Check `public/build/` directory exists

### "Session expired" on every action

- Make sure `SESSION_DRIVER=file` in `.env`
- Clear session: `php artisan session:clear`
- Check `storage/framework/sessions/` is writable

---

## Quick Reference Card

```
URL:        http://securecat.test
Admin:      [your configured email] / [your configured password]
Database:   securecat (MySQL, root, no password)
Project:    C:\laragon\www\securecat

# Useful commands (run in Laragon Terminal):
cd C:\laragon\www\securecat
php artisan config:clear        # After .env changes
php artisan cache:clear         # Clear all caches
php artisan migrate             # Run new migrations
php artisan db:seed             # Re-run seeders
php artisan schedule:list       # View scheduled tasks
php artisan storage:link        # Fix storage symlink
npm run build                   # Rebuild frontend
```

---

## Seeded Data Summary

After `php artisan migrate --seed`, the system contains:

### Courses (12)
| Code | Program |
|------|---------|
| BSEd | Bachelor of Secondary Education |
| BEEd | Bachelor of Elementary Education |
| BPEd | Bachelor of Physical Education |
| BSMath | Bachelor of Science in Mathematics |
| BAPsych | Bachelor of Arts in Psychology |
| BSPsych | Bachelor of Science in Psychology |
| BSIT | Bachelor of Science in Information Technology |
| BSBA | Bachelor of Science in Business Administration |
| BSEntrep | Bachelor of Science in Entrepreneurship |
| BPA | Bachelor of Public Administration |
| BAEL | Bachelor of Arts in English Language |
| BASS | Bachelor of Arts in Social Science |

### Aptitude Areas (6)
| Code | Area | Max Items |
|------|------|-----------|
| GA | General Ability | 25 |
| VA | Verbal Aptitude | 25 |
| NAP | Numerical Aptitude | 25 |
| SPA | Spatial Aptitude | 25 |
| PA | Perceptual Aptitude | 25 |
| MD | Manual Dexterity | 20 |

### Default Rooms (4)
| Room | Building | Capacity |
|------|----------|----------|
| Room 101 | Main Building | 30 |
| Room 102 | Main Building | 30 |
| Room 201 | Main Building | 30 |
| Lab Room 1 | ITBR | 25 |

### Roles (5)
| Role | Access |
|------|--------|
| Super Admin | Full system access |
| Staff | Process applications |
| Registrar Administrator | Manage scheduling, courses, rooms |
| Proctor | Monitor exam sessions |
| Test Administrator | Manage grading and result release |

### Rating Scale
| Score Range | Label |
|-------------|-------|
| 90–100 | Outstanding |
| 75–89 | Above Average |
| 50–74 | Average |
| 25–49 | Below Average |
| 0–24 | Needs Improvement |
