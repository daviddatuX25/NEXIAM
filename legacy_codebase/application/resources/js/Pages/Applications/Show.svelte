<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, router } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Badge } from '@/Components/ui/badge';
  import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
  import { Textarea } from '@/Components/ui/textarea';
  import { ArrowLeft, CheckCircle, XCircle, Mail } from 'lucide-svelte';
  import { pipelineBadgeVariant, pipelineStatusLabel, pipelineMilestones, pipelineOrder } from '@/lib/pipeline-helpers';
  import { formatDate, formatDateTime } from '@/lib/date-utils';

  let { application, courses = [], within_application_window = false, application_window_label = null, pipeline_status = null, pipeline_details = null } = $props();

  function statusVariant(status) {
    if (status === 'pending') return 'warning';
    if (status === 'accepted') return 'success';
    if (status === 'dismissed') return 'danger';
    return 'muted';
  }

  function statusLabel(status) {
    const labels = {
      pending: 'Pending',
      accepted: 'Accepted',
      dismissed: 'Dismissed',
    };
    return labels[status] ?? status;
  }

  const fullName = $derived(
    [application?.first_name, application?.middle_name, application?.last_name, application?.suffix]
      .filter(Boolean)
      .join(' ') || '—'
  );

  let dismissReason = $state('');
  let showDismissModal = $state(false);

  function accept() {
    router.put(`/admin/applications/${application.id}/accept`);
  }

  function openDismiss() {
    showDismissModal = true;
  }

  function cancelDismiss() {
    showDismissModal = false;
    dismissReason = '';
  }

  function submitDismiss() {
    router.put(`/admin/applications/${application.id}/dismiss`, { reason: dismissReason.trim() || undefined });
    showDismissModal = false;
    dismissReason = '';
  }

  function resendSetupEmail() {
    router.post(`/admin/applications/${application.id}/resend-setup-email`);
  }

  function revertToPending() {
    if (confirm('Are you sure you want to revert this application to pending status?')) {
      router.put(`/admin/applications/${application.id}/reopen`);
    }
  }

  const courseLabel = (id) => {
    const c = courses.find((x) => x.id === id);
    return c ? `${c.code} – ${c.name}` : '—';
  };

  const canAccept = $derived(
    application && application.status === 'pending'
  );
  const canDismiss = $derived(
    application && application.status === 'pending'
  );
  const canRevert = $derived(
    application && ['accepted', 'dismissed'].includes(application.status)
  );
  const breadcrumbs = $derived([
    { label: 'Applications', href: '/admin/applications' },
    { label: application?.reference_number ?? 'Application' }
  ]);
</script>

