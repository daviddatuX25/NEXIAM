<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, useForm } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import Switch from '@/Components/ui/switch/switch.svelte';
  import { Textarea } from '@/Components/ui/textarea';
  import { success } from '@/lib/toast';

  let { document: doc } = $props();

  const breadcrumbs = [
    { label: 'Knowledge Documents', href: '/admin/knowledge-documents' },
    { label: 'Edit' },
  ];

  const meta = doc?.metadata ?? {};
  const tagsArray = Array.isArray(meta.tags) ? meta.tags : [];
  const initialTags = tagsArray.join(', ');

  const form = useForm({
    title: doc?.title ?? '',
    content: doc?.content ?? '',
    metadata: {
      category: meta.category ?? '',
      year: meta.year ?? '',
      description: meta.description ?? '',
      tags: tagsArray,
    },
    is_active: doc?.is_active ?? true,
  });

  $form.onFinish = () => {
    if (!$form.errors || Object.keys($form.errors).length === 0) {
      success('Knowledge document updated');
    }
  };

  let tagsInput = $state(initialTags);

  $effect(() => {
    tagsInput = initialTags;
  });

  function submitForm(e) {
    e.preventDefault();
    const tags = tagsInput
      ? tagsInput.split(',').map((t) => t.trim()).filter(Boolean)
      : [];
    $form.transform((data) => ({
      ...data,
      metadata: { ...data.metadata, tags },
    }));
    $form.put(`/admin/knowledge-documents/${doc.id}`);
  }
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="max-w-2xl space-y-6">
    <form onsubmit={submitForm} class="space-y-4 rounded-lg border border-border bg-card p-6">
      <div class="space-y-2">
        <label for="title">Title</label>
        <Input id="title" bind:value={$form.title} placeholder="e.g. Engineering success rates 2024" required class="min-h-[44px]" />
        {#if $form.errors?.title}
          <p class="text-sm text-destructive">{$form.errors.title}</p>
        {/if}
      </div>

      <div class="space-y-2">
        <label for="content">Content</label>
        <Textarea
          id="content"
          bind:value={$form.content}
          placeholder="Paste or type the text the AI can use."
          rows="10"
          class="flex w-full min-h-[120px]"
          required
        />
        {#if $form.errors?.content}
          <p class="text-sm text-destructive">{$form.errors.content}</p>
        {/if}
      </div>

      <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-2">
          <label for="category">Category (optional)</label>
          <Input id="category" bind:value={$form.metadata.category} placeholder="e.g. Engineering" class="min-h-[44px]" />
        </div>
        <div class="space-y-2">
          <label for="year">Year (optional)</label>
          <Input id="year" bind:value={$form.metadata.year} placeholder="e.g. 2024" class="min-h-[44px]" />
        </div>
      </div>

      <div class="space-y-2">
        <label for="meta-description">Description (optional)</label>
        <Input id="meta-description" bind:value={$form.metadata.description} placeholder="Short description for retrieval" class="min-h-[44px]" />
      </div>

      <div class="space-y-2">
        <label for="tags">Tags (comma-separated)</label>
        <Input id="tags" bind:value={tagsInput} placeholder="e.g. success_rates, engineering" class="min-h-[44px]" />
      </div>

      <div class="flex items-center gap-4 rounded-lg border border-border p-4">
        <Switch
          checked={$form.is_active}
          onCheckedChange={(checked) => form.update((f) => ({ ...f, is_active: checked }))}
          aria-label="Document active for retrieval"
        />
        <div>
          <label class="font-medium">Active</label>
          <p class="text-xs text-muted-foreground">Inactive documents are not used for AI retrieval.</p>
        </div>
      </div>

      <div class="flex gap-3 pt-2">
        <Button type="submit" disabled={$form.processing} class="min-h-[44px]">
          {$form.processing ? 'Saving…' : 'Save changes'}
        </Button>
        <Link href="/admin/knowledge-documents">
          <Button type="button" variant="outline" class="min-h-[44px]">Cancel</Button>
        </Link>
      </div>
    </form>
  </div>
</AuthenticatedLayout>
