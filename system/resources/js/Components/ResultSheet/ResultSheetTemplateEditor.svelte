<script>
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import { useForm, usePage, router } from '@inertiajs/svelte';
    import {
        FileText,
        Play,
        CheckCircle2,
        AlertTriangle,
        AlertCircle,
        ShieldAlert,
        RefreshCw,
        RotateCcw,
        ChevronLeft,
        ChevronRight,
        ChevronDown,
        Save,
        Trash2,
        Download,
    } from 'lucide-svelte';
    import { onDestroy, onMount, tick, untrack } from 'svelte';
    import { csrfToken } from '@/lib/utils';
    import { success, warning, info, error } from '@/lib/toast.js';
    import ResultSheetStudio from './ResultSheetStudio.svelte';
    import PlaceholderPicker from './PlaceholderPicker.svelte';
    let {
        mode = 'create',
        template = {},
        draft = null,
        placeholderGroups = {},
        builderCatalog = {},
        builderDefaults = {},
        builderCompilerVersion = 'result-sheet-builder-2',
        documentAnalysis = null,
        sampleContext = {},
        printPlaceholderGroups = null,
        printSampleContext = null,
        builderCapabilities = {},
        basePath = '/setup/result-templates',
        surface = null,
        studioTitle = null,
        baselineInventory = [],
        officePacks = [],
        activePack = null,
        fieldPolicy = null,
    } = $props();
    const field_policy = $derived(fieldPolicy);
    const resolvedStudioTitle = $derived(
        studioTitle ??
            (surface?.title
                ? `${surface.title} Studio`
                : basePath?.startsWith('/setup/report-templates') || basePath?.startsWith('/setup/document-templates')
                  ? 'Document Template Studio'
                  : 'Result Sheet Studio'),
    );

    const page = usePage();
    const token = $derived(page.props.csrf_token || csrfToken());
    const draftState = draft?.state ?? {};

    const isDocumentTemplateStudio = $derived(
        basePath?.startsWith('/setup/report-templates') ||
            basePath?.startsWith('/setup/document-templates'),
    );
    const form = useForm({
        name: draftState.name ?? template.name ?? '',
        mode: draftState.mode ?? template.mode ?? 'html',
        source_kind:
            draftState.source_kind ??
            template.source_kind ??
            (mode === 'create' ? 'builder' : 'legacy_html'),
        paper_size: draftState.paper_size ?? template.paper_size ?? 'a4',
        orientation:
            draftState.orientation ?? template.orientation ?? 'portrait',
        logical_unit: draftState.logical_unit ?? 'full',
        content: draftState.content ?? template.content ?? '',
        document: null,
        builder_json: draftState.builder_json ?? template.builder_json ?? null,
        builder_schema_version:
            draftState.builder_schema_version ??
            template.builder_schema_version ??
            null,
        compiled_html: template.compiled_html ?? null,
        compiler_version:
            draftState.compiler_version ??
            template.compiler_version ??
            builderCompilerVersion,
        lock_version: template.lock_version ?? 1,
        draft_id: draft?.id ?? null,
        watermark_text:
            draftState.watermark_text ?? template.watermark_text ?? '',
        is_active:
            draftState.is_active ??
            (mode === 'create' ? false : (template.is_active ?? false)),
        mounted_slots:
            draftState.mounted_slots ??
            template.mounted_slots ??
            activePack?.default_mounted_slots ??
            (isDocumentTemplateStudio ? ['analytics_reports_hub'] : []),
        custom_group: draftState.custom_group ?? template.custom_group ?? '',
        grain:
            draftState.grain ??
            template.grain ??
            fieldPolicy?.default_grain ??
            activePack?.grain ??
            (fieldPolicy?.allowed_grains?.[0] ?? 'student'),
        selected_leaves: Array.isArray(draftState.selected_leaves)
            ? draftState.selected_leaves
            : Array.isArray(template.selected_leaves)
              ? template.selected_leaves
              : Array.isArray(activePack?.default_selected_leaves)
                ? activePack.default_selected_leaves
                : [],
    });
    let editorMode = $state(
        mode === 'edit' && !draft ? 'viewing_committed' : 'working_draft',
    );
    let autosaveEnabled = $state(mode === 'create' || Boolean(draft));
    let hasUnsavedCommittedEdits = $state(false);
    const committedSnapshot = clone({
        name: template.name,
        mode: template.mode,
        paper_size: template.paper_size,
        orientation: template.orientation,
        watermark_text: template.watermark_text,
        custom_group: template.custom_group,
        grain: template.grain,
        mounted_slots: template.mounted_slots,
        selected_leaves: template.selected_leaves,
        builder_json: template.builder_json,
        content: template.content,
    });

    const masterDataBranches = [
        { id: 'institution_metadata', label: 'Institution Metadata', leaves: [['institution_name', 'Institution name'], ['campus_name', 'Campus name'], ['campus_address', 'Campus address'], ['issuing_office_name', 'Issuing office name'], ['document_title', 'Document title'], ['document_code', 'Document code'], ['revision_no', 'Revision number'], ['generated_at', 'Generated at'], ['prepared_by_name', 'Prepared by'], ['page_i', 'Page number'], ['page_all', 'Total pages']] },
        { id: 'academic_cycle_and_programs', label: 'Cycle & Programs', leaves: [['academic_cycle_name', 'Academic cycle name'], ['school_year', 'School year'], ['semester', 'Semester'], ['program_code', 'Program code'], ['program_name', 'Program name'], ['department_code', 'Department code'], ['quota_capacity', 'Quota capacity'], ['qualified_count', 'Qualified count'], ['remaining_quota_slots', 'Remaining quota slots'], ['program_quota_rows', 'Program quota rows']] },
        { id: 'applicant_profile', label: 'Applicant Profile', leaves: [['reference_code', 'Reference code'], ['examinee_number', 'Examinee number (5-digit)'], ['sequence_number', 'Sequence number (compact)'], ['applicant_name', 'Applicant name'], ['email', 'Email'], ['contact_number', 'Contact number'], ['last_school_enrolled', 'Last school — full name'], ['last_school_short', 'Last school — abbreviation'], ['strand_or_prev_course', 'Strand / previous course — full text'], ['strand_or_prev_course_short', 'Strand / previous course — abbreviation'], ['shs_strand', 'Senior high school strand'], ['gpa_or_gwa', 'GPA / GWA'], ['first_choice_program_name', 'First-choice program'], ['assigned_or_qualified_program_name', 'Assigned or qualified program'], ['application_status', 'Application status'], ['qr_code_svg', 'QR code'], ['verification_code', 'Verification code']] },
        { id: 'requirements_and_clearance', label: 'Requirements & Clearance', leaves: [['eligibility_status', 'Eligibility status'], ['clearance_reference_no', 'Clearance reference number'], ['cleared_for_program_name', 'Cleared for program'], ['cleared_at', 'Cleared at'], ['cleared_by_staff_name', 'Cleared by staff member'], ['student_requirements', 'Student requirements'], ['qualifiers_or_clearance_roster', 'Qualifiers or clearance roster']] },
        { id: 'exam_logistics', label: 'Exam Logistics', leaves: [['exam_session_code', 'Exam session code'], ['exam_session_label', 'Exam session label'], ['exam_date', 'Exam date'], ['exam_time_window', 'Exam time window'], ['building_name', 'Building name'], ['room_name', 'Room name'], ['seat_number', 'Seat number'], ['test_instrument_name', 'Test instrument name'], ['booklet_set_code', 'Booklet set code'], ['attendance_status', 'Attendance status'], ['session_examinees', 'Session examinees']] },
        { id: 'psychometric_scores', label: 'Psychometric Scores', leaves: [['total_raw_score', 'Total raw score'], ['max_possible_score', 'Maximum possible score'], ['overall_percentage_score', 'Overall percentage score'], ['overall_percentile_rank', 'Overall percentile rank'], ['descriptive_rating', 'Descriptive rating'], ['exam_outcome_verdict', 'Exam outcome verdict'], ['aptitude_subtest_scores', 'Aptitude Area scores (0–100)'], ['passing_or_score_roster', 'Passing or score roster']] },
        { id: 'guidance_consultation', label: 'Guidance Consultation', leaves: [['top_recommended_program_1', 'Top recommended program'], ['match_score_1', 'Program match score'], ['consultation_decision', 'Consultation decision'], ['counselor_name', 'Counselor name'], ['endorsed_program_name', 'Endorsed program'], ['department_interview_status', 'Department interview status'], ['consultation_or_placement_roster', 'Consultation or placement roster']] },
    ];
    const effectiveOfficeKey = $derived.by(() => {
        const policyOffice =
            field_policy?.office_key ??
            field_policy?.office ??
            activePack?.office_key ??
            activePack?.office ??
            '';
        const rawOffice = String(policyOffice).toLowerCase();
        return rawOffice.includes('admin')
            ? 'admin'
            : rawOffice.includes('guidance')
              ? 'guidance'
              : rawOffice.includes('testing')
                ? 'testing'
                : rawOffice.includes('registrar')
                  ? 'registrar'
                  : 'registrar';
    });
    const visibleMasterDataBranches = $derived.by(() => {
        const officeBranchIds = {
            admin: ['institution_metadata', 'academic_cycle_and_programs'],
            registrar: [
                'institution_metadata',
                'academic_cycle_and_programs',
                'applicant_profile',
                'requirements_and_clearance',
                'exam_logistics',
            ],
            guidance: [
                'institution_metadata',
                'academic_cycle_and_programs',
                'applicant_profile',
                'exam_logistics',
                'psychometric_scores',
                'guidance_consultation',
            ],
            testing: [
                'institution_metadata',
                'academic_cycle_and_programs',
                'applicant_profile',
                'exam_logistics',
            ],
        };
        const officeKey = effectiveOfficeKey;
        const allowedBranchIds =
            officeBranchIds[officeKey] ?? officeBranchIds.registrar;
        return masterDataBranches
            .filter((branch) => allowedBranchIds.includes(branch.id))
            .map((branch) => {
                if (branch.id === 'exam_logistics' && activePack?.office === 'registrar') {
                    const permitLeaves = new Set([
                        'exam_session_code',
                        'exam_session_label',
                        'exam_date',
                        'exam_time_window',
                        'building_name',
                        'room_name',
                        'seat_number',
                    ]);
                    return {
                        ...branch,
                        leaves: branch.leaves.filter(([leafId]) =>
                            permitLeaves.has(leafId),
                        ),
                    };
                }
                if (branch.id === 'applicant_profile' && activePack?.office === 'testing') {
                    return {
                        ...branch,
                        leaves: branch.leaves.filter(([leafId]) =>
                            ['reference_code', 'examinee_number', 'sequence_number', 'applicant_name'].includes(leafId),
                        ),
                    };
                }
                return branch;
            })
            .map((branch) => {
                const allowedLeaves = fieldPolicy?.allowed_leaves;
                if (!Array.isArray(allowedLeaves)) {
                    return branch;
                }

                return {
                    ...branch,
                    leaves: branch.leaves.filter(([leafId]) =>
                        allowedLeaves.includes(leafId),
                    ),
                };
            });
    });
    const allowedMasterDataLeaves = $derived(
        visibleMasterDataBranches.flatMap((branch) =>
            branch.leaves.map(([leafId]) => leafId),
        ),
    );
    const activeMasterDataLeaves = $derived(
        (form.selected_leaves ?? []).filter((leafId) =>
            allowedMasterDataLeaves.includes(leafId),
        ),
    );
    let expandedBranches = $state(
        Object.fromEntries(
            masterDataBranches.map((branch) => [
                branch.id,
                (form.selected_leaves ?? []).length > 0
                    ? branch.leaves.some(([leafId]) =>
                          form.selected_leaves.includes(leafId),
                      )
                    : branch.id === 'institution_metadata',
            ]),
        ),
    );
    let macroSearch = $state('');
    let macroToast = $state('');
    const filteredMasterDataBranches = $derived.by(() => {
        const query = macroSearch.trim().toLowerCase();
        if (!query) return visibleMasterDataBranches;
        return visibleMasterDataBranches
            .map((branch) => ({
                ...branch,
                leaves: branch.leaves.filter(([leafId, label]) =>
                    `${leafId} ${label}`.toLowerCase().includes(query),
                ),
            }))
            .filter((branch) => branch.leaves.length > 0);
    });

    async function writeClipboardText(value) {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(value);
            return;
        }
        const area = document.createElement('textarea');
        area.value = value;
        area.style.position = 'fixed';
        area.style.opacity = '0';
        document.body.appendChild(area);
        area.select();
        const copied = document.execCommand('copy');
        area.remove();
        if (!copied) throw new Error('Clipboard copy failed');
    }

    async function copyMacro(leafId) {
        try {
            await writeClipboardText(`{{${leafId}}}`);
            macroToast = `Copied {{${leafId}}} to clipboard`;
            success(macroToast);
        } catch {
            macroToast = `Failed to copy {{${leafId}}}`;
            error(macroToast);
        }
    }

    async function copyRepeaterRow(leafId) {
        const branch = visibleMasterDataBranches.find((item) =>
            item.leaves.some(([id]) => id === leafId),
        );
        const columns = branch?.leaves
            .filter(([id]) => id !== leafId)
            .map(([id]) => `{{${id}}}`) ?? [`{{${leafId}}}`];
        try {
            await writeClipboardText(columns.join('\t'));
            macroToast = `Copied {{${leafId}}} to clipboard`;
            success(macroToast);
        } catch {
            macroToast = `Failed to copy {{${leafId}}}`;
            error(macroToast);
        }
    }

    function setSelectedLeaves(leafIds) {
        form.selected_leaves = [...new Set(leafIds)];
        for (const branch of visibleMasterDataBranches) {
            if (branch.leaves.some(([leafId]) => form.selected_leaves.includes(leafId))) {
                expandedBranches[branch.id] = true;
            }
        }
        scheduleDraftSave();
    }

    function removeSelectedLeaf(leafId) {
        setSelectedLeaves((form.selected_leaves ?? []).filter((id) => id !== leafId));
    }

    function clearSelectedLeaves() {
        setSelectedLeaves([]);
    }

    function selectAllAllowedLeaves() {
        const policyAllowedLeaves = fieldPolicy?.allowed_leaves;
        const selectableLeaves = Array.isArray(policyAllowedLeaves)
            ? allowedMasterDataLeaves.filter((leafId) =>
                  policyAllowedLeaves.includes(leafId),
              )
            : allowedMasterDataLeaves;
        setSelectedLeaves(selectableLeaves);
    }

    function toggleSelectedLeaf(leafId, checked) {
        const selected = Array.isArray(form.selected_leaves) ? form.selected_leaves : [];
        form.selected_leaves = checked
            ? [...new Set([...selected, leafId])]
            : selected.filter((id) => id !== leafId);
        if (checked) {
            const branch = visibleMasterDataBranches.find((item) =>
                item.leaves.some(([id]) => id === leafId),
            );
            if (branch) {
                expandedBranches[branch.id] = true;
            }
        }
        scheduleDraftSave();
    }

    function applyStarterPreset(preset) {
        const presetLeaves = {
            'Clearance Slip': [
                'institution_name',
                'academic_cycle_name',
                'page_i',
                'page_all',
                'generated_at',
                'reference_code',
                'examinee_number',
                'sequence_number',
                'applicant_name',
                'program_code',
                'eligibility_status',
                'clearance_reference_no',
                'cleared_at',
                'cleared_by_staff_name',
            ],
            'Admission Permit': [
                'institution_name',
                'academic_cycle_name',
                'page_i',
                'page_all',
                'generated_at',
                'reference_code',
                'examinee_number',
                'sequence_number',
                'applicant_name',
                'exam_date',
                'exam_time_window',
                'room_name',
                'application_status',
            ],
            'Qualifiers Roster': [
                'institution_name',
                'academic_cycle_name',
                'generated_at',
                'prepared_by_name',
                'quota_capacity',
                'qualified_count',
                'remaining_quota_slots',
                'qualifiers_or_clearance_roster',
            ],
            'Passing Report': [
                'institution_name',
                'academic_cycle_name',
                'generated_at',
                'prepared_by_name',
                'qualified_count',
                'remaining_quota_slots',
                'passing_or_score_roster',
            ],
            'Executive Summary': [
                'institution_name',
                'academic_cycle_name',
                'generated_at',
                'prepared_by_name',
                'program_code',
                'quota_capacity',
                'qualified_count',
                'remaining_quota_slots',
            ],
            'Reset Neutral': [],
        };
        setSelectedLeaves(presetLeaves[preset] ?? []);
    }



    function availableMountSlotsForGrain(grain) {
        const compatibleSlots = Array.isArray(activePack?.compatible_slots)
            ? activePack.compatible_slots
            : [];
        return compatibleSlots.filter(
            (slot) => !slot.grain || slot.grain === (grain || selectedGrain),
        );
    }
    $effect(() => {
        const grain = form.grain || selectedGrain;
        const compatibleSlotKeys = availableMountSlotsForGrain(grain).map(
            (slot) => slot.slot_key,
        );
        const mountedSlots = Array.isArray(form.mounted_slots)
            ? form.mounted_slots
            : [];
        const reconciledSlots = mountedSlots.filter((slotKey) =>
            compatibleSlotKeys.includes(slotKey),
        );
        if (reconciledSlots.length !== mountedSlots.length) {
            form.mounted_slots = reconciledSlots;
            scheduleDraftSave();
        }
        selectedGrain = grain;
    });
    function toggleMountedSlot(slotKey, checked) {
        const current = Array.isArray(form.mounted_slots)
            ? form.mounted_slots
            : [];
        if (checked) {
            if (!current.includes(slotKey)) {
                form.mounted_slots = [...current, slotKey];
            }
        } else {
            form.mounted_slots = current.filter((key) => key !== slotKey);
        }
        scheduleDraftSave();
    }

    let rawDocumentFile = null;
    let analysis = $state(
        draft && draftState.mode === 'docx' ? null : (documentAnalysis ?? null),
    );
    let builderDocument = $state(
        draftState.builder_json
            ? clone(draftState.builder_json)
            : template.builder_json
              ? clone(template.builder_json)
              : mode === 'create'
                ? clone(builderDefaults)
                : null,
    );
    let previewPdfUrl = $state('');
    let previewLoading = $state(false);
    let previewError = $state(null);
    let previewDiagnostics = $state([]);
    let isPdfStale = $state(Number(draft?.stage ?? 1) === 3);
    let pdfGeneratedAt = $state(null);
    let previewSequence = 0;
    let previewController = null;
    let previewPollTimer = null;
    let previewPollController = null;
    let previewPollRunId = null;
    let previewJobIsPolling = false;
    const previewPollIntervalMs = 1200;
    const previewPollRequestTimeoutMs = 10000;
    const previewPollTimeoutMs = 120000;
    let previewTimer = null;
    let analysisLoading = $state(false);
    let analysisProgressPct = $state(0);
    let analysisStepLabel = $state('');
    const analysisProgressSteps = [
        {
            percent: 18,
            stage: 'Upload & inspect',
            label: 'Uploading Word package & inspecting archive bounds…',
        },
        {
            percent: 48,
            stage: 'Scan OOXML',
            label: 'Scanning OOXML headers, footers & media…',
        },
        {
            percent: 78,
            stage: 'Verify template',
            label: 'Verifying macros, table repeaters & {{page_i}} pagination…',
        },
        {
            percent: 94,
            stage: 'Finalize',
            label: 'Finalizing template verification…',
        },
    ];
    let previewProgressPct = $state(0);
    let previewStepLabel = $state('');
    const previewProgressSteps = [
        {
            percent: 15,
            stage: 'Queue render',
            label: 'Queueing document render job…',
        },
        {
            percent: 42,
            stage: 'Bind data',
            label: 'Binding data pack & resolving {{page_i}} of {{page_all}}…',
        },
        {
            percent: 74,
            stage: 'Render layout',
            label: 'Rendering multi-page PDF layout…',
        },
        {
            percent: 92,
            stage: 'Stream preview',
            label: 'Streaming verified PDF preview…',
        },
    ];
    $effect(() => {
        if (!analysisLoading) {
            analysisProgressPct = 0;
            analysisStepLabel = '';
            return;
        }

        let stepIndex = 0;
        analysisProgressPct = analysisProgressSteps[stepIndex].percent;
        analysisStepLabel = analysisProgressSteps[stepIndex].label;

        const timer = window.setInterval(() => {
            stepIndex += 1;
            analysisProgressPct = analysisProgressSteps[stepIndex].percent;
            analysisStepLabel = analysisProgressSteps[stepIndex].label;

            if (stepIndex === analysisProgressSteps.length - 1) {
                window.clearInterval(timer);
            }
        }, 900);

        return () => window.clearInterval(timer);
    });
    $effect(() => {
        if (!previewLoading) {
            previewProgressPct = 0;
            previewStepLabel = '';
            return;
        }

        let stepIndex = 0;
        previewProgressPct = previewProgressSteps[stepIndex].percent;
        previewStepLabel = previewProgressSteps[stepIndex].label;

        const timer = window.setInterval(() => {
            stepIndex += 1;
            previewProgressPct = previewProgressSteps[stepIndex].percent;
            previewStepLabel = previewProgressSteps[stepIndex].label;

            if (stepIndex === previewProgressSteps.length - 1) {
                window.clearInterval(timer);
            }
        }, 1200);

        return () => window.clearInterval(timer);
    });
    let validationSequence = 0;
    let validationController = null;
    const resumedWordDraft = Boolean(draft && draftState.mode === 'docx');
    const rawInitialStage = draft?.stage ?? 1;
    const initialStageId =
        typeof rawInitialStage === 'string' && rawInitialStage !== ''
            ? rawInitialStage
            : form.mode === 'docx'
              ? Number(rawInitialStage) === 1
                  ? 'copy'
                  : 'upload'
              : Number(rawInitialStage) === 2
                ? 'design'
                : Number(rawInitialStage) === 3
                  ? 'verify'
                  : 'setup';
    let currentStageId = $state(
        ['preview', 'verify'].includes(initialStageId) &&
            form.mode === 'docx' &&
            !rawDocumentFile
            ? 'upload'
            : initialStageId,
    );
    let draftId = $state(draft?.id ?? null);
    let selectedGrain = $state(
        draftState.grain ??
            template?.grain ??
            fieldPolicy?.default_grain ??
            activePack?.grain ??
            (fieldPolicy?.allowed_grains?.[0] ?? 'student'),
    );
    let draftLockVersion = $state(draft?.lock_version ?? null);
    let draftSaveTimer = null;
    let draftSaveInFlight = null;
    let draftSaveStatus = $state(draft ? 'saved' : 'idle');
    let draftSaveError = $state('');
    let lifecycleAction = $state('');
    let editorAlive = true;
    let draftRequestGeneration = 0;
    const initialEditorPath =
        typeof window === 'undefined' ? '' : window.location.pathname;
    let mobileFocusMode = $state(false);
    let studioViewport = $state({
        zoomMode: 'fit',
        zoom: 0.72,
        panX: 0,
        panY: 0,
        effectiveZoom: 0.72,
        percentage: 72,
    });
    let conflictDetected = $state(false);
    let conflictMessage = $state('');
    let pickerOpen = $state(false);
    let pickerMode = $state('copy');
    let bindingTargetId = $state(null);
    let recentKeys = $state([]);
    let selectedBaselineKey = $state('');
    const selectedBaseline = $derived.by(() => {
        const fallback = {
            key: 'ispsc_qad_f032a_rev5',
            name: 'ISPSC Internal Communication Letterhead — QAD-F032a, Rev. 5',
            download_url: '/setup/report-templates/baselines/ispsc_qad_f032a_rev5/download',
        };
        const inventory = Array.isArray(baselineInventory) ? baselineInventory : [];
        if (!inventory.length) return fallback;
        const selected = inventory.find((item) =>
            String(item.key ?? item.id ?? '') === selectedBaselineKey,
        );
        if (selected) return selected;
        return inventory.find((item) =>
            String(item.key ?? '') === 'ispsc_qad_f032a_rev5',
        ) ?? inventory[0] ?? fallback;
    });

    onMount(() => {
        const handleBeforeUnload = (event) => {
            if (draftSaveStatus !== 'pending' && draftSaveStatus !== 'saving')
                return;
            event.preventDefault();
            event.returnValue = '';
        };
        const handlePageHide = () => {
            if (
                isDocumentTemplateStudio &&
                (editorMode === 'viewing_committed' || !autosaveEnabled)
            ) {
                return;
            }
            if (
                draftSaveStatus === 'pending' &&
                lifecycleAction !== 'discard' &&
                lifecycleAction !== 'restart'
            ) {
                void persistDraft({
                    flush: true,
                    keepalive: true,
                    updateUi: false,
                });
            }
        };
        const removeBeforeVisitListener = router.on('before', (event) => {
            if (
                event.detail.visit.method === 'get' &&
                !lifecycleAction &&
                (draftSaveStatus === 'pending' || draftSaveStatus === 'saving')
            ) {
                event.preventDefault();
                warning(
                    'Wait for the draft to finish saving, or use Continue later.',
                );
            }
        });

        window.addEventListener('beforeunload', handleBeforeUnload);
        window.addEventListener('pagehide', handlePageHide);
        if (resumedWordDraft) {
            warning(
                'Draft restored. Select the Word / ODT file again before analysis or preview.',
            );
        }
        if (
            currentStage === 3 &&
            (form.mode === 'html' ||
                (template && !resumedWordDraft) ||
                rawDocumentFile)
        ) {
            isPdfStale = true;
            schedulePreview();
        }

        return () => {
            window.removeEventListener('beforeunload', handleBeforeUnload);
            removeBeforeVisitListener();
            window.removeEventListener('pagehide', handlePageHide);
        };
    });
    let conflictRef = $state(null);
    let diagnosticsRef = $state(null);
    let verifyPreviewContentNode = $state(null);
    let verifySafeBottom = $state(16);

    const stages = $derived(
        form.mode === 'docx'
          ? [
                { id: 'copy', number: 1, label: 'Copy', description: 'Placeholders' },
                { id: 'upload', number: 2, label: 'Upload', description: 'Analyze document' },
                { id: 'preview', number: 3, label: 'Preview', description: 'Verify and save' },
            ]
          : [
                { id: 'setup', number: 1, label: 'Setup', description: 'Format and page' },
                { id: 'design', number: 2, label: 'Design', description: 'Build and style' },
                { id: 'verify', number: 3, label: 'Verify', description: 'PDF and save' },
            ]);
    const currentStageIndex = $derived(
        Math.max(
            0,
            stages.findIndex(
                (s) => s.id === currentStageId || s.number === currentStageId,
            ),
        ),
    );
    const currentStage = $derived(stages[currentStageIndex]?.number ?? 1);
    const currentStageDescriptor = $derived(
        stages[currentStageIndex] ?? stages[0],
    );
    const isFirstStage = $derived(currentStageIndex === 0);
    const isLastStage = $derived(currentStageIndex === stages.length - 1);
    const previousStageDescriptor = $derived(
        !isFirstStage ? stages[currentStageIndex - 1] : null,
    );
    const nextStageDescriptor = $derived(
        !isLastStage ? stages[currentStageIndex + 1] : null,
    );

    const saveStateLabel = $derived(
        form.processing
            ? 'Saving template…'
            : draftSaveStatus === 'pending'
              ? 'Changes pending…'
              : draftSaveStatus === 'saving'
                ? 'Saving draft…'
                : draftSaveStatus === 'error'
                  ? 'Draft save failed'
                  : draftSaveStatus === 'saved'
                    ? 'Draft saved'
                    : mode === 'create'
                      ? 'Draft'
                      : 'Saved',
    );
    const selectedPlaceholderGroups = $derived.by(() => {
        if (!Array.isArray(form.selected_leaves) || form.selected_leaves.length === 0) {
            return placeholderGroups;
        }
        const selected = new Set(form.selected_leaves);
        return Object.fromEntries(
            Object.entries(placeholderGroups).map(([group, items]) => [
                group,
                (items ?? []).filter((item) => {
                    const key = String(item.placeholder ?? item.value ?? '').replace(/^\{\{|\}\}$/g, '');
                    return selected.has(key);
                }),
            ]),
        );
    });
    const usedKeys = $derived.by(() =>
        form.mode === 'docx'
            ? (analysis?.found ?? [])
            : builderDocument
              ? bindingsFrom(builderDocument)
              : bindingsFrom(form.content),
    );

    const isLegacyHtml = $derived(
        form.mode === 'html' &&
            !builderDocument &&
            Boolean(form.content?.trim()),
    );

    const hasValidName = $derived(
        Boolean(form.name && form.name.trim().length > 0),
    );

    const hasFatalHtmlIssues = $derived.by(() => {
        if (form.mode !== 'html') return false;
        if (previewDiagnostics.length > 0) return true;

        if (isLegacyHtml) {
            return !form.content?.trim();
        }

        if (!builderDocument && !form.content?.trim()) return true;
        if (
            builderDocument &&
            (!Array.isArray(builderDocument.components) ||
                builderDocument.components.length === 0)
        )
            return true;
        const clientValidation = validateBuilderState(
            builderDocument,
            form.paper_size,
            form.orientation,
        );
        if (!clientValidation.valid) return true;
        return false;
    });

    const hasFatalDocxIssues = $derived.by(() => {
        if (form.mode !== 'docx') return false;
        if (!analysis?.valid) return true;
        if (!rawDocumentFile && !(mode === 'edit' && template.id)) return true;
        return false;
    });

    const pdfIsCurrentAndValid = $derived(
        Boolean(previewPdfUrl) &&
            !previewLoading &&
            !previewError &&
            !isPdfStale &&
            previewDiagnostics.length === 0,
    );

    const isConflictActive = $derived(
        conflictDetected ||
            Boolean(
                form.errors?.template_conflict ||
                page.props.errors?.template_conflict,
            ),
    );

    const effectiveConflictMessage = $derived(
        conflictMessage ||
            form.errors?.template_conflict ||
            page.props.errors?.template_conflict ||
            'This template was modified in another session. Please reload to review current changes and avoid overwriting newer data.',
    );

    const canSubmitTemplate = $derived(
        mode === 'edit' && template?.id && form.mode === 'html'
            ? hasValidName &&
                  !hasFatalHtmlIssues &&
                  !form.processing &&
                  !isConflictActive
            : canSave,
    );

    const canSave = $derived(
        hasValidName &&
            pdfIsCurrentAndValid &&
            !hasFatalHtmlIssues &&
            !hasFatalDocxIssues &&
            !form.processing &&
            !isConflictActive,
    );

    const saveDisabledReason = $derived.by(() => {
        if (isConflictActive)
            return 'Template conflict detected. Reload the template to resolve.';
        if (!hasValidName) return 'Enter a template name before saving.';
        if (analysisLoading)
            return 'Wait for the current Word / ODT analysis to finish before saving.';
        if (hasFatalDocxIssues)
            return 'Upload a valid Word document with all required placeholders.';
        if (hasFatalHtmlIssues) {
            return isLegacyHtml
                ? 'Provide valid template HTML content.'
                : 'Add at least one printable component without layout issues to the canvas.';
        }
        if (previewError)
            return `PDF preview generation failed: ${previewError}`;
        if (isPdfStale || !previewPdfUrl)
            return 'Generate and verify a current production PDF preview before saving.';
        if (form.processing) return 'Saving changes…';
        return '';
    });

    onDestroy(() => {
        if (
            (!isDocumentTemplateStudio ||
                (editorMode !== 'viewing_committed' && autosaveEnabled)) &&
            draftSaveStatus === 'pending' &&
            lifecycleAction !== 'discard' &&
            lifecycleAction !== 'restart'
        ) {
            void persistDraft({
                flush: true,
                keepalive: true,
                updateUi: false,
            });
        }
        editorAlive = false;
        draftRequestGeneration++;
        previewSequence++;
        if (previewController) previewController.abort();
        stopPreviewPolling();
        clearScheduledPreview();
        clearScheduledDraftSave();
        if (validationController) validationController.abort();
    });
    $effect(() => {
        currentStage;
        const node = verifyPreviewContentNode;
        if (typeof window === 'undefined' || !node || currentStage !== 3) {
            verifySafeBottom = 16;
            return;
        }

        const measure = () => {
            const rect = node.getBoundingClientRect?.();
            if (!rect?.width || !rect?.height) {
                verifySafeBottom = 16;
                return;
            }

            let next = 16;
            for (const assistant of document.querySelectorAll(
                '.xiam-fab, .xiam-dock',
            )) {
                const assistantRect = assistant.getBoundingClientRect();
                const horizontalOverlap =
                    Math.min(rect.right, assistantRect.right) -
                    Math.max(rect.left, assistantRect.left);
                const verticalOverlap =
                    Math.min(rect.bottom, assistantRect.bottom) -
                    Math.max(rect.top, assistantRect.top);
                if (
                    horizontalOverlap > 0 &&
                    verticalOverlap > 0 &&
                    assistantRect.bottom >= rect.bottom - 2
                ) {
                    next = Math.max(
                        next,
                        Math.ceil(rect.bottom - assistantRect.top + 12),
                    );
                }
            }
            verifySafeBottom = next;
        };

        measure();
        const resizeObserver =
            typeof ResizeObserver === 'function'
                ? new ResizeObserver(measure)
                : null;
        resizeObserver?.observe(node);
        window.addEventListener('resize', measure);
        const mutationObserver =
            typeof MutationObserver === 'function'
                ? new MutationObserver(measure)
                : null;
        mutationObserver?.observe(document.body ?? document.documentElement, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter: ['class', 'style', 'hidden'],
        });

        return () => {
            resizeObserver?.disconnect();
            mutationObserver?.disconnect();
            window.removeEventListener('resize', measure);
        };
    });

    async function focusAlert() {
        await tick();
        if (conflictRef) {
            conflictRef.focus();
        } else if (diagnosticsRef) {
            diagnosticsRef.focus();
        }
    }

    function humanizeError(code) {
        if (!code)
            return 'Preview generation failed. Please check the template and try again.';
        switch (code) {
            case 'builder_invalid':
                return 'The template layout contains compilation issues. Review the diagnostics below and adjust the layout in Design.';
            case 'template_document_required':
                return 'A Word or ODT document is required before generating a preview.';
            case 'template_mode_mismatch':
                return 'The selected template mode does not match the document format.';
            case 'template_conflict':
                return 'This template was modified in another session. Please reload to review the latest changes.';
            case 'template_document_missing':
                return 'The document file is missing or unreadable on the server.';
            default:
                return String(code).replace(/_/g, ' ');
        }
    }

    function clone(value) {
        return value ? JSON.parse(JSON.stringify(value)) : null;
    }
    function hasNetworkEvidence(caught) {
        return /(failed to fetch|network|connection|offline|timeout)/i.test(
            String(caught?.message ?? caught ?? ''),
        );
    }

    function failureMessage(caught, fallback) {
        const message = caught?.message || (caught ? String(caught) : '');
        if (hasNetworkEvidence(caught)) {
            return `${fallback} Check your network connection and try again.`;
        }
        return message || fallback;
    }

    function draftStatePayload() {
        return {
            name: form.name ?? '',
            mode: form.mode ?? 'html',
            source_kind:
                form.mode === 'docx'
                    ? 'docx'
                    : builderDocument
                      ? 'builder'
                      : 'legacy_html',
            paper_size: form.paper_size ?? 'a4',
            orientation: form.orientation ?? 'portrait',
            logical_unit: form.logical_unit ?? 'full',
            content: form.content ?? '',
            builder_json: builderDocument ? clone(builderDocument) : null,
            builder_schema_version: form.builder_schema_version ?? null,
            compiler_version: form.compiler_version ?? null,
            watermark_text: form.watermark_text ?? '',
            is_active: Boolean(form.is_active),
            ...(isDocumentTemplateStudio
                ? {
                      mounted_slots: Array.isArray(form.mounted_slots)
                          ? [...form.mounted_slots]
                          : [],
                      custom_group: form.custom_group ?? '',
                      grain: form.grain,
                      selected_leaves: [...(form.selected_leaves ?? [])],
                  }
                : {}),
        };
    }

    function scheduleDraftSave() {
        if (isDocumentTemplateStudio) {
            if (editorMode === 'viewing_committed') {
                hasUnsavedCommittedEdits = true;
                return;
            }
            if (!autosaveEnabled) {
                draftSaveStatus = 'idle';
                return;
            }
        }
        draftSaveStatus = 'pending';
        if (draftSaveTimer) clearTimeout(draftSaveTimer);
        draftSaveTimer = setTimeout(() => {
            draftSaveTimer = null;
            void persistDraft();
        }, 300);
    }

    async function startWorkingDraft() {
        editorMode = 'working_draft';
        autosaveEnabled = true;
        hasUnsavedCommittedEdits = false;
        await persistDraft({ flush: true });
    }

    function discardCommittedChanges() {
        form.name = committedSnapshot?.name ?? '';
        form.mode = committedSnapshot?.mode ?? 'html';
        form.paper_size = committedSnapshot?.paper_size ?? 'a4';
        form.orientation = committedSnapshot?.orientation ?? 'portrait';
        form.watermark_text = committedSnapshot?.watermark_text ?? '';
        form.custom_group = committedSnapshot?.custom_group ?? '';
        form.grain = committedSnapshot?.grain ?? 'student';
        form.mounted_slots = clone(committedSnapshot?.mounted_slots) ?? [];
        form.selected_leaves = clone(committedSnapshot?.selected_leaves) ?? [];
        form.content = committedSnapshot?.content ?? '';
        builderDocument = clone(committedSnapshot?.builder_json);
        hasUnsavedCommittedEdits = false;
        editorMode = 'viewing_committed';
        autosaveEnabled = false;
    }

    function toggleAutosave() {
        autosaveEnabled = !autosaveEnabled;
        if (!autosaveEnabled) {
            clearScheduledDraftSave();
            if (draftSaveStatus === 'pending') draftSaveStatus = 'idle';
            return;
        }
        if (editorMode === 'working_draft') scheduleDraftSave();
    }

    function clearScheduledDraftSave() {
        if (!draftSaveTimer) return;
        clearTimeout(draftSaveTimer);
        draftSaveTimer = null;
    }

    function attachDraftToUrl(id) {
        if (!editorAlive || typeof window === 'undefined') return;
        const url = new URL(window.location.href);
        if (url.pathname !== initialEditorPath) return;
        url.searchParams.set('draft', String(id));
        window.history.replaceState(
            window.history.state,
            '',
            `${url.pathname}${url.search}${url.hash}`,
        );
    }

    function clearDraftFromUrl() {
        if (typeof window === 'undefined') return;
        const url = new URL(window.location.href);
        url.searchParams.delete('draft');
        window.history.replaceState(
            window.history.state,
            '',
            `${url.pathname}${url.search}${url.hash}`,
        );
    }

    async function persistDraft({
        flush = false,
        keepalive = false,
        updateUi = true,
    } = {}) {
        if (flush) clearScheduledDraftSave();

        if (draftSaveInFlight) {
            await draftSaveInFlight;
            if (!editorAlive && !keepalive) return false;
            return persistDraft({ flush: true, keepalive, updateUi });
        }
        if (updateUi) {
            draftSaveStatus = 'saving';
            draftSaveError = '';
        }
        const requestGeneration = draftRequestGeneration;
        const isNewDraft = !draftId;
        const endpoint = isNewDraft
            ? `${basePath}/drafts`
            : `${basePath}/drafts/${draftId}`;
        const body = {
            state: draftStatePayload(),
            stage: currentStageDescriptor.number,
            ...(isNewDraft
                ? { source_template_id: mode === 'edit' ? template.id : null }
                : { lock_version: draftLockVersion }),
        };

        const request = (async () => {
            let draftConflict = false;

            try {
                const response = await fetch(endpoint, {
                    method: isNewDraft ? 'POST' : 'PUT',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(body),
                    keepalive,
                });
                const result = await response.json().catch(() => ({}));
                if (
                    !editorAlive ||
                    requestGeneration !== draftRequestGeneration
                ) {
                    return response.ok;
                }
                if (!response.ok) {
                    if (
                        response.status === 409 ||
                        result.error === 'draft_conflict'
                    ) {
                        draftConflict = true;
                        conflictDetected = true;
                        conflictMessage =
                            result.message ||
                            'This draft was modified in another session. Please reload to review the latest saved state.';
                    }
                    throw new Error(
                        result.message || result.error || 'Draft save failed.',
                    );
                }

                if (!result.draft?.id) {
                    throw new Error('Draft save returned no draft identity.');
                }

                draftId = result.draft.id;
                draftLockVersion = result.draft.lock_version;
                form.draft_id = draftId;
                attachDraftToUrl(draftId);
                if (updateUi)
                    draftSaveStatus = draftSaveTimer ? 'pending' : 'saved';
                return true;
            } catch (caught) {
                if (
                    !editorAlive ||
                    requestGeneration !== draftRequestGeneration
                )
                    return false;
                if (updateUi) {
                    draftSaveStatus = 'error';
                    draftSaveError = failureMessage(
                        caught,
                        'Draft save failed. Your final template has not been changed.',
                    );
                    if (!draftConflict) {
                        error(draftSaveError);
                    }
                }
                return false;
            }
        })();

        draftSaveInFlight = request;
        try {
            return await request;
        } finally {
            if (draftSaveInFlight === request) draftSaveInFlight = null;
        }
    }

    async function deleteCurrentDraft() {
        clearScheduledDraftSave();
        if (draftSaveInFlight) await draftSaveInFlight;
        draftRequestGeneration++;
        if (!draftId) return true;

        const response = await fetch(`${basePath}/drafts/${draftId}`, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token,
            },
            credentials: 'same-origin',
        });
        if (!response.ok) {
            const result = await response.json().catch(() => ({}));
            throw new Error(
                result.message ||
                    result.error ||
                    'Draft could not be discarded.',
            );
        }
        draftId = null;
        draftLockVersion = null;
        form.draft_id = null;
        return true;
    }

    async function continueLater() {
        if (lifecycleAction) return;
        if (form.mode === 'docx' && rawDocumentFile) {
            const confirmed = window.confirm(
                'The selected Word / ODT file is not stored in drafts. Continue and select it again when you return?',
            );
            if (!confirmed) return;
        }

        lifecycleAction = 'continue';
        const saved = await persistDraft({ flush: true });
        lifecycleAction = '';
        if (saved) router.visit(surface?.surface_key === 'custom_document' ? '/setup/document-templates' : basePath);
    }

    async function discardDraft() {
        if (
            lifecycleAction ||
            !window.confirm(
                'Discard this draft and leave the editor? This cannot be undone.',
            )
        )
            return;
        lifecycleAction = 'discard';
        try {
            await deleteCurrentDraft();
            router.visit(surface?.surface_key === 'custom_document' ? '/setup/document-templates' : basePath);
        } catch (caught) {
            lifecycleAction = '';
            error(failureMessage(caught, 'Draft could not be discarded.'));
        }
    }

    async function startOver() {
        if (
            lifecycleAction ||
            !window.confirm(
                'Start over with a new blank template? This draft will be discarded.',
            )
        )
            return;
        lifecycleAction = 'restart';
        try {
            await deleteCurrentDraft();
            validationSequence++;
            validationController?.abort();
            validationController = null;
            rawDocumentFile = null;
            analysis = null;
            analysisLoading = false;
            builderDocument = clone(builderDefaults);
            form.name = '';
            form.mode = 'html';
            form.source_kind = 'builder';
            form.paper_size = builderDocument?.page?.paper_size ?? 'a4';
            form.orientation = builderDocument?.page?.orientation ?? 'portrait';
            currentStageId = 'setup';
            form.content = '';
            form.document = null;
            form.builder_json = clone(builderDocument);
            form.builder_schema_version =
                builderDocument?.schema_version ?? null;
            form.compiled_html = null;
            form.compiler_version = builderCompilerVersion;
            form.lock_version = 1;
            form.watermark_text = '';
            form.is_active = false;
            form.mounted_slots = isDocumentTemplateStudio
                ? [
                      ...(activePack?.default_mounted_slots ?? [
                          'analytics_reports_hub',
                      ]),
                  ]
                : [];
            form.custom_group = '';
            form.clearErrors();
            currentStageId = 'setup';
            conflictDetected = false;
            conflictMessage = '';
            pickerOpen = false;
            recentKeys = [];
            mobileFocusMode = false;
            draftSaveStatus = 'idle';
            draftSaveError = '';
            invalidatePreview();
            clearDraftFromUrl();
            lifecycleAction = '';
            success('Draft discarded. The editor is ready for a new template.');
        } catch (caught) {
            lifecycleAction = '';
            error(failureMessage(caught, 'The draft could not be reset.'));
        }
    }

    function bindingsFrom(value) {
        const found = new Set();
        const walk = (item) => {
            if (Array.isArray(item)) return item.forEach(walk);
            if (item && typeof item === 'object')
                return Object.values(item).forEach(walk);
            if (typeof item !== 'string') return;
            for (const match of item.matchAll(/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/g))
                found.add(match[1]);
        };
        walk(value);
        return [...found].sort();
    }

    function pageDimensions(paperSize, orientation) {
        const base =
            paperSize === 'legal'
                ? { width_mm: 216, height_mm: 356 }
                : paperSize === 'letter'
                  ? { width_mm: 216, height_mm: 279 }
                  : { width_mm: 210, height_mm: 297 };
        return orientation === 'landscape'
            ? { width_mm: base.height_mm, height_mm: base.width_mm }
            : base;
    }

    function validateBuilderState(doc, paperSize, orientation) {
        if (
            !doc ||
            !Array.isArray(doc.components) ||
            doc.components.length === 0
        ) {
            return {
                valid: false,
                message:
                    'Add at least one printable component to the canvas in Design before proceeding to Verify.',
            };
        }

        const page = doc.page || {};
        const margin = typeof page.margin_mm === 'number' ? page.margin_mm : 12;
        const dims = pageDimensions(
            paperSize || page.paper_size || 'a4',
            orientation || page.orientation || 'portrait',
        );
        const contentWidth = dims.width_mm - margin * 2;
        const contentHeight = dims.height_mm - margin * 2;

        for (const comp of doc.components) {
            if (comp.type === 'field') {
                const binding = comp.props?.binding;
                if (!binding || !String(binding).trim()) {
                    return {
                        valid: false,
                        message: `Field "${comp.props?.label || 'Bound Field'}" is missing a placeholder binding. Select the field in Design and choose a placeholder.`,
                    };
                }
            }

            const x = Number(comp.x) || 0;
            const y = Number(comp.y) || 0;
            const w = Number(comp.w) || 0;
            const h = Number(comp.h) || 0;

            if (
                doc.layout_paradigm === 'absolute' &&
                (x < 0 ||
                    y < 0 ||
                    x + w > contentWidth + 0.1 ||
                    y + h > contentHeight + 0.1)
            ) {
                return {
                    valid: false,
                    message: `Component "${comp.props?.label || comp.type || 'Item'}" extends beyond the printable page margins (${contentWidth} × ${contentHeight} mm). Adjust its position or dimensions in Design before verifying.`,
                };
            }
        }

        return { valid: true, message: null };
    }

    function clearScheduledPreview() {
        if (!previewTimer) return;
        clearTimeout(previewTimer);
        previewTimer = null;
    }
    function invalidatePreview() {
        clearScheduledPreview();
        previewSequence++;
        if (previewController) {
            previewController.abort();
            previewController = null;
        }
        stopPreviewPolling();
        previewLoading = false;
        isPdfStale = true;
        previewPdfUrl = '';
        previewError = null;
        previewDiagnostics = [];
    }
    function schedulePreview() {
        clearScheduledPreview();

        if (form.mode !== 'html') return;

        const hasHtmlSource = Boolean(builderDocument || form.content?.trim());
        if (!hasHtmlSource) {
            invalidatePreview();
            return;
        }

        previewTimer = setTimeout(() => {
            previewTimer = null;
            void runPreview();
        }, 400);
    }

    function handlePageSetting(key, value) {
        form[key] = value;
        if (!builderDocument) {
            invalidatePreview();
            schedulePreview();
            scheduleDraftSave();
            return;
        }
        const page = builderDocument.page ?? {};
        builderDocument = {
            ...builderDocument,
            page: {
                ...page,
                paper_size: form.paper_size,
                orientation: form.orientation,
                ...pageDimensions(form.paper_size, form.orientation),
            },
        };
        handleStudioChange(builderDocument);
    }

    function handleStudioChange(next) {
        builderDocument = clone(next);
        if (next.page?.paper_size) form.paper_size = next.page.paper_size;
        if (next.page?.orientation) form.orientation = next.page.orientation;
        form.source_kind = 'builder';
        form.builder_schema_version = next.schema_version ?? 1;
        form.compiler_version = builderCompilerVersion;

        invalidatePreview();
        schedulePreview();
        scheduleDraftSave();
    }

    function handleStudioSelectionChange(selection) {
        mobileFocusMode = Boolean(selection?.focused);
    }

    function handleStudioViewportChange(viewport) {
        if (!viewport) return;

        const nextViewport = {
            zoomMode: viewport.zoomMode,
            zoom: viewport.zoom,
            panX: viewport.panX,
            panY: viewport.panY,
            effectiveZoom: viewport.effectiveZoom,
            percentage: viewport.percentage,
        };
        const isSameViewport = untrack(
            () =>
                studioViewport.zoomMode === nextViewport.zoomMode &&
                studioViewport.zoom === nextViewport.zoom &&
                studioViewport.panX === nextViewport.panX &&
                studioViewport.panY === nextViewport.panY &&
                studioViewport.effectiveZoom === nextViewport.effectiveZoom &&
                studioViewport.percentage === nextViewport.percentage,
        );
        if (isSameViewport) {
            return;
        }

        studioViewport = nextViewport;
    }

    function convertLegacy() {
        const defaults = clone(builderDefaults) ?? {};
        builderDocument = {
            ...defaults,
            page: {
                ...(defaults.page ?? {}),
                paper_size: form.paper_size,
                orientation: form.orientation,
                ...pageDimensions(form.paper_size, form.orientation),
            },
        };
        form.source_kind = 'builder';
        form.builder_json = clone(builderDocument);
        invalidatePreview();
        scheduleDraftSave();
        success(
            'Converted to the structured ' +
                (surface?.title ?? 'template') +
                ' builder. Review the layout before saving.',
        );
    }
    function resolveStageDescriptor(targetStage) {
        if (!targetStage) return null;
        if (typeof targetStage === 'string') {
            return stages.find((stage) => stage.id === targetStage) ?? null;
        }
        if (typeof targetStage === 'number') {
            return stages.find((stage) => stage.number === targetStage) ?? null;
        }
        if (typeof targetStage === 'object' && targetStage.id) {
            return stages.find((stage) => stage.id === targetStage.id) ?? null;
        }
        return null;
    }

    async function goToStage(targetStage) {
        const targetDescriptor = resolveStageDescriptor(targetStage);
        if (!targetDescriptor) return;

        const currentIndex = currentStageIndex;
        const targetIndex = stages.findIndex(
            (s) => s.id === targetDescriptor.id,
        );
        if (targetIndex < 0 || targetIndex === currentIndex) return;

        // Forward navigation may only advance to the next ordered descriptor.
        if (targetIndex > currentIndex + 1) {
            warning('Complete the current stage before skipping ahead.');
            return;
        }

        if (targetIndex > 0 && !form.name?.trim()) {
            warning('Enter a template name in Setup before proceeding.');
            return;
        }

        if (targetIndex === stages.length - 1) {
            if (form.mode === 'docx') {
                if (!rawDocumentFile && !(mode === 'edit' && template.id)) {
                    warning(
                        'Upload a Word / ODT document before moving to Preview.',
                    );
                    return;
                }
                if (analysisLoading) {
                    warning(
                        'Wait for the current Word / ODT analysis to finish before moving to Preview.',
                    );
                    return;
                }
                if (!analysis?.valid) {
                    warning(
                        'Resolve the missing, unknown, or malformed placeholders before moving to Preview.',
                    );
                    return;
                }
            } else if (form.mode === 'html') {
                if (isLegacyHtml) {
                    if (!form.content?.trim()) {
                        warning('Template HTML content cannot be empty.');
                        return;
                    }
                } else if (form.source_kind === 'builder' || builderDocument) {
                    const validation = validateBuilderState(
                        builderDocument,
                        form.paper_size,
                        form.orientation,
                    );
                    if (!validation.valid) {
                        warning(validation.message);
                        return;
                    }
                } else if (!form.content?.trim()) {
                    warning('Template HTML content cannot be empty.');
                    return;
                }
            }
        }

        const previousStageId = currentStageId;
        currentStageId = targetDescriptor.id;
        if (
            isDocumentTemplateStudio &&
            (editorMode === 'viewing_committed' || !autosaveEnabled)
        ) {
            if (
                (targetDescriptor.id === 'verify' ||
                    targetDescriptor.id === 'preview') &&
                (!previewPdfUrl || isPdfStale) &&
                !previewLoading
            ) {
                void runPreview();
            }
            return;
        }
        const persisted = await persistDraft({ flush: true });
        if (!persisted) {
            currentStageId = previousStageId;
            if (isConflictActive) void focusAlert();
        } else if (
            (targetDescriptor.id === 'verify' ||
                targetDescriptor.id === 'preview') &&
            (!previewPdfUrl || isPdfStale) &&
            !previewLoading
        ) {
            void runPreview();
        }
    }

    function setTemplateMode(nextMode) {
        if (nextMode === form.mode) return;
        if (
            (nextMode === 'docx' || form.mode === 'docx') &&
            (rawDocumentFile || analysis) &&
            !window.confirm(
                'Changing format clears the selected Word / ODT file and its analysis. Continue?',
            )
        ) {
            return;
        }

        if (nextMode === 'docx' || form.mode === 'docx') {
            validationSequence++;
            validationController?.abort();
            validationController = null;
            rawDocumentFile = null;
            form.document = null;
            analysis = null;
            analysisLoading = false;
            invalidatePreview();
        }

        form.mode = nextMode;
        if (nextMode === 'docx') {
            form.source_kind = 'docx';
        } else {
            if (!builderDocument) builderDocument = clone(builderDefaults);
            form.source_kind = 'builder';
            form.builder_json = clone(builderDocument);
        }
        currentStageId = nextMode === 'docx' ? 'copy' : 'setup';
        scheduleDraftSave();
    }

    function returnToWordCopy() {
        currentStageId = 'copy';
        info(
            'Copy the missing fields below, update the external document, then return to Upload & Analyze.',
        );
        scheduleDraftSave();
    }

    function openPicker(request = {}) {
        pickerMode = request.mode ?? 'copy';
        bindingTargetId = request.targetId ?? null;
        pickerOpen = true;
    }

    function closePicker() {
        pickerOpen = false;
        bindingTargetId = null;
    }

    function remember(key) {
        if (!recentKeys.includes(key))
            recentKeys = [key, ...recentKeys].slice(0, 8);
    }

    function prototypeTableRow() {
        const tableComponent = builderDocument?.components?.find(
            (component) => component?.type === 'data_table',
        );
        const catalogColumns = builderCatalog?.data_table?.default_props?.columns;
        const columns =
            Array.isArray(tableComponent?.props?.columns) &&
            tableComponent.props.columns.length > 0
                ? tableComponent.props.columns
                : Array.isArray(catalogColumns)
                  ? catalogColumns
                  : [];
        const repeatKey =
            tableComponent?.props?.repeat_key ??
            builderCatalog?.data_table?.default_props?.repeat_key ??
            surface?.repeat_key ??
            'qualifiers';
        const enabledBindings = columns
            .filter((column) => column?.checked !== false)
            .map((column) =>
                String(
                    column?.binding ??
                        column?.key ??
                        column?.field_key ??
                        column?.descriptor_key ??
                        '',
                ),
            )
            .filter(Boolean);

        if (!enabledBindings.length) return '';

        return enabledBindings
            .map((binding) => {
                const rowKey = binding.startsWith(`${repeatKey}.`)
                    ? binding.slice(repeatKey.length + 1)
                    : binding;
                return `{{${repeatKey}.${rowKey}}}`;
            })
            .join('\t');
    }

    async function copyPrototypeTableRow() {
        const row = prototypeTableRow();
        if (!row) return;
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(row);
            } else {
                const area = document.createElement('textarea');
                area.value = row;
                area.style.position = 'fixed';
                area.style.opacity = '0';
                document.body.appendChild(area);
                area.select();
                document.execCommand('copy');
                area.remove();
            }
            success('Copied prototype Word table row');
        } catch {
            error('Failed to copy prototype table row');
        }
    }

    async function copyPlaceholder(item) {
        try {
            let copied = false;
            if (navigator.clipboard && window.isSecureContext) {
                try {
                    await navigator.clipboard.writeText(item.macro);
                    copied = true;
                } catch {
                    // The browser can deny Clipboard API access while allowing a user-initiated copy.
                }
            }
            if (!copied) {
                const area = document.createElement('textarea');
                area.value = item.macro;
                area.style.position = 'fixed';
                area.style.opacity = '0';
                document.body.appendChild(area);
                try {
                    area.select();
                    if (!document.execCommand('copy')) {
                        throw new Error('Clipboard copy was denied');
                    }
                } finally {
                    area.remove();
                }
            }
            remember(item.key);
            success(`Copied ${item.label}`);
        } catch {
            error('Failed to copy placeholder');
        }
    }

    function insertBinding(item) {
        if (!builderDocument || !bindingTargetId) {
            closePicker();
            return;
        }
        builderDocument = {
            ...builderDocument,
            components: builderDocument.components.map((component) =>
                component.id === bindingTargetId
                    ? {
                          ...component,
                          props: {
                              ...(component.props ?? {}),
                              binding: item.key,
                              label: item.label,
                          },
                      }
                    : component,
            ),
        };
        remember(item.key);
        handleStudioChange(builderDocument);
        closePicker();
    }

    async function runValidation() {
        const runId = ++validationSequence;
        validationController?.abort();
        validationController = null;

        // Every replacement analysis starts from an untrusted state. The previous
        // result must not authorize Preview or Save while this request is pending.
        analysis = null;
        analysisLoading = false;

        if (form.mode !== 'docx' || !rawDocumentFile) {
            return;
        }

        const controller = new AbortController();
        validationController = controller;
        analysisLoading = true;
        const fd = new FormData();
        fd.append('logical_unit', 'full');
        fd.append('document', rawDocumentFile);

        try {
            const res = await fetch(`${basePath}/validate-document`, {
                method: 'POST',
                body: fd,
                signal: controller.signal,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token,
                },
                credentials: 'same-origin',
            });
            const result = await res.json().catch(() => null);
            if (!res.ok)
                throw new Error(result?.error || 'Template analysis failed');
            if (runId !== validationSequence) return;
            analysis = result;
        } catch (caught) {
            if (caught?.name !== 'AbortError' && runId === validationSequence) {
                error(
                    failureMessage(caught, 'Could not analyze the document.'),
                );
            }
        } finally {
            if (runId === validationSequence) {
                analysisLoading = false;
                validationController = null;
            }
        }
    }

    async function runPreview() {
        clearScheduledPreview();
        const runId = ++previewSequence;
        if (previewController) {
            previewController.abort();
            previewController = null;
        }
        stopPreviewPolling();

        previewError = null;
        previewDiagnostics = [];
        previewPdfUrl = '';
        previewLoading = true;
        if (form.mode === 'docx' && (analysisLoading || !analysis?.valid)) {
            previewLoading = false;
            isPdfStale = true;
            previewError = analysisLoading
                ? 'Wait for the current Word / ODT analysis to finish before previewing.'
                : 'Analyze a valid Word / ODT document before previewing.';
            return;
        }

        const fd = new FormData();
        fd.append('mode', form.mode);
        fd.append('paper_size', form.paper_size);
        fd.append('orientation', form.orientation);
        fd.append('logical_unit', 'full');

        if (form.mode === 'html') {
            if (builderDocument) {
                fd.append('builder_json', JSON.stringify(builderDocument));
            } else if (form.content?.trim()) {
                fd.append('content', form.content);
            } else {
                if (runId !== previewSequence) return;
                previewLoading = false;
                previewError = 'No template content available to preview.';
                isPdfStale = true;
                return;
            }
        } else if (rawDocumentFile) {
            fd.append('document', rawDocumentFile);
        } else if (mode === 'edit' && template.id) {
            fd.append('template_id', String(template.id));
        } else {
            if (runId !== previewSequence) return;
            previewLoading = false;
            previewError = 'No document selected for preview.';
            isPdfStale = true;
            return;
        }

        const controller = new AbortController();
        previewController = controller;

        try {
            const res = await fetch(`${basePath}/preview`, {
                method: 'POST',
                body: fd,
                signal: controller.signal,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token,
                },
                credentials: 'same-origin',
            });
            const data = await res.json().catch(() => ({}));

            if (runId !== previewSequence) return;

            if (!res.ok) {
                if (
                    data.diagnostics &&
                    Array.isArray(data.diagnostics) &&
                    data.diagnostics.length > 0
                ) {
                    previewDiagnostics = data.diagnostics;
                    previewError = `Builder validation failed with ${data.diagnostics.length} diagnostic issue${data.diagnostics.length > 1 ? 's' : ''}. Correct the issues listed below.`;
                } else {
                    previewDiagnostics = [];
                    previewError = humanizeError(data.error);
                }
                isPdfStale = true;
                previewPdfUrl = '';
                if (previewDiagnostics.length > 0) void focusAlert();
                return;
            }

            if (data.pdf_base64) {
                try {
                    const bin = atob(data.pdf_base64);
                    const arr = new Uint8Array(bin.length);
                    for (let i = 0; i < bin.length; i++) {
                        arr[i] = bin.charCodeAt(i);
                    }
                    const blob = new Blob([arr], {
                        type: 'application/pdf',
                    });
                    previewPdfUrl = `${URL.createObjectURL(blob)}#toolbar=0`;
                } catch {
                    previewPdfUrl = `data:application/pdf;base64,${data.pdf_base64}#toolbar=0`;
                }
                isPdfStale = false;
                previewDiagnostics = [];
                previewError = null;
                pdfGeneratedAt = new Date();
            } else if (data.preview_job_id && data.preview_url) {
                pollPreview(data.preview_job_id, data.preview_url, runId);
                return;
            } else {
                previewError =
                    'Preview generation returned no PDF or background job. Try again.';
                isPdfStale = true;
                previewPdfUrl = '';
            }
        } catch (err) {
            if (err?.name === 'AbortError' || runId !== previewSequence) return;
            previewError = failureMessage(err, 'Preview generation failed.');
            previewDiagnostics = [];
            isPdfStale = true;
            previewPdfUrl = '';
            if (hasNetworkEvidence(err)) error(previewError);
        } finally {
            if (runId === previewSequence) {
                previewController = null;
                if (!previewJobIsPolling) previewLoading = false;
            }
        }
    }

    function stopPreviewPolling(runId = null) {
        if (runId !== null && previewPollRunId !== runId) return;
        if (previewPollTimer) clearTimeout(previewPollTimer);
        previewPollTimer = null;
        previewPollController?.abort();
        previewPollController = null;
        previewPollRunId = null;
        previewJobIsPolling = false;
    }

    function failPreviewPolling(message, runId) {
        if (previewPollRunId !== runId) return;
        stopPreviewPolling(runId);
        previewError = message;
        previewLoading = false;
        isPdfStale = true;
        previewPdfUrl = '';
    }

    function pollPreview(jobId, downloadUrl, runId) {
        stopPreviewPolling();
        if (runId !== previewSequence) return;

        const deadline = Date.now() + previewPollTimeoutMs;
        previewPollRunId = runId;
        previewJobIsPolling = true;

        const poll = async () => {
            if (runId !== previewSequence || previewPollRunId !== runId) {
                stopPreviewPolling(runId);
                return;
            }
            if (Date.now() >= deadline) {
                failPreviewPolling(
                    'Preview generation timed out. Retry to start a new preview.',
                    runId,
                );
                return;
            }

            const controller = new AbortController();
            previewPollController = controller;
            const requestTimeout = setTimeout(
                () => controller.abort(),
                Math.min(
                    previewPollRequestTimeoutMs,
                    Math.max(1, deadline - Date.now()),
                ),
            );

            try {
                const res = await fetch(`/printing/jobs/${jobId}`, {
                    signal: controller.signal,
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });
                if (runId !== previewSequence || previewPollRunId !== runId) {
                    stopPreviewPolling(runId);
                    return;
                }
                if (!res.ok) {
                    failPreviewPolling(
                        `Preview status request failed (${res.status}). Retry to start a new preview.`,
                        runId,
                    );
                    return;
                }

                const job = await res.json().catch(() => null);
                if (runId !== previewSequence || previewPollRunId !== runId) {
                    stopPreviewPolling(runId);
                    return;
                }
                if (!job || typeof job !== 'object' || !job.status) {
                    failPreviewPolling(
                        'Preview status was not recognized. Retry to start a new preview.',
                        runId,
                    );
                    return;
                }
                if (job.status === 'ready') {
                    stopPreviewPolling(runId);
                    previewPdfUrl = `${downloadUrl}#toolbar=0`;
                    previewLoading = false;
                    previewError = null;
                    previewDiagnostics = [];
                    isPdfStale = false;
                    pdfGeneratedAt = new Date();
                    return;
                }
                if (job.status === 'failed') {
                    failPreviewPolling(
                        job.safe_error ||
                            'Preview generation failed in worker.',
                        runId,
                    );
                    return;
                }
                if (
                    job.status !== 'queued' &&
                    job.status !== 'rendering' &&
                    job.status !== 'processing'
                ) {
                    failPreviewPolling(
                        'Preview status was not recognized. Retry to start a new preview.',
                        runId,
                    );
                    return;
                }

                previewPollTimer = setTimeout(poll, previewPollIntervalMs);
            } catch (caught) {
                if (runId !== previewSequence || previewPollRunId !== runId) {
                    stopPreviewPolling(runId);
                    return;
                }
                const message =
                    caught?.name === 'AbortError'
                        ? 'Preview status request timed out. Retry to start a new preview.'
                        : failureMessage(caught, 'Preview status unavailable.');
                failPreviewPolling(message, runId);
                if (hasNetworkEvidence(caught)) error(message);
            } finally {
                clearTimeout(requestTimeout);
                if (previewPollController === controller) {
                    previewPollController = null;
                }
            }
        };

        previewPollTimer = setTimeout(poll, previewPollIntervalMs);
    }

    function handleDocument(event) {
        rawDocumentFile = event?.target?.files?.[0] ?? null;
        form.document = rawDocumentFile;
        // Drop the prior analyzer result before issuing the replacement request.
        // This keeps stale missing/valid evidence from authorizing navigation.
        analysis = null;
        analysisLoading = false;
        invalidatePreview();
        scheduleDraftSave();
        void runValidation();
    }

    function bleedParent(node) {
        const slot = node.parentElement;
        const main = slot?.closest('main');
        const shell =
            slot?.parentElement && slot.parentElement !== main
                ? slot.parentElement
                : null;
        if (!main) return;

        // The authenticated shell gives this route a definite main height, but its
        // content wrapper is a block. Promote only this Result Sheet route chain to
        // a column flex context so every stage receives the remaining height.
        const savedMain = {
            padding: main.style.padding,
            overflow: main.style.overflow,
            position: main.style.position,
            display: main.style.display,
            flexDirection: main.style.flexDirection,
            minHeight: main.style.minHeight,
            minWidth: main.style.minWidth,
        };
        const savedShell = shell
            ? {
                  display: shell.style.display,
                  flex: shell.style.flex,
                  flexDirection: shell.style.flexDirection,
                  minHeight: shell.style.minHeight,
                  minWidth: shell.style.minWidth,
                  overflow: shell.style.overflow,
              }
            : null;
        const savedSlot = {
            padding: slot?.style.padding ?? '',
            overflow: slot?.style.overflow ?? '',
            display: slot?.style.display ?? '',
            flex: slot?.style.flex ?? '',
            flexDirection: slot?.style.flexDirection ?? '',
            minHeight: slot?.style.minHeight ?? '',
            minWidth: slot?.style.minWidth ?? '',
            height: slot?.style.height ?? '',
            maxHeight: slot?.style.maxHeight ?? '',
        };

        main.style.padding = '0';
        main.style.overflow = 'hidden';
        main.style.position = 'relative';
        main.style.display = 'flex';
        main.style.flexDirection = 'column';
        main.style.minHeight = '0';
        main.style.minWidth = '0';

        if (shell && savedShell) {
            shell.style.display = 'flex';
            shell.style.flex = '1 1 0%';
            shell.style.flexDirection = 'column';
            shell.style.minHeight = '0';
            shell.style.minWidth = '0';
            shell.style.overflow = 'hidden';
        }

        slot.style.padding = '0';
        slot.style.overflow = 'hidden';
        slot.style.display = 'flex';
        slot.style.flex = '1 1 0%';
        slot.style.flexDirection = 'column';
        slot.style.minHeight = '0';
        slot.style.minWidth = '0';
        slot.style.height = 'auto';
        slot.style.maxHeight = 'none';

        return {
            destroy() {
                main.style.padding = savedMain.padding;
                main.style.overflow = savedMain.overflow;
                main.style.position = savedMain.position;
                main.style.display = savedMain.display;
                main.style.flexDirection = savedMain.flexDirection;
                main.style.minHeight = savedMain.minHeight;
                main.style.minWidth = savedMain.minWidth;

                if (shell && savedShell) {
                    shell.style.display = savedShell.display;
                    shell.style.flex = savedShell.flex;
                    shell.style.flexDirection = savedShell.flexDirection;
                    shell.style.minHeight = savedShell.minHeight;
                    shell.style.minWidth = savedShell.minWidth;
                    shell.style.overflow = savedShell.overflow;
                }

                slot.style.padding = savedSlot.padding;
                slot.style.overflow = savedSlot.overflow;
                slot.style.display = savedSlot.display;
                slot.style.flex = savedSlot.flex;
                slot.style.flexDirection = savedSlot.flexDirection;
                slot.style.minHeight = savedSlot.minHeight;
                slot.style.minWidth = savedSlot.minWidth;
                slot.style.height = savedSlot.height;
                slot.style.maxHeight = savedSlot.maxHeight;
            },
        };
    }

    function handleReloadConflict() {
        router.reload({ preserveScroll: false });
    }

    function buildPayload(data) {
        return {
            ...data,
            document: rawDocumentFile,
            source_kind:
                form.mode === 'html'
                    ? builderDocument
                        ? 'builder'
                        : template.source_kind ||
                          form.source_kind ||
                          'legacy_html'
                    : 'docx',
            builder_json:
                form.mode === 'html' && builderDocument
                    ? JSON.stringify(builderDocument)
                    : null,
            builder_schema_version:
                form.mode === 'html' && builderDocument
                    ? (builderDocument.schema_version ?? 1)
                    : null,
            compiler_version:
                form.mode === 'html' && builderDocument
                    ? builderCompilerVersion
                    : null,
            content:
                form.mode === 'html' && !builderDocument
                    ? form.content
                    : data.content,
            ...(isDocumentTemplateStudio
                ? {
                      mounted_slots: Array.isArray(form.mounted_slots)
                          ? [...form.mounted_slots]
                          : [],
                      custom_group: form.custom_group,
                  }
                : {}),
            return_to: basePath.startsWith('/setup/document-templates') ? 'hub' : undefined,
        };
    }

    const saveTemplate = submit;

    async function submit() {
        conflictDetected = false;

        if (!canSubmitTemplate) {
            warning(
                saveDisabledReason ||
                    'Complete all verification requirements before saving.',
            );
            return;
        }

        if (!(await persistDraft({ flush: true }))) {
            if (isConflictActive) void focusAlert();
            return;
        }

        form.transform((data) => {
            const payload = buildPayload(data);
            if (
                basePath.startsWith('/setup/document-templates') ||
                surface?.surface_key === 'custom_document'
            ) {
                payload.return_to = 'hub';
            }
            return payload;
        });

        const options = {
            forceFormData: true,
            onError: (errors) => {
                if (errors.template_conflict || errors.lock_version) {
                    conflictDetected = true;
                    conflictMessage =
                        errors.template_conflict ||
                        'This template was modified in another session. Please reload to review the latest changes.';
                    void focusAlert();
                } else {
                    error(
                        String(
                            Object.values(errors)[0] ||
                                'Failed to save template. Check the form for details.',
                        ),
                    );
                }
            },
        };

        if (mode === 'create') {
            form.post(basePath, options);
        } else {
            form.put(`${basePath}/${template.id}`, options);
        }
    }
