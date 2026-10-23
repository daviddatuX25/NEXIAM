<script>
  import { onMount } from 'svelte';
  import { Button } from '@/Components/ui/button';
  import PracticePreparationPanel from './PracticePreparationPanel.svelte';
  import { safeMarkdownHtml } from '@/lib/safeMarkdownHtml';
  import { router } from '@inertiajs/svelte';
  import { Printer, ExternalLink, Users, MessageSquare, ClipboardCheck, Calendar, GraduationCap, Eye } from 'lucide-svelte';

  let { groups = [], selected_role = null, facilitator = {}, source_version = null, can_control = false } = $props();
  let preparationOpen = $state(false);
  let preparationBusy = $state(false);
  let preparationPurpose = $state('research');
  const preparationScenario = $derived(['student', 'registrar', 'guidance', 'academic-leader'].includes(selected_role) ? selected_role : 'student');

  const roleGroups = $derived(Array.isArray(groups) ? groups : []);
  const guideRoles = $derived(Array.isArray(facilitator?.roles) ? facilitator.roles : []);
  const taskRows = $derived(guideRoles.flatMap((role) => (Array.isArray(role.tasks) ? role.tasks : []).map((task) => ({ role, task }))));
  const caseTrace = $derived(Array.isArray(facilitator?.case_trace) ? facilitator.case_trace : []);
  const preparationHtml = $derived(safeMarkdownHtml(String(facilitator?.preparation_markdown ?? '')));
  const briefingHtml = $derived(safeMarkdownHtml(String(facilitator?.standard_briefing ?? '')));
  const observationHtml = $derived(safeMarkdownHtml(String(facilitator?.observation_markdown ?? '')));
  const rulesHtml = $derived(safeMarkdownHtml(String(facilitator?.rules_markdown ?? '')));

  let diagramSvg = $state('');
  let diagramLoading = $state(true);
  let diagramMounted = false;
  let diagramRenderPromise = null;
  let diagramError = $state('');
  let mermaidPromise = null;
  let showSwimlaneDiagram = $state(false);
  let showRawDiagram = $state(false);

  const activityDiagramCode = `sequenceDiagram
    autonumber
    actor Pres as Presenter A
    actor Staff as Staff B
    actor Pilot as Pilot C
    actor Cohort as Student cohort

    Note over Pres,Cohort: Phase 1 - Intake and review - 00:00 to 03:30
    Pres->>Cohort: Rehearsal only - use fictional details on /apply
    par Normal application
      Pilot->>Pilot: Submit fictional details and save Access Key
    and Cohort mirrors the pilot
      Cohort->>Cohort: Research participants use assigned synthetic accounts instead
    end
    Staff->>Staff: Review applications in bounded batches
    opt Correction example after the normal path
      Staff->>Pilot: Send correction notice on a separate case
      Pilot->>Staff: Correct application information and resubmit
      Staff->>Pilot: Review the corrected application
    end

    Note over Pres,Cohort: Phase 2 - Portal and messages - 03:30 to 06:30
    Pres->>Cohort: Sign in and read your actual status and checklist
    par Pilot inquiry
      Pilot->>Staff: Send an Admissions inquiry from the portal
      Staff-->>Pilot: Reply through the assigned office inbox
    and Cohort explores
      Cohort->>Cohort: Read notices and try Messages
    end

    Note over Pres,Cohort: Phase 3 - Scheduling and Compass - 06:30 to 10:00
    Staff->>Staff: Distribute eligible applicants and retain self-booking slots
    par Normal assigned schedule
      Cohort->>Cohort: Check own session and Find Venue
    and Self-booking example
      Pilot->>Pilot: Book an eligible open slot and check Find Venue
    end
    Staff->>Staff: Publish eligible sessions after reviewing rosters

    Note over Pres,Cohort: Phase 4 - Exam monitoring - 10:00 to 13:00
    Staff->>Staff: Review relative timing for an owned practice session
    Pres->>Cohort: Read the permit and follow the approved exam briefing
    Pilot->>Staff: Show permit for attendance check-in
    par Early-arrival example
      Pilot->>Staff: Show another-day schedule and request accommodation
      Staff-->>Pilot: Confirm permitted accommodation then refresh schedule
    and Cohort remains engaged
      Cohort->>Cohort: Check own permit or explore Campus Compass
    end
    Staff->>Staff: Reconcile attendance and close through the normal workflow

    Note over Pres,Cohort: Phase 5 - Scoring - 13:00 to 15:00
    Pres->>Cohort: Check the portal while scoring is in progress
    Staff->>Staff: Demonstrate ordinary scoring on the spotlight case
    Note right of Staff: No implemented score-all runner is established here

    Note over Pres,Cohort: Phase 6 - Release and advising - 15:00 to 17:00
    Staff->>Staff: Demonstrate release scheduling and the print workflow
    Pilot->>Pilot: Check assigned release slot or permitted self-booking
    Cohort->>Cohort: Check own release information
    Staff->>Pilot: Discuss career information during the advising encounter
    alt Direct endorsement
      Staff->>Pilot: Record the permitted advising decision
    else Academic review
      Staff->>Staff: Refer a separate case and exchange paired staff messages
    end

    Note over Pres,Cohort: Phase 7 - Clearance and survey - 17:00 to 20:00
    Staff->>Staff: Verify physical documents and record clearance and sheet issue
    Pres->>Cohort: Open the approved survey link in Meet chat
    Cohort->>Cohort: Complete the questionnaire independently`;

  async function ensureMermaid() {
    if (!mermaidPromise) {
      mermaidPromise = import('mermaid').then(({ default: mermaid }) => {
        mermaid.initialize({
          startOnLoad: false,
          suppressErrorRendering: true,
          securityLevel: 'strict',
          theme: 'dark',
          fontFamily: 'ui-sans-serif, system-ui, sans-serif',
          themeVariables: {
            darkMode: true,
            background: '#020617',
            primaryColor: '#1e293b',
            primaryTextColor: '#f8fafc',
            primaryBorderColor: '#38bdf8',
            lineColor: '#64748b',
          },
        });
        return mermaid;
      }).catch((error) => {
        mermaidPromise = null;
        throw error;
      });
    }
    return mermaidPromise;
  }

  function renderActivityDiagram() {
    if (diagramRenderPromise) return diagramRenderPromise;
    diagramLoading = true;
    diagramError = '';
    diagramRenderPromise = (async () => {
      const host = document.createElement('div');
      host.style.cssText = 'position:fixed;left:-10000px;top:0;width:1400px;visibility:hidden;pointer-events:none';
      host.setAttribute('aria-hidden', 'true');
      document.body.append(host);
      try {
        const mermaid = await ensureMermaid();
        if (!diagramMounted) return;
        const id = 'mermaid-activity-' + Math.random().toString(36).slice(2, 9);
        const { svg } = await mermaid.render(id, activityDiagramCode, host);
        if (diagramMounted) diagramSvg = svg;
      } catch (error) {
        if (diagramMounted) diagramError = String(error?.message || error);
      } finally {
        host.remove();
        if (diagramMounted) diagramLoading = false;
        diagramRenderPromise = null;
      }
    })();
    return diagramRenderPromise;
  }

  onMount(() => {
    diagramMounted = true;
    void renderActivityDiagram();
    return () => { diagramMounted = false; };
  });

  function changeFilter(event) {
    const role = event.currentTarget.value;
    if (role && !roleGroups.some((group) => group.key === role)) return;
    router.get('/demo/research/facilitator', role ? { role } : {}, { preserveState: false, preserveScroll: true });
  }

  function printReference() {
    window.print();
  }
