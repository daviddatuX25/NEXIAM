<script>
    import StaffLayout from '@/Pages/Staff/StaffLayout.svelte';
    import PageHeaderBar from '@/Components/kit/PageHeaderBar.svelte';
    import NoticeFamilyList from '@/Components/Notifications/NoticeFamilyList.svelte';
    import { success, error } from '@/lib/toast';
    import * as Card from '@/Components/ui/card';
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import { Switch } from '@/Components/ui/switch';
    import { Link, useForm } from '@inertiajs/svelte';
    let { active_cycle = null, policy = {}, custom_sessions = [], notices = [], capabilities = { can_manage: false } } = $props();
    const form = useForm({ expected_version: policy.version ?? 0, publication_email_enabled: policy.publication_email_enabled ?? true, reminder_email_enabled: policy.reminder_email_enabled ?? true, reminder_offsets_minutes: policy.reminder_offsets_minutes ?? [1440] });
    const reminderOptions = [{ minutes: 4320, label: '72h' }, { minutes: 1440, label: '24h' }, { minutes: 120, label: '2h' }, { minutes: 60, label: '1h' }];
    let activeTab = $state('notices');
    const breadcrumbs = [{ label: 'Setup', href: '/setup' }, { label: 'Email & Notifications', href: '/setup/notifications' }, { label: 'Examination Desk' }];
    function toggleReminder(minutes) {
        const selected = form.reminder_offsets_minutes;
        form.reminder_offsets_minutes = selected.includes(minutes) ? selected.filter((offset) => offset !== minutes) : [...selected, minutes].sort((left, right) => right - left);
    }
    function submit(event) {
        event.preventDefault();
        form.put('/setup/notifications/examination', {
            onSuccess: (page) => {
                form.expected_version = page.props.policy?.version ?? form.expected_version;
                success('Communication rules saved.');
            },
            onError: () => {
                error(Object.values(form.errors ?? {})[0] ?? 'Communication rules could not be saved.');
            },
        });
    }
    function handleTabKeydown(event) {
        const tabKeys = ['notices', 'rules'];
        const currentIndex = tabKeys.indexOf(activeTab);
        let nextIndex = currentIndex;
        if (event.key === 'ArrowRight') {
            nextIndex = (currentIndex + 1) % tabKeys.length;
        } else if (event.key === 'ArrowLeft') {
            nextIndex = (currentIndex - 1 + tabKeys.length) % tabKeys.length;
        } else if (event.key === 'Home') {
            nextIndex = 0;
        } else if (event.key === 'End') {
            nextIndex = tabKeys.length - 1;
        } else {
            return;
        }
        event.preventDefault();
        activeTab = tabKeys[nextIndex];
        event.currentTarget.parentElement?.querySelectorAll('[role="tab"]')[nextIndex]?.focus();
    }
