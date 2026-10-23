<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Card from '@/Components/ui/card';
  import { Link, router, useForm } from '@inertiajs/svelte';
  import {
    SlidersHorizontal,
    Lock,
    CheckCircle2,
    ShieldCheck,
    Save,
    RotateCcw,
    Layers,
    Info,
    Calendar,
  } from 'lucide-svelte';

  let {
    cycles = [],
    selected_cycle_id = null,
    allow_guidance_all_in_one_intake = false,
    policy_version = 1,
    grouped_policies = {},
    requirement_levels = [],
    capabilities = {},
  } = $props();

  // Flatten policies for form state
  const initialPolicies = $derived(() => {
    const list = [];
    for (const groupKey of Object.keys(grouped_policies)) {
      for (const p of grouped_policies[groupKey] ?? []) {
        list.push({
          field_key: p.field_key,
          label: p.label,
          field_group: p.field_group,
          requirement_level: p.requirement_level,
          is_mandatory_floor: p.is_mandatory_floor,
        });
      }
    }
    return list;
  });

  let policyItems = $state([]);

  $effect(() => {
    policyItems = initialPolicies().map(p => ({ ...p }));
  });

  let saving = $state(false);

  function handleLevelChange(fieldKey, newLevel) {
    const idx = policyItems.findIndex(p => p.field_key === fieldKey);
    if (idx !== -1 && !policyItems[idx].is_mandatory_floor) {
      policyItems[idx].requirement_level = newLevel;
    }
  }

  function submitChanges(e) {
    e.preventDefault();
    if (!selected_cycle_id) return;

    saving = true;
    router.put('/setup/intake-fields', {
      academic_cycle_id: selected_cycle_id,
      policies: policyItems.map(p => ({
        field_key: p.field_key,
        requirement_level: p.requirement_level,
      })),
    }, {
      onFinish: () => { saving = false; },
    });
  }

  function toggleGuidanceDelegation(event) {
    if (!selected_cycle_id) return;
    router.put(`/setup/cycles/${selected_cycle_id}/guidance-delegation`, {
      allow_guidance_all_in_one_intake: event.currentTarget.checked,
    });
  }

  function onCycleChange(cycleId) {
    router.get('/setup/intake-fields', { cycle_id: cycleId });
  }

  const groupLabels = {
    identity: 'Core Identity & Demographics',
    contact: 'Contact & Location Details',
    academic: 'Prior Academic History & Origin',
    program_choices: 'Degree Program Choices & Alternatives',
  };

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'Application Form Fields' },
  ];
</script>

<svelte:head>
  <title>Application Form Fields · Setup</title>
</svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Application Form Fields"
      description="Configure institutional requirement levels for application forms and spreadsheet imports. Core identity fields are protected by a code-owned non-negotiable floor."
    >
      {#snippet actions()}
        <div class="flex items-center gap-2">
          <Badge variant="outline" class="font-mono text-xs">
            Revision v{policy_version}
          </Badge>
          <Link href="/setup">
            <Button variant="outline" size="sm">Back to readiness</Button>
          </Link>
        </div>
      {/snippet}
    </PageHeaderBar>

    <!-- Cycle Selector Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl border border-border bg-card p-4">
      <div class="flex items-center gap-3">
        <span class="inline-flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary">
          <Calendar class="size-4" />
        </span>
        <div>
          <p class="text-xs font-bold text-foreground">Governing Academic Cycle</p>
          <p class="text-[11px] text-muted-foreground">Requirements are versioned per cycle to prevent invalidating historic applications.</p>
        </div>
      </div>

      <div class="flex items-center gap-2">
        <select
          id="cycle-picker"
          value={selected_cycle_id}
          onchange={(e) => onCycleChange(e.target.value)}
          class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-xs font-medium text-foreground shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
        >
          {#each cycles as cycle}
            <option value={cycle.id}>
              {cycle.label} {cycle.is_active ? '★ (Active)' : ''}
            </option>
          {/each}
        </select>
      </div>
    </div>

    <!-- Non-Negotiable Floor Notice (D-085 Invariant) -->
    <div class="rounded-xl border border-primary/20 bg-primary/5 p-4">
      <div class="flex items-start gap-3">
        <ShieldCheck class="mt-0.5 size-5 shrink-0 text-primary" />
        <div class="space-y-1 text-xs">
          <p class="font-bold text-foreground">Non-Negotiable System Floor (D-085 &amp; PostgreSQL Constraints)</p>
          <p class="text-muted-foreground leading-relaxed">
            <strong class="text-foreground">First Name</strong>, <strong class="text-foreground">Last Name</strong>, <strong class="text-foreground">Date of Birth</strong>, <strong class="text-foreground">Sex</strong>, and <strong class="text-foreground">1st Choice Program</strong> are permanently system-mandatory. Birthdate is required for applicant portal access-key authentication (D-085); Sex and Names are required for deduplicated identity keys and cannot be disabled.
          </p>
        </div>
      </div>
    </div>

    <Card.Root class="border-border bg-card shadow-sm">
      <Card.Header>
        <Card.Title class="text-sm">Interoffice Application Delegation</Card.Title>
        <Card.Description>
          Allow Guidance Counselors to stage and commit All-in-One examinee files directly without requiring separate Registrar approval.
        </Card.Description>
      </Card.Header>
      <Card.Content>
        <label class="flex cursor-pointer items-center gap-3 text-sm text-foreground">
          <input
            type="checkbox"
            checked={allow_guidance_all_in_one_intake}
            onchange={toggleGuidanceDelegation}
            disabled={!selected_cycle_id}
            class="size-4 rounded border-border accent-primary"
          />
          <span>Allow Guidance All-in-One application delegation for this academic cycle</span>
        </label>
      </Card.Content>
    </Card.Root>

    <!-- Policy Edit Form -->
    <form onsubmit={submitChanges} class="space-y-6">
      {#each Object.keys(groupLabels) as groupKey}
        {@const fieldsInGroup = policyItems.filter(p => p.field_group === groupKey)}
        {#if fieldsInGroup.length > 0}
          <div class="rounded-2xl border border-border bg-card shadow-sm overflow-hidden">
            <div class="border-b border-border/60 bg-muted/40 px-5 py-3 flex items-center justify-between">
              <h2 class="text-xs font-bold uppercase tracking-wider text-muted-foreground flex items-center gap-2">
                <SlidersHorizontal class="size-3.5 text-primary" />
                {groupLabels[groupKey] ?? groupKey}
              </h2>
              <span class="text-[11px] text-muted-foreground font-mono">
                {fieldsInGroup.length} field{fieldsInGroup.length === 1 ? '' : 's'}
              </span>
            </div>

            <div class="divide-y divide-border/60">
              {#each fieldsInGroup as field}
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-3.5 hover:bg-muted/20 transition-colors">
                  <div class="space-y-0.5">
                    <div class="flex items-center gap-2">
                      <span class="text-xs font-bold text-foreground">{field.label}</span>
                      {#if field.is_mandatory_floor}
                        <span class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[10px] font-mono font-bold bg-primary/10 text-primary border border-primary/20">
                          <Lock class="size-3" />
                          Locked Floor
                        </span>
                      {:else}
                        <code class="text-[10px] text-muted-foreground font-mono">{field.field_key}</code>
                      {/if}
                    </div>
                    <p class="text-[11px] text-muted-foreground">
                      {#if field.is_mandatory_floor}
                        Protected schema minimum; verified by both portal and spreadsheet import.
                      {:else if field.requirement_level === 'institution_required'}
                        Strictly required by this institution; rows missing this value fail validation.
                      {:else if field.requirement_level === 'optional'}
                        Accepted if present; records pass validation if omitted.
                      {:else}
                        Disabled for this academic cycle; safely skipped during application processing.
                      {/if}
                    </p>
                  </div>

                  <div class="flex items-center gap-2 shrink-0">
                    {#if field.is_mandatory_floor}
                      <span class="text-xs font-semibold font-mono text-primary px-3 py-1.5 rounded-lg bg-primary/10 border border-primary/20">
                        System Mandatory
                      </span>
                    {:else}
                      <select
                        value={field.requirement_level}
                        onchange={(e) => handleLevelChange(field.field_key, e.target.value)}
                        class="flex h-9 rounded-md border border-input bg-background px-3 py-1 text-xs font-medium text-foreground shadow-xs focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                      >
                        <option value="institution_required">Required</option>
                        <option value="optional">Optional</option>
                        <option value="disabled">Disabled</option>
                      </select>
                    {/if}
                  </div>
                </div>
              {/each}
            </div>
          </div>
        {/if}
      {/each}

      <!-- Bottom Submit Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-xl border border-border bg-card p-4 shadow-sm">
        <div class="text-xs text-muted-foreground">
          Saving creates a <strong class="text-foreground">new policy revision (v{policy_version + 1})</strong> for this academic cycle.
          <span class="block text-[11px] text-muted-foreground/80">Already-accepted applicants remain protected under their historical submission revision.</span>
        </div>

        <div class="flex items-center gap-2 shrink-0">
          <Button
            type="submit"
            disabled={saving}
            class="min-h-10 gap-2 font-bold shadow-sm"
          >
            <Save class="size-4" />
            {saving ? 'Saving Policy Revision...' : `Save Revision v${policy_version + 1}`}
          </Button>
        </div>
      </div>
    </form>
  </div>
</StaffLayout>