</script>

<svelte:head>
  <title>Research facilitator reference · NEXIAM</title>
  <meta name="description" content="Source-bound facilitator preparation, observation, and case trace reference." />
</svelte:head>

<div class="min-h-screen bg-slate-950 text-slate-100">
  <main class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-7 sm:py-10 lg:px-10">
    <header class="no-print flex flex-col gap-5 border-b border-slate-800 pb-6 sm:flex-row sm:items-start sm:justify-between">
      <div class="min-w-0">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-amber-200">Facilitator-only reference</p>
        <h1 class="mt-2 break-words text-3xl font-semibold tracking-tight text-white sm:text-4xl">Research session preparation</h1>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-300">Source-bound facilitator material and current use-case trace. This is a reference, not a session tracker; no observation or participant information is entered here.</p>
      </div>
      <Button type="button" variant="outline" class="min-h-11 shrink-0 border-slate-700 bg-slate-900 text-slate-100 hover:bg-slate-800" onclick={printReference}><Printer class="mr-2 size-4" aria-hidden="true" /> Print reference</Button>
    </header>

    <div class="no-print mt-5 max-w-sm">
      <label for="facilitator-role-filter" class="text-sm font-semibold text-slate-200">Role filter <span class="font-normal text-slate-400">(display only)</span></label>
      <select id="facilitator-role-filter" value={selected_role ?? ''} onchange={changeFilter} disabled={preparationBusy} class="mt-2 flex min-h-11 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400">
        <option value="">All roles</option>
        {#each roleGroups as group (group.key)}
          <option value={group.key}>{group.label}</option>
        {/each}
      </select>
    </div>

    <!-- Sticky Teammate B Live Operator Control Bar -->
    <section class="no-print sticky top-0 z-30 mt-4 rounded-xl border border-slate-700 bg-slate-900/95 p-3.5 shadow-2xl backdrop-blur-md">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2">
          <span class="flex size-2 rounded-full bg-emerald-400"></span>
          <p class="text-xs font-semibold uppercase tracking-wider text-blue-300">
            Teammate B Operator Bar
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <Button type="button" variant="outline" size="sm" aria-expanded={showSwimlaneDiagram} aria-controls="facilitator-swimlane" class="border-amber-700/70 bg-amber-950/40 text-xs text-amber-200 hover:bg-amber-900/50" onclick={() => { showSwimlaneDiagram = !showSwimlaneDiagram; }}>
            <Calendar class="mr-1.5 size-3.5" /> {showSwimlaneDiagram ? 'Hide Swimlane Diagram' : 'View Swimlane Diagram'}
          </Button>
          {#if can_control}
            <Button type="button" variant="outline" size="sm" aria-expanded={preparationOpen} aria-controls="research-practice-panel" disabled={preparationBusy} onclick={() => { preparationOpen = !preparationOpen; }}>
              {preparationOpen ? 'Close practice preparation' : 'Prepare research practice'}
            </Button>
          {/if}
          {#if !selected_role || selected_role === 'student'}
            <a href="/staff/inquiries" target="_blank" rel="noreferrer" class="inline-flex min-h-8 items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 px-2.5 py-1 text-xs font-medium text-slate-200 hover:bg-slate-700 hover:text-white">
              <MessageSquare class="size-3 text-blue-400" /> Inquiries <ExternalLink class="size-2.5 text-slate-400" />
            </a>
          {/if}
          {#if !selected_role || selected_role === 'registrar'}
            <a href="/admissions" target="_blank" rel="noreferrer" class="inline-flex min-h-8 items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 px-2.5 py-1 text-xs font-medium text-slate-200 hover:bg-slate-700 hover:text-white">
              <Users class="size-3 text-emerald-400" /> Admissions Queue <ExternalLink class="size-2.5 text-slate-400" />
            </a>
            <a href="/registrar/enrollment-eligibility" target="_blank" rel="noreferrer" class="inline-flex min-h-8 items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 px-2.5 py-1 text-xs font-medium text-slate-200 hover:bg-slate-700 hover:text-white">
              <ClipboardCheck class="size-3 text-emerald-400" /> Clearance Desk <ExternalLink class="size-2.5 text-slate-400" />
            </a>
          {/if}
          {#if !selected_role || selected_role === 'guidance'}
            <a href="/exams/sessions" target="_blank" rel="noreferrer" class="inline-flex min-h-8 items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 px-2.5 py-1 text-xs font-medium text-slate-200 hover:bg-slate-700 hover:text-white">
              <Calendar class="size-3 text-amber-400" /> Scheduling <ExternalLink class="size-2.5 text-slate-400" />
            </a>
            <a href="/exams/monitoring" target="_blank" rel="noreferrer" class="inline-flex min-h-8 items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 px-2.5 py-1 text-xs font-medium text-slate-200 hover:bg-slate-700 hover:text-white">
              <ClipboardCheck class="size-3 text-amber-400" /> Test Administrator Station <ExternalLink class="size-2.5 text-slate-400" />
            </a>
            <a href="/scoring" target="_blank" rel="noreferrer" class="inline-flex min-h-8 items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 px-2.5 py-1 text-xs font-medium text-slate-200 hover:bg-slate-700 hover:text-white">
              <GraduationCap class="size-3 text-amber-400" /> Scoring <ExternalLink class="size-2.5 text-slate-400" />
            </a>
            <a href="/release" target="_blank" rel="noreferrer" class="inline-flex min-h-8 items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 px-2.5 py-1 text-xs font-medium text-slate-200 hover:bg-slate-700 hover:text-white">
              <Printer class="size-3 text-amber-400" /> Release <ExternalLink class="size-2.5 text-slate-400" />
            </a>
          {/if}
          {#if !selected_role || selected_role === 'academic-leader'}
            <a href="/staff/programs/BSIT" target="_blank" rel="noreferrer" class="inline-flex min-h-8 items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 px-2.5 py-1 text-xs font-medium text-slate-200 hover:bg-slate-700 hover:text-white">
              <GraduationCap class="size-3 text-purple-400" /> BSIT Desk <ExternalLink class="size-2.5 text-slate-400" />
            </a>
          {/if}
        </div>
      </div>
    </section>
    {#if can_control}
      <section id="research-practice-panel" class="no-print mt-5 border-y border-slate-800 py-4" aria-label="Researcher practice preparation" hidden={!preparationOpen}>
        <label class="text-sm font-semibold" for="facilitator-preparation-purpose">Preparation context</label>
        <select id="facilitator-preparation-purpose" bind:value={preparationPurpose} disabled={preparationBusy} class="mt-2 min-h-11 w-full rounded-lg border border-slate-700 bg-slate-900 px-3 text-sm sm:max-w-md">
          <option value="research">Research task starting cases</option>
          <option value="demo">Demo rehearsal stages</option>
        </select>
        <p class="mt-3 max-w-prose text-sm leading-6 text-slate-300">Use the selected set’s Assigned accounts and access checks for real generated credentials. Stage preparation creates a separate starting fixture; it does not advance everyone currently on the call.</p>
        <div class="mt-4">
          <PracticePreparationPanel purpose={preparationPurpose} scenario={preparationPurpose === 'research' ? preparationScenario : 'exam-scheduling-attendance'} active={preparationOpen} {can_control} onbusychange={(busy) => { preparationBusy = busy; }} />
        </div>
      </section>
    {/if}

    <details class="no-print mt-5 border-b border-slate-800 pb-4" aria-label="Verified demo control coverage">
      <summary class="min-h-11 cursor-pointer py-3 text-base font-semibold text-white">Available operator controls and gaps</summary>
      <p class="mt-2 max-w-prose text-sm leading-6 text-slate-300">Student is one research starting scenario, not the entire demo pipeline. Open practice preparation above and choose Demo rehearsal stages for scheduling/attendance, scoring/results, consultation/referral, or enrollment starting cases.</p>
      <dl class="mt-3 space-y-3 text-sm leading-6">
        <div><dt class="font-semibold text-white">Relative-now exam timing</dt><dd class="text-slate-300">Select an existing owned set, then Session timing and reminders. Presets: +72h, +24h, +2h, +1h, +45m, door check-in (+20m), live (−30m), ended (−155m). Review and confirm one session at a time. Timing is relative to preview time; it does not publish, start, close, send reminders, or change the system clock.</dd></div>
        <div><dt class="font-semibold text-white">Scoring</dt><dd class="text-slate-300">Scoring/results preparation creates new starting cases. It does not score an existing cohort. Use the selected case’s actual scoring handoff for ordinary scoring and separate finalization.</dd></div>
        <div><dt class="font-semibold text-amber-200">Missing orchestration contract</dt><dd class="text-slate-300">A custom +2-minute preset, convert-all-to-monitoring, and score-all-on-the-call runner are not implemented controls. Do not substitute seeding for those operations. No 280-account cohort, automatic career-model output, or fixed execution-time guarantee is established by this page.</dd></div>
      </dl>
    </details>

    {#if showSwimlaneDiagram}
      <!-- Collapsible Live Rendered Mermaid Sequence / Swimlane Diagram -->
      <section id="facilitator-swimlane" class="no-print mt-4 rounded-xl border border-amber-800/80 bg-slate-900/95 p-4 sm:p-5">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-800 pb-3">
          <div>
            <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-amber-300">
              <Calendar class="size-3.5" aria-hidden="true" /> 4-Lane Swimlane Activity Diagram
            </p>
            <h2 class="text-base font-semibold text-white">Full-Through Coordination Sequence</h2>
          </div>
          <div class="flex items-center gap-2">
            <Button type="button" variant="outline" size="sm" class="h-8 border-slate-700 bg-slate-800 text-xs text-slate-300 hover:bg-slate-700 hover:text-white" onclick={() => { showRawDiagram = !showRawDiagram; }}>
              <Eye class="mr-1 size-3" /> {showRawDiagram ? 'Hide Code' : 'View Code'}
            </Button>
            <Button type="button" variant="ghost" size="sm" class="h-8 text-xs text-slate-400 hover:text-white" onclick={() => { showSwimlaneDiagram = false; }}>
              Close
            </Button>
          </div>
        </div>

        {#if showRawDiagram}
          <div class="mt-3 rounded-lg border border-slate-800 bg-slate-950 p-3 font-mono text-xs text-slate-300 overflow-x-auto max-h-48">
            <pre><code>{activityDiagramCode}</code></pre>
          </div>
        {/if}

        {#if diagramError}
          <div role="alert" class="mt-3 rounded-lg border border-amber-800 bg-amber-950/40 p-3 text-xs text-amber-200">
            <p class="font-semibold">Diagram could not render: {diagramError}</p>
            <Button type="button" variant="outline" size="sm" onclick={renderActivityDiagram}>Retry diagram</Button>
          </div>
        {/if}

        <p class="mt-3 text-sm text-slate-300">Presentation timing, not a live progress tracker. Operator availability and current prerequisites are listed below.</p>
        <div class="mt-3 max-h-[65dvh] overflow-auto rounded-lg border border-slate-800 bg-slate-950/90 p-3" role="region" aria-label="Scrollable coordination diagram" tabindex="0">
          {#if diagramSvg}
            <div class="min-w-[1400px] [&_svg]:h-auto [&_svg]:w-full [&_svg]:max-w-none">{@html diagramSvg}</div>
          {:else if diagramLoading}
            <p role="status" class="py-6 text-sm text-slate-300">Preparing the coordination diagram…</p>
          {/if}
        </div>
      </section>
    {/if}



    <!-- Facilitator Spoken Read-Through Script Card -->
    <section class="no-print mt-6 rounded-xl border border-slate-800 bg-slate-900/70 p-4 sm:p-5">
      <div class="border-b border-slate-800 pb-3">
        <h2 class="text-base font-semibold text-white">Presenter Spoken Script (Teammate A on Mic)</h2>
        <p class="text-sm text-slate-300">Optional full-demo rehearsal pacing, not the neutral research task briefing. Use fictional data only; research participants start with assigned synthetic accounts and receive no task coaching.</p>
      </div>
      <div class="mt-3 space-y-2.5 text-xs leading-relaxed text-slate-300">
        <div class="rounded-lg border border-blue-900/60 bg-blue-950/25 p-3">
          <div class="flex items-center justify-between font-semibold text-blue-300">
            <span>Phase 1: Welcome & Live Intake</span>
            <span class="font-mono text-[11px] text-slate-400">00:00 – 03:30</span>
          </div>
          <p class="mt-1 text-slate-200">"For this rehearsal, follow the pilot on /apply using fictional details only. Save your Access Key. Research participants use their assigned synthetic accounts instead."</p>
        </div>
        <div class="rounded-lg border border-emerald-900/60 bg-emerald-950/25 p-3">
          <div class="flex items-center justify-between font-semibold text-emerald-300">
            <span>Phase 2: Portal Sign-In & Admissions Messaging</span>
            <span class="font-mono text-[11px] text-slate-400">03:30 – 06:30</span>
          </div>
          <p class="mt-1 text-slate-200">"Sign in with your Access Key and birthdate. Read your actual status and checklist, then try sending Admissions a question."</p>
        </div>
        <div class="rounded-lg border border-amber-900/60 bg-amber-950/25 p-3">
          <div class="flex items-center justify-between font-semibold text-amber-300">
            <span>Phase 3: Scheduling & Campus Compass Directions</span>
            <span class="font-mono text-[11px] text-slate-400">06:30 – 10:00</span>
          </div>
          <p class="mt-1 text-slate-200">"Check Schedule and tap Find Venue. Most students use assigned slots; our pilot will also show self-booking where a slot is open."</p>
        </div>
        <div class="rounded-lg border border-purple-900/60 bg-purple-950/25 p-3">
          <div class="flex items-center justify-between font-semibold text-purple-300">
            <span>Phase 4: Exam Monitoring & Accommodation</span>
            <span class="font-mono text-[11px] text-slate-400">10:00 – 13:00</span>
          </div>
          <p class="mt-1 text-slate-200">"Have your permit ready. Watch the normal check-in first, then an early-arrival accommodation. Meanwhile, check your own permit or explore Find Venue."</p>
        </div>
        <div class="rounded-lg border border-cyan-900/60 bg-cyan-950/25 p-3">
          <div class="flex items-center justify-between font-semibold text-cyan-300">
            <span>Phase 5: Scoring</span>
            <span class="font-mono text-[11px] text-slate-400">13:00 – 15:00</span>
          </div>
          <p class="mt-1 text-slate-200">"After the exam, check your portal while scoring progresses. We’ll demonstrate a score card; the operator will handle scoring separately."</p>
        </div>
        <div class="rounded-lg border border-cyan-900/60 bg-cyan-950/25 p-3">
          <div class="flex items-center justify-between font-semibold text-cyan-300">
            <span>Phase 6: Release & Advising</span>
            <span class="font-mono text-[11px] text-slate-400">15:00 – 17:00</span>
          </div>
          <p class="mt-1 text-slate-200">"Check your release schedule. Career information is discussed during counseling; we’ll show direct endorsement, then a separate academic-review case."</p>
        </div>
        <div class="rounded-lg border border-rose-900/60 bg-rose-950/25 p-3">
          <div class="flex items-center justify-between font-semibold text-rose-300">
            <span>Phase 7: Clearance & TAM Survey</span>
            <span class="font-mono text-[11px] text-slate-400">17:00 – 20:00</span>
          </div>
          <p class="mt-1 text-slate-200">"Clearance requires document verification. Please open the approved survey link in Meet chat and rate your own experience independently."</p>
        </div>
      </div>
    </section>


    <div class="print-reference">
      <section class="mt-7 grid gap-4 lg:grid-cols-2" aria-label="Preparation and briefing">
        <article class="guide-facilitator-markdown min-w-0 rounded-xl border border-slate-800 bg-slate-900/70 p-4 sm:p-6">
          <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-blue-200">Preparation required</p>
          {@html preparationHtml}
        </article>
        <article class="guide-facilitator-markdown min-w-0 rounded-xl border border-slate-800 bg-slate-900/70 p-4 sm:p-6">
          <h2 class="text-lg font-semibold text-white">Standard briefing</h2>
          {@html briefingHtml}
        </article>
      </section>

      <section class="mt-8" aria-labelledby="role-task-heading">
        <h2 id="role-task-heading" class="text-xl font-semibold text-white">Role and task reference</h2>
        {#if selected_role}
          <p class="mt-1 text-sm text-slate-400">Showing {roleGroups.find((group) => group.key === selected_role)?.label ?? selected_role}. The filter changes what is displayed; it does not grant access.</p>
        {:else}
          <p class="mt-1 text-sm text-slate-400">All roles are shown. Participant wording and task sequence are reproduced from the published source.</p>
        {/if}
        <div class="mt-4 grid gap-3 md:grid-cols-2">
          {#each guideRoles.filter((role) => !selected_role || role.key === selected_role) as role (role.key)}
            <article class="rounded-xl border border-slate-800 bg-slate-900/70 p-4">
              <h3 class="text-base font-semibold text-white">{role.label}</h3>
              <p class="mt-1 text-xs font-medium text-slate-400">{role.scenario}</p>
              <dl class="mt-4 space-y-3 text-sm">
                <div><dt class="font-semibold text-blue-200">Required context</dt><dd class="mt-1 whitespace-pre-wrap break-words leading-6 text-slate-300">{role.context}</dd></div>
                <div><dt class="font-semibold text-blue-200">Role briefing</dt><dd class="mt-1 whitespace-pre-wrap break-words leading-6 text-slate-300">{role.briefing}</dd></div>
                <div><dt class="font-semibold text-amber-200">Restrictions</dt><dd class="mt-1 whitespace-pre-wrap break-words leading-6 text-slate-300">{role.restrictions}</dd></div>
              </dl>
            </article>
          {/each}
        </div>
        <div class="mt-4 rounded-xl border border-border/80 bg-card/60 backdrop-blur-md overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] border-collapse text-left text-sm">
              <thead class="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
                <tr><th scope="col" class="px-4 py-3">Role / scenario</th><th scope="col" class="px-4 py-3">Task</th><th scope="col" class="px-4 py-3">Source item mapping</th></tr>
              </thead>
              <tbody class="divide-y divide-border/60 bg-transparent">
                {#each taskRows.filter(({ role }) => !selected_role || role.key === selected_role) as row (`${row.role.key}-${row.task.id}`)}
                  <tr class="align-top">
                    <td class="px-4 py-3"><p class="font-semibold text-slate-100">{row.role.label}</p><p class="mt-1 text-xs text-slate-400">{row.role.scenario}</p></td>
                    <td class="max-w-xl whitespace-pre-wrap break-words px-4 py-3"><span class="font-semibold text-blue-200">{row.task.id}</span><p class="mt-1 leading-6 text-slate-200">{row.task.text}</p></td>
                    <td class="px-4 py-3"><div class="flex flex-wrap gap-1.5">{#if row.task.item_ids?.length}{#each row.task.item_ids as item (item)}<code class="rounded border border-slate-700 bg-slate-900 px-2 py-1 text-xs text-slate-200">{item}</code>{/each}{:else}<span class="text-slate-400">No item IDs published</span>{/if}</div></td>
                  </tr>
                {:else}
                  <tr><td colspan="3" class="px-4 py-6 text-sm text-slate-400">No role/task mapping is published for this selection.</td></tr>
                {/each}
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section class="mt-8" aria-labelledby="observation-heading">
        <h2 id="observation-heading" class="text-xl font-semibold text-white">Observation reference</h2>
        <p class="mt-1 text-sm leading-6 text-slate-400">Blank, read-only reference only. No fields below accept or retain observations.</p>
        <article class="guide-facilitator-markdown mt-4 rounded-xl border border-slate-800 bg-slate-900/70 p-4 sm:p-6">{@html observationHtml}</article>
      </section>

      <section class="mt-8" aria-labelledby="rules-heading">
        <h2 id="rules-heading" class="text-xl font-semibold text-white">Facilitation rules</h2>
        <article class="guide-facilitator-markdown mt-4 rounded-xl border border-slate-800 bg-slate-900/70 p-4 sm:p-6">{@html rulesHtml}</article>
      </section>

      <section class="mt-8" aria-labelledby="paired-exchange-heading">
        <h2 id="paired-exchange-heading" class="text-xl font-semibold text-white">Paired exchange</h2>
        <article class="mt-4 rounded-xl border border-slate-800 bg-slate-900/70 p-4 sm:p-6">
          <p class="text-sm font-semibold text-slate-100">Tasks {(facilitator?.paired_exchange?.tasks ?? []).join(' and ')}</p>
          <p class="mt-2 whitespace-pre-wrap break-words text-sm leading-6 text-slate-300">{facilitator?.paired_exchange?.note ?? 'Paired-exchange guidance is not included in the published reference.'}</p>
        </article>
      </section>

      <section class="mt-8" aria-labelledby="trace-heading">
        <h2 id="trace-heading" class="text-xl font-semibold text-white">Current use-case trace and limitations</h2>
        <p class="mt-2 text-sm leading-6 text-amber-100">Source-derived boundaries, not exercised task outcomes. Evaluation accounts, fixtures, permissions, physical outputs and institutional approval remain UNVERIFIED.</p>
        {#if caseTrace.length > 0}
          <div class="mt-4 grid gap-4">
            {#each caseTrace as trace (`${trace.task_id}-${trace.goal}`)}
              <article class="min-w-0 rounded-xl border border-slate-800 bg-slate-900/70 p-4 sm:p-6">
                <h3 class="text-base font-semibold text-white">{trace.task_id}: {trace.goal}</h3>
                <dl class="mt-4 grid gap-x-5 gap-y-3 text-sm sm:grid-cols-2">
                  <div><dt class="font-semibold text-blue-200">Workspace</dt><dd class="mt-1 whitespace-pre-wrap break-words leading-6 text-slate-300">{trace.workspace}</dd></div>
                  <div><dt class="font-semibold text-blue-200">Capability</dt><dd class="mt-1 whitespace-pre-wrap break-words leading-6 text-slate-300">{trace.capability}</dd></div>
                  <div><dt class="font-semibold text-blue-200">Scope</dt><dd class="mt-1 whitespace-pre-wrap break-words leading-6 text-slate-300">{trace.scope}</dd></div>
                  <div><dt class="font-semibold text-blue-200">Prerequisites</dt><dd class="mt-1 whitespace-pre-wrap break-words leading-6 text-slate-300">{trace.prerequisites}</dd></div>
                  <div><dt class="font-semibold text-blue-200">Intended outcome</dt><dd class="mt-1 whitespace-pre-wrap break-words leading-6 text-slate-300">{trace.outcome}</dd></div>
                  <div><dt class="font-semibold text-amber-200">Limitations</dt><dd class="mt-1 whitespace-pre-wrap break-words leading-6 text-slate-300">{trace.limitations}</dd></div>
                </dl>
                {#if trace.source}<p class="mt-4 break-words border-t border-slate-800 pt-3 text-xs leading-5 text-slate-400">Source: {Array.isArray(trace.source) ? trace.source.join('; ') : trace.source}</p>{/if}
              </article>
            {/each}
          </div>
        {:else}
          <p class="mt-4 rounded-xl border border-amber-400/20 bg-amber-400/5 p-4 text-sm leading-6 text-amber-100" role="status"><strong>UNVERIFIED:</strong> No current use-case trace is present in the published facilitator reference. Do not treat this absence as evidence that a workspace, capability, scope, or case is ready.</p>
        {/if}
      </section>

      {#if source_version}
        <section class="mt-8 rounded-xl border border-slate-800 bg-slate-900/70 p-4 sm:p-6" aria-label="Reference provenance">
          <h2 class="text-sm font-semibold text-slate-200">Reference provenance</h2>
          <dl class="mt-3 grid gap-3 text-xs sm:grid-cols-2">
            {#each Object.entries(source_version) as [name, value] (name)}
              <div class="min-w-0"><dt class="font-medium text-slate-400">{name}</dt><dd class="mt-1 break-all font-mono text-slate-300">{value}</dd></div>
            {/each}
          </dl>
        </section>
      {/if}
    </div>
  </main>
</div>

<style>
  :global(.guide-facilitator-markdown) { color: #cbd5e1; font-size: 0.9375rem; line-height: 1.7; overflow-wrap: anywhere; }
  :global(.guide-facilitator-markdown h1), :global(.guide-facilitator-markdown h2), :global(.guide-facilitator-markdown h3), :global(.guide-facilitator-markdown h4) { color: #f1f5f9; font-weight: 600; line-height: 1.35; margin: 1.5rem 0 0.75rem; }
  :global(.guide-facilitator-markdown h1) { font-size: 1.5rem; }
  :global(.guide-facilitator-markdown h2) { font-size: 1.25rem; }
  :global(.guide-facilitator-markdown h3) { font-size: 1.125rem; }
  :global(.guide-facilitator-markdown p) { margin: 0.75rem 0; }
  :global(.guide-facilitator-markdown ul), :global(.guide-facilitator-markdown ol) { padding-left: 1.5rem; margin: 0.75rem 0; }
  :global(.guide-facilitator-markdown ul) { list-style: disc; }
  :global(.guide-facilitator-markdown ol) { list-style: decimal; }
  :global(.guide-facilitator-markdown li) { margin: 0.375rem 0; }
  :global(.guide-facilitator-markdown table) { display: block; max-width: 100%; overflow-x: auto; border-collapse: collapse; margin: 1rem 0; }
  :global(.guide-facilitator-markdown th), :global(.guide-facilitator-markdown td) { border: 1px solid #334155; padding: 0.5rem 0.75rem; vertical-align: top; }
  :global(.guide-facilitator-markdown a) { color: #93c5fd; text-decoration: underline; }
  :global(.guide-facilitator-markdown blockquote) { border-left: 1px solid #475569; padding-left: 1rem; margin: 1rem 0; }
  :global(.guide-facilitator-markdown pre) { overflow-x: auto; padding: 1rem; background: #0f172a; border-radius: 0.5rem; }
  :global(.guide-facilitator-markdown code) { font-family: var(--font-mono); font-size: 0.875em; }
  @media print {
    :global(body) { background: white !important; color: black !important; }
    .no-print { display: none !important; }
    .min-h-screen { background: white !important; }
    main { max-width: none; padding: 0; }
    .print-reference, .print-reference :global(*) { color: black !important; background: white !important; overflow-wrap: anywhere; }
    .print-reference article, .print-reference section { break-inside: auto; border-color: #bbb !important; }
    .print-reference :global(div) { overflow: visible !important; }
    .print-reference :global(table) { display: table; width: 100%; min-width: 0; max-width: 100%; table-layout: fixed; overflow: visible; }
    .print-reference :global(tr) { break-inside: avoid; }
    .print-reference :global(th), .print-reference :global(td) { padding: 0.4rem; }
  }
</style>
