<script>
    import {
        AlertTriangle,
        CheckCircle2,
        ChevronDown,
        CircleDot,
    } from 'lucide-svelte';
    import { untrack } from 'svelte';

    let { steps = [], streaming = false } = $props();

    let open = $state(false);
    let elapsedSeconds = $state(0);
    let timer = null;

    const STEP_LABELS = {
        'workspace.summary@1': 'Reviewing workspace & queues…',
        'exams.sessions.today@1': 'Checking today’s scheduled sessions…',
        'exams.rooms.available@1': 'Checking room availability…',
        'exams.sessions.list@1': 'Checking the exam schedule…',
        'exams.session.show@1': 'Loading exam session details…',
        'foundation.academic_cycles.list@1': 'Checking academic year & semester…',
        'exams.session.create@1': 'Preparing exam session proposal…',
        'exams.session.assign@1': 'Checking applicant assignments…',
        'exams.session.publish@1': 'Validating session publication…',
        'exams.session.reservation.open@1': 'Opening session reservation window…',
        'exams.slot.reserve@1': 'Processing exam slot reservation…',
        'consultation.sessions.list@1': 'Checking Release Day schedule…',
        'consultation.session.create@1': 'Preparing Release Day session draft…',
        'consultation.session.reservation.open@1': 'Opening Release Day reservations…',
        'consultation.decision.record@2': 'Recording Release Day decision…',
        'consultation.session.cancel@1': 'Cancelling Release Day session…',
        'admissions.attempt.accept@1': 'Preparing applicant acceptance…',
        'admissions.attempt.bulk_accept@1': 'Preparing bulk acceptance…',
        'admissions.attempt.request_correction@1': 'Preparing correction request…',
        'assessment.unscored_examinees.query@1': 'Checking unscored examinees…',
        'assessment.scores.record_manual@1': 'Preparing manual scores entry…',
        'assessment.grading.finalize@1': 'Validating grading finalization…',
        'results.release.overview@1': 'Checking results release progress…',
        'release.result.publish@2': 'Preparing result publication…',
        'setup.readiness.read@1': 'Checking setup readiness…',
        'setup.cycles.create_draft@1': 'Preparing academic cycle draft…',
        'setup.cycles.activate@1': 'Preparing cycle activation…',
        'setup.rooms.create_draft@1': 'Preparing room setup…',
        'setup.rooms.update@1': 'Updating room configuration…',
        'setup.quotas.upsert@1': 'Managing program quota…',
        'setup.windows.store@1': 'Managing application window…',
        'knowledge.search@1': 'Checking approved policies & FAQs…',
        'knowledge.sources.list@1': 'Listing knowledge sources…',
        'reports.applications_census@1': 'Reading applications census report…',
        'reports.feeder_high_schools@1': 'Reading feeder high schools report…',
        'reports.daily_admissions_logbook@1': 'Reading daily admissions logbook…',
        'reports.college_program_quota_summary@1': 'Reading program quota summary…',
        'reports.score_distribution@1': 'Reading score distribution report…',
        'reports.exam_attendance@1': 'Reading exam attendance report…',
        'reports.exam_session_attendance@1': 'Reading session attendance report…',
        'reports.room_utilization@1': 'Reading room utilization report…',
        'reports.staff_accomplishment@1': 'Reading staff accomplishment report…',
        ambient_briefing: 'Reviewing workspace & queues…',
        policy_inquiry: 'Checking approved guidelines…',
        action_proposal: 'Preparing proposal…',
        query_workspace_context: 'Inspecting page context…',
        evaluate_surface_policy: 'Validating action policy…',
    };
    $effect(() => {
        if (!streaming) {
            if (timer !== null) {
                clearInterval(timer);
                timer = null;
            }
            return;
        }

        const startedAt = Date.now() - untrack(() => elapsedSeconds) * 1000;
        timer = setInterval(() => {
            elapsedSeconds = Math.max(
                0.1,
                Number(((Date.now() - startedAt) / 1000).toFixed(1)),
            );
        }, 100);

        return () => {
            if (timer !== null) {
                clearInterval(timer);
                timer = null;
            }
        };
    });

    function labelForStep(step) {
        const key = step.capability_id || step.tool || step.phase || step.event_type;
        if (key && Object.hasOwn(STEP_LABELS, key)) {
            return STEP_LABELS[key];
        }
        if (step.label && !step.label.includes('@') && !step.label.includes('Evidence')) {
            return step.label;
        }
        return 'Checking details…';
    }

    function isResultStep(step) {
        return step.event_type === 'tool.result' || step.event_type === 'skill.result';
    }

    function isSuccessfulResult(step) {
        return isResultStep(step) && (step.ok === true || step.success === true);
    }

    function isFailedResult(step) {
        return isResultStep(step) && (step.ok === false || step.success === false);
    }

    function detailForStep(step) {
        if (isFailedResult(step)) return 'Could not complete this check';
        const detail = step.detail ?? step.mode ?? '';
        if (detail === 'Recorded' || detail === 'pass' || detail === step.label) return '';
        return detail;
    }

    let currentTask = $derived(steps.length > 0 ? labelForStep(steps[steps.length - 1]) : '');
    let visibleDetailSteps = $derived(steps);
    let headerLabel = $derived(
        streaming
            ? `Thinking (${elapsedSeconds.toFixed(1)}s)`
            : elapsedSeconds > 0
              ? `Thought for ${elapsedSeconds.toFixed(1)}s`
              : steps.length > 0
                ? 'Finished'
                : 'Ready',
    );
