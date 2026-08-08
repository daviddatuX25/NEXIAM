<script>
  import ApplicantLayout from '@/Pages/Applicant/ApplicantLayout.svelte';
  import IntakeCorrectionFields from '@/Pages/Admission/IntakeCorrectionFields.svelte';
  import { Button } from '@/Components/ui/button';
  import { Link, useForm } from '@inertiajs/svelte';
  import { ArrowLeft } from 'lucide-svelte';

  let {
    page_state = 'ready',
    mode = 'resubmit',
    attempt,
    programs = [],
    grade_fields = [],
  } = $props();

  const isAmend = $derived(mode === 'amend');

  const initialGrades = Object.fromEntries(
    (grade_fields ?? []).map((f) => [
      f.code,
      attempt.grades?.find((g) => g.code === f.code)?.value ?? '',
    ]),
  );

  const form = useForm({
    expected_version: attempt.version,
    applicant_type: attempt.applicant_type ?? 'new',
    contact_email: attempt.contact_email ?? '',
    contact_phone: attempt.contact_phone ?? '',
    program_preference_1_code: attempt.program_preference_1_code ?? '',
    program_preference_2_code: attempt.program_preference_2_code ?? '',
    program_preference_3_code: attempt.program_preference_3_code ?? '',
    address_line: attempt.address_line ?? '',
    city: attempt.city ?? '',
    province: attempt.province ?? '',
    zip_code: attempt.zip_code ?? '',
    last_school_enrolled: attempt.last_school_enrolled ?? '',
    strand: attempt.strand ?? '',
    grades: initialGrades,
    resubmission_notes: '',
  });

  function submit() {
    form.expected_version = attempt.version;
    form.post(`/applicant/applications/${attempt.id}/correct`);
  }
</script>

<svelte:head>
  <title
    >{isAmend ? 'Update details' : 'Correct application'} · {attempt.reference_code}</title
  >
</svelte:head>

<ApplicantLayout title={isAmend ? 'Update details' : 'Correct application'}>
  <div class="mx-auto max-w-2xl space-y-6">
    <div class="space-y-2">
      <Link
        href="/applicant/schedule"
        class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
      >
        <ArrowLeft class="size-4" />
        Back to portal
      </Link>
      <h1 class="text-2xl font-semibold tracking-tight">
        {isAmend ? 'Update my details' : 'Respond to correction request'}
      </h1>
      <p class="text-sm text-muted-foreground">
        Reference <span class="font-mono">{attempt.reference_code}</span>.
        {#if isAmend}
          Fix a typo or contact detail while your application is still pending. This stays in review
          — no loud status change.
        {:else}
          Update the fields the registrar asked for, then resubmit for review.
        {/if}
      </p>
    </div>

    {#if !isAmend && attempt.correction_notes}
      <p class="rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm">
        Registrar request: {attempt.correction_notes}
      </p>
    {/if}

    <form
      class="space-y-6"
      onsubmit={(e) => {
        e.preventDefault();
        submit();
      }}
    >
      <IntakeCorrectionFields
        {form}
        {programs}
        {grade_fields}
        identity={attempt.identity}
        showResubmissionNotes={!isAmend}
        idPrefix="correct"
      />

      {#if form.errors.version}
        <p class="text-sm text-destructive">{form.errors.version}</p>
      {/if}

      <div class="flex flex-wrap gap-2">
        <Button type="submit" class="min-h-11" disabled={form.processing}>
          {isAmend ? 'Save details' : 'Resubmit for review'}
        </Button>
        <Link href="/applicant/schedule">
          <Button type="button" variant="outline" class="min-h-11">Cancel</Button>
        </Link>
      </div>
    </form>
  </div>
</ApplicantLayout>
