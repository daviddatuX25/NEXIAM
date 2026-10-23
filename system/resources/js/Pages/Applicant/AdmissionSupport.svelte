<script lang="ts">
  import { Link, router, usePage } from '@inertiajs/svelte';
  import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
  import CaseConversation from '@/Components/Admission/CaseConversation.svelte';
  import OfficeInquiryWorkspace from '@/Components/Admission/OfficeInquiryWorkspace.svelte';
  import { onMount } from 'svelte';
  import NavBadge from '@/Components/kit/NavBadge.svelte';
  import { subscribeRealtimePulse } from '@/lib/realtime-pulse.svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Badge } from '@/Components/ui/badge';
  import {
    ArrowLeft,
    Clock,
    History,
    MessageSquare,
    Search,
    ShieldAlert,
    ShieldCheck,
    X,
  } from 'lucide-svelte';

  import type { CaseConversationSnapshot } from '@/lib/case-conversation';
  import type { PublicFaqCategory, PublicFaqItem } from '@/lib/public-faq';

  type MessageGroup = 'program_support' | 'offices';
  type ApplicantInquiryDraft = {
    reason: 'exam_time_extension' | 'program_considering' | 'program_changed' | 'program_enrolled_elsewhere' | 'program_help';
    principal_key: string;
    attempt_key: string;
    office: string;
    subject: string;
    body: string;
  };
  type ApplicantMessagingUrls = {
    index: string;
    access_history?: string;
    snapshot?: string | null;
    send?: string | null;
    read?: string | null;
    create?: string | null;
    show?: Record<string, string>;
    resolve?: string | null;
    return?: string | null;
  };
  type CaseRow = {
    id: string;
    subject: string;
    status?: string | null;
    unread_count?: number;
    last_activity_at?: string | null;
    snapshot_url?: string;
    send_url?: string;
    read_url?: string;
    principal_key?: string | null;
    attempt_key?: string | null;
  };
  type InquiryRow = {
    id: string;
    subject: string;
    status: string;
    unread_count: number;
    office_label: string;
    show_url: string;
    latest_message_at?: string | null;
    updated_at?: string | null;
  };
  type InquirySnapshot = CaseConversationSnapshot & {
    thread: CaseConversationSnapshot['thread'] & { office_label?: string };
  };
  type ApplicantMessagingProps = {
    groups_available?: { program_support: boolean; offices: boolean } | MessageGroup[];
    active_group?: MessageGroup;
    page_state?: string;
    cases?: CaseRow[];
    case_selected?: CaseRow | null;
    case_snapshot?: (CaseConversationSnapshot & { attempt_key?: string }) | null;
    case_search?: string | null;
    case_cursor?: string | null;
    next_case_cursor?: string | null;
    has_more?: boolean;
    inquiries?: InquiryRow[];
    next_cursor?: string | null;
    destinations?: { code: string; label: string }[];
    initial?: InquirySnapshot | null;
    inquiry_draft?: ApplicantInquiryDraft | null;
    draft_reason?: string | null;
    staff_mode?: boolean;
    office_label?: string;
    synthetic_only?: boolean;
    unavailable?: { available: false; reason: string; message: string } | null;
    office_search?: string | null;
    office_cursor?: string | null;
    faqs?: PublicFaqItem[];
    faq_categories?: PublicFaqCategory[];
    principal_key?: string;
    attempt_key?: string;
    urls?: ApplicantMessagingUrls;
    snapshotUrl?: string | null;
    sendUrl?: string | null;
    readUrl?: string | null;
  };
  type OfficeInquiryWorkspaceRef = { openNewInquiry: () => void };
  type OfficeInquiryWorkspaceUrls = {
    index: string;
    return: string | null;
    create: string | null;
    show: Record<string, string>;
    snapshot: string | null;
    send: string | null;
    read: string | null;
    resolve: string | null;
  };

  const defaultApplicantUrls: ApplicantMessagingUrls = {
    index: '/applicant/admission-support',
    access_history: '/applicant/access-history',
    snapshot: null,
    send: null,
    read: null,
    create: null,
    show: {},
    resolve: null,
  };

  let {
    groups_available = { program_support: false, offices: false },
    active_group = 'program_support',
    page_state = 'ready',
    cases = [],
    case_selected = null,
    case_snapshot = null,
    case_search = null,
    case_cursor = null,
    next_case_cursor = null,
    has_more = false,
    inquiries = [],
    next_cursor = null,
    destinations = [],
    initial = null,
    inquiry_draft = null,
    draft_reason = null,
    staff_mode = false,
    office_label = '',
    synthetic_only = true,
    unavailable = null,
    office_search = null,
    office_cursor = null,
    faqs = [],
    faq_categories = [],
    principal_key = '',
    attempt_key = '',
    urls = defaultApplicantUrls,
    snapshotUrl = null,
    sendUrl = null,
    readUrl = null,
  }: ApplicantMessagingProps = $props();
  const officeWorkspaceUrls = $derived<OfficeInquiryWorkspaceUrls>({
    index: urls.index,
    return: urls.return ?? null,
    create: urls.create ?? null,
    show: urls.show ?? {},
    snapshot: urls.snapshot ?? null,
    send: urls.send ?? null,
    read: urls.read ?? null,
    resolve: urls.resolve ?? null,
  });

  let consumedDraftReason = $state<string | null>(null);
  let officeInquiryWorkspace = $state<OfficeInquiryWorkspaceRef | null>(null);
  $effect(() => {
    const reason = draft_reason;
    if (reason !== 'exam_time_extension' || consumedDraftReason === reason) return;
    consumedDraftReason = reason;
    const current = new URL(window.location.href);
    current.searchParams.delete('draft_reason');
    router.replace({
      url: `${current.pathname}${current.search}${current.hash}`,
      props: (currentProps) => currentProps,
      preserveState: true,
      preserveScroll: true,
    });
  });

  const page = usePage();
  const sharedProps = page.props as { navigation_badges?: { groups?: { messaging?: Record<string, number> } } };
  const messagingGroups = $derived(sharedProps.navigation_badges?.groups?.messaging ?? {});

  onMount(() => {
    let navigating = false;
    const removeStartListener = router.on('start', () => { navigating = true; });
    const removeFinishListener = router.on('finish', () => { navigating = false; });
    const unsubscribe = subscribeRealtimePulse('messaging', () => {
      if (navigating) return;
      router.reload({
        only: active_group === 'offices'
          ? ['inquiries', 'next_cursor', 'urls', 'navigation_badges']
          : ['cases', 'next_case_cursor', 'has_more', 'urls', 'navigation_badges'],
        preserveState: true,
        preserveScroll: true,
      });
    });

    return () => {
      unsubscribe();
      removeStartListener();
      removeFinishListener();
    };
  });

  const canProgramSupport = $derived(
    Array.isArray(groups_available)
      ? groups_available.includes('program_support')
      : Boolean(groups_available?.program_support),
  );

  const canOffices = $derived(
    Array.isArray(groups_available)
      ? groups_available.includes('offices')
      : Boolean(groups_available?.offices),
  );

  const showGroupTabs = $derived(canProgramSupport && canOffices);

  const hasSelectedDetail = $derived(
    active_group === 'program_support'
      ? Boolean(case_selected || case_snapshot)
      : Boolean(initial),
  );

  const canCreateOfficeInquiry = $derived(
    !staff_mode
      && typeof urls?.create === 'string'
      && urls.create.trim() !== ''
      && Array.isArray(destinations)
      && destinations.length > 0,
  );

  let localSearch = $state('');

  $effect(() => {
    if (active_group === 'program_support') {
      localSearch = case_search ?? '';
    } else {
      localSearch = office_search ?? '';
    }
  });

  function handleSearchSubmit(e: SubmitEvent): void {
    e?.preventDefault();
    const params = new URLSearchParams();
    params.set('group', active_group);
    const query = localSearch.trim();
    if (active_group === 'program_support') {
      if (query) params.set('case_search', query);
    } else {
      if (query) params.set('office_search', query);
    }
    router.get(urls?.index ?? '/applicant/admission-support', Object.fromEntries(params), {
      preserveState: true,
      preserveScroll: true,
    });
  }

  function handleClearSearch() {
    localSearch = '';
    const params = new URLSearchParams();
    params.set('group', active_group);
    router.get(urls?.index ?? '/applicant/admission-support', Object.fromEntries(params), {
      preserveState: true,
      preserveScroll: true,
    });
  }

  function switchGroup(targetGroup: MessageGroup): void {
    if (targetGroup === active_group) return;
    const params = new URLSearchParams();
    params.set('group', targetGroup);
    if (targetGroup === 'program_support') {
      if (case_search) params.set('case_search', case_search);
    } else {
      if (office_search) params.set('office_search', office_search);
    }
    router.get(urls?.index ?? '/applicant/admission-support', Object.fromEntries(params), {
      preserveState: true,
      preserveScroll: true,
    });
  }

  function selectCase(id: string): void {
    const params = new URLSearchParams();
    params.set('group', 'program_support');
    if (case_search) params.set('case_search', case_search);
    if (case_cursor) params.set('case_cursor', case_cursor);
    params.set('thread_id', String(id));
    router.get(urls?.index ?? '/applicant/admission-support', Object.fromEntries(params), {
      preserveState: true,
      preserveScroll: true,
    });
  }

  function selectOffice(id: string): void {
    const params = new URLSearchParams();
    params.set('group', 'offices');
    if (office_search) params.set('office_search', office_search);
    if (office_cursor) params.set('office_cursor', office_cursor);
    params.set('inquiry_id', String(id));
    router.get(urls?.index ?? '/applicant/admission-support', Object.fromEntries(params), {
      preserveState: true,
      preserveScroll: true,
    });
  }

  function backToList() {
    const params = new URLSearchParams();
    params.set('group', active_group);
    if (active_group === 'program_support') {
      if (case_search) params.set('case_search', case_search);
      if (case_cursor) params.set('case_cursor', case_cursor);
    } else {
      if (office_search) params.set('office_search', office_search);
      if (office_cursor) params.set('office_cursor', office_cursor);
    }
    router.get(urls?.index ?? '/applicant/admission-support', Object.fromEntries(params), {
      preserveState: true,
      preserveScroll: true,
    });
  }

  function loadMoreCases() {
    if (!next_case_cursor) return;
    const params = new URLSearchParams();
    params.set('group', 'program_support');
    if (case_search) params.set('case_search', case_search);
    params.set('case_cursor', next_case_cursor);
    if (case_selected?.id) params.set('thread_id', String(case_selected.id));
    router.get(urls?.index ?? '/applicant/admission-support', Object.fromEntries(params), {
      preserveState: true,
      preserveScroll: true,
    });
  }

  function loadMoreInquiries() {
    if (!next_cursor) return;
    const params = new URLSearchParams();
    params.set('group', 'offices');
    if (office_search) params.set('office_search', office_search);
    params.set('office_cursor', next_cursor);
    if (initial?.thread?.id) params.set('inquiry_id', String(initial.thread.id));
    router.get(urls?.index ?? '/applicant/admission-support', Object.fromEntries(params), {
      preserveState: true,
      preserveScroll: true,
    });
  }

  const officeReturnUrl = $derived.by(() => {
    const params = new URLSearchParams();
    params.set('group', 'offices');
    if (office_search) params.set('office_search', office_search);
    if (office_cursor) params.set('office_cursor', office_cursor);
    return `${urls?.index ?? '/applicant/admission-support'}?${params.toString()}`;
  });

  function formatDateTime(value: string | null | undefined): string | null {
    if (!value) return null;
    try {
      const d = new Date(value);
      if (Number.isNaN(d.getTime())) return null;
      return d.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: d.getFullYear() !== new Date().getFullYear() ? 'numeric' : undefined,
      });
    } catch {
      return null;
    }
  }
