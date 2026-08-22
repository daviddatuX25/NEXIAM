# NEXIAM — Client Visit Brief Kit (Read-Through Material)

**Phase:** RAD — Requirements Planning (Session 2) + User Design (Session 1)
**Purpose:** Bring this kit to the client offices so they are updated and aligned with the system vision, and so we leave with a signed, validated decision matrix.

**Simulation note:** This kit is written as if the project is exactly at this point of the RAD timeline (2nd requirements session, 1st user-design session). It reflects interviews already done (June–July 2026).

---

## Kit contents

| File | What it is | Use at the visit |
|---|---|---|
| `client-brief.html` | **The read-through brief** (single print-ready page) | Main document — read through with the client page by page |
| `client-brief.md` | Same content, markdown source | Editing / reference only |
| `ascii-wireframes.html` | **Lofi wireframe pad** — all 23 screens in ASCII | Photo op: hold/tab through with the client, take the photo |
| `ascii-wireframes.md` | Same, markdown source | Editing / reference only |
| `ui-kit-index.html` | Index page linking the real HTML wireframes + UI kit | Laptop/tablet: show the "real look" after the lofi pad |

Related artifacts that already exist (referenced, not copied here):
- `../02-user-design/wireframes/` — detailed HTML wireframes per role (applicant, guidance, registrar, program head)
- `../../../docs/greenfield-foundation/evidence/ui-kit-reference/` — locked UI kit HTML (staff shell light/dark, management list)
- `../01-requirements-planning/` — interview guides, questionnaires, CSV responses, feature alignment checklist, use case diagram

---

## Browser access over ZeroTier

The documentation is mounted read-only into the existing NEXIAM web service, so no separate documentation port is required.

Open the documentation home from a ZeroTier-connected device:

- `http://192.168.192.141:18000/rad-work/00-client-brief/`
- `http://10.147.19.141:18000/rad-work/00-client-brief/` if the phone is also connected to the Dxtech Server network

Direct rendered Markdown pages:

- `http://192.168.192.141:18000/rad-work/00-client-brief/markdown-viewer.html?doc=client-brief.md`
- `http://192.168.192.141:18000/rad-work/00-client-brief/markdown-viewer.html?doc=ascii-wireframes.md`

The standalone `serve.py` remains available for local-only work, but port `8765` may be blocked by the host/network firewall. The recommended phone path is the existing NEXIAM port `18000`.

## Visit logistics

**Who we visit (recommended order):**
1. **Guidance Office** (primary — the main user; ICAT test administrators) — full 1st-session recap + everything
2. **Registrar Office** — recap in brief, focus on Digitized Processes #1, #9, #10 and quota alerts
3. **Program Heads** (via the guidance/admin coordinator, i.e. Sir Sonny, or per program) — quota pipeline + pre-enrollment slate + alerts

**What to bring:**
- [ ] Printed `client-brief.html` (2 copies — one for reading, one to leave with the office)
- [ ] Printed `ascii-wireframes.html` (the lofi pad)
- [ ] Laptop/tablet with `ui-kit-index.html` → real wireframes + UI kit opened
- [ ] Pen for the client to mark the decision matrix
- [ ] Camera/phone for the photo op (ask permission first)
- [ ] This README as the team's own checklist

**Time:** ~45–60 min per office (recap 10 min, From→To + decision matrix 25 min, wireframes + photo 10–15 min, sign-off 5 min)

---

## Photo-op checklist (evidence for Chapter 3 / User Design)

- [ ] Photo 1: client holding/touching the lofi ASCII wireframe pad (permission first)
- [ ] Photo 2: decision matrix page with client's marks/signature
- [ ] Photo 3 (optional): client at the laptop viewing the real UI kit
- [ ] After visit: name files `guidance_req2_2026-08-XX.jpg`, `registrar_req2_...`, `proghead_req2_...` and move them under `../01-requirements-planning/Preliminary Interviews & Questionaire/` (or a new `visit-evidence/` folder)

---

## After each visit

1. Transcribe any verbal remarks into the decision matrix (kept in `client-brief.md` / this folder)
2. Mark each From→To row `Agreed / Modified / Disagreed`
3. Log new gaps/facts discovered → update `../01-requirements-planning/feature-alignment-checklist.md`
4. File the signed page as the validation record (this becomes Chapter 3 SO1/SO2 evidence)