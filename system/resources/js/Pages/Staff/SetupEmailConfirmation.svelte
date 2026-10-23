<script>
    import GuestLayout from '@/Layouts/GuestLayout.svelte';
    import { Button } from '@/Components/ui/button';
    import * as Card from '@/Components/ui/card';
    import { router } from '@inertiajs/svelte';
    import { MailCheck } from 'lucide-svelte';

    let { email = null, can_confirm = false } = $props();
    let processing = $state(false);

    function confirmEmail() {
        if (!can_confirm || processing) return;
        processing = true;
        router.post(
            '/staff/account-setup/email/confirm',
            {},
            {
                onFinish: () => (processing = false),
            },
        );
    }
</script>

<svelte:head>
    <title>Confirm staff email</title>
    <meta name="referrer" content="no-referrer" />
</svelte:head>

<GuestLayout maxWidth="max-w-xl">
    <Card.Root
        variant="glass"
        class="border-border/60 border shadow-xl backdrop-blur-xl"
    >
        <Card.Header class="text-center">
            <div
                class="bg-primary/10 text-primary mx-auto mb-2 flex size-12 items-center justify-center rounded-full"
            >
                <MailCheck class="size-6" />
            </div>
            <Card.Title class="text-2xl font-bold"
                >Confirm your staff email</Card.Title
            >
            <Card.Description>
                {#if can_confirm}
                    Confirm that you control <span
                        class="text-foreground font-medium">{email}</span
                    >.
                {:else}
                    This confirmation is no longer valid for the current account
                    state. Contact the authorized issuer if your setup still
                    needs attention.
                {/if}
            </Card.Description>
        </Card.Header>
        <Card.Content>
            {#if can_confirm}
                <p class="text-muted-foreground mb-4 text-center text-sm">
                    This action verifies email ownership only. It does not sign
                    you in or resume a used setup voucher.
                </p>
                <Button
                    class="w-full"
                    type="button"
                    disabled={processing}
                    onclick={confirmEmail}
                >
                    {processing ? 'Confirming…' : 'Confirm email address'}
                </Button>
            {:else}
                <Button
                    class="w-full"
                    type="button"
                    variant="outline"
                    onclick={() => router.visit('/login')}
                >
                    Continue to sign in
                </Button>
            {/if}
        </Card.Content>
    </Card.Root>
</GuestLayout>
