<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import { Link, useForm } from '@inertiajs/svelte';
    import { newIdempotencyKey } from '@/lib/utils.js';

    let {
        announcement = null,
        revisions = [],
        capabilities = { manage: false, publish: false, campus: false },
        publishers = [],
        programs = [],
    } = $props();

    function localInputValue(value) {
        if (!value) return '';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '';
        const local = new Date(
            date.getTime() - date.getTimezoneOffset() * 60000,
        );
        return local.toISOString().slice(0, 16);
    }

    const form = useForm({
        publisher_key: announcement ? undefined : (publishers[0]?.key ?? ''),
        program_id: announcement?.program_id ?? '',
        title: announcement?.title ?? '',
        summary: announcement?.summary ?? '',
        body: announcement?.body ?? '',
        priority: announcement?.priority ?? 'normal',
        is_featured: announcement?.is_featured ?? false,
        starts_at: localInputValue(announcement?.starts_at),
        ends_at: localInputValue(announcement?.ends_at),
        request_id: '',
    });
    let requestSignature = '';
    let requestToken = '';
    const isEdit = $derived(Boolean(announcement));
    const isPublished = $derived(announcement?.status === 'published');
    const canSave = $derived(
        announcement
            ? Boolean(announcement.can_edit)
            : Boolean(capabilities.manage),
    );
    const selectedPublisher = $derived(
        publishers.find((publisher) => publisher.key === form.publisher_key) ??
            null,
    );
    const selectedProgram = $derived(
        programs.find((program) => String(program.id) === String(form.program_id)) ??
            null,
    );
    const titleLength = $derived(String(form.title ?? '').length);
    const summaryLength = $derived(String(form.summary ?? '').length);
    const bodyLength = $derived(String(form.body ?? '').length);

    function dateAsIso(value) {
        if (!value) return null;
        const parsed = new Date(value);
        return Number.isNaN(parsed.getTime()) ? value : parsed.toISOString();
    }

    function stableRequestId(payload) {
        const signature = JSON.stringify(payload);
        if (signature !== requestSignature) {
            requestSignature = signature;
            requestToken = newIdempotencyKey();
        }
        return requestToken;
    }

    function submit(event) {
        event.preventDefault();
        const localStartsAt = form.starts_at;
        const localEndsAt = form.ends_at;
        const payload = {
            title: form.title,
            summary: form.summary,
            body: form.body,
            priority: form.priority,
            is_featured: form.is_featured,
            starts_at: dateAsIso(form.starts_at),
            ends_at: dateAsIso(form.ends_at),
        };
        if (!announcement) {
            payload.publisher_key = form.publisher_key;
            payload.program_id =
                form.program_id === '' || form.program_id === null
                    ? null
                    : Number(form.program_id);
        }
        if (announcement) payload.expected_revision = announcement.revision;
        form.request_id = stableRequestId(payload);
        form.transform((data) => ({
            title: data.title,
            summary: data.summary,
            body: data.body,
            priority: data.priority,
            is_featured: data.is_featured,
            starts_at: dateAsIso(data.starts_at),
            ends_at: dateAsIso(data.ends_at),
            request_id: data.request_id,
            ...(announcement
                ? { expected_revision: announcement.revision }
                : {
                      publisher_key: data.publisher_key,
                      program_id:
                          data.program_id === '' || data.program_id === null
                              ? null
                              : Number(data.program_id),
                  }),
        }));
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                requestSignature = '';
                requestToken = '';
            },
            onError: () => {
                form.starts_at = localStartsAt;
                form.ends_at = localEndsAt;
            },
        };
        if (announcement) {
            form.put(`/communications/${announcement.id}`, options);
        } else {
            form.post('/communications', options);
        }
    }

    const breadcrumbs = [
        { label: 'Communications' },
        { label: 'Announcements', href: '/communications' },
        { label: announcement ? 'Edit announcement' : 'Create announcement' },
    ];
</script>

<svelte:head
    ><title
        >{announcement ? 'Edit announcement' : 'Create announcement'} — Announcements</title
    ></svelte:head
>

