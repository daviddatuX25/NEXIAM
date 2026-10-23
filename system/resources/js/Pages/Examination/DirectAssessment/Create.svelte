<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import ApplicantActionsMenu from '@/Components/Admission/ApplicantActionsMenu.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import {
        Card,
        CardContent,
        CardDescription,
        CardHeader,
        CardTitle,
    } from '@/Components/ui/card';
    import { Checkbox } from '@/Components/ui/checkbox';
    import { Label } from '@/Components/ui/label';
    import * as Dialog from '@/Components/ui/dialog';
    import { Link, useForm, usePage } from '@inertiajs/svelte';
    import { SvelteDate, SvelteSet } from 'svelte/reactivity';

    let {
        academicCycles = [],
        applicants = [],
        activeCycleId = null,
        kitVersions = [],
        storeRoute = '/exams/direct-assessment',
        focusAttemptId = null,
    } = $props();
    const focusedApplicant = $derived(applicants.find((applicant) => String(applicant.id) === String(focusAttemptId)) ?? null);

    const now = new SvelteDate();
    const defaultDate = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    const defaultStartTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    const defaultEndDate = new SvelteDate(now.getTime());
    defaultEndDate.setHours(defaultEndDate.getHours() + 1);
    const defaultEndTime = `${String(defaultEndDate.getHours()).padStart(2, '0')}:${String(defaultEndDate.getMinutes()).padStart(2, '0')}`;

    const form = useForm({
        academic_cycle_id: activeCycleId ? String(activeCycleId) : '',
        applicant_ids: focusedApplicant ? [focusedApplicant.id] : [],
        label: 'Walk-in Batch 1',
        date: defaultDate,
        start_time: defaultStartTime,
        end_time: defaultEndTime,
        test_kit_version_id: '',
    });
    const page = usePage();
    let search = $state('');
    let showSuccessDialog = $state(false);
    let lastSuccessFlash = false;
    const eligibleApplicants = $derived.by(() => {
        const query = search.trim().toLocaleLowerCase();
        if (!query) return applicants;
        return applicants.filter((applicant) =>
            `${applicant.name ?? ''} ${applicant.reference ?? ''}`
                .toLocaleLowerCase()
                .includes(query),
        );
    });
    const allVisibleSelected = $derived(
        eligibleApplicants.length > 0 &&
            eligibleApplicants.every((applicant) =>
                form.applicant_ids.includes(applicant.id),
            ),
    );

    $effect(() => {
        const success = Boolean(page.props.flash?.direct_assessment_created);
        if (success && !lastSuccessFlash) showSuccessDialog = true;
        lastSuccessFlash = success;
    });

    function toggleApplicant(id, checked) {
        const selected = new SvelteSet(form.applicant_ids);
        if (checked) selected.add(id);
        else selected.delete(id);
        form.applicant_ids = [...selected];
    }

    function toggleAllVisible(checked) {
        const selected = new SvelteSet(form.applicant_ids);
        for (const applicant of eligibleApplicants) {
            if (checked) selected.add(applicant.id);
            else selected.delete(applicant.id);
        }
        form.applicant_ids = [...selected];
    }

    function submit() {
        form.post(storeRoute);
    }

    const breadcrumbs = [
        { label: 'Exam Scheduling', href: '/exams/sessions' },
        { label: 'Direct Assessment' },
    ];
</script>

