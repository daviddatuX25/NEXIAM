# 04 — UI/UX Observed Baseline

**Purpose:** preserve useful frontend behavior while preventing legacy markup and visual accidents from becoming greenfield authority

## 1. What the legacy frontend gives us

The old Inertia/Svelte application is a useful observation source for:

- staff and applicant layout shells;
- responsive sidebar/navigation behavior;
- top-bar and breadcrumb placement;
- notification polling and mark-read interactions;
- flash/toast presentation;
- table/card view switching;
- URL-backed filters and pagination patterns;
- Chart.js line/bar/doughnut dashboard visuals;
- schedule-assistant panels;
- AI chat and knowledge-support entry points;
- QR/camera interaction states;
- score-import preview and validation;
- result/admission document preview and print flows;
- form-level validation and bulk-action feedback.

Representative source areas:

- `legacy_codebase/application/resources/js/Layouts/`;
- `legacy_codebase/application/resources/js/Components/`;
- `legacy_codebase/application/resources/js/Pages/`;
- `legacy_codebase/application/resources/js/app.svelte`;
- `legacy_codebase/application/resources/css/`;
- the legacy planning/UI specifications under `legacy_codebase/planning/`.

## 2. What is not copied automatically

The greenfield UI must not inherit:

- hidden controls as an authorization boundary;
- ambiguous “success” toasts that only mean a request returned;
- hard-coded role strings or client-only permission checks;
- charts without scope, denominator, freshness, or table alternatives;
- empty screens that hide loading/degraded/denied/conflict states;
- forms that lose input after validation/network failure;
- result/score screens that imply model or provider authority;
- desktop-only assumptions for applicant/proctor operations;
- markup or CSS that conflicts with accessibility and responsive acceptance.

GF-22 is the target interface contract. The legacy frontend is the visual/behavioral evidence layer.

## 3. Recommended greenfield presentation baseline

Unless the stakeholder changes it, use:

- applicant experience: mobile-first and low-bandwidth tolerant;
- staff experience: desktop-first but usable on tablet;
- proctor experience: focused session workspace, large touch-safe actions, clear deadline/connection state;
- shell structure: staff, applicant, proctor/session, and break-glass variants;
- navigation: server-authorized grouped domains, with breadcrumbs on authenticated multi-step pages;
- design system: Tailwind 4 plus the selected Svelte component primitives, with tokens and accessibility rules defined before broad page recreation;
- feedback: in-page authoritative state plus deduplicated toast/notification projection;
- charts: explanation only, always paired with scope/freshness and a text/table alternative;
- language: plain English labels by default; localization is a separate decision rather than accidental mixed terminology.

These are working defaults from GF-22, not permission to skip stakeholder review.

## 4. UI evidence to capture before recreating a page

For each legacy page/flow, record:

1. actor, office, program/cycle/session scope;
2. entry route and breadcrumb context;
3. page purpose and primary action;
4. visible data fields and redaction expectations;
5. loading, empty, denied, validation, conflict, offline, queued, failed, and success states;
6. bulk action semantics and selection scope;
7. notification/toast wording and whether it claims commit or delivery;
8. responsive behavior and minimum useful viewport;
9. keyboard/focus/label/contrast issues observed;
10. print/export/document behavior;
11. target disposition and unresolved question.

## 5. Page recreation rule

Do not recreate an entire legacy page because it exists. Recreate a target page only after the page responsibility, authorized query, command list, scope, state contract, and acceptance evidence are recorded in the greenfield/OpenSpec chain.