<AuthenticatedLayout breadcrumbs={breadcrumbs}>
  <div class="space-y-6 min-w-0">
    <!-- Status + action bar -->
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-border bg-card px-4 py-3">
      <div class="flex flex-wrap items-center gap-3">
        {#if pipeline_status}
          <span class="text-sm text-muted-foreground">Status:</span>
          <Badge variant={pipelineBadgeVariant(pipeline_status)}>{pipelineStatusLabel(pipeline_status)}</Badge>
        {/if}
        {#if application_window_label}
          <span class="text-xs text-muted-foreground">{application_window_label}</span>
        {/if}
      </div>
      <div class="flex flex-wrap gap-2">
        {#if canAccept}
          <Button onclick={accept} size="sm" class="min-h-[40px]">
            <CheckCircle class="mr-1.5 h-4 w-4" />
            Accept
          </Button>
        {/if}
        {#if canDismiss}
          <Button
            onclick={openDismiss}
            variant="outline"
            size="sm"
            class="min-h-[40px] border-red-300 text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300"
          >
            <XCircle class="mr-1.5 h-4 w-4" />
            Dismiss
          </Button>
        {/if}
        {#if application?.status === 'accepted'}
          <Button onclick={resendSetupEmail} variant="outline" size="sm" class="min-h-[40px]">
            <Mail class="mr-1.5 h-4 w-4" />
            Resend Setup Email
          </Button>
        {/if}
        {#if canRevert}
          <Button onclick={revertToPending} variant="outline" size="sm" class="min-h-[40px]">
            <ArrowLeft class="mr-1.5 h-4 w-4" />
            Revert to Pending
          </Button>
        {/if}
      </div>
    </div>

    <!-- Pipeline Progress -->
    {#if pipeline_details}
      {@const visibleMilestones = pipelineMilestones({ isF2f: pipeline_details.is_f2f, isDirect: pipeline_details.is_direct })}
      <div class="rounded-lg border border-border bg-card px-4 py-3">
        <h3 class="mb-3 text-sm font-medium text-muted-foreground">Pipeline Progress</h3>
        <div class="flex items-center gap-1 overflow-x-auto pb-2">
          {#each visibleMilestones as milestone, i}
            {@const isActive = pipeline_details.status === milestone.key}
            {@const isPast = pipelineOrder(pipeline_details.status) > pipelineOrder(milestone.key)}
            {@const milestoneData = pipeline_details.milestones?.[milestone.key]}
            <div class="flex flex-col items-center min-w-[80px]">
              <div class="flex items-center gap-1">
                {#if i > 0}
                  <div class="h-0.5 w-4 {isPast ? 'bg-primary' : 'bg-muted'}"></div>
                {/if}
                <div
                  class="flex h-7 w-7 items-center justify-center rounded-full border-2 text-xs font-medium
                    {isActive ? 'border-primary bg-primary text-primary-foreground' : isPast ? 'border-primary bg-primary/10 text-primary' : 'border-muted-foreground/30 bg-background text-muted-foreground'}"
                >
                  {#if isPast}
                    ✓
                  {:else if isActive}
                    ●
                  {:else}
                    &nbsp;
                  {/if}
                </div>
              </div>
              <span class="mt-1 text-[10px] leading-tight text-center {isActive ? 'font-semibold text-foreground' : isPast ? 'text-muted-foreground' : 'text-muted-foreground/60'}">
                {milestone.label}
              </span>
              {#if milestoneData?.at}
                <span class="text-[9px] text-muted-foreground/60">
                  {formatDate(milestoneData.at, 'short')}
                </span>
              {/if}
            </div>
          {/each}
          <!-- Dismissed is a terminal state, shown separately -->
          {#if pipeline_details.status === 'dismissed'}
            <div class="flex flex-col items-center min-w-[80px]">
              <div class="flex items-center gap-1">
                <div class="h-0.5 w-4 bg-destructive"></div>
                <div class="flex h-7 w-7 items-center justify-center rounded-full border-2 border-destructive bg-destructive text-destructive-foreground text-xs font-medium">
                  ✗
                </div>
              </div>
              <span class="mt-1 text-[10px] leading-tight text-center font-semibold text-destructive">Dismissed</span>
            </div>
          {/if}
        </div>
      </div>
    {/if}

    <div class="grid gap-6 md:grid-cols-2">
      <Card>
        <CardHeader>
          <CardTitle>Personal details</CardTitle>
        </CardHeader>
        <CardContent class="space-y-2 text-sm">
          <p><span class="text-muted-foreground">Name:</span> {fullName}</p>
          <p><span class="text-muted-foreground">Birthdate:</span> {formatDate(application?.birthdate, 'long')}</p>
          <p><span class="text-muted-foreground">Sex:</span> {application?.sex ?? '—'}</p>
          <p><span class="text-muted-foreground">Classification:</span> {application?.applicant_type === 'transferee' ? 'Transferee' : 'New Student'}</p>
          {#if application?.last_school_enrolled}
            <p><span class="text-muted-foreground">Last school enrolled:</span> {application.last_school_enrolled}</p>
          {/if}
          {#if application?.strand}
            <p><span class="text-muted-foreground">SHS Strand / Previous Course:</span> {application.strand}</p>
          {/if}
          <p><span class="text-muted-foreground">Email:</span> {application?.email ?? '—'}</p>
          <p><span class="text-muted-foreground">Phone:</span> {application?.phone ?? '—'}</p>
          {#if application?.gwa != null}
            <p><span class="text-muted-foreground">GWA:</span> {application.gwa}</p>
          {/if}
          <p><span class="text-muted-foreground">Address:</span> {[application?.address_line, application?.city, application?.province, application?.zip_code].filter(Boolean).join(', ') || '—'}</p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Course preferences</CardTitle>
        </CardHeader>
        <CardContent class="space-y-2 text-sm">
          <p><span class="text-muted-foreground">1st:</span> {courseLabel(application?.course_preference_1)}</p>
          {#if application?.course_preference_2 != null}
            <p><span class="text-muted-foreground">2nd:</span> {courseLabel(application.course_preference_2)}</p>
          {/if}
          {#if application?.course_preference_3 != null}
            <p><span class="text-muted-foreground">3rd:</span> {courseLabel(application.course_preference_3)}</p>
          {/if}
          {#if application?.appointment_label}
            <p class="pt-2"><span class="text-muted-foreground">Appointment:</span> {application.appointment_label}</p>
          {/if}
        </CardContent>
      </Card>
    </div>

    <Card>
      <CardHeader>
        <CardTitle>Timeline</CardTitle>
      </CardHeader>
      <CardContent class="space-y-2 text-sm">
        <p><span class="text-muted-foreground">Submitted:</span> {formatDateTime(application?.submitted_at)}</p>
        {#if application?.processed_at}
          <p><span class="text-muted-foreground">Processed:</span> {formatDateTime(application.processed_at)}</p>
        {/if}
        {#if application?.status === 'dismissed' && application?.rejection_reason}
          <p><span class="text-muted-foreground">Dismissal reason:</span> {application.rejection_reason}</p>
        {/if}
      </CardContent>
    </Card>
  </div>

  {#if showDismissModal}
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="dismiss-title">
      <div class="w-full max-w-md rounded-lg border border-border bg-card p-6 shadow-lg">
        <h2 id="dismiss-title" class="text-lg font-semibold">Dismiss application</h2>
        <p class="mt-2 text-sm text-muted-foreground">Optionally provide a reason for dismissal.</p>
        <Textarea
          bind:value={dismissReason}
          placeholder="Reason for dismissal (optional)"
          rows="3"
          class="mt-3 w-full"
        />
        <div class="mt-4 flex justify-end gap-2">
          <Button variant="outline" onclick={cancelDismiss}>Cancel</Button>
          <Button
            variant="outline"
            class="border-red-300 bg-red-50/80 text-red-800 hover:bg-red-100/80 hover:border-red-400 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200 dark:hover:bg-red-900/40"
            onclick={submitDismiss}
          >
            Dismiss
          </Button>
        </div>
      </div>
    </div>
  {/if}
</AuthenticatedLayout>
