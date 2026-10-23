<script>
    import { router } from '@inertiajs/svelte';
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Button } from '@/Components/ui/button';
    import { csrfHeaders, newIdempotencyKey } from '@/lib/utils';
    import OmrTemplateStudio from './OmrTemplateStudio.svelte';
    import BlankInstanceHistoryPanel from './BlankInstanceHistoryPanel.svelte';
    import BlankInstancePrintPanel from './BlankInstancePrintPanel.svelte';
    let {
        draft = {},
        marker_profiles = [],
        aptitude_areas = [],
        test_instrument = null,
        stages = [],
        urls: serverUrls = {},
        verification_run = null,
        physical_run_id = null,
        kit_context = null,
    } = $props();

    function withKitContext(path) {
        if (!path || !kit_context) return path;
        const url = new URL(path, window.location.origin);
        if (url.origin !== window.location.origin) return path;
        url.searchParams.delete('preparation_id');
        url.searchParams.delete('test_kit_version_id');
        url.searchParams.set('test_kit_id', String(kit_context.test_kit_id));
        if (kit_context.preparation_id !== null) {
            url.searchParams.set('preparation_id', String(kit_context.preparation_id));
        }
        if (kit_context.test_kit_version_id !== null) {
            url.searchParams.set('test_kit_version_id', String(kit_context.test_kit_version_id));
        }
        return `${url.pathname}${url.search}${url.hash}`;
    }

    const urls = $derived.by(() =>
        Object.fromEntries(
            Object.entries(serverUrls).map(([key, value]) => [
                key,
                typeof value === 'string' ? withKitContext(value) : value,
            ]),
        ),
    );

    const stageMeta = {
        draft: { label: 'Start', short: 'Start' },
        convert_inspect: { label: 'Convert / inspect', short: 'Inspect' },
        page_prepare: { label: 'Prepare page', short: 'Prepare' },
        overlay: { label: 'Overlay', short: 'Overlay' },
        map: { label: 'Map', short: 'Map' },
        digital_verify: { label: 'Digital verify', short: 'Verify' },
        compile: { label: 'Compile', short: 'Compile' },
        compiled: { label: 'Compiled component', short: 'Compiled' },
        blank_print: { label: 'Blank print', short: 'Print' },
    };
    const isUploadedSource = ['pdf', 'image', 'docx'].includes(
        draft.source_kind,
    );
    const domainView = {
        draft: draft.source_kind === 'preset' ? 'map' : 'draft',
        source_confirmed: 'convert_inspect',
        page_prepared: 'map',
        mapping_complete: 'map',
        digital_verified: 'compile',
        compiled: 'compiled',
    };
    const breadcrumbs = $derived(
        kit_context
            ? [
                  { label: 'Setup' },
                  { label: 'Test Kits' },
                  { label: kit_context.code },
                  { label: 'Answer Sheet Studio' },
              ]
            : [
                  { label: 'Setup', href: '/setup' },
                  { label: 'Answer Sheets', href: '/setup/exam-materials/answer-sheets' },
                  { label: 'Answer Sheet Studio' },
              ],
    );

    function returnToKit() {
        if (kit_context?.back_url) router.visit(kit_context.back_url);
    }

    let activeStage = $state(domainView[draft.stage] ?? 'draft');
    let busy = $state(false);
    let message = $state('');
    let error = $state('');
    let sourceFile = $state(null);
    let sourceKind = $state(draft.source_kind ?? 'image');
    let paperWidth = $state(216);
    let paperHeight = $state(330);
    let selectedProfile = $state(
        draft.authoring?.marker?.profile_id ?? marker_profiles[0]?.id ?? null,
    );
    let lockVersion = $state(draft.lock_version ?? 0);
    let mapping = $state(
        draft.authoring?.mapping?.input ?? {
            page_width: 2551,
            page_height: 3898,
            page_count: 1,
            blocks: [],
        },
    );
    let overlay = $state(draft.authoring?.overlay ?? { boxes: [] });
    const unlockedIndex = $derived(
        Math.max(0, stages.indexOf(domainView[draft.stage] ?? 'draft')),
    );
    let selectedBlock = $state(null);
    let printerIdentity = $state('');
    let deviceIdentity = $state('');
    let measuredWidthMm = $state('');
    let measuredHeightMm = $state('');
    let qualificationRunId = $state(physical_run_id ?? '');
    let quantity = $state(1);
    let copyPolicy = $state('unique_instance');
    let conversionRun = $state(draft.authoring?.source_run ?? null);

    function setResult(nextDraft, text = 'Saved') {
        if (nextDraft) {
            activeStage = nextDraft.stage ?? activeStage;
            lockVersion = nextDraft.lock_version ?? lockVersion;
            draft = { ...draft, ...nextDraft };
        }
        busy = false;
        message = text;
        error = '';
    }

    function fail(formError) {
        busy = false;
        error =
            formError?.message ??
            'The operation failed. No staged data was discarded.';
        message = '';
    }

    function submitIngest() {
        if (!sourceFile || !draft.can_edit) return;
        busy = true;
        const data = {
            source_kind: sourceKind,
            source: sourceFile,
            idempotency_key: newIdempotencyKey(),
            width_mm: paperWidth,
            height_mm: paperHeight,
        };
        router.post(urls.source_ingest, data, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                conversionRun = {
                    status: 'queued',
                    progress_done: 0,
                    progress_total: 1,
                };
                setResult(
                    null,
                    'Conversion queued. Refresh when the worker finishes.',
                );
            },
            onError: fail,
            onFinish: () => (busy = false),
        });
    }
    async function refreshConversionRun() {
        if (!urls.source_run) return;
        const response = await fetch(urls.source_run, {
            headers: { Accept: 'application/json' },
        });
        if (response.ok) conversionRun = (await response.json()).run;
    }

    async function cancelConversionRun() {
        if (!urls.source_cancel) return;
        const response = await fetch(urls.source_cancel, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...csrfHeaders(),
            },
            body: JSON.stringify({ idempotency_key: newIdempotencyKey() }),
        });
        const body = await response.json();
        if (response.ok) conversionRun = body.run;
        else error = body?.message ?? 'Cancellation could not be requested.';
    }

    function confirmPages() {
        const pages = (draft.authoring?.pages ?? []).map((page) => ({
            page_no: page.page_no,
            decision: 'confirmed',
        }));
        busy = true;
        router.post(
            urls.confirm_pages,
            { pages },
            {
                preserveScroll: true,
                onSuccess: () =>
                    setResult(null, 'All intermediate pages confirmed.'),
                onError: fail,
                onFinish: () => (busy = false),
            },
        );
    }

    function compose() {
        busy = true;
        router.post(
            urls.compose,
            {
                paper_width_mm: Number(paperWidth),
                paper_height_mm: Number(paperHeight),
                content_margin_mm: { top: 18, right: 15, bottom: 18, left: 15 },
                printer_safe_margin_mm: {
                    top: 5,
                    right: 5,
                    bottom: 5,
                    left: 5,
                },
                marker_inset_mm: { top: 12, right: 12, bottom: 14, left: 12 },
                alignment: 'center',
                source_dpi: 300,
                idempotency_key: newIdempotencyKey(),
            },
            {
                preserveScroll: true,
                onSuccess: () =>
                    setResult(null, 'Print-ready 300-PPI page set composed.'),
                onError: fail,
                onFinish: () => (busy = false),
            },
        );
    }

    function chooseProfile(profileId) {
        if (!draft.can_edit) return;
        selectedProfile = profileId;
        busy = true;
        router.put(
            `${urls.mapping?.split('/mapping')[0] ?? ''}/marker-profile/${profileId}`,
            {
                marker_profile_id: profileId,
                insets: { top: 12, right: 12, bottom: 14, left: 12 },
                lock_version: lockVersion,
                idempotency_key: newIdempotencyKey(),
            },
            {
                preserveScroll: true,
                onSuccess: () =>
                    setResult(
                        null,
                        'Marker profile selected; physical size remains profile-owned.',
                    ),
                onError: fail,
                onFinish: () => (busy = false),
            },
        );
    }

    function saveOverlay() {
        busy = true;
        router.put(
            urls.overlay,
            {
                overlay,
                lock_version: lockVersion,
                idempotency_key: newIdempotencyKey(),
            },
            {
                preserveScroll: true,
                onSuccess: () =>
                    setResult(
                        null,
                        'Fixed overlay saved; mapping and verification are now explicitly stale.',
                    ),
                onError: fail,
                onFinish: () => (busy = false),
            },
        );
    }
    function saveMapping() {
        busy = true;
        router.put(
            urls.mapping,
            {
                mapping,
                lock_version: lockVersion,
                idempotency_key: newIdempotencyKey(),
            },
            {
                preserveScroll: true,
                onSuccess: () =>
                    setResult(
                        null,
                        'Mapping saved. Individual bubbles remain inspect-only.',
                    ),
                onError: fail,
                onFinish: () => (busy = false),
            },
        );
    }

    function runDigitalVerification() {
        busy = true;
        router.post(
            urls.digital_verification,
            { idempotency_key: newIdempotencyKey() },
            {
                preserveScroll: true,
                onSuccess: () =>
                    setResult(
                        null,
                        'Digital proof and no-go matrix passed. Thresholds remain Unqualified until physical calibration.',
                    ),
                onError: fail,
                onFinish: () => (busy = false),
            },
        );
    }

    function compile() {
        busy = true;
        router.post(
            urls.compile,
            { idempotency_key: newIdempotencyKey() },
            {
                preserveScroll: true,
                onSuccess: () =>
                    setResult(null, 'Immutable draft version compiled.'),
                onError: fail,
                onFinish: () => (busy = false),
            },
        );
    }

    function physicalQualification() {
        if (
            !qualificationRunId ||
            !printerIdentity ||
            !deviceIdentity ||
            (draft.registration_mode === 'fiducial_anchored' &&
                (!measuredWidthMm || !measuredHeightMm))
        )
            return;
        busy = true;
        // AD-7: qualification is DERIVED from the passed full-qualification run;
        // there is no manual "matrix passed" checkbox.
        router.post(
            withKitContext(`/setup/omr/templates/verification-runs/${qualificationRunId}/qualify`),
            {
                printer: printerIdentity,
                device: deviceIdentity,
                paper: `${paperWidth}×${paperHeight} mm`,
                renderer_version: 'canonical-renderer-v1',
                ...(draft.registration_mode === 'fiducial_anchored'
                    ? {
                          measured_marker_center_mm: [
                              Number(measuredWidthMm),
                              Number(measuredHeightMm),
                          ],
                      }
                    : {}),
                idempotency_key: newIdempotencyKey(),
            },
            {
                preserveScroll: true,
                onSuccess: () =>
                    setResult(
                        null,
                        'Measured physical qualification evidence recorded against the passed full-challenge run.',
                    ),
                onError: fail,
                onFinish: () => (busy = false),
            },
        );
    }


    function printBlankInstances() {
        if (!draft.compiled_version_id) return;
        busy = true;
        router.post(
            withKitContext(`/setup/omr/templates/${draft.compiled_version_id}/blank/print`),
            {
                quantity: Number(quantity),
                copy_policy: copyPolicy,
                idempotency_key: newIdempotencyKey(),
            },
            {
                preserveScroll: true,
                onSuccess: () =>
                    setResult(
                        null,
                        'Blank print job queued in deterministic chunks.',
                    ),
                onError: fail,
                onFinish: () => (busy = false),
            },
        );
    }
