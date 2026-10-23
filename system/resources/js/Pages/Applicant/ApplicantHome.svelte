<script>
  import ApplicantJourneyNode from '@/Components/kit/ApplicantJourneyNode.svelte';
  import { buildJourneyPositions } from '@/lib/applicant-journey';
  import TourTriggerGlyph from '@/Components/kit/Walkthrough/TourTriggerGlyph.svelte';
  import XiamNpcDialog from '@/Components/kit/Walkthrough/XiamNpcDialog.svelte';
  import * as Card from '@/Components/ui/card';
  import { Link, usePage } from '@inertiajs/svelte';
  import NexiamLogo from '@/Components/kit/NexiamLogo.svelte';
  import { AlertTriangle, CalendarDays, HelpCircle, UserRound } from 'lucide-svelte';
  import { parseDate } from '@/lib/date-utils.js';

  let {
    page_state = 'ready',
    identity = null,
    stages = [],
    pipeline = [],
    notices = {},
    quick_links = [],
    selectedHref = null,
    secondaryLinks = [],
    showTour = true,
    compact = false,
    hideFooter = false,
    onlyFooter = false,
  } = $props();
  const page = usePage();
  const institution = $derived(page.props.institution ?? null);
  const visibleSecondaryLinks = $derived.by(() => {
    const links = secondaryLinks.filter((item) =>
      ['results', 'program_choice', 'admission_support', 'office_inquiries', 'access_history', 'policies', 'announcements'].includes(item.key),
    );
    const known = new Set(links.map((item) => item.key));
    for (const item of [
      { key: 'policies', label: 'Public policies', href: '/policies' },
      { key: 'announcements', label: 'Announcements', href: '/announcements' },
    ]) {
      if (!known.has(item.key)) links.push(item);
    }
    return links;
  });
  const releaseLinks = $derived(secondaryLinks.filter((item) => ['release_schedule', 'release_day'].includes(item.key)));

  const formatStageDate = (value) => {
    if (!value) return '';
    const date = parseDate(value);
    if (!date) return String(value);
    const months = ['Jan.', 'Feb.', 'Mar.', 'Apr.', 'May', 'Jun.', 'Jul.', 'Aug.', 'Sep.', 'Oct.', 'Nov.', 'Dec.'];
    return `${months[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
  };
  const stepNumbers = {
    application: 1,
    exam_scheduling: 2,
    exam_monitoring: 3,
    scoring: 4,
    release_encounter: 5,
    admission_clearance: 6,
  };
  const homePipeline = $derived(pipeline.map((phase) => {
    if (phase.id !== 'release_of_results') return phase;
    const encounter = phase.steps.find((step) => step.id === 'release_encounter');
    const scheduling = phase.steps.find((step) => step.id === 'release_scheduling');
    if (!encounter) return phase;
    const completed = encounter.status === 'done';
    const needsScheduling = !completed && encounter.status === 'upcoming' && scheduling?.status === 'current';
    return {
      ...phase,
      steps: [{
        ...encounter,
        label: 'Release of Results',
        status: needsScheduling ? scheduling.status : encounter.status,
        summary: completed ? 'Released' : (needsScheduling ? scheduling.summary : encounter.summary),
        href: needsScheduling ? scheduling.href : encounter.href,
        detail_label: needsScheduling ? 'Schedule Release Day' : encounter.detail_label,
        scheduling_href: scheduling?.href ?? '/applicant/release-schedule',
        scheduling_summary: scheduling?.summary ?? null,
        needs_scheduling: needsScheduling,
      }],
    };
  }));
  const journeyPositions = $derived(buildJourneyPositions(homePipeline));
  let tourOpen = $state(false);
  const completedCount = $derived((stages ?? []).filter((stage) => stage.status === 'done').length);
  const hasQr = $derived((quick_links ?? []).some((link) => link.key === 'qr'));
  const portalContext = $derived.by(() => {
    const currentStage = stages?.find((stage) => stage.status === 'current') ?? null;
    return {
      entityKey: identity?.name ?? 'applicant-portal',
      safetyKey: `${page_state}:${currentStage?.title ?? 'none'}:${completedCount}:${hasQr ? 'qr' : 'noqr'}`,
      freshness: 'current',
      pageState: page_state,
      applicantName: identity?.name ?? 'Applicant',
      totalStagesCount: stages?.length ?? 0,
      currentStageTitle: currentStage?.title ?? null,
      hasActionRequired: false,
      actionStageTitle: null,
      hasQrLink: hasQr,
      completedStagesCount: completedCount,
    };
  });
</script>

<div class:compact class="space-y-6" data-applicant-home>
  {#if !compact}
    <div class="space-y-1">
      <div class="flex items-center gap-2">
        <h1 class="text-2xl font-semibold tracking-tight">Your status</h1>
        {#if showTour}
          <TourTriggerGlyph tourKey="applicant-portal" label="Need guidance? Kuya Xiam is here to help!" onclick={() => (tourOpen = true)} />
        {/if}
      </div>
      <p class="text-sm text-muted-foreground">Your application journey at a glance. Use Sign out when you finish on a shared device.</p>
    </div>
  {:else}
    <div class="flex items-center gap-2">
      <h2 class="text-base font-semibold tracking-tight">Your status</h2>
    </div>
  {/if}

{#if !onlyFooter}
  {#if page_state === 'empty'}
    <Card.Root>
      <Card.Content class="space-y-3 p-6 text-center">
        <UserRound class="mx-auto size-8 text-muted-foreground" />
        <p class="font-medium">No application found for this account</p>
        <p class="text-sm text-muted-foreground">If you applied, contact the Registrar. Your access key was shown after submitting your application and may also be in your email.</p>
      </Card.Content>
    </Card.Root>
  {:else}
    {#if notices.dismissed || notices.archived}
      <Card.Root class="border-destructive/30">
        <Card.Content class="space-y-3 p-6 text-center">
          <AlertTriangle class="mx-auto size-8 text-destructive" aria-hidden="true" />
          <p class="font-medium">{notices.dismissed ? 'Application dismissed' : 'Application archived'}</p>
          {#if notices.dismissal_reason}<p class="text-sm text-muted-foreground">{notices.dismissal_reason}</p>{/if}
          <p class="text-sm text-muted-foreground">{notices.dismissed ? 'This application is closed. Contact the Registrar if you believe this is a mistake.' : 'This record is no longer active.'}</p>
        </Card.Content>
      </Card.Root>
    {:else if pipeline.length > 0}
      <section class="space-y-6" data-tour="applicant-stages-list" aria-label="Application journey">
        {#each homePipeline as phase (phase.id)}
          <section class="space-y-2" aria-labelledby="phase-{phase.id}">
            <div class="mb-4 mt-8 first:mt-0">
              <h2 id="phase-{phase.id}" class="journey-phase-title font-semibold tracking-tight text-foreground">{phase.label}</h2>
            </div>
            <ol class="m-0 list-none p-0">
              {#each phase.steps as step (step.id)}
                {@const selected = selectedHref === String(step.href ?? '').split('?')[0]}
                <li class="relative flex gap-4 pb-8 last:pb-2">
                  <div class="journey-rail relative w-10 shrink-0 self-stretch">
                    <ApplicantJourneyNode status={step.status} />
                    {#if !journeyPositions.isLast(step.id)}<div class="journey-flow" aria-hidden="true"></div>{/if}
                  </div>
                  <div class="min-w-0 flex-1 space-y-3 rounded-lg border bg-card p-4 shadow-xs">
                    <div class="flex items-center justify-between gap-3">
                      <div class="flex min-w-0 items-center gap-2">
                        <h3 class="text-sm font-medium"><span class="mr-1.5 text-xs text-muted-foreground">Step {stepNumbers[step.id]}</span>{step.label}</h3>
                        {#if step.id === 'release_encounter' && step.status === 'done'}<span class="text-sm font-medium text-primary">Released</span>{/if}
                      </div>
                      {#if step.at}<time class="shrink-0 text-right text-xs text-muted-foreground" datetime={step.at}>{formatStageDate(step.at)}</time>{/if}
                    </div>
                    {#if step.id === 'admission_clearance' && step.status === 'done'}
                      <p class="pl-6 text-sm font-medium text-primary">Thank you. Your NEXIAM admission clearance is complete.</p>
                    {:else if step.summary && !(step.id === 'release_encounter' && step.status === 'done')}
                      <p class="pl-6 text-sm leading-relaxed text-muted-foreground">{step.summary}</p>
                    {/if}
                    <div class="space-y-3 pl-6">
                      <div class="flex flex-wrap items-center gap-2.5">
                        <Link
                          href={step.href}
                          class="inline-flex min-h-[44px] items-center gap-2 rounded-md border px-3.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring {selected ? 'border-primary bg-primary/10 text-primary' : 'border-input bg-background hover:bg-muted'}"
                          aria-current={selected ? 'page' : undefined}
                          aria-label={step.detail_label === 'View My Results' ? 'View My Results' : `View details: ${step.label}`}
                        >
                          {#if step.needs_scheduling}
                            <CalendarDays class="size-4 shrink-0 text-primary" aria-hidden="true" />
                          {/if}
                          <span>{step.detail_label ?? 'View details'}</span>
                        </Link>
                        {#if step.id === 'release_encounter' && !step.needs_scheduling}
                          <Link
                            href={step.scheduling_href ?? '/applicant/release-schedule'}
                            class="inline-flex min-h-[44px] items-center gap-2 rounded-md border border-input bg-background px-3.5 text-sm font-medium shadow-xs transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring {selectedHref === '/applicant/release-schedule' ? 'border-primary bg-primary/10 text-primary' : ''}"
                            aria-current={selectedHref === '/applicant/release-schedule' ? 'page' : undefined}
                          >
                            <CalendarDays class="size-4 shrink-0 text-primary" aria-hidden="true" />
                            <span>Release Day Scheduling</span>
                          </Link>
                        {/if}
                      </div>
                      {#if step.id === 'release_encounter'}
                        {#if step.scheduling_summary && !step.needs_scheduling}
                          <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <CalendarDays class="size-3.5 shrink-0" aria-hidden="true" />
                            <span>{step.scheduling_summary}</span>
                          </p>
                        {/if}
                        {#if releaseLinks.length > 0}
                          {@const otherLinks = releaseLinks.filter((item) => {
                            const itemPath = String(item.href ?? '').split('?')[0];
                            const stepPath = String(step.href ?? '').split('?')[0];
                            return itemPath !== stepPath && item.key !== 'release_schedule';
                          })}
                          {#if otherLinks.length > 0}
                            <nav class="flex flex-wrap gap-x-3 gap-y-1" aria-label="Related Release Day pages">
                              {#each otherLinks as item (item.key)}
                                {@const itemPath = String(item.href ?? '').split('?')[0]}
                                <Link href={item.href} class="text-xs text-muted-foreground underline-offset-4 hover:text-foreground hover:underline" aria-current={selectedHref === itemPath ? 'page' : undefined}>{item.label}</Link>
                              {/each}
                            </nav>
                          {/if}
                        {/if}
                      {/if}
                    </div>
                  </div>
                </li>
              {/each}
            </ol>
          </section>
        {/each}
      </section>
    {/if}
  {/if}
{/if}

{#if !hideFooter}

  <Card.Root class="border-border/70 bg-card/60">
    <Card.Content class="flex flex-col gap-4 p-5 sm:p-6 {compact ? '' : 'sm:flex-row sm:items-center sm:justify-between'}">
      <div class="space-y-1">
        <div class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-primary"><HelpCircle class="size-4" /><span>Questions & Guidance</span></div>
        <p class="font-medium text-foreground">Need help with requirements, schedules, or test rules?</p>
        <p class="text-xs text-muted-foreground sm:text-sm">Read verified institutional guidelines or send an inquiry to the admissions office.</p>
      </div>
      <div class="flex shrink-0 gap-3 {compact ? 'flex-col items-stretch' : 'items-center'}">
        <Link href="/faqs" class="inline-flex min-h-[44px] items-center rounded-lg border border-border bg-background px-4 py-2 text-sm font-semibold text-foreground shadow-xs transition hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Browse FAQs</Link>
        <Link href="/applicant/admission-support" class="inline-flex min-h-[44px] items-center rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-xs transition hover:bg-primary/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Contact Office</Link>
      </div>
    </Card.Content>
  </Card.Root>

  {#if visibleSecondaryLinks.length > 0}
    <nav class="space-y-2 border-t border-border/60 pt-4" aria-label="More applicant pages">
      <h2 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">More applicant pages</h2>
      <ul class="flex flex-wrap gap-x-4 gap-y-2">
        {#each visibleSecondaryLinks as item (item.key)}
          <li><Link href={item.href} class="text-sm text-primary underline-offset-4 hover:underline">{item.key === 'program_choice' ? 'Your program plans' : item.label}</Link></li>
        {/each}
      </ul>
    </nav>
  {/if}

  {#if !hideFooter}
    <footer class="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-border/60 pt-4 text-xs text-muted-foreground">
      <span>{institution?.name ?? 'Admissions & Testing'}</span>
      <p class="inline-flex items-center gap-1.5 text-muted-foreground/80 text-[11px]">
        <span>Admissions & testing powered by</span>
        <span class="inline-flex items-center gap-1 font-bold text-foreground/85">
          <NexiamLogo variant="mark" size="xs" class="size-3.5 inline-block" />
          NEXIAM
        </span>
      </p>
    </footer>
  {/if}
{/if}
  {#if showTour}<XiamNpcDialog tourKey="applicant-portal" context={portalContext} bind:open={tourOpen} />{/if}
</div>

<style>
  .journey-phase-title { margin: 0 0 0.75rem; padding-inline-start: 3.5rem; font-size: clamp(1.125rem, 2vw, 1.25rem); line-height: 1.35; }
  .journey-flow { position: absolute; inset-inline-start: 1.1875rem; top: 2.875rem; bottom: 0; width: 0.125rem; overflow: hidden; pointer-events: none; }
  .journey-flow::before { content: ''; position: absolute; inset-inline: 0; top: -10px; bottom: 0; background: repeating-linear-gradient(to bottom, color-mix(in oklab, var(--primary) 45%, var(--border)) 0 3px, transparent 3px 10px); animation: flowDown 1.25s linear infinite; }
  @keyframes flowDown { from { transform: translateY(0); } to { transform: translateY(10px); } }
  @media (prefers-reduced-motion: reduce) { .journey-flow::before { animation: none; transform: none; } }
</style>
