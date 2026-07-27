# Remove lean-ctx Local Traces and Install Globally Implementation Plan

> **For Antigravity:** REQUIRED WORKFLOW: Use `.agent/workflows/execute-plan.md` to execute this plan in single-flow mode.

**Goal:** Remove local configurations and dependencies of `lean-ctx` from the project, update scripts to reference the global installation, and install `lean-ctx` globally via npm.

**Architecture:** Remove `lean-ctx-bin` from `package.json`, delete local configuration files, update scripts and workspace documentation (`CLAUDE.md`, `.gitattributes`, `scripts/package-conventions.cjs`), and run global NPM installation commands with system verification.

**Tech Stack:** Node.js / NPM, Bash, Git

---

### Task 1: Remove local npm dependency `lean-ctx-bin`

**Files:**
- Modify: `package.json`
- Modify: `package-lock.json`

**Step 1: Edit `package.json` to remove `lean-ctx-bin`**
Remove `"lean-ctx-bin": "^3.8.1",` from `devDependencies`.

**Step 2: Run npm install to clean node_modules and update lockfile**
Run: `npm install`
Expected: Succeeds and `package-lock.json` is updated to no longer include `lean-ctx-bin`.

**Step 3: Verify package-lock.json changes**
Run: `git diff package-lock.json`
Expected: Diff shows removal of `lean-ctx-bin` and its sub-dependencies.

**Step 4: Commit package.json changes**
Run: `git add package.json package-lock.json`
Run: `git commit -m "chore: remove lean-ctx-bin local npm dependency"`

---

### Task 2: Remove `.lean-ctx` directory and `.gitattributes` configuration

**Files:**
- Modify: `.gitattributes`
- Delete: `.lean-ctx/` (directory containing rules.toml and overlays.json)

**Step 1: Edit `.gitattributes` to remove lean-ctx configuration**
Remove line 28: `/.lean-ctx export-ignore`

**Step 2: Remove the `.lean-ctx/` directory**
Run: `rm -rf .lean-ctx`

**Step 3: Commit removal of configuration files**
Run: `git add .gitattributes`
Run: `git rm -r .lean-ctx`
Run: `git commit -m "chore: remove local .lean-ctx configuration directory and gitattributes entries"`

---

### Task 4: Clean up `CLAUDE.md` documentation

**Files:**
- Modify: `CLAUDE.md`

**Step 1: Edit `CLAUDE.md` to remove token optimization and lean-ctx rules**
Remove lines 1 to 32 (from `# lean-ctx — Token Optimization` up to `===`). Ensure the file now starts directly with `===` or `<laravel-boost-guidelines>`.

**Step 2: Verify diff**
Run: `git diff CLAUDE.md`
Expected: Diff shows clean removal of lean-ctx rules and explanations.

**Step 3: Commit**
Run: `git add CLAUDE.md`
Run: `git commit -m "docs: remove lean-ctx rules and references from CLAUDE.md"`

---

### Task 4: Update `scripts/package-conventions.cjs` to use global commands

**Files:**
- Modify: `scripts/package-conventions.cjs`

**Step 1: Edit `scripts/package-conventions.cjs`**
Replace spawn commands running `npx` + `lean-ctx` with direct `lean-ctx` command calls since it will be installed globally.
Change lines 35-42 to:
```javascript
const result = spawnSync('lean-ctx', [
  'knowledge',
  'remember',
  value,
  '--category', category,
  '--key', key
], { encoding: 'utf8' });
```
Change line 53 to:
```javascript
const graphResult = spawnSync('lean-ctx', ['graph', 'build'], { stdio: 'inherit' });
```
Change line 60 to:
```javascript
const packResult = spawnSync('lean-ctx', ['pack', 'create', '--name', 'securecat-conventions'], { stdio: 'inherit' });
```
Change line 67 to:
```javascript
const exportResult = spawnSync('lean-ctx', ['pack', 'export', 'securecat-conventions'], { stdio: 'inherit' });
```

**Step 2: Verify diff**
Run: `git diff scripts/package-conventions.cjs`
Expected: Diff shows replacement of `'npx', ['lean-ctx', ...]` with `'lean-ctx', [...]`.

**Step 3: Commit**
Run: `git add scripts/package-conventions.cjs`
Run: `git commit -m "refactor: update package-conventions script to use global lean-ctx command"`

---

### Task 5: Install `lean-ctx` globally and verify

**Files:**
- None (Global System Changes)

**Step 1: Install `lean-ctx-bin` globally via npm**
Run: `npm install -g lean-ctx-bin`
Expected: Installs successfully and adds `lean-ctx` command to global PATH.

**Step 2: Perform global setup**
Run: `lean-ctx setup`
Expected: Setup completes and outputs configuration settings.

**Step 3: Run diagnosis check**
Run: `lean-ctx doctor`
Expected: Diagnostic passes or shows healthy state.

**Step 4: Verify the local package packaging script works with the global install**
Run: `node scripts/package-conventions.cjs`
Expected: Script successfully runs and completes CTXPKG clean packaging.
