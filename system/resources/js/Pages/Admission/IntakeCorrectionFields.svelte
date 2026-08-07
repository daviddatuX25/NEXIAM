<script>
  /**
   * Shared intake field groups for registrar Update-for-applicant + applicant Correction.
   * Mirrors public Apply.svelte section order/labels. Identity core fields are read-only
   * (normalized identity key HOLD — see OpenSpec design note).
   */
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import { Textarea } from '@/Components/ui/textarea';

  let {
    form,
    programs = [],
    grade_fields = [],
    identity = null,
    identityEditable = false,
    showInternalNotes = false,
    showResubmissionNotes = false,
    idPrefix = 'intake',
  } = $props();

  const optionsFor2 = $derived(
    programs.filter((p) => p.code !== form.program_preference_1_code),
  );
  const optionsFor3 = $derived(
    programs.filter(
      (p) =>
        p.code !== form.program_preference_1_code && p.code !== form.program_preference_2_code,
    ),
  );

  $effect(() => {
    const p1 = form.program_preference_1_code;
    const p2 = form.program_preference_2_code;
    const p3 = form.program_preference_3_code;
    if (p2 && p2 === p1) {
      form.program_preference_2_code = '';
    }
    if (p3 && (p3 === p1 || p3 === p2)) {
      form.program_preference_3_code = '';
    }
  });
</script>

