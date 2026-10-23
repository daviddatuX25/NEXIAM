<script>
    import { untrack } from 'svelte';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Textarea } from '@/Components/ui/textarea';
    import { governanceRoute as route, governanceRequest, governanceError, GovernanceRequestError } from '@/lib/governanceRequests';
    import { success as toastSuccess, error as toastError } from '@/lib/toast.js';
    import { newIdempotencyKey } from '@/lib/utils';
    /** @typedef {{ type: 'discussion.read' | 'discussion.message-sent', threadId: number }} GovernanceDiscussionActivity */

    /** @type {{ program: any, cycle: any, topic: string, initialList?: any, onGovernanceActivity?: (activity: GovernanceDiscussionActivity) => void }} */
    let { program, cycle, topic, initialList = null, onGovernanceActivity = () => {} } = $props();
    let list = $state(null);
    let transcript = $state(null);
    let messages = $state([]);
    let search = $state('');
    let subject = $state('');
    let body = $state('');
    let error = $state('');
    let busy = $state(false);
    let sequence = 0;
    let pending = null;
    const scope = $derived(`${program?.id}:${cycle?.id}:${topic}`);

    $effect(() => {
        scope;
        untrack(() => {
            sequence++;
            list = initialList;
            transcript = null;
            messages = [];
            subject = '';
            body = '';
            pending = null;
            search = '';
            error = '';
            busy = false;
        });
    });

    $effect(() => {
        const refreshedList = initialList;
        untrack(() => {
            list = refreshedList;
        });
    });

    async function loadList(page = 1) {
        const token = ++sequence;
        busy = true;
        error = '';
        try {
            const query = new URLSearchParams({ discussion_page: String(page), topic, discussion_search: search });
            const data = await governanceRequest(`${route('setup.admission-support.governance.show', { program: program.id, cycle: cycle.id })}?${query}`);
            if (token !== sequence) return;
            list = data.discussions?.[topic] ?? null;
            if (!list) error = 'Discussion access is not available for this topic.';
        } catch (failure) {
            if (token === sequence) { error = governanceError(failure); if (failure instanceof GovernanceRequestError && failure.status === 403) { list = null; transcript = null; messages = []; } }
        } finally { if (token === sequence) busy = false; }
    }

    async function openHistory(thread, older = false) {
        const token = ++sequence;
        busy = true;
        error = '';
        const cursor = older ? transcript?.pagination?.next_before_message_id : null;
        try {
            const query = new URLSearchParams({ thread_id: String(thread.id), per_page: '30' });
            if (cursor) query.set('before_message_id', String(cursor));
            const data = await governanceRequest(`${route('setup.admission-support.discussions.history', { program: program.id, cycle: cycle.id })}?${query}`);
            if (token !== sequence) return;
            transcript = data;
            messages = older ? [...data.messages, ...messages.filter((message) => !data.messages.some((old) => old.id === message.id))] : data.messages;
        } catch (failure) {
            if (token === sequence) { error = governanceError(failure); if (failure instanceof GovernanceRequestError && failure.status === 403) { transcript = null; messages = []; } }
        } finally { if (token === sequence) busy = false; }
    }

    async function command(action, payload) {
        if (busy) return;
        const capturedScope = scope;
        const fingerprint = JSON.stringify({ action, payload, scope });
        if (pending?.fingerprint !== fingerprint) pending = { fingerprint, key: newIdempotencyKey() };
        busy = true;
        error = '';
        try {
            const receipt = await governanceRequest(route(`setup.admission-support.discussions.${action}`, { program: program.id, cycle: cycle.id }), { ...payload, idempotency_key: pending.key });
            if (scope !== capturedScope) return;
            pending = null;
            if (action === 'read' || action === 'send') {
                onGovernanceActivity({
                    type: action === 'read' ? 'discussion.read' : 'discussion.message-sent',
                    threadId: Number(payload.thread_id),
                });
            }
            busy = false;
            if (action === 'open') {
                subject = '';
                toastSuccess('Discussion thread opened.');
                await loadList(1);
                if (scope !== capturedScope) return;
                await openHistory(receipt.thread);
            } else {
                if (action === 'send') {
                    body = '';
                    toastSuccess('Reply posted.');
                } else if (action === 'state') {
                    toastSuccess('Discussion state updated.');
                }
                const activeThread = transcript.thread;
                await loadList(list?.pagination?.current_page ?? 1);
                if (scope !== capturedScope) return;
                await openHistory(activeThread);
            }
        } catch (failure) {
            if (scope === capturedScope) {
                error = governanceError(failure);
                toastError(error);
                if (failure instanceof GovernanceRequestError && failure.status === 403) { list = null; transcript = null; messages = []; }
            }
        } finally { if (scope === capturedScope) busy = false; }
    }
</script>

