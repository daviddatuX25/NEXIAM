<script lang="ts">
    type EmailRun = Record<string, unknown>;
    let { value = [], label = 'Text', tokens = [], scopeKey = 'message', readOnly = false, onChanged = (_runs: EmailRun[]) => {} } = $props<{
        value: EmailRun[];
        label: string;
        tokens: { token: string; label: string }[];
        scopeKey: string;
        readOnly?: boolean;
        onChanged: (runs: EmailRun[]) => void;
    }>();
    let activeInput: HTMLElement | null = null;
    let activeIndex = -1;
    let linkSelection = $state<{ index: number; start: number; end: number } | null>(null);
    let linkUrl = $state('');
    let status = $state('');

    function selectionOffsets(input: HTMLElement): { start: number; end: number } {
        const selection = window.getSelection();
        if (!selection || selection.rangeCount === 0 || !input.contains(selection.anchorNode)) {
            const end = input.textContent?.length ?? 0;
            return { start: end, end };
        }
        const range = selection.getRangeAt(0);
        const beforeStart = range.cloneRange();
        beforeStart.selectNodeContents(input);
        beforeStart.setEnd(range.startContainer, range.startOffset);
        const beforeEnd = range.cloneRange();
        beforeEnd.selectNodeContents(input);
        beforeEnd.setEnd(range.endContainer, range.endOffset);
        return { start: beforeStart.toString().length, end: beforeEnd.toString().length };
    }

    function change(next: EmailRun[]): void {
        onChanged(next);
        status = '';
    }

    function editText(index: number, text: string): void {
        change(value.map((run, runIndex) => runIndex === index ? { ...run, text } : run));
    }

    function selectToken(index: number, token: string): void {
        change(value.map((run, runIndex) => {
            if (runIndex !== index) return { ...run };
            const changed = { ...run, token };
            delete changed.text;
            return changed;
        }));
    }

    function insertToken(index: number, token: string): void {
        const input = activeInput;
        const run = value[index];
        if (!input || activeIndex !== index || !run || typeof run.text !== 'string') {
            status = 'Focus a text run and choose its insertion point before adding a token.';
            return;
        }
        const { start, end } = selectionOffsets(input);
        const pieces: EmailRun[] = [];
        const prefix = run.text.slice(0, start);
        const selected = run.text.slice(start, end);
        const suffix = run.text.slice(end);
        if (prefix) pieces.push({ ...run, text: prefix });
        const tokenRun = { ...run, token };
        delete tokenRun.text;
        pieces.push(tokenRun);
        if (suffix) pieces.push({ ...run, text: suffix });
        change([...value.slice(0, index), ...pieces, ...value.slice(index + 1)]);
        status = selected ? 'Selected text was replaced by a typed token run.' : 'Typed token run inserted.';
    }

    function formatText(index: number, field: 'bold' | 'italic' | 'underline'): void {
        const run = value[index];
        if (!run || typeof run.text !== 'string') return;
        const input = activeInput;
        const { start, end } = input && activeIndex === index ? selectionOffsets(input) : { start: 0, end: 0 };
        const enabled = !Boolean(run[field]);
        if (start === end) {
            change(value.map((item, runIndex) => runIndex === index ? { ...run, [field]: enabled } : item));
            return;
        }
        const chunks: EmailRun[] = [];
        if (start > 0) chunks.push({ ...run, text: run.text.slice(0, start) });
        chunks.push({ ...run, text: run.text.slice(start, end), [field]: enabled });
        if (end < run.text.length) chunks.push({ ...run, text: run.text.slice(end) });
        change([...value.slice(0, index), ...chunks, ...value.slice(index + 1)]);
    }

    function beginLink(index: number): void {
        const input = activeInput;
        const { start, end } = input ? selectionOffsets(input) : { start: 0, end: 0 };
        if (!input || activeIndex !== index || start === end) {
            status = 'Select text in a text run before adding a link.';
            return;
        }
        linkSelection = { index, start, end };
        linkUrl = String(value[index]?.href ?? value[index]?.link ?? '');
    }

    function applyLink(): void {
        if (!linkSelection || !linkUrl.trim()) {
            status = 'Enter a destination URL for the selected text.';
            return;
        }
        const { index, start, end } = linkSelection;
        const run = value[index];
        if (!run || typeof run.text !== 'string') return;
        const chunks: EmailRun[] = [];
        if (start > 0) chunks.push({ ...run, text: run.text.slice(0, start) });
        chunks.push({ ...run, text: run.text.slice(start, end), href: linkUrl.trim() });
        if (end < run.text.length) chunks.push({ ...run, text: run.text.slice(end) });
        change([...value.slice(0, index), ...chunks, ...value.slice(index + 1)]);
        linkSelection = null;
        linkUrl = '';
        status = 'Link applied to the selected text.';
    }

    function removeRun(index: number): void {
        if (value.length < 2) {
            status = 'Keep at least one text run in this content block.';
            return;
        }
        change(value.filter((_, runIndex) => runIndex !== index));
    }
