<script>
    import GuestLayout from '@/Layouts/GuestLayout.svelte';
    import { Button } from '@/Components/ui/button';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import * as Card from '@/Components/ui/card';
    import { useForm } from '@inertiajs/svelte';
    import { KeyRound, TicketCheck } from 'lucide-svelte';

    const form = useForm({ reference: '', fallback: '', token: '' });
    let fragmentLoaded = false;

    function readFragment() {
        if (typeof window === 'undefined' || fragmentLoaded) return;
        fragmentLoaded = true;
        const hash = window.location.hash.startsWith('#')
            ? window.location.hash.slice(1)
            : window.location.hash;
        const params = new URLSearchParams(hash);
        form.reference = (params.get('v') ?? '').toUpperCase();
        form.token = params.get('t') ?? '';
        if (params.has('v') || params.has('t')) {
            window.history.replaceState(
                null,
                '',
                `${window.location.pathname}${window.location.search}`,
            );
        }
    }

    function submit(event) {
        event.preventDefault();
        form.post('/staff/activate');
    }

    readFragment();
</script>

<svelte:head>
    <title>Activate staff account</title>
</svelte:head>

<GuestLayout>
    <Card.Root
        variant="glass"
        class="border-border/60 border shadow-xl backdrop-blur-xl"
    >
        <Card.Header class="text-center">
            <div
                class="bg-primary/10 text-primary mx-auto mb-2 flex size-12 items-center justify-center rounded-full"
            >
                <TicketCheck class="size-6" />
            </div>
            <Card.Title class="text-2xl font-bold"
                >Activate Staff Account</Card.Title
            >
            <Card.Description>
                Enter your voucher reference and 8-character fallback code from
                your physical slip, or open the link you received.
            </Card.Description>
        </Card.Header>
        <Card.Content>
            <form onsubmit={submit} class="space-y-4">
                <div class="space-y-2">
                    <Label for="voucher-reference">Voucher reference</Label>
                    <Input
                        id="voucher-reference"
                        bind:value={form.reference}
                        placeholder="XXXXXXXXXX"
                        required
                        maxlength="10"
                        autocomplete="off"
                        class="text-center font-mono tracking-wider uppercase"
                        aria-invalid={!!form.errors.reference}
                        aria-describedby={form.errors.reference
                            ? 'ref-error'
                            : undefined}
                    />
                    {#if form.errors.reference}
                        <p
                            id="ref-error"
                            class="text-destructive text-sm"
                            role="alert"
                        >
                            {form.errors.reference}
                        </p>
                    {/if}
                </div>
                <div class="space-y-2">
                    <Label for="voucher-fallback"
                        >8-character fallback code</Label
                    >
                    <div class="relative">
                        <KeyRound
                            class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2"
                        />
                        <Input
                            id="voucher-fallback"
                            bind:value={form.fallback}
                            placeholder="XXXX-XXXX"
                            maxlength="9"
                            autocomplete="one-time-code"
                            class="pl-9 text-center font-mono tracking-widest uppercase"
                            aria-invalid={!!form.errors.fallback}
                            aria-describedby={form.errors.fallback
                                ? 'fallback-error'
                                : undefined}
                        />
                    </div>
                    {#if form.errors.fallback}
                        <p
                            id="fallback-error"
                            class="text-destructive text-sm"
                            role="alert"
                        >
                            {form.errors.fallback}
                        </p>
                    {/if}
                </div>
                {#if form.errors.token}
                    <p class="text-destructive text-sm" role="alert">
                        {form.errors.token}
                    </p>
                {/if}
                <Button class="w-full" type="submit" disabled={form.processing}>
                    {form.processing ? 'Verifying…' : 'Continue securely'}
                </Button>
            </form>
        </Card.Content>
        <Card.Footer class="block">
            <div class="pt-2 text-center">
                <p class="text-muted-foreground text-xs">
                    Single-use onboarding credential · Follow the expiry shown
                    on your issued voucher. Starting setup opens a separate,
                    session-bound 10-minute attempt.
                </p>
            </div>
        </Card.Footer>
    </Card.Root>
</GuestLayout>