</script>


<svelte:head>
  <title>Messages - NEXIAM</title>
</svelte:head>

<ApplicantLayout title="Messages">
  <div class="space-y-4">
    <!-- PAGE HEADER -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div class="space-y-0.5">
        <div class="flex items-center gap-2">
          <MessageSquare class="size-6 text-primary" aria-hidden="true" />
          <h1 class="text-2xl font-semibold tracking-tight">Messages</h1>
        </div>
        <p class="text-sm text-muted-foreground">
          Institutional admission communications and inquiry records.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <Link
          href="/faqs"
          class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
          <span>Browse FAQs</span>
        </Link>
        {#if urls?.access_history}
          <Link
            href={urls.access_history}
            class="inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          >
            <History class="size-3.5" aria-hidden="true" />
            <span>Privacy & access history</span>
          </Link>
        {/if}
      </div>
    </div>

    <!-- MAIN INTERFACE -->
    {#if !canProgramSupport && !canOffices}
      <div class="rounded-xl border border-border bg-card p-8 text-center space-y-2">
        <MessageSquare class="mx-auto size-10 text-muted-foreground/50" aria-hidden="true" />
        <h2 class="text-base font-semibold">Messaging unavailable</h2>
        <p class="text-sm text-muted-foreground max-w-md mx-auto">
          No communication channels are currently available for this account.
        </p>
      </div>
    {:else}
      <div class="flex flex-col md:flex-row min-h-[580px] rounded-xl border border-border bg-card overflow-hidden">
        <!-- LIST RAIL: visible on desktop; on mobile visible only when !hasSelectedDetail -->
        <div
          class="{hasSelectedDetail ? 'hidden md:flex' : 'flex'} w-full md:w-80 lg:w-96 shrink-0 md:border-r border-border flex-col bg-card"
        >
          <!-- GROUP SELECTOR TABS (Rendered only when both groups are allowed) -->
          {#if showGroupTabs}
            <div class="p-3 border-b border-border bg-card">
              <div
                class="grid grid-cols-2 p-1 rounded-lg bg-muted text-muted-foreground text-xs font-medium"
                role="tablist"
                aria-label="Message channels"
              >
                <button
                  type="button"
                  role="tab"
                  id="tab-program-support"
                  aria-selected={active_group === 'program_support'}
                  class="inline-flex items-center justify-center gap-2 py-1.5 px-3 rounded-md transition-all text-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring {active_group === 'program_support' ? 'bg-background text-foreground shadow-sm font-semibold' : 'hover:text-foreground'}"
                  onclick={() => switchGroup('program_support')}
                >
                  <span>Program support</span>
                  {#if messagingGroups.program_support > 0}<NavBadge badge={{ kind: 'count', count: messagingGroups.program_support, accessible_label: `${messagingGroups.program_support} unread program support messages` }} size="sm" />{/if}
                </button>
                <button
                  type="button"
                  role="tab"
                  id="tab-offices"
                  aria-selected={active_group === 'offices'}
                  class="inline-flex items-center justify-center gap-2 py-1.5 px-3 rounded-md transition-all text-center focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring {active_group === 'offices' ? 'bg-background text-foreground shadow-sm font-semibold' : 'hover:text-foreground'}"
                  onclick={() => switchGroup('offices')}
                >
                  <span>Offices</span>
                  {#if messagingGroups.offices > 0}<NavBadge badge={{ kind: 'count', count: messagingGroups.offices, accessible_label: `${messagingGroups.offices} unread office inquiries` }} size="sm" />{/if}
                </button>
            </div>
            </div>
          {:else}
            <div class="px-4 py-2.5 border-b border-border text-xs font-semibold uppercase tracking-wider text-muted-foreground">
              {active_group === 'program_support' ? 'Program support' : 'Office inquiries'}
            </div>
          {/if}

          <!-- SUBJECT SEARCH -->
          <form onsubmit={handleSearchSubmit} class="flex items-center gap-2 border-b border-border p-2.5">
            <div class="relative min-w-0 flex-1">
              <Search class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
              <Input
                type="search"
                placeholder="Search subject..."
                bind:value={localSearch}
                class="h-9 pl-8 pr-8 text-sm"
                aria-label="Search subject"
              />
              {#if localSearch}
                <button
                  type="button"
                  aria-label="Clear search"
                  onclick={handleClearSearch}
                  class="absolute right-2 top-1/2 -translate-y-1/2 size-5 flex items-center justify-center text-muted-foreground hover:text-foreground focus-visible:outline-none"
                >
                  <X class="size-3.5" aria-hidden="true" />
                </button>
              {/if}
            </div>
            <Button type="submit" variant="secondary" size="sm" class="h-9 px-3 text-xs" disabled={false}>
              Search
            </Button>
          </form>
          {#if active_group === 'offices' && !initial && canCreateOfficeInquiry}
            <div class="border-b border-border p-2.5 md:hidden">
              <Button
                type="button"
                class="min-h-[44px] w-full"
                disabled={false}
                onclick={() => officeInquiryWorkspace?.openNewInquiry()}
              >
                New inquiry
              </Button>
            </div>
          {/if}

          <!-- LIST OF ROWS -->
          <div class="flex-1 min-h-0 overflow-y-auto">
            {#if active_group === 'program_support'}
              {#if cases.length === 0}
                <div class="p-6 text-center text-sm text-muted-foreground space-y-1">
                  <p class="font-medium text-foreground">No conversations found</p>
                  <p class="text-xs">
                    {case_search ? 'No conversations match your search.' : 'Support conversations opened by program staff will appear here.'}
                  </p>
                </div>
              {:else}
                <ul class="divide-y divide-border/60">
                  {#each cases as row (row.id)}
                    {@const isSelected = String(case_selected?.id) === String(row.id) || String(case_snapshot?.thread?.id) === String(row.id)}
                    {@const unreadCount = row.unread_count ?? 0}
                    <li>
                      <button
                        type="button"
                        class="w-full text-left p-3.5 transition-colors hover:bg-muted/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring {isSelected ? 'bg-primary/10 border-l-2 border-l-primary font-medium' : ''}"
                        onclick={() => selectCase(row.id)}
                      >
                        <div class="flex items-start justify-between gap-2">
                          <span class="text-sm truncate text-foreground leading-snug">{row.subject}</span>
                          <Badge variant={row.status === 'open' ? 'outline' : 'secondary'} class="text-[11px] shrink-0 font-normal">
                            {row.status}
                          </Badge>
                        </div>
                        <div class="mt-1 flex items-center justify-between text-xs text-muted-foreground">
                          {#if formatDateTime(row.last_activity_at)}
                            <span>{formatDateTime(row.last_activity_at)}</span>
                          {:else}
                            <span>No messages</span>
                          {/if}
                          {#if unreadCount > 0}
                            <Badge variant="default" class="text-[10px] px-1.5 py-0 h-4 font-semibold">
                              {unreadCount} new
                            </Badge>
                          {/if}
                        </div>
                      </button>
                    </li>
                  {/each}
                </ul>
                {#if next_case_cursor}
                  <div class="p-3 border-t border-border text-center">
                    <Button type="button" variant="ghost" size="sm" onclick={loadMoreCases} class="w-full text-xs h-8" disabled={false}>
                      Load older conversations
                    </Button>
                  </div>
                {/if}
              {/if}
            {:else if active_group === 'offices'}
              {#if inquiries.length === 0}
                <div class="p-6 text-center text-sm text-muted-foreground space-y-1">
                  <p class="font-medium text-foreground">No inquiries found</p>
                  <p class="text-xs">
                    {office_search ? 'No inquiries match your search.' : 'Direct inquiries to campus offices will appear here.'}
                  </p>
                </div>
              {:else}
                <ul class="divide-y divide-border/60">
                  {#each inquiries as inquiry (inquiry.id)}
                    {@const isSelected = String(initial?.thread?.id) === String(inquiry.id)}
                    <li>
                      <button
                        type="button"
                        class="w-full text-left p-3.5 transition-colors hover:bg-muted/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring {isSelected ? 'bg-primary/10 border-l-2 border-l-primary font-medium' : ''}"
                        onclick={() => selectOffice(inquiry.id)}
                      >
                        <div class="flex items-start justify-between gap-2">
                          <div class="min-w-0">
                            {#if inquiry.office_label}
                              <span class="text-[10px] font-semibold tracking-wider uppercase text-primary block truncate">
                                {inquiry.office_label}
                              </span>
                            {/if}
                            <span class="text-sm truncate text-foreground block leading-snug">
                              {inquiry.subject}
                            </span>
                          </div>
                          <Badge variant={inquiry.status === 'open' ? 'outline' : 'secondary'} class="text-[11px] shrink-0 font-normal">
                            {inquiry.status}
                          </Badge>
                        </div>
                        <div class="mt-1 flex items-center justify-between text-xs text-muted-foreground">
                          {#if formatDateTime(inquiry.updated_at)}
                            <span>{formatDateTime(inquiry.updated_at)}</span>
                          {:else}
                            <span>No messages</span>
                          {/if}
                          {#if inquiry.unread_count > 0}
                            <Badge variant="default" class="text-[10px] px-1.5 py-0 h-4 font-semibold">
                              {inquiry.unread_count} new
                            </Badge>
                          {/if}
                        </div>
                      </button>
                    </li>
                  {/each}
                </ul>
                {#if next_cursor}
                  <div class="p-3 border-t border-border text-center">
                    <Button type="button" variant="ghost" size="sm" onclick={loadMoreInquiries} class="w-full text-xs h-8" disabled={false}>
                      Load older inquiries
                    </Button>
                  </div>
                {/if}
              {/if}
            {/if}
          </div>
        </div>

        <!-- DETAIL PANE: visible on desktop; on mobile visible only when hasSelectedDetail -->
        <div
          class="{hasSelectedDetail ? 'flex' : 'hidden md:flex'} flex-1 flex-col min-w-0 bg-background/50"
        >
          {#if active_group === 'program_support'}
            {#if case_snapshot}
              <div class="flex flex-col h-full min-w-0">
                <!-- Mobile Back bar -->
                <div class="md:hidden flex items-center justify-between p-3 border-b border-border bg-card">
                  <Button type="button" variant="ghost" size="sm" onclick={backToList} class="gap-1.5 text-xs h-8" disabled={false}>
                    <ArrowLeft class="size-3.5" aria-hidden="true" />
                    <span>Back to conversations</span>
                  </Button>
                  {#if case_selected?.status}
                    <Badge variant={case_selected.status === 'open' ? 'outline' : 'secondary'} class="text-xs">
                      {case_selected.status}
                    </Badge>
                  {/if}
                </div>

                <!-- Conversation component -->
                <div class="flex-1 min-h-0 overflow-y-auto p-4 sm:p-6">
                  {#key case_selected?.id ?? case_snapshot.thread?.id}
                    <CaseConversation
                      initial={case_snapshot}
                      snapshotUrl={snapshotUrl ?? urls?.snapshot ?? case_selected?.snapshot_url ?? ''}
                      sendUrl={sendUrl ?? urls?.send ?? case_selected?.send_url ?? ''}
                      readUrl={readUrl ?? urls?.read ?? case_selected?.read_url ?? ''}
                      principalContext={principal_key || case_selected?.principal_key || null}
                      attemptContext={attempt_key || case_snapshot?.attempt_key || case_selected?.attempt_key || null}
                    />
                  {/key}
                </div>
              </div>
            {:else}
              <div class="flex-1 flex flex-col items-center justify-center p-8 text-center text-muted-foreground space-y-2">
                <MessageSquare class="size-10 stroke-1 text-muted-foreground/60" aria-hidden="true" />
                <h3 class="font-medium text-foreground">Select a conversation</h3>
                <p class="text-xs max-w-sm">
                  Choose a conversation from the list to view messages and replies.
                </p>
              </div>
            {/if}
          {:else if active_group === 'offices'}
            {#if initial}
              <div class="flex flex-col h-full min-w-0">
                <!-- Mobile Back bar -->
                <div class="md:hidden flex items-center justify-between p-3 border-b border-border bg-card">
                  <Button type="button" variant="ghost" size="sm" onclick={backToList} class="gap-1.5 text-xs h-8" disabled={false}>
                    <ArrowLeft class="size-3.5" aria-hidden="true" />
                    <span>Back to inquiries</span>
                  </Button>
                  {#if initial.thread?.status}
                    <Badge variant={initial.thread.status === 'open' ? 'outline' : 'secondary'} class="text-xs">
                      {initial.thread.status}
                    </Badge>
                  {/if}
                </div>

                <!-- Office workspace detail -->
                <div class="flex-1 min-h-0 overflow-y-auto p-4 sm:p-6">
                  <OfficeInquiryWorkspace
                    {inquiries}
                    {faqs}
                    {next_cursor}
                    {destinations}
                    {initial}
                    inquiry_draft={inquiry_draft}
                    {staff_mode}
                    {office_label}
                    {synthetic_only}
                    {unavailable}
                    {principal_key}
                    {attempt_key}
                    urls={officeWorkspaceUrls}
                    embedded={true}
                    return_url={officeReturnUrl}
                  />
                </div>
              </div>
            {:else}
              <!-- Standalone/unselected workspace or placeholder -->
              <div class="flex-1 min-h-0 overflow-y-auto p-4 sm:p-6">
                {#if (destinations && destinations.length > 0) || unavailable}
                  {#if draft_reason && !inquiry_draft}
                    <p role="status" class="mb-3 rounded-md border border-amber-600/30 bg-amber-600/5 p-3 text-sm text-muted-foreground">
                      An editable draft is not available for this office inquiry path. Nothing was sent or changed. Select an available office or contact it directly.
                    </p>
                  {/if}
                  <OfficeInquiryWorkspace bind:this={officeInquiryWorkspace}
                    {inquiries}
                    {faqs}
                    {next_cursor}
                    {destinations}
                    inquiry_draft={inquiry_draft}
                    initial={null}
                    {staff_mode}
                    {office_label}
                    {synthetic_only}
                    {unavailable}
                    {principal_key}
                    {attempt_key}
                    urls={officeWorkspaceUrls}
                    embedded={true}
                    return_url={officeReturnUrl}
                  />
                {:else}
                  <div class="flex-1 flex flex-col items-center justify-center p-8 text-center text-muted-foreground space-y-2">
                    <MessageSquare class="size-10 stroke-1 text-muted-foreground/60" aria-hidden="true" />
                    <h3 class="font-medium text-foreground">Select an inquiry</h3>
                    <p class="text-xs max-w-sm">
                      Choose an inquiry from the list to view replies and communicate with campus offices.
                    </p>
                  </div>
                {/if}
              </div>
            {/if}
          {/if}
        </div>
      </div>
    {/if}
  </div>
</ApplicantLayout>
