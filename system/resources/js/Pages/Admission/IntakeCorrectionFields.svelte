<script>
  /**
   * Shared application field groups for Registrar New Application, Edit Application,
   * and Applicant Correction. Mirrors public Apply.svelte section order, labels,
   * reference suggestions, MM-DD-YYYY birthdate input, and PSGC address selector.
   */
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import { Textarea } from '@/Components/ui/textarea';
  import ApplicantBirthdateInput from '@/Components/ApplicantBirthdateInput.svelte';
  import ApplicantIntakeSuggestionInput from '@/Components/ApplicantIntakeSuggestionInput.svelte';
  import PhilippineAddressSelector from '@/Components/PhilippineAddressSelector.svelte';
  import ReasonSuggestionChips from '@/Components/kit/ReasonSuggestionChips.svelte';
  import {
    formatIsoBirthdate,
    parseMmDdYyyyBirthdate,
  } from '@/lib/applicant-birthdate';
  import {
    ADMISSION_APPLICANT_RESUBMISSION_SUGGESTIONS,
    ADMISSION_STAFF_CORRECTION_NOTE_SUGGESTIONS,
  } from '@/lib/reason-suggestions.js';

  let {
    form,
    programs = [],
    intake_suggestions = { schools: [], shs: [], programs: [] },
    identity = null,
    identityEditable = false,
    birthdateEditable = true,
    allowManualAddress = true,
    showInternalNotes = false,
    showResubmissionNotes = false,
    idPrefix = 'application',
    birthdateFormatError = $bindable(''),
  } = $props();

  function initialBirthdateDisplay() {
    const raw = form.identity_birthdate || form.birthdate || identity?.birthdate || '';
    return formatIsoBirthdate(raw) ?? raw;
  }

  let birthdateInput = $state(initialBirthdateDisplay());

  $effect(() => {
    const externalIso = form.identity_birthdate || form.birthdate || '';
    if (!externalIso && parseMmDdYyyyBirthdate(birthdateInput)) {
      birthdateInput = '';
      return;
    }
    const formatted = formatIsoBirthdate(externalIso);
    if (formatted && birthdateInput !== formatted && parseMmDdYyyyBirthdate(birthdateInput) !== externalIso) {
      birthdateInput = formatted;
    }
  });

  function handleBirthdateInput() {
    birthdateFormatError = '';
    form.clearErrors?.('birthdate', 'identity_birthdate');
    const parsed =
      parseMmDdYyyyBirthdate(birthdateInput) ??
      (/^\d{4}-\d{2}-\d{2}$/.test(birthdateInput) ? birthdateInput : '');
    form.identity_birthdate = parsed;
    if ('birthdate' in form) {
      form.birthdate = parsed;
    }
  }

  $effect(() => {
    if (identityEditable && form.contact_email !== undefined) {
      form.identity_email = form.contact_email;
      if ('email' in form) {
        form.email = form.contact_email;
      }
    }
  });

  $effect(() => {
    if (identityEditable && form.identity_sex !== undefined && 'sex' in form) {
      form.sex = form.identity_sex;
    }
  });

  const academicHistorySuggestions = $derived(
    form.applicant_type === 'transferee'
      ? intake_suggestions.programs
      : intake_suggestions.shs,
  );
  const academicHistoryLabel = $derived(
    form.applicant_type === 'transferee'
      ? 'Previous course or program'
      : 'SHS strand / track',
  );
  const suggestionHelperText = 'Optional: choose a suggestion or enter your own.';

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

