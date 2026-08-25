<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>NEXIAM · Client Visit Brief — Requirements + User Design</title>
  <style>
    :root { color-scheme: dark; --bg: #090d1c; --panel: #11182b; --line: #2a3656; --text: #eef2ff; --muted: #a8b3d0; --accent: #73a7ff; --ok: #72ddb0; --warn: #f6c453; --danger: #ff8fa3; }
    * { box-sizing: border-box; }
    body { margin: 0; background: var(--bg); color: var(--text); font: 15px/1.5 system-ui, -apple-system, sans-serif; }
    main { width: min(1180px, calc(100% - 2rem)); margin: 0 auto; padding: 1.4rem 0 3rem; }
    header { border-bottom: 1px solid var(--line); padding-bottom: .9rem; }
    .eyebrow { color: var(--accent); font-size: .68rem; font-weight: 800; letter-spacing: .2em; text-transform: uppercase; }
    h1 { margin: .3rem 0 .5rem; font-size: clamp(1.5rem, 3vw, 2.1rem); line-height: 1.1; }
    .sub { color: var(--muted); font-size: .88rem; margin: 0; }
    a { color: var(--accent); }
    .actions { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .7rem; }
    .actions a { border: 1px solid var(--line); border-radius: .6rem; padding: .3rem .6rem; text-decoration: none; font-size: .78rem; color: var(--muted); }
    .actions a:hover { color: var(--text); border-color: var(--accent); }
    .actions a.primary { background: var(--accent); color: #081126; font-weight: 700; }
    .savebar { position: sticky; top: .5rem; z-index: 20; display: flex; align-items: center; justify-content: space-between; gap: 1rem; border: 1px solid var(--line); border-radius: .8rem; background: color-mix(in srgb, var(--panel) 92%, transparent); backdrop-filter: blur(8px); padding: .4rem .8rem; margin: .9rem 0 .6rem; }
    .savebar .status { color: var(--muted); font-size: .8rem; } .savebar .status.ok { color: var(--ok); } .savebar .status.err { color: var(--danger); }
    .modes { display: inline-flex; gap: .2rem; border: 1px solid var(--line); border-radius: .6rem; padding: .2rem; }
    .modes button { border: 0; border-radius: .45rem; background: transparent; color: var(--muted); padding: .3rem .6rem; font: inherit; font-size: .8rem; cursor: pointer; }
    .modes button.active { background: var(--accent); color: #081126; font-weight: 700; }
    .progress { display: flex; align-items: center; gap: .8rem; margin: .4rem 0 .9rem; }
    .track { flex: 1; height: .4rem; border-radius: 999px; background: #0d1426; border: 1px solid var(--line); overflow: hidden; }
    .track-fill { height: 100%; width: 0; border-radius: 999px; background: linear-gradient(90deg, var(--accent), var(--ok)); transition: width .25s ease; }
    .counter { color: var(--muted); font-size: .78rem; white-space: nowrap; }
    .part-chip { color: var(--accent); font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; white-space: nowrap; }
    .stage { display: grid; grid-template-columns: 1fr 1fr; gap: .9rem; }
    .stage.interview .pane-briefing { display: none; }
    .stage.briefing .pane-interview { display: none; }
    @media (max-width: 900px) { .stage { grid-template-columns: 1fr; } }
    .pane { border: 1px solid var(--line); border-radius: 1rem; background: var(--panel); padding: 1.1rem 1.2rem; min-height: 300px; display: flex; flex-direction: column; }
    .pane-briefing { border-color: color-mix(in srgb, var(--accent) 30%, var(--line)); }
    .pane-interview { border-color: color-mix(in srgb, var(--ok) 30%, var(--line)); }
    .pane-label { display: flex; align-items: center; gap: .4rem; font-size: .66rem; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; margin: 0 0 .7rem; }
    .pane-label::before { content: ""; width: .45rem; height: .45rem; border-radius: 50%; }
    .pane-briefing .pane-label { color: var(--accent); } .pane-briefing .pane-label::before { background: var(--accent); }
    .pane-interview .pane-label { color: var(--ok); } .pane-interview .pane-label::before { background: var(--ok); }
    .step-no { color: var(--muted); font-size: .78rem; margin: 0 0 .25rem; }
    h3 { margin: 0 0 .5rem; font-size: 1.1rem; }
    .flow { display: flex; flex-direction: column; gap: .6rem; margin: .3rem 0; }
    .flow .from { border-left: 3px solid var(--danger); padding: .5rem .75rem; background: color-mix(in srgb, var(--danger) 8%, transparent); border-radius: .5rem; }
    .flow .to { border-left: 3px solid var(--ok); padding: .5rem .75rem; background: color-mix(in srgb, var(--ok) 8%, transparent); border-radius: .5rem; }
    .flow .why { color: var(--muted); font-size: .86rem; }
    .flow .why b { color: var(--text); }
    .arrow { color: var(--muted); text-align: center; font-size: .8rem; }
    .body { color: var(--muted); flex: 1; }
    .body strong { color: var(--text); }
    .prompt { color: var(--text); font-size: 1.02rem; }
    .pill-row { display: flex; flex-wrap: wrap; gap: .45rem; margin-top: 1rem; }
    .pill { display: inline-flex; align-items: center; gap: .35rem; border: 1px solid var(--line); border-radius: 999px; padding: .42rem .8rem; font-size: .88rem; color: var(--muted); cursor: pointer; background: transparent; transition: all .12s; }
    .pill input { margin: 0; accent-color: var(--accent); }
    .pill:has(input:checked) { border-color: var(--accent); color: var(--text); background: color-mix(in srgb, var(--accent) 14%, transparent); }
    .pill.checked { border-color: var(--accent); color: var(--text); background: color-mix(in srgb, var(--accent) 14%, transparent); }
    .textfield { width: 100%; border: 1px solid var(--line); border-radius: .6rem; background: #0a1122; color: var(--text); padding: .6rem .75rem; font: inherit; margin-top: .7rem; }
    .textfield:focus { outline: 2px solid var(--accent); border-color: transparent; }
    .muted-note { color: var(--muted); font-size: .86rem; margin: 0; }
    .partdone { margin-top: 1.2rem; }
    .partdone button { border: 1px solid var(--line); border-radius: .7rem; background: transparent; color: var(--text); padding: .6rem 1.1rem; font: inherit; font-weight: 700; cursor: pointer; }
    .partdone button.done { border-color: color-mix(in srgb, var(--ok) 50%, var(--line)); color: var(--ok); }
    .nav { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-top: 1.1rem; }
    .nav button { border: 1px solid var(--line); border-radius: .7rem; background: var(--panel); color: var(--text); padding: .6rem 1.2rem; font: inherit; font-weight: 600; cursor: pointer; }
    .nav button:disabled { opacity: .35; cursor: not-allowed; }
    .nav button.primary { background: var(--accent); border-color: var(--accent); color: #081126; }
    .nav .hint { color: var(--muted); font-size: .78rem; }
    footer { margin-top: 2.5rem; border-top: 1px solid var(--line); padding-top: 1rem; color: var(--muted); font-size: .82rem; }
    @media print { :root { color-scheme: light; } body { background: #fff; color: #17202a; } main { width: 100%; padding: 0; } .savebar, .modes, .progress, .nav, .actions, footer { display: none; } .stage { grid-template-columns: 1fr; } .pane { border-color: #cbd3da; background: #fff; color: #17202a; min-height: 0; page-break-inside: avoid; } }
  </style>
</head>
<body>
<main>
  <div class="savebar">
    <div class="modes" role="group" aria-label="View mode">
      <button type="button" data-mode="briefing">Briefing</button>
      <button type="button" data-mode="interview">Interview</button>
    </div>
    <div class="status" id="save-status">Loading saved state…</div>
  </div>

  <header>
    <div class="eyebrow">NEXIAM · requirements gathering + user design</div>
    <h1>Client visit brief</h1>
    <p class="sub">One question at a time. Briefing on the left, interview on the right.</p>
  </header>

  <div class="progress">
    <span class="part-chip" id="part-chip">Part 1</span>
    <div class="track"><div class="track-fill" id="track-fill"></div></div>
    <span class="counter" id="counter"></span>
  </div>

  <div class="stage split" id="stage">
    <section class="pane pane-briefing" aria-label="Briefing">
      <p class="pane-label">Briefing</p>
      <div id="briefing"></div>
    </section>
    <section class="pane pane-interview" aria-label="Interview">
      <p class="pane-label">Interview</p>
      <div id="interview"></div>
    </section>
  </div>

  <div class="nav">
    <button type="button" id="prev">← Previous</button>
    <span class="hint">← → keys</span>
    <button type="button" id="next" class="primary">Next →</button>
  </div>

  <footer>Dev-only workspace · decisions save to the separate SQLite docs store, never the system database.</footer>
</main>

<script>
  const PAGE = 'client-brief';
  const STATE_API = '/foundation/docs/state';
  const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
  const DECISIONS = [['A', 'Agree'], ['M', 'Modify'], ['K', 'Keep current'], ['D', 'Defer']];
  const SIGNOFF = [['A', 'Agree'], ['M', 'Modify'], ['D', 'Defer']];
  const YESREVISE = [['yes', 'Yes'], ['revise', 'Revise']];

  const DATA = [
    {
      label: 'Part 1 · Session 1 recap', taskKey: 'task.part1',
      steps: [
        { type: 'info', no: '1', title: 'Manual scoring', body: 'Recorded responses described scoring one admission cycle as taking <strong>more than five days</strong>. Problems included double-marked or unclear answers.', why: 'Results may be delayed; manual checking can produce errors.' },
        { type: 'info', no: '2', title: 'Staff effort', body: 'Responses mentioned about <strong>2–3 staff</strong> for one scoring process, with trained staff helping.', why: 'Automation should reduce repetitive checking but keep a human fallback.' },
        { type: 'info', no: '3', title: 'Consultation volume', body: 'One Guidance response described about <strong>70–80 applicants per day</strong> in a peak period, with short interactions of about <strong>2–5 minutes</strong>.', why: 'The volume is a heavy <strong>staff load</strong> (70–80 short sessions a day), and <strong>applicants wait in line</strong> for their turn; quick record access and fast advice recording ease the staff load and cut applicant waiting time.' },
        { type: 'info', no: '4', title: 'Applicant visits', body: 'About <strong>three campus visits</strong> were described, mainly for requirements, taking the exam, and claiming results.', why: 'Online information and status tracking may reduce unnecessary travel.' },
        { type: 'info', no: '5', title: 'Travel', body: 'Municipalities mentioned included Sta. Cruz, Bangar, Sudipen, Tagudin, Sta. Lucia, and Alilem. Some applicants may travel more than two hours.', why: 'Clear schedules, status information, and online updates may help applicants plan.' },
        { type: 'info', no: '6', title: 'Common questions', body: '“When is the exam?”, “Is the application still open?”, requirements, application status, and contact information.', why: 'These are candidates for an approved FAQ/chatbot and visible status page.' },
        { type: 'info', no: '7', title: 'Proctoring', body: 'Guidance staff are trained to administer the ICAT. Other institutional staff may be trained under strict procedures during a surge.', why: 'Additional proctors need authorization, training, and confidentiality rules.' },
        { type: 'info', no: '8', title: 'Scheduling', body: 'Schedules are coordinated with Guidance and other relevant personnel through an official messaging application. Miscommunication sometimes occurs.', why: 'A shared, trackable schedule can reduce lost or misunderstood messages.' },
        { type: 'info', no: '9', title: 'Clustering and chatbot', body: 'Previous clustering output was not integrated into the workflow. A live dashboard was considered possibly helpful. A chatbot was considered somewhat useful.', why: 'Model output must support—not replace—the counselor’s judgement. Routine questions may be answered faster.' },
        { type: 'info', no: '10', title: 'Quota information', body: 'Guidance obtains quota information through the coordinator and relevant personnel. Program Head feedback described face-to-face coordination and daily updates.', why: 'A shared quota view and alerts may reduce repeated follow-ups.' },
        { type: 'info', no: '11', title: 'Program Head needs', body: 'Real-time quota capacity, pre-enrollment applicant lists, and threaded inter-office notes were rated highly useful in the recorded response.', why: 'Program Heads can see their own intake situation and communicate a needed action.' },
        { type: 'choice', key: 'part1.confirmation', title: 'Session 1 confirmation', prompt: 'Is the recap accurate enough, accurate after corrections, or does it need a new description?', options: [['accurate', 'Accurate enough'], ['after-corrections', 'Accurate after corrections'], ['new-description', 'New description needed']] },
        { type: 'text', key: 'part1.corrections', title: 'Corrections / missing facts', prompt: 'Record any corrections or missing facts from the recap.' },
      ],
    },
    {
      label: 'Part 2 · Digitized processes', taskKey: 'task.part2',
      steps: [
        { type: 'decision', key: 'd1', no: 'D1', from: 'Application is written on paper and encoded later.', to: '<strong>Digital application form</strong> saves the information once and gives an identifier key. Paper may remain as backup.', user: 'Applicant / Registrar' },
        { type: 'decision', key: 'd2', no: 'D2', from: 'Identity is connected using a name, NOA number, or paper record.', to: '<strong>One application ID key</strong> is used on the application, slip, answer sheet, result, and portal.', user: 'Applicant / Registrar / Guidance' },
        { type: 'decision', key: 'd3', no: 'D3', from: 'Requirements are checked through folders or separate lists.', to: '<strong>Digital requirements checklist</strong> shows only <strong>missing</strong> and <strong>submitted/verified</strong> documents (no separate submitted or verified states).', user: 'Registrar' },
        { type: 'decision', key: 'd4', no: 'D4', from: 'Exam slots are set by tradition through a single authority; no adjustments are allowed. Coordination runs through messages, calls, or conversations.', to: '<strong>Shared exam schedule</strong> shows date, time, room, batch size, and proctor.', user: 'Guidance' },
        { type: 'decision', key: 'd5', no: 'D5', from: 'Students wait for the office to assign a schedule.', to: '<strong>Optional student booking</strong> may be turned on or off by authorized staff.', user: 'Guidance / Applicant' },
        { type: 'decision', key: 'd6', no: 'D6', from: 'Attendance is written on paper or checked manually.', to: '<strong>Digital attendance</strong> uses the ID/QR on the admission slip. Paper backup remains available.', user: 'Proctor / Guidance' },
        { type: 'decision', key: 'd7', no: 'D7', from: 'Answer sheets are checked using a stencil or by hand.', to: '<strong>OMR-powered checking</strong> reads the sheet and checks marked answers.', user: 'Guidance' },
        { type: 'decision', key: 'd8', no: 'D8', from: 'Unclear, double-marked, or erased answers are judged during checking.', to: '<strong>Manual fallback</strong> sends low-confidence answers to a staff review list before confirmation.', user: 'Guidance' },
        { type: 'decision', key: 'd9', no: 'D9', from: 'Raw scores are converted using a separate table or calculation.', to: '<strong>Stored conversion table</strong> converts the raw score into the official result after confirmation.', user: 'Guidance' },
        { type: 'decision', key: 'd10', no: 'D10', from: 'Results are released only together with the consultation, face-to-face; there is no online result.', to: '<strong>Online result + print on request</strong>; a human confirms before publication.', user: 'Guidance / Applicant' },
        { type: 'decision', key: 'd11', no: 'D11', from: 'Consultation is arranged as applicants arrive or through office coordination.', to: '<strong>Consultation schedule</strong> displays open slots, bookings, daily capacity, and mode.', user: 'Guidance' },
        { type: 'decision', key: 'd12', no: 'D12', from: 'Consultation is face-to-face only or arranged informally.', to: '<strong>Two modes: face-to-face and online/video</strong>, according to policy and situation.', user: 'Guidance / Applicant' },
        { type: 'decision', key: 'd13', no: 'D13', from: 'Advice is only said during the session; it is not noted on paper or in any other record.', to: '<strong>Short typed consultation note</strong> records advice, date, counselor, and next step.', user: 'Guidance' },
        { type: 'decision', key: 'd14', no: 'D14', from: 'After consultation, a follow-up may be invisible to the next office.', to: '<strong>Trackable follow-up</strong> records action, responsible person, date, remarks, and status.', user: 'Guidance / Registrar / Program Head' },
        { type: 'decision', key: 'd15', no: 'D15', from: 'Quota information is requested from a coordinator or Program Head.', to: '<strong>Program quota view</strong> shows quota, current status, and last update time.', user: 'Guidance / Program Head / Registrar' },
        { type: 'decision', key: 'd16', no: 'D16', from: 'Staff discover late that a program is near or over quota.', to: '<strong>Quota threshold alert</strong> notifies authorized staff.', user: 'Guidance / Registrar / Program Head' },
        { type: 'decision', key: 'd17', no: 'D17', from: 'Routine questions arrive through messages, calls, social media, or visits.', to: '<strong>Approved FAQ/chatbot</strong> answers routine questions and has a staff fallback.', user: 'Applicant / Guidance' },
        { type: 'decision', key: 'd18', no: 'D18', from: 'Guidance and Registrar coordinate in external messaging channels.', to: '<strong>Trackable in-app request/task</strong> keeps the request, response, and status connected.', user: 'Guidance / Registrar' },
        { type: 'decision', key: 'd19', no: 'D19', from: 'Reports are prepared manually from separate lists.', to: '<strong>Role-based reports</strong> show applicants, batches, scores, results, quota, and follow-ups.', user: 'All offices' },
        { type: 'decision', key: 'd20', no: 'D20', from: 'It is difficult to prove who viewed or changed a record.', to: '<strong>Activity history</strong> records important views, edits, approvals, releases, alerts, and changes.', user: 'Authorized staff / IT' },
      ],
    },
    {
      label: 'Part 2 · Breaking changes', taskKey: 'task.part2',
      steps: [
        { type: 'decision', key: 'b1', no: 'B1', from: 'After consultation, the next step may be invisible.', to: '<strong>Trackable handoff:</strong> advice, follow-up, person, due date, status.', reason: 'Prevents forgotten applicants and clarifies responsibility.' },
        { type: 'decision', key: 'b2', no: 'B2', from: 'Results are released only together with the consultation, face-to-face, as one crowded activity.', to: 'Separate result release from consultation scheduling.', reason: 'Reduces lines and manages daily capacity.' },
        { type: 'decision', key: 'b3', no: 'B3', from: 'Release and consultation may be face-to-face only.', to: 'Support online and face-to-face modes under office policy.', reason: 'Helps applicants who live far away while keeping F2F available.' },
        { type: 'decision', key: 'b4', no: 'B4', from: 'Admin controls every exam schedule.', to: 'Office may allow or disable student self-booking for exam and consultation separately.', reason: 'Gives the office flexibility.' },
        { type: 'decision', key: 'b5', no: 'B5', from: 'Schedule is coordinated through separate messages.', to: 'One shared schedule shows room, batch, capacity, proctors, and conflicts.', reason: 'Reduces double booking and unclear updates.' },
        { type: 'decision', key: 'b6', no: 'B6', from: 'Only Guidance normally proctors.', to: 'Trained institutional staff may proctor during a surge if authorized.', reason: 'Adds capacity without removing Guidance control.' },
        { type: 'decision', key: 'b7', no: 'B7', from: 'Manual stencil scoring is the main check.', to: 'OMR is the first check; manual review is the safety net.', reason: 'Speeds checking while preserving human review.' },
        { type: 'decision', key: 'b8', no: 'B8', from: 'Score moves between paper, spreadsheet, and message.', to: 'One confirmed result record feeds conversion, release, print, and portal.', reason: 'Reduces re-encoding and inconsistent results.' },
        { type: 'decision', key: 'b9', no: 'B9', from: 'Quota is obtained by asking the coordinator/Program Head.', to: 'Shared quota status with update time and threshold alerts.', reason: 'Lets staff act earlier.' },
        { type: 'decision', key: 'b10', no: 'B10', from: 'Routine questions require staff time.', to: 'Approved FAQ/chatbot with human fallback.', reason: 'Reduces repeated questions without inventing policy.' },
        { type: 'decision', key: 'b11', no: 'B11', from: 'Clustering/recommendation output is separate from consultation.', to: 'Counselor dashboard shows suggestions beside applicant record.', reason: 'Makes information easier to review; counselor still decides.' },
        { type: 'decision', key: 'b12', no: 'B12', from: 'No single activity history across offices.', to: 'Important actions record staff, date, time, and action.', reason: 'Supports accountability and dispute resolution.' },
        { type: 'decision', key: 'b13', no: 'B13', from: 'Exam slots are set by a single authority; no adjustments are allowed.', to: '<strong>Shared, trackable exam schedule</strong> with office-controlled adjustments.', reason: 'Removes the single-authority bottleneck and makes every change visible to all parties.' },
        { type: 'decision', key: 'b14', no: 'B14', from: 'Consultations are arranged as applicants arrive or through informal office coordination.', to: '<strong>Scheduled consultation slots</strong> with bookings, daily capacity, and mode.', reason: 'Manages the 70–80-per-day staff load and shortens applicant waiting in line.' },
      ],
    },
    {
      label: 'Part 2 · Summary sign-off', taskKey: 'task.part2',
      steps: [
        { type: 'signoff', key: 'signoff.digital-application', title: 'Digital application' },
        { type: 'signoff', key: 'signoff.omr-fallback', title: 'OMR + manual fallback' },
        { type: 'signoff', key: 'signoff.human-confirmation', title: 'Human confirmation before release' },
        { type: 'signoff', key: 'signoff.online-result', title: 'Online result + print on request' },
        { type: 'signoff', key: 'signoff.consultation-schedule', title: 'Separate consultation schedule' },
        { type: 'signoff', key: 'signoff.f2f-consultation', title: 'F2F consultation remains' },
        { type: 'signoff', key: 'signoff.online-consultation', title: 'Online consultation option' },
        { type: 'signoff', key: 'signoff.self-booking', title: 'Exam/consultation self-booking option' },
        { type: 'signoff', key: 'signoff.trackable-followup', title: 'Trackable follow-up and handoff' },
        { type: 'signoff', key: 'signoff.shared-quota', title: 'Shared quota view and alerts' },
        { type: 'signoff', key: 'signoff.chatbot', title: 'FAQ/chatbot with fallback' },
        { type: 'signoff', key: 'signoff.counselor-final', title: 'Counselor remains final decision-maker' },
        { type: 'text', key: 'part2.must-not-change', title: 'Must not change', prompt: 'Processes that must not change without further approval.' },
      ],
    },
    {
      label: 'Part 2 · Follow-up questions', taskKey: 'task.part2',
      steps: [
        { type: 'text', key: 'followup.retake', title: 'Retake policy', prompt: 'Is retake possible? Within the same admission cycle, or in another academic year, when the student already has a saved result on record?' },
      ],
    },
    {
      label: 'Part 3 · User Design walkthrough', taskKey: 'task.part3',
      steps: [
        { type: 'info', no: 'Guidance', title: 'Walkthrough: Guidance', body: 'Screens: triage, OMR, scheduling, release, consultation, proctoring.', why: 'Ask the client: which screen is opened first each day? What must appear without another click?' },
        { type: 'info', no: 'Registrar', title: 'Walkthrough: Registrar', body: 'Screens: applications, import, processing, reports, setup.', why: 'Ask the client: which column or status is checked most often? What is missing?' },
        { type: 'info', no: 'Program Head', title: 'Walkthrough: Program Head', body: 'Screens: quota, slate, reports.', why: 'Ask the client: what quota signal must be visible at a glance?' },
        { type: 'info', no: 'Applicant', title: 'Walkthrough: Applicant', body: 'Screens: entry, status, form, slip, chatbot.', why: 'Ask the client: what does the applicant ask first? Is the next step obvious?' },
        { type: 'info', no: 'System Admin', title: 'Walkthrough: System Admin', body: 'Screens: users, audit, engines, settings.', why: 'Ask the client: which setting changes most often?' },
        { type: 'text', key: 'part3.q1', title: 'Design question 1', prompt: 'What is the first action needed when an applicant arrives or calls?' },
        { type: 'text', key: 'part3.q2', title: 'Design question 2', prompt: 'What must be visible without opening another page?' },
        { type: 'text', key: 'part3.q3', title: 'Design question 3', prompt: 'What must be hidden from other offices?' },
        { type: 'text', key: 'part3.q4', title: 'Design question 4', prompt: 'What is the most dangerous mistake the system could make?' },
        { type: 'text', key: 'part3.q5', title: 'Design question 5', prompt: 'What must still be possible during an internet outage?' },
        { type: 'text', key: 'part3.q6', title: 'Design question 6', prompt: 'What screen should be tested first in the next review?' },
        { type: 'decision', key: 'validate.guidance', title: 'Validation: Guidance', from: 'Screens reviewed: triage, OMR, scheduling, release, consultation, proctoring.', to: 'Direction accepted?', options: YESREVISE, remarksLabel: 'Changes requested' },
        { type: 'decision', key: 'validate.registrar', title: 'Validation: Registrar', from: 'Screens reviewed: applications, import, processing, reports, setup.', to: 'Direction accepted?', options: YESREVISE, remarksLabel: 'Changes requested' },
        { type: 'decision', key: 'validate.program-head', title: 'Validation: Program Head', from: 'Screens reviewed: quota, slate, reports.', to: 'Direction accepted?', options: YESREVISE, remarksLabel: 'Changes requested' },
        { type: 'decision', key: 'validate.applicant', title: 'Validation: Applicant', from: 'Screens reviewed: entry, status, form, slip, chatbot.', to: 'Direction accepted?', options: YESREVISE, remarksLabel: 'Changes requested' },
        { type: 'decision', key: 'validate.system-admin', title: 'Validation: System Admin', from: 'Screens reviewed: users, audit, engines, settings.', to: 'Direction accepted?', options: YESREVISE, remarksLabel: 'Changes requested' },
        { type: 'text', key: 'part3.top-change', title: 'Most important design change', prompt: 'Record the most important design change requested.' },
      ],
    },
    {
      label: 'Part 4 · Validation and evidence', taskKey: 'task.part4',
      steps: [
        { type: 'info', no: 'Record', title: 'Record after the visit', body: 'Exact corrections to the Session 1 recap; each From → To decision; new facts or constraints; screens accepted for the next design review; rejected screens and the reason; policy or confidentiality restrictions; permission status for photos and documents.', why: 'Keep proposed requirements, confirmed requirements, and unresolved questions separate. The dated notes and signed/initialed matrix are the RAD evidence.' },
        { type: 'text', key: 'part4.new-facts', title: 'New facts or constraints', prompt: 'Record any new facts or constraints from the visit.' },
      ],
    },
  ];

  const steps = [];
  DATA.forEach((section, s) => {
    section.steps.forEach((step, i) => steps.push({ section, step, s, i }));
    steps.push({
      section, s, i: section.steps.length,
      step: { type: 'partdone', taskKey: section.taskKey, title: section.label + ' complete?' },
    });
  });

  let index = 0;
  let state = {};
  let pending = new Map();
  let saveTimer = null;
  let mode = localStorage.getItem('brief.mode') || 'split';

  const statusEl = document.getElementById('save-status');
  const stage = document.getElementById('stage');
  const briefingEl = document.getElementById('briefing');
  const interviewEl = document.getElementById('interview');
  const trackFill = document.getElementById('track-fill');
  const counter = document.getElementById('counter');
  const partChip = document.getElementById('part-chip');
  const prevBtn = document.getElementById('prev');
  const nextBtn = document.getElementById('next');

  function setStatus(text, kind) {
    statusEl.textContent = text;
    statusEl.className = 'status' + (kind ? ' ' + kind : '');
  }

  function queueSave(key, value) {
    if (value === null || value === '') { pending.delete(key); state[key] = ''; }
    else { pending.set(key, value); state[key] = value; }
    if (saveTimer) clearTimeout(saveTimer);
    saveTimer = setTimeout(flush, 350);
  }

  async function flush() {
    if (saveTimer) { clearTimeout(saveTimer); saveTimer = null; }
    if (pending.size === 0) return;
    const entries = [...pending].map(([key, value]) => ({ key, value }));
    setStatus('Saving ' + entries.length + ' item(s)…');
    try {
      const res = await fetch(STATE_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ page: PAGE, entries }),
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      pending.clear();
      setStatus('Saved ' + new Date().toLocaleTimeString(), 'ok');
    } catch (e) {
      console.error('docs save failed', e);
      setStatus('Save failed — retrying…', 'err');
      saveTimer = setTimeout(flush, 3000);
    }
  }

  function pills(options, key, current) {
    return '<div class="pill-row">' + options.map(([value, label]) =>
      '<label class="pill' + (current === value ? ' checked' : '') + '"><input type="radio" name="' + key + '" value="' + value + '"' + (current === value ? ' checked' : '') + '> ' + label + '</label>'
    ).join('') + '</div>';
  }

  function render() {
    const { section, step } = steps[index];
    partChip.textContent = section.label;
    counter.textContent = (index + 1) + ' / ' + steps.length;
    trackFill.style.width = Math.round(((index + 1) / steps.length) * 100) + '%';
    prevBtn.disabled = index === 0;
    nextBtn.disabled = index === steps.length - 1;

    let b = '', i = '';
    if (step.type === 'info') {
      b = '<p class="step-no">' + step.no + '</p><h3>' + step.title + '</h3><div class="body">' + step.body + '</div>' +
        (step.why ? '<p class="flow"><span class="why"><b>Why it matters:</b> ' + step.why + '</span></p>' : '');
      i = (step.why && step.why.indexOf('Ask the client') === 0)
        ? '<p class="prompt">' + step.why + '</p>'
        : '<p class="muted-note">Read the finding on the left. No question here; corrections are captured at the end of this part.</p>';
    } else if (step.type === 'choice') {
      b = '<p class="step-no">Part 1 · confirmation</p><h3>' + step.title + '</h3><div class="body">' + step.prompt + '</div>';
      i = '<p class="prompt">' + step.prompt + '</p>' + pills(step.options, step.key, state[step.key] || '');
    } else if (step.type === 'decision') {
      const opts = step.options || DECISIONS;
      b = '<p class="step-no">' + (step.no || '') + (step.user ? ' · ' + step.user : '') + '</p><h3>' + (step.title || step.no || 'Decision') + '</h3><div class="flow">' +
        '<div class="from"><b>Current:</b> ' + step.from + '</div>' +
        '<div class="to"><b>Proposed:</b> ' + step.to + '</div>' +
        (step.reason ? '<span class="why"><b>Why:</b> ' + step.reason + '</span>' : '') + '</div>';
      i = pills(opts, step.key + '.decision', state[step.key + '.decision'] || '') +
        '<input class="textfield" data-state-key="' + step.key + '.remarks" placeholder="' + (step.remarksLabel || 'Client remarks (optional)') + '" value="' + (state[step.key + '.remarks'] || '').replace(/"/g, '&quot;') + '">';
    } else if (step.type === 'signoff') {
      b = '<p class="step-no">Summary sign-off</p><h3>' + step.title + '</h3><div class="body">What final direction does the client give for this decision area?</div>';
      i = '<p class="prompt">Final direction</p>' + pills(SIGNOFF, step.key + '.direction', state[step.key + '.direction'] || '') +
        '<input class="textfield" data-state-key="' + step.key + '.remarks" placeholder="Client remarks / exact wording" value="' + (state[step.key + '.remarks'] || '').replace(/"/g, '&quot;') + '">';
    } else if (step.type === 'text') {
      b = '<p class="step-no">' + section.label + '</p><h3>' + step.title + '</h3><div class="body">' + step.prompt + '</div>';
      i = '<p class="prompt">' + step.prompt + '</p><textarea class="textfield" rows="4" data-state-key="' + step.key + '" placeholder="Answer / notes…">' + (state[step.key] || '') + '</textarea>';
    } else if (step.type === 'partdone') {
      const done = state[step.taskKey] === '1';
      b = '<h3>' + step.title + '</h3><div class="body">When every question in this part is answered, mark the part done.</div>';
      i = '<div class="partdone"><button type="button" id="part-done-btn" class="' + (done ? 'done' : '') + '">' + (done ? 'Part marked done ✓' : 'Mark this part done') + '</button></div>';
    }
    briefingEl.innerHTML = b;
    interviewEl.innerHTML = i;
  }

  function go(delta) {
    const next = index + delta;
    if (next < 0 || next >= steps.length) return;
    const t = document.activeElement;
    if (t && (t.tagName === 'TEXTAREA' || t.tagName === 'INPUT' || t.tagName === 'SELECT')) return;
    index = next;
    render();
  }

  document.addEventListener('change', (e) => {
    const t = e.target;
    if (t.type === 'radio' && t.checked) {
      const row = t.closest('.pill-row');
      const key = t.name;
      row.querySelectorAll('.pill').forEach((p) => p.classList.remove('checked'));
      t.closest('.pill').classList.add('checked');
      queueSave(key, t.value);
    }
  });

  document.addEventListener('input', (e) => {
    const t = e.target;
    if (t.dataset.stateKey) queueSave(t.dataset.stateKey, t.value);
  });

  document.addEventListener('click', (e) => {
    if (e.target.id === 'part-done-btn') {
      const { step } = steps[index];
      queueSave(step.taskKey, '1');
      e.target.classList.add('done');
      e.target.textContent = 'Part marked done ✓';
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') go(-1);
    if (e.key === 'ArrowRight') go(1);
  });

  prevBtn.addEventListener('click', () => { index = Math.max(0, index - 1); render(); });
  nextBtn.addEventListener('click', () => { index = Math.min(steps.length - 1, index + 1); render(); });

  document.querySelectorAll('.modes button').forEach((btn) => {
    btn.addEventListener('click', () => {
      mode = mode === btn.dataset.mode ? 'split' : btn.dataset.mode;
      localStorage.setItem('brief.mode', mode);
      stage.className = 'stage ' + mode;
      document.querySelectorAll('.modes button').forEach((x) => x.classList.toggle('active', x.dataset.mode === mode));
    });
  });
  stage.className = 'stage ' + mode;
  document.querySelectorAll('.modes button').forEach((x) => x.classList.toggle('active', x.dataset.mode === mode));

  async function load() {
    try {
      const res = await fetch(STATE_API + '?page=' + PAGE, { headers: { 'Accept': 'application/json' } });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      state = data.state || {};
      setStatus('Saved state loaded', 'ok');
      render();
    } catch (e) {
      console.error('docs load failed', e);
      setStatus('Could not load saved state', 'err');
      render();
    }
  }

  load();
</script>
</body>
</html>
