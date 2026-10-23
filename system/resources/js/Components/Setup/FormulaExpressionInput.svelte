<script lang="ts">
    import { tick } from 'svelte';

    type FormulaSuggestion = {
        kind: 'variable' | 'function';
        name: string;
        description: string;
    };

    const FUNCTIONS: FormulaSuggestion[] = [
        {
            kind: 'function',
            name: 'SUM',
            description: 'Add one or more values',
        },
        {
            kind: 'function',
            name: 'AVERAGE',
            description: 'Average one or more values',
        },
        { kind: 'function', name: 'MIN', description: 'Smallest value' },
        { kind: 'function', name: 'MAX', description: 'Largest value' },
        {
            kind: 'function',
            name: 'ROUND',
            description: 'Round a value to 0–6 places',
        },
    ];
    const AUTOCOMPLETE_LIMIT = 12;

    let {
        id,
        value = '',
        variableCodes = [],
        disabled = false,
        ariaDescribedBy = undefined,
        textareaClass = 'min-h-24 w-full resize-y rounded-md border border-input bg-background px-3 py-2 font-mono text-sm',
        onValueChange,
    } = $props<{
        id: string;
        value?: string;
        variableCodes?: string[];
        disabled?: boolean;
        ariaDescribedBy?: string;
        textareaClass?: string;
        onValueChange: (value: string) => void;
    }>();

    let textarea = $state<HTMLTextAreaElement | null>(null);
    let caret = $state(0);
    let tokenStart = $state(0);
    let tokenText = $state('');
    let autocompleteOpen = $state(false);
    let activeSuggestionIndex = $state(0);

    const suggestions = $derived.by(() => {
        const query = tokenText.toUpperCase();
        const options: FormulaSuggestion[] = [
            ...variableCodes.map((name) => ({
                kind: 'variable' as const,
                name,
                description: 'Checked raw metric',
            })),
            ...FUNCTIONS,
        ];
        return options
            .filter((option) => query === '' || option.name.startsWith(query))
            .slice(0, AUTOCOMPLETE_LIMIT);
    });

    function rememberCaret(input: HTMLTextAreaElement): void {
        caret = input.selectionStart ?? input.value.length;
        const beforeCaret = input.value.slice(0, caret);
        const match = /(?:^|[^A-Za-z0-9_])([A-Za-z_][A-Za-z0-9_]*)?$/.exec(
            beforeCaret,
        );
        tokenText = match?.[1] ?? '';
        tokenStart = caret - tokenText.length;
        activeSuggestionIndex = 0;
        autocompleteOpen = tokenText.length > 0;
    }

    function handleInput(event: Event): void {
        const input = event.currentTarget as HTMLTextAreaElement;
        onValueChange(input.value);
        rememberCaret(input);
    }

    async function replaceSelection(
        start: number,
        end: number,
        insertion: string,
        cursor: number,
    ): Promise<void> {
        const nextValue = `${value.slice(0, start)}${insertion}${value.slice(end)}`;
        if (textarea) textarea.value = nextValue;
        onValueChange(nextValue);
        autocompleteOpen = false;
        await tick();
        textarea?.focus();
        textarea?.setSelectionRange(cursor, cursor);
        if (textarea) {
            caret = cursor;
            tokenText = '';
            tokenStart = cursor;
        }
    }

    async function insertVariable(
        name: string,
        replaceToken = false,
    ): Promise<void> {
        const start = replaceToken ? tokenStart : caret;
        const end = replaceToken ? caret : (textarea?.selectionEnd ?? caret);
        const joinsPreviousToken =
            start > 0 && /[A-Za-z0-9_)]/.test(value.slice(start - 1, start));
        const prefix = joinsPreviousToken ? '*' : '';
        const insertion = `${prefix}${name}`;
        await replaceSelection(start, end, insertion, start + insertion.length);
    }

    async function insertFunction(
        name: string,
        replaceToken = false,
    ): Promise<void> {
        const start = replaceToken ? tokenStart : caret;
        const end = replaceToken ? caret : caret;
        const joinsPreviousToken =
            start > 0 && /[A-Za-z0-9_)]/.test(value.slice(start - 1, start));
        const prefix = joinsPreviousToken ? '*' : '';
        const selectedText = replaceToken
            ? ''
            : value.slice(start, textarea?.selectionEnd ?? start);
        const insertion =
            name === 'ROUND'
                ? `${prefix}ROUND(${selectedText}, 0)`
                : selectedText
                  ? `${prefix}${name}(${selectedText})`
                  : `${prefix}${name}()`;
        const cursor = selectedText
            ? start + insertion.length
            : start + prefix.length + name.length + 1;
        await replaceSelection(
            start,
            replaceToken ? end : (textarea?.selectionEnd ?? end),
            insertion,
            cursor,
        );
    }

    async function chooseSuggestion(
        suggestion: FormulaSuggestion,
    ): Promise<void> {
        if (suggestion.kind === 'function') {
            await insertFunction(suggestion.name, true);
            return;
        }
        await insertVariable(suggestion.name, true);
    }

    function handleKeydown(event: KeyboardEvent): void {
        if (event.key === ' ' && (event.ctrlKey || event.metaKey)) {
            event.preventDefault();
            rememberCaret(event.currentTarget as HTMLTextAreaElement);
            autocompleteOpen = true;
            activeSuggestionIndex = 0;
            return;
        }
        if (!autocompleteOpen || suggestions.length === 0) return;
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const direction = event.key === 'ArrowDown' ? 1 : -1;
            activeSuggestionIndex =
                (activeSuggestionIndex + direction + suggestions.length) %
                suggestions.length;
            return;
        }
        if (event.key === 'Enter') {
            event.preventDefault();
            void chooseSuggestion(suggestions[activeSuggestionIndex]);
            return;
        }
        if (event.key === 'Escape') {
            event.preventDefault();
            autocompleteOpen = false;
        }
    }
