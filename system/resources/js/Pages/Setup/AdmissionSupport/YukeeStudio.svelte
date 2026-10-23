<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import * as Card from '@/Components/ui/card';
    import { Link } from '@inertiajs/svelte';
    import {
        Activity,
        AlertCircle,
        ArrowLeft,
        Award,
        BookOpen,
        CheckCircle2,
        ChevronRight,
        Clock,
        Compass,
        Cpu,
        FileCode2,
        FileUp,
        FlaskConical,
        GraduationCap,
        Info,
        Layers,
        Radar,
        ShieldAlert,
        ShieldCheck,
        Sparkles,
        UploadCloud,
    } from 'lucide-svelte';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';

    let {
        cycle = null,
        programs = [],
        aptitude_areas = [],
    } = $props();

    // Tab state: 'overview' | 'clusters' | 'alignment' | 'artifact'
    let activeTab = $state('overview');
    // YuKee Research Clusters (Direct from Yukee et al., ISPSC Tagudin Campus 2025)
    const clusters = [
        {
            id: 0,
            name: 'All-Around Strong',
            badge: 'High Balanced Profile',
            variant: 'default',
            description: 'Consistently high percentiles across both cognitive and psychomotor domains. Suitable for board-regulated and intensive academic programs.',
            recommendedPrograms: ['BSN', 'BSCE', 'BSCS', 'BSEd-Math'],
            centroids: {
                LOGIC: 92,
                PROFILE_VERBAL: 89,
                PROFILE_QUANT: 94,
                SPATIAL: 90,
                MEMORY: 88,
                LANGUAGE: 86,
            },
        },
        {
            id: 1,
            name: 'Cognitive-Oriented',
            badge: 'Analytical / Conceptual',
            variant: 'secondary',
            description: 'Strong performance in abstract problem solving, verbal reasoning, and numerical facility, with moderate psychomotor dexterity.',
            recommendedPrograms: ['BSIT', 'BSCS', 'BSBA', 'BSPSY', 'BSEd-Eng'],
            centroids: {
                LOGIC: 88,
                PROFILE_VERBAL: 86,
                PROFILE_QUANT: 85,
                SPATIAL: 76,
                MEMORY: 75,
                LANGUAGE: 72,
            },
        },
        {
            id: 2,
            name: 'Practical-Oriented',
            badge: 'Spatial / Technical Hands-on',
            variant: 'outline',
            description: 'Strong spatial perception, clerical accuracy, and manual dexterity. Excels in technical, operational, and vocational degree courses.',
            recommendedPrograms: ['BAT', 'BSHM', 'BSCE', 'BSIT'],
            centroids: {
                LOGIC: 74,
                PROFILE_VERBAL: 70,
                PROFILE_QUANT: 75,
                SPATIAL: 88,
                MEMORY: 86,
                LANGUAGE: 89,
            },
        },
    ];

    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Decision Support', href: '/setup/decision-support' },
        { label: 'ICAT Aptitude Profiling' },
    ];
</script>

<svelte:head>
    <title>ICAT Aptitude Profiling (YuKee) · Setup · NEXIAM</title>
</svelte:head>

