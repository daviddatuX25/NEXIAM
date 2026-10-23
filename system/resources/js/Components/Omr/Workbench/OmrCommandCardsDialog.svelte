<script lang="ts">
    import { onDestroy, tick, untrack } from 'svelte';
    import QRCode from 'qrcode';
    import { Printer, QrCode } from 'lucide-svelte';
    import * as Dialog from '@/Components/ui/dialog';
    import { OMR_QR_COMMAND_PAYLOADS, type OmrQrCommand } from '@/lib/omrCapturePlugins';

    const cards: ReadonlyArray<{ command: OmrQrCommand; label: string; explanation: string }> = [
        { command: 'CAPTURE', label: 'Capture', explanation: 'Start a capture for the current page.' },
        { command: 'NEXT_PAGE', label: 'Next page', explanation: 'Request the already-authorized next page.' },
        { command: 'NEXT_SET', label: 'Next set', explanation: 'Request an eligible next set.' },
        { command: 'REDO_LAST', label: 'Redo last page', explanation: 'Open desktop confirmation for the accepted page.' },
    ];

    let {
        open = $bindable(false),
        initialSelection = cards.map(({ command }) => command),
        onSelectionChange,
    }: {
        open?: boolean;
        initialSelection?: readonly OmrQrCommand[];
        onSelectionChange?: (selected: readonly OmrQrCommand[]) => void;
    } = $props();
    let selected = $state<OmrQrCommand[]>(cards.filter(({ command }) => initialSelection.includes(command)).map(({ command }) => command));
    let canvasElements: Partial<Record<OmrQrCommand, HTMLCanvasElement>> = {};
    let renderErrors = $state<Partial<Record<OmrQrCommand, string>>>({});
    let rendering = $state(false);
    let previewHtml = $state('');
    let previewError = $state('');
    let printFrame = $state<HTMLIFrameElement | null>(null);
    let previewReady = $state(false);
    let printing = $state(false);
    let previewUrl = $state('');
    let afterPrintHandler: (() => void) | null = null;
    let wasOpen = false;

    function detachAfterPrint(): void {
        if (afterPrintHandler && printFrame?.contentWindow) {
            printFrame.contentWindow.removeEventListener('afterprint', afterPrintHandler);
        }
        afterPrintHandler = null;
    }

    function revokePreviewUrl(): void {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
            previewUrl = '';
        }
    }

    function resetPrintLifecycle(): void {
        detachAfterPrint();
        revokePreviewUrl();
        previewReady = false;
        printing = false;
    }

    onDestroy(resetPrintLifecycle);

    function escapeHtml(value: string): string {
        return value.replace(/[&<>"']/g, (character) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
        })[character] ?? character);
    }

    function setSelected(command: OmrQrCommand, checked: boolean): void {
        selected = checked
            ? [...selected, command]
            : selected.filter((item) => item !== command);
        resetPrintLifecycle();
        previewReady = false;
        previewHtml = '';
        previewError = '';
        onSelectionChange?.(selected);
    }

    function selectAll(): void {
        selected = cards.map(({ command }) => command);
        resetPrintLifecycle();
        previewHtml = '';
        previewReady = false;
        previewError = '';
        onSelectionChange?.(selected);
    }

    function clearSelection(): void {
        selected = [];
        resetPrintLifecycle();
        previewHtml = '';
        previewReady = false;
        previewError = '';
        onSelectionChange?.(selected);
    }

    async function renderCodes(): Promise<void> {
        resetPrintLifecycle();
        rendering = true;
        previewHtml = '';
        previewError = '';
        await tick();
        const currentCanvases = canvasElements;
        for (const card of cards) {
            const canvas = currentCanvases[card.command];
            if (!canvas) continue;
            try {
                await QRCode.toCanvas(canvas, OMR_QR_COMMAND_PAYLOADS[card.command], {
                    width: 220,
                    margin: 4,
                    errorCorrectionLevel: 'M',
                    color: { dark: '#000000', light: '#ffffff' },
                });
                canvas.style.width = '';
                canvas.style.height = '';
                renderErrors[card.command] = undefined;
            } catch {
                renderErrors[card.command] = 'QR code could not be generated. Close and reopen this dialog to try again.';
            }
        }
        renderErrors = { ...renderErrors };
        rendering = false;
    }

    $effect(() => {
        const isOpen = open;
        if (isOpen === wasOpen) return;
        wasOpen = isOpen;
        untrack(() => {
            if (isOpen) {
                void renderCodes();
            } else {
                resetPreview();
                resetPrintLifecycle();
            }
        });
    });

    function preparePreview(): void {
        previewReady = false;
        previewError = '';
        if (rendering || selected.length === 0 || cards.some(({ command }) => selected.includes(command) && renderErrors[command])) {
            previewError = 'Select at least one card and wait for its QR code to finish rendering.';
            return;
        }

        try {
            const selectedCards = cards.filter(({ command }) => selected.includes(command));
            const cardMarkup = selectedCards.map((card) => {
                const canvas = canvasElements[card.command];
                if (!canvas) throw new Error('A selected QR code is not ready yet.');
                const image = canvas.toDataURL('image/png');
                return `<article class="card"><img src="${image}" alt="${escapeHtml(card.label)} QR command"><div><h2>${escapeHtml(card.label)}</h2><p>${escapeHtml(card.explanation)}</p><p class="note">Command card · not an answer sheet or applicant code.</p><code>${escapeHtml(OMR_QR_COMMAND_PAYLOADS[card.command])}</code></div></article>`;
            }).join('');

            previewHtml = `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>OMR command cards</title><style>
                *{box-sizing:border-box}body{margin:0;padding:20px;font:14px/1.45 Arial,sans-serif;color:#111;background:#f4f4f5}
                main{max-width:760px;margin:auto}.card{display:flex;align-items:center;gap:18px;margin:0 0 16px;padding:16px;border:1px solid #777;background:#fff;break-inside:avoid}
                img{width:180px;height:180px;object-fit:contain;flex:none}.card h2{margin:0 0 8px;font-size:20px}.card p{margin:0 0 8px}.note{font-size:12px;font-weight:600}code{display:block;overflow-wrap:anywhere;font-size:12px}
                @page{size:auto;margin:12mm}@media print{body{padding:0;background:#fff}.card{margin-bottom:10mm}}
                @media(max-width:520px){body{padding:10px}.card{align-items:flex-start;gap:12px;padding:10px}img{width:120px;height:120px}}
            </style></head><body><main>${cardMarkup}</main></body></html>`;
            previewUrl = URL.createObjectURL(new Blob([previewHtml], { type: 'text/html;charset=utf-8' }));
        } catch (error) {
            previewError = error instanceof Error ? error.message : 'The printable preview could not be prepared. Try again.';
            previewHtml = '';
        }
    }

    function printPreview(): void {
        const frameWindow = printFrame?.contentWindow;
        if (!previewHtml || !frameWindow) {
            previewError = 'The print preview is not ready. Prepare the preview again.';
            return;
        }

        try {
            detachAfterPrint();
            afterPrintHandler = () => {
                printing = false;
                detachAfterPrint();
            };
            frameWindow.addEventListener('afterprint', afterPrintHandler, { once: true });
            frameWindow.focus();
            printing = true;
            frameWindow.print();
        } catch {
            detachAfterPrint();
            printing = false;
            previewError = 'Your browser could not open the print dialog. Open the printable document in a new tab and use its browser print command.';
        }
    }

    function handlePreviewLoad(): void {
        previewReady = true;
        previewError = '';
    }

    function resetPreview(): void {
        previewHtml = '';
        previewError = '';
        resetPrintLifecycle();
    }
