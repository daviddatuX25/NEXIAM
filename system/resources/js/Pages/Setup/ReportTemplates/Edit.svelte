<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import ResultSheetTemplateEditor from '@/Components/ResultSheet/ResultSheetTemplateEditor.svelte';

    let {
        surface = {},
        template = {},
        draft = null,
        placeholderGroups = {},
        domainPlaceholders = [],
        builderCatalog = {},
        builderCapabilities = {},
        builderDefaults = {},
        sampleContext = {},
        builderCompilerVersion = 'document-blueprint-1',
        documentAnalysis = null,
        baseline_inventory: baselineInventory = [],
        office_packs: officePacks = [],
        active_pack: activePack = null,
        field_policy = null,
    } = $props();

    const resolvedActivePack = $derived(
        activePack ??
            officePacks.find(
                (pack) =>
                    pack?.surface_key === surface?.surface_key ||
                    pack?.repeat_key === surface?.repeat_key,
            ) ??
            null,
    );

    const isCustomDocument = $derived(surface?.surface_key === 'custom_document');
    const basePath = $derived(
        isCustomDocument
            ? '/setup/document-templates/custom_document'
            : `/setup/report-templates/${surface.surface_key}`,
    );
    const breadcrumbs = isCustomDocument
        ? [
              { label: 'Setup', href: '/setup' },
              { label: 'Document & Template Hub', href: '/setup/document-templates' },
              { label: 'Custom Office Documents', href: '/setup/document-templates' },
              { label: template.name ?? 'Custom Document' },
          ]
        : [
              { label: 'Setup', href: '/setup' },
              { label: 'Document & Template Hub', href: '/setup/report-templates' },
              {
                  label: surface.title ?? 'Surface',
                  href: `/setup/report-templates/${surface.surface_key}`,
              },
              { label: template.name ?? 'Edit' },
          ];
</script>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 p-4 sm:p-6">
        <ResultSheetTemplateEditor
            {basePath}
            fieldPolicy={field_policy}
            mode="edit"
            {surface}
            {template}
            {placeholderGroups}
            {domainPlaceholders}
            {builderCatalog}
            {builderCapabilities}
            {builderDefaults}
            {builderCompilerVersion}
            {sampleContext}
            {documentAnalysis}
            {baselineInventory}
            {officePacks}
            activePack={resolvedActivePack}
            {draft}
        />
    </div>
</StaffLayout>