<StaffLayout {breadcrumbs}>
        <div class="mx-auto grid w-full max-w-6xl gap-6">
            <PageHeaderBar
                title={announcement ? 'Edit announcement' : 'New announcement'}
                description={isPublished
                    ? 'Review the current notice and submit an attributable correction.'
                    : 'Write a clear public update for an authorized office or academic program.'}
            />

            {#if !canSave}
                <div
                    class="border-destructive/30 bg-destructive/5 text-foreground rounded-lg border p-4 text-sm"
                    role="alert"
                >
                    {#if announcement?.status === 'withdrawn'}This announcement was withdrawn.
                        Its content and revision history are read-only.
                    {:else if isPublished}You need publish capability to edit a
                        published announcement.{:else}You do not have permission
                        to save announcements.{/if}
                </div>
            {/if}
            {#if !isEdit && publishers.length === 0}
                <div
                    class="border-border bg-muted/30 text-muted-foreground rounded-lg border p-4 text-sm"
                    role="status"
                >
                    No authorized publishing sources are available for your
                    account.
                </div>
            {/if}
            {#if form.errors?.conflict || form.errors?.expected_revision || form.errors?.request_id}
                <div
                    class="text-foreground rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-sm"
                    role="alert"
                >
                    <p class="font-semibold">
                        This announcement changed before your save was accepted.
                    </p>
                    <p class="mt-1">
                        Your text is still here. Review the current revision
                        before trying again.
                    </p>
                    <Link
                        href="/communications"
                        class="text-primary mt-2 inline-flex min-h-[44px] items-center underline underline-offset-4"
                        >Return to Announcements</Link
                    >
                </div>
            {/if}

            <form class="grid min-w-0 gap-6 lg:grid-cols-2" onsubmit={submit}>
                <section
                    class="border-border bg-card/80 min-w-0 space-y-5 rounded-lg border p-4 sm:p-6"
                    aria-labelledby="announcement-content-title"
                >
                    <div>
                        <h2
                            id="announcement-content-title"
                            class="text-lg font-semibold"
                        >
                            Announcement content
                        </h2>
                        <p class="text-muted-foreground mt-1 text-sm">
                            Plain text only. Formatting is preserved when the
                            notice is read.
                        </p>
                    </div>
                    {#if !isEdit}
                        <div class="space-y-2">
                            <Label for="publisher_key">Publishing source</Label>
                            <select
                                id="publisher_key"
                                bind:value={form.publisher_key}
                                disabled={!canSave || form.processing}
                                class="border-input bg-background text-foreground focus-visible:ring-ring min-h-[44px] w-full rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none disabled:opacity-60"
                            >
                                {#each publishers as publisher (publisher.key)}<option
                                        value={publisher.key}
                                        >{publisher.label}</option
                                    >{/each}
                            </select>
                            {#if form.errors.publisher_key}<p
                                    class="text-destructive text-sm"
                                    role="alert"
                                >
                                    {form.errors.publisher_key}
                                </p>{/if}
                        </div>
                    {:else}
                        <div class="space-y-1">
                            <p
                                class="text-muted-foreground text-xs font-medium"
                            >
                                Publishing source
                            </p>
                            <p class="text-foreground text-sm font-semibold">
                                {announcement.publisher_label}
                            </p>
                        </div>
                    {/if}
                    {#if !isEdit}
                        <div class="space-y-2">
                            <div class="flex items-center gap-1.5">
                                <Label for="program_id">Delivery target</Label>
                                <InfoPopover
                                    title="Where this publishes"
                                    content="Office-scoped items appear on the main institutional bulletin; program-scoped items appear on that degree program's public page."
                                />
                            </div>
                            <select
                                id="program_id"
                                bind:value={form.program_id}
                                disabled={!canSave || form.processing}
                                required={Boolean(selectedPublisher?.requires_program)}
                                aria-invalid={Boolean(form.errors.program_id)}
                                class="border-input bg-background text-foreground focus-visible:ring-ring min-h-[44px] w-full rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none disabled:opacity-60"
                            >
                                <option value="" disabled={Boolean(selectedPublisher?.requires_program)}>General — no program</option>
                                {#each programs as program (program.id)}
                                    <option value={String(program.id)}>
                                        {program.code} — {program.name}
                                    </option>
                                {/each}
                            </select>
                            <p class="text-muted-foreground text-xs leading-5">
                                {selectedPublisher?.requires_program
                                    ? 'Choose the active program this academic source owns.'
                                    : 'General notices appear on the homepage. Choose a program to keep this notice within that program.'}
                            </p>
                            {#if form.errors.program_id}
                                <p class="text-destructive text-sm" role="alert">
                                    {form.errors.program_id}
                                </p>
                            {/if}
                        </div>
                    {:else}
                        <div class="space-y-1">
                            <p class="text-muted-foreground text-xs font-medium">Delivery target</p>
                            <p class="text-foreground text-sm font-semibold">
                                {announcement.program_code
                                    ? `${announcement.program_code}${selectedProgram?.name ? ` — ${selectedProgram.name}` : ''}`
                                    : 'General — no program'}
                            </p>
                            <p class="text-muted-foreground text-xs leading-5">
                                Issuing source and delivery target cannot be changed after creation.
                            </p>
                        </div>
                    {/if}

                    <div class="space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <Label for="title">Subject / short title</Label><span
                                class="text-muted-foreground text-xs tabular-nums"
                                >{titleLength}/80</span
                            >
                        </div>
                        <Input
                            id="title"
                            bind:value={form.title}
                            maxlength="80"
                            required
                            aria-invalid={Boolean(form.errors.title)}
                            disabled={!canSave || form.processing}
                            class="min-h-[44px]"
                        />
                        <p class="text-muted-foreground text-xs leading-5">
                            Use a clear, short headline for the ticker (about 6–10 words).
                        </p>
                        {#if form.errors.title}<p
                                class="text-destructive text-sm"
                                role="alert"
                            >
                                {form.errors.title}
                            </p>{/if}
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <Label for="summary">Short summary</Label><span
                                class="text-muted-foreground text-xs tabular-nums"
                                >{summaryLength}/160</span
                            >
                        </div>
                        <textarea
                            id="summary"
                            bind:value={form.summary}
                            maxlength="160"
                            required
                            rows="3"
                            aria-invalid={Boolean(form.errors.summary)}
                            disabled={!canSave || form.processing}
                            class="border-input bg-background text-foreground placeholder:text-muted-foreground focus-visible:ring-ring min-h-20 w-full resize-y rounded-md border px-3 py-2 text-sm leading-6 focus-visible:ring-2 focus-visible:outline-none disabled:opacity-60"
                        ></textarea>
                        {#if form.errors.summary}<p
                                class="text-destructive text-sm"
                                role="alert"
                            >
                                {form.errors.summary}
                            </p>{/if}
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <Label for="body">Full announcement</Label><span
                                class="text-muted-foreground text-xs tabular-nums"
                                >{bodyLength}/10,000</span
                            >
                        </div>
                        <textarea
                            id="body"
                            bind:value={form.body}
                            maxlength="10000"
                            required
                            rows="10"
                            aria-invalid={Boolean(form.errors.body)}
                            disabled={!canSave || form.processing}
                            class="border-input bg-background text-foreground placeholder:text-muted-foreground focus-visible:ring-ring min-h-64 w-full resize-y rounded-md border px-3 py-2 text-sm leading-6 focus-visible:ring-2 focus-visible:outline-none disabled:opacity-60"
                        ></textarea>
                        {#if form.errors.body}<p
                                class="text-destructive text-sm"
                                role="alert"
                            >
                                {form.errors.body}
                            </p>{/if}
                    </div>
                </section>

                <div class="min-w-0 space-y-6">
                    <section
                        class="border-border bg-card/80 space-y-5 rounded-lg border p-4 sm:p-6"
                        aria-labelledby="publication-settings-title"
                    >
                        <div>
                            <h2
                                id="publication-settings-title"
                                class="text-lg font-semibold"
                            >
                                Publication settings
                            </h2>
                            <p class="text-muted-foreground mt-1 text-sm">
                                Publishing happens from the communications list
                                after saving.
                            </p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="starts_at">Starts at</Label>
                                <Input
                                    id="starts_at"
                                    type="datetime-local"
                                    bind:value={form.starts_at}
                                    disabled={!canSave || form.processing}
                                    aria-invalid={Boolean(
                                        form.errors.starts_at,
                                    )}
                                    aria-describedby="starts-at-help"
                                    class="min-h-[44px]"
                                />
                                <p
                                    id="starts-at-help"
                                    class="text-muted-foreground text-xs"
                                >
                                    Leave blank to start when the notice is
                                    published.
                                </p>
                                {#if form.errors.starts_at}<p
                                        class="text-destructive text-sm"
                                        role="alert"
                                    >
                                        {form.errors.starts_at}
                                    </p>{/if}
                            </div>
                            <div class="space-y-2">
                                <Label for="ends_at">Ends at (optional)</Label>
                                <Input
                                    id="ends_at"
                                    type="datetime-local"
                                    bind:value={form.ends_at}
                                    disabled={!canSave || form.processing}
                                    aria-invalid={Boolean(form.errors.ends_at)}
                                    class="min-h-[44px]"
                                />
                                {#if form.errors.ends_at}<p
                                        class="text-destructive text-sm"
                                        role="alert"
                                    >
                                        {form.errors.ends_at}
                                    </p>{/if}
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label for="priority">Priority</Label>
                            <select
                                id="priority"
                                bind:value={form.priority}
                                disabled={!canSave || form.processing}
                                class="border-input bg-background text-foreground focus-visible:ring-ring min-h-[44px] w-full rounded-md border px-3 text-sm focus-visible:ring-2 focus-visible:outline-none disabled:opacity-60"
                            >
                                <option value="normal">Normal</option><option
                                    value="urgent">Urgent</option
                                >
                            </select>
                            {#if form.errors.priority}<p
                                    class="text-destructive text-sm"
                                    role="alert"
                                >
                                    {form.errors.priority}
                                </p>{/if}
                        </div>
                        <label
                            class="flex min-h-[44px] items-center gap-3 text-sm font-medium"
                        >
                            <input
                                type="checkbox"
                                bind:checked={form.is_featured}
                                disabled={!canSave || form.processing}
                                class="border-input accent-primary size-4 rounded"
                            />
                            Include in featured alerts when published
                        </label>
                        {#if form.errors.is_featured}<p
                                class="text-destructive text-sm"
                                role="alert"
                            >
                                {form.errors.is_featured}
                            </p>{/if}
                    </section>

                    <section
                        class="border-border bg-muted/25 space-y-3 rounded-lg border p-4 sm:p-6"
                        aria-labelledby="announcement-preview-title"
                    >
                        <h2
                            id="announcement-preview-title"
                            class="text-lg font-semibold"
                        >
                            Preview
                        </h2>
                        <article class="border-border/70 min-w-0 border-t pt-4">
                            <p class="text-muted-foreground text-xs">
                                {announcement?.publisher_label ??
                                    selectedPublisher?.label ??
                                    'Publishing source'}
                            </p>
                            <p class="text-muted-foreground mt-1 text-xs">
                                {announcement
                                    ? announcement.program_code
                                        ? `${announcement.program_code} program notice`
                                        : 'General notice'
                                    : selectedProgram
                                      ? `${selectedProgram.code} program notice`
                                      : selectedPublisher?.requires_program
                                        ? 'Select a required program'
                                        : 'General notice'}
                            </p>
                            <h3
                                class="text-foreground mt-2 text-xl font-semibold break-words"
                            >
                                {form.title || 'Subject preview'}
                            </h3>
                            <p
                                class="text-foreground mt-2 text-sm leading-6 break-words whitespace-pre-wrap"
                            >
                                {form.summary ||
                                    'Your short summary will appear here.'}
                            </p>
                            <p
                                class="text-muted-foreground mt-3 text-sm leading-6 break-words whitespace-pre-wrap"
                            >
                                {form.body ||
                                    'The full announcement preview will appear here.'}
                            </p>
                        </article>
                    </section>

                    {#if isEdit && revisions.length > 0}
                        <section
                            class="border-border bg-card/80 rounded-lg border p-4 sm:p-6"
                            aria-labelledby="revision-history-title"
                        >
                            <h2
                                id="revision-history-title"
                                class="text-lg font-semibold"
                            >
                                Revision history
                            </h2>
                            <ol class="divide-border/60 mt-4 divide-y">
                                {#each revisions as revision (revision.revision)}
                                    <li class="py-4 first:pt-0 last:pb-0">
                                        <div
                                            class="flex flex-wrap items-baseline justify-between gap-2"
                                        >
                                            <h3 class="font-semibold">
                                                Revision {revision.revision}: {revision.title}
                                            </h3>
                                            <time
                                                class="text-muted-foreground text-xs"
                                                datetime={revision.created_at}
                                                >{new Date(
                                                    revision.created_at,
                                                ).toLocaleString()}</time
                                            >
                                        </div>
                                        <p
                                            class="text-muted-foreground mt-1 text-sm"
                                        >
                                            {revision.status} · {revision.actor_name}
                                        </p>
                                        {#if revision.reason}<p
                                                class="text-muted-foreground mt-2 text-sm whitespace-pre-wrap"
                                            >
                                                {revision.reason}
                                            </p>{/if}
                                        <p
                                            class="text-foreground mt-2 text-sm whitespace-pre-wrap"
                                        >
                                            {revision.summary}
                                        </p>
                                        <details class="mt-2 text-sm">
                                            <summary class="cursor-pointer font-medium">Read full revision</summary>
                                            <p class="mt-2 break-words whitespace-pre-wrap">{revision.body}</p>
                                        </details>
                                    </li>
                                {/each}
                            </ol>
                        </section>
                    {/if}

                    <div
                        class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
                    >
                        <Link
                            href="/communications"
                            class="border-border inline-flex min-h-[44px] items-center justify-center rounded-lg border px-4 text-sm font-medium"
                            >Cancel</Link
                        >
                        <Button
                            type="submit"
                            disabled={!canSave ||
                                (!isEdit && publishers.length === 0) ||
                                form.processing}
                            class="min-h-[44px]"
                            >{form.processing
                                ? 'Saving…'
                                : isPublished
                                  ? 'Save correction'
                                  : isEdit
                                    ? 'Save draft'
                                    : 'Save announcement'}</Button
                        >
                    </div>
                    {#if form.errors?.message}<p
                            role="alert"
                            class="text-destructive text-sm"
                        >
                            {form.errors.message}
                        </p>{/if}
                </div>
            </form>
        </div>
</StaffLayout>
