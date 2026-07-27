<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, useForm, usePage } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import * as Select from '@/Components/ui/select';
  import { success, error } from '@/lib/toast';

  let { courses = [], appointments = [], active_season = null } = $props();

  const page = usePage();
  const allowFlexible = $derived($page.props.allow_flexible_applications ?? false);

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
    course_preference_1: '',
    course_preference_2: '',
    course_preference_3: '',
    appointment_id: '',
    status: 'pending',
  });

  $form.onFinish = () => {
    if (!$form.errors || Object.keys($form.errors).length === 0) {
      success('Application created');
    } else {
      error('Please fix the errors in the form');
    }
  };

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

  function submitForm(e) {
    e.preventDefault();
    $form.post('/admin/applications');
  }

  const breadcrumbs = [{ label: 'Applications', href: '/admin/applications' }, { label: 'Create' }];
</script>

<svelte:head>
  <title>Create Application - SecureCAT</title>
</svelte:head>

<AuthenticatedLayout {breadcrumbs}>
  <div class="mx-auto max-w-2xl space-y-6">
    {#if active_season}
      <div class="rounded-lg bg-muted/50 px-4 py-2 text-sm text-muted-foreground">
        Creating application for A.Y. {active_season.academic_year} – {active_season.semester_label}
      </div>
    {/if}

    <form onsubmit={submitForm} class="space-y-6 rounded-lg border border-border bg-card p-6">
      <div class="space-y-4">
        <h3 class="text-lg font-semibold">Personal Information</h3>
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="space-y-2">
            <label for="first_name" class="text-sm font-medium">First name *</label>
            <Input id="first_name" bind:value={$form.first_name} required />
            {#if $form.errors?.first_name}
              <p class="text-sm text-destructive">{$form.errors.first_name}</p>
            {/if}
          </div>
          <div class="space-y-2">
            <label for="last_name" class="text-sm font-medium">Last name *</label>
            <Input id="last_name" bind:value={$form.last_name} required />
            {#if $form.errors?.last_name}
              <p class="text-sm text-destructive">{$form.errors.last_name}</p>
            {/if}
          </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="space-y-2">
            <label for="middle_name" class="text-sm font-medium">Middle name</label>
            <Input id="middle_name" bind:value={$form.middle_name} />
          </div>
          <div class="space-y-2">
            <label for="suffix" class="text-sm font-medium">Suffix</label>
            <Input id="suffix" bind:value={$form.suffix} placeholder="e.g., Jr., Sr." />
          </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="space-y-2">
            <label for="birthdate" class="text-sm font-medium">Birthdate {allowFlexible ? '(optional)' : '*'}</label>
            <Input id="birthdate" type="date" bind:value={$form.birthdate} required={!allowFlexible} />
            {#if $form.errors?.birthdate}
              <p class="text-sm text-destructive">{$form.errors.birthdate}</p>
            {/if}
          </div>
          <div class="space-y-2">
            <label for="sex" class="text-sm font-medium">Sex {allowFlexible ? '(optional)' : '*'}</label>
            <Select.Root type="single" bind:value={$form.sex}>
              <Select.Trigger id="sex" class="w-full">
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
              <p class="text-sm text-destructive">{$form.errors.sex}</p>
            {/if}
          </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="space-y-2">
            <label for="applicant_type" class="text-sm font-medium">Applicant classification {allowFlexible ? '(optional)' : '*'}</label>
            <Select.Root type="single" bind:value={$form.applicant_type}>
              <Select.Trigger id="applicant_type" class="w-full">
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
              <p class="text-sm text-destructive">{$form.errors.applicant_type}</p>
            {/if}
          </div>
          <div class="space-y-2">
            <label for="last_school_enrolled" class="text-sm font-medium">Last school enrolled</label>
            <Input id="last_school_enrolled" bind:value={$form.last_school_enrolled} placeholder="School of origin" />
            {#if $form.errors?.last_school_enrolled}
              <p class="text-sm text-destructive">{$form.errors.last_school_enrolled}</p>
            {/if}
          </div>
          <div class="space-y-2">
            <label for="strand" class="text-sm font-medium">
              {$form.applicant_type === 'transferee' ? 'Previous Course' : 'SHS Strand'}
            </label>
            <Input
              id="strand"
              bind:value={$form.strand}
              placeholder={$form.applicant_type === 'transferee' ? 'e.g., BS Computer Science, BS Nursing' : 'e.g., STEM, ABM, HUMSS, GAS'}
            />
            {#if $form.errors?.strand}
              <p class="text-sm text-destructive">{$form.errors.strand}</p>
            {/if}
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <h3 class="text-lg font-semibold">Contact Information</h3>
        <div class="space-y-2">
          <label for="email" class="text-sm font-medium">Email {allowFlexible ? '(optional)' : '*'}</label>
          <Input id="email" type="email" bind:value={$form.email} required={!allowFlexible} />
          {#if $form.errors?.email}
            <p class="text-sm text-destructive">{$form.errors.email}</p>
          {/if}
        </div>
        <div class="space-y-2">
          <label for="phone" class="text-sm font-medium">Phone</label>
          <Input id="phone" type="tel" bind:value={$form.phone} placeholder="e.g., 09123456789" maxlength="12" oninput={(e) => { if (e.target.value.length > 12) { e.target.value = e.target.value.slice(0, 12); $form.phone = e.target.value; } }} />
          {#if $form.phone && $form.phone.length >= 12}
            <p class="text-xs text-amber-500 mt-0.5">Maximum 12 characters reached</p>
          {/if}
        </div>
      </div>

      <div class="space-y-4">
        <h3 class="text-lg font-semibold">Address</h3>
        <div class="space-y-2">
          <label for="address_line" class="text-sm font-medium">Street address</label>
          <Input id="address_line" bind:value={$form.address_line} placeholder="House No., Street, Barangay" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="space-y-2">
            <label for="city" class="text-sm font-medium">City</label>
            <Input id="city" bind:value={$form.city} />
          </div>
          <div class="space-y-2">
            <label for="province" class="text-sm font-medium">Province</label>
            <Input id="province" bind:value={$form.province} />
          </div>
        </div>
        <div class="space-y-2">
          <label for="zip_code" class="text-sm font-medium">ZIP code</label>
          <Input id="zip_code" bind:value={$form.zip_code} maxlength="10" />
        </div>
      </div>

      <div class="space-y-4">
        <h3 class="text-lg font-semibold">Course Preferences</h3>
        <p class="text-xs text-muted-foreground">Select one or up to three different courses in order of preference.</p>
        <div class="space-y-2">
          <Select.Root type="single" bind:value={$form.course_preference_1}>
            <Select.Trigger id="course_preference_1" class="w-full">
              {#if $form.course_preference_1}
                {@const course = coursesUnique.find(c => String(c.id) === String($form.course_preference_1))}
                {course ? `${course.code} – ${course.name}` : '1st choice *'}
              {:else}
                <span class="text-muted-foreground">1st choice *</span>
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
            <Select.Trigger class="w-full">
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
            <Select.Trigger class="w-full">
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
          {#if $form.errors?.course_preference_1}
            <p class="text-sm text-destructive">{$form.errors.course_preference_1}</p>
          {/if}
        </div>
      </div>

      <div class="space-y-4">
        <h3 class="text-lg font-semibold">Application Status</h3>
        <Select.Root type="single" bind:value={$form.status}>
          <Select.Trigger class="w-full">
            {#if $form.status === 'pending'}
              Pending
            {:else if $form.status === 'accepted'}
              Accepted
            {:else if $form.status === 'dismissed'}
              Dismissed
            {:else}
              <span class="text-muted-foreground">Select status</span>
            {/if}
          </Select.Trigger>
          <Select.Content>
            <Select.Item value="pending" label="Pending">Pending</Select.Item>
            <Select.Item value="accepted" label="Accepted">Accepted</Select.Item>
            <Select.Item value="dismissed" label="Dismissed">Dismissed</Select.Item>
          </Select.Content>
        </Select.Root>
      </div>

      <input type="hidden" name="rejection_reason" value="" />

      <div class="flex justify-end gap-4 pt-4">
        <Button type="submit" disabled={$form.processing}>
          {$form.processing ? 'Creating...' : 'Create Application'}
        </Button>
        <Link href="/admin/applications">
          <Button type="button" variant="outline">Cancel</Button>
        </Link>
      </div>
    </form>
  </div>
</AuthenticatedLayout>