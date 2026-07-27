<script>
  import GuestLayout from '@/Layouts/GuestLayout.svelte';
  import { Link, useForm, usePage } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
  import { CalendarX } from 'lucide-svelte';
  import { Switch } from '@/Components/ui/switch';
  import * as Select from '@/Components/ui/select';
  import * as Dialog from '@/Components/ui/dialog';

  let { courses = [], appointments = [], active_season = null, allow_apply = false, is_staff = false } = $props();

  const page = usePage();
  const allowFlexible = $derived($page.props.allow_flexible_applications ?? false);
  const isFlexible = $derived(allowFlexible || is_staff);

  const form = useForm({
    first_name: '',
    middle_name: '',
    last_name: '',
    suffix: '',
    birthdate: '',
    sex: '',
    applicant_type: 'new',
    last_school_enrolled: '',
    strand: '',
    email: '',
    phone: '',
    address_line: '',
    city: '',
    province: '',
    zip_code: '',
    gwa: '',
    course_preference_1: '',
    course_preference_2: '',
    course_preference_3: '',
    appointment_id: '',
    accept_immediately: false,
    terms_accepted: false,
  });

  const canSubmit = $derived($form.terms_accepted && !$form.processing);

  const coursesUnique = $derived(
    courses.filter((c, i, a) => a.findIndex((x) => x.id === c.id) === i)
  );
  const optionsFor2 = $derived.by(() =>
    coursesUnique.filter((c) => String(c.id) !== String($form.course_preference_1))
  );
  const optionsFor3 = $derived.by(() =>
    coursesUnique.filter(
      (c) =>
        String(c.id) !== String($form.course_preference_1) &&
        String(c.id) !== String($form.course_preference_2)
    )
  );

  $effect(() => {
    const p1 = $form.course_preference_1;
    const p2 = $form.course_preference_2;
    const p3 = $form.course_preference_3;
    const updates = {};
    if (p2 && String(p2) === String(p1)) {
      updates.course_preference_2 = '';
    }
    if (p3 && (String(p3) === String(p1) || String(p3) === String(p2))) {
      updates.course_preference_3 = '';
    }
    if (Object.keys(updates).length) {
      form.setData({ ...$form, ...updates });
    }
  });

  // Privacy policy dialog state
  let policyDialogOpen = $state(false);
  let policyTitle = $state('');
  let policyContent = $state('');
  let policyLoading = $state(false);
  let policyLoaded = $state(false);

  async function openPrivacyPolicy(e) {
    e.preventDefault();
    policyDialogOpen = true;

    if (policyLoaded) return;

    policyLoading = true;
    try {
      const res = await fetch('/api/privacy-policy');
      const data = await res.json();
      if (data.policy) {
        policyTitle = data.policy.title;
        policyContent = data.policy.content;
      } else {
        policyTitle = 'Privacy Policy';
        policyContent = 'No privacy policy has been published yet.';
      }
      policyLoaded = true;
    } catch {
      policyTitle = 'Privacy Policy';
      policyContent = 'Unable to load privacy policy at this time.';
    } finally {
      policyLoading = false;
    }
  }
</script>

<svelte:head>
  <title>Apply - SecureCAT</title>
</svelte:head>

