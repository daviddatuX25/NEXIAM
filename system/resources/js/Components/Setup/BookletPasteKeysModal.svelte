<script>
    import { Button } from '@/Components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogHeader,
        DialogTitle,
    } from '@/Components/ui/dialog';
    import { AlertTriangle, CheckCircle2, KeyRound } from 'lucide-svelte';

    let {
        open = false,
        subtest = null,
        startNumber = 1,
        onClose = () => {},
        onApply = () => {},
    } = $props();

    let input = $state('');

    const items = $derived(subtest?.items ?? []);
    const tokens = $derived(input.trim() ? input.trim().split(/[\s,]+/) : []);
    const preview = $derived.by(() => {
        const mappedTokens = {};
        let invalidToken = null;

        for (let index = 0; index < tokens.length; index += 1) {
            const questionNumber = Number(startNumber) + index;
            const item = items.find((candidate) => Number(candidate.item_number) === questionNumber);

            if (!item) {
                invalidToken = { token: tokens[index], questionNumber };
                break;
            }

            if (!(item.option_tokens ?? []).includes(tokens[index])) {
                invalidToken = { token: tokens[index], questionNumber };
                break;
            }

            mappedTokens[item.id] = tokens[index];
        }

        return { mappedTokens, invalidToken, count: Object.keys(mappedTokens).length };
    });

    const isValid = $derived(tokens.length > 0 && !preview.invalidToken);

    function close() {
        input = '';
        onClose();
    }

    function handleOpenChange(value) {
        if (!value) {
            close();
        }
    }

    function applyKeys() {
        if (!isValid) return;

        onApply(preview.mappedTokens);
        close();
    }
</script>

<Dialog {open} onOpenChange={handleOpenChange}>
    <DialogContent class="flex max-h-[90dvh] flex-col gap-0 overflow-hidden rounded-2xl border border-border/80 bg-background p-0 shadow-2xl sm:max-w-xl">
        <div class="border-b border-border/60 bg-muted/20 px-5 py-4 sm:px-6">
            <DialogHeader class="space-y-1 text-left">
                <DialogTitle class="flex items-center gap-2 text-base font-bold tracking-tight text-foreground">
                    <KeyRound class="size-4 shrink-0 text-primary" />
                    <span>Paste Answer Keys: {subtest?.name}</span>
                </DialogTitle>
                <DialogDescription class="text-sm leading-relaxed text-muted-foreground">
                    Paste one choice per question, starting at Question {startNumber}.
                </DialogDescription>
            </DialogHeader>
        </div>

        <div class="space-y-4 overflow-y-auto px-5 py-5 sm:px-6">
            <label for="booklet-paste-keys" class="text-sm font-medium text-foreground">
                Answer key tokens
            </label>
            <textarea
                id="booklet-paste-keys"
                bind:value={input}
                rows="6"
                placeholder="Paste answer key tokens separated by spaces, commas, or newlines (e.g. A B C D A-B S D...)"
                class="w-full resize-y rounded-xl border border-input bg-background px-3.5 py-3 font-mono text-sm leading-relaxed text-foreground shadow-sm outline-none placeholder:text-muted-foreground/70 focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/40 dark:bg-slate-950"
            ></textarea>

            <div
                class="flex items-start gap-2.5 rounded-xl border px-3.5 py-3 text-sm {isValid ? 'border-emerald-500/30 bg-emerald-500/5' : 'border-amber-500/30 bg-amber-500/5'}"
                aria-live="polite"
            >
                {#if preview.invalidToken}
                    <AlertTriangle class="mt-0.5 size-4 shrink-0 text-amber-600 dark:text-amber-400" />
                    <p class="text-amber-800 dark:text-amber-200">
                        Warning: '{preview.invalidToken.token}' at Question {preview.invalidToken.questionNumber} is not a valid choice.
                    </p>
                {:else if isValid}
                    <CheckCircle2 class="mt-0.5 size-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
                    <p class="text-emerald-800 dark:text-emerald-200">
                        {preview.count} {preview.count === 1 ? 'key' : 'keys'} detected (Questions {startNumber} to {Number(startNumber) + preview.count - 1}). All tokens valid.
                    </p>
                {:else}
                    <AlertTriangle class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                    <p class="text-muted-foreground">Paste keys to preview token validity.</p>
                {/if}
            </div>
        </div>

        <DialogFooter class="border-t border-border/60 bg-muted/10 px-5 py-4 sm:px-6">
            <Button type="button" variant="outline" onclick={close}>Cancel</Button>
            <Button type="button" onclick={applyKeys} disabled={!isValid}>
                Apply Keys
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
