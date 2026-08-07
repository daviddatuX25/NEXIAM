<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { useForm, usePage, router } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
  import { Badge } from '@/Components/ui/badge';
  import { Input } from '@/Components/ui/input';
  import { Building2, Users, ChevronDown, ChevronUp, RotateCcw, Save } from 'lucide-svelte';
  import { success as showSuccess, error as showError } from '@/lib/toast';
  import { onMount } from 'svelte';

  let { profile = {}, personnel = {}, personnelRoles = [] } = $props();

  const page = usePage();
  const breadcrumbs = [
    { label: 'Setup', href: '/admin/setup' },
    { label: 'Institution' },
  ];

  const profileLabels = {
    name: 'Institution Name',
    campus: 'Campus',
    address: 'Address',
    contact_number: 'Contact Number',
    email: 'Email',
    website: 'Website',
    exam_name: 'Exam Name',
    exam_acronym: 'Exam Acronym',
  };

  const roleLabels = {
    guidance_counselor: 'Guidance Counselor',
    registrar: 'Registrar',
    college_president: 'College President',
    campus_director: 'Campus Director',
    vp_academic_affairs: 'VP for Academic Affairs',
    dean: 'Dean',
    testing_coordinator: 'Testing Coordinator',
  };

  function getRoleLabel(role) {
    return roleLabels[role] ?? role.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
  }

  let profileForm = $state(
    Object.fromEntries(Object.entries(profile).map(([k, v]) => [k, v.value ?? '']))
  );

  let personnelForm = $state(
    Object.fromEntries(
      Object.entries(personnel).map(([role, fields]) => [
        role,
        Object.fromEntries(Object.entries(fields).map(([f, v]) => [f, v.value ?? ''])),
      ])
    )
  );

  let expandedRoles = $state(new Set(personnelRoles.length > 0 ? [personnelRoles[0]] : []));

  function toggleRole(role) {
    const next = new Set(expandedRoles);
    if (next.has(role)) {
      next.delete(role);
    } else {
      next.add(role);
    }
    expandedRoles = next;
  }

  const form = useForm({});

  let showResetConfirm = $state(false);

  function submit(e) {
    e.preventDefault();
    form.update(() => ({
      profile: profileForm,
      personnel: personnelForm,
    }));
    $form.put('/admin/setup/institution', {
      preserveScroll: true,
      onSuccess: () => {
        const flash = $page.props.flash;
        if (flash?.success) showSuccess(flash.success);
      },
      onError: (errors) => {
        const first = Object.values(errors)[0];
        showError(first ?? 'Failed to save settings.');
      },
    });
  }

  function resetDefaults() {
    router.post('/admin/setup/institution/reset', {}, {
      preserveScroll: true,
      onSuccess: () => {
        const flash = $page.props.flash;
        if (flash?.success) showSuccess(flash.success);
        profileForm = Object.fromEntries(
          Object.entries(profile).map(([k, v]) => [k, v.env_default ?? ''])
        );
        personnelForm = Object.fromEntries(
          Object.entries(personnel).map(([role, fields]) => [
            role,
            Object.fromEntries(Object.entries(fields).map(([f, v]) => [f, v.env_default ?? ''])),
          ])
        );
        showResetConfirm = false;
      },
    });
  }

  onMount(() => {
    const flash = $page.props.flash;
    if (flash?.success) showSuccess(flash.success);
    if (flash?.error) showError(flash.error);
  });
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="space-y-6 min-w-0">
    <div class="flex items-center justify-between">
      <p class="text-sm text-muted-foreground">
        Manage institution profile, exam branding, and key personnel for documents.
      </p>
      {#if $form.processing}
        <span class="text-sm text-muted-foreground animate-pulse">Saving...</span>
      {/if}
    </div>

    <form onsubmit={submit} class="space-y-6">
      <!-- Institution Profile Card -->
      {#if Object.keys(profile).length > 0}
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Building2 class="h-5 w-5" />
            Institution Profile
          </CardTitle>
          <CardDescription>
            Core institution details and exam branding used across documents and the applicant portal.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div class="grid gap-4 sm:grid-cols-2">
            {#each Object.entries(profileLabels) as [key, label]}
              {@const field = profile[key]}
              <div class="space-y-1.5">
                <div class="flex items-center gap-2">
                  <label for="profile_{key}" class="text-sm font-medium leading-none">{label}</label>
                  {#if field?.overridden}
                    <Badge variant="warning" class="text-[10px] px-1.5 py-0">Override</Badge>
                  {:else if field?.env_default}
                    <Badge variant="muted" class="text-[10px] px-1.5 py-0">env</Badge>
                  {/if}
                </div>
                <Input
                  id="profile_{key}"
                  bind:value={profileForm[key]}
                  placeholder={field?.env_default || label}
                />
              </div>
            {/each}
          </div>
        </CardContent>
      </Card>
      {/if}

      <!-- Key Personnel Card -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Users class="h-5 w-5" />
            Key Personnel
          </CardTitle>
          <CardDescription>
            Officials whose names and titles appear on admission slips, result sheets, and other exam documents.
          </CardDescription>
        </CardHeader>
        <CardContent class="space-y-2">
          {#each personnelRoles as role}
            {@const fields = personnel[role]}
            {@const isExpanded = expandedRoles.has(role)}
            {@const hasOverride = fields && Object.values(fields).some((f) => f?.overridden)}
            {@const hasAnyValue = personnelForm[role] && Object.values(personnelForm[role]).some((v) => v?.trim() !== '')}

            <div class="rounded-xl border border-border overflow-hidden transition-colors {hasOverride ? 'border-amber-500/30' : ''}">
              <button
                type="button"
                onclick={() => toggleRole(role)}
                class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-muted/50 transition-colors"
              >
                <span class="font-medium text-sm text-foreground flex-1">{getRoleLabel(role)}</span>
                <div class="flex items-center gap-2">
                  {#if hasOverride}
                    <Badge variant="warning" class="text-[10px] px-1.5 py-0">Override</Badge>
                  {/if}
                  {#if !hasAnyValue && !hasOverride}
                    <Badge variant="muted" class="text-[10px] px-1.5 py-0">Not set</Badge>
                  {/if}
                  {#if isExpanded}
                    <ChevronUp class="h-4 w-4 text-muted-foreground shrink-0" />
                  {:else}
                    <ChevronDown class="h-4 w-4 text-muted-foreground shrink-0" />
                  {/if}
                </div>
              </button>

              {#if isExpanded}
                <div class="border-t border-border px-4 py-4 space-y-4 bg-muted/20">
                  <div class="grid gap-4 sm:grid-cols-3">
                    {#each ['name', 'title', 'credentials'] as field}
                      {@const f = fields?.[field]}
                      {@const fieldLabel = field === 'name' ? 'Name' : field === 'title' ? 'Title' : 'Credentials'}
                      <div class="space-y-1.5">
                        <div class="flex items-center gap-2">
                          <label for="personnel_{role}_{field}" class="text-sm font-medium leading-none">{fieldLabel}</label>
                          {#if f?.overridden}
                            <Badge variant="warning" class="text-[10px] px-1.5 py-0">Override</Badge>
                          {:else if f?.env_default}
                            <Badge variant="muted" class="text-[10px] px-1.5 py-0">env</Badge>
                          {/if}
                        </div>
                        <Input
                          id="personnel_{role}_{field}"
                          bind:value={personnelForm[role][field]}
                          placeholder={f?.env_default || fieldLabel}
                        />
                      </div>
                    {/each}
                  </div>
                </div>
              {/if}
            </div>
          {/each}
        </CardContent>
      </Card>

      <!-- Footer actions -->
      <div class="flex items-center justify-between pt-2">
        <div class="relative">
          {#if showResetConfirm}
            <div class="flex items-center gap-2">
              <span class="text-sm text-muted-foreground">Clear all overrides?</span>
              <Button type="button" variant="destructive" size="sm" onclick={resetDefaults}>
                Yes, reset
              </Button>
              <Button type="button" variant="ghost" size="sm" onclick={() => (showResetConfirm = false)}>
                Cancel
              </Button>
            </div>
          {:else}
            <Button
              type="button"
              variant="outline"
              onclick={() => (showResetConfirm = true)}
            >
              <RotateCcw class="h-4 w-4" />
              Reset to Defaults
            </Button>
          {/if}
        </div>
        <Button type="submit" disabled={$form.processing}>
          <Save class="h-4 w-4" />
          Save Changes
        </Button>
      </div>
    </form>
  </div>
</AuthenticatedLayout>
