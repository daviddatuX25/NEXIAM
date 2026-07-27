<script>
  import { Link, router } from '@inertiajs/svelte';
  import PortalLayout from '@/Layouts/PortalLayout.svelte';
  import * as Card from '@/Components/ui/card';
  import { Button } from '@/Components/ui/button';
  import ApplicationStatusTimeline from '@/Components/ApplicationStatusTimeline.svelte';
  import ApplicantTips from '@/Components/ApplicantTips.svelte';
  import { FileText, Mail, FileBadge2, MapPin, Calendar, Download, Printer } from 'lucide-svelte';

  let {
    applicant = {},
    application = null,
    status_tracker,
    exam_schedule = null,
    consultation = { status: 'pending', summary: null },
    ai_companion_enabled = false,
    results_blocked = false,
    admission_slip_enabled = false,
  } = $props();
  const safeStatusTracker = $derived(Array.isArray(status_tracker) ? status_tracker : []);
  const isAdmitted = $derived(safeStatusTracker.some((s) => s.stage === 'Successfully admitted' && s.completed));
  const showAdmissionSlip = $derived(admission_slip_enabled && isAdmitted);
</script>

<PortalLayout>
  <div class="lg:grid lg:grid-cols-3 lg:gap-8 space-y-8 lg:space-y-0">
    <!-- Left Column: Main Content -->
    <div class="lg:col-span-2 space-y-6">
      
      <!-- Applicant Info Card -->
      <Card.Root class="overflow-hidden border-primary/20 bg-primary/5">
        <Card.Content class="p-6 md:p-8">
          <div class="flex flex-col gap-2">
            <h1 class="text-3xl font-bold tracking-tight text-foreground">
              Welcome, {applicant.name ?? 'Applicant'}
            </h1>
            <div class="flex flex-col sm:flex-row sm:items-center gap-4 mt-2 text-muted-foreground">
              <span class="flex items-center gap-1.5 bg-background/50 px-3 py-1.5 rounded-md border text-sm font-mono shadow-sm">
                <FileBadge2 class="h-4 w-4 text-primary" />
                Ref: {applicant.reference_number ?? '—'}
              </span>
              {#if applicant.email}
                <span class="flex items-center gap-1.5 text-sm">
                  <Mail class="h-4 w-4" />
                  {applicant.email}
                </span>
              {/if}
            </div>
          </div>
        </Card.Content>
      </Card.Root>

      <!-- Application Status Timeline -->
      <Card.Root class="overflow-hidden shadow-sm">
        <Card.Header class="pb-4 border-b bg-muted/20">
          <Card.Title class="text-xl">Application Status</Card.Title>
          <Card.Description>Track your admission progress</Card.Description>
        </Card.Header>
        <Card.Content class="pt-6">
          {#if safeStatusTracker.length > 0}
            <ApplicationStatusTimeline stages={safeStatusTracker} />
          {:else}
            <div class="flex flex-col items-center justify-center py-12 text-center">
              <div class="h-12 w-12 rounded-full bg-muted flex items-center justify-center mb-4">
                <FileText class="h-6 w-6 text-muted-foreground" />
              </div>
              <p class="text-lg font-medium">No progress yet</p>
              <p class="text-muted-foreground mt-1 max-w-sm">
                Your progress will appear here once your application has been submitted and processed.
              </p>
            </div>
          {/if}
        </Card.Content>
      </Card.Root>

      <!-- Edit Application Button (if editable) -->
      {#if application?.is_editable}
        <Card.Root class="border-primary/30 bg-primary/5">
          <Card.Content class="p-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="font-medium">Application Details</p>
                <p class="text-sm text-muted-foreground">You can update your application information</p>
              </div>
              <Link href="/portal/application/edit">
                <Button>Edit Application</Button>
              </Link>
            </div>
          </Card.Content>
        </Card.Root>
      {:else if application?.assigned_session_status === 'published'}
        <Card.Root class="border-muted">
          <Card.Content class="p-6 text-center">
            <p class="text-muted-foreground">Your application is locked. Contact the registrar's office for assistance.</p>
          </Card.Content>
        </Card.Root>
      {/if}

      <!-- Results blocked banner (f2f mode) -->
      {#if results_blocked}
        <Card.Root>
          <Card.Content class="p-6 text-center space-y-2">
            <p class="font-medium text-foreground">Results will be provided in person</p>
            <p class="text-sm text-muted-foreground">
              Please visit the guidance office to receive your exam results.
            </p>
          </Card.Content>
        </Card.Root>
      {/if}

    </div>

    <!-- Right Column: Sidebar -->
    <div class="space-y-6">
      
      <!-- Tips/Trivia -->
      <ApplicantTips />

      <!-- Exam Schedule -->
      {#if exam_schedule?.assigned}
        <Card.Root>
          <Card.Header class="pb-3 border-b bg-primary/5">
            <Card.Title class="text-lg">Exam Schedule</Card.Title>
            <Card.Description>Your assigned session</Card.Description>
          </Card.Header>
          <Card.Content class="pt-4 space-y-3">
            <div class="flex items-start gap-3">
              <Calendar class="h-5 w-5 text-primary mt-0.5" />
              <div>
                <p class="font-medium">{exam_schedule.date}</p>
                <p class="text-sm text-muted-foreground">{exam_schedule.time}</p>
              </div>
            </div>
            <div class="flex items-start gap-3">
              <MapPin class="h-5 w-5 text-primary mt-0.5" />
              <div>
                <p class="font-medium">{exam_schedule.room}</p>
                <p class="text-sm text-muted-foreground">{exam_schedule.building}</p>
              </div>
            </div>
          </Card.Content>
        </Card.Root>
      {/if}

      <!-- Admission Slip -->
      {#if showAdmissionSlip}
        <Card.Root class="border-primary/30 shadow-sm bg-gradient-to-br from-background to-primary/5">
          <Card.Header>
            <Card.Title class="text-lg">Admission Slip</Card.Title>
            <Card.Description>Download or print your admission slip for the exam.</Card.Description>
          </Card.Header>
          <Card.Content class="space-y-3">
            <div class="flex flex-col gap-2">
              <Link href="/portal/admission-slip" class="block w-full">
                <Button variant="default" class="w-full gap-2">
                  <Download class="h-4 w-4" />
                  Download PDF
                </Button>
              </Link>
              <Link href="/portal/admission-slip/view" target="_blank" class="block w-full">
                <Button variant="outline" class="w-full gap-2">
                  <Printer class="h-4 w-4" />
                  Print Slip
                </Button>
              </Link>
            </div>
          </Card.Content>
        </Card.Root>
      {/if}

      <!-- AI Companion -->
      {#if ai_companion_enabled && consultation.status === 'released'}
        <Card.Root class="border-primary/30 shadow-sm bg-gradient-to-br from-background to-primary/5">
          <Card.Header>
            <Card.Title class="text-lg">Chat with Advisor</Card.Title>
            <Card.Description>Ask questions about your results and course fit. Advice is based on your scores and institutional data.</Card.Description>
          </Card.Header>
          <Card.Content>
            <Link href="/portal/ai-companion" class="block w-full">
              <Button class="w-full">Open Chat</Button>
            </Link>
          </Card.Content>
        </Card.Root>
      {/if}

      </div>
  </div>
</PortalLayout>
