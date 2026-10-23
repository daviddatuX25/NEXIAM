<script>
    /**
     * P-REG-02 — Registrar attempt detail.
     * Legacy feel (Applications/Show): status+action bar, Card dossier sections, Timeline.
     * Nav: StaffLayout breadcrumbs only — no redundant Back link.
     * Action matrix: openspec/changes/admission-attempt-detail/design.md
     */
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import HeaderActions from '@/Components/kit/HeaderActions.svelte';
    import ActionDropdown from '@/Components/kit/ActionDropdown.svelte';
    import ApplicantCredentialHandoffCard from '@/Components/Admission/ApplicantCredentialHandoffCard.svelte';
    import DocumentRequirementsPanel from '@/Components/Admission/DocumentRequirementsPanel.svelte';
    import ApplicantActionsMenu from '@/Components/Admission/ApplicantActionsMenu.svelte';
    import PrintDocumentsSlot from '@/Components/DocumentTemplate/PrintDocumentsSlot.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import { Label } from '@/Components/ui/label';
    import { Textarea } from '@/Components/ui/textarea';
    import * as Card from '@/Components/ui/card';
    import * as Dialog from '@/Components/ui/dialog';
    import ReasonSuggestionChips from '@/Components/kit/ReasonSuggestionChips.svelte';
    import {
        formatBirthdateDisplay,
        formatDateTime,
    } from '@/lib/date-utils.js';
    import {
        ADMISSION_DISMISS_SUGGESTIONS,
        ADMISSION_REQUEST_CORRECTION_SUGGESTIONS,
        ADMISSION_REOPEN_SUGGESTIONS,
        ADMISSION_RECONSIDER_SUGGESTIONS,
        ADMISSION_REISSUE_KEY_SUGGESTIONS,
    } from '@/lib/reason-suggestions.js';
    import { router } from '@inertiajs/svelte';
    import {
        Archive,
        BadgeInfo,
        BookOpen,
        Building2,
        Calendar,
        CheckCheck,
        CheckCircle,
        Clock,
        FileEdit,
        FileWarning,
        GraduationCap,
        Hash,
        KeyRound,
        Mail,
        MapPin,
        Phone,
        RotateCcw,
        School,
        Sparkles,
        Undo2,
        User,
        XCircle,
    } from 'lucide-svelte';

    let {
        page_state = 'ready',
        attempt,
        programs = [],
        reissued_access_key = null,
        credential_handoff = null,
        capabilities = { accept: false, dismiss: false, correct: false },
        document_checklist = null,
        request_history = [],
    } = $props();

    let dismissOpen = $state(false);
    let requestOpen = $state(false);
    let archiveOpen = $state(false);
    let reopenOpen = $state(false);
    let reconsiderOpen = $state(false);
    let reissueOpen = $state(false);
    let acceptAndVerifyOpen = $state(false);
    let isAcceptingAndVerifying = $state(false);

    let dismissReason = $state('');
    let requestReason = $state('');
    let reopenReason = $state('');
    let reissueReason = $state('');
    let reconsiderReason = $state('');

    const canDecide = $derived(
        attempt.status === 'pending' || attempt.status === 'needs_correction',
    );
    const canVerifyDocs = $derived(
        Boolean(
            document_checklist?.flags?.documents_verify && !isMutationFenced,
        ),
    );
    const unverifiedItems = $derived(
        (document_checklist?.items ?? []).filter(
            (item) => item.latest_status !== 'verified',
        ),
    );
    const canAcceptAndVerifyAll = $derived(
        capabilities.accept &&
            canVerifyDocs &&
            canDecide &&
            unverifiedItems.length > 0 &&
            !isMutationFenced,
    );
    const canRequestCorrection = $derived(
        attempt.status === 'pending' && capabilities.correct,
    );
    const canEditFields = $derived(canDecide && capabilities.correct);
    const isAccepted = $derived(attempt.status === 'accepted');
    const isMutationFenced = $derived(
        Boolean(
            document_checklist?.is_clearance_confirmed ||
            attempt.is_clearance_confirmed ||
            attempt.has_active_legacy_stamp,
        ),
    );
    /** Authority paths after accept — not quiet Correct. */
    const canAuthorizeAmend = $derived(
        isAccepted && capabilities.accept && !isMutationFenced,
    );
    const canReconsider = $derived(
        isAccepted && capabilities.accept && !isMutationFenced,
    );
    const canRevert = $derived(
        (attempt.status === 'dismissed' || attempt.status === 'archived') &&
            capabilities.accept,
    );
    const canArchive = $derived(
        attempt.status !== 'archived' && capabilities.dismiss,
    );
    const canReissueKey = $derived(
        attempt.status !== 'archived' && capabilities.correct,
    );
    const overflowItems = $derived(
        [
            capabilities.dismiss && canDecide
                ? {
                      label: 'Dismiss',
                      icon: XCircle,
                      danger: true,
                      onclick: () => {
                          dismissReason = '';
                          dismissOpen = true;
                      },
                  }
                : null,
            canRequestCorrection
                ? {
                      label: 'Ask applicant to correct',
                      icon: FileWarning,
                      onclick: () => {
                          requestReason = '';
                          requestOpen = true;
                      },
                  }
                : null,
            canReissueKey
                ? {
                      label: 'Reissue access key',
                      icon: KeyRound,
                      onclick: () => {
                          reissueReason = '';
                          reissueOpen = true;
                      },
                  }
                : null,
            canReconsider
                ? {
                      label: 'Reconsider acceptance',
                      icon: RotateCcw,
                      onclick: () => {
                          reconsiderReason = '';
                          reconsiderOpen = true;
                      },
                  }
                : null,
            canRevert
                ? {
                      label: 'Revert to pending',
                      icon: Undo2,
                      onclick: () => {
                          reopenReason = '';
                          reopenOpen = true;
                      },
                  }
                : null,
            canArchive
                ? {
                      label: 'Archive',
                      icon: Archive,
                      onclick: () => {
                          archiveOpen = true;
                      },
                  }
                : null,
        ].filter(Boolean),
    );
    const hasSecondaryActions = $derived(
        Boolean(canEditFields || canAuthorizeAmend || overflowItems.length > 0),
    );
    const addressLine = $derived(
        [
            attempt.address?.line,
            attempt.address?.city,
            attempt.address?.province,
            attempt.address?.zip_code,
        ]
            .filter(Boolean)
            .join(', ') || '—',
    );

    const preferenceList = $derived(
        attempt.program_preferences?.length
            ? attempt.program_preferences
            : attempt.preferred_program_code
              ? [attempt.preferred_program_code]
              : [],
    );

    function programName(code) {
        if (!code) return null;
        const match = (programs ?? []).find((p) => p.code === code);
        return match?.name ?? null;
    }

    function statusVariant(status) {
        switch (status) {
            case 'accepted':
                return 'default';
            case 'needs_correction':
                return 'outline';
            case 'dismissed':
                return 'destructive';
            default:
                return 'secondary';
        }
    }

    function statusLabel(status) {
        const labels = {
            pending: 'Pending',
            needs_correction: 'Needs correction',
            accepted: 'Accepted',
            dismissed: 'Dismissed',
            archived: 'Archived',
        };
        return labels[status] ?? status;
    }

    function accept() {
        router.post(`/admissions/${attempt.id}/accept`, {
            expected_version: attempt.version,
        });
    }

    function submitAcceptAndVerifyAll() {
        if (isAcceptingAndVerifying) return;
        isAcceptingAndVerifying = true;
        router.post(
            `/admissions/${attempt.id}/accept`,
            {
                expected_version: attempt.version,
                verify_documents: true,
            },
            {
                onSuccess: () => {
                    acceptAndVerifyOpen = false;
                },
                onFinish: () => {
                    isAcceptingAndVerifying = false;
                },
            },
        );
    }

    function submitDismiss() {
        if (!dismissReason.trim()) return;
        router.post(
            `/admissions/${attempt.id}/dismiss`,
            {
                expected_version: attempt.version,
                reason: dismissReason.trim(),
            },
            {
                onSuccess: () => {
                    dismissOpen = false;
                },
            },
        );
    }

    function submitRequestCorrection() {
        if (!requestReason.trim()) return;
        router.post(
            `/admissions/${attempt.id}/request-correction`,
            {
                expected_version: attempt.version,
                reason: requestReason.trim(),
            },
            {
                onSuccess: () => {
                    requestOpen = false;
                },
            },
        );
    }

    function submitArchive() {
        router.post(
            `/admissions/${attempt.id}/archive`,
            { expected_version: attempt.version },
            {
                onSuccess: () => {
                    archiveOpen = false;
                },
            },
        );
    }

    function submitReopen() {
        if (!reopenReason.trim()) return;
        router.post(
            `/admissions/${attempt.id}/reopen`,
            {
                expected_version: attempt.version,
                reason: reopenReason.trim(),
            },
            {
                onSuccess: () => {
                    reopenOpen = false;
                },
            },
        );
    }

    function submitReconsider() {
        if (!reconsiderReason.trim()) return;
        router.post(
            `/admissions/${attempt.id}/reconsider`,
            {
                expected_version: attempt.version,
                reason: reconsiderReason.trim(),
            },
            {
                onSuccess: () => {
                    reconsiderOpen = false;
                },
            },
        );
    }

    function submitReissue() {
        router.post(
            `/admissions/${attempt.id}/reissue-access-key`,
            {
                expected_version: attempt.version,
                reason: reissueReason.trim(),
            },
            {
                onSuccess: () => {
                    reissueOpen = false;
                },
            },
        );
    }

    const breadcrumbs = [
        { label: 'Admissions', href: '/admissions' },
        { label: attempt.reference_code ?? 'Application' },
    ];
