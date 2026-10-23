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
    body { margin: 0; background: var(--bg); color: var(--text); font: 18px/1.55 system-ui, -apple-system, sans-serif; }
    main { width: 100%; margin: 0; padding: 1rem 1rem 8rem; min-height: 100vh; }
    header { border-bottom: 1px solid var(--line); padding-bottom: .9rem; }
    .eyebrow { color: var(--accent); font-size: .68rem; font-weight: 800; letter-spacing: .2em; text-transform: uppercase; }
    h1 { margin: .3rem 0 .5rem; font-size: clamp(1.5rem, 3vw, 2.1rem); line-height: 1.1; }
    .sub { color: var(--muted); font-size: .88rem; margin: 0; }
    a { color: var(--accent); }
    .actions { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .7rem; }
    .actions a { border: 1px solid var(--line); border-radius: .6rem; padding: .3rem .6rem; text-decoration: none; font-size: .78rem; color: var(--muted); }
    .actions a:hover { color: var(--text); border-color: var(--accent); }
    .actions a.primary { background: var(--accent); color: #081126; font-weight: 700; }
    .session-dock { position: fixed; right: 1rem; bottom: .8rem; left: 1rem; z-index: 30; }
    .dock-toggle { display: flex; align-items: center; gap: .75rem; min-height: 2.8rem; border: 1px solid var(--line); border-radius: .8rem; background: color-mix(in srgb, var(--panel) 94%, transparent); backdrop-filter: blur(8px); padding: .45rem .8rem; cursor: pointer; list-style: none; }
    .dock-toggle::-webkit-details-marker { display: none; }
    .dock-toggle::after { content: ''; width: .45rem; height: .45rem; margin-left: auto; border-top: 1px solid var(--muted); border-right: 1px solid var(--muted); transform: rotate(-45deg); transition: transform .15s ease; }
    .session-dock:not([open]) .dock-toggle::after { transform: rotate(135deg); }
    .dock-title { color: var(--text); font-size: .82rem; font-weight: 700; white-space: nowrap; }
    .dock-step { color: var(--muted); font-size: .76rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .dock-toggle .status { margin-left: auto; color: var(--muted); font-size: .76rem; white-space: nowrap; }
    .dock-toggle .status.ok { color: var(--ok); } .dock-toggle .status.err { color: var(--danger); }
    .dock-panel { max-height: min(72vh, 42rem); overflow: auto; margin-bottom: .4rem; border: 1px solid var(--line); border-radius: .9rem; background: color-mix(in srgb, var(--panel) 97%, transparent); box-shadow: 0 1rem 3rem rgb(0 0 0 / .3); padding: .85rem 1rem 1rem; }
    .session-dock[open] .dock-panel { margin-bottom: 7rem; }
    @media (max-width: 700px) { .session-dock[open] .dock-panel { margin-bottom: 8.2rem; } }
    .dock-panel header { border-bottom: 1px solid var(--line); padding-bottom: .7rem; }
    .dock-panel h1 { font-size: clamp(1.35rem, 3vw, 2.1rem); }
    .dock-panel .sub { font-size: .82rem; }
    .dock-panel .actions { margin-top: .55rem; }
    .dock-controls { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .6rem; margin-top: .75rem; }
    .dock-controls .modes { margin-left: auto; }
    .modes { display: inline-flex; gap: .2rem; border: 1px solid var(--line); border-radius: .6rem; padding: .2rem; }
    .modes button { border: 0; border-radius: .45rem; background: transparent; color: var(--muted); padding: .3rem .6rem; font: inherit; font-size: .8rem; cursor: pointer; }
    .modes button.active { background: var(--accent); color: #081126; font-weight: 700; }
    .dock-panel .progress { margin: .75rem 0 .5rem; }
    .dock-panel .nav { margin-top: .7rem; }
    .sections-strip { display: flex; flex-wrap: wrap; gap: .4rem; margin: 0 0 .7rem; padding: .4rem; border: 1px solid var(--line); border-radius: .7rem; background: color-mix(in srgb, var(--panel) 80%, transparent); }
    .sec-toggle { display: inline-flex; align-items: center; gap: .35rem; border: 1px solid var(--line); border-radius: .55rem; background: #0a1122; color: var(--muted); padding: .3rem .55rem; font: inherit; font-size: .74rem; cursor: pointer; transition: all .12s; }
    .sec-toggle svg { flex: 0 0 auto; }
    .sec-toggle.on { border-color: var(--accent); color: var(--text); background: color-mix(in srgb, var(--accent) 14%, transparent); }
    .sec-toggle.off { opacity: .55; }
    .sec-hint { margin: .2rem .2rem .6rem; color: var(--muted); font-size: .78rem; }
    .briefing-element-toggles { display: inline-flex; align-items: center; gap: .35rem; flex-wrap: wrap; margin-bottom: .65rem; padding: .3rem .55rem; background: #0c1222; border: 1px solid var(--line); border-radius: .6rem; }
    .element-toggle-label { font-size: .7rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .08em; margin-right: .2rem; }
    .elem-toggle { border: 1px solid var(--line); border-radius: .45rem; background: transparent; color: var(--muted); padding: .2rem .5rem; font-size: .74rem; font-weight: 600; cursor: pointer; transition: all .12s; display: inline-flex; align-items: center; gap: .25rem; }
    .elem-toggle svg { flex: 0 0 auto; }
    .elem-toggle.on { border-color: var(--ok); color: var(--text); background: color-mix(in srgb, var(--ok) 12%, transparent); }
    .elem-toggle.off { border-color: var(--line); color: var(--muted); opacity: .55; }
    .element-hidden { display: none !important; }
    .interview-flow { transition: opacity .15s ease; }
    .interview-flow.dimmed { opacity: .35; }
    .interview-flow.dimmed .sequence-card { border-style: dashed; border-color: color-mix(in srgb, var(--muted) 45%, var(--line)); }
    .diagram-hidden { display: none !important; }
    .briefing-flow-card .sequence-toolbar { display: none; }
    .no-sections { border: 1px dashed var(--line); border-radius: 1rem; padding: 2rem 1.5rem; text-align: center; color: var(--muted); }
    .no-sections p { font-size: 1.05rem; margin: .35rem 0 0; }
    .always-nav { position: fixed; right: 1rem; bottom: 4rem; left: 1rem; z-index: 40; display: grid; grid-template-columns: auto minmax(0, 1fr) auto; align-items: center; gap: .75rem; margin: 0 auto; max-width: 1500px; border: 1px solid var(--line); border-radius: .85rem; background: color-mix(in srgb, var(--panel) 96%, transparent); backdrop-filter: blur(8px); padding: .4rem; box-shadow: 0 1rem 3rem rgb(0 0 0 / .25); }
    .always-nav button { min-height: 2.7rem; border: 1px solid var(--line); border-radius: .6rem; background: transparent; color: var(--text); padding: .45rem .9rem; font: inherit; font-size: .82rem; font-weight: 700; cursor: pointer; }
    .always-nav button:disabled { opacity: .35; cursor: not-allowed; }
    .always-nav button.primary { border-color: var(--accent); background: var(--accent); color: #081126; }
    .nav-summary { min-width: 0; display: grid; grid-template-columns: minmax(8rem, auto) minmax(8rem, 1fr) auto; align-items: center; gap: .7rem; }
    .nav-summary .progress { min-width: 0; margin: 0; }
    .nav-summary .part-chip { overflow: hidden; text-overflow: ellipsis; }
    .nav-summary .counter { text-align: right; }
    @media (max-width: 700px) { .always-nav { right: .5rem; bottom: 4rem; left: .5rem; gap: .35rem; } .always-nav button { padding: .45rem .6rem; font-size: .75rem; } .nav-summary { grid-template-columns: 1fr; gap: .15rem; } .nav-summary .counter { text-align: center; } }
    .progress { display: flex; align-items: center; gap: .8rem; }
    .track { flex: 1; height: .4rem; border-radius: 999px; background: #0d1426; border: 1px solid var(--line); overflow: hidden; }
    .track-fill { height: 100%; width: 100%; transform: scaleX(0); transform-origin: left center; border-radius: 999px; background: linear-gradient(90deg, var(--accent), var(--ok)); transition: transform .25s ease; }
    .counter { color: var(--muted); font-size: .78rem; white-space: nowrap; }
    .part-chip { color: var(--accent); font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; white-space: nowrap; }
    .stage { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: .9rem; width: min(1500px, 100%); min-width: 0; min-height: calc(100vh - 2rem); margin: 0 auto; }
    .stage.interview .pane-briefing { display: none; }
    .stage.briefing .pane-interview { display: none; }
    .stage.briefing, .stage.interview { grid-template-columns: minmax(0, 1fr); }
    @media (max-width: 900px) { .stage { grid-template-columns: minmax(0, 1fr); } }
    .pane { min-width: 0; border: 1px solid var(--line); border-radius: 1rem; background: var(--panel); padding: 1.1rem 1.2rem; min-height: calc(100vh - 2rem); max-height: calc(100vh - 2rem); overflow: auto; display: flex; flex-direction: column; }
    .pane-briefing { border-color: color-mix(in srgb, var(--accent) 30%, var(--line)); }
    .pane-interview { border-color: color-mix(in srgb, var(--ok) 30%, var(--line)); }
    .pane-label { display: flex; align-items: center; gap: .4rem; font-size: .66rem; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; margin: 0 0 .7rem; }
    .pane-label::before { content: ""; width: .45rem; height: .45rem; border-radius: 50%; }
    .pane-briefing .pane-label { color: var(--accent); } .pane-briefing .pane-label::before { background: var(--accent); }
    .pane-interview .pane-label { color: var(--ok); } .pane-interview .pane-label::before { background: var(--ok); }
    .step-no { color: var(--muted); font-size: 1rem; margin: 0 0 .25rem; }
    h3 { margin: 0 0 .5rem; font-size: 1.45rem; }
    .flow { display: flex; flex-direction: column; gap: .6rem; margin: .3rem 0; }
    .flow .from { border: 1px solid color-mix(in srgb, var(--danger) 42%, var(--line)); padding: .6rem .85rem; font-size: 1.1rem; background: color-mix(in srgb, var(--danger) 8%, transparent); border-radius: .5rem; }
    .flow .to { border: 1px solid color-mix(in srgb, var(--ok) 42%, var(--line)); padding: .6rem .85rem; font-size: 1.1rem; background: color-mix(in srgb, var(--ok) 8%, transparent); border-radius: .5rem; }
    .flow .why { color: var(--muted); font-size: 1.05rem; }
    .flow .why b { color: var(--text); }
    .arrow { color: var(--muted); text-align: center; font-size: .8rem; }
    .body { color: var(--muted); flex: 1; font-size: 1.12rem; }
    .body strong { color: var(--text); }
    .prompt { color: var(--text); font-size: 1.35rem; }
    .pill-row { display: flex; flex-wrap: wrap; gap: .45rem; margin-top: 1rem; }
    .pill { display: inline-flex; align-items: center; gap: .35rem; border: 1px solid var(--line); border-radius: 999px; padding: .5rem .9rem; font-size: 1.05rem; color: var(--muted); cursor: pointer; background: transparent; transition: all .12s; }
    .pill input { margin: 0; accent-color: var(--accent); }
    .pill:has(input:checked) { border-color: var(--accent); color: var(--text); background: color-mix(in srgb, var(--accent) 14%, transparent); }
    .muted-note { color: var(--muted); font-size: 1.15rem; margin: 0; }
    .cue-label { margin: 0 0 .45rem; color: var(--ok); font-size: .82rem; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; }
    .textfield { width: 100%; border: 1px solid var(--line); border-radius: .6rem; background: #0a1122; color: var(--text); padding: .6rem .75rem; font: inherit; margin-top: .7rem; }
    .textfield:focus { outline: 2px solid var(--accent); border-color: transparent; }
    .muted-note { color: var(--muted); font-size: 1.15rem; margin: 0; }
    .briefing-reference { margin-top: 1rem; border-top: 1px solid var(--line); padding-top: .8rem; }
    .reference-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .6rem; }
    .reference-card { border: 1px solid var(--line); border-radius: .65rem; background: #0c1222; padding: .65rem .75rem; }
    .reference-label { margin: 0 0 .35rem; color: var(--accent); font-size: .78rem; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
    .ref-icon { display: inline-flex; align-items: center; gap: .35rem; margin: 0 0 .45rem; color: var(--accent); }
    .ref-icon svg { flex: 0 0 auto; }
    .ref-icon .ref-word { font-size: .72rem; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
    .ref-icon.finding { color: #9ec5ff; }
    .ref-icon.feature { color: #a78bfa; }
    .ref-icon.flow { color: #72ddb0; }
    .section-chip { margin-top: .45rem; display: flex; align-items: flex-start; gap: .35rem; color: #7f8daf; font-size: .8rem; line-height: 1.4; }
    .section-chip b { color: var(--muted); font-weight: 700; }
    .interview-flow { margin-top: 1rem; }
    .walkthrough-note { margin-top: .6rem; border: 1px solid color-mix(in srgb, var(--ok) 25%, var(--line)); border-radius: .65rem; background: color-mix(in srgb, var(--ok) 7%, var(--panel)); padding: .6rem .75rem; }
    .walkthrough-note p { margin: 0; color: var(--text); font-size: 1.05rem; line-height: 1.5; }
    .reference-card p { margin: 0; color: var(--muted); font-size: 1rem; line-height: 1.5; }
    .reference-card strong { color: var(--text); }
    .reference-meta { margin-top: .5rem !important; color: #7f8daf !important; font-size: .84rem !important; }
    .sequence-card { margin-top: .6rem; border: 1px solid var(--line); border-radius: .65rem; background: #0c1222; padding: .65rem .75rem; }
    .sequence-toolbar { display: flex; align-items: center; justify-content: space-between; gap: .6rem; margin-bottom: .45rem; }
    .sequence-toolbar .reference-label { margin: 0; }
    .sequence-tools { display: inline-flex; align-items: center; gap: .25rem; }
    .sequence-tools button { min-width: 2rem; min-height: 1.9rem; border: 1px solid var(--line); border-radius: .45rem; background: #0a1122; color: var(--muted); padding: .2rem .45rem; font: inherit; font-size: .75rem; cursor: pointer; }
    .sequence-tools button:hover, .sequence-tools button:focus-visible { border-color: var(--accent); color: var(--text); outline: none; }
    .sequence-tools output { min-width: 3.2rem; color: var(--muted); font-size: .7rem; text-align: center; }
    .sequence-slot { cursor: grab; user-select: none; }
    .sequence-slot.is-panning { cursor: grabbing; }
    .sequence-slot { min-height: 18rem; max-height: 36rem; overflow: auto; border: 1px solid #202d4b; overscroll-behavior: contain; border-radius: .5rem; background: #080d1a; padding: .55rem; }
    .sequence-slot svg { display: block; width: 100%; max-width: none; min-width: 48rem; height: auto; }
    .sequence-slot:fullscreen { width: 100vw; height: 100vh; max-height: none; border: 0; border-radius: 0; padding: 2rem; background: #080d1a; }
    .sequence-slot:fullscreen .sequence-canvas { min-width: max-content; min-height: 100%; display: flex; align-items: flex-start; justify-content: center; }
    .sequence-slot:fullscreen svg { width: auto; min-width: min(90vw, 80rem); max-width: none; }
    .sequence-fallback { margin: 0; color: #a8b3d0; font: .68rem/1.45 ui-monospace, SFMono-Regular, Menlo, monospace; white-space: pre-wrap; }
    .sequence-loading { color: var(--muted); font-size: .76rem; }
    .talk-track { margin-top: .6rem; border: 1px solid color-mix(in srgb, var(--accent) 25%, var(--line)); border-radius: .65rem; background: color-mix(in srgb, var(--accent) 6%, var(--panel)); padding: .55rem .7rem; }
    .talk-track summary { color: var(--accent); cursor: pointer; font-size: .76rem; font-weight: 700; }
    .taglish { margin: .55rem 0 0; color: var(--text); font-size: 1.35rem; line-height: 1.5; }
    @media (max-width: 900px) { .reference-grid { grid-template-columns: 1fr; } .sequence-slot svg { min-width: 40rem; } }
    .partdone { margin-top: 1.2rem; }
    .partdone button { border: 1px solid var(--line); border-radius: .7rem; background: transparent; color: var(--text); padding: .6rem 1.1rem; font: inherit; font-weight: 700; cursor: pointer; }
    .partdone button.done { border-color: color-mix(in srgb, var(--ok) 50%, var(--line)); color: var(--ok); }
    @media print { :root { color-scheme: light; } body { background: #fff; color: #17202a; } main { width: 100%; padding: 0; } .session-dock, .always-nav { display: none; } .stage { grid-template-columns: 1fr; min-height: 0; } .pane { border-color: #cbd3da; background: #fff; color: #17202a; min-height: 0; max-height: none; overflow: visible; page-break-inside: avoid; } }
  </style>
</head>
<body>
<main>
    <div class="stage split" id="stage">
    <section class="pane pane-briefing" aria-label="Briefing">
      <p class="pane-label">Briefing</p>
      <div id="briefing"></div>
    </section>
    <section class="pane pane-interview" aria-label="Interview">
      <p class="pane-label">Interview · interviewer controls</p>
      <div class="sections-strip" role="group" aria-label="Presentation sections">
        <span class="sec-hint" id="sec-hint"></span>
        <div class="sec-eyes" id="sec-eyes" style="display:contents"></div>
      </div>
      <div id="interview"></div>
    </section>
  </div>
  <div class="always-nav" aria-label="Brief navigation">
    <button type="button" id="prev">← Previous</button>
    <div class="nav-summary">
      <span class="part-chip" id="part-chip">Part 1</span>
      <div class="progress">
        <div class="track"><div class="track-fill" id="track-fill"></div></div>
      </div>
      <span class="counter" id="counter"></span>
    </div>
    <button type="button" id="next" class="primary">Next →</button>
  </div>

  <details class="session-dock" id="session-dock">
    <summary class="dock-toggle">
      <span class="dock-title">Client brief</span>
      <span class="dock-step" id="dock-step">Loading…</span>
      <span class="status" id="save-status">Loading saved state…</span>
    </summary>
    <div class="dock-panel">
      <header>
        <div class="eyebrow">NEXIAM · requirements gathering + user design</div>
        <h1>Client visit brief</h1>
        <p class="sub">One question at a time. Briefing on the left, interview on the right.</p>
        <div class="dock-controls">
          <div class="modes" role="group" aria-label="View mode">
            <button type="button" data-mode="briefing">Briefing</button>
            <button type="button" data-mode="interview">Interview</button>
          </div>
          <div class="actions">
            <a class="primary" href="/capstone/status">Roadmap</a>
            <a href="/capstone-interview-guide.html">Guide</a>
            <a href="/capstone-manuscript-brief.html">Manuscript</a>
            <a href="/capstone-wireframe-review.html">Wireframes</a>
          </div>
        </div>
      </header>

    </div>
  </details>
</main>

<script>
  const PAGE = 'client-brief';
  const STATE_API = '/capstone/docs/state';
  const NAV_KEY = 'navigation.step';
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
        { type: 'info', no: '3', title: 'Consultation volume', estimated: true, body: 'On consultation day, applicants <strong>wait in line for hours</strong> without a specific time slot, only to receive a brief <strong>2–5 minute</strong> advising session.', why: 'Driven by <strong>student wait-time pain</strong>: granular <strong>hourly appointment slots</strong> (e.g. 9:00–10:00 AM) prevent all-day queues and spread the counseling load.' },
        { type: 'info', no: '4', title: 'Applicant visits', body: 'About <strong>three campus visits</strong> were described, mainly for requirements, taking the exam, and claiming results.', why: 'Online information and status tracking may reduce unnecessary travel.' },
        { type: 'info', no: '5', title: 'Travel', body: 'Applicants come from nearby municipalities including <strong>Sta. Cruz, Bangar, Sudipen, Tagudin, Sta. Lucia, and Alilem</strong>, with an estimated travel time of at least <strong>10 to 30 minutes</strong>.', why: 'Clear published schedules and online status help applicants plan their travel and avoid unnecessary campus trips.' },
        { type: 'info', no: '6', title: 'Common questions', body: 'Staff <strong>repeatedly answer the same self-explanatory questions</strong>: exam date, application status, requirements, contact info.', why: 'A routine load an approved FAQ/chatbot could absorb — staff time goes to real cases.' },
        { type: 'info', no: '7', title: 'Proctoring', body: 'Guidance/psychometricians administer the ICAT. Under a <strong>strict window</strong> (e.g. one-week deadline), <strong>authorized trained staff</strong> plus allotted rooms can make <strong>all exams happen in one day</strong>.', why: 'Proctoring flexibility is the feature enabler for a tight exam window.' },
        { type: 'info', no: '8', title: 'Scheduling', body: 'Applicants experience <strong>schedule uncertainty and travel conflicts</strong> when exam and consultation batches are announced informally or on short notice, without slot choice.', why: 'Driven by <strong>student pain</strong>: published schedules and optional online self-booking remove schedule anxiety and repeat campus visits.' },
        { type: 'info', no: '9', title: 'Clustering and chatbot', body: 'A <strong>data-driven analysis capability is underutilized</strong>: techniques exist but are disconnected from daily work.', why: 'Direction: house analysis techniques under <strong>one analysis engine / administration</strong> (future), always as a counselor reference — never a decision.' },
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
  const SECTION_CONTEXT = {
    'Part 1 · Session 1 recap': {
      summary: 'Ito ang recorded baseline: saan mabagal ang process, saan nawawala ang impormasyon, at saan umaasa sa messages o memory.',
      source: 'Guidance questionnaire, 14 Jun 2026; follow-up interview and Program Head survey, 25 Jun 2026',
      feature: 'Applicant intake · OMR checking · consultation scheduling · quota visibility',
      requirement: 'SO1 evidence → FR-01, FR-03, FR-05, FR-07, FR-08, FR-11',
      status: 'Recorded baseline',
      script: 'Mabilis na recap lang po ito ng na-record natin. Tama ba ang description sa actual na office process?',
      sequence: `flowchart LR
    A[Paper application] --> B[Requirements folders and lists]
    B --> C[Exam slots: one authority]
    C --> D[Exam and paper attendance]
    D --> E[Manual scoring: 5+ days]
    E --> F[Release with face-to-face consultation]
    F --> G[Enrollment follow-up]
    B -.->|FM5| B
    C -.->|FM3| C
    E -.->|FM1| E
    F -.->|FM2 and FM4| F`,
    },
    'Part 2 · Digitized processes': {
      summary: 'For every proposed digital process, compare the current practice with the proposed feature before recording the client decision.',
      source: 'Requirements and decision matrix; Guidance and Program Head responses, June 2026',
      feature: 'Applicant portal · shared schedule · OMR · consultation record · quota view · audit history',
      requirement: 'D1–D20 → FR-01 to FR-13',
      status: 'Proposed requirement',
      script: 'Isa-isahin natin ang proposed process. Sabihin lang po kung agree, modify, keep current, or defer.',
      sequence: `sequenceDiagram
    actor Applicant
    participant Portal
    participant Registrar
    participant Guidance
    Applicant->>Portal: Submit and track application
    Portal->>Registrar: Create review record
    Registrar-->>Portal: Return status or correction
    Portal->>Guidance: Show confirmed schedule and result state
    Guidance-->>Portal: Publish allowed next action`,
    },
    'Part 2 · Breaking changes': {
      summary: 'These rows change how offices work, not only where they store information. Each one needs an explicit policy decision.',
      source: 'Requirements and decision matrix; client validation brief, assumed complete for drafting',
      feature: 'Shared ownership · visible status · controlled fallbacks · recorded decisions',
      requirement: 'B1–B14 → FR-03, FR-04, FR-07, FR-09, FR-12, FR-13',
      status: 'Proposed change',
      script: 'Ito po ang change sa workflow. Compare natin ang current at proposed, then pili tayo ng decision.',
      sequence: `flowchart LR
    A[Current office practice] --> B{Client decision}
    B -->|Agree| C[Record proposed feature]
    B -->|Modify| D[Record correction]
    B -->|Keep current| E[Preserve existing rule]
    B -->|Defer| F[Create follow-up task]
    C & D & E & F --> G[Update matrix and requirement]`,
    },
    'Part 2 · Summary sign-off': {
      summary: 'The sign-off matrix gathers the final direction for the proposed feature set before the requirements are treated as settled.',
      source: 'Client visit brief, summary sign-off matrix',
      feature: 'Confirmed requirement register · client wording · owner and follow-up',
      requirement: 'Decision rows → approved, modified, or deferred requirement',
      status: 'Needs client direction',
      script: 'For this feature, ano po ang final direction: agree, modify, or defer? May exact wording ba tayo?',
      sequence: `sequenceDiagram
    actor Briefer
    participant Brief
    participant Client
    participant Matrix
    Briefer->>Brief: Show finding and feature map
    Brief->>Client: Ask final direction
    Client-->>Matrix: Agree, modify, or defer
    Client-->>Matrix: Add exact wording
    Matrix-->>Briefer: Save decision and follow-up`,
    },
    'Part 2 · Follow-up questions': {
      summary: 'Open policy questions must be answered before they become system rules. The retake question affects result history, cycle scope, and future admission attempts.',
      source: 'Client visit brief, follow-up question added 26 Aug 2026',
      feature: 'Retake policy · result history · cycle and academic-year rules',
      requirement: 'Open policy decision before implementation',
      status: 'Open follow-up',
      script: 'May isang policy question po tayo: puwede ba ang retake sa same cycle, or next academic year kung may saved result na?',
      sequence: `flowchart LR
    A[Student has saved result] --> B{Same admission cycle?}
    B -->|Allowed| C[Define attempt limit and correction rule]
    B -->|Not allowed| D[Check next academic year policy]
    D --> E[Record result-history rule]
    C --> E`,
    },
    'Part 3 · User Design walkthrough': {
      summary: 'The screen review follows the office roles. Confirm what each person must see first, what stays hidden, and which action or state is missing.',
      source: 'User Design Session 1 packet and existing UI kit reference',
      feature: 'Role-specific dashboards · lists · detail pages · actions · states',
      requirement: 'Interface coverage → role and workflow acceptance',
      status: 'Ready for walkthrough',
      script: 'Tingnan natin ang screens by role. Ano ang dapat makita agad, at ano ang hindi dapat makita ng ibang office?',
      sequence: `sequenceDiagram
    actor Briefer
    participant Screen
    participant Client
    participant Backlog
    Briefer->>Screen: Show role screen and states
    Screen->>Client: Ask what is missing or unnecessary
    Client-->>Backlog: Mark correction with owner
    Backlog-->>Briefer: Prepare next design revision`,
    },
    'Part 4 · Validation and evidence': {
      summary: 'The final record separates confirmed facts from proposals and unresolved questions. It is the evidence used to explain the requirements in Chapter 3.',
      source: 'Client visit brief, validation and evidence record',
      feature: 'Evidence log · decision history · corrections · permissions',
      requirement: 'Research traceability and RAD validation record',
      status: 'Evidence capture',
      script: 'Last part po: ilalagay natin ang corrections, new facts, and evidence para clear ang record ng visit.',
      sequence: `sequenceDiagram
    actor Interviewer
    participant Evidence
    participant Matrix
    participant Manuscript
    Interviewer->>Evidence: Record correction or decision
    Evidence->>Matrix: Link owner, state, and source
    Matrix->>Manuscript: Feed SO1 and SO2 explanation
    Manuscript-->>Interviewer: Preserve traceable result`,
    },
  };

  const ITEM_CONTEXT = {
    'Manual scoring': {
      fm: 'fm1',
      source: 'Guidance questionnaire R1 · 14 Jun 2026 (Real survey data)',
      finding: 'One cycle took more than five days; illegible and double-marked answers were reported.',
      feature: 'OMR-powered checking with manual fallback',
      requirement: 'FR-05, FR-06',
      status: 'In progress',
      script: 'Based po sa initial survey data natin, mahigit 5 araw ang inaabot sa pag-check ng isang cycle gamit ang stencil, at madalas may malabong shading o double marks. Tama po ba ito sa actual ninyong nararanasan?'
    },
    'Staff effort': {
      fm: 'fm1',
      source: 'Guidance questionnaire R1 · 14 Jun 2026 (Real survey data)',
      finding: 'About two to three staff, or all trained staff, may be drawn into one scoring process.',
      feature: 'Batch capture, review queue, and manual fallback',
      requirement: 'FR-05, FR-06',
      status: 'In progress',
      script: 'Na-record po sa survey na 2 hanggang 3 staff—at minsan lahat ng trained personnel—ang kailangang tumulong para lang matapos ang scoring. Ganito po ba kalaki ang staff effort tuwing checking?'
    },
    'Consultation volume': {
      fm: 'fm4',
      estimated: true,
      source: 'Applicant queue feedback & Guidance observation',
      finding: 'Applicants wait in line for hours without a specific appointment time, causing long queues for a 2–5 minute consultation.',
      feature: 'Granular hourly appointment slots (e.g. 9–10 AM) & capacity limit',
      requirement: 'FR-07',
      status: 'In progress',
      script: 'Ayon po sa feedback ng mga estudyante, dagsa sila sa consultation day at naghihintay ng ilang oras sa pila dahil walang tiyak na oras ng appointment. Sa NEXIAM, magkakaroon ng hourly appointment slots (hal. 9:00–10:00 AM) para mai-spread ang tao at hindi maghintay ang estudyante nang buong araw. Sang-ayon po ba kayo sa ganitong hourly scheduling?'
    },
    'Applicant visits': {
      fm: 'fm2',
      source: 'Guidance questionnaire R1 · 14 Jun 2026 (Real survey data)',
      finding: 'Applicants were described as making about three campus visits for requirements, the exam, and results.',
      feature: 'Applicant portal with visible status and next action',
      requirement: 'FR-01, FR-11',
      status: 'Implemented / extending',
      script: 'Ayon po sa naitalang sagot sa survey, karaniwang 3 beses bumabalik ang estudyante sa campus: submission, exam day, at pagkuha ng resulta. Tama po ba itong 3 visits sa normal na proseso?'
    },
    'Travel': {
      fm: 'fm2',
      source: 'Guidance questionnaire R1 & Estimation · 14 Jun 2026',
      finding: 'Applicants come from nearby municipalities (Sta. Cruz, Bangar, Sudipen, Tagudin, Sta. Lucia, Alilem) with an estimated travel time of at least 10–30 minutes.',
      feature: 'Published schedules and controlled online updates',
      requirement: 'FR-03, FR-11',
      status: 'In progress',
      script: 'Naitala po sa survey na nanggagaling ang applicants sa Sta. Cruz, Bangar, Sudipen, Tagudin, Sta. Lucia, at Alilem (roughly 10-30 mins). Kung gagawing online ang schedule at status, maiiwasan po ba ang dagdag na biyahe nila bago ang exam?'
    },
    'Common questions': {
      fm: 'fm2',
      source: 'Observation & Survey R1/R2 · June 2026',
      finding: 'Staff repeatedly answer the same self-explanatory questions: exam date, requirements, status, contact info.',
      feature: 'Approved FAQ and assistant fallback',
      requirement: 'FR-11, conditional chatbot',
      status: 'In progress',
      script: 'Na-observe po natin na paulit-ulit na may nagtatanong sa opisina kung bukas pa ang application, kailan ang exam, at ano ang requirements. Ganito po ba ang sitwasyon sa araw-araw?'
    },
    'Proctoring': {
      fm: 'fm3',
      source: 'Guidance follow-up interview · 25 Jun 2026 (Real interview data)',
      finding: 'ICAT is administered by trained Guidance/psychometrician staff; other staff could proctor under strict protocol.',
      feature: 'Session-scoped proctor duty — enables single-day exam',
      requirement: 'FR-03, capability and duty rules',
      status: 'In progress',
      script: 'Sa initial interview po, Guidance personnel ang trained mag-administer ng ICAT. Kung magkakaroon ng strict one-week deadline, sang-ayon po ba kayo na magtalaga ng authorized trained proctors para matapos ang lahat ng examinees sa takdang araw?'
    },
    'Scheduling': {
      fm: 'fm3',
      source: 'Guidance interview & Applicant feedback (Student pain-point)',
      finding: 'In the manual process, student slot choice does not exist — all batches are assigned. Applicants suffer from schedule clashes and travel anxiety when slots conflict with their availability.',
      feature: '24/7 published schedule & policy-controlled student self-booking',
      requirement: 'FR-03, FR-04, FR-07',
      status: 'In progress',
      script: 'Sa kasalukuyan po, wala talagang sistema kung saan nakakapili ang estudyante ng sarili nilang schedule — purely school-assigned lahat. Pero sa feedback ng mga estudyante, malaking tulong kung mabibigyan sila ng choice na pumili ng appointment o exam slot online para iwas-conflict sa biyahe at oras nila. Sa NEXIAM, may published calendar at kontrolado ng Guidance kung kailan bubuksan ang self-booking. Sang-ayon po ba kayo sa ganitong flexibility?'
    },
    'Clustering and chatbot': {
      fm: 'fm4',
      source: 'Guidance follow-up interview · 25 Jun 2026 (Real interview data)',
      finding: 'Data-driven analysis exists but is underutilized and disconnected from daily work.',
      feature: 'One analysis engine / administration (future); counselor reference',
      requirement: 'FR-08, conditional',
      status: 'Direction agreed, future',
      script: 'Ayon po sa interview, may machine learning research na rito sa campus para sa course profiling pero hindi nagagamit sa daily counseling. Sang-ayon po ba kayo na maging automated reference tool ito sa dashboard habang kayo pa rin ang may final say?'
    },
    'Quota information': {
      fm: 'fm4',
      source: 'Program Head survey · 25 Jun 2026 (Real survey data)',
      finding: 'Quota information moved through face-to-face coordination and daily updates during admission.',
      feature: 'Shared quota view with update time',
      requirement: 'FR-08',
      status: 'In progress',
      script: 'Base po sa survey ng Program Heads, face-to-face at araw-araw silang nag-a-update ng quota kay Guidance. Tama po ba na kailangan ng real-time quota tracker para agad ninyong makita kung may available slot pa habang nag-a-advise?'
    },
    'Program Head needs': {
      fm: 'fm4',
      source: 'Program Head survey · 25 Jun 2026 (Real survey data)',
      finding: 'Real-time capacity, pre-enrollment lists, threaded notes, and quota alerts were rated useful.',
      feature: 'Program Head dashboard and quota alerts',
      requirement: 'FR-08, FR-13',
      status: 'Designed / extending',
      script: 'Sa survey po ng Program Heads, kailangan nila ng real-time visibility sa slots at automated alert kapag malapit nang mapuno ang kurso. Sang-ayon po ba kayo na ito ang maging feature para sa kanila?'
    },
    d1: {
      source: 'Guidance questionnaire R1 · 14 Jun 2026',
      finding: 'Paper applications are encoded later.',
      feature: 'Digital application and applicant portal',
      requirement: 'FR-01',
      status: 'Implemented',
      script: 'Inirerekomenda po natin ang online application form para isang beses lang i-encode ng applicant at may unique access key agad. Sang-ayon po ba kayo na ito ang standard intake natin?'
    },
    d2: {
      source: 'Guidance questionnaire R1 and client brief',
      finding: 'Identity is carried by names, NOA numbers, and paper records.',
      feature: 'Single applicant identifier key',
      requirement: 'FR-01, FR-11',
      status: 'Implemented',
      script: 'Iisang permanent applicant reference key po ang gagamitin mula form, exam slip, hanggang release para walang kalituhan sa duplicate names. Sang-ayon po ba kayo?'
    },
    d3: {
      source: 'Client brief validation correction',
      finding: 'Requirements are checked in folders or separate lists.',
      feature: 'Checklist with only missing and submitted/verified states',
      requirement: 'FR-02',
      status: 'Proposed',
      script: 'Gagawin po nating digital checklist na may missing at submitted/verified status para transparent sa applicant at Registrar ang kulang na dokumento. Sang-ayon po ba kayo?'
    },
    d4: {
      source: 'Guidance interview and client brief correction',
      finding: 'Exam slots are set by one authority and traditionally cannot be adjusted.',
      feature: 'Shared exam schedule with visible ownership and changes',
      requirement: 'FR-03',
      status: 'In progress',
      script: 'Iisang shared exam calendar po na may room capacity at proctor assignment para bawal ang double-booking at overbooking. Sang-ayon po ba kayo?'
    },
    d5: {
      source: 'Requirements matrix & Applicant interview (A8)',
      finding: 'Students currently have zero slot choice and must accept assigned dates, leading to conflicts and no-shows.',
      feature: 'Policy-controlled applicant booking',
      requirement: 'FR-04',
      status: 'In progress',
      script: 'Dahil walang slot choice ang student sa manual process ngayon, nagkakaroon minsan ng no-show o conflict sa biyahe. Sa NEXIAM, puwedeng buksan ng Guidance ang online self-booking slots para sa exam at consultation, pero puwede ring i-lock sa admin-assigned kung kailangan. Sang-ayon po ba kayo sa ganitong feature?'
    },
    d6: {
      source: 'Guidance questionnaire and requirements matrix',
      finding: 'Attendance is written or checked manually.',
      feature: 'Digital attendance with QR and paper fallback',
      requirement: 'FR-03',
      status: 'Implemented',
      script: 'Scan ng applicant QR code po ang gagamitin sa exam attendance at submission para mabilis at may instant attendance record, habang may manual fallback pa rin. Sang-ayon po ba kayo?'
    },
    d7: {
      source: 'Guidance questionnaire R1 · 14 Jun 2026',
      finding: 'Answer sheets are checked using a stencil or by hand.',
      feature: 'OMR-powered checking',
      requirement: 'FR-05',
      status: 'In progress',
      script: 'OMR automated scanning po ang gagamitin para sa bubble sheets para makuha agad ang raw score sa loob ng ilang minuto. Sang-ayon po ba kayo?'
    },
    d8: {
      source: 'Guidance questionnaire R1 · 14 Jun 2026',
      finding: 'Illegible, double-marked, or erased answers require judgment during checking.',
      feature: 'Review queue and manual fallback',
      requirement: 'FR-05',
      status: 'In progress',
      script: 'Lahat po ng malabo o double-marked answers ay awtomatikong papasok sa review queue para tao pa rin ang magdesisyon bago ma-finalize. Sang-ayon po ba kayo sa safety net na ito?'
    },
    d9: {
      source: 'Client brief and scoring setup',
      finding: 'Raw scores are converted with a separate table or calculation.',
      feature: 'Confidential stored conversion table',
      requirement: 'FR-06',
      status: 'Implemented',
      script: 'Ang confidential conversion table ng Guidance ang awtomatikong gagamitin ng system para i-convert ang raw score sa official rating para iwas-manual calculation error. Sang-ayon po ba kayo?'
    },
    d10: {
      source: 'Client brief validation correction',
      finding: 'Results are released only with the face-to-face consultation; there is no online result.',
      feature: 'Controlled release policy and result preparation',
      requirement: 'FR-09',
      status: 'Policy decision needed',
      script: 'Inirerekomenda po natin na may human approval muna bago ma-release ang official results online o printed copy para kontrolado ang release. Sang-ayon po ba kayo?'
    },
    d11: {
      source: 'Guidance questionnaire & Student wait-time feedback (A2, A8)',
      finding: 'Consultations currently have no hourly time slots, leading to multi-hour queues for a brief 2–5 minute session.',
      feature: 'Granular hourly consultation slots with room capacity',
      requirement: 'FR-07',
      status: 'Designed',
      script: 'Gagawin po nating may hourly appointment slots (halimbawa 9:00–10:00 AM) at room capacity ang consultation para hindi maghintay ang estudyante ng ilang oras sa labas ng Guidance Office. Sang-ayon po ba kayo?'
    },
    d12: {
      source: 'Client brief',
      finding: 'Consultation is currently face-to-face and arranged informally.',
      feature: 'Policy-controlled face-to-face or online mode',
      requirement: 'FR-07',
      status: 'Proposed',
      script: 'Isu-support po ng system ang face-to-face consultation bilang default, pero may option din for online consultation para sa mga malalayo. Sang-ayon po ba kayo?'
    },
    d13: {
      source: 'Client brief validation correction',
      finding: 'Advice is spoken during the session and not recorded on paper or elsewhere.',
      feature: 'Short typed consultation record',
      requirement: 'FR-07',
      status: 'Proposed',
      script: 'Direkta pong itatype ng counselor ang maikling remarks at next step sa portal para may permanenteng record ang student. Sang-ayon po ba kayo?'
    },
    d14: {
      source: 'Requirements matrix',
      finding: 'Follow-up may be invisible to the next office.',
      feature: 'Trackable follow-up and handoff',
      requirement: 'FR-07, FR-13',
      status: 'Designed',
      script: 'May malinaw pong status at due date ang bawat follow-up para makita agad ng Registrar o Program Head ang estado ng aplikante. Sang-ayon po ba kayo?'
    },
    d15: {
      source: 'Program Head survey · 25 Jun 2026',
      finding: 'Quota information is requested from a coordinator or Program Head.',
      feature: 'Program quota view',
      requirement: 'FR-08',
      status: 'Designed',
      script: 'Real-time dashboard po ng course quota ang makikita ng Guidance at Program Heads para alam agad kung bukas pa ang kurso. Sang-ayon po ba kayo?'
    },
    d16: {
      source: 'Program Head survey and requested alert',
      finding: 'Staff may learn late that a program is near or over quota.',
      feature: 'Quota threshold alert',
      requirement: 'FR-08',
      status: 'Conditional',
      script: 'Awtomatikong magpapadala ng alert ang system kapag 90% na o puno na ang slots ng isang kurso. Sang-ayon po ba kayo?'
    },
    d17: {
      source: 'Guidance questionnaire R1 and R2',
      finding: 'Routine questions arrive through messages, calls, social media, or visits.',
      feature: 'Approved FAQ/chatbot with human fallback',
      requirement: 'FR-11',
      status: 'Started / conditional',
      script: 'Ang chatbot po ay sasagot lamang base sa approved institutional documents at magdidirekta sa staff kapag hindi sigurado. Sang-ayon po ba kayo?'
    },
    d18: {
      source: 'Guidance follow-up interview · 25 Jun 2026',
      finding: 'Guidance and Registrar coordinate through external messaging channels.',
      feature: 'Trackable in-app request or task',
      requirement: 'FR-13',
      status: 'Designed',
      script: 'Lahat po ng coordination sa pagitan ng Guidance at Registrar ay nakakabit sa official applicant record sa halip na sa personal chat. Sang-ayon po ba kayo?'
    },
    d19: {
      source: 'Requirements matrix',
      finding: 'Reports are prepared manually from separate lists.',
      feature: 'Role-based reports',
      requirement: 'FR-11, FR-12',
      status: 'In progress',
      script: 'One-click generation po ng official applicant lists, score rosters, at quota summaries base sa authorized role ng opisina. Sang-ayon po ba kayo?'
    },
    d20: {
      source: 'Guidance questionnaire and requirements matrix',
      finding: 'The process has no single activity history for views or changes.',
      feature: 'Activity and audit history',
      requirement: 'FR-12',
      status: 'Implemented / extending',
      script: 'Naka-log po ang bawat viewing, score entry, approval, at release kasama ang pangalan ng staff at exact timestamp para sa audit trail. Sang-ayon po ba kayo?'
    },
    b1: {
      source: 'Requirements matrix B1',
      finding: 'After consultation, the next step may be invisible.',
      feature: 'Trackable handoff',
      requirement: 'FR-07, FR-13',
      status: 'Proposed',
      script: 'Inirerekomenda po natin na may malinaw na digital handoff ang bawat advising session para walang estudyanteng napapabayaan. Sang-ayon po ba kayo?'
    },
    b2: {
      source: 'Client brief correction',
      finding: 'Release occurs only with the face-to-face consultation as one crowded activity.',
      feature: 'Separate release timing from consultation scheduling',
      requirement: 'FR-09',
      status: 'Policy decision needed',
      script: 'Mas mainam po na ihiwalay ang schedule ng result release sa consultation sessions para hindi magdagsaan sa iisang araw ang mga tao. Sang-ayon po ba kayo sa separation na ito?'
    },
    b3: {
      source: 'Client brief',
      finding: 'Release and consultation may be face-to-face only.',
      feature: 'Office-policy mode selection',
      requirement: 'FR-07, FR-09',
      status: 'Proposed',
      script: 'Mananatili po ang face-to-face consultation, pero papayagan ang verified online mode kung may office approval. Sang-ayon po ba kayo?'
    },
    b4: {
      source: 'Requirements matrix B4 & Student slot selection',
      finding: 'Admin controls every exam schedule and booking without student choice.',
      feature: 'Separate booking policy toggles',
      requirement: 'FR-04',
      status: 'In progress',
      script: 'Hawak po ng Guidance ang switch: puwedeng admin-assigned ang exam pero self-booking ang consultation, o vice versa, para maibagay sa dami ng staff at pasilidad. Sang-ayon po ba kayo sa ganitong hiwalay na kontrol?'
    },
    b5: {
      source: 'Guidance interview R2',
      finding: 'Schedule is coordinated through separate messages.',
      feature: 'Shared schedule with conflict visibility',
      requirement: 'FR-03',
      status: 'In progress',
      script: 'Iisang master schedule po ang magbabantay sa room capacity para maiwasan ang venue conflicts at double-booking. Sang-ayon po ba kayo?'
    },
    b6: {
      source: 'Guidance interview R2',
      finding: 'Guidance normally proctors; trained staff may help during a surge.',
      feature: 'Authorized session-scoped proctor duty',
      requirement: 'FR-03, duty authorization',
      status: 'In progress',
      script: 'Puwede pong magtalaga ng trained support staff bilang proctor kapag surge pero Guidance pa rin ang mag-aapruba. Sang-ayon po ba kayo?'
    },
    b7: {
      source: 'Guidance questionnaire R1',
      finding: 'Manual stencil scoring is the main check.',
      feature: 'OMR first check with human review',
      requirement: 'FR-05',
      status: 'In progress',
      script: 'OMR scanning po ang gagamitin para mapabilis ang checking, pero mananatili ang manual checking bilang complete fallback. Sang-ayon po ba kayo?'
    },
    b8: {
      source: 'Requirements matrix B8',
      finding: 'Scores move between paper, spreadsheets, and messages.',
      feature: 'One confirmed result record',
      requirement: 'FR-06, FR-09',
      status: 'In progress',
      script: 'Isang confirmed score record lang po ang gagamitin para sa printing, release, at portal para iwas sa magkakaibang kopya. Sang-ayon po ba kayo?'
    },
    b9: {
      source: 'Program Head survey R1',
      finding: 'Quota is obtained by asking the coordinator or Program Head.',
      feature: 'Shared quota status and alerts',
      requirement: 'FR-08',
      status: 'Designed',
      script: 'Live quota tracking po na may automated threshold alert ang gagamitin para mas mabilis makapagdesisyon ang Program Heads. Sang-ayon po ba kayo?'
    },
    b10: {
      source: 'Guidance questionnaire R1 and R2',
      finding: 'Routine questions consume staff time.',
      feature: 'Approved FAQ/chatbot with staff fallback',
      requirement: 'FR-11',
      status: 'Started / conditional',
      script: 'Hindi po mag-iimbento ng sagot ang chatbot — sasagot lamang ito mula sa official guidelines ng ISPSC. Sang-ayon po ba kayo?'
    },
    b11: {
      source: 'Guidance interview R2',
      finding: 'Clustering output is separate from the consultation workflow.',
      feature: 'Counselor reference beside applicant record',
      requirement: 'FR-08, conditional',
      status: 'Conditional',
      script: 'Ang AI o profiling models ay magsisilbing reference lamang; ang counselor at applicant pa rin ang may huling desisyon sa kurso. Sang-ayon po ba kayo sa panuntunang ito?'
    },
    b12: {
      source: 'Requirements matrix B12',
      finding: 'No single activity history spans the offices.',
      feature: 'Attributable activity history',
      requirement: 'FR-12',
      status: 'Implemented / extending',
      script: 'Lahat po ng sensitibong aksyon (tulad ng grade correction o unrelease) ay kailangang maglagay ng authority reason at naka-record sa audit history. Sang-ayon po ba kayo?'
    },
    b13: {
      source: 'Client brief correction',
      finding: 'Exam slots are set by a single authority and cannot be adjusted.',
      feature: 'Shared schedule with office-controlled adjustments',
      requirement: 'FR-03',
      status: 'Policy decision needed',
      script: 'Sa halip na isang tao lang ang humahawak ng schedule na bawal baguhin, magiging transparent at adjustable ito sa ilalim ng Guidance rules. Sang-ayon po ba kayo?'
    },
    b14: {
      source: 'Client brief correction & Student wait-time survey',
      finding: 'Open-ended arrivals lead to congested queues and wasted student hours.',
      feature: 'Defined hourly consultation windows',
      requirement: 'FR-07',
      status: 'Policy decision needed',
      script: 'Gagawin po nating may tiyak na oras o hourly appointment slots ang consultation sa halip na sabihing pumunta lang sa isang araw nang walang oras, para mawala ang mahabang pila. Sang-ayon po ba kayo?'
    },
    'followup.retake': {
      source: 'Client brief follow-up question',
      finding: 'Policy is not yet settled for a student with a saved result.',
      feature: 'Retake and result-history policy',
      requirement: 'Open decision before implementation',
      status: 'Open',
      script: 'Inirerekomenda po natin na kung may saved result na ang student sa current cycle, sa susunod na academic year na siya puwedeng mag-retake ng exam. Sang-ayon po ba kayo rito o may exception tayong papayagan?'
    },
    'signoff.digital-application': {
      source: 'Summary sign-off matrix',
      finding: 'Digital application and identifier key',
      feature: 'Digital intake and portal',
      requirement: 'FR-01',
      status: 'Needs direction',
      script: 'Para sa digital intake at applicant portal: kumpirmado po ba na ito ang magiging standard natin?'
    },
    'signoff.omr-fallback': {
      source: 'Summary sign-off matrix',
      finding: 'OMR plus manual fallback',
      feature: 'Human-reviewed OMR workflow',
      requirement: 'FR-05',
      status: 'Needs direction',
      script: 'Para sa OMR automated checking with human review: kumpirmado po ba na ito ang gagamitin?'
    },
    'signoff.human-confirmation': {
      source: 'Summary sign-off matrix',
      finding: 'Human confirmation before release',
      feature: 'Release approval gate',
      requirement: 'FR-09',
      status: 'Needs direction',
      script: 'Para sa mandatory human approval bago ang anumang result release: kumpirmado po ba?'
    },
    'signoff.online-result': {
      source: 'Summary sign-off matrix and corrected current state',
      finding: 'Proposed online result versus current release with consultation',
      feature: 'Release policy and result delivery',
      requirement: 'FR-09',
      status: 'Needs direction',
      script: 'Para sa online release na may print-on-request: kumpirmado po ba na ito ang magiging delivery mode natin?'
    },
    'signoff.consultation-schedule': {
      source: 'Summary sign-off matrix',
      finding: 'Separate consultation schedule',
      feature: 'Consultation sessions and capacity',
      requirement: 'FR-07',
      status: 'Needs direction',
      script: 'Para sa hiwalay na consultation scheduling: kumpirmado po ba?'
    },
    'signoff.f2f-consultation': {
      source: 'Summary sign-off matrix',
      finding: 'Face-to-face consultation remains available',
      feature: 'F2F consultation mode',
      requirement: 'FR-07',
      status: 'Needs direction',
      script: 'Para sa pananatili ng face-to-face consultation bilang primary option: kumpirmado po ba?'
    },
    'signoff.online-consultation': {
      source: 'Summary sign-off matrix',
      finding: 'Online consultation option',
      feature: 'Policy-controlled online mode',
      requirement: 'FR-07',
      status: 'Needs direction',
      script: 'Para sa verified online consultation option kung may pahintulot: kumpirmado po ba?'
    },
    'signoff.self-booking': {
      source: 'Summary sign-off matrix',
      finding: 'Exam and consultation self-booking',
      feature: 'Booking policy toggles',
      requirement: 'FR-04, FR-07',
      status: 'Needs direction',
      script: 'Para sa switchable applicant booking sa ilalim ng kontrol ng Guidance: kumpirmado po ba?'
    },
    'signoff.trackable-followup': {
      source: 'Summary sign-off matrix',
      finding: 'Trackable follow-up and handoff',
      feature: 'Applicant workspace and follow-up queue',
      requirement: 'FR-07, FR-13',
      status: 'Needs direction',
      script: 'Para sa trackable advising handoff at follow-up records: kumpirmado po ba?'
    },
    'signoff.shared-quota': {
      source: 'Summary sign-off matrix',
      finding: 'Shared quota view and alerts',
      feature: 'Program quota dashboard',
      requirement: 'FR-08',
      status: 'Needs direction',
      script: 'Para sa shared real-time quota visibility at threshold alerts: kumpirmado po ba?'
    },
    'signoff.chatbot': {
      source: 'Summary sign-off matrix',
      finding: 'FAQ/chatbot with fallback',
      feature: 'Bounded assistant experience',
      requirement: 'FR-11',
      status: 'Needs direction',
      script: 'Para sa FAQ at chatbot na sasagot lamang base sa official ISPSC documents: kumpirmado po ba?'
    },
    'signoff.counselor-final': {
      source: 'Summary sign-off matrix',
      finding: 'Counselor remains final decision-maker',
      feature: 'Human decision boundary',
      requirement: 'FR-08',
      status: 'Needs direction',
      script: 'Kumpirmado po ba na counselor pa rin ang may final authority sa advising at placement?'
    },
    'Walkthrough: Guidance': {
      source: 'User Design Session 1',
      finding: 'Triage, OMR, scheduling, release, consultation, and proctoring screens',
      feature: 'Guidance dashboard and applicant workspace',
      requirement: 'Role-specific interface coverage',
      status: 'Ready for review',
      script: 'Ito po ang Guidance dashboard: dito makikita ang daily triage, scheduling, at scoring. Aling screen po ang una ninyong binubuksan araw-araw?'
    },
    'Walkthrough: Registrar': {
      source: 'User Design Session 1',
      finding: 'Applications, import, processing, reports, and setup screens',
      feature: 'Registrar queue and eligibility view',
      requirement: 'Role-specific interface coverage',
      status: 'Ready for review',
      script: 'Ito po ang Registrar queue: dito sinusuri ang submissions at eligibility. Aling column o status po ang pinakamadalas ninyong tinitingnan?'
    },
    'Walkthrough: Program Head': {
      source: 'User Design Session 1',
      finding: 'Quota, slate, and reports screens',
      feature: 'Program Head quota and candidate view',
      requirement: 'Role-specific interface coverage',
      status: 'Ready for review',
      script: 'Ito po ang Program Head view: dito makikita ang slots at incoming candidates. Malinaw po ba ang quota signal sa unang tingin?'
    },
    'Walkthrough: Applicant': {
      source: 'User Design Session 1',
      finding: 'Entry, status, form, slip, and chatbot screens',
      feature: 'Applicant portal',
      requirement: 'Own-data interface coverage',
      status: 'Ready for review',
      script: 'Ito po ang nakikita ng estudyante sa portal. Malinaw po ba ang susunod na hakbang na kailangan nilang gawin?'
    },
    'Walkthrough: System Admin': {
      source: 'User Design Session 1',
      finding: 'Users, audit, engines, and settings screens',
      feature: 'Administrative setup and audit views',
      requirement: 'Capability and scope coverage',
      status: 'Ready for review',
      script: 'Ito po ang admin view para sa account setup at audit logs. Aling configuration po ang pinakamadalas ninyong ina-adjust?'
    },
  };
  const CURATED_SEQUENCES = {
    'Manual scoring': `flowchart TD
    A["One admission cycle"] --> B["Stencil scoring, 5+ days"]
    B --> C["Illegible / double-marked"]
    C --> D["Release delayed"]`,
    'Staff effort': `flowchart LR
    A["1 cycle, 2–3 staff"] --> B["All trained staff drawn in"]
    B --> C["Repetitive manual checking"]`,
    'Consultation volume': `flowchart LR
    A["Consultation day: very busy"] --> B["Many applicants attend"]
    B --> C["Estimated 70–80 / day (verify)"]
    C --> D["Brief interactions, long lines"]`,
    'Applicant visits': `flowchart LR
    A["Visit 1 : requirements"] --> B["Visit 2 : exam"]
    B --> C["Visit 3 : claim result"]`,
    'Travel': `flowchart LR
    A["Municipalities: Sta. Cruz, Bangar, Sudipen, Tagudin, Sta. Lucia, Alilem"]
    B["Travel time: estimated 10–30 mins"] --> C["Clear online schedule reduces repeat trips"]`,
    'Common questions': `flowchart TD
    A["Staff answer the same questions repeatedly"] --> B["When is the exam? Is application open? Requirements? Status?"]
    B --> C["Chatbot could absorb routine load"]
    C --> D["Staff freed for real cases"]`,
    'Proctoring': `flowchart TD
    A["Strict exam window (e.g. one week)"] --> B{"All applicants in one day?"}
    B -->|rooms allotted| C["Multiple rooms in use"]
    B -->|staff allowed| D["Trained staff proctor under protocol"]
    C --> E["All exams completed on time"]
    D --> E`,
    'Scheduling': `flowchart TD
    A["Applicant: uncertain of exam/consultation slot"] --> B["Monitors Facebook / visits campus to ask"]
    B --> C["Travel and schedule conflicts occur"]
    C --> D["NEXIAM: published calendar + optional student self-booking"]
    D --> E["Applicant picks convenient slot; zero conflict"]`,
    'Clustering and chatbot': `flowchart TD
    A["Analysis techniques exist but are underused"] --> B["Techniques disconnected from daily work"]
    B --> C["Direction: one analysis engine / admin\nhousing all techniques (future)"]
    C --> D["Counselor decides from reference"]`,
    'Quota information': `sequenceDiagram
    participant ProgramHead
    participant Coordinator
    participant Guidance
    ProgramHead->>Coordinator: Daily updates (face-to-face)
    Coordinator->>Guidance: Relay quota figure
    Guidance->>Applicant: Advise on that figure`,
    'Program Head needs': `sequenceDiagram
    participant ProgramHead
    participant Program
    participant Registrar
    ProgramHead->>Program: Open quota capacity + applicant lists
    Program->>Program: Check current position
    ProgramHead->>Registrar: Request alert when quota reached`,
    d1: `sequenceDiagram
    participant Applicant
    participant Portal
    participant Registrar
    Applicant->>Portal: Submit application (once)
    Portal->>Registrar: Create review record
    Registrar-->>Applicant: Identifier key issued`,
    d2: `flowchart LR
    A["Name / NOA / paper"] --> B["One application ID key"]
    B --> C["Slip, answer sheet, result, portal"]`,
    d3: `flowchart LR
    A["Folders and separate lists"] --> B["Checklist"]
    B --> C["States: missing or submitted/verified"]`,
    d4: `flowchart LR
    A["Single authority slots"] --> B["Shared exam schedule"]
    B --> C["Date, time, room, batch, proctor"]`,
    d5: `sequenceDiagram
    participant Office
    participant Scheduling
    participant Applicant
    Office->>Scheduling: Turn booking on/off
    Scheduling-->>Applicant: Book or assigned
    Applicant->>Scheduling: Reserve seat
    Scheduling-->>Office: Confirm state`,
    d6: `sequenceDiagram
    participant Proctor
    participant Roster
    participant Attendance
    Proctor->>Roster: Scan ID/QR
    Roster->>Attendance: Mark present
    Attendance-->>Proctor: Paper backup stays`,
    d7: `sequenceDiagram
    participant Sheet
    participant OMR
    participant Scores
    Sheet->>OMR: Read marked answers
    OMR->>Scores: Produce raw counts
    Scores-->>Sheet: Confirmed later by staff`,
    d8: `flowchart LR
    A["Unclear / double-marked"] --> B["Low-confidence review list"]
    B --> C["Staff checks and confirms"]`,
    d9: `flowchart LR
    A["Raw score"] --> B["Stored conversion table"]
    B --> C["Official result"]`,
    d10: `flowchart LR
    A["Released only with consultation"] --> B["Controlled release timing"]
    B --> C["Online result + print on request"]`,
    d11: `sequenceDiagram
    participant Office
    participant Session
    participant Applicant
    Office->>Session: Create slots, capacity, mode
    Session-->>Applicant: Show open slots
    Applicant->>Session: Reserve / assigned
    Session-->>Office: Booked state`,
    d12: `flowchart TD
    A["Consultation mode"] --> B{"Policy / situation"}
    B -->|face-to-face| C["On-site session"]
    B -->|online| D["Video session"]`,
    d13: `sequenceDiagram
    participant Counselor
    participant Note
    Counselor->>Note: Type short advice
    Note->>Note: Date, counselor, next step
    Note-->>Counselor: Record retrievable later`,
    d14: `flowchart LR
    A["Follow-up after consultation"] --> B["Action, owner, due date, status"]
    B --> C["Visible to next office"]`,
    d15: `flowchart LR
    A["Ask coordinator / PH"] --> B["Program quota view"]
    B --> C["Quota, current status, last update"]`,
    d16: `flowchart LR
    A["Program near or over quota"] --> B["Threshold alert"]
    B --> C["Authorized staff notified"]`,
    d17: `sequenceDiagram
    participant Applicant
    participant Chatbot
    participant Staff
    Applicant->>Chatbot: Routine question
    Chatbot-->>Applicant: Approved answer
    Applicant->>Staff: Fallback when unanswered`,
    d18: `sequenceDiagram
    participant Guidance
    participant Request
    participant Registrar
    Guidance->>Request: Create in-app request
    Request->>Registrar: Notify with status
    Registrar-->>Guidance: Respond; status tracked`,
    d19: `flowchart LR
    A["Manual lists"] --> B["Role-based reports"]
    B --> C["Applicants, batches, scores, quota, follow-ups"]`,
    d20: `sequenceDiagram
    participant Actor
    participant History
    Actor->>History: Action (view / edit / approve)
    History->>History: Record who, when, what
    History-->>Actor: Attributable trail`,
    b1: `sequenceDiagram
    participant Consultation
    participant Handoff
    participant NextOffice
    Consultation->>Handoff: Advice + follow-up + owner + due
    Handoff->>NextOffice: Visible handoff state`,
    b2: `flowchart LR
    A["Release + consultation same day"] --> B["Separate result release timing"]
    B --> C["Release, then consultation schedule"]`,
    b3: `flowchart TD
    A["Release / consultation mode"] --> B{"Office policy"}
    B -->|F2F| C["On-site"]
    B -->|online| D["Remote + validated copy"]`,
    b4: `flowchart LR
    A["Admin controls every booking"] --> B["Separate booking toggles"]
    B --> C["Exam toggle | Consultation toggle"]`,
    b5: `flowchart LR
    A["Schedule via separate messages"] --> B["One shared schedule"]
    B --> C["Room, batch, capacity, proctors, conflicts"]`,
    b6: `flowchart LR
    A["Guidance only proctors"] --> B["Trained staff if authorized"]
    B --> C["Capacity without losing control"]`,
    b7: `flowchart LR
    A["Stencil main check"] --> B["OMR first check"]
    B --> C["Manual review as safety net"]`,
    b8: `flowchart LR
    A["Paper / spreadsheet / message"] --> B["One confirmed result record"]
    B --> C["Conversion, release, print, portal"]`,
    b9: `sequenceDiagram
    participant Guidance
    participant Status
    participant ProgramHead
    Guidance->>Status: Open quota status
    Status-->>ProgramHead: Update time visible
    Status-->>Guidance: Alerts on threshold`,
    b10: `sequenceDiagram
    participant Applicant
    participant FAQ
    participant Staff
    Applicant->>FAQ: Routine question
    FAQ-->>Applicant: Approved answer or decline
    Applicant->>Staff: Human fallback`,
    b11: `flowchart LR
    A["Clustering separate from work"] --> B["Dashboard beside applicant record"]
    B --> C["Counselor still decides"]`,
    b12: `sequenceDiagram
    participant Action
    participant Log
    Action->>Log: Staff, date, time, action
    Log-->>Action: Disputes resolved from record`,
    b13: `flowchart LR
    A["One authority, no adjustments"] --> B["Shared trackable schedule"]
    B --> C["Office-controlled adjustments"]`,
    b14: `flowchart LR
    A["Arrivals / informal coordination"] --> B["Scheduled consultation slots"]
    B --> C["Bookings, daily capacity, mode"]`,
    'Walkthrough: Guidance': `flowchart LR
    A["Triage, OMR, scheduling, release, consultation, proctoring"] --> B["Which opens first each day?"]`,
    'Walkthrough: Registrar': `flowchart LR
    A["Applications, import, processing, reports, setup"] --> B["Which column is checked most?"]`,
    'Walkthrough: Program Head': `flowchart LR
    A["Quota, slate, reports"] --> B["Quota signal visible at a glance?"]`,
    'Walkthrough: Applicant': `flowchart LR
    A["Entry, status, form, slip, chatbot"] --> B["Is the next step obvious?"]`,
    'Walkthrough: System Admin': `flowchart LR
    A["Users, audit, engines, settings"] --> B["Which setting changes most?"]`,
  };

  const FM_SEQUENCES = {
    fm1: `flowchart LR
    A["Answer sheets pile up"] --> B["Stencil scoring takes 5+ days"]
    B --> C["Illegible or double-marked answers"]
    C --> D["Result release is delayed"]`,
    fm2: `flowchart LR
    A["Results are ready"] --> B["Posted on Facebook page (office hours only)"]
    B --> C["Applicant travels back to campus"]
    C --> D["Result claimed only at consultation"]`,
    fm3: `flowchart LR
    A["Exam slots set by one authority"] --> B["Coordination through messaging app"]
    B --> C["No shared record of changes"]
    C --> D["Miscommunication and disputes"]`,
    fm4: `flowchart LR
    A["70–80 consultations per day"] --> B["Advice given verbally, 2–5 minutes"]
    B --> C["Quota figure from the coordinator"]
    C --> D["No traceable advice record"]`,
    fm5: `flowchart LR
    A["Cycle configuration rebuilt from memory"] --> B["Requirements checked in folders"]
    B --> C["No persistent managed record"]
    C --> D["Knowledge lost when staff change"]`,
  };

  function changeDiagram(step) {
    const from = plainText(step.from).slice(0, 110);
    const to = plainText(step.to).slice(0, 110);
    const owner = step.user ? plainText(step.user) : (step.title || step.no || 'Review');
    return `flowchart LR
    A["Current: ${from}"] --> B["Proposed: ${to}"]
    B --> C["Main user: ${owner}"]`;
  }

  function signoffDiagram(title) {
    return `flowchart LR
    A["${title}"] --> B{"Final direction?"}
    B -->|Agree| C["Feature confirmed"]
    B -->|Modify| D["Correction recorded"]
    B -->|Defer| E["Follow-up created"]`;
  }

  function contextFor(section, step) {
    const sectionContext = SECTION_CONTEXT[section.label] || {};
    const itemContext = ITEM_CONTEXT[step.key] || ITEM_CONTEXT[step.title] || {};
    let sequence = CURATED_SEQUENCES[step.key] || CURATED_SEQUENCES[step.title] || itemContext.sequence;
    if (!sequence && itemContext.fm) sequence = FM_SEQUENCES[itemContext.fm];
    if (!sequence && step.type === 'decision' && /^(d|b)\d|^validate\./.test(step.key)) sequence = changeDiagram(step);
    if (!sequence && (step.type === 'text' || step.type === 'partdone' || step.type === 'choice' || step.type === 'signoff')) sequence = null;
    return { ...sectionContext, ...itemContext, sequence, sectionFeatureList: sectionContext.feature };
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;',
    }[character]));
  }

  function plainText(value) {
    return String(value ?? '').replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();
  }

  let mermaidPromise = null;

  async function renderMermaid(slot, source, id) {
    if (!slot || !source) return;
    try {
      if (!mermaidPromise) {
        mermaidPromise = import('https://cdn.jsdelivr.net/npm/mermaid@11.17.2/+esm').then(({ default: mermaid }) => {
          mermaid.initialize({
            startOnLoad: false,
            securityLevel: 'strict',
            theme: 'dark',
            fontSize: 18,
            themeVariables: { fontSize: '18px' },
            flowchart: { wrappingWidth: 320, htmlLabels: true },
            sequence: { wrap: true, wrapPadding: 12, actorFontSize: 18, messageFontSize: 18, noteFontSize: 18 },
            fontFamily: 'system-ui, sans-serif',
          });
          return mermaid;
        });
      }
      const mermaid = await mermaidPromise;
      const { svg } = await mermaid.render(id, source);
      const canvas = slot.querySelector('.sequence-canvas') || slot;
      canvas.innerHTML = svg;
      bindDiagramPanning(slot);
      const gIdx = Number(slot.dataset.globalIdx ?? -1);
      if (gIdx >= 0) svgCache.set(gIdx + (id.includes('iv-') ? '-i' : '-b'), svg);
      bindDiagramWheel(slot, id);
    } catch (error) {
      console.error('briefing flow render failed', error);
      slot.classList.add('failed');
      const loading = slot.querySelector('.sequence-loading');
      if (loading) loading.textContent = 'Diagram unavailable; source shown below.';
      const fallback = slot.querySelector('.sequence-fallback');
      if (fallback) fallback.style.display = 'block';
    }
  }

  function setDiagramScale(id, requestedScale) {
    const slot = document.getElementById(id);
    if (!slot) return;
    const scale = Math.min(2.5, Math.max(.75, requestedScale));
    const canvas = slot.querySelector('.sequence-canvas') || slot;
    canvas.style.zoom = scale;
    slot.dataset.scale = String(scale);
    const output = document.querySelector('[data-diagram-scale="' + id + '"]');
    if (output) output.textContent = Math.round(scale * 100) + '%';
  }
  async function toggleDiagramFullscreen(id) {
    const slot = document.getElementById(id);
    if (!slot || !slot.requestFullscreen) return;
    try {
      if (document.fullscreenElement === slot) await document.exitFullscreen();
      else await slot.requestFullscreen();
    } catch (error) {
      console.debug('diagram fullscreen unavailable', error);
    }
  }

  document.addEventListener('fullscreenchange', () => {
    document.querySelectorAll('[data-diagram-action="fullscreen"]').forEach((button) => {
      const active = document.fullscreenElement?.id === button.dataset.diagramId;
      button.textContent = active ? 'Exit full screen' : 'Full screen';
      button.setAttribute('aria-pressed', String(active));
    });
  });

  function bindDiagramPanning(slot) {
    if (slot.dataset.panBound === 'true') return;
    slot.dataset.panBound = 'true';
    let pointer = null;

    slot.addEventListener('pointerdown', (event) => {
      if (event.target.closest('button')) return;
      pointer = { x: event.clientX, y: event.clientY, left: slot.scrollLeft, top: slot.scrollTop };
      slot.classList.add('is-panning');
      slot.setPointerCapture(event.pointerId);
      event.preventDefault();
    });
    slot.addEventListener('pointermove', (event) => {
      if (!pointer) return;
      slot.scrollLeft = pointer.left - (event.clientX - pointer.x);
      slot.scrollTop = pointer.top - (event.clientY - pointer.y);
    });
    const stop = (event) => {
      pointer = null;
      slot.classList.remove('is-panning');
      if (slot.hasPointerCapture(event.pointerId)) slot.releasePointerCapture(event.pointerId);
    };
    slot.addEventListener('pointerup', stop);
    slot.addEventListener('pointercancel', stop);
  }
  function bindDiagramWheel(slot, id) {
    if (slot.dataset.wheelBound === 'true') return;
    slot.dataset.wheelBound = 'true';
    slot.addEventListener('wheel', (event) => {
      event.preventDefault();
      const scale = Number(slot.dataset.scale || 1);
      if (event.shiftKey) {
        slot.scrollLeft += event.deltaY || event.deltaX;
        return;
      }
      setDiagramScale(slot.id, scale * (event.deltaY < 0 ? 1.1 : 0.9));
    }, { passive: false });
  }

  const ICONS = {
    finding: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>',
    feature: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l9 5-9 5-9-5 9-5z"/><path d="M3 13l9 5 9-5"/></svg>',
    flow: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="5" cy="12" r="2.5"/><circle cx="19" cy="5" r="2.5"/><circle cx="19" cy="19" r="2.5"/><path d="M7 11l10-6M7 13l10 6"/></svg>',
    section: '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="3" y="4" width="7" height="7" rx="1"/><rect x="14" y="4" width="7" height="7" rx="1"/><rect x="3" y="15" width="7" height="7" rx="1"/><rect x="14" y="15" width="7" height="7" rx="1"/></svg>',
  };

  function iconChip(kind, word, label) {
    return '<span class="ref-icon ' + kind + '" aria-label="' + label + '">' + ICONS[kind] + '<span class="ref-word">' + word + '</span></span>';
  }

  function walkthroughWording(context, step) {
    if (step.type === 'decision') {
      const user = step.user ? ' Main user: ' + plainText(step.user) + '.' : '';
      return 'Ito ang flow ng pagbabago: mula sa current process papunta sa proposed feature.' + user + ' I-walk through mo muna ito bago itanong ang decision.';
    }
    if (step.type === 'signoff') {
      return 'Ito ang decision area na kailangang i-confirm. Sabihin ang final direction, tapos itanong kung may exact wording ang client.';
    }
    if (step.type === 'info') {
      return 'Ito ang na-observe nating flow tungkol sa ' + (step.title || 'process nito') + '. I-walk through mo ito bago i-confirm ang finding sa client.';
    }
    return 'I-walk through mo ang flow na ito habang nagtatanong ka.';
  }

  function renderInterviewFlow(context, id, step) {
    const hasInsight = Boolean(context.finding || context.summary);
    const hasFeature = Boolean(context.feature);
    const hasDiagram = Boolean(context.sequence);

    let togglesHtml = '';
    if (hasInsight || hasFeature || hasDiagram) {
      togglesHtml = '<div class="briefing-element-toggles" role="group" aria-label="Client view element controls">' +
        '<span class="element-toggle-label">Client sees:</span>' +
        (hasInsight ? '<button type="button" class="elem-toggle on" data-element-toggle="insight" aria-label="Toggle insight on client view">' + ICONS.finding + ' <span>Insight</span></button>' : '') +
        (hasFeature ? '<button type="button" class="elem-toggle on" data-element-toggle="feature" aria-label="Toggle feature mapping on client view">' + ICONS.feature + ' <span>Feature</span></button>' : '') +
        (hasDiagram ? '<button type="button" class="elem-toggle on" data-element-toggle="diagram" aria-label="Toggle flow diagram on client view">' + ICONS.flow + ' <span>Flow</span></button>' : '') +
      '</div>';
    }

    let flowHtml = '';
    if (hasDiagram) {
      flowHtml = '<div class="sequence-card">' +
        '<div class="sequence-toolbar">' + iconChip('flow', '', 'Section flow') +
          '<div class="sequence-tools" role="group" aria-label="Diagram controls">' +
            '<button type="button" data-diagram-action="zoom-out" data-diagram-id="' + id + '" aria-label="Zoom out">−</button>' +
            '<button type="button" data-diagram-action="reset" data-diagram-id="' + id + '">Reset</button>' +
            '<button type="button" data-diagram-action="zoom-in" data-diagram-id="' + id + '" aria-label="Zoom in">+</button>' +
            '<button type="button" data-diagram-action="fullscreen" data-diagram-id="' + id + '">Full screen</button>' +
            '<output data-diagram-scale="' + id + '">100%</output>' +
          '</div>' +
        '</div>' +
        '<div class="sequence-slot" id="' + id + '" data-scale="1">' +
          '<div class="sequence-canvas"><div class="sequence-loading">Loading flow…</div>' +
          '<pre class="sequence-fallback">' + escapeHtml(context.sequence || '') + '</pre></div>' +
        '</div>' +
      '</div>';
    }

    return '<div class="interview-flow">' +
      togglesHtml +
      flowHtml +
      '<div class="walkthrough-note"><p>' + escapeHtml(walkthroughWording(context, step)) + '</p></div>' +
    '</div>';
  }

  function renderBriefingReference(context, id, step) {
    const script = context.script || (
      step.type === 'decision'
        ? `${step.no || 'Row'} po: current natin, ${plainText(step.from)} Proposed: ${plainText(step.to)} Agree, modify, keep current, or defer?`
        : `Ito ang na-record nating finding tungkol sa ${step.title || 'process'}. Tama ba ito sa actual na office process ninyo?`
    );
    const cards = [];
    if (context.finding || context.summary) {
      cards.push(`<article class="reference-card briefing-card-insight">
        ${iconChip('finding', 'Insight', 'Earlier interview finding')}
        <p>${escapeHtml(context.finding || context.summary)}</p>
        <p class="reference-meta">${escapeHtml(context.source || 'Client brief')}</p>
      </article>`);
    }
    if (context.feature) {
      cards.push(`<article class="reference-card briefing-card-feature">
        ${iconChip('feature', 'Feature', 'Feature mapping')}
        <p><strong>${escapeHtml(context.feature)}</strong></p>
        <p class="reference-meta">${escapeHtml(context.requirement || '')}${context.requirement ? ' · ' : ''}${escapeHtml(context.status || '')}</p>
        ${context.sectionFeatureList ? `<p class="section-chip">${ICONS.section} <span><b>Section:</b> ${escapeHtml(context.sectionFeatureList)}</span></p>` : ''}
      </article>`);
    }
    return `<div class="briefing-reference">
      ${cards.length ? '<div class="reference-grid">' + cards.join('') + '</div>' : ''}
      ${context.sequence ? `<div class="sequence-card briefing-flow-card">
        <div class="sequence-toolbar">
          ${iconChip('flow', '', 'Section flow')}
        </div>
        <div class="sequence-slot" id="${id}" data-scale="1">
          <div class="sequence-canvas">
            <div class="sequence-loading">Loading flow…</div>
            <pre class="sequence-fallback">${escapeHtml(context.sequence || '')}</pre>
          </div>
        </div>
      </div>` : ''}
      <details class="talk-track">
        <summary>Briefer note · Taglish</summary>
        <p class="taglish">${escapeHtml(script)}</p>
      </details>
    </div>`;
  }

  const steps = [];
  DATA.forEach((section, s) => {
    section.steps.forEach((step, i) => steps.push({ section, step, s, i }));
    steps.push({
      section, s, i: section.steps.length,
      step: { type: 'partdone', taskKey: section.taskKey, title: section.label + ' complete?' },
    });
  });

  const SECTIONS_KEY = 'sections.visible';
  const DIAGRAM_HIDDEN_KEY = 'diagram.hidden';
  const INSIGHT_HIDDEN_KEY = 'insight.hidden';
  const FEATURE_HIDDEN_KEY = 'feature.hidden';
  const EYE_OPEN = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>';
  const EYE_OFF = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/><path d="M4 4l16 16"/></svg>';

  let sectionsVisible = new Set();

  function sectionLabels() {
    return DATA.map(s => s.label);
  }

  function visibleSteps() {
    return steps.filter(s => sectionsVisible.has(s.section.label));
  }

  function syncSectionsUI() {
    const hint = document.getElementById('sec-hint');
    const eyes = document.querySelectorAll('#sec-eyes .sec-toggle');
    const onCount = sectionsVisible.size;
    if (hint) hint.textContent = 'Sections to present (' + onCount + '/' + eyes.length + ' shown):';
    let k = 0;
    eyes.forEach((btn) => {
      const label = btn.dataset.section;
      const on = sectionsVisible.has(label);
      btn.classList.toggle('on', on);
      btn.classList.toggle('off', !on);
      btn.querySelector('svg').outerHTML = on ? EYE_OPEN : EYE_OFF;
      btn.setAttribute('aria-pressed', String(on));
      k += 1;
    });
  }

  function renderSectionsStrip() {
    const host = document.getElementById('sec-eyes');
    if (!host) return;
    host.innerHTML = sectionLabels().map(label =>
      '<button type="button" class="sec-toggle off" data-section="' + escapeHtml(label) + '" aria-pressed="false">' + EYE_OFF + '<span>' + escapeHtml(label) + '</span></button>'
    ).join('');
    syncSectionsUI();
  }

  function persistKey(key, value) {
    state[key] = value;
    if (value === '') {
      fetch(STATE_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ page: PAGE, entries: [{ key, value: '' }] }),
      }).catch(() => {});
      return;
    }
    queueSave(key, value);
  }

  function persistSections() {
    const idx = sectionLabels().map((label, i) => sectionsVisible.has(label) ? String(i) : '').filter(Boolean).join(',');
    persistKey(SECTIONS_KEY, idx);
  }

  let hiddenInsights = new Set();
  let hiddenFeatures = new Set();
  let hiddenDiagrams = new Set();
  let rerenderSeq = 0;
  const diagramCache = new Map();   // globalIdx -> block HTML (stays mounted across visits)
  const svgCache = new Map();       // globalIdx -> rendered canvas innerHTML
  const svgRendered = new Set();    // globalIdx -> mermaid already rendered once

  function persistInsights() {
    persistKey(INSIGHT_HIDDEN_KEY, [...hiddenInsights].sort((a, b) => a - b).join(','));
  }
  function persistFeatures() {
    persistKey(FEATURE_HIDDEN_KEY, [...hiddenFeatures].sort((a, b) => a - b).join(','));
  }
  function persistDiagrams() {
    persistKey(DIAGRAM_HIDDEN_KEY, [...hiddenDiagrams].sort((a, b) => a - b).join(','));
  }

  function applyInsightsFromValue(value) {
    const parts = String(value || '').split(',').map(s => s.trim()).filter(s => s !== '');
    hiddenInsights = new Set(parts.map(Number).filter(n => Number.isInteger(n) && n >= 0 && n < steps.length));
  }
  function applyFeaturesFromValue(value) {
    const parts = String(value || '').split(',').map(s => s.trim()).filter(s => s !== '');
    hiddenFeatures = new Set(parts.map(Number).filter(n => Number.isInteger(n) && n >= 0 && n < steps.length));
  }
  function applyDiagramsFromValue(value) {
    const parts = String(value || '').split(',').map(s => s.trim()).filter(s => s !== '');
    hiddenDiagrams = new Set(parts.map(Number).filter(n => Number.isInteger(n) && n >= 0 && n < steps.length));
  }

  function applyElementVisibility(globalIdx) {
    const isInsightHidden = hiddenInsights.has(globalIdx);
    const isFeatureHidden = hiddenFeatures.has(globalIdx);
    const isDiagramHidden = hiddenDiagrams.has(globalIdx);

    document.querySelectorAll('.pane-briefing .briefing-card-insight').forEach((el) => {
      el.classList.toggle('element-hidden', isInsightHidden);
    });
    document.querySelectorAll('.pane-briefing .briefing-card-feature').forEach((el) => {
      el.classList.toggle('element-hidden', isFeatureHidden);
    });
    document.querySelectorAll('.pane-briefing .briefing-flow-card').forEach((el) => {
      el.classList.toggle('element-hidden', isDiagramHidden);
    });

    document.querySelectorAll('.interview-flow .sequence-card').forEach((el) => {
      el.classList.toggle('dimmed', isDiagramHidden);
    });

    document.querySelectorAll('[data-element-toggle="insight"]').forEach((btn) => {
      const on = !isInsightHidden;
      btn.classList.toggle('on', on);
      btn.classList.toggle('off', !on);
      btn.setAttribute('aria-pressed', String(on));
    });
    document.querySelectorAll('[data-element-toggle="feature"]').forEach((btn) => {
      const on = !isFeatureHidden;
      btn.classList.toggle('on', on);
      btn.classList.toggle('off', !on);
      btn.setAttribute('aria-pressed', String(on));
    });
    document.querySelectorAll('[data-element-toggle="diagram"]').forEach((btn) => {
      const on = !isDiagramHidden;
      btn.classList.toggle('on', on);
      btn.classList.toggle('off', !on);
      btn.setAttribute('aria-pressed', String(on));
    });
  }

  function applySectionsFromValue(value) {
    const parts = String(value || '').split(',').map(s => s.trim()).filter(s => s !== '');
    const idx = new Set(parts.map(Number).filter(n => Number.isInteger(n) && n >= 0 && n < DATA.length));
    sectionsVisible = new Set(sectionLabels().filter((_, i) => idx.has(i)));
    syncSectionsUI();
  }

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.sec-toggle');
    if (!btn) return;
    const label = btn.dataset.section;
    const oldPool = visibleSteps();
    const currentEntry = oldPool[index] || null;

    if (sectionsVisible.has(label)) {
      sectionsVisible.delete(label);
    } else {
      sectionsVisible.add(label);
    }
    syncSectionsUI();
    persistSections();

    const newPool = visibleSteps();
    if (newPool.length === 0) {
      index = 0;
    } else if (currentEntry && newPool.includes(currentEntry)) {
      index = newPool.indexOf(currentEntry);
    } else {
      index = 0;
    }
    persistKey(NAV_KEY, String(index));
    render();
  });

  function emptySectionsMessage() {
    return '<div class="no-sections"><strong>No sections selected</strong><p>Choose the sections above (interviewer panel) to present to the client.</p></div>';
  }

  let index = 0;
  let state = {};
  let pending = new Map();
  let saveTimer = null;
  let syncTimer = null;
  let mode = localStorage.getItem('brief.mode') || 'split';

  const statusEl = document.getElementById('save-status');
  const dockStep = document.getElementById('dock-step');
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
  function setIndex(next) {
    const pool = visibleSteps();
    if (next < 0 || next >= pool.length || next === index || pool.length === 0) return;
    index = next;
    queueSave(NAV_KEY, String(index));
    render();
  }

  async function syncNavigation() {
    if (pending.has(NAV_KEY)) return;
    try {
      const res = await fetch(STATE_API + '?page=' + PAGE, { headers: { 'Accept': 'application/json' } });
      if (!res.ok) return;
      const data = await res.json();
      const remoteIndex = Number.parseInt(data.state?.[NAV_KEY] ?? '', 10);
      const remoteSections = data.state?.[SECTIONS_KEY];
      if (typeof remoteSections === 'string' && remoteSections !== state[SECTIONS_KEY] && !pending.has(SECTIONS_KEY)) {
        state[SECTIONS_KEY] = remoteSections;
        applySectionsFromValue(remoteSections);
      }
      const remoteDiagrams = data.state?.[DIAGRAM_HIDDEN_KEY];
      if (typeof remoteDiagrams === 'string' && remoteDiagrams !== state[DIAGRAM_HIDDEN_KEY] && !pending.has(DIAGRAM_HIDDEN_KEY)) {
        state[DIAGRAM_HIDDEN_KEY] = remoteDiagrams;
        applyDiagramsFromValue(remoteDiagrams);
      }
      const remoteInsights = data.state?.[INSIGHT_HIDDEN_KEY];
      if (typeof remoteInsights === 'string' && remoteInsights !== state[INSIGHT_HIDDEN_KEY] && !pending.has(INSIGHT_HIDDEN_KEY)) {
        state[INSIGHT_HIDDEN_KEY] = remoteInsights;
        applyInsightsFromValue(remoteInsights);
      }
      const remoteFeatures = data.state?.[FEATURE_HIDDEN_KEY];
      if (typeof remoteFeatures === 'string' && remoteFeatures !== state[FEATURE_HIDDEN_KEY] && !pending.has(FEATURE_HIDDEN_KEY)) {
        state[FEATURE_HIDDEN_KEY] = remoteFeatures;
        applyFeaturesFromValue(remoteFeatures);
      }
      const pool = visibleSteps();
      if (pool.length > 0 && index < pool.length) {
        const g = steps.indexOf(pool[index]);
        applyElementVisibility(g);
      }
      if (Number.isInteger(remoteIndex) && remoteIndex >= 0 && remoteIndex < visibleSteps().length && remoteIndex !== index) {
        state[NAV_KEY] = String(remoteIndex);
        index = remoteIndex;
        render();
      }
    } catch (error) {
      console.debug('brief navigation sync unavailable', error);
    }
  }

  function startNavigationSync() {
    if (syncTimer === null) syncTimer = setInterval(syncNavigation, 1000);
  }

  function pills(options, key, current) {
    return '<div class="pill-row">' + options.map(([value, label]) =>
      '<label class="pill' + (current === value ? ' checked' : '') + '"><input type="radio" name="' + key + '" value="' + value + '"' + (current === value ? ' checked' : '') + '> ' + label + '</label>'
    ).join('') + '</div>';
  }

  function render() {
    const pool = visibleSteps();
    if (pool.length === 0) {
      partChip.textContent = 'No sections';
      dockStep.textContent = 'Select sections to present';
      counter.textContent = '0 / 0';
      trackFill.style.transform = 'scaleX(0)';
      prevBtn.disabled = true;
      nextBtn.disabled = true;
      briefingEl.innerHTML = emptySectionsMessage();
      interviewEl.innerHTML = '<div class="no-sections"><strong>Interviewer console</strong><p>Use the eye buttons in this panel; the briefing pane shows only chosen sections.</p></div>';
      return;
    }
    if (index >= pool.length) index = 0;
    const entry = pool[index];
    const globalIdx = steps.indexOf(entry);
    const { section, step } = entry;
    const context = contextFor(section, step);
    const slotId = `brief-flow-slot-g${globalIdx}`;
    const diagramId = `brief-flow-diagram-g${globalIdx}`;
    partChip.textContent = section.label;
    dockStep.textContent = section.label + ' · ' + (step.no || step.title || 'Review');
    counter.textContent = (index + 1) + ' / ' + pool.length;
    trackFill.style.transform = 'scaleX(' + ((index + 1) / pool.length) + ')';
    prevBtn.disabled = index === 0;
    nextBtn.disabled = index === pool.length - 1;

    let b = '', i = '';
    if (step.type === 'info') {
      b = '<p class="step-no">' + step.no + '</p><h3>' + step.title + '</h3><div class="body">' + step.body + '</div>' +
        (step.why ? '<p class="flow"><span class="why"><b>Why it matters:</b> ' + step.why + '</span></p>' : '');
      const present = plainText(step.body) || (context.finding ? plainText(context.finding) : '');
      const ask = step.why && step.why.indexOf('Ask the client') === 0 ? plainText(step.why) : '';
      const say = context.script || `Ito ang na-record nating finding tungkol sa ${step.title || 'process'}. Tama ba ito sa actual na office process ninyo?`;
      i = '<p class="cue-label">Present this</p><p class="taglish">' + escapeHtml(present || (context.finding || '')) + '</p>' +
        (ask ? '<p class="prompt">' + escapeHtml(ask) + '</p>' : '<p class="muted-note">Walk the client through this finding. No decision here; corrections go in the confirmation step.</p>') +
        '<p class="cue-label">Say this</p><p class="taglish">' + escapeHtml(say) + '</p>';
    } else if (step.type === 'choice') {
      b = '<p class="step-no">Part 1 · confirmation</p><h3>' + step.title + '</h3><div class="body">' + step.prompt + '</div>';
      i = '<p class="prompt">' + step.prompt + '</p>' + pills(step.options, step.key, state[step.key] || '');
    } else if (step.type === 'decision') {
      const opts = step.options || DECISIONS;
      b = '<p class="step-no">' + (step.no || '') + (step.user ? ' · ' + step.user : '') + '</p><h3>' + (step.title || step.no || 'Decision') + '</h3><div class="flow">' +
        '<div class="from"><b>Current:</b> ' + step.from + '</div>' +
        '<div class="to"><b>Proposed:</b> ' + step.to + '</div>' +
        (step.reason ? '<span class="why"><b>Why:</b> ' + step.reason + '</span>' : '') + '</div>';
      const say = context.script || `${step.no || 'Row'} po: current natin, ${plainText(step.from)} Proposed: ${plainText(step.to)} Agree, modify, keep current, or defer?`;
      i = '<p class="cue-label">Say this</p><p class="taglish">' + escapeHtml(say) + '</p>' +
        pills(opts, step.key + '.decision', state[step.key + '.decision'] || '') +
        '<input class="textfield" data-state-key="' + step.key + '.remarks" placeholder="' + (step.remarksLabel || 'Client remarks (optional)') + '" value="' + escapeHtml(state[step.key + '.remarks'] || '') + '">';
    } else if (step.type === 'signoff') {
      b = '<p class="step-no">Summary sign-off</p><h3>' + step.title + '</h3><div class="body">What final direction does the client give for this decision area?</div>';
      const saySig = context.script || `${step.title}: ano po ang final direction? Agree, modify, or defer?`;
      i = '<p class="cue-label">Say this</p><p class="taglish">' + escapeHtml(saySig) + '</p>' +
        pills(SIGNOFF, step.key + '.direction', state[step.key + '.direction'] || '') +
        '<input class="textfield" data-state-key="' + step.key + '.remarks" placeholder="Client remarks / exact wording" value="' + escapeHtml(state[step.key + '.remarks'] || '') + '">';
    } else if (step.type === 'text') {
      b = '<p class="step-no">' + section.label + '</p><h3>' + step.title + '</h3><div class="body">' + step.prompt + '</div>';
      const sayText = context.script || `${step.title}: ${step.prompt}`;
      i = '<p class="cue-label">Say this</p><p class="taglish">' + escapeHtml(sayText) + '</p>' +
        '<p class="prompt">' + step.prompt + '</p><textarea class="textfield" rows="4" data-state-key="' + step.key + '" placeholder="Answer / notes…">' + escapeHtml(state[step.key] || '') + '</textarea>';
    } else if (step.type === 'partdone') {
      const done = state[step.taskKey] === '1';
      b = '<h3>' + step.title + '</h3><div class="body">When every question in this part is answered, mark the part done.</div>';
      i = '<div class="partdone"><button type="button" id="part-done-btn" class="' + (done ? 'done' : '') + '">' + (done ? 'Part marked done ✓' : 'Mark this part done') + '</button></div>';
    }

    let ivSlotId = context.sequence ? `iv-flow-slot-g${globalIdx}` : null;
    let ivDiagramId = context.sequence ? `iv-flow-diagram-g${globalIdx}` : null;
    if (context.sequence) {
      const cacheKey = 'g' + globalIdx;
      if (diagramCache.has(cacheKey + '-b')) {
        b += diagramCache.get(cacheKey + '-b');
        i += diagramCache.get(cacheKey + '-i');
      } else {
        const refB = renderBriefingReference(context, slotId, step);
        const refI = renderInterviewFlow(context, ivSlotId, step);
        b += refB;
        i += refI;
        diagramCache.set(cacheKey + '-b', refB);
        diagramCache.set(cacheKey + '-i', refI);
      }
    }
    briefingEl.innerHTML = b;
    interviewEl.innerHTML = i;
    if (context.sequence) {
      const briefingSlot = document.getElementById(slotId);
      const interviewSlot = document.getElementById(ivSlotId);
      if (briefingSlot) briefingSlot.dataset.globalIdx = String(globalIdx);
      if (interviewSlot) interviewSlot.dataset.globalIdx = String(globalIdx);
      if (!svgRendered.has(globalIdx)) {
        renderMermaid(briefingSlot, context.sequence, diagramId);
        renderMermaid(interviewSlot, context.sequence, ivDiagramId);
        svgRendered.add(globalIdx);
      } else {
        const briefSvg = svgCache.get(globalIdx + '-b');
        const ivSvg = svgCache.get(globalIdx + '-i');
        if (briefSvg && briefingSlot) {
          briefingSlot.querySelector('.sequence-canvas').innerHTML = briefSvg;
          const loading = briefingSlot.querySelector('.sequence-loading');
          if (loading) loading.remove();
        } else if (briefingSlot) {
          renderMermaid(briefingSlot, context.sequence, diagramId + '-r' + (++rerenderSeq));
        }
        if (ivSvg && interviewSlot) {
          interviewSlot.querySelector('.sequence-canvas').innerHTML = ivSvg;
          const loading = interviewSlot.querySelector('.sequence-loading');
          if (loading) loading.remove();
        } else if (interviewSlot) {
          renderMermaid(interviewSlot, context.sequence, ivDiagramId + '-r' + (++rerenderSeq));
        }
      }
      applyElementVisibility(globalIdx);
    }
  }

  function go(delta) {
    const next = index + delta;
    if (next < 0 || next >= visibleSteps().length) return;
    const t = document.activeElement;
    if (t && (t.tagName === 'TEXTAREA' || t.tagName === 'INPUT' || t.tagName === 'SELECT')) return;
    setIndex(next);
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
    const elemBtn = e.target.closest('[data-element-toggle]');
    if (elemBtn) {
      const pool = visibleSteps();
      if (pool.length === 0) return;
      const globalIdx = steps.indexOf(pool[index]);
      if (globalIdx < 0) return;
      const type = elemBtn.dataset.elementToggle;
      if (type === 'insight') {
        if (hiddenInsights.has(globalIdx)) hiddenInsights.delete(globalIdx); else hiddenInsights.add(globalIdx);
        persistInsights();
      } else if (type === 'feature') {
        if (hiddenFeatures.has(globalIdx)) hiddenFeatures.delete(globalIdx); else hiddenFeatures.add(globalIdx);
        persistFeatures();
      } else if (type === 'diagram') {
        if (hiddenDiagrams.has(globalIdx)) hiddenDiagrams.delete(globalIdx); else hiddenDiagrams.add(globalIdx);
        persistDiagrams();
      }
      applyElementVisibility(globalIdx);
      return;
    }

    const diagramControl = e.target.closest('[data-diagram-action]');
    if (diagramControl) {
      const id = diagramControl.dataset.diagramId;
      const action = diagramControl.dataset.diagramAction;
      if (action === 'fullscreen') {
        toggleDiagramFullscreen(id);
        return;
      }
      const slot = document.getElementById(id);
      const current = Number(slot?.dataset.scale || 1);
      setDiagramScale(id, action === 'zoom-in' ? current + .25 : action === 'zoom-out' ? current - .25 : 1);
      return;
    }
    if (e.target.id === 'part-done-btn') {
      const current = visibleSteps()[index];
      const step = current ? current.step : null;
      queueSave(step.taskKey, '1');
      e.target.classList.add('done');
      e.target.textContent = 'Part marked done ✓';
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') go(-1);
    if (e.key === 'ArrowRight') go(1);
  });

  prevBtn.addEventListener('click', () => setIndex(index - 1));
  nextBtn.addEventListener('click', () => setIndex(index + 1));

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
    renderSectionsStrip();
    try {
      const res = await fetch(STATE_API + '?page=' + PAGE, { headers: { 'Accept': 'application/json' } });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const data = await res.json();
      state = data.state || {};
      applySectionsFromValue(state[SECTIONS_KEY]);
      applyDiagramsFromValue(state[DIAGRAM_HIDDEN_KEY]);
      applyInsightsFromValue(state[INSIGHT_HIDDEN_KEY]);
      applyFeaturesFromValue(state[FEATURE_HIDDEN_KEY]);
      applyInsightsFromValue(state[INSIGHT_HIDDEN_KEY]);
      applyFeaturesFromValue(state[FEATURE_HIDDEN_KEY]);
      const savedIndex = Number.parseInt(state[NAV_KEY] ?? '', 10);
      if (Number.isInteger(savedIndex) && savedIndex >= 0 && savedIndex < visibleSteps().length) index = savedIndex;
      setStatus('Saved state loaded', 'ok');
      render();
      startNavigationSync();
    } catch (e) {
      console.error('docs load failed', e);
      setStatus('Could not load saved state', 'err');
      render();
      startNavigationSync();
    }
  }

  load();
</script>
</body>
</html>
