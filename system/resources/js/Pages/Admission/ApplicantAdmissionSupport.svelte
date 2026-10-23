<script>
    import { newIdempotencyKey } from '@/lib/utils';
    import { success as toastSuccess, error as toastError } from '@/lib/toast.js';
    /* global route */
    import { governanceRoute, governanceRequest, governanceError } from '@/lib/governanceRequests';
    import CaseConversationStarter from '@/Components/Admission/CaseConversationStarter.svelte';
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
    import { Link, router, useForm, usePage } from '@inertiajs/svelte';
    import TabFmConsultationCard from '@/Components/admission-support/TabFmConsultationCard.svelte';
    import YuKeeConsultationCard from '@/Components/admission-support/YuKeeConsultationCard.svelte';
    import ApplicantEvidenceBar from '@/Components/admission-support/ApplicantEvidenceBar.svelte';
    import SupplementalEvidenceModal from '@/Components/admission-support/SupplementalEvidenceModal.svelte';
    import HollandRadarHexagon from '@/Components/admission-support/HollandRadarHexagon.svelte';
    import ConsultationMobileDock from '@/Components/consultation/ConsultationMobileDock.svelte';
    import ApplicantLookupDialog from '@/Components/consultation/ApplicantLookupDialog.svelte';
    import UnknownEngineCard from '@/Components/admission-support/UnknownEngineCard.svelte';
    import CaseConversation from '@/Components/Admission/CaseConversation.svelte';
    import PrintDocumentsSlot from '@/Components/DocumentTemplate/PrintDocumentsSlot.svelte';
    import ApplicantActionsMenu from '@/Components/Admission/ApplicantActionsMenu.svelte';
    import { CheckCircle2, ShieldCheck, Plus, UserCheck, Lock, Building2, Clock, Trash2, ChevronDown, ChevronRight } from 'lucide-svelte';
    let {
        page_state = 'denied',
        role = null,
        attempt = null,
        support = null,
        online_encounter_id = null,
        online_consultation = null,
        available_programs = [],
        ph_record = null,
        ph_program = null,
        threads = [],
        selected_conversation = null,
        published_advising_notes = [],
    } = $props();

    const page = usePage();
    const sessionRosterBreadcrumbs = $derived.by(() => {
        if (role === 'program_head') {
            return [];
        }

        const sessionId = new URL(page.url, 'http://localhost').searchParams.get('session_id');
        if (!sessionId || !/^\d+$/.test(sessionId)) {
            return [];
        }

        return [{ label: 'Release Day session', href: `/consultation/sessions/${sessionId}` }];
    });
    const breadcrumbs = $derived.by(() => {
        const applicantLabel = attempt?.reference_code ?? 'Decision Support';
        if (role === 'program_head') {
            return [
                { label: 'My Programs', href: '/staff/programs' },
                ...(ph_program?.code ? [{ label: ph_program.code, href: `/staff/programs/${encodeURIComponent(ph_program.code)}` }] : []),
                { label: applicantLabel },
            ];
        }

        return [
            { label: 'Release Day', href: '/consultation' },
            ...sessionRosterBreadcrumbs,
            { label: applicantLabel },
        ];
    });

    let activeTab = $state('profile');
    let decisionDialogOpen = $state(false);
    let lookupDialogOpen = $state(false);
    let followUpDialogOpen = $state(false);
    let isSupplementalEvidenceOpen = $state(false);
    let discussionAudience = $state('internal_ph_guidance');
    let tabLocation = $state('');
    const openingCycleCode = $derived(attempt?.cycle_code ?? new URL(page.url, 'http://localhost').searchParams.get('cycle_code') ?? '');
    const openingContext = $derived([
        page.props.auth?.user?.id ?? '',
        page.props.auth?.staff_inactivity_generation ?? '',
        page.props.practice_workspace?.id ?? '',
        role ?? '',
        attempt?.id ?? '',
        ph_program?.code ?? '',
        selected_conversation?.thread?.id ?? '',
        openingCycleCode,
    ].join(':'));
    const canOpenDiscussion = $derived(role === 'guidance'
        || (role === 'program_head' && ph_record?.tier === 'B'));
    const openingProgramCode = $derived.by(() => {
        const programs = ph_program ? [ph_program] : available_programs;
        const requested = ph_program?.code ?? selected_conversation?.thread?.program_code ?? '';
        return programs.some((program) => program.code === requested) ? requested : '';
    });
    let requirementsExpanded = $state(false);
    let showOriginalDemographicsGwa = $state(false);

    const internalStaffThreads = $derived(
        threads.filter((t) => t.thread_type === 'internal_ph_guidance' && t.can_read)
    );
    const withApplicantThreads = $derived(
        threads.filter((t) => t.thread_type === 'applicant_support' && t.can_read)
    );
    const visibleDiscussionThreads = $derived(
        discussionAudience === 'applicant_support' ? withApplicantThreads : internalStaffThreads
    );
    let noteUseStates = $state({});

    async function recordNoteUsed(note) {
        const attemptId = attempt?.id;
        const stateKey = `${attemptId}:${note.revision_id}`;
        const previous = noteUseStates[stateKey];
        if (!note.can_record_use || previous?.busy || previous?.receipt || note.usage_receipt) return;
        const key = previous?.key ?? newIdempotencyKey();
        noteUseStates[stateKey] = { key, busy: true, error: '', receipt: null };
        try {
            const response = await governanceRequest(governanceRoute('setup.admission-support.advising-note.use', { attempt: attemptId }), {
                source_revision_id: note.revision_id,
                idempotency_key: key,
            });
            if (!response?.usage?.id || Number(response.usage.revision_id) !== Number(note.revision_id)
                || Number(response.usage.admission_attempt_id) !== Number(attemptId)) {
                throw new Error('The note-use receipt could not be confirmed. Retry the same request.');
            }
            noteUseStates[stateKey] = { key, busy: false, error: '', receipt: response.usage };
            toastSuccess('Advising note recorded as used for this applicant.');
        } catch (error) {
            const err = governanceError(error);
            noteUseStates[stateKey] = { key, busy: false, error: err, receipt: null };
            toastError(err);
        }
    }
    function currentPageUrl() {
        const currentUrl = new URL(page.url, window.location.origin);
        return `${currentUrl.pathname}${currentUrl.search}${currentUrl.hash}`;
    }

    function caseUrl(tab = 'threads') {
        const target = new URL(currentPageUrl(), window.location.origin);
        target.searchParams.set('tab', tab);
        if (ph_program?.code) target.searchParams.set('program_code', ph_program.code);
        if (target.searchParams.get('program_code') && !target.searchParams.has('cycle_code') && attempt?.cycle_code) target.searchParams.set('cycle_code', attempt.cycle_code);
        return target;
    }

    function selectThread(thread) {
        if (!thread?.html_url) return;
        const target = new URL(thread.html_url, window.location.origin);
        target.searchParams.set('tab', 'threads');
        router.get(`${target.pathname}${target.search}`, {}, { preserveState: true, preserveScroll: true });
    }

    function clearSelectedThread() {
        const target = caseUrl();
        target.searchParams.delete('thread_id');
        target.searchParams.delete('thread_type');
        router.get(`${target.pathname}${target.search}`, {}, { preserveState: true, preserveScroll: true });
    }

    function messagesUrl() {
        const target = new URL('/staff/inquiries', window.location.origin);
        target.searchParams.set('group', 'case_threads');
        if (selected_conversation?.thread?.id) target.searchParams.set('thread_id', selected_conversation.thread.id);
        if (ph_program?.code) {
            target.searchParams.set('program_code', ph_program.code);
            const cycle = new URL(page.url, window.location.origin).searchParams.get('cycle_code');
            if (cycle) target.searchParams.set('cycle_code', cycle);
        }
        return `${target.pathname}${target.search}`;
    }


    $effect(() => {
        const location = `${page.url}:${role}:${selected_conversation?.thread?.id ?? ''}`;
        if (location !== tabLocation) {
            tabLocation = location;
            const valid = role === 'guidance' ? ['profile', 'analysis', 'logs', 'threads'] : ['record', 'threads'];
            const requested = new URL(page.url, 'http://localhost').searchParams.get('tab');
            activeTab = requested && valid.includes(requested)
                ? requested
                : selected_conversation ? 'threads' : valid[0];
            if (selected_conversation?.thread?.thread_type) discussionAudience = selected_conversation.thread.thread_type;
        }
    });


    // Helper to format program title
    function programTitle(code) {
        if (!code) return '—';
        const found = (available_programs ?? []).find((p) => p.code === code);
        return found ? `[${found.code}] ${found.name}` : code;
    }

    const decisionForm = useForm({
        decision_type: 'approve',
        target_program_code: '',
        referral_reason: '',
        notes: '',
        idempotency_key: newIdempotencyKey(),
    });
    let decisionClientError = $state('');
    let decisionAdvisedProgram = $state('');
    let decisionAlternates = $state([]);
    let decisionPoolEditAmbiguous = $state(false);
    let decisionPoolOptionBases = $state({});
    const p1Code = $derived(support?.preferences?.[0] ?? '');
    const canRecordGuidanceDecision = $derived(
        page_state === 'ready' && role === 'guidance' && support?.readiness?.result_ready === true,
    );

    function handleDecisionDialogChange(open) {
        if (open && !decisionDialogOpen) {
            const pool = support?.program_pool;
            const existingDefault = pool?.default ?? '';
            const existingOptions = Array.isArray(pool?.options) ? pool.options : [];
            decisionPoolEditAmbiguous = !existingDefault && existingOptions.length > 0;
            decisionPoolOptionBases = Object.fromEntries(
                existingOptions
                    .filter((option) => option?.program_code)
                    .map((option) => [option.program_code, option.basis]),
            );
            decisionAdvisedProgram = existingDefault || (existingOptions.length > 0 ? '' : p1Code);
            decisionAlternates = existingOptions
                .map((option) => option?.program_code ?? '')
                .filter((code) => code !== existingDefault);
            decisionForm.decision_type = existingDefault
                ? (existingDefault === p1Code ? 'approve' : 'modify')
                : (existingOptions.length > 0 ? 'modify' : 'approve');
            decisionForm.target_program_code = '';
            decisionForm.referral_reason = '';
            decisionForm.notes = '';
            decisionForm.idempotency_key = newIdempotencyKey();
            decisionClientError = '';
            decisionForm.clearErrors();
        }

        decisionDialogOpen = open;
    }

    function decisionPoolOptionError(index, field) {
        return decisionForm.errors[`pool_options.${index}.${field}`] ?? '';
    }

    function setDecisionType(type) {
        decisionForm.decision_type = ['approve', 'modify'].includes(type)
            ? (decisionAdvisedProgram && decisionAdvisedProgram === p1Code ? 'approve' : 'modify')
            : type;
        decisionClientError = '';
    }

    function selectDecisionProgram(programCode) {
        decisionAdvisedProgram = programCode;
        decisionAlternates = decisionAlternates.filter((alternate) => alternate !== programCode);
        if (decisionForm.decision_type !== 'override') {
            decisionForm.decision_type = programCode === p1Code ? 'approve' : 'modify';
        }
        decisionClientError = '';
    }

    function handleAddToAdvisedPool(program) {
        if (!canRecordGuidanceDecision) return;

        const code = typeof program === 'string' ? program : program?.program_code ?? '';
        if (typeof code !== 'string' || code === '') return;
        if (!(available_programs ?? []).some((candidate) => candidate.code === code)) return;

        handleDecisionDialogChange(true);
        selectDecisionProgram(code);
    }

    function toggleDecisionOverride(enabled) {
        decisionForm.decision_type = enabled
            ? 'override'
            : (decisionAdvisedProgram === p1Code ? 'approve' : 'modify');
        decisionClientError = '';
    }

    function programQuotaLabel(program) {
        if (!program.configured) {
            return 'No quota configured';
        }
        if (typeof program.remaining === 'number' && program.remaining <= 0) {
            return 'Capacity full';
        }
        if (typeof program.remaining === 'number') {
            const pct = program.quota_percentage ?? Math.round(((program.committed ?? 0) / (program.capacity || 1)) * 100);
            return `${program.remaining} seats left (${pct}% full)`;
        }
        return 'Quota unconfigured';
    }

    function programQuotaIsProjected(program) {
        return Boolean(program.configured);
    }

    const consultationHistory = $derived.by(() => {
        const history = Array.isArray(support?.consultation_history)
            ? [...support.consultation_history]
            : (() => {
                const evidence = support?.consultation_evidence;
                return evidence?.recorded_at || evidence?.decision_type || evidence?.notes
                    ? [evidence]
                    : [];
            })();

        return history.sort((first, second) => {
            const firstDate = Date.parse(first.recorded_at ?? first.created_at ?? '');
            const secondDate = Date.parse(second.recorded_at ?? second.created_at ?? '');
            return (Number.isNaN(firstDate) ? 0 : firstDate) - (Number.isNaN(secondDate) ? 0 : secondDate);
        });
    });

    function addDecisionAlternate() {
        const usedCodes = new Set([decisionAdvisedProgram, ...decisionAlternates]);
        if ((available_programs ?? []).some((program) => !usedCodes.has(program.code))) {
            decisionAlternates = [...decisionAlternates, ''];
            decisionClientError = '';
        }
    }

    function removeDecisionAlternate(index) {
        decisionAlternates = decisionAlternates.filter((_, alternateIndex) => alternateIndex !== index);
        decisionClientError = '';
    }


    const followUpForm = useForm({
        program_code: '',
        reason: '',
        idempotency_key: newIdempotencyKey(),
    });


    function submitFollowUp() {
        if (!attempt?.id) return;
        if (!followUpForm.program_code || !followUpForm.reason.trim()) {
            return;
        }
        followUpForm.idempotency_key = newIdempotencyKey();
        followUpForm.post(
            typeof route === 'function'
                ? route('program-follow-up.open', { attempt: attempt.id })
                : `/consultation/${attempt.id}/follow-up`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    followUpDialogOpen = false;
                    toastSuccess('Applicant referred to Program Head / Dean.');
                },
                onError: (errors) => {
                    toastError(Object.values(errors).flat().join(' ') || 'Follow-up referral could not be recorded.');
                },
            },
        );
    }

    function submitDecision() {
        if (!attempt?.id || role !== 'guidance') return;

        const decisionType = decisionForm.decision_type;
        const advisesProgram = ['approve', 'modify', 'override'].includes(decisionType);
        const primaryProgram = decisionAdvisedProgram;
        const notesRequired = ['override', 'defer'].includes(decisionType);
        const availableCodes = new Set((available_programs ?? []).map((program) => program.code));
        const invalidAlternate = decisionAlternates.some((code) => !code || !availableCodes.has(code));
        const duplicateAlternate = new Set(decisionAlternates).size !== decisionAlternates.length;

        if (advisesProgram && (!primaryProgram || !availableCodes.has(primaryProgram))) {
            decisionClientError = decisionPoolEditAmbiguous
                ? 'Select a primary recommendation to revise the saved program options.'
                : 'Select a valid recommended degree program before submitting.';
            return;
        }
        if (advisesProgram && (invalidAlternate || duplicateAlternate || decisionAlternates.includes(primaryProgram))) {
            decisionClientError = 'Choose a valid, distinct program for every alternate option. An alternate cannot match the primary recommendation.';
            return;
        }
        if (decisionType === 'refer' && (!decisionForm.target_program_code || !decisionForm.referral_reason.trim())) {
            decisionClientError = 'Select a target program and enter a referral reason before submitting.';
            return;
        }
        if (notesRequired && !decisionForm.notes.trim()) {
            decisionClientError = decisionType === 'override'
                ? 'Enter the institutional override justification before submitting.'
                : 'Enter the pending items or reason for deferral before submitting.';
            return;
        }

        decisionClientError = '';
        const primaryIsPreference = (support?.preferences ?? []).includes(primaryProgram);
        const primaryBasis = decisionPoolOptionBases[primaryProgram]
            ?? (primaryIsPreference ? 'preference' : 'guidance_option');
        const payload = {
            decision_type: decisionType,
            idempotency_key: decisionForm.idempotency_key,
            ...(online_encounter_id !== null ? { encounter_id: online_encounter_id } : {}),
            ...(advisesProgram
                ? {
                    default_program_code: primaryProgram,
                    pool_options: [
                        { program_code: primaryProgram, display_order: 1, basis: primaryBasis },
                        ...decisionAlternates
                            .map((programCode, index) => ({
                                program_code: programCode,
                                display_order: index + 2,
                                basis: decisionPoolOptionBases[programCode] ?? 'guidance_option',
                            })),
                    ],
                    pool_reason: (decisionForm.notes || 'Advising decision').slice(0, 500),
                }
                : {}),
            ...(decisionType === 'refer'
                ? {
                    target_program_code: decisionForm.target_program_code,
                    referral_reason: decisionForm.referral_reason,
                }
                : {}),
            ...(decisionType === 'defer' || decisionType === 'override'
                ? { notes: decisionForm.notes }
                : decisionType === 'approve' || decisionType === 'modify'
                    ? { notes: decisionForm.notes }
                    : {}),
        };
        decisionForm.transform(() => payload).post(
            typeof route === 'function'
                ? route('consultation.decision', { attempt: attempt.id })
                : `/consultation/${attempt.id}/decision`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    decisionDialogOpen = false;
                    toastSuccess('Advising decision recorded.');
                },
                onError: (errors) => {
                    toastError(Object.values(errors).flat().join(' ') || 'Advising decision could not be recorded.');
                },
            },
        );
    }



    const readinessLabels = {
        accepted: 'Application accepted',
        documents_verified: 'Document & registrar requirements',
        exam_completed: 'Assessment completed',
        result_ready: 'Result prepared',
        advised: 'Result released',
    };

    function formatScore(value) {
        if (value === null || value === undefined || value === '') return '—';
        const numeric = Number(value);
        return Number.isNaN(numeric) ? String(value) : numeric.toLocaleString(undefined, { maximumFractionDigits: 1 });
    }
    function formatExactScore(value) {
        if (value === null || value === undefined || value === '') return '—';
        const exact = String(value);
        if (!/^-?\d+(?:\.\d+)?$/.test(exact)) return exact;
        return exact.includes('.') ? exact.replace(/0+$/, '').replace(/\.$/, '') : exact;
    }

    function formatDate(iso) {
        if (!iso) return null;
        return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    }

    let onlineStartKey = $state(null);
    let onlineStartAttemptId = $state(null);
    let onlineStarting = $state(false);
    let onlineStartError = $state('');

    $effect(() => {
        const attemptId = attempt?.id ?? null;
        if (attemptId !== onlineStartAttemptId) {
            onlineStartAttemptId = attemptId;
            onlineStartKey = null;
            onlineStartError = '';
            onlineStarting = false;
        }
    });

    async function startOnlineConsultation() {
        const attemptId = attempt?.id;
        if (!attemptId || onlineStarting || !online_consultation?.can_start) return;

        onlineStartKey ??= newIdempotencyKey();
        onlineStarting = true;
        onlineStartError = '';

        try {
            const response = await fetch(
                typeof route === 'function'
                    ? route('consultation.applicants.online.start', { attempt: attemptId })
                    : `/consultation/applicants/${encodeURIComponent(attemptId)}/online/start`,
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ idempotency_key: onlineStartKey }),
                },
            );
            const result = await response.json();
            if (attempt?.id !== attemptId) return;
            if (!response.ok) throw new Error(result.message ?? 'The online consultation could not be started. Retry this action.');
            if (!result.url) throw new Error('The encounter was created but no join URL was returned.');
            onlineStartKey = null;
            window.location.assign(result.url);
        } catch (error) {
            if (attempt?.id === attemptId) {
                onlineStartError = error instanceof Error ? error.message : 'The online consultation could not be started. Retry this action.';
            }
        } finally {
            if (attempt?.id === attemptId) onlineStarting = false;
        }
    }
