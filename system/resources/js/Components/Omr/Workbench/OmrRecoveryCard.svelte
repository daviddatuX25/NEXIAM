<script lang="ts">
    import { Check, Clipboard, LoaderCircle } from 'lucide-svelte';
    import type {
        OmrWorkbenchRecoveryAction,
        OmrWorkbenchRecoveryItem,
    } from '@/lib/omrWorkbench';

    type RecoveryAction = OmrWorkbenchRecoveryAction;
    type RecoveryRecord = Record<string, unknown>;

    type OmrRecoveryActionHandler = (
        action: RecoveryAction,
        recovery: OmrWorkbenchRecoveryItem,
    ) => void | Promise<void>;

    type RecoveryCardProps = {
        recovery: OmrWorkbenchRecoveryItem;
        onAction?: OmrRecoveryActionHandler | null;
        busy?: boolean;
        idPrefix?: string;
        className?: string;
    };

    let {
        recovery,
        onAction = null,
        busy = false,
        idPrefix = 'omr-recovery',
        className = '',
    }: RecoveryCardProps = $props();

    let pendingAction = $state<RecoveryAction | null>(null);
    let copyStatus = $state<'idle' | 'copied' | 'failed'>('idle');
    let actionError = $state<string | null>(null);

    function asRecord(value: unknown): RecoveryRecord {
        if (
            value === null ||
            typeof value !== 'object' ||
            Array.isArray(value)
        ) {
            return {};
        }

        return value as RecoveryRecord;
    }

    function textValue(value: unknown): string | null {
        if (typeof value !== 'string') return null;
        const valueText = value.trim();
        return valueText === '' ? null : valueText;
    }

    function fallbackText(value: unknown, fallback: string): string {
        return textValue(value) ?? fallback;
    }

    function titleCase(value: string): string {
        return value
            .replace(/[_-]+/g, ' ')
            .replace(/\b\w/g, (character) => character.toUpperCase());
    }

    function actionLabel(
        action: RecoveryAction | null,
        explicit: unknown,
    ): string {
        const serverLabel = textValue(explicit);
        if (serverLabel !== null) return serverLabel;
        if (action === null) return '';
        return titleCase(action);
    }

    function technicalText(value: unknown): string | null {
        if (typeof value === 'string') return textValue(value);
        if (
            value === null ||
            value === undefined ||
            typeof value === 'number' ||
            typeof value === 'boolean'
        ) {
            return value === null || value === undefined ? null : String(value);
        }

        try {
            return JSON.stringify(value, null, 2);
        } catch {
            return null;
        }
    }

    function safeId(value: unknown): string {
        const raw = textValue(value) ?? 'card';
        return (
            raw.replace(/[^A-Za-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '') ||
            'card'
        );
    }

    function actionFrom(value: unknown): RecoveryAction | null {
        return textValue(value) as RecoveryAction | null;
    }

    const source = $derived(asRecord(recovery));
    const title = $derived(fallbackText(source.title, 'Review required'));
    const explanation = $derived(
        fallbackText(
            source.message ?? source.explanation,
            'This scan needs staff review before scoring can continue.',
        ),
    );
    const blocked = $derived(
        source.blocked === true || source.blocking === true,
    );
    const owner = $derived(fallbackText(source.owner, 'Responsible staff'));
    const supportCode = $derived(
        textValue(source.support_code) ?? textValue(source.code),
    );
    const technicalDetails = $derived(technicalText(source.technical_detail));
    const primaryAction = $derived(actionFrom(source.primary_action));
    const secondaryAction = $derived(actionFrom(source.secondary_action));
    const primaryLabel = $derived(
        actionLabel(primaryAction, source.primary_label ?? source.primary_text),
    );
    const secondaryLabel = $derived(
        actionLabel(
            secondaryAction,
            source.secondary_label ?? source.secondary_text,
        ),
    );
    const cardId = $derived(
        `${safeId(idPrefix)}-${safeId(source.capture_id ?? supportCode ?? source.code)}`,
    );
    const isBusy = $derived(busy || pendingAction !== null);

    async function invokeAction(action: RecoveryAction | null): Promise<void> {
        if (action === null || isBusy || !onAction) return;

        pendingAction = action;
        actionError = null;
        try {
            await onAction(action, recovery);
        } catch (error) {
            actionError =
                error instanceof Error
                    ? error.message
                    : 'The recovery action could not be completed.';
        } finally {
            pendingAction = null;
        }
    }

    async function copySupportCode(): Promise<void> {
        if (!supportCode) return;

        try {
            if (
                typeof navigator !== 'undefined' &&
                navigator.clipboard &&
                typeof navigator.clipboard.writeText === 'function'
            ) {
                await navigator.clipboard.writeText(supportCode);
            } else if (typeof document !== 'undefined') {
                const input = document.createElement('textarea');
                input.value = supportCode;
                input.setAttribute('readonly', '');
                input.style.position = 'fixed';
                input.style.opacity = '0';
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                input.remove();
            } else {
                throw new Error('Clipboard unavailable');
            }
            copyStatus = 'copied';
        } catch {
            copyStatus = 'failed';
        }

        window.setTimeout(() => {
            copyStatus = 'idle';
        }, 2200);
    }
</script>

<article
    class={`omr-recovery-card border-border bg-card min-w-0 rounded-xl border p-4 shadow-xs sm:p-5 ${className}`}
    data-omr-recovery-card
    data-omr-recovery-blocked={blocked}
    aria-labelledby={`${cardId}-title`}
    aria-describedby={`${cardId}-explanation`}
    role={blocked ? 'alert' : 'region'}
>
    <div
        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
    >
        <div class="min-w-0">
            <h3
                id={`${cardId}-title`}
                class="text-base font-semibold tracking-tight"
            >
                {title}
            </h3>
            <p
                id={`${cardId}-explanation`}
                class="text-muted-foreground mt-1 text-sm leading-relaxed"
            >
                {explanation}
            </p>
            {#if textValue(source.stage)}
                <p class="text-muted-foreground mt-2 text-xs">Stage: {titleCase(textValue(source.stage) ?? '')}</p>
            {/if}
            {#if textValue(source.recovery)}
                <p class="text-foreground mt-1 text-sm leading-relaxed">{textValue(source.recovery)}</p>
            {/if}
        </div>
        <span
            class={`inline-flex min-h-7 w-fit shrink-0 items-center rounded-full border px-2.5 py-1 text-xs font-semibold ${blocked ? 'border-amber-500/40 bg-amber-500/10 text-amber-800 dark:text-amber-200' : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-800 dark:text-emerald-200'}`}
            role="status"
            aria-live="polite"
        >
            {#if blocked}
                Scoring blocked
            {:else}
                Ready
            {/if}
        </span>
    </div>

    <dl
        class="border-border/70 mt-4 grid gap-3 border-t pt-3 text-sm sm:grid-cols-2"
    >
        <div class="min-w-0">
            <dt class="text-muted-foreground text-xs">Responsible owner</dt>
            <dd class="mt-0.5 truncate font-medium" title={owner}>{owner}</dd>
        </div>
        <div class="min-w-0">
            <dt class="text-muted-foreground text-xs">Support code</dt>
            <dd class="mt-0.5 flex min-w-0 items-center gap-2">
                {#if supportCode}
                    <code
                        class="min-w-0 flex-1 truncate font-mono text-xs font-semibold select-all"
                        title={supportCode}>{supportCode}</code
                    >
                    <button
                        type="button"
                        class="border-border bg-background hover:bg-muted focus-visible:ring-ring inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-md border px-2.5 text-xs font-semibold focus-visible:ring-2 focus-visible:outline-none"
                        onclick={copySupportCode}
                        aria-label={`Copy support code ${supportCode}`}
                    >
                        {#if copyStatus === 'copied'}
                            <Check
                                class="size-3.5 text-emerald-600"
                                aria-hidden="true"
                            />
                            <span>Copied</span>
                        {:else}
                            <Clipboard class="size-3.5" aria-hidden="true" />
                            <span>Copy</span>
                        {/if}
                    </button>
                {:else}
                    <span class="text-muted-foreground">Unavailable</span>
                {/if}
            </dd>
        </div>
    </dl>

    {#if primaryAction || secondaryAction}
        <div class="mt-4 flex flex-wrap gap-2" data-omr-recovery-actions>
            {#if primaryAction}
                <button
                    type="button"
                    class="bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring inline-flex min-h-10 items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    onclick={() => invokeAction(primaryAction)}
                    disabled={isBusy || !onAction}
                    aria-busy={pendingAction === primaryAction}
                >
                    {#if pendingAction === primaryAction}
                        <LoaderCircle
                            class="size-4 animate-spin"
                            aria-hidden="true"
                        />
                    {/if}
                    <span>{primaryLabel}</span>
                </button>
            {/if}
            {#if secondaryAction}
                <button
                    type="button"
                    class="border-border bg-background hover:bg-muted focus-visible:ring-ring inline-flex min-h-10 items-center justify-center gap-2 rounded-md border px-3 py-2 text-sm font-semibold focus-visible:ring-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    onclick={() => invokeAction(secondaryAction)}
                    disabled={isBusy || !onAction}
                    aria-busy={pendingAction === secondaryAction}
                >
                    {#if pendingAction === secondaryAction}
                        <LoaderCircle
                            class="size-4 animate-spin"
                            aria-hidden="true"
                        />
                    {/if}
                    <span>{secondaryLabel}</span>
                </button>
            {/if}
        </div>
    {/if}

    {#if actionError}
        <p class="text-destructive mt-3 text-sm" role="alert">{actionError}</p>
    {/if}

    {#if technicalDetails}
        <details
            class="border-border/70 mt-4 border-t pt-3"
            data-omr-recovery-technical
        >
            <summary
                class="text-muted-foreground hover:text-foreground focus-visible:ring-ring inline-flex min-h-9 cursor-pointer items-center rounded-md text-xs font-semibold underline-offset-2 hover:underline focus-visible:ring-2 focus-visible:outline-none"
            >
                Technical details
            </summary>
            <pre
                class="border-border/70 bg-muted/30 mt-2 max-h-48 overflow-auto rounded-md border p-3 font-mono text-[11px] leading-relaxed break-words whitespace-pre-wrap">{technicalDetails}</pre>
        </details>
    {/if}
</article>
