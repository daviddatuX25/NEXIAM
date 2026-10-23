<script lang="ts">
    import { tick } from 'svelte';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import {
        filterApplicantIntakeSuggestions,
        type ApplicantIntakeSuggestion,
    } from '@/lib/applicant-intake-suggestions';

    interface Props {
        id: string;
        label: string;
        value?: string;
        suggestions?: readonly ApplicantIntakeSuggestion[];
        helperText?: string;
        error?: string | null;
        placeholder?: string;
        resultLimit?: number;
    }
    let {
        id,
        value = $bindable(''),
        label,
        suggestions = [],
        helperText = '',
        error = null,
        placeholder = '',
        resultLimit = 8,
    }: Props = $props();

    let isOpen = $state(false);
    let activeIndex = $state(-1);

    const listboxId = $derived(`${id}-suggestions`);
    const helperId = $derived(`${id}-help`);
    const errorId = $derived(`${id}-error`);
    const matchingSuggestions = $derived(
        filterApplicantIntakeSuggestions(suggestions, value, resultLimit),
    );
    const typedValue = $derived(value.trim());
    const hasCustomChoice = $derived(
        typedValue.length > 0 &&
            !matchingSuggestions.some(
                (suggestion) =>
                    suggestion.label.trim().toLowerCase() ===
                    typedValue.toLowerCase(),
            ),
    );
    const choiceCount = $derived(
        matchingSuggestions.length + (hasCustomChoice ? 1 : 0),
    );
    const activeDescendant = $derived.by(() => {
        if (activeIndex < 0) {
            return undefined;
        }

        if (activeIndex < matchingSuggestions.length) {
            return `${listboxId}-option-${matchingSuggestions[activeIndex].key}`;
        }

        return hasCustomChoice && activeIndex === matchingSuggestions.length
            ? `${listboxId}-custom`
            : undefined;
    });
    const descriptionIds = $derived(
        [helperText ? helperId : null, error ? errorId : null]
            .filter(Boolean)
            .join(' ') || undefined,
    );
    const resultAnnouncement = $derived.by(() => {
        if (!isOpen) {
            return '';
        }

        if (matchingSuggestions.length === 0) {
            return 'No suggested matches. You can submit your entered text.';
        }

        return `${matchingSuggestions.length} suggestions available. ${hasCustomChoice ? 'You can also use your entered text.' : ''}`;
    });
    $effect(() => {
        const activeOptionId = activeDescendant;

        if (activeOptionId) {
            void tick().then(() => {
                document
                    .getElementById(activeOptionId)
                    ?.scrollIntoView?.({ block: 'nearest' });
            });
        }
    });

    function handleInput(event: Event): void {
        value = (event.currentTarget as HTMLInputElement).value;
        isOpen = choiceCount > 0;
        activeIndex = -1;
    }

    function handleFocus(): void {
        isOpen = choiceCount > 0;
    }

    function handleBlur(): void {
        setTimeout(() => {
            isOpen = false;
            activeIndex = -1;
        }, 0);
    }

    function handleKeydown(event: KeyboardEvent): void {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            if (choiceCount === 0) {
                isOpen = value.trim().length > 0;
                return;
            }

            event.preventDefault();
            isOpen = true;
            const direction = event.key === 'ArrowDown' ? 1 : -1;
            activeIndex =
                activeIndex < 0
                    ? direction > 0
                        ? 0
                        : choiceCount - 1
                    : (activeIndex + direction + choiceCount) % choiceCount;
            return;
        }

        if (event.key === 'Enter' && isOpen && activeIndex >= 0) {
            event.preventDefault();

            if (activeIndex < matchingSuggestions.length) {
                chooseSuggestion(matchingSuggestions[activeIndex]);
            } else if (
                hasCustomChoice &&
                activeIndex === matchingSuggestions.length
            ) {
                chooseTypedValue();
            }

            return;
        }

        if (event.key === 'Escape' && isOpen) {
            event.preventDefault();
            isOpen = false;
            activeIndex = -1;
            return;
        }

        if (event.key === 'Tab') {
            isOpen = false;
            activeIndex = -1;
        }
    }

    function chooseSuggestion(suggestion: ApplicantIntakeSuggestion): void {
        value = suggestion.label;
        isOpen = false;
        activeIndex = -1;
    }

    function chooseTypedValue(): void {
        isOpen = false;
        activeIndex = -1;
    }
</script>

<div class="space-y-2">
    <Label for={id} class="">{label}</Label>
    <Input
        {id}
        type="text"
        role="combobox"
        bind:value
        autocomplete="off"
        {placeholder}
        aria-autocomplete="list"
        aria-haspopup="listbox"
        aria-expanded={isOpen}
        aria-controls={listboxId}
        aria-activedescendant={activeDescendant}
        aria-invalid={Boolean(error)}
        aria-describedby={descriptionIds}
        class="min-h-11"
        oninput={handleInput}
        onfocus={handleFocus}
        onclick={handleFocus}
        onblur={handleBlur}
        onkeydown={handleKeydown}
    />
    {#if helperText}
        <p id={helperId} class="text-muted-foreground text-xs">
            {helperText}
        </p>
    {/if}
    {#if error}
        <p id={errorId} class="text-destructive text-sm" role="alert">
            {error}
        </p>
    {/if}
    <p class="sr-only" role="status" aria-live="polite">
        {resultAnnouncement}
    </p>
    {#if isOpen}
        <div
            id={listboxId}
            role="listbox"
            aria-label={`${label} suggestions`}
            class="border-border bg-popover text-popover-foreground max-h-64 overflow-y-auto rounded-md border p-1 shadow-md"
        >
            {#each matchingSuggestions as suggestion, index (suggestion.key)}
                <button
                    role="option"
                    type="button"
                    id={`${listboxId}-option-${suggestion.key}`}
                    aria-selected={activeIndex === index}
                    tabindex="-1"
                    class="hover:bg-accent hover:text-accent-foreground flex w-full flex-col items-start rounded-sm px-2 py-2 text-left text-sm"
                    class:bg-accent={activeIndex === index}
                    onpointerdown={(event) => event.preventDefault()}
                    onpointerenter={() => (activeIndex = index)}
                    onclick={() => chooseSuggestion(suggestion)}
                >
                    <span>{suggestion.label}</span>
                </button>
            {/each}
            {#if hasCustomChoice}
                <button
                    type="button"
                    id={`${listboxId}-custom`}
                    role="option"
                    aria-selected={activeIndex === matchingSuggestions.length}
                    tabindex="-1"
                    class="hover:bg-accent hover:text-accent-foreground flex w-full items-center rounded-sm px-2 py-2 text-left text-sm"
                    class:bg-accent={activeIndex === matchingSuggestions.length}
                    onpointerdown={(event) => event.preventDefault()}
                    onpointerenter={() =>
                        (activeIndex = matchingSuggestions.length)}
                    onclick={chooseTypedValue}
                >
                    Use entered text: {value}
                </button>
            {/if}
        </div>
    {/if}
</div>