<section class="min-w-0 space-y-4" aria-label={`${topic} discussions`} aria-busy={busy}>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="font-semibold capitalize">{topic} discussion</h3>
        <Button variant="ghost" size="sm" disabled={busy} onclick={() => transcript ? openHistory(transcript.thread) : loadList(list?.pagination?.current_page ?? 1)}>Refresh</Button>
    </div>
    <p class="text-xs text-muted-foreground">Staff-only, {program?.code} · {cycle?.staff_label ?? cycle?.name}. Do not include applicant details.</p>
    {#if error}<p role="alert" class="text-sm text-destructive">{error}</p>{/if}
    {#if transcript}
        <div class="flex flex-wrap items-center justify-between gap-2">
            <Button variant="outline" size="sm" disabled={busy} onclick={() => { transcript = null; messages = []; loadList(list?.pagination?.current_page ?? 1); }}>Back to discussions</Button>
            <span class="text-xs text-muted-foreground">{transcript.thread.status.replace('_', ' ')} · {transcript.thread.unread && transcript.thread.message_count === 0 ? 'New discussion' : `${transcript.unread_count} unread`}</span>
        </div>
        <h4 class="font-medium break-words">{transcript.thread.subject}</h4>
        {#if transcript.pagination?.has_more}<Button variant="outline" size="sm" disabled={busy} onclick={() => openHistory(transcript.thread, true)}>Load older messages</Button>{/if}
        <ol class="max-h-[28rem] space-y-4 overflow-y-auto" aria-label="Messages">
            {#each messages as message (message.id)}
                <li class="space-y-1 border-b pb-3"><p class="text-xs text-muted-foreground"><span class="font-medium text-foreground">{message.sender?.name ?? 'Staff'}</span> · {new Date(message.created_at).toLocaleString()}</p><p class="whitespace-pre-wrap break-words text-sm">{message.body}</p></li>
            {:else}<li class="text-sm text-muted-foreground">No messages yet.</li>{/each}
        </ol>
        {#if transcript.can_read && transcript.thread.unread}
            {#if messages.length}
                <Button variant="outline" size="sm" disabled={busy} onclick={() => command('read', { thread_id: transcript.thread.id, through_message_id: messages[messages.length - 1].id })}>Mark displayed messages read</Button>
            {:else if transcript.thread.message_count === 0}
                <Button variant="outline" size="sm" disabled={busy} onclick={() => command('read', { thread_id: transcript.thread.id, through_message_id: null })}>Acknowledge empty discussion</Button>
            {/if}
        {/if}
        {#if transcript.can_manage_state}
            <div class="flex flex-wrap gap-2" aria-label="Discussion state">
                {#each ['open', 'read_only', 'closed'] as status}<Button variant="outline" size="sm" disabled={busy || transcript.thread.status === status} onclick={() => command('state', { thread_id: transcript.thread.id, status })}>{status === 'open' ? 'Reopen' : status === 'closed' ? 'Close discussion' : 'Make read-only'}</Button>{/each}
            </div>
        {/if}
        {#if transcript.can_send}
            <form class="space-y-2" onsubmit={(event) => { event.preventDefault(); command('send', { thread_id: transcript.thread.id, body }); }}>
                <label for={`reply-${scope}`} class="text-sm font-medium">Reply</label>
                <Textarea id={`reply-${scope}`} bind:value={body} required maxlength={8000} rows={4} disabled={busy} />
                <Button type="submit" disabled={busy || !body.trim()}>{busy ? 'Sending…' : 'Send reply'}</Button>
            </form>
        {:else}<p class="text-sm text-muted-foreground">This discussion is readable; replies are unavailable.</p>{/if}
    {:else}
        {#if busy}<p role="status" class="text-sm text-muted-foreground">Loading discussions…</p>{/if}
        <form class="flex flex-col gap-2 sm:flex-row" onsubmit={(event) => { event.preventDefault(); loadList(1); }}>
            <label class="sr-only" for={`search-${scope}`}>Search discussion subjects</label>
            <Input id={`search-${scope}`} bind:value={search} maxlength={255} placeholder="Search subjects" disabled={busy} />
            <Button type="submit" variant="outline" disabled={busy}>Search</Button>
        </form>
        <ul class="divide-y">
            {#each list?.threads ?? [] as thread (thread.id)}
                <li>
                    <button type="button" class="w-full py-3 text-left hover:text-primary focus-visible:outline-2 focus-visible:outline-ring" disabled={busy || !thread.can_read} onclick={() => openHistory(thread)}>
                        <span class="flex min-w-0 items-center justify-between gap-3">
                            <span class="min-w-0 break-words text-sm font-medium">{thread.subject}</span>
                            {#if thread.unread}<span class="shrink-0 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">{thread.unread_count || 'New'}</span>{/if}
                        </span>
                        <span class="block text-xs text-muted-foreground">{thread.status.replace('_', ' ')} · {thread.message_count} {thread.message_count === 1 ? 'message' : 'messages'}</span>
                    </button>
                </li>
            {:else}<li class="py-3 text-sm text-muted-foreground">No discussions in this context.</li>{/each}
        </ul>
        {#if list?.pagination && (list.pagination.current_page > 1 || list.pagination.has_more)}
            <div class="flex items-center gap-2"><Button variant="outline" size="sm" disabled={busy || list.pagination.current_page <= 1} onclick={() => loadList(list.pagination.current_page - 1)}>Previous</Button><span class="text-xs">Page {list.pagination.current_page}</span><Button variant="outline" size="sm" disabled={busy || !list.pagination.has_more} onclick={() => loadList(list.pagination.current_page + 1)}>Next</Button></div>
        {/if}
        {#if list?.can_send}
            <form class="space-y-2 border-t pt-4" onsubmit={(event) => { event.preventDefault(); command('open', { topic, subject }); }}>
                <label for={`subject-${scope}`} class="text-sm font-medium">New discussion subject</label>
                <Input id={`subject-${scope}`} bind:value={subject} required maxlength={255} disabled={busy} />
                <Button type="submit" disabled={busy || !subject.trim()}>{busy ? 'Opening…' : 'Start discussion'}</Button>
            </form>
        {/if}
    {/if}
</section>
