<script>
  /**
   * Applicant status-spine hub (2026-08-15-applicant-portal-interface).
   * Server projection only: stage order, states, actions, and release-mode
   * placement come from ApplicantPortalProjection — the client never reorders
   * or invents actions.
   */
  import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Card from '@/Components/ui/card';
  import { Link } from '@inertiajs/svelte';
  import {
    AlertTriangle,
    BellRing,
    CheckCircle2,
    Circle,
    ClipboardList,
    LifeBuoy,
    QrCode,
    ShieldCheck,
    Sparkles,
    UserRound,
  } from 'lucide-svelte';

  let {
    page_state = 'ready',
    identity = null,
    stages = [],
    notices = {},
    quick_links = [],
  } = $props();

  const badgeFor = (status) => {
    switch (status) {
      case 'done':
        return { label: 'Done', variant: 'outline' };
      case 'current':
        return { label: 'Now', variant: 'default' };
      case 'action':
        return { label: 'Needs action', variant: 'destructive' };
      case 'auto':
        return { label: 'Auto', variant: 'secondary' };
      default:
        return { label: 'Next', variant: 'secondary' };
    }
  };

  const quickLinkIcon = (key) => {
    if (key === 'notifications') return BellRing;
    if (key === 'qr') return QrCode;
    if (key === 'admission_support') return ClipboardList;
    return LifeBuoy;
  };
</script>

<svelte:head><title>Your status</title></svelte:head>

