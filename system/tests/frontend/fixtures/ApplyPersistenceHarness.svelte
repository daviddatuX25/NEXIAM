<script>
    import { usePersistentForm } from '../../../resources/js/lib/persistent-form.svelte';
    let { prefilledEmail = '' } = $props();
    let birthdateInput = $state('');

    const persistent = usePersistentForm({
        formId: 'admission_apply',
        getContext: () => ({
            cycle_id: 'cycle-1',
            window_id: 'window-1',
            policy_digest: 'policy-1',
        }),
        initialData: {
            first_name: '',
            email: prefilledEmail,
            birthdate_input: '',
            terms_accepted: false,
        },
        excludeKeys: ['terms_accepted'],
        debounceMs: 500,
        onRestored: (data) => {
            if (data.birthdate_input) {
                birthdateInput = data.birthdate_input;
            }
        },
    });
    const { form } = persistent;

    $effect(() => {
        form.birthdate_input = birthdateInput;
    });
</script>

<label>
    Email
    <input aria-label="Email" bind:value={form.email} />
</label>
<label>
    First name
    <input aria-label="First name" bind:value={form.first_name} />
</label>
<label>
    Birthdate
    <input aria-label="Birthdate" bind:value={birthdateInput} />
</label>
<label>
    Accept terms
    <input aria-label="Accept terms" type="checkbox" bind:checked={form.terms_accepted} />
</label>
<button type="button" onclick={() => persistent.clearSavedDraft()}>Clear draft</button>
