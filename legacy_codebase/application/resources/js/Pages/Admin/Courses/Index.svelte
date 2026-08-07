<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, router } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Badge } from '@/Components/ui/badge';
  import { Plus, Pencil, Pause, Play, Trash2 } from 'lucide-svelte';
  import SwitchableListView from '@/Components/SwitchableListView.svelte';
  import SimplePagination from '@/Components/SimplePagination.svelte';
  import * as Table from '@/Components/ui/table';

  let { courses } = $props();


  let viewMode = $state('responsive');

  function doToggle(id, currentActive) {
    if (currentActive) {
      router.post(`/admin/courses/${id}/deactivate`, {}, {
        onSuccess: () => router.reload(),
        onError: (errors) => console.error('[Courses] deactivate failed:', errors?.message || errors),
      });
    } else {
      router.post(`/admin/courses/${id}/activate`, {}, {
        onSuccess: () => router.reload(),
      });
    }
  }

  function doDeletePermanent(id) {
    if (confirm('Permanently delete this course?')) {
      router.delete(`/admin/courses/${id}`, { onSuccess: () => router.reload() });
    }
  }

const breadcrumbs = [{ label: 'Setup', href: '/admin/setup' }, { label: 'Courses' }];
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="space-y-6 min-w-0">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm text-muted-foreground">View and manage available courses</p>
      </div>
      <div class="flex flex-wrap items-center gap-2 sm:gap-3">
        <Link href="/admin/courses/create">
          <Button class="min-h-[44px] gap-2">
            <Plus class="h-4 w-4" />
            <span class="hidden sm:inline">Add Course</span>
          </Button>
        </Link>
      </div>
    </div>

    <SwitchableListView bind:viewMode overflow="scroll">
      {#snippet table()}
        <Table.Root>
          <Table.Header class="bg-muted/50">
            <Table.Row>
              <Table.Head>Code</Table.Head>
              <Table.Head>Name</Table.Head>
              <Table.Head>Status</Table.Head>
              <Table.Head class="px-4 py-3 text-center">Actions</Table.Head>
            </Table.Row>
          </Table.Header>
          <Table.Body>
            {#each courses.data as course}
              <Table.Row class={course.is_active ? '' : 'text-muted-foreground/50 transition-colors'}>
                <Table.Cell class="font-mono">{course.code}</Table.Cell>
                <Table.Cell class="font-medium">{course.name}</Table.Cell>
                <Table.Cell>
                  <Badge variant={course.is_active ? 'success' : 'muted'} class="font-medium">
                    {course.is_active ? 'Active' : 'Inactive'}
                  </Badge>
                </Table.Cell>
                <Table.Cell class="text-center px-4 py-3">
                  <div class="w-[280px] inline-grid grid-cols-3 gap-2">
                    <Link href={`/admin/courses/${course.id}/edit`}>
                      <Button variant="ghost" size="sm" class="h-8 px-2 text-xs font-semibold hover:bg-muted">
                        <Pencil class="mr-1.5 h-3.5 w-3.5" />
                        Edit
                      </Button>
                    </Link>
                    <Button
                      variant="ghost"
                      size="sm"
                      class="h-8 px-2 text-xs font-semibold text-destructive hover:text-destructive hover:bg-destructive/5"
                      onclick={() => doDeletePermanent(course.id)}
                    >
                      <Trash2 class="mr-1.5 h-3.5 w-3.5" />
                      Delete
                    </Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      class="h-8 px-2 text-xs font-semibold {course.is_active
                        ? 'text-amber-600 hover:text-amber-700 hover:bg-amber-50'
                        : 'text-primary hover:text-primary-700 hover:bg-primary/5'}"
                      onclick={() => doToggle(course.id, course.is_active)}
                    >
                      {#if course.is_active}
                        <Pause class="mr-1.5 h-3.5 w-3.5" />
                        Deactivate
                      {:else}
                        <Play class="mr-1.5 h-3.5 w-3.5" />
                        Activate
                      {/if}
                    </Button>
                  </div>
                </Table.Cell>
              </Table.Row>
            {:else}
              <Table.Row>
                <Table.Cell colspan={4} class="py-12 text-center text-muted-foreground">
                  No courses yet. Create one to get started.
                </Table.Cell>
              </Table.Row>
            {/each}
          </Table.Body>
        </Table.Root>
        <SimplePagination data={courses} variant="table" />
      {/snippet}

      {#snippet cards()}
        {#if (courses?.data ?? []).length > 0}
          <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" role="list">
            {#each (courses?.data ?? []) as course}
              <li class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 transition-all {course.is_active ? 'shadow-sm' : 'opacity-60 grayscale-[0.5]'}">
                <div class="flex items-start justify-between gap-2">
                  <div class="min-w-0">
                    <p class="font-mono text-[10px] uppercase tracking-wider text-muted-foreground">{course.code}</p>
                    <h3 class="truncate font-bold tracking-tight">{course.name}</h3>
                  </div>
                  <Badge variant={course.is_active ? 'success' : 'muted'} class="shrink-0 font-medium">
                    {course.is_active ? 'Active' : 'Inactive'}
                  </Badge>
                </div>
                <div class="mt-auto flex gap-2 pt-2">
                  <Link href={`/admin/courses/${course.id}/edit`} class="flex-1">
                    <Button variant="outline" size="sm" class="w-full min-h-[40px] font-semibold">
                      <Pencil class="h-3.5 w-3.5 mr-1.5" />
                      Edit
                    </Button>
                  </Link>
                  <Button
                    variant="outline"
                    size="sm"
                    class="flex-1 min-h-[40px] font-semibold {course.is_active
                      ? 'text-amber-600 border-amber-200 hover:bg-amber-50 hover:text-amber-700'
                      : 'text-primary border-primary/20 hover:bg-primary/5 hover:text-primary-700'}"
                    onclick={() => doToggle(course.id, course.is_active)}
                  >
                    {#if course.is_active}
                      <Pause class="h-3.5 w-3.5 mr-1.5" />
                      Deactivate
                    {:else}
                      <Play class="h-3.5 w-3.5 mr-1.5" />
                      Activate
                    {/if}
                  </Button>
                </div>
              </li>
            {/each}
          </ul>
        {:else}
          <p class="py-12 text-center text-muted-foreground">No courses yet. Create one to get started.</p>
        {/if}
        <SimplePagination data={courses} variant="centered" />
      {/snippet}
    </SwitchableListView>
  </div>
</AuthenticatedLayout>