<script>
    import { onMount } from 'svelte';
    import { Link } from '@inertiajs/svelte';
    import { Button } from '@/Components/ui/button';
    import { Badge } from '@/Components/ui/badge';
    import { Video, Mic, UserCheck, UserMinus, PhoneOff, ArrowLeft, AlertCircle } from 'lucide-svelte';
    import ApplicantActionsMenu from '@/Components/Admission/ApplicantActionsMenu.svelte';

    let {
        encounter,
        applicant,
        participant_name = null,
        score_summary = null,
        can_control = false,
        decision_url = '/consultation',
        back_url = '/consultation',
    } = $props();

    let frame = $state(null);
    let api = $state(null);
    let loading = $state(true);
    let callError = $state('');
    let closing = $state(false);
    let admittedParticipant = $state(null);
    let participantId = $state('');
    let participantOptions = $state([]);

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    }

    async function postJson(url) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({}),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(payload.message ?? 'The call action could not be completed.');
        }
        return payload;
    }

    function loadExternalApi(domain) {
        return new Promise((resolve, reject) => {
            if (window.JitsiMeetExternalAPI) {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = `https://${domain}/external_api.js`;
            script.async = true;
            script.onload = resolve;
            script.onerror = () => reject(new Error('The secure video service could not be loaded.'));
            document.head.appendChild(script);
        });
    }

    function syncParticipants(participants = []) {
        participantOptions = participants.map((participant) => ({
            id: participant.id,
            name: participant.displayName || 'Participant',
        }));
        if (!participantOptions.some((participant) => participant.id === participantId)) {
            participantId = participantOptions[0]?.id ?? '';
        }
    }
    async function refreshParticipants() {
        if (!api) return;
        try {
            syncParticipants(await api.getParticipantsInfo());
        } catch {
            participantOptions = [];
        }
    }

    async function startCall() {
        try {
            const credentials = await postJson(`/consultation/encounters/${encounter.id}/token`);
            if (!credentials.token || !credentials.domain || !credentials.room_name) {
                throw new Error('The call service returned incomplete credentials.');
            }
            await loadExternalApi(credentials.domain);
            if (!frame) {
                return;
            }

            api = new window.JitsiMeetExternalAPI(credentials.domain, {
                roomName: credentials.room_name,
                parentNode: frame,
                jwt: credentials.token,
                width: '100%',
                height: '100%',
                userInfo: { displayName: participant_name ?? applicant.name },
                configOverwrite: {
                    prejoinConfig: { enabled: false },
                    enableLobbyChat: false,
                    startWithAudioMuted: !can_control,
                    startWithVideoMuted: !can_control,
                },
                interfaceConfigOverwrite: {
                    TOOLBAR_BUTTONS: ['microphone', 'camera', 'hangup', 'tileview'],
                },
            });
            loading = false;

            api.addListener('videoConferenceJoined', () => { loading = false; });
            api.addListener('readyToClose', () => { loading = false; });
            api.addListener('participantJoined', () => { void refreshParticipants(); });
            api.addListener('participantLeft', () => { void refreshParticipants(); });
            api.addListener('knockingParticipant', (event) => {
                admittedParticipant = event.participant?.id ?? event.id ?? null;
            });
            api.addListener('videoConferenceLeft', () => { loading = false; });
            void refreshParticipants();
        } catch (error) {
            callError = error instanceof Error ? error.message : 'Unable to join the consultation call.';
            loading = false;
        }
    }

    function admitParticipant() {
        if (admittedParticipant && api) {
            api.executeCommand('answerKnockingParticipant', admittedParticipant, true);
            admittedParticipant = null;
        }
    }

    function muteParticipants() {
        if (!api) return;
        api.executeCommand('muteEveryone');
    }

    function removeParticipant() {
        if (api && participantId) {
            api.executeCommand('kickParticipant', participantId);
            participantId = '';
        }
    }

    async function endConference() {
        closing = true;
        callError = '';
        try {
            await postJson(`/consultation/encounters/${encounter.id}/close`);
            api?.dispose();
            api = null;
            window.location.assign(back_url);
        } catch (error) {
            callError = error instanceof Error ? error.message : 'The call could not be ended.';
            closing = false;
        }
    }

    onMount(() => {
        void startCall();
        return () => api?.dispose();
    });
</script>

<svelte:head>
    <title>Online consultation · NEXIAM</title>
</svelte:head>

<main class="min-h-screen bg-background p-3 sm:p-6">
    <div class="mx-auto flex max-w-[1600px] flex-col gap-4">
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <Link href={back_url} aria-label="Back to consultations">
                    <Button variant="ghost" size="icon" class="size-10"><ArrowLeft class="size-4" /></Button>
                </Link>
                <div>
                    <h1 class="text-xl font-semibold">Online consultation</h1>
                    <p class="text-sm text-muted-foreground">{applicant.name} · {encounter.status_label ?? 'Online appointment'}</p>
                </div>
            </div>
            {#if applicant.attempt_id}
                <ApplicantActionsMenu attemptId={applicant.attempt_id} />
            {/if}
            <Badge variant="outline" class="border-sky-500/30 bg-sky-500/5 text-sky-700 dark:text-sky-300">
                <Video class="mr-1 size-3.5" /> Online consultation
            </Badge>
        </header>

        {#if callError}
            <div role="alert" class="flex items-center gap-2 rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                <AlertCircle class="size-4 shrink-0" /> {callError}
            </div>
        {/if}

        <div class="grid min-h-[70vh] gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <section class="relative min-h-[55vh] overflow-hidden rounded-xl border bg-black" aria-label="Consultation video call">
                <div bind:this={frame} class="absolute inset-0"></div>
                {#if loading}
                    <div class="absolute inset-0 grid place-items-center bg-black/70 text-center text-sm text-white">
                        <div class="space-y-2"><Video class="mx-auto size-8" /><p>Connecting to the secure consultation room…</p></div>
                    </div>
                {/if}
            </section>

            <aside class="space-y-4 rounded-xl border bg-card p-4 sm:p-5">
                <section class="space-y-1">
                    <h2 class="text-base font-semibold">Applicant overview</h2>
                    <p class="text-sm text-muted-foreground">{applicant.name}</p>
                    {#if applicant.reference_code}
                        <p class="text-xs text-muted-foreground">Reference: {applicant.reference_code}</p>
                    {/if}
                </section>

                {#if score_summary?.series?.length}
                    <section class="space-y-3 border-t pt-4">
                        <h3 class="text-sm font-semibold">Candidate score summary</h3>
                        <ul class="space-y-2">
                            {#each score_summary.series as item (item.area_code)}
                                <li class="flex items-center justify-between gap-3 text-sm">
                                    <span class="text-muted-foreground">{item.area_name ?? item.area_code}</span>
                                    <span class="font-medium tabular-nums">{item.score ?? '—'}</span>
                                </li>
                            {/each}
                        </ul>
                    </section>
                {:else}
                    <p class="border-t pt-4 text-sm text-muted-foreground">No finalized score summary is available for this appointment.</p>
                {/if}

                {#if can_control}
                    <section class="space-y-3 border-t pt-4">
                        <h3 class="text-sm font-semibold">Counselor controls</h3>
                        <Button variant="outline" class="w-full justify-start gap-2" onclick={admitParticipant} disabled={!api || !admittedParticipant}>
                            <UserCheck class="size-4" /> {admittedParticipant ? 'Admit from lobby' : 'Waiting for applicant'}
                        </Button>
                        <Button variant="outline" class="w-full justify-start gap-2" onclick={muteParticipants} disabled={!api}>
                            <Mic class="size-4" /> Mute participants
                        </Button>
                        <div class="flex gap-2">
                            <select bind:value={participantId} class="h-10 min-w-0 flex-1 rounded-md border bg-background px-3 text-sm" aria-label="Participant to remove">
                                <option value="">Select participant</option>
                                {#each participantOptions as participant (participant.id)}
                                    <option value={participant.id}>{participant.name}</option>
                                {/each}
                            </select>
                            <Button variant="outline" size="icon" aria-label="Remove participant" onclick={removeParticipant} disabled={!participantId || !api}>
                                <UserMinus class="size-4" />
                            </Button>
                        </div>
                        <Button variant="destructive" class="w-full gap-2" onclick={endConference} disabled={closing}>
                            <PhoneOff class="size-4" /> {closing ? 'Ending call…' : 'End Conference'}
                        </Button>
                    </section>
                    <Button href={decision_url} target="_blank" rel="noopener noreferrer" class="w-full">
                        Open consultation decision in new tab
                    </Button>
                {/if}
            </aside>
        </div>
    </div>
</main>
