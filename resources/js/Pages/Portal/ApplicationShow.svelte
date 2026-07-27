<script>
  import { Link } from '@inertiajs/svelte';
  import PortalLayout from '@/Layouts/PortalLayout.svelte';
  import { Button } from '@/Components/ui/button';
  import * as Card from '@/Components/ui/card';
  import { ArrowLeft, Edit, Lock, FileText, Download } from 'lucide-svelte';
  import { formatDate } from '@/lib/date-utils';

  let { application = {}, courses = [], admission_slip_enabled = false } = $props();
  const showAdmissionSlip = $derived(admission_slip_enabled && (application.status === 'accepted' || application.pipeline_status === 'accepted'));
</script>

<svelte:head>
  <title>My Application - SecureCAT</title>
</svelte:head>

<PortalLayout>
  <div class="mx-auto max-w-2xl space-y-6">
    <div class="flex items-center gap-4">
      <Link href="/portal">
        <Button variant="outline" size="icon" class="min-h-[44px] min-w-[44px]">
          <ArrowLeft class="h-4 w-4" />
        </Button>
      </Link>
      <div>
        <h1 class="text-2xl font-bold">My Application</h1>
        <p class="text-sm text-muted-foreground">Applicant No.: {application.reference_number}</p>
      </div>
    </div>

    <!-- Status Info -->
    <Card.Root>
      <Card.Content class="p-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-sm text-muted-foreground">Status</p>
            <p class="text-lg font-medium capitalize">{application.status}</p>
          </div>
          {#if application.is_editable}
            <Link href="/portal/application/edit">
              <Button class="gap-2">
                <Edit class="h-4 w-4" />
                Edit
              </Button>
            </Link>
          {:else if application.assigned_session_status === 'published'}
            <div class="flex items-center gap-2 text-muted-foreground">
              <Lock class="h-4 w-4" />
              <span class="text-sm">Locked</span>
            </div>
          {/if}
          {#if showAdmissionSlip}
            <Link href="/portal/admission-slip">
              <Button variant="outline" class="gap-2">
                <Download class="h-4 w-4" />
                Admission Slip
              </Button>
            </Link>
          {/if}
        </div>
      </Card.Content>
    </Card.Root>

    <!-- Personal Information -->
    <Card.Root>
      <Card.Header class="pb-3 border-b">
        <Card.Title class="text-lg">Personal Information</Card.Title>
      </Card.Header>
      <Card.Content class="pt-4 space-y-3">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <p class="text-sm text-muted-foreground">Full Name</p>
            <p class="font-medium">
              {[application.first_name, application.middle_name, application.last_name].filter(Boolean).join(' ')}
              {application.suffix || ''}
            </p>
          </div>
          <div>
            <p class="text-sm text-muted-foreground">Email</p>
            <p class="font-medium">{application.email}</p>
          </div>
          <div>
            <p class="text-sm text-muted-foreground">Birthdate</p>
            <p class="font-medium">{application.birthdate || '—'}</p>
          </div>
          <div>
            <p class="text-sm text-muted-foreground">Sex</p>
            <p class="font-medium capitalize">{application.sex || '—'}</p>
          </div>
          {#if application.phone}
            <div>
              <p class="text-sm text-muted-foreground">Phone</p>
              <p class="font-medium">{application.phone}</p>
            </div>
          {/if}
          {#if application.gwa != null}
            <div>
              <p class="text-sm text-muted-foreground">GWA</p>
              <p class="font-medium">{application.gwa}</p>
            </div>
          {/if}
          {#if application.strand}
            <div>
              <p class="text-sm text-muted-foreground">SHS Strand / Previous Course</p>
              <p class="font-medium">{application.strand}</p>
            </div>
          {/if}
        </div>
        {#if application.address_line || application.city || application.province}
          <div>
            <p class="text-sm text-muted-foreground">Address</p>
            <p class="font-medium">
              {[application.address_line, application.city, application.province, application.zip_code].filter(Boolean).join(', ')}
            </p>
          </div>
        {/if}
      </Card.Content>
    </Card.Root>

    <!-- Course Preferences -->
    <Card.Root>
      <Card.Header class="pb-3 border-b">
        <Card.Title class="text-lg">Course Preferences</Card.Title>
      </Card.Header>
      <Card.Content class="pt-4 space-y-2">
        {#if application.course_preference_1_label}
          <div class="flex items-center gap-2">
            <span class="text-sm text-muted-foreground w-8">1st:</span>
            <span class="font-medium">{application.course_preference_1_label}</span>
          </div>
        {/if}
        {#if application.course_preference_2_label}
          <div class="flex items-center gap-2">
            <span class="text-sm text-muted-foreground w-8">2nd:</span>
            <span class="font-medium">{application.course_preference_2_label}</span>
          </div>
        {/if}
        {#if application.course_preference_3_label}
          <div class="flex items-center gap-2">
            <span class="text-sm text-muted-foreground w-8">3rd:</span>
            <span class="font-medium">{application.course_preference_3_label}</span>
          </div>
        {/if}
        {#if !application.course_preference_1_label}
          <p class="text-muted-foreground">No course preferences recorded.</p>
        {/if}
      </Card.Content>
    </Card.Root>

    <!-- Submitted -->
    {#if application.submitted_at}
      <Card.Root>
        <Card.Content class="p-4">
          <div class="flex items-center gap-2 text-sm text-muted-foreground">
            <FileText class="h-4 w-4" />
            <span>Submitted on {formatDate(application.submitted_at)}</span>
          </div>
        </Card.Content>
      </Card.Root>
    {/if}
  </div>
</PortalLayout>