<div class="space-y-6">
  <section class="space-y-3">
    <h3 class="text-sm font-medium">Personal information</h3>
    <p class="text-xs text-muted-foreground">
      {#if identityEditable}
        Legal name and identity fields are editable only on this authorized post-accept path.
        Status stays accepted — exam assignment is not cancelled.
      {:else}
        Legal name, birthdate, and sex are locked to the applicant identity record. Quiet correction
        cannot change them — use Authorize amendment after accept if needed.
      {/if}
    </p>
    <div class="grid gap-3 sm:grid-cols-2">
      <div class="space-y-1.5">
        <Label for={`${idPrefix}-first_name`}>First name</Label>
        {#if identityEditable}
          <Input id={`${idPrefix}-first_name`} bind:value={form.identity_first_name} class="min-h-11" />
        {:else}
          <Input id={`${idPrefix}-first_name`} value={identity?.first_name ?? ''} disabled class="min-h-11" />
        {/if}
      </div>
      <div class="space-y-1.5">
        <Label for={`${idPrefix}-last_name`}>Last name</Label>
        {#if identityEditable}
          <Input id={`${idPrefix}-last_name`} bind:value={form.identity_last_name} class="min-h-11" />
        {:else}
          <Input id={`${idPrefix}-last_name`} value={identity?.last_name ?? ''} disabled class="min-h-11" />
        {/if}
      </div>
      <div class="space-y-1.5">
        <Label for={`${idPrefix}-middle_name`}>
          Middle name <span class="text-muted-foreground">(optional)</span>
        </Label>
        {#if identityEditable}
          <Input id={`${idPrefix}-middle_name`} bind:value={form.identity_middle_name} class="min-h-11" />
        {:else}
          <Input id={`${idPrefix}-middle_name`} value={identity?.middle_name ?? ''} disabled class="min-h-11" />
        {/if}
      </div>
      <div class="space-y-1.5">
        <Label for={`${idPrefix}-suffix`}>
          Suffix <span class="text-muted-foreground">(optional)</span>
        </Label>
        {#if identityEditable}
          <Input id={`${idPrefix}-suffix`} bind:value={form.identity_suffix} class="min-h-11" />
        {:else}
          <Input id={`${idPrefix}-suffix`} value={identity?.suffix ?? ''} disabled class="min-h-11" />
        {/if}
      </div>
      <div class="space-y-1.5">
        <Label for={`${idPrefix}-identity-email`}>Email (identity)</Label>
        {#if identityEditable}
          <Input
            id={`${idPrefix}-identity-email`}
            type="email"
            bind:value={form.identity_email}
            class="min-h-11"
          />
        {:else}
          <Input
            id={`${idPrefix}-identity-email`}
            type="email"
            value={identity?.email ?? ''}
            disabled
            class="min-h-11"
          />
        {/if}
      </div>
      <div class="space-y-1.5">
        <Label for={`${idPrefix}-birthdate`}>Birthdate</Label>
        {#if identityEditable}
          <Input
            id={`${idPrefix}-birthdate`}
            type="date"
            bind:value={form.identity_birthdate}
            class="min-h-11"
          />
        {:else}
          <Input id={`${idPrefix}-birthdate`} type="date" value={identity?.birthdate ?? ''} disabled class="min-h-11" />
        {/if}
      </div>
      <div class="space-y-1.5">
        <Label for={`${idPrefix}-sex`}>Sex</Label>
        {#if identityEditable}
          <select
            id={`${idPrefix}-sex`}
            bind:value={form.identity_sex}
            class="border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
          >
            <option value="male">Male</option>
            <option value="female">Female</option>
          </select>
        {:else}
          <Input
            id={`${idPrefix}-sex`}
            value={identity?.sex === 'female' ? 'Female' : identity?.sex === 'male' ? 'Male' : (identity?.sex ?? '')}
            disabled
            class="min-h-11"
          />
        {/if}
      </div>
      <div class="space-y-1.5">
        <Label for={`${idPrefix}-applicant_type`}>Applicant type</Label>
        <select
          id={`${idPrefix}-applicant_type`}
          bind:value={form.applicant_type}
          class="border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
        >
          <option value="new">New</option>
          <option value="transferee">Transferee</option>
        </select>
        {#if form.errors?.applicant_type}
          <p class="text-sm text-destructive">{form.errors.applicant_type}</p>
        {/if}
      </div>
    </div>
  </section>

  <section class="space-y-3">
    <h3 class="text-sm font-medium">Academic background</h3>
    <p class="text-xs text-muted-foreground">
      Digital grades are a consultation snapshot. Hard-copy Form 138 / TOR is still required at the
      Registrar.
    </p>
    <div class="grid gap-3 sm:grid-cols-2">
      <div class="space-y-1.5 sm:col-span-2">
        <Label for={`${idPrefix}-last_school`}>Last school enrolled</Label>
        <Input
          id={`${idPrefix}-last_school`}
          bind:value={form.last_school_enrolled}
          class="min-h-11"
          placeholder="School of origin"
        />
      </div>
      <div class="space-y-1.5">
        <Label for={`${idPrefix}-strand`}>
          {form.applicant_type === 'transferee' ? 'Previous course' : 'SHS strand'}
        </Label>
        <Input id={`${idPrefix}-strand`} bind:value={form.strand} class="min-h-11" />
      </div>
      {#each grade_fields as field (field.code)}
        <div class="space-y-1.5">
          <Label for={`${idPrefix}-grade_${field.code}`}>{field.label}</Label>
          <Input
            id={`${idPrefix}-grade_${field.code}`}
            type="number"
            step="0.01"
            min={field.min_value}
            max={field.max_value}
            bind:value={form.grades[field.code]}
            class="min-h-11"
          />
          {#if form.errors?.[`grades.${field.code}`]}
            <p class="text-sm text-destructive">{form.errors[`grades.${field.code}`]}</p>
          {/if}
        </div>
      {/each}
    </div>
  </section>

  <section class="space-y-3">
    <h3 class="text-sm font-medium">
      Contact information <span class="font-normal text-muted-foreground">(optional)</span>
    </h3>
    <div class="grid gap-3 sm:grid-cols-2">
      <div class="space-y-1.5 sm:col-span-2">
        <Label for={`${idPrefix}-contact_email`}>Email</Label>
        <Input
          id={`${idPrefix}-contact_email`}
          type="email"
          bind:value={form.contact_email}
          class="min-h-11"
        />
        {#if form.errors?.contact_email}
          <p class="text-sm text-destructive">{form.errors.contact_email}</p>
        {/if}
      </div>
      <div class="space-y-1.5">
        <Label for={`${idPrefix}-phone`}>Phone</Label>
        <Input id={`${idPrefix}-phone`} type="tel" bind:value={form.contact_phone} class="min-h-11" />
      </div>
      <div class="space-y-1.5 sm:col-span-2">
        <Label for={`${idPrefix}-address`}>Address</Label>
        <Input id={`${idPrefix}-address`} bind:value={form.address_line} class="min-h-11" />
      </div>
      <div class="space-y-1.5">
        <Label for={`${idPrefix}-city`}>City / municipality</Label>
        <Input id={`${idPrefix}-city`} bind:value={form.city} class="min-h-11" />
      </div>
      <div class="space-y-1.5">
        <Label for={`${idPrefix}-province`}>Province</Label>
        <Input id={`${idPrefix}-province`} bind:value={form.province} class="min-h-11" />
      </div>
      <div class="space-y-1.5">
        <Label for={`${idPrefix}-zip`}>ZIP code</Label>
        <Input id={`${idPrefix}-zip`} bind:value={form.zip_code} class="min-h-11" />
      </div>
    </div>
  </section>

  <section class="space-y-3">
    <h3 class="text-sm font-medium">Program preferences</h3>
    <p class="text-xs text-muted-foreground">
      Choose up to three distinct programs. First preference is required on apply; keep at least one
      when correcting.
    </p>
    <div class="space-y-3">
      <div class="space-y-1.5">
        <Label for={`${idPrefix}-pref1`}>First preference</Label>
        <select
          id={`${idPrefix}-pref1`}
          bind:value={form.program_preference_1_code}
          class="border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
        >
          <option value="">Select a program</option>
          {#each programs as program (program.code)}
            <option value={program.code}>{program.name} ({program.code})</option>
          {/each}
        </select>
        {#if form.errors?.program_preference_1_code}
          <p class="text-sm text-destructive">{form.errors.program_preference_1_code}</p>
        {/if}
      </div>
      <div class="space-y-1.5">
        <Label for={`${idPrefix}-pref2`}>
          Second preference <span class="text-muted-foreground">(optional)</span>
        </Label>
        <select
          id={`${idPrefix}-pref2`}
          bind:value={form.program_preference_2_code}
          class="border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
        >
          <option value="">None</option>
          {#each optionsFor2 as program (program.code)}
            <option value={program.code}>{program.name} ({program.code})</option>
          {/each}
        </select>
      </div>
      <div class="space-y-1.5">
        <Label for={`${idPrefix}-pref3`}>
          Third preference <span class="text-muted-foreground">(optional)</span>
        </Label>
        <select
          id={`${idPrefix}-pref3`}
          bind:value={form.program_preference_3_code}
          class="border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
        >
          <option value="">None</option>
          {#each optionsFor3 as program (program.code)}
            <option value={program.code}>{program.name} ({program.code})</option>
          {/each}
        </select>
      </div>
    </div>
  </section>

  {#if showInternalNotes}
    <section class="space-y-1.5">
      <Label for={`${idPrefix}-notes`}>Internal notes <span class="text-muted-foreground">(registrar)</span></Label>
      <Textarea id={`${idPrefix}-notes`} bind:value={form.correction_notes} rows={2} />
    </section>
  {/if}

  {#if showResubmissionNotes}
    <section class="space-y-1.5">
      <Label for={`${idPrefix}-resubmit-notes`}>What did you fix? <span class="text-muted-foreground">(optional)</span></Label>
      <Textarea id={`${idPrefix}-resubmit-notes`} bind:value={form.resubmission_notes} rows={3} />
    </section>
  {/if}
</div>
