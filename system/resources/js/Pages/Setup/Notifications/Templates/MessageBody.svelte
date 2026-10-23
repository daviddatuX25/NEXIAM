<script lang="ts">
    import { extractTokens, insertToken } from '@/lib/noticeMessageBody';

    type VariableToken = { token: string; label?: string };
    type EmailBlock = Record<string, unknown>;
    type ImageAsset = { asset_id: string; preview_url: string; alt?: string };

    let {
        text,
        tokens,
        readOnly = false,
        onTextChange,
        existingBlocks = [],
        assets = [],
        onImageReplace,
        onBlockRemove,
        onAltChange,
    }: {
        text: string;
        tokens: VariableToken[];
        readOnly?: boolean;
        onTextChange: (newText: string) => void;
        existingBlocks?: EmailBlock[];
        assets?: ImageAsset[];
        onImageReplace?: (index: number) => void;
        onBlockRemove?: (index: number) => void;
        onAltChange?: (index: number, alt: string) => void;
    } = $props();

    let textarea: HTMLTextAreaElement;
    let selection: { start: number; end: number } | null = null;
    const insertedTokens = $derived(extractTokens(text));
    const retainedBlocks = $derived(existingBlocks
        .map((block, index) => ({ block, index }))
        .filter(({ block }) => block.type !== 'paragraph' && block.type !== 'heading'));

    function rememberSelection(): void {
        if (textarea) selection = { start: textarea.selectionStart, end: textarea.selectionEnd };
    }

    function addVariable(token: string): void {
        const cursor = selection?.start ?? text.length;
        const insertion = insertToken(text, token, cursor);
        onTextChange(insertion.newText);
        selection = { start: insertion.newCursor, end: insertion.newCursor };
        requestAnimationFrame(() => {
            textarea?.focus();
            textarea?.setSelectionRange(insertion.newCursor, insertion.newCursor);
        });
    }

    function updateText(value: string): void {
        onTextChange(value);
        selection = textarea ? { start: textarea.selectionStart, end: textarea.selectionEnd } : null;
    }
</script>

<div class="space-y-4">
    <section class="space-y-2" aria-label="Available email variables">
        <p class="text-sm font-medium">Available variables (click to insert):</p>
        <div class="flex flex-wrap gap-2">
            {#each tokens as item (item.token)}
                <button type="button" class="min-h-10 rounded-full border border-border bg-background px-3 text-sm hover:bg-muted disabled:cursor-not-allowed disabled:opacity-60" disabled={readOnly} onclick={() => addVariable(item.token)}>
                    + {item.label ?? item.token}
                </button>
            {/each}
            {#if tokens.length === 0}<p class="text-sm text-muted-foreground">No variables are available for this notice.</p>{/if}
        </div>
    </section>

    <label class="block space-y-2">
        <span class="text-sm font-medium">Email message</span>
        <textarea
            bind:this={textarea}
            class="min-h-64 w-full resize-y rounded-md border border-input bg-background p-4 font-sans leading-relaxed"
            aria-label="Email message body"
            placeholder="Write your email message here. Use variables above to personalize."
            readonly={readOnly}
            value={text}
            onselect={rememberSelection}
            onclick={rememberSelection}
            onkeyup={rememberSelection}
            oninput={(event) => updateText(event.currentTarget.value)}
        ></textarea>
        <span class="text-xs text-muted-foreground">{insertedTokens.length} {insertedTokens.length === 1 ? 'variable' : 'variables'} inserted.</span>
    </label>

    {#if retainedBlocks.length}
        <section class="space-y-3" aria-label="Existing email elements">
            {#each retainedBlocks as { block, index } (index)}
                <article class="flex flex-col gap-3 rounded-lg border border-border bg-muted/20 p-3 sm:flex-row sm:items-center">
                    {#if block.type === 'image'}
                        {@const asset = assets.find((candidate) => candidate.asset_id === String(block.asset_id))}
                        {#if asset}<img class="max-h-36 max-w-full rounded object-contain sm:max-w-48" src={asset.preview_url} alt={String(block.alt ?? asset.alt ?? '')} />{:else}<div class="grid min-h-24 w-full place-items-center rounded bg-muted text-sm text-muted-foreground sm:max-w-48">Image preview unavailable</div>{/if}
                        <label class="block space-y-1">
                            <span class="text-xs font-medium">Image alt text</span>
                            <input
                                aria-label="Image alt text"
                                class="min-h-10 w-full rounded border border-input bg-background px-2 text-sm"
                                disabled={readOnly}
                                value={String(block.alt ?? asset?.alt ?? '')}
                                oninput={(e) => onAltChange?.(index, e.currentTarget.value)}
                            />
                        </label>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="min-h-10 rounded-md border px-3 text-sm" disabled={readOnly || !onImageReplace} onclick={() => onImageReplace?.(index)}>Replace image</button>
                            <button type="button" class="min-h-10 rounded-md border border-destructive/40 px-3 text-sm text-destructive" disabled={readOnly || !onBlockRemove} onclick={() => onBlockRemove?.(index)}>Remove</button>
                        </div>
                    {:else}
                        <div class="min-w-0 flex-1"><p class="font-medium">{String(block.type ?? 'Email element')}</p><p class="text-sm text-muted-foreground">This existing element is preserved while you edit the message text.</p></div>
                        <button type="button" class="min-h-10 rounded-md border border-destructive/40 px-3 text-sm text-destructive" disabled={readOnly || !onBlockRemove} onclick={() => onBlockRemove?.(index)}>Remove</button>
                    {/if}
                </article>
            {/each}
        </section>
    {/if}
</div>
