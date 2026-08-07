<script>
  import GuestLayout from '@/Layouts/GuestLayout.svelte';
  import { useForm } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import * as Card from '@/Components/ui/card';
  import * as Dialog from '@/Components/ui/dialog';

  let {
    page_state = 'ready',
    institution = null,
    cycle = null,
    window = null,
    programs = [],
    grade_fields = [],
    privacy_policy = null,
  } = $props();

  const initialGrades = Object.fromEntries((grade_fields ?? []).map((f) => [f.code, '']));

  // Inertia Svelte 5 useForm returns a reactive object, not a store — do not use $form.
  const form = useForm({
    academic_cycle_id: cycle?.id ?? '',
    application_window_id: window?.id ?? '',
    first_name: '',
    middle_name: '',
    last_name: '',
    suffix: '',
    email: '',
    birthdate: '',
    sex: '',
    applicant_type: 'new',
    last_school_enrolled: '',
    strand: '',
    grades: initialGrades,
    phone: '',
    address_line: '',
    city: '',
    province: '',
    zip_code: '',
    program_preference_1: '',
    program_preference_2: '',
    program_preference_3: '',
    terms_accepted: false,
  });

  let policyDialogOpen = $state(false);

  const optionsFor2 = $derived(
    programs.filter((p) => p.code !== form.program_preference_1),
  );
  const optionsFor3 = $derived(
    programs.filter(
      (p) => p.code !== form.program_preference_1 && p.code !== form.program_preference_2,
    ),
  );

  $effect(() => {
    const p1 = form.program_preference_1;
    const p2 = form.program_preference_2;
    const p3 = form.program_preference_3;
    if (p2 && p2 === p1) {
      form.program_preference_2 = '';
    }
    if (p3 && (p3 === p1 || p3 === p2)) {
      form.program_preference_3 = '';
    }
  });

  function submit(event) {
    event.preventDefault();
    form.post('/apply');
  }
</script>

<svelte:head>
  <title>Admission application</title>
</svelte:head>

