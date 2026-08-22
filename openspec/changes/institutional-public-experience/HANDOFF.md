# HANDOFF — Institutional Public Experience (`institutional-public-experience`)

## Status

Implementation complete; founder review remains required before simulated copy and remote stock media are treated as launch-ready institutional content.

## What ships

- Institution-first public homepage at `/` for Ilocos Sur Polytechnic State College — Tagudin Campus.
- Legacy public shell shape retained as evidence: institutional header, anchor navigation, applicant/staff entry points, footer service attribution, and responsive mobile menu.
- New public sequence: hero, mission, program explorer, campus stories, admissions gateway, announcements, preview notice, and contact/footer.
- `Explore programs` is the primary hero action; Apply, Applicant Portal, and Staff sign in remain visible secondary paths.
- Auth-aware portal links: staff → `/staff`, applicant → `/applicant/portal`, guest → `/applicant/login`.
- Theme control with persisted light/dark preference; mobile menu includes the same control.
- Reduced-motion-safe Motion.dev reveals, fixed aspect-ratio media, remote-media fallback, semantic landmarks, focus rings, and mobile no-overflow behavior.
- Public UI-kit reference at `/ui-kit` includes media, story, program, admissions, announcement, reduced-motion, and fallback states.

## Legacy trace and disposition

| Legacy evidence | Greenfield disposition |
|---|---|
| `legacy_codebase/application/resources/js/Pages/Home/Index.svelte` | Preserve public entry-point intent and service CTAs; rewrite hierarchy institution-first under this change. |
| `legacy_codebase/application/resources/js/Layouts/HomeLayout.svelte` | Preserve sticky identity/navigation/footer affordances; rewrite with the greenfield public shell and auth-aware links. |
| `legacy_codebase/application/resources/js/Components/blocks/ProcessFlow.svelte` | Remove from the first viewport; admissions workflow detail now lives in the admissions gateway/supporting journey. |
| `legacy_codebase/application/app/Http/Controllers/HomeController.php` | Preserve active-season truth through `AcademicCycle`; replace inline root props with `PublicHomeProjection`. |

## Content and media replacement inventory

Replace these preview entries before public launch:

- `system/config/public-site.php` — institution copy, hero, stories, program fallback metadata, announcements, and preview notice.
- Hero image — Unsplash photo ID `1498243691581-b145c3f54a5a`; current source marker: `simulated stock photography`.
- Story image 1 — Unsplash photo ID `1522202176988-66273c2fd55f`; current source marker: `simulated stock photography`.
- Story image 2 — Unsplash photo ID `1517486808906-6ca8b3f04846`; current source marker: `simulated stock photography`.
- Replace remote URLs with approved local or managed institutional assets and retain descriptive alt text.
- Set `PUBLIC_SITE_PREVIEW=false` only after approved copy and media have replaced the preview inventory.

No institutional outcome, ranking, accreditation, or achievement claim is included.

## Seed and recreate

```bash
cd system
docker compose exec -T app php artisan foundation:reset --force
```

Expected seeded verification identities:

- Staff: `registrar.clerk@nexiam.test` / `password`
- Applicant: `PORT-DEFL` / birthdate `2003-09-25`

## URLs and expected state

- `/` — institution-first homepage, active season badge when a cycle is seeded, preview marker in local mode.
- `/apply` — existing applicant intake flow.
- `/applicant/login` — existing applicant portal entry.
- `/login` — existing staff entry.
- `/ui-kit` — authenticated public foundation reference states.

## Verification evidence

- Pest: `docker compose exec -T app php artisan test tests/Feature/FoundationHealthTest.php --compact` — 4 tests passed, 51 assertions.
- Vitest: `npm test` — 6 files passed, 19 tests passed.
- Targeted ESLint: `npx eslint resources/js/Pages/Home.svelte resources/js/Layouts/HomeLayout.svelte resources/js/Pages/UiKit/Gallery.svelte resources/js/Components/public/*.svelte` — passed.
- Formatting: targeted Prettier check — passed.
- Production build: `npm run build` and Docker app rebuild — passed; unrelated existing Svelte warnings remain in other pages.
- Browser desktop at 1280px: institution-first hero, all public section anchors, loaded hero media, theme toggle, no horizontal overflow, zero new console errors.
- Browser mobile at 390px: wrapped institution copy, mobile navigation, theme control, no horizontal overflow, zero hidden reduced-motion content.
- Browser media failure: invalid hero source rendered the accessible image placeholder and replacement copy.
- Browser lower-page scroll: all three configured images loaded with descriptive alt text; reduced-motion mode left revealed content visible.
- `npm run check` remains blocked by the repository's existing Vite/svelte-check configuration and unrelated TypeScript/implicit-any diagnostics; the full `npm run lint` remains blocked by 151 pre-existing diagnostics. Changed public files pass targeted lint/build checks.

## Founder review gates

- Approve or replace the simulated institution copy and three media assets.
- Confirm whether `/about`, `/programs`, `/admissions`, `/campus-life`, `/news`, and `/contact` remain homepage anchors or receive dedicated public routes.
- Keep sound absent unless a later approved change defines an opt-in, muted-by-default, reduced-motion-aware asset contract.
