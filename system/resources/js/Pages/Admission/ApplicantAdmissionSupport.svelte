<script>
import { newIdempotencyKey } from '@/lib/utils';
    /* global route */
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import E2PageState from '@/Pages/Admission/E2PageState.svelte';
    import { Button } from '@/Components/ui/button';
    import { Badge } from '@/Components/ui/badge';
    import * as Card from '@/Components/ui/card';
    import * as Table from '@/Components/ui/table';
    import * as Tabs from '@/Components/ui/tabs';
    import {
        Dialog,
        DialogContent,
        DialogHeader,
        DialogTitle,
        DialogDescription,
        DialogFooter,
    } from '@/Components/ui/dialog';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import { Textarea } from '@/Components/ui/textarea';
    import { useForm } from '@inertiajs/svelte';
    import { CheckCircle2, Info, ShieldCheck } from 'lucide-svelte';

    let {
        page_state = 'denied',
        role = null,
        attempt = null,
        support = null,
        ph_record = null,
        ph_program = null,
        threads = [],
    } = $props();

    let activeTab = $state('assessment');
    let decisionDialogOpen = $state(false);

    $effect(() => {
        const validTabs = role === 'guidance'
            ? ['assessment', 'evidence', 'analysis', 'pool', 'followup']
            : ['record', 'analysis', 'followup', 'threads'];
        if (!validTabs.includes(activeTab)) activeTab = validTabs[0];
    });
    let poolDialogOpen = $state(false);
    let followUpDialogOpen = $state(false);

    const poolForm = useForm({
        default_program_code: '',
        options: [],
        reason: '',
        idempotency_key: newIdempotencyKey(),
    });

    const decisionForm = useForm({
        decision_type: 'approve',
        notes: '',
        idempotency_key: newIdempotencyKey(),
    });

    const followUpForm = useForm({
        program_code: '',
        reason: '',
        idempotency_key: newIdempotencyKey(),
    });


    function addPoolOption() {
        poolForm.options = [...poolForm.options, { program_code: '', display_order: poolForm.options.length + 1, basis: 'guidance_option' }];
    }

    function submitPool() {
        if (!attempt?.id) return;
        poolForm.idempotency_key = newIdempotencyKey();
        poolForm.post(
            typeof route === 'function'
                ? route('program-pool.set', { attempt: attempt.id })
                : `/consultation/${attempt.id}/program-pool`,
            { preserveScroll: true, onSuccess: () => (poolDialogOpen = false) },
        );
    }

    function submitFollowUp() {
        if (!attempt?.id) return;
        followUpForm.idempotency_key = newIdempotencyKey();
        followUpForm.post(
            typeof route === 'function'
                ? route('program-follow-up.open', { attempt: attempt.id })
                : `/consultation/${attempt.id}/follow-up`,
            { preserveScroll: true, onSuccess: () => (followUpDialogOpen = false) },
        );
    }

    function submitDecision() {
        if (!attempt?.id || role !== 'guidance') return;
        decisionForm.idempotency_key = newIdempotencyKey();
        decisionForm.post(
            typeof route === 'function'
                ? route('consultation.decision', { attempt: attempt.id })
                : `/consultation/${attempt.id}/decision`,
            { preserveScroll: true, onSuccess: () => (decisionDialogOpen = false) },
        );
    }

    const readinessLabels = {
        accepted: 'Application accepted',
        exam_completed: 'Assessment completed',
        grading_finalized: 'Result finalized',
        result_released: 'Result released',
        advised: 'Consultation advised',
    };

    function maxScore(series) {
        return Math.max(...(series ?? []).map((s) => Number(s.score) || 0), 100);
    }

    function formatScore(value) {
        if (value === null || value === undefined || value === '') return '—';
        const numeric = Number(value);
        return Number.isNaN(numeric) ? String(value) : numeric.toLocaleString(undefined, { maximumFractionDigits: 1 });
    }

    function formatDate(iso) {
        if (!iso) return null;
        return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    }
</script>

<svelte:head><title>Admission support</title></svelte:head>

