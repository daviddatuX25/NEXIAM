<script>
    /**
     * P-REG-03 — Registrar Edit Application.
     * Legacy mimic: Admin/Applications/Edit.svelte
     * Allows registrar to update applicant details according to the current stage:
     * - Pending / Needs correction: in-office correction via POST /admissions/{id}/correct
     * - Accepted: authorized amendment via POST /admissions/{id}/authorize-amendment
     */
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import IntakeCorrectionFields from '@/Pages/Admission/IntakeCorrectionFields.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import { Label } from '@/Components/ui/label';
    import { Textarea } from '@/Components/ui/textarea';
    import * as Card from '@/Components/ui/card';
    import { Link, useForm } from '@inertiajs/svelte';
    import ReasonSuggestionChips from '@/Components/kit/ReasonSuggestionChips.svelte';
    import { formatEnumLabel } from '@/lib/date-utils.js';
    import { ADMISSION_AMENDMENT_SUGGESTIONS } from '@/lib/reason-suggestions.js';
    import { FileEdit, ArrowLeft, CheckCircle2, ShieldAlert } from 'lucide-svelte';

    let {
        page_state = 'ready',
        attempt,
        programs = [],
        expected_version = 1,
        capabilities = { accept: false, correct: false },
        intake_suggestions = { schools: [], shs: [], programs: [] },
    } = $props();

    const isAccepted = $derived(attempt.status === 'accepted');
    const isNeedsCorrection = $derived(attempt.status === 'needs_correction');
    const isPending = $derived(attempt.status === 'pending');

    const form = useForm({
        expected_version: expected_version ?? attempt.version ?? 1,
        identity_first_name: attempt.identity?.first_name ?? '',
        identity_middle_name: attempt.identity?.middle_name ?? '',
        identity_last_name: attempt.identity?.last_name ?? '',
        identity_suffix: attempt.identity?.suffix ?? '',
        identity_birthdate: attempt.identity?.birthdate ?? '',
        identity_sex: attempt.identity?.sex ?? 'male',
        identity_email: attempt.identity?.email ?? '',
        identity_phone: attempt.identity?.phone ?? '',
        preferred_program_code: attempt.preferred_program_code ?? '',
        program_preference_1_code:
            attempt.program_preferences?.[0] ??
            attempt.program_preference_1_code ??
            attempt.preferred_program_code ??
            '',
        program_preference_2_code:
            attempt.program_preferences?.[1] ??
            attempt.program_preference_2_code ??
            '',
        program_preference_3_code:
            attempt.program_preferences?.[2] ??
            attempt.program_preference_3_code ??
            '',
        contact_email: attempt.contact_email ?? attempt.identity?.email ?? '',
        contact_phone: attempt.contact_phone ?? attempt.identity?.phone ?? '',
        applicant_type: attempt.applicant_type ?? 'new',
        last_school_enrolled: attempt.last_school_enrolled ?? '',
        strand: attempt.strand ?? '',
        address_line: attempt.address?.line ?? attempt.address_line ?? '',
        city: attempt.address?.city ?? attempt.city ?? '',
        province: attempt.address?.province ?? attempt.province ?? '',
        zip_code: attempt.address?.zip_code ?? attempt.zip_code ?? '',
        correction_notes: attempt.correction_notes ?? '',
        amendment_reason: '',
    });

    function submit(event) {
        event.preventDefault();

        if (isAccepted) {
            form.transform((data) => ({
                ...data,
                reason: data.amendment_reason,
            })).post(`/admissions/${attempt.id}/authorize-amendment`);
        } else {
            form.post(`/admissions/${attempt.id}/correct`);
        }
    }

    const breadcrumbs = [
        { label: 'Admissions', href: '/admissions' },
        { label: attempt.reference_code, href: `/admissions/${attempt.id}` },
        { label: 'Edit' },
    ];
</script>

<svelte:head>
    <title>Edit {attempt.reference_code} · Admissions · NEXIAM</title>
</svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="mx-auto max-w-4xl space-y-6 pb-16">
        <PageHeaderBar
            title="Edit Application · {attempt.reference_code}"
            subtitle="Update applicant credentials, program preferences, and contact details."
        >
            {#snippet actions()}
                <Button variant="outline" href={`/admissions/${attempt.id}`} class="gap-2">
                    <ArrowLeft class="size-4" />
                    Back to detail
                </Button>
            {/snippet}
        </PageHeaderBar>

        <!-- Status Context Banner -->
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-border bg-card p-4 shadow-sm">
            <div class="space-y-0.5">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-semibold text-foreground">Application: {attempt.reference_code}</span>
                    <Badge variant={isAccepted ? 'success' : 'secondary'}>
                        {formatEnumLabel(attempt.status)}
                    </Badge>
                </div>
                <p class="text-xs text-muted-foreground">
                    Applicant: <strong class="text-foreground">{attempt.identity?.first_name} {attempt.identity?.last_name}</strong> ·
                    Version: <span class="font-mono">{expected_version}</span>
                </p>
            </div>
            <span class="text-xs font-mono text-muted-foreground">ID: #{attempt.id}</span>
        </div>

        {#if isAccepted}
            <div class="rounded-xl border border-warning/30 bg-warning/5 p-4 text-sm text-foreground flex items-start gap-3">
                <ShieldAlert class="size-5 text-warning shrink-0 mt-0.5" />
                <div class="space-y-1">
                    <span class="font-semibold text-foreground">Post-Acceptance Authorized Amendment</span>
                    <p class="text-xs text-muted-foreground leading-relaxed">
                        This application has already been accepted. Modifying applicant facts maintains accepted eligibility
                        and existing examination appointments, but requires an attributable authority reason for auditing.
                    </p>
                </div>
            </div>
        {:else if isNeedsCorrection}
            <div class="rounded-xl border border-info/30 bg-info/5 p-4 text-sm text-foreground flex items-start gap-3">
                <FileEdit class="size-5 text-info shrink-0 mt-0.5" />
                <div class="space-y-1">
                    <span class="font-semibold text-foreground">Office Correction for Pending Applicant Visit</span>
                    <p class="text-xs text-muted-foreground leading-relaxed">
                        Office Correction for Pending Applicant Visit — Saving records the office fix and returns this application to pending review (ready for Accept) without requiring portal resubmission.
                    </p>
                </div>
            </div>
        {:else if isPending}
            <div class="rounded-xl border border-border bg-muted/30 p-4 text-sm text-foreground flex items-start gap-3">
                <FileEdit class="size-5 text-primary shrink-0 mt-0.5" />
                <div class="space-y-1">
                    <span class="font-semibold text-foreground">In-place Correction While Pending</span>
                    <p class="text-xs text-muted-foreground leading-relaxed">
                        In-place correction while pending — updates application facts in place while keeping the application in the review queue.
                    </p>
                </div>
            </div>
        {/if}

        <form onsubmit={submit} class="space-y-6">
            <Card.Root>
                <Card.Header>
                    <Card.Title class="text-lg font-semibold flex items-center gap-2">
                        <FileEdit class="size-5 text-primary" />
                        Application Fields
                    </Card.Title>
                    <Card.Description>
                        Edit program choices, academic history, and contact details.
                    </Card.Description>
                </Card.Header>
                <Card.Content>
                    <IntakeCorrectionFields
                        {form}
                        {programs}
                        intake_suggestions={intake_suggestions}
                        identityEditable={true}
                        birthdateEditable={true}
                        showInternalNotes={!isAccepted}
                        idPrefix="edit-app"
                    />
                </Card.Content>
            </Card.Root>

            {#if isAccepted}
                <Card.Root class="border-warning/30">
                    <Card.Header>
                        <Card.Title class="text-base font-semibold text-foreground">Authority Reason for Amendment</Card.Title>
                        <Card.Description>
                            State the administrative justification for modifying an already-accepted application.
                        </Card.Description>
                    </Card.Header>
                    <Card.Content class="space-y-2">
                        <Label for="amendment_reason">Reason for amendment *</Label>
                        <Textarea
                            id="amendment_reason"
                            bind:value={form.amendment_reason}
                            placeholder="e.g. Corrected school address per official Form 138 submission."
                            rows={3}
                            required
                        />
                        <ReasonSuggestionChips
                            suggestions={ADMISSION_AMENDMENT_SUGGESTIONS}
                            bind:value={form.amendment_reason}
                            maxlength={2000}
                        />
                        {#if form.errors.reason || form.errors.amendment_reason}
                            <p class="text-sm text-destructive">{form.errors.reason || form.errors.amendment_reason}</p>
                        {/if}
                    </Card.Content>
                </Card.Root>
            {/if}

            {#if form.errors.version}
                <p class="text-sm text-destructive">{form.errors.version}</p>
            {/if}

            <div class="flex items-center justify-end gap-3 pt-2">
                <Button variant="outline" href={`/admissions/${attempt.id}`}>Cancel</Button>
                <Button type="submit" class="min-w-36 gap-2" disabled={form.processing || (isAccepted && !form.amendment_reason.trim())}>
                    <CheckCircle2 class="size-4" />
                    {form.processing ? 'Saving…' : (isAccepted ? 'Authorize amendment' : 'Save changes')}
                </Button>
            </div>
        </form>
    </div>
</StaffLayout>
