## 1. Projection and simulated content

- [x] 1.1 Add `config/public-site.php` with replaceable preview identity, mission copy, fixed stock media URLs, story blocks, announcements, program metadata, and source markers.
- [x] 1.2 Add `App\PublicSite\Queries\PublicHomeProjection` to combine configured preview content, active academic-cycle truth, and active seeded programs.
- [x] 1.3 Replace the root route closure's inline public props with the projection while preserving `/`, `/apply`, `/applicant/login`, `/login`, `/staff`, and `/applicant/portal` destinations.
- [x] 1.4 Add focused Pest coverage for projection shape, active-season absence, simulated preview metadata, and seeded program fallback.

## 2. Public shell and reusable composites

- [x] 2.1 Rework `HomeLayout.svelte` into an institution-first public shell with official-name-first navigation, utility applicant/staff actions, mobile menu behavior, and footer service attribution.
- [x] 2.2 Add `Components/public/MediaFrame.svelte` with stable aspect-ratio layout, alt text, eager/lazy loading inputs, simulated-source marker, and media fallback.
- [x] 2.3 Add `Components/public/StorySection.svelte` with editorial media/text variants and reduced-motion-safe Motion.dev reveal behavior.
- [x] 2.4 Add `Components/public/ProgramExplorer.svelte` using seeded/configured program data, clear program discovery links, and empty/fallback state.
- [x] 2.5 Add `Components/public/AdmissionsGateway.svelte` with active-cycle truth, Apply, Applicant Portal, and requirements/next-step links.
- [x] 2.6 Add `Components/public/AnnouncementRail.svelte` with populated and no-announcement states.

## 3. Institution-first homepage

- [x] 3.1 Replace the feature-led Home hero with institution identity, simulated/replaceable hero media, mission-led welcome copy, and `Explore programs` as primary action.
- [x] 3.2 Compose mission, program explorer, campus/student story, admissions gateway, announcement rail, and contact/visit sections in a non-card-grid editorial sequence.
- [x] 3.3 Add local-only preview notice and ensure simulated content is never presented as verified institutional evidence.
- [x] 3.4 Remove the legacy process strip from the first viewport; retain admissions workflow detail for the admissions gateway/supporting route.
- [x] 3.5 Add public page metadata, semantic landmarks, descriptive image alt text, keyboard focus states, and mobile no-overflow behavior.
- [x] 3.6 Add or update `/ui-kit` public reference coverage for shell, media, story, program, admissions, announcement, reduced-motion, and fallback states.

## 4. Motion, accessibility, and verification

- [x] 4.1 Add bounded public reveal/transition choreography using existing Motion.dev helpers; no scroll event loops, autoplay audio, or scroll hijacking.
- [x] 4.2 Verify reduced-motion mode renders all content immediately and preserves focus/keyboard behavior.
- [x] 4.3 Run focused Pest, Vitest, Svelte check, lint, format, and production asset build checks; record pre-existing failures separately.
- [x] 4.4 Browser-verify desktop and mobile populated/empty/preview states, media fallback, navigation, CTAs, dark mode, reduced motion, no horizontal overflow, and zero new console errors.
- [x] 4.5 Update this change's `HANDOFF.md` with simulated asset replacement inventory, seeded credentials, URLs, reset commands, and verification evidence.
- [x] 4.6 Update the master reference checkpoint with the approved institutional-public-experience change and its interim simulated-content limitation.
