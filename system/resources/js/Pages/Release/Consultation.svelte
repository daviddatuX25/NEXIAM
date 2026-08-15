<script>
    /* global route */
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import E2PageState from '@/Pages/Admission/E2PageState.svelte';
    import { Button } from '@/Components/ui/button';
    import { Badge } from '@/Components/ui/badge';
    import * as Card from '@/Components/ui/card';
    import { Input } from '@/Components/ui/input';
    import { Link, router } from '@inertiajs/svelte';
    import { Calendar, QrCode, Search, UserCheck, Clock3, UsersRound, DoorOpen } from 'lucide-svelte';
    import { openScanModal } from '@/lib/scan-modal';

    let { page_state = 'ready', dashboard = null, can_plan = false, search_query = '', search_results = [] } = $props();

    const sessionsUrl =
        typeof route === 'function' ? route('consultation.sessions.index') : '/consultation/sessions';

    let searchTerm = $state(search_query);

    function runSearch() {
        const t = searchTerm.trim();
        if (!t) return;
        router.get('/consultation', { q: t }, { preserveState: true, replace: true });
    }

    function openApplicant(attemptId) {
        router.visit(
            typeof route === 'function'
                ? route('admission-support.show', { attempt: attemptId })
                : `/consultation/applicants/${attemptId}`,
        );
    }

    function formatDate(iso) {
        if (!iso) return null;
        return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    }
</script>

<svelte:head><title>Consultation</title></svelte:head>

