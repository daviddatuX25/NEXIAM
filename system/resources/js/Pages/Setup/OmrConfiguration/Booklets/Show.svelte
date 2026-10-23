<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import { Link, router } from '@inertiajs/svelte';
    import {
        error as toastError,
        success as toastSuccess,
    } from '@/lib/toast.js';

    let {
        booklet = {},
        pairs = [],
        areas = [],
        capabilities = {},
        errors = {},
    } = $props();

    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Booklets', href: '/setup/omr/booklets' },
        { label: booklet.name ?? 'Booklet' },
    ];

    const versions = $derived(booklet.versions ?? []);
    const newestActive = $derived(
        versions.find((v) => v.status === 'active') ?? null,
    );
    const newestDraft = $derived(
        versions.find((v) => v.status === 'draft') ?? null,
    );

    // Toast server-side OMR errors once each (fires on mount for the initial props).
    let toastedErrors = new Set();
    $effect(() => {
        for (const [key, message] of Object.entries(errors)) {
            if (key.startsWith('omr_') && message && !toastedErrors.has(key)) {
                toastedErrors.add(key);
                toastError(message);
            }
        }
    });

    function statusVariant(status) {
        if (status === 'active') return 'default';
        if (status === 'draft') return 'secondary';
        return 'outline';
    }

    // Inline retire confirmation: { kind, id, label, pairs } — no window.confirm.
    let confirming = $state(null);
    let retiring = $state(false);

    function cascadePairs(version) {
        // Prefer explicit active_pair_ids when the backend provides them.
        if (Array.isArray(version.active_pair_ids)) {
            return version.active_pair_ids
                .map((id) => pairs.find((pair) => pair.id === id))
                .filter(Boolean);
        }
        // Fall back to booklet_version_id when present on the pair rows.
        if (pairs.some((pair) => pair.booklet_version_id != null)) {
            return pairs.filter(
                (pair) => pair.booklet_version_id === version.id,
            );
        }
        return [];
    }

    function requestRetire(kind, id, label, cascade = []) {
        confirming = { kind, id, label, pairs: cascade };
    }

    function confirmRetire() {
        if (!confirming || retiring) return;
        retiring = true;
        router.post(
            '/setup/omr/retire',
            { kind: confirming.kind, id: confirming.id },
            {
                preserveScroll: true,
                onSuccess: () => {
                    toastSuccess(`${confirming.label} retired.`);
                    confirming = null;
                },
                onError: (errs) => {
                    const first = errs.omr_retire ?? Object.values(errs)[0];
                    if (first) toastError(first);
                },
                onFinish: () => {
                    retiring = false;
                },
            },
        );
    }

    let copying = $state(false);

    function copyActiveVersion() {
        if (!newestActive || copying) return;
        copying = true;
        router.post(
            `/setup/omr/booklets/${booklet.id}/copy`,
            { version_id: newestActive.id },
            {
                preserveScroll: true,
                onSuccess: () =>
                    toastSuccess(`Draft copied from v${newestActive.version}.`),
                onError: (errs) => {
                    toastError(
                        errs.omr_booklet ??
                            Object.values(errs)[0] ??
                            'The draft could not be copied.',
                    );
                },
                onFinish: () => {
                    copying = false;
                },
            },
        );
    }

    // Key preview helpers.
    function areaGroups(questions) {
        const groups = [];
        const index = new Map();
        for (const question of questions ?? []) {
            if (!index.has(question.area_code)) {
                index.set(question.area_code, groups.length);
                groups.push({ area_code: question.area_code, questions: [] });
            }
            groups[index.get(question.area_code)].questions.push(question);
        }
        return groups;
    }

    function optionDistribution(questions) {
        const counts = { A: 0, B: 0, C: 0, D: 0 };
        for (const question of questions ?? []) {
            if (counts[question.correct_option_code] !== undefined)
                counts[question.correct_option_code] += 1;
        }
        return ['A', 'B', 'C', 'D']
            .filter((letter) => counts[letter] > 0)
            .map((letter) => `${letter} ${counts[letter]}`)
            .join(' · ');
    }
</script>

