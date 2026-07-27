<script>
  import GuestLayout from '@/Layouts/GuestLayout.svelte';
  import { Button } from '@/Components/ui/button';
  import { CheckCircle } from 'lucide-svelte';

  let { reference_number, appointment_details } = $props();
</script>

<svelte:head>
  <title>Application Submitted - SecureCAT</title>
</svelte:head>

<GuestLayout>
  <div class="rounded-lg border border-border bg-card p-8 text-center shadow-lg">
    <CheckCircle class="mx-auto h-16 w-16 text-green-600" />
    <h1 class="mt-4 text-2xl font-bold">Application Submitted</h1>
    <p class="mt-2 text-muted-foreground">
      Your applicant number is <strong class="font-mono text-foreground">{reference_number}</strong>
    </p>
    {#if appointment_details}
      <p class="mt-4 text-sm text-muted-foreground">
        Appointment: {appointment_details}
      </p>
    {/if}
    <div class="mt-8 mb-4 text-left p-6 bg-muted/30 rounded-xl border border-border shadow-sm">
      <h3 class="font-bold text-lg mb-2 text-foreground">Next Steps</h3>
      <p class="text-sm text-muted-foreground mb-4">
        Please take a screenshot or write down your applicant number. To proceed with your application and exam scheduling, you must personally submit the following requirements to the Registrar's Office:
      </p>
      <ul class="list-disc list-inside text-sm text-foreground space-y-1.5 ml-2 mb-4">
        <li>Form 138 (Report Card) or Transcript of Records</li>
        <li>Certificate of Good Moral Character</li>
        <li>2 pcs Recent 2x2 ID Pictures with nametag</li>
        <li>Photocopy of PSA Birth Certificate</li>
      </ul>
      <p class="text-sm text-muted-foreground italic">
        * You will receive your portal login and finalized exam schedule via email once the registrar verifies your documents.
      </p>
    </div>
  </div>
</GuestLayout>