<GuestLayout>
  <div class="mx-auto max-w-2xl space-y-8 px-6 py-10">
    <header class="space-y-2">
      <p class="text-sm text-muted-foreground">{institution?.name ?? 'Institution'}</p>
      <h1 class="text-3xl font-semibold tracking-tight">Admission application</h1>
      {#if window}
        <p class="text-sm text-muted-foreground">
          {window.name}
          {#if cycle}
            · {cycle.name}
          {/if}
        </p>
      {/if}
    </header>

    {#if page_state === 'empty' || !window}
      <Card.Root>
        <Card.Content class="pt-6 text-sm text-muted-foreground">
          No admission period is currently open. Please contact the Registrar’s office for details.
        </Card.Content>
      </Card.Root>
    {:else}
      {#if form.errors.identity}
        <p class="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2 text-sm text-destructive">
          {form.errors.identity}
        </p>
      {/if}

      <form onsubmit={submit} class="space-y-8">
        <section class="space-y-4">
          <h2 class="text-lg font-medium">Personal information</h2>
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="first_name">First name</Label>
              <Input id="first_name" bind:value={form.first_name} required class="min-h-11" />
              {#if form.errors.first_name}<p class="text-sm text-destructive">{form.errors.first_name}</p>{/if}
            </div>
            <div class="space-y-2">
              <Label for="last_name">Last name</Label>
              <Input id="last_name" bind:value={form.last_name} required class="min-h-11" />
              {#if form.errors.last_name}<p class="text-sm text-destructive">{form.errors.last_name}</p>{/if}
            </div>
            <div class="space-y-2">
              <Label for="middle_name">Middle name <span class="text-muted-foreground">(optional)</span></Label>
              <Input id="middle_name" bind:value={form.middle_name} class="min-h-11" />
            </div>
            <div class="space-y-2">
              <Label for="suffix">Suffix <span class="text-muted-foreground">(optional)</span></Label>
              <Input id="suffix" bind:value={form.suffix} class="min-h-11" placeholder="Jr., III" />
            </div>
            <div class="space-y-2">
              <Label for="email">Email</Label>
              <Input id="email" type="email" bind:value={form.email} required class="min-h-11" />
              {#if form.errors.email}<p class="text-sm text-destructive">{form.errors.email}</p>{/if}
            </div>
            <div class="space-y-2">
              <Label for="birthdate">Birthdate</Label>
              <Input id="birthdate" type="date" bind:value={form.birthdate} required class="min-h-11" />
              {#if form.errors.birthdate}<p class="text-sm text-destructive">{form.errors.birthdate}</p>{/if}
            </div>
            <div class="space-y-2">
              <Label for="sex">Sex</Label>
              <select
                id="sex"
                bind:value={form.sex}
                required
                class="border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
              >
                <option value="" disabled>Select</option>
                <option value="female">Female</option>
                <option value="male">Male</option>
              </select>
              {#if form.errors.sex}<p class="text-sm text-destructive">{form.errors.sex}</p>{/if}
            </div>
            <div class="space-y-2">
              <Label for="applicant_type">Applicant type</Label>
              <select
                id="applicant_type"
                bind:value={form.applicant_type}
                required
                class="border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
              >
                <option value="new">New</option>
                <option value="transferee">Transferee</option>
              </select>
            </div>
          </div>
        </section>

        <section class="space-y-4">
          <h2 class="text-lg font-medium">Academic background</h2>
          <p class="text-sm text-muted-foreground">
            Enter grades from the active intake catalog as a digital copy for Guidance consultation. You still
            need to submit Form 138 / TOR as hard copy to the Registrar. Fields are configured by the institution
            (not fixed in the application code).
          </p>
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2 sm:col-span-2">
              <Label for="last_school_enrolled">Last school enrolled</Label>
              <Input
                id="last_school_enrolled"
                bind:value={form.last_school_enrolled}
                class="min-h-11"
                placeholder="School of origin"
              />
            </div>
            <div class="space-y-2">
              <Label for="strand">
                {form.applicant_type === 'transferee' ? 'Previous course' : 'SHS strand'}
              </Label>
              <Input id="strand" bind:value={form.strand} class="min-h-11" />
            </div>
            {#each grade_fields as field (field.code)}
              <div class="space-y-2">
                <Label for={`grade_${field.code}`}>
                  {field.label}
                  {#if field.is_required}<span class="text-destructive">*</span>{/if}
                </Label>
                <Input
                  id={`grade_${field.code}`}
                  type="number"
                  step="0.01"
                  min={field.min_value}
                  max={field.max_value}
                  bind:value={form.grades[field.code]}
                  required={field.is_required}
                  class="min-h-11"
                />
                {#if form.errors[`grades.${field.code}`]}
                  <p class="text-sm text-destructive">{form.errors[`grades.${field.code}`]}</p>
                {/if}
              </div>
            {/each}
          </div>
        </section>

        <section class="space-y-4">
          <h2 class="text-lg font-medium">
            Contact information <span class="text-sm font-normal text-muted-foreground">(optional)</span>
          </h2>
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
              <Label for="phone">Phone</Label>
              <Input id="phone" type="tel" bind:value={form.phone} class="min-h-11" />
            </div>
            <div class="space-y-2 sm:col-span-2">
              <Label for="address_line">Address</Label>
              <Input id="address_line" bind:value={form.address_line} class="min-h-11" />
            </div>
            <div class="space-y-2">
              <Label for="city">City / municipality</Label>
              <Input id="city" bind:value={form.city} class="min-h-11" />
            </div>
            <div class="space-y-2">
              <Label for="province">Province</Label>
              <Input id="province" bind:value={form.province} class="min-h-11" />
            </div>
            <div class="space-y-2">
              <Label for="zip_code">ZIP code</Label>
              <Input id="zip_code" bind:value={form.zip_code} class="min-h-11" />
            </div>
          </div>
        </section>

        <section class="space-y-4">
          <h2 class="text-lg font-medium">Program preferences</h2>
          <p class="text-sm text-muted-foreground">
            Choose up to three distinct programs. First preference is required.
          </p>
          <div class="space-y-3">
            <div class="space-y-2">
              <Label for="program_preference_1">First preference</Label>
              <select
                id="program_preference_1"
                bind:value={form.program_preference_1}
                required
                class="border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
              >
                <option value="" disabled>Select a program</option>
                {#each programs as program (program.code)}
                  <option value={program.code}>{program.name} ({program.code})</option>
                {/each}
              </select>
              {#if form.errors.program_preference_1}
                <p class="text-sm text-destructive">{form.errors.program_preference_1}</p>
              {/if}
            </div>
            <div class="space-y-2">
              <Label for="program_preference_2">
                Second preference <span class="text-muted-foreground">(optional)</span>
              </Label>
              <select
                id="program_preference_2"
                bind:value={form.program_preference_2}
                class="border-input bg-background flex min-h-11 w-full rounded-md border px-3 text-sm"
              >
                <option value="">None</option>
                {#each optionsFor2 as program (program.code)}
                  <option value={program.code}>{program.name} ({program.code})</option>
                {/each}
              </select>
            </div>
            <div class="space-y-2">
              <Label for="program_preference_3">
                Third preference <span class="text-muted-foreground">(optional)</span>
              </Label>
              <select
                id="program_preference_3"
                bind:value={form.program_preference_3}
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

        <section class="space-y-3 rounded-md border bg-muted/30 px-4 py-4">
          <label class="flex items-start gap-3 text-sm leading-6">
            <input type="checkbox" bind:checked={form.terms_accepted} class="mt-1" required />
            <span>
              I certify that the information provided is true and complete, and I have read and agree to the
              <button
                type="button"
                class="font-medium text-foreground underline underline-offset-4"
                onclick={() => (policyDialogOpen = true)}
              >
                Privacy Policy
              </button>
              (including digital academic grades for consultation).
            </span>
          </label>
          {#if form.errors.terms_accepted}
            <p class="text-sm text-destructive">{form.errors.terms_accepted}</p>
          {/if}
        </section>

        <Button type="submit" class="min-h-11 w-full sm:w-auto" disabled={form.processing}>
          {form.processing ? 'Submitting…' : 'Submit application'}
        </Button>
      </form>
    {/if}
  </div>

  <Dialog.Root bind:open={policyDialogOpen}>
    <Dialog.Content class="max-h-[80vh] max-w-2xl overflow-y-auto">
      <Dialog.Header>
        <Dialog.Title>{privacy_policy?.title ?? 'Privacy Policy'}</Dialog.Title>
        <Dialog.Description>
          Please read the full notice before accepting. Hard-copy Form 138 / TOR is still required at the Registrar.
        </Dialog.Description>
      </Dialog.Header>
      <div class="whitespace-pre-wrap py-2 text-sm leading-6 text-foreground">
        {privacy_policy?.content ??
          'No privacy policy has been published yet. Contact the Registrar’s office before applying.'}
      </div>
      <Dialog.Footer>
        <Button type="button" onclick={() => (policyDialogOpen = false)}>Close</Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>
</GuestLayout>
