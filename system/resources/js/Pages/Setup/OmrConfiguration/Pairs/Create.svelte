<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import { Button } from '@/Components/ui/button';
    import { Label } from '@/Components/ui/label';
    import { Link, router } from '@inertiajs/svelte';
    import {
        error as toastError,
        success as toastSuccess,
    } from '@/lib/toast.js';

    let { draft_templates = [], draft_booklets = [], errors = {} } = $props();

    let templateVersionId = $state(draft_templates[0]?.id ?? null);
    let bookletVersionId = $state(draft_booklets[0]?.id ?? null);
    let saving = $state(false);
    let toastedError = false;

    const selectedTemplate = $derived(
        draft_templates.find((template) => template.id === templateVersionId) ??
            null,
    );
    const selectedBooklet = $derived(
        draft_booklets.find((booklet) => booklet.id === bookletVersionId) ??
            null,
    );

    const templateSummary = $derived(
        selectedTemplate
            ? `${selectedTemplate.page_count} pages · ${selectedTemplate.page_width}×${selectedTemplate.page_height} px`
            : '',
    );
    const bookletSummary = $derived(
        selectedBooklet ? `${selectedBooklet.questions_count} questions` : '',
    );

    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Test Kits', href: '/setup/exam-materials/test-kits' },
        { label: 'Activate Pair' },
    ];

    // A failed activation arrives as redirected props; surface it once per page load.
    $effect(() => {
        if (errors.omr_pair && !toastedError) {
            toastError(errors.omr_pair);
            toastedError = true;
        }
    });

    function submit() {
        if (!templateVersionId || !bookletVersionId || saving) return;
        saving = true;
        router.post(
            '/setup/omr/pairs',
            {
                template_version_id: templateVersionId,
                booklet_version_id: bookletVersionId,
            },
            {
                onSuccess: () => {
                    toastSuccess('Pair activated.');
                    router.visit('/setup/exam-materials/test-kits');
                },
                onError: () => {
                    saving = false;
                },
                onFinish: () => {
                    saving = false;
                },
            },
        );
    }
</script>

<StaffLayout {breadcrumbs}>
    <PageHeaderBar
        title="Activate Template–Booklet Pair"
        description="Link an answer-sheet template with a question booklet and activate the pairing for test administration."
        {breadcrumbs}
    />

    <div class="min-w-0 w-full max-w-5xl space-y-6 p-4 sm:p-6">
        {#if errors.omr_pair}
            <div
                class="border-destructive/50 bg-destructive/10 text-destructive rounded-lg border px-4 py-3 text-sm"
            >
                {errors.omr_pair}
            </div>
        {/if}

        {#if draft_templates.length === 0 || draft_booklets.length === 0}
            <div class="rounded-xl border border-border bg-card p-6">
                <h2 class="text-lg font-semibold">Nothing to pair yet</h2>
                <p class="text-muted-foreground mt-2 text-sm">
                    {#if draft_templates.length === 0}
                        No draft template versions are available.
                        <Link
                            href="/setup/exam-materials/answer-sheets"
                            class="text-primary font-medium underline underline-offset-4"
                            >Open Answer Sheet Studio</Link
                        >
                        to create one.
                    {/if}
                    {#if draft_booklets.length === 0}
                        No draft booklet versions are available.
                        <Link
                            href="/setup/omr/booklets"
                            class="text-primary font-medium underline underline-offset-4"
                            >Author booklets</Link
                        >
                        to create one.
                    {/if}
                </p>
            </div>
        {:else}
            <div class="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)] items-start">
                <div class="border-border bg-card space-y-5 rounded-xl border p-6 shadow-xs">
                    <div class="space-y-1 border-b border-border pb-3">
                        <h2 class="text-sm font-semibold text-foreground">Select Assets to Pair</h2>
                        <p class="text-xs text-muted-foreground">Pair an answer-sheet template with a question booklet</p>
                    </div>
                <div class="space-y-2">
                    <Label for="template_version_id">Template</Label>
                    <select
                        id="template_version_id"
                        class="border-input bg-background flex min-h-10 w-full rounded-md border px-3 text-sm"
                        bind:value={templateVersionId}
                    >
                        {#each draft_templates as template (template.id)}
                            <option value={template.id}
                                >{template.label} ({template.code})</option
                            >
                        {/each}
                    </select>
                    <p class="text-muted-foreground text-xs">
                        {templateSummary}
                    </p>
                </div>

                <div class="space-y-2">
                    <Label for="booklet_version_id">Booklet</Label>
                    <select
                        id="booklet_version_id"
                        class="border-input bg-background flex min-h-10 w-full rounded-md border px-3 text-sm"
                        bind:value={bookletVersionId}
                    >
                        {#each draft_booklets as booklet (booklet.id)}
                            <option value={booklet.id}
                                >{booklet.label} ({booklet.code})</option
                            >
                        {/each}
                    </select>
                    <p class="text-muted-foreground text-xs">
                        {bookletSummary}
                    </p>
                </div>

                <div class="flex gap-3 pt-2">
                    <Button
                        onclick={submit}
                        disabled={!templateVersionId ||
                            !bookletVersionId ||
                            saving}
                    >
                        {saving ? 'Activating…' : 'Activate pair'}
                    </Button>
                    <Button asChild variant="outline">
                        <Link href="/setup/exam-materials/test-kits">Cancel</Link>
                    </Button>
                </div>
            </div>

            <div class="border-border bg-card space-y-5 rounded-xl border p-6 shadow-xs">
                <div class="space-y-1 border-b border-border pb-3">
                    <h3 class="font-semibold text-sm text-foreground">Pairing Specifications</h3>
                    <p class="text-xs text-muted-foreground">Asset compatibility overview</p>
                </div>

                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between py-1.5 border-b border-border/50">
                        <span class="text-muted-foreground">Selected Template</span>
                        <span class="font-medium text-foreground text-right">{selectedTemplate?.code ?? 'None'}</span>
                    </div>
                    <div class="flex items-center justify-between py-1.5 border-b border-border/50">
                        <span class="text-muted-foreground">Template Dimensions</span>
                        <span class="font-medium text-foreground text-right">{templateSummary || '—'}</span>
                    </div>
                    <div class="flex items-center justify-between py-1.5 border-b border-border/50">
                        <span class="text-muted-foreground">Selected Booklet</span>
                        <span class="font-medium text-foreground text-right">{selectedBooklet?.code ?? 'None'}</span>
                    </div>
                    <div class="flex items-center justify-between py-1.5 border-b border-border/50">
                        <span class="text-muted-foreground">Question Scope</span>
                        <span class="font-medium text-foreground text-right">{bookletSummary || '—'}</span>
                    </div>
                </div>

                <div class="rounded-lg border border-dashed border-border bg-muted/20 p-3.5 text-xs text-muted-foreground leading-relaxed">
                    Activating a template–booklet pair registers this combination as available for live exam session material selection and OMR sheet generation.
                </div>
            </div>
        </div>
    {/if}
</div>
</StaffLayout>
