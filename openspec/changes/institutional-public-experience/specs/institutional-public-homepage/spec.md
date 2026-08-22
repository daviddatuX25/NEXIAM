## ADDED Requirements

### Requirement: Institution-first public homepage

The public root route MUST present the institution as the primary identity and welcome prospective students and families before exposing admissions workflow details. NEXIAM MAY appear as the admissions/testing service identity but MUST NOT be the hero's primary subject.

#### Scenario: First visit sees the institution before the workflow
- **WHEN** a guest visits `/`
- **THEN** the first viewport contains the approved or explicitly simulated institution name, campus identity, welcoming institutional statement, real-or-replaceable media, and a primary `Explore programs` action
- **AND** applicant/staff authentication actions remain available without dominating the hero

#### Scenario: Simulated content is honest during development
- **WHEN** the public projection is marked as preview/simulated in a local environment
- **THEN** the page displays a discreet replaceable-content notice
- **AND** media/content metadata identifies simulated sources for handoff
- **AND** the page does not invent outcome, accreditation, ranking, or institutional achievement claims

### Requirement: Public institution story sequence

The homepage MUST provide a coherent, responsive sequence for mission, programs, campus/student experience, admissions, announcements, and contact/visit content. Sections MUST be readable without animation and MUST not present the admissions process as the first content after the hero.

#### Scenario: Visitor explores the institution
- **WHEN** a visitor scrolls the homepage
- **THEN** mission/story content appears before the detailed admissions gateway
- **AND** program discovery provides a clear route to `/apply` or a future program detail page
- **AND** a visitor can find contact/visit information in the footer or final section

#### Scenario: No announcement data is available
- **WHEN** the projection contains no announcements
- **THEN** the page renders an honest empty/quiet state or omits the announcement rail without fabricated news

### Requirement: Admissions gateway remains discoverable

The homepage MUST expose `Explore programs`, `Start your application`, and `Applicant Portal` actions with distinct intent. The active academic cycle MAY be shown as a truthful admissions-window notice sourced from the server projection.

#### Scenario: Visitor is ready to apply
- **WHEN** a visitor activates `Start your application`
- **THEN** Inertia navigation opens `/apply`
- **AND** the active-season window is not claimed unless the server projection contains an active cycle

#### Scenario: Existing applicant enters the portal
- **WHEN** a visitor activates `Applicant Portal`
- **THEN** navigation opens `/applicant/login` for guests
- **AND** an authenticated applicant receives the existing portal destination without a new authorization path

### Requirement: Responsive public navigation

The public shell MUST provide institutional navigation for Home, About/Mission, Programs, Admissions, Campus Life, News, and Contact/Visit as routes become available. It MUST preserve the existing `/`, `/apply`, `/applicant/login`, `/login`, `/staff`, and `/applicant/portal` destinations.

#### Scenario: Mobile visitor opens navigation
- **WHEN** the viewport is below the mobile navigation breakpoint
- **THEN** the public navigation remains keyboard accessible, exposes all available destinations, and does not create horizontal overflow
- **AND** primary actions maintain touch targets of at least 44px

### Requirement: Public media behavior

Public media MUST use explicit dimensions/aspect-ratio containers, descriptive alternative text, deterministic sources, and loading behavior appropriate to position. Remote simulated media MUST be replaceable without changing page composition.

#### Scenario: Hero media loads
- **WHEN** the homepage first renders
- **THEN** the hero media has a stable layout box, descriptive alt text, and eager/high-priority loading appropriate for the first viewport
- **AND** lower-page media uses lazy loading where supported

#### Scenario: Media fails
- **WHEN** a media request fails or is unavailable
- **THEN** the content remains readable with a styled fallback surface and no broken-layout collapse

### Requirement: Accessible public motion

Public motion MUST be progressive enhancement. The homepage MUST remain fully readable and usable when motion is reduced, JavaScript animation is unavailable, or a user navigates by keyboard.

#### Scenario: Reduced motion is requested
- **WHEN** `prefers-reduced-motion: reduce` matches
- **THEN** reveal transforms and decorative transitions are disabled or reduced
- **AND** headings, media, actions, and program content remain visible without waiting for animation

#### Scenario: Visitor uses keyboard navigation
- **WHEN** a visitor tabs through the public page
- **THEN** focus is visible, focus order follows the reading order, and interactive media/program elements expose accessible names