</script>

<svelte:head><title>Decision Support · {attempt?.name ?? 'Examinee'}</title></svelte:head>

{#snippet journeySection()}
    <Card.Root class="border-primary/20 bg-primary/[0.02]">
        <Card.Header class="pb-3">
            <Card.Title>Applicant journey</Card.Title>
            <Card.Description>One authoritative view of the admission path.</Card.Description>
        </Card.Header>
        <Card.Content>
            <ol class="space-y-3">
                {#each Object.entries(readinessLabels) as [key, label]}
                    {@const ready = key === 'documents_verified'
                        ? (support?.readiness?.documents_verified ?? support?.requirements_checklist?.all_mandatory_verified ?? false)
                        : support?.readiness?.[key]}
                    <li>
                        <div class="flex items-center gap-2 text-sm">
                            {#if ready}
                                <CheckCircle2 class="size-4 shrink-0 text-emerald-600" />
                                <span class="min-w-0 flex-1">{label}</span>
                                <Badge variant="outline" class="shrink-0 border-emerald-300 bg-emerald-50 text-emerald-700">Complete</Badge>
                            {:else}
                                <span class="size-4 shrink-0 rounded-full border border-muted-foreground/40"></span>
                                <span class="min-w-0 flex-1 text-muted-foreground">{label}</span>
                                <Badge variant="secondary" class="shrink-0">Pending</Badge>
                            {/if}
                            {#if key === 'documents_verified' && (support?.requirements_checklist?.items?.length ?? 0) > 0}
                                {@const checklist = support.requirements_checklist}
                                <button type="button" class="inline-flex min-h-8 items-center gap-1 rounded px-1.5 text-xs text-muted-foreground hover:bg-muted hover:text-foreground" aria-expanded={requirementsExpanded} aria-label="Toggle document and registrar requirements" onclick={() => requirementsExpanded = !requirementsExpanded}>
                                    {checklist.verified_count ?? 0}/{checklist.total_count ?? checklist.items.length}
                                    {#if requirementsExpanded}<ChevronDown class="size-3.5" />{:else}<ChevronRight class="size-3.5" />{/if}
                                </button>
                            {/if}
                        </div>
                        {#if key === 'documents_verified' && (support?.requirements_checklist?.items?.length ?? 0) > 0 && requirementsExpanded}
                            <ul class="mt-2 ml-6 space-y-1.5 border-l border-border/70 pl-3 text-xs">
                                {#each support.requirements_checklist.items as item (item.id)}
                                    {@const status = String(item.status ?? 'not_checked')}
                                    {@const verified = status === 'verified'}
                                    {@const needsCorrection = status === 'needs_correction'}
                                    <li class="flex items-center justify-between gap-2">
                                        <span class="min-w-0">{item.label}</span>
                                        <Badge variant="outline" class={`shrink-0 ${verified ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : needsCorrection ? 'border-amber-400 bg-amber-50 text-amber-800' : 'text-muted-foreground'}`}>{verified ? 'Verified' : needsCorrection ? 'Needs correction' : 'Unchecked'}</Badge>
                                    </li>
                                {/each}
                            </ul>
                        {/if}
                    </li>
                {/each}
            </ol>
        </Card.Content>
    </Card.Root>
{/snippet}

{#snippet assessmentSection()}

    {@const assessmentDetail = support?.assessment_detail}
    {@const assessmentResults = assessmentDetail === null || assessmentDetail === undefined ? (support?.score_profile?.series ?? []) : (assessmentDetail.subtests ?? [])}
    {@const normLabels = [...new Set(assessmentResults.map((result) => result.norm_label).filter(Boolean))]}
    {@const commonNormLabel = normLabels.length === 1 ? normLabels[0] : null}
    {@const assessmentGroups = [
        { key: 'direct', label: 'Direct measures', results: assessmentResults.filter((result) => result.metric_kind !== 'derived') },
        { key: 'derived', label: 'Derived measures', results: assessmentResults.filter((result) => result.metric_kind === 'derived') },
    ]}
    <section class="space-y-6">
        <div>
            <h2 class="text-lg font-semibold tracking-tight">Profile &amp; Assessment</h2>
            <p class="text-sm text-muted-foreground">Candidate details, initial course preferences, and released aptitude results.</p>
        </div>

        <Card.Root class="border-border/80 shadow-xs">
            <Card.Header class="pb-3 border-b border-border/40">
                <Card.Title class="text-base font-bold text-foreground">Candidate Demographics</Card.Title>
                <Card.Description class="text-xs">Applicant identity and submitted academic preferences.</Card.Description>
            </Card.Header>
            <Card.Content class="grid gap-x-8 gap-y-5 pt-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="space-y-1">
                    <p class="text-[11px] font-mono font-bold uppercase tracking-wider text-muted-foreground">Full Name</p>
                    <p class="text-sm font-semibold text-foreground">{attempt?.name ?? '—'}</p>
                </div>
                <div class="space-y-1">
                    <p class="text-[11px] font-mono font-bold uppercase tracking-wider text-muted-foreground">Reference Code</p>
                    <p class="font-mono text-sm font-bold text-foreground">{attempt?.reference_code ?? '—'}</p>
                </div>
                <div class="space-y-1">
                    <p class="text-[11px] font-mono font-bold uppercase tracking-wider text-muted-foreground">Sex</p>
                    <p class="text-sm font-medium capitalize text-foreground">{attempt?.sex ?? attempt?.identity?.sex ?? '—'}</p>
                </div>
                <div class="space-y-1">
                    <p class="text-[11px] font-mono font-bold uppercase tracking-wider text-muted-foreground">Track / Strand</p>
                    <span class="inline-block rounded-md border border-border/80 bg-muted/40 px-2 py-0.5 text-xs font-semibold text-foreground">
                        {support?.applicant_evidence?.strand ?? attempt?.strand ?? 'General'}
                    </span>
                </div>
                <div class="space-y-1">
                    <p class="text-[11px] font-mono font-bold uppercase tracking-wider text-muted-foreground">
                        {support?.applicant_evidence?.applicant_type === 'transferee' ? 'Prior College / TOR GWA' : 'High School GWA'}
                    </p>
                    <div class="flex flex-wrap items-baseline gap-1">
                        {#if showOriginalDemographicsGwa && support?.applicant_evidence?.gwa_is_converted}
                            <span class="font-mono text-xl font-extrabold tabular-nums tracking-tight text-foreground">
                                {formatScore(support?.applicant_evidence?.gwa_raw)}
                            </span>
                            <span class="text-xs text-muted-foreground">
                                ({support?.applicant_evidence?.gwa_scale_label ?? 'Original'})
                            </span>
                            <button
                                type="button"
                                class="ml-1 inline-flex items-center gap-0.5 rounded border border-border/70 bg-muted/40 px-1 py-0.5 font-mono text-[10px] font-medium text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                                onclick={() => { showOriginalDemographicsGwa = false; }}
                                title="Switch to 0–100 scale"
                                aria-label={`Switch to 0–100 scale: ${formatScore(support?.applicant_evidence?.gwa ?? attempt?.gwa)}`}
                            >
                                <span>{formatScore(support?.applicant_evidence?.gwa ?? attempt?.gwa)}</span>
                                <span aria-hidden="true">⇄</span>
                            </button>
                        {:else}
                            <span class="font-mono text-xl font-extrabold tabular-nums tracking-tight text-foreground">
                                {formatScore(support?.applicant_evidence?.gwa ?? attempt?.gwa)}
                            </span>
                            <span class="text-xs text-muted-foreground">/ 100</span>
                            {#if support?.applicant_evidence?.gwa_is_converted}
                                <button
                                    type="button"
                                    class="ml-1 inline-flex items-center gap-0.5 rounded border border-border/70 bg-muted/40 px-1 py-0.5 font-mono text-[10px] font-medium text-muted-foreground hover:text-foreground transition-colors cursor-pointer"
                                    onclick={() => { showOriginalDemographicsGwa = true; }}
                                    title="Switch to original reported scale"
                                    aria-label={`Switch to original scale: ${formatScore(support?.applicant_evidence?.gwa_raw)}`}
                                >
                                    <span>{formatScore(support?.applicant_evidence?.gwa_raw)}</span>
                                    <span aria-hidden="true">⇄</span>
                                </button>
                            {/if}
                        {/if}
                    </div>
                </div>
                <div class="space-y-1.5 sm:col-span-2 lg:col-span-1">
                    <p class="text-[11px] font-mono font-bold uppercase tracking-wider text-muted-foreground">Course Preferences</p>
                    <div class="flex flex-col gap-1.5">
                        {#each [0, 1, 2] as index}
                            {@const preference = support?.preferences?.[index]}
                            <div class="flex items-center gap-1.5 text-xs">
                                <span class="w-6 shrink-0 font-mono font-bold {index === 0 ? 'text-primary' : 'text-muted-foreground'}">P{index + 1}</span>
                                {#if preference}
                                    <span class="truncate font-medium {index === 0 ? 'rounded-md border border-primary/30 bg-primary/10 px-2 py-0.5 font-semibold text-primary dark:text-primary-foreground/90' : 'text-muted-foreground'}">
                                        {programTitle(preference)}
                                    </span>
                                {:else}
                                    <span class="text-muted-foreground/60 italic">—</span>
                                {/if}
                            </div>
                        {/each}
                    </div>
                </div>
            </Card.Content>
        </Card.Root>

        {#if role === 'guidance'}
            <ApplicantEvidenceBar
                evidence={support?.applicant_evidence}
                inputSchema={support?.input_schema}
                onRecordSupplementalEvidence={() => { isSupplementalEvidenceOpen = true; }}
            />
        {/if}

        <section class="space-y-3">
            <Card.Root class="border-border/80 shadow-xs">
                <Card.Header class="pb-3 border-b border-border/40">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="space-y-1">
                            <Card.Title class="text-base font-bold text-foreground">Assessment Results &amp; Scoring Breakdown</Card.Title>
                            <Card.Description class="text-xs">
                                {#if assessmentDetail?.instrument_name}
                                    {assessmentDetail.instrument_name}{#if assessmentDetail.form_code} · Form {assessmentDetail.form_code}{/if}
                                {:else if support?.score_profile?.source}
                                    Source: {support.score_profile.source}
                                {:else}
                                    Raw scores and norm-referenced results with their derivation and capture source.
                                {/if}
                            </Card.Description>
                            {#if commonNormLabel}<p class="text-xs text-muted-foreground">Internal norm package: {commonNormLabel}</p>{/if}
                        </div>
                        {#if support?.score_profile?.released_at}
                            <Badge variant="outline" class="font-mono text-xs">
                                Released {formatDate(support.score_profile.released_at)}
                            </Badge>
                        {/if}
                    </div>
                </Card.Header>
                <Card.Content class="p-0">
                    <div class="divide-y divide-border/60 sm:hidden">
                        {#if assessmentResults.length === 0}
                            <p class="px-4 py-8 text-center text-xs text-muted-foreground">No subtest assessment results are available.</p>
                        {:else}
                            {#each assessmentGroups as group (group.key)}
                                {#if group.results.length > 0}
                                    <h3 class="px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">{group.label}</h3>
                                    {#each group.results as result (result.code ?? result.area_code ?? result.name ?? result.area_name)}
                                        <article class="space-y-3 p-4">
                                            <div class="space-y-1">
                                                <p class="text-[11px] font-mono font-bold uppercase tracking-wider text-muted-foreground">Metric</p>
                                                <p class="break-words text-sm font-semibold text-foreground">
                                                    {result.metric_name ?? result.name ?? result.area_name ?? result.code ?? result.area_code ?? '—'}
                                                </p>
                                                {#if result.construct_name && result.construct_name !== result.metric_name}
                                                    <p class="text-xs text-muted-foreground">Construct: {result.construct_name}</p>
                                                {/if}
                                                {#if result.subtest_name}
                                                    <p class="text-xs text-muted-foreground">Subtest: {result.subtest_name}</p>
                                                {/if}
                                            </div>
                                            <dl class="grid grid-cols-2 gap-x-4 gap-y-3">
                                                <div class="min-w-0 space-y-1">
                                                    <dt class="text-[11px] font-mono font-bold uppercase tracking-wider text-muted-foreground">Percentile rank (PR)</dt>
                                                    <dd class="font-mono text-sm tabular-nums text-foreground">
                                                        {#if result.percentile !== null && result.percentile !== undefined}{formatExactScore(result.percentile)} PR{:else}—{/if}
                                                    </dd>
                                                    {#if result.norm_label && !commonNormLabel}<dd class="text-xs text-muted-foreground">Norm: {result.norm_label}</dd>{/if}
                                                </div>
                                                <div class="min-w-0 space-y-1">
                                                    <dt class="text-[11px] font-mono font-bold uppercase tracking-wider text-muted-foreground">Raw score</dt>
                                                    <dd class="font-mono text-sm tabular-nums text-foreground">
                                                        {#if result.raw_score !== null && result.raw_score !== undefined}
                                                            {#if result.metric_kind === 'derived' && result.raw_min_score !== null}
                                                                {formatExactScore(result.raw_score)}
                                                                <span class="font-sans text-xs text-muted-foreground">
                                                                    (allowed {formatExactScore(result.raw_min_score)}–{formatExactScore(result.raw_max_score)})
                                                                </span>
                                                            {:else}
                                                                {formatExactScore(result.raw_score)}
                                                                {#if result.raw_max_score !== null && result.raw_max_score !== undefined}
                                                                    / {formatExactScore(result.raw_max_score)}
                                                                {/if}
                                                            {/if}
                                                            {#if result.unit && result.unit !== 'unknown'}
                                                                <span class="ml-1 font-sans text-xs text-muted-foreground">{result.unit}</span>
                                                            {/if}
                                                        {:else}
                                                            {#if result.source === 'manual_converted'}
                                                                <span class="font-sans text-xs text-muted-foreground">External final provided; no raw score was included.</span>
                                                            {:else}
                                                                —
                                                            {/if}
                                                        {/if}
                                                    </dd>
                                                </div>
                                                <div class="col-span-2 min-w-0 space-y-1">
                                                    <dt class="text-[11px] font-mono font-bold uppercase tracking-wider text-muted-foreground">Capture source</dt>
                                                    <dd class="break-words text-xs text-muted-foreground">{result.capture_source ?? result.source ?? '—'}{#if result.external_source_reference}<span class="block mt-1">Source reference: {result.external_source_reference}</span>{/if}</dd>
                                                </div>
                                                <div class="col-span-2 min-w-0 space-y-1">
                                                    <dt class="text-[11px] font-mono font-bold uppercase tracking-wider text-muted-foreground">Scoring derivation</dt>
                                                    <dd class="break-words text-xs text-muted-foreground">{result.derivation ?? result.converted_label ?? (result.metric_kind === 'derived' ? 'Derivation unavailable from frozen evidence.' : '—')}</dd>
                                                </div>
                                            </dl>
                                            {#if result.metric_kind === 'derived'}
                                                {#if result.formula_expression && result.formula_substitution && result.components?.length}
                                                    <details class="rounded-md bg-muted/40 p-3 text-xs">
                                                        <summary class="cursor-pointer font-semibold text-foreground">Formula details</summary>
                                                        <div class="mt-2 space-y-2">
                                                            <p class="break-all font-mono text-muted-foreground">{result.formula_expression}</p>
                                                            <p class="break-all font-mono text-foreground">= {result.formula_substitution}</p>
                                                            <ul class="space-y-1">
                                                                {#each result.components as component (component.code)}
                                                                    <li class="break-words text-muted-foreground">{component.name} ({component.code}): <span class="font-mono tabular-nums text-foreground">{component.raw_value}</span> · {component.source}</li>
                                                                {/each}
                                                            </ul>
                                                        </div>
                                                    </details>
                                                {:else}
                                                    <p class="text-xs text-muted-foreground">Frozen derivation details are unavailable.</p>
                                                {/if}
                                            {/if}
                                        </article>
                                    {/each}
                                {/if}
                            {/each}
                        {/if}
                    </div>
                    <div class="hidden sm:block">
                        <Table.Root class="w-full">
                            <Table.Header>
                                <Table.Row class="hover:bg-transparent">
                                    <Table.Head class="whitespace-nowrap">Metric</Table.Head>
                                    <Table.Head class="whitespace-nowrap">Raw score</Table.Head>
                                    <Table.Head class="whitespace-nowrap">Percentile rank (PR)</Table.Head>
                                    <Table.Head>Scoring derivation</Table.Head>
                                    <Table.Head class="whitespace-nowrap">Capture source</Table.Head>
                                </Table.Row>
                            </Table.Header>
                            <Table.Body>
                                {#if assessmentResults.length === 0}
                                    <Table.Row>
                                        <Table.Cell colspan={5} class="py-8 text-center text-xs text-muted-foreground">No subtest assessment results are available.</Table.Cell>
                                    </Table.Row>
                                {:else}
                                    {#each assessmentGroups as group (group.key)}
                                        {#if group.results.length > 0}
                                            <Table.Row class="bg-muted/30 hover:bg-muted/30">
                                                <Table.Cell colspan={5} class="py-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">{group.label}</Table.Cell>
                                            </Table.Row>
                                            {#each group.results as result (result.code ?? result.area_code ?? result.name ?? result.area_name)}
                                                <Table.Row>
                                                    <Table.Cell class="text-xs text-foreground">
                                                        <p class="font-semibold">{result.metric_name ?? result.name ?? result.area_name ?? result.code ?? result.area_code ?? '—'}</p>
                                                        {#if result.construct_name && result.construct_name !== result.metric_name}<p class="mt-1 text-muted-foreground">Construct: {result.construct_name}</p>{/if}
                                                        {#if result.subtest_name}<p class="text-muted-foreground">Subtest: {result.subtest_name}</p>{/if}
                                                    </Table.Cell>
                                                    <Table.Cell class="whitespace-nowrap font-mono text-sm tabular-nums text-foreground">
                                                        {#if result.raw_score !== null && result.raw_score !== undefined}
                                                            {#if result.metric_kind === 'derived' && result.raw_min_score !== null}
                                                                {formatExactScore(result.raw_score)}
                                                                <span class="font-sans text-xs text-muted-foreground">
                                                                    (allowed {formatExactScore(result.raw_min_score)}–{formatExactScore(result.raw_max_score)})
                                                                </span>
                                                            {:else}
                                                                {formatExactScore(result.raw_score)}
                                                                {#if result.raw_max_score !== null && result.raw_max_score !== undefined}
                                                                    / {formatExactScore(result.raw_max_score)}
                                                                {/if}
                                                            {/if}
                                                            {#if result.unit && result.unit !== 'unknown'}
                                                                <span class="ml-1 font-sans text-xs text-muted-foreground">{result.unit}</span>
                                                            {/if}
                                                        {:else}
                                                            {#if result.source === 'manual_converted'}
                                                                <span class="font-sans text-xs text-muted-foreground">External final provided; no raw score was included.</span>
                                                            {:else}
                                                                —
                                                            {/if}
                                                        {/if}
                                                    </Table.Cell>
                                                    <Table.Cell class="whitespace-nowrap font-mono text-sm tabular-nums text-foreground">
                                                        {#if result.percentile !== null && result.percentile !== undefined}{formatExactScore(result.percentile)} PR{:else}—{/if}
                                                        {#if result.norm_label && !commonNormLabel}<p class="mt-1 font-sans text-xs text-muted-foreground">Norm: {result.norm_label}</p>{/if}
                                                    </Table.Cell>
                                                    <Table.Cell class="max-w-80 whitespace-normal text-xs text-muted-foreground">
                                                        <p>{result.derivation ?? result.converted_label ?? (result.metric_kind === 'derived' ? 'Derivation unavailable from frozen evidence.' : '—')}</p>
                                                        {#if result.metric_kind === 'derived'}
                                                            {#if result.formula_expression && result.formula_substitution && result.components?.length}
                                                                <details class="mt-2">
                                                                    <summary class="cursor-pointer font-semibold text-foreground">Formula details</summary>
                                                                    <div class="mt-2 space-y-1">
                                                                        <p class="break-all font-mono">{result.formula_expression}</p>
                                                                        <p class="break-all font-mono text-foreground">= {result.formula_substitution}</p>
                                                                        <ul>
                                                                            {#each result.components as component (component.code)}
                                                                                <li class="break-words">{component.name} ({component.code}): <span class="font-mono tabular-nums text-foreground">{component.raw_value}</span> · {component.source}</li>
                                                                            {/each}
                                                                        </ul>
                                                                    </div>
                                                                </details>
                                                            {:else}
                                                                <p class="mt-1">Frozen derivation details are unavailable.</p>
                                                            {/if}
                                                        {/if}
                                                    </Table.Cell>
                                                    <Table.Cell class="whitespace-nowrap text-xs text-muted-foreground">{result.capture_source ?? result.source ?? '—'}{#if result.external_source_reference}<span class="block mt-1">Source reference: {result.external_source_reference}</span>{/if}</Table.Cell>
                                                </Table.Row>
                                            {/each}
                                        {/if}
                                    {/each}
                                {/if}
                            </Table.Body>
                        </Table.Root>
                    </div>
                </Card.Content>
            </Card.Root>

            <Card.Root class="border-border/80 shadow-xs">
                <Card.Header class="pb-3 border-b border-border/40">
                    <Card.Title class="text-base font-bold text-foreground">Structured Consultation Evidence</Card.Title>
                </Card.Header>
                <Card.Content class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-muted-foreground">Decision Status:</span>
                        {#if support?.consultation_evidence?.decision_type}
                            <Badge variant="default" class="uppercase">{support.consultation_evidence.decision_type}</Badge>
                        {:else}
                            <Badge variant="outline">Not recorded</Badge>
                        {/if}
                    </div>
                    {#if support?.consultation_evidence?.recorded_by}
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">Recorded By:</span>
                            <span class="font-medium">{support.consultation_evidence.recorded_by}</span>
                        </div>
                    {/if}
                    {#if support?.consultation_evidence?.recorded_at}
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-muted-foreground">Recorded On:</span>
                            <span>{formatDate(support.consultation_evidence.recorded_at)}</span>
                        </div>
                    {/if}
                    {#if support?.consultation_evidence?.notes}
                        <div class="rounded-md bg-muted/50 p-3 text-xs text-muted-foreground">
                            <p class="font-medium text-foreground">Notes</p>
                            <p class="mt-1 whitespace-pre-wrap">{support.consultation_evidence.notes}</p>
                        </div>
                    {/if}
                </Card.Content>
            </Card.Root>

            {@render publishedNotesSection()}
        </section>
    </section>
{/snippet}

{#snippet publishedNotesSection()}
    {#if published_advising_notes.length}
        <section class="space-y-3" aria-label="Published program advising notes">
            <h3 class="text-base font-semibold tracking-tight">Program advising notes</h3>
            {#each published_advising_notes as note (note.revision_id)}
                {@const usageState = noteUseStates[`${attempt?.id}:${note.revision_id}`]}
                {@const receipt = usageState?.receipt ?? note.usage_receipt}
                <div class="space-y-2 rounded-md border border-border px-4 py-3">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h4 class="text-sm font-semibold">{note.program_code} · {note.program_name}</h4>
                        <a href={note.details_url} class="text-sm text-primary underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Note details</a>
                    </div>
                    <p class="whitespace-pre-wrap break-words text-sm leading-relaxed">{note.content}</p>
                    <p class="text-xs text-muted-foreground">
                        Revision {note.version} · Author {note.author_name} · Published by {note.publisher_name}
                        <time datetime={note.published_at}> · {formatDate(note.published_at)}</time>
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        {#if receipt}
                            <p role="status" class="text-xs text-muted-foreground">Note use recorded · receipt #{receipt.id} · revision {note.version} · {formatDate(receipt.used_at)}</p>
                        {:else if note.can_record_use}
                            <Button size="sm" variant="outline" disabled={usageState?.busy} onclick={() => recordNoteUsed(note)}>
                                {usageState?.busy ? 'Recording…' : 'Record note used'}
                            </Button>
                        {/if}
                        <span class="text-xs text-muted-foreground">Advisory only; recording use does not change the decision.</span>
                    </div>
                    {#if usageState?.error}
                        <p role="alert" class="text-sm text-destructive">{usageState.error}</p>
                    {/if}
                </div>
            {/each}
        </section>
    {/if}
{/snippet}


{#snippet analysisSection()}
    <section class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold tracking-tight">Analysis &amp; Evidence</h2>
                <p class="text-sm text-muted-foreground">Independent decision-support and applicant profile visualizers.</p>
            </div>
        </div>
        <div class="grid gap-4 {(support?.models ?? []).length > 1 ? 'xl:grid-cols-2' : ''}">
            {#each support?.models ?? [] as m (m.slot)}
                {#if m.slot === 'tabfm_pairwise'}
                    {#if canRecordGuidanceDecision}
                        <TabFmConsultationCard
                            model={m}
                            available_programs={available_programs}
                            showEvidenceBar={false}
                            onAddToAdvisedPool={handleAddToAdvisedPool}
                            vocationalProfile={support?.vocational_profile ?? support?.applicant_evidence?.vocational_profile}
                            onOpenSupplementalEvidence={() => { isSupplementalEvidenceOpen = true; }}
                            publishedAdvisingNotes={published_advising_notes}
                            {threads}
                            onOpenThread={(thread) => { activeTab = 'threads'; selectThread(thread); }}
                        />
                    {:else}
                        <TabFmConsultationCard
                            model={m}
                            available_programs={available_programs}
                            showEvidenceBar={false}
                            vocationalProfile={support?.vocational_profile ?? support?.applicant_evidence?.vocational_profile}
                            onOpenSupplementalEvidence={() => { isSupplementalEvidenceOpen = true; }}
                            publishedAdvisingNotes={published_advising_notes}
                            {threads}
                            onOpenThread={(thread) => { activeTab = 'threads'; selectThread(thread); }}
                        />
                    {/if}
                {:else if m.slot === 'yu_kee'}
                    <YuKeeConsultationCard model={m} />
                {:else}
                    <UnknownEngineCard model={m} />
                {/if}
            {/each}
        </div>
    </section>
{/snippet}


{#snippet followupSection()}
    <!-- Humanized Academic Department Referrals Section -->
    <section class="space-y-4">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold tracking-tight">Academic Department Referrals</h2>
                <p class="text-sm text-muted-foreground">Candidate referrals and special waivers dispatched to Department Heads or College Deans.</p>
            </div>
            {#if role === 'guidance'}
                <Button variant="outline" class="min-h-[44px] gap-2 font-semibold" onclick={() => { followUpForm.program_code = available_programs?.[0]?.code || ''; followUpDialogOpen = true; }}>
                    <ShieldCheck class="size-4" /> + Refer to Dean / Head
                </Button>
            {/if}
        </div>
        <Card.Root>
            <Card.Content class="p-0">
                {#if support?.follow_ups?.length}
                    <ul class="divide-y">
                        {#each support.follow_ups as f (f.program_code + f.opened_at)}
                            <li class="flex items-start justify-between gap-3 p-4 text-sm">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold">{programTitle(f.program_code)}</span>
                                        <Badge variant="outline" class="capitalize">{f.status?.replace(/_/g, ' ')}</Badge>
                                    </div>
                                    <p class="mt-1 text-xs text-muted-foreground">{f.reason}</p>
                                </div>
                                <span class="shrink-0 text-xs text-muted-foreground">{formatDate(f.opened_at)}</span>
                            </li>
                        {/each}
                    </ul>
                {:else}
                    <p class="py-8 text-center text-sm text-muted-foreground">No open or resolved departmental referrals for this examinee.</p>
                {/if}
            </Card.Content>
        </Card.Root>
    </section>
{/snippet}
{#snippet logsSection()}
    <section class="space-y-6">
        {@render followupSection()}

        <section class="space-y-4">
            <div>
                <h2 class="text-lg font-semibold tracking-tight">Chronological Consultation History &amp; Activity Logs</h2>
                <p class="text-sm text-muted-foreground">Recorded decisions, timestamps, counselor attribution, and notes.</p>
            </div>
            {#if consultationHistory.length}
                <ol class="relative space-y-4 border-l pl-5">
                    {#each consultationHistory as entry, index (`${entry.recorded_at ?? entry.created_at ?? index}-${index}`)}
                        <li class="relative">
                            <span class="absolute -left-[1.58rem] top-1.5 size-2.5 rounded-full border-2 border-background bg-primary"></span>
                            <Card.Root>
                                <Card.Content class="space-y-2 p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <Badge variant="outline" class="capitalize">{(entry.decision_type ?? entry.action ?? 'Consultation activity').replace(/_/g, ' ')}</Badge>
                                            {#if entry.recorded_by || entry.counselor}
                                                <span class="text-sm text-muted-foreground">by {entry.recorded_by ?? entry.counselor}</span>
                                            {/if}
                                        </div>
                                        <time class="text-xs text-muted-foreground" datetime={entry.recorded_at ?? entry.created_at ?? undefined}>
                                            {entry.recorded_at || entry.created_at ? new Date(entry.recorded_at ?? entry.created_at).toLocaleString() : 'Timestamp unavailable'}
                                        </time>
                                    </div>
                                    {#if entry.notes}
                                        <p class="whitespace-pre-wrap text-sm leading-relaxed">{entry.notes}</p>
                                    {:else}
                                        <p class="text-sm text-muted-foreground">No counselor notes recorded.</p>
                                    {/if}
                                </Card.Content>
                            </Card.Root>
                        </li>
                    {/each}
                </ol>
            {:else}
                <Card.Root>
                    <Card.Content class="p-6 text-center text-sm text-muted-foreground">No advising decisions or activity logs have been recorded.</Card.Content>
                </Card.Root>
            {/if}
        </section>
    </section>
{/snippet}

{#snippet recordSection()}
    <section class="space-y-6">
        {#if ph_record?.tier !== 'B'}
            <div role="status" class="rounded-lg border border-border bg-muted/30 p-4 text-sm">
                <h2 class="font-semibold">Academic review</h2>
                <p class="mt-2 text-muted-foreground">{ph_record?.blocked_reason ?? ph_record?.visibility_note}</p>
            </div>
        {:else}
            <section class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold">Assessment results</h2>
                    {#if ph_record.result_sheet_url}
                        <a href={ph_record.result_sheet_url} class="inline-flex min-h-10 items-center rounded-md border border-border px-3 text-sm font-medium hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">View result sheet</a>
                    {/if}
                </div>
                <p role="status" class="text-sm text-muted-foreground">
                    {ph_record.result_released ? 'Results issued to applicant' : 'Results not yet issued to applicant'}
                </p>
                <div class="overflow-x-auto rounded-lg border border-border">
                    <Table.Root>
                        <Table.Caption class="sr-only">Academic assessment results for {ph_program?.code}</Table.Caption>
                        <Table.Header><Table.Row><Table.Head>Aptitude area</Table.Head><Table.Head>Converted result</Table.Head><Table.Head>Scale</Table.Head></Table.Row></Table.Header>
                        <Table.Body>
                            {#each ph_record.score_profile?.series ?? [] as score (score.area_code)}
                                <Table.Row>
                                    <Table.Cell>{score.area_name ?? score.area_code}</Table.Cell>
                                    <Table.Cell class="tabular-nums">{score.score ?? 'Unavailable'}</Table.Cell>
                                    <Table.Cell>{(score.scale ?? 'converted score').replaceAll('_', ' ')}</Table.Cell>
                                </Table.Row>
                            {:else}
                                <Table.Row><Table.Cell colspan={3} class="py-6 text-muted-foreground">No current assessment scores are available.</Table.Cell></Table.Row>
                            {/each}
                        </Table.Body>
                    </Table.Root>
                </div>
            </section>

            <TabFmConsultationCard
                model={ph_record.analysis}
                scopedProgramCode={ph_program?.code ?? 'selected program'}
                showEvidenceBar={false}
            />

            <section class="space-y-3" aria-label="Applicant background">
                <h2 class="text-lg font-semibold">Applicant background</h2>
                <dl class="grid gap-x-4 gap-y-3 rounded-lg border border-border/70 p-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs font-medium text-muted-foreground">Applicant type</dt><dd class="mt-1 font-medium">{ph_record.background?.applicant_type ?? 'Not provided'}</dd></div>
                    <div><dt class="text-xs font-medium text-muted-foreground">Academic strand</dt><dd class="mt-1 font-medium">{ph_record.background?.strand ?? 'Not provided'}</dd></div>
                    <div><dt class="text-xs font-medium text-muted-foreground">Normalized GWA</dt><dd class="mt-1 font-medium tabular-nums">{ph_record.background?.gwa ?? 'Not provided'}</dd></div>
                </dl>
                {#if ph_record.background?.vocational_profile}
                    {@const vocational = ph_record.background.vocational_profile}
                    <section class="space-y-2 rounded-lg border border-border/70 p-4" aria-label="Reviewed Holland summary">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold">Holland summary</h3>
                            <Badge variant="outline">{vocational.is_reviewed || vocational.status === 'reviewed' ? 'Reviewed' : 'Not reviewed'}</Badge>
                        </div>
                        {#if vocational.is_reviewed || vocational.status === 'reviewed'}
                            {#if vocational.emergent_code}<p class="text-sm text-muted-foreground">Profile: <span class="font-medium text-foreground">{vocational.emergent_code}</span></p>{/if}
                            {#if vocational.emergent_themes?.length}<p class="break-words text-sm">{vocational.emergent_themes.join(' · ')}</p>{/if}
                            {#if vocational.scores}
                                <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-3">
                                    {#each [{key: 'R', label: 'Realistic'}, {key: 'I', label: 'Investigative'}, {key: 'A', label: 'Artistic'}, {key: 'S', label: 'Social'}, {key: 'E', label: 'Enterprising'}, {key: 'C', label: 'Conventional'}] as area (area.key)}
                                        {#if vocational.scores[area.key] !== undefined}<div><dt class="text-xs text-muted-foreground">{area.label}</dt><dd class="font-mono tabular-nums">{vocational.scores[area.key]}%</dd></div>{/if}
                                    {/each}
                                </dl>
                            {/if}
                            {#if vocational.program_targets?.[ph_program?.code]}
                                <HollandRadarHexagon
                                    vocationalProfile={vocational}
                                    selectedProgram={{ program_code: ph_program?.code, program_name: ph_program?.name }}
                                    applicantEvidence={{ strand: ph_record.background?.strand, applicant_type: ph_record.background?.applicant_type }}
                                />
                            {/if}
                        {:else}
                            <p class="text-sm text-muted-foreground">A reviewed vocational summary is not available.</p>
                        {/if}
                    </section>
                {/if}
            </section>

            <section class="space-y-3">
                <h2 class="text-lg font-semibold">Referral and shared context</h2>
                {#each ph_record.follow_ups ?? [] as followUp (followUp.id)}
                    <article class="space-y-2 rounded-lg border border-border p-4 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-medium">{ph_program?.code} academic review</span>
                            <Badge variant="outline">{({ open: 'Pending', appearance_requested: 'Appearance requested', cleared_by_ph: 'Cleared', declined_by_ph: 'Declined' })[followUp.status] ?? followUp.status}</Badge>
                        </div>
                        <p class="whitespace-pre-wrap break-words">{followUp.reason}</p>
                        <p class="text-xs text-muted-foreground">{followUp.opened_by ?? 'Guidance'}{#if followUp.opened_at} · {formatDate(followUp.opened_at)}{/if}</p>
                        {#if followUp.resolution_reason}<p class="whitespace-pre-wrap break-words text-muted-foreground">{followUp.resolution_reason}</p>{/if}
                    </article>
                {:else}
                    <p class="text-sm text-muted-foreground">No targeted referral is recorded for this program.</p>
                {/each}
                {#if ph_record.advising_outcome}
                    <p class="text-sm">
                        Advising outcome: <span class="font-medium">{({ approve: 'Approved', modify: 'Program options updated', override: 'Override recorded', defer: 'Deferred', refer: 'Referred for academic review' })[ph_record.advising_outcome.decision_type] ?? ph_record.advising_outcome.decision_type}</span>
                        <span class="text-muted-foreground"> · {ph_record.advising_outcome.recorded_by ?? 'Guidance'}{#if ph_record.advising_outcome.recorded_at} · {formatDate(ph_record.advising_outcome.recorded_at)}{/if}</span>
                    </p>
                {/if}
                <p class="text-xs text-muted-foreground">Only the referral context shared for this program is shown. Private counseling notes are not included.</p>
            </section>

            {@render publishedNotesSection()}
        {/if}
    </section>
{/snippet}

{#snippet threadRow(t)}
    <button
        type="button"
        class="w-full text-left p-3.5 hover:bg-muted/40 transition-colors flex items-center justify-between gap-4 cursor-pointer"
        onclick={() => selectThread(t)}
    >
        <div class="min-w-0 flex-1 space-y-1">
            <div class="flex items-center gap-2">
                <span class="font-semibold text-sm text-foreground truncate">{t.subject}</span>
                {#if t.unread_count > 0}
                    <Badge variant="destructive" class="text-[10px] px-1.5 py-0 font-medium">
                        {t.unread_count} unread
                    </Badge>
                {/if}
            </div>
            <div class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                {#if t.applicant?.display_name || attempt?.name}
                    <span class="font-medium text-foreground/80">{t.applicant?.display_name ?? attempt?.name}</span>
                    <span>&middot;</span>
                {/if}
                {#if t.applicant?.reference_code || attempt?.reference_code}
                    <span class="font-mono text-[11px]">{t.applicant?.reference_code ?? attempt?.reference_code}</span>
                    <span>&middot;</span>
                {/if}
                {#if t.program_code}
                    <Badge variant="outline" class="font-mono text-[10px] py-0 px-1.5">{t.program_code}</Badge>
                {/if}
                {#if t.cycle_code}
                    <Badge variant="secondary" class="font-mono text-[10px] py-0 px-1.5">{t.cycle_code}</Badge>
                {/if}
                {#if t.last_activity_at}
                    <span>&middot;</span>
                    <span class="inline-flex items-center gap-1">
                        <Clock class="size-3" /> {formatDate(t.last_activity_at)}
                    </span>
                {/if}
            </div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <Badge variant={t.status === 'open' ? 'default' : 'secondary'} class="capitalize text-xs">
                {t.status}
            </Badge>
            <span class="text-muted-foreground text-xs">&rarr;</span>
        </div>
    </button>
{/snippet}

{#snippet threadsSection()}
    <section class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">Discussion</h2>
            <CaseConversationStarter
                programs={ph_program ? [ph_program] : available_programs}
                cycleCode={openingCycleCode}
                principalContext={openingContext}
                attempt={attempt}
                initialProgramCode={openingProgramCode}
                initialAudience={discussionAudience}
                allowApplicantSupport={role === 'program_head'}
                destination="case"
                destinationUrl={page.url}
                disabled={!canOpenDiscussion}
            />
        </div>
        <nav class="flex flex-wrap gap-2" aria-label="Discussion audience">
            <Button variant={discussionAudience === 'internal_ph_guidance' ? 'default' : 'outline'} aria-pressed={discussionAudience === 'internal_ph_guidance'} onclick={() => { discussionAudience = 'internal_ph_guidance'; if (selected_conversation?.thread?.thread_type !== discussionAudience) clearSelectedThread(); }}>
                <Lock class="mr-2 size-3.5" /> Staff discussion
            </Button>
            {#if role === 'program_head'}
                <Button variant={discussionAudience === 'applicant_support' ? 'default' : 'outline'} aria-pressed={discussionAudience === 'applicant_support'} onclick={() => { discussionAudience = 'applicant_support'; if (selected_conversation?.thread?.thread_type !== discussionAudience) clearSelectedThread(); }}>
                    <UserCheck class="mr-2 size-3.5" /> With applicant
                </Button>
            {/if}
        </nav>
        {#if role === 'program_head' && !canOpenDiscussion}
            <p role="status" class="rounded-lg border border-border bg-muted/30 p-4 text-sm text-muted-foreground">{ph_record?.blocked_reason ?? 'This case is not available for protected academic discussion.'}</p>
        {:else if selected_conversation && selected_conversation.thread?.thread_type === discussionAudience}
            <div class="flex flex-wrap items-center justify-between gap-2">
                <Button variant="ghost" size="sm" onclick={clearSelectedThread}>← Back to discussions</Button>
                <Link href={messagesUrl()} class="inline-flex min-h-10 items-center rounded-md px-3 text-sm font-medium text-primary hover:bg-muted">Open in Messages →</Link>
            </div>
            {#key selected_conversation.thread.id}
                <CaseConversation
                    initial={selected_conversation}
                    snapshotUrl={selected_conversation.snapshot_url}
                    sendUrl={selected_conversation.send_url}
                    readUrl={selected_conversation.read_url}
                    principalContext={selected_conversation.principal_key}
                    attemptContext={selected_conversation.attempt_key}
                />
            {/key}
        {:else}
            <p class="text-sm text-muted-foreground">
                {discussionAudience === 'internal_ph_guidance'
                    ? 'Private discussion between Guidance and authorized academic staff. The applicant cannot see these messages.'
                    : 'These conversations are visible to the applicant. Private staff discussion is not included.'}
            </p>
            {#if visibleDiscussionThreads.length}
                <div class="divide-y divide-border rounded-lg border border-border">
                    {#each visibleDiscussionThreads as thread (thread.id)}{@render threadRow(thread)}{/each}
                </div>
            {:else}
                <p class="rounded-lg border border-dashed border-border p-6 text-sm text-muted-foreground">No conversations recorded for this audience. Use Start conversation to write the first message.</p>
            {/if}
        {/if}
    </section>

{/snippet}

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 pb-[calc(7rem+env(safe-area-inset-bottom))] sm:p-6 sm:pb-[calc(7rem+env(safe-area-inset-bottom))] lg:pb-6">
        <E2PageState state={page_state} />

        {#if page_state === 'ready' && attempt && ((role === 'guidance' && support) || (role === 'program_head' && ph_record))}
            <header class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="font-serif text-3xl font-semibold tracking-tight">{attempt.name}</h1>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        <span class="font-mono">{attempt.reference_code}</span>
                        <span aria-hidden="true">·</span>
                        <span>Decision Support &amp; Advisory Workspace</span>
                        {#if role === 'program_head' && ph_program}
                            <Badge variant="outline">Assigned program: {ph_program.code}</Badge>
                        {/if}
                    </div>
                </div>
                        <ApplicantActionsMenu attemptId={attempt.id} context={{ program_code: ph_program?.code ?? openingProgramCode ?? null, consultation_session_id: new URL(page.url, 'http://localhost').searchParams.get('session_id') ?? null }} />
                {#if role === 'guidance'}
                    <div class="flex flex-wrap items-center gap-2">
                        <PrintDocumentsSlot slotKey="guidance_applicant_support" attemptIds={attempt?.id ? [attempt.id] : []} autoStart={true} compact={false} />
                        <Button
                            size="lg"
                            class="hidden min-h-[44px] gap-2 font-semibold shadow-sm lg:inline-flex"
                            disabled={!support?.readiness?.result_ready}
                            onclick={() => handleDecisionDialogChange(true)}
                        >
                            <UserCheck class="size-4" /> Record Decision
                        </Button>
                        {#if online_consultation?.visible}
                            {#if online_consultation.encounter_url}
                                <a href={online_consultation.encounter_url} class="text-xs font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline">
                                    Open online consultation
                                </a>
                            {:else if !online_consultation.can_start}
                                <span role="note" class="text-xs font-normal text-muted-foreground" title={online_consultation.reason ?? undefined}>
                                    {online_consultation.reason?.startsWith('Online consultation is unavailable:') ? 'Online consultation is temporarily unavailable' : online_consultation.reason ?? 'Online consultation is currently unavailable'}
                                </span>
                            {:else}
                                <button type="button" class="text-xs font-normal text-muted-foreground underline-offset-4 hover:text-foreground hover:underline disabled:opacity-60" disabled={onlineStarting || !online_consultation.can_start} title={online_consultation.reason ?? undefined} onclick={startOnlineConsultation}>
                                    {onlineStarting ? 'Starting…' : online_consultation.exception_mode ? 'Start online exception' : 'Start online consultation'}
                                </button>
                            {/if}
                            {#if onlineStartError}
                                <p role="alert" class="text-xs text-destructive">{onlineStartError}</p>
                            {/if}
                        {/if}
                    </div>
                {/if}
            </header>

            {#if role === 'guidance' && !support?.readiness?.result_ready}
                <div class="rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-sm" role="status" aria-live="polite">
                    <p class="font-semibold text-amber-950 dark:text-amber-200">Release Day advising is not ready</p>
                    <p class="mt-1 text-muted-foreground">Prepare this examinee's completed individual result before recording a Release Day advising decision. Other examinees in the grading session do not need to be finished.</p>
                </div>
            {/if}

            {#if role === 'guidance'}
                <div class="lg:hidden">
                    {@render journeySection()}
                </div>
            {/if}

            <div class={role === 'guidance' ? 'grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start' : 'min-w-0'}>
                <main class="min-w-0">
                    <Tabs.Root value={activeTab} onValueChange={(value) => (activeTab = value)}>
                        <!-- TACTILE SEGMENTED SUB-NAVBAR (Clean pills, zero underline conflict or ugly scrollbar) -->
                        <Tabs.List class="sticky top-0 z-30 mb-6 flex w-full max-w-2xl items-center gap-1 rounded-xl border border-border/70 bg-card/90 p-1 backdrop-blur-md shadow-2xs">
                            {#if role === 'guidance'}
                                <Tabs.Trigger class="flex-1 rounded-lg py-2 px-3 text-xs font-semibold transition-all duration-200 text-muted-foreground hover:text-foreground data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-xs data-[state=active]:border data-[state=active]:border-border/60" value="profile">
                                    <span class="sm:hidden">Profile</span><span class="hidden sm:inline">Profile &amp; Assessment</span>
                                </Tabs.Trigger>
                                <Tabs.Trigger class="flex-1 rounded-lg py-2 px-3 text-xs font-semibold transition-all duration-200 text-muted-foreground hover:text-foreground data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-xs data-[state=active]:border data-[state=active]:border-border/60" value="analysis">
                                    <span class="sm:hidden">Matches</span><span class="hidden sm:inline">Recommendations &amp; Analysis</span>
                                </Tabs.Trigger>
                                <Tabs.Trigger class="flex-1 rounded-lg py-2 px-3 text-xs font-semibold transition-all duration-200 text-muted-foreground hover:text-foreground data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-xs data-[state=active]:border data-[state=active]:border-border/60" value="logs">
                                    <span class="sm:hidden">Logs</span><span class="hidden sm:inline">Referrals &amp; Activity Logs</span>
                                </Tabs.Trigger>
                                <Tabs.Trigger class="flex-1 rounded-lg py-2 px-3 text-xs font-semibold transition-all duration-200 text-muted-foreground hover:text-foreground data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-xs data-[state=active]:border data-[state=active]:border-border/60" value="threads">
                                    Discussion
                                </Tabs.Trigger>
                            {:else if role === 'program_head'}
                                <Tabs.Trigger class="flex-1 rounded-lg px-3 py-2 text-sm font-medium" value="record">Academic review</Tabs.Trigger>
                                <Tabs.Trigger class="flex-1 rounded-lg px-3 py-2 text-sm font-medium" value="threads">Discussion</Tabs.Trigger>
                            {/if}
                        </Tabs.List>
                        {#if role === 'guidance'}
                            <Tabs.Content value="profile">{@render assessmentSection()}</Tabs.Content>
                            <Tabs.Content value="analysis">{@render analysisSection()}</Tabs.Content>
                            <Tabs.Content value="logs">{@render logsSection()}</Tabs.Content>
                            <Tabs.Content value="threads">{@render threadsSection()}</Tabs.Content>
                        {:else if role === 'program_head'}
                            <Tabs.Content value="record">{@render recordSection()}</Tabs.Content>
                            <Tabs.Content value="threads">{@render threadsSection()}</Tabs.Content>
                        {/if}
                    </Tabs.Root>
                </main>
                <!-- STICKY APPLICANT JOURNEY SIDEBAR (Pinned on desktop during scrolling) -->
                {#if role === 'guidance'}
                <aside class="hidden space-y-4 lg:block lg:sticky lg:top-6 lg:self-start h-fit">
                    {@render journeySection()}
                    <Card.Root class="border-border/80 shadow-xs">
                        <Card.Header class="pb-3 border-b border-border/40">
                            <Card.Title class="text-sm font-bold text-foreground">Case facts</Card.Title>
                        </Card.Header>
                        <Card.Content class="space-y-3 pt-3 text-xs">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-muted-foreground">Current Selection</span>
                                <span class="font-semibold text-foreground">{programTitle(support?.readiness?.selection?.program_code)}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-muted-foreground">Advised Course</span>
                                <span class="font-semibold text-primary">{programTitle(support?.program_pool?.default)}</span>
                            </div>
                        </Card.Content>
                    </Card.Root>
                </aside>
                {/if}
            </div>

            {#if role === 'guidance'}
                <ConsultationMobileDock
                    mode="detail"
                    onSearch={() => (lookupDialogOpen = true)}
                    listHref="/consultation"
                    onRecord={() => handleDecisionDialogChange(true)}
                    recordDisabled={!support?.readiness?.result_ready}
                />
                <ApplicantLookupDialog open={lookupDialogOpen} onOpenChange={(open) => (lookupDialogOpen = open)} />
            {/if}
        {#if role === 'guidance'}
            <SupplementalEvidenceModal
                open={isSupplementalEvidenceOpen}
                onClose={() => { isSupplementalEvidenceOpen = false; }}
                attemptId={attempt?.id}
                baselineEvidence={support?.applicant_evidence}
                inputSchema={support?.input_schema ?? support?.models?.[0]?.input_schema}
                benchmarkActivationId={support?.benchmark_activation_id ?? support?.models?.[0]?.evidence_context?.benchmark_activation_id}
                schemaDigest={support?.schema_digest ?? support?.models?.[0]?.evidence_context?.schema_digest}
            />
        {/if}

            <!-- Record Advising Decision Modal -->
            {#if role === 'guidance'}
                <Dialog open={decisionDialogOpen} onOpenChange={handleDecisionDialogChange}>
                    <DialogContent class="sm:max-w-3xl max-h-[90vh] flex flex-col p-0">
                        <div class="overflow-y-auto p-6 space-y-6">
                            <DialogHeader>
                                <DialogTitle>Record Advising Decision</DialogTitle>
                                <DialogDescription>Choose the advising outcome for {attempt.name}. This action and any program recommendations are recorded together.</DialogDescription>
                            </DialogHeader>

                            <section class="space-y-3">
                                <h3 class="text-sm font-semibold">Choose an action</h3>
                                <div class="flex gap-2.5 overflow-x-auto pb-1">
                                    <button type="button" aria-pressed={['approve', 'modify', 'override'].includes(decisionForm.decision_type)} onclick={() => setDecisionType(p1Code ? 'approve' : 'modify')} class={`inline-flex min-h-11 shrink-0 items-center gap-2 rounded-lg border px-4 text-sm font-semibold transition-colors ${['approve', 'modify', 'override'].includes(decisionForm.decision_type) ? 'border-primary bg-primary/5 text-primary' : 'hover:border-primary/50 hover:bg-muted/40'}`}>
                                        <span aria-hidden="true">★</span> Endorse Program
                                    </button>
                                    <button type="button" aria-pressed={decisionForm.decision_type === 'refer'} onclick={() => setDecisionType('refer')} class={`inline-flex min-h-11 shrink-0 items-center gap-2 rounded-lg border px-4 text-sm font-semibold transition-colors ${decisionForm.decision_type === 'refer' ? 'border-primary bg-primary/5 text-primary' : 'hover:border-primary/50 hover:bg-muted/40'}`}>
                                        <Building2 class="size-4" /> Refer for Academic Review
                                    </button>
                                    <button type="button" aria-pressed={decisionForm.decision_type === 'defer'} onclick={() => setDecisionType('defer')} class={`inline-flex min-h-11 shrink-0 items-center gap-2 rounded-lg border px-4 text-sm font-semibold transition-colors ${decisionForm.decision_type === 'defer' ? 'border-primary bg-primary/5 text-primary' : 'hover:border-primary/50 hover:bg-muted/40'}`}>
                                        <Clock class="size-4" /> Defer Session
                                    </button>
                                </div>
                            </section>

                            {#if ['approve', 'modify', 'override'].includes(decisionForm.decision_type)}
                                <section class="space-y-4 rounded-xl border bg-muted/20 p-4">
                                    <div>
                                        <h3 class="font-semibold">Recommended Program</h3>
                                        <p class="mt-1 text-sm text-muted-foreground">Choose one primary recommendation. Add other programs only if the applicant may choose them instead.</p>
                                    </div>
                                    {#if decisionPoolEditAmbiguous}
                                        <p class="text-sm text-amber-800 dark:text-amber-300" role="status">Saved program options have no recorded primary counselor recommendation. Choose a primary recommendation below; the other existing options remain applicant choices.</p>
                                    {/if}
                                    <div class="space-y-2">
                                        <Label for="primary-recommended-program" class="font-semibold">Primary recommended program</Label>
                                        <select
                                            id="primary-recommended-program"
                                            bind:value={decisionAdvisedProgram}
                                            onchange={(event) => selectDecisionProgram(event.currentTarget.value)}
                                            required
                                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            <option value="">Select primary recommended program...</option>
                                            {#each available_programs as prog (prog.code)}
                                                <option value={prog.code}>[{prog.code}] {prog.name}</option>
                                            {/each}
                                        </select>
                                        {#if decisionForm.errors.default_program_code}<p class="text-sm text-destructive" role="alert">{decisionForm.errors.default_program_code}</p>{/if}
                                        {#if decisionPoolOptionError(0, 'program_code')}<p class="text-sm text-destructive" role="alert">{decisionPoolOptionError(0, 'program_code')}</p>{/if}
                                    </div>
                                    <div class="space-y-3 rounded-lg border bg-background p-3">
                                        <div>
                                            <h4 class="text-sm font-semibold">Optional applicant choices</h4>
                                            <p class="mt-1 text-sm text-muted-foreground">Only saved alternatives become available to the applicant. Their confirmation or choice is recorded separately from your recommendation.</p>
                                        </div>
                                        {#each decisionAlternates as alternate, index (index)}
                                            <div class="flex items-center gap-2">
                                                <select
                                                    aria-label={`Alternate degree option ${index + 1}`}
                                                    bind:value={decisionAlternates[index]}
                                                    onchange={() => (decisionClientError = '')}
                                                    class="flex h-10 min-w-0 flex-1 rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                >
                                                    <option value="">Select an alternate program...</option>
                                                    {#each available_programs as prog (prog.code)}
                                                        {#if prog.code !== decisionAdvisedProgram && !decisionAlternates.some((code, alternateIndex) => alternateIndex !== index && code === prog.code)}
                                                            <option value={prog.code}>[{prog.code}] {prog.name}</option>
                                                        {/if}
                                                    {/each}
                                                </select>
                                                {#if decisionPoolOptionError(index + 1, 'program_code')}<p class="text-xs text-destructive" role="alert">{decisionPoolOptionError(index + 1, 'program_code')}</p>{/if}
                                                <Button type="button" variant="outline" size="icon" aria-label={`Remove alternate program ${index + 1}`} onclick={() => removeDecisionAlternate(index)}><Trash2 class="size-4" /></Button>
                                            </div>
                                        {/each}
                                        <button type="button" class="inline-flex min-h-10 items-center gap-2 text-sm font-semibold text-primary hover:underline" onclick={addDecisionAlternate} disabled={decisionAlternates.length >= Math.max(0, available_programs.length - 1)}>
                                            <Plus class="size-4" /> Add Alternate Degree Option
                                        </button>
                                        {#if decisionForm.errors.pool_options}<p class="text-sm text-destructive" role="alert">{decisionForm.errors.pool_options}</p>{/if}
                                    </div>
                                    <div class="space-y-2">
                                        <h4 class="text-sm font-semibold">Programs and quota</h4>
                                        <p class="text-sm text-muted-foreground">Select the primary program above or choose it here. Quota figures describe current capacity.</p>
                                        {#each available_programs as prog (prog.code)}
                                            <button
                                                type="button"
                                                aria-pressed={decisionAdvisedProgram === prog.code}
                                                onclick={() => selectDecisionProgram(prog.code)}
                                                class={`flex w-full flex-wrap items-center justify-between gap-3 rounded-lg border bg-background p-3 text-left transition-colors ${decisionAdvisedProgram === prog.code ? 'border-primary ring-2 ring-primary/20' : 'hover:border-primary/50'}`}
                                            >
                                                <span class="min-w-0">
                                                    <span class="flex flex-wrap items-center gap-2">
                                                        <span class="font-mono text-xs font-semibold">{prog.code}</span>
                                                        {#if prog.code === p1Code}<Badge variant="default" class="text-[10px]">★ 1st Preference</Badge>{/if}
                                                    </span>
                                                    <span class="mt-1 block text-sm font-medium">{prog.name}</span>
                                                </span>
                                                <span class="flex shrink-0 flex-col items-end gap-1 text-xs font-medium">
                                                    {#if prog.configured}
                                                        <span class="flex items-center gap-1.5 {prog.remaining <= 0 ? 'text-destructive font-semibold' : 'text-emerald-700 dark:text-emerald-400 font-semibold'}">
                                                            <span aria-hidden="true">●</span>
                                                            {programQuotaLabel(prog)}
                                                        </span>
                                                        <span class="text-[10px] text-muted-foreground font-normal">{prog.committed}/{prog.capacity} committed</span>
                                                    {:else}
                                                        <span class="flex items-center gap-1.5 text-amber-700 dark:text-amber-400 font-medium">
                                                            <span aria-hidden="true">○</span>
                                                            No quota configured
                                                        </span>
                                                        <span class="max-w-48 text-right text-[10px] text-muted-foreground font-normal">Capacity enforced when saved.</span>
                                                    {/if}
                                                </span>
                                            </button>
                                        {/each}
                                    </div>
                                    {#if decisionPoolOptionError(0, 'basis')}<p class="text-xs text-destructive" role="alert">{decisionPoolOptionError(0, 'basis')}</p>{/if}
                                    {#if decisionPoolOptionError(0, 'display_order')}<p class="text-xs text-destructive" role="alert">{decisionPoolOptionError(0, 'display_order')}</p>{/if}

                                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border bg-background p-3 text-sm">
                                        <input
                                            type="checkbox"
                                            class="mt-0.5 size-4 accent-primary"
                                            checked={decisionForm.decision_type === 'override'}
                                            onchange={(event) => toggleDecisionOverride(event.currentTarget.checked)}
                                        />
                                        <span>
                                            <span class="font-semibold">Grant Institutional Policy Override</span>
                                            <span class="mt-0.5 block text-xs text-muted-foreground">A written justification is required. Quota and program capacity still apply.</span>
                                        </span>
                                    </label>

                                    {#if decisionForm.decision_type === 'override'}
                                        <div class="space-y-2">
                                            <Label for="decision-notes" class="font-semibold">Override Justification *</Label>
                                            <Textarea id="decision-notes" bind:value={decisionForm.notes} oninput={() => (decisionClientError = '')} placeholder="Document the institutional policy basis and justification..." rows="3" required />
                                            {#if decisionForm.errors.notes}<p class="text-sm text-destructive" role="alert">{decisionForm.errors.notes}</p>{/if}
                                        </div>
                                    {:else}
                                        <div class="space-y-2">
                                            <Label for="decision-notes" class="font-semibold">Guidance Notes &amp; Findings</Label>
                                            <Textarea id="decision-notes" bind:value={decisionForm.notes} placeholder="Record student strengths, career considerations, or advising context..." rows="3" />
                                            {#if decisionForm.errors.notes}<p class="text-sm text-destructive" role="alert">{decisionForm.errors.notes}</p>{/if}
                                        </div>
                                    {/if}
                                </section>
                            {:else if decisionForm.decision_type === 'refer'}
                                <section class="space-y-4 rounded-xl border border-purple-200 bg-purple-50/30 p-4 dark:border-purple-900 dark:bg-purple-950/20">
                                    <div class="space-y-2">
                                        <Label for="target-program-code" class="font-semibold">Target Academic Department Program *</Label>
                                        <select
                                            id="target-program-code"
                                            bind:value={decisionForm.target_program_code}
                                            onchange={() => (decisionClientError = '')}
                                            required
                                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        >
                                            <option value="">Select department program...</option>
                                            {#each available_programs as prog (prog.code)}
                                                <option value={prog.code}>[{prog.code}] {prog.name}</option>
                                            {/each}
                                        </select>
                                        {#if decisionForm.errors.target_program_code}<p class="text-sm text-destructive" role="alert">{decisionForm.errors.target_program_code}</p>{/if}
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="referral-reason" class="font-semibold">Referral Reason for Academic Review *</Label>
                                        <Textarea id="referral-reason" bind:value={decisionForm.referral_reason} oninput={() => (decisionClientError = '')} placeholder="Explain why academic department evaluation, interview, or clearance is needed..." rows="3" required />
                                        {#if decisionForm.errors.referral_reason}<p class="text-sm text-destructive" role="alert">{decisionForm.errors.referral_reason}</p>{/if}
                                    </div>
                                </section>
                            {:else}
                                <section class="space-y-3 rounded-xl border bg-muted/20 p-4">
                                    <div class="space-y-2">
                                        <Label for="decision-notes" class="font-semibold">Pending Items / Reason for Deferral *</Label>
                                        <Textarea id="decision-notes" bind:value={decisionForm.notes} oninput={() => (decisionClientError = '')} placeholder="List the documents or discussion needed before advising can continue..." rows="3" required />
                                        {#if decisionForm.errors.notes}<p class="text-sm text-destructive" role="alert">{decisionForm.errors.notes}</p>{/if}
                                    </div>
                                    <p class="text-sm text-muted-foreground">The student stays in the Guidance queue and is not cleared for enrollment until consultation is completed.</p>
                                </section>
                            {/if}

                            {#if decisionClientError}<p class="text-sm font-medium text-destructive" role="alert">{decisionClientError}</p>{/if}
                            {#if decisionForm.errors.decision_type}<p class="text-sm text-destructive">{decisionForm.errors.decision_type}</p>{/if}
                            {#if decisionForm.errors.default_program_code}<p class="text-sm text-destructive">{decisionForm.errors.default_program_code}</p>{/if}
                            {#if decisionForm.errors.pool_options}<p class="text-sm text-destructive">{decisionForm.errors.pool_options}</p>{/if}
                            {#if decisionForm.errors.target_program_code}<p class="text-sm text-destructive">{decisionForm.errors.target_program_code}</p>{/if}
                            {#if decisionForm.errors.referral_reason}<p class="text-sm text-destructive">{decisionForm.errors.referral_reason}</p>{/if}
                            {#if decisionForm.errors.notes}<p class="text-sm text-destructive">{decisionForm.errors.notes}</p>{/if}
                            {#if decisionForm.errors.decision}<p class="text-sm font-medium text-destructive" role="alert">{decisionForm.errors.decision}</p>{/if}
                        </div>
                        <DialogFooter class="px-6 pb-6">
                            <Button variant="outline" onclick={() => (decisionDialogOpen = false)}>Cancel</Button>
                            <Button onclick={submitDecision} disabled={decisionForm.processing} class="font-semibold">Save Decision</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <!-- Refer to Department Head / Dean Dialog -->
                <Dialog open={followUpDialogOpen} onOpenChange={(v) => (followUpDialogOpen = v)}>
                    <DialogContent class="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Refer to Academic Department Head / Dean</DialogTitle>
                            <DialogDescription>
                                Dispatches an edge case or waiver evaluation to the academic department head. This action cannot be withdrawn by Guidance after opening.
                            </DialogDescription>
                        </DialogHeader>
                        <div class="space-y-4">
                            <div class="space-y-2">
                                <Label for="followup-program" class="font-semibold">Department Degree Program</Label>
                                <select
                                    id="followup-program"
                                    bind:value={followUpForm.program_code}
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <option value="">Select academic program...</option>
                                    {#each available_programs as prog (prog.code)}
                                        <option value={prog.code}>[{prog.code}] {prog.name}</option>
                                    {/each}
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label for="followup-reason" class="font-semibold">Referral Reason &amp; Specific Request</Label>
                                <Textarea
                                    id="followup-reason"
                                    bind:value={followUpForm.reason}
                                    placeholder="Explain why this applicant is referred (e.g. Needs interview, quota inquiry, prerequisite waiver)..."
                                    rows="3"
                                />
                            </div>
                            {#if followUpForm.errors.follow_up}<p class="text-sm font-medium text-destructive">{followUpForm.errors.follow_up}</p>{/if}
                        </div>
                        <DialogFooter>
                            <Button variant="outline" onclick={() => (followUpDialogOpen = false)}>Cancel</Button>
                            <Button onclick={submitFollowUp} disabled={followUpForm.processing} class="font-semibold">Send Referral</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            {/if}
        {/if}
    </div>
</StaffLayout>
