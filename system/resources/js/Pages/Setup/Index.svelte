<script>
  /**
   * Setup hub — legacy Admin/Setup: per-category create+activate checks,
   * one Configure link each; capability-gated (aptitude = TA only).
   */
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Badge } from '@/Components/ui/badge';
  import { Button } from '@/Components/ui/button';
  import * as Card from '@/Components/ui/card';
  import { Link } from '@inertiajs/svelte';
  import { CheckCircle2, CircleAlert, ChevronDown, ChevronRight } from 'lucide-svelte';

  let {
    readiness = { overall_percent: 0, passed: 0, total: 0, categories: [] },
    capabilities = { setup: false, assessment: false, quota: false },
  } = $props();

  let expanded = $state(null);

  $effect(() => {
    const firstFail = (readiness.categories ?? []).find((c) =>
      (c.checks ?? []).some((ch) => !ch.passed),
    );
    if (firstFail && expanded === null) {
      expanded = firstFail.key;
    }
  });

  function toggle(key) {
    expanded = expanded === key ? null : key;
  }

  function categoryStatus(category) {
    const checks = category.checks ?? [];
    if (checks.some((c) => !c.passed && c.severity === 'critical')) return 'critical';
    if (checks.some((c) => !c.passed)) return 'warning';
    return 'healthy';
  }

  function canConfigure(category) {
    const need = category.capability ?? 'setup';
    if (need === 'assessment') return capabilities.assessment;
    if (need === 'quota') return capabilities.quota;
    return capabilities.setup;
  }

  const breadcrumbs = [{ label: 'Setup' }];
</script>

<svelte:head><title>Setup · NEXIAM</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="System setup"
      description="Legacy-style readiness with create then activate checks per catalog. Aptitude is Test Administrator only. Rooms, programs, and academic years are shared."
    >
      {#snippet actions()}
        <Badge variant="secondary">{readiness.overall_percent ?? 0}% ready</Badge>
      {/snippet}
    </PageHeaderBar>

    <Card.Root>
      <Card.Header class="border-b border-border/60 pb-4">
        <Card.Title class="text-base">Readiness</Card.Title>
        <Card.Description>
          {readiness.passed ?? 0} of {readiness.total ?? 0} checks passed. Each category has its own
          Configure link.
        </Card.Description>
      </Card.Header>
      <Card.Content class="space-y-3 pt-4">
        <div class="h-2 overflow-hidden rounded-full bg-muted">
          <div
            class="h-full rounded-full bg-primary transition-all"
            style={`width: ${readiness.overall_percent ?? 0}%`}
          ></div>
        </div>

        <ul class="space-y-2">
          {#each readiness.categories ?? [] as category (category.key)}
            {@const status = categoryStatus(category)}
            {@const open = expanded === category.key}
            {@const configure = canConfigure(category)}
            <li class="rounded-xl border border-border/70 bg-card">
              <button
                type="button"
                class="flex w-full items-center gap-2 px-3 py-3 text-left"
                onclick={() => toggle(category.key)}
              >
                {#if open}
                  <ChevronDown class="size-4 text-muted-foreground" />
                {:else}
                  <ChevronRight class="size-4 text-muted-foreground" />
                {/if}
                <span class="flex-1 text-sm font-medium">{category.label}</span>
                {#if category.capability === 'assessment'}
                  <span class="text-[10px] uppercase tracking-wide text-muted-foreground">TA</span>
                {/if}
                <Badge
                  variant={status === 'critical'
                    ? 'destructive'
                    : status === 'warning'
                      ? 'outline'
                      : 'secondary'}
                  class="capitalize"
                >
                  {status}
                </Badge>
              </button>
              {#if open}
                <div class="space-y-2 border-t border-border/60 px-3 py-3">
                  {#each category.checks as check}
                    <div class="flex items-start gap-2 text-sm">
                      {#if check.passed}
                        <CheckCircle2 class="mt-0.5 size-4 shrink-0 text-primary" />
                      {:else}
                        <CircleAlert
                          class="mt-0.5 size-4 shrink-0 {check.severity === 'critical'
                            ? 'text-destructive'
                            : 'text-amber-600 dark:text-amber-400'}"
                        />
                      {/if}
                      <div class="min-w-0 flex-1">
                        <p class="font-medium">{check.label}</p>
                        {#if check.detail}
                          <p class="text-xs text-muted-foreground">{check.detail}</p>
                        {/if}
                      </div>
                    </div>
                  {/each}
                  {#if configure}
                    <Link href={category.href}>
                      <Button size="sm" variant="outline" class="mt-1"
                        >Configure {category.label}</Button
                      >
                    </Link>
                  {:else}
                    <p class="text-xs text-muted-foreground">
                      {#if category.capability === 'assessment'}
                        Aptitude areas are configured by the Test Administrator.
                      {:else if category.capability === 'quota'}
                        Program Heads set their program quotas. The Test
                        Administrator may adjust with a notice to the Program Head.
                      {:else}
                        Open Configure when you have institutional setup access.
                      {/if}
                    </p>
                  {/if}
                </div>
              {/if}
            </li>
          {/each}
        </ul>
      </Card.Content>
    </Card.Root>
  </div>
</StaffLayout>
