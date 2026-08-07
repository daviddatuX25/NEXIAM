# Design: Institutional Setup Readiness

## Founder resolutions (D-087 + 2026-08-06 refinement)

| Topic | Decision |
|---|---|
| Rooms / courses / cycles / windows | **Shared** Registrar ↔ Guidance write (`registrar.setup.manage`) |
| Privacy / intake grade fields | Institutional setup (shared setup capability) |
| **Aptitude areas (and later rating scales / result templates)** | **Test Administrator only** — Guidance Counselor inherent TA (`guidance.assessment.catalog`); Registrar sees readiness but does **not** configure |
| Setup hub UX | Mimic legacy: **per category** create + activate checks; **one Configure href per category** (never merge aptitude + privacy) |
| Quotas | PH **PROGRAM** write **or** Guidance TA write + PH communication seam |
| Timing | Quota readiness **before** result release / pre-enrolment |

## Capability split

| Capability | Surfaces |
|---|---|
| `registrar.setup.manage` | Cycles/windows, programs, rooms, privacy, intake grades |
| `guidance.assessment.catalog` | Aptitude areas (TA-sensitive) |
| `program.quota.manage` | Program quotas |

## Readiness categories (legacy 1:1)

Each category: own `href`, checks for **created** then **activated** (and optional extras).

| Category | href | Who configures |
|---|---|---|
| Academic years & application windows | `/setup/cycles` | setup.manage |
| Programs & courses | `/setup/programs` | setup.manage |
| Rooms & facilities | `/setup/rooms` | setup.manage |
| Privacy policies | `/setup/privacy` | setup.manage |
| Intake grade fields | `/setup/grades` | setup.manage |
| Aptitude areas | `/setup/aptitude` | assessment.catalog (TA) |
| Program quotas | `/setup/quotas` | quota.manage |

Hub shows all categories for awareness; Configure button only when the actor holds that category’s capability.

## Staff vocabulary (2026-08-06)

Internal aggregate remains `AcademicCycle` (GF-05 / D-CYCLE-01). Staff-facing setup uses legacy Academic Year language:

- `school_year` (e.g. 2025-2026) + `semester` (`1` / `2`) on each cycle row
- `ApplicationWindow` still controls when public apply is open for that year/semester
- Route stays `/setup/cycles`; labels say Academic years
