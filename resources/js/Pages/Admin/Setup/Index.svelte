<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, usePage } from '@inertiajs/svelte';
  import {
    CalendarRange,
    BookOpen,
    DoorOpen,
    Brain,
    FileText,
    Shield,
    Bot,
    Settings,
    Ticket,
    Users,
    BarChart3,
    CheckCircle2,
    AlertTriangle,
    XCircle,
    ChevronDown,
    ChevronUp,
    Activity,
    ArrowRight,
    Building2,
  } from 'lucide-svelte';

  let { allowDirectAssessment = false, aiCompanionEnabled = false, health = { categories: [], overall: { score: 0, total: 0, percentage: 0 } } } = $props();

  const breadcrumbs = [{ label: 'Setup' }];
  const page = usePage();
  const roles = $derived(
    $page.props.auth?.user?.roles?.map((r) => r.name) ?? []
  );

  function hasRole(r) {
    return roles.includes(r);
  }

  // Track which health category is expanded
  let expandedCategory = $state(null);

  function toggleCategory(key) {
    expandedCategory = expandedCategory === key ? null : key;
  }

  // Map category keys to their icon components
  const categoryIcons = {
    academic_years: CalendarRange,
    courses: BookOpen,
    rooms: DoorOpen,
    aptitude_areas: Brain,
    result_templates: FileText,
    admission_templates: Ticket,
    privacy_policies: Shield,
    staff: Users,
    institution: Building2,
    rating_scales: BarChart3,
  };

  // Compute category status: 'healthy' | 'warning' | 'critical'
  function getCategoryStatus(category) {
    const checks = category.checks || [];
    const hasCriticalFail = checks.some(c => !c.passed && c.severity === 'critical');
    const hasImportantFail = checks.some(c => !c.passed && c.severity === 'important');
    if (hasCriticalFail) return 'critical';
    if (hasImportantFail) return 'warning';
    return 'healthy';
  }

  function getCategoryPassedCount(category) {
    return (category.checks || []).filter(c => c.passed).length;
  }

  // Derive counts
  const criticalFails = $derived(
    health.categories.flatMap(c => c.checks).filter(c => !c.passed && c.severity === 'critical')
  );
  const importantFails = $derived(
    health.categories.flatMap(c => c.checks).filter(c => !c.passed && c.severity === 'important')
  );

  // Auto-expand first failing category
  $effect(() => {
    if (expandedCategory === null && health.categories.length > 0) {
      const firstFailing = health.categories.find(c => getCategoryStatus(c) !== 'healthy');
      if (firstFailing) {
        expandedCategory = firstFailing.key;
      }
    }
  });

  // Health bar color
  const healthBarColor = $derived(() => {
    if (health.overall.percentage >= 90) return 'bg-emerald-500';
    if (health.overall.percentage >= 60) return 'bg-amber-500';
    return 'bg-red-500';
  });

  const healthTextColor = $derived(() => {
    if (health.overall.percentage >= 90) return 'text-emerald-600 dark:text-emerald-400';
    if (health.overall.percentage >= 60) return 'text-amber-600 dark:text-amber-400';
    return 'text-red-600 dark:text-red-400';
  });

  const healthLabel = $derived(() => {
    if (health.overall.percentage === 100) return 'All systems ready';
    if (health.overall.percentage >= 90) return 'Almost there';
    if (health.overall.percentage >= 60) return 'Needs attention';
    return 'Setup required';
  });

  const setupCards = $derived(
    [
      {
        href: '/admin/academic-years',
        label: 'Academic Years',
        description: 'Manage academic year periods and application windows.',
        icon: CalendarRange,
        roles: ['super_admin', 'registrar_administrator'],
        healthKey: 'academic_years',
      },
      {
        href: '/admin/courses',
        label: 'Programs & Courses',
        description: 'Configure available courses and program offerings.',
        icon: BookOpen,
        roles: ['super_admin', 'registrar_administrator'],
        healthKey: 'courses',
      },
      {
        href: '/admin/rooms',
        label: 'Rooms & Facilities',
        description: 'Manage assessment rooms and seating capacity.',
        icon: DoorOpen,
        roles: ['super_admin', 'registrar_administrator'],
        healthKey: 'rooms',
      },
      {
        href: '/admin/aptitude-areas',
        label: 'Aptitude Areas',
        description:
          'Define scoring domains, weights, and computation formulas.',
        icon: Brain,
        roles: ['super_admin', 'test_administrator'],
        healthKey: 'aptitude_areas',
      },
      {
        href: '/admin/setup/rating-scales',
        label: 'Rating Scales',
        description: 'Percentile-to-rating mappings for result sheets.',
        icon: BarChart3,
        roles: ['super_admin', 'test_administrator'],
        healthKey: 'rating_scales',
      },
      {
        href: '/admin/setup/institution',
        label: 'Institution',
        description: 'Institution profile, exam branding, and key personnel for documents.',
        icon: Building2,
        roles: ['super_admin', 'registrar_administrator', 'test_administrator'],
        healthKey: 'institution',
      },
      {
        href: '/admin/release/result-templates',
        label: 'Result Sheet Templates',
        description: 'Design and manage result sheet print templates.',
        icon: FileText,
        roles: ['super_admin', 'test_administrator'],
        healthKey: 'result_templates',
      },
      {
        href: '/admin/admission-slip-templates',
        label: 'Admission Slip Templates',
        description: 'Configure admission slip layout and content.',
        icon: Ticket,
        roles: ['super_admin'],
        healthKey: 'admission_templates',
      },
      {
        href: '/admin/privacy-policies',
        label: 'Privacy Policies',
        description:
          'Manage privacy policy versions shown on the application form.',
        icon: Shield,
        roles: ['super_admin', 'registrar_administrator'],
        healthKey: 'privacy_policies',
      },
      {
        href: '/admin/users',
        label: 'Staff Accounts',
        description: 'Manage staff roles and user accounts.',
        icon: Users,
        roles: ['super_admin'],
        healthKey: 'staff',
      },
      {
        href: '/admin/ai-companion',
        label: 'AI Companion',
        description:
          'Configure AI advisor persona, knowledge base, and feature toggle.',
        icon: Bot,
        roles: ['super_admin'],
        badge: aiCompanionEnabled ? 'Active' : null,
      },
      {
        href: '/admin/settings',
        label: 'System Settings',
        description: 'Feature toggles, release mode, and system-wide configuration.',
        icon: Settings,
        roles: ['super_admin'],
      },
    ].filter((card) => card.roles.some((r) => hasRole(r)))
  );

  function getCardHealth(healthKey) {
    if (!healthKey) return null;
    return health.categories.find(c => c.key === healthKey) || null;
  }

  function getCardStatus(healthKey) {
    const cat = getCardHealth(healthKey);
    if (!cat) return null;
    return getCategoryStatus(cat);
  }

  function getCardProgress(healthKey) {
    const cat = getCardHealth(healthKey);
    if (!cat) return null;
    const total = cat.checks.length;
    const passed = cat.checks.filter(c => c.passed).length;
    return { passed, total, pct: total > 0 ? Math.round((passed / total) * 100) : 0 };
  }
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="space-y-6 min-w-0">
    <div>
      <p class="text-sm text-muted-foreground">
        Configure reference data, templates, and system-wide settings.
      </p>
    </div>

    <!-- ─── Overall Health Banner ─── -->
    <div class="rounded-2xl border border-border bg-card p-5 space-y-4">
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
          <div class="rounded-xl bg-muted p-2.5">
            <Activity class="h-5 w-5 text-muted-foreground" />
          </div>
          <div>
            <h2 class="text-sm font-semibold text-foreground">System Readiness</h2>
            <p class="text-xs text-muted-foreground mt-0.5">
              {health.overall.score} of {health.overall.total} checks passing
            </p>
          </div>
        </div>
        <div class="text-right">
          <span class="text-2xl font-bold {healthTextColor()}">{health.overall.percentage}%</span>
          <p class="text-xs text-muted-foreground mt-0.5">{healthLabel()}</p>
        </div>
      </div>

      <!-- Health bar -->
      <div class="relative h-2.5 w-full rounded-full bg-muted overflow-hidden">
        <div
          class="absolute inset-y-0 left-0 rounded-full transition-all duration-700 ease-out {healthBarColor()}"
          style="width: {health.overall.percentage}%"
        ></div>
      </div>

      <!-- Summary badges -->
      {#if criticalFails.length > 0 || importantFails.length > 0}
        <div class="flex flex-wrap gap-2">
          {#if criticalFails.length > 0}
            <span class="inline-flex items-center gap-1.5 rounded-full bg-red-500/10 px-3 py-1 text-xs font-medium text-red-600 dark:text-red-400">
              <XCircle class="h-3.5 w-3.5" />
              {criticalFails.length} critical
            </span>
          {/if}
          {#if importantFails.length > 0}
            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 px-3 py-1 text-xs font-medium text-amber-600 dark:text-amber-400">
              <AlertTriangle class="h-3.5 w-3.5" />
              {importantFails.length} need attention
            </span>
          {/if}
        </div>
      {/if}
    </div>

    <!-- ─── Category Checklists ─── -->
    {#if health.categories.length > 0}
      <div class="space-y-2">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground px-1">
          Setup Checklist
        </h3>
        <div class="space-y-1.5">
          {#each health.categories as category}
            {@const status = getCategoryStatus(category)}
            {@const passed = getCategoryPassedCount(category)}
            {@const total = category.checks.length}
            {@const pct = total > 0 ? Math.round((passed / total) * 100) : 0}
            {@const isExpanded = expandedCategory === category.key}
            {@const CatIcon = categoryIcons[category.key]}

            <div class="rounded-xl border border-border bg-card overflow-hidden transition-colors
              {status === 'critical' ? 'border-red-500/30' : status === 'warning' ? 'border-amber-500/20' : 'border-border'}">
              <!-- Category header (clickable) -->
              <button
                onclick={() => toggleCategory(category.key)}
                class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-muted/50 transition-colors"
              >
                <!-- Status indicator -->
                {#if status === 'healthy'}
                  <CheckCircle2 class="h-4.5 w-4.5 text-emerald-500 shrink-0" />
                {:else if status === 'warning'}
                  <AlertTriangle class="h-4.5 w-4.5 text-amber-500 shrink-0" />
                {:else}
                  <XCircle class="h-4.5 w-4.5 text-red-500 shrink-0" />
                {/if}

                <span class="font-medium text-sm text-foreground flex-1">{category.label}</span>

                <!-- Mini progress -->
                <span class="text-xs text-muted-foreground tabular-nums mr-2">
                  {passed}/{total}
                </span>

                <!-- Mini bar -->
                <div class="w-16 h-1.5 rounded-full bg-muted overflow-hidden mr-2 hidden sm:block">
                  <div
                    class="h-full rounded-full transition-all duration-500 {status === 'healthy' ? 'bg-emerald-500' : status === 'warning' ? 'bg-amber-500' : 'bg-red-500'}"
                    style="width: {pct}%"
                  ></div>
                </div>

                {#if isExpanded}
                  <ChevronUp class="h-4 w-4 text-muted-foreground shrink-0" />
                {:else}
                  <ChevronDown class="h-4 w-4 text-muted-foreground shrink-0" />
                {/if}
              </button>

              <!-- Expanded checks -->
              {#if isExpanded}
                <div class="border-t border-border px-4 py-3 space-y-2.5 bg-muted/20">
                  {#each category.checks as check}
                    <div class="flex items-start gap-2.5">
                      {#if check.passed}
                        <CheckCircle2 class="h-4 w-4 text-emerald-500 mt-0.5 shrink-0" />
                      {:else if check.severity === 'critical'}
                        <XCircle class="h-4 w-4 text-red-500 mt-0.5 shrink-0" />
                      {:else if check.severity === 'important'}
                        <AlertTriangle class="h-4 w-4 text-amber-500 mt-0.5 shrink-0" />
                      {:else}
                        <div class="h-4 w-4 rounded-full border-2 border-muted-foreground/30 mt-0.5 shrink-0"></div>
                      {/if}

                      <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium {check.passed ? 'text-foreground' : check.severity === 'critical' ? 'text-red-600 dark:text-red-400' : 'text-foreground'}">
                          {check.label}
                          {#if !check.passed && check.severity === 'critical'}
                            <span class="ml-1.5 inline-flex items-center rounded bg-red-500/10 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-red-600 dark:text-red-400">
                              Required
                            </span>
                          {/if}
                        </p>
                        <p class="text-xs text-muted-foreground mt-0.5">{check.message}</p>
                      </div>
                    </div>
                  {/each}

                  <!-- Quick action link -->
                  <div class="pt-1">
                    <Link
                      href={category.href}
                      class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                    >
                      Configure {category.label}
                      <ArrowRight class="h-3 w-3" />
                    </Link>
                  </div>
                </div>
              {/if}
            </div>
          {/each}
        </div>
      </div>
    {/if}

    <!-- ─── Setup Cards Grid ─── -->
    {#if setupCards.length > 0}
      <div>
        <h3 class="text-xs font-semibold uppercase tracking-wider text-muted-foreground px-1 mb-3">
          Configuration Modules
        </h3>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {#each setupCards as card}
            {@const cardStatus = getCardStatus(card.healthKey)}
            {@const progress = getCardProgress(card.healthKey)}

            <Link
              href={card.href}
              class="group relative flex flex-col gap-3 rounded-2xl border bg-card p-6 transition-all hover:border-primary/30 hover:shadow-md hover:shadow-primary/5 hover:-translate-y-0.5
                {cardStatus === 'critical' ? 'border-red-500/30' : cardStatus === 'warning' ? 'border-amber-500/20' : 'border-border'}"
            >
              <div class="flex items-start justify-between">
                <div
                  class="rounded-xl bg-muted p-2.5 transition-colors group-hover:bg-primary/10"
                >
                  <card.icon
                    class="h-5 w-5 text-muted-foreground transition-colors group-hover:text-primary"
                  />
                </div>
                <div class="flex items-center gap-2">
                  {#if card.badge}
                    <span
                      class="inline-flex items-center rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-semibold text-emerald-600 dark:text-emerald-400"
                    >
                      {card.badge}
                    </span>
                  {/if}
                  {#if cardStatus === 'healthy'}
                    <CheckCircle2 class="h-4 w-4 text-emerald-500" />
                  {:else if cardStatus === 'warning'}
                    <AlertTriangle class="h-4 w-4 text-amber-500" />
                  {:else if cardStatus === 'critical'}
                    <XCircle class="h-4 w-4 text-red-500" />
                  {/if}
                </div>
              </div>
              <div>
                <h3 class="font-semibold text-foreground">{card.label}</h3>
                <p class="mt-1 text-sm text-muted-foreground leading-relaxed">
                  {card.description}
                </p>
              </div>

              <!-- Per-card health bar -->
              {#if progress}
                <div class="mt-auto pt-2 space-y-1.5">
                  <div class="flex items-center justify-between">
                    <span class="text-xs text-muted-foreground">
                      {progress.passed}/{progress.total} checks
                    </span>
                    <span class="text-xs font-medium {cardStatus === 'healthy' ? 'text-emerald-600 dark:text-emerald-400' : cardStatus === 'warning' ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400'}">
                      {progress.pct}%
                    </span>
                  </div>
                  <div class="h-1.5 w-full rounded-full bg-muted overflow-hidden">
                    <div
                      class="h-full rounded-full transition-all duration-500 {cardStatus === 'healthy' ? 'bg-emerald-500' : cardStatus === 'warning' ? 'bg-amber-500' : 'bg-red-500'}"
                      style="width: {progress.pct}%"
                    ></div>
                  </div>
                </div>
              {:else}
                <div class="mt-auto pt-2">
                  <span
                    class="text-sm font-medium text-primary opacity-0 transition-opacity group-hover:opacity-100"
                  >
                    Configure →
                  </span>
                </div>
              {/if}
            </Link>
          {/each}
        </div>
      </div>
    {:else}
      <p class="py-12 text-center text-muted-foreground">
        No setup options available for your role.
      </p>
    {/if}
  </div>
</AuthenticatedLayout>
