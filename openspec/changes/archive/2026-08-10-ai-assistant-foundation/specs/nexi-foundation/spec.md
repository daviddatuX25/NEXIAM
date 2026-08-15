## ADDED Requirements

### Requirement: Server-owned SurfaceProfile resolution
The system SHALL resolve exactly one code-owned `SurfaceProfile` for each authenticated Nexi-eligible request from trusted server context (matched route + auth/duty), using deny-on-ambiguity precedence, and SHALL NOT derive the profile from client URLs or role-name strings alone.

#### Scenario: Staff workspace resolves staff profile
- **WHEN** an authenticated staff actor opens a staff workspace route with Nexi enabled
- **THEN** the Inertia `nexi` prop names `surface.key = staff` (versioned) and does not reuse an applicant pack

#### Scenario: Applicant portal resolves applicant profile
- **WHEN** an authenticated applicant opens `/applicant/portal` with Nexi enabled for applicants
- **THEN** the Inertia `nexi` prop names `surface.key = applicant` and contains no staff operational pack keys

#### Scenario: Ambiguous or unknown surface fails closed
- **WHEN** surface resolution is ambiguous or the route is not registered for a pack
- **THEN** Nexi availability is `hidden` or an explicit fail-closed degraded state without tools, and no previous page pack is inherited

#### Scenario: Public and login shells omit authenticated Nexi
- **WHEN** a guest opens `/` or `/login`
- **THEN** no authenticated `NexiSessionView` conversation surface is mounted

### Requirement: PageCapabilityPack from matched route name
The system SHALL map each included canonical Laravel route name to at most one owned `(pack_key, pack_version)` artifact at build/boot time, default allowed modes to Inquiry for foundation packs, and SHALL treat packs as relevance/disclosure filters that never grant authorization.

#### Scenario: Setup readiness pack is Inquiry-only
- **WHEN** staff opens the setup readiness hub route registered to `staff.setup.readiness@1`
- **THEN** `nexi.page_pack` identifies that pack and `active_mode` is `inquiry` with no activatable Assistant mutation tools

#### Scenario: Duplicate route registration fails
- **WHEN** two packs claim the same canonical route name
- **THEN** boot/build fails closed rather than picking one silently

### Requirement: Typed NexiSessionView share
The system SHALL share a versioned `NexiSessionView` (`contract_version = 1`) as the Inertia `nexi` prop containing surface, context epoch, page pack, availability enum, modes, disclosure, nullable conversation cursors, usage presentation, empty proposals, and manual fallback intents.

#### Scenario: Unknown contract version degrades safely
- **WHEN** the client receives an unsupported `contract_version`
- **THEN** the dock renders degraded/manual fallback and does not execute unknown fields as authority

#### Scenario: Availability enum is canonical
- **WHEN** the server projects Nexi state
- **THEN** `availability` is one of `hidden`, `inquiry_only`, `assistant_available`, `assistant_active`, `degraded`, `quota_exhausted`, `suspended`

### Requirement: Shared degraded Svelte dock
Authenticated staff and applicant shells SHALL mount one shared Nexi presentation adapter driven only by server `nexi` props, preserving locked wrap-island chrome, with client ownership limited to presentation preferences.

#### Scenario: No provider calls in foundation cut
- **WHEN** a user opens Nexi on staff home or applicant portal under this OpenSpec
- **THEN** the UI shows degraded or Inquiry-only guidance and does not call an LLM provider or SDK

#### Scenario: Client stubs are non-authoritative
- **WHEN** production Nexi decides mode, pack, or suggestions
- **THEN** it does not use `nexi-context.js` / `nexi-actions.js` URL parsing or local capability arrays as authority

#### Scenario: Accessibility baseline
- **WHEN** a keyboard and reduced-motion user opens the dock on staff or applicant shells
- **THEN** focus order remains usable and motion-safe fallbacks apply without conveying authority via animation

### Requirement: Foundation non-goals enforced
This change SHALL NOT install a provider SDK, persist conversation/run/proposal tables, expose propose/command tools to a model, or enable RAG/embeddings.

#### Scenario: Assistant mutation path absent
- **WHEN** the foundation dock is shown
- **THEN** no confirm-to-command proposal execution endpoint is required or exposed for Nexi mutations