<StaffLayout breadcrumbs={[{ label: 'Consultation' }]}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="font-serif text-2xl font-semibold tracking-tight text-foreground">Consultation</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Consultation progress from scheduled sessions — only correctly scheduled applicants can proceed.
                </p>
            </div>
            {#if can_plan}
                <Link href={sessionsUrl}>
                    <Button variant="outline" class="min-h-10 gap-2 font-semibold">
                        <Calendar class="size-4" />
                        Manage consultation sessions
                    </Button>
                </Link>
            {/if}
        </div>

        <E2PageState state={page_state} />

        {#if page_state === 'ready' && dashboard}
            <!-- Stats -->
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Card.Root>
                    <Card.Content class="p-4">
                        <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <UserCheck class="size-4" /> Today's scheduled
                        </p>
                        <p class="mt-1 font-serif text-3xl font-semibold">{dashboard.stats.today_scheduled}</p>
                    </Card.Content>
                </Card.Root>
                <Card.Root>
                    <Card.Content class="p-4">
                        <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <Clock3 class="size-4" /> Pending advise
                        </p>
                        <p class="mt-1 font-serif text-3xl font-semibold">{dashboard.stats.pending_advise}</p>
                    </Card.Content>
                </Card.Root>
                <Card.Root>
                    <Card.Content class="p-4">
                        <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <UsersRound class="size-4" /> Not yet scheduled
                        </p>
                        <p class="mt-1 font-serif text-3xl font-semibold">{dashboard.stats.not_yet_scheduled}</p>
                    </Card.Content>
                </Card.Root>
                <Card.Root>
                    <Card.Content class="p-4">
                        <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                            <DoorOpen class="size-4" /> Sessions open / closed
                        </p>
                        <p class="mt-1 font-serif text-3xl font-semibold">
                            {dashboard.stats.sessions_open} / {dashboard.stats.sessions_closed}
                        </p>
                    </Card.Content>
                </Card.Root>
            </div>

            <!-- Quick actions -->
            <div class="grid gap-3 sm:grid-cols-2">
                <Card.Root>
                    <Card.Content class="flex items-center justify-between gap-3 p-4">
                        <div>
                            <p class="font-semibold">Scan examinee QR</p>
                            <p class="text-sm text-muted-foreground">Opens their admission support page.</p>
                        </div>
                        <Button
                            class="gap-2"
                            onclick={() =>
                                openScanModal({
                                    key: 'admission_support',
                                    label: 'Open admission support',
                                    urlFor: (id) =>
                                        typeof route === 'function'
                                            ? route('admission-support.show', { attempt: id })
                                            : `/consultation/applicants/${id}`,
                                })
                            }
                        >
                            <QrCode class="size-4" /> Scan
                        </Button>
                    </Card.Content>
                </Card.Root>
                <Card.Root>
                    <Card.Content class="flex items-center justify-between gap-3 p-4">
                        <div>
                            <p class="font-semibold">Search applicant</p>
                            <p class="text-sm text-muted-foreground">By name or applicant no.</p>
                        </div>
                        <div class="flex w-64 items-center gap-2">
                            <Input bind:value={searchTerm} placeholder="Name or applicant no…" />
                            <Button variant="outline" class="gap-2" onclick={runSearch}>
                                <Search class="size-4" /> Go
                            </Button>
                        </div>
                    </Card.Content>
                </Card.Root>
            </div>

            <!-- Today's schedule grouped by session -->
            <Card.Root>
                <Card.Header>
                    <Card.Title>Today's schedule</Card.Title>
                    <Card.Description>
                        {formatDate(new Date().toISOString())} — applicants grouped under their assigned consultation session.
                    </Card.Description>
                </Card.Header>
                <Card.Content class="space-y-4">
                    {#if dashboard.today_schedule?.length}
                        {#each dashboard.today_schedule as session (session.id)}
                            <div class="rounded-md border bg-card/60 p-3">
                                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                    <p class="font-medium">
                                        {session.time} · {session.room ?? '—'}
                                        <Badge variant="outline" class="ml-2">{session.assigned_count} assigned</Badge>
                                    </p>
                                    <Link href={typeof route === 'function' ? route('consultation.sessions.show', { session: session.id }) : '#'}>
                                        <Button variant="ghost" size="sm">Open session</Button>
                                    </Link>
                                </div>
                                <ul class="divide-y rounded-md bg-background/60">
                                    {#each session.applicants as a (a.attempt_id)}
                                        <li>
                                            <button
                                                class="flex min-h-12 w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left text-sm transition-colors hover:bg-muted/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                onclick={() => openApplicant(a.attempt_id)}
                                            >
                                                <span class="min-w-0" >
                                                    <span class="block truncate font-medium">{a.name}</span>
                                                    <span class="block text-xs text-muted-foreground">{a.reference_code}</span>
                                                </span>
                                                {#if a.advised}
                                                    <Badge class="shrink-0 gap-1"><UserCheck class="size-3" /> advised</Badge>
                                                {:else}
                                                    <Badge variant="secondary" class="shrink-0">awaiting advise</Badge>
                                                {/if}
                                            </button>
                                        </li>
                                    {/each}
                                </ul>
                            </div>
                        {/each}
                    {:else}
                        <p class="py-4 text-sm text-muted-foreground">No consultation sessions scheduled for today.</p>
                    {/if}
                </Card.Content>
            </Card.Root>

            <!-- Search results -->
            {#if search_query}
                <Card.Root>
                    <Card.Header>
                        <Card.Title>Search results for "{search_query}"</Card.Title>
                    </Card.Header>
                    <Card.Content>
                        {#if search_results.length}
                            <ul class="divide-y">
                                {#each search_results as a (a.attempt_id)}
                                    <li>
                                        <button
                                            class="flex min-h-12 w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left text-sm transition-colors hover:bg-muted/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            onclick={() => openApplicant(a.attempt_id)}
                                        >
                                            <span class="min-w-0"><span class="block truncate font-medium">{a.name}</span><span class="block text-xs text-muted-foreground">{a.reference_code}</span></span>
                                            <Badge variant="outline" class="shrink-0">open support</Badge>
                                        </button>
                                    </li>
                                {/each}
                            </ul>
                        {:else}
                            <p class="py-3 text-sm text-muted-foreground">No matching applicants.</p>
                        {/if}
                    </Card.Content>
                </Card.Root>
            {/if}

            <!-- Not yet scheduled -->
            {#if dashboard.not_yet_scheduled?.length}
                <Card.Root>
                    <Card.Header>
                        <Card.Title>Not yet scheduled</Card.Title>
                        <Card.Description>Result-ready, advised applicants without a consultation assignment.</Card.Description>
                    </Card.Header>
                    <Card.Content>
                        <ul class="divide-y">
                            {#each dashboard.not_yet_scheduled as a (a.attempt_id)}
                                <li>
                                    <button
                                        class="flex min-h-12 w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left text-sm transition-colors hover:bg-muted/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        onclick={() => openApplicant(a.attempt_id)}
                                    >
                                        <span class="min-w-0"><span class="block truncate font-medium">{a.name}</span><span class="block text-xs text-muted-foreground">{a.reference_code}</span></span>
                                        <Badge variant="outline" class="shrink-0">ready</Badge>
                                    </button>
                                </li>
                            {/each}
                        </ul>
                    </Card.Content>
                </Card.Root>
            {/if}
        {/if}
    </div>
</StaffLayout>
