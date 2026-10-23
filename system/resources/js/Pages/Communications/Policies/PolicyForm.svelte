<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import { Link, useForm } from '@inertiajs/svelte';
    import { newIdempotencyKey } from '@/lib/utils.js';

    let { policy = null, revisions = [], publishers = [], programs = [], current_intake_selection = null } = $props();
    function initialProgramId() {
        const publisher = publishers[0];
        const code = publisher?.requires_program ? publisher.key.slice('program:'.length) : '';
        return policy?.program_id ?? (programs.find((program) => program.code === code)?.id ?? '');
    }
    const form = useForm({
        publisher_key: policy ? undefined : (publishers[0]?.key ?? ''),
        program_id: initialProgramId(), kind: policy?.kind ?? 'policy',
        title: policy?.title ?? '', content: policy?.content ?? '', request_id: '',
    });
    let requestSignature = '';
    let requestToken = '';
    const isWithdrawn = $derived(policy?.status === 'withdrawn');
    const canSave = $derived(!isWithdrawn && (policy ? policy.can_manage : publishers.length > 0));
    const hasPendingDraft = $derived(Boolean(policy) && policy.revision > (policy.published_revision ?? 0));
    const canPublish = $derived(Boolean(policy?.can_publish && !isWithdrawn));
    const selectedPublisher = $derived(publishers.find((publisher) => publisher.key === form.publisher_key) ?? null);
    const titleLength = $derived(String(form.title ?? '').length);
    const contentLength = $derived(String(form.content ?? '').length);
    const documentDirty = $derived(Boolean(policy) && (
        String(form.kind ?? '') !== String(policy.kind ?? '') ||
        String(form.title ?? '').trim() !== policy.title ||
        String(form.content ?? '').trim() !== policy.content
    ));
    function changePublisher(event) {
        const publisherKey = event.currentTarget.value;
        form.publisher_key = publisherKey;
        const publisher = publishers.find((item) => item.key === publisherKey);
        const code = publisher?.requires_program ? publisher.key.slice('program:'.length) : '';
        form.program_id = programs.find((program) => program.code === code)?.id ?? '';
    }

    function changePlacement(event) {
        form.program_id = event.currentTarget.value;
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
        const payload = { kind: form.kind, title: form.title.trim(), content: form.content.trim() };
        if (policy) payload.expected_revision = policy.revision;
        else {
            payload.publisher_key = form.publisher_key;
            payload.program_id = form.program_id === '' ? null : Number(form.program_id);
        }
        form.request_id = stableRequestId(payload);
        form.transform((data) => ({
            kind: data.kind, title: data.title, content: data.content, request_id: data.request_id,
            ...(policy ? { expected_revision: policy.revision } : {
                publisher_key: data.publisher_key,
                program_id: data.program_id === '' ? null : Number(data.program_id),
            }),
        }));
        const options = { preserveScroll: true, onSuccess: () => { requestSignature = ''; requestToken = ''; } };
        if (policy) form.put(`/communications/policies/${policy.id}`, options);
        else form.post('/communications/policies', options);
    }

    function publish() {
        const payload = { operation: 'publish', policy_id: policy.id, expected_revision: policy.revision };
        form.request_id = stableRequestId(payload);
        form.transform(() => ({ request_id: form.request_id, expected_revision: policy.revision }));
        form.post(`/communications/policies/${policy.id}/publish`, {
            preserveScroll: true, onSuccess: () => { requestSignature = ''; requestToken = ''; },
        });
    }

    const breadcrumbs = [
        { label: 'Communications' },
        { label: 'Public policies', href: '/communications/policies' },
        { label: policy ? 'Edit policy' : 'Create policy' },
    ];
</script>

