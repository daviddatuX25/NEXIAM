<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import { Link, usePage } from '@inertiajs/svelte';

    const page = usePage();
    const saveError = $derived(page.props.errors?.omr_bundle ?? '');
    const status = $derived(page.props.flash?.status ?? '');

    let templateCode = $state('');
    let templateFile = $state(null);
    let configFile = $state(null);
    let manifestFile = $state(null);
    let referenceFiles = $state([]);
    let saving = $state(false);

    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Test Kits', href: '/setup/exam-materials/test-kits' },
        { label: 'Register bundle' },
    ];
</script>

<StaffLayout>
    <PageHeaderBar
        title="Register Native Bundle"
        description="Upload a verified OMRChecker package containing template definitions, configuration, reference assets, and manifest."
        {breadcrumbs}
    />

    <div class="min-w-0 w-full max-w-5xl space-y-6 p-4 sm:p-6">
        {#if status}<p class="rounded bg-green-50 p-3 text-sm text-green-800">
                {status}
            </p>{/if}
        {#if saveError}<p class="rounded bg-red-50 p-3 text-sm text-red-800">
                {saveError}
            </p>{/if}
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)] items-start">
            <div class="border-border bg-card space-y-5 rounded-xl border p-6 shadow-xs">
                <div class="space-y-1 border-b border-border pb-3">
                    <h2 class="text-sm font-semibold text-foreground">Package Files</h2>
                    <p class="text-xs text-muted-foreground">Select the verified OMRChecker asset bundle files</p>
                </div>
        <form
            method="post"
            action="/setup/omr/bundles"
            enctype="multipart/form-data"
            class="space-y-5"
        >
            <div class="space-y-2">
                <Label for="template_code">Template code</Label>
                <Input
                    id="template_code"
                    name="template_code"
                    placeholder="e.g. REAL-OMR-2026"
                    bind:value={templateCode}
                    required
                />
            </div>

            <div class="space-y-2">
                <Label for="template">template.json</Label>
                <Input
                    id="template"
                    name="template"
                    type="file"
                    accept=".json"
                    bind:files={templateFile}
                    required
                />
            </div>

            <div class="space-y-2">
                <Label for="config">config.json</Label>
                <Input
                    id="config"
                    name="config"
                    type="file"
                    accept=".json"
                    bind:files={configFile}
                    required
                />
            </div>

            <div class="space-y-2">
                <Label for="manifest">manifest.json</Label>
                <Input
                    id="manifest"
                    name="manifest"
                    type="file"
                    accept=".json"
                    bind:files={manifestFile}
                    required
                />
            </div>

            <div class="space-y-2">
                <Label for="references"
                    >Reference assets (one SVG per page)</Label
                >
                <Input
                    id="references"
                    name="references[]"
                    type="file"
                    accept=".svg"
                    multiple
                    bind:files={referenceFiles}
                    required
                />
            </div>

            <div class="flex gap-3">
                <Button type="submit" disabled={saving}
                    >{saving ? 'Registering…' : 'Register bundle'}</Button
                >
                <Button asChild variant="outline">
                    <Link href="/setup/exam-materials/test-kits">Cancel</Link>
                </Button>
            </div>
        </form>
            </div>

            <div class="border-border bg-card space-y-5 rounded-xl border p-6 shadow-xs">
                <div class="space-y-1 border-b border-border pb-3">
                    <h3 class="font-semibold text-sm text-foreground">Bundle Structure</h3>
                    <p class="text-xs text-muted-foreground">Native OMRChecker package guide</p>
                </div>

                <div class="space-y-3 text-sm">
                    <div class="py-1 border-b border-border/50">
                        <span class="font-mono text-xs font-semibold text-foreground">template.json</span>
                        <p class="text-xs text-muted-foreground mt-0.5">Defines bubble grid coordinates, field names, and question mappings.</p>
                    </div>
                    <div class="py-1 border-b border-border/50">
                        <span class="font-mono text-xs font-semibold text-foreground">config.json</span>
                        <p class="text-xs text-muted-foreground mt-0.5">Thresholding, marker detection, and processing parameters.</p>
                    </div>
                    <div class="py-1 border-b border-border/50">
                        <span class="font-mono text-xs font-semibold text-foreground">manifest.json</span>
                        <p class="text-xs text-muted-foreground mt-0.5">Package metadata, versioning, author, and page order.</p>
                    </div>
                    <div class="py-1 border-b border-border/50">
                        <span class="font-mono text-xs font-semibold text-foreground">Reference SVGs</span>
                        <p class="text-xs text-muted-foreground mt-0.5">Normalized vector templates used for optical homography alignment.</p>
                    </div>
                </div>

                <div class="rounded-lg border border-dashed border-border bg-muted/20 p-3.5 text-xs text-muted-foreground leading-relaxed">
                    Native bundles bypass browser-based authoring and are imported directly into the engine as verified canonical assets.
                </div>
            </div>
        </div>
    </div>
</StaffLayout>
