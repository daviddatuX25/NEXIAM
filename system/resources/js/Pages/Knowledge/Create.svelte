<script>
  /**
   * Create knowledge source — quarantine body as draft (no Mixedbread, no is_active).
   */
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Label } from '@/Components/ui/label';
  import { Textarea } from '@/Components/ui/textarea';
  import { Link, useForm } from '@inertiajs/svelte';

  const form = useForm({
    title: '',
    body_text: '',
  });

  function submit(e) {
    e.preventDefault();
    form.post('/knowledge');
  }

  const breadcrumbs = [
    { label: 'Knowledge', href: '/knowledge' },
    { label: 'Create' },
  ];
</script>

<svelte:head><title>Add knowledge source</title></svelte:head>

<StaffLayout {breadcrumbs}>
  <div class="min-w-0 max-w-2xl space-y-6 p-4 sm:p-6">
    <PageHeaderBar
      title="Add knowledge source"
      description="Paste institutional policy text. It starts in quarantine, then extract → review → approve (another staff) → publish."
    />

    <form onsubmit={submit} class="space-y-4 rounded-lg border border-border bg-card p-6">
      <div class="space-y-2">
        <Label for="title">Title</Label>
        <Input id="title" bind:value={form.title} placeholder="e.g. Admission policy overview" required class="min-h-10" />
        {#if form.errors?.title}
          <p class="text-sm text-destructive">{form.errors.title}</p>
        {/if}
      </div>

      <div class="space-y-2">
        <Label for="body_text">Policy content</Label>
        <Textarea
          id="body_text"
          bind:value={form.body_text}
          placeholder="Institutional policy text only — no applicant PII."
          rows="12"
          required
          class="min-h-[160px] w-full"
        />
        {#if form.errors?.body_text}
          <p class="text-sm text-destructive">{form.errors.body_text}</p>
        {/if}
      </div>

      <div class="flex flex-wrap gap-2">
        <Button type="submit" class="min-h-10" disabled={form.processing}>Create draft</Button>
        <Link href="/knowledge">
          <Button type="button" variant="outline" class="min-h-10">Cancel</Button>
        </Link>
      </div>
    </form>
  </div>
</StaffLayout>
