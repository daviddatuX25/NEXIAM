<script>
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import InfoPopover from '@/Components/kit/InfoPopover.svelte';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import { Link, useForm } from '@inertiajs/svelte';
    import { newIdempotencyKey } from '@/lib/utils.js';

    let { actions = {} } = $props();
    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Test Kits', href: actions.index ?? '/setup/exam-materials/test-kits' },
        { label: 'Create Test Kit' },
    ];
    const form = useForm({
        code: '',
        name: '',
        expected_revision: 0,
        idempotency_key: newIdempotencyKey(),
    });

    function submit(event) {
        event.preventDefault();
        form.transform((data) => ({
            code: data.code.trim().toUpperCase(),
            name: data.name.trim(),
            expected_revision: 0,
            idempotency_key: data.idempotency_key || newIdempotencyKey(),
        })).post(actions.store ?? '/setup/exam-materials/test-kits');
    }
</script>

<svelte:head><title>Create Test Kit · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-6 p-4 sm:p-6">
        <PageHeaderBar title="Create Test Kit">
            {#snippet actions_content()}
                <Link href={actions.index ?? '/setup/exam-materials/test-kits'}>
                    <Button variant="outline" class="min-h-10">Back to Test Kits</Button>
                </Link>
            {/snippet}
        </PageHeaderBar>

        <form onsubmit={submit} class="max-w-2xl space-y-6">
            <div class="grid gap-5 rounded-xl border border-border bg-card p-5 sm:p-6">
                <div class="space-y-2">
                    <div class="flex items-center gap-1.5">
                        <Label for="kit-code">Code</Label>
                        <InfoPopover
                            title="Test Kit identifier"
                            content="A unique uppercase code for this Test Kit family within the institution (e.g., TK-ICAT-2026)."
                        />
                    </div>
                    <Input
                        id="kit-code"
                        bind:value={form.code}
                        maxlength={64}
                        autocomplete="off"
                        class="min-h-11 font-mono uppercase"
                        required
                    />
                    {#if form.errors.code}<p class="text-sm text-destructive">{form.errors.code}</p>{/if}
                </div>
                <div class="space-y-2">
                    <div class="flex items-center gap-1.5">
                        <Label for="kit-name">Name</Label>
                        <InfoPopover
                            title="Thin Kit wrapper"
                            content="Names the Test Kit shown in exam scheduling, manual scoring, and score imports. After creation, select an approved Test Setup and lock a numbered version."
                        />
                    </div>
                    <Input id="kit-name" bind:value={form.name} maxlength={255} class="min-h-11" required />
                    {#if form.errors.name}<p class="text-sm text-destructive">{form.errors.name}</p>{/if}
                </div>
                <p class="text-sm text-muted-foreground">The kit opens as a preparation. Components can be selected and saved later.</p>
                {#if form.errors.test_kit}<p class="text-sm text-destructive">{form.errors.test_kit}</p>{/if}
            </div>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <Link href={actions.index ?? '/setup/exam-materials/test-kits'}>
                    <Button type="button" variant="outline" class="min-h-10 w-full sm:w-auto">Cancel</Button>
                </Link>
                <Button type="submit" disabled={form.processing || !form.code.trim() || !form.name.trim()} class="min-h-10">
                    {form.processing ? 'Creating…' : 'Create Test Kit'}
                </Button>
            </div>
        </form>
    </div>
</StaffLayout>
