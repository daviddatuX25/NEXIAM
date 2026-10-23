<script>
    let {
        options = [],
        value = $bindable(''),
        invalid = false,
    } = $props();
</script>

<select
    id="strand"
    aria-label="SHS Strand / Track"
    aria-invalid={invalid}
    class="flex h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
    bind:value
>
    <option value="">Select SHS strand / track</option>
    {#each options as option (option.key)}
        <option value={option.code}>{option.label}</option>
    {/each}
</select>
