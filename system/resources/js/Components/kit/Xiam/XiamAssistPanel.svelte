<script>
    import { onDestroy, onMount, tick, untrack } from 'svelte';
    import { Link, usePage } from '@inertiajs/svelte';
    import { Button } from '@/Components/ui/button';
    import {
        Activity,
        ArrowDown,
        BookOpenText,
        CircleDot,
        Compass,
        Database,
        HelpCircle,
        LockKeyhole,
        RotateCcw,
        Send,
        ShieldCheck,
        Sparkles,
        Table2,
        Wrench,
        X,
    } from 'lucide-svelte';
    import XiamAvatar, {
        prepareXiamThinkingArtwork,
    } from '@/Components/kit/Xiam/XiamAvatar.svelte';
    import XiamProposalCard from '@/Components/kit/Xiam/XiamProposalCard.svelte';
    import { normalizeXiamNavigation } from '@/lib/xiam-navigation.js';
    import XiamThinkingCard from '@/Components/kit/Xiam/XiamThinkingCard.svelte';
    import {
        XIAM_AVATAR_EMOTIONS,
        isXiamAvatarEmotion,
    } from '@/lib/xiam-avatar-reducer';
    import { sound } from '@/lib/sound.js';
    import {
        success as toastSuccess,
        info as toastInfo,
        error as toastError,
    } from '@/lib/toast.js';
    import { getXiamActionPresentation } from '@/lib/xiam-presentation';
    import {
        formatXiamPackTitle,
        resolveXiamContext,
    } from '@/lib/xiam-context.js';
    import { decodeXiamProposalView } from '@/lib/xiam-proposal-view';
    import {
        cancelXiamProposal,
        clearXiamConversation,
        confirmXiamProposal,
        fetchXiamMode,
        fetchXiamSnapshot,
        setXiamMode,
        streamXiamConversation,
        submitXiamTurn,
    } from '@/lib/xiam-transport';
    import {
        computeAvatarDimensions,
        isRowCleared,
        calculateScrollFollow,
    } from '@/lib/xiam-panel-layout';
    import {
        calculateLauncherCoordinates,
        createLauncherDragTracker,
        getPersistedPosition,
        refreshSafeAreaInsets,
    } from '@/lib/xiam-launcher-drag';

    let {
        enabled = true,
        mode = 'degraded',
        disclosure = 'Xiam is an assistant. It cannot grant access or change records without a confirmed typed command.',
        defaultInteraction = 'inquiry',
        interactionModes = [{ key: 'inquiry', label: 'Inquiry' }],
        usage: initialUsage = null,
        session = null,
    } = $props();

    const STORAGE_KEY = 'nexiam.xiam.expanded';

    let innerWidth = $state(
        typeof window !== 'undefined' ? window.innerWidth : 1024,
    );
    let followingEnd = $state(true);
    let clearedRowIds = $state(new Set());
    let rowRefs = $state({});
    let liveUsage = $state(initialUsage ?? session?.usage ?? null);
    let modePending = $state(false);
    let preferenceLoading = $state(false);
    let modeError = $state('');
    let modeFeedback = $state('');
    let usageDetailsOpen = $state(false);
    let usageButtonEl = $state(null);
    let usageModalEl = $state(null);

    function closeUsageModal() {
        usageDetailsOpen = false;
        usageButtonEl?.focus();
    }

    $effect(() => {
        if (usageDetailsOpen) {
            void tick().then(() => {
                usageModalEl?.focus();
            });
        }
    });
    let historyStatus = $state('idle');
    let usageGeneration = 0;
    let lastResetRefresh = '';

    const avatarDims = $derived(computeAvatarDimensions(innerWidth));
    const avatarSpriteSize = $derived(avatarDims.spriteSize);

    function updateRowClearance() {
        if (!scrollEl) return;
        const scrollRect = scrollEl.getBoundingClientRect();
        const avatarHeight = avatarDims.cardHeight;
        const safetyGap = 8;
        const nextCleared = new Set();

        for (const message of messages) {
            const el = rowRefs[message.id];
            if (!el) continue;
            const elRect = el.getBoundingClientRect();
            const rowBottomFromBottom = scrollRect.bottom - elRect.bottom;
            if (isRowCleared(rowBottomFromBottom, avatarHeight, safetyGap)) {
                nextCleared.add(message.id);
            }
        }
        clearedRowIds = nextCleared;
    }

    function handleScroll() {
        if (!scrollEl) return;
        followingEnd = calculateScrollFollow(
            scrollEl.scrollTop,
            scrollEl.scrollHeight,
            scrollEl.clientHeight,
            32,
        );
        updateRowClearance();
    }
    const AVATAR_EMOTIONS = XIAM_AVATAR_EMOTIONS;
    const page = usePage();
    const isStaff = $derived(
        page.props.auth?.surface === 'staff' ||
            page.url.startsWith('/staff') ||
            page.url.startsWith('/admin') ||
            page.url.startsWith('/setup') ||
            page.url.startsWith('/assessment') ||
            page.url.startsWith('/consultation') ||
            page.url.startsWith('/reports'),
    );
    const fixtureContext = $derived(resolveXiamContext(page.url ?? '/'));
    const contextTitle = $derived(
        formatXiamPackTitle(
            session?.page_pack?.key,
            fixtureContext.title ?? 'Workspace',
        ),
    );
    const modes = $derived(
        Array.isArray(serverInteractionModes)
            ? serverInteractionModes
            : Array.isArray(session?.interaction_modes)
              ? session.interaction_modes
              : Array.isArray(interactionModes)
                ? interactionModes
                : [{ key: 'inquiry', label: 'Inquiry' }],
    );
    const allowedModes = $derived(modes.map((option) => option.key));
    const inquiryAllowed = $derived(allowedModes.includes('inquiry'));
    const assistantAllowed = $derived(allowedModes.includes('assistant'));
    const currentModeLabel = $derived(
        modes.find((option) => option.key === interaction)?.label ?? 'Inquiry',
    );
    const usageLabel = $derived.by(() => {
        if (!liveUsage) return 'Usage unavailable';
        const unit = liveUsage.unit || 'asks';
        const limitLabel =
            liveUsage.limit === -1 ? 'unlimited' : String(liveUsage.limit);
        return `${liveUsage.used}/${limitLabel} ${unit}`;
    });
    const usageTitle = $derived.by(() => {
        if (!liveUsage) return 'Usage status unavailable';
        const remaining =
            liveUsage.limit === -1
                ? 'Unlimited'
                : `${liveUsage.remaining ?? 0} remaining`;
        const reset = liveUsage.resets_at
            ? ` · resets ${new Date(liveUsage.resets_at).toLocaleString()}`
            : '';
        const credits =
            liveUsage.credit_balance > 0
                ? ` · ${liveUsage.credit_balance} extension credits`
                : '';
        return `${liveUsage.used} of ${liveUsage.limit === -1 ? 'unlimited' : liveUsage.limit} ${liveUsage.unit} ${liveUsage.period} · ${remaining}${credits}${reset}`;
    });
    const composerUnavailable = $derived(
        busy ||
            modePending ||
            !allowedModes.includes(interaction) ||
            liveUsage?.state === 'exhausted' ||
            (session?.availability === 'quota_exhausted' && !liveUsage),
    );

    let expanded = $state(false);
    let interaction = $state('inquiry');
    let input = $state('');
    let messages = $state([]);
    let gate = $state(null);
    let citationOpen = $state(null);
    let avatarEvent = $state({ emotion: 'idle', generation: 1, mood: 'idle' });
    let hydrated = $state(false);
    let busy = $state(false);
    let statusText = $state(null);
    let streamMessageId = $state(null);
    let loadedConversationId = $state(null);
    let clearedSessionConvId = $state(null);
    let currentCursor = $state(0);
    let scrollEl = $state();
    let dockRoot = $state();
    let detailsRoot = $state();
    let launcherSummary = $state();
    let abortController = $state(null);
    let streamReceivedText = false;
    let seenEventSeq = new Set();
    let historyRequestEpoch = 0;
    let targetStreamMood = $state(null);
    let userInitiatedOpen = false;
    let avatarGraceTimer = null;
    let launcherPosition = $state({ side: 'right', yRatio: 1.0 });
    let launcherLeft = $state(0);
    let launcherTop = $state(0);
    let isDragging = $state(false);
    let capsuleExpanded = $state(false);
    const launcherDrag = createLauncherDragTracker({
        getElement: () => launcherSummary,
        onDraggingChange: (dragging) => {
            isDragging = dragging;
            if (dragging) capsuleExpanded = false;
        },
        onMove: (coordinates) => {
            launcherLeft = coordinates.left;
            launcherTop = coordinates.top;
        },
        onSnap: (position, coordinates) => {
            launcherPosition = position;
            launcherLeft = coordinates.left;
            launcherTop = coordinates.top;
        },
    });

    function updateLauncherPosition() {
        const coordinates = calculateLauncherCoordinates(launcherPosition);
        launcherLeft = coordinates.left;
        launcherTop = coordinates.top;
    }

    function handleViewportChange() {
        innerWidth = window.innerWidth;
        refreshSafeAreaInsets();
        updateLauncherPosition();
        updateRowClearance();
    }

    onMount(() => {
        launcherPosition = getPersistedPosition();
        updateLauncherPosition();
        window.addEventListener('resize', handleViewportChange);
        window.addEventListener('orientationchange', handleViewportChange);

        function handleOpenXiamChat(event) {
            if (detailsRoot?.open) {
                minimize();
                return;
            }
            capsuleExpanded = false;
            userInitiatedOpen = true;
            if (detailsRoot) {
                detailsRoot.open = true;
                detailsRoot.dispatchEvent(new Event('toggle'));
            }
            expanded = true;
            sound.pop();
            if (event?.detail?.prompt) {
                input = event.detail.prompt;
            }
            const isBusyOrTyping = busy || Boolean(typewriterInterval);
            const hasIncidentOrTool = Boolean(
                gate ||
                [
                    'sad',
                    'shock',
                    'angry',
                    'searching-paper',
                    'searching-phone',
                ].includes(avatarEvent?.emotion),
            );
            if (!isBusyOrTyping && !hasIncidentOrTool) {
                setAvatar('hi', { mood: 'idle' });
            }
        }

        window.addEventListener('nexiam:open-xiam-chat', handleOpenXiamChat);

        return () => {
            window.removeEventListener('resize', handleViewportChange);
            window.removeEventListener('orientationchange', handleViewportChange);
            window.removeEventListener('nexiam:open-xiam-chat', handleOpenXiamChat);
        };
    });

    function scheduleAvatarIdleGrace(delayMs = 2800) {
        if (avatarGraceTimer) {
            clearTimeout(avatarGraceTimer);
            avatarGraceTimer = null;
        }
        avatarGraceTimer = setTimeout(() => {
            avatarGraceTimer = null;
            if (!avatarWorking) {
                setAvatar('idle', {
                    lifecycle: true,
                    resolveIncident: true,
                    mood: 'idle',
                });
            }
        }, delayMs);
    }

    onDestroy(() => {
        if (avatarGraceTimer !== null) clearTimeout(avatarGraceTimer);
        if (typewriterInterval !== null) clearInterval(typewriterInterval);
        abortController?.abort();
    });

    const latestAssistantId = $derived.by(() => {
        for (let index = messages.length - 1; index >= 0; index -= 1) {
            if (messages[index].role === 'assistant') {
                return messages[index].id;
            }
        }
        return null;
    });
    const latestAssistant = $derived(
        messages.find((message) => message.id === latestAssistantId) ?? null,
    );
    const gateRenderedInHistory = $derived(
        Boolean(
            gate &&
            messages.some((message) => message.proposal?.id === gate.id),
        ),
    );

    function formatMessageMarkdown(content) {
        return content
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(
                /`(.*?)`/g,
                '<code class="rounded bg-muted px-1 py-0.5 font-mono text-[11px] text-foreground">$1</code>',
            )
            .replace(
                /\*\*(.*?)\*\*/g,
                '<strong class="font-semibold text-foreground">$1</strong>',
            )
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/_(.*?)_/g, '<em>$1</em>')
            .replace(/\[\[(?:navigate:)?([a-zA-Z0-9_.-]+)\]\]/g, '$1')
            .replace(/\n/g, '<br />');
    }

    function asObject(value) {
        return value && typeof value === 'object' && !Array.isArray(value)
            ? value
            : {};
    }

    function textValue(value, fallback = '') {
        if (typeof value === 'string') return value;
        if (typeof value === 'number' || typeof value === 'boolean') {
            return String(value);
        }
        if (value === null || value === undefined) return fallback;
        try {
            return JSON.stringify(value);
        } catch {
            return fallback;
        }
    }

    function citationFrom(value) {
        const source = asObject(value);
        return {
            source_title: textValue(
                source.source_title ?? source.title ?? source.source_class,
                'Approved source',
            ),
            source_id: textValue(source.source_id ?? source.version_id, ''),
            version_id: textValue(source.version_id, ''),
            excerpt: textValue(source.excerpt, 'No excerpt supplied.'),
            asof: textValue(source.asof ?? source.created_at, ''),
        };
    }

    function citationKey(citation) {
        return [
            citation.version_id,
            citation.source_id,
            citation.source_title,
            citation.excerpt,
        ].join('|');
    }

    function rowsFromSummary(summary, capability) {
        const source = asObject(summary);
        const rawRows = Array.isArray(summary)
            ? summary
            : Array.isArray(summary?.rows)
              ? summary.rows
              : Array.isArray(summary?.changes)
                ? summary.changes
                : Array.isArray(summary?.diff)
                  ? summary.diff
                  : Array.isArray(summary?.impact?.rows)
                    ? summary.impact.rows
                    : [];
        if (rawRows.length) {
            return rawRows.map((raw, index) => {
                const row = asObject(raw);
                const isDiffRow =
                    Object.hasOwn(row, 'before') ||
                    Object.hasOwn(row, 'after') ||
                    Object.hasOwn(row, 'key');
                return {
                    entity: isDiffRow
                        ? (row.label ?? row.key ?? `Target ${index + 1}`)
                        : (row.entity ??
                          row.target ??
                          row.label ??
                          `Target ${index + 1}`),
                    current: isDiffRow ? row.before : (row.current ?? '—'),
                    proposed: isDiffRow
                        ? row.after
                        : (row.proposed ?? row.next ?? 'Server proposal'),
                    delta: isDiffRow
                        ? null
                        : (row.delta ?? row.change ?? 'Review'),
                };
            });
        }

        return [
            {
                entity: 'Change',
                current: source.current ?? '',
                proposed:
                    source.proposed ??
                    source.action ??
                    'Review the proposed changes',
                delta: source.delta ?? 'Review',
            },
        ];
    }

    function preflightFromSummary(summary) {
        const raw = Array.isArray(summary)
            ? summary
            : Array.isArray(summary?.checks)
              ? summary.checks
              : Array.isArray(summary?.preflight)
                ? summary.preflight
                : Array.isArray(summary?.preflight?.checks)
                  ? summary.preflight.checks
                  : Array.isArray(summary?.impact?.preflight)
                    ? summary.impact.preflight
                    : [];
        if (raw.length > 0) {
            return raw.map((item) => {
                const check = asObject(item);
                const status = check.status;
                return {
                    key: check.key,
                    label: check.label ?? check.message ?? '',
                    status,
                    detail: check.detail,
                    ok: status === 'pass',
                };
            });
        }
        return [
            {
                label: 'Checks will run when you confirm',
                status: 'unknown',
                detail: null,
                ok: false,
            },
        ];
    }

    function proposalMatches(existing, candidate) {
        if (!existing || !candidate) return false;
        const candId = textValue(candidate?.proposal_id ?? candidate?.id, '');
        const candRunId = textValue(candidate?.run_id, '');
        const existId = textValue(existing?.proposal_id ?? existing?.id, '');
        const existRunId = textValue(existing?.run_id, '');

        if (candId && existId && candId !== 'pending' && existId !== 'pending') {
            return candId === existId;
        }
        if (candRunId && existRunId && candRunId === existRunId) {
            return true;
        }
        return false;
    }

    function messageMatchesProposal(message, proposal) {
        if (!message || !proposal) return false;
        if (Array.isArray(message.proposals) && message.proposals.some((p) => proposalMatches(p, proposal))) {
            return true;
        }
        if (message.proposal && proposalMatches(message.proposal, proposal)) {
            return true;
        }
        const runId = textValue(proposal.run_id, '');
        if (runId && (message.id === runId || message.run_id === runId)) {
            return true;
        }
        return false;
    }

    function normalizeProposal(data) {
        const source = asObject(data);
        const proposalView =
            decodeXiamProposalView(source.proposal_view) ??
            decodeXiamProposalView(source.proposal_review) ??
            decodeXiamProposalView(source) ??
            null;
        const summary = asObject(source.summary);
        const capability = textValue(
            proposalView?.capability_id ??
                source.capability_id ??
                source.capability,
            'allowlisted capability',
        );
        const proposalId = textValue(
            proposalView?.proposal_id ?? source.proposal_id ?? source.id,
            'pending',
        );
        const runId = textValue(
            proposalView?.run_id ?? source.run_id ?? source.run?.id,
            '',
        );
        return {
            id: proposalId,
            proposal_id: proposalId,
            run_id: runId,
            capability,
            title: textValue(
                proposalView?.title ?? summary.title ?? summary.action,
                `Review ${capability}`,
            ),
            proposal_view: source.proposal_view ?? proposalView,
            rows: rowsFromSummary(
                source.diff ?? summary.diff ?? summary,
                capability,
            ),
            preflight: preflightFromSummary({
                preflight:
                    proposalView?.preflight?.checks ??
                    source.preflight_checks ??
                    source.preflight ??
                    summary.preflight_checks ??
                    summary.evidence?.checks,
            }),
            digest: textValue(
                summary.digest ?? source.digest,
                'server supplied',
            ),
            safety_tier: textValue(
                proposalView?.safety?.tier ??
                    source.safety_tier ??
                    summary.safety_tier,
                't1',
            ),
            confirmation_phrase: ((proposalView?.safety?.confirmation_phrase ??
                textValue(
                    source.confirmation_phrase ?? summary.confirmation_phrase,
                    '',
                )) || null),
            requires_reauth: Boolean(
                proposalView?.safety?.requires_reauth ??
                    source.requires_reauth ??
                    summary.requires_reauth,
            ),
            expires_at: textValue(
                proposalView?.safety?.expires_at ?? source.expires_at,
                '',
            ),
            status: normalizeProposalStatus(
                proposalView?.status ?? source.status,
            ),
            receipt: textValue(
                proposalView?.receipt?.receipt_id ??
                    source.receipt_id ??
                    source.receipt,
                '',
            ),
            error: proposalView?.error ?? source.error ?? null,
        };
    }

    function normalizeProposalStatus(rawStatus) {
        const status = textValue(rawStatus, 'proposed');
        if (status === 'pending' || status === 'proposed') return 'proposed';
        if (status === 'executed' || status === 'committed') return 'committed';
        if (status === 'cancelled' || status === 'rejected') return 'rejected';
        if (status === 'invalidated') return 'invalidated';
        if (status === 'failed') return 'failed';
        return status;
    }

    function diffRowFrom(data) {
        const source = asObject(data);
        const kind = textValue(source.kind, 'change');
        const label = textValue(source.label, 'Server diff');
        const value = textValue(source.value, '—');
        return {
            entity: label,
            current: kind === 'current' ? value : '—',
            proposed: kind === 'proposed' ? value : '—',
            delta: kind === 'delta' ? value : kind,
        };
    }

    function messageTemplate(id, role, content = '') {
        return {
            id,
            role,
            content,
            citations: [],
            navigation: [],
            corpusNote: null,
            steps: [],
            diffRows: [],
            proposal: null,
            modeRequest: null,
            streaming: false,
            streamCompleted: false,
        };
    }

    let typewriterInterval = $state(null);
    const avatarWorking = $derived(
        busy ||
            typewriterInterval !== null ||
            messages.some((message) => message.streaming),
    );

    function finishAvatarTurn() {
        if (avatarWorking) return;
        const reaction =
            targetStreamMood ??
            (latestAssistant?.proposal?.status === 'proposed'
                ? 'thumbs-up'
                : 'idle');
        targetStreamMood = null;
        setAvatar(reaction, {
            lifecycle: true,
            resolveIncident: true,
            mood: 'idle',
        });
        if (reaction !== 'idle') scheduleAvatarIdleGrace();
    }

    function queueTypewriter() {
        if (typewriterInterval) return;
        typewriterInterval = setInterval(() => {
            let anyActive = false;
            messages = messages.map((message) => {
                const raw = message.rawContent ?? message.content;
                if (message.content.length < raw.length) {
                    anyActive = true;
                    const remaining = raw.length - message.content.length;
                    const step =
                        remaining > 120
                            ? 6
                            : remaining > 50
                              ? 4
                              : remaining > 15
                                ? 2
                                : 1;
                    const nextLen = Math.min(
                        raw.length,
                        message.content.length + step,
                    );
                    return {
                        ...message,
                        content: raw.slice(0, nextLen),
                    };
                }
                if (
                    message.streaming &&
                    message.streamCompleted &&
                    message.content.length >= raw.length
                ) {
                    return {
                        ...message,
                        streaming: false,
                    };
                }
                return message;
            });

            void scrollToBottom();

            if (anyActive) {
                if (
                    ![
                        'searching-paper',
                        'searching-phone',
                        'shock',
                        'sad',
                    ].includes(avatarEvent?.emotion) &&
                    avatarEvent?.emotion !== 'talking-calm'
                ) {
                    setAvatar('talking-calm', { lifecycle: true });
                }
            } else {
                clearInterval(typewriterInterval);
                typewriterInterval = null;
                if (!avatarWorking) {
                    finishAvatarTurn();
                } else if (
                    avatarWorking &&
                    ![
                        'searching-paper',
                        'searching-phone',
                        'shock',
                        'sad',
                    ].includes(avatarEvent?.emotion)
                ) {
                    setAvatar('thinking', { lifecycle: true });
                }
            }
        }, 16);
    }

    function updateMessage(id, updater) {
        messages = messages.map((message) =>
            message.id === id ? updater({ ...message }) : message,
        );
    }

    function ensureMessage(id, role = 'assistant') {
        if (messages.some((message) => message.id === id)) return;
        messages = [...messages, messageTemplate(id, role)];
    }

    function speak(content, citations = [], corpusNote = null) {
        const id = `local-${Date.now()}-${messages.length}`;
        messages = [
            ...messages,
            {
                ...messageTemplate(id, 'assistant'),
                content: '',
                rawContent: content,
                citations,
                corpusNote,
                streaming: true,
                streamCompleted: true,
            },
        ];
        queueTypewriter();
        setAvatar('talking-calm', { lifecycle: true });
    }

    function setAvatar(emotion, extra = {}) {
        if (!AVATAR_EMOTIONS.includes(emotion) || !isXiamAvatarEmotion(emotion))
            return;
        avatarEvent = {
            emotion,
            generation: (avatarEvent?.generation ?? 0) + 1,
            ...extra,
        };
    }

    function mergeCitations(id, rawCitations) {
        const incoming = Array.isArray(rawCitations)
            ? rawCitations.map(citationFrom)
            : [];
        updateMessage(id, (message) => {
            const byKey = new Map(
                message.citations.map((citation) => [
                    citationKey(citation),
                    citation,
                ]),
            );
            for (const citation of incoming) {
                byKey.set(citationKey(citation), citation);
            }
            return { ...message, citations: [...byKey.values()] };
        });
    }

    function addStep(id, payload) {
        const source = asObject(payload);
        const step = {
            event_type: textValue(source.event_type, 'evidence.checkpoint'),
            capability_id: textValue(source.capability_id ?? source.tool, ''),
            tool_call_id: textValue(source.tool_call_id ?? source.id, ''),
            phase: textValue(source.phase, ''),
            mode: textValue(source.mode, ''),
            ok: source.ok ?? source.successful ?? null,
            label: textValue(source.label, ''),
            detail: textValue(source.detail, ''),
        };
        const signature = step.tool_call_id
            ? [step.event_type, step.capability_id, step.tool_call_id].join('|')
            : [step.event_type, step.capability_id, step.phase].join('|');
        updateMessage(id, (message) => {
            const known = new Set(
                message.steps.map((item) =>
                    item.tool_call_id
                        ? [item.event_type, item.capability_id, item.tool_call_id].join('|')
                        : [item.event_type, item.capability_id, item.phase].join('|'),
                ),
            );
            if (known.has(signature)) return message;
            return { ...message, steps: [...message.steps, step] };
        });
    }

    function addDiff(id, payload) {
        const row = diffRowFrom(payload);
        updateMessage(id, (message) => {
            const signature = JSON.stringify(row);
            if (
                message.diffRows.some(
                    (item) => JSON.stringify(item) === signature,
                )
            ) {
                return message;
            }
            return { ...message, diffRows: [...message.diffRows, row] };
        });
    }

    function setProposal(id, payload) {
        const proposal = normalizeProposal(payload);
        updateMessage(id, (message) => {
            const currentProposals = Array.isArray(message.proposals) ? message.proposals : [];
            const exists = currentProposals.some((p) => proposalMatches(p, proposal));
            const nextProposals = exists
                ? currentProposals.map((p) => proposalMatches(p, proposal) ? { ...p, ...proposal } : p)
                : [...currentProposals, proposal];
            return {
                ...message,
                proposal,
                proposals: nextProposals,
            };
        });
        updateProposalEverywhere(proposal);
        if (!avatarWorking) {
            setAvatar('thumbs-up', { lifecycle: true, mood: 'idle' });
            scheduleAvatarIdleGrace();
        }
    }

    function setModeRequest(id, payload) {
        const source = asObject(payload);
        const request = {
            mode: textValue(source.mode, 'assistant'),
            reason: textValue(
                source.reason,
                'This action requires an authenticated Assistant Mode boundary.',
            ),
        };
        updateMessage(id, (message) => ({ ...message, modeRequest: request }));
    }

    function applyDataPart(id, type, data) {
        if (type === 'data-xiam-avatar') {
            const source = asObject(data);
            const eventType = textValue(source.event_type, '');
            const emotion = textValue(source.emotion, 'idle');
            const extra = {};

            if (
                eventType === 'assistant.mood_cue' &&
                isXiamAvatarEmotion(emotion)
            ) {
                targetStreamMood = emotion;
                return;
            }
            if (eventType === 'tool.call' && typeof source.tool === 'string') {
                extra.toolStart = source.tool;
            } else if (
                eventType === 'tool.result' &&
                typeof source.tool === 'string'
            ) {
                extra.toolEnd = source.tool;
            }
            if (
                eventType === 'tool.call' ||
                eventType === 'tool.result' ||
                eventType === 'run.started'
            ) {
                extra.lifecycle = true;
                extra.mood = 'thinking';
            } else if (
                avatarWorking &&
                ['skill.proposal', 'run.completed'].includes(eventType)
            ) {
                return;
            }

            if (source.incident === true || eventType === 'incident.started') {
                extra.incident = true;
            } else if (
                source.resolveIncident === true ||
                eventType === 'incident.resolved' ||
                eventType === 'incident.closed'
            ) {
                extra.resolveIncident = true;
            }

            setAvatar(emotion, extra);
            return;
        }
        if (type === 'data-xiam-navigation') {
            updateMessage(id, (message) => ({
                ...message,
                navigation: normalizeXiamNavigation(asObject(data).navigation),
            }));
            return;
        }
        if (type === 'data-xiam-citation') {
            mergeCitations(id, asObject(data).citations);
            return;
        }
        if (type === 'data-xiam-steps') {
            const stepData = asObject(data);
            const stepLabel = textValue(stepData.label, '');
            if (stepLabel) {
                statusText = stepLabel;
            }
            addStep(id, stepData);
            return;
        }
        if (type === 'data-xiam-diff') {
            addDiff(id, data);
            return;
        }
        if (type === 'data-xiam-proposal') {
            setProposal(id, data);
            return;
        }
        if (type === 'data-xiam-mode-request') {
            setModeRequest(id, data);
        }
    }

    function applyStreamPart(part) {
        const targetId = streamMessageId || latestAssistantId;
        if (!targetId) return;
        ensureMessage(targetId);

        if (part.type === 'text-start') {
            streamMessageId = targetId;
            updateMessage(targetId, (message) => ({
                ...message,
                streaming: true,
                streamCompleted: false,
            }));
            statusText = 'Writing a reply…';
            return;
        }
        if (part.type === 'text-delta') {
            streamReceivedText = true;
            updateMessage(targetId, (message) => ({
                ...message,
                rawContent: `${message.rawContent ?? message.content}${textValue(part.delta)}`,
                streaming: true,
            }));
            queueTypewriter();
            statusText = 'Writing a reply…';
            return;
        }
        if (part.type === 'text-end') {
            updateMessage(targetId, (message) => ({
                ...message,
                streamCompleted: true,
            }));
            queueTypewriter();
            return;
        }
        if (part.type === 'done') {
            updateMessage(targetId, (message) => ({
                ...message,
                streamCompleted: true,
            }));
            queueTypewriter();
            return;
        }
        if (part.type.startsWith('data-xiam-')) {
            applyDataPart(targetId, part.type, part.data);
        }
    }

    function applyEvent(event, targetId, includeText = true) {
        const type = textValue(event.type);
        const payload = asObject(event.payload);
        if (type === 'run.started') {
            if (includeText)
                setAvatar('thinking', {
                    lifecycle: true,
                    resolveIncident: true,
                    mood: 'thinking',
                });
            return;
        }
        if (type === 'run.completed') {
            updateMessage(targetId, (message) => ({
                ...message,
                streamCompleted: true,
            }));
            queueTypewriter();
            return;
        }
        if (type === 'run.failed' || type === 'run.cancelled') {
            if (includeText) {
                targetStreamMood = 'sad';
                setAvatar('sad', { incident: true, lifecycle: true });
                scheduleAvatarIdleGrace(2800);
            }
            updateMessage(targetId, (message) => ({
                ...message,
                streaming: false,
            }));
            return;
        }
        if (type === 'assistant.delta' && includeText && !streamReceivedText) {
            updateMessage(targetId, (message) => ({
                ...message,
                rawContent: `${message.rawContent ?? message.content}${textValue(payload.text)}`,
                streaming: true,
            }));
            queueTypewriter();
            return;
        }
        if (type === 'assistant.message') {
            if (includeText && isXiamAvatarEmotion(payload.mood)) {
                targetStreamMood = payload.mood;
            }
            const nextContent = textValue(payload.content, '');
            updateMessage(targetId, (message) => ({
                ...message,
                rawContent:
                    nextContent || message.rawContent || message.content,
                citations: Array.isArray(payload.citations)
                    ? payload.citations.map(citationFrom)
                    : message.citations,
                navigation: includeText
                    ? normalizeXiamNavigation(payload.navigation)
                    : message.navigation,
                streamCompleted: true,
            }));
            queueTypewriter();
            return;
        }
        if (type === 'assistant.navigation') {
            if (!includeText) return;
            updateMessage(targetId, (message) => ({
                ...message,
                navigation: normalizeXiamNavigation(payload.navigation),
            }));
            return;
        }
        if (type === 'assistant.citations') {
            mergeCitations(targetId, payload.citations);
            return;
        }
        if (type === 'knowledge.retrieval') {
            const outcome = textValue(payload.outcome, 'unknown');
            const hits = Number(payload.hit_count ?? 0);
            updateMessage(targetId, (message) => ({
                ...message,
                corpusNote:
                    outcome === 'support' && hits > 0
                        ? `Corpus match: ${hits} approved source${hits === 1 ? '' : 's'}.`
                        : message.corpusNote,
            }));
            return;
        }
        if (type === 'assistant.refusal') {
            const refusalMode = textValue(payload.mode, '');
            if (refusalMode === 'conversational_orientation') {
                return;
            }

            const reason = textValue(payload.reason, '');
            const corpusNote =
                reason === 'protected_output_validation_unavailable'
                    ? 'Evidence was found, but generated answers are paused until privacy validation is available.'
                    : reason === 'no_support' ||
                        reason === 'no_grounded_support'
                      ? 'No approved policy citation supports this question.'
                      : 'The response was withheld by output safety checks.';
            updateMessage(targetId, (message) => ({
                ...message,
                corpusNote,
            }));
            setAvatar('sad');
            return;
        }
        if (type === 'skill.proposal') {
            setProposal(targetId, {
                ...payload,
                proposal_id: payload.proposal_id,
                capability_id: payload.capability_id,
                summary: payload.summary,
                expires_at: payload.expires_at,
                run_id: payload.run_id ?? event.run_id,
                proposal_view: payload.proposal_view,
                proposal_review: payload.proposal_review,
            });
            return;
        }
        if (
            type === 'skill.proposal_confirmed' ||
            type === 'skill.proposal_cancelled'
        ) {
            const proposalId = textValue(payload.proposal_id ?? payload.id, '');
            const runId = textValue(payload.run_id ?? event.run_id, '');
            const candidate = {
                id: proposalId,
                proposal_id: proposalId,
                run_id: runId,
            };
            const status =
                type === 'skill.proposal_confirmed' ? 'committed' : 'rejected';
            const existingProposal =
                (gate && proposalMatches(gate, candidate))
                    ? gate
                    : messages.find((message) =>
                          messageMatchesProposal(message, candidate),
                      )?.proposal;
            if (existingProposal) {
                updateProposalEverywhere({
                    ...existingProposal,
                    status,
                });
            }
            if (
                type === 'skill.proposal_cancelled' &&
                gate &&
                proposalMatches(gate, candidate)
            ) {
                gate = null;
            }
            return;
        }
        if (
            type === 'skill.plan' ||
            type === 'skill.tool' ||
            type === 'skill.result' ||
            type === 'tool.call' ||
            type === 'tool.result'
        ) {
            addStep(targetId, { ...payload, event_type: type });
            return;
        }
        if (type === 'assistant.diff') {
            addDiff(targetId, payload);
            return;
        }
        if (type === 'assistant.mode_request') {
            setModeRequest(targetId, payload);
        }
    }

    function applySnapshotEvents(events, targetId, includeText = true) {
        if (!Array.isArray(events)) return;
        for (const event of events) {
            const sequence = Number(event.seq ?? 0);
            if (sequence > 0 && seenEventSeq.has(sequence)) continue;
            if (sequence > 0) seenEventSeq.add(sequence);
            applyEvent(event, targetId, includeText);
        }
    }

    function snapshotAssistant(snapshot, runId) {
        const candidates = Array.isArray(snapshot?.messages)
            ? snapshot.messages.filter(
                  (message) => message.role === 'assistant',
              )
            : [];
        if (runId) {
            return (
                candidates.find((message) => message.run_id === runId) ?? null
            );
        }
        return candidates[candidates.length - 1] ?? null;
    }
    function hydrateMessages(snapshot) {
        const existing = new Map(
            messages.map((message) => [message.id, message]),
        );
        const next = Array.isArray(snapshot?.messages)
            ? snapshot.messages.map((message, index) => {
                  const id =
                      message.run_id ??
                      message.turn_id ??
                      `${message.role}-${index}`;
                  const old = existing.get(id);
                  return {
                      ...messageTemplate(
                          id,
                          message.role,
                          message.content ?? '',
                      ),
                      ...old,
                      run_id: message.run_id ?? old?.run_id ?? null,
                      content: textValue(message.content, old?.content ?? ''),
                      citations: Array.isArray(message.citations)
                          ? message.citations.map(citationFrom)
                          : (old?.citations ?? []),
                      navigation: Object.hasOwn(message, 'navigation')
                          ? normalizeXiamNavigation(message.navigation)
                          : (old?.navigation ?? []),
                      proposals: Array.isArray(message.proposals)
                          ? message.proposals.map(normalizeProposal)
                          : (message.proposal ? [normalizeProposal(message.proposal)] : []),
                      proposal: message.proposal
                          ? normalizeProposal(message.proposal)
                          : (old?.proposal ?? null),
                      streaming: false,
                  };
              })
            : [];
        if (next.length) {
            messages = next;
        } else {
            messages = [];
        }
    }


    let serverInteractionModes = $state(null);
    let modeContextEpoch = $state(session?.context_epoch ?? null);
    let modeRequestGeneration = 0;
    let latestTurnGeneration = 0;

    function applyUsageView(nextUsage) {
        if (!nextUsage || typeof nextUsage !== 'object') return;
        if (
            liveUsage &&
            Number(liveUsage.used) > 0 &&
            Number(nextUsage.used) === 0
        ) {
            const currentResetsAt = liveUsage.resets_at
                ? new Date(liveUsage.resets_at).getTime()
                : 0;
            const nextResetsAt = nextUsage.resets_at
                ? new Date(nextUsage.resets_at).getTime()
                : 0;
            if (nextResetsAt <= currentResetsAt || nextResetsAt <= Date.now()) {
                return;
            }
        }
        liveUsage = nextUsage;
        usageGeneration += 1;
    }

    function applyModeView(view, expectedUsageGeneration = usageGeneration) {
        if (!view || !['inquiry', 'assistant'].includes(view.active_mode))
            return;
        serverInteractionModes = Array.isArray(view.interaction_modes)
            ? view.interaction_modes.filter(
                  (item) =>
                      item &&
                      ['inquiry', 'assistant'].includes(item.key) &&
                      typeof item.label === 'string',
              )
            : [];
        modeContextEpoch = view.context_epoch ?? null;
        if (
            serverInteractionModes.some((item) => item.key === view.active_mode)
        ) {
            interaction = view.active_mode;
        } else {
            interaction = serverInteractionModes[0]?.key ?? 'inquiry';
        }
        if (usageGeneration === expectedUsageGeneration) {
            applyUsageView(view.usage);
        }
    }

    function preferenceScopeStamp() {
        return `${page.props?.auth?.user?.id ?? 'session'}|${page.url ?? ''}|${session?.page_pack?.key ?? ''}|${session?.context_epoch ?? ''}`;
    }

    function usageScopeStamp() {
        return `${page.props?.auth?.user?.id ?? 'session'}`;
    }

    async function applyAuthoritativeModeView(
        view,
        expectedUsageGeneration,
        generation,
        scopeStamp,
    ) {
        const previousEpoch = modeContextEpoch;
        applyModeView(view, expectedUsageGeneration);
        if (previousEpoch !== view.context_epoch) {
            const conversationId =
                loadedConversationId ?? session?.conversation?.id ?? null;
            if (conversationId) {
                await refreshProposalStatuses(
                    conversationId,
                    view.context_epoch,
                    generation,
                    scopeStamp,
                );
            }
        }
    }

    async function refreshModeView() {
        const generation = ++modeRequestGeneration;
        const scopeStamp = preferenceScopeStamp();
        const expectedUsageGeneration = usageGeneration;
        modeFeedback = '';
        preferenceLoading = true;
        try {
            const view = await fetchXiamMode();
            if (
                generation !== modeRequestGeneration ||
                scopeStamp !== preferenceScopeStamp()
            ) {
                return false;
            }
            await applyAuthoritativeModeView(
                view,
                expectedUsageGeneration,
                generation,
                scopeStamp,
            );
            modeError = '';
            return true;
        } catch {
            return false;
        } finally {
            if (generation === modeRequestGeneration) preferenceLoading = false;
        }
    }

    async function refreshProposalStatuses(
        conversationId,
        expectedEpoch,
        generation,
        scopeStamp,
    ) {
        try {
            const snapshot = await fetchXiamSnapshot(conversationId, 0);
            if (
                generation !== modeRequestGeneration ||
                scopeStamp !== preferenceScopeStamp() ||
                snapshot.context_epoch !== expectedEpoch
            ) {
                return;
            }
            for (const proposal of snapshot.proposals ?? []) {
                const proposalId = textValue(proposal?.id, '');
                if (!proposalId) continue;
                const existing =
                    gate?.id === proposalId
                        ? gate
                        : messages.find(
                              (message) => message.proposal?.id === proposalId,
                          )?.proposal;
                if (existing) {
                    updateProposalEverywhere({
                        ...existing,
                        status: normalizeProposalStatus(proposal.status),
                    });
                }
            }
        } catch {
            // Keep the existing proposal view if authoritative metadata is unavailable.
        }
    }

    async function setInteraction(key) {
        if (
            modePending ||
            preferenceLoading ||
            busy ||
            !allowedModes.includes(key) ||
            (key === 'assistant' && !assistantAllowed) ||
            interaction === key
        ) {
            return;
        }
        modePending = true;
        modeError = '';
        modeFeedback = '';
        const generation = ++modeRequestGeneration;
        const scopeStamp = preferenceScopeStamp();
        const expectedUsageGeneration = usageGeneration;
        try {
            const view = await setXiamMode(key, modeContextEpoch);
            if (
                generation !== modeRequestGeneration ||
                scopeStamp !== preferenceScopeStamp()
            ) {
                return;
            }
            await applyAuthoritativeModeView(
                view,
                expectedUsageGeneration,
                generation,
                scopeStamp,
            );
            if (view.active_mode !== key) {
                throw new Error('mode_ack_invalid');
            }
            sound.tick();
            const acknowledgedLabel =
                view.interaction_modes.find(
                    (option) => option.key === view.active_mode,
                )?.label ??
                (view.active_mode === 'assistant' ? 'Assistant' : 'Inquiry');
            modeFeedback = `${acknowledgedLabel} mode is active.`;
            toastInfo(modeFeedback);
        } catch (error) {
            if (scopeStamp !== preferenceScopeStamp()) return;
            const code =
                error instanceof Error ? error.message : 'mode_change_failed';
            const reconciled = await refreshModeView();
            if (reconciled && interaction === key) {
                modeError = '';
                modeFeedback = `${currentModeLabel} mode is active.`;
                toastInfo(modeFeedback);
            } else {
                const staleContext = [
                    'context_epoch_conflict',
                    'context_epoch_stale',
                    'stale_context_epoch',
                ].includes(code);
                modeError =
                    code === 'mode_unavailable'
                        ? 'That mode is unavailable for this session.'
                        : code === 'mode_change_busy'
                          ? 'Xiam is finishing an active run. Try switching when it is done.'
                          : staleContext
                            ? reconciled
                                ? 'This page context changed. Xiam refreshed its current mode; try again if needed.'
                                : 'This page context changed, but Xiam could not refresh. The last confirmed mode remains selected.'
                            : reconciled
                              ? 'Xiam could not confirm the mode change. The latest server mode is shown.'
                              : 'Xiam could not confirm the mode change or refresh server state. The last confirmed mode remains selected.';
                toastError(modeError);
            }
        } finally {
            modePending = false;
        }
    }

    async function loadServerHistory(conversationId) {
        if (!conversationId || conversationId === clearedSessionConvId) return;
        const requestEpoch = ++historyRequestEpoch;
        const expectedUsageGeneration = usageGeneration;
        const scopeStamp = preferenceScopeStamp();
        historyStatus = 'loading';
        try {
            const snapshot = await fetchXiamSnapshot(conversationId, 0);
            if (
                requestEpoch !== historyRequestEpoch ||
                conversationId === clearedSessionConvId
            ) {
                return;
            }
            if (scopeStamp !== preferenceScopeStamp()) {
                historyStatus = 'error';
                return;
            }
            loadedConversationId = conversationId;
            currentCursor = Number(snapshot.cursor ?? 0);
            seenEventSeq = new Set();
            if (usageGeneration === expectedUsageGeneration) {
                applyUsageView(snapshot.usage);
            }
            hydrateMessages(snapshot);
            historyStatus = 'loaded';
            if (
                Array.isArray(snapshot.events) &&
                snapshot.events.length > 0 &&
                messages.length > 0
            ) {
                const targetId =
                    latestAssistantId ?? `history-${conversationId}`;
                ensureMessage(targetId);
                applySnapshotEvents(snapshot.events, targetId, false);
            }
            for (const snapProposal of snapshot.proposals ?? []) {
                const propId = textValue(
                    snapProposal?.proposal_id ?? snapProposal?.id,
                    '',
                );
                const runId = textValue(snapProposal?.run_id, '');
                if (!propId && !runId) continue;
                const candidate = {
                    id: propId,
                    proposal_id: propId,
                    run_id: runId,
                };
                const resolvedStatus = normalizeProposalStatus(
                    snapProposal?.status,
                );
                const existing =
                    (gate && proposalMatches(gate, candidate))
                        ? gate
                        : messages.find((message) =>
                              messageMatchesProposal(message, candidate),
                          )?.proposal;
                if (existing) {
                    updateProposalEverywhere({
                        ...existing,
                        ...snapProposal,
                        status: resolvedStatus,
                    });
                } else if (['pending', 'proposed'].includes(resolvedStatus)) {
                    const owner = messages.find(
                        (message) =>
                            runId &&
                            (message.id === runId || message.run_id === runId),
                    );
                    if (owner) {
                        setProposal(owner.id, snapProposal);
                    }
                }
            }
            const openProposal = (session?.proposals ?? []).find((proposal) =>
                ['proposed', 'pending'].includes(proposal?.status),
            );
            const proposalAlreadyResolved =
                openProposal &&
                ((snapshot.proposals ?? []).some(
                    (proposal) =>
                        proposalMatches(openProposal, proposal) &&
                        !['pending', 'proposed'].includes(proposal?.status),
                ) ||
                    (snapshot.events ?? []).some(
                        (event) =>
                            [
                                'skill.proposal_confirmed',
                                'skill.proposal_cancelled',
                            ].includes(event?.type) &&
                            proposalMatches(openProposal, {
                                id: event?.payload?.proposal_id,
                                proposal_id: event?.payload?.proposal_id,
                                run_id: event?.run_id,
                            }),
                    ));
            if (openProposal && !proposalAlreadyResolved && !gate) {
                updateProposalEverywhere(normalizeProposal(openProposal));
                setAvatar('thumbs-up');
                scheduleAvatarIdleGrace(2800);
            } else {
                setAvatar('idle', { resolveIncident: true, mood: 'idle' });
            }
            await scrollToBottom();
        } catch {
            if (
                requestEpoch !== historyRequestEpoch ||
                scopeStamp !== preferenceScopeStamp()
            ) {
                return;
            }
            historyStatus = 'error';
            if (!messages.length) messages = [];
        }
    }

    function updateProposalEverywhere(proposal) {
        const actionable = ['proposed', 'confirming', 'executing'].includes(
            proposal?.status,
        );
        const matchesGate = gate && proposalMatches(gate, proposal);
        if (actionable) {
            gate = proposal;
        } else if (matchesGate) {
            gate = null;
        }
        messages = messages.map((message) => {
            if (!messageMatchesProposal(message, proposal)) return message;
            const existingProposals = Array.isArray(message.proposals) && message.proposals.length > 0
                ? message.proposals
                : (message.proposal ? [message.proposal] : []);

            const updatedProposals = existingProposals.map((p) =>
                proposalMatches(p, proposal) ? { ...p, ...proposal } : p
            );

            return {
                ...message,
                proposal: proposalMatches(message.proposal, proposal)
                    ? { ...(message.proposal ?? {}), ...proposal }
                    : (message.proposal ?? proposal),
                proposals: updatedProposals,
            };
        });
    }


    async function settleServerTurn(
        accepted,
        targetId,
        turnGeneration,
        turnUsageScopeStamp,
    ) {
        let snapshot = null;
        for (let attempt = 0; attempt < 30; attempt += 1) {
            snapshot = await fetchXiamSnapshot(
                accepted.conversation_id,
                accepted.cursor ?? currentCursor,
            );
            currentCursor = Number(snapshot.cursor ?? currentCursor);
            if (
                turnGeneration === latestTurnGeneration &&
                turnUsageScopeStamp === usageScopeStamp() &&
                snapshot?.conversation_id === accepted.conversation_id &&
                snapshot?.run?.id === accepted.run_id
            ) {
                applyUsageView(snapshot.usage);
                if (typeof snapshot.context_epoch === 'string') {
                    modeContextEpoch = snapshot.context_epoch;
                }
            }
            applySnapshotEvents(snapshot.events, targetId, true);
            const assistant = snapshotAssistant(snapshot, accepted.run_id);
            const terminal = ['succeeded', 'failed', 'cancelled'].includes(
                snapshot.run?.status,
            );
            if (assistant?.content || terminal) break;
            await new Promise((resolve) => setTimeout(resolve, 120));
        }
        return snapshot;
    }

    async function send() {
        const text = input.trim();
        if (
            !text ||
            busy ||
            modePending ||
            preferenceLoading ||
            !allowedModes.includes(interaction)
        )
            return;
        if (
            liveUsage?.state === 'exhausted' ||
            (session?.availability === 'quota_exhausted' && !liveUsage)
        ) {
            void refreshModeView();
            return;
        }
        const turnGeneration = ++latestTurnGeneration;
        const turnPreferenceStamp = preferenceScopeStamp();
        const turnUsageScopeStamp = usageScopeStamp();
        modeFeedback = '';
        input = '';
        messages = [
            ...messages,
            {
                ...messageTemplate(
                    `user-${Date.now()}-${messages.length}`,
                    'user',
                    text,
                ),
            },
        ];
        followingEnd = true;
        void scrollToBottom(true);
        if (avatarGraceTimer) {
            clearTimeout(avatarGraceTimer);
            avatarGraceTimer = null;
        }
        targetStreamMood = null;
        setAvatar('thinking', {
            lifecycle: true,
            resolveIncident: true,
            mood: 'thinking',
        });
        sound.press();

        if (!session) {
            busy = true;
            statusText = 'Thinking…';
            const targetId = `sim-${Date.now()}`;
            messages = [
                ...messages,
                {
                    ...messageTemplate(targetId, 'assistant'),
                    streaming: true,
                    streamCompleted: false,
                    steps: [
                        {
                            label: 'query_workspace_context',
                            detail: `Scope: ${contextTitle}`,
                            ok: true,
                        },
                        {
                            label: 'evaluate_surface_policy',
                            detail: 'Degraded preview mode active',
                            ok: true,
                        },
                    ],
                },
            ];
            await new Promise((resolve) => setTimeout(resolve, 500));
            const reply =
                interaction === 'inquiry'
                    ? `Inquiry on “${contextTitle}” — I cite approved Knowledge sources when they support the answer.`
                    : `I’m with you on “${contextTitle}”. Use the page controls when you are ready to act.`;
            updateMessage(targetId, (m) => ({
                ...m,
                rawContent: reply,
                corpusNote:
                    'Grounding and action boundaries are supplied by the server session.',
                streamCompleted: true,
            }));
            queueTypewriter();
            busy = false;
            statusText = null;
            return;
        }

        busy = true;
        statusText = 'Thinking…';
        setAvatar('thinking', {
            lifecycle: true,
            resolveIncident: true,
            mood: 'thinking',
        });
        streamReceivedText = false;
        seenEventSeq = new Set();
        streamMessageId = `run-${Date.now()}`;
        const targetId = streamMessageId;
        messages = [
            ...messages,
            {
                ...messageTemplate(targetId, 'assistant'),
                streaming: true,
                streamCompleted: false,
                steps: [],
            },
        ];
        try {
            await prepareXiamThinkingArtwork();
            const accepted = await submitXiamTurn({
                message: text,
                pagePackKey: session.page_pack?.key ?? null,
                pagePackVersion: session.page_pack?.version ?? null,
            });
            if (
                turnGeneration === latestTurnGeneration &&
                turnUsageScopeStamp === usageScopeStamp()
            ) {
                applyUsageView(accepted.usage);
            }
            if (
                turnPreferenceStamp === preferenceScopeStamp() &&
                typeof accepted.context_epoch === 'string'
            ) {
                modeContextEpoch = accepted.context_epoch;
            }
            loadedConversationId = accepted.conversation_id;
            currentCursor = Number(accepted.cursor ?? currentCursor);
            abortController = new AbortController();
            statusText = 'Consulting institutional workspace…';
            let streamError = null;
            try {
                await streamXiamConversation(
                    accepted.conversation_id,
                    Number(accepted.cursor ?? currentCursor),
                    applyStreamPart,
                    abortController.signal,
                );
            } catch (error) {
                streamError = error;
            }

            statusText = 'Finishing up…';
            const snapshot = await settleServerTurn(
                accepted,
                targetId,
                turnGeneration,
                turnUsageScopeStamp,
            );
            const assistant = snapshotAssistant(snapshot, accepted.run_id);
            if (assistant) {
                const finalContent = textValue(assistant.content, '');
                updateMessage(targetId, (message) => ({
                    ...message,
                    rawContent:
                        finalContent || message.rawContent || message.content,
                    citations: Array.isArray(assistant.citations)
                        ? assistant.citations.map(citationFrom)
                        : message.citations,
                    streamCompleted: true,
                }));
                queueTypewriter();
            }
            if (
                ['failed', 'cancelled'].includes(snapshot?.run?.status) &&
                !assistant?.content
            ) {
                const failureReason = snapshot?.run?.failure_reason;
                let failureContent = 'That run did not complete. Use the ordinary page controls, or try again.';
                if (failureReason === 'provider_insufficient_credits') {
                    failureContent = 'The AI provider account has insufficient credits (HTTP 402). Please add credits to your provider balance or switch to a free model in AI Control Center.';
                } else if (failureReason === 'provider_unavailable') {
                    failureContent = 'The AI provider is currently overloaded or unavailable. Please try again in a moment or switch models in AI Control Center.';
                } else if (failureReason === 'provider_timeout' || failureReason === 'run_deadline_exhausted') {
                    failureContent = 'The assistant operation took longer than the allocated deadline. Please try again.';
                }
                updateMessage(targetId, (message) => ({
                    ...message,
                    content: failureContent,
                    corpusNote: null,
                    streaming: false,
                }));
            }
        } catch (error) {
            console.error('Xiam turn failed:', error);
            const code = error instanceof Error ? error.message : 'turn_failed';
            if (
                turnGeneration === latestTurnGeneration &&
                turnUsageScopeStamp === usageScopeStamp() &&
                error?.usage
            ) {
                applyUsageView(error.usage);
            }
            if (
                code === 'quota_exhausted' &&
                turnGeneration === latestTurnGeneration &&
                turnUsageScopeStamp === usageScopeStamp() &&
                !error?.usage
            ) {
                void refreshModeView();
            }
            targetStreamMood = code === 'emergency_stop' ? 'sad' : 'shock';
            let content =
                'I could not send that turn. Use the ordinary page controls, or try again.';
            if (code === 'quota_exhausted') {
                content =
                    'You have reached the Xiam ask limit. Use the ordinary page controls or try again later.';
            } else if (
                code === 'csrf_expired' ||
                code.includes('CSRF') ||
                code.includes('Page Expired')
            ) {
                content =
                    'Your session security token expired. Please refresh the page to continue.';
            } else if (code === 'surface_denied') {
                content =
                    'This page is not authorized for assistant commands. Use the ordinary page controls.';
            }
            updateMessage(targetId, (message) => ({
                ...message,
                content,
                streaming: false,
            }));
            setAvatar(code === 'emergency_stop' ? 'sad' : 'shock', {
                lifecycle: true,
            });
            scheduleAvatarIdleGrace(2800);
        } finally {
            abortController = null;
            busy = false;
            statusText = null;
            streamMessageId = null;
            finishAvatarTurn();
        }
    }

    async function confirmGate(proposal = gate, options = {}) {
        if (!proposal || busy || proposal.status === 'committed') return;
        const proposalId =
            proposal.proposal_id && proposal.proposal_id !== 'pending'
                ? proposal.proposal_id
                : proposal.id === 'pending'
                  ? null
                  : proposal.id;
        if (!proposalId) {
            updateProposalEverywhere({
                ...proposal,
                status: 'failed',
                error: 'No durable proposal id was attached; use the page action.',
            });
            return;
        }
        sound.press();
        busy = true;
        statusText = 'Saving changes…';
        updateProposalEverywhere({ ...proposal, status: 'confirming' });
        try {
            const confirmed = await confirmXiamProposal(proposalId, {
                phrase: options.phrase ?? '',
            });
            if (
                confirmed.ok !== true ||
                normalizeProposalStatus(confirmed.proposal?.status) !==
                    'committed'
            ) {
                throw new Error('confirm_failed');
            }
            updateProposalEverywhere({
                ...proposal,
                status: 'committed',
            });
            setAvatar('celebrating', {
                lifecycle: true,
                resolveIncident: true,
                mood: 'idle',
            });
            scheduleAvatarIdleGrace(2800);
            toastSuccess(
                getXiamActionPresentation(proposal?.capability, proposal?.title)
                    .successMessage,
                { dedupeKey: proposalId },
            );
        } catch (error) {
            updateProposalEverywhere({
                ...proposal,
                status: 'failed',
                error:
                    error instanceof Error &&
                    error.message === 'reauth_required'
                        ? 'Please sign in again before confirming this action.'
                        : error instanceof Error
                          ? error.message
                          : 'confirm_failed',
            });
            setAvatar('sad', { incident: true, lifecycle: true });
            scheduleAvatarIdleGrace(2800);
        } finally {
            busy = false;
            statusText = null;
        }
    }

    async function rejectGate(proposal = gate) {
        if (!proposal || busy || proposal.status === 'committed') return;
        sound.pop();
        updateProposalEverywhere({ ...proposal, status: 'rejected' });
        if (proposal.id !== 'pending') {
            try {
                await cancelXiamProposal(proposal.id);
            } catch {
                // The visual state remains rejected; server cancellation is best effort.
            }
        }
        setAvatar('idle');
    }

    async function clearChat() {
        if (busy || modePending) return;
        busy = true;
        modeFeedback = '';
        historyRequestEpoch += 1;
        try {
            const result = await clearXiamConversation();
            historyRequestEpoch += 1;
            clearedSessionConvId = session?.conversation?.id ?? null;
            loadedConversationId = result.conversation_id ?? null;
            if (typeof result.context_epoch === 'string') {
                modeContextEpoch = result.context_epoch;
            }
            currentCursor = 0;
            seenEventSeq = new Set();
            gate = null;
            citationOpen = null;
            messages = [];
            clearedRowIds = new Set();
            rowRefs = {};
            followingEnd = true;
        } catch {
            speak(
                'I could not clear the conversation. Use the ordinary page controls, or try again.',
            );
        } finally {
            busy = false;
            statusText = null;
            if (avatarGraceTimer) {
                clearTimeout(avatarGraceTimer);
                avatarGraceTimer = null;
            }
            setAvatar('idle', {
                lifecycle: true,
                resolveIncident: true,
                mood: 'idle',
            });
        }
    }

    function usePrompt(prompt) {
        input = prompt;
    }

    function openCitation(citation) {
        sound.pop();
        citationOpen = citation;
    }

    function onKeydown(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            void send();
        }
    }

    async function scrollToBottom(force = false) {
        if (!force && !followingEnd) return;
        await tick();
        requestAnimationFrame(() => {
            if (scrollEl) {
                scrollEl.scrollTop = scrollEl.scrollHeight;
                updateRowClearance();
            }
        });
    }
    function minimize() {
        if (busy || modePending) return;
        sound.tick();
        userInitiatedOpen = false;
        expanded = false;
        if (detailsRoot) detailsRoot.open = false;
    }

    function handleDetailsToggle(event) {
        expanded = event.currentTarget.open;
        if (expanded) {
            if (userInitiatedOpen) {
                userInitiatedOpen = false;
                const isBusyOrTyping = busy || Boolean(typewriterInterval);
                const hasIncidentOrTool = Boolean(
                    gate ||
                    [
                        'sad',
                        'shock',
                        'angry',
                        'searching-paper',
                        'searching-phone',
                    ].includes(avatarEvent?.emotion),
                );
                if (!isBusyOrTyping && !hasIncidentOrTool) {
                    setAvatar('hi', { mood: 'idle' });
                }
            }
        } else {
            userInitiatedOpen = false;
        }
    }
    function handleWindowClick(event) {
        if (
            capsuleExpanded &&
            !event.target.closest?.('[data-xiam-capsule-root]') &&
            !launcherSummary?.contains(event.target)
        ) {
            capsuleExpanded = false;
        }
    }
    $effect(() => {
        if (hydrated) return;
        hydrated = true;
        try {
            expanded = sessionStorage.getItem(STORAGE_KEY) === '1';
        } catch {
            expanded = false;
        }
        interaction = session?.active_mode || defaultInteraction || 'inquiry';
        if (!allowedModes.includes(interaction)) {
            interaction = allowedModes[0] || 'inquiry';
        }
        serverInteractionModes = Array.isArray(session?.interaction_modes)
            ? session.interaction_modes
            : null;
        modeContextEpoch = session?.context_epoch ?? null;
        if (session?.usage) liveUsage = session.usage;
        historyStatus = session?.conversation?.id ? 'loading' : 'loaded';
        currentCursor = Number(session?.conversation?.cursor ?? 0);
    });
    $effect(() => {
        if (!hydrated || !detailsRoot) return;
        detailsRoot.open = expanded;
    });

    $effect(() => {
        const conversationId = session?.conversation?.id ?? null;
        if (!hydrated) return;
        if (!conversationId) {
            if (historyStatus === 'idle') historyStatus = 'loaded';
            return;
        }
        untrack(() => {
            if (
                conversationId === loadedConversationId ||
                conversationId === clearedSessionConvId
            ) {
                return;
            }
            gate = null;
            void loadServerHistory(conversationId);
        });
    });
    $effect(() => {
        if (!hydrated) return;
        try {
            sessionStorage.setItem(STORAGE_KEY, expanded ? '1' : '0');
        } catch {
            // Expansion is a local presentation preference only.
        }
    });

    $effect(() => {
        const isOpen = expanded;
        preferenceScopeStamp();
        if (!hydrated || !isOpen) return;
        untrack(() => {
            void refreshModeView();
        });
    });

    $effect(() => {
        const incomingUsage = session?.usage;
        const incomingMode = session?.active_mode;
        const incomingEpoch = session?.context_epoch;
        const incomingModes = session?.interaction_modes;
        if (!hydrated) return;
        untrack(() => {
            if (incomingUsage) applyUsageView(incomingUsage);
            if (incomingEpoch !== undefined) modeContextEpoch = incomingEpoch;
            if (Array.isArray(incomingModes))
                serverInteractionModes = incomingModes;
            if (
                incomingMode &&
                !modePending &&
                allowedModes.includes(incomingMode)
            ) {
                interaction = incomingMode;
            }
        });
    });

    $effect(() => {
        const resetAt = liveUsage?.resets_at;
        if (!resetAt) return;
        const delay = new Date(resetAt).getTime() - Date.now();
        if (!Number.isFinite(delay) || delay < 0) {
            if (lastResetRefresh !== resetAt) {
                lastResetRefresh = resetAt;
                void refreshModeView();
            }
            return;
        }
        const timer = setTimeout(
            () => {
                lastResetRefresh = resetAt;
                void refreshModeView();
            },
            Math.min(delay + 50, 2_147_000_000),
        );
        return () => clearTimeout(timer);
    });

    $effect(() => {
        messages;
        statusText;
        expanded;
        if (expanded) {
            void scrollToBottom();
            void tick().then(updateRowClearance);
        }
    });