<StaffLayout {breadcrumbs}>
    <PageHeaderBar
        title={booklet.name ?? 'Booklet'}
        description={booklet.code ?? ''}
    >
        {#snippet actions()}
            {#if newestActive}
                <Button onclick={copyActiveVersion} disabled={copying}>
                    {copying ? 'Copying…' : 'New draft from this version'}
                </Button>
            {/if}
            {#if newestDraft}
                <Button asChild variant="outline">
                    <Link
                        href={`/setup/omr/booklets/create?draft=${newestDraft.id}`}
                        >Edit draft</Link
                    >
                </Button>
            {/if}
            <Button asChild variant="outline">
                <Link href="/setup/omr/booklets/create">Author new booklet</Link
                >
            </Button>
        {/snippet}
    </PageHeaderBar>

    <section class="space-y-6 p-6">
        <div>
            <h2 class="mb-3 text-lg font-semibold">Versions</h2>
            {#if versions.length === 0}
                <p class="text-muted-foreground text-sm">
                    No versions registered for this booklet yet.
                </p>
            {:else}
                <div class="space-y-4">
                    {#each versions as version (version.id)}
                        <div class="rounded-lg border p-4">
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-semibold"
                                        >v{version.version}</span
                                    >
                                    <Badge
                                        variant={statusVariant(version.status)}
                                        >{version.status}</Badge
                                    >
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="text-muted-foreground font-mono text-xs"
                                        title={version.answer_key_hash ?? ''}
                                        >{(version.answer_key_hash ?? '').slice(
                                            0,
                                            12,
                                        ) || '—'}</span
                                    >
                                    {#if version.status !== 'retired'}
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onclick={() =>
                                                requestRetire(
                                                    'booklet_version',
                                                    version.id,
                                                    `booklet version v${version.version}`,
                                                    cascadePairs(version),
                                                )}
                                        >
                                            Retire
                                        </Button>
                                    {/if}
                                </div>
                            </div>

                            {#if confirming?.kind === 'booklet_version' && confirming.id === version.id}
                                <div
                                    class="border-destructive/40 bg-destructive/5 mt-3 rounded-md border p-3 text-sm"
                                >
                                    <p class="font-medium">
                                        Retire v{version.version}?
                                    </p>
                                    {#if confirming.pairs.length > 0}
                                        <p class="text-muted-foreground mt-1">
                                            This also retires the following
                                            pair(s):
                                        </p>
                                        <ul class="mt-2 space-y-1">
                                            {#each confirming.pairs as pair (pair.id)}
                                                <li
                                                    class="text-muted-foreground"
                                                >
                                                    • {pair.label}
                                                </li>
                                            {/each}
                                        </ul>
                                    {:else}
                                        <p class="text-muted-foreground mt-1">
                                            No pairs are attached to this
                                            version.
                                        </p>
                                    {/if}
                                    <div class="mt-3 flex gap-2">
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onclick={confirmRetire}
                                            disabled={retiring}
                                        >
                                            {retiring
                                                ? 'Retiring…'
                                                : `Retire v${version.version}`}
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onclick={() => (confirming = null)}
                                            >Cancel</Button
                                        >
                                    </div>
                                </div>
                            {/if}

                            <div class="mt-3">
                                <div class="mb-1 flex items-baseline gap-3">
                                    <h3 class="text-sm font-semibold">
                                        Key preview
                                    </h3>
                                    <span class="text-muted-foreground text-xs">
                                        {(version.questions ?? []).length} question{(
                                            version.questions ?? []
                                        ).length === 1
                                            ? ''
                                            : 's'}
                                    </span>
                                </div>
                                {#if (version.questions ?? []).length === 0}
                                    <p class="text-muted-foreground text-sm">
                                        No questions in this version.
                                    </p>
                                {:else}
                                    <div class="space-y-3">
                                        {#each areaGroups(version.questions) as group (group.area_code)}
                                            <div>
                                                <p
                                                    class="text-muted-foreground text-xs font-semibold tracking-wide uppercase"
                                                >
                                                    {group.area_code} ({group
                                                        .questions.length} question{group
                                                        .questions.length === 1
                                                        ? ''
                                                        : 's'})
                                                </p>
                                                <div
                                                    class="mt-1.5 flex flex-wrap gap-1.5"
                                                >
                                                    {#each group.questions as question (question.position)}
                                                        <span
                                                            class="rounded border px-2 py-0.5 font-mono text-xs"
                                                        >
                                                            P{question.position}
                                                            <strong
                                                                class="text-primary"
                                                                >{question.correct_option_code}</strong
                                                            >
                                                        </span>
                                                    {/each}
                                                </div>
                                                <p
                                                    class="text-muted-foreground mt-1 font-mono text-xs"
                                                >
                                                    {optionDistribution(
                                                        group.questions,
                                                    )}
                                                </p>
                                            </div>
                                        {/each}
                                    </div>
                                {/if}
                            </div>
                        </div>
                    {/each}
                </div>
            {/if}
        </div>

        <div>
            <h2 class="mb-3 text-lg font-semibold">Pairs</h2>
            {#if pairs.length === 0}
                <p class="text-muted-foreground text-sm">
                    No template pairs registered for this booklet.
                </p>
            {:else}
                <div class="space-y-2">
                    {#each pairs as pair (pair.id)}
                        <div class="rounded-lg border p-3">
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="truncate text-sm"
                                        >{pair.label}</span
                                    >
                                    <Badge variant={statusVariant(pair.status)}
                                        >{pair.status}</Badge
                                    >
                                </div>
                                {#if pair.status === 'active'}
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onclick={() =>
                                            requestRetire(
                                                'pair',
                                                pair.id,
                                                `pair ${pair.label}`,
                                            )}
                                    >
                                        Retire
                                    </Button>
                                {/if}
                            </div>

                            {#if confirming?.kind === 'pair' && confirming.id === pair.id}
                                <div
                                    class="border-destructive/40 bg-destructive/5 mt-3 rounded-md border p-3 text-sm"
                                >
                                    <p class="font-medium">Retire this pair?</p>
                                    <p class="text-muted-foreground mt-1">
                                        {pair.label}
                                    </p>
                                    <div class="mt-3 flex gap-2">
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onclick={confirmRetire}
                                            disabled={retiring}
                                        >
                                            {retiring ? 'Retiring…' : 'Retire'}
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onclick={() => (confirming = null)}
                                            >Cancel</Button
                                        >
                                    </div>
                                </div>
                            {/if}
                        </div>
                    {/each}
                </div>
            {/if}
        </div>
    </section>
</StaffLayout>