</script>

<div class="space-y-3">
    <textarea
        {id}
        bind:this={textarea}
        class={textareaClass}
        {value}
        {disabled}
        role="combobox"
        aria-autocomplete="list"
        aria-expanded={autocompleteOpen && suggestions.length > 0}
        aria-controls={`${id}-suggestions`}
        aria-activedescendant={autocompleteOpen && suggestions.length > 0
            ? `${id}-suggestion-${activeSuggestionIndex}`
            : undefined}
        aria-describedby={ariaDescribedBy}
        oninput={handleInput}
        onselect={(event) =>
            rememberCaret(event.currentTarget as HTMLTextAreaElement)}
        onkeyup={(event) => {
            if (
                ![
                    'ArrowDown',
                    'ArrowUp',
                    'Enter',
                    'Escape',
                    ' ',
                    'Control',
                    'Meta',
                    'Shift',
                    'Alt',
                ].includes(event.key)
            ) {
                rememberCaret(event.currentTarget as HTMLTextAreaElement);
            }
        }}
        onclick={(event) =>
            rememberCaret(event.currentTarget as HTMLTextAreaElement)}
        onkeydown={handleKeydown}
        onfocus={(event) => {
            caret =
                (event.currentTarget as HTMLTextAreaElement).selectionStart ??
                0;
        }}></textarea>

    <fieldset class="space-y-2">
        <legend class="text-sm font-medium">Insert checked variable</legend>
        <div class="flex flex-wrap gap-2">
            {#each variableCodes as code (code)}
                <button
                    type="button"
                    class="border-border hover:bg-muted focus-visible:outline-ring min-h-10 rounded-full border px-3 font-mono text-xs focus-visible:outline-2 focus-visible:outline-offset-2"
                    {disabled}
                    aria-label={`Insert ${code}`}
                    onclick={() => void insertVariable(code)}>{code}</button
                >
            {/each}
        </div>
    </fieldset>

    <fieldset class="space-y-2">
        <legend class="text-sm font-medium">Insert function</legend>
        <div class="flex flex-wrap gap-2">
            {#each FUNCTIONS as fn (fn.name)}
                <button
                    type="button"
                    class="border-border hover:bg-muted focus-visible:outline-ring min-h-10 rounded-full border px-3 font-mono text-xs focus-visible:outline-2 focus-visible:outline-offset-2"
                    {disabled}
                    aria-label={`Insert ${fn.name} function`}
                    title={fn.description}
                    onclick={() => void insertFunction(fn.name)}
                    >{fn.name}</button
                >
            {/each}
        </div>
    </fieldset>

    {#if autocompleteOpen && suggestions.length > 0}
        <div
            class="border-border bg-popover max-h-52 overflow-y-auto rounded-md border p-1 shadow-md"
            id={`${id}-suggestions`}
            role="listbox"
            aria-label="Formula autocomplete suggestions"
        >
            {#each suggestions as suggestion, index (`${suggestion.kind}-${suggestion.name}`)}
                <button
                    type="button"
                    id={`${id}-suggestion-${index}`}
                    role="option"
                    aria-selected={activeSuggestionIndex === index}
                    class={`hover:bg-muted flex min-h-10 w-full items-center justify-between gap-3 rounded px-3 text-left text-sm ${activeSuggestionIndex === index ? 'bg-muted' : ''}`}
                    onclick={() => void chooseSuggestion(suggestion)}
                >
                    <span class="font-mono"
                        >{suggestion.kind === 'function'
                            ? `${suggestion.name}()`
                            : suggestion.name}</span
                    >
                    <span class="text-muted-foreground text-xs"
                        >{suggestion.description}</span
                    >
                </button>
            {/each}
        </div>
    {/if}
</div>