<ApplicantLayout title="Your status">
  <div class="space-y-6">
    <div class="space-y-1">
      <h1 class="text-2xl font-semibold tracking-tight">Your status</h1>
      <p class="text-sm text-muted-foreground">
        Your application journey at a glance. Use Sign out when you finish on a shared device.
      </p>
    </div>

    {#if page_state === 'empty'}
      <Card.Root>
        <Card.Content class="space-y-3 p-6 text-center">
          <UserRound class="mx-auto size-8 text-muted-foreground" />
          <p class="font-medium">No application found for this account</p>
          <p class="text-sm text-muted-foreground">
            If you applied, contact the Registrar. Your access key was shown after submitting your
            application and may also be in your email.
          </p>
        </Card.Content>
      </Card.Root>
    {:else}
      {#if identity}
        <Card.Root class="overflow-hidden border-primary/20 bg-primary/5">
          <Card.Content class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
              <span
                class="flex size-11 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary"
                aria-hidden="true"
              >
                <UserRound class="size-5" />
              </span>
              <div class="min-w-0">
                <p class="truncate font-semibold">{identity.name}</p>
                <p class="font-mono text-sm text-muted-foreground">Applicant No. {identity.applicant_no}</p>
              </div>
            </div>
            {#if identity.program_hint}
              <Badge variant="outline" class="w-fit shrink-0 border-primary/30 text-primary">
                {identity.program_hint}
              </Badge>
            {/if}
          </Card.Content>
        </Card.Root>
      {/if}

      {#if notices.needs_correction}
        <Card.Root class="border-amber-500/40 bg-amber-500/10">
          <Card.Content class="space-y-3 p-4">
            <p class="flex items-center gap-2 font-medium text-amber-800 dark:text-amber-300">
              <AlertTriangle class="size-4" aria-hidden="true" />
              Registrar needs a correction
            </p>
            {#if notices.correction_notes}
              <p class="text-sm text-muted-foreground">{notices.correction_notes}</p>
            {/if}
            {#if notices.correction_href}
              <Link href={notices.correction_href}>
                <Button class="min-h-11 w-full">Fix application</Button>
              </Link>
            {/if}
          </Card.Content>
        </Card.Root>
      {/if}

      {#if notices.results_blocked}
        <Card.Root class="border-primary/30 bg-primary/5">
          <Card.Content class="space-y-3 p-4">
            <p class="flex items-center gap-2 font-medium">
              <ShieldCheck class="size-4 text-primary" aria-hidden="true" />
              Results ready — claim them at your consultation
            </p>
            <p class="text-sm text-muted-foreground">
              Your results are released for face-to-face consultation. Book a session with Guidance to
              view your scores.
            </p>
          </Card.Content>
        </Card.Root>
      {/if}

      {#if notices.dismissed || notices.archived}
        <Card.Root class="border-destructive/30">
          <Card.Content class="space-y-3 p-6 text-center">
            <AlertTriangle class="mx-auto size-8 text-destructive" aria-hidden="true" />
            <p class="font-medium">{notices.dismissed ? 'Application dismissed' : 'Application archived'}</p>
            {#if notices.dismissal_reason}
              <p class="text-sm text-muted-foreground">{notices.dismissal_reason}</p>
            {/if}
            <p class="text-sm text-muted-foreground">
              {notices.dismissed
                ? 'This application is closed. Contact the Registrar if you believe this is a mistake.'
                : 'This record is no longer active.'}
            </p>
          </Card.Content>
        </Card.Root>
      {:else if stages.length > 0}
        <Card.Root class="overflow-hidden shadow-sm">
          <Card.Header class="border-b bg-muted/20 pb-3">
            <Card.Title class="text-lg">Your journey</Card.Title>
            <Card.Description>Each step shows what you can do right now.</Card.Description>
          </Card.Header>
          <Card.Content class="pt-5">
            <ol class="relative m-0 list-none space-y-0 p-0" aria-label="Application journey">
              {#each stages as stage, i (stage.id)}
                {@const isLast = i === stages.length - 1}
                {@const badge = badgeFor(stage.status)}
                <li class="relative flex gap-3">
                  <div class="flex shrink-0 flex-col items-center">
                    <div class="relative z-10">
                      {#if stage.status === 'current'}
                        <div
                          class="absolute -inset-1.5 animate-ping rounded-full bg-primary/20 opacity-75"
                          style="animation-duration: 3s;"
                          aria-hidden="true"
                        ></div>
                      {/if}
                      <div
                        class="relative flex size-8 items-center justify-center rounded-full border-2 transition-colors
                          {stage.status === 'done'
                            ? 'border-primary bg-primary text-primary-foreground'
                            : stage.status === 'current'
                              ? 'border-primary bg-background text-primary ring-4 ring-primary/10'
                              : stage.status === 'action'
                                ? 'border-amber-500 bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                : stage.status === 'auto'
                                  ? 'border-emerald-600/50 bg-emerald-600/10 text-emerald-700 dark:text-emerald-400'
                                  : 'border-muted-foreground/30 bg-background text-muted-foreground'}"
                      >
                        {#if stage.status === 'done'}
                          <CheckCircle2 class="size-4" aria-hidden="true" />
                        {:else if stage.status === 'action'}
                          <AlertTriangle class="size-4" aria-hidden="true" />
                        {:else if stage.status === 'auto'}
                          <Sparkles class="size-4" aria-hidden="true" />
                        {:else if stage.status === 'current'}
                          <Circle class="size-4 fill-primary/20" aria-hidden="true" />
                        {:else}
                          <Circle class="size-4" aria-hidden="true" />
                        {/if}
                      </div>
                    </div>
                    {#if !isLast}
                      <div
                        class="mt-1 min-h-7 w-0.5 flex-1 rounded-full
                          {stage.status === 'done'
                            ? 'bg-primary'
                            : stage.status === 'current'
                              ? 'bg-primary/40'
                              : 'bg-border'}"
                        aria-hidden="true"
                      ></div>
                    {/if}
                  </div>

                  <div class="min-w-0 flex-1 pb-5 pt-0.5 last:pb-0">
                    <div class="flex flex-wrap items-center gap-2">
                      <span
                        class="text-sm font-medium
                          {stage.status === 'upcoming' ? 'text-muted-foreground' : 'text-foreground'}"
                      >
                        {stage.label}
                      </span>
                      <Badge
                        variant={badge.variant}
                        class="shrink-0 text-[10px] uppercase tracking-wide"
                      >
                        {badge.label}
                      </Badge>
                      {#if stage.status === 'auto'}
                        <span class="text-xs text-emerald-700 dark:text-emerald-400" title="Set by Guidance — no action needed">
                          ✦ projection active
                        </span>
                      {/if}
                      {#if stage.at}
                        <span class="text-xs text-muted-foreground">{stage.at}</span>
                      {/if}
                    </div>

                    {#if stage.summary}
                      <p class="mt-1 text-sm leading-relaxed text-muted-foreground">{stage.summary}</p>
                    {/if}

                    {#if stage.release && stage.release.mode === 'online' && stage.profile?.series?.length}
                      <div class="mt-3 rounded-lg border border-border bg-muted/20 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                          {stage.profile.title ?? 'Released score profile'}
                        </p>
                        <ul class="mt-3 space-y-3">
                          {#each stage.profile.series as item (item.area_code)}
                            {@const scoreNum = Number(item.score)}
                            {@const barWidth = Number.isFinite(scoreNum) ? Math.min(Math.max(scoreNum, 0), 100) : null}
                            <li>
                              <div class="flex items-baseline justify-between gap-3">
                                <span class="text-sm font-medium">{item.area_name}</span>
                                <span class="font-mono text-sm tabular-nums">{item.score}</span>
                              </div>
                              {#if barWidth !== null}
                                <div
                                  class="mt-1 h-1.5 overflow-hidden rounded-full bg-border"
                                  role="img"
                                  aria-label="{item.area_name}: {item.score} converted score"
                                >
                                  <div
                                    class="h-full rounded-full bg-primary"
                                    style="width: {barWidth}%"
                                  ></div>
                                </div>
                              {:else}
                                <p class="mt-1 text-xs text-muted-foreground">
                                  Descriptor value — no numeric bar.
                                </p>
                              {/if}
                            </li>
                          {/each}
                        </ul>
                        <dl class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                          {#if stage.release.released_at}
                            <div class="flex gap-1">
                              <dt class="font-medium">Released</dt>
                              <dd>{new Date(stage.release.released_at).toLocaleDateString()}</dd>
                            </div>
                          {/if}
                          {#if stage.release.version}
                            <div class="flex gap-1">
                              <dt class="font-medium">Version</dt>
                              <dd>{stage.release.version}</dd>
                            </div>
                          {/if}
                        </dl>
                        <p class="mt-2 text-xs text-muted-foreground">
                          Unit: converted score · source: released result document. Scores are
                          informational; the official result sheet is released by the Registrar.
                        </p>
                      </div>
                    {/if}

                    {#if stage.actions?.length}
                      <div class="mt-2.5 flex flex-wrap gap-2">
                        {#each stage.actions as action (action.key)}
                          {#if action.primary}
                            <Link href={action.href}>
                              <Button class="min-h-10">{action.label}</Button>
                            </Link>
                          {:else}
                            <Link href={action.href}>
                              <Button variant="outline" class="min-h-10">{action.label}</Button>
                            </Link>
                          {/if}
                        {/each}
                      </div>
                    {/if}
                  </div>
                </li>
              {/each}
            </ol>
          </Card.Content>
        </Card.Root>
      {/if}

      {#if quick_links.length > 0}
        <div>
          <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
            Quick links
          </p>
          <div class="flex flex-wrap gap-2">
            {#each quick_links as link (link.key)}
              {@const Icon = quickLinkIcon(link.key)}
              <Link href={link.href}>
                <Button variant="outline" class="min-h-11 gap-2">
                  <Icon class="size-4" aria-hidden="true" />
                  {link.label}
                </Button>
              </Link>
            {/each}
          </div>
        </div>
      {/if}
    {/if}
  </div>
</ApplicantLayout>
