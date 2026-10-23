<script>
  /**
   * XiamWalkthroughPreview.svelte
   *
   * Staff-facing interactive preview for planned Xiam Assistant controls:
   * 1. Office Access — Inquiry and task assistance switches with effective status
   * 2. Usage & Limit Status — Capacity meter and safe fallback next actions
   * 3. Office Allocation (What-If) — Proposed per-office caps (pending policy decision)
   * 4. Knowledge & Readiness — Document reviews, separation of duty, and setup status
   *
   * In-memory prototype only. No persistent changes or live API calls.
   */
  import { reveal } from '@/lib/motion.js';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import { Switch } from '@/Components/ui/switch';
  import {
    Building2,
    RotateCcw,
    CircleCheck,
    CircleAlert,
    CircleX,
    TriangleAlert,
    Lock,
    SlidersHorizontal,
    Activity,
    FileText,
    Check,
    Clock,
  } from 'lucide-svelte';

  // Active view selection
  let activeView = $state('access'); // 'access' | 'usage' | 'allocation' | 'knowledge'

  // =========================================================================
  // 1. Office Access State
  // =========================================================================
  let channels = $state({
    registrar: { inquiry: true, assistant: true },
    guidance: { inquiry: true, assistant: false }, // Narrowed by guidance staff
    applicant: { inquiry: true, assistant: false },
  });

  let officialResultsReleased = $state(false);

  const effectiveStatus = $derived({
    registrar: channels.registrar.inquiry && channels.registrar.assistant
      ? 'Full Office Service (Inquiry + Task Assistance)'
      : channels.registrar.inquiry
        ? 'Inquiry Only (Task assistance paused by office)'
        : channels.registrar.assistant
          ? 'Task Assistance Only'
          : 'Service Paused by Office',
    guidance: channels.guidance.inquiry && channels.guidance.assistant
      ? 'Full Office Service (Inquiry + Consultation Assistance)'
      : channels.guidance.inquiry
        ? 'Inquiry Only (Guidance office paused task drafting)'
        : channels.guidance.assistant
          ? 'Consultation Assistance Only'
          : 'Service Paused by Office',
    applicant: channels.applicant.inquiry && channels.applicant.assistant
      ? 'Inquiry + Guided Self-Service'
      : channels.applicant.inquiry
        ? 'General Inquiries Only'
        : 'Applicant Portal Service Paused',
  });

  // =========================================================================
  // 2. Usage & Limit Health State
  // =========================================================================
  let usageLevel = $state('normal'); // 'normal' | 'near_cap' | 'at_limit' | 'reconciling'

  const usageInfo = $derived.by(() => {
    switch (usageLevel) {
      case 'near_cap':
        return {
          percent: 86,
          label: 'Near Monthly Cap (86%)',
          badgeVariant: 'outline',
          badgeClass: 'border-amber-500/50 text-amber-700 dark:text-amber-300 bg-amber-500/10',
          staffAction: 'High volume period. Assistant continues operating. Office staff should prioritize urgent admissions inquiries.',
        };
      case 'at_limit':
        return {
          percent: 100,
          label: 'Monthly Limit Reached (100%)',
          badgeVariant: 'destructive',
          badgeClass: 'bg-rose-500 text-white',
          staffAction: 'Assistant queries are paused until the monthly reset. Core desk operations are unaffected: all standard forms, manual document verification, and scoring remain fully operational.',
        };
      case 'reconciling':
        return {
          percent: 74,
          label: 'Checking Disconnected Requests',
          badgeVariant: 'secondary',
          badgeClass: 'bg-muted text-foreground',
          staffAction: 'Two interrupted requests are being safely verified by the system. Normal office operations continue as usual.',
        };
      default:
        return {
          percent: 64,
          label: 'Healthy Operation (64%)',
          badgeVariant: 'default',
          badgeClass: 'bg-emerald-600 text-white',
          staffAction: 'Capacity is optimal across all office desks. Standard inquiry and task assistance are running normally.',
        };
    }
  });

  // =========================================================================
  // 3. Proposed Office Allocation (What-If)
  // =========================================================================
  let registrarShare = $state(45);
  let guidanceShare = $state(35);

  const allocationMath = $derived.by(() => {
    const totalAssigned = registrarShare + guidanceShare;
    const reserve = 100 - totalAssigned;
    const isOver = totalAssigned > 100;
    return { totalAssigned, reserve, isOver, overAmount: Math.abs(reserve) };
  });

  // =========================================================================
  // 4. Knowledge & Setup Readiness State
  // =========================================================================
  let guidanceDocApproved = $state(false);
  let jointPolicyApproved = $state(false);

  const readinessScore = $derived.by(() => {
    let passed = 2; // Registrar document & privacy baseline always ready in mock
    if (guidanceDocApproved) passed += 1;
    if (jointPolicyApproved) passed += 1;
    return { passed, total: 4, percent: Math.round((passed / 4) * 100) };
  });

  // =========================================================================
  // Reset All Mock State
  // =========================================================================
  function resetAll() {
    activeView = 'access';
    channels = {
      registrar: { inquiry: true, assistant: true },
      guidance: { inquiry: true, assistant: false },
      applicant: { inquiry: true, assistant: false },
    };
    officialResultsReleased = false;
    usageLevel = 'normal';
    registrarShare = 45;
    guidanceShare = 35;
    guidanceDocApproved = false;
    jointPolicyApproved = false;
  }