<svelte:head><title>{policy ? 'Edit public policy' : 'Create public policy'} — Public policies</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="mx-auto grid w-full max-w-6xl gap-6">
        <PageHeaderBar title={policy ? 'Edit public policy' : 'New public policy'} description="Draft plain-text documents for your authorized office or owned active program. Publishing is a separate action." />
        {#if isWithdrawn}
            <div class="rounded-lg border border-border bg-muted/30 p-4 text-sm" role="status">This policy was withdrawn. Its full content and publication history are read-only. Create a new policy for a replacement.</div>
        {:else if !canSave}
            <div class="rounded-lg border border-destructive/30 bg-destructive/5 p-4 text-sm" role="alert">You do not have the required current authority to edit this policy.</div>
        {/if}
        {#if form.errors?.expected_revision || form.errors?.request_id || form.errors?.conflict}
            <div class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-sm" role="alert">
                <p class="font-semibold">This policy changed or the request could not be replayed.</p>
                <p class="mt-1">Your text remains here. Reload the current revision before retrying, and use a new request ID for a changed command.</p>
                <Link href="/communications/policies" class="mt-2 inline-block underline">Return to policy list</Link>
            </div>
        {/if}
        <form class="grid min-w-0 gap-6 lg:grid-cols-2" onsubmit={submit}>
            <section class="min-w-0 space-y-5 rounded-lg border border-border bg-card/70 p-4 sm:p-6">
                <h2 class="text-lg font-semibold">Policy details</h2>
                {#if !policy}
                    <div class="space-y-2">
                        <Label for="publisher_key">Issuing office or program</Label>
                        <select id="publisher_key" bind:value={form.publisher_key} onchange={changePublisher} disabled={form.processing || publishers.length < 2} class="border-input bg-background min-h-11 w-full rounded-md border px-3 py-2">
                            {#each publishers as publisher (publisher.key)}<option value={publisher.key}>{publisher.label}{publisher.requires_program ? ' — own program' : publisher.label.endsWith('Office') ? '' : ' office'}</option>{/each}
                        </select>
                        {#if form.errors.publisher_key}<p class="text-sm text-destructive">{form.errors.publisher_key}</p>{/if}
                    </div>
                    {#if selectedPublisher && !selectedPublisher.requires_program}
                        <div class="space-y-2">
                            <div class="flex items-center gap-1.5">
                                <Label for="program_id">Public placement</Label>
                                <InfoPopover
                                    title="Where this publishes"
                                    content="General policies appear on the main institutional policy bulletin; program-scoped policies appear on that degree program's public page."
                                />
                            </div>
                            <select id="program_id" bind:value={form.program_id} onchange={changePlacement} disabled={form.processing} class="border-input bg-background min-h-11 w-full rounded-md border px-3 py-2">
                                <option value="">General policy</option>
                                {#each programs as program (program.id)}<option value={String(program.id)}>{program.code} — {program.name}</option>{/each}
                            </select>
                            {#if form.errors.program_id}<p class="text-sm text-destructive">{form.errors.program_id}</p>{/if}
                        </div>
                    {:else if selectedPublisher?.requires_program}
                        <p class="rounded-md border border-border bg-muted/30 p-3 text-sm">This policy will appear only on the owned active program page.</p>
                    {/if}
                {:else}
                    <dl class="grid gap-2 text-sm sm:grid-cols-2"><div><dt class="text-muted-foreground">Issuer</dt><dd class="font-medium">{policy.publisher_label}</dd></div><div><dt class="text-muted-foreground">Placement</dt><dd class="font-medium">{policy.program_code ? `${policy.program_code} program page` : 'General'}</dd></div><div><dt class="text-muted-foreground">Status</dt><dd class="font-medium capitalize">{policy.status}</dd></div><div><dt class="text-muted-foreground">Draft revision</dt><dd class="font-medium">{policy.revision}</dd></div></dl>
                {/if}
                {#if policy && policy.kind === 'privacy'}
                    <div class="space-y-1">
                        <span class="text-xs text-muted-foreground">Document kind</span>
                        <p class="text-sm font-medium">Privacy policy</p>
                    </div>
                {/if}
                <div class="space-y-2">
                    <Label for="title">Title</Label>
                    <Input id="title" bind:value={form.title} maxlength="160" required disabled={!canSave || form.processing} aria-describedby="policy-title-count" />
                    <div class="flex justify-between text-xs text-muted-foreground"><span id="policy-title-count">1–160 characters</span><span>{titleLength}/160</span></div>
                    {#if form.errors.title}<p class="text-sm text-destructive">{form.errors.title}</p>{/if}
                </div>
                <div class="space-y-2">
                    <Label for="content">Policy text</Label>
                    <textarea id="content" bind:value={form.content} maxlength="10000" required disabled={!canSave || form.processing} aria-describedby="policy-content-count" class="border-input bg-background min-h-[22rem] w-full rounded-md border px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-ring" placeholder="Enter plain-text policy content."></textarea>
                    <div class="flex justify-between text-xs text-muted-foreground"><span id="policy-content-count">1–10,000 characters · plain text</span><span>{contentLength}/10000</span></div>
                    {#if form.errors.content}<p class="text-sm text-destructive">{form.errors.content}</p>{/if}
                </div>
                {#if form.errors.status}<p role="alert" class="text-sm text-destructive">{form.errors.status}</p>{/if}
                <div class="flex flex-wrap justify-end gap-3 pt-2">
                    <Link href="/communications/policies"><Button type="button" variant="outline">Cancel</Button></Link>
                    {#if canSave}<Button type="submit" disabled={form.processing}>{form.processing ? 'Saving…' : policy ? 'Save draft changes' : 'Save draft'}</Button>{/if}
                    {#if canPublish && policy}<Button type="button" variant="default" disabled={form.processing || documentDirty || !hasPendingDraft} onclick={publish}>Publish saved revision</Button>{/if}
                </div>
                {#if policy?.status === 'published'}<p class="text-xs text-muted-foreground">Saving changes does not alter the published document. Publish the saved revision to make the successor public.</p>{/if}
            </section>
            <aside class="min-w-0 space-y-4 rounded-lg border border-border bg-card/50 p-4 sm:p-6">
                <h2 class="text-lg font-semibold">Version history</h2>
                {#if revisions.length === 0}<p class="text-sm text-muted-foreground">No saved revisions yet.</p>{:else}
                    <ol class="divide-y divide-border/60" aria-label="Immutable policy revision history">
                        {#each revisions as revision (revision.revision)}
                            <li class="space-y-2 py-4 first:pt-0">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <strong>Revision {revision.revision} · <span class="capitalize">{revision.state}</span> · {revision.kind ?? 'Unknown historical kind'}</strong>
                                    <span class="text-xs text-muted-foreground">{revision.actor_name} · {revision.created_at}</span>
                                </div>
                                <h3 class="font-medium">{revision.title}</h3>
                                <p class="whitespace-pre-wrap break-words text-sm">{revision.content}</p>
                                {#if revision.reason}<p class="text-sm text-muted-foreground">Reason: {revision.reason}</p>{/if}
                            </li>
                        {/each}
                    </ol>
                {/if}
            </aside>
        </form>
    </div>
</StaffLayout>
