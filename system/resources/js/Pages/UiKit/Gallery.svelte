<script>
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';

    // ui primitives
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import * as Card from '@/Components/ui/card';
    import { Checkbox } from '@/Components/ui/checkbox';
    import * as Command from '@/Components/ui/command';
    import * as Dialog from '@/Components/ui/dialog';
    import { FileUpload } from '@/Components/ui/file-upload';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import * as Popover from '@/Components/ui/popover';
    import * as Select from '@/Components/ui/select';
    import { Separator } from '@/Components/ui/separator';
    import { Switch } from '@/Components/ui/switch';
    import * as Table from '@/Components/ui/table';
    import * as Tabs from '@/Components/ui/tabs';
    import { Textarea } from '@/Components/ui/textarea';
    import { Toggle } from '@/Components/ui/toggle';
    import * as ToggleGroup from '@/Components/ui/toggle-group';

    // presentational composites
    import StatCard from '@/Components/kit/StatCard.svelte';
    import KpiCard from '@/Components/kit/KpiCard.svelte';
    import SimplePagination from '@/Components/kit/SimplePagination.svelte';
    import SwitchableListView from '@/Components/kit/SwitchableListView.svelte';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import PasswordStrengthInput from '@/Components/kit/PasswordStrengthInput.svelte';
    import ActionDropdown from '@/Components/kit/ActionDropdown.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import FilterToolbar from '@/Components/kit/FilterToolbar.svelte';
    import BulkActionBar from '@/Components/kit/BulkActionBar.svelte';
    import ModeBanner from '@/Components/kit/ModeBanner.svelte';
    import ActiveFilterChips from '@/Components/kit/ActiveFilterChips.svelte';
    import GuidePanel from '@/Components/kit/Guide/GuidePanel.svelte';
    import GuideSection from '@/Components/kit/Guide/GuideSection.svelte';
    import GuideNote from '@/Components/kit/Guide/GuideNote.svelte';
    import MarketingHomePreview from '@/Components/kit/samples/MarketingHomePreview.svelte';
    import XiamWalkthroughPreview from '@/Components/kit/samples/XiamWalkthroughPreview.svelte';
    import ImportEnginePreview from '@/Components/kit/samples/ImportEnginePreview.svelte';
    import AdmissionsGateway from '@/Components/public/AdmissionsGateway.svelte';
    import AnnouncementRail from '@/Components/public/AnnouncementRail.svelte';
    import MediaFrame from '@/Components/public/MediaFrame.svelte';
    import ProgramExplorer from '@/Components/public/ProgramExplorer.svelte';
    import StorySection from '@/Components/public/StorySection.svelte';
    import {
        success as toastSuccess,
        error as toastError,
        info as toastInfo,
    } from '@/lib/toast.js';
    import { reveal } from '@/lib/motion.js';

    import {
        Upload,
        Users,
        ClipboardCheck,
        TrendingUp as TrendingUpIcon,
        Settings,
        Trash2,
        Pencil,
        Eye,
        FolderOpen,
        Plus,
        Download,
        CheckCircle,
        XCircle,
        Printer,
    } from 'lucide-svelte';

    const breadcrumbs = [{ label: 'UI Kit' }];

    // --- nav sections (table of contents) ---
    const sections = [
        { id: 'public-foundation', label: 'Public foundation' },
        { id: 'home-sample', label: 'Homepage sample' },
        { id: 'xiam-sample', label: 'Xiam assist' },
        { id: 'import-engine', label: 'Import pipeline' },
        { id: 'locked-set', label: 'Locked start set' },
        { id: 'tokens', label: 'Color tokens' },
        { id: 'typography', label: 'Typography' },
        { id: 'radius-surfaces', label: 'Radius & surfaces' },
        { id: 'layout-chrome', label: 'Layout chrome' },
        { id: 'dark-mode', label: 'Dark mode' },
        { id: 'states', label: 'Page states' },
        { id: 'buttons', label: 'Button' },
        { id: 'badges', label: 'Badge' },
        { id: 'inputs', label: 'Input & Label' },
        { id: 'textarea', label: 'Textarea' },
        { id: 'toggles', label: 'Checkbox / Switch / Toggle' },
        { id: 'select', label: 'Select' },
        { id: 'tabs', label: 'Tabs' },
        { id: 'cards', label: 'Card' },
        { id: 'table', label: 'Table & Pagination' },
        { id: 'dialog', label: 'Dialog' },
        { id: 'popover', label: 'Popover & Info' },
        { id: 'command', label: 'Command' },
        { id: 'file-upload', label: 'File upload' },
        { id: 'separator', label: 'Separator' },
        { id: 'stat-kpi', label: 'StatCard / KpiCard' },
        { id: 'view-mode', label: 'View mode / list' },
        { id: 'password', label: 'Password strength' },
        { id: 'toasts', label: 'Toasts' },
        { id: 'guide', label: 'Guide panels' },
        { id: 'management-list', label: 'Management list page' },
        { id: 'page-header', label: 'Page header actions' },
        { id: 'filter-toolbar', label: 'Filter toolbar' },
        { id: 'bulk-bar', label: 'Bulk action bar' },
        { id: 'mode-banner', label: 'Mode banner' },
        { id: 'action-dropdown', label: 'Action dropdown' },
    ];

    const colorTokens = [
        { name: 'background', className: 'bg-background border' },
        { name: 'foreground', className: 'bg-foreground' },
        { name: 'primary', className: 'bg-primary' },
        { name: 'secondary', className: 'bg-secondary border' },
        { name: 'muted', className: 'bg-muted border' },
        { name: 'accent', className: 'bg-accent border' },
        { name: 'destructive', className: 'bg-destructive' },
        { name: 'success', className: 'bg-success' },
        { name: 'warning', className: 'bg-warning' },
        { name: 'info', className: 'bg-info' },
        { name: 'card', className: 'bg-card border' },
        { name: 'border', className: 'bg-border' },
    ];

    // --- button demo state ---
    let buttonLoading = $state(false);

    function simulateLoading() {
        buttonLoading = true;
        setTimeout(() => (buttonLoading = false), 1500);
    }

    // --- input demo state ---
    let inputValue = $state('registrar.clerk@nexiam.test');
    let invalidValue = $state('not-an-email');
    let textareaValue = $state(
        'Synthetic multi-line demo content for the textarea primitive.',
    );

    // --- checkbox/switch/toggle demo state ---
    let checkboxChecked = $state(true);
    let checkboxIndeterminate = $state(false);
    let switchOn = $state(true);
    let togglePressed = $state(false);
    let toggleGroupValue = $state('bold');

    // --- select demo state ---
    const selectOptions = [
        { value: 'registrar', label: 'Registrar' },
        { value: 'proctor', label: 'Proctor' },
        { value: 'test-administrator', label: 'Test administrator' },
    ];
    let selectValue = $state('registrar');
    const selectLabel = $derived(
        selectOptions.find((o) => o.value === selectValue)?.label ??
            'Select an office…',
    );

    // --- tabs demo state ---
    let tabsValue = $state('overview');

    // --- table / pagination demo data (synthetic) ---
    const tableRows = [
        {
            id: 1,
            name: 'A. Dela Cruz',
            program: 'BS Computer Science',
            status: 'Passed',
        },
        {
            id: 2,
            name: 'B. Santos',
            program: 'BS Accountancy',
            status: 'For review',
        },
        {
            id: 3,
            name: 'C. Reyes',
            program: 'BS Civil Engineering',
            status: 'Failed',
        },
    ];
    const paginationDemoPage2of5 = {
        current_page: 2,
        last_page: 5,
        from: 11,
        to: 20,
        total: 48,
        prev_page_url: '/ui-kit#table',
        next_page_url: '/ui-kit#table',
    };
    const paginationDemoPage1of3 = {
        current_page: 1,
        last_page: 3,
        from: 1,
        to: 10,
        total: 27,
        prev_page_url: null,
        next_page_url: '/ui-kit#table',
    };
    const publicPreview = {
        hero: {
            src: 'https://images.unsplash.com/photo-1498243691581-b145c3f54a5a?auto=format&fit=crop&w=1200&q=80',
            alt: 'A quiet library aisle with tall shelves and warm overhead light',
            source: 'simulated stock photography',
        },
        story: {
            eyebrow: 'Editorial media frame',
            title: 'Public composites keep the institution in view.',
            body: 'This static twin exercises the public media, story, program, admissions, and announcement primitives without calling domain APIs.',
            image: {
                src: 'https://images.unsplash.com/photo-1517486808906-6ca8b3f04846?auto=format&fit=crop&w=1000&q=80',
                alt: 'A group of students smiling together outdoors',
                source: 'simulated stock photography',
            },
            align: 'left',
        },
        programs: [
            {
                code: 'BSN',
                name: 'Bachelor of Science in Nursing',
                discipline: 'Health and human care',
                summary: 'Static preview catalog data.',
            },
            {
                code: 'BSPSY',
                name: 'Bachelor of Science in Psychology',
                discipline: 'Mind and community',
                summary: 'Static preview catalog data.',
            },
        ],
        announcements: [
            {
                eyebrow: 'Preview',
                title: 'Replace with approved campus notices.',
                body: 'The empty state remains available when no notices are published.',
                href: '#public-foundation',
                label: 'Inspect primitive',
            },
        ],
    };

    // --- dialog demo state ---
    let dialogOpen = $state(false);

    // --- command demo state ---
    let commandDialogOpen = $state(false);
    let commandLoading = $state(false);
    const commandItems = [
        'Applications',
        'Exam scheduling',
        'Grading',
        'Reports',
        'Setup',
    ];

    function simulateCommandLoading() {
        commandLoading = true;
        setTimeout(() => (commandLoading = false), 1200);
    }

    // --- file upload demo state ---
    let uploadFiles = $state([]);
    let uploadError = $state('');

    // --- view mode / switchable list demo state ---
    let viewMode = $state('responsive');
    const listRows = [
        { id: 1, label: 'Room 101 — Main building' },
        { id: 2, label: 'Room 204 — Annex' },
        { id: 3, label: 'Room 305 — Science wing' },
    ];

    // --- password strength demo state ---
    let demoPassword = $state('');
    let demoPasswordConfirmation = $state('');

    // --- management list / filter demo (synthetic; no router) ---
    let mgmtSearch = $state('');
    let mgmtStatus = $state('');
    let mgmtSeason = $state('');
    let mgmtDateFrom = $state('');
    let mgmtDateTo = $state('');
    let mgmtViewMode = $state('responsive');
    let mgmtSelected = $state(/** @type {number[]} */ ([1, 3]));

    const mgmtStatusOptions = [
        { value: '', label: 'All statuses' },
        { value: 'pending', label: 'Pending' },
        { value: 'accepted', label: 'Accepted' },
        { value: 'dismissed', label: 'Dismissed' },
    ];
    const mgmtSeasonOptions = [
        { value: '', label: 'Active season' },
        { value: '2026', label: 'AY 2026–2027' },
        { value: '2025', label: 'AY 2025–2026' },
    ];
    const mgmtRows = [
        {
            id: 1,
            name: 'A. Dela Cruz',
            email: 'a.delacruz@example.test',
            status: 'pending',
        },
        {
            id: 2,
            name: 'B. Santos',
            email: 'b.santos@example.test',
            status: 'accepted',
        },
        {
            id: 3,
            name: 'C. Reyes',
            email: 'c.reyes@example.test',
            status: 'pending',
        },
        {
            id: 4,
            name: 'D. Lim',
            email: 'd.lim@example.test',
            status: 'dismissed',
        },
    ];
    const mgmtFiltered = $derived(
        mgmtRows.filter((row) => {
            const q = mgmtSearch.trim().toLowerCase();
            if (q && !`${row.name} ${row.email}`.toLowerCase().includes(q))
                return false;
            if (mgmtStatus && row.status !== mgmtStatus) return false;
            return true;
        }),
    );
    const mgmtStatusLabel = $derived(
        mgmtStatusOptions.find((o) => o.value === mgmtStatus)?.label ??
            'All statuses',
    );
    const mgmtSeasonLabel = $derived(
        mgmtSeasonOptions.find((o) => o.value === mgmtSeason)?.label ??
            'Active season',
    );
    const mgmtChips = $derived(
        [
            mgmtStatus
                ? { id: 'status', label: `Status: ${mgmtStatusLabel}` }
                : null,
            mgmtSeason
                ? { id: 'season', label: `Season: ${mgmtSeasonLabel}` }
                : null,
            mgmtDateFrom
                ? { id: 'from', label: `From: ${mgmtDateFrom}` }
                : null,
            mgmtDateTo ? { id: 'to', label: `To: ${mgmtDateTo}` } : null,
        ].filter(Boolean),
    );
    const mgmtAllSelected = $derived(
        mgmtFiltered.length > 0 &&
            mgmtFiltered.every((r) => mgmtSelected.includes(r.id)),
    );
    const mgmtSomeSelected = $derived(
        mgmtFiltered.some((r) => mgmtSelected.includes(r.id)) &&
            !mgmtAllSelected,
    );

    function mgmtToggleRow(id) {
        mgmtSelected = mgmtSelected.includes(id)
            ? mgmtSelected.filter((x) => x !== id)
            : [...mgmtSelected, id];
    }
    function mgmtToggleAll(checked) {
        const ids = mgmtFiltered.map((r) => r.id);
        mgmtSelected = checked
            ? [...new Set([...mgmtSelected, ...ids])]
            : mgmtSelected.filter((id) => !ids.includes(id));
    }
    function mgmtClearFilters() {
        mgmtSearch = '';
        mgmtStatus = '';
        mgmtSeason = '';
        mgmtDateFrom = '';
        mgmtDateTo = '';
    }
    function mgmtRemoveChip(id) {
        if (id === 'status') mgmtStatus = '';
        if (id === 'season') mgmtSeason = '';
        if (id === 'from') mgmtDateFrom = '';
        if (id === 'to') mgmtDateTo = '';
    }
    function mgmtStatusVariant(status) {
        if (status === 'accepted') return 'success';
        if (status === 'dismissed') return 'danger';
        if (status === 'pending') return 'warning';
        return 'muted';
    }

    // --- action dropdown demo items ---
    const actionItems = [
        {
            label: 'View',
            icon: Eye,
            onclick: () => toastInfo('Demo: view action clicked.'),
        },
        {
            label: 'Edit',
            icon: Pencil,
            onclick: () => toastInfo('Demo: edit action clicked.'),
        },
        {
            label: 'Delete',
            icon: Trash2,
            danger: true,
            method: 'delete',
            onclick: () =>
                toastError(
                    'Demo: delete action confirmed (no data was changed).',
                ),
        },
    ];
