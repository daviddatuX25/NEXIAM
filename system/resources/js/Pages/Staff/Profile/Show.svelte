<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Button } from '@/Components/ui/button';
  import { Link } from '@inertiajs/svelte';
  import { ArrowLeft, CircleUser } from 'lucide-svelte';

  let { page_state = 'ready', user } = $props();

  const breadcrumbs = [{ label: 'Staff', href: '/staff' }, { label: 'My Profile' }];
</script>

<svelte:head><title>My Profile</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="space-y-6 min-w-0 p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <PageHeaderBar
        title="My Profile"
        description="Your own staff account. Self-service password and account changes will live here."
      />
      <Link href="/staff/personnel">
        <Button variant="outline" class="min-h-11 gap-2">
          <ArrowLeft class="h-4 w-4" />
          Staff directory
        </Button>
      </Link>
    </div>

    {#if page_state === 'ready'}
      <div class="flex max-w-2xl flex-col gap-4 rounded-xl border border-border bg-card p-6 shadow-sm">
        <div class="flex items-center gap-4">
          <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
            <CircleUser class="h-6 w-6 text-primary" />
          </div>
          <div class="min-w-0">
            <p class="text-lg font-semibold">{user.name}</p>
            <p class="text-sm text-muted-foreground">{user.email}</p>
          </div>
        </div>

        <dl class="grid gap-3 sm:grid-cols-2">
          <div class="rounded-lg bg-muted/40 p-3">
            <dt class="text-xs uppercase tracking-wide text-muted-foreground">Office</dt>
            <dd class="mt-1 capitalize">{user.office}</dd>
          </div>
          <div class="rounded-lg bg-muted/40 p-3">
            <dt class="text-xs uppercase tracking-wide text-muted-foreground">Status</dt>
            <dd class="mt-1 capitalize">{user.status}</dd>
          </div>
        </dl>

        <div>
          <p class="text-xs uppercase tracking-wide text-muted-foreground">Role helpers</p>
          <div class="mt-2 flex flex-wrap gap-1.5">
            {#each user.roles ?? [] as r}
              <span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary">
                {r.display_name}
              </span>
            {/each}
          </div>
        </div>

        <p class="rounded-lg border border-dashed border-border bg-muted/20 p-3 text-sm text-muted-foreground">
          Password change and editing your own account are planned for this page in a follow-up cut.
        </p>
      </div>
    {/if}
  </div>
</StaffLayout>
