<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import NoticeFamilyList from '@/Components/Notifications/NoticeFamilyList.svelte';
    import { success, error } from '@/lib/toast';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import * as Card from '@/Components/ui/card';
    import { Switch } from '@/Components/ui/switch';
    import { useForm } from '@inertiajs/svelte';
    let { active_cycle = null, policy = {}, notices = [], capabilities = { can_manage: false } } = $props();
    const form = useForm({
        expected_version: policy.version ?? 0,
        applicant_status_email_enabled: policy.applicant_status_email_enabled ?? false,
        registrar_new_submission_alert_enabled: policy.registrar_new_submission_alert_enabled ?? false,
    });
    const breadcrumbs = [
        { label: 'Setup', href: '/setup' },
        { label: 'Email & Notifications', href: '/setup/notifications' },
        { label: 'Admissions Desk' },
    ];
    function submit(event) {
        event.preventDefault();
        form.put('/setup/notifications/admissions', {
            onSuccess: (page) => {
                form.expected_version = page.props.policy?.version ?? form.expected_version;
                success('Communication rules saved.');
            },
            onError: () => {
                error(Object.values(form.errors ?? {})[0] ?? 'Communication rules could not be saved.');
            },
        });
    }
</script>
<svelte:head><title>Admissions Desk · Notifications</title></svelte:head>
<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-7 p-4 sm:p-6">
        <PageHeaderBar title="Admissions Desk" description="Canonical admissions notices and authoritative academic year communication rules." />

        <NoticeFamilyList {notices} domain="admissions" canEdit={capabilities.can_manage} />
        <Card.Root class="border-border/70">
            <Card.Header>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><Card.Title>Admissions communication rules</Card.Title><Card.Description>These academic year settings govern delivery. Editing a notice does not change channels.</Card.Description></div>
                    <Badge variant="outline">{active_cycle ? `Active cycle · ${active_cycle.label ?? active_cycle.name}` : 'No active academic year'}</Badge>
                </div>
            </Card.Header>
            <Card.Content>
                <form class="space-y-5" onsubmit={submit}>
                    <fieldset disabled={!capabilities.can_manage || form.processing || !active_cycle} class="grid min-w-0 gap-4 lg:grid-cols-2">
                        <legend class="sr-only">Admissions policy settings</legend>
                        <label class="flex items-start justify-between gap-4 rounded-lg border border-border p-4">
                            <span class="space-y-1"><span class="block text-sm font-medium">Email applicants upon status change</span><span class="block text-sm text-muted-foreground">Accepted, dismissed and correction-requested notices follow this optional email setting. Required inbox delivery is unchanged.</span></span>
                            <Switch bind:checked={form.applicant_status_email_enabled} aria-label="Email applicants upon status change" />
                        </label>
                        <label class="flex items-start justify-between gap-4 rounded-lg border border-border p-4">
                            <span class="space-y-1"><span class="block text-sm font-medium">Alert Registrar queue about new submissions</span><span class="block text-sm text-muted-foreground">Notify the authorized Registrar queue when an application is submitted.</span></span>
                            <Switch bind:checked={form.registrar_new_submission_alert_enabled} aria-label="Alert Registrar queue about new submissions" />
                        </label>
                    </fieldset>
                    {#each Object.values(form.errors ?? {}) as message}<p role="alert" class="text-sm text-destructive">{message}</p>{/each}
                    <div class="flex flex-wrap items-center justify-end gap-3"><Button type="submit" disabled={!capabilities.can_manage || form.processing || !active_cycle}>{form.processing ? 'Saving…' : 'Save Admissions Rules'}</Button></div>
                </form>
            </Card.Content>
        </Card.Root>
    </div>
</StaffLayout>