</script>

<svelte:head><title>{draft.name} · Answer Sheet Studio</title></svelte:head>

<StaffLayout {breadcrumbs}>
    {#if kit_context}
        <div class="flex shrink-0 items-center justify-between gap-3 border-b border-border px-4 py-2.5">
            <p class="min-w-0 truncate text-sm text-muted-foreground">
                {kit_context.read_only ? 'Read-only sealed Test Kit' : 'Preparing Test Kit'} · {kit_context.code}
            </p>
            <Button variant="outline" size="sm" onclick={returnToKit}>
                Back to {kit_context.code}
            </Button>
        </div>
    {/if}
    <div class={kit_context ? 'flex h-[calc(100vh-6.5rem)] w-full flex-col overflow-hidden' : 'flex h-[calc(100vh-3.5rem)] w-full flex-col overflow-hidden'}>
        {#if activeStage === 'blank_print'}
            <div class="mx-auto w-full max-w-5xl space-y-6 overflow-y-auto p-6">
                <BlankInstancePrintPanel
                    {quantity}
                    qrMode={draft.qr_mode}
                    {copyPolicy}
                    {busy}
                    disabled={!draft.can_edit ||
                        draft.compiled_version_status !== 'active'}
                    onquantity={(value) => (quantity = value)}
                    oncopyPolicy={(value) => (copyPolicy = value)}
                    onprint={printBlankInstances}
                />
                <BlankInstanceHistoryPanel
                    templateVersionId={draft.compiled_version_id}
                />
            </div>
        {:else}
            <OmrTemplateStudio
                {draft}
                {marker_profiles}
                {aptitude_areas}
                {test_instrument}
                {urls}
                {kit_context}
                onMappingSaved={() => {
                    message =
                        'Studio layout autosaved. Mapping and overlay are synchronized.';
                    error = '';
                }}
            />
        {/if}
    </div>
</StaffLayout>