</script>

<div class="overflow-hidden rounded-2xl border border-border/70 bg-card shadow-xs" use:reveal={{ y: 12 }}>
  <!-- Clean Header Banner -->
  <div class="flex flex-col gap-2.5 border-b border-border/60 bg-muted/20 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
    <div>
      <div class="flex flex-wrap items-center gap-2">
        <h3 class="text-sm font-semibold text-foreground sm:text-base">
          Office Operations & Controls Preview
        </h3>
        <Badge variant="outline" class="border-amber-500/40 bg-amber-500/10 text-[11px] text-amber-700 dark:text-amber-300">
          Planned · Synthetic Preview
        </Badge>
      </div>
      <p class="mt-0.5 text-xs text-muted-foreground">
        Simulate office service switches, capacity limits, unapproved quota proposals, and knowledge reviews.
      </p>
    </div>

    <Button
      variant="outline"
      size="sm"
      class="h-8 gap-1.5 self-start text-xs text-muted-foreground hover:text-foreground sm:self-center"
      onclick={resetAll}
      aria-label="Reset simulation to initial values"
    >
      <RotateCcw class="size-3.5" />
      <span>Reset Defaults</span>
    </Button>
  </div>

  <!-- Accessible Navigation -->
  <nav class="flex border-b border-border/60 bg-background px-3 py-1.5 overflow-x-auto sm:px-5" aria-label="Staff operating sections">
    <div class="flex gap-1.5 min-w-max">
      {#each [
        { id: 'access', label: 'Office Access', icon: Building2 },
        { id: 'usage', label: 'Usage & Limits', icon: Activity },
        { id: 'allocation', label: 'Office Quotas (What-If)', icon: SlidersHorizontal },
        { id: 'knowledge', label: 'Knowledge & Readiness', icon: FileText },
      ] as tab}
        <button
          type="button"
          aria-pressed={activeView === tab.id}
          class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium transition-all {activeView === tab.id
            ? 'bg-primary text-primary-foreground shadow-xs'
            : 'text-muted-foreground hover:bg-muted/80 hover:text-foreground'}"
          onclick={() => (activeView = tab.id)}
        >
          <tab.icon class="size-3.5" />
          <span>{tab.label}</span>
        </button>
      {/each}
    </div>
  </nav>

  <!-- View 1: Office Access & Channel Controls -->
  {#if activeView === 'access'}
    <div class="p-4 sm:p-6 space-y-6">
      <div class="rounded-xl border border-primary/20 bg-primary/5 p-3.5 text-xs text-muted-foreground">
        <strong class="text-foreground">Office Policy Rule:</strong> Office staff can pause or limit Xiam on their desk (e.g. during busy intake hours), but office switches cannot grant permissions beyond official university policy.
      </div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <!-- Registrar Card -->
        <div class="rounded-xl border border-border/70 bg-card p-4 space-y-3">
          <div class="flex items-start justify-between">
            <div>
              <h4 class="text-sm font-semibold text-foreground">Registrar Desk</h4>
              <p class="text-[11px] text-muted-foreground">Admissions, Credentials, Records</p>
            </div>
            <Badge variant="outline" class="text-[10px]">Office Desk</Badge>
          </div>

          <div class="space-y-2.5 rounded-lg border border-border/50 bg-muted/20 p-3 text-xs">
            <div class="flex items-center justify-between">
              <label for="reg-inquiry" class="cursor-pointer">
                <span class="font-medium text-foreground block">Inquiry Assistant</span>
                <span class="text-[10px] text-muted-foreground">Requirements & FAQ</span>
              </label>
              <Switch id="reg-inquiry" bind:checked={channels.registrar.inquiry} aria-label="Toggle Registrar inquiry" />
            </div>

            <div class="border-t border-border/40 pt-2 flex items-center justify-between">
              <label for="reg-assist" class="cursor-pointer">
                <span class="font-medium text-foreground block">Task Assistance</span>
                <span class="text-[10px] text-muted-foreground">Checklist proposals</span>
              </label>
              <Switch id="reg-assist" bind:checked={channels.registrar.assistant} aria-label="Toggle Registrar task assistant" />
            </div>
          </div>

          <div class="text-xs">
            <span class="text-[10px] text-muted-foreground uppercase font-semibold block">Effective Status:</span>
            <span class="text-foreground font-medium">{effectiveStatus.registrar}</span>
          </div>
        </div>

        <!-- Guidance Card -->
        <div class="rounded-xl border border-border/70 bg-card p-4 space-y-3">
          <div class="flex items-start justify-between">
            <div>
              <h4 class="text-sm font-semibold text-foreground">Guidance Desk</h4>
              <p class="text-[11px] text-muted-foreground">Testing, Diagnostics, Counseling</p>
            </div>
            <Badge variant="outline" class="text-[10px]">Office Desk</Badge>
          </div>

          <div class="space-y-2.5 rounded-lg border border-border/50 bg-muted/20 p-3 text-xs">
            <div class="flex items-center justify-between">
              <label for="gui-inquiry" class="cursor-pointer">
                <span class="font-medium text-foreground block">Inquiry Assistant</span>
                <span class="text-[10px] text-muted-foreground">Exam rules & guidance</span>
              </label>
              <Switch id="gui-inquiry" bind:checked={channels.guidance.inquiry} aria-label="Toggle Guidance inquiry" />
            </div>

            <div class="border-t border-border/40 pt-2 flex items-center justify-between">
              <label for="gui-assist" class="cursor-pointer">
                <span class="font-medium text-foreground block">Task Assistance</span>
                <span class="text-[10px] text-muted-foreground">Interview scheduling</span>
              </label>
              <Switch id="gui-assist" bind:checked={channels.guidance.assistant} aria-label="Toggle Guidance task assistant" />
            </div>
          </div>

          <div class="text-xs">
            <span class="text-[10px] text-muted-foreground uppercase font-semibold block">Effective Status:</span>
            <span class="text-foreground font-medium">{effectiveStatus.guidance}</span>
          </div>
        </div>

        <!-- Applicant Portal Card -->
        <div class="rounded-xl border border-border/70 bg-card p-4 space-y-3">
          <div class="flex items-start justify-between">
            <div>
              <h4 class="text-sm font-semibold text-foreground">Applicant Portal</h4>
              <p class="text-[11px] text-muted-foreground">Candidate Self-Service</p>
            </div>
            <Badge variant="outline" class="text-[10px]">Student Facing</Badge>
          </div>

          <div class="space-y-2.5 rounded-lg border border-border/50 bg-muted/20 p-3 text-xs">
            <div class="flex items-center justify-between">
              <label for="app-inquiry" class="cursor-pointer">
                <span class="font-medium text-foreground block">General Inquiries</span>
                <span class="text-[10px] text-muted-foreground">Public admissions questions</span>
              </label>
              <Switch id="app-inquiry" bind:checked={channels.applicant.inquiry} aria-label="Toggle Applicant inquiry" />
            </div>

            <div class="border-t border-border/40 pt-2 flex items-center justify-between">
              <label for="app-assist" class="cursor-pointer">
                <span class="font-medium text-foreground block">Task Guidance</span>
                <span class="text-[10px] text-muted-foreground">Step-by-step submission</span>
              </label>
              <Switch id="app-assist" bind:checked={channels.applicant.assistant} aria-label="Toggle Applicant task guidance" />
            </div>
          </div>

          <div class="text-xs">
            <span class="text-[10px] text-muted-foreground uppercase font-semibold block">Effective Status:</span>
            <span class="text-foreground font-medium">{effectiveStatus.applicant}</span>
          </div>
        </div>
      </div>

      <!-- Policy Gate Simulation -->
      <div class="rounded-xl border border-border/70 bg-muted/10 p-4 space-y-3">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h4 class="text-sm font-semibold text-foreground">Official Exam Results Release Gate</h4>
            <p class="text-xs text-muted-foreground">Experience how local switches respect university release authority.</p>
          </div>
          <div class="flex items-center gap-2.5 rounded-lg border border-border/60 bg-background px-3 py-1.5">
            <label for="switch-gate" class="text-xs font-medium cursor-pointer">Official Results Released:</label>
            <Switch id="switch-gate" bind:checked={officialResultsReleased} aria-label="Toggle official results release state" />
            <span class="text-xs font-semibold {officialResultsReleased ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}">
              {officialResultsReleased ? 'Released' : 'Withheld'}
            </span>
          </div>
        </div>

        <div class="rounded-lg border border-border/50 bg-background p-3.5 text-xs space-y-2">
          <p class="text-muted-foreground italic">Applicant asks: "Can I view my entrance examination rating?"</p>
          {#if officialResultsReleased}
            <div class="rounded-md border border-emerald-500/30 bg-emerald-500/5 p-2.5 text-emerald-800 dark:text-emerald-200 flex items-center gap-2 font-medium">
              <CircleCheck class="size-4 text-emerald-500 shrink-0" />
              Official results are released. Authorized result is available only for the applicant's own released record.
            </div>
          {:else}
            <div class="rounded-md border border-amber-500/40 bg-amber-500/5 p-2.5 text-amber-800 dark:text-amber-200 flex items-center gap-2 font-medium">
              <TriangleAlert class="size-4 text-amber-500 shrink-0" />
              Results unreleased by university policy. Xiam refuses disclosure even if office switches are active.
            </div>
          {/if}
        </div>
      </div>
    </div>

  <!-- View 2: Usage & Limit Status -->
  {:else if activeView === 'usage'}
    <div class="p-4 sm:p-6 space-y-6">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h4 class="text-sm font-semibold text-foreground">Monthly Capacity & Limit Feedback</h4>
          <p class="text-xs text-muted-foreground">Select a scenario to preview system behavior and staff instructions.</p>
        </div>
        <div class="flex flex-wrap gap-1 rounded-lg border border-border/60 bg-muted/40 p-1">
          {#each [
            { id: 'normal', label: 'Normal (64%)' },
            { id: 'near_cap', label: 'Near Cap (86%)' },
            { id: 'at_limit', label: 'At Limit (100%)' },
            { id: 'reconciling', label: 'Interrupted Request' },
          ] as sc}
            <button
              type="button"
              aria-pressed={usageLevel === sc.id}
              class="rounded px-2.5 py-1 text-xs font-medium transition-colors {usageLevel === sc.id
                ? 'bg-background text-foreground shadow-xs'
                : 'text-muted-foreground hover:text-foreground'}"
              onclick={() => (usageLevel = sc.id)}
            >
              {sc.label}
            </button>
          {/each}
        </div>
      </div>

      <!-- Meter Card -->
      <div class="rounded-xl border border-border/70 bg-card p-5 space-y-4">
        <div class="flex items-center justify-between">
          <div class="space-y-0.5">
            <span class="text-xs text-muted-foreground font-medium uppercase">Current Monthly Capacity</span>
            <div class="text-2xl font-bold text-foreground">{usageInfo.percent}% Used</div>
          </div>
          <Badge class="text-xs {usageInfo.badgeClass}">
            {usageInfo.label}
          </Badge>
        </div>

        <div class="h-2.5 w-full overflow-hidden rounded-full bg-muted">
          <div
            class="h-full rounded-full transition-all duration-300 {usageInfo.percent >= 100
              ? 'bg-rose-500'
              : usageInfo.percent >= 85
                ? 'bg-amber-500'
                : 'bg-primary'}"
            style="width: {usageInfo.percent}%"
          ></div>
        </div>

        <div class="rounded-lg border border-border/50 bg-background p-3.5 text-xs space-y-1.5">
          <strong class="text-foreground block">Staff Guidance & Action:</strong>
          <p class="text-muted-foreground leading-relaxed">{usageInfo.staffAction}</p>
        </div>

        <div class="rounded-lg border border-border/40 bg-muted/20 p-3 text-[11px] text-muted-foreground">
          <strong>Non-Interruption Assurance:</strong> AI assistance is an optional aid. University operations never depend on Xiam: desk staff can always complete admissions, reviews, and registrations using regular web forms.
        </div>
      </div>
    </div>

  <!-- View 3: Proposed Office Quotas (What-If) -->
  {:else if activeView === 'allocation'}
    <div class="p-4 sm:p-6 space-y-6">
      <!-- Explicit Policy Pending Notice -->
      <div class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 space-y-1.5 text-xs text-amber-900 dark:text-amber-200">
        <div class="flex items-center gap-2 font-semibold">
          <TriangleAlert class="size-4 text-amber-600 dark:text-amber-400 shrink-0" />
          <span>Proposed Quota Model — Pending Founder Policy Decision</span>
        </div>
        <p class="leading-relaxed">
          This what-if tool illustrates a proposed allocation model for separate monthly shares across individual offices so busy enrollment periods in one office do not deplete another, pending policy review.
        </p>
      </div>

      <!-- Controls -->
      <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div class="rounded-xl border border-border/70 bg-card p-4 space-y-4">
          <h4 class="text-sm font-semibold text-foreground">Adjust Office Allowances</h4>

          <div class="space-y-2">
            <div class="flex justify-between text-xs">
              <label for="slider-reg" class="font-medium text-foreground">Registrar Office Allowance:</label>
              <span class="font-semibold text-primary">{registrarShare}%</span>
            </div>
            <input
              id="slider-reg"
              type="range"
              min="10"
              max="80"
              step="5"
              bind:value={registrarShare}
              class="w-full accent-primary h-2 bg-muted rounded-lg cursor-pointer"
            />
          </div>

          <div class="space-y-2">
            <div class="flex justify-between text-xs">
              <label for="slider-gui" class="font-medium text-foreground">Guidance & Testing Allowance:</label>
              <span class="font-semibold text-primary">{guidanceShare}%</span>
            </div>
            <input
              id="slider-gui"
              type="range"
              min="10"
              max="80"
              step="5"
              bind:value={guidanceShare}
              class="w-full accent-primary h-2 bg-muted rounded-lg cursor-pointer"
            />
          </div>

          <p class="text-[11px] text-muted-foreground">
            Remaining unallocated share is preserved for applicant self-service and emergency buffer.
          </p>
        </div>

        <!-- Feedback Card -->
        <div class="rounded-xl border border-border/70 bg-card p-4 space-y-3">
          <h4 class="text-sm font-semibold text-foreground">Ceiling Check</h4>

          {#if allocationMath.isOver}
            <div class="rounded-lg border border-rose-500/40 bg-rose-500/10 p-3 text-xs text-rose-800 dark:text-rose-200 space-y-1">
              <div class="font-semibold flex items-center gap-1.5">
                <CircleX class="size-4 text-rose-500" />
                Exceeds 100% Institution Ceiling
              </div>
              <p>Combined allocations equal <strong>{allocationMath.totalAssigned}%</strong> (+{allocationMath.overAmount}% over budget). Lower one office share to fit within university capacity.</p>
            </div>
          {:else}
            <div class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-800 dark:text-emerald-200 space-y-1">
              <div class="font-semibold flex items-center gap-1.5">
                <CircleCheck class="size-4 text-emerald-500" />
                Balanced Allocation Plan
              </div>
              <p><strong>{allocationMath.reserve}%</strong> remains reserved for student portal inquiries and shared buffer.</p>
            </div>
          {/if}

          <!-- Visual Bar -->
          <div class="space-y-1 text-xs">
            <div class="flex h-3 w-full overflow-hidden rounded-md bg-muted text-[10px]">
              <div class="bg-blue-600 transition-all" style="width: {registrarShare}%"></div>
              <div class="bg-emerald-600 transition-all" style="width: {guidanceShare}%"></div>
              {#if !allocationMath.isOver}
                <div class="bg-slate-400 dark:bg-slate-600 transition-all" style="width: {allocationMath.reserve}%"></div>
              {/if}
            </div>
            <div class="flex justify-between text-[11px] text-muted-foreground pt-1">
              <span>Registrar: {registrarShare}%</span>
              <span>Guidance: {guidanceShare}%</span>
              <span>Buffer: {allocationMath.reserve}%</span>
            </div>
          </div>
        </div>
      </div>
    </div>

  <!-- View 4: Knowledge & Setup Readiness -->
  {:else if activeView === 'knowledge'}
    <div class="p-4 sm:p-6 space-y-6">
      <div class="flex flex-col gap-2 rounded-xl border border-border/70 bg-card p-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h4 class="text-sm font-semibold text-foreground">Setup Hub: Knowledge & Governance Readiness</h4>
          <p class="text-xs text-muted-foreground">Office guidance documents must be signed off by authorized reviewers before assistant answers adopt them.</p>
        </div>
        <Badge variant={readinessScore.passed === 4 ? 'default' : 'secondary'} class="text-xs">
          {readinessScore.percent}% Ready ({readinessScore.passed}/4 items)
        </Badge>
      </div>

      <!-- Document List with Separation of Duty -->
      <div class="space-y-3">
        <h5 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Office Knowledge Sources</h5>

        <!-- Doc 1: Registrar -->
        <div class="rounded-xl border border-border/70 bg-card p-3.5 text-xs flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <div class="flex items-center gap-2">
              <span class="font-semibold text-foreground">Freshman Admission & Document Guide 2026</span>
              <Badge variant="outline" class="text-[10px] text-emerald-600 dark:text-emerald-400 border-emerald-500/30">Active</Badge>
            </div>
            <p class="text-[11px] text-muted-foreground">Owned by Registrar Office · Approved by Head Officer (Separation of duty verified)</p>
          </div>
          <span class="text-[11px] text-muted-foreground">Published</span>
        </div>

        <!-- Doc 2: Guidance -->
        <div class="rounded-xl border border-border/70 bg-card p-3.5 text-xs flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <div class="flex items-center gap-2">
              <span class="font-semibold text-foreground">Aptitude Diagnostic Rubric & Counseling Guide</span>
              <Badge variant="outline" class="text-[10px] {guidanceDocApproved ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}">
                {guidanceDocApproved ? 'Approved' : 'Pending Review'}
              </Badge>
            </div>
            <p class="text-[11px] text-muted-foreground">Owned by Guidance Office · Created by Testing Counselor</p>
          </div>
          <Button
            size="sm"
            variant={guidanceDocApproved ? 'outline' : 'default'}
            class="h-7 text-xs"
            onclick={() => (guidanceDocApproved = !guidanceDocApproved)}
          >
            {guidanceDocApproved ? 'Revoke Approval' : 'Approve as Guidance Head'}
          </Button>
        </div>

        <!-- Doc 3: Joint Policy -->
        <div class="rounded-xl border border-border/70 bg-card p-3.5 text-xs flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <div class="flex items-center gap-2">
              <span class="font-semibold text-foreground">Joint Cutoff Criteria & Program Roster Guidelines</span>
              <Badge variant="outline" class="text-[10px] {jointPolicyApproved ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'}">
                {jointPolicyApproved ? 'Dual Sign-off Complete' : 'Awaiting Guidance Sign-off'}
              </Badge>
            </div>
            <p class="text-[11px] text-muted-foreground">Cross-Office Policy · Registrar signed off; requires Guidance sign-off</p>
          </div>
          <Button
            size="sm"
            variant={jointPolicyApproved ? 'outline' : 'default'}
            class="h-7 text-xs"
            onclick={() => (jointPolicyApproved = !jointPolicyApproved)}
          >
            {jointPolicyApproved ? 'Withdraw Sign-off' : 'Sign off as Guidance Officer'}
          </Button>
        </div>
      </div>
    </div>
  {/if}

  <!-- Footer Deployment Invariant Note -->
  <div class="border-t border-border/60 bg-muted/20 px-4 py-2.5 sm:px-6 text-xs text-muted-foreground flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
    <span class="flex items-center gap-1.5">
      <Lock class="size-3.5 text-muted-foreground" />
      Deployment settings (model, prompts, credentials, emergency stop) are managed by the deployment and operations team.
    </span>
    <span class="font-mono text-[10px]">Synthetic UI Lab Preview</span>
  </div>
</div>