<svelte:head><title>Create Direct Assessment Session</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar
            title="Create Direct Assessment Session"
            description="Create a grading session for walk-in or offline score entry. Bypasses room scheduling and monitoring; creates an attended session ready for scoring."
        />

        <form
            onsubmit={(event) => {
                event.preventDefault();
                submit();
            }}
            class="space-y-6"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Session details</CardTitle>
                    <CardDescription
                        >Choose the academic cycle and assessment time for this
                        cohort.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="direct-assessment-cycle"
                                >Academic Cycle</Label
                            >
                            <select
                                id="direct-assessment-cycle"
                                bind:value={form.academic_cycle_id}
                                required
                                class="border-input bg-background flex min-h-10 w-full rounded-md border px-3 py-2 text-sm"
                            >
                                <option value="" disabled
                                    >Select academic cycle</option
                                >
                                {#each academicCycles as cycle (cycle.id)}
                                    <option value={String(cycle.id)}
                                        >{cycle.label}{cycle.is_active
                                            ? ' (Active)'
                                            : ''}</option
                                    >
                                {/each}
                            </select>
                            {#if form.errors.academic_cycle_id}<p
                                    class="text-destructive text-sm"
                                >
                                    {form.errors.academic_cycle_id}
                                </p>{/if}
                        </div>
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="direct-assessment-label"
                                >Label <span class="text-muted-foreground"
                                    >(optional)</span
                                ></Label
                            >
                            <Input
                                id="direct-assessment-label"
                                bind:value={form.label}
                                placeholder="e.g. Walk-in Batch 1"
                            />
                            {#if form.errors.label}<p
                                    class="text-destructive text-sm"
                                >
                                    {form.errors.label}
                                </p>{/if}
                        </div>
                        <div class="space-y-2">
                            <Label for="direct-assessment-date">Date</Label>
                            <Input
                                id="direct-assessment-date"
                                type="date"
                                bind:value={form.date}
                                required
                            />
                            {#if form.errors.date}<p
                                    class="text-destructive text-sm"
                                >
                                    {form.errors.date}
                                </p>{/if}
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-2">
                                <Label for="direct-assessment-start"
                                    >Start time</Label
                                >
                                <Input
                                    id="direct-assessment-start"
                                    type="time"
                                    bind:value={form.start_time}
                                    required
                                />
                                {#if form.errors.start_time}<p
                                        class="text-destructive text-sm"
                                    >
                                        {form.errors.start_time}
                                    </p>{/if}
                            </div>
                            <div class="space-y-2">
                                <Label for="direct-assessment-end"
                                    >End time</Label
                                >
                                <Input
                                    id="direct-assessment-end"
                                    type="time"
                                    bind:value={form.end_time}
                                    required
                                />
                                {#if form.errors.end_time}<p
                                        class="text-destructive text-sm"
                                    >
                                        {form.errors.end_time}
                                    </p>{/if}
                            </div>
                        </div>
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="direct-assessment-kit">Test Kit version</Label>
                            <select
                                id="direct-assessment-kit"
                                bind:value={form.test_kit_version_id}
                                required
                                class="border-input bg-background flex min-h-10 w-full rounded-md border px-3 py-2 text-sm"
                            >
                                <option value="" disabled>Select a locked Test Kit version</option>
                                {#each kitVersions as kit (kit.id)}
                                    <option value={String(kit.id)}>
                                        {kit.display_name} ({kit.kit_code} v{kit.version_number}){kit.profile_name ? ` · ${kit.profile_name}` : ''}
                                    </option>
                                {/each}
                            </select>
                            {#if form.errors.test_kit_version_id}
                                <p class="text-destructive text-sm">{form.errors.test_kit_version_id}</p>
                            {/if}
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div>
                            <div class="flex items-center gap-1.5">
                                <CardTitle>Eligible Applicants</CardTitle>
                                <InfoPopover
                                    title="Eligible walk-in / offline candidates"
                                    content="Lists accepted applicants in the active cycle who do not currently have an assigned exam session. Creating this session marks selected candidates Attended in a completed session ready for scoring."
                                />
                            </div>
                            <CardDescription
                                >Select the accepted applicants to register for
                                direct assessment.</CardDescription
                            >
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            {#if focusedApplicant}
                                <ApplicantActionsMenu attemptId={focusedApplicant.id} label="Focused applicant actions" />
                            {/if}
                            <Badge variant="outline">{form.applicant_ids.length} selected</Badge>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <Input
                            type="search"
                            bind:value={search}
                            placeholder="Search name or reference…"
                            aria-label="Search eligible applicants by name or reference"
                        />
                        <label
                            class="flex min-h-10 items-center gap-3 rounded-md border px-3 text-sm font-medium"
                        >
                            <Checkbox
                                checked={allVisibleSelected}
                                onCheckedChange={(checked) =>
                                    toggleAllVisible(Boolean(checked))}
                            />
                            Select All
                        </label>
                        <div
                            class="max-h-112 divide-y overflow-y-auto rounded-md border"
                        >
                            {#if eligibleApplicants.length === 0}
                                <p
                                    class="text-muted-foreground p-8 text-center text-sm"
                                >
                                    No eligible applicants found.
                                </p>
                            {:else}
                                {#each eligibleApplicants as applicant (applicant.id)}
                                    <label
                                        class="hover:bg-muted/40 flex cursor-pointer items-start gap-3 p-3"
                                    >
                                        <Checkbox
                                            checked={form.applicant_ids.includes(
                                                applicant.id,
                                            )}
                                            onCheckedChange={(checked) =>
                                                toggleApplicant(
                                                    applicant.id,
                                                    Boolean(checked),
                                                )}
                                        />
                                        <span class="min-w-0 flex-1">
                                            <span
                                                class="block truncate text-sm font-medium"
                                                >{applicant.name}</span
                                            >
                                            <span
                                                class="text-muted-foreground mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs"
                                            >
                                                <span class="font-mono"
                                                    >{applicant.reference}</span
                                                >
                                                <span
                                                    >{applicant.course_preference ??
                                                        'No course preference'}</span
                                                >
                                            </span>
                                        </span>
                                    </label>
                                {/each}
                            {/if}
                        </div>
                        {#if form.errors.applicant_ids}<p
                                class="text-destructive text-sm"
                            >
                                {form.errors.applicant_ids}
                            </p>{/if}
                    </div>
                </CardContent>
            </Card>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <Link href="/exams/sessions"
                    ><Button type="button" variant="outline" class="min-h-10"
                        >Cancel</Button
                    ></Link
                >
                <Button
                    type="submit"
                    class="min-h-10"
                    disabled={form.applicant_ids.length === 0 ||
                        form.processing}
                >
                    {form.processing
                        ? 'Creating…'
                        : `Create Session (${form.applicant_ids.length})`}
                </Button>
            </div>
        </form>
    </div>

    <Dialog.Root bind:open={showSuccessDialog}>
        <Dialog.Content class="sm:max-w-lg">
            <Dialog.Header>
                <Dialog.Title>Direct Assessment Created</Dialog.Title>
                <Dialog.Description
                    >The applicants have been registered for direct assessment
                    and are ready for score encoding. Please contact a Test
                    Administrator to begin grading.</Dialog.Description
                >
            </Dialog.Header>
            <Dialog.Footer>
                <Button
                    variant="outline"
                    onclick={() => (showSuccessDialog = false)}
                    >Stay Here</Button
                >
                <Link href="/exams/sessions"
                    ><Button>Go to Exam Scheduling</Button></Link
                >
            </Dialog.Footer>
        </Dialog.Content>
    </Dialog.Root>
</StaffLayout>
