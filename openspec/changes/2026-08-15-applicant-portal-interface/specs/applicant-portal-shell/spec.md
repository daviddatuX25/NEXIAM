# applicant-portal-shell Spec

## ADDED Requirements

### Requirement: Server-authorized applicant navigation

The applicant shell SHALL render navigation items only from a server projection that already applied capability, scope, and state checks. The client MUST NOT hide or unhide items as an authorization boundary (REQ-VS-027). Hidden-for-actor items MUST NOT be serialized to the browser.

#### Scenario: Deep page for a non-applicable stage
- **WHEN** the projection omits the Results item because no release exists
- **THEN** the Results nav item does not serialize and no client-side condition can reveal it

#### Scenario: Every nav item is capability-backed
- **WHEN** an applicant opens the shell
- **THEN** each rendered item maps to an existing application capability or an own-scope route, and no item is rendered from a bare role string

### Requirement: Applicant breadcrumbs on deep pages

Every deep applicant page SHALL render a server-authored breadcrumb trail whose ancestors are safe for the actor, with the final crumb marked `aria-current="page"` and driving the document title. The hub itself SHALL NOT render a breadcrumb. Mobile SHALL collapse multi-level trails without losing the current crumb.

#### Scenario: Deep page with collapsed trail
- **WHEN** an applicant opens `/applicant/schedule` on a narrow viewport
- **THEN** the breadcrumb collapses to the current crumb plus an accessible ancestor menu, and the document title equals the current crumb label

### Requirement: Applicant shell uses the locked UI kit

The applicant shell SHALL use the locked tokens — IBM Plex Sans, `--radius: 0.5rem`, layered paper/glass surfaces, brand green, dark token block — and SHALL NOT introduce new fonts, radii, or marketing palettes (ui-kit-locked-start-set §2). The NexiDock SHALL remain the only assistant chrome, with disclosed assistant states.

#### Scenario: Kit drift
- **WHEN** a new applicant page is added
- **THEN** it inherits kit tokens and reuses kit components; a second visual convention is not created

### Requirement: Sign-out and shared-device safety

The applicant shell SHALL keep Sign out reachable on every authenticated page and SHALL show shared-device guidance copy on the hub.

#### Scenario: Shared device
- **WHEN** an applicant signs out from any applicant page
- **THEN** the session clears and the login page does not preserve the access key unless the applicant chose device save at login