</script>

<section class="@container border-border/70 bg-card/90 overflow-hidden rounded-xl border shadow-xs">
    <button
        type="button"
        class="hover:bg-muted/30 flex w-full min-w-0 flex-wrap items-center gap-2 px-3 py-2 text-left transition-colors @min-[240px]:flex-nowrap"
        aria-expanded={open}
        onclick={() => (open = !open)}
    >
        <span
            class="inline-flex shrink-0 items-center gap-1 rounded-full border border-indigo-500/30 bg-indigo-500/15 px-1.5 py-1"
            aria-hidden="true"
        >
            <span
                class="size-1.5 rounded-full bg-indigo-500 motion-reduce:animate-none"
                class:animate-bounce={streaming}
                style="animation-delay: 0s; animation-duration: 0.8s;"
            ></span>
            <span
                class="size-1.5 rounded-full bg-indigo-500 motion-reduce:animate-none"
                class:animate-bounce={streaming}
                style="animation-delay: 0.16s; animation-duration: 0.8s;"
            ></span>
            <span
                class="size-1.5 rounded-full bg-indigo-500 motion-reduce:animate-none"
                class:animate-bounce={streaming}
                style="animation-delay: 0.32s; animation-duration: 0.8s;"
            ></span>
        </span>
        <span class="min-w-0 flex-1">
            <span class="text-foreground block whitespace-normal break-words font-mono text-[11px] font-bold">
                {headerLabel}
            </span>
            {#if currentTask && !open}
                <span class="text-muted-foreground block truncate text-[11px]">
                    {currentTask}
                </span>
            {/if}
        </span>
        <ChevronDown
            class={`text-muted-foreground size-3.5 shrink-0 transition-transform duration-200 ${open ? 'rotate-180' : ''}`}
        />
    </button>

    {#if open && visibleDetailSteps.length > 0}
        <div class="border-border/40 bg-muted/10 space-y-1.5 border-t p-3 pt-2 font-mono text-[10px]">
            {#each visibleDetailSteps as step, index}
                <div class="text-muted-foreground flex items-start gap-2">
                    {#if isSuccessfulResult(step)}
                        <CheckCircle2 class="mt-0.5 size-3 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    {:else if isFailedResult(step)}
                        <AlertTriangle class="mt-0.5 size-3 shrink-0 text-amber-600 dark:text-amber-400" />
                    {:else}
                        <CircleDot class="mt-0.5 size-3 shrink-0 text-primary animate-pulse" />
                    {/if}
                    <span class="min-w-0">
                        <strong class="font-medium text-foreground">{labelForStep(step)}</strong>
                        {#if detailForStep(step)}
                            <span class="text-muted-foreground ml-1">({detailForStep(step)})</span>
                        {/if}
                    </span>
                </div>
            {/each}
        </div>
    {/if}
</section>