<div class="space-y-8">
  <section class="space-y-4">
    <div class="flex items-center justify-between">
      <h3 class="text-foreground text-lg font-semibold tracking-tight">Personal information</h3>
      <span class="text-muted-foreground text-xs">Official PSA records</span>
    </div>
    <div
      class="border-border/70 bg-muted/20 text-muted-foreground rounded-xl border p-3.5 text-xs leading-relaxed"
    >
      {#if identityEditable && birthdateEditable}
        Enter legal name, birthdate, and demographic information exactly as shown on the PSA birth certificate or official school documents for identity verification.
      {:else if identityEditable && !birthdateEditable}
        You may update your legal name and sex while your application is open for updates. Birthdate is part of your sign-in credential — contact the Registrar's Office if it needs correction.
      {:else}
        Legal name, birthdate, and sex are locked to the applicant identity record.
      {/if}
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
      <div class="space-y-2">
        <Label for={`${idPrefix}-first_name`}>
          First name <span class="text-destructive">*</span>
        </Label>
        {#if identityEditable}
          <Input
            id={`${idPrefix}-first_name`}
            bind:value={form.identity_first_name}
            required
            class="min-h-11"
          />
        {:else}
          <Input
            id={`${idPrefix}-first_name`}
            value={identity?.first_name ?? ''}
            disabled
            class="min-h-11"
          />
        {/if}
        {#if form.errors?.identity_first_name || form.errors?.first_name}
          <p class="text-sm text-destructive">
            {form.errors.identity_first_name || form.errors.first_name}
          </p>
        {/if}
      </div>
      <div class="space-y-2">
        <Label for={`${idPrefix}-last_name`}>
          Last name <span class="text-destructive">*</span>
        </Label>
        {#if identityEditable}
          <Input
            id={`${idPrefix}-last_name`}
            bind:value={form.identity_last_name}
            required
            class="min-h-11"
          />
        {:else}
          <Input
            id={`${idPrefix}-last_name`}
            value={identity?.last_name ?? ''}
            disabled
            class="min-h-11"
          />
        {/if}
        {#if form.errors?.identity_last_name || form.errors?.last_name}
          <p class="text-sm text-destructive">
            {form.errors.identity_last_name || form.errors.last_name}
          </p>
        {/if}
      </div>
      <div class="space-y-2">
        <Label for={`${idPrefix}-middle_name`}>
          Middle name <span class="text-muted-foreground font-normal">(optional)</span>
        </Label>
        {#if identityEditable}
          <Input id={`${idPrefix}-middle_name`} bind:value={form.identity_middle_name} class="min-h-11" />
        {:else}
          <Input id={`${idPrefix}-middle_name`} value={identity?.middle_name ?? ''} disabled class="min-h-11" />
        {/if}
        {#if form.errors?.identity_middle_name || form.errors?.middle_name}
          <p class="text-sm text-destructive">
            {form.errors.identity_middle_name || form.errors.middle_name}
          </p>
        {/if}
      </div>
      <div class="space-y-2">
        <Label for={`${idPrefix}-suffix`}>
          Suffix <span class="text-muted-foreground font-normal">(optional)</span>
        </Label>
        {#if identityEditable}
          <Input
            id={`${idPrefix}-suffix`}
            bind:value={form.identity_suffix}
            class="min-h-11"
            placeholder="Jr., III"
          />
        {:else}
          <Input id={`${idPrefix}-suffix`} value={identity?.suffix ?? ''} disabled class="min-h-11" />
        {/if}
        {#if form.errors?.identity_suffix || form.errors?.suffix}
          <p class="text-sm text-destructive">
            {form.errors.identity_suffix || form.errors.suffix}
          </p>
        {/if}
      </div>
      <div class="space-y-2">
        <Label for={`${idPrefix}-contact_email`}>
          Email <span class="text-muted-foreground font-normal">(optional)</span>
        </Label>
        <Input
          id={`${idPrefix}-contact_email`}
          type="email"
          bind:value={form.contact_email}
          class="min-h-11"
        />
        {#if form.errors?.contact_email || form.errors?.identity_email || form.errors?.email}
          <p class="text-sm text-destructive">
            {form.errors.contact_email || form.errors.identity_email || form.errors.email}
          </p>
        {/if}
      </div>
      {#if identityEditable && birthdateEditable}
        <ApplicantBirthdateInput
          id={`${idPrefix}-birthdate`}
          bind:value={birthdateInput}
          error={birthdateFormatError || form.errors?.identity_birthdate || form.errors?.birthdate}
          oninput={handleBirthdateInput}
        />
      {:else}
        <div class="space-y-2">
          <Label for={`${idPrefix}-birthdate`}>Birthdate</Label>
          <Input
            id={`${idPrefix}-birthdate`}
            type="text"
            value={formatIsoBirthdate(identity?.birthdate ?? '') ?? identity?.birthdate ?? ''}
            disabled
            class="min-h-11"
          />
          {#if form.errors?.identity_birthdate || form.errors?.birthdate}
            <p class="text-sm text-destructive">
              {form.errors.identity_birthdate || form.errors.birthdate}
            </p>
          {/if}
        </div>
      {/if}
      <div class="space-y-2">
        <Label for={`${idPrefix}-sex`}>
          Sex <span class="text-destructive">*</span>
        </Label>
        {#if identityEditable}
          <select
            id={`${idPrefix}-sex`}
            bind:value={form.identity_sex}
            required
            class="border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
          >
            <option value="" disabled>Select</option>
            <option value="female">Female</option>
            <option value="male">Male</option>
          </select>
        {:else}
          <Input
            id={`${idPrefix}-sex`}
            value={identity?.sex === 'female' ? 'Female' : identity?.sex === 'male' ? 'Male' : (identity?.sex ?? '')}
            disabled
            class="min-h-11"
          />
        {/if}
        {#if form.errors?.identity_sex || form.errors?.sex}
          <p class="text-sm text-destructive">
            {form.errors.identity_sex || form.errors.sex}
          </p>
        {/if}
      </div>
      <div class="space-y-2">
        <Label for={`${idPrefix}-applicant_type`}>
          Applicant classification <span class="text-destructive">*</span>
        </Label>
        <select
          id={`${idPrefix}-applicant_type`}
          bind:value={form.applicant_type}
          required
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

  <section class="space-y-4">
    <h3 class="text-foreground text-lg font-semibold tracking-tight">Academic background</h3>
    <div class="grid gap-4 sm:grid-cols-2">
      <div class="space-y-2 sm:col-span-2">
        <ApplicantIntakeSuggestionInput
          id={`${idPrefix}-last_school`}
          label="Last school enrolled"
          bind:value={form.last_school_enrolled}
          suggestions={intake_suggestions.schools}
          placeholder="Type a school name or acronym"
          helperText={suggestionHelperText}
          error={form.errors?.last_school_enrolled}
        />
      </div>
      <div class="space-y-2 sm:col-span-2">
        <ApplicantIntakeSuggestionInput
          id={`${idPrefix}-strand`}
          label={academicHistoryLabel}
          bind:value={form.strand}
          suggestions={academicHistorySuggestions}
          placeholder="Type a name or acronym"
          helperText={suggestionHelperText}
          error={form.errors?.strand}
        />
      </div>
    </div>
  </section>

  <section class="space-y-4">
    <div class="flex items-center justify-between">
      <h3 class="text-foreground text-lg font-semibold tracking-tight">Contact information</h3>
      <span class="text-muted-foreground text-xs">Optional</span>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
      <div class="space-y-2 sm:col-span-2">
        <Label for={`${idPrefix}-phone`}>
          Phone <span class="text-muted-foreground font-normal">(optional)</span>
        </Label>
        <Input
          id={`${idPrefix}-phone`}
          type="tel"
          bind:value={form.contact_phone}
          class="min-h-11"
          placeholder="e.g. 09171234567"
        />
        {#if form.errors?.contact_phone || form.errors?.phone}
          <p class="text-sm text-destructive">{form.errors.contact_phone || form.errors.phone}</p>
        {/if}
      </div>
    </div>
    <PhilippineAddressSelector
      bind:address_line={form.address_line}
      bind:city={form.city}
      bind:province={form.province}
      bind:zip_code={form.zip_code}
      idPrefix="{idPrefix}-ph-addr"
      allowManual={allowManualAddress}
      errors={form.errors}
    />
  </section>

  <section class="space-y-4">
    <div class="flex items-center justify-between">
      <h3 class="text-foreground text-lg font-semibold tracking-tight">Program preferences</h3>
      <span class="text-muted-foreground text-xs">Degree choices</span>
    </div>
    <div
      class="border-border/70 bg-muted/20 text-muted-foreground rounded-xl border p-3.5 text-xs leading-relaxed"
    >
      Choose up to three programs in order of preference. Your 1st preference is your primary admission goal; 2nd and 3rd choices serve as alternative degree options if first-choice quotas are reached.
    </div>
    <div class="space-y-3">
      <div class="space-y-2">
        <Label for={`${idPrefix}-pref1`}>
          First preference <span class="text-destructive">*</span>
        </Label>
        <select
          id={`${idPrefix}-pref1`}
          bind:value={form.program_preference_1_code}
          required
          class="border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
        >
          <option value="" disabled>Select a program</option>
          {#each programs as program (program.code)}
            <option value={program.code}>{program.name} ({program.code})</option>
          {/each}
        </select>
        {#if form.errors?.program_preference_1_code}
          <p class="text-sm text-destructive">{form.errors.program_preference_1_code}</p>
        {/if}
      </div>
      <div class="space-y-2">
        <Label for={`${idPrefix}-pref2`}>
          Second preference <span class="text-muted-foreground font-normal">(optional)</span>
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
      <div class="space-y-2">
        <Label for={`${idPrefix}-pref3`}>
          Third preference <span class="text-muted-foreground font-normal">(optional)</span>
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
      <ReasonSuggestionChips
        mode="append"
        separator=" — "
        suggestions={ADMISSION_STAFF_CORRECTION_NOTE_SUGGESTIONS}
        bind:value={form.correction_notes}
        maxlength={2000}
      />
    </section>
  {/if}

  {#if showResubmissionNotes}
    <section class="space-y-1.5">
      <Label for={`${idPrefix}-resubmit-notes`}>What did you fix? <span class="text-muted-foreground">(optional)</span></Label>
      <Textarea id={`${idPrefix}-resubmit-notes`} bind:value={form.resubmission_notes} rows={3} />
      <ReasonSuggestionChips
        mode="append"
        separator=" — "
        suggestions={ADMISSION_APPLICANT_RESUBMISSION_SUGGESTIONS}
        bind:value={form.resubmission_notes}
        maxlength={2000}
      />
    </section>
  {/if}
</div>
