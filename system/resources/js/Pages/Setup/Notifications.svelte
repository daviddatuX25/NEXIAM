<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import ActiveFilterChips from '@/Components/kit/ActiveFilterChips.svelte';
  import SimplePagination from '@/Components/kit/SimplePagination.svelte';
  import * as Card from '@/Components/ui/card';
  import { Badge } from '@/Components/ui/badge';
  import * as Table from '@/Components/ui/table';
  import { Link, router } from '@inertiajs/svelte';
  import {
    AlertCircle,
    ArrowRight,
    Bell,
    CalendarDays,
    CheckCircle2,
    FileText,
    Inbox,
    Mail,
    Sliders,
    Users,
  } from 'lucide-svelte';

  let {
    master_controls = {
      outbound_email_enabled: true,
      portal_inbox_enabled: true,
      staff_action_alerts_enabled: true,
      can_manage: false,
      cycle_label: 'Current Cycle',
    },
    domain_policies = {},
    activity = { data: [] },
    domain_cards = {},
    filters = {},
  } = $props();

  let controls = $state({ ...master_controls });
  const allChannelsOperational = $derived(
    controls.outbound_email_enabled && controls.portal_inbox_enabled && controls.staff_action_alerts_enabled,
  );
  const activityPage = $derived(activity?.data ? activity : { data: [] });
  const rows = $derived(activityPage.data ?? []);
  let search = $state(filters.search ?? '');
  let status = $state(filters.status ?? '');
  let domain = $state(filters.domain ?? '');
  let channel = $state(filters.channel ?? '');
  let from = $state(filters.from ?? '');
  let to = $state(filters.to ?? '');
  let timer;

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'Email & Notifications' },
  ];

  const channelControls = [
    {
      key: 'outbound_email_enabled',
      label: 'Outbound Email (SMTP)',
      description: 'Deliver status notices, permits, and official letters to applicant and staff email addresses.',
      icon: Mail,
    },
    {
      key: 'portal_inbox_enabled',
      label: 'Applicant Portal Inbox',
      description: 'Publish notices, exam permits, and scorecards directly into applicant portal accounts.',
      icon: Inbox,
    },
    {
      key: 'staff_action_alerts_enabled',
      label: 'Staff Action Alerts',
      description: 'Notify admissions evaluators and guidance staff of queued dossiers and room assignments.',
      icon: Bell,
    },
  ];

  const cards = $derived([
    {
      key: 'admissions',
      title: 'Admissions Desk',
      office: "Registrar's Office",
      url: domain_policies.admissions?.url ?? domain_cards.admissions?.url ?? '/setup/notifications/admissions',
      canAccess: domain_cards.admissions?.can_access ?? domain_policies.admissions?.can_access ?? false,
      count: domain_cards.admissions?.count,
      icon: Users,
      policies: [
        { label: 'Status Change Notices', active: domain_policies.admissions?.applicant_status_email_enabled ?? true },
        { label: 'Registrar Arrival Alert', active: domain_policies.admissions?.registrar_new_submission_alert_enabled ?? true },
        { label: 'Official Offer Letter PDF', attached: domain_policies.admissions?.attach_pdf_document ?? true },
      ],
      action: 'Open Admissions Desk',
    },
    {
      key: 'examination',
      title: 'Examinations Desk',
      office: 'Guidance & Testing Center',
      url: domain_policies.examination?.url ?? domain_cards.examination?.url ?? '/setup/notifications/examination',
      canAccess: domain_cards.examination?.can_access ?? domain_policies.examination?.can_access ?? false,
      count: domain_cards.examination?.count,
      icon: CalendarDays,
      policies: [
        { label: 'Schedule Publication Notices', active: domain_policies.examination?.publication_email_enabled ?? true },
        { label: reminderSummary(domain_policies.examination?.reminder_offsets_minutes), active: domain_policies.examination?.reminder_email_enabled ?? true },
        { label: 'Examination Permit PDF', attached: domain_policies.examination?.attach_pdf_document ?? true },
      ],
      action: 'Open Examination Desk',
    },
    {
      key: 'results',
      title: 'Results & Counseling Desk',
      office: 'Guidance Center',
      url: domain_policies.results?.url ?? domain_cards.results?.url ?? '/setup/notifications/results',
      canAccess: domain_cards.results?.can_access ?? domain_policies.results?.can_access ?? false,
      count: domain_cards.results?.count,
      icon: FileText,
      policies: [
        { label: 'Portal Score Release', active: domain_policies.results?.candidate_portal_release_enabled ?? true },
        { label: 'Consultation Notices', governed: true },
        { label: 'Official Scorecard PDF', attached: domain_policies.results?.attach_pdf_document ?? true },
      ],
      action: 'Open Results Desk',
    },
  ].filter((card) => card.canAccess));

  const activeFilterChips = $derived([
    ...(search ? [{ id: 'search', label: `Search: ${search}` }] : []),
    ...(status ? [{ id: 'status', label: `Status: ${statusLabel(status)}` }] : []),
    ...(domain ? [{ id: 'domain', label: `Domain: ${domainLabel(domain)}` }] : []),
    ...(channel ? [{ id: 'channel', label: `Channel: ${channelLabel(channel)}` }] : []),
    ...(from ? [{ id: 'from', label: `From: ${from}` }] : []),
    ...(to ? [{ id: 'to', label: `To: ${to}` }] : []),
  ]);

  function reminderSummary(offsets) {
    if (!Array.isArray(offsets)) return 'Countdown Reminders (48h/24h)';
    const has48Hours = offsets.includes(2880);
    const has24Hours = offsets.includes(1440);
    if (has48Hours && has24Hours) return 'Countdown Reminders (48h/24h)';
    if (has48Hours) return 'Countdown Reminder (48h)';
    if (has24Hours) return 'Countdown Reminder (24h)';
    return 'Countdown Reminders';
  }

  function toggleMasterControl(key) {
    if (!master_controls.can_manage) return;
    const previous = controls[key];
    const next = {
      outbound_email_enabled: controls.outbound_email_enabled,
      portal_inbox_enabled: controls.portal_inbox_enabled,
      staff_action_alerts_enabled: controls.staff_action_alerts_enabled,
      [key]: !controls[key],
    };
    controls[key] = next[key];
    router.post('/setup/notifications/controls', next, {
      preserveScroll: true,
      onError: () => {
        controls[key] = previous;
      },
    });
  }

  function statusLabel(value) {
    return {
      pending: 'Queued',
      queued: 'Queued',
      processing: 'Queued',
      in_progress: 'Queued',
      retrying: 'Queued',
      retry_scheduled: 'Queued',
      delivered: 'Delivered',
      accepted_mail: 'Delivered',
      available_in_app: 'Delivered',
      failed: 'Bounced / Failed',
      failed_terminal: 'Bounced / Failed',
      outcome_unknown: 'Bounced / Failed',
      superseded: 'Outdated',
      unknown: 'Bounced / Failed',
      mixed: 'Queued',
    }[value] ?? value;
  }

  function domainLabel(value) {
    return { admissions: 'Admissions', examination: 'Examinations', results: 'Results' }[value] ?? value;
  }

  function channelLabel(value) {
    return { in_app: 'Portal Inbox', mail: 'Email' }[value] ?? value;
  }

  function applyFilters() {
    router.get('/setup/notifications', {
      search: search || undefined,
      status: status || undefined,
      domain: domain || undefined,
      channel: channel || undefined,
      from: from || undefined,
      to: to || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
  }

  function scheduleFilters() {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 250);
  }

  function clearFilter(id) {
    if (id === 'search') search = '';
    if (id === 'status') status = '';
    if (id === 'domain') domain = '';
    if (id === 'channel') channel = '';
    if (id === 'from') from = '';
    if (id === 'to') to = '';
    applyFilters();
  }

  function clearAllFilters() {
    search = '';
    status = '';
    domain = '';
    channel = '';
    from = '';
    to = '';
    applyFilters();
  }

  function occurrenceHref(row) {
    return `/setup/notifications/occurrences/${encodeURIComponent(row.occurrence_id)}`;
  }

  function statusTone(value) {
    if (['delivered', 'accepted_mail', 'available_in_app'].includes(value)) {
      return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }
    if (['failed', 'failed_terminal', 'outcome_unknown', 'unknown'].includes(value)) {
      return 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300';
    }
    if (['superseded'].includes(value)) {
      return 'border-muted-foreground/20 bg-muted text-muted-foreground';
    }
    return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300';
  }

  function occurredAt(value) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
  }
