<script>
  import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.svelte';
  import { Link, router } from '@inertiajs/svelte';
  import { useForm } from '@inertiajs/svelte';
  import { Button } from '@/Components/ui/button';
  import { Badge } from '@/Components/ui/badge';
  import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
  import * as Table from '@/Components/ui/table';
  import { Textarea } from '@/Components/ui/textarea';
  import { Plus, Pencil, Trash2, FileText, Bot } from 'lucide-svelte';

  let { documents = [], ai_companion_persona = '' } = $props();


  const breadcrumbs = [{ label: 'Setup', href: '/admin/setup' }, { label: 'AI Companion' }];

  // Tab state
  let activeTab = $state('documents');

  // Persona form
  const form = useForm({ ai_companion_persona });

  $effect(() => {
    form.update((f) => ({ ...f, ai_companion_persona }));
  });

  let savingPersona = $state(false);

  function submitPersona(e) {
    e.preventDefault();
    savingPersona = true;
    $form.put('/admin/ai-companion/persona', {
      preserveScroll: true,
      onFinish: () => { savingPersona = false; },
    });
  }

  function deleteDocument(id) {
    if (confirm('Delete this document? This cannot be undone.')) {
      router.delete(`/admin/knowledge-documents/${id}`, { preserveScroll: true });
    }
  }
</script>

<AuthenticatedLayout {breadcrumbs}>
  <div class="space-y-6 min-w-0">
    <!-- Page intro -->
    <div>
      <h2 class="text-2xl font-semibold tracking-tight">AI Companion</h2>
      <p class="mt-1 text-sm text-muted-foreground">Configure the chat advisor that helps applicants understand their exam results.</p>
    </div>

    <!-- Tab navigation - styled as segmented control -->
    <div class="inline-flex items-center rounded-lg border border-border bg-muted/50 p-1">
      <button
        type="button"
        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-md transition-all {activeTab === 'documents'
          ? 'bg-background text-foreground shadow-sm'
          : 'text-muted-foreground hover:text-foreground'}"
        onclick={() => (activeTab = 'documents')}
      >
        <FileText class="h-4 w-4" />
        Knowledge Documents
      </button>
      <button
        type="button"
        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-md transition-all {activeTab === 'persona'
          ? 'bg-background text-foreground shadow-sm'
          : 'text-muted-foreground hover:text-foreground'}"
        onclick={() => (activeTab = 'persona')}
      >
        <Bot class="h-4 w-4" />
        Persona
      </button>
    </div>

    <!-- Tab: Knowledge Documents -->
    {#if activeTab === 'documents'}
      <Card class="bg-transparent border-none shadow-none p-0">
        <CardHeader class="pb-4">
          <div class="flex items-center justify-between">
            <div>
              <CardTitle>Knowledge Base</CardTitle>
              <CardDescription>
                Documents the AI uses to answer applicant questions. Upload PDFs, texts, or FAQs.
              </CardDescription>
            </div>
            <Link href="/admin/knowledge-documents/create">
              <Button class="min-h-[44px]">
                <Plus class="mr-2 h-4 w-4" />
                Add Document
              </Button>
            </Link>
          </div>
        </CardHeader>
        <CardContent class="pt-0">
          <div class="min-w-0">
            <Table.Root class="w-full min-w-[640px] text-sm">
              <Table.Header class="bg-muted/50">
                <Table.Row>
                  <Table.Head class="px-4 py-3">Title</Table.Head>
                  <Table.Head class="px-4 py-3">Status</Table.Head>
                  <Table.Head class="px-4 py-3">Added</Table.Head>
                  <Table.Head class="text-center">Actions</Table.Head>
                </Table.Row>
              </Table.Header>
              <Table.Body>
                {#each documents as doc (doc.id)}
                  <Table.Row>
                    <Table.Cell class="px-4 py-3 font-medium">{doc.title ?? doc.name ?? '—'}</Table.Cell>
                    <Table.Cell class="px-4 py-3">
                      <Badge variant={doc.status === 'active' ? 'success' : 'muted'}>{doc.status ?? 'active'}</Badge>
                    </Table.Cell>
                    <Table.Cell class="px-4 py-3 text-sm text-muted-foreground">{doc.created_at ?? '—'}</Table.Cell>
                    <Table.Cell class="text-center">
                      <div class="flex justify-center gap-2">
                        <Link href={`/admin/knowledge-documents/${doc.id}/edit`}>
                          <Button variant="ghost" size="sm" class="h-8 px-2 text-xs">
                            <Pencil class="mr-1.5 h-3.5 w-3.5" />
                            Edit
                          </Button>
                        </Link>
                        <Button
                          variant="ghost"
                          size="sm"
                          class="h-8 px-2 text-xs text-destructive hover:text-destructive hover:bg-destructive/10"
                          onclick={() => deleteDocument(doc.id)}
                        >
                          <Trash2 class="mr-1.5 h-3.5 w-3.5" />
                          Delete
                        </Button>
                      </div>
                    </Table.Cell>
                  </Table.Row>
                {:else}
                  <Table.Row>
                    <Table.Cell colspan={4} class="px-4 py-12">
                      <div class="text-center">
                        <FileText class="mx-auto h-10 w-10 text-muted-foreground/40 mb-3" />
                        <p class="text-sm text-muted-foreground">No knowledge documents yet.</p>
                        <p class="text-xs text-muted-foreground mt-1">Add documents to give the AI advisor context for answering questions.</p>
                      </div>
                    </Table.Cell>
                  </Table.Row>
                {/each}
              </Table.Body>
            </Table.Root>
          </div>
        </CardContent>
      </Card>
    {/if}

    <!-- Tab: Persona -->
    {#if activeTab === 'persona'}
      <Card class="bg-transparent border-none shadow-none p-0">
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <Bot class="h-5 w-5" />
            Persona & Behavior
          </CardTitle>
          <CardDescription>
            System instructions that define how the AI advisor behaves — its tone, guardrails, and boundaries. Used when applicants chat with the advisor.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onsubmit={submitPersona} class="space-y-4">
            <Textarea
              bind:value={$form.ai_companion_persona}
              placeholder="e.g. You are an encouraging academic counselor. Be warm but factual. Base your advice only on the data provided. Never share sensitive personal information."
              rows="10"
              class="flex w-full min-h-[200px]"
              maxlength="5000"
            />
            {#if $form.errors?.ai_companion_persona}
              <p class="text-sm text-destructive">{$form.errors.ai_companion_persona}</p>
            {/if}
            <div class="flex items-center justify-between">
              <p class="text-xs text-muted-foreground">Max 5000 characters. Plain text only — no HTML.</p>
              <Button type="submit" disabled={savingPersona} class="min-h-[44px]">
                {savingPersona ? 'Saving…' : 'Save persona'}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    {/if}

  </div>
</AuthenticatedLayout>