</script>

<div
    use:bleedParent
    class="bg-studio-shell text-studio-shell-foreground relative flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden select-none"
    aria-busy={form.processing}
>
    <header
        data-result-sheet-document-bar
        class={currentStageDescriptor.id === 'design' && form.mode === 'html'
            ? 'border-studio-border bg-studio-panel text-studio-panel-foreground hidden shrink-0 flex-col gap-3 border-b px-4 py-3 xl:flex xl:flex-row xl:items-center xl:justify-between xl:gap-5 xl:px-6'
            : 'border-studio-border bg-studio-panel text-studio-panel-foreground flex shrink-0 flex-col gap-3 border-b px-4 py-3 xl:flex-row xl:items-center xl:justify-between xl:gap-5 xl:px-6'}
    >
        <div class="flex min-w-0 flex-1 items-center gap-3">
            <div
                class="bg-studio-success text-studio-success-foreground flex size-9 shrink-0 items-center justify-center rounded-lg text-sm font-black"
                aria-hidden="true"
            >
                N
            </div>
            <div class="min-w-0">
                {#if isDocumentTemplateStudio}
                    <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                        <h1 class="min-w-0 truncate text-sm font-bold">
                            Document Templating Studio
                        </h1>
                        <span class="text-studio-panel-muted-foreground min-w-0 truncate text-xs">
                            {surface?.title ?? 'Document Template'} · {form.name?.trim() || (mode === 'create' ? 'New Template' : (template?.name || 'Untitled Variant'))}
                        </span>
                        {#if editorMode === 'viewing_committed'}
                            <span class="rounded-full border px-2 py-0.5 text-[10px] font-semibold">Viewing Committed Template</span>
                            <span class="rounded-full border px-2 py-0.5 text-[10px] font-semibold">
                                {template.is_active ? 'Active' : 'Inactive'}
                            </span>
                        {:else if editorMode === 'working_draft'}
                            <span class="rounded-full border px-2 py-0.5 text-[10px] font-semibold">Working Draft</span>
                        {/if}
                        <span
                            data-result-sheet-save-state
                            class={`rounded-full border px-2 py-0.5 text-[10px] font-semibold ${
                                draftSaveStatus === 'pending' ||
                                draftSaveStatus === 'error'
                                    ? 'border-studio-warning/70 bg-studio-warning text-studio-warning-foreground'
                                    : 'border-studio-success/70 bg-studio-success/30 text-studio-success-foreground'
                            }`}
                            aria-live="polite"
                        >
                            {saveStateLabel}
                        </span>
                    </div>
                {:else}
                    <p class="text-studio-panel-muted-foreground text-[10px] font-bold tracking-[0.16em] uppercase">
                        {resolvedStudioTitle}
                    </p>
                    <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                        <h1 class="min-w-0 truncate text-sm font-bold">
                            {form.name?.trim() || (mode === 'create' ? 'Create template' : 'Edit template')}
                        </h1>
                        <span
                            data-result-sheet-save-state
                            class={`rounded-full border px-2 py-0.5 text-[10px] font-semibold ${
                                draftSaveStatus === 'pending' ||
                                draftSaveStatus === 'error'
                                    ? 'border-studio-warning/70 bg-studio-warning text-studio-warning-foreground'
                                    : 'border-studio-success/70 bg-studio-success/30 text-studio-success-foreground'
                            }`}
                            aria-live="polite"
                        >
                            {saveStateLabel}
                        </span>
                    </div>
                {/if}
            </div>
        </div>

        <nav
            data-result-sheet-workflow
            class="border-studio-border bg-studio-panel-muted hidden min-w-0 shrink-0 items-center gap-1 overflow-x-auto rounded-lg border p-1 xl:flex"
            aria-label="Template workflow stages"
        >
            {#each stages as stage (stage.id)}
                <button
                    type="button"
                    data-result-sheet-workflow-stage={stage.id}
                    class={`flex min-h-10 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-md px-3 text-left text-xs font-semibold transition-colors motion-reduce:transition-none ${
                        stage.number === currentStage
                            ? 'bg-studio-selection text-studio-selection-foreground shadow-sm'
                            : 'text-studio-panel-muted-foreground hover:bg-studio-panel hover:text-studio-panel-foreground'
                    }`}
                    title={stage.description}
                    aria-current={stage.number === currentStage
                        ? 'step'
                        : undefined}
                    onclick={() => goToStage(stage.id)}
                >
                    <span>{stage.number}. {stage.label}</span>
                </button>
            {/each}
        </nav>

        <div
            data-result-sheet-mobile-workflow
            class="border-studio-border bg-studio-panel-muted flex min-w-0 items-center justify-between gap-2 rounded-lg border p-1 xl:hidden"
        >
            <div class="inline-flex min-h-10 items-center">
                <button
                    type="button"
                    class="text-studio-panel-muted-foreground hover:bg-studio-panel hover:text-studio-panel-foreground focus-visible:ring-studio-focus inline-flex min-h-10 min-w-10 items-center justify-center rounded-md focus-visible:ring-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-30"
                    aria-label="Previous workflow stage"
                    title="Previous workflow stage"
                    disabled={isFirstStage}
                    onclick={() => goToStage(previousStageDescriptor?.id)}
                >
                    <ChevronLeft class="size-4" aria-hidden="true" />
                </button>
            </div>
            <span
                class="text-studio-panel-foreground min-w-0 truncate text-center text-xs font-semibold"
            >
                {currentStageDescriptor?.label} · {currentStageIndex + 1} of {stages.length}
            </span>
            <div class="inline-flex items-center gap-1.5">
                {#if mode === 'edit' && currentStageId !== 'verify'}
                    <Button
                        form="result-sheet-template-form"
                        type="submit"
                        size="sm"
                        class="min-h-10 gap-1 px-2 text-[11px]"
                        disabled={!canSubmitTemplate || form.processing}
                    >
                        {form.processing ? 'Saving…' : 'Save changes'}
                    </Button>
                {/if}
                {#if !isLastStage}
                    <button
                        type="button"
                        class="bg-studio-selection text-studio-selection-foreground hover:bg-studio-selection/80 focus-visible:ring-studio-focus inline-flex min-h-10 min-w-10 items-center justify-center gap-1 rounded-md px-2 text-[11px] font-semibold focus-visible:ring-2 focus-visible:outline-none"
                        aria-label={`Continue to ${nextStageDescriptor?.label}`}
                        title={`Continue to ${nextStageDescriptor?.label}`}
                        onclick={() => goToStage(nextStageDescriptor?.id)}
                    >
                        <span>Next</span>
                        <ChevronRight class="size-4" aria-hidden="true" />
                    </button>
                {:else}
                    <Button
                        form="result-sheet-template-form"
                        type="submit"
                        size="sm"
                        class="min-h-10 gap-1"
                        disabled={!canSubmitTemplate || form.processing}
                    >
                        <span>{form.processing ? 'Saving…' : 'Save'}</span>
                        <ChevronRight class="size-4" aria-hidden="true" />
                    </Button>
                {/if}
            </div>
        </div>


        <div class="hidden shrink-0 items-center justify-end gap-3 xl:flex">
            {#if isDocumentTemplateStudio}
                <button
                    type="button"
                    class="border-studio-border bg-studio-panel-muted text-studio-panel-foreground hover:bg-studio-panel focus-visible:ring-studio-focus inline-flex min-h-10 items-center rounded-lg border px-3 text-xs font-semibold focus-visible:ring-2 focus-visible:outline-none"
                    aria-pressed={autosaveEnabled && editorMode === 'working_draft'}
                    onclick={toggleAutosave}
                >
                    Autosave: {autosaveEnabled && editorMode === 'working_draft' ? 'Enabled' : 'Paused'}
                </button>
                {#if editorMode === 'viewing_committed'}
                    <button
                        type="button"
                        class="bg-studio-selection text-studio-selection-foreground hover:bg-studio-selection/90 focus-visible:ring-studio-focus inline-flex min-h-10 items-center rounded-lg px-3 text-xs font-semibold focus-visible:ring-2 focus-visible:outline-none"
                        onclick={startWorkingDraft}
                    >
                        Save as Working Draft
                    </button>
                    {#if hasUnsavedCommittedEdits}
                        <button
                            type="button"
                            class="border-studio-border bg-studio-panel-muted text-studio-panel-foreground hover:bg-studio-panel focus-visible:ring-studio-focus inline-flex min-h-10 items-center rounded-lg border px-3 text-xs font-semibold focus-visible:ring-2 focus-visible:outline-none"
                            onclick={discardCommittedChanges}
                        >
                            Discard Changes
                        </button>
                    {/if}
                {/if}
            {/if}
            {#if mode === 'create'}
                <details
                    data-result-sheet-draft-actions
                    class="relative shrink-0"
                >
                    <summary
                        class="border-studio-border bg-studio-panel-muted text-studio-panel-foreground hover:bg-studio-panel focus-visible:ring-studio-focus inline-flex min-h-10 cursor-pointer list-none items-center gap-1.5 rounded-lg border px-3 text-xs font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none"
                        title="Draft and exit options"
                    >
                        <span>Exit</span>
                        <ChevronDown
                            class="text-studio-panel-muted-foreground size-3.5"
                            aria-hidden="true"
                        />
                    </summary>
                    <div
                        class="border-studio-border bg-studio-panel absolute right-0 z-40 mt-1.5 grid min-w-52 gap-1 rounded-xl border p-1.5 shadow-xl"
                    >
                        <button
                            type="button"
                            class="hover:bg-studio-panel-muted text-studio-panel-foreground flex items-center gap-2 rounded-lg px-3 py-2 text-left text-xs font-semibold transition-colors disabled:opacity-50"
                            disabled={Boolean(lifecycleAction)}
                            onclick={continueLater}
                        >
                            <Save
                                class="text-studio-panel-muted-foreground size-3.5"
                                aria-hidden="true"
                            />
                            <span
                                >{lifecycleAction === 'continue'
                                    ? 'Saving…'
                                    : 'Continue later'}</span
                            >
                        </button>
                            <button
                                type="button"
                                class="hover:bg-studio-panel-muted text-studio-panel-foreground flex items-center gap-2 rounded-lg px-3 py-2 text-left text-xs font-semibold transition-colors disabled:opacity-50"
                                disabled={Boolean(lifecycleAction)}
                                onclick={startOver}
                            >
                                <RotateCcw class="text-studio-panel-muted-foreground size-3.5" aria-hidden="true" />
                                <span>Start over</span>
                            </button>
                        <div class="border-studio-border my-0.5 border-t"></div>
                            <button
                                type="button"
                                class="text-destructive hover:bg-destructive/10 flex items-center gap-2 rounded-lg px-3 py-2 text-left text-xs font-semibold transition-colors disabled:opacity-50"
                                disabled={Boolean(lifecycleAction)}
                                onclick={discardDraft}
                            >
                                <Trash2 class="size-3.5" aria-hidden="true" />
                                <span>Discard draft</span>
                            </button>
                    </div>
                </details>
            {:else}
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="border-studio-border text-studio-panel-muted-foreground hover:text-studio-panel-foreground min-h-10"
                    onclick={() => router.visit(surface?.surface_key === 'custom_document' ? '/setup/document-templates' : basePath)}
                >
                    Exit
                </Button>
            {/if}

            {#if template?.download_source_url}
                <a
                    href={template.download_source_url}
                    download
                    class="border-studio-border text-studio-panel-muted-foreground hover:text-studio-panel-foreground inline-flex min-h-10 items-center gap-1.5 rounded-lg border bg-background px-3 text-xs font-semibold shadow-sm transition-colors hover:bg-muted"
                    title="Download active Word .docx template for offline editing"
                >
                    <Download class="size-3.5" aria-hidden="true" />
                    <span class="hidden sm:inline">Export .docx</span>
                </a>
            {/if}

            {#if !isLastStage}
                <div
                    class="border-studio-border bg-studio-panel inline-flex min-h-10 items-stretch rounded-lg border p-0.5 shadow-sm"
                >
                    {#if mode === 'edit'}
                        <Button
                            form="result-sheet-template-form"
                            type="submit"
                            size="sm"
                            class="bg-studio-selection text-studio-selection-foreground hover:bg-studio-selection/90 min-h-10 gap-1.5"
                            disabled={!canSubmitTemplate || form.processing}
                        >
                            <span>{form.processing ? 'Saving…' : 'Save changes'}</span>
                            <CheckCircle2 class="size-3.5" aria-hidden="true" />
                        </Button>
                    {/if}
                    <button
                        type="button"
                        class="text-studio-panel-muted-foreground hover:bg-studio-panel-muted hover:text-studio-panel-foreground focus-visible:ring-studio-focus inline-flex items-center gap-1 rounded-md px-3 text-xs font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-30"
                        aria-label="Previous workflow stage"
                        title="Previous workflow stage"
                        disabled={isFirstStage}
                        onclick={() => goToStage(previousStageDescriptor?.id)}
                    >
                        <ChevronLeft class="size-3.5" aria-hidden="true" />
                        <span>Back</span>
                    </button>
                    <div
                        class="bg-studio-border my-1 w-px"
                        aria-hidden="true"
                    ></div>
                    <button
                        type="button"
                        class="bg-studio-selection text-studio-selection-foreground hover:bg-studio-selection/90 focus-visible:ring-studio-focus inline-flex items-center gap-1 rounded-md px-3.5 text-xs font-semibold transition-colors focus-visible:ring-2 focus-visible:outline-none"
                        title={`Continue to ${nextStageDescriptor?.label}`}
                        onclick={() => goToStage(nextStageDescriptor?.id)}
                    >
                        <span>Next</span>
                        <span class="hidden xl:inline"
                            >&nbsp;to {nextStageDescriptor?.label}</span
                        >
                        <ChevronRight class="size-3.5" aria-hidden="true" />
                    </button>
                </div>
            {:else}
                <div class="inline-flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="border-studio-border min-h-10 gap-1"
                        aria-label="Previous workflow stage"
                        title="Previous workflow stage"
                        onclick={() => goToStage(previousStageDescriptor?.id)}
                    >
                        <ChevronLeft class="size-3.5" aria-hidden="true" />
                        <span>Back</span>
                    </Button>
                    <Button
                        form="result-sheet-template-form"
                        type="submit"
                        size="sm"
                        class="bg-studio-selection text-studio-selection-foreground hover:bg-studio-selection/90 min-h-10 gap-1.5"
                        disabled={!canSave || form.processing}
                    >
                        <span>{form.processing ? 'Saving…' : mode === 'create' ? 'Create template' : 'Save changes'}</span>
                        <CheckCircle2 class="size-3.5" aria-hidden="true" />
                    </Button>
                </div>
            {/if}
        </div>
    </header>

    {#if currentStageDescriptor.id === 'design' && form.mode === 'html' && !mobileFocusMode}
        <div
            data-result-sheet-mobile-design-chrome
            class="border-studio-border bg-studio-panel text-studio-panel-foreground flex min-h-12 shrink-0 items-center gap-1.5 border-b px-2 py-1.5 xl:hidden"
        >
            <button
                type="button"
                class="border-studio-border text-studio-panel-muted-foreground hover:bg-studio-panel-muted focus-visible:ring-studio-focus inline-flex min-h-11 min-w-11 shrink-0 items-center justify-center rounded-md border focus-visible:ring-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-30"
                aria-label="Previous workflow stage"
                title="Previous workflow stage"
                disabled={isFirstStage}
                onclick={() => goToStage(previousStageDescriptor?.id)}
            >
                <ChevronLeft class="size-4" aria-hidden="true" />
            </button>
            <span
                class="min-w-0 flex-1 truncate text-center text-xs font-semibold"
                data-result-sheet-mobile-design-stage
                aria-current="step"
            >
                Design · {currentStageIndex + 1} of {stages.length}
            </span>
            <span
                data-result-sheet-mobile-zoom
                class="min-w-12 shrink-0 text-center font-mono text-[11px] font-bold"
                aria-label={`Canvas zoom ${studioViewport.percentage ?? Math.round((studioViewport.effectiveZoom ?? studioViewport.zoom) * 100)} percent`}
            >
                {studioViewport.percentage ??
                    Math.round(
                        (studioViewport.effectiveZoom ?? studioViewport.zoom) *
                            100,
                    )}%
            </span>
            <button
                type="button"
                class="bg-studio-selection text-studio-selection-foreground hover:bg-studio-selection/80 focus-visible:ring-studio-focus inline-flex min-h-11 shrink-0 items-center justify-center gap-1 rounded-md px-2 text-[11px] font-semibold focus-visible:ring-2 focus-visible:outline-none"
                aria-label="Continue to Verify"
                title="Continue to Verify"
                onclick={() => goToStage(nextStageDescriptor?.id)}
            >
                <span>Verify</span>
                <ChevronRight class="size-4" aria-hidden="true" />
            </button>
            {#if mode === 'create'}
                <details
                    data-result-sheet-mobile-draft-actions
                    class="relative shrink-0"
                >
                    <summary
                        class="border-studio-border text-studio-panel-foreground focus-visible:ring-studio-focus inline-flex min-h-11 cursor-pointer list-none items-center rounded-md border px-2 text-[11px] font-semibold focus-visible:ring-2 focus-visible:outline-none"
                    >
                        Draft
                    </summary>
                    <div
                        class="border-studio-border bg-studio-panel absolute right-0 z-40 mt-1 grid min-w-44 gap-1 rounded-lg border p-1.5 shadow-xl"
                    >
                        <button
                            type="button"
                            class="hover:bg-studio-panel-muted rounded-md px-3 py-2 text-left text-xs font-semibold disabled:opacity-50"
                            disabled={Boolean(lifecycleAction)}
                            onclick={continueLater}>Continue later</button
                        >
                        <button
                            type="button"
                            class="hover:bg-studio-panel-muted rounded-md px-3 py-2 text-left text-xs font-semibold disabled:opacity-50"
                            disabled={Boolean(lifecycleAction)}
                            onclick={startOver}>Start over</button
                        >
                        <button
                            type="button"
                            class="text-destructive hover:bg-destructive/10 rounded-md px-3 py-2 text-left text-xs font-semibold disabled:opacity-50"
                            disabled={Boolean(lifecycleAction)}
                            onclick={discardDraft}>Discard draft</button
                        >
                    </div>
                </details>
            {/if}
        </div>
    {/if}

    <!-- Actionable Conflict Alert -->
    {#if isConflictActive}
        <div
            bind:this={conflictRef}
            tabindex="-1"
            role="alert"
            aria-live="assertive"
            class="border-destructive/40 bg-destructive/10 text-destructive focus:ring-studio-focus relative mx-3 mt-3 flex min-w-0 shrink-0 items-start justify-between gap-4 rounded-xl border p-4 text-xs focus:ring-2 focus:outline-none sm:mx-4 lg:mx-6"
        >
            <div class="flex min-w-0 items-start gap-3">
                <ShieldAlert class="text-destructive mt-0.5 size-5 shrink-0" />
                <div class="min-w-0 space-y-1">
                    <h3 class="text-destructive font-bold">
                        Template Version Conflict
                    </h3>
                    <p class="text-destructive/90 leading-relaxed">
                        {effectiveConflictMessage}
                    </p>
                    <p class="text-destructive/80 text-[11px]">
                        To prevent overwriting newer changes made elsewhere,
                        reload this template before making further edits.
                    </p>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="border-studio-border bg-studio-panel text-studio-panel-foreground hover:bg-studio-panel-muted h-8 gap-1.5 text-xs font-semibold"
                    onclick={handleReloadConflict}
                >
                    <RotateCcw class="size-3.5" />
                    Reload Template
                </Button>
            </div>
        </div>
    {/if}

    <form
        id="result-sheet-template-form"
        class="relative flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden"
        onsubmit={(event) => {
            event.preventDefault();
            submit();
        }}
    >
        {#if currentStageDescriptor.id === 'setup' || currentStageDescriptor.id === 'copy'}
            {#if form.mode === 'docx'}
                <div
                    data-result-sheet-stage="word-copy"
                    data-result-sheet-word-copy
                    class="grid min-h-0 flex-1 gap-3 overflow-y-auto p-3 lg:grid-cols-[minmax(20rem,24rem)_minmax(0,1fr)] lg:overflow-hidden lg:p-4"
                >
                    <aside
                        data-result-sheet-stage-card="word-copy"
                        class="border-studio-focus/70 bg-studio-panel flex min-h-0 min-w-0 flex-col gap-4 overflow-y-auto rounded-xl border p-4 shadow-sm"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="bg-studio-focus text-studio-focus-foreground flex size-8 shrink-0 items-center justify-center rounded-lg text-xs font-black"
                            >
                                W
                            </div>
                            <div class="min-w-0">
                                <p
                                    class="text-studio-panel-muted-foreground text-[10px] font-bold tracking-[0.16em] uppercase"
                                >
                                    Word / ODT workflow
                                </p>
                                <h2
                                    class="text-studio-panel-foreground text-sm font-black"
                                >
                                    Copy placeholders
                                </h2>
                                <p
                                    class="text-studio-panel-muted-foreground mt-1 text-[11px] leading-relaxed"
                                >
                                    Copy the exact macros below into your Word
                                    or LibreOffice document. Keep the braces and
                                    spelling unchanged.
                                </p>
                            </div>
                        </div>
                        <section class="border-studio-border bg-studio-panel-muted/40 space-y-2 rounded-lg border p-3">
                            <Label>Template Format</Label>
                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    onclick={() => setTemplateMode('html')}
                                    class={form.mode === 'html'
                                        ? 'border-studio-focus bg-studio-selection text-studio-selection-foreground ring-studio-focus flex min-h-10 flex-1 items-center justify-center rounded-lg border text-xs font-semibold ring-1'
                                        : 'border-studio-border bg-studio-panel text-studio-panel-muted-foreground flex min-h-10 flex-1 items-center justify-center rounded-lg border text-xs font-semibold'}
                                >HTML</button>
                                <button
                                    type="button"
                                    onclick={() => setTemplateMode('docx')}
                                    class={form.mode === 'docx'
                                        ? 'border-studio-focus bg-studio-selection text-studio-selection-foreground ring-studio-focus flex min-h-10 flex-1 items-center justify-center rounded-lg border text-xs font-semibold ring-1'
                                        : 'border-studio-border bg-studio-panel text-studio-panel-muted-foreground flex min-h-10 flex-1 items-center justify-center rounded-lg border text-xs font-semibold'}
                                >Word</button>
                            </div>
                        </section>
                        {#if isDocumentTemplateStudio}
                        <section data-result-sheet-active-pack class="border-studio-border bg-studio-panel-muted/40 space-y-2.5 rounded-lg border p-3">
                            <h3 class="text-studio-panel-foreground text-[11px] font-bold">Plugged-In Data Pack</h3>
                            <p class="text-studio-panel-foreground text-xs font-semibold">
                                {activePack?.title ?? (surface?.title ? `${surface.title} Pack` : 'Office Data Pack')}
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                <span class="rounded-full border px-2 py-1 text-[10px]">{surface?.repeat_key ?? activePack?.repeat_key ?? 'qualifiers'}</span>
                                <span class="rounded-full border px-2 py-1 text-[10px]">Institution</span>
                                <span class="rounded-full border px-2 py-1 text-[10px]">Academic Cycle</span>
                                <span class="rounded-full border px-2 py-1 text-[10px]">Pagination {'{{page_i}} of {{page_all}}'}</span>
                            </div>
                                <div
                                    data-report-template-grain-badge
                                    class="border-studio-focus/40 bg-studio-selection/40 text-studio-panel-foreground inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-[10px] font-bold"
                                >
                                    {activePack?.grain_label ??
                                        (activePack?.grain === 'student'
                                            ? 'Student Grain · 1 slip per applicant'
                                            : 'Cycle / Program Grain · Aggregate & roster report')}
                                </div>
                                <fieldset
                                    data-report-template-mount-slots
                                    class="border-studio-focus/40 bg-studio-panel space-y-2.5 rounded-lg border-2 p-3"
                                >
                                    <h3 class="text-xs font-bold text-foreground">Print Button Placements</h3>
                                    <p class="text-[11px] text-muted-foreground">
                                        Choose the pages in your Office where staff can click Print for this document.
                                    </p>
                                    <div class="space-y-2 pt-1">
                                        {#each availableMountSlotsForGrain(form.grain) as slot (slot.slot_key)}
                                            {@const pageUrl = slot.page_url ?? slot.url ?? slot.label.match(/(\/[^ )]+)/)?.[1] ?? `/${slot.slot_key}`}
                                            <label class="border-studio-border bg-studio-panel-muted/40 flex cursor-pointer items-start gap-2.5 rounded-md border p-2 text-[11px] leading-snug">
                                                <input type="checkbox" class="border-studio-border text-studio-focus focus:ring-studio-focus mt-0.5 size-4 shrink-0 rounded" value={slot.slot_key} checked={Array.isArray(form.mounted_slots) && form.mounted_slots.includes(slot.slot_key)} onchange={(event) => toggleMountedSlot(slot.slot_key, event.currentTarget.checked)} />
                                                <span class="min-w-0 flex-1">
                                                    <span class="block font-bold text-studio-panel-foreground">{slot.label.split(' (')[0]}</span>
                                                    <span class="mt-1 inline-flex max-w-full rounded bg-studio-selection/60 px-1.5 py-0.5 font-mono text-[10px] text-studio-panel-muted-foreground">{pageUrl}</span>
                                                </span>
                                            </label>
                                        {/each}
                                    </div>
                                    {#if availableMountSlotsForGrain(form.grain).length === 0}
                                        <p class="text-[11px] leading-relaxed text-muted-foreground">
                                            Hub-only for this print mode (staff can run and print this document directly from the Document &amp; Template Hub).
                                        </p>
                                    {/if}
                                    <p class="text-studio-panel-muted-foreground text-[10px] leading-relaxed">
                                        Optional. Even with no locations checked, this template can always be run from the Document &amp; Template Hub.
                                    </p>
                                </fieldset>
                                <div
                                    data-report-template-custom-group
                                    class="border-studio-border/80 space-y-1 border-t pt-2.5"
                                >
                                    <Label
                                        for="custom_group_docx"
                                        class="text-[11px] font-bold"
                                    >
                                        Custom Hub Group / Folder (Optional)
                                    </Label>
                                    <Input
                                        id="custom_group_docx"
                                        bind:value={form.custom_group}
                                        oninput={scheduleDraftSave}
                                        class="h-8 min-h-8 text-xs"
                                        maxlength="120"
                                        placeholder="e.g. AACCUP Accreditation, Daily Counter Slips"
                                    />
                                </div>
                        </section>

                        <section class="border-studio-border bg-studio-panel-muted/40 space-y-2 rounded-lg border p-3">
                            <div>
                                <h3 class="text-studio-panel-foreground text-[11px] font-bold">Institutional Baseline Template</h3>
                                <p class="text-studio-panel-muted-foreground mt-0.5 text-[10px] leading-relaxed">
                                    Start with an institutional Word shell, then add the fields required by this document.
                                </p>
                            </div>
                            {#if Array.isArray(baselineInventory) && baselineInventory.length}
                                <label class="text-studio-panel-muted-foreground block text-[10px]">
                                    Baseline shell
                                    <select
                                        class="border-studio-border bg-studio-panel text-studio-panel-foreground mt-1 w-full rounded border px-2 py-1.5 text-xs"
                                        value={selectedBaselineKey || String(selectedBaseline.key ?? selectedBaseline.id ?? '')}
                                        onchange={(event) => (selectedBaselineKey = event.currentTarget.value)}
                                    >
                                        {#each baselineInventory as baseline (baseline.key ?? baseline.id)}
                                            <option value={String(baseline.key ?? baseline.id ?? '')}>
                                                {baseline.title ?? baseline.name ?? baseline.label ?? 'Institutional baseline'}
                                            </option>
                                        {/each}
                                    </select>
                                </label>
                            {:else}
                                <p class="text-studio-panel-foreground rounded-md bg-studio-panel px-2.5 py-2 text-[10px] font-semibold">
                                    ISPSC Internal Communication Letterhead — QAD-F032a, Rev. 5
                                </p>
                            {/if}
                            <a
                                class="border-studio-focus bg-studio-selection text-studio-selection-foreground hover:bg-studio-selection/80 inline-flex min-h-9 w-full items-center justify-center rounded-md border px-3 py-2 text-[10px] font-bold"
                                href={selectedBaseline.download_url ?? '/setup/report-templates/baselines/ispsc_qad_f032a_rev5/download'}
                                download
                            >
                                Download Word Shell (.docx)
                            </a>
                        </section>
                        {/if}

                        {#if prototypeTableRow()}
                            <button
                                type="button"
                                class="border-studio-border bg-studio-panel text-studio-panel-foreground hover:bg-studio-panel-muted w-full rounded-lg border px-3 py-2 text-left text-[10px] font-semibold"
                                onclick={copyPrototypeTableRow}
                            >
                                Copy Word Prototype Table Row
                                <span class="text-studio-panel-muted-foreground mt-1 block truncate font-normal">
                                    {prototypeTableRow()}
                                </span>
                            </button>
                        {/if}

                        <div
                            class="border-studio-focus/40 bg-studio-selection/30 text-studio-panel-foreground space-y-2 rounded-xl border p-3 text-[11px] leading-relaxed"
                        >
                            <p class="font-bold">Required order</p>
                            <ol class="list-decimal space-y-1 pl-4">
                                <li>
                                    Copy every required field used by your{' '}
                                    {surface?.title
                                        ? surface.title.toLowerCase()
                                        : 'template'}
                                    .
                                </li>
                                <li>
                                    Save the external document as .docx or .odt.
                                </li>
                                <li>
                                    Continue to Upload, choose that file, and
                                    wait for analysis.
                                </li>
                            </ol>
                        </div>

                        {#if analysis?.missing?.length}
                            <div
                                data-result-sheet-word-missing-callout
                                role="alert"
                                class="border-studio-warning bg-studio-warning text-studio-warning-foreground space-y-2 rounded-xl border p-3 text-[11px] leading-relaxed"
                            >
                                <p class="font-black">
                                    Analysis found {analysis.missing.length} required
                                    field{analysis.missing.length === 1
                                        ? ''
                                        : 's'} missing.
                                </p>
                                <p>
                                    These fields are highlighted in the picker.
                                    Copy them into the corrected document,
                                    upload it again, and analyze before
                                    continuing.
                                </p>
                                <button
                                    type="button"
                                    class="border-studio-warning-foreground/40 bg-studio-panel text-studio-panel-foreground hover:bg-studio-panel-muted focus-visible:ring-studio-focus w-full rounded-lg border px-2.5 py-2 text-[11px] font-bold focus-visible:ring-2 focus-visible:outline-none"
                                    onclick={() => goToStage('upload')}
                                >
                                    Return to Upload &amp; Analyze
                                </button>
                            </div>
                        {/if}

                        <div
                            class="border-studio-border bg-studio-panel-muted text-studio-panel-muted-foreground rounded-xl border border-dashed p-3 text-[11px] leading-relaxed"
                        >
                            The Copy stage is the source of truth for field
                            names. Do not type macros by hand when you can copy
                            them from the categorized list.
                        </div>
                    </aside>

                    <section
                        data-result-sheet-stage-main="word-copy"
                        class="border-studio-border bg-studio-canvas/40 text-studio-canvas-foreground min-h-0 min-w-0 overflow-hidden rounded-xl border"
                    >
                        {#if isDocumentTemplateStudio}
                            <div class="flex h-full min-h-0 flex-col gap-4 overflow-y-auto p-6 lg:p-8">
                                <header class="space-y-1">
                                    <h2 class="text-xl font-bold">Word / ODT Macro Reference</h2>
                                    <p class="text-sm text-muted-foreground">Click any macro to copy it to your Word template.</p>
                                </header>
                                <Input bind:value={macroSearch} placeholder="Search macros across all branches…" aria-label="Search macros" />
                                {#if macroToast}
                                    <p role="status" aria-live="polite" class="text-xs text-muted-foreground">{macroToast}</p>
                                {/if}
                                <div class="space-y-3">
                                    {#each filteredMasterDataBranches as branch (branch.id)}
                                        <section class="rounded-lg border bg-card p-4">
                                            <button type="button" class="flex w-full items-center justify-between text-left font-semibold" aria-expanded={Boolean(expandedBranches[branch.id])} onclick={() => (expandedBranches[branch.id] = !expandedBranches[branch.id])}>
                                                <span>{branch.label}</span>
                                                <ChevronDown class={`size-4 transition-transform ${expandedBranches[branch.id] ? '' : '-rotate-90'}`} />
                                            </button>
                                            {#if expandedBranches[branch.id]}
                                                <div class="mt-3 space-y-2">
                                                    {#each branch.leaves as [leafId, label] (leafId)}
                                                        {#if ['program_quota_rows', 'student_requirements', 'qualifiers_or_clearance_roster', 'session_examinees', 'passing_or_score_roster', 'consultation_or_placement_roster'].includes(leafId)}
                                                            <div class="space-y-1.5 rounded border bg-card/60 p-2.5">
                                                                <div class="flex items-center justify-between">
                                                                    <span class="text-xs font-bold">Table Repeater: {label}</span>
                                                                    <Button size="sm" variant="default" class="h-7 text-xs" onclick={() => copyRepeaterRow(leafId)}>Copy Table Row</Button>
                                                                </div>
                                                                <p class="text-[11px] text-muted-foreground">Inserts table row cells for all columns in this repeater.</p>
                                                            </div>
                                                        {:else}
                                                            <div class="flex items-center justify-between rounded border bg-card p-2">
                                                                <span><code class="font-mono text-xs">{`{{${leafId}}}`}</code> <span class="ml-2 text-xs text-muted-foreground">{label}</span></span>
                                                                <Button size="sm" variant="outline" class="h-7 text-xs" onclick={() => copyMacro(leafId)}>Copy</Button>
                                                            </div>
                                                        {/if}
                                                    {/each}
                                                </div>
                                            {/if}
                                        </section>
                                    {/each}
                                    {#if macroSearch.trim() && filteredMasterDataBranches.length === 0}
                                        <p class="text-sm text-muted-foreground">No macros match this search.</p>
                                    {/if}
                                </div>
                            </div>
                        {:else}
                            <PlaceholderPicker
                                open={true}
                                embedded
                                {placeholderGroups}
                                {usedKeys}
                                {recentKeys}
                                missingKeys={analysis?.missing ?? []}
                                mode="copy"
                                onclose={closePicker}
                                oncopy={copyPlaceholder}
                                oninsert={insertBinding}
                            />
                        {/if}
                    </section>
                </div>
            {:else}
                <div
                    data-result-sheet-stage="setup"
                    class="grid min-h-0 flex-1 gap-3 overflow-y-auto p-3 lg:grid-cols-[minmax(20rem,26rem)_minmax(0,1fr)] lg:overflow-hidden lg:p-4"
                >
                    <aside
                        data-result-sheet-stage-card="setup"
                        class="border-studio-border bg-studio-panel flex min-h-0 min-w-0 flex-col overflow-hidden rounded-xl border shadow-sm"
                    >
                        <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
                            <div class="space-y-1.5">
                                <Label for="template-name-input">Template Name</Label>
                                <Input
                                    id="template-name-input"
                                    data-testid="template-name-input"
                                    bind:value={form.name}
                                    oninput={scheduleDraftSave}
                                    class="min-h-10 font-medium"
                                    placeholder={surface?.surface_key === 'custom_document' ? 'e.g. Guidance Referral Slip / Custom Office Notice' : 'e.g. Official ICAT Results 2026'}
                                    required
                                />
                            </div>
                            {#if isDocumentTemplateStudio}
                                <div data-report-template-custom-group class="space-y-1.5">
                                    <Label for="custom_group_setup" class="text-[11px] font-bold">
                                        Custom Hub Group / Folder (Optional)
                                    </Label>
                                    <Input
                                        id="custom_group_setup"
                                        bind:value={form.custom_group}
                                        oninput={scheduleDraftSave}
                                        class="h-8 min-h-8 text-xs"
                                        maxlength="120"
                                        placeholder="e.g. AACCUP Accreditation, Daily Counter Slips"
                                    />
                                </div>
                            {/if}
                            <div class="space-y-1.5">
                                <Label>Template Format</Label>
                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        onclick={() => setTemplateMode('html')}
                                        class={form.mode === 'html'
                                            ? 'border-studio-focus bg-studio-selection text-studio-selection-foreground ring-studio-focus flex min-h-10 flex-1 items-center justify-center gap-2 rounded-lg border text-xs font-semibold ring-1'
                                            : 'border-studio-border bg-studio-panel-muted text-studio-panel-muted-foreground hover:bg-studio-panel flex min-h-10 flex-1 items-center justify-center gap-2 rounded-lg border text-xs font-semibold'}
                                    >
                                        <FileText class="size-4" /> HTML
                                    </button>
                                    <button
                                        type="button"
                                        onclick={() => setTemplateMode('docx')}
                                        class={form.mode === 'docx'
                                            ? 'border-studio-focus bg-studio-selection text-studio-selection-foreground ring-studio-focus flex min-h-10 flex-1 items-center justify-center gap-2 rounded-lg border text-xs font-semibold ring-1'
                                            : 'border-studio-border bg-studio-panel-muted text-studio-panel-muted-foreground hover:bg-studio-panel flex min-h-10 flex-1 items-center justify-center gap-2 rounded-lg border text-xs font-semibold'}
                                    >
                                        <FileText class="size-4" /> Word
                                    </button>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="space-y-1.5">
                                    <Label for="paper_size">Paper Size</Label>
                                    <select
                                        id="paper_size"
                                        class="border-studio-border bg-studio-panel text-studio-panel-foreground min-h-10 w-full rounded-md border px-3 text-sm"
                                        value={form.paper_size}
                                        onchange={(event) => handlePageSetting('paper_size', event.currentTarget.value)}
                                    >
                                        <option value="a4">A4 (210 × 297 mm)</option>
                                        <option value="legal">Legal (216 × 356 mm)</option>
                                        <option value="letter">Letter (216 × 279 mm)</option>
                                    </select>
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="orientation">Orientation</Label>
                                    <select
                                        id="orientation"
                                        class="border-studio-border bg-studio-panel text-studio-panel-foreground min-h-10 w-full rounded-md border px-3 text-sm"
                                        value={form.orientation}
                                        onchange={(event) => handlePageSetting('orientation', event.currentTarget.value)}
                                    >
                                        <option value="portrait">Portrait</option>
                                        <option value="landscape">Landscape</option>
                                    </select>
                                </div>
                            </div>
                            <div class="space-y-1.5">
                                <Label for="watermark_text">Watermark</Label>
                                <Input
                                    id="watermark_text"
                                    bind:value={form.watermark_text}
                                    oninput={scheduleDraftSave}
                                    class="min-h-10 font-mono text-xs"
                                    maxlength="50"
                                    placeholder="Optional"
                                />
                            </div>
                            {#if isDocumentTemplateStudio}
                                <fieldset data-report-template-mount-slots class="border-studio-focus/40 bg-studio-panel space-y-2 rounded-lg border p-3">
                                    <legend class="px-1 text-xs font-bold text-foreground">Print Button Placements</legend>
                                    <p class="text-[11px] font-semibold text-foreground">Print on pages</p>
                                    <div class="space-y-2">
                                        {#each availableMountSlotsForGrain(form.grain) as slot (slot.slot_key)}
                                            {@const pageUrl = slot.page_url ?? slot.url ?? slot.label.match(/(\/[^ )]+)/)?.[1] ?? `/${slot.slot_key}`}
                                            <label class="border-studio-border bg-studio-panel-muted/40 flex cursor-pointer items-start gap-2.5 rounded-md border p-2 text-[11px] leading-snug">
                                                <input type="checkbox" class="border-studio-border text-studio-focus focus:ring-studio-focus mt-0.5 size-4 shrink-0 rounded" value={slot.slot_key} checked={Array.isArray(form.mounted_slots) && form.mounted_slots.includes(slot.slot_key)} onchange={(event) => toggleMountedSlot(slot.slot_key, event.currentTarget.checked)} />
                                                <span class="min-w-0 flex-1">
                                                    <span class="block font-bold text-studio-panel-foreground">{slot.label.split(' (')[0]}</span>
                                                    <span class="mt-1 inline-flex max-w-full rounded bg-studio-selection/60 px-1.5 py-0.5 font-mono text-[10px] text-studio-panel-muted-foreground">{pageUrl}</span>
                                                </span>
                                            </label>
                                        {/each}
                                    </div>
                                    {#if availableMountSlotsForGrain(form.grain).length === 0}
                                        <p class="text-[11px] leading-relaxed text-muted-foreground">
                                            Hub-only (staff can print this document from the Document &amp; Template Hub).
                                        </p>
                                    {/if}
                                </fieldset>
                            {/if}
                        </div>
                    </aside>
                    <section
                        data-result-sheet-stage-main="setup"
                        data-result-sheet-document-summary
                        class="border-studio-border bg-studio-canvas/60 text-studio-canvas-foreground flex min-h-0 min-w-0 flex-col justify-start overflow-y-auto rounded-xl border p-5 lg:p-8"
                    >
                        {#if isDocumentTemplateStudio}
                            <div class="mx-auto w-full max-w-5xl space-y-5">
                                {#if form.mode === 'docx'}
                                    <div class="space-y-1.5">
                                        <Label for="template-name-input-docx">Template Name</Label>
                                        <Input
                                            id="template-name-input-docx"
                                            data-testid="template-name-input-docx"
                                            bind:value={form.name}
                                            oninput={scheduleDraftSave}
                                            class="min-h-10 font-medium"
                                            placeholder="Name this Word template"
                                            required
                                        />
                                    </div>
                                {/if}
                                <section class="space-y-2">
                                    <h2 class="text-studio-panel-foreground text-sm font-bold">Print Mode</h2>
                                    <div class="grid gap-2 md:grid-cols-3">
                                        {#each [
                                            { id: 'student', label: 'Individual Slip', explanation: 'One per applicant' },
                                            { id: 'session', label: 'Session Roster', explanation: 'One per session' },
                                            { id: 'cycle_program', label: 'Cycle / Program Summary', explanation: 'Aggregate report' },
                                        ].filter((printMode) => (fieldPolicy?.allowed_grains ?? ['student', 'session', 'cycle_program']).includes(printMode.id)) as printMode (printMode.id)}
                                            <button type="button" aria-pressed={form.grain === printMode.id} class={`rounded-lg border p-3 text-left ${form.grain === printMode.id ? 'border-studio-focus bg-studio-selection text-studio-selection-foreground' : 'border-studio-border bg-studio-panel text-studio-panel-foreground'}`} onclick={() => {
                                                if (!(fieldPolicy?.allowed_grains ?? ['student', 'session', 'cycle_program']).includes(printMode.id)) return;
                                                form.grain = printMode.id;
                                                const compatibleSlotKeys = availableMountSlotsForGrain(printMode.id).map((slot) => slot.slot_key);
                                                form.mounted_slots = (form.mounted_slots ?? []).filter((slotKey) => compatibleSlotKeys.includes(slotKey));
                                                scheduleDraftSave();
                                            }}>
                                                <span class="block text-xs font-bold">{printMode.label}</span>
                                                <span class="mt-1 block text-[10px] font-normal leading-relaxed opacity-80">{printMode.explanation}</span>
                                            </button>
                                        {/each}
                                    </div>
                                    </section>
                                <section class="space-y-3">
                                    <h2 class="text-studio-panel-foreground text-sm font-bold">Data Fields</h2>
                                    <div class="flex flex-wrap gap-2">
                                        {#each (effectiveOfficeKey === 'registrar'
                                            ? ['Clearance Slip', 'Admission Permit', 'Qualifiers Roster']
                                            : effectiveOfficeKey === 'guidance'
                                              ? ['Passing Report', 'Reset Neutral']
                                              : effectiveOfficeKey === 'admin'
                                                ? ['Executive Summary', 'Reset Neutral']
                                                : ['Reset Neutral']) as preset}
                                            <button type="button" class="border-studio-border bg-studio-panel text-studio-panel-foreground rounded-full border px-3 py-1.5 text-xs font-semibold" onclick={() => applyStarterPreset(preset)}>{preset}</button>
                                        {/each}
                                    </div>
                                    <section class="border-studio-focus/40 bg-studio-selection/20 space-y-2 rounded-lg border p-3">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <h3 class="text-xs font-bold text-studio-panel-foreground">Active in this template ({activeMasterDataLeaves.length} fields chosen)</h3>
                                            <div class="flex gap-2">
                                                <button type="button" class="text-studio-focus text-[10px] font-bold underline underline-offset-2" onclick={clearSelectedLeaves}>Clear All</button>
                                                <button type="button" class="text-studio-focus text-[10px] font-bold underline underline-offset-2" onclick={selectAllAllowedLeaves}>Select All Allowed</button>
                                            </div>
                                        </div>
                                        {#if activeMasterDataLeaves.length > 0}
                                            <div class="flex max-h-24 flex-wrap gap-1.5 overflow-y-auto" aria-label="Active parameter fields">
                                                {#each activeMasterDataLeaves as leafId (leafId)}
                                                    <span class="border-studio-border bg-studio-panel inline-flex items-center gap-1 rounded-full border px-2 py-1 font-mono text-[10px]">
                                                        {leafId}
                                                        <button type="button" class="text-studio-panel-muted-foreground hover:text-studio-panel-foreground text-xs font-bold leading-none" aria-label={`Remove ${leafId}`} onclick={() => removeSelectedLeaf(leafId)}>×</button>
                                                    </span>
                                                {/each}
                                            </div>
                                        {:else}
                                            <p class="text-studio-panel-muted-foreground text-[10px]">No parameters selected yet.</p>
                                        {/if}
                                    </section>
                                    <div class="max-h-[55vh] space-y-2 overflow-y-auto">
                                        {#each visibleMasterDataBranches as branch (branch.id)}
                                            {@const registrarScoresDisabled = false}
                                            {@const activeCount = branch.leaves.filter(([leafId]) => activeMasterDataLeaves.includes(leafId)).length}
                                            <section class="border-studio-border bg-studio-panel rounded-lg border p-3">
                                                <button type="button" class="flex w-full items-center justify-between gap-3 text-left text-xs font-bold" aria-expanded={Boolean(expandedBranches[branch.id])} onclick={() => (expandedBranches[branch.id] = !expandedBranches[branch.id])}>
                                                    <span>{branch.label} {registrarScoresDisabled ? '· Registrar restricted' : ''}</span>
                                                    <span class="flex shrink-0 items-center gap-2">
                                                        <span class={`rounded-full px-2 py-1 text-[10px] font-semibold ${activeCount > 0 ? 'bg-studio-selection text-studio-selection-foreground' : 'bg-studio-panel-muted text-studio-panel-muted-foreground'}`}>{activeCount} / {branch.leaves.length} active{activeCount > 0 ? ' ✓' : ''}</span>
                                                        <span>{expandedBranches[branch.id] ? '−' : '+'}</span>
                                                    </span>
                                                </button>
                                                {#if expandedBranches[branch.id]}
                                                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                                        {#each branch.leaves as [leafId, label] (leafId)}
                                                            <label class={`flex items-center gap-2 text-xs ${registrarScoresDisabled ? 'opacity-50' : ''}`}><input type="checkbox" disabled={registrarScoresDisabled} checked={(form.selected_leaves ?? []).includes(leafId)} onchange={(event) => toggleSelectedLeaf(leafId, event.currentTarget.checked)} /><span>{label}</span></label>
                                                        {/each}
                                                    </div>
                                                {/if}
                                            </section>
                                        {/each}
                                    </div>
                                </section>
                            </div>
                        {:else}
                            <div>
                                <p
                                    class="text-studio-panel-muted-foreground text-[10px] font-bold tracking-[0.16em] uppercase"
                                >
                                    Document summary
                                </p>
                                <h2
                                    class="text-studio-panel-foreground mt-1 text-lg font-bold"
                                >
                                    Ready for the next stage
                                </h2>
                                <p
                                    class="text-studio-panel-muted-foreground mt-1 max-w-prose text-xs leading-relaxed"
                                >
                                    Confirm the document context here before
                                    opening the stage-specific workspace.
                                </p>
                            </div>
                            <dl
                                class="border-studio-border bg-studio-panel grid gap-2 rounded-xl border p-4 text-xs sm:grid-cols-2"
                            >
                                <div>
                                    <dt
                                        class="text-studio-panel-muted-foreground"
                                    >
                                        Template
                                    </dt>
                                    <dd class="mt-1 truncate font-semibold">
                                        {form.name?.trim() || 'Untitled draft'}
                                    </dd>
                                </div>
                                <div>
                                    <dt
                                        class="text-studio-panel-muted-foreground"
                                    >
                                        Format
                                    </dt>
                                    <dd class="mt-1 font-semibold">
                                        {form.mode === 'html'
                                            ? 'HTML Studio'
                                            : 'Word / ODT'}
                                    </dd>
                                </div>
                                <div>
                                    <dt
                                        class="text-studio-panel-muted-foreground"
                                    >
                                        Page
                                    </dt>
                                    <dd class="mt-1 font-semibold">
                                        {form.paper_size} · {form.orientation}
                                    </dd>
                                </div>
                                <div>
                                    <dt
                                        class="text-studio-panel-muted-foreground"
                                    >
                                        Placed components
                                    </dt>
                                    <dd class="mt-1 font-semibold">
                                        {builderDocument?.components?.length ??
                                            0}
                                    </dd>
                                </div>
                            </dl>
                            {#if isDocumentTemplateStudio}
                            <section data-result-sheet-active-pack class="border-studio-border bg-studio-panel space-y-2 rounded-xl border p-4">
                                <h3 class="text-studio-panel-foreground text-[11px] font-bold">Plugged-In Data Pack</h3>
                                <p class="text-studio-panel-foreground text-sm font-semibold">
                                    {activePack?.title ?? (surface?.title ? `${surface.title} Pack` : 'Office Data Pack')}
                                </p>
                                <div class="flex flex-wrap gap-1.5">
                                    <span class="rounded-full border px-2 py-1 text-[10px]">{surface?.repeat_key ?? activePack?.repeat_key ?? 'qualifiers'}</span>
                                    <span class="rounded-full border px-2 py-1 text-[10px]">Institution</span>
                                    <span class="rounded-full border px-2 py-1 text-[10px]">Academic Cycle</span>
                                    <span class="rounded-full border px-2 py-1 text-[10px]">Pagination {'{{page_i}} of {{page_all}}'}</span>
                                </div>
                            </section>
                            {/if}
                            {#if form.mode === 'docx'}
                                <p
                                    class="border-studio-focus/40 bg-studio-selection/30 text-studio-panel-foreground rounded-lg border p-3 text-[11px] leading-relaxed"
                                >
                                    Copy required fields from the Copy stage
                                    before uploading the completed Word or ODT
                                    document.
                                </p>
                            {:else}
                                <p
                                    class="border-studio-border bg-studio-panel-muted text-studio-panel-muted-foreground rounded-lg border border-dashed p-3 text-[11px] leading-relaxed"
                                >
                                    Design opens the canvas and its Add,
                                    Inspector, and Layers tools. Verify is
                                    reserved for production PDF review and save.
                                </p>
                            {/if}
                        {/if}
                    </section>
                </div>
            {/if}
        {:else if currentStageDescriptor.id === 'design' || currentStageDescriptor.id === 'upload'}
            {#if form.mode === 'docx'}
                <div
                    data-result-sheet-stage="word-upload"
                    class="grid min-h-0 flex-1 gap-3 overflow-y-auto p-3 lg:grid-cols-[minmax(20rem,26rem)_minmax(0,1fr)] lg:overflow-hidden lg:p-4"
                >
                    <aside
                        data-result-sheet-stage-card="word-upload"
                        class="border-studio-focus/70 bg-studio-panel flex min-h-0 min-w-0 flex-col overflow-hidden rounded-xl border shadow-sm"
                    >
                        <div
                            class="border-studio-focus/70 bg-studio-panel/80 flex h-11 shrink-0 items-center justify-between border-b px-4"
                        >
                            <div class="flex items-center gap-2">
                                <div
                                    class="bg-studio-focus text-studio-focus-foreground flex size-5 items-center justify-center rounded text-[10px] font-bold"
                                >
                                    W
                                </div>
                                <span
                                    class="text-studio-panel-foreground truncate text-xs font-bold"
                                    >Upload & Analyze</span
                                >
                            </div>
                            <span
                                class="text-studio-panel-muted-foreground font-mono text-[10px]"
                                >2 / 3</span
                            >
                        </div>
                        <div
                            class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4"
                            aria-busy={analysisLoading}
                        >
                            <div>
                                <h2
                                    class="text-studio-panel-foreground text-sm font-black"
                                >
                                    Upload your completed document
                                </h2>
                                <p
                                    class="text-studio-panel-muted-foreground mt-1 text-[11px] leading-relaxed"
                                >
                                    This stage analyzes the document you
                                    authored externally. It is not an HTML
                                    canvas.
                                </p>
                            </div>
                            <p
                                class="border-studio-warning/60 bg-studio-warning/20 text-studio-warning-foreground rounded-lg border p-3 text-[11px] leading-relaxed"
                            >
                                Drafts save this stage and its metadata, not the
                                selected document. If you leave, select the Word
                                / ODT file again before analysis or preview.
                            </p>
                            <input
                                type="file"
                                accept=".docx,.odt"
                                onchange={handleDocument}
                                disabled={analysisLoading}
                                class="border-studio-border bg-studio-panel text-studio-panel-foreground file:bg-studio-panel-muted min-h-10 w-full rounded-md border text-xs file:mr-3 file:border-0 file:px-3 file:py-2.5 file:font-semibold"
                            />
                            {#if mode === 'edit' && template.document_extension}
                                <p
                                    class="text-studio-panel-muted-foreground text-[11px]"
                                >
                                    Current file: <span
                                        class="font-mono font-semibold uppercase"
                                        >{template.document_extension}</span
                                    > (checksummed)
                                </p>
                            {/if}
                            {#if analysisLoading}
                                <div
                                    class="border-studio-border bg-studio-panel-muted w-full space-y-3 rounded-xl border p-3"
                                    role="status"
                                    aria-live="polite"
                                    aria-atomic="true"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <p
                                            class="text-studio-panel-foreground min-w-0 text-[11px] leading-relaxed"
                                        >
                                            {analysisStepLabel}
                                        </p>
                                        <span
                                            class="text-studio-panel-foreground shrink-0 font-mono text-sm font-bold tabular-nums"
                                            >{analysisProgressPct}%</span
                                        >
                                    </div>
                                    <div
                                        class="h-2 w-full rounded-full bg-muted overflow-hidden"
                                        role="progressbar"
                                        aria-label="Word document analysis"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                        aria-valuenow={analysisProgressPct}
                                    >
                                        <div
                                            class="bg-primary h-full rounded-full transition-all duration-300"
                                            style={`width: ${analysisProgressPct}%`}
                                        ></div>
                                    </div>
                                    <ol
                                        class="flex flex-wrap gap-1.5"
                                        aria-label="Word analysis pipeline steps"
                                    >
                                        {#each analysisProgressSteps as step}
                                            <li
                                                class={`rounded-full border px-2 py-1 text-[10px] font-semibold ${analysisProgressPct === step.percent ? 'border-primary bg-primary text-primary-foreground' : analysisProgressPct > step.percent ? 'border-primary/30 bg-primary/10 text-primary' : 'border-studio-border bg-studio-panel text-studio-panel-muted-foreground'}`}
                                                aria-current={analysisProgressPct ===
                                                step.percent
                                                    ? 'step'
                                                    : undefined}
                                            >
                                                {step.stage}
                                            </li>
                                        {/each}
                                    </ol>
                                </div>
                            {/if}
                            {#if analysis}
                                <div
                                    class="border-studio-border bg-studio-panel-muted space-y-2 rounded-xl border p-3 text-xs"
                                >
                                    <div class="flex justify-between">
                                        <span
                                            class="text-studio-panel-muted-foreground"
                                            >Used</span
                                        ><strong
                                            >{analysis.found?.length ??
                                                0}</strong
                                        >
                                    </div>
                                    <div class="flex justify-between">
                                        <span
                                            class="text-studio-panel-muted-foreground"
                                            >Missing required</span
                                        ><strong
                                            class={analysis.missing?.length
                                                ? 'text-destructive'
                                                : 'text-studio-success-foreground'}
                                            >{analysis.missing?.length ??
                                                0}</strong
                                        >
                                    {#if analysis.recommended_missing?.length}
                                        <div class="flex justify-between text-[10px]">
                                            <span class="text-studio-panel-muted-foreground">Omitted recommended</span>
                                            <span class="font-medium text-studio-panel-muted-foreground">{analysis.recommended_missing.length} (Optional)</span>
                                        </div>
                                    {/if}
                                    </div>
                                    <div class="flex justify-between">
                                        <span
                                            class="text-studio-panel-muted-foreground"
                                            >Unknown / extra</span
                                        ><strong
                                            class={analysis.extra?.length
                                                ? 'text-destructive'
                                                : 'text-studio-success-foreground'}
                                            >{analysis.extra?.length ??
                                                0}</strong
                                        >
                                    </div>
                                    <div
                                        data-result-sheet-word-unresolved-count
                                        class="flex justify-between"
                                    >
                                        <span
                                            class="text-studio-panel-muted-foreground"
                                            >Malformed / unresolved</span
                                        ><strong
                                            class={analysis.unresolved?.length
                                                ? 'text-destructive'
                                                : 'text-studio-success-foreground'}
                                            >{analysis.unresolved?.length ??
                                                0}</strong
                                        >
                                    </div>
                                    {#if analysis.missing?.length}
                                        <div
                                            data-result-sheet-word-missing-callout
                                            role="alert"
                                            class="border-studio-warning bg-studio-warning text-studio-warning-foreground space-y-2 rounded-lg border p-2.5 text-[11px]"
                                        >
                                            <p class="font-bold">
                                                Missing required fields block
                                                Preview and Save.
                                            </p>
                                            <p>
                                                Return to Copy, copy the
                                                highlighted exact macros, then
                                                upload and analyze the corrected
                                                document again.
                                            </p>
                                            <button
                                                type="button"
                                                class="border-studio-warning-foreground/40 bg-studio-panel text-studio-panel-foreground hover:bg-studio-panel-muted w-full rounded-lg border px-2 py-1.5 text-[10px] font-semibold"
                                                onclick={returnToWordCopy}
                                            >
                                                Back to Copy missing fields
                                            </button>
                                        </div>
                                    {/if}
                                </div>
                            {/if}
                        </div>
                    </aside>
                    <main
                        data-result-sheet-stage-main="word-upload"
                        class="border-studio-focus/70 bg-studio-canvas/30 text-studio-canvas-foreground flex min-h-0 min-w-0 items-center justify-center overflow-hidden rounded-xl border"
                        aria-busy={analysisLoading}
                    >
                        {#if analysisLoading}
                            <div
                                class="border-studio-border bg-studio-panel-muted w-full max-w-xl space-y-3 rounded-xl border p-5 shadow-sm"
                                role="status"
                                aria-live="polite"
                                aria-atomic="true"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <p
                                        class="text-studio-panel-foreground min-w-0 text-sm leading-relaxed"
                                    >
                                        {analysisStepLabel}
                                    </p>
                                    <span
                                        class="text-studio-panel-foreground shrink-0 font-mono text-lg font-bold tabular-nums"
                                        >{analysisProgressPct}%</span
                                    >
                                </div>
                                <div
                                    class="h-2 w-full rounded-full bg-muted overflow-hidden"
                                    role="progressbar"
                                    aria-label="Word document analysis"
                                    aria-valuemin="0"
                                    aria-valuemax="100"
                                    aria-valuenow={analysisProgressPct}
                                >
                                    <div
                                        class="bg-primary h-full rounded-full transition-all duration-300"
                                        style={`width: ${analysisProgressPct}%`}
                                    ></div>
                                </div>
                                <ol
                                    class="flex flex-wrap gap-1.5"
                                    aria-label="Word analysis pipeline steps"
                                >
                                    {#each analysisProgressSteps as step}
                                        <li
                                            class={`rounded-full border px-2 py-1 text-[10px] font-semibold ${analysisProgressPct === step.percent ? 'border-primary bg-primary text-primary-foreground' : analysisProgressPct > step.percent ? 'border-primary/30 bg-primary/10 text-primary' : 'border-studio-border bg-studio-panel text-studio-panel-muted-foreground'}`}
                                            aria-current={analysisProgressPct ===
                                            step.percent
                                                ? 'step'
                                                : undefined}
                                        >
                                            {step.stage}
                                        </li>
                                    {/each}
                                </ol>
                            </div>
                        {:else}
                            <div
                                class="border-studio-focus/70 bg-studio-panel/90 text-studio-panel-foreground max-w-sm rounded-2xl border p-6 text-center shadow-md"
                            >
                                <FileText
                                    class="text-studio-focus mx-auto size-8"
                                />
                                <h2
                                    class="text-studio-panel-foreground mt-3 text-sm font-bold"
                                >
                                    Analyze the uploaded file
                                </h2>
                                <p
                                    class="text-studio-panel-muted-foreground mt-1 text-xs leading-relaxed"
                                >
                                    After analysis, missing and unknown
                                    placeholders appear in the Upload card.
                                </p>
                            </div>
                        {/if}
                    </main>
                </div>
            {:else}
                <div
                    data-result-sheet-stage="design"
                    class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden"
                >
                    <ResultSheetStudio
                        fullBleed
                        {builderDocument}
                        {builderDefaults}
                        {builderCatalog}
                        {builderCapabilities}
                        {sampleContext}
                        {selectedPlaceholderGroups}
                        legacyContent={form.content}
                        onChange={handleStudioChange}
                        onSelectionChange={handleStudioSelectionChange}
                        onViewportChange={handleStudioViewportChange}
                        onConvertLegacy={convertLegacy}
                        onOpenPlaceholders={openPicker}
                    />
                </div>
            {/if}
        {:else}
            <!-- Stage 3: Verification and Final Save -->
            <div
                data-result-sheet-stage="verify"
                class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto p-3 pb-[calc(1rem+env(safe-area-inset-bottom))] lg:grid lg:grid-cols-[minmax(20rem,26rem)_minmax(0,1fr)] lg:grid-rows-[minmax(0,1fr)] lg:overflow-hidden lg:p-4"
            >
                <aside
                    data-result-sheet-stage-card="verify"
                    class="border-studio-border bg-studio-panel flex min-h-0 min-w-0 flex-col overflow-hidden rounded-xl border shadow-sm"
                >
                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
                        <div>
                            <h2
                                class="text-studio-panel-foreground text-sm font-black"
                            >
                                Preview and verify
                            </h2>
                            <p
                                class="text-studio-panel-muted-foreground mt-1 text-[11px] leading-relaxed"
                            >
                                Review the production PDF before saving or
                                activating the template.
                            </p>
                        </div>

                        <div
                            class="border-studio-border bg-studio-panel-muted space-y-2 rounded-xl border p-3 text-xs"
                        >
                            <div class="flex justify-between">
                                <span class="text-studio-panel-muted-foreground"
                                    >Format</span
                                ><strong
                                    >{form.mode === 'html'
                                        ? isLegacyHtml
                                            ? 'HTML (Legacy)'
                                            : 'HTML Studio'
                                        : 'Word / ODT'}</strong
                                >
                            </div>
                            <div class="flex justify-between">
                                <span class="text-studio-panel-muted-foreground"
                                    >Page</span
                                ><strong
                                    >{form.paper_size} · {form.orientation}</strong
                                >
                            </div>
                            <div class="flex justify-between">
                                <span class="text-studio-panel-muted-foreground"
                                    >Bindings</span
                                ><strong>{usedKeys.length}</strong>
                            </div>
                            {#if previewPdfUrl && !isPdfStale && previewDiagnostics.length === 0}
                                <div
                                    class="text-studio-success-foreground flex items-center gap-1.5 pt-1 font-semibold"
                                >
                                    <CheckCircle2 class="size-3.5" /> PDF verified
                                    & current
                                </div>
                            {:else if isPdfStale}
                                <div
                                    class="text-studio-warning-foreground flex items-center gap-1.5 pt-1 font-semibold"
                                >
                                    <RefreshCw class="size-3.5 animate-spin" /> Template
                                    changed — refresh PDF
                                </div>
                            {/if}
                            {#if form.mode === 'docx'}
                                <div
                                    class="border-studio-border space-y-1.5 border-t pt-2 text-[11px]"
                                >
                                    <div class="flex justify-between">
                                        <span
                                            class="text-studio-panel-muted-foreground"
                                            >Source Checksum</span
                                        >
                                        <span
                                            class="max-w-[12rem] truncate font-mono text-[10px]"
                                            title={analysis?.source_checksum ||
                                                'Pending'}
                                        >
                                            {analysis?.source_checksum
                                                ? `${analysis.source_checksum.slice(0, 12)}…`
                                                : 'Calculated on upload'}
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span
                                            class="text-studio-panel-muted-foreground"
                                            >Analyzer Status</span
                                        >
                                        <strong
                                            class={analysis?.valid
                                                ? 'text-studio-success-foreground'
                                                : 'text-destructive'}
                                        >
                                            {analysis?.valid
                                                ? 'Valid (All required fields)'
                                                : analysis
                                                  ? 'Invalid (Issues detected)'
                                                  : 'Pending upload'}
                                        </strong>
                                    </div>
                                    <div class="flex justify-between">
                                        <span
                                            class="text-studio-panel-muted-foreground"
                                            >LibreOffice Conversion</span
                                        >
                                        <strong
                                            class={previewPdfUrl && !isPdfStale
                                                ? 'text-studio-success-foreground'
                                                : previewError
                                                  ? 'text-destructive'
                                                  : 'text-studio-panel-muted-foreground'}
                                        >
                                            {previewPdfUrl && !isPdfStale
                                                ? 'Verified (%PDF-1.7 ready)'
                                                : previewError
                                                  ? 'Conversion failed'
                                                  : 'Pending'}
                                        </strong>
                                    </div>
                                </div>
                            {/if}
                        </div>

                        <!-- Compiler Diagnostics Panel -->
                        {#if previewDiagnostics.length > 0}
                            <div
                                bind:this={diagnosticsRef}
                                tabindex="-1"
                                role="alert"
                                aria-live="assertive"
                                class="border-destructive/40 bg-destructive/10 text-destructive focus:ring-studio-focus space-y-2 rounded-xl border p-3 text-xs shadow-sm focus:ring-2 focus:outline-none"
                            >
                                <div
                                    class="text-destructive flex items-center gap-1.5 font-bold"
                                >
                                    <AlertCircle
                                        class="text-destructive size-4"
                                    />
                                    <span
                                        >Compiler Diagnostics ({previewDiagnostics.length})</span
                                    >
                                </div>
                                <p class="text-destructive/80 text-[11px]">
                                    The template contains layout or binding
                                    issues that prevent compilation:
                                </p>
                                <ul class="space-y-1.5 pt-1">
                                    {#each previewDiagnostics as diag}
                                        <li
                                            class="border-destructive/30 bg-studio-panel rounded-lg border p-2 text-[11px]"
                                        >
                                            <div
                                                class="flex items-center justify-between gap-1"
                                            >
                                                <span
                                                    class="bg-destructive/10 text-destructive rounded px-1.5 py-0.5 font-mono text-[10px] font-bold uppercase"
                                                    >{diag.code}</span
                                                >
                                                {#if diag.component_id}
                                                    <span
                                                        class="text-studio-panel-muted-foreground font-mono text-[10px]"
                                                        >id: {diag.component_id}</span
                                                    >
                                                {/if}
                                            </div>
                                            <p
                                                class="text-studio-panel-foreground mt-1"
                                            >
                                                {diag.message}
                                            </p>
                                        </li>
                                    {/each}
                                </ul>
                            </div>
                        {/if}

                        {#if form.mode === 'html' && isLegacyHtml}
                            <p
                                class="text-studio-panel-muted-foreground text-[11px] leading-relaxed"
                            >
                                Editing metadata only. The legacy HTML source remains active until converted.
                            </p>
                        {:else if form.mode === 'docx'}
                            <p
                                class="text-studio-panel-muted-foreground text-[11px] leading-relaxed"
                            >
                                The source document remains private and
                                checksummed.
                            </p>
                        {/if}

                        <!-- Activation Toggle -->
                        <div class="border-studio-border border-t pt-3">
                            <label
                                class="flex cursor-pointer items-center gap-2 text-xs font-medium"
                                class:opacity-60={!canSave && !form.is_active}
                            >
                                <input
                                    type="checkbox"
                                    bind:checked={form.is_active}
                                    onchange={scheduleDraftSave}
                                    disabled={!canSave && !form.is_active}
                                    class="border-studio-border text-studio-success-foreground focus:ring-studio-focus size-4 rounded border"
                                />
                                <span
                                    class="text-studio-panel-foreground font-semibold"
                                    >Set as active template</span
                                >
                            </label>
                            {#if !canSave && !form.is_active}
                                <p
                                    class="text-studio-panel-muted-foreground mt-1 text-[10px]"
                                >
                                    Verification and a valid current PDF preview
                                    are required before activating.
                                </p>
                            {/if}
                        </div>
                    </div>
                </aside>

                <main
                    data-result-sheet-stage-main="verify"
                    class="border-studio-border bg-studio-panel flex h-[min(70dvh,52rem)] min-h-[28rem] min-w-0 flex-col overflow-hidden rounded-xl border lg:h-auto lg:min-h-0"
                    aria-busy={previewLoading}
                >
                    <div
                        class="border-studio-border bg-studio-panel/90 flex h-11 shrink-0 items-center justify-between border-b px-4"
                    >
                        <span
                            class="text-studio-panel-foreground text-xs font-black tracking-wider uppercase"
                            >Production PDF</span
                        >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-7 gap-1 px-2 text-[11px]"
                            onclick={() => void runPreview()}
                            disabled={previewLoading}
                        >
                            <Play class="size-3" /> Refresh
                        </Button>
                    </div>
                    <div
                        bind:this={verifyPreviewContentNode}
                        data-result-sheet-preview-content
                        data-result-sheet-verify-safe-bottom={verifySafeBottom}
                        class="box-border flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden"
                        style={`padding-bottom:calc(${verifySafeBottom}px + env(safe-area-inset-bottom));`}
                    >
                        {#if previewError}
                            <div
                                class="border-destructive/30 bg-destructive/10 text-destructive m-4 min-h-0 flex-1 space-y-2 overflow-y-auto rounded-xl border p-4 text-xs"
                                role="alert"
                            >
                                <div class="flex items-center gap-2 font-bold">
                                    <AlertTriangle class="size-4" />
                                    <span>Preview Compilation Issue</span>
                                </div>
                                <p class="leading-relaxed">{previewError}</p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="mt-2 h-8"
                                    onclick={() => void runPreview()}
                                >
                                    Retry preview
                                </Button>
                            </div>
                        {:else if previewLoading}
                            <div
                                class="flex min-h-0 w-full flex-1 items-center justify-center overflow-y-auto p-4"
                                role="status"
                                aria-live="polite"
                                aria-atomic="true"
                            >
                                <div
                                    class="border-studio-border bg-studio-panel-muted w-full max-w-xl space-y-3 rounded-xl border p-5 shadow-sm"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <p
                                            class="text-studio-panel-foreground min-w-0 text-sm leading-relaxed"
                                        >
                                            {previewStepLabel}
                                        </p>
                                        <span
                                            class="text-studio-panel-foreground shrink-0 font-mono text-lg font-bold tabular-nums"
                                            >{previewProgressPct}%</span
                                        >
                                    </div>
                                    <div
                                        class="h-2 w-full rounded-full bg-muted overflow-hidden"
                                        role="progressbar"
                                        aria-label="PDF preview rendering"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                        aria-valuenow={previewProgressPct}
                                    >
                                        <div
                                            class="bg-primary h-full rounded-full transition-all duration-300"
                                            style={`width: ${previewProgressPct}%`}
                                        ></div>
                                    </div>
                                    <ol
                                        class="flex flex-wrap gap-1.5"
                                        aria-label="PDF rendering pipeline steps"
                                    >
                                        {#each previewProgressSteps as step}
                                            <li
                                                class={`rounded-full border px-2 py-1 text-[10px] font-semibold ${previewProgressPct === step.percent ? 'border-primary bg-primary text-primary-foreground' : previewProgressPct > step.percent ? 'border-primary/30 bg-primary/10 text-primary' : 'border-studio-border bg-studio-panel text-studio-panel-muted-foreground'}`}
                                                aria-current={previewProgressPct ===
                                                step.percent
                                                    ? 'step'
                                                    : undefined}
                                            >
                                                {step.stage}
                                            </li>
                                        {/each}
                                    </ol>
                                </div>
                            </div>
                        {:else if previewPdfUrl}
                                <iframe
                                    title="Template PDF preview"
                                    class="block h-full min-h-0 w-full min-w-0 flex-1 bg-white"
                                    src={previewPdfUrl}
                                ></iframe>
                        {:else}
                            <div
                                class="text-studio-panel-muted-foreground flex min-h-0 flex-1 flex-col items-center justify-center p-6 text-center"
                            >
                                <AlertTriangle class="mb-2 size-8 opacity-40" />
                                <p class="text-xs">
                                    Continue from Design or click Refresh to
                                    generate a PDF preview.
                                </p>
                            </div>
                        {/if}
                    </div>
                </main>
            </div>
        {/if}
    </form>
</div>

<PlaceholderPicker
    open={pickerOpen}
    placeholderGroups={placeholderGroups}
    usedKeys={usedKeys}
    recentKeys={recentKeys}
    mode={pickerMode === 'insert' ? 'insert' : 'copy'}
    onclose={closePicker}
    oncopy={copyPlaceholder}
    oninsert={insertBinding}
/>
