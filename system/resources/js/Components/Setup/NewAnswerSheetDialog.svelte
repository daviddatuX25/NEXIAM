<script>
    import { Button } from '@/Components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogHeader,
        DialogTitle,
        DialogTrigger,
    } from '@/Components/ui/dialog';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import { useForm } from '@inertiajs/svelte';
    import {
        AlertCircle,
        FileStack,
        Plus,
        QrCode,
        ScanLine,
    } from 'lucide-svelte';

    let { create_url = '' } = $props();
    let open = $state(false);
    const form = useForm({
        code: `ICAT-AS-${new Date().getFullYear()}`,
        name: `ICAT Answersheet ${new Date().getFullYear()}`,
        registration_mode: 'document_bound',
        qr_mode: 'qr_disabled',
        requested_page_count: 1,
    });

    const registrationOptions = [
        {
            value: 'document_bound',
            title: 'Standard Paper / Document Boundary',
            description:
                'Detects the physical paper edges. Best for ordinary printing and photocopying.',
            badge: 'Recommended',
        },
        {
            value: 'fiducial_anchored',
            title: 'High-Precision Corner Markers',
            description:
                'Prints four corner markers for tightly controlled alignment.',
            badge: 'Pinned',
        },
    ];

    const qrOptions = [
        {
            value: 'qr_disabled',
            title: 'No production QR',
            description: 'Clean sheets; staff selects pages during scanning.',
        },
        {
            value: 'qr_signed',
            title: 'Signed production QR',
            description: 'Prints a signed instance and page barcode.',
        },
    ];

    function createDraft(event) {
        event.preventDefault();
        form.post(create_url, {
            preserveScroll: true,
            onSuccess: () => {
                open = false;
                form.reset();
            },
        });
    }
</script>

<Dialog bind:open>
    <DialogTrigger>
        {#snippet child({ props })}
            <Button {...props} class="min-h-11 gap-2">
                <Plus class="size-4" />New Answer Sheet
            </Button>
        {/snippet}
    </DialogTrigger>
    <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
            <DialogTitle>New Answer Sheet</DialogTitle>
            <DialogDescription>
                Set the paper structure before entering Studio. These choices
                control layout, printing, and scanning.
            </DialogDescription>
        </DialogHeader>

        <form class="space-y-5" onsubmit={createDraft}>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-1.5">
                    <Label for="new-sheet-code">Reference code</Label>
                    <Input
                        id="new-sheet-code"
                        placeholder={`e.g. ICAT-AS-${new Date().getFullYear()}`}
                        bind:value={form.code}
                        class="font-mono text-xs uppercase"
                        aria-invalid={Boolean(form.errors.code)}
                        required
                    />
                </div>
                <div class="space-y-1.5">
                    <Label for="new-sheet-name">Answer Sheet name</Label>
                    <Input
                        id="new-sheet-name"
                        placeholder={`e.g. ICAT Answersheet ${new Date().getFullYear()}`}
                        bind:value={form.name}
                        aria-invalid={Boolean(form.errors.name)}
                        required
                    />
                </div>
            </div>

            <fieldset class="space-y-2">
                <legend class="flex items-center gap-2 text-sm font-semibold">
                    <ScanLine class="text-primary size-4" />
                    Paper registration
                </legend>
                <div class="grid gap-2">
                    {#each registrationOptions as option}
                        <label
                            class={[
                                'cursor-pointer rounded-xl border p-3 transition-colors',
                                form.registration_mode === option.value
                                    ? 'border-primary bg-primary/5 ring-primary/30 ring-1'
                                    : 'border-border bg-card hover:bg-muted/40',
                            ]}
                        >
                            <span class="flex items-start gap-3">
                                <input
                                    type="radio"
                                    name="registration_mode"
                                    value={option.value}
                                    bind:group={form.registration_mode}
                                    class="accent-primary mt-1 size-4"
                                />
                                <span class="min-w-0 flex-1">
                                    <span
                                        class="flex flex-wrap items-center justify-between gap-2"
                                    >
                                        <span class="text-sm font-semibold"
                                            >{option.title}</span
                                        >
                                        <span
                                            class="bg-muted text-muted-foreground rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wide uppercase"
                                            >{option.badge}</span
                                        >
                                    </span>
                                    <span
                                        class="text-muted-foreground mt-1 block text-xs leading-relaxed"
                                        >{option.description}</span
                                    >
                                </span>
                            </span>
                        </label>
                    {/each}
                </div>
            </fieldset>

            <fieldset class="space-y-2">
                <legend class="flex items-center gap-2 text-sm font-semibold">
                    <QrCode class="text-primary size-4" />
                    Barcode and page tracking
                </legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    {#each qrOptions as option}
                        <label
                            class={[
                                'cursor-pointer rounded-xl border p-3 transition-colors',
                                form.qr_mode === option.value
                                    ? 'border-primary bg-primary/5 ring-primary/30 ring-1'
                                    : 'border-border bg-card hover:bg-muted/40',
                            ]}
                        >
                            <span class="flex items-start gap-2">
                                <input
                                    type="radio"
                                    name="qr_mode"
                                    value={option.value}
                                    bind:group={form.qr_mode}
                                    class="accent-primary mt-0.5 size-4"
                                />
                                <span>
                                    <span class="block text-sm font-semibold"
                                        >{option.title}</span
                                    >
                                    <span
                                        class="text-muted-foreground mt-1 block text-xs leading-relaxed"
                                        >{option.description}</span
                                    >
                                </span>
                            </span>
                        </label>
                    {/each}
                </div>
            </fieldset>

            <fieldset class="space-y-2">
                <legend class="flex items-center gap-2 text-sm font-semibold">
                    <FileStack class="text-primary size-4" />
                    Page count
                </legend>
                <div class="grid grid-cols-4 gap-2">
                    {#each [1, 2, 3, 4] as pageCount}
                        <label
                            class={[
                                'cursor-pointer rounded-lg border px-2 py-2.5 text-center text-xs font-semibold transition-colors',
                                form.requested_page_count === pageCount
                                    ? 'border-primary bg-primary/10 text-primary ring-primary/30 ring-1'
                                    : 'border-border bg-card text-muted-foreground hover:bg-muted/40 hover:text-foreground',
                            ]}
                        >
                            <input
                                class="sr-only"
                                type="radio"
                                name="requested_page_count"
                                value={pageCount}
                                bind:group={form.requested_page_count}
                            />
                            {pageCount}
                            {pageCount === 1 ? 'Page' : 'Pages'}
                        </label>
                    {/each}
                </div>
                <p class="text-muted-foreground text-xs leading-relaxed">
                    {form.requested_page_count === 1
                        ? 'Single-page sheet: design in Visual Designer, or upload a 1-page PDF, Word, or camera photo.'
                        : `Multi-page sheet (${form.requested_page_count} pages): design across all ${form.requested_page_count} pages in Visual Designer, or upload an existing ${form.requested_page_count}-page PDF / Word document.`}
                </p>
            </fieldset>

            {#if Object.keys(form.errors).length}
                <div
                    role="alert"
                    class="border-destructive/40 bg-destructive/8 text-destructive flex items-start gap-2 rounded-lg border p-3 text-sm"
                >
                    <AlertCircle class="mt-0.5 size-4 shrink-0" />
                    <span>{Object.values(form.errors)[0]}</span>
                </div>
            {/if}

            <DialogFooter>
                <Button type="submit" disabled={form.processing}>
                    {form.processing
                        ? 'Creating Answer Sheet…'
                        : 'Create and open Studio'}
                </Button>
            </DialogFooter>
        </form>
    </DialogContent>
</Dialog>
