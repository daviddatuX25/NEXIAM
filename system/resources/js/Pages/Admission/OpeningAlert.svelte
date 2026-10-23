<script>
    import HomeLayout from '@/Layouts/HomeLayout.svelte';
    import { Link, router } from '@inertiajs/svelte';
    import { ArrowRight, MailCheck, ShieldCheck } from 'lucide-svelte';
    import {
        canConfirmOpeningAlertToken,
        postOpeningAlertTokenAction,
    } from '@/lib/opening-alert-token';

    let {
        institution = null,
        state: pageState = 'invalid',
        token = '',
        purpose = '',
        message = '',
    } = $props();

    let submitting = $state(false);

    const canConfirm = $derived(
        canConfirmOpeningAlertToken(pageState, purpose, token),
    );
    const heading = $derived.by(() => {
        if (pageState === 'verify') return 'Confirm your email';
        if (pageState === 'unsubscribe') return 'Unsubscribe from opening alerts';
        if (pageState === 'confirmed') return 'Email confirmed';
        if (pageState === 'unsubscribed') return 'You are unsubscribed';
        if (pageState === 'open') return 'Applications are open';
        return 'This link is no longer available';
    });
    const defaultMessage = $derived.by(() => {
        if (pageState === 'verify') return 'Confirm that you want one email when applications next open.';
        if (pageState === 'unsubscribe') return 'Confirm that you want to stop application-opening emails.';
        if (pageState === 'confirmed') return 'Your email is confirmed for one application-opening alert.';
        if (pageState === 'unsubscribed') return 'No further application-opening alerts will be sent for this signup.';
        if (pageState === 'open') return 'Applications are currently open. Continue to the application when you are ready.';
        return 'The link may have expired or already been used. Return to the admissions page to check current options.';
    });

    function confirmAction() {
        if (!canConfirm || submitting) return;
        submitting = true;
        postOpeningAlertTokenAction(
            router,
            pageState,
            purpose,
            token,
            () => (submitting = false),
        );
    }
</script>

<svelte:head>
    <title>{heading} · Admissions</title>
</svelte:head>

<HomeLayout {institution}>
    <main class="mx-auto w-full max-w-2xl px-4 py-10 sm:px-6 sm:py-14">
        <section class="border-border/70 bg-card space-y-5 rounded-xl border p-5 sm:p-7" aria-labelledby="opening-alert-title">
            <div class="flex items-start gap-3">
                {#if pageState === 'verify'}
                    <MailCheck class="text-primary mt-1 size-5 shrink-0" aria-hidden="true" />
                {:else}
                    <ShieldCheck class="text-primary mt-1 size-5 shrink-0" aria-hidden="true" />
                {/if}
                <div class="space-y-2">
                    <h1 id="opening-alert-title" class="text-foreground text-2xl font-semibold tracking-tight">{heading}</h1>
                    <p class="text-muted-foreground text-sm leading-6">{message || defaultMessage}</p>
                </div>
            </div>

            {#if canConfirm}
                <button
                    type="button"
                    disabled={submitting}
                    onclick={confirmAction}
                    class="bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-semibold transition disabled:cursor-wait disabled:opacity-60 focus-visible:ring-2 focus-visible:outline-none sm:w-auto"
                >
                    {submitting ? 'Please wait…' : pageState === 'verify' ? 'Confirm email' : 'Unsubscribe'}
                    <ArrowRight class="size-4" />
                </button>
            {:else if pageState === 'open'}
                <Link href="/apply" class="bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-semibold focus-visible:ring-2 focus-visible:outline-none sm:w-auto">
                    Continue to the application <ArrowRight class="size-4" />
                </Link>
            {/if}

            <p class="text-muted-foreground border-border/60 border-t pt-4 text-xs leading-5">
                Opening alerts are separate from applications. Confirming or unsubscribing does not submit an application or reserve a place.
            </p>
        </section>
    </main>
</HomeLayout>