<GuestLayout>
  <div class="mx-auto max-w-2xl space-y-6">
    {#if !active_season}
      <div class="flex min-h-[60vh] items-center justify-center">
        <div class="max-w-md text-center space-y-3">
          <h2 class="text-xl font-semibold">Applications Closed</h2>
          <p class="text-muted-foreground">No admission period is currently open. Contact the registrar's office for details.</p>
        </div>
      </div>
    {:else if !allow_apply}
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <CalendarX class="h-5 w-5" />
            Application window closed
          </CardTitle>
          <CardDescription>
            New applications are not being accepted at this time. Please check back later or contact the office.
          </CardDescription>
        </CardHeader>
        <CardContent>
        </CardContent>
      </Card>
    {:else}
      <Card>
        <CardHeader>
          <CardTitle class="text-center">Submit an application</CardTitle>
          <CardDescription>
            {#if active_season}
              A.Y. {active_season.academic_year} – {active_season.semester_label}
            {:else}
              Fill in your details below.
            {/if}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form
            method="post"
            action={is_staff ? '/admin/applications' : '/applications'}
            onsubmit={(e) => {
              e.preventDefault();
              $form.post(is_staff ? '/admin/applications' : '/applications');
            }}
            class="space-y-4"
          >
            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label for="first_name" class="block text-sm font-medium">First name *</label>
                <Input id="first_name" name="first_name" bind:value={$form.first_name} class="mt-1 min-h-[44px]" required />
                {#if $form.errors?.first_name}
                  <p class="mt-1 text-sm text-destructive">{$form.errors.first_name}</p>
                {/if}
              </div>
              <div>
                <label for="last_name" class="block text-sm font-medium">Last name *</label>
                <Input id="last_name" name="last_name" bind:value={$form.last_name} class="mt-1 min-h-[44px]" required />
                {#if $form.errors?.last_name}
                  <p class="mt-1 text-sm text-destructive">{$form.errors.last_name}</p>
                {/if}
              </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label for="middle_name" class="block text-sm font-medium">Middle name / Initial <span class="text-muted-foreground font-normal">(optional)</span></label>
                <Input id="middle_name" name="middle_name" bind:value={$form.middle_name} class="mt-1 min-h-[44px]" placeholder="e.g. Santos or S." />
                {#if $form.errors?.middle_name}
                  <p class="mt-1 text-sm text-destructive">{$form.errors.middle_name}</p>
                {/if}
              </div>
              <div>
                <label for="suffix" class="block text-sm font-medium">Suffix <span class="text-muted-foreground font-normal">(optional)</span></label>
                <Input id="suffix" name="suffix" bind:value={$form.suffix} class="mt-1 min-h-[44px]" placeholder="e.g. Jr., III" />
                {#if $form.errors?.suffix}
                  <p class="mt-1 text-sm text-destructive">{$form.errors.suffix}</p>
                {/if}
              </div>
            </div>
            <div>
              <label for="email" class="block text-sm font-medium">Email {isFlexible ? '(optional)' : '*'}</label>
              <Input id="email" name="email" type="email" bind:value={$form.email} class="mt-1 min-h-[44px]" required={!isFlexible} />
              {#if $form.errors?.email}
                <p class="mt-1 text-sm text-destructive">{$form.errors.email}</p>
              {/if}
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label for="birthdate" class="block text-sm font-medium">Birthdate {isFlexible ? '(optional)' : '*'}</label>
                <Input id="birthdate" name="birthdate" type="date" bind:value={$form.birthdate} class="mt-1 min-h-[44px]" required={!isFlexible} />
                {#if $form.errors?.birthdate}
                  <p class="mt-1 text-sm text-destructive">{$form.errors.birthdate}</p>
                {/if}
              </div>
              <div>
                <label for="sex" class="block text-sm font-medium">Sex {isFlexible ? '(optional)' : '*'}</label>
                <Select.Root type="single" bind:value={$form.sex}>
                  <Select.Trigger id="sex" class="mt-1 w-full min-h-[44px]">
                    {#if $form.sex === 'male'}
                      Male
                    {:else if $form.sex === 'female'}
                      Female
                    {:else}
                      <span class="text-muted-foreground">Select</span>
                    {/if}
                  </Select.Trigger>
                  <Select.Content>
                    <Select.Item value="male" label="Male">Male</Select.Item>
                    <Select.Item value="female" label="Female">Female</Select.Item>
                  </Select.Content>
                </Select.Root>
                {#if $form.errors?.sex}
                  <p class="mt-1 text-sm text-destructive">{$form.errors.sex}</p>
                {/if}
              </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label for="applicant_type" class="block text-sm font-medium">Applicant classification {isFlexible ? '(optional)' : '*'}</label>
                <Select.Root type="single" bind:value={$form.applicant_type}>
                  <Select.Trigger id="applicant_type" class="mt-1 w-full min-h-[44px]">
                    {#if $form.applicant_type === 'new'}
                      New Student
                    {:else if $form.applicant_type === 'transferee'}
                      Transferee
                    {:else}
                      <span class="text-muted-foreground">Select</span>
                    {/if}
                  </Select.Trigger>
                  <Select.Content>
                    <Select.Item value="new" label="New Student">New Student</Select.Item>
                    <Select.Item value="transferee" label="Transferee">Transferee</Select.Item>
                  </Select.Content>
                </Select.Root>
                {#if $form.errors?.applicant_type}
                  <p class="mt-1 text-sm text-destructive">{$form.errors.applicant_type}</p>
                {/if}
              </div>
              <div>
                <label for="last_school_enrolled" class="block text-sm font-medium">Last school enrolled</label>
                <Input id="last_school_enrolled" name="last_school_enrolled" bind:value={$form.last_school_enrolled} class="mt-1 min-h-[44px]" placeholder="School of origin" />
                {#if $form.errors?.last_school_enrolled}
                  <p class="mt-1 text-sm text-destructive">{$form.errors.last_school_enrolled}</p>
                {/if}
              </div>
              <div>
                <label for="strand" class="block text-sm font-medium">
                  {$form.applicant_type === 'transferee' ? 'Previous Course' : 'SHS Strand'}
                </label>
                <Input
                  id="strand"
                  name="strand"
                  bind:value={$form.strand}
                  class="mt-1 min-h-[44px]"
                  placeholder={$form.applicant_type === 'transferee' ? 'e.g., BS Computer Science, BS Nursing' : 'e.g., STEM, ABM, HUMSS, GAS'}
                />
                {#if $form.errors?.strand}
                  <p class="mt-1 text-sm text-destructive">{$form.errors.strand}</p>
                {/if}
              </div>
            </div>
            <div>
              <label for="gwa" class="block text-sm font-medium">GWA</label>
              <Input id="gwa" name="gwa" type="number" step="0.01" bind:value={$form.gwa} class="mt-1 min-h-[44px]" placeholder="e.g., 1.75" />
              {#if $form.errors?.gwa}
                <p class="mt-1 text-sm text-destructive">{$form.errors.gwa}</p>
              {/if}
            </div>
            <details class="rounded-md border border-border p-4">
              <summary class="cursor-pointer text-sm font-medium">Contact Information (optional)</summary>
              <div class="mt-3 space-y-4">
                <div>
                  <label for="phone" class="block text-sm font-medium">Phone number</label>
                  <Input id="phone" name="phone" type="tel" bind:value={$form.phone} class="mt-1 min-h-[44px]" maxlength="12" oninput={(e) => { if (e.target.value.length > 12) { e.target.value = e.target.value.slice(0, 12); $form.phone = e.target.value; } }} />
                  {#if $form.phone && $form.phone.length >= 12}
                    <p class="text-xs text-amber-500 mt-0.5">Maximum 12 characters reached</p>
                  {/if}
                  {#if $form.errors?.phone}
                    <p class="mt-1 text-sm text-destructive">{$form.errors.phone}</p>
                  {/if}
                </div>
                <div>
                  <label for="address_line" class="block text-sm font-medium">Street address</label>
                  <Input id="address_line" name="address_line" bind:value={$form.address_line} class="mt-1 min-h-[44px]" />
                  {#if $form.errors?.address_line}
                    <p class="mt-1 text-sm text-destructive">{$form.errors.address_line}</p>
                  {/if}
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                  <div>
                    <label for="city" class="block text-sm font-medium">City</label>
                    <Input id="city" name="city" bind:value={$form.city} class="mt-1 min-h-[44px]" />
                    {#if $form.errors?.city}
                      <p class="mt-1 text-sm text-destructive">{$form.errors.city}</p>
                    {/if}
                  </div>
                  <div>
                    <label for="province" class="block text-sm font-medium">Province</label>
                    <Input id="province" name="province" bind:value={$form.province} class="mt-1 min-h-[44px]" />
                    {#if $form.errors?.province}
                      <p class="mt-1 text-sm text-destructive">{$form.errors.province}</p>
                    {/if}
                  </div>
                  <div>
                    <label for="zip_code" class="block text-sm font-medium">Zip code</label>
                    <Input id="zip_code" name="zip_code" bind:value={$form.zip_code} class="mt-1 min-h-[44px]" />
                    {#if $form.errors?.zip_code}
                      <p class="mt-1 text-sm text-destructive">{$form.errors.zip_code}</p>
                    {/if}
                  </div>
                </div>
              </div>
            </details>
            <div>
              <label for="course_preference_1" class="block text-sm font-medium">Course preferences {isFlexible ? '(optional)' : '*'}</label>
              <p class="text-xs text-muted-foreground mt-1">Select one or up to three different courses in order of preference.</p>
              <div class="mt-2 space-y-2">
                <Select.Root type="single" bind:value={$form.course_preference_1}>
                  <Select.Trigger id="course_preference_1" class="w-full min-h-[44px]">
                    {#if $form.course_preference_1}
                      {@const course = coursesUnique.find(c => String(c.id) === String($form.course_preference_1))}
                      {course ? `${course.code} – ${course.name}` : '1st choice'}
                    {:else}
                      <span class="text-muted-foreground">{isFlexible ? '1st choice (optional)' : '1st choice *'}</span>
                    {/if}
                  </Select.Trigger>
                  <Select.Content>
                    {#each coursesUnique as c}
                      <Select.Item value={String(c.id)} label={`${c.code} – ${c.name}`}>
                        {c.code} – {c.name}
                      </Select.Item>
                    {/each}
                  </Select.Content>
                </Select.Root>
                <Select.Root type="single" bind:value={$form.course_preference_2}>
                  <Select.Trigger class="w-full min-h-[44px]">
                    {#if $form.course_preference_2}
                      {@const course = optionsFor2.find(c => String(c.id) === String($form.course_preference_2)) || coursesUnique.find(c => String(c.id) === String($form.course_preference_2))}
                      {course ? `${course.code} – ${course.name}` : '2nd choice (optional)'}
                    {:else}
                      <span class="text-muted-foreground">2nd choice (optional)</span>
                    {/if}
                  </Select.Trigger>
                  <Select.Content>
                    {#if $form.course_preference_2}
                      <Select.Item value="" label="None">— Clear selection —</Select.Item>
                    {/if}
                    {#each optionsFor2 as c}
                      <Select.Item value={String(c.id)} label={`${c.code} – ${c.name}`}>
                        {c.code} – {c.name}
                      </Select.Item>
                    {/each}
                  </Select.Content>
                </Select.Root>
                <Select.Root type="single" bind:value={$form.course_preference_3}>
                  <Select.Trigger class="w-full min-h-[44px]">
                    {#if $form.course_preference_3}
                      {@const course = optionsFor3.find(c => String(c.id) === String($form.course_preference_3)) || coursesUnique.find(c => String(c.id) === String($form.course_preference_3))}
                      {course ? `${course.code} – ${course.name}` : '3rd choice (optional)'}
                    {:else}
                      <span class="text-muted-foreground">3rd choice (optional)</span>
                    {/if}
                  </Select.Trigger>
                  <Select.Content>
                    {#if $form.course_preference_3}
                      <Select.Item value="" label="None">— Clear selection —</Select.Item>
                    {/if}
                    {#each optionsFor3 as c}
                      <Select.Item value={String(c.id)} label={`${c.code} – ${c.name}`}>
                        {c.code} – {c.name}
                      </Select.Item>
                    {/each}
                  </Select.Content>
                </Select.Root>
              </div>
              {#if $form.errors?.course_preference_1}
                <p class="mt-1 text-sm text-destructive">{$form.errors.course_preference_1}</p>
              {/if}
            </div>
            {#if is_staff}
              <div class="rounded-md border border-border bg-muted/50 p-4">
                <div class="flex items-center justify-between">
                  <div class="space-y-1">
                    <label for="accept_immediately" class="text-sm font-medium">Accept immediately</label>
                    <p class="text-xs text-muted-foreground">Create application as accepted so applicant can access portal</p>
                  </div>
                  <Switch id="accept_immediately" bind:checked={$form.accept_immediately} />
                </div>
              </div>
            {/if}
            <div class="rounded-md border border-border bg-muted/30 p-4">
              <label class="flex items-start gap-3 cursor-pointer">
                <input
                  type="checkbox"
                  bind:checked={$form.terms_accepted}
                  class="mt-0.5 h-4 w-4 rounded border-border text-primary accent-primary cursor-pointer"
                />
                <span class="text-sm text-muted-foreground">
                  I have read and agree to the <button type="button" onclick={openPrivacyPolicy} class="underline hover:text-foreground">Privacy Policy</button>. *
                </span>
              </label>
            </div>
            <div class="flex justify-end gap-4 pt-4">
              <Button type="submit" disabled={!canSubmit} class="min-h-[44px]">
                {$form.processing ? 'Submitting…' : 'Submit application'}
              </Button>
              <Link href="/">
                <Button type="button" variant="outline" class="min-h-[44px]">Cancel</Button>
              </Link>
            </div>
          </form>
        </CardContent>
      </Card>
    {/if}
  </div>

  <!-- Privacy Policy Dialog -->
  <Dialog.Root bind:open={policyDialogOpen}>
    <Dialog.Content class="max-w-2xl max-h-[80vh] overflow-y-auto">
      <Dialog.Header>
        <Dialog.Title>{policyLoading ? 'Loading...' : policyTitle || 'Privacy Policy'}</Dialog.Title>
      </Dialog.Header>
      <div class="py-4">
        {#if policyLoading}
          <div class="space-y-3">
            <div class="h-4 bg-muted animate-pulse rounded w-3/4"></div>
            <div class="h-4 bg-muted animate-pulse rounded w-full"></div>
            <div class="h-4 bg-muted animate-pulse rounded w-5/6"></div>
            <div class="h-4 bg-muted animate-pulse rounded w-2/3"></div>
          </div>
        {:else}
          <div class="prose prose-sm max-w-none text-foreground whitespace-pre-wrap">
            {policyContent}
          </div>
        {/if}
      </div>
      <Dialog.Footer>
        <Button onclick={() => policyDialogOpen = false}>Close</Button>
      </Dialog.Footer>
    </Dialog.Content>
  </Dialog.Root>
</GuestLayout>