</script>

<svelte:window bind:innerWidth onresize={handleViewportChange} onclick={handleWindowClick} />

{#if enabled && mode !== 'disabled'}
    <details
        bind:this={detailsRoot}
        class="z-50 {!isStaff && !expanded ? 'hidden md:block' : ''}"
        style="position: fixed; left: {launcherLeft}px; top: {launcherTop}px; right: auto; bottom: auto; transform: none; transition: {isDragging ? 'none' : 'left 0.22s cubic-bezier(0.16, 1, 0.3, 1), top 0.22s cubic-bezier(0.16, 1, 0.3, 1)'};"
        data-xiam-assist-root
        ontoggle={handleDetailsToggle}
    >
        <summary
            bind:this={launcherSummary}
            class={`border-primary/30 bg-card hover:border-primary/50 focus:ring-primary/40 flex size-12 cursor-pointer list-none items-center justify-center rounded-full border shadow-xl transition-colors focus:ring-2 focus:outline-none [&::-webkit-details-marker]:hidden ${!isStaff && capsuleExpanded ? 'opacity-0' : ''} ${!isStaff ? 'hidden md:flex' : ''}`}
            style="touch-action: none; user-select: none;"
            aria-label={isStaff ? 'Open Xiam' : 'Campus Tools & AI Assistant'}
            title={isStaff ? 'Open Xiam' : 'Campus Tools & AI Assistant'}
            onpointerdown={launcherDrag.pointerdown}
            onpointermove={launcherDrag.pointermove}
            onpointerup={launcherDrag.pointerup}
            onpointercancel={launcherDrag.pointercancel}
            onclick={(event) => {
                if (launcherDrag.clickInterceptor(event)) return;
                if (isStaff) {
                    event.preventDefault();
                    if (!detailsRoot?.open) userInitiatedOpen = true;
                    if (detailsRoot) detailsRoot.open = !detailsRoot.open;
                    sound.pop();
                    return;
                }

                if (detailsRoot?.open) return;
                event.preventDefault();
                capsuleExpanded = !capsuleExpanded;
                sound.pop();
            }}
        >
            <span
                class="bg-primary/10 text-primary relative flex size-9 items-center justify-center rounded-full"
            >
                {#if isStaff}
                    <Sparkles class="size-4" />
                    <span
                        class="ring-card absolute -top-0.5 -right-0.5 size-2 rounded-full bg-emerald-500 ring-2"
                    ></span>
                {:else}
                    <HelpCircle class="size-4" />
                    <span
                        class="ring-card absolute -top-0.5 -right-0.5 size-2 rounded-full bg-primary ring-2"
                    ></span>
                {/if}
            </span>
        </summary>

        <div class="pointer-events-none fixed inset-0 z-[60]">
            <div
                bind:this={dockRoot}
                class="xiam-dock border-border/80 bg-card ring-border/50 pointer-events-auto absolute inset-x-3 bottom-3 flex h-[min(690px,calc(100dvh-1.5rem))] max-h-[calc(100dvh-1.5rem)] flex-col overflow-hidden rounded-[1.25rem] border text-xs shadow-2xl ring-1 sm:inset-x-auto sm:right-6 sm:bottom-6 sm:h-[min(700px,calc(100dvh-2rem))] sm:w-[500px]"
                data-xiam-assist-root
                role="dialog"
                aria-modal="false"
                aria-labelledby="xiam-workspace-title"
                tabindex="-1"
                onkeydown={(event) => event.key === 'Escape' && minimize()}
            >
                <header
                    class="border-border/70 bg-card/95 shrink-0 border-b px-3.5 py-2.5"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-3"
                    >
                        <div class="flex min-w-0 items-center gap-2.5">
                            <div
                                class="border-primary/20 bg-primary/10 text-primary flex size-6 shrink-0 items-center justify-center rounded-lg border"
                            >
                                <Sparkles class="size-3.5" />
                            </div>
                            <div class="min-w-0">
                                <h2
                                    id="xiam-workspace-title"
                                    class="text-foreground text-xs font-bold"
                                >
                                    Xiam
                                </h2>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 sm:gap-3">
                            <div
                                class="border-border bg-card flex items-center gap-1 rounded-full border p-0.5 font-mono text-[10px] font-bold shadow-xs"
                            >
                                <button
                                    type="button"
                                    class={`flex items-center gap-1 rounded-full px-2.5 py-0.5 transition-all disabled:cursor-wait disabled:opacity-50 ${interaction === 'inquiry' ? 'border border-blue-500/30 bg-blue-500/15 text-blue-600 dark:text-blue-400' : 'text-muted-foreground hover:text-foreground'}`}
                                    aria-pressed={interaction === 'inquiry'}
                                    disabled={!inquiryAllowed ||
                                        busy ||
                                        modePending ||
                                        preferenceLoading}
                                    onclick={() =>
                                        void setInteraction('inquiry')}
                                >
                                    <span
                                        class="size-1.5 rounded-full bg-blue-500"
                                    ></span>
                                    Inquiry
                                </button>
                                <button
                                    type="button"
                                    class={`flex items-center gap-1 rounded-full px-2.5 py-0.5 transition-all disabled:cursor-not-allowed disabled:opacity-40 ${interaction === 'assistant' ? 'border border-emerald-500/30 bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground hover:text-foreground'}`}
                                    aria-pressed={interaction === 'assistant'}
                                    disabled={!assistantAllowed ||
                                        busy ||
                                        modePending ||
                                        preferenceLoading}
                                    onclick={() =>
                                        void setInteraction('assistant')}
                                >
                                    <span
                                        class="size-1.5 rounded-full bg-emerald-500"
                                    ></span>
                                    Assistant
                                </button>
                            </div>
                            <button
                                bind:this={usageButtonEl}
                                type="button"
                                class="hover:bg-muted/80 text-muted-foreground hover:text-foreground border-border/80 bg-muted/40 relative inline-flex min-h-7 items-center gap-1.5 rounded-full border px-2.5 py-1 font-mono text-[10px] font-semibold tabular-nums transition before:absolute before:-inset-1 before:content-['']"
                                title={usageTitle}
                                aria-label="View usage details"
                                onclick={() => (usageDetailsOpen = true)}
                            >
                                <span
                                    class={`size-1.5 rounded-full ${liveUsage?.state === 'exhausted' ? 'bg-rose-500' : liveUsage?.state === 'near_limit' ? 'bg-amber-500' : 'bg-emerald-500'}`}
                                ></span>
                                <span>{usageLabel}</span>
                            </button>
                            <button
                                type="button"
                                class="text-muted-foreground hover:bg-muted hover:text-foreground rounded-md p-1.5 transition disabled:opacity-40"
                                title="Clear conversation"
                                aria-label="Clear conversation"
                                disabled={busy || modePending}
                                onclick={() => void clearChat()}
                            >
                                <RotateCcw class="size-3.5" />
                            </button>
                            <button
                                type="button"
                                class="text-muted-foreground hover:bg-muted hover:text-foreground rounded-md p-1.5 transition disabled:opacity-40"
                                title="Close Xiam"
                                aria-label="Close Xiam"
                                disabled={busy || modePending}
                                onclick={minimize}
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                    </div>
                </header>

                <main
                    class="relative flex min-h-0 flex-1 flex-col overflow-hidden"
                >
                    <div class="relative flex min-h-0 flex-1 flex-col">
                        <section class="min-h-0 flex-1">
                            <div
                                class="bg-card relative flex h-full min-h-0 flex-col overflow-hidden"
                            >
                                <div
                                    bind:this={scrollEl}
                                    onscroll={handleScroll}
                                    class="relative min-h-0 flex-1 [scrollbar-gutter:stable] space-y-3 overflow-y-auto overscroll-contain bg-[radial-gradient(ellipse_65%_45%_at_12%_100%,oklch(0.88_0.06_145_/_0.20),transparent_55%)] p-3 pb-4 [overflow-anchor:none] sm:p-5 sm:pb-6 dark:bg-[radial-gradient(ellipse_65%_45%_at_12%_100%,oklch(0.35_0.05_145_/_0.15),transparent_55%)]"
                                >
                                    {#if session?.availability === 'suspended'}
                                        <div
                                            class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-center text-xs text-amber-700 dark:text-amber-300"
                                        >
                                            Hi — I’m Xiam. Assistant runs are
                                            currently paused; use the page
                                            controls directly.
                                        </div>
                                    {:else if liveUsage?.state === 'exhausted' || (session?.availability === 'quota_exhausted' && !liveUsage)}
                                        <div
                                            class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-center text-xs text-amber-800 dark:text-amber-200"
                                            role="status"
                                        >
                                            Your Xiam allowance is exhausted for
                                            this period. Ordinary page controls
                                            remain available.
                                        </div>
                                    {:else if messages.length === 0}
                                        <div
                                            class="border-border text-muted-foreground rounded-xl border border-dashed p-4 text-center text-xs"
                                        >
                                            Ask Xiam about this page to start a
                                            grounded turn.
                                        </div>
                                        {#if historyStatus === 'loaded'}
                                            <aside
                                                class="text-foreground w-full rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 text-left text-xs leading-relaxed"
                                                aria-label="Xiam beta notice"
                                            >
                                                <p class="font-semibold">
                                                    Xiam is in beta
                                                </p>
                                                <p
                                                    class="text-muted-foreground mt-1"
                                                >
                                                    Responses may be inaccurate
                                                    or interrupted. Verify
                                                    important details, review
                                                    proposals before confirming,
                                                    and avoid sharing sensitive
                                                    information.
                                                </p>
                                            </aside>
                                        {/if}
                                    {/if}

                                    {#each messages as message (message.id)}
                                        {@const isCleared = clearedRowIds.has(
                                            message.id,
                                        )}
                                        {#if message.role === 'user'}
                                            <div
                                                bind:this={rowRefs[message.id]}
                                                class={`flex justify-end transition-[max-width,margin] duration-200 ease-out motion-reduce:transition-none ${isCleared ? 'ml-auto max-w-[88%]' : 'ml-auto max-w-[calc(100%-128px)] sm:max-w-[calc(100%-148px)]'}`}
                                            >
                                                <div
                                                    class="bg-primary text-primary-foreground w-full space-y-1 rounded-2xl rounded-tr-none p-3 shadow-sm"
                                                >
                                                    <p
                                                        class="text-xs leading-relaxed font-medium whitespace-pre-wrap"
                                                    >
                                                        {message.content}
                                                    </p>
                                                </div>
                                            </div>
                                        {:else}
                                            <div
                                                bind:this={rowRefs[message.id]}
                                                class={`space-y-2 transition-[max-width,margin] duration-200 ease-out motion-reduce:transition-none ${isCleared ? 'ml-0 max-w-full' : 'ml-[128px] max-w-[calc(100%-128px)] sm:ml-[148px] sm:max-w-[calc(100%-148px)]'}`}
                                            >
                                                {#if message.streaming || (message.steps && message.steps.length > 0)}
                                                    <XiamThinkingCard
                                                        steps={message.steps ??
                                                            []}
                                                        streaming={message.streaming}
                                                    />
                                                {/if}
                                                {#if message.content || (message.rawContent && !message.streaming) || message.navigation?.length}
                                                    <div
                                                        class="border-border/80 bg-card space-y-2 rounded-2xl rounded-tl-none border p-3.5 shadow-xs"
                                                    >
                                                        {#if message.content}
                                                            <p
                                                                class="text-foreground text-xs leading-relaxed whitespace-pre-wrap"
                                                            >
                                                                {@html formatMessageMarkdown(
                                                                    message.content,
                                                                )}{#if message.streaming}<span
                                                                        class="bg-primary ml-0.5 inline-block h-3.5 w-1.5 animate-pulse rounded-xs align-middle"

                                                                    ></span>{/if}
                                                            </p>
                                                        {/if}
                                                        {#if message.navigation?.length}
                                                            <nav
                                                                aria-label="Suggested destinations"
                                                                class="flex flex-wrap gap-2 pt-1"
                                                            >
                                                                {#each message.navigation as destination (destination.id)}
                                                                    <Link
                                                                        href={destination.href}
                                                                        class="inline-flex max-w-full"
                                                                    >
                                                                        <Button
                                                                            type="button"
                                                                            variant="outline"
                                                                            class="min-h-10 max-w-full gap-1.5 text-xs"
                                                                        >
                                                                            <span
                                                                                class="truncate"
                                                                                >Visit
                                                                                {destination.label}</span
                                                                            >
                                                                        </Button>
                                                                    </Link>
                                                                {/each}
                                                            </nav>
                                                        {/if}
                                                        {#if message.corpusNote}
                                                            <p
                                                                class="border-border/50 text-muted-foreground border-t pt-1.5 font-mono text-[10px]"
                                                            >
                                                                {message.corpusNote}
                                                            </p>
                                                        {/if}
                                                        {#if message.citations.length}
                                                            <div
                                                                class="flex flex-wrap items-center gap-1.5 pt-1"
                                                            >
                                                                {#each message.citations as citation}
                                                                    <button
                                                                        type="button"
                                                                        class="bg-primary/10 text-primary hover:bg-primary/20 inline-flex max-w-full items-center gap-1 rounded px-1.5 py-0.5 text-left font-mono text-[9px] font-bold transition"
                                                                        onclick={() =>
                                                                            openCitation(
                                                                                citation,
                                                                            )}
                                                                    >
                                                                        <Database
                                                                            class="size-3 shrink-0"
                                                                        />
                                                                        <span
                                                                            class="truncate"
                                                                            >[{citation.source_title}]</span
                                                                        >
                                                                    </button>
                                                                {/each}
                                                            </div>
                                                        {/if}
                                                    </div>
                                                {/if}

                                                {#if message.diffRows.length}
                                                    <div
                                                        class="border-border bg-card overflow-x-auto rounded-xl border p-3 shadow-xs"
                                                    >
                                                        <div
                                                            class="mb-2 flex items-center gap-1.5 font-mono text-[10px] font-bold tracking-wider text-indigo-500 uppercase"
                                                        >
                                                            <Table2
                                                                class="size-3"
                                                            />
                                                            Visual tabular diff
                                                        </div>
                                                        <table
                                                            class="w-full min-w-[420px] text-left font-mono text-[10px]"
                                                        >
                                                            <thead
                                                                class="bg-muted/30 text-muted-foreground text-[9px]"
                                                            >
                                                                <tr>
                                                                    <th
                                                                        class="p-1.5 pl-2"
                                                                        >Target
                                                                        entity</th
                                                                    >
                                                                    <th
                                                                        class="p-1.5"
                                                                        >Current</th
                                                                    >
                                                                    <th
                                                                        class="p-1.5"
                                                                        >Proposed</th
                                                                    >
                                                                    <th
                                                                        class="p-1.5 pr-2 text-right"
                                                                        >Delta</th
                                                                    >
                                                                </tr>
                                                            </thead>
                                                            <tbody
                                                                class="divide-border/50 divide-y"
                                                            >
                                                                {#each message.diffRows as row}
                                                                    <tr>
                                                                        <td
                                                                            class="text-foreground p-1.5 pl-2 font-bold"
                                                                            >{row.entity}</td
                                                                        >
                                                                        <td
                                                                            class="text-muted-foreground p-1.5"
                                                                            >{row.current}</td
                                                                        >
                                                                        <td
                                                                            class="p-1.5 font-bold text-emerald-600 dark:text-emerald-400"
                                                                            >{row.proposed}</td
                                                                        >
                                                                        <td
                                                                            class="text-primary p-1.5 pr-2 text-right font-bold"
                                                                            >{row.delta}</td
                                                                        >
                                                                    </tr>
                                                                {/each}
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                {/if}

                                                {#if message.modeRequest}
                                                    <div
                                                        class="space-y-2.5 rounded-xl border border-amber-500/40 bg-amber-500/10 p-3.5"
                                                    >
                                                        <div
                                                            class="flex items-start gap-2.5"
                                                        >
                                                            <div
                                                                class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-amber-500/20 text-amber-700 dark:text-amber-300"
                                                            >
                                                                <LockKeyhole
                                                                    class="size-3.5"
                                                                />
                                                            </div>
                                                            <div
                                                                class="space-y-1"
                                                            >
                                                                <div
                                                                    class="flex flex-wrap items-center gap-1.5"
                                                                >
                                                                    <h4
                                                                        class="text-foreground text-xs font-bold"
                                                                    >
                                                                        Permission
                                                                        boundary:
                                                                        Assistant
                                                                        Mode
                                                                    </h4>
                                                                    <span
                                                                        class="rounded bg-amber-500/20 px-1.5 py-0.5 font-mono text-[9px] font-bold text-amber-700 dark:text-amber-300"
                                                                        >No
                                                                        silent
                                                                        escalation</span
                                                                    >
                                                                </div>
                                                                <p
                                                                    class="text-muted-foreground text-[11px] leading-relaxed"
                                                                >
                                                                    {message
                                                                        .modeRequest
                                                                        ?.reason ??
                                                                        'A page mutation requires an explicitly allowed Assistant Mode and a human confirmation.'}
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <div
                                                            class="flex flex-wrap items-center justify-between gap-2 border-t border-amber-500/20 pt-1"
                                                        >
                                                            <span
                                                                class="text-muted-foreground font-mono text-[9px]"
                                                                >Scope: {contextTitle}</span
                                                            >
                                                            <button
                                                                type="button"
                                                                class="bg-primary text-primary-foreground inline-flex items-center gap-1 rounded-lg px-3 py-1 text-[11px] font-bold shadow-xs transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                                                                disabled={!assistantAllowed ||
                                                                    busy ||
                                                                    modePending ||
                                                                    preferenceLoading}
                                                                onclick={() =>
                                                                    void setInteraction(
                                                                        'assistant',
                                                                    )}
                                                            >
                                                                <ShieldCheck
                                                                    class="size-3"
                                                                />
                                                                {assistantAllowed
                                                                    ? 'Authorize Assistant Mode'
                                                                    : 'Assistant unavailable'}
                                                            </button>
                                                        </div>
                                                    </div>
                                                {/if}

                                                {#if message.proposals && message.proposals.length > 0}
                                                    {#each message.proposals as prop (prop.id ?? prop.proposal_id)}
                                                        <div class="mt-2">
                                                            <XiamProposalCard
                                                                proposal={prop}
                                                                onConfirm={(options) =>
                                                                    void confirmGate(
                                                                        prop,
                                                                        options,
                                                                    )}
                                                                onReject={() =>
                                                                    void rejectGate(
                                                                        prop,
                                                                    )}
                                                            />
                                                        </div>
                                                    {/each}
                                                {:else if message.proposal}
                                                    <XiamProposalCard
                                                        proposal={message.proposal}
                                                        onConfirm={(options) =>
                                                            void confirmGate(
                                                                message.proposal,
                                                                options,
                                                            )}
                                                        onReject={() =>
                                                            void rejectGate(
                                                                message.proposal,
                                                            )}
                                                    />
                                                {/if}
                                            </div>
                                        {/if}
                                    {/each}
                                    {#if busy && statusText && !messages.some((m) => m.streaming)}
                                        <div
                                            class="text-muted-foreground animate-in fade-in ml-[128px] flex max-w-[calc(100%-128px)] items-center gap-2 py-1 text-left font-mono text-xs duration-200 sm:ml-[148px] sm:max-w-[calc(100%-148px)]"
                                        >
                                            <span
                                                class="relative flex size-2 shrink-0"
                                            >
                                                <span
                                                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-indigo-400 opacity-75"
                                                ></span>
                                                <span
                                                    class="relative inline-flex size-2 rounded-full bg-indigo-500"
                                                ></span>
                                            </span>
                                            <span
                                                class="inline-flex min-w-0 items-center gap-1 truncate"
                                            >
                                                <span class="truncate"
                                                    >{statusText.replace(
                                                        /[\.\u2026]+$/,
                                                        '',
                                                    )}</span
                                                >
                                                <span
                                                    class="ml-0.5 inline-flex shrink-0 items-center gap-0.5"
                                                    aria-hidden="true"
                                                >
                                                    <span
                                                        class="inline-block size-1 animate-pulse rounded-full bg-indigo-500"
                                                        style="animation-delay: 0s; animation-duration: 0.8s;"
                                                    ></span>
                                                    <span
                                                        class="inline-block size-1 animate-pulse rounded-full bg-indigo-500"
                                                        style="animation-delay: 0.16s; animation-duration: 0.8s;"
                                                    ></span>
                                                    <span
                                                        class="inline-block size-1 animate-pulse rounded-full bg-indigo-500"
                                                        style="animation-delay: 0.32s; animation-duration: 0.8s;"
                                                    ></span>
                                                </span>
                                            </span>
                                        </div>
                                    {/if}

                                    {#if gate && !gateRenderedInHistory}
                                        <div
                                            class="ml-[128px] max-w-[calc(100%-128px)] sm:ml-[148px] sm:max-w-[calc(100%-148px)]"
                                        >
                                            <XiamProposalCard
                                                proposal={gate}
                                                onConfirm={(options) =>
                                                    void confirmGate(
                                                        gate,
                                                        options,
                                                    )}
                                                onReject={() =>
                                                    void rejectGate(gate)}
                                            />
                                        </div>
                                    {/if}
                                </div>

                                {#if citationOpen}
                                    <div
                                        class="bg-card animate-in fade-in slide-in-from-bottom-2 absolute right-3 bottom-[64px] left-3 z-30 rounded-xl border border-indigo-500/30 p-3 font-mono text-xs shadow-xl duration-150 sm:right-5 sm:left-5"
                                    >
                                        <div
                                            class="border-border flex items-center justify-between gap-2 border-b pb-1"
                                        >
                                            <div
                                                class="flex min-w-0 items-center gap-1.5 font-bold text-indigo-500"
                                            >
                                                <BookOpenText
                                                    class="size-3 shrink-0"
                                                />
                                                <span class="truncate"
                                                    >Grounding source: {citationOpen.source_title}</span
                                                >
                                            </div>
                                            <button
                                                type="button"
                                                class="text-muted-foreground hover:text-foreground"
                                                aria-label="Close citation"
                                                onclick={() =>
                                                    (citationOpen = null)}
                                            >
                                                <X class="size-3.5" />
                                            </button>
                                        </div>
                                        <p
                                            class="text-foreground mt-2 text-[11px] leading-relaxed"
                                        >
                                            {citationOpen.excerpt}
                                        </p>
                                        <div
                                            class="text-muted-foreground mt-2 flex flex-wrap items-center justify-between gap-2 text-[9px]"
                                        >
                                            <span
                                                >Locator: {citationOpen.version_id ||
                                                    citationOpen.source_id ||
                                                    'server source'}</span
                                            >
                                            {#if citationOpen.asof}<span
                                                    >As of: {citationOpen.asof}</span
                                                >{/if}
                                        </div>
                                    </div>
                                {/if}

                                {#if !followingEnd && messages.length > 0}
                                    <button
                                        type="button"
                                        class="bg-primary/90 text-primary-foreground hover:bg-primary animate-in fade-in slide-in-from-bottom-1 absolute right-4 bottom-[64px] z-30 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-semibold shadow-lg backdrop-blur-xs transition duration-150"
                                        onclick={() => {
                                            followingEnd = true;
                                            void scrollToBottom(true);
                                        }}
                                    >
                                        <span>Latest</span>
                                        <ArrowDown class="size-3" />
                                    </button>
                                {/if}

                                <div
                                    class="pointer-events-auto absolute bottom-[54px] left-2.5 z-20"
                                >
                                    <div
                                        class={`bg-card relative flex h-[152px] w-[118px] shrink-0 items-center justify-center overflow-hidden rounded-[18px] border-2 shadow-xl sm:h-[176px] sm:w-[136px] ${interaction === 'assistant' ? 'border-emerald-500/50 ring-2 ring-emerald-500/20' : 'border-border/80'}`}
                                    >
                                        <div
                                            class="from-card via-muted/30 to-muted/60 absolute inset-0 bg-gradient-to-b dark:from-slate-900/90 dark:to-slate-950"
                                        ></div>
                                        <div
                                            class="relative z-10 flex size-[116px] shrink-0 items-center justify-center sm:size-[132px]"
                                        >
                                            <XiamAvatar
                                                event={avatarEvent}
                                                playbackKey={`chat-turn:${avatarEvent?.generation ?? 0}`}
                                                size={avatarSpriteSize}
                                                busy={avatarWorking}
                                                label="Xiam assistant state"
                                            />
                                        </div>
                                        <div
                                            class="absolute right-0 bottom-1.5 left-0 z-20 text-center font-mono text-[8px] font-bold tracking-wider uppercase"
                                        >
                                            <span
                                                class="border-border/70 bg-background/90 text-primary rounded-full border px-1.5 py-0.5 shadow-xs"
                                                >Xiam · {currentModeLabel}</span
                                            >
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="border-border bg-card z-30 flex shrink-0 items-center gap-2 border-t p-2.5"
                                >
                                    <div
                                        class="flex min-w-0 flex-1 items-center gap-2"
                                    >
                                        <textarea
                                            class="border-border bg-background text-foreground placeholder:text-muted-foreground focus:border-primary/50 focus:ring-primary/15 h-8 max-h-8 min-h-8 flex-1 resize-none rounded-xl border px-3 py-1.5 text-xs transition outline-none focus:ring-2"
                                            rows="1"
                                            bind:value={input}
                                            placeholder={liveUsage?.state ===
                                            'exhausted'
                                                ? 'Xiam allowance exhausted for this period'
                                                : 'Ask Xiam or propose an operational change…'}
                                            aria-label="Ask Xiam"
                                            disabled={composerUnavailable}
                                            onkeydown={onKeydown}></textarea>
                                        <button
                                            type="button"
                                            class="bg-primary text-primary-foreground inline-flex size-8 shrink-0 items-center justify-center rounded-xl shadow-sm transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                                            aria-label="Send message"
                                            disabled={composerUnavailable ||
                                                preferenceLoading ||
                                                !input.trim()}
                                            onclick={() => void send()}
                                        >
                                            <Send class="size-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </main>
            </div>
        </div>
        {#if usageDetailsOpen}
            <div
                bind:this={usageModalEl}
                class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/65 p-4 backdrop-blur-xs outline-none"
                role="dialog"
                aria-modal="true"
                aria-labelledby="xiam-usage-title"
                tabindex="-1"
                onclick={(e) => {
                    if (e.target === e.currentTarget) {
                        closeUsageModal();
                    }
                }}
                onkeydown={(e) => {
                    if (e.key === 'Escape') {
                        e.stopPropagation();
                        closeUsageModal();
                    }
                }}
            >
                <div
                    class="border-border bg-card text-card-foreground w-full max-w-sm space-y-4 rounded-2xl border p-5 shadow-2xl"
                >
                    <div
                        class="flex items-center justify-between border-b pb-3"
                    >
                        <div class="flex items-center gap-2">
                            <Activity class="text-primary size-4" />
                            <h3 id="xiam-usage-title" class="text-sm font-bold">
                                Xiam Usage & Limits
                            </h3>
                        </div>
                        <button
                            type="button"
                            class="text-muted-foreground hover:text-foreground inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-md p-1 transition"
                            onclick={closeUsageModal}
                            aria-label="Close usage details"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                    <div class="space-y-2.5 font-mono text-xs">
                        <div
                            class="bg-muted/40 flex items-center justify-between rounded-lg p-2.5"
                        >
                            <span class="text-muted-foreground">Allowance</span>
                            <span class="text-foreground font-bold"
                                >{liveUsage?.used ?? 0} of {liveUsage?.limit ===
                                -1
                                    ? 'Unlimited'
                                    : (liveUsage?.limit ?? 15)}
                                {liveUsage?.unit || 'asks'}</span
                            >
                        </div>
                        <div
                            class="bg-muted/40 flex items-center justify-between rounded-lg p-2.5"
                        >
                            <span class="text-muted-foreground">Remaining</span>
                            <span class="text-foreground font-bold"
                                >{liveUsage?.limit === -1
                                    ? 'Unlimited'
                                    : `${liveUsage?.remaining ?? 0} remaining`}</span
                            >
                        </div>
                        <div
                            class="bg-muted/40 flex items-center justify-between rounded-lg p-2.5"
                        >
                            <span class="text-muted-foreground"
                                >Quota Period</span
                            >
                            <span class="text-foreground font-bold capitalize"
                                >{liveUsage?.period || 'today'}</span
                            >
                        </div>
                        {#if liveUsage?.resets_at}
                            <div
                                class="bg-muted/40 flex items-center justify-between rounded-lg p-2.5"
                            >
                                <span class="text-muted-foreground">Resets</span
                                >
                                <span class="text-foreground font-bold"
                                    >{new Date(
                                        liveUsage.resets_at,
                                    ).toLocaleTimeString([], {
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    })} ({new Date(
                                        liveUsage.resets_at,
                                    ).toLocaleDateString([], {
                                        month: 'short',
                                        day: 'numeric',
                                    })})</span
                                >
                            </div>
                        {/if}
                        {#if (liveUsage?.credit_balance ?? 0) > 0}
                            <div
                                class="flex items-center justify-between rounded-lg bg-emerald-500/10 p-2.5 text-emerald-700 dark:text-emerald-300"
                            >
                                <span>Extension Credits</span>
                                <span class="font-bold"
                                    >+{liveUsage.credit_balance}</span
                                >
                            </div>
                        {/if}
                    </div>
                    <p
                        class="text-muted-foreground text-[11px] leading-relaxed"
                    >
                        Asks count whenever you submit a message or request an
                        operational action. Standard portal features remain
                        fully accessible even if Xiam allowance is reached.
                    </p>
                    <div class="flex justify-end pt-1">
                        <Button
                            size="sm"
                            variant="outline"
                            class="min-h-9"
                            onclick={closeUsageModal}>Close</Button
                        >
                    </div>
                </div>
            </div>
        {/if}
    </details>
    {#if !isStaff && capsuleExpanded}
        <div
            data-xiam-capsule-root
            class="hidden md:flex fixed z-[70] items-center gap-2 rounded-full border border-border/70 bg-background/95 p-1.5 shadow-2xl backdrop-blur-md max-w-[calc(100vw-16px)]"
            style={`position: fixed; top: ${launcherTop}px; ${
                launcherPosition.side === 'right'
                    ? `right: ${Math.max(8, innerWidth - launcherLeft - 48)}px; left: auto;`
                    : `left: ${Math.max(8, launcherLeft)}px; right: auto;`
            }; touch-action: none;`}
        >
            {#if launcherPosition.side === 'right'}
                <button
                    type="button"
                    data-xiam-capsule-faq
                    class="size-11 flex items-center justify-center rounded-full border border-primary/30 bg-card text-primary shadow-lg transition hover:bg-primary hover:text-primary-foreground"
                    title="FAQs & Office Inquiry"
                    aria-label="FAQs & Office Inquiry"
                    onclick={() => {
                        capsuleExpanded = false;
                        window.dispatchEvent(
                            new CustomEvent('nexiam:open-faq-inquiry-modal', {
                                detail: { view: 'faqs' },
                            }),
                        );
                    }}
                >
                    <HelpCircle class="size-5" />
                </button>
                <button
                    type="button"
                    data-xiam-capsule-compass
                    class="size-11 flex items-center justify-center rounded-full border border-primary/30 bg-card text-primary shadow-lg transition hover:bg-primary hover:text-primary-foreground"
                    title="Campus Compass"
                    aria-label="Campus Compass"
                    onclick={() => {
                        window.dispatchEvent(new CustomEvent('nexiam:open-wayfinding'));
                        capsuleExpanded = false;
                    }}
                >
                    <Compass class="size-5" />
                </button>
                <button
                    type="button"
                    data-xiam-capsule-ai
                    class="size-11 flex items-center justify-center rounded-full border bg-primary/10 text-primary shadow-lg transition hover:bg-primary hover:text-primary-foreground"
                    title="Ask Kuya Xiam AI"
                    aria-label="Ask Kuya Xiam AI"
                    onclick={() => {
                        capsuleExpanded = false;
                        userInitiatedOpen = true;
                        if (detailsRoot) detailsRoot.open = true;
                    }}
                >
                    <Sparkles class="size-5" />
                </button>
                <button
                    type="button"
                    class="size-11 flex items-center justify-center rounded-full border bg-card text-muted-foreground shadow-lg transition hover:text-foreground"
                    title="Close launcher"
                    aria-label="Close launcher"
                    onclick={() => (capsuleExpanded = false)}
                >
                    <X class="size-4" />
                </button>
            {:else}
                <button
                    type="button"
                    class="size-11 flex items-center justify-center rounded-full border bg-card text-muted-foreground shadow-lg transition hover:text-foreground"
                    title="Close launcher"
                    aria-label="Close launcher"
                    onclick={() => (capsuleExpanded = false)}
                >
                    <X class="size-4" />
                </button>
                <button
                    type="button"
                    data-xiam-capsule-ai
                    class="size-11 flex items-center justify-center rounded-full border bg-primary/10 text-primary shadow-lg transition hover:bg-primary hover:text-primary-foreground"
                    title="Ask Kuya Xiam AI"
                    aria-label="Ask Kuya Xiam AI"
                    onclick={() => {
                        capsuleExpanded = false;
                        userInitiatedOpen = true;
                        if (detailsRoot) detailsRoot.open = true;
                    }}
                >
                    <Sparkles class="size-5" />
                </button>
                <button
                    type="button"
                    data-xiam-capsule-compass
                    class="size-11 flex items-center justify-center rounded-full border border-primary/30 bg-card text-primary shadow-lg transition hover:bg-primary hover:text-primary-foreground"
                    title="Campus Compass"
                    aria-label="Campus Compass"
                    onclick={() => {
                        window.dispatchEvent(new CustomEvent('nexiam:open-wayfinding'));
                        capsuleExpanded = false;
                    }}
                >
                    <Compass class="size-5" />
                </button>
                <button
                    type="button"
                    data-xiam-capsule-faq
                    class="size-11 flex items-center justify-center rounded-full border border-primary/30 bg-card text-primary shadow-lg transition hover:bg-primary hover:text-primary-foreground"
                    title="FAQs & Office Inquiry"
                    aria-label="FAQs & Office Inquiry"
                    onclick={() => {
                        capsuleExpanded = false;
                        window.dispatchEvent(
                            new CustomEvent('nexiam:open-faq-inquiry-modal', {
                                detail: { view: 'faqs' },
                            }),
                        );
                    }}
                >
                    <HelpCircle class="size-5" />
                </button>
            {/if}
        </div>
    {/if}
{/if}