</script>

<svelte:head><title>Email &amp; Notifications · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-8 px-4 pb-40 pt-20 sm:px-6 sm:pb-40 sm:pt-24">
    <PageHeaderBar
      title="Email & Notifications"
      description="System delivery channels, office notice policies, and recent dispatch activity."
    />

    <section aria-labelledby="delivery-channels-heading" class="space-y-4">
      <Card.Root class="overflow-hidden border-border/70 bg-card shadow-xs">
        <Card.Header class="border-b border-border/60 bg-muted/20 p-5 sm:p-6">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-3">
              <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                <Sliders class="size-5" />
              </span>
              <div class="space-y-1">
                <Card.Title id="delivery-channels-heading" class="text-lg">Delivery Channels</Card.Title>
                <Card.Description>
                  System channels active for {master_controls.cycle_label ?? 'the active academic cycle'}.
                </Card.Description>
              </div>
            </div>
            {#if allChannelsOperational}
              <Badge variant="outline" class="border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">
                All Channels Operational
              </Badge>
            {:else}
              <Badge variant="outline" class="border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300">
                Channels Restricted / Attention
              </Badge>
            {/if}
          </div>
        </Card.Header>
        <Card.Content class="grid divide-y divide-border/60 p-0 md:grid-cols-3 md:divide-x md:divide-y-0">
          {#each channelControls as item (item.key)}
            <div class="flex min-h-28 items-start gap-3.5 p-5 sm:p-6">
              <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <item.icon class="size-4" />
              </span>
              <div class="min-w-0 flex-1 space-y-1.5">
                <div class="flex items-center justify-between gap-2">
                  <p class="text-sm font-semibold text-foreground">{item.label}</p>
                  {#if controls[item.key]}
                    <button
                      type="button"
                      class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400 hover:underline cursor-pointer disabled:cursor-default disabled:no-underline"
                      onclick={() => toggleMasterControl(item.key)}
                      disabled={!master_controls.can_manage}
                      title={master_controls.can_manage ? `Click to toggle ${item.label}` : 'Requires management authority'}
                    >
                      <span class="size-1.5 rounded-full bg-emerald-500"></span> Active
                    </button>
                  {:else}
                    <button
                      type="button"
                      class="inline-flex items-center gap-1.5 text-xs font-medium text-amber-600 dark:text-amber-400 hover:underline cursor-pointer disabled:cursor-default disabled:no-underline"
                      onclick={() => toggleMasterControl(item.key)}
                      disabled={!master_controls.can_manage}
                      title={master_controls.can_manage ? `Click to toggle ${item.label}` : 'Requires management authority'}
                    >
                      <span class="size-1.5 rounded-full bg-amber-500"></span> Inactive
                    </button>
                  {/if}
                </div>
                <p class="text-xs leading-relaxed text-muted-foreground">{item.description}</p>
              </div>
            </div>
          {/each}
        </Card.Content>
      </Card.Root>
    </section>

    <section aria-labelledby="operational-desks-heading" class="space-y-4">
      <div class="flex flex-wrap items-end justify-between gap-2">
        <div class="space-y-1">
          <h2 id="operational-desks-heading" class="text-lg font-semibold tracking-tight text-foreground">Operational Desks</h2>
          <p class="text-sm text-muted-foreground">Notice templates, countdown schedules, and document attachments managed by each office.</p>
        </div>
      </div>
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        {#each cards as card (card.key)}
          <Card.Root class="flex h-full flex-col overflow-hidden border-border/70 bg-card shadow-sm">
            <Card.Header class="border-b border-border/60 pb-4">
              <div class="flex items-start justify-between gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"><card.icon class="size-5" /></span>
                <Badge variant="outline" class="max-w-[75%] text-right text-[10px] leading-relaxed">{card.office}</Badge>
              </div>
              <Card.Title class="pt-2 text-base">{card.title}</Card.Title>
            </Card.Header>
            <Card.Content class="flex flex-1 flex-col gap-4 pt-4">
              <div class="space-y-3">
                {#each card.policies as policy (policy.label)}
                  <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="text-muted-foreground">{policy.label}</span>
                    {#if policy.governed}
                      <Badge variant="outline" class="shrink-0 border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300">Workflow Governed</Badge>
                    {:else if policy.attached}
                      <span class="inline-flex shrink-0 items-center gap-1.5 text-xs font-medium text-emerald-700 dark:text-emerald-300"><CheckCircle2 class="size-3.5" />Attached</span>
                    {:else if policy.active}
                      <span class="inline-flex shrink-0 items-center gap-1.5 text-xs font-medium text-emerald-700 dark:text-emerald-300"><CheckCircle2 class="size-3.5" />Active</span>
                    {:else}
                      <span class="inline-flex shrink-0 items-center gap-1.5 text-xs font-medium text-muted-foreground"><AlertCircle class="size-3.5" />Paused</span>
                    {/if}
                  </div>
                {/each}
              </div>
              {#if card.canAccess && card.count !== undefined}
                <div class="mt-auto rounded-lg border border-border/60 bg-muted/30 p-3">
                  <p class="text-xs text-muted-foreground">Scoped notification occurrences</p>
                  <p class="mt-1 text-lg font-semibold tabular-nums text-foreground">{card.count}</p>
                </div>
              {/if}
              <div class="border-t border-border/60 pt-4">
                <Link href={card.url} class="inline-flex min-h-10 w-full items-center justify-between rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-primary-foreground shadow-sm transition hover:brightness-105 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                  {card.action}<ArrowRight class="size-4" />
                </Link>
              </div>
            </Card.Content>
          </Card.Root>
        {/each}
      </div>
    </section>

    <section aria-labelledby="activity-heading" class="space-y-4">
      <div class="space-y-1">
        <h2 id="activity-heading" class="text-lg font-semibold tracking-tight text-foreground">Recent Dispatch Activity</h2>
        <p class="text-sm text-muted-foreground">Record of recent candidate and staff notifications across all offices.</p>
      </div>

      <div class="grid gap-3 rounded-xl border border-border/70 bg-card p-4 shadow-xs sm:grid-cols-2 xl:grid-cols-6" oninput={scheduleFilters} onchange={applyFilters}>
        <label class="grid gap-1.5 text-xs font-medium text-muted-foreground sm:col-span-2" for="activity-search">
          Search notifications
          <input id="activity-search" type="search" bind:value={search} placeholder="Search safe notification summaries" class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground placeholder:text-muted-foreground" />
        </label>
        <label class="grid gap-1.5 text-xs font-medium text-muted-foreground" for="activity-domain">
          Domain
          <select id="activity-domain" bind:value={domain} class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground">
            <option value="">All authorized domains</option><option value="admissions">Admissions</option><option value="examination">Examinations</option><option value="results">Results</option>
          </select>
        </label>
        <label class="grid gap-1.5 text-xs font-medium text-muted-foreground" for="activity-status">
          Status
          <select id="activity-status" bind:value={status} class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground">
            <option value="">All statuses</option><option value="pending">Queued</option><option value="processing">Queued</option><option value="delivered">Delivered</option><option value="failed_terminal">Bounced / Failed</option><option value="outcome_unknown">Bounced / Failed</option><option value="superseded">Outdated</option>
          </select>
        </label>
        <label class="grid gap-1.5 text-xs font-medium text-muted-foreground" for="activity-channel">
          Channel
          <select id="activity-channel" bind:value={channel} class="h-10 rounded-md border border-input bg-background px-3 text-sm text-foreground">
            <option value="">All channels</option><option value="mail">Email</option><option value="in_app">Portal Inbox</option>
          </select>
        </label>
        <div class="grid grid-cols-2 gap-2 sm:col-span-2 xl:col-span-1">
          <label class="grid gap-1.5 text-xs font-medium text-muted-foreground" for="activity-from">From<input id="activity-from" type="date" bind:value={from} class="h-10 min-w-0 rounded-md border border-input bg-background px-2 text-sm text-foreground" /></label>
          <label class="grid gap-1.5 text-xs font-medium text-muted-foreground" for="activity-to">To<input id="activity-to" type="date" bind:value={to} class="h-10 min-w-0 rounded-md border border-input bg-background px-2 text-sm text-foreground" /></label>
        </div>
      </div>
      <ActiveFilterChips chips={activeFilterChips} onRemove={clearFilter} onClearAll={clearAllFilters} />

      <Card.Root class="overflow-hidden border-border/70 bg-card shadow-sm">
        {#if rows.length > 0}
          <div class="overflow-x-auto">
            <Table.Root class="min-w-[900px]">
              <Table.Header class="bg-muted/40"><Table.Row><Table.Head>Dispatched</Table.Head><Table.Head>Office / Domain</Table.Head><Table.Head>Notification</Table.Head><Table.Head>Recipients</Table.Head><Table.Head>Channels</Table.Head><Table.Head>Status</Table.Head><Table.Head><span class="sr-only">Actions</span></Table.Head></Table.Row></Table.Header>
              <Table.Body>
                {#each rows as row (row.occurrence_id)}
                  <Table.Row>
                    <Table.Cell class="whitespace-nowrap text-xs text-muted-foreground">{occurredAt(row.occurred_at)}</Table.Cell>
                    <Table.Cell class="font-medium text-foreground">{domainLabel(row.domain)}</Table.Cell>
                    <Table.Cell class="max-w-sm"><p class="font-medium text-foreground">{row.safe_summary}</p></Table.Cell>
                    <Table.Cell class="tabular-nums">{row.recipient_count ?? '—'}</Table.Cell>
                    <Table.Cell><div class="flex flex-wrap gap-1">{#each row.channel_summaries ?? [] as item (item.channel)}<Badge variant="outline" class="border-border/70 bg-muted/30">{channelLabel(item.channel)}{item.count != null ? ` · ${item.count}` : ''}</Badge>{/each}</div></Table.Cell>
                    <Table.Cell><Badge variant="outline" class="whitespace-nowrap {statusTone(row.status)}">{statusLabel(row.status)}</Badge></Table.Cell>
                    <Table.Cell><Link href={row.detail_url ?? occurrenceHref(row)} class="inline-flex min-h-8 items-center justify-center rounded-md border border-border/80 bg-card px-3 text-xs font-semibold text-foreground transition-colors hover:border-primary/50 hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">View Details</Link></Table.Cell>
                  </Table.Row>
                {/each}
              </Table.Body>
            </Table.Root>
          </div>
        {:else}
          <Card.Content class="flex min-h-44 flex-col items-center justify-center gap-2 p-6 text-center">
            <span class="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground"><Mail class="size-5" /></span>
            <p class="font-medium text-foreground">No recent dispatch activity found.</p>
            <p class="max-w-md text-sm text-muted-foreground">Try another search or filter, or check back when scoped activity is available.</p>
          </Card.Content>
        {/if}
      </Card.Root>
      <SimplePagination data={activityPage} />
    </section>
  </div>
</StaffLayout>
