## ADDED Requirements

### Requirement: Server-owned public home projection

The public root route MUST receive a stable server-authored projection containing institution identity, active-season truth, program catalog entries, story/media blocks, announcements, and preview metadata. Controllers/routes MUST delegate projection assembly to a query/support class rather than embedding business/data branching in the page component.

#### Scenario: Projection returns a seeded preview
- **WHEN** the development database and public-site config are available
- **THEN** the projection returns deterministic institution, program, media, story, and preview fields
- **AND** the page can render without hand-created browser fixtures

#### Scenario: Active cycle is absent
- **WHEN** no active academic cycle exists
- **THEN** `activeSeason` is null or an equivalent explicit closed state
- **AND** the public page does not claim that admissions are open

### Requirement: Replaceable simulated content

Simulated copy and stock media MUST be declared in a replaceable configuration/content source with source metadata. The public components MUST consume the projection shape rather than hard-code institutional facts in markup.

#### Scenario: Approved asset replacement
- **WHEN** a simulated media URL, mission copy, program description, or announcement is replaced in the configured source
- **THEN** the same public component renders the replacement without a structural page rewrite

#### Scenario: Preview metadata is disabled
- **WHEN** preview metadata is disabled for a production-like environment
- **THEN** the page omits the local preview notice while retaining only configured content
- **AND** the application does not silently label simulated content as verified institutional evidence

### Requirement: Reusable public composites

The public experience MUST provide reusable components for the public shell, media frame, editorial story section, program explorer, admissions gateway, announcement rail, and institutional footer. Components MUST reuse existing UI primitives where a primitive already exists.

#### Scenario: Homepage composes public sections
- **WHEN** `Home.svelte` renders
- **THEN** the page composes public components with server projection data
- **AND** repeated layout/interaction rules are not duplicated in unrelated page markup

### Requirement: Motion and media contracts

Public motion and media components MUST use the existing Motion.dev dependency or native CSS/Svelte behavior, support reduced motion, avoid scroll event loops, and preserve content visibility by default. Media components MUST expose alt/fallback/loading inputs.

#### Scenario: Motion action is mounted
- **WHEN** a public section uses an in-view reveal
- **THEN** the action cleans up observers/animations on destroy
- **AND** the section remains visible when reduced motion is requested

#### Scenario: Public component is reused
- **WHEN** a component is mounted with missing optional media or empty content
- **THEN** it renders an intentional fallback/empty state rather than throwing or leaving a collapsed region

### Requirement: Public kit gallery coverage

The live `/ui-kit` gallery or an equivalent public reference surface MUST include the public shell/media/story/program/admissions primitives and document their light/dark, responsive, reduced-motion, and fallback states before the change is considered complete.

#### Scenario: Reviewer inspects public primitives
- **WHEN** a reviewer opens the public kit section
- **THEN** each public composite has a representative populated state and at least one relevant fallback or reduced-motion state
- **AND** the existing staff management/Nexi sections remain available
