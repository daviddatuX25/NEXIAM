## Context

NEXIAM currently serves `/` from `system/resources/js/Pages/Home.svelte` inside a public shell that was ported from the legacy home page. The result preserves the legacy one-screen hero and admissions process strip, but it foregrounds NEXIAM and workflow language instead of ISPSC Tagudin's institutional welcome. The staff/applicant kit is intentionally locked around IBM Plex Sans, brand green, layered paper/glass, `0.5rem` radius, responsive shells, and restrained operational motion. This change adds an expressive public layer without changing staff workflow behavior or authorization.

The institution's approved photography, mission copy, program descriptions, and announcements are not yet available. Development therefore uses fixed remote stock-image URLs and explicitly marked simulated content behind a local preview flag. The projection must make replacement deterministic: approved media and copy can replace config entries without changing page structure.

## Goals / Non-Goals

**Goals:**

- Make the public homepage institution-first for prospective students and families.
- Establish the public navigation and content structure for Home, About/Mission, Programs, Admissions, Campus Life, News, and Contact/Visit.
- Make Explore Programs the primary action; keep Apply and Applicant Portal visible but secondary.
- Add reusable public components for media, editorial sections, program discovery, admissions gateway, announcements, and footer content.
- Add purposeful Motion.dev reveals with reduced-motion behavior and no scroll hijacking.
- Keep simulated media/content replaceable and visibly marked in local preview mode.
- Preserve the current staff/applicant UI kit and all existing authentication/application routes.
- Prove light/dark, mobile, keyboard, reduced-motion, no-overflow, and console-error behavior in the browser.

**Non-Goals:**

- No institutional claims, official mission wording, program facts, campus achievements, or student outcomes beyond verified source material.
- No database schema or domain-workflow changes.
- No React dependency or direct SmoothUI component adoption in the Svelte app.
- No automatic audio, ambient sound, scroll-jacking, WebGL requirement, or provider/AI integration.
- No replacement of the locked staff shell, Nexi chrome, or management-list primitives.
- No implementation of every supporting public route in this first slice; the homepage establishes their navigation/content contracts.

## Decisions

### 1. Use a public projection plus replaceable configuration

Create `App\PublicSite\Queries\PublicHomeProjection` as the server-owned read projection for `/`. It combines the current institution/cycle/program catalog with `config/public-site.php` for simulated copy, media, story blocks, and announcement fixtures. The projection emits a stable page contract (`institution`, `activeSeason`, `programs`, `stories`, `announcements`, `media`, `preview`). This keeps the root route thin and makes approved database/CMS content a later source replacement rather than a page rewrite.

Alternative rejected: embedding all content directly in `Home.svelte`, because it would make factual copy, data ownership, and asset replacement opaque.

### 2. Use fixed stock media during development, never random source URLs

Use deterministic remote Unsplash image URLs in config with descriptive alt text and a `source: simulated` marker. The local preview displays a discreet replaceable-content notice. Hero media loads eagerly; lower media is lazy and uses explicit aspect-ratio containers. Production asset replacement changes config/asset records only.

Alternative rejected: random `source.unsplash.com` URLs, because they are unstable, unreviewable, and can change visual content between screenshots.

### 3. Build a public editorial composition, not a feature-card grid

The homepage sequence is: institutional hero, mission, program explorer, campus/student story, admissions gateway, announcements, and contact/footer. Program cards are content-discovery controls, not claims about system features. NEXIAM appears as a quiet admissions/testing service identity in the shell/gateway/footer.

Alternative rejected: keeping the five-card process strip in the first viewport, because it repeats workflow language before the visitor understands the institution.

### 4. Extend the kit with public composites while retaining core tokens

Add public composites under `system/resources/js/Components/public/` and reuse existing UI primitives. Keep the current staff token lock unchanged. If a later founder decision changes shared fonts, radii, or core colors, update the locked evidence, `app.css`, live gallery, and static twin together under a separate decision/task.

Alternative rejected: importing a second design system or copying React dashboard/animation packages into the Svelte tree.

### 5. Use Motion.dev for one coherent motion grammar

Use existing `motion` dependency and `lib/motion.js` actions for in-view reveals and staggered content. Add only purposeful transitions: media reveal, section entrance, program focus, and admissions CTA emphasis. Respect `prefers-reduced-motion`; content is visible without JavaScript animation. A future route-transition primitive may use progressive View Transitions where supported, with a normal Inertia navigation fallback.

Alternative rejected: adding SmoothUI as a dependency, because it targets React and would duplicate/adapt components rather than fit this Svelte codebase.

### 6. Keep sound optional and outside the first required path

Do not add audio to the initial homepage implementation. Reserve a future opt-in sound toggle and asset contract; sound must be muted by default, persisted, non-essential, and absent when reduced-motion/assistive preferences require a quieter experience.

## Risks / Trade-offs

- **Simulated media can look like institutional proof.** Mitigation: local preview marker, source metadata, conservative copy, and a replacement inventory in `HANDOFF.md`.
- **Remote stock URLs can fail or change.** Mitigation: fixed IDs, explicit fallback styling, and a later local asset import before production.
- **A richer homepage may drift from the staff kit.** Mitigation: separate public composites, shared semantic tokens, and no changes to staff defaults in this change.
- **Motion can reduce accessibility or performance.** Mitigation: reduced-motion fallback, bounded transforms, lazy lower media, no scroll listeners, and browser checks at mobile/desktop.
- **Program catalog data is currently synthetic.** Mitigation: render actual seeded catalog names only as demo data, include preview metadata, and make copy/metadata replacement config-driven.
- **Public foundation routes may be incomplete.** Mitigation: expose navigation contracts and disabled/placeholder states honestly; do not add dead links without a planned route or clear coming-soon state.
