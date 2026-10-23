<script lang="ts">
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import { formatIsoBirthdate } from '@/lib/applicant-birthdate';

    let {
        id,
        value = $bindable(''),
        error = null,
        oninput = undefined,
    } = $props();

    function handleInput(event: Event): void {
        const input = event.currentTarget as HTMLInputElement;
        const previousValue = input.value;
        const formattedIsoDate = formatIsoBirthdate(previousValue);

        if (formattedIsoDate) {
            value = formattedIsoDate;
            input.value = formattedIsoDate;
        } else if (/^\d{4}-\d{2}-\d{2}$/.test(previousValue)) {
            value = previousValue;
        } else {
            const selectionStart = input.selectionStart ?? previousValue.length;
            const digitsBeforeCaret = previousValue
                .slice(0, selectionStart)
                .replace(/\D/g, '').length;
            const digits = previousValue.replace(/\D/g, '').slice(0, 8);
            const formattedValue =
                digits.length > 4
                    ? `${digits.slice(0, 2)}-${digits.slice(2, 4)}-${digits.slice(4)}`
                    : digits.length > 2
                      ? `${digits.slice(0, 2)}-${digits.slice(2)}`
                      : digits;
            const caretPosition = Math.min(
                digitsBeforeCaret +
                    Number(digitsBeforeCaret > 2) +
                    Number(digitsBeforeCaret > 4),
                formattedValue.length,
            );

            value = formattedValue;
            input.value = formattedValue;
            input.setSelectionRange(caretPosition, caretPosition);
        }

        oninput?.();
    }
</script>

<div class="space-y-2">
    <Label for={id}>Birthdate <span class="text-destructive">*</span></Label>
    <Input
        {id}
        type="text"
        bind:value
        inputmode="numeric"
        autocomplete="bday"
        maxlength="10"
        pattern="(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])-[0-9]{4}"
        placeholder="MM-DD-YYYY"
        required
        aria-invalid={Boolean(error)}
        aria-describedby={error ? `${id}-format ${id}-error` : `${id}-format`}
        class="min-h-11"
        oninput={handleInput}
    />
    <p id={`${id}-format`} class="text-muted-foreground text-xs">
        Enter as MM-DD-YYYY.
    </p>
    {#if error}
        <p id={`${id}-error`} class="text-destructive text-sm" role="alert">
            {error}
        </p>
    {/if}
</div>