</script>


<Dialog.Root bind:open>
    <Dialog.Content portalProps={{}} class="max-h-[90dvh] w-[calc(100vw-2rem)] max-w-4xl overflow-y-auto sm:max-w-4xl sm:p-6" data-omr-command-cards>
        <Dialog.Header class="text-left">
            <Dialog.Title class="flex items-center gap-2 text-lg font-semibold"><QrCode class="size-5" /> Print command cards</Dialog.Title>
            <Dialog.Description class="text-sm">
                Choose cards to include in your local print preview. These are scanner commands only; they are not answer sheets or applicant codes.
            </Dialog.Description>
        </Dialog.Header>

        <div class="flex flex-wrap items-center justify-between gap-3 no-print">
            <p class="text-muted-foreground text-sm">{selected.length} of {cards.length} cards selected</p>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" onclick={selectAll}>Select all</button>
                <button type="button" class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" onclick={clearSelection}>Clear selection</button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            {#each cards as card (card.command)}
                <article class="command-card rounded-xl border border-border bg-background p-4" class:selected-card={selected.includes(card.command)} data-command={card.command}>
                    <label class="no-print mb-3 flex cursor-pointer items-center gap-2 text-sm font-medium">
                        <input type="checkbox" checked={selected.includes(card.command)} aria-label={`Include ${card.label} card`} onchange={(event) => setSelected(card.command, event.currentTarget.checked)} />
                        Include this card
                    </label>
                    <div class="command-card-content flex flex-col items-start gap-4 lg:flex-row">
                        <div role="img" aria-label={`${card.label} QR command`} class="size-40 shrink-0 sm:size-48">
                            <canvas bind:this={canvasElements[card.command]} width="220" height="220" class="h-full w-full border border-black bg-white p-2" aria-hidden="true"></canvas>
                        </div>
                        <div class="min-w-0 space-y-2">
                            <h3 class="text-lg font-semibold">{card.label}</h3>
                            <p class="text-muted-foreground text-sm">{card.explanation}</p>
                            <p class="text-xs font-medium">Command card · not an answer sheet or applicant code.</p>
                            <code class="block break-all rounded bg-muted px-2 py-1 text-[11px]">{OMR_QR_COMMAND_PAYLOADS[card.command]}</code>
                            {#if renderErrors[card.command]}<p class="text-sm text-destructive" role="alert">{renderErrors[card.command]}</p>{/if}
                        </div>
                    </div>
                </article>
            {/each}
        </div>
        {#if previewHtml}
            <section class="space-y-2 rounded-lg border border-border p-3" aria-label="Printable command card preview">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="font-semibold">Print preview · {selected.length} selected card{selected.length === 1 ? '' : 's'}</h3>
                    <button type="button" class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted" onclick={resetPreview} disabled={printing}>Back to selection</button>
                    <a href={previewUrl} target="_blank" rel="noopener noreferrer" class="rounded-md border border-border px-3 py-2 text-sm hover:bg-muted">Open printable document in new tab</a>
                </div>
                <iframe bind:this={printFrame} title="Printable QR command cards" srcdoc={previewHtml} sandbox="allow-same-origin allow-modals" onload={handlePreviewLoad} class="h-[min(60dvh,36rem)] w-full rounded border border-border bg-white"></iframe>
                <p class="text-muted-foreground text-xs">This preview contains only the selected scanner command cards.</p>
            </section>
        {/if}
        {#if previewError}<p class="text-sm text-destructive" role="alert">{previewError}</p>{/if}

        <Dialog.Footer class="no-print">
            <button type="button" class="rounded-md border border-border px-4 py-2 text-sm hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" onclick={() => (open = false)}>Close</button>
            {#if previewHtml}
                <button type="button" class="bg-primary text-primary-foreground inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-50" onclick={printPreview} disabled={printing || !previewReady}>
                    <Printer class="size-4" /> {printing ? 'Printing…' : 'Print preview'}
                </button>
            {:else}
                <button type="button" class="bg-primary text-primary-foreground inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" onclick={preparePreview} disabled={rendering || selected.length === 0 || cards.some(({ command }) => selected.includes(command) && renderErrors[command])}>
                    <Printer class="size-4" /> Prepare print preview
                </button>
            {/if}
        </Dialog.Footer>
    </Dialog.Content>
</Dialog.Root>

