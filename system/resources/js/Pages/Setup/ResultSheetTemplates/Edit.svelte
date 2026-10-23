<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import ResultSheetTemplateEditor from '@/Components/ResultSheet/ResultSheetTemplateEditor.svelte';

    let {
        template = {},
        draft = null,
        placeholderGroups = {},
        domainPlaceholders = [],
        builderCatalog = {},
        builderCapabilities = {},
        sampleContext = {},
        builderDefaults = {},
        builderCompilerVersion = 'result-sheet-builder-1',
        documentAnalysis = null,
    } = $props();

    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Result Sheet Templates', href: '/setup/result-templates' },
        { label: template.name ?? 'Edit' },
    ];
</script>

<svelte:head><title>Edit Result Sheet Template · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 p-4 sm:p-6">
        <ResultSheetTemplateEditor
            mode="edit"
            {template}
            {placeholderGroups}
            {domainPlaceholders}
            {builderCatalog}
            {builderCapabilities}
            {sampleContext}
            {builderDefaults}
            {builderCompilerVersion}
            {documentAnalysis}
            {draft}
        />
    </div>
</StaffLayout>