</script>

<svelte:head><title>{attempt.reference_code} · Admissions</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title={attempt.applicant_name}
            description={`Reference ${attempt.reference_code} · v${attempt.version} · ${attempt.cycle?.name ?? 'Cycle —'}`}
        >
            {#snippet actions()}
                <Badge
                    variant={statusVariant(attempt.status)}
                    class="shrink-0 text-sm capitalize"
                >
                    {statusLabel(attempt.status)}
                </Badge>
                {#snippet secondaryActionsSnippet()}
                    {#if canEditFields || canAuthorizeAmend}
                        <Button
                            size="sm"
                            variant="outline"
                            class="min-h-10 gap-1.5"
                            href={`/admissions/${attempt.id}/edit`}
                        >
                            <FileEdit class="size-4" />
                            {canAuthorizeAmend
                                ? 'Authorize amendment'
                                : 'Edit application'}
                        </Button>
                    {/if}
                    {#if overflowItems.length > 0}
                        <ActionDropdown items={overflowItems} />
                    {/if}
                {/snippet}
                <HeaderActions
                    secondaryActions={hasSecondaryActions
                        ? secondaryActionsSnippet
                        : null}
                >
                    {#snippet primaryAction()}
                        <ApplicantActionsMenu attemptId={attempt.id} context={{ program_code: attempt.admitted_program_code ?? null }} />
                        {#if capabilities.accept && canDecide}
                            <div class="flex flex-wrap items-center gap-2">
                                <Button
                                    size="sm"
                                    class="min-h-10"
                                    onclick={accept}
                                >
                                    <CheckCircle class="mr-1.5 size-4" />Accept
                                </Button>
                                {#if canAcceptAndVerifyAll}
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="min-h-10 gap-1.5 border-emerald-600/30 text-emerald-700 hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-950/40"
                                        title="Accept application and verify all document requirements"
                                        onclick={() =>
                                            (acceptAndVerifyOpen = true)}
                                    >
                                        <CheckCheck
                                            class="size-4 text-emerald-600 dark:text-emerald-400"
                                        />
                                        Accept & verify all documents
                                    </Button>
                                {/if}
                            </div>
                        {/if}
                    {/snippet}
                </HeaderActions>
                <div
                    class="shrink-0 [&_button]:min-h-10 [&>button>span]:sr-only sm:[&>button>span]:not-sr-only"
                >
                    <PrintDocumentsSlot
                        slotKey="admissions_applicant_show"
                        attemptIds={[attempt.id]}
                        autoStart={true}
                    />
                </div>
            {/snippet}
        </PageHeaderBar>

        {#if credential_handoff || reissued_access_key}
            <ApplicantCredentialHandoffCard
                accessKey={reissued_access_key}
                handoff={credential_handoff}
                referenceCode={attempt.reference_code}
                applicantName={attempt.applicant_name}
                birthdate={attempt.identity?.birthdate}
                mode="staff"
            />
        {/if}

        {#if isAccepted}
            <p
                class="border-border bg-muted/30 rounded-lg border px-3 py-2 text-sm"
            >
                Accepted — exam-eligible. Quiet Update / Ask correction stay
                off. Use <span class="text-foreground font-medium"
                    >Authorize amendment</span
                >
                for wrong name/details (stays accepted, no retake). Use
                <span class="text-foreground font-medium"
                    >Reconsider acceptance</span
                > only if the decision itself must return to pending.
            </p>
        {/if}
        {#if attempt.status === 'needs_correction' && attempt.correction_notes}
            <p
                class="border-border bg-muted/30 text-muted-foreground rounded-lg border px-3 py-2 text-sm"
            >
                Waiting on applicant: {attempt.correction_notes}
            </p>
        {/if}
        {#if attempt.status === 'dismissed' && attempt.dismissal_reason}
            <p
                class="border-border bg-muted/30 text-muted-foreground rounded-lg border px-3 py-2 text-sm"
            >
                Dismissal reason: {attempt.dismissal_reason}
            </p>
        {/if}

        <div
            class="grid min-w-0 grid-cols-1 items-start gap-6 lg:grid-cols-12"
            data-testid="admission-dossier-split"
        >
            <div
                class="min-w-0 space-y-6 lg:col-span-6"
                data-testid="admission-dossier-left"
            >
                <Card.Root>
                    <Card.Header class="border-border/60 border-b pb-4">
                        <Card.Title class="flex items-center gap-2 text-base">
                            <User class="text-primary size-4 shrink-0" />
                            Personal details
                        </Card.Title>
                    </Card.Header>
                    <Card.Content class="space-y-3 pt-4 text-sm">
                        <div class="space-y-2.5">
                            <p class="flex items-start gap-2.5">
                                <User
                                    class="text-muted-foreground mt-0.5 size-4 shrink-0"
                                />
                                <span>
                                    <span class="text-muted-foreground"
                                        >Name:</span
                                    >
                                    <span class="text-foreground font-medium">
                                        {attempt.applicant_name}
                                    </span>
                                </span>
                            </p>
                            <p class="flex items-start gap-2.5">
                                <Calendar
                                    class="text-muted-foreground mt-0.5 size-4 shrink-0"
                                />
                                <span>
                                    <span class="text-muted-foreground"
                                        >Birthdate:</span
                                    >
                                    <span class="text-foreground font-medium">
                                        {formatBirthdateDisplay(
                                            attempt.identity?.birthdate,
                                        )}
                                    </span>
                                </span>
                            </p>
                            <p class="flex items-start gap-2.5">
                                <BadgeInfo
                                    class="text-muted-foreground mt-0.5 size-4 shrink-0"
                                />
                                <span>
                                    <span class="text-muted-foreground"
                                        >Sex:</span
                                    >
                                    <span
                                        class="text-foreground font-medium capitalize"
                                        >{attempt.identity?.sex ?? '—'}</span
                                    >
                                </span>
                            </p>
                            <p class="flex flex-wrap items-center gap-2">
                                <Sparkles
                                    class="text-muted-foreground size-4 shrink-0"
                                />
                                <span class="text-muted-foreground"
                                    >Classification:</span
                                >
                                <span
                                    class="border-primary/20 bg-primary/10 text-primary inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium capitalize"
                                >
                                    {attempt.applicant_type === 'transferee'
                                        ? 'Transferee'
                                        : (attempt.applicant_type ??
                                          'New student')}
                                </span>
                            </p>
                            {#if attempt.last_school_enrolled}
                                <p class="flex items-start gap-2.5">
                                    <School
                                        class="text-muted-foreground mt-0.5 size-4 shrink-0"
                                    />
                                    <span>
                                        <span class="text-muted-foreground"
                                            >Last school enrolled:</span
                                        >
                                        <span
                                            class="text-foreground font-medium"
                                        >
                                            {attempt.last_school_enrolled}
                                        </span>
                                    </span>
                                </p>
                            {/if}
                            {#if attempt.strand}
                                <p class="flex flex-wrap items-center gap-2">
                                    <BookOpen
                                        class="text-muted-foreground size-4 shrink-0"
                                    />
                                    <span class="text-muted-foreground"
                                        >SHS strand / previous course:</span
                                    >
                                    <span
                                        class="inline-flex items-center rounded-md border border-amber-500/25 bg-amber-500/10 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:text-amber-300"
                                    >
                                        {attempt.strand}
                                    </span>
                                </p>
                            {/if}
                        </div>

                        <div class="border-border/50 space-y-2.5 border-t pt-3">
                            <p class="flex items-start gap-2.5">
                                <Mail
                                    class="text-muted-foreground mt-0.5 size-4 shrink-0"
                                />
                                <span class="min-w-0 break-all">
                                    <span class="text-muted-foreground"
                                        >Email:</span
                                    >
                                    <span class="text-foreground font-medium">
                                        {attempt.contact_email ?? '—'}
                                    </span>
                                </span>
                            </p>
                            <p class="flex items-start gap-2.5">
                                <Phone
                                    class="text-muted-foreground mt-0.5 size-4 shrink-0"
                                />
                                <span>
                                    <span class="text-muted-foreground"
                                        >Phone:</span
                                    >
                                    <span
                                        class="text-foreground font-mono text-xs font-medium"
                                    >
                                        {attempt.contact_phone ?? '—'}
                                    </span>
                                </span>
                            </p>
                            <p class="flex items-start gap-2.5">
                                <MapPin
                                    class="text-muted-foreground mt-0.5 size-4 shrink-0"
                                />
                                <span>
                                    <span class="text-muted-foreground"
                                        >Address:</span
                                    >
                                    <span class="text-foreground font-medium">
                                        {addressLine}
                                    </span>
                                </span>
                            </p>
                        </div>
                    </Card.Content>
                </Card.Root>

                <Card.Root>
                    <Card.Header class="border-border/60 border-b pb-4">
                        <Card.Title class="flex items-center gap-2 text-base">
                            <GraduationCap
                                class="text-primary size-4 shrink-0"
                            />
                            Program preferences
                        </Card.Title>
                    </Card.Header>
                    <Card.Content class="space-y-3.5 pt-4 text-sm">
                        {#if preferenceList.length}
                            <div
                                class="border-primary/25 bg-primary/5 rounded-xl border p-3.5"
                                data-testid="primary-program-highlight"
                            >
                                <div class="flex items-start gap-3">
                                    <div
                                        class="bg-primary/15 text-primary flex size-10 shrink-0 items-center justify-center rounded-lg"
                                    >
                                        <GraduationCap class="size-5" />
                                    </div>
                                    <div class="min-w-0 flex-1 space-y-1">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <span
                                                class="text-muted-foreground text-xs font-semibold tracking-wider uppercase"
                                            >
                                                1st:
                                            </span>
                                            <Badge
                                                variant="default"
                                                class="font-mono text-xs font-semibold tracking-wide"
                                            >
                                                {preferenceList[0] ?? '—'}
                                            </Badge>
                                        </div>
                                        <p
                                            class="text-foreground text-sm leading-snug font-semibold sm:text-base"
                                        >
                                            {programName(preferenceList[0]) ??
                                                preferenceList[0] ??
                                                '—'}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {#if preferenceList.length > 1}
                                <div class="space-y-2">
                                    {#each preferenceList.slice(1) as code, idx}
                                        {@const i = idx + 1}
                                        <div
                                            class="border-border/60 bg-muted/25 flex items-center justify-between gap-2 rounded-lg border px-3 py-2"
                                        >
                                            <p
                                                class="flex min-w-0 items-center gap-2"
                                            >
                                                <BookOpen
                                                    class="text-muted-foreground size-3.5 shrink-0"
                                                />
                                                <span
                                                    class="text-muted-foreground text-xs font-medium"
                                                >
                                                    {i === 1
                                                        ? '2nd'
                                                        : `${i + 1}th`}:
                                                </span>
                                                <span
                                                    class="text-foreground truncate text-sm font-semibold"
                                                >
                                                    {programName(code) ??
                                                        code ??
                                                        '—'}
                                                </span>
                                            </p>
                                            <Badge
                                                variant="outline"
                                                class="shrink-0 font-mono text-xs font-semibold"
                                            >
                                                {code ?? '—'}
                                            </Badge>
                                        </div>
                                    {/each}
                                </div>
                            {/if}
                        {:else}
                            <div
                                class="border-border/60 bg-muted/20 text-muted-foreground flex items-center gap-2.5 rounded-lg border border-dashed px-3.5 py-3 text-sm"
                            >
                                <GraduationCap class="size-4 shrink-0" />
                                <p>No preferences recorded.</p>
                            </div>
                        {/if}

                        {#if attempt.strand}
                            <div
                                class="border-border/50 flex flex-wrap items-center gap-2 border-t pt-3"
                            >
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-md border border-amber-500/25 bg-amber-500/10 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:text-amber-300"
                                >
                                    <BookOpen class="size-3.5 shrink-0" />
                                    <span>Strand: {attempt.strand}</span>
                                </span>
                            </div>
                        {/if}

                        <div
                            class="border-border/50 space-y-1.5 border-t pt-3 text-xs"
                        >
                            <p
                                class="text-muted-foreground flex items-center gap-2"
                            >
                                <Building2
                                    class="text-muted-foreground size-3.5 shrink-0"
                                />
                                <span>
                                    Cycle: {attempt.cycle?.name ?? '—'} ({attempt
                                        .cycle?.code ?? '—'})
                                </span>
                            </p>
                            <p
                                class="text-muted-foreground flex items-center gap-2"
                            >
                                <Hash
                                    class="text-muted-foreground size-3.5 shrink-0"
                                />
                                <span>
                                    Reference:
                                    <span class="text-foreground font-mono"
                                        >{attempt.reference_code}</span
                                    >
                                    · v{attempt.version}
                                </span>
                            </p>
                        </div>
                    </Card.Content>
                </Card.Root>

                <Card.Root>
                    <Card.Header class="border-border/60 border-b pb-4">
                        <Card.Title class="flex items-center gap-2 text-base">
                            <Clock class="text-primary size-4 shrink-0" />
                            Timeline
                        </Card.Title>
                    </Card.Header>
                    <Card.Content class="space-y-2.5 pt-4 text-sm">
                        <p class="flex items-start gap-2.5">
                            <Clock
                                class="text-muted-foreground mt-0.5 size-4 shrink-0"
                            />
                            <span>
                                <span class="text-muted-foreground"
                                    >Submitted:</span
                                >
                                <span class="text-foreground font-medium">
                                    {formatDateTime(attempt.submitted_at)}
                                </span>
                            </span>
                        </p>
                        {#if attempt.accepted_at}
                            <p class="flex items-start gap-2.5">
                                <CheckCircle
                                    class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400"
                                />
                                <span>
                                    <span class="text-muted-foreground"
                                        >Accepted:</span
                                    >
                                    <span class="text-foreground font-medium">
                                        {formatDateTime(attempt.accepted_at)}
                                    </span>
                                </span>
                            </p>
                        {/if}
                        {#if attempt.dismissed_at}
                            <p class="flex items-start gap-2.5">
                                <XCircle
                                    class="text-destructive mt-0.5 size-4 shrink-0"
                                />
                                <span>
                                    <span class="text-muted-foreground"
                                        >Dismissed:</span
                                    >
                                    <span class="text-foreground font-medium">
                                        {formatDateTime(attempt.dismissed_at)}
                                    </span>
                                </span>
                            </p>
                        {/if}
                        {#if attempt.archived_at}
                            <p class="flex items-start gap-2.5">
                                <Calendar
                                    class="text-muted-foreground mt-0.5 size-4 shrink-0"
                                />
                                <span>
                                    <span class="text-muted-foreground"
                                        >Archived:</span
                                    >
                                    <span class="text-foreground font-medium">
                                        {formatDateTime(attempt.archived_at)}
                                    </span>
                                </span>
                            </p>
                        {/if}
                        {#if attempt.status === 'dismissed' && attempt.dismissal_reason}
                            <p class="flex items-start gap-2.5">
                                <BadgeInfo
                                    class="text-destructive mt-0.5 size-4 shrink-0"
                                />
                                <span>
                                    <span class="text-muted-foreground"
                                        >Dismissal reason:</span
                                    >
                                    <span class="text-foreground font-medium">
                                        {attempt.dismissal_reason}
                                    </span>
                                </span>
                            </p>
                        {/if}
                    </Card.Content>
                </Card.Root>
            </div>
            <div
                class="min-w-0 space-y-6 lg:col-span-6"
                data-testid="admission-dossier-right"
                data-tour="document-requirements-panel"
            >
                <DocumentRequirementsPanel
                    attemptId={attempt.id}
                    checklist={document_checklist}
                    requestHistory={document_checklist?.request_history ??
                        request_history}
                    expectedVersion={document_checklist?.expected_version ??
                        attempt.version}
                    flags={document_checklist?.flags}
                    mutationBlockReason={document_checklist?.mutation_block_reason}
                    isClearanceConfirmed={Boolean(
                        document_checklist?.is_clearance_confirmed ??
                        attempt.is_clearance_confirmed ??
                        false,
                    )}
                    verifyUrl={`/registrar/enrollment-eligibility/${attempt.id}/documents/verify`}
                    requestUrl={`/admissions/${attempt.id}/document-correction-requests`}
                />
            </div>
        </div>
    </div>
</StaffLayout>

<Dialog.Root bind:open={dismissOpen}>
    <Dialog.Content class="sm:max-w-md">
        <Dialog.Header>
            <Dialog.Title>Dismiss application</Dialog.Title>
            <Dialog.Description
                >Requires a reason. History is retained.</Dialog.Description
            >
        </Dialog.Header>
        <div class="space-y-2 py-2">
            <Label for="dismiss-reason">Reason</Label>
            <Textarea
                id="dismiss-reason"
                bind:value={dismissReason}
                rows={3}
                class="min-h-24"
            />
            <ReasonSuggestionChips
                ariaLabel="Dismissal reason suggestions"
                suggestions={ADMISSION_DISMISS_SUGGESTIONS}
                bind:value={dismissReason}
                maxlength={2000}
            />
        </div>
        <Dialog.Footer>
            <Button variant="outline" onclick={() => (dismissOpen = false)}
                >Cancel</Button
            >
            <Button
                variant="destructive"
                disabled={!dismissReason.trim()}
                onclick={submitDismiss}>Dismiss</Button
            >
        </Dialog.Footer>
    </Dialog.Content>
</Dialog.Root>

<Dialog.Root bind:open={requestOpen}>
    <Dialog.Content class="sm:max-w-md">
        <Dialog.Header>
            <Dialog.Title>Ask applicant to correct</Dialog.Title>
            <Dialog.Description>
                Moves status to needs correction and records instructions for
                the applicant (notice delivery later).
            </Dialog.Description>
        </Dialog.Header>
        <div class="space-y-2 py-2">
            <Label for="request-reason">What must be corrected?</Label>
            <Textarea
                id="request-reason"
                bind:value={requestReason}
                rows={4}
                class="min-h-28"
            />
            <ReasonSuggestionChips
                ariaLabel="Correction reason suggestions"
                suggestions={ADMISSION_REQUEST_CORRECTION_SUGGESTIONS}
                bind:value={requestReason}
                maxlength={2000}
            />
        </div>
        <Dialog.Footer>
            <Button variant="outline" onclick={() => (requestOpen = false)}
                >Cancel</Button
            >
            <Button
                disabled={!requestReason.trim()}
                onclick={submitRequestCorrection}>Send request</Button
            >
        </Dialog.Footer>
    </Dialog.Content>
</Dialog.Root>

<Dialog.Root bind:open={archiveOpen}>
    <Dialog.Content class="sm:max-w-md">
        <Dialog.Header>
            <Dialog.Title>Archive application</Dialog.Title>
            <Dialog.Description>
                Removes it from the active queue. Identity and history are kept.
            </Dialog.Description>
        </Dialog.Header>
        <Dialog.Footer>
            <Button variant="outline" onclick={() => (archiveOpen = false)}
                >Cancel</Button
            >
            <Button onclick={submitArchive}>Archive</Button>
        </Dialog.Footer>
    </Dialog.Content>
</Dialog.Root>

<Dialog.Root bind:open={reopenOpen}>
    <Dialog.Content class="sm:max-w-md">
        <Dialog.Header>
            <Dialog.Title>Revert to pending</Dialog.Title>
            <Dialog.Description>
                Non-destructive reopen — never deletes identity or history.
            </Dialog.Description>
        </Dialog.Header>
        <div class="space-y-2 py-2">
            <Label for="reopen-reason">Reason</Label>
            <Textarea
                id="reopen-reason"
                bind:value={reopenReason}
                rows={3}
                class="min-h-24"
            />
            <ReasonSuggestionChips
                ariaLabel="Reopen reason suggestions"
                suggestions={ADMISSION_REOPEN_SUGGESTIONS}
                bind:value={reopenReason}
                maxlength={2000}
            />
        </div>
        <Dialog.Footer>
            <Button variant="outline" onclick={() => (reopenOpen = false)}
                >Cancel</Button
            >
            <Button disabled={!reopenReason.trim()} onclick={submitReopen}
                >Revert</Button
            >
        </Dialog.Footer>
    </Dialog.Content>
</Dialog.Root>

<Dialog.Root bind:open={reconsiderOpen}>
    <Dialog.Content class="sm:max-w-md">
        <Dialog.Header>
            <Dialog.Title>Reconsider acceptance</Dialog.Title>
            <Dialog.Description>
                Returns status to pending (decision pullback). Existing exam
                links are preserved for reconciliation — new scheduling requires
                Accept again. Do not use this for a typo; use Authorize
                amendment instead.
            </Dialog.Description>
        </Dialog.Header>
        <div class="space-y-2 py-2">
            <Label for="reconsider-reason">Authority reason</Label>
            <Textarea
                id="reconsider-reason"
                bind:value={reconsiderReason}
                rows={3}
                class="min-h-24"
            />
            <ReasonSuggestionChips
                ariaLabel="Reconsideration reason suggestions"
                suggestions={ADMISSION_RECONSIDER_SUGGESTIONS}
                bind:value={reconsiderReason}
                maxlength={2000}
            />
        </div>
        <Dialog.Footer>
            <Button variant="outline" onclick={() => (reconsiderOpen = false)}
                >Cancel</Button
            >
            <Button
                disabled={!reconsiderReason.trim()}
                onclick={submitReconsider}>Reconsider</Button
            >
        </Dialog.Footer>
    </Dialog.Content>
</Dialog.Root>

<Dialog.Root bind:open={reissueOpen}>
    <Dialog.Content class="sm:max-w-md">
        <Dialog.Header>
            <Dialog.Title>Reissue access key</Dialog.Title>
            <Dialog.Description>
                Revokes every prior key for this applicant. The new key is shown
                once on this page.
            </Dialog.Description>
        </Dialog.Header>
        <div class="space-y-2 py-2">
            <Label for="reissue-reason">Reason (optional)</Label>
            <Textarea
                id="reissue-reason"
                bind:value={reissueReason}
                rows={2}
                class="min-h-20"
            />
            <ReasonSuggestionChips
                ariaLabel="Access key reissue reason suggestions"
                suggestions={ADMISSION_REISSUE_KEY_SUGGESTIONS}
                bind:value={reissueReason}
                maxlength={2000}
            />
        </div>
        <Dialog.Footer>
            <Button variant="outline" onclick={() => (reissueOpen = false)}
                >Cancel</Button
            >
            <Button onclick={submitReissue}>Reissue key</Button>
        </Dialog.Footer>
    </Dialog.Content>
</Dialog.Root>

<Dialog.Root bind:open={acceptAndVerifyOpen}>
    <Dialog.Content class="sm:max-w-md">
        <Dialog.Header>
            <Dialog.Title
                >Accept application and verify all documents</Dialog.Title
            >
            <Dialog.Description>
                This will accept the application for examination scheduling and
                mark all {unverifiedItems.length} document {unverifiedItems.length ===
                1
                    ? 'requirement'
                    : 'requirements'} as verified in the official checklist:
            </Dialog.Description>
        </Dialog.Header>
        <div class="space-y-3">
            <ul
                class="max-h-60 divide-y overflow-y-auto rounded-md border text-sm"
            >
                {#each unverifiedItems as item (item.id)}
                    <li class="flex items-center justify-between p-2.5">
                        <span class="text-foreground font-medium"
                            >{item.label}</span
                        >
                    </li>
                {/each}
            </ul>
        </div>
        <Dialog.Footer class="flex-col-reverse gap-2 sm:flex-row">
            <Button
                type="button"
                variant="outline"
                disabled={isAcceptingAndVerifying}
                onclick={() => (acceptAndVerifyOpen = false)}
            >
                Cancel
            </Button>
            <Button
                type="button"
                class="bg-emerald-600 text-white hover:bg-emerald-700"
                disabled={isAcceptingAndVerifying}
                onclick={submitAcceptAndVerifyAll}
            >
                <CheckCheck class="mr-1.5 size-4" />
                {isAcceptingAndVerifying
                    ? 'Accepting & Verifying…'
                    : 'Confirm Accept & Verify All Documents'}
            </Button>
        </Dialog.Footer>
    </Dialog.Content>
</Dialog.Root>
