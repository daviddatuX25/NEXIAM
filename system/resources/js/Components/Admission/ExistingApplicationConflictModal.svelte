<script>
    import { Button } from '@/Components/ui/button';
    import * as Dialog from '@/Components/ui/dialog';
    import { AlertTriangle, ArrowRight, Home, KeyRound } from 'lucide-svelte';
    import { router } from '@inertiajs/svelte';

    let {
        open = $bindable(false),
        conflict = null,
        mode = 'applicant',
        onClose = () => {},
    } = $props();

    function goToPortal() {
        onClose();
        router.visit('/applicant/login');
    }

    function goToHome() {
        onClose();
        router.visit('/');
    }

    function goToAdmissionsIndex() {
        onClose();
        router.visit('/admissions');
    }

    function goToExistingAttempt() {
        onClose();
        if (conflict?.id) {
            router.visit(`/admissions/${conflict.id}`);
        } else {
            router.visit('/admissions');
        }
    }
</script>

<Dialog.Root bind:open>
    <Dialog.Content class="sm:max-w-lg">
        <Dialog.Header class="space-y-3">
            <div
                class="bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300 inline-flex w-fit items-center gap-2 rounded-full px-3 py-1.5 text-sm font-semibold"
            >
                <AlertTriangle class="size-4" />
                <span>Existing Application Detected</span>
            </div>
            <Dialog.Title>Application already on file</Dialog.Title>
            <Dialog.Description>
                Our admission registry already has an active application on file
                for this legal identity in {conflict?.cycle_name ??
                    'the current cycle'}.
            </Dialog.Description>
        </Dialog.Header>

        <div class="bg-muted/50 border border-border/70 rounded-xl p-4 space-y-2 text-sm">
            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground">Applicant Name</span>
                <span class="text-right font-medium">{conflict?.applicant_name ?? '—'}</span>
            </div>
            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground">Application Reference No</span>
                <span class="font-mono font-bold text-primary text-right">{conflict?.reference_code ?? '—'}</span>
            </div>
            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground">Date Submitted</span>
                <span class="text-right">{conflict?.created_at ?? '—'}</span>
            </div>
            <div class="flex justify-between gap-4">
                <span class="text-muted-foreground">Status</span>
                <span class="bg-muted rounded-full px-2 py-0.5 font-medium capitalize">{conflict?.status ?? '—'}</span>
            </div>
        </div>

        <p class="bg-amber-50 text-amber-900 dark:bg-amber-950/30 dark:text-amber-200 rounded-lg p-3 text-sm leading-relaxed">
            Institutional policy permits only one application per academic year.
            You do not need to apply again.
        </p>

        <Dialog.Footer class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <Button variant="ghost" onclick={onClose}>
                Close &amp; Review
            </Button>
            {#if mode === 'staff'}
                <Button variant="outline" onclick={goToAdmissionsIndex}>
                    Back to Applications
                </Button>
                <Button onclick={goToExistingAttempt} class="gap-1.5 font-bold">
                    Open Existing Application
                    <ArrowRight class="size-4 ml-1" />
                </Button>
            {:else}
                <Button variant="outline" onclick={goToHome}>
                    <Home class="size-4 mr-1.5" />
                    Back to Home
                </Button>
                <Button onclick={goToPortal} class="gap-1.5 font-bold">
                    <KeyRound class="size-4" />
                    Sign in to Applicant Portal
                    <ArrowRight class="size-4 ml-1" />
                </Button>
            {/if}
        </Dialog.Footer>
    </Dialog.Content>
</Dialog.Root>
