<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import NoticeFamilyList from '@/Components/Notifications/NoticeFamilyList.svelte';
    import { Badge } from '@/Components/ui/badge';
    let { notices = [], capabilities = { can_manage: false } } = $props();
    const breadcrumbs = [{ label: 'Setup', href: '/setup' }, { label: 'Email & Notifications', href: '/setup/notifications' }, { label: 'Results Desk' }];
</script>
<svelte:head><title>Results &amp; Consultation Communications · Setup</title></svelte:head>
<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-7 p-4 sm:p-6">
        <PageHeaderBar title="Results & Consultation Communications" description="Canonical notices governed by official release and consultation workflows." />
        <NoticeFamilyList {notices} domain="results" canEdit={capabilities.can_manage} />
        <section class="flex flex-wrap items-center gap-2" aria-label="Release governance">
            <Badge variant="outline">Online release PDF-eligible</Badge>
            <Badge variant="outline">Scores governed by release workflow</Badge>
            <Badge variant="outline">{capabilities.can_manage ? 'Release-authorized authoring' : 'Metadata-only access'}</Badge>
        </section>
    </div>
</StaffLayout>
