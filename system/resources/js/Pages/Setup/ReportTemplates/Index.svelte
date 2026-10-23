<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import MaterialDocumentPreview from '@/Components/kit/MaterialDocumentPreview.svelte';
    import DocumentPrintManagerModal from '@/Components/DocumentTemplate/DocumentPrintManagerModal.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import { Link, router } from '@inertiajs/svelte';
    import {
        Download,
        FileText,
        Layers,
        Pause,
        Play,
        Plus,
        Printer,
        Search,
        Users,
        X,
        Upload,
        Eye,
        Landmark,
        Check,
        FileUp,
    } from 'lucide-svelte';
    let {
        offices = [],
        flat_variants = [],
        baseline_inventory = [],
        office_packs = [],
        available_surfaces = [],
        can_create_custom_document = false,
        custom_documents = [],
    } = $props();

    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Document & Template Hub' },
    ];

    const SLOT_LABELS = {
        analytics_reports_hub: 'Analytics & Reports Hub',
        admissions_registry_bulk: 'Applications Registry',
        admissions_applicant_show: 'Applicant Profile',
        applicant_qr_scan_modal: 'Applicant QR Scan',
        registrar_enrollment_clearance: 'Admission Clearance',
        program_workspace_show: 'Program Workspace',
        exam_session_show: 'Exam Session Workspace',
    };

    const FALLBACK_BASELINES = [
        {
            key: 'ispsc_qad_f032a_rev5',
            title: 'ISPSC Internal Communication Letterhead — QAD-F032a, Rev. 5',
            form_code: 'ISPSC-QAD-F032a',
            revision: 'Rev. 5 – 07-07-2025',
            format: 'docx',
            paper_size: 'a4',
            orientation: 'portrait',
            description:
                'Official ISPSC letterhead shell with Vision/Mission header, logos, and Page {{ pg_i }} of {{ pg_all }} multi-page numbering.',
            download_url:
                '/setup/report-templates/baselines/ispsc_qad_f032a_rev5/download',
        },
        {
            key: 'standard_portrait_letterhead',
            title: 'Standard Portrait Report Shell',
            format: 'docx',
            paper_size: 'a4',
            orientation: 'portrait',
            download_url:
                '/setup/report-templates/baselines/standard_portrait_letterhead/download',
        },
        {
            key: 'landscape_wide_register',
            title: 'Landscape Wide Census & Attendance Shell',
            format: 'docx',
            paper_size: 'a4',
            orientation: 'landscape',
            download_url:
                '/setup/report-templates/baselines/landscape_wide_register/download',
        },
        {
            key: 'executive_summary_frame',
            title: 'Executive KPI & Summary Report Shell',
            format: 'docx',
            paper_size: 'a4',
            orientation: 'portrait',
            download_url:
                '/setup/report-templates/baselines/executive_summary_frame/download',
        },
    ];

    let selectedDesk = $state('all');
    let selectedGroup = $state('');
    let selectedOffice = $state('all');
    let selectedStatus = $state('all');
    let searchQuery = $state('');
    let uploadInfoOpen = $state(false);
    let selectedBaselineSpec = $state(null);
    let printModalOpen = $state(false);
    let selectedTemplate = $state(null);
    const desks = [
        { id: 'all', label: 'All Authorized Desks' },
        { id: 'admin', label: 'Admin Desk' },
        { id: 'registrar', label: 'Registrar Desk' },
        { id: 'testing', label: 'Testing Unit' },
        { id: 'guidance', label: 'Guidance & Deans' },
    ];

    function resolvePackForSurface(surfaceKey) {
        const keyMap = {
            course_quota_qualifiers: 'registrar.qualifiers',
            passing_report: 'guidance.passing',
            admission_slip: 'registrar.admission_slip',
            clearance_slip: 'registrar.clearance_slip',
            applications_census: 'registrar.applications_census',
            program_demand: 'registrar.program_demand',
            geographic_origin: 'registrar.geographic_origin',
        };
        const targetKey = keyMap[surfaceKey] ?? surfaceKey;
        return (
            (office_packs ?? []).find(
                (p) =>
                    p.key === targetKey ||
                    p.pack_key === targetKey ||
                    p.key === surfaceKey ||
                    p.pack_key === surfaceKey,
            ) ?? null
        );
    }

    function inferGrain(surfaceKey, pack = null, variant = null) {
        if (variant?.grain) return variant.grain;
        if (pack?.grain) return pack.grain;
        if (
            surfaceKey === 'admission_slip' ||
            surfaceKey === 'clearance_slip' ||
            surfaceKey === 'registrar.admission_slip' ||
            surfaceKey === 'registrar.clearance_slip'
        ) {
            return 'student';
        }
        return 'cycle_program';
    }

    function formatGrainBadge(grain) {
        if (grain === 'student') return 'Student Grain';
        if (grain === 'session') return 'Session Grain';
        return 'Cycle / Program Grain';
    }

    function inferPurpose(surfaceKey, officeKey, pack = null, variant = null) {
        const raw = (variant?.purpose ?? pack?.purpose ?? '').toLowerCase();
        if (raw === 'admissions' || raw.includes('admission') || raw.includes('clearance')) {
            return 'admissions';
        }
        if (raw === 'examination' || raw.includes('exam') || raw.includes('result')) {
            return 'examination';
        }
        if (raw === 'analytics' || raw.includes('analytic') || raw.includes('report')) {
            return 'analytics';
        }
        if (
            surfaceKey === 'admission_slip' ||
            surfaceKey === 'clearance_slip' ||
            surfaceKey === 'registrar.admission_slip' ||
            surfaceKey === 'registrar.clearance_slip'
        ) {
            return 'admissions';
        }
        if (officeKey === 'guidance' || surfaceKey === 'passing_report') {
            return 'analytics';
        }
        return 'analytics';
    }

    function defaultMountedSlots(surfaceKey, grain) {
        if (surfaceKey === 'admission_slip' || surfaceKey === 'registrar.admission_slip') {
            return [
                'admissions_registry_bulk',
                'admissions_applicant_show',
                'applicant_qr_scan_modal',
            ];
        }
        if (surfaceKey === 'clearance_slip' || surfaceKey === 'registrar.clearance_slip') {
            return ['registrar_enrollment_clearance'];
        }
        if (grain === 'student') {
            return ['admissions_applicant_show'];
        }
        return ['analytics_reports_hub'];
    }

    function formatSlotPill(slot) {
        if (!slot) return '';
        if (typeof slot === 'object') {
            return slot.label ?? SLOT_LABELS[slot.slot_key] ?? slot.slot_key ?? '';
        }
        return SLOT_LABELS[slot] ?? slot;
    }

    function formatPaperBadge(paperSize, orientation) {
        const size = String(paperSize ?? 'a4').toUpperCase();
        const orient =
            String(orientation ?? 'portrait').toLowerCase() === 'landscape'
                ? 'Landscape'
                : 'Portrait';
        return `${size} ${orient}`;
    }

    function startCustomDocumentCreation() {
        router.visit('/setup/document-templates/custom_document/create');
    }

    function baselineSpecs(baseline) {
        return baseline.specifications ?? ['Header Logo', 'ISO Document Box', 'Confidential Watermark', 'Page X of Y Footer'];
    }

    function formatModeBadge(mode) {
        return String(mode ?? 'html').toLowerCase() === 'docx'
            ? 'Word DOCX'
            : 'HTML Studio';
    }

    const filteredCustomDocuments = $derived.by(() => {
        const query = searchQuery.trim().toLowerCase();

        return (custom_documents ?? []).filter((document) => {
            if (!matchesDesk(document.office, selectedDesk)) return false;
            if (selectedOffice !== 'all' && document.office !== selectedOffice) return false;
            if (selectedStatus === 'active' && !document.is_active) return false;
            if (selectedStatus === 'inactive' && document.is_active) return false;
            if (selectedGroup && String(document.custom_group ?? '').trim() !== selectedGroup) return false;
            if (!query) return true;

            return [
                document.name,
                document.surface_title,
                document.custom_group,
                document.office_label,
                document.grain,
            ].filter(Boolean).join(' ').toLowerCase().includes(query);
        });
    });

    function customDocumentPath(document, action = null) {
        const base = `/setup/document-templates/custom_document/${document.id}`;
        return action ? `${base}/${action}` : base;
    }
    const surfaceSections = $derived.by(() => {
        const map = new Map();

        for (const officeGroup of offices ?? []) {
            const officeKey =
                officeGroup.office ?? officeGroup.office_key ?? 'registrar';
            const officeLabel =
                officeGroup.office_label ??
                officeGroup.label ??
                (officeKey === 'registrar'
                    ? 'Registrar Office'
                    : officeKey === 'guidance'
                      ? 'Guidance Office'
                      : officeKey === 'admin'
                        ? 'Admin Office'
                        : 'Academic Programs');

            for (const surface of officeGroup.surfaces ?? []) {
                if (surface.surface_key === 'custom_document') continue;
                const pack = resolvePackForSurface(surface.surface_key);
                const grain = inferGrain(surface.surface_key, pack);
                const purpose = inferPurpose(surface.surface_key, officeKey, pack);
                map.set(surface.surface_key, {
                    surface_key: surface.surface_key,
                    title: surface.title,
                    pack_title: pack?.title ?? surface.title,
                    office: surface.office ?? officeKey,
                    office_label: surface.office_label ?? officeLabel,
                    layout_paradigm: surface.layout_paradigm ?? null,
                    templates_count:
                        surface.templates_count ?? surface.template_count ?? 0,
                    active_template: surface.active_template ?? null,
                    grain,
                    grain_label: formatGrainBadge(grain),
                    purpose,
                    create_variant_url: `/setup/report-templates/${surface.surface_key}/create`,
                    variants: [],
                });
            }
        }

        for (const rawVariant of flat_variants ?? []) {
            const surfaceKey = rawVariant.surface_key ?? 'course_quota_qualifiers';
            if (surfaceKey === 'custom_document') continue;
            const pack = resolvePackForSurface(surfaceKey);
            if (!map.has(surfaceKey)) {
                const officeKey = rawVariant.office ?? pack?.office ?? 'registrar';
                const grain = inferGrain(surfaceKey, pack, rawVariant);
                map.set(surfaceKey, {
                    surface_key: surfaceKey,
                    title:
                        rawVariant.surface_title ??
                        pack?.title ??
                        surfaceKey,
                    pack_title:
                        rawVariant.pack_title ??
                        pack?.title ??
                        rawVariant.surface_title ??
                        surfaceKey,
                    office: officeKey,
                    office_label:
                        rawVariant.office_label ??
                        pack?.office_label ??
                        'Registrar Office',
                    layout_paradigm: null,
                    templates_count: 0,
                    active_template: null,
                    grain,
                    grain_label: formatGrainBadge(grain),
                    purpose: inferPurpose(surfaceKey, officeKey, pack, rawVariant),
                    create_variant_url:
                        rawVariant.create_variant_url ??
                        `/setup/report-templates/${surfaceKey}/create`,
                    variants: [],
                });
            }

            const section = map.get(surfaceKey);
            const grain = inferGrain(surfaceKey, pack, rawVariant);
            const purpose = inferPurpose(
                surfaceKey,
                section.office,
                pack,
                rawVariant,
            );
            const mountedSlots = Array.isArray(rawVariant.mounted_slots)
                ? rawVariant.mounted_slots
                : defaultMountedSlots(surfaceKey, grain);

            section.variants.push({
                ...rawVariant,
                surface_key: surfaceKey,
                surface_title: rawVariant.surface_title ?? section.title,
                office: rawVariant.office ?? section.office,
                office_label: rawVariant.office_label ?? section.office_label,
                pack_title:
                    rawVariant.pack_title ?? section.pack_title ?? section.title,
                grain,
                grain_label: formatGrainBadge(grain),
                purpose,
                mounted_slots: mountedSlots,
                custom_group: rawVariant.custom_group ?? null,
                edit_url:
                    rawVariant.edit_url ??
                    `/setup/report-templates/${surfaceKey}/${rawVariant.id}/edit`,
                activate_url:
                    rawVariant.activate_url ??
                    `/setup/report-templates/${surfaceKey}/${rawVariant.id}/activate`,
                deactivate_url:
                    rawVariant.deactivate_url ??
                    `/setup/report-templates/${surfaceKey}/${rawVariant.id}/deactivate`,
                create_variant_url:
                    rawVariant.create_variant_url ??
                    `/setup/report-templates/${surfaceKey}/create`,
            });
        }

        for (const section of map.values()) {
            if (section.variants.length === 0 && section.active_template) {
                const tpl = section.active_template;
                section.variants.push({
                    id: tpl.id,
                    name: tpl.name,
                    surface_key: section.surface_key,
                    surface_title: section.title,
                    office: section.office,
                    office_label: section.office_label,
                    mode: tpl.mode ?? 'html',
                    paper_size: tpl.paper_size ?? 'a4',
                    orientation: tpl.orientation ?? 'portrait',
                    builder_json: tpl.builder_json ?? null,
                    is_active: true,
                    grain: section.grain,
                    grain_label: section.grain_label,
                    purpose: section.purpose,
                    custom_group: tpl.custom_group ?? null,
                    mounted_slots: Array.isArray(tpl.mounted_slots)
                        ? tpl.mounted_slots
                        : defaultMountedSlots(section.surface_key, section.grain),
                    pack_title: section.pack_title,
                    thumbnail_url: tpl.thumbnail_url,
                    edit_url: `/setup/report-templates/${section.surface_key}/${tpl.id}/edit`,
                    activate_url: `/setup/report-templates/${section.surface_key}/${tpl.id}/activate`,
                    deactivate_url: `/setup/report-templates/${section.surface_key}/${tpl.id}/deactivate`,
                    create_variant_url: `/setup/report-templates/${section.surface_key}/create`,
                });
            }
        }

        return Array.from(map.values());
    });

    function matchesDesk(office, desk) {
        if (desk === 'all') return true;
        if (desk === 'testing') return office === 'testing' || office === 'guidance';
        if (desk === 'guidance') return ['guidance', 'program'].includes(office);
        return office === desk;
    }

    const customGroupLabels = $derived.by(() => {
        const labels = new Set();
        for (const section of surfaceSections) {
            if (selectedDesk !== 'all' && selectedDesk !== 'baselines' && !matchesDesk(section.office, selectedDesk)) {
                continue;
            }
            for (const variant of section.variants) {
                const group = String(variant.custom_group ?? '').trim();
                if (group) labels.add(group);
            }
        }
        for (const document of custom_documents ?? []) {
            if (selectedDesk !== 'all' && !matchesDesk(document.office, selectedDesk)) {
                continue;
            }
            const group = String(document.custom_group ?? '').trim();
            if (group) labels.add(group);
        }
        return Array.from(labels);
    });


    const filteredSurfaceSections = $derived.by(() => {
        if (selectedDesk === 'baselines') return [];
        const query = searchQuery.trim().toLowerCase();
        const results = [];
        for (const section of surfaceSections) {
            if (!matchesDesk(section.office, selectedDesk)) continue;
            if (selectedOffice !== 'all' && section.office !== selectedOffice) continue;
            const variants = section.variants.filter((variant) => {
                if (!matchesDesk(variant.office ?? section.office, selectedDesk)) return false;
                if (selectedStatus === 'active' && !variant.is_active) return false;
                if (selectedStatus === 'inactive' && variant.is_active) return false;
                if (selectedGroup && String(variant.custom_group ?? '').trim() !== selectedGroup) return false;
                if (!query) return true;
                const haystack = [
                    variant.name,
                    variant.surface_title,
                    section.title,
                    variant.pack_title,
                    section.pack_title,
                    variant.custom_group,
                    variant.office_label,
                    section.office_label,
                ].filter(Boolean).join(' ').toLowerCase();
                return haystack.includes(query);
            });
            if (variants.length > 0) {
                results.push({ ...section, filteredVariants: variants });
            } else if (section.variants.length === 0 && selectedStatus === 'all' && !selectedGroup) {
                const haystack = [section.title, section.pack_title, section.office_label, section.surface_key]
                    .filter(Boolean).join(' ').toLowerCase();
                if (!query || haystack.includes(query)) {
                    results.push({ ...section, filteredVariants: [] });
                }
            }
        }
        return results;
    });

    const effectiveBaselines = $derived.by(() => {
        const raw =
            Array.isArray(baseline_inventory) && baseline_inventory.length > 0
                ? baseline_inventory
                : FALLBACK_BASELINES;
        const query = searchQuery.trim().toLowerCase();
        if (!query) return raw;
        return raw.filter((item) =>
            [item.title, item.form_code, item.revision, item.description, item.key]
                .filter(Boolean)
                .join(' ')
                .toLowerCase()
                .includes(query),
        );
    });

    function toggleActive(variant, section) {
        const url = variant.is_active
            ? (variant.deactivate_url ?? `/setup/report-templates/${section.surface_key}/${variant.id}/deactivate`)
            : (variant.activate_url ?? `/setup/report-templates/${section.surface_key}/${variant.id}/activate`);
        router.post(url, { return_to: 'hub' }, { preserveScroll: true });
    }

    function openRunModal(variant, section) {
        selectedTemplate = {
            ...variant,
            surface_key: variant.surface_key ?? section?.surface_key,
            grain: variant.grain ?? section?.grain ?? 'cycle_program',
        };
        printModalOpen = true;
    }
