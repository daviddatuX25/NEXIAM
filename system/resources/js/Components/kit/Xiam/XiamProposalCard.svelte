<script>
    import {
        AlertTriangle,
        CheckCircle2,
        Info,
        LoaderCircle,
        ShieldCheck,
        X,
    } from 'lucide-svelte';
    import { getXiamActionPresentation } from '@/lib/xiam-presentation';
    import {
        decodeXiamProposalView,
        formatXiamProposalFieldChange,
        formatXiamProposalValue,
    } from '@/lib/xiam-proposal-view';

    let { proposal, onConfirm = null, onReject = null } = $props();
    let phraseInput = $state('');

    const decodedView = $derived.by(() => {
        return (
            decodeXiamProposalView(proposal?.proposal_view) ??
            decodeXiamProposalView(proposal) ??
            decodeXiamProposalView(proposal?.proposal_review) ??
            decodeXiamProposalView(proposal?.view) ??
            null
        );
    });

    const currentStatus = $derived(
        decodedView?.status ?? proposal?.status ?? 'pending',
    );

    const isCommitted = $derived(
        currentStatus === 'committed' || currentStatus === 'executed',
    );

    const isConfirming = $derived(
        currentStatus === 'confirming' || currentStatus === 'executing',
    );

    const isNonActionable = $derived(
        [
            'committed',
            'executed',
            'cancelled',
            'rejected',
            'invalidated',
            'conflicted',
            'failed',
            'expired',
            'retired',
        ].includes(currentStatus),
    );

    const confirmationPhrase = $derived(
        decodedView?.safety?.confirmation_phrase ??
            proposal?.confirmation_phrase ??
            null,
    );

    const requiresReauth = $derived(
        decodedView?.safety?.requires_reauth ??
            proposal?.requires_reauth ??
            false,
    );

    const canConfirm = $derived(
        Boolean(decodedView?.confirmable) &&
            (!confirmationPhrase || phraseInput.trim() === confirmationPhrase),
    );

    const actionPresentation = $derived(
        getXiamActionPresentation(
            decodedView?.capability_id ??
                proposal?.capability ??
                proposal?.capability_id,
            decodedView?.title ?? proposal?.title,
        ),
    );

    const displayTitle = $derived(
        decodedView?.title ?? actionPresentation.title,
    );

    const statusLabel = $derived.by(() => {
        switch (currentStatus) {
            case 'confirming':
            case 'executing':
                return 'Executing…';
            case 'committed':
            case 'executed':
                return 'Completed';
            case 'cancelled':
            case 'rejected':
                return 'Cancelled';
            case 'invalidated':
                return 'No longer current';
            case 'conflicted':
                return 'Conflicted';
            case 'expired':
                return 'Expired';
            case 'retired':
                return 'Retired';
            case 'failed':
                return 'Execution failed';
            default:
                return 'Pending confirmation';
        }
    });

    const statusClass = $derived.by(() => {
        switch (currentStatus) {
            case 'committed':
            case 'executed':
                return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
            case 'cancelled':
            case 'rejected':
            case 'invalidated':
            case 'conflicted':
            case 'expired':
            case 'retired':
            case 'failed':
                return 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300';
            case 'confirming':
            case 'executing':
                return 'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300';
            default:
                return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300';
        }
    });

    const preflightChecks = $derived(
        decodedView?.preflight?.checks ??
            (Array.isArray(proposal?.preflight) ? proposal.preflight : []),
    );

    const structuredError = $derived.by(() => {
        if (decodedView?.error) return decodedView.error;
        if (proposal?.error && typeof proposal.error === 'object') {
            return proposal.error;
        }
        return null;
    });

    const rows = $derived(Array.isArray(proposal?.rows) ? proposal.rows : []);
    const isCreation = $derived(
        rows.length > 0 &&
            rows.every((r) => !r.current || r.current === 'None'),
    );

    function roleBadgeClass(role) {
        switch (role) {
            case 'choice':
                return 'bg-blue-500/10 text-blue-700 dark:text-blue-300 border-blue-500/20';
            case 'effect':
                return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20';
            case 'context':
            default:
                return 'bg-muted text-muted-foreground border-border/40';
        }
    }

    function remediationText(remediation) {
        switch (remediation) {
            case 'repropose':
                return 'Please submit a new proposal to try again.';
            case 'reauthenticate':
                return 'Recent sign-in required. Please authenticate and retry.';
            case 'refresh':
                return 'Please refresh the page to see the latest data.';
            case 'reconcile':
                return 'Changes were reconciled with the server. Review current status.';
            case 'manual':
                return 'Manual action required. Please perform this operation directly in the module.';
            default:
                return '';
        }
    }

    function checkLabel(check) {
        const labels = check?.key ? FRIENDLY_CHECKS[check.key] : null;
        if (labels) {
            return check.status === 'pass'
                ? labels.pass
                : ['fail', 'conflict'].includes(check.status)
                  ? labels.fail
                  : labels.pending;
        }
        if (
            check?.label &&
            !check.label.includes('_') &&
            !check.label.includes('@')
        ) {
            return check.label;
        }
        return check?.label || 'Review required';
    }

    const FRIENDLY_CHECKS = {
        chronology_valid: {
            pass: 'Start and end times are valid',
            fail: 'End time must be after start time',
            pending: 'Check start and end times',
        },
        room_suitable: {
            pass: 'Room is suitable for exams',
            fail: 'Choose a room configured for exams',
            pending: 'Check room suitability',
        },
        no_room_collision: {
            pass: 'Room is available at this time',
            fail: 'Another session uses this room at this time',
            pending: 'Check room availability',
        },
        has_assignments: {
            pass: 'Applicants are assigned',
            fail: 'Assign applicants before continuing',
            pending: 'Check applicant assignments',
        },
        materials_selected: {
            pass: 'Exam materials are selected',
            fail: 'Select exam materials before continuing',
            pending: 'Check exam materials',
        },
        booklet_usable: {
            pass: 'Booklet is ready',
            fail: 'Choose a finalized booklet',
            pending: 'Check booklet readiness',
        },
    };

    function preflightLabel(check) {
        switch (check?.status) {
            case 'pass':
                return 'Passed';
            case 'fail':
            case 'conflict':
                return 'Needs attention';
            case 'warn':
                return 'Warning';
            case 'noop':
                return 'No change';
            default:
                return 'Pending';
        }
    }

    function preflightClass(check) {
        switch (check?.status) {
            case 'pass':
                return 'text-emerald-600 dark:text-emerald-400';
            case 'fail':
            case 'conflict':
                return 'text-rose-600 dark:text-rose-400';
            case 'warn':
                return 'text-amber-600 dark:text-amber-400';
            case 'noop':
            default:
                return 'text-muted-foreground';
        }
    }

    function preflightIcon(check) {
        switch (check?.status) {
            case 'pass':
                return CheckCircle2;
            case 'fail':
            case 'conflict':
            case 'warn':
                return AlertTriangle;
            default:
                return Info;
        }
    }

    function isSafeDetail(detail) {
        return (
            typeof detail === 'string' &&
            !/\b(?:uuid|capability|tier|digest|sha-?256)\b/i.test(detail) &&
            !/\b[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\b/i.test(
                detail,
            ) &&
            !/\b[a-z][\w.-]*@[0-9]+\b/i.test(detail)
        );
    }

    function confirm() {
        if (onConfirm && canConfirm) onConfirm({ phrase: phraseInput });
    }

    function reject() {
        if (onReject) onReject();
    }
</script>

<section
    class="border-primary/30 bg-card ring-primary/10 @container space-y-3 rounded-xl border p-3.5 text-xs shadow-md ring-1"
>
    {#if isCommitted}
        <div
            class="flex items-center gap-2 rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-2.5 text-xs font-semibold text-emerald-700 dark:text-emerald-300"
            role="status"
        >
            <CheckCircle2 class="size-4 shrink-0" />
            <span
                >{decodedView?.receipt?.outcome_label ||
                    actionPresentation.successMessage}</span
            >
        </div>
    {:else}
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0 flex-1 space-y-0.5">
                <h4
                    class="text-foreground text-xs leading-snug font-bold [overflow-wrap:anywhere] break-words"
                >
                    {displayTitle}
                </h4>
                <p class="text-muted-foreground text-[10px]">
                    Review details before confirming
                </p>
            </div>
            <span
                class={`max-w-full shrink rounded-full border px-2 py-0.5 text-center font-mono text-[9px] font-bold whitespace-normal ${statusClass}`}
            >
                {statusLabel}
            </span>
        </div>

        {#if decodedView}
            <div class="space-y-2.5">
                {#each decodedView.sections as section (section.key)}
                    {#if section.kind === 'fields'}
                        <div
                            class="border-border/60 bg-muted/15 space-y-1.5 rounded-lg border p-2.5"
                        >
                            {#if section.label && (decodedView.sections.length > 1 || section.label !== displayTitle)}
                                <h5
                                    class="text-foreground border-border/40 border-b pb-1 text-[11px] font-bold tracking-wider uppercase"
                                >
                                    {section.label}
                                </h5>
                            {/if}
                            <div class="space-y-1">
                                {#each section.fields as field (field.key)}
                                    <div
                                        class="border-border/30 grid grid-cols-1 items-start gap-2 border-b py-1 text-xs last:border-b-0 @min-[280px]:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)]"
                                    >
                                        <div class="min-w-0 space-y-0.5">
                                            <div
                                                class="flex flex-wrap items-center gap-1.5"
                                            >
                                                <span
                                                    class="text-foreground text-[11px] font-medium [overflow-wrap:anywhere] break-words"
                                                >
                                                    {field.label}
                                                </span>
                                                <span
                                                    class={`py-0.2 rounded border px-1.5 font-mono text-[9px] font-semibold uppercase ${roleBadgeClass(field.role)}`}
                                                >
                                                    {field.role}
                                                </span>
                                            </div>
                                            <div
                                                class="text-muted-foreground/80 text-[10px] [overflow-wrap:anywhere] break-words"
                                                title={formatXiamProposalFieldChange(
                                                    field,
                                                )}
                                            >
                                                {formatXiamProposalFieldChange(
                                                    field,
                                                )}
                                            </div>
                                        </div>
                                        <div
                                            class="min-w-0 space-y-0.5 text-left text-xs @min-[280px]:text-right"
                                        >
                                            {#if field.change === 'unchanged'}
                                                <div
                                                    class="text-muted-foreground text-[11px] italic"
                                                >
                                                    No change
                                                </div>
                                            {:else if field.change === 'clear'}
                                                <div class="space-y-0.5">
                                                    <div
                                                        class="text-muted-foreground text-[11px] [overflow-wrap:anywhere] break-words"
                                                    >
                                                        <span
                                                            class="text-muted-foreground/75 font-normal"
                                                            >Before:</span
                                                        >
                                                        <span
                                                            class="whitespace-pre-line"
                                                            >{formatXiamProposalValue(
                                                                field.before,
                                                            )}</span
                                                        >
                                                    </div>
                                                    <div
                                                        class="text-[11px] font-semibold [overflow-wrap:anywhere] break-words text-amber-600 dark:text-amber-400"
                                                    >
                                                        <span
                                                            class="text-muted-foreground font-normal"
                                                            >After:</span
                                                        >
                                                        <span>Cleared</span>
                                                    </div>
                                                </div>
                                            {:else}
                                                <div class="space-y-0.5">
                                                    <div
                                                        class="text-muted-foreground text-[11px] [overflow-wrap:anywhere] break-words"
                                                    >
                                                        <span
                                                            class="text-muted-foreground/75 font-normal"
                                                            >Before:</span
                                                        >
                                                        <span
                                                            class="whitespace-pre-line"
                                                            >{formatXiamProposalValue(
                                                                field.before,
                                                            )}</span
                                                        >
                                                    </div>
                                                    <div
                                                        class="text-foreground font-semibold [overflow-wrap:anywhere] break-words"
                                                    >
                                                        <span
                                                            class="text-muted-foreground text-[11px] font-normal"
                                                            >After:</span
                                                        >
                                                        <span
                                                            class="whitespace-pre-line"
                                                            >{formatXiamProposalValue(
                                                                field.after,
                                                            )}</span
                                                        >
                                                    </div>
                                                </div>
                                            {/if}
                                        </div>
                                    </div>
                                {/each}
                            </div>
                        </div>
                    {:else if section.kind === 'groups'}
                        <div
                            class="border-border/60 bg-muted/15 space-y-2 rounded-lg border p-2.5"
                        >
                            <div
                                class="border-border/40 flex items-center justify-between gap-2 border-b pb-1.5"
                            >
                                <h5
                                    class="text-foreground text-[11px] font-bold tracking-wider uppercase"
                                >
                                    {section.label}
                                </h5>
                                <span
                                    class="text-muted-foreground text-[10px] font-medium"
                                >
                                    {section.groups.length}
                                    {section.groups.length === 1
                                        ? 'item'
                                        : 'items'}
                                </span>
                            </div>

                            <div
                                class="max-h-80 space-y-2 overflow-y-auto pr-0.5"
                            >
                                {#each section.groups as group (group.key)}
                                    <div
                                        class="border-border/50 bg-background/60 space-y-1.5 rounded-md border p-2 text-xs"
                                    >
                                        <div
                                            class="flex flex-wrap items-center justify-between gap-1.5"
                                        >
                                            <span
                                                class="text-foreground text-xs font-semibold"
                                                >{group.label}</span
                                            >
                                            {#if group.context && group.context.type !== 'missing' && group.context.type !== 'null'}
                                                <span
                                                    class="text-muted-foreground text-[10px] [overflow-wrap:anywhere] break-words"
                                                >
                                                    {formatXiamProposalValue(
                                                        group.context,
                                                    )}
                                                </span>
                                            {/if}
                                        </div>

                                        <!-- Individual preflight for group -->
                                        {#if group.preflight?.checks && group.preflight.checks.length > 0}
                                            <div
                                                class="border-border/40 bg-muted/20 space-y-0.5 rounded p-1.5 text-[10px]"
                                            >
                                                {#each group.preflight.checks as check}
                                                    {@const PreflightIcon =
                                                        preflightIcon(check)}
                                                    <div
                                                        class={`flex items-start gap-1 ${preflightClass(check)}`}
                                                    >
                                                        <PreflightIcon
                                                            class="mt-0.5 size-3 shrink-0"
                                                        />
                                                        <span
                                                            class="font-medium"
                                                            >{checkLabel(
                                                                check,
                                                            )}</span
                                                        >
                                                        <span
                                                            class="font-semibold"
                                                            >({preflightLabel(
                                                                check,
                                                            )})</span
                                                        >
                                                        {#if check.detail && isSafeDetail(check.detail)}
                                                            <span
                                                                class="text-muted-foreground [overflow-wrap:anywhere] break-words"
                                                            >
                                                                {check.detail}
                                                            </span>
                                                        {/if}
                                                    </div>
                                                {/each}
                                            </div>
                                        {:else if group.preflight?.outcome && group.preflight.outcome !== 'pass'}
                                            <div
                                                class="text-[10px] font-medium text-rose-600 dark:text-rose-400"
                                            >
                                                Preflight: {group.preflight
                                                    .outcome}
                                            </div>
                                        {/if}

                                        <!-- Group fields -->
                                        {#if group.fields && group.fields.length > 0}
                                            <div
                                                class="border-border/30 space-y-1 border-t pt-1"
                                            >
                                                {#each group.fields as field (field.key)}
                                                    <div
                                                        class="grid grid-cols-1 items-start gap-1 py-0.5 text-xs @min-[280px]:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)]"
                                                    >
                                                        <div
                                                            class="min-w-0 space-y-0.5"
                                                        >
                                                            <div
                                                                class="flex flex-wrap items-center gap-1"
                                                            >
                                                                <span
                                                                    class="text-foreground text-[11px] font-medium [overflow-wrap:anywhere] break-words"
                                                                >
                                                                    {field.label}
                                                                </span>
                                                                <span
                                                                    class={`py-0.2 rounded border px-1 font-mono text-[8px] font-semibold uppercase ${roleBadgeClass(field.role)}`}
                                                                >
                                                                    {field.role}
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div
                                                            class="min-w-0 space-y-0.5 text-left text-xs @min-[280px]:text-right"
                                                        >
                                                            {#if field.change === 'unchanged'}
                                                                <div
                                                                    class="text-muted-foreground text-[10px] italic"
                                                                >
                                                                    {formatXiamProposalFieldChange(
                                                                        field,
                                                                    )}
                                                                </div>
                                                            {:else if field.change === 'clear'}
                                                                <div
                                                                    class="text-[10px] text-amber-600 dark:text-amber-400"
                                                                >
                                                                    {formatXiamProposalFieldChange(
                                                                        field,
                                                                    )}
                                                                </div>
                                                            {:else}
                                                                {#if field.before.type !== 'missing'}
                                                                    <div
                                                                        class="text-muted-foreground text-[10px] [overflow-wrap:anywhere] break-words"
                                                                    >
                                                                        <span
                                                                            class="text-muted-foreground/75 font-normal"
                                                                            >Before:</span
                                                                        >
                                                                        <span
                                                                            class="whitespace-pre-line"
                                                                            >{formatXiamProposalValue(
                                                                                field.before,
                                                                            )}</span
                                                                        >
                                                                    </div>
                                                                {/if}
                                                                <div
                                                                    class="text-foreground text-[11px] font-semibold [overflow-wrap:anywhere] break-words"
                                                                >
                                                                    {#if field.before.type !== 'missing'}
                                                                        <span
                                                                            class="text-muted-foreground text-[10px] font-normal"
                                                                            >After:</span
                                                                        >
                                                                    {/if}
                                                                    <span
                                                                        class="whitespace-pre-line"
                                                                        >{formatXiamProposalValue(
                                                                            field.after,
                                                                        )}</span
                                                                    >
                                                                </div>
                                                            {/if}
                                                        </div>
                                                    </div>
                                                {/each}
                                            </div>
                                        {/if}
                                    </div>
                                {/each}
                            </div>
                        </div>
                    {/if}
                {/each}
            </div>
        {:else}
            <div
                class="flex items-start gap-2 rounded-lg border border-amber-500/30 bg-amber-500/10 p-2.5 text-xs text-amber-700 dark:text-amber-300"
            >
                <AlertTriangle
                    class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400"
                />
                <span
                    >This proposal uses an older format and is no longer
                    actionable. Please submit a new request.</span
                >
            </div>

            {#if rows.length > 0}
                {#if isCreation}
                    <div
                        class="border-border/60 bg-muted/15 grid grid-cols-1 gap-2 rounded-lg border p-2.5 text-xs @min-[280px]:grid-cols-2"
                    >
                        {#each rows as row}
                            <div class="min-w-0">
                                <span
                                    class="text-muted-foreground block text-[10px] font-bold uppercase"
                                    >{row.entity}</span
                                >
                                <span
                                    class="text-foreground block text-xs font-semibold [overflow-wrap:anywhere] break-words"
                                    >{row.proposed}</span
                                >
                            </div>
                        {/each}
                    </div>
                {:else}
                    <div
                        class="border-border/60 bg-muted/15 space-y-1.5 rounded-lg border p-2.5"
                    >
                        {#each rows as row}
                            <div
                                class="grid grid-cols-1 items-start gap-2 text-xs @min-[280px]:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)]"
                            >
                                <span
                                    class="text-muted-foreground text-[11px] font-medium"
                                    >{row.entity}</span
                                >
                                <div
                                    class="min-w-0 space-y-1 text-right text-xs font-semibold"
                                >
                                    <div
                                        class="[overflow-wrap:anywhere] break-words"
                                    >
                                        <span
                                            class="text-muted-foreground font-normal"
                                            >Before:</span
                                        >
                                        {row.current || 'None'}
                                    </div>
                                    <div
                                        class="[overflow-wrap:anywhere] break-words"
                                    >
                                        <span
                                            class="text-muted-foreground font-normal"
                                            >After:</span
                                        >
                                        {row.proposed}
                                    </div>
                                </div>
                            </div>
                        {/each}
                    </div>
                {/if}
            {/if}
        {/if}

        {#if requiresReauth}
            <div
                class="rounded border border-amber-200 bg-amber-50 p-2 text-[10px] text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200"
                role="status"
            >
                Recent sign-in required to execute this action.
            </div>
        {/if}

        {#if confirmationPhrase}
            <div
                class="space-y-1.5 rounded-lg border border-amber-200 bg-amber-50 p-2.5 dark:border-amber-800 dark:bg-amber-950/30"
            >
                <label
                    class="block space-y-1 text-xs font-medium text-amber-950 dark:text-amber-100"
                >
                    <span>
                        Type "<strong class="font-mono"
                            >{confirmationPhrase}</strong
                        >" to confirm:
                    </span>
                    <input
                        type="text"
                        bind:value={phraseInput}
                        autocomplete="off"
                        placeholder={`Type "${confirmationPhrase}"`}
                        aria-label={`Type ${confirmationPhrase} to confirm this action`}
                        class="border-border bg-background text-foreground focus:ring-primary/20 w-full rounded-md border px-2.5 py-1.5 font-mono text-xs outline-none focus:ring-2"
                    />
                </label>
            </div>
        {/if}

        {#if preflightChecks.length > 0}
            <div
                class="border-border/60 bg-muted/20 space-y-1 rounded-lg border p-2 text-xs"
            >
                {#each preflightChecks as check}
                    {@const PreflightIcon = preflightIcon(check)}
                    <div
                        class={`flex items-start gap-1.5 ${preflightClass(check)}`}
                    >
                        <PreflightIcon class="mt-0.5 size-3.5 shrink-0" />
                        <span class="text-[11px] font-medium"
                            >{checkLabel(check)}</span
                        >
                        <span class="text-[10px] font-semibold"
                            >{preflightLabel(check)}</span
                        >
                        {#if !FRIENDLY_CHECKS[check.key] && check.detail && check.detail !== 'pass' && check.detail !== check.label && isSafeDetail(check.detail)}
                            <span
                                class="text-muted-foreground text-[10px] [overflow-wrap:anywhere] break-words"
                                >{check.detail}</span
                            >
                        {/if}
                    </div>
                {/each}
            </div>
        {:else if decodedView?.preflight?.outcome && decodedView.preflight.outcome === 'fail'}
            <div
                class="border-border/60 bg-muted/20 flex items-start gap-1.5 rounded-lg border p-2 text-xs text-rose-600 dark:text-rose-400"
            >
                <AlertTriangle class="mt-0.5 size-3.5 shrink-0" />
                <span class="text-[11px] font-medium"
                    >Preflight check failed.</span
                >
            </div>
        {/if}

        {#if structuredError}
            <div
                class="space-y-1.5 rounded-lg border border-rose-500/30 bg-rose-500/10 p-2.5 text-xs text-rose-700 dark:text-rose-300"
                role="alert"
            >
                <div class="flex items-start gap-2">
                    <AlertTriangle
                        class="mt-0.5 size-4 shrink-0 text-rose-600 dark:text-rose-400"
                    />
                    <div class="min-w-0 flex-1 space-y-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-xs font-bold"
                                >{structuredError.message}</span
                            >
                            <span
                                class="py-0.2 rounded border border-rose-500/30 bg-rose-500/20 px-1.5 font-mono text-[9px] text-rose-700 uppercase dark:text-rose-300"
                            >
                                {structuredError.category}
                            </span>
                        </div>
                        {#if structuredError.remediation && structuredError.remediation !== 'none'}
                            <p
                                class="text-[11px] text-rose-600/90 dark:text-rose-300/90"
                            >
                                <span class="font-semibold">Remediation:</span>
                                <span
                                    >{remediationText(
                                        structuredError.remediation,
                                    )}</span
                                >
                            </p>
                        {/if}
                    </div>
                </div>
            </div>
        {:else if currentStatus === 'rejected' || currentStatus === 'cancelled'}
            <div
                class="flex items-center gap-2 rounded-lg border border-rose-500/30 bg-rose-500/10 p-2 text-xs text-rose-700 dark:text-rose-300"
            >
                <X class="size-3.5 shrink-0" />
                <span>Proposal cancelled.</span>
            </div>
        {:else if currentStatus === 'invalidated'}
            <div
                class="flex items-center gap-2 rounded-lg border border-amber-500/30 bg-amber-500/10 p-2 text-xs text-amber-700 dark:text-amber-300"
            >
                <AlertTriangle class="size-3.5 shrink-0" />
                <span>Proposal is no longer current.</span>
            </div>
        {:else if currentStatus === 'conflicted'}
            <div
                class="flex items-center gap-2 rounded-lg border border-amber-500/30 bg-amber-500/10 p-2 text-xs text-amber-700 dark:text-amber-300"
            >
                <AlertTriangle class="size-3.5 shrink-0" />
                <span>Proposal conflicted with concurrent changes.</span>
            </div>
        {:else if currentStatus === 'expired'}
            <div
                class="flex items-center gap-2 rounded-lg border border-amber-500/30 bg-amber-500/10 p-2 text-xs text-amber-700 dark:text-amber-300"
            >
                <AlertTriangle class="size-3.5 shrink-0" />
                <span>Proposal has expired.</span>
            </div>
        {:else if currentStatus === 'retired'}
            <div
                class="flex items-center gap-2 rounded-lg border border-rose-500/30 bg-rose-500/10 p-2 text-xs text-rose-700 dark:text-rose-300"
                role="alert"
            >
                <AlertTriangle class="size-3.5 shrink-0" />
                <span
                    >This capability has been retired and is no longer
                    available.</span
                >
            </div>
        {:else if currentStatus === 'failed'}
            <div
                class="flex items-center gap-2 rounded-lg border border-rose-500/30 bg-rose-500/10 p-2 text-xs text-rose-700 dark:text-rose-300"
                role="alert"
            >
                <AlertTriangle class="size-3.5 shrink-0" />
                <span>{proposal?.error ?? 'Execution failed.'}</span>
            </div>
        {/if}

        {#if !isNonActionable}
            <div
                class="border-border flex flex-col items-stretch justify-end gap-2 border-t pt-2 @min-[280px]:flex-row @min-[280px]:items-center"
            >
                <button
                    type="button"
                    class="border-border text-muted-foreground hover:bg-muted hover:text-foreground inline-flex w-full items-center justify-center gap-1 rounded-lg border px-2.5 py-1 text-xs font-medium transition-colors disabled:cursor-not-allowed disabled:opacity-60 @min-[280px]:w-auto"
                    disabled={isConfirming}
                    onclick={reject}
                >
                    <X class="size-3" />
                    Cancel
                </button>
                <button
                    type="button"
                    class="bg-primary text-primary-foreground inline-flex w-full items-center justify-center gap-1 rounded-lg px-3 py-1 text-xs font-bold shadow-sm transition hover:opacity-90 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 @min-[280px]:w-auto"
                    disabled={isConfirming ||
                        !canConfirm ||
                        preflightChecks.some((check) =>
                            ['fail', 'conflict'].includes(check?.status),
                        )}
                    onclick={confirm}
                >
                    {#if isConfirming}
                        <LoaderCircle class="size-3.5 animate-spin" />
                        <span>Executing…</span>
                    {:else}
                        <ShieldCheck class="size-3.5" />
                        <span>Confirm &amp; execute</span>
                    {/if}
                </button>
            </div>
        {/if}
    {/if}
</section>
