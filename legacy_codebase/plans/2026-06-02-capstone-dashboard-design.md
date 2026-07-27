# Design Document: Capstone Document Portal & Interactive Gantt Chart

**Author:** Antigravity  
**Date:** June 2, 2026  
**Status:** Approved  

---

## 1. Overview
The `capstone/` directory of the `SecureCAT-v2` project contains all BSIT Capstone-related documents, guidelines, and pathways. To make these documents easy to browse, find, and navigate (especially when hosted on GitHub Pages), we are introducing a unified portal at `capstone/index.html` and updating the structural overview in `capstone/README.md`.

## 2. Requirements & Constraints
1. **Directory Structure Reflection:** The portal must accurately show the newly reorganized directory structure:
   - Root files: `README.md`, `ROADMAP.md`, `SYSTEM_FEATURES.md`
   - `guides/` — Formatting and content guides
   - `research/` — Research direction, thesis, and argument bank
   - `strategy/` — Defense and framing playbook
   - `team_meta/` — Assignments, checklists, and responses
2. **File Explorer & Indexing:** Direct, relative link navigation to all `.md` files (source of truth) from the portal.
3. **Mobile-Friendly Gantt Visualizer:**
   - On Desktop: A beautiful, wide timeline layout showing task bars across June 1 to June 10, 2026.
   - On Mobile: Automatically adapt the horizontal Gantt chart into a responsive vertical card/checklist view.
   - Filtering: Filter by Assignee, Chapter/Type, and Due Date.
   - Interactive detail modal for each of the 24 tasks containing descriptions, subtasks, estimated hours, and deliverables.
4. **Rich Aesthetics:** Dark mode/glassmorphism design, clean typography (e.g. Outfit), smooth transitions, custom interactive elements, and color coding.
5. **No Build Process / Vanilla CSS:** Self-contained in a single `index.html` file to ensure local portability and direct loading on GitHub Pages.

## 3. Scope of Work
- **Task 1: Update `capstone/README.md`**  
  Update directory structure tree, essential reading order, and add file descriptions for new files (e.g., `RESEARCH_DIRECTION_ANALYSIS.md`, `RESEARCH_ARGUMENT_BANK.md`, `SEARCH_TERM_CHEAT_SHEET.md`, `strategy/pre_proposal_defense.md`).
  
- **Task 2: Build `capstone/index.html`**  
  Implement the unified responsive HTML portal with Vanilla CSS and JS containing:
  - Document Index (collapsible folder categories with relative links)
  - Team Dashboard (workload metrics, hours, stats)
  - Gantt Chart (desktop timeline + mobile-friendly task card list)
  - Modal system for detailed task information.

- **Task 3: Remove Old Timeline File**  
  Remove the superseded `capstone/team_meta/PROJECT_GANTT_CHART.html`.

---

## 4. Work Items & Plan File Linkage
These tasks are registered in `docs/plans/task.md` under the `CAP` code.
