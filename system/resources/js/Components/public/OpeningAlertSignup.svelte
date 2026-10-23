<script>
    import { Link } from '@inertiajs/svelte';
    import { ArrowRight } from 'lucide-svelte';
    import {
        forgetRememberedOpeningAlertEmail,
        loadRememberedOpeningAlertEmail,
        rememberOpeningAlertEmail,
    } from '@/lib/opening-alert-email';

    let {
        available = false,
        prefilledEmail = '',
    } = $props();

    let rememberedEmail = $state(loadRememberedOpeningAlertEmail());
    let email = $state(prefilledEmail || rememberedEmail);
    let consent = $state(false);
    let rememberOnDevice = $state(false);
    let submitting = $state(false);
    let error = $state('');
    let success = $state('');
    let openNow = $state(false);

    async function submit(event) {
        event.preventDefault();
        if (!available || submitting) {
            return;
        }

        error = '';
        success = '';
        openNow = false;
        submitting = true;

        try {
            const response = await fetch('/apply/opening-alerts', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ email: email.trim(), consent }),
            });
            const result = await response.json().catch(() => ({}));

            if (!response.ok) {
                error = response.status === 503
                    ? 'Email alerts are temporarily unavailable. Please try again later.'
                    : 'We could not submit this request. Check the email and consent, then try again.';
                return;
            }

            if (rememberOnDevice) {
                rememberOpeningAlertEmail(email);
                rememberedEmail = email.trim();
            }

            if (result.status === 'open') {
                openNow = true;
                success = 'Applications are open now. You can continue to the application below.';
                return;
            }

            success = typeof result.message === 'string' && result.message.trim()
                ? result.message
                : 'If this request can be accepted, a confirmation email will arrive shortly.';
        } catch {
            error = 'We could not connect. Check your connection and try again.';
        } finally {
            submitting = false;
        }
    }

    function forgetEmail() {
        const previousEmail = rememberedEmail;
        forgetRememberedOpeningAlertEmail();
        rememberedEmail = '';
        if (email === previousEmail) {
            email = '';
        }
    }
</script>

<section
    aria-labelledby="opening-alert-heading"
    class="border-border/70 bg-background/70 mt-5 space-y-4 rounded-xl border p-4 sm:p-5"
>
    <div class="space-y-1">
        <h2 id="opening-alert-heading" class="text-foreground text-base font-semibold">
            Get an email when applications open
        </h2>
        <p class="text-muted-foreground max-w-[68ch] text-sm leading-6">
            This is only an opening alert. It does not submit an application or reserve a place.
        </p>
    </div>

    {#if !available}
        <p class="text-muted-foreground text-sm" role="status">
            Email alerts are not available right now. Please check the admissions page again later.
        </p>
        {#if rememberedEmail}
            <button type="button" class="text-muted-foreground hover:text-foreground min-h-11 text-left text-sm underline underline-offset-4" onclick={forgetEmail}>
                Forget remembered email
            </button>
        {/if}
    {:else}
        <form class="space-y-3" onsubmit={submit}>
            <div class="space-y-1.5">
                <label for="opening-alert-email" class="text-foreground text-sm font-medium">Email address</label>
                <input
                    id="opening-alert-email"
                    bind:value={email}
                    type="email"
                    name="email"
                    autocomplete="email"
                    inputmode="email"
                    required
                    maxlength="254"
                    class="border-input bg-background text-foreground placeholder:text-muted-foreground focus-visible:ring-ring min-h-11 w-full rounded-md border px-3 py-2 text-base outline-none focus-visible:ring-2"
                    aria-describedby="opening-alert-consent-copy"
                />
            </div>

            <label class="text-foreground flex items-start gap-3 text-sm leading-6">
                <input
                    bind:checked={consent}
                    type="checkbox"
                    name="consent"
                    required
                    class="border-input text-primary focus-visible:ring-ring mt-1 size-4 shrink-0 rounded focus-visible:ring-2"
                />
                <span id="opening-alert-consent-copy">
                    Email me once when applications next open. I understand this is separate from an application and can unsubscribe at any time. Read the
                    <Link href="/policies/privacy" class="text-primary underline underline-offset-4 hover:no-underline">Privacy Policy</Link>.
                </span>
            </label>

            <label class="text-muted-foreground flex min-h-11 items-start gap-3 text-sm leading-6">
                <input
                    bind:checked={rememberOnDevice}
                    type="checkbox"
                    name="remember_email"
                    class="border-input text-primary focus-visible:ring-ring mt-1 size-4 shrink-0 rounded focus-visible:ring-2"
                />
                <span>Remember this email on this device for 7 days. It is not saved unless you select this.</span>
            </label>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <button
                    type="submit"
                    disabled={submitting || !consent}
                    class="bg-primary text-primary-foreground hover:bg-primary/90 focus-visible:ring-ring inline-flex min-h-11 items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-55 focus-visible:ring-2 focus-visible:outline-none"
                >
                    {submitting ? 'Sending…' : 'Notify me'}
                    <ArrowRight class="size-4" />
                </button>
                {#if rememberedEmail}
                    <button type="button" class="text-muted-foreground hover:text-foreground min-h-11 text-left text-sm underline underline-offset-4" onclick={forgetEmail}>
                        Forget remembered email
                    </button>
                {/if}
            </div>

            {#if error}
                <p class="text-destructive text-sm" role="alert">{error}</p>
            {/if}
            {#if success}
                <div class="border-border/70 bg-muted/30 space-y-2 rounded-lg border p-3 text-sm" role="status" aria-live="polite">
                    <p class="text-foreground">{success}</p>
                    {#if openNow}
                        <Link href="/apply" class="text-primary inline-flex min-h-11 items-center gap-2 font-semibold underline underline-offset-4">
                            Continue to the application <ArrowRight class="size-4" />
                        </Link>
                    {/if}
                    <p class="text-muted-foreground">No application has been submitted and no place has been reserved.</p>
                </div>
            {/if}
        </form>
    {/if}
</section>