</script>

<div class="space-y-3" aria-label={label}>
    {#each value as run, index (index)}
        <article class="min-w-0 space-y-2 rounded-lg border border-border p-3">
            {#if typeof run.token === 'string'}
                <label class="block space-y-1.5"><span class="text-sm font-medium">{label} token run {index + 1}</span><select disabled={readOnly} class="min-h-11 w-full rounded-md border border-input bg-background px-3" aria-label={`${label} token run ${index + 1}`} value={run.token} onchange={(event) => selectToken(index, event.currentTarget.value)}>{#each tokens as item}<option value={item.token}>{item.label}</option>{/each}</select></label>
                <p class="text-sm text-muted-foreground">Inserted token: <code>{run.token}</code>. It remains a typed token run.</p>
            {:else}
                <label class="block space-y-1.5"><span class="text-sm font-medium">{label} text run {index + 1}</span><div id={`message-run-${scopeKey}-${index}`} role="textbox" aria-label={`${label} text run ${index + 1}`} aria-multiline="true" aria-readonly={readOnly} contenteditable={readOnly ? 'false' : 'true'} class="min-h-20 w-full whitespace-pre-wrap rounded-md border border-input bg-background p-3" class:font-bold={Boolean(run.bold)} class:italic={Boolean(run.italic)} class:underline={Boolean(run.underline || run.href || run.link)} onfocus={(event) => { activeInput = event.currentTarget; activeIndex = index; }} oninput={(event) => { const text = event.currentTarget.textContent ?? (event.target as HTMLInputElement)?.value ?? ''; if ((event.target as HTMLInputElement)?.value && !event.currentTarget.textContent) { event.currentTarget.textContent = text; } editText(index, text); }}>{String(run.text ?? '')}</div></label>
                <div class="flex flex-wrap gap-2" role="group" aria-label={`Formatting for ${label} text run ${index + 1}`}>
                    <button disabled={readOnly} type="button" class="min-h-10 rounded-md border px-3 text-sm" aria-pressed={Boolean(run.bold)} onclick={() => formatText(index, 'bold')}>Bold</button>
                    <button disabled={readOnly} type="button" class="min-h-10 rounded-md border px-3 text-sm" aria-pressed={Boolean(run.italic)} onclick={() => formatText(index, 'italic')}>Italic</button>
                    <button disabled={readOnly} type="button" class="min-h-10 rounded-md border px-3 text-sm" aria-pressed={Boolean(run.underline)} onclick={() => formatText(index, 'underline')}>Underline</button>
                    <button disabled={readOnly} type="button" class="min-h-10 rounded-md border px-3 text-sm" onclick={() => beginLink(index)}>Link</button>
                    <label class="flex min-h-10 items-center gap-2 rounded-md border px-3 text-sm">Insert token<select disabled={readOnly} aria-label={`Insert token in ${label} text run ${index + 1}`} class="max-w-48 bg-background" value="" onchange={(event) => event.currentTarget.value && insertToken(index, event.currentTarget.value)}><option value="">Choose token</option>{#each tokens as item}<option value={item.token}>{item.label}</option>{/each}</select></label>
                </div>
                {#if linkSelection?.index === index}<div class="flex flex-wrap items-end gap-2"><label class="min-w-0 flex-1 space-y-1.5"><span class="block text-sm font-medium">Link destination URL</span><input disabled={readOnly} type="url" class="min-h-11 w-full rounded-md border border-input bg-background px-3" bind:value={linkUrl} /></label><button disabled={readOnly} type="button" class="min-h-11 rounded-md bg-primary px-4 text-primary-foreground" onclick={applyLink}>Apply link</button><button disabled={readOnly} type="button" class="min-h-11 rounded-md border px-4" onclick={() => { linkSelection = null; linkUrl = ''; }}>Cancel link</button></div>{/if}
            {/if}
            <button disabled={readOnly} type="button" class="min-h-10 text-sm text-destructive underline underline-offset-4" aria-label={`Remove ${label} run ${index + 1}`} onclick={() => removeRun(index)}>Remove run</button>
        </article>
    {/each}
    <div class="flex flex-wrap gap-2"><button disabled={readOnly} type="button" class="min-h-10 rounded-md border px-3 text-sm" onclick={() => onChanged([...value, { text: '' }])}>Add text run</button>{#if tokens.length}<button disabled={readOnly} type="button" class="min-h-10 rounded-md border px-3 text-sm" onclick={() => onChanged([...value, { token: tokens[0].token }])}>Add token run</button>{/if}</div>
    {#if status}<p role="status" class="text-sm text-muted-foreground">{status}</p>{/if}
</div>