</script>

<svelte:head>
    <title>UI kit gallery</title>
</svelte:head>

<AuthenticatedLayout {breadcrumbs}>
    <div class="mx-auto max-w-6xl space-y-10">
        <div>
            <div class="flex flex-wrap items-center gap-3">
                <h1
                    class="text-foreground text-3xl font-semibold tracking-tight"
                >
                    UI kit gallery
                </h1>
                <Badge variant="muted">Presentational only</Badge>
                <Badge variant="outline">Synthetic data</Badge>
                <Badge variant="success">Locked start set</Badge>
            </div>
            <p class="text-muted-foreground mt-2 max-w-3xl">
                Full staff UI suite: locked tokens (IBM Plex Sans, radius
                0.5rem, layered paper + glass), every ported <code
                    >Components/ui/*</code
                >
                primitive and
                <code>Components/kit/*</code> composite, responsive chrome
                notes, and a scoped dark-mode panel. Synthetic data only — no
                domain APIs. Authority:
                <code
                    >docs/greenfield-foundation/evidence/ui-kit-locked-start-set.md</code
                >. Use the header theme toggle for full-app dark; use the Dark
                mode section for side-by-side referral without leaving the page.
            </p>
        </div>

        <!-- table of contents -->
        <nav
            aria-label="Gallery sections"
            class="border-border bg-card rounded-lg border p-4"
        >
            <ul class="flex flex-wrap gap-2 text-sm">
                {#each sections as section (section.id)}
                    <li>
                        <a
                            href="#{section.id}"
                            class="border-border/60 text-muted-foreground hover:border-primary/40 hover:text-foreground inline-flex rounded-md border px-2.5 py-1 transition-colors"
                        >
                            {section.label}
                        </a>
                    </li>
                {/each}
            </ul>
        </nav>

        <!-- Homepage section sample -->
        <section id="home-sample" class="space-y-4" use:reveal={{ y: 10 }}>
            <div>
                <h2 class="text-foreground text-xl font-semibold">
                    Homepage sample
                </h2>
                <p class="text-muted-foreground mt-1 max-w-3xl text-sm">
                    Rebuilt marketing surface (not a port of the weak legacy
                    Home blocks). Uses Motion.dev
                    <code>inView</code> scroll reveals so we can judge real section
                    motion before journey pages ship.
                </p>
            </div>
            <MarketingHomePreview />
        </section>
        <section
            id="public-foundation"
            class="space-y-6"
            use:reveal={{ y: 10 }}
        >
            <div>
                <h2 class="text-foreground text-xl font-semibold">
                    Public foundation
                </h2>
                <p class="text-muted-foreground mt-1 max-w-3xl text-sm">
                    Static reference states for the institution-first public
                    shell. Media is deterministic simulated content;
                    reduced-motion and fallback states are part of the component
                    contract.
                </p>
            </div>
            <div class="grid gap-6 lg:grid-cols-[0.8fr_1.2fr]">
                <MediaFrame
                    image={publicPreview.hero}
                    ratio="aspect-[4/3]"
                    priority
                    showSource
                />
                <StorySection story={publicPreview.story} preview />
            </div>
            <ProgramExplorer programs={publicPreview.programs} preview />
            <AdmissionsGateway
                activeSeason={{
                    academic_year: '2026–2027',
                    is_open: true,
                }}
            />
            <AnnouncementRail announcements={publicPreview.announcements} />
        </section>

        <!-- Xiam companion sample -->
        <section id="xiam-sample" class="space-y-4" use:reveal={{ y: 10 }}>
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-foreground text-xl font-semibold">
                        Xiam Assistant & Office Governance Lab
                    </h2>
                    <Badge variant="outline" class="border-primary/40 text-primary text-xs">
                        Delivered Cuts 0–6 Live Dock
                    </Badge>
                    <Badge variant="outline" class="border-amber-500/40 text-amber-600 dark:text-amber-400 text-xs">
                        Planned Cuts 8–10 Staff Lab
                    </Badge>
                </div>
                <p class="text-muted-foreground max-w-3xl text-sm">
                    Xiam operates as an auxiliary assistant across staff desks and applicant surfaces. Operational controls adhere to strict authority boundaries: office policy can narrow channel availability, but cannot widen domain capabilities or bypass institutional gates.
                </p>
            </div>
            <div
                class="border-border/50 bg-muted/20 text-muted-foreground rounded-xl border p-4 text-sm"
            >
                <p>
                    Test the live runtime helper at the bottom-right floating button, or use the simulated controls below to preview upcoming office channel, usage, quota, and knowledge governance settings.
                </p>
            </div>
            <XiamWalkthroughPreview />
        </section>

        <!-- Next-Gen Import Pipeline Showcase -->
        <section id="import-engine" class="space-y-4" use:reveal={{ y: 14 }}>
            <div class="space-y-1">
                <h2 class="text-foreground text-xl font-semibold">
                    Dynamic Ingestion & Import Pipeline
                </h2>
                <p class="text-muted-foreground text-sm">
                    Interactive prototype: multi-sheet workbook inspector, pre-upload health checks, column mapping, and single-pass All-in-One intake.
                </p>
            </div>
            <ImportEnginePreview />
        </section>

        <!-- Locked start set -->
        <section id="locked-set" class="space-y-4" use:reveal={{ y: 14 }}>
            <h2 class="text-foreground text-xl font-semibold">
                Locked start set
            </h2>
            <Card.Root variant="glass">
                <Card.Header>
                    <Card.Title>Institutional staff chrome</Card.Title>
                    <Card.Description>
                        Trust-first dials 3 / 2 / 5. Change only via founder
                        decision + this gallery + app.css together.
                    </Card.Description>
                </Card.Header>
                <Card.Content>
                    <dl class="grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-foreground font-medium">Sans</dt>
                            <dd class="text-muted-foreground">IBM Plex Sans</dd>
                        </div>
                        <div>
                            <dt class="text-foreground font-medium">Mono</dt>
                            <dd class="text-muted-foreground">
                                IBM Plex Mono (code only)
                            </dd>
                        </div>
                        <div>
                            <dt class="text-foreground font-medium">Radius</dt>
                            <dd class="text-muted-foreground">
                                0.5rem (+ sm/md/lg/xl scale)
                            </dd>
                        </div>
                        <div>
                            <dt class="text-foreground font-medium">
                                Background
                            </dt>
                            <dd class="text-muted-foreground">
                                Layered paper — forest wash + warm bone
                            </dd>
                        </div>
                        <div>
                            <dt class="text-foreground font-medium">
                                Surfaces
                            </dt>
                            <dd class="text-muted-foreground">
                                glass-panel + solid card
                            </dd>
                        </div>
                        <div>
                            <dt class="text-foreground font-medium">
                                Rejected
                            </dt>
                            <dd class="text-muted-foreground">
                                Montserrat, radius 0, meta banners above header
                            </dd>
                        </div>
                    </dl>
                </Card.Content>
            </Card.Root>
        </section>

        <!-- Color tokens -->
        <section id="tokens" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">Color tokens</h2>
            <Card.Root>
                <Card.Content>
                    <div
                        class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6"
                    >
                        {#each colorTokens as token (token.name)}
                            <div class="space-y-2">
                                <div
                                    class="h-14 w-full rounded-lg {token.className}"
                                    aria-hidden="true"
                                ></div>
                                <p
                                    class="text-muted-foreground font-mono text-xs"
                                >
                                    {token.name}
                                </p>
                            </div>
                        {/each}
                    </div>
                </Card.Content>
            </Card.Root>
        </section>

        <!-- Typography -->
        <section id="typography" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">Typography</h2>
            <Card.Root>
                <Card.Content class="space-y-4">
                    <p class="text-4xl font-semibold tracking-tight">
                        Display / page title
                    </p>
                    <p class="text-2xl font-semibold tracking-tight">
                        Section heading
                    </p>
                    <p class="text-xl font-semibold">Card title</p>
                    <p class="text-foreground text-base">
                        Body — IBM Plex Sans at comfortable reading size for
                        staff forms and tables.
                    </p>
                    <p class="text-muted-foreground text-sm">
                        Muted helper / description copy. Keep line length
                        readable on wide layouts.
                    </p>
                    <p
                        class="text-muted-foreground text-xs font-semibold tracking-[0.2em] uppercase"
                    >
                        Eyebrow / brand lockup
                    </p>
                    <p class="font-mono text-sm">
                        Mono tabular: APP-2026-0042 · never for chrome captions
                    </p>
                </Card.Content>
            </Card.Root>
        </section>

        <!-- Radius & surfaces -->
        <section id="radius-surfaces" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">
                Radius & surfaces
            </h2>
            <div class="grid gap-4 md:grid-cols-2">
                <Card.Root>
                    <Card.Header>
                        <Card.Title>Solid card</Card.Title>
                        <Card.Description
                            >Default opaque surface for dense content.</Card.Description
                        >
                    </Card.Header>
                    <Card.Content class="flex flex-wrap gap-3">
                        <div
                            class="border-border bg-muted flex size-16 items-end rounded-sm border p-1 text-[10px]"
                        >
                            sm
                        </div>
                        <div
                            class="border-border bg-muted flex size-16 items-end rounded-md border p-1 text-[10px]"
                        >
                            md
                        </div>
                        <div
                            class="border-border bg-muted flex size-16 items-end rounded-lg border p-1 text-[10px]"
                        >
                            lg
                        </div>
                        <div
                            class="border-border bg-muted flex size-16 items-end rounded-xl border p-1 text-[10px]"
                        >
                            xl
                        </div>
                    </Card.Content>
                </Card.Root>
                <Card.Root variant="glass">
                    <Card.Header>
                        <Card.Title>Glass panel</Card.Title>
                        <Card.Description
                            >Sidebar, header, and elevated overlays — paper
                            shows through.</Card.Description
                        >
                    </Card.Header>
                    <Card.Content>
                        <p class="text-muted-foreground text-sm">
                            Uses <code>.glass-panel</code> (blur + inset
                            highlight). Solid fallback when
                            <code>prefers-reduced-transparency</code> is set.
                        </p>
                    </Card.Content>
                </Card.Root>
            </div>
        </section>

        <!-- Layout chrome -->
        <section id="layout-chrome" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">
                Layout chrome (responsive)
            </h2>
            <Card.Root>
                <Card.Content class="text-muted-foreground space-y-3 text-sm">
                    <p>
                        This page already sits inside <code
                            >AuthenticatedLayout</code
                        >. Header is flush top — never place design meta banners
                        above it. Breakpoints match legacy:
                    </p>
                    <ul class="list-disc space-y-1 pl-5">
                        <li>
                            <strong class="text-foreground">&lt; md</strong> — sidebar
                            off-canvas; hamburger + backdrop
                        </li>
                        <li>
                            <strong class="text-foreground">md+</strong> — fixed
                            <code>w-64</code>
                            sidebar; main <code>md:pl-64</code>
                        </li>
                        <li>
                            <strong class="text-foreground">Header</strong> —
                            <code>h-20</code> glass; breadcrumbs hidden on small screens
                        </li>
                        <li>
                            <strong class="text-foreground">Main</strong> —
                            <code>p-4 lg:p-8</code> scroll region
                        </li>
                    </ul>
                    <p>
                        Resize the browser below the <code>md</code> breakpoint
                        and use the menu button to verify drawer behavior.
                        Static twins:
                        <code
                            >docs/greenfield-foundation/evidence/ui-kit-reference/</code
                        >.
                    </p>
                </Card.Content>
            </Card.Root>
        </section>

        <!-- Dark mode scoped panel -->
        <section id="dark-mode" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">
                Dark mode (scoped referral)
            </h2>
            <p class="text-muted-foreground text-sm">
                Nested <code>.dark</code> island — does not flip the whole app. For
                full chrome dark, use the sun/moon control in the header.
            </p>
            <div class="dark border-border overflow-hidden rounded-xl border">
                <div class="bg-background text-foreground space-y-4 p-4 sm:p-6">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge>Default</Badge>
                        <Badge variant="success">Success</Badge>
                        <Badge variant="warning">Warning</Badge>
                        <Badge variant="muted">Muted</Badge>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button>Primary</Button>
                        <Button variant="outline">Outline</Button>
                        <Button variant="secondary">Secondary</Button>
                        <Button variant="destructive">Destructive</Button>
                    </div>
                    <Card.Root variant="glass">
                        <Card.Header>
                            <Card.Title>Dark glass card</Card.Title>
                            <Card.Description
                                >Same structure as light — tokens swap under
                                .dark.</Card.Description
                            >
                        </Card.Header>
                        <Card.Content class="grid gap-3 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="dark-demo-input">Email</Label>
                                <Input
                                    id="dark-demo-input"
                                    value="registrar.clerk@nexiam.test"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="dark-demo-select">Office</Label>
                                <Select.Root type="single" value="registrar">
                                    <Select.Trigger class="w-full"
                                        >Registrar</Select.Trigger
                                    >
                                    <Select.Content>
                                        <Select.Item
                                            value="registrar"
                                            label="Registrar"
                                        />
                                        <Select.Item
                                            value="proctor"
                                            label="Proctor"
                                        />
                                    </Select.Content>
                                </Select.Root>
                            </div>
                        </Card.Content>
                    </Card.Root>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <StatCard
                            label="Open apps"
                            value="128"
                            icon={FolderOpen}
                        />
                        <StatCard
                            label="Scheduled"
                            value="42"
                            icon={ClipboardCheck}
                        />
                        <StatCard
                            label="Trend"
                            value="+12%"
                            icon={TrendingUpIcon}
                        />
                    </div>
                </div>
            </div>
        </section>

        <!-- Page states -->
        <section id="states" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">
                Page states (patterns)
            </h2>
            <div class="grid gap-4 md:grid-cols-2">
                <Card.Root>
                    <Card.Header>
                        <Card.Title>Empty</Card.Title>
                        <Card.Description
                            >No rows yet — keep actions obvious.</Card.Description
                        >
                    </Card.Header>
                    <Card.Content
                        class="text-muted-foreground py-8 text-center text-sm"
                    >
                        No applications match these filters.
                        <div class="mt-4">
                            <Button variant="outline" size="sm"
                                >Clear filters</Button
                            >
                        </div>
                    </Card.Content>
                </Card.Root>
                <Card.Root>
                    <Card.Header>
                        <Card.Title>Error / denied</Card.Title>
                        <Card.Description
                            >Visible failure — never silent omit.</Card.Description
                        >
                    </Card.Header>
                    <Card.Content class="space-y-2 text-sm">
                        <p class="text-destructive font-medium">
                            You do not have capability to view this list.
                        </p>
                        <p class="text-muted-foreground">
                            GF-21 denial is authoritative; UI only reflects it.
                        </p>
                    </Card.Content>
                </Card.Root>
                <Card.Root>
                    <Card.Header>
                        <Card.Title>Loading</Card.Title>
                        <Card.Description
                            >Prefer skeletons over blank flash.</Card.Description
                        >
                    </Card.Header>
                    <Card.Content class="space-y-2">
                        <div
                            class="bg-muted h-4 w-2/3 animate-pulse rounded-md"
                        ></div>
                        <div
                            class="bg-muted h-4 w-1/2 animate-pulse rounded-md"
                        ></div>
                        <div
                            class="bg-muted h-24 animate-pulse rounded-lg"
                        ></div>
                    </Card.Content>
                </Card.Root>
                <Card.Root>
                    <Card.Header>
                        <Card.Title>Success / conflict</Card.Title>
                        <Card.Description
                            >Toast + inline status where durable.</Card.Description
                        >
                    </Card.Header>
                    <Card.Content class="flex flex-wrap gap-2">
                        <Button
                            size="sm"
                            onclick={() => toastSuccess('Saved (demo).')}
                            >Success toast</Button
                        >
                        <Button
                            size="sm"
                            variant="outline"
                            onclick={() => toastError('Conflict (demo).')}
                            >Conflict toast</Button
                        >
                    </Card.Content>
                </Card.Root>
            </div>
        </section>

        <!-- Button -->
        <section id="buttons" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">Button</h2>
            <Card.Root>
                <Card.Content class="space-y-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <Button variant="default">Default</Button>
                        <Button variant="outline">Outline</Button>
                        <Button variant="secondary">Secondary</Button>
                        <Button variant="ghost">Ghost</Button>
                        <Button variant="destructive">Destructive</Button>
                        <Button variant="link">Link</Button>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <Button size="xs">Extra small</Button>
                        <Button size="sm">Small</Button>
                        <Button size="default">Default size</Button>
                        <Button size="lg">Large</Button>
                        <Button size="icon-xs" aria-label="Settings xs"
                            ><Settings /></Button
                        >
                        <Button size="icon-sm" aria-label="Settings sm"
                            ><Settings /></Button
                        >
                        <Button size="icon" aria-label="Settings"
                            ><Settings /></Button
                        >
                        <Button size="icon-lg" aria-label="Settings lg"
                            ><Settings /></Button
                        >
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <Button variant="outline"
                            ><Upload data-icon="inline-start" /> Upload</Button
                        >
                        <Button variant="secondary"
                            >Continue <Users data-icon="inline-end" /></Button
                        >
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <Button disabled>Disabled</Button>
                        <Button href="/ui-kit#buttons" variant="outline"
                            >As link</Button
                        >
                        <Button
                            onclick={simulateLoading}
                            disabled={buttonLoading}
                        >
                            {buttonLoading ? 'Loading…' : 'Simulate loading'}
                        </Button>
                    </div>
                </Card.Content>
            </Card.Root>
        </section>

        <!-- Badge -->
        <section id="badges" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">Badge</h2>
            <Card.Root>
                <Card.Content class="flex flex-wrap gap-2">
                    <Badge>Default</Badge>
                    <Badge variant="secondary">Secondary</Badge>
                    <Badge variant="destructive">Destructive</Badge>
                    <Badge variant="outline">Outline</Badge>
                    <Badge variant="success">Success</Badge>
                    <Badge variant="warning">Warning</Badge>
                    <Badge variant="danger">Danger</Badge>
                    <Badge variant="muted">Muted</Badge>
                </Card.Content>
            </Card.Root>
        </section>

        <!-- Input & Label -->
        <section id="inputs" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">Input & Label</h2>
            <Card.Root>
                <Card.Content class="grid gap-6 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="demo-input-default">Default</Label>
                        <Input
                            id="demo-input-default"
                            bind:value={inputValue}
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="demo-input-disabled">Disabled</Label>
                        <Input
                            id="demo-input-disabled"
                            value="Cannot edit"
                            disabled
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="demo-input-invalid">Invalid</Label>
                        <Input
                            id="demo-input-invalid"
                            bind:value={invalidValue}
                            aria-invalid={true}
                        />
                        <p class="text-destructive text-sm">
                            Enter a valid email address.
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label for="demo-input-placeholder"
                            >Placeholder only</Label
                        >
                        <Input
                            id="demo-input-placeholder"
                            placeholder="e.g. registrar@nexiam.test"
                        />
                    </div>
                </Card.Content>
            </Card.Root>
        </section>

        <!-- Textarea -->
        <section id="textarea" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">Textarea</h2>
            <Card.Root>
                <Card.Content class="grid gap-6 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="demo-textarea">Default</Label>
                        <Textarea
                            id="demo-textarea"
                            bind:value={textareaValue}
                            rows={4}
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="demo-textarea-disabled">Disabled</Label>
                        <Textarea
                            id="demo-textarea-disabled"
                            value="Read-only demo content."
                            disabled
                            rows={4}
                        />
                    </div>
                </Card.Content>
            </Card.Root>
        </section>

        <!-- Checkbox / Switch / Toggle -->
        <section id="toggles" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">
                Checkbox, Switch, Toggle & Toggle group
            </h2>
            <Card.Root>
                <Card.Content class="space-y-6">
                    <div class="flex flex-wrap items-center gap-6">
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox bind:checked={checkboxChecked} />
                            Checked
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox checked={false} />
                            Unchecked
                        </label>
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox
                                bind:indeterminate={checkboxIndeterminate}
                                checked={false}
                            />
                            Indeterminate
                        </label>
                        <label
                            class="text-muted-foreground flex items-center gap-2 text-sm"
                        >
                            <Checkbox checked disabled />
                            Disabled
                        </label>
                    </div>

                    <div class="flex flex-wrap items-center gap-6">
                        <label class="flex items-center gap-2 text-sm">
                            <Switch bind:checked={switchOn} />
                            {switchOn ? 'On' : 'Off'}
                        </label>
                        <label
                            class="text-muted-foreground flex items-center gap-2 text-sm"
                        >
                            <Switch checked disabled />
                            Disabled (on)
                        </label>
                    </div>

                    <div class="flex flex-wrap items-center gap-6">
                        <Toggle
                            bind:pressed={togglePressed}
                            variant="outline"
                            aria-label="Toggle bold"
                        >
                            {togglePressed ? 'Pressed' : 'Not pressed'}
                        </Toggle>
                        <Toggle pressed disabled variant="outline"
                            >Disabled</Toggle
                        >
                    </div>

                    <div class="space-y-2">
                        <Label>Toggle group (single select)</Label>
                        <ToggleGroup.Root
                            type="single"
                            bind:value={toggleGroupValue}
                            variant="outline"
                            size="sm"
                        >
                            <ToggleGroup.Item value="bold"
                                >Bold</ToggleGroup.Item
                            >
                            <ToggleGroup.Item value="italic"
                                >Italic</ToggleGroup.Item
                            >
                            <ToggleGroup.Item value="underline"
                                >Underline</ToggleGroup.Item
                            >
                        </ToggleGroup.Root>
                        <p class="text-muted-foreground text-sm">
                            Selected: {toggleGroupValue}
                        </p>
                    </div>
                </Card.Content>
            </Card.Root>
        </section>

        <!-- Select -->
        <section id="select" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">Select</h2>
            <Card.Root>
                <Card.Content class="grid gap-6 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Office (default)</Label>
                        <Select.Root type="single" bind:value={selectValue}>
                            <Select.Trigger class="w-56"
                                >{selectLabel}</Select.Trigger
                            >
                            <Select.Content>
                                {#each selectOptions as option (option.value)}
                                    <Select.Item
                                        value={option.value}
                                        label={option.label}
                                    />
                                {/each}
                            </Select.Content>
                        </Select.Root>
                    </div>
                    <div class="space-y-2">
                        <Label>Disabled</Label>
                        <Select.Root type="single" disabled>
                            <Select.Trigger class="w-56"
                                >Locked selection</Select.Trigger
                            >
                            <Select.Content>
                                <Select.Item
                                    value="locked"
                                    label="Locked selection"
                                />
                            </Select.Content>
                        </Select.Root>
                    </div>
                </Card.Content>
            </Card.Root>
        </section>

        <!-- Tabs -->
        <section id="tabs" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">Tabs</h2>
            <Card.Root>
                <Card.Content class="space-y-8">
                    <div>
                        <p
                            class="text-muted-foreground mb-3 text-xs font-medium tracking-wide uppercase"
                        >
                            Line (default) — institutional
                        </p>
                        <Tabs.Root bind:value={tabsValue}>
                            <Tabs.List>
                                <Tabs.Trigger value="overview"
                                    >Overview</Tabs.Trigger
                                >
                                <Tabs.Trigger value="details"
                                    >Details</Tabs.Trigger
                                >
                                <Tabs.Trigger value="disabled" disabled
                                    >Disabled</Tabs.Trigger
                                >
                            </Tabs.List>
                            <Tabs.Content
                                value="overview"
                                class="text-muted-foreground pt-4 text-sm"
                            >
                                Synthetic overview panel content.
                            </Tabs.Content>
                            <Tabs.Content
                                value="details"
                                class="text-muted-foreground pt-4 text-sm"
                            >
                                Synthetic details panel content.
                            </Tabs.Content>
                        </Tabs.Root>
                    </div>
                    <div>
                        <p
                            class="text-muted-foreground mb-3 text-xs font-medium tracking-wide uppercase"
                        >
                            Segmented — optional dense toolbars
                        </p>
                        <Tabs.Root value="a">
                            <Tabs.List variant="default">
                                <Tabs.Trigger value="a">Accepted</Tabs.Trigger>
                                <Tabs.Trigger value="b">Pending</Tabs.Trigger>
                                <Tabs.Trigger value="c">Dismissed</Tabs.Trigger>
                            </Tabs.List>
                        </Tabs.Root>
                    </div>
                </Card.Content>
            </Card.Root>
        </section>

        <!-- Card -->
        <section id="cards" class="space-y-4" use:reveal={{ y: 14 }}>
            <h2 class="text-foreground text-xl font-semibold">Card</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <Card.Root>
                    <Card.Header>
                        <Card.Title>Default card</Card.Title>
                        <Card.Description
                            >Standard elevated surface.</Card.Description
                        >
                        <Card.Action
                            ><Badge variant="outline">Default</Badge
                            ></Card.Action
                        >
                    </Card.Header>
                    <Card.Content>
                        <p class="text-muted-foreground text-sm">
                            Body content area — used for most panel layouts
                            across the kit.
                        </p>
                    </Card.Content>
                    <Card.Footer class="border-t pt-6">
                        <Button size="sm" variant="outline">Action</Button>
                    </Card.Footer>
                </Card.Root>
                <div
                    class="rounded-xl p-5 sm:p-6"
                    style="background-image:
            radial-gradient(ellipse 90% 70% at 0% 0%, oklch(0.88 0.06 145 / 0.55), transparent 55%),
            radial-gradient(ellipse 70% 60% at 100% 100%, oklch(0.92 0.03 95 / 0.45), transparent 50%),
            linear-gradient(180deg, oklch(0.97 0.01 87), oklch(0.94 0.02 145 / 0.9));"
                >
                    <Card.Root variant="glass" class="border-0">
                        <Card.Header>
                            <Card.Title>Glass panel card</Card.Title>
                            <Card.Description>
                                Single quiet edge from <code>.glass-panel</code> only
                                — no second wrapper border.
                            </Card.Description>
                        </Card.Header>
                        <Card.Content>
                            <p class="text-muted-foreground text-sm">
                                Sit glass on layered paper so blur reads; never
                                nest inside another bordered card.
                            </p>
                        </Card.Content>
                    </Card.Root>
                </div>
            </div>
        </section>

        <!-- Table & Pagination -->
        <section id="table" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">
                Table & pagination
            </h2>
            <Card.Root>
                <Card.Content>
                    <Table.Root>
                        <Table.Caption
                            >Synthetic applicant rows — demo data only.</Table.Caption
                        >
                        <Table.Header>
                            <Table.Row>
                                <Table.Head>Name</Table.Head>
                                <Table.Head>Program</Table.Head>
                                <Table.Head>Status</Table.Head>
                            </Table.Row>
                        </Table.Header>
                        <Table.Body>
                            {#each tableRows as row (row.id)}
                                <Table.Row>
                                    <Table.Cell>{row.name}</Table.Cell>
                                    <Table.Cell>{row.program}</Table.Cell>
                                    <Table.Cell>
                                        <Badge
                                            variant={row.status === 'Passed'
                                                ? 'success'
                                                : row.status === 'Failed'
                                                  ? 'destructive'
                                                  : 'muted'}
                                        >
                                            {row.status}
                                        </Badge>
                                    </Table.Cell>
                                </Table.Row>
                            {/each}
                        </Table.Body>
                    </Table.Root>

                    <SimplePagination data={paginationDemoPage2of5} />
                    <SimplePagination
                        data={paginationDemoPage1of3}
                        variant="centered"
                        class="mt-6"
                    />
                </Card.Content>
            </Card.Root>
        </section>

        <!-- Dialog -->
        <section id="dialog" class="space-y-4" use:reveal={{ y: 14 }}>
            <h2 class="text-foreground text-xl font-semibold">Dialog</h2>
            <Card.Root>
                <Card.Content class="flex items-center gap-3">
                    <Button
                        variant="outline"
                        onclick={() => (dialogOpen = true)}>Open dialog</Button
                    >
                    <Dialog.Root bind:open={dialogOpen}>
                        <Dialog.Content class="sm:max-w-md">
                            <Dialog.Header>
                                <Dialog.Title
                                    >Synthetic confirmation</Dialog.Title
                                >
                                <Dialog.Description>
                                    This dialog is presentational only — closing
                                    it changes no data.
                                </Dialog.Description>
                            </Dialog.Header>
                            <Dialog.Footer>
                                <Button
                                    variant="outline"
                                    onclick={() => (dialogOpen = false)}
                                    >Cancel</Button
                                >
                                <Button onclick={() => (dialogOpen = false)}
                                    >Confirm</Button
                                >
                            </Dialog.Footer>
                        </Dialog.Content>
                    </Dialog.Root>
                    <span class="text-muted-foreground text-sm"
                        >Opens via <code>bind:open</code> (no nested button trigger).</span
                    >
                </Card.Content>
            </Card.Root>
        </section>

        <!-- Popover & InfoPopover -->
        <section id="popover" class="space-y-4" use:reveal={{ y: 14 }}>
            <h2 class="text-foreground text-xl font-semibold">
                Popover & info popover
            </h2>
            <Card.Root>
                <Card.Content class="flex flex-wrap items-center gap-6">
                    <Popover.Root>
                        <Popover.Trigger>
                            {#snippet child({ props })}
                                <Button variant="outline" {...props}
                                    >Open popover</Button
                                >
                            {/snippet}
                        </Popover.Trigger>
                        <Popover.Content>
                            <Popover.Header>
                                <Popover.Title>Synthetic popover</Popover.Title>
                                <Popover.Description>
                                    Non-modal contextual content. Inline help
                                    uses the question-mark InfoPopover only.
                                </Popover.Description>
                            </Popover.Header>
                        </Popover.Content>
                    </Popover.Root>

                    <div class="flex items-center gap-2 text-sm">
                        <span>Aptitude area weight</span>
                        <InfoPopover
                            content="Synthetic help text: this value has no effect on any real computation in the gallery. Hover or focus the question mark."
                        />
                    </div>
                </Card.Content>
            </Card.Root>
        </section>

        <!-- Command -->
        <section id="command" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">Command</h2>
            <Card.Root>
                <Card.Content class="space-y-4">
                    <div class="flex items-center gap-3">
                        <Button
                            variant="outline"
                            onclick={() => (commandDialogOpen = true)}
                        >
                            Open command palette
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onclick={simulateCommandLoading}
                        >
                            Simulate loading state
                        </Button>
                    </div>

                    <div class="border-border max-w-md rounded-lg border">
                        <Command.Root>
                            <Command.Input placeholder="Search sections…" />
                            <Command.List>
                                {#if commandLoading}
                                    <Command.Loading>Loading…</Command.Loading>
                                {:else}
                                    <Command.Empty
                                        >No sections found.</Command.Empty
                                    >
                                    <Command.Group heading="Gallery sections">
                                        {#each commandItems as item (item)}
                                            <Command.Item value={item}>
                                                {item}
                                                <Command.Shortcut
                                                    >⌘K</Command.Shortcut
                                                >
                                            </Command.Item>
                                        {/each}
                                    </Command.Group>
                                {/if}
                            </Command.List>
                        </Command.Root>
                    </div>

                    <Command.Dialog
                        bind:open={commandDialogOpen}
                        title="UI kit command palette"
                    >
                        <Command.Input placeholder="Type a section name…" />
                        <Command.List>
                            <Command.Empty>No sections found.</Command.Empty>
                            <Command.Group heading="Gallery sections">
                                {#each commandItems as item (item)}
                                    <Command.Item
                                        value={item}
                                        onSelect={() =>
                                            (commandDialogOpen = false)}
                                    >
                                        {item}
                                    </Command.Item>
                                {/each}
                            </Command.Group>
                        </Command.List>
                    </Command.Dialog>
                </Card.Content>
            </Card.Root>
        </section>

        <!-- File upload -->
        <section id="file-upload" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">File upload</h2>
            <Card.Root>
                <Card.Content class="grid gap-6 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label>Default</Label>
                        <FileUpload
                            label="Upload applicant list"
                            sublabel="Drag and drop or click to browse"
                            accept=".csv,.xlsx"
                            maxSize="5mb"
                            bind:files={uploadFiles}
                            bind:error={uploadError}
                            onfiles={() =>
                                toastInfo(
                                    'Demo: file selected (no upload occurs).',
                                )}
                        />
                        {#if uploadFiles.length > 0}
                            <p class="text-muted-foreground text-sm">
                                Selected: {uploadFiles
                                    .map((f) => f.name)
                                    .join(', ')}
                            </p>
                        {/if}
                    </div>
                    <div class="space-y-2">
                        <Label>Disabled</Label>
                        <FileUpload
                            label="Upload disabled"
                            sublabel="Not accepting files right now"
                            disabled
                        />
                    </div>
                </Card.Content>
            </Card.Root>
        </section>

        <!-- Separator -->
        <section id="separator" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">Separator</h2>
            <Card.Root>
                <Card.Content class="space-y-4">
                    <p class="text-muted-foreground text-sm">
                        Above the horizontal separator.
                    </p>
                    <Separator />
                    <p class="text-muted-foreground text-sm">
                        Below the horizontal separator.
                    </p>
                    <div class="flex h-8 items-center gap-4">
                        <span class="text-sm">Left</span>
                        <Separator orientation="vertical" />
                        <span class="text-sm">Right</span>
                    </div>
                </Card.Content>
            </Card.Root>
        </section>

        <!-- StatCard / KpiCard -->
        <section id="stat-kpi" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">
                StatCard & KpiCard
            </h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <StatCard
                    label="Applicants (synthetic)"
                    value="1,284"
                    icon={Users}
                    trend="up"
                    trendLabel="+12% vs last cycle"
                />
                <StatCard
                    label="Sessions scheduled"
                    value="42"
                    icon={ClipboardCheck}
                    trend="down"
                    trendLabel="-3 vs last week"
                />
                <StatCard
                    label="Avg. score"
                    value="76.4"
                    icon={TrendingUpIcon}
                    trend="neutral"
                    trendLabel="No change"
                    href="#stat-kpi"
                />
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <KpiCard
                    label="Capacity used"
                    value="82%"
                    status="ok"
                    delta={4}
                    deltaLabel="vs last cycle"
                    secondaryItems={[{ label: 'Rooms', value: 12 }]}
                />
                <KpiCard
                    label="Pending grading"
                    value="16"
                    status="warn"
                    delta={2}
                    deltaLabel="new today"
                />
                <KpiCard
                    label="Overdue release"
                    value="3"
                    status="critical"
                    delta={-1}
                    deltaLabel="vs yesterday"
                />
            </div>
        </section>

        <!-- View mode / switchable list -->
        <section id="view-mode" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">
                View mode toggle & switchable list
            </h2>
            <Card.Root>
                <Card.Content>
                    <SwitchableListView bind:viewMode>
                        {#snippet table()}
                            <Table.Root>
                                <Table.Header>
                                    <Table.Row>
                                        <Table.Head>Room</Table.Head>
                                    </Table.Row>
                                </Table.Header>
                                <Table.Body>
                                    {#each listRows as row (row.id)}
                                        <Table.Row>
                                            <Table.Cell>{row.label}</Table.Cell>
                                        </Table.Row>
                                    {/each}
                                </Table.Body>
                            </Table.Root>
                        {/snippet}
                        {#snippet cards()}
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                {#each listRows as row (row.id)}
                                    <Card.Root>
                                        <Card.Content
                                            class="flex items-center gap-2"
                                        >
                                            <FolderOpen
                                                class="text-muted-foreground size-4"
                                            />
                                            {row.label}
                                        </Card.Content>
                                    </Card.Root>
                                {/each}
                            </div>
                        {/snippet}
                    </SwitchableListView>
                </Card.Content>
            </Card.Root>
        </section>

        <!-- Password strength -->
        <section id="password" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">
                Password strength input
            </h2>
            <Card.Root>
                <Card.Content class="max-w-md">
                    <PasswordStrengthInput
                        bind:password={demoPassword}
                        bind:passwordConfirmation={demoPasswordConfirmation}
                    />
                </Card.Content>
            </Card.Root>
        </section>

        <!-- Toasts -->
        <section id="toasts" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">Toast pattern</h2>
            <Card.Root>
                <Card.Content class="flex flex-wrap gap-3">
                    <Button
                        variant="outline"
                        onclick={() =>
                            toastSuccess('Demo: synthetic success toast.')}
                    >
                        Trigger success
                    </Button>
                    <Button
                        variant="outline"
                        onclick={() =>
                            toastError('Demo: synthetic error toast.')}
                    >
                        Trigger error
                    </Button>
                    <Button
                        variant="outline"
                        onclick={() => toastInfo('Demo: synthetic info toast.')}
                    >
                        Trigger info
                    </Button>
                    <p class="text-muted-foreground w-full text-sm">
                        The rendering surface (<code
                            >&lt;ToastManager /&gt;</code
                        >) is already mounted once in
                        <code>AuthenticatedLayout</code>; these buttons just
                        push onto the shared store.
                    </p>
                </Card.Content>
            </Card.Root>
        </section>

        <!-- Guide panels -->
        <section id="guide" class="space-y-4" use:reveal={{ y: 14 }}>
            <h2 class="text-foreground text-xl font-semibold">Guide panels</h2>
            <GuidePanel title="How to read this gallery" defaultOpen={true}>
                <GuideSection title="What this page is">
                    <p>
                        Interactive catalogue of staff primitives and
                        composites. Synthetic data only — nothing here calls a
                        domain API or mutates production state.
                    </p>
                </GuideSection>
                <div class="grid gap-3 sm:grid-cols-3">
                    <GuideNote variant="info" title="Informational">
                        Quiet context for operators — soft tinted surface, no
                        left stripe.
                    </GuideNote>
                    <GuideNote variant="warning" title="Watch this">
                        Use for caution that is not yet a blocking error.
                    </GuideNote>
                    <GuideNote variant="tip" title="Tip">
                        Prefer kit pieces before inventing one-off chrome.
                    </GuideNote>
                </div>
            </GuidePanel>
        </section>

        <!-- Action dropdown -->
        <!-- Management list page (composed) -->
        <section id="management-list" class="space-y-4" use:reveal={{ y: 14 }}>
            <h2 class="text-foreground text-xl font-semibold">
                Management list page
            </h2>
            <p class="text-muted-foreground max-w-3xl text-sm">
                Full composed pattern from legacy Index pages (Applications,
                Users, Exam Scheduling, Release): page header actions,
                responsive filter toolbar, active chips, bulk bar, mode banner,
                and SwitchableListView table/cards with selection. Resize below <code
                    >md</code
                > to exercise the Filters drawer. Synthetic data only — no Inertia
                visits.
            </p>

            <div
                class="border-border bg-card/40 space-y-4 rounded-xl border p-4 sm:p-6"
            >
                <PageHeaderBar
                    description="View and manage synthetic applicants (gallery demo)"
                >
                    {#snippet actions()}
                        <Button variant="outline" class="min-h-[44px] gap-2">
                            <Upload class="h-4 w-4" />
                            <span class="hidden sm:inline">Import</span>
                        </Button>
                        <Button variant="outline" class="min-h-[44px] gap-2">
                            <Download class="h-4 w-4" />
                            <span class="hidden sm:inline">Export</span>
                        </Button>
                        <Button class="min-h-[44px] gap-2">
                            <Plus class="h-4 w-4" />
                            <span class="hidden sm:inline">Create</span>
                        </Button>
                    {/snippet}
                </PageHeaderBar>

                <ModeBanner variant="primary" title="Online mode:">
                    Releasing would notify applicants — demo only, no side
                    effects.
                </ModeBanner>

                <FilterToolbar
                    bind:search={mgmtSearch}
                    searchPlaceholder="Search applicants…"
                    searchLabel="Search"
                >
                    {#snippet desktopFilters()}
                        <div>
                            <label
                                class="text-muted-foreground mb-1 block text-xs font-medium"
                                for="mgmt-status-desk">Status</label
                            >
                            <Select.Root type="single" bind:value={mgmtStatus}>
                                <Select.Trigger
                                    id="mgmt-status-desk"
                                    class="min-h-[40px] w-[150px]"
                                    >{mgmtStatusLabel}</Select.Trigger
                                >
                                <Select.Content>
                                    {#each mgmtStatusOptions as o (o.value)}
                                        <Select.Item
                                            value={o.value}
                                            label={o.label}
                                            >{o.label}</Select.Item
                                        >
                                    {/each}
                                </Select.Content>
                            </Select.Root>
                        </div>
                        <div>
                            <label
                                class="text-muted-foreground mb-1 block text-xs font-medium"
                                for="mgmt-season-desk">Season</label
                            >
                            <Select.Root type="single" bind:value={mgmtSeason}>
                                <Select.Trigger
                                    id="mgmt-season-desk"
                                    class="min-h-[40px] w-[200px]"
                                    >{mgmtSeasonLabel}</Select.Trigger
                                >
                                <Select.Content>
                                    {#each mgmtSeasonOptions as o (o.value)}
                                        <Select.Item
                                            value={o.value}
                                            label={o.label}
                                            >{o.label}</Select.Item
                                        >
                                    {/each}
                                </Select.Content>
                            </Select.Root>
                        </div>
                        <div>
                            <label
                                class="text-muted-foreground mb-1 block text-xs font-medium"
                                for="mgmt-from-desk">From</label
                            >
                            <Input
                                id="mgmt-from-desk"
                                type="date"
                                bind:value={mgmtDateFrom}
                                class="min-h-[40px] max-w-[160px]"
                            />
                        </div>
                        <div>
                            <label
                                class="text-muted-foreground mb-1 block text-xs font-medium"
                                for="mgmt-to-desk">To</label
                            >
                            <Input
                                id="mgmt-to-desk"
                                type="date"
                                bind:value={mgmtDateTo}
                                class="min-h-[40px] max-w-[160px]"
                            />
                        </div>
                    {/snippet}
                    {#snippet mobileFilters()}
                        <div>
                            <label
                                class="mb-1 block text-sm font-medium"
                                for="mgmt-status-mob">Status</label
                            >
                            <Select.Root type="single" bind:value={mgmtStatus}>
                                <Select.Trigger
                                    id="mgmt-status-mob"
                                    class="min-h-[44px] w-full"
                                    >{mgmtStatusLabel}</Select.Trigger
                                >
                                <Select.Content>
                                    {#each mgmtStatusOptions as o (o.value)}
                                        <Select.Item
                                            value={o.value}
                                            label={o.label}
                                            >{o.label}</Select.Item
                                        >
                                    {/each}
                                </Select.Content>
                            </Select.Root>
                        </div>
                        <div>
                            <label
                                class="mb-1 block text-sm font-medium"
                                for="mgmt-season-mob">Season</label
                            >
                            <Select.Root type="single" bind:value={mgmtSeason}>
                                <Select.Trigger
                                    id="mgmt-season-mob"
                                    class="min-h-[44px] w-full"
                                    >{mgmtSeasonLabel}</Select.Trigger
                                >
                                <Select.Content>
                                    {#each mgmtSeasonOptions as o (o.value)}
                                        <Select.Item
                                            value={o.value}
                                            label={o.label}
                                            >{o.label}</Select.Item
                                        >
                                    {/each}
                                </Select.Content>
                            </Select.Root>
                        </div>
                        <div>
                            <span class="mb-1 block text-sm font-medium"
                                >Date range</span
                            >
                            <div class="grid grid-cols-2 gap-2">
                                <Input
                                    type="date"
                                    bind:value={mgmtDateFrom}
                                    class="min-h-[44px]"
                                    aria-label="From date"
                                />
                                <Input
                                    type="date"
                                    bind:value={mgmtDateTo}
                                    class="min-h-[44px]"
                                    aria-label="To date"
                                />
                            </div>
                        </div>
                    {/snippet}
                </FilterToolbar>

                <ActiveFilterChips
                    chips={mgmtChips}
                    onRemove={mgmtRemoveChip}
                    onClearAll={mgmtClearFilters}
                />

                <BulkActionBar
                    count={mgmtSelected.length}
                    hint="Bulk actions apply to the current selection only (demo)."
                    onClear={() => (mgmtSelected = [])}
                >
                    {#snippet actions()}
                        <Button
                            size="sm"
                            class="gap-1.5 bg-green-600 text-white hover:bg-green-700"
                            onclick={() =>
                                toastSuccess('Demo: accept selected.')}
                        >
                            <CheckCircle class="h-3.5 w-3.5" />
                            Accept
                        </Button>
                        <Button
                            size="sm"
                            variant="destructive"
                            class="gap-1.5"
                            onclick={() =>
                                toastError('Demo: dismiss selected.')}
                        >
                            <XCircle class="h-3.5 w-3.5" />
                            Dismiss
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            class="gap-1.5"
                            onclick={() => toastInfo('Demo: print slips.')}
                        >
                            <Printer class="h-3.5 w-3.5" />
                            Print
                        </Button>
                    {/snippet}
                </BulkActionBar>

                <SwitchableListView bind:viewMode={mgmtViewMode}>
                    {#snippet table()}
                        <div
                            class="border-border overflow-x-auto rounded-lg border"
                        >
                            <Table.Root class="w-full min-w-[640px] text-sm">
                                <Table.Header class="bg-muted/50">
                                    <Table.Row>
                                        <Table.Head class="w-10 px-2 py-3">
                                            <Checkbox
                                                checked={mgmtAllSelected}
                                                indeterminate={mgmtSomeSelected}
                                                onCheckedChange={(v) =>
                                                    mgmtToggleAll(!!v)}
                                                aria-label="Select all on page"
                                            />
                                        </Table.Head>
                                        <Table.Head class="px-4 py-3"
                                            >Name</Table.Head
                                        >
                                        <Table.Head class="px-4 py-3"
                                            >Email</Table.Head
                                        >
                                        <Table.Head class="px-4 py-3"
                                            >Status</Table.Head
                                        >
                                        <Table.Head
                                            class="px-4 py-3 text-center"
                                            >Actions</Table.Head
                                        >
                                    </Table.Row>
                                </Table.Header>
                                <Table.Body>
                                    {#each mgmtFiltered as row (row.id)}
                                        <Table.Row>
                                            <Table.Cell class="px-2">
                                                <Checkbox
                                                    checked={mgmtSelected.includes(
                                                        row.id,
                                                    )}
                                                    onCheckedChange={() =>
                                                        mgmtToggleRow(row.id)}
                                                    aria-label="Select {row.name}"
                                                />
                                            </Table.Cell>
                                            <Table.Cell class="px-4 font-medium"
                                                >{row.name}</Table.Cell
                                            >
                                            <Table.Cell
                                                class="text-muted-foreground px-4"
                                                >{row.email}</Table.Cell
                                            >
                                            <Table.Cell class="px-4">
                                                <Badge
                                                    variant={mgmtStatusVariant(
                                                        row.status,
                                                    )}
                                                    class="capitalize"
                                                    >{row.status}</Badge
                                                >
                                            </Table.Cell>
                                            <Table.Cell class="px-4">
                                                <div
                                                    class="flex justify-center gap-1"
                                                >
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        class="h-8 px-2 text-xs"
                                                    >
                                                        <Pencil
                                                            class="mr-1 h-3.5 w-3.5"
                                                        /> Edit
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        class="text-destructive hover:text-destructive h-8 px-2 text-xs"
                                                    >
                                                        <Trash2
                                                            class="mr-1 h-3.5 w-3.5"
                                                        /> Delete
                                                    </Button>
                                                </div>
                                            </Table.Cell>
                                        </Table.Row>
                                    {:else}
                                        <Table.Row>
                                            <Table.Cell
                                                colspan={5}
                                                class="text-muted-foreground px-4 py-10 text-center"
                                            >
                                                No rows match these filters.
                                            </Table.Cell>
                                        </Table.Row>
                                    {/each}
                                </Table.Body>
                            </Table.Root>
                        </div>
                    {/snippet}
                    {#snippet cards()}
                        <div class="mb-3 flex items-center gap-2 md:hidden">
                            <Checkbox
                                checked={mgmtAllSelected}
                                indeterminate={mgmtSomeSelected}
                                onCheckedChange={(v) => mgmtToggleAll(!!v)}
                                aria-label="Select all on page"
                            />
                            <span class="text-muted-foreground text-sm"
                                >Select all on page</span
                            >
                        </div>
                        {#if mgmtFiltered.length > 0}
                            <ul
                                class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                                role="list"
                            >
                                {#each mgmtFiltered as row (row.id)}
                                    <li
                                        class="border-border/80 bg-card flex min-w-0 flex-col gap-3.5 rounded-[1.25rem] border p-5 shadow-sm dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.08)] hover:-translate-y-0.5 hover:border-primary/40 transition-all duration-200"
                                    >
                                        <div class="flex items-start gap-3">
                                            <Checkbox
                                                checked={mgmtSelected.includes(
                                                    row.id,
                                                )}
                                                onCheckedChange={() =>
                                                    mgmtToggleRow(row.id)}
                                                aria-label="Select {row.name}"
                                            />
                                            <div class="min-w-0 flex-1">
                                                <p
                                                    class="truncate font-semibold"
                                                >
                                                    {row.name}
                                                </p>
                                                <p
                                                    class="text-muted-foreground truncate text-sm"
                                                >
                                                    {row.email}
                                                </p>
                                            </div>
                                            <Badge
                                                variant={mgmtStatusVariant(
                                                    row.status,
                                                )}
                                                class="shrink-0 capitalize"
                                                >{row.status}</Badge
                                            >
                                        </div>
                                        <div class="flex gap-2 pt-1 border-t border-border/60">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="min-h-[38px] flex-1 rounded-xl text-xs font-semibold"
                                                >Edit</Button
                                            >
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="text-destructive min-h-[38px] rounded-xl text-xs font-semibold"
                                                >Delete</Button
                                            >
                                        </div>
                                    </li>
                                {/each}
                            </ul>
                        {:else}
                            <p
                                class="text-muted-foreground py-8 text-center text-sm"
                            >
                                No rows match these filters.
                            </p>
                        {/if}
                    {/snippet}
                </SwitchableListView>

                <SimplePagination data={paginationDemoPage1of3} />
            </div>
        </section>

        <!-- Page header actions (isolated) -->
        <section id="page-header" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">
                Page header actions
            </h2>
            <Card.Root>
                <Card.Content class="space-y-4">
                    <PageHeaderBar
                        title="Rooms"
                        description="Short description sits left; actions cluster right."
                    >
                        {#snippet actions()}
                            <Button class="min-h-[44px] gap-2">
                                <Plus class="h-4 w-4" />
                                <span class="hidden sm:inline">Add Room</span>
                            </Button>
                        {/snippet}
                    </PageHeaderBar>
                    <p class="text-muted-foreground text-xs">
                        On narrow viewports only the icon shows (<code
                            >hidden sm:inline</code
                        > on labels) — same as legacy Create/Import/Export.
                    </p>
                </Card.Content>
            </Card.Root>
        </section>

        <!-- Filter toolbar (isolated) -->
        <section id="filter-toolbar" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">
                Filter toolbar
            </h2>
            <Card.Root>
                <Card.Header>
                    <Card.Title>Desktop row / mobile Filters panel</Card.Title>
                    <Card.Description>
                        Search always visible. Extra selects and dates collapse
                        into a Filters <code>details</code> below
                        <code>md</code>.
                    </Card.Description>
                </Card.Header>
                <Card.Content>
                    <FilterToolbar
                        bind:search={mgmtSearch}
                        searchPlaceholder="Search…"
                    >
                        {#snippet desktopFilters()}
                            <Select.Root type="single" bind:value={mgmtStatus}>
                                <Select.Trigger class="min-h-[40px] w-[150px]"
                                    >{mgmtStatusLabel}</Select.Trigger
                                >
                                <Select.Content>
                                    {#each mgmtStatusOptions as o (o.value)}
                                        <Select.Item
                                            value={o.value}
                                            label={o.label}
                                            >{o.label}</Select.Item
                                        >
                                    {/each}
                                </Select.Content>
                            </Select.Root>
                        {/snippet}
                        {#snippet mobileFilters()}
                            <label
                                class="mb-1 block text-sm font-medium"
                                for="iso-status-mob">Status</label
                            >
                            <Select.Root type="single" bind:value={mgmtStatus}>
                                <Select.Trigger
                                    id="iso-status-mob"
                                    class="min-h-[44px] w-full"
                                    >{mgmtStatusLabel}</Select.Trigger
                                >
                                <Select.Content>
                                    {#each mgmtStatusOptions as o (o.value)}
                                        <Select.Item
                                            value={o.value}
                                            label={o.label}
                                            >{o.label}</Select.Item
                                        >
                                    {/each}
                                </Select.Content>
                            </Select.Root>
                        {/snippet}
                    </FilterToolbar>
                    <p class="text-muted-foreground mt-3 text-xs">
                        Wire <code>lib/auto-filter.js</code> debounced/immediate watches
                        on real pages (search debounce ~300ms; selects immediate).
                    </p>
                </Card.Content>
            </Card.Root>
        </section>

        <!-- Bulk bar isolated -->
        <section id="bulk-bar" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">
                Bulk action bar
            </h2>
            <BulkActionBar
                count={3}
                hint="Accept or dismiss pending rows."
                onClear={() => toastInfo('Demo: cleared.')}
            >
                {#snippet actions()}
                    <Button size="sm" class="gap-1.5">Accept All</Button>
                    <Button size="sm" variant="destructive" class="gap-1.5"
                        >Dismiss All</Button
                    >
                {/snippet}
            </BulkActionBar>
            <p class="text-muted-foreground text-xs">
                Hidden when count is 0 (component returns nothing).
            </p>
        </section>

        <!-- Mode banner isolated -->
        <section id="mode-banner" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">Mode banner</h2>
            <div class="space-y-3">
                <ModeBanner variant="info" title="F2F mode:"
                    >Results provided in person. Email delivery disabled.</ModeBanner
                >
                <ModeBanner variant="primary" title="Online mode:"
                    >Portal notification + email on release.</ModeBanner
                >
                <ModeBanner variant="warning" title="Attention:"
                    >Season rolls over in 7 days (demo).</ModeBanner
                >
            </div>
        </section>

        <section id="action-dropdown" class="space-y-4">
            <h2 class="text-foreground text-xl font-semibold">
                Action dropdown
            </h2>
            <Card.Root>
                <Card.Content class="flex items-center justify-between">
                    <span class="text-muted-foreground text-sm"
                        >Row-level actions shell (generic items, no domain
                        binding)</span
                    >
                    <ActionDropdown items={actionItems} confirmDelete={true} />
                </Card.Content>
            </Card.Root>
        </section>
    </div>
</AuthenticatedLayout>
