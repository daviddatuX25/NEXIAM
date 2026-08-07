<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, useForm } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Input } from '@/Components/ui/input';
  import { Textarea } from '@/Components/ui/textarea';
  import { success } from '@/lib/toast';

  const breadcrumbs = [
    { label: 'Knowledge Documents', href: '/admin/knowledge-documents' },
    { label: 'Create' },
  ];

  const form = useForm({
    title: '',
    content: '',
    metadata: {
      category: '',
      year: '',
      description: '',
      tags: [],
    },
  });

  $form.onFinish = () => {
    if (!$form.errors || Object.keys($form.errors).length === 0) {
      success('Knowledge document created');
    }
  };

  let tagsInput = $state('');

  function submitForm(e) {
    e.preventDefault();
    const tags = tagsInput
      ? tagsInput.split(',').map((t) => t.trim()).filter(Boolean)
      : [];
    $form.transform((data) => ({
      ...data,
      metadata: { ...data.metadata, tags },
    }));
    $form.post('/admin/knowledge-documents');
  }
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="max-w-2xl space-y-6">
    <p class="text-sm text-muted-foreground">
      Add text and metadata. Metadata (category, year) defines what this document is and is used for retrieval when the AI answers applicant questions.
    </p>

    <form onsubmit={submitForm} class="space-y-4 rounded-lg border border-border bg-card p-6">
      <div class="space-y-2">
        <label for="title" class="text-sm font-medium leading-none">Title</label>
        <Input id="title" bind:value={$form.title} placeholder="e.g. Engineering success rates 2024" required class="min-h-[44px]" />
        {#if $form.errors?.title}
          <p class="text-sm text-destructive">{$form.errors.title}</p>
        {/if}
      </div>

      <div class="space-y-2">
        <label for="content" class="text-sm font-medium leading-none">Content</label>
        <Textarea
          id="content"
          bind:value={$form.content}
          placeholder="Paste or type the text the AI can use. e.g. narrative sentences from data."
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
          <label for="category" class="text-sm font-medium leading-none">Category (optional)</label>
          <Input id="category" bind:value={$form.metadata.category} placeholder="e.g. Engineering" class="min-h-[44px]" />
          {#if $form.errors?.['metadata.category']}
            <p class="text-sm text-destructive">{$form.errors['metadata.category']}</p>
          {/if}
        </div>
        <div class="space-y-2">
          <label for="year" class="text-sm font-medium leading-none">Year (optional)</label>
          <Input id="year" bind:value={$form.metadata.year} placeholder="e.g. 2024" class="min-h-[44px]" />
          {#if $form.errors?.['metadata.year']}
            <p class="text-sm text-destructive">{$form.errors['metadata.year']}</p>
          {/if}
        </div>
      </div>

      <div class="space-y-2">
        <label for="meta-description" class="text-sm font-medium leading-none">Description (optional)</label>
        <Input id="meta-description" bind:value={$form.metadata.description} placeholder="Short description for retrieval" class="min-h-[44px]" />
        {#if $form.errors?.['metadata.description']}
          <p class="text-sm text-destructive">{$form.errors['metadata.description']}</p>
        {/if}
      </div>

      <div class="space-y-2">
        <label for="tags" class="text-sm font-medium leading-none">Tags (optional, comma-separated)</label>
        <Input id="tags" bind:value={tagsInput} placeholder="e.g. success_rates, engineering" class="min-h-[44px]" />
      </div>

      <div class="flex gap-3 pt-2">
        <Button type="submit" disabled={$form.processing} class="min-h-[44px]">
          {$form.processing ? 'Creating…' : 'Create document'}
        </Button>
        <Link href="/admin/knowledge-documents">
          <Button type="button" variant="outline" class="min-h-[44px]">Cancel</Button>
        </Link>
      </div>
    </form>
  </div>
</AuthenticatedLayout>
