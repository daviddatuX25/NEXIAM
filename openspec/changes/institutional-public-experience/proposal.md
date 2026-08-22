## Why

The current public landing is a functional legacy-parity port, but it presents NEXIAM and the admissions workflow before the institution itself. Prospective students and families need an authentic first impression of ISPSC Tagudin: welcome, mission, programs, campus life, and a clear path into admissions. Real institutional assets are not yet available, so the first implementation will use clearly replaceable stock imagery and simulated content while keeping claims conservative and easy to swap.

## What Changes

- Replace the feature-led public landing with an institution-first public experience for prospective students and families.
- Establish a public information foundation for Home, About/Mission, Programs, Admissions, Campus Life, News, and Contact/Visit routes.
- Make Explore Programs the primary homepage action; keep Apply and Applicant Portal visible but secondary.
- Reposition NEXIAM as the admissions/testing service behind the institution rather than the public hero identity.
- Add reusable public-shell, media, editorial-story, program-explorer, admissions-gateway, announcement, and footer patterns without weakening the operational staff UI kit.
- Add purposeful Motion.dev-based reveals and route transitions with reduced-motion fallbacks; avoid scroll hijacking and animation-first content.
- Reserve an optional, opt-in sound layer; no autoplay and no essential state may depend on sound.
- Use replaceable stock images and simulated program/announcement data for development; keep a documented replacement inventory for approved institutional assets and copy.
- **BREAKING**: The public homepage composition and hierarchy change from NEXIAM/workflow-first to institution-first; admissions workflow details move into the public Admissions section or supporting pages.

## Capabilities

### New Capabilities

- `institutional-public-homepage`: Institution-first public homepage with real-or-replaceable media, mission/story sections, program discovery entry, admissions gateway, and responsive public navigation.
- `public-experience-foundation`: Reusable public shell, content/media primitives, motion contracts, simulated-content seams, and public information-architecture routes.

### Modified Capabilities

- None. Existing staff/applicant workflow capabilities remain behaviorally unchanged; this change modifies public presentation and adds public entry points only.