</script>

<svelte:head><title>Document &amp; Template Hub · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6" data-report-templates-hub>
        <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight text-foreground">
                    Document &amp; Template Hub
                </h1>
                <p class="text-sm text-muted-foreground">
                    Manage institutional document defaults, office-specific custom templates, and official baselines.
                </p>
            </div>
            {#if can_create_custom_document}
                <Button class="gap-1.5 font-bold" onclick={startCustomDocumentCreation}>
                    <Plus class="size-4" /> Create Custom Document
                </Button>
            {/if}
        </header>

        <section class="space-y-3 rounded-xl border border-border bg-card p-4 shadow-sm" aria-label="Template discovery filters">
            <div class="flex flex-wrap items-center gap-1.5 border-b border-border pb-3" role="tablist" aria-label="Office and desk tabs">
                {#each desks as desk (desk.id)}
                    <button
                        type="button"
                        role="tab"
                        aria-selected={selectedDesk === desk.id}
                        onclick={() => {
                            selectedDesk = desk.id;
                            selectedGroup = '';
                        }}
                        class={`inline-flex min-h-8 items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${selectedDesk === desk.id ? 'bg-primary text-primary-foreground shadow-xs' : 'bg-muted/50 text-muted-foreground hover:bg-muted hover:text-foreground'}`}
                    >
                        {desk.label}
                    </button>
                {/each}
            </div>

            {#if selectedDesk !== 'baselines' && customGroupLabels.length > 0}
                <div class="flex flex-wrap items-center gap-1.5 border-b border-border pb-3" aria-label="Custom template groups">
                    <span class="mr-1 text-xs font-medium text-muted-foreground">Folders / Tags</span>
                    <button type="button" aria-pressed={!selectedGroup} onclick={() => (selectedGroup = '')} class={`rounded-full border px-3 py-1 text-xs font-semibold transition-colors ${!selectedGroup ? 'border-primary bg-primary/10 text-primary' : 'border-border text-muted-foreground hover:bg-muted'}`}>
                        All groups
                    </button>
                    {#each customGroupLabels as group (group)}
                        <button type="button" aria-pressed={selectedGroup === group} onclick={() => (selectedGroup = selectedGroup === group ? '' : group)} class={`rounded-full border px-3 py-1 text-xs font-semibold transition-colors ${selectedGroup === group ? 'border-primary bg-primary/10 text-primary' : 'border-border text-muted-foreground hover:bg-muted'}`}>
                            {group}
                        </button>
                    {/each}
                </div>
            {/if}

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_auto_auto]">
                <div class="relative flex items-center">
                    <Search class="pointer-events-none absolute left-3 size-4 text-muted-foreground" />
                    <input type="search" bind:value={searchQuery} placeholder="Search by template name, surface, data pack, or custom group..." aria-label="Search templates" class="h-9 w-full rounded-md border border-input bg-background pl-9 pr-3 text-sm text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring" />
                </div>
                <div class="flex items-center gap-2">
                    <label for="hub-office-filter" class="text-xs font-medium text-muted-foreground whitespace-nowrap">Office</label>
                    <select id="hub-office-filter" aria-label="Office filter" bind:value={selectedOffice} class="h-9 rounded-md border border-input bg-background px-3 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                        <option value="all">All Offices</option>
                        <option value="registrar">Registrar Office</option>
                        <option value="guidance">Guidance Office</option>
                        <option value="admin">Admin Office</option>
                        <option value="testing">Testing Unit</option>
                        <option value="program">Academic Programs</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <label for="hub-status-filter" class="text-xs font-medium text-muted-foreground whitespace-nowrap">Status</label>
                    <select id="hub-status-filter" aria-label="Status filter" bind:value={selectedStatus} class="h-9 rounded-md border border-input bg-background px-3 text-xs font-medium text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                        <option value="all">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
        </section>

        {#if selectedDesk !== 'baselines'}
            {#each filteredSurfaceSections as section (section.surface_key)}
                <section class="space-y-3 rounded-xl border border-border bg-card/60 p-4 shadow-xs sm:p-5" aria-labelledby={`surface-${section.surface_key}`} data-surface-section={section.surface_key}>
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 pb-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 id={`surface-${section.surface_key}`} class="text-base font-semibold text-foreground">{section.title}</h2>
                            <Badge variant="outline" class="text-[11px] font-medium">{section.office_label}</Badge>
                            <Badge variant="secondary" class="gap-1 text-[11px] font-semibold" data-grain-badge={section.grain}>
                                {#if section.grain === 'student'}<Users class="size-3" />{:else}<Layers class="size-3" />{/if}
                                {section.grain_label}
                            </Badge>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link href={`/setup/report-templates/${section.surface_key}`} class="inline-flex min-h-9 items-center justify-center rounded-md border border-border bg-background px-3 py-1.5 text-xs font-semibold text-foreground transition-colors hover:bg-muted">Manage Surface</Link>
                            <Link href={`/setup/report-templates/${section.surface_key}/create`} class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground transition-opacity hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"><Plus class="size-3.5" /> + New Variant</Link>
                        </div>
                    </div>
                    {#if section.filteredVariants.length > 0}
                        <div class="mt-3 flex flex-col gap-2.5">
                            {#each section.filteredVariants.filter((variant) => variant.surface_key !== 'custom_document' && variant.name !== 'Guidance Career Advising & Referral Slip') as variant (variant.id ?? `${section.surface_key}-${variant.name}`)}
                                <article class="flex flex-col gap-3 rounded-xl border bg-card/85 px-4 py-3 transition hover:border-primary/40 md:flex-row md:items-center md:justify-between" data-variant-card={variant.id}>
                                    <div class="flex min-w-0 flex-1 items-center gap-3.5">
                                        <MaterialDocumentPreview src={variant.thumbnail_url} kind="report" mode={(variant.mode ?? 'html').toUpperCase()} title={variant.name ?? section.title} builder_json={variant.builder_json} orientation={variant.orientation ?? 'portrait'} fullWidth={true} class="h-14 w-11 shrink-0 overflow-hidden rounded border border-border/70 bg-white shadow-xs" alt={`${variant.name ?? section.title} template preview`} />
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="break-words text-sm font-semibold text-foreground">{variant.name}</h3>
                                                {#if variant.is_active}<Badge class="shrink-0 border border-primary/30 bg-primary/15 text-primary text-[10px]">Active</Badge>{:else}<Badge variant="secondary" class="shrink-0 text-[10px]">Inactive</Badge>{/if}
                                                {#if variant.custom_group}<Badge variant="secondary" class="shrink-0 text-[10px] font-medium">{variant.custom_group}</Badge>{/if}
                                            </div>
                                            <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                <span>{variant.mode?.toUpperCase() || 'HTML'} · {variant.paper_size?.toUpperCase() || 'A4'} {variant.orientation || 'portrait'} · {variant.grain_label || variant.grain}</span>
                                                <span>{variant.mounted_slots?.length ? `Mounted on ${variant.mounted_slots.length} page${variant.mounted_slots.length === 1 ? '' : 's'}` : 'Hub-only'}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 md:shrink-0 md:flex-nowrap">
                                        <Button type="button" class="inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-primary px-3.5 text-xs font-semibold text-primary-foreground whitespace-nowrap shadow-xs hover:bg-primary/90" onclick={() => openRunModal(variant, section)}><Printer class="size-3.5" />Run / Print</Button>
                                        {#if variant.download_source_url}
                                            <a href={variant.download_source_url} download class="inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-border bg-background px-3 text-xs font-medium text-foreground whitespace-nowrap hover:bg-muted" title="Download active Word .docx template for offline editing"><Download class="size-3.5" />Export .docx</a>
                                        {/if}
                                        <Link href={variant.edit_url ?? `/setup/report-templates/${section.surface_key}/${variant.id}/edit`} class="inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-border bg-background px-3.5 text-xs font-medium text-foreground whitespace-nowrap hover:bg-muted">Edit in Studio</Link>
                                        {#if variant.is_active}
                                            <Button type="button" variant="outline" class="inline-flex h-9 shrink-0 items-center justify-center rounded-lg border border-border bg-background px-3 text-xs font-medium text-muted-foreground whitespace-nowrap hover:bg-muted hover:text-foreground" onclick={() => toggleActive(variant, section)}><Pause class="size-3.5" />Deactivate</Button>
                                        {:else}
                                            <Button type="button" variant="outline" class="inline-flex h-9 shrink-0 items-center justify-center rounded-lg border border-border bg-background px-3 text-xs font-medium text-muted-foreground whitespace-nowrap hover:bg-muted hover:text-foreground" onclick={() => toggleActive(variant, section)}><Play class="size-3.5" />Activate</Button>
                                        {/if}
                                    </div>
                                </article>
                            {/each}
                        </div>
                    {:else}
                        <div class="flex flex-col items-center justify-between gap-3 rounded-lg border border-dashed border-border bg-muted/20 px-4 py-6 sm:flex-row">
                            <div class="space-y-0.5 text-center sm:text-left">
                                <p class="text-sm font-medium text-foreground">No active template</p>
                                <p class="text-xs text-muted-foreground">Create the first template variant for {section.title} to enable studio editing and direct hub printing.</p>
                            </div>
                            <Link href={`/setup/report-templates/${section.surface_key}/create`} class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground transition-opacity hover:opacity-90"><Plus class="size-3.5" />+ New Variant</Link>
                        </div>
                    {/if}

                </section>
            {/each}

            <section class="space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm sm:p-5" aria-labelledby="custom-office-documents-heading" data-custom-office-documents-section>
                <div class="flex flex-col justify-between gap-3 border-b border-border/70 pb-3 sm:flex-row sm:items-center">
                    <div class="space-y-1">
                        <h2 id="custom-office-documents-heading" class="text-base font-semibold text-foreground">Custom Office Documents</h2>

                    </div>
                    {#if can_create_custom_document}
                        <Button class="h-9 gap-1.5 text-xs font-semibold" onclick={startCustomDocumentCreation}>
                            <Plus class="size-3.5" /> Create Custom Document
                        </Button>
                    {/if}
                </div>

                {#if filteredCustomDocuments.length > 0}
                    <div class="flex flex-col gap-2.5">
                        {#each filteredCustomDocuments as document (document.id)}
                            <article class="flex flex-col gap-3 rounded-xl border bg-card/85 px-4 py-3 transition hover:border-primary/40 md:flex-row md:items-center md:justify-between" data-custom-document-card={document.id}>
                                <div class="flex min-w-0 flex-1 items-center gap-3.5">
                                    <MaterialDocumentPreview src={document.thumbnail_url} kind="report" mode={(document.mode ?? 'html').toUpperCase()} title={document.name ?? 'Custom Office Document'} orientation={document.orientation ?? 'portrait'} fullWidth={true} class="h-14 w-11 shrink-0 overflow-hidden rounded border border-border/70 bg-white shadow-xs" alt={`${document.name ?? 'Custom Office Document'} template preview`} />
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="break-words text-sm font-semibold text-foreground">{document.name}</h3>
                                            {#if document.is_active}<Badge class="shrink-0 border border-primary/30 bg-primary/15 text-primary text-[10px]">Active</Badge>{:else}<Badge variant="secondary" class="shrink-0 text-[10px]">Inactive</Badge>{/if}
                                            {#if document.custom_group}<Badge variant="secondary" class="shrink-0 text-[10px] font-medium">{document.custom_group}</Badge>{/if}
                                        </div>
                                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                            <span>{document.mode?.toUpperCase() || 'HTML'} · {document.paper_size?.toUpperCase() || 'A4'} {document.orientation || 'portrait'} · {document.grain_label ?? formatGrainBadge(document.grain)}</span>
                                            <span>{document.mounted_slots?.length ? `Mounted on ${document.mounted_slots.length} page${document.mounted_slots.length === 1 ? '' : 's'}` : 'Hub-only'}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 md:shrink-0 md:flex-nowrap">
                                    <Button type="button" class="inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg bg-primary px-3.5 text-xs font-semibold text-primary-foreground whitespace-nowrap shadow-xs hover:bg-primary/90" onclick={() => openRunModal(document, { surface_key: 'custom_document', grain: document.grain })}><Printer class="size-3.5" />Run / Print</Button>
                                    {#if document.download_source_url}
                                        <a href={document.download_source_url} download class="inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-border bg-background px-3 text-xs font-medium text-foreground whitespace-nowrap hover:bg-muted" title="Download active Word .docx template for offline editing"><Download class="size-3.5" />Export .docx</a>
                                    {/if}
                                    <Link href={customDocumentPath(document, 'edit')} class="inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-border bg-background px-3.5 text-xs font-medium text-foreground whitespace-nowrap hover:bg-muted">Edit in Studio</Link>
                                    <Button type="button" variant="outline" class="inline-flex h-9 shrink-0 items-center justify-center rounded-lg border border-border bg-background px-3 text-xs font-medium text-muted-foreground whitespace-nowrap hover:bg-muted hover:text-foreground" onclick={() => toggleActive({ ...document, activate_url: customDocumentPath(document, 'activate'), deactivate_url: customDocumentPath(document, 'deactivate') }, { surface_key: 'custom_document' })}>
                                        {#if document.is_active}<Pause class="size-3.5" />Deactivate{:else}<Play class="size-3.5" />Activate{/if}
                                    </Button>
                                </div>
                            </article>
                        {/each}
                    </div>
                {:else if (custom_documents ?? []).length === 0}
                    <div class="flex flex-col items-center justify-between gap-3 rounded-lg border border-dashed border-border bg-muted/20 px-4 py-6 sm:flex-row">
                        <div class="space-y-0.5 text-center sm:text-left">
                            <p class="text-sm font-medium text-foreground">Start a document for your office</p>
                            <p class="text-xs text-muted-foreground">Create a referral slip, letter, notice, or other standalone document.</p>
                        </div>
                        {#if can_create_custom_document}
                            <Button class="gap-1.5 text-xs font-semibold" onclick={startCustomDocumentCreation}><Plus class="size-3.5" /> Create Custom Document</Button>
                        {/if}
                    </div>
                {:else}
                    <p class="rounded-lg border border-dashed border-border p-6 text-center text-sm text-muted-foreground">No custom documents match the current filters.</p>
                {/if}
            </section>
        {/if}

        {#if selectedDesk === 'baselines'}
            <section class="space-y-4 rounded-xl border border-border bg-card p-4 shadow-sm sm:p-6" aria-labelledby="institutional-baselines-heading" data-institutional-baselines-section>
                <div class="flex flex-col justify-between gap-3 border-b border-border/70 pb-4 sm:flex-row sm:items-center">
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <Landmark class="size-5 text-primary" />
                            <h2 id="institutional-baselines-heading" class="text-lg font-semibold text-foreground">Institutional Baselines &amp; Letterhead Vault</h2>
                            <Badge variant="outline" class="text-xs">Governed DOCX shells</Badge>
                        </div>
                        <p class="max-w-3xl text-sm text-muted-foreground">Official ISO 9001:2015 letterhead shells and approved document frames used as the foundation for templates.</p>
                    </div>
                    <Button type="button" variant="outline" class="gap-1.5 font-semibold" onclick={() => (uploadInfoOpen = true)}>
                        <Upload class="size-4" /> Upload / Register New Word Shell (.docx)
                    </Button>
                </div>
                {#if effectiveBaselines.length > 0}
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {#each effectiveBaselines as baseline (baseline.key)}
                            <article class="flex min-w-0 flex-col gap-3 rounded-xl border border-border bg-background p-4 shadow-xs" data-baseline-card={baseline.key}>
                                <div class="flex aspect-[0.707] w-full items-center justify-center rounded-md border border-border bg-white p-8 shadow-sm">
                                    <div class="flex h-full w-full flex-col items-center justify-center gap-3 border border-dashed border-slate-300 bg-slate-50 p-4 text-center">
                                        <Landmark class="size-8 text-primary" />
                                        <span class="text-xs font-semibold text-slate-700">Institutional Baseline</span>
                                        <span class="text-[10px] text-slate-500">{baseline.form_code ?? 'Official Document Shell'}</span>
                                    </div>
                                </div>
                                <div class="flex flex-1 flex-col justify-between gap-3">
                                    <div class="space-y-3">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div class="flex min-w-0 items-start gap-2"><FileText class="mt-0.5 size-5 shrink-0 text-primary" /><div><h3 class="text-sm font-semibold text-foreground">{baseline.title}</h3>{#if baseline.form_code}<p class="mt-1 font-mono text-xs text-muted-foreground">{baseline.form_code}{baseline.revision ? ` · ${baseline.revision}` : ''}</p>{/if}</div></div>
                                            <Badge variant="outline" class="font-mono text-[10px] uppercase">{baseline.format ?? 'DOCX'} · {formatPaperBadge(baseline.paper_size, baseline.orientation)}</Badge>
                                        </div>
                                        <Badge class="bg-emerald-700 text-white hover:bg-emerald-700">Official Active Institutional Base</Badge>
                                        {#if baseline.description}<p class="text-xs text-muted-foreground">{baseline.description}</p>{/if}
                                        <div class="space-y-1.5 rounded-lg bg-muted/40 p-3">
                                            <p class="text-xs font-semibold text-foreground">Pinned shell specifications</p>
                                            <ul class="grid grid-cols-1 gap-1.5 text-xs text-muted-foreground sm:grid-cols-2">
                                                {#each baselineSpecs(baseline) as spec (spec)}<li class="flex items-center gap-1.5"><Check class="size-3.5 text-emerald-600" />{spec}</li>{/each}
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <Button type="button" variant="outline" size="sm" class="gap-1.5" onclick={() => (selectedBaselineSpec = baseline)}><Eye class="size-3.5" />View Field Spec</Button>
                                        <a href={baseline.download_url} class="inline-flex h-8 items-center justify-center gap-1.5 rounded-md bg-primary px-3 text-xs font-semibold text-primary-foreground hover:opacity-90"><Download class="size-3.5" />Download Shell (.docx)</a>
                                    </div>
                                </div>
                            </article>
                        {/each}
                    </div>
                {:else}
                    <p class="rounded-lg border border-dashed border-border p-6 text-center text-sm text-muted-foreground">No institutional shells match your search.</p>
                {/if}
            </section>
        {/if}


        {#if uploadInfoOpen}
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <section role="dialog" aria-modal="true" aria-labelledby="shell-upload-heading" class="w-full max-w-lg rounded-xl border border-border bg-card p-5 shadow-xl">
                    <div class="mb-3 flex items-start justify-between gap-3"><div><h2 id="shell-upload-heading" class="text-lg font-semibold">Register a Word Shell</h2><p class="mt-1 text-sm text-muted-foreground">Shell registration requires institutional review before a DOCX can become an approved baseline.</p></div><Button type="button" variant="ghost" size="icon" aria-label="Close" onclick={() => (uploadInfoOpen = false)}><X class="size-4" /></Button></div>
                    <p class="rounded-lg border border-dashed border-border bg-muted/30 p-4 text-sm text-muted-foreground"><FileUp class="mr-2 inline size-4" />Upload and approval are not available in this view. Contact your institutional document administrator to register a new shell.</p>
                    <div class="mt-4 flex justify-end"><Button type="button" onclick={() => (uploadInfoOpen = false)}>Close</Button></div>
                </section>
            </div>
        {/if}

        {#if selectedBaselineSpec}
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                <section role="dialog" aria-modal="true" aria-labelledby="baseline-spec-heading" class="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-xl border border-border bg-card p-5 shadow-xl">
                    <div class="mb-3 flex items-start justify-between gap-3"><div><h2 id="baseline-spec-heading" class="text-lg font-semibold">Field Specification</h2><p class="mt-1 text-sm text-muted-foreground">{selectedBaselineSpec.title}</p></div><Button type="button" variant="ghost" size="icon" aria-label="Close" onclick={() => (selectedBaselineSpec = null)}><X class="size-4" /></Button></div>
                    <ul class="space-y-2 text-sm">{#each baselineSpecs(selectedBaselineSpec) as spec (spec)}<li class="flex items-center gap-2"><Check class="size-4 text-emerald-600" />{spec}</li>{/each}</ul>
                    <div class="mt-5 flex justify-end"><Button type="button" onclick={() => (selectedBaselineSpec = null)}>Close</Button></div>
                </section>
            </div>
        {/if}

        {#if offices.length === 0 && flat_variants.length === 0 && custom_documents.length === 0}
            <p class="rounded-xl border border-dashed border-border px-6 py-10 text-center text-sm text-muted-foreground">No report surfaces are available.</p>
        {:else if selectedDesk !== 'baselines' && filteredSurfaceSections.length === 0 && filteredCustomDocuments.length === 0}
            <p class="rounded-xl border border-dashed border-border px-6 py-10 text-center text-sm text-muted-foreground">No templates match the current filters.</p>
        {/if}
    </div>

    <DocumentPrintManagerModal
        bind:open={printModalOpen}
        template={selectedTemplate}
        attemptIds={[]}
        sessionId={null}
        academicCycleId={null}
        onClose={() => {
            printModalOpen = false;
            selectedTemplate = null;
        }}
    />
</StaffLayout>