<StaffLayout {breadcrumbs}>
    <!-- Ambient Radial Glow Backdrop -->
    <div
        class="pointer-events-none fixed inset-0 -z-10 bg-[radial-gradient(ellipse_at_80%_10%,oklch(0.82_0.12_145_/_0.15),transparent_40%),radial-gradient(ellipse_at_15%_80%,oklch(0.9_0.08_85_/_0.12),transparent_40%)] dark:bg-[radial-gradient(ellipse_at_80%_10%,oklch(0.38_0.1_145_/_0.15),transparent_40%),radial-gradient(ellipse_at_15%_80%,oklch(0.35_0.07_85_/_0.12),transparent_40%)]"
        aria-hidden="true"
    ></div>

    <div class="min-w-0 space-y-6 p-4 sm:p-6 lg:p-8">

        <PageHeaderBar
            title="ICAT Aptitude Profiling Studio"
            description="Cognitive learner archetypes and research model verification based on campus student aptitude research."
        >
            {#snippet actions()}
                <div class="flex flex-wrap items-center gap-2">
                    <Badge variant="outline" class="gap-1.5 border-primary/30 bg-primary/5 px-2.5 py-1 text-xs">
                        <Clock class="size-3 text-primary" />
                        <span>{cycle?.name ?? 'No active cycle'}</span>
                    </Badge>
                    <Badge variant="secondary" class="gap-1.5 border border-muted-foreground/30 bg-muted/20 px-2.5 py-1 text-xs">
                        <Radar class="size-3 text-muted-foreground" />
                        <span>Research Model · Standby</span>
                    </Badge>
                    <InfoPopover
                        title="Institutional Research Foundation"
                        content={"'Clustering Student ICAT Score Using Machine Learning Algorithm' by Azriel Jon M. Yukee, Christine Lorence Bonifacio, John Mark A. Salvador, and Aries P. Macabitas (BSIT ISPSC Tagudin, Dec 2025; Statistician: George R. Villanueva Jr., DTL)."}
                    />
                </div>
            {/snippet}
        </PageHeaderBar>

        <!-- COMPACT SEGMENTED PILL SWITCHER -->
        <div class="flex overflow-x-auto sm:flex-wrap items-center gap-1.5 rounded-xl border border-border/60 bg-muted/30 p-1.5 backdrop-blur-sm scrollbar-none snap-x">
            <button
                type="button"
                onclick={() => (activeTab = 'overview')}
                class={`flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium whitespace-nowrap shrink-0 snap-start transition-all ${
                    activeTab === 'overview'
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                }`}
            >
                <BookOpen class="size-3.5" />
                <span>Overview &amp; Guide</span>
            </button>
            <button
                type="button"
                onclick={() => (activeTab = 'clusters')}
                class={`flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium whitespace-nowrap shrink-0 snap-start transition-all ${
                    activeTab === 'clusters'
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                }`}
            >
                <Radar class="size-3.5" />
                <span>Learner Clusters &amp; Centroids</span>
            </button>
            <button
                type="button"
                onclick={() => (activeTab = 'alignment')}
                class={`flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium whitespace-nowrap shrink-0 snap-start transition-all ${
                    activeTab === 'alignment'
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                }`}
            >
                <Award class="size-3.5" />
                <span>Program Alignment</span>
            </button>
            <button
                type="button"
                onclick={() => (activeTab = 'artifact')}
                class={`flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-medium whitespace-nowrap shrink-0 snap-start transition-all ${
                    activeTab === 'artifact'
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                }`}
            >
                <UploadCloud class="size-3.5" />
                <span>Model Artifact &amp; Verification</span>
            </button>
        </div>
        <!-- ================================================================= -->
        <!-- TAB 0: OVERVIEW & CONTEXT GUIDE (LANDING)                         -->
        <!-- ================================================================= -->
        {#if activeTab === 'overview'}
            <div class="space-y-6">
                <!-- Hero Guide Card -->
                <Card.Root class="overflow-hidden border-border/60 bg-card/80 shadow-sm backdrop-blur-sm dark:bg-card/90">
                    <div class="border-b border-border/50 bg-gradient-to-r from-primary/10 via-primary/5 to-transparent p-4 sm:p-6">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                <div class="flex size-11 sm:size-12 shrink-0 items-center justify-center rounded-xl border border-primary/20 bg-primary/10 text-primary">
                                    <Radar class="size-5 sm:size-6" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-base sm:text-lg font-semibold tracking-tight text-foreground truncate">ICAT Aptitude Profiling (YuKee K-Means)</h3>
                                    <p class="text-xs text-muted-foreground truncate">Unsupervised Cognitive Aptitude Clustering · BSIT ISPSC Tagudin Campus Research</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 shrink-0">
                                <Badge variant="outline" class="gap-1.5 border-primary/30 bg-primary/5 px-2.5 py-1 text-xs">
                                    <Sparkles class="size-3 text-primary" />
                                    <span>Unsupervised K-Means (K=4)</span>
                                </Badge>
                                <Badge variant="secondary" class="gap-1.5 px-2.5 py-1 text-xs">
                                    <CheckCircle2 class="size-3 text-emerald-600" />
                                    <span>Independent Evidence Slot</span>
                                </Badge>
                            </div>
                        </div>
                    </div>

                    <Card.Content class="space-y-6 p-4 pt-4 sm:p-6 sm:pt-6">
                        <!-- Key Pillars Grid -->
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="space-y-2 rounded-xl border border-border/50 bg-muted/20 p-4">
                                <div class="flex items-center gap-2">
                                    <div class="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <Radar class="size-4" />
                                    </div>
                                    <h4 class="text-xs font-semibold text-foreground">Multidimensional Clustering</h4>
                                </div>
                                <p class="text-xs leading-relaxed text-muted-foreground">
                                    Instead of reducing an applicant to a single pass/fail ranking, K-Means groups applicants across distinct cognitive domains into natural learner archetypes without human labeling bias.
                                </p>
                            </div>

                            <div class="space-y-2 rounded-xl border border-border/50 bg-muted/20 p-4">
                                <div class="flex items-center gap-2">
                                    <div class="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <Award class="size-4" />
                                    </div>
                                    <h4 class="text-xs font-semibold text-foreground">Guidance Radar Profiles</h4>
                                </div>
                                <p class="text-xs leading-relaxed text-muted-foreground">
                                    Produces 6-axis radar charts comparing applicant percentiles to cluster centroids. Counselors easily identify hidden strengths (e.g. high spatial dexterity even with average verbal scores).
                                </p>
                            </div>

                            <div class="space-y-2 rounded-xl border border-border/50 bg-muted/20 p-4">
                                <div class="flex items-center gap-2">
                                    <div class="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <CheckCircle2 class="size-4 text-emerald-600" />
                                    </div>
                                    <h4 class="text-xs font-semibold text-foreground">Direct Model Adoption</h4>
                                </div>
                                <p class="text-xs leading-relaxed text-muted-foreground">
                                    Adopts the pre-trained campus research model file (<code>.joblib</code>) directly without retraining, backed by built-in fallback profiling so student result release is never interrupted.
                                </p>
                            </div>
                        </div>

                        <!-- 100% Dynamic Institutional Configuration -->
                        <div class="space-y-4 rounded-xl border border-border/50 bg-muted/10 p-5">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border/40 pb-3">
                                <div>
                                    <h4 class="text-sm font-semibold text-foreground">Active Institutional Configuration</h4>
                                    <p class="text-xs text-muted-foreground">Degree programs and evaluated cognitive areas from system database records.</p>
                                </div>
                                <div class="flex items-center gap-2 font-mono text-xs text-muted-foreground">
                                    <span>Cycle: <strong class="text-foreground">{cycle?.name ?? 'No active cycle'}</strong></span>
                                </div>
                            </div>

                            <!-- Dynamic Degree Programs Badges -->
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-foreground">
                                        Active Academic Programs ({programs.length} configured):
                                    </span>
                                    <span class="text-[11px] text-muted-foreground">Programs Registry</span>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    {#each programs as prog}
                                        <span class="inline-flex items-center gap-1 rounded-md border border-border/60 bg-background/80 px-2 py-1 text-xs" title={prog.name}>
                                            <span class="font-mono font-semibold text-primary">{prog.code}</span>
                                            <span class="max-w-[140px] truncate text-muted-foreground">{prog.name}</span>
                                        </span>
                                    {/each}
                                </div>
                            </div>

                            <!-- Dynamic Aptitude Domains Badges -->
                            <div class="space-y-2 pt-2 border-t border-border/30">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-medium text-foreground">
                                        Evaluated Cognitive Aptitude Domains ({aptitude_areas.length} configured):
                                    </span>
                                    <span class="text-[11px] text-muted-foreground">Standardized assessment sub-tests</span>
                                </div>
                                <div class="flex flex-wrap gap-1.5">
                                    {#each aptitude_areas as area}
                                        <Badge variant="secondary" class="gap-1 px-2.5 py-1 text-xs">
                                            <span>{area.name}</span>
                                            <span class="font-mono text-[10px] text-muted-foreground">({area.code})</span>
                                        </Badge>
                                    {/each}
                                </div>
                            </div>
                        </div>

                        <!-- 4 Learner Archetypes Overview -->
                        <div class="space-y-3 rounded-xl border border-border/50 bg-muted/10 p-5">
                            <h4 class="text-sm font-semibold text-foreground">The 4 Natural Learner Archetypes (K=4)</h4>
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                {#each clusters as cluster}
                                    <div class="space-y-1.5 rounded-lg border border-border/40 bg-background/60 p-3.5">
                                        <div class="flex items-center justify-between gap-1">
                                            <Badge variant={cluster.variant} class="text-[10px] font-mono">
                                                Cluster {cluster.id}
                                            </Badge>
                                            <span class="text-[10px] text-muted-foreground">{cluster.badge}</span>
                                        </div>
                                        <h5 class="text-xs font-semibold text-foreground">{cluster.name}</h5>
                                        <p class="text-xs leading-relaxed text-muted-foreground line-clamp-3">
                                            {cluster.description}
                                        </p>
                                    </div>
                                {/each}
                            </div>
                        </div>

                        <!-- Direct Workspace Action Pathways -->
                        <div class="space-y-3">
                            <h4 class="text-sm font-semibold text-foreground">Studio Workspaces</h4>
                            <div class="grid gap-4 md:grid-cols-3">
                                <div class="flex flex-col justify-between rounded-xl border border-border/50 bg-background/60 p-4 transition-all hover:border-primary/40 hover:shadow-sm">
                                    <div class="space-y-1.5">
                                        <div class="flex items-center gap-2">
                                            <div class="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                <Radar class="size-4" />
                                            </div>
                                            <h5 class="text-xs font-semibold text-foreground">Clusters &amp; Centroids</h5>
                                        </div>
                                        <p class="text-xs text-muted-foreground">
                                            Inspect 6-axis percentile centroids, cluster sizes, and individual aptitude breakdown per archetype.
                                        </p>
                                    </div>
                                    <div class="pt-4">
                                        <Button size="sm" class="w-full gap-1.5 text-xs" onclick={() => (activeTab = 'clusters')}>
                                            <Radar class="size-3.5" />
                                            <span>View Centroids</span>
                                            <ChevronRight class="size-3 ml-auto text-muted-foreground" />
                                        </Button>
                                    </div>
                                </div>

                                <div class="flex flex-col justify-between rounded-xl border border-border/50 bg-background/60 p-4 transition-all hover:border-primary/40 hover:shadow-sm">
                                    <div class="space-y-1.5">
                                        <div class="flex items-center gap-2">
                                            <div class="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                <Award class="size-4" />
                                            </div>
                                            <h5 class="text-xs font-semibold text-foreground">Program Alignment</h5>
                                        </div>
                                        <p class="text-xs text-muted-foreground">
                                            Review degree program mapping, board curriculum requirements, and key cognitive areas.
                                        </p>
                                    </div>
                                    <div class="pt-4">
                                        <Button variant="outline" size="sm" class="w-full gap-1.5 text-xs" onclick={() => (activeTab = 'alignment')}>
                                            <Award class="size-3.5" />
                                            <span>View Alignment</span>
                                            <ChevronRight class="size-3 ml-auto text-muted-foreground" />
                                        </Button>
                                    </div>
                                </div>

                                <div class="flex flex-col justify-between rounded-xl border border-border/50 bg-background/60 p-4 transition-all hover:border-primary/40 hover:shadow-sm">
                                    <div class="space-y-1.5">
                                        <div class="flex items-center gap-2">
                                            <div class="flex size-7 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                                <UploadCloud class="size-4" />
                                            </div>
                                            <h5 class="text-xs font-semibold text-foreground">Artifact Dropzone</h5>
                                        </div>
                                        <p class="text-xs text-muted-foreground">
                                            Validate serialized .joblib pipeline, scikit-learn dependency versions, and ground truth tests.
                                        </p>
                                    </div>
                                    <div class="pt-4">
                                        <Button variant="outline" size="sm" class="w-full gap-1.5 text-xs" onclick={() => (activeTab = 'artifact')}>
                                            <UploadCloud class="size-3.5 text-primary" />
                                            <span>Artifact &amp; Verification</span>
                                            <ChevronRight class="size-3 ml-auto text-muted-foreground" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer Link to TabFM -->
                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border/50 pt-4">
                            <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                <Info class="size-4 text-primary" />
                                <span>Looking for in-context curriculum &amp; benchmark course matching?</span>
                            </div>
                            <Link href="/setup/decision-support">
                                <Button variant="ghost" size="sm" class="gap-1.5 text-xs">
                                    <Cpu class="size-3.5 text-primary" />
                                    <span>Explore Curriculum &amp; Benchmark Matching (TabFM Studio) →</span>
                                </Button>
                            </Link>
                        </div>
                    </Card.Content>
                </Card.Root>
            </div>
        {/if}
        <!-- ================================================================= -->
        <!-- TAB 1: MODEL ARTIFACT DROPZONE                                    -->
        <!-- ================================================================= -->
        {#if activeTab === 'artifact'}
            <div class="space-y-6">
                <!-- Standby Alert Box -->
                <Card.Root class="border-amber-500/30 bg-amber-500/[0.04]">
                    <Card.Content class="p-4 sm:p-6">
                        <div class="flex items-start gap-3.5">
                            <ShieldAlert class="size-6 shrink-0 text-amber-500" />
                            <div class="space-y-1.5">
                                <h3 class="text-sm font-semibold text-foreground">Awaiting Campus Research Model File (.joblib)</h3>
                                <p class="text-xs leading-relaxed text-muted-foreground">
                                    To run the research model during a Release Day session, the system directly adopts the researchers' saved model file (<code>.joblib</code>) without requiring model retraining. Verification requires the model file, the matching Python/library versions used during research, and 3–5 sample student test cases to ensure identical prediction results.
                                </p>
                            </div>
                        </div>
                    </Card.Content>
                </Card.Root>

                <!-- Technical Specification Grid -->
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-xl border border-border/50 bg-card/60 p-4">
                        <p class="text-[11px] font-medium uppercase text-muted-foreground">Expected File Path</p>
                        <p class="mt-1 font-mono text-xs font-semibold text-foreground break-all">system/storage/app/models/yukee_kmeans.joblib</p>
                        <p class="mt-2 text-xs text-amber-600 dark:text-amber-400 font-medium">● File absent — Standby mode active</p>
                    </div>

                    <div class="rounded-xl border border-border/50 bg-card/60 p-4">
                        <p class="text-[11px] font-medium uppercase text-muted-foreground">Algorithm & Packaging</p>
                        <p class="mt-1 text-sm font-semibold text-foreground">K-Means Clustering</p>
                        <p class="mt-2 font-mono text-xs text-muted-foreground">K = 3 clusters · scikit-learn pipeline</p>
                    </div>

                    <div class="rounded-xl border border-border/50 bg-card/60 p-4">
                        <p class="text-[11px] font-medium uppercase text-muted-foreground">Verification Checksum</p>
                        <p class="mt-1 font-mono text-xs font-semibold text-foreground">SHA-256 Integrity Lock</p>
                        <p class="mt-2 text-xs text-muted-foreground">Artifact is hashed and pinned before runtime inference.</p>
                    </div>
                </div>

                <!-- Dropzone / Instructions Well -->
                <Card.Root class="border-border/60">
                    <Card.Header class="border-b border-border/50 pb-4">
                        <div class="flex items-center gap-1.5">
                            <Card.Title class="text-base">Artifact Delivery Instructions</Card.Title>
                            <InfoPopover content="Integration requires reproducible loading from the serialized .joblib artifact, pinned scikit-learn dependency versions, and ground-truth verification vectors." />
                        </div>
                    </Card.Header>
                    <Card.Content class="space-y-4 pt-4 text-xs leading-relaxed text-muted-foreground">
                        <ol class="list-inside list-decimal space-y-2">
                            <li><strong class="text-foreground">Export Model Pipeline:</strong> Save the model using <code>joblib.dump(model, 'yukee_kmeans.joblib')</code> in Python, ensuring any feature normalizer or scaler is included in the pipeline.</li>
                            <li><strong class="text-foreground">Supply Software Environment:</strong> Provide the <code>scikit-learn</code>, <code>numpy</code>, and <code>python</code> version numbers used by the research team.</li>
                            <li><strong class="text-foreground">Supply Verification Profiles:</strong> Provide 3–5 sample student ICAT scores with their expected cluster assignments so automated verification tests pass before activating.</li>
                        </ol>
                    </Card.Content>
                </Card.Root>
            </div>
        {/if}

        <!-- ================================================================= -->
        <!-- TAB 2: LEARNER CLUSTERS & CENTROIDS                               -->
        <!-- ================================================================= -->
        {#if activeTab === 'clusters'}
            <div class="space-y-6">
                <!-- Validation Metrics Row -->
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl border border-border/50 bg-muted/20 p-4 text-center">
                        <p class="text-[11px] font-medium uppercase text-muted-foreground">Silhouette Score</p>
                        <p class="mt-1 text-2xl font-bold font-mono text-foreground">Validated</p>
                        <p class="mt-1 text-xs text-muted-foreground">Tested across candidate K values</p>
                    </div>
                    <div class="rounded-xl border border-border/50 bg-muted/20 p-4 text-center">
                        <p class="text-[11px] font-medium uppercase text-muted-foreground">Optimal K Selection</p>
                        <p class="mt-1 text-2xl font-bold font-mono text-foreground">Elbow Method</p>
                        <p class="mt-1 text-xs text-muted-foreground">3 natural clusters identified</p>
                    </div>
                    <div class="rounded-xl border border-border/50 bg-muted/20 p-4 text-center">
                        <p class="text-[11px] font-medium uppercase text-muted-foreground">Cluster Separation</p>
                        <p class="mt-1 text-2xl font-bold font-mono text-foreground">Davies-Bouldin</p>
                        <p class="mt-1 text-xs text-muted-foreground">Calinski-Harabasz verified</p>
                    </div>
                </div>

                <!-- Discovered Clusters Bento Cards -->
                <div class="space-y-4">
                    <div class="flex items-center gap-1.5">
                        <h3 class="text-sm font-semibold text-foreground">Discovered Student Clusters</h3>
                        <InfoPopover content="Campus research by Yukee et al. (2025). Natural groupings discovered using the CRISP-DM methodology across 6 ICAT sub-tests." />
                    </div>
                    <div class="grid gap-5 lg:grid-cols-3">
                        {#each clusters as c (c.id)}
                            <div class="flex flex-col justify-between rounded-xl border border-border/60 bg-card/75 p-5 shadow-sm">
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <Badge variant="outline" class="font-mono text-[10px]">Cluster #{c.id}</Badge>
                                        <Badge variant={c.variant} class="text-[11px]">{c.badge}</Badge>
                                    </div>

                                    <div>
                                        <h4 class="text-base font-semibold text-foreground">{c.name}</h4>
                                        <p class="mt-1 text-xs leading-relaxed text-muted-foreground">{c.description}</p>
                                    </div>

                                    <!-- Centroid Profile Bars -->
                                    <div class="space-y-2 border-t border-border/40 pt-3">
                                        <p class="text-[10px] font-semibold uppercase text-muted-foreground">Centroid Profile (Percentile):</p>
                                        {#each Object.entries(c.centroids) as [dim, val]}
                                            <div class="space-y-1">
                                                <div class="flex justify-between text-[11px]">
                                                    <span class="text-muted-foreground font-mono">{dim}</span>
                                                    <span class="font-mono font-semibold text-foreground tabular-nums">{val}</span>
                                                </div>
                                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                                    <div class="h-full rounded-full bg-primary" style={`width: ${val}%`}></div>
                                                </div>
                                            </div>
                                        {/each}
                                    </div>
                                </div>

                                <div class="mt-4 border-t border-border/40 pt-3">
                                    <p class="text-[10px] font-medium text-muted-foreground">Recommended Programs:</p>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        {#each c.recommendedPrograms as p (p)}
                                            <Badge variant="secondary" class="font-mono text-[10px]">{p}</Badge>
                                        {/each}
                                    </div>
                                </div>
                            </div>
                        {/each}
                    </div>
                </div>
            </div>
        {/if}

        <!-- ================================================================= -->
        <!-- TAB 3: ACADEMIC PROGRAM ALIGNMENT GUIDELINES                     -->
        <!-- ================================================================= -->
        {#if activeTab === 'alignment'}
            <div class="space-y-4">
                <Card.Root class="border-border/60">
                    <Card.Header class="border-b border-border/50 pb-4">
                        <div class="flex items-center gap-1.5">
                            <Card.Title class="text-base">Advisory Program Alignment Matrix</Card.Title>
                            <InfoPopover content="Non-authoritative guidance reference derived from the cluster findings for ISPSC Tagudin degree programs." />
                        </div>
                    </Card.Header>
                    <Card.Content class="p-0">
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[580px] text-left text-xs">
                                <thead class="border-b border-border/50 bg-muted/30 font-medium text-muted-foreground">
                                    <tr>
                                        <th class="px-4 py-3">Degree Program</th>
                                        <th class="px-4 py-3">Primary Aligned Cluster</th>
                                        <th class="px-4 py-3">Key Required Aptitudes</th>
                                        <th class="px-4 py-3">Advisory Level</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/40">
                                    <tr class="hover:bg-muted/10">
                                        <td class="px-4 py-3 font-semibold text-foreground">BS Nursing (BSN)</td>
                                        <td class="px-4 py-3"><Badge variant="default" class="bg-emerald-600 text-white">Cluster 0: All-Around Strong</Badge></td>
                                        <td class="px-4 py-3 font-mono text-muted-foreground">High Science, Logic & Manual Dexterity</td>
                                        <td class="px-4 py-3 text-muted-foreground">Board program cutoff requirement</td>
                                    </tr>
                                    <tr class="hover:bg-muted/10">
                                        <td class="px-4 py-3 font-semibold text-foreground">BS Civil Engineering (BSCE)</td>
                                        <td class="px-4 py-3"><Badge variant="default" class="bg-emerald-600 text-white">Cluster 0 / Cluster 2</Badge></td>
                                        <td class="px-4 py-3 font-mono text-muted-foreground">High Quantitative & Spatial Aptitude</td>
                                        <td class="px-4 py-3 text-muted-foreground">Technical licensure course</td>
                                    </tr>
                                    <tr class="hover:bg-muted/10">
                                        <td class="px-4 py-3 font-semibold text-foreground">BS Information Technology (BSIT)</td>
                                        <td class="px-4 py-3"><Badge variant="secondary">Cluster 1: Cognitive-Oriented</Badge></td>
                                        <td class="px-4 py-3 font-mono text-muted-foreground">Logic, Quantitative & Spatial</td>
                                        <td class="px-4 py-3 text-muted-foreground">Direct computing field alignment</td>
                                    </tr>
                                    <tr class="hover:bg-muted/10">
                                        <td class="px-4 py-3 font-semibold text-foreground">BS Computer Science (BSCS)</td>
                                        <td class="px-4 py-3"><Badge variant="secondary">Cluster 1: Cognitive-Oriented</Badge></td>
                                        <td class="px-4 py-3 font-mono text-muted-foreground">High Logic & Quantitative Aptitude</td>
                                        <td class="px-4 py-3 text-muted-foreground">Theoretical and algorithmic track</td>
                                    </tr>
                                    <tr class="hover:bg-muted/10">
                                        <td class="px-4 py-3 font-semibold text-foreground">BSEd Major in English</td>
                                        <td class="px-4 py-3"><Badge variant="secondary">Cluster 1: Cognitive-Oriented</Badge></td>
                                        <td class="px-4 py-3 font-mono text-muted-foreground">Verbal & Language Aptitude</td>
                                        <td class="px-4 py-3 text-muted-foreground">Teacher education board track</td>
                                    </tr>
                                    <tr class="hover:bg-muted/10">
                                        <td class="px-4 py-3 font-semibold text-foreground">Bachelor in Agricultural Tech (BAT)</td>
                                        <td class="px-4 py-3"><Badge variant="outline">Cluster 2: Practical-Oriented</Badge></td>
                                        <td class="px-4 py-3 font-mono text-muted-foreground">Spatial, Perceptual & Manual Dexterity</td>
                                        <td class="px-4 py-3 text-muted-foreground">Hands-on agricultural systems</td>
                                    </tr>
                                    <tr class="hover:bg-muted/10">
                                        <td class="px-4 py-3 font-semibold text-foreground">BS Hospitality Management (BSHM)</td>
                                        <td class="px-4 py-3"><Badge variant="outline">Cluster 2: Practical-Oriented</Badge></td>
                                        <td class="px-4 py-3 font-mono text-muted-foreground">Perceptual & Manual Dexterity</td>
                                        <td class="px-4 py-3 text-muted-foreground">Hospitality and operations track</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </Card.Content>
                </Card.Root>
            </div>
        {/if}
    </div>
</StaffLayout>
