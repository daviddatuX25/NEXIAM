# Dokploy Deployment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Verify local compatibility, test suite integrity, and document the step-by-step Dokploy deployment configuration.

**Architecture:** A minimal Docker architecture utilizing a web application container (built from the local `Dockerfile`) and a linked MySQL database container. Static defaults (drivers, tool paths) are packaged in `.env.dokploy.example`, while dynamic secrets are set via the Dokploy UI.

**Tech Stack:** Laravel 12, PHP 8.4, Docker, MySQL 8.0/8.4, Nginx.

## Global Constraints
* Dockerfile build must use the multi-stage assets build and serversideup production runtime.
* Cache, Session, and Queue drivers must default to `database` for minimal deploy footprint.
* PDF generation must use Chromium headless with no sandbox on production.
* LibreOffice PDF conversion path must point to `/usr/bin/libreoffice`.

---

### Task 1: Local Verification of Setup Helper and Configurations

**Files:**
* Verify: `app/Providers/AppServiceProvider.php:40-45`
* Verify: `.env.dokploy.example`
* Test: `tests/Feature/ExampleTest.php`

**Interfaces:**
* Consumes: Existing PHPUnit tests.
* Produces: Verified helper code and clean test suite status.

- [ ] **Step 1: Check APP_KEY check in AppServiceProvider**
  Verify that the code block has no syntax errors and works when `app.key` is empty.
  Run: `git diff app/Providers/AppServiceProvider.php`
  Expected: Diff shows the added `config('app.env') === 'production'` conditional block.

- [ ] **Step 2: Run application test suite**
  Run the test suite to ensure the added check doesn't interfere with standard test runners.
  Run: `php artisan test --compact`
  Expected: PASS

- [ ] **Step 3: Commit verification**
  Run: `git commit --allow-empty -m "test: verify startup helper behaves correctly under phpunit"`

---

### Task 2: Create MySQL Service in Dokploy

**Files:**
* None (Dokploy UI Setup)

**Interfaces:**
* Consumes: Dokploy Dashboard interface.
* Produces: Active MySQL service host and credentials.

- [ ] **Step 1: Provision MySQL in Dokploy**
  Create a MySQL database service inside the Dokploy panel.
  Expected: Active database service with host `mysql` (or custom name), port `3306`, and dynamic root password.

- [ ] **Step 2: Create database**
  Create a database named `securecat` inside the MySQL instance.

---

### Task 3: Setup Web Application in Dokploy

**Files:**
* Config: `.env.dokploy.example`

**Interfaces:**
* Consumes: GitHub repository, MySQL service host & credentials.
* Produces: Live application container.

- [ ] **Step 1: Connect Git Repository**
  Create a new Web Application in Dokploy linked to this GitHub repository. Set the build provider to **Dockerfile**.

- [ ] **Step 2: Add Environment Variables**
  Copy the contents of `.env.dokploy.example` into Dokploy's environment variables dashboard and fill in the dynamic secrets (`APP_URL`, `DB_HOST`, `DB_PASSWORD`, `SUPER_ADMIN_PASSWORD`). Leave `APP_KEY` blank initially.

- [ ] **Step 3: Perform Initial Deployment to Retrieve APP_KEY**
  Trigger the deployment.
  Expected: The app fails to boot, but outputs the setup helper log block with a generated key in the Dokploy Application logs.

- [ ] **Step 4: Update Environment with Key and Redeploy**
  Copy the generated key from the logs, paste it as `APP_KEY` in the Dokploy UI, and trigger redeployment.
  Expected: Container boots successfully, runs migrations automatically, and reaches active status.

---

### Task 4: Mount Storage Volume

**Files:**
* Mount: `/var/www/html/storage/app`

**Interfaces:**
* Consumes: Dokploy Volume interface.
* Produces: Persistent uploads and generated PDFs.

- [ ] **Step 1: Add Mount Path in Dokploy**
  Under the Web Application's **Mounts** configuration, create a persistent path mount mapping `/var/www/html/storage/app` to a persistent Docker volume.
  
- [ ] **Step 2: Verify Symlink**
  Ensure the storage link operates properly. Visit `https://your-domain.com` to see if CSS and public assets load.
