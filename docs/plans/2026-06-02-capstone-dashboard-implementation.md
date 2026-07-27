# Capstone Document Portal Implementation Plan

> **For Antigravity:** REQUIRED WORKFLOW: Use `.agent/workflows/execute-plan.md` to execute this plan in single-flow mode.

**Goal:** Create a high-fidelity, premium index portal at `capstone/index.html` and update `capstone/README.md` to reflect the reorganized documents and task timeline.

**Architecture:** A unified portable HTML dashboard with interactive file indexing, metrics, Gantt chart, and task card timelines using CSS custom properties, flex/grid layouts, and vanilla JS for filtering/modals.

**Tech Stack:** HTML5, CSS3, JavaScript (ES6), Google Fonts (Outfit).

---

### Task 1: Update capstone/README.md

**Files:**
- Modify: `capstone/README.md`

**Step 1: Write updated contents**
Prepare the README file to accurately detail:
- The updated file layout structure (incorporating `research/`, `strategy/`, `team_meta/`, `guides/`).
- The 24 tasks assignment (reassigned status).
- Descriptions for all new files.

**Step 2: Commit changes**
Run:
```bash
git add capstone/README.md
git commit -m "docs: update capstone README to reflect reorganized folder structure"
```

---

### Task 2: Create capstone/index.html Document Portal

**Files:**
- Create: `capstone/index.html`

**Step 1: Write unified dashboard portal**
Generate `capstone/index.html` with:
- Dark glassmorphism header and theme styling.
- Responsive CSS custom layout (interactive file tree explorer, progress cards, desktop Gantt timeline, mobile task card view).
- Complete and accurate dataset of the 24 tasks (C1-01 to C1-12, C2-01 to C2-08, CC-01 to CC-04) with subtasks, due dates, assignees, deliverables, and dependencies.
- Filter controls for Assignee (All/David/Jaypee/Christine), Category (All/Chapter 1/Chapter 2/Cross-Cutting).
- Accessible relative links from portal to source-of-truth markdown files.
- Visual team workload breakdown statistics card.

**Step 2: Commit changes**
Run:
```bash
git add capstone/index.html
git commit -m "feat: implement high-fidelity portable capstone document index and Gantt portal"
```

---

### Task 3: Clean up superseded Gantt chart HTML

**Files:**
- Delete: `capstone/team_meta/PROJECT_GANTT_CHART.html`

**Step 1: Remove file**
Remove `capstone/team_meta/PROJECT_GANTT_CHART.html` as it is replaced by `capstone/index.html`.

**Step 2: Commit changes**
Run:
```bash
git rm capstone/team_meta/PROJECT_GANTT_CHART.html
git commit -m "cleanup: remove superseded PROJECT_GANTT_CHART.html file"
```