{#snippet journeySection()}
<Card.Root class="border-primary/20 bg-primary/[0.02]"><Card.Header class="pb-3"><Card.Title>Applicant journey</Card.Title><Card.Description>One authoritative view of the admission path.</Card.Description></Card.Header><Card.Content><ol class="space-y-3">{#each Object.entries(readinessLabels) as [key, label]}<li class="flex items-center gap-2 text-sm">{#if support.readiness[key]}<CheckCircle2 class="size-4 shrink-0 text-emerald-600" /><span class="min-w-0 flex-1">{label}</span><Badge variant="outline" class="shrink-0">Complete</Badge>{:else}<span class="size-4 shrink-0 rounded-full border border-muted-foreground/40"></span><span class="min-w-0 flex-1 text-muted-foreground">{label}</span><Badge variant="secondary" class="shrink-0">Pending</Badge>{/if}</li>{/each}<li class="flex items-center gap-2 border-t pt-3 text-sm">{#if support.readiness.selection?.program_code}<CheckCircle2 class="size-4 shrink-0 text-emerald-600" />{:else}<span class="size-4 shrink-0 rounded-full border border-muted-foreground/40"></span>{/if}<span class="min-w-0 flex-1">Program selection</span><span class="text-right text-xs text-muted-foreground">{support.readiness.selection?.program_code ?? 'None'}</span></li></ol></Card.Content></Card.Root>
{/snippet}

{#snippet assessmentSection()}
<section class="space-y-4"><div><h2 class="text-lg font-semibold tracking-tight">Released assessment</h2><p class="text-sm text-muted-foreground">Read-only result evidence from the prepared result document.</p></div>{#if support.score_profile}{@const series = support.score_profile.series ?? []}{@const top = maxScore(series)}<Card.Root><Card.Header><Card.Title>{support.score_profile.title}</Card.Title><Card.Description>Source: {support.score_profile.source} · v{support.score_profile.version ?? '—'} · Released {formatDate(support.score_profile.released_at)}</Card.Description></Card.Header><Card.Content>{#if series.every((s) => s.score !== null && s.score !== '' && Number.isFinite(Number(s.score)))}<div class="flex h-56 items-end gap-6 overflow-x-auto px-2" role="img" aria-label="Released score profile chart">{#each series as s (s.area_code)}<div class="flex min-w-16 flex-1 flex-col items-center gap-1"><span class="text-xs font-semibold">{formatScore(s.score)}</span><div class="w-full rounded-t-md bg-primary/80" style="height: {Math.max((Number(s.score) || 0) / top * 160, 4)}px" aria-hidden="true"></div><span class="text-center text-xs text-muted-foreground">{s.area_name}</span></div>{/each}</div>{:else}<div class="grid gap-3 sm:grid-cols-2" role="list" aria-label="Released score profile values">{#each series as s (s.area_code)}<div class="flex items-center justify-between gap-3 rounded-lg border bg-muted/20 px-4 py-3" role="listitem"><div><p class="text-sm font-medium">{s.area_name}</p><p class="text-xs text-muted-foreground">Source: {s.source ?? '—'}</p></div><Badge variant="secondary" class="shrink-0">{formatScore(s.score)}</Badge></div>{/each}</div>{/if}<p class="mt-3 text-xs text-muted-foreground">Unit: {support.score_profile.unit}. The accessible table below contains the same values.</p></Card.Content></Card.Root><Card.Root><Card.Header><Card.Title>Score table</Card.Title></Card.Header><Card.Content><Table.Root><Table.Header><Table.Row><Table.Head>Area</Table.Head><Table.Head>Score</Table.Head><Table.Head>Source</Table.Head></Table.Row></Table.Header><Table.Body>{#each series as s (s.area_code)}<Table.Row><Table.Cell>{s.area_name}</Table.Cell><Table.Cell>{formatScore(s.score)}</Table.Cell><Table.Cell>{s.source ?? '—'}</Table.Cell></Table.Row>{/each}</Table.Body></Table.Root></Card.Content></Card.Root>{:else}<Card.Root><Card.Content class="p-6 text-sm text-muted-foreground">Assessment evidence unlocks after grading is finalized and the result is prepared.</Card.Content></Card.Root>{/if}</section>
{/snippet}

{#snippet evidenceSection()}
<section class="space-y-4"><div><h2 class="text-lg font-semibold tracking-tight">Preferences &amp; consultation evidence</h2><p class="text-sm text-muted-foreground">Applicant inputs and human consultation evidence stay separate.</p></div><Card.Root><Card.Header><Card.Title>Applicant preferences</Card.Title></Card.Header><Card.Content>{#if support.preferences?.length}<ol class="list-inside list-decimal space-y-2 text-sm">{#each support.preferences as p (p)}<li>{p}</li>{/each}</ol>{:else}<p class="text-sm text-muted-foreground">No program preferences recorded.</p>{/if}</Card.Content></Card.Root><Card.Root><Card.Header><Card.Title>Structured consultation evidence</Card.Title></Card.Header><Card.Content class="space-y-3 text-sm"><div class="flex flex-wrap items-center gap-2"><span class="text-muted-foreground">Decision</span><Badge variant="outline">{support.consultation_evidence.decision_type ?? 'Not recorded'}</Badge></div><p class="text-muted-foreground">Recorded by {support.consultation_evidence.recorded_by ?? '—'} · {formatDate(support.consultation_evidence.recorded_at) ?? '—'}</p>{#if support.consultation_evidence.notes}<p class="rounded-lg bg-muted/60 p-3 text-muted-foreground">{support.consultation_evidence.notes}</p>{/if}</Card.Content></Card.Root></section>
{/snippet}

{#snippet analysisSection()}
<section class="space-y-4"><div class="flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-lg font-semibold tracking-tight">Analysis &amp; Evidence</h2><p class="text-sm text-muted-foreground">Independent visualization slots. No plugin output writes decisions or program state.</p></div><Badge variant="outline">Non-authoritative</Badge></div><div class="grid gap-4 xl:grid-cols-2">{#each support.models ?? [] as m (m.slot)}<Card.Root class="overflow-hidden"><Card.Header class="border-b bg-muted/20 pb-4"><div class="flex items-start justify-between gap-3"><div><Card.Title>{m.slot === 'yu_kee' ? 'YuKee / K-Means compatibility' : 'Program-fit constraints'}</Card.Title><Card.Description class="mt-1">{m.slot === 'yu_kee' ? 'Artifact-compatible cluster and profile evidence.' : 'Transparent rule profile for program-head constraints.'}</Card.Description></div><Badge variant="secondary" class="shrink-0">Reserved slot</Badge></div></Card.Header><Card.Content class="space-y-3 pt-4"><div class="relative flex h-36 items-center justify-center overflow-hidden rounded-lg border border-dashed bg-muted/30" role="img" aria-label="Reserved visualization slot with no provider output"><div class="absolute inset-x-8 bottom-5 flex items-end justify-between gap-2 opacity-40" aria-hidden="true"><span class="h-8 w-2 rounded-full bg-primary"></span><span class="h-16 w-2 rounded-full bg-primary"></span><span class="h-11 w-2 rounded-full bg-primary"></span><span class="h-20 w-2 rounded-full bg-primary"></span><span class="h-14 w-2 rounded-full bg-primary"></span></div><div class="relative flex flex-col items-center gap-1 text-center"><Badge variant="outline" class="bg-background/90">Preview fixture</Badge><span class="text-xs font-medium">{m.status === 'result_not_ready' ? 'Result not ready' : 'No provider configured'}</span></div></div><p class="text-xs leading-relaxed text-muted-foreground">{m.message}</p></Card.Content></Card.Root>{/each}</div></section>
{/snippet}

{#snippet poolSection()}
<section class="space-y-4"><div class="flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-lg font-semibold tracking-tight">Guidance program pool</h2><p class="text-sm text-muted-foreground">Versioned options and advised default remain human-owned.</p></div><Button class="min-h-[44px] gap-2" onclick={() => (poolDialogOpen = true)}><ShieldCheck class="size-4" />Set / revise pool</Button></div><Card.Root><Card.Header><Card.Title>Current pool</Card.Title><Card.Description>Version {support.program_pool.version ?? '—'} · default {support.program_pool.default ?? 'none'} · selection {support.program_pool.selection_status ?? 'none'} ({support.program_pool.selection_program ?? '—'})</Card.Description></Card.Header><Card.Content>{#if support.program_pool.options?.length}<Table.Root><Table.Header><Table.Row><Table.Head>Order</Table.Head><Table.Head>Program</Table.Head><Table.Head>Basis</Table.Head></Table.Row></Table.Header><Table.Body>{#each support.program_pool.options as o (o.program_code)}<Table.Row><Table.Cell>{o.display_order}</Table.Cell><Table.Cell>{o.program_name} ({o.program_code})</Table.Cell><Table.Cell>{o.basis}</Table.Cell></Table.Row>{/each}</Table.Body></Table.Root>{:else}<p class="py-3 text-sm text-muted-foreground">No pool has been set for this applicant yet.</p>{/if}</Card.Content></Card.Root></section>
{/snippet}

{#snippet followupSection()}
<section class="space-y-4"><div class="flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-lg font-semibold tracking-tight">Targeted follow-ups</h2><p class="text-sm text-muted-foreground">Escalations remain visible without changing the primary journey state.</p></div>{#if role === 'guidance'}<Button variant="outline" class="min-h-[44px] gap-2" onclick={() => (followUpDialogOpen = true)}><ShieldCheck class="size-4" />Open follow-up</Button>{/if}</div><Card.Root><Card.Content class="p-0">{#if support.follow_ups?.length}<ul class="divide-y">{#each support.follow_ups as f (f.program_code + f.opened_at)}<li class="flex items-start justify-between gap-3 p-4 text-sm"><div><div class="flex flex-wrap items-center gap-2"><span class="font-medium">{f.program_code}</span><Badge variant="outline">{f.status}</Badge></div><p class="mt-1 text-muted-foreground">{f.reason}</p>{#if f.resolution_reason}<p class="mt-1 text-xs text-muted-foreground">Resolved: {f.resolution_reason}</p>{/if}</div></li>{/each}</ul>{:else}<p class="p-6 text-sm text-muted-foreground">No targeted follow-ups.</p>{/if}</Card.Content></Card.Root></section>
{/snippet}

{#snippet recordSection()}
<section class="space-y-4"><div><h2 class="text-lg font-semibold tracking-tight">Program Head record</h2><p class="text-sm text-muted-foreground">Assigned-program visibility is scoped and audited.</p></div>{#if ph_record}<Card.Root><Card.Header><div class="flex flex-wrap items-center gap-2"><Card.Title>{ph_record.reference_code} · {ph_record.name}</Card.Title><Badge variant={ph_record.tier === 'B' ? 'default' : 'secondary'}>{ph_record.tier === 'B' ? 'Protected record' : 'Summary'}</Badge></div><Card.Description>{#if ph_record.tier === 'B'}Access reason: {ph_record.access_reason} · audited{:else}{ph_record.visibility_note}{/if}</Card.Description></Card.Header><Card.Content class="space-y-4 text-sm"><p>Lifecycle: {ph_record.lifecycle ?? '—'} · Selection: {ph_record.selection_status ?? '—'}</p>{#if ph_record.tier === 'B' && ph_record.score_profile?.series?.length}<div><p class="mb-2 font-medium">Released score profile</p><ul class="space-y-2">{#each ph_record.score_profile.series as s (s.area_code)}<li class="flex items-center gap-3"><span class="w-44 text-muted-foreground">{s.area_name}</span><span class="tabular-nums">{formatScore(s.score)}</span><span class="text-xs text-muted-foreground">({s.source ?? '—'})</span></li>{/each}</ul></div>{/if}{#if ph_record.stamp_state}<p>Enrollment: {ph_record.stamp_state.enrolling ? 'enrolling' : ''} {ph_record.stamp_state.enrolled ? 'enrolled' : 'not enrolled'}</p>{/if}</Card.Content></Card.Root>{:else}<Card.Root><Card.Content class="p-6 text-sm text-muted-foreground">No assigned-program record for this applicant.</Card.Content></Card.Root>{/if}</section>
{/snippet}

{#snippet threadsSection()}
<section class="space-y-4"><div><h2 class="text-lg font-semibold tracking-tight">Internal case threads</h2><p class="text-sm text-muted-foreground">Scoped discussion and history for this applicant.</p></div>{#if threads.length}{#each threads as t (t.id)}<Card.Root><Card.Header><Card.Title>{t.subject}</Card.Title><Card.Description>{t.status} · {t.messages?.length ?? 0} messages</Card.Description></Card.Header><Card.Content class="space-y-2">{#each t.messages ?? [] as m (m.id)}<div class="rounded-lg bg-muted/60 p-3 text-sm"><p class="text-xs text-muted-foreground">{m.sender} · {formatDate(m.created_at)}</p><p class="mt-1">{m.body}</p></div>{/each}</Card.Content></Card.Root>{/each}{:else}<Card.Root><Card.Content class="p-6 text-sm text-muted-foreground">No internal case threads for this applicant.</Card.Content></Card.Root>{/if}</section>
{/snippet}

<StaffLayout breadcrumbs={[{ label: role === 'program_head' ? 'My Programs' : 'Consultation' }, { label: attempt?.reference_code ?? 'Admission support' }]}>
<div class="min-w-0 space-y-6 p-4 sm:p-6"><E2PageState state={page_state} />{#if page_state === 'ready' && attempt && support}
<header class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-end sm:justify-between"><div><h1 class="font-serif text-3xl font-semibold tracking-tight">{attempt.name}</h1><div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted-foreground"><span class="font-mono">{attempt.reference_code}</span><span aria-hidden="true">·</span><span>Admission support workspace</span>{#if role === 'program_head' && ph_program}<Badge variant="outline">Assigned program: {ph_program.code}</Badge>{/if}</div></div>{#if role === 'guidance'}<Button size="lg" class="min-h-[44px] gap-2" onclick={() => (decisionDialogOpen = true)}><CheckCircle2 class="size-4" />Record consultation</Button>{/if}</header>
<div class="lg:hidden">{@render journeySection()}</div>
<div class="hidden items-start gap-6 lg:grid lg:grid-cols-[minmax(0,1fr)_19rem]"><main class="min-w-0 space-y-8">{#if role === 'guidance'}{@render assessmentSection()}{@render evidenceSection()}{@render analysisSection()}{@render poolSection()}{@render followupSection()}{:else}{@render recordSection()}{@render analysisSection()}{@render followupSection()}{@render threadsSection()}{/if}</main><aside class="sticky top-6 space-y-4">{@render journeySection()}<Card.Root><Card.Header class="pb-3"><Card.Title>Case facts</Card.Title></Card.Header><Card.Content class="space-y-3 text-sm"><div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">Current selection</span><span class="font-medium">{support.readiness.selection?.program_code ?? 'None'}</span></div><div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">Consultation</span><Badge variant={support.readiness.advised ? 'default' : 'secondary'}>{support.readiness.advised ? 'Advised' : 'Pending'}</Badge></div>{#if ph_program}<div class="flex items-center justify-between gap-3"><span class="text-muted-foreground">Assigned program</span><span class="font-medium">{ph_program.code}</span></div>{/if}</Card.Content></Card.Root></aside></div>
<div class="lg:hidden"><Tabs.Root value={activeTab} onValueChange={(v) => (activeTab = v)}><Tabs.List class="w-full justify-start gap-1 overflow-x-auto">{#if role === 'guidance'}<Tabs.Trigger class="min-h-[44px] px-3 text-xs" value="assessment">Assessment</Tabs.Trigger><Tabs.Trigger class="min-h-[44px] px-3 text-xs" value="evidence">Evidence</Tabs.Trigger><Tabs.Trigger class="min-h-[44px] px-3 text-xs" value="analysis">Analysis</Tabs.Trigger><Tabs.Trigger class="min-h-[44px] px-3 text-xs" value="pool">Program pool</Tabs.Trigger><Tabs.Trigger class="min-h-[44px] px-3 text-xs" value="followup">Follow-up</Tabs.Trigger>{:else}<Tabs.Trigger class="min-h-[44px] px-3 text-xs" value="record">Record</Tabs.Trigger><Tabs.Trigger class="min-h-[44px] px-3 text-xs" value="analysis">Analysis</Tabs.Trigger><Tabs.Trigger class="min-h-[44px] px-3 text-xs" value="followup">Follow-up</Tabs.Trigger><Tabs.Trigger class="min-h-[44px] px-3 text-xs" value="threads">Threads</Tabs.Trigger>{/if}</Tabs.List>{#if role === 'guidance'}<Tabs.Content value="assessment" class="mt-5">{@render assessmentSection()}</Tabs.Content><Tabs.Content value="evidence" class="mt-5">{@render evidenceSection()}</Tabs.Content><Tabs.Content value="analysis" class="mt-5">{@render analysisSection()}</Tabs.Content><Tabs.Content value="pool" class="mt-5">{@render poolSection()}</Tabs.Content><Tabs.Content value="followup" class="mt-5">{@render followupSection()}</Tabs.Content>{:else}<Tabs.Content value="record" class="mt-5">{@render recordSection()}</Tabs.Content><Tabs.Content value="analysis" class="mt-5">{@render analysisSection()}</Tabs.Content><Tabs.Content value="followup" class="mt-5">{@render followupSection()}</Tabs.Content><Tabs.Content value="threads" class="mt-5">{@render threadsSection()}</Tabs.Content>{/if}</Tabs.Root></div>
{#if role === 'guidance'}<Dialog open={decisionDialogOpen} onOpenChange={(v) => (decisionDialogOpen = v)}><DialogContent class="sm:max-w-lg"><DialogHeader><DialogTitle>Record consultation decision</DialogTitle><DialogDescription>Confirm the human consultation outcome for {attempt.name}. This action is audited.</DialogDescription></DialogHeader><div class="space-y-4"><div class="space-y-2"><Label for="decision-type">Decision</Label><select id="decision-type" bind:value={decisionForm.decision_type} class="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm"><option value="approve">Approve</option><option value="modify">Modify</option><option value="override">Override</option><option value="defer">Defer</option></select></div><div class="space-y-2"><Label for="decision-notes">Notes</Label><Textarea id="decision-notes" bind:value={decisionForm.notes} placeholder="Record the consultation basis…" /></div>{#if decisionForm.errors.decision_type}<p class="text-sm text-destructive">{decisionForm.errors.decision_type}</p>{/if}</div><DialogFooter><Button variant="outline" onclick={() => (decisionDialogOpen = false)}>Cancel</Button><Button onclick={submitDecision} disabled={decisionForm.processing}>Save decision</Button></DialogFooter></DialogContent></Dialog><Dialog open={poolDialogOpen} onOpenChange={(v) => (poolDialogOpen = v)}><DialogContent class="sm:max-w-lg"><DialogHeader><DialogTitle>Set program pool and advised default</DialogTitle><DialogDescription>Pool versions are immutable after finalization and require released result plus confirmed consultation.</DialogDescription></DialogHeader><div class="space-y-4"><div class="space-y-2"><Label>Program options</Label>{#each poolForm.options as option, i (i)}<div class="flex gap-2"><Input class="flex-1" placeholder="Program code (e.g. BSN)" value={option.program_code} oninput={(e) => (poolForm.options[i].program_code = e.currentTarget.value)} /><select class="rounded-md border border-input bg-background px-2 text-sm" value={option.basis} onchange={(e) => (poolForm.options[i].basis = e.currentTarget.value)}><option value="preference">preference</option><option value="guidance_option">guidance option</option></select></div>{/each}<Button variant="outline" size="sm" onclick={addPoolOption}>Add option</Button></div><div class="space-y-2"><Label>Advised default program code</Label><Input bind:value={poolForm.default_program_code} placeholder="BSN" /></div><div class="space-y-2"><Label>Reason</Label><Textarea bind:value={poolForm.reason} placeholder="Why this pool / default?" /></div>{#if poolForm.errors.pool}<p class="text-sm text-destructive">{poolForm.errors.pool}</p>{/if}</div><DialogFooter><Button variant="outline" onclick={() => (poolDialogOpen = false)}>Cancel</Button><Button onclick={submitPool} disabled={poolForm.processing}>Finalize pool</Button></DialogFooter></DialogContent></Dialog><Dialog open={followUpDialogOpen} onOpenChange={(v) => (followUpDialogOpen = v)}><DialogContent class="sm:max-w-md"><DialogHeader><DialogTitle>Open targeted PH follow-up</DialogTitle><DialogDescription>Routes an edge case to the assigned Program Head. Guidance cannot settle or withdraw it after opening.</DialogDescription></DialogHeader><div class="space-y-4"><div class="space-y-2"><Label>Program code</Label><Input bind:value={followUpForm.program_code} placeholder="BSN" /></div><div class="space-y-2"><Label>Reason</Label><Textarea bind:value={followUpForm.reason} placeholder="What needs the Program Head's attention?" /></div>{#if followUpForm.errors.follow_up}<p class="text-sm text-destructive">{followUpForm.errors.follow_up}</p>{/if}</div><DialogFooter><Button variant="outline" onclick={() => (followUpDialogOpen = false)}>Cancel</Button><Button onclick={submitFollowUp} disabled={followUpForm.processing}>Open follow-up</Button></DialogFooter></DialogContent></Dialog>{/if}
{/if}</div></StaffLayout>