</script>
<svelte:head><title>Examination Desk · Notifications</title></svelte:head>
<StaffLayout {breadcrumbs}>
    <div class="min-w-0 space-y-7 p-4 sm:p-6">
        <PageHeaderBar title="Examination Desk" description="Canonical notices, academic-year communication rules, and separate session-specific overrides." />

        <div role="tablist" aria-label="Examination setup sections" class="flex w-full gap-1 rounded-lg border border-border bg-muted/30 p-1 sm:w-auto">
            <button
                type="button"
                id="examination-tab-notices"
                role="tab"
                aria-selected={activeTab === 'notices'}
                aria-controls="examination-notices-panel"
                tabindex={activeTab === 'notices' ? 0 : -1}
                class={`min-h-10 flex-1 rounded-md px-4 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:flex-none ${activeTab === 'notices' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
                onclick={() => activeTab = 'notices'}
                onkeydown={handleTabKeydown}
            >
                Notices
            </button>
            <button
                type="button"
                id="examination-tab-rules"
                role="tab"
                aria-selected={activeTab === 'rules'}
                aria-controls="examination-rules-panel"
                tabindex={activeTab === 'rules' ? 0 : -1}
                class={`min-h-10 flex-1 rounded-md px-4 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:flex-none ${activeTab === 'rules' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}
                onclick={() => activeTab = 'rules'}
                onkeydown={handleTabKeydown}
            >
                Rules &amp; Overrides
            </button>
        </div>
        <section
            role="tabpanel"
            id="examination-notices-panel"
            aria-labelledby="examination-tab-notices"
            tabindex="0"
            hidden={activeTab !== 'notices'}
            class="min-w-0"
        >
            <NoticeFamilyList {notices} domain="examination" canEdit={capabilities.can_manage} />
        </section>
        <section
            role="tabpanel"
            id="examination-rules-panel"
            aria-labelledby="examination-tab-rules"
            tabindex="0"
            hidden={activeTab !== 'rules'}
        >
            <div class="grid min-w-0 gap-6 xl:grid-cols-2">
                <Card.Root class="min-w-0 border-border/70">
                    <Card.Header><Card.Title>Examination communication rules</Card.Title><Card.Description>{active_cycle?.label ?? 'No active academic year'} · Required inbox delivery is unaffected by optional email settings.</Card.Description></Card.Header>
                    <Card.Content>
                        <form class="space-y-5" onsubmit={submit}>
                            <fieldset disabled={!capabilities.can_manage || form.processing || !active_cycle} class="min-w-0 space-y-4">
                                <legend class="sr-only">Examination policy settings</legend>
                                <label class="flex items-start justify-between gap-4 rounded-lg border border-border p-4"><span class="text-sm font-medium">Email candidates when their examination schedule is published</span><Switch bind:checked={form.publication_email_enabled} aria-label="Email examination schedule publication" /></label>
                                <label class="flex items-start justify-between gap-4 rounded-lg border border-border p-4"><span class="text-sm font-medium">Send countdown reminder emails in addition to required inbox reminders</span><Switch bind:checked={form.reminder_email_enabled} aria-label="Send countdown reminder emails" /></label>
                                <fieldset class="space-y-3 rounded-lg border border-border p-4">
                                    <legend class="px-1 text-sm font-semibold">Reminder countdown intervals</legend>
                                    <p class="text-sm text-muted-foreground">These windows govern in-app reminders and optional mail before the assigned session.</p>
                                    <div class="flex flex-wrap gap-2">{#each reminderOptions as option}<Button type="button" variant={form.reminder_offsets_minutes.includes(option.minutes) ? 'default' : 'outline'} aria-pressed={form.reminder_offsets_minutes.includes(option.minutes)} onclick={() => toggleReminder(option.minutes)}>{option.label}</Button>{/each}</div>
                                </fieldset>
                            </fieldset>
                            {#each Object.values(form.errors ?? {}) as message}<p role="alert" class="text-sm text-destructive">{message}</p>{/each}
                            <div class="flex flex-wrap items-center justify-end gap-3"><Button type="submit" disabled={!capabilities.can_manage || form.processing || !active_cycle}>{form.processing ? 'Saving…' : 'Save Examination Rules'}</Button></div>
                        </form>
                    </Card.Content>
                </Card.Root>
                <Card.Root class="min-w-0 border-border/70">
                    <Card.Header><Card.Title>Active session overrides</Card.Title><Card.Description>Existing session settings and publication snapshots retain precedence over academic year defaults.</Card.Description></Card.Header>
                    <Card.Content>
                        <div class="divide-y divide-border">
                            {#each custom_sessions as session (session.id)}
                                <article class="space-y-2 py-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2"><h3 class="break-words text-sm font-semibold">{session.code}</h3><Badge variant="outline">{session.status}</Badge></div>
                                    <p class="break-words text-sm text-muted-foreground">{session.room_name ?? 'Venue not set'} · {session.starts_at ?? 'Time not set'}</p>
                                    <p class="text-sm text-muted-foreground">Custom countdowns: {reminderOptions.filter((option) => (session.reminder_offsets_minutes ?? []).includes(option.minutes)).map((option) => option.label).join(', ') || 'None'}</p>
                                    <Link href={`/exams/sessions/${session.id}/edit`} class="inline-flex min-h-11 items-center text-sm font-medium text-primary underline underline-offset-4">Edit session</Link>
                                </article>
                            {:else}<p class="py-5 text-sm text-muted-foreground">No session-specific overrides are configured. Sessions inherit academic year defaults.</p>{/each}
                        </div>
                    </Card.Content>
                </Card.Root>
            </div>
        </section>
    </div>
</StaffLayout>
