<script>
  import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
  import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
  import { Button } from '@/Components/ui/button';
  import BlueprintBuilder from './BlueprintBuilder.svelte';
  import TemplateEditor from './TemplateEditor.svelte';

  let {
    areas = [],
    paper_options = [],
    engine = {},
    errors = {},
    draft_version = null,
    draft_refusal = null,
  } = $props();

  const breadcrumbs = [
    { label: 'Setup', href: '/setup' },
    { label: 'OMR Configuration', href: '/setup/omr' },
    { label: 'Template builder' },
  ];

  // Blueprint-first: new templates open in the blueprint editor; drafts that
  // carry a layout blueprint keep it; legacy geometry drafts open the
  // advanced template editor. The toggle is an explicit escape hatch.
  let mode = $state(draft_version ? (draft_version.layout_blueprint_json ? 'blueprint' : 'template') : 'blueprint');
</script>

<StaffLayout {breadcrumbs}>
  <PageHeaderBar
    title="Template builder"
    description="Compose answer-sheet templates from a semantic layout, or edit the raw native template directly."
  >
    {#snippet actions()}
      <div class="flex items-center gap-2">
        <Button
          variant={mode === 'blueprint' ? 'default' : 'outline'}
          size="sm"
          onclick={() => (mode = 'blueprint')}
        >
          Layout
        </Button>
        <Button
          variant={mode === 'template' ? 'default' : 'outline'}
          size="sm"
          onclick={() => (mode = 'template')}
        >
          Advanced
        </Button>
      </div>
    {/snippet}
  </PageHeaderBar>

  <section class="space-y-6 p-6">
    {#if draft_refusal}
      <p class="rounded bg-red-50 p-3 text-sm text-red-800">{draft_refusal}</p>
    {/if}
    {#if mode === 'blueprint'}
      <BlueprintBuilder {areas} {paper_options} {engine} {errors} {draft_version} />
    {:else}
      <TemplateEditor {areas} {paper_options} {engine} {errors} {draft_version} />
    {/if}
  </section>
</StaffLayout>
