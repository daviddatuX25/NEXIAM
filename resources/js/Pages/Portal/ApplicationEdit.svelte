<script>
  import { Link, useForm } from '@inertiajs/svelte';
  import PortalLayout from '@/Layouts/PortalLayout.svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
  import { ArrowLeft, Save, Lock } from 'lucide-svelte';
  import * as Select from '@/Components/ui/select';

  let { application = {}, courses = [] } = $props();

  const form = useForm({
    first_name: application.first_name || '',
    middle_name: application.middle_name || '',
    last_name: application.last_name || '',
    suffix: application.suffix || '',
    birthdate: application.birthdate || '',
    sex: application.sex || '',
    applicant_type: application.applicant_type || 'new',
    last_school_enrolled: application.last_school_enrolled || '',
    strand: application.strand || '',
    email: application.email || '',
    phone: application.phone || '',
    address_line: application.address_line || '',
    city: application.city || '',
    province: application.province || '',
    zip_code: application.zip_code || '',
    gwa: application.gwa || '',
    course_preference_1: application.course_preference_1 ? String(application.course_preference_1) : '',
    course_preference_2: application.course_preference_2 ? String(application.course_preference_2) : '',
    course_preference_3: application.course_preference_3 ? String(application.course_preference_3) : '',
  });

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
</script>

<svelte:head>
  <title>Edit Application - SecureCAT</title>
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
        <h1 class="text-2xl font-bold">Edit Application</h1>
        <p class="text-sm text-muted-foreground">Reference: {application.reference_number}</p>
      </div>
    </div>

    {#if !application.is_editable}
      <Card>
        <CardContent class="p-6">
          <div class="flex items-center gap-3 text-destructive">
            <Lock class="h-5 w-5" />
            <p class="font-medium">Application is locked</p>
          </div>
          <p class="text-sm text-muted-foreground mt-2">
            {#if application.assigned_session_status === 'published'}
              Your application is locked because you have been assigned to a published exam session. Contact the registrar's office for assistance.
            {:else}
              You cannot edit your application at this time.
            {/if}
          </p>
          <Link href="/portal" class="mt-4 inline-block">
            <Button variant="outline">Back to Dashboard</Button>
          </Link>
        </CardContent>
      </Card>
    {:else}
      <Card>
        <CardContent class="pt-6">
          <form
            method="post"
            action="/portal/application"
            onsubmit={(e) => {
              e.preventDefault();
              $form.put('/portal/application');
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
            <div>
              <label for="email" class="block text-sm font-medium">Email *</label>
              <Input id="email" name="email" type="email" bind:value={$form.email} class="mt-1 min-h-[44px]" required />
              {#if $form.errors?.email}
                <p class="mt-1 text-sm text-destructive">{$form.errors.email}</p>
              {/if}
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label for="birthdate" class="block text-sm font-medium">Birthdate *</label>
                <Input id="birthdate" name="birthdate" type="date" bind:value={$form.birthdate} class="mt-1 min-h-[44px]" required />
                {#if $form.errors?.birthdate}
                  <p class="mt-1 text-sm text-destructive">{$form.errors.birthdate}</p>
                {/if}
              </div>
              <div>
                <label for="sex" class="block text-sm font-medium">Sex *</label>
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
                <label for="applicant_type" class="block text-sm font-medium">Applicant classification *</label>
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
              <label for="course_preference_1" class="block text-sm font-medium">Course preferences *</label>
              <p class="text-xs text-muted-foreground mt-1">Select one or up to three different courses in order of preference.</p>
              <div class="mt-2 space-y-2">
                <Select.Root type="single" bind:value={$form.course_preference_1}>
                  <Select.Trigger id="course_preference_1" class="w-full min-h-[44px]">
                    {#if $form.course_preference_1}
                      {@const course = coursesUnique.find(c => String(c.id) === String($form.course_preference_1))}
                      {course ? `${course.code} – ${course.name}` : '1st choice'}
                    {:else}
                      <span class="text-muted-foreground">1st choice</span>
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
                  <Select.Trigger id="course_preference_2" class="w-full min-h-[44px]">
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
                  <Select.Trigger id="course_preference_3" class="w-full min-h-[44px]">
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
            <div class="flex justify-end gap-4 pt-4">
              <Button type="submit" disabled={$form.processing} class="min-h-[44px] gap-2">
                <Save class="h-4 w-4" />
                {$form.processing ? 'Saving…' : 'Save changes'}
              </Button>
              <Link href="/portal">
                <Button type="button" variant="outline" class="min-h-[44px]">Cancel</Button>
              </Link>
            </div>
          </form>
        </CardContent>
      </Card>
    {/if}
  </div>
</PortalLayout>