<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import ResultSheetTemplateEditor from '@/Components/ResultSheet/ResultSheetTemplateEditor.svelte';

    let {
        draft = null,
        placeholderGroups = {},
        domainPlaceholders = [],
        builderCatalog = {},
        builderCapabilities = {},
        builderDefaults = {},
        sampleContext = {},
        builderCompilerVersion = 'result-sheet-builder-1',
    } = $props();

    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Result Sheet Templates', href: '/setup/result-templates' },
        { label: 'Create' },
    ];
</script>

<svelte:head><title>New Result Sheet Template · Setup</title></svelte:head>

<StaffLayout {breadcrumbs}>
    <div class="min-w-0 p-4 sm:p-6">
        <ResultSheetTemplateEditor
            mode="create"
            {placeholderGroups}
            {domainPlaceholders}
            {builderCatalog}
            {builderCapabilities}
            {builderDefaults}
            {builderCompilerVersion}
            {sampleContext}
            {draft}
        />
    </div>
</StaffLayout>
