<script>
    import { router } from '@inertiajs/svelte';
    import { Building2, ClipboardList, Clock, MapPin, User } from 'lucide-svelte';
    import { onDestroy, untrack } from 'svelte';
    import { SvelteSet, SvelteURLSearchParams } from 'svelte/reactivity';

    const ALLOWED_DOMAIN_CONTEXTS = new Set([
        'monitoring',
        'grading',
        'scheduling',
        'consultation_scheduling',
    ]);

    const DEBOUNCE_MS = 250;
    const MAX_QUERY_LENGTH = 100;
    const MAX_SUGGESTIONS = 8;
    const FALLBACK_OPTION = 'fallback';

    let {
        search = '',
        academicCycleId,
        domainContext,
        contextKey = null,
        status: statusFilter = '',
        tab = '',
        date = '',
        roomId = '',
        examSessionId,
        gradingSessionId = '',
        lookupScope = '',
        destinationKind = '',
        navigationOnly = false,
        onactivate,
        oninteractionchange,
        inputRef,
        class: className = '',
    } = $props();

    let currentQuery = $state(String(search ?? ''));
    let isOpen = $state(false);
    let activeOption = $state(null);
    let status = $state('idle');
    let suggestions = $state([]);
    let matchesTruncated = $state(false);
    let dropdownEl = $state(null);
    let unavailableMessage = $state(
        'Suggestions are unavailable. You can still filter sessions.',
    );

    const listboxId = `search-suggestion-listbox-${Math.random().toString(36).slice(2, 10)}`;
    let debounceTimer = null;
    let controller = null;
    let generation = 0;
    let dismissed = false;
    let inputFocused = false;
    let composing = false;
    let lastInteractionState = false;
    let userEditedInput = false;
    let observedProps = false;
    let previousSearch = '';
    let resultsContext = $state(null);
    let resultsQuery = $state(null);
    let resultsGeneration = $state(null);
    let previousContextSignature = '';

    function normalizedQuery(value = currentQuery) {
        return String(value ?? '').trim();
    }

    function queryLength(value) {
        return Array.from(value).length;
    }

    function formatTime(value) {
        if (!value) {
            return '—';
        }

        const time = new Date(value);

        return Number.isNaN(time.getTime())
            ? '—'
            : time.toLocaleTimeString([], {
                  hour: '2-digit',
                  minute: '2-digit',
              });
    }

    function serializeContextKey(value) {
        if (
            typeof value === 'string' ||
            typeof value === 'number' ||
            typeof value === 'boolean'
        ) {
            return String(value);
        }

        if (value === null || value === undefined) {
            return '';
        }

        try {
            return JSON.stringify(value);
        } catch {
            return String(value);
        }
    }

    function contextSignature() {
        return JSON.stringify([
            academicCycleId ?? null,
            domainContext ?? null,
            statusFilter ?? '',
            tab ?? '',
            date ?? '',
            roomId ?? '',
            serializeContextKey(contextKey),
            examSessionId ?? gradingSessionId ?? '',
            lookupScope ?? '',
            destinationKind ?? '',
            navigationOnly === true,
        ]);
    }

    function lookupContextIsValid() {
        const cycleId = Number(academicCycleId);

        return (
            Number.isInteger(cycleId) &&
            cycleId > 0 &&
            ALLOWED_DOMAIN_CONTEXTS.has(domainContext) &&
            contextKey !== null &&
            contextKey !== undefined &&
            contextKey !== ''
        );
    }

    function interactionIsActive() {
        return inputFocused || composing || isOpen;
    }

    function notifyInteractionChange() {
        syncInputAria();

        const isActive = interactionIsActive();

        if (isActive === lastInteractionState) {
            return;
        }

        lastInteractionState = isActive;
        oninteractionchange?.(isActive);
    }

    function syncInputAria() {
        if (!(inputRef instanceof HTMLElement)) {
            return;
        }

        inputRef.setAttribute('aria-expanded', String(isOpen));

        const listboxId = listboxIdentifier();
        if (listboxId) {
            inputRef.setAttribute('aria-controls', listboxId);
        } else {
            inputRef.removeAttribute('aria-controls');
        }

        const activeId = activeDescendantId();
        if (activeId) {
            inputRef.setAttribute('aria-activedescendant', activeId);
        } else {
            inputRef.removeAttribute('aria-activedescendant');
        }
    }

    function clearPendingLookup() {
        clearTimeout(debounceTimer);
        debounceTimer = null;
        controller?.abort();
        controller = null;
        generation += 1;
    }

    function clearResults() {
        suggestions = [];
        matchesTruncated = false;
        activeOption = null;
        resultsContext = null;
        resultsQuery = null;
        resultsGeneration = null;
        unavailableMessage =
            'Suggestions are unavailable. You can still filter sessions.';
    }

    function suggestionsAreCurrent() {
        return (
            resultsContext === contextSignature() &&
            resultsQuery === normalizedQuery() &&
            resultsGeneration === generation
        );
    }

    function currentSuggestions() {
        if (!suggestionsAreCurrent()) {
            return [];
        }

        return navigationOnly === true
            ? suggestions.filter((item) => item.kind === 'examinee')
            : suggestions;
    }
    function scheduleLookup(query) {
        if (!lookupContextIsValid()) {
            setUnavailable();
            return;
        }

        status = 'debouncing';
        isOpen = true;
        dismissed = false;
        const requestGeneration = generation;
        const requestContext = contextSignature();
        const requestSnapshot = {
            academicCycleId: String(academicCycleId),
            domainContext,
            status: statusFilter ?? '',
            tab,
            date,
            roomId,
            examSessionId: examSessionId ?? gradingSessionId ?? '',
            lookupScope: lookupScope ?? '',
        };
        debounceTimer = setTimeout(() => {
            debounceTimer = null;
            void fetchSuggestions(
                query,
                requestGeneration,
                requestContext,
                requestSnapshot,
            );
        }, DEBOUNCE_MS);
        notifyInteractionChange();
    }

    function refreshForContextChange(query, shouldRefresh) {
        invalidateCurrentLookup();
        currentQuery = query;

        if (!shouldRefresh) {
            dismissed = true;
            status = 'idle';
            isOpen = false;
            notifyInteractionChange();
            return;
        }

        dismissed = false;
        const length = queryLength(normalizedQuery(query));

        if (length > MAX_QUERY_LENGTH) {
            status = 'invalid';
            isOpen = true;
            notifyInteractionChange();
            return;
        }

        if (length === 0) {
            status = 'idle';
            isOpen = false;
            notifyInteractionChange();
            return;
        }

        isOpen = true;

        if (length < 2) {
            status = 'idle';
            notifyInteractionChange();
            return;
        }

        scheduleLookup(normalizedQuery(query));
    }

    function invalidateCurrentLookup() {
        clearPendingLookup();
        clearResults();
    }
    function canShowFallback() {
        if (navigationOnly === true) {
            return false;
        }

        const query = normalizedQuery();

        return queryLength(query) > 0 && queryLength(query) <= MAX_QUERY_LENGTH;
    }
    function selectableOptionCount() {
        return currentSuggestions().length + (canShowFallback() ? 1 : 0);
    }
    function listboxIsRendered() {
        return isOpen && selectableOptionCount() > 0;
    }
    function setUnavailable(
        message = 'Suggestions are unavailable. You can still filter sessions.',
    ) {
        const fallbackWasActive =
            activeOption === FALLBACK_OPTION && canShowFallback();
        suggestions = [];
        matchesTruncated = false;
        activeOption = fallbackWasActive ? FALLBACK_OPTION : null;
        resultsContext = null;
        resultsQuery = null;
        resultsGeneration = null;
        status = 'unavailable';
        unavailableMessage = message;
        isOpen = true;
        dismissed = false;
        notifyInteractionChange();
    }

    function isCurrentRequest(
        requestGeneration,
        requestContext,
        requestQuery,
        requestController = null,
    ) {
        return (
            requestGeneration === generation &&
            requestContext === contextSignature() &&
            normalizedQuery() === requestQuery &&
            normalizedQuery(search) === requestQuery &&
            !dismissed &&
            (requestController === null || !requestController.signal.aborted)
        );
    }

    export function handleInput(value) {
        const nextValue = String(value ?? '');
        currentQuery = nextValue;
        inputFocused = true;
        userEditedInput = true;
        dismissed = false;

        invalidateCurrentLookup();
        const query = normalizedQuery(nextValue);

        if (queryLength(query) > MAX_QUERY_LENGTH) {
            status = 'invalid';
            isOpen = true;
            notifyInteractionChange();
            return;
        }

        if (queryLength(query) === 0) {
            status = 'idle';
            isOpen = false;
            notifyInteractionChange();
            return;
        }

        isOpen = true;

        if (queryLength(query) < 2) {
            status = 'idle';
            notifyInteractionChange();
            return;
        }

        scheduleLookup(query);
    }

    export function handleFocus() {
        inputFocused = true;
        notifyInteractionChange();
    }

    function isInsideInputOrDropdown(target) {
        if (!(target instanceof Node)) {
            return false;
        }

        if (
            inputRef instanceof Node &&
            (inputRef === target || inputRef.contains(target))
        ) {
            return true;
        }

        return (
            dropdownEl instanceof Node &&
            (dropdownEl === target || dropdownEl.contains(target))
        );
    }

    export function handleBlur(event) {
        if (isInsideInputOrDropdown(event?.relatedTarget)) {
            return;
        }

        inputFocused = false;
        composing = false;
        dismiss();
    }

    export function dismiss() {
        dismissed = true;
        invalidateCurrentLookup();
        status = 'idle';
        isOpen = false;
        notifyInteractionChange();
    }

    function handleOutsidePointerdown(event) {
        if (isInsideInputOrDropdown(event.target)) {
            return;
        }

        if (isOpen || debounceTimer !== null || controller !== null) {
            dismiss();
        }
    }

    function keepInputFocused(event) {
        event.preventDefault();
    }

    function optionIdFor(key) {
        const encodedKey = Array.from(String(key))
            .map((character) => character.codePointAt(0).toString(16))
            .join('-');

        return `${listboxId}-option-${encodedKey}`;
    }

    export function activeDescendantId() {
        if (
            !isOpen ||
            dismissed ||
            status === 'loading' ||
            !activeOption ||
            !listboxIsRendered()
        ) {
            return undefined;
        }

        if (activeOption === FALLBACK_OPTION) {
            return canShowFallback()
                ? `${listboxId}-option-fallback`
                : undefined;
        }

        const suggestionKey = activeOption.startsWith('suggestion:')
            ? activeOption.slice('suggestion:'.length)
            : null;
        const item = currentSuggestions().find(
            (suggestion) => suggestion.key === suggestionKey,
        );

        return item ? optionIdFor(item.key) : undefined;
    }

    export function isExpanded() {
        return isOpen;
    }

    export function listboxIdentifier() {
        return listboxIsRendered() ? listboxId : undefined;
    }

    function setActiveOption(option) {
        if (!isOpen || dismissed) {
            return;
        }

        if (option === FALLBACK_OPTION && canShowFallback()) {
            activeOption = FALLBACK_OPTION;
            notifyInteractionChange();
            return;
        }

        if (typeof option === 'string' && option.startsWith('suggestion:')) {
            const key = option.slice('suggestion:'.length);

            if (currentSuggestions().some((item) => item.key === key)) {
                activeOption = option;
                notifyInteractionChange();
            }
        }
    }
    function orderedOptionKeys() {
        const keys = currentSuggestions().map(
            (item) => `suggestion:${item.key}`,
        );

        if (canShowFallback()) {
            keys.push(FALLBACK_OPTION);
        }

        return keys;
    }

    function moveSelection(direction) {
        const keys = orderedOptionKeys();

        if (keys.length === 0) {
            return false;
        }

        if (!isOpen || dismissed) {
            dismissed = false;
            isOpen = true;
        }

        const activeIndex =
            activeOption === null ? -1 : keys.indexOf(activeOption);

        if (direction === 'down') {
            activeOption =
                activeIndex < 0
                    ? keys[0]
                    : keys[Math.min(activeIndex + 1, keys.length - 1)];
        } else if (activeIndex < 0) {
            activeOption = keys[keys.length - 1];
        } else {
            activeOption = activeIndex === 0 ? null : keys[activeIndex - 1];
        }

        notifyInteractionChange();
        return true;
    }

    function validSameOriginUrl(value) {
        if (
            typeof value !== 'string' ||
            value.length === 0 ||
            value.includes('\\')
        ) {
            return false;
        }

        const isRelativePath = value.startsWith('/') && !value.startsWith('//');
        const isAbsoluteHttpUrl = /^https?:\/\//i.test(value);

        if (!isRelativePath && !isAbsoluteHttpUrl) {
            return false;
        }

        if (typeof window === 'undefined') {
            return isRelativePath;
        }

        try {
            const parsed = new URL(value, window.location.origin);

            return (
                parsed.origin === window.location.origin &&
                parsed.username === '' &&
                parsed.password === ''
            );
        } catch {
            return false;
        }
    }

    function isValidSuggestionItem(item) {
        if (
            !item ||
            typeof item !== 'object' ||
            typeof item.key !== 'string' ||
            item.key.trim() === ''
        ) {
            return false;
        }

        const entityType = item.entity_type ?? item.kind;
        if (
            !['session', 'examinee', 'waitlist_applicant', 'pipeline_applicant'].includes(
                entityType,
            )
        ) {
            return false;
        }

        const destinationUrl = item.destination?.url ?? item.destination_url;
        if (!validSameOriginUrl(destinationUrl)) {
            return false;
        }

        if (entityType === 'session') {
            if (
                typeof item.session_code !== 'string' ||
                item.session_code.trim() === '' ||
                typeof item.room_label !== 'string' ||
                typeof item.session_status !== 'string'
            ) {
                return false;
            }

            if (item.destination && typeof item.destination === 'object') {
                return (
                    item.destination.focus_kind === null &&
                    item.destination.focus_id === null
                );
            }
            return true;
        }

        if (entityType === 'examinee') {
            if (
                typeof item.name !== 'string' ||
                item.name.trim() === '' ||
                typeof item.reference_code !== 'string' ||
                item.reference_code.trim() === ''
            ) {
                return false;
            }

            const expectedFocusKind =
                domainContext === 'grading' ? 'attempt' : 'assignment';
            if (item.destination && typeof item.destination === 'object') {
                return (
                    (item.destination.focus_kind === expectedFocusKind ||
                        item.destination.focus_kind === 'attempt' ||
                        item.destination.focus_kind === 'assignment') &&
                    Number.isInteger(item.destination.focus_id) &&
                    item.destination.focus_id > 0
                );
            }
            return true;
        }

        if (
            entityType === 'waitlist_applicant' ||
            entityType === 'pipeline_applicant'
        ) {
            if (
                typeof item.name !== 'string' ||
                item.name.trim() === '' ||
                typeof item.reference_code !== 'string' ||
                item.reference_code.trim() === ''
            ) {
                return false;
            }

            if (!Number.isInteger(item.attempt_id) || item.attempt_id <= 0) {
                return false;
            }

            return true;
        }

        return false;
    }

    function isValidSuggestion(item) {
        return isValidSuggestionItem(item);
    }

    function pipelineBadgeClass(tone) {
        switch (tone) {
            case 'emerald':
                return 'border-success/30 bg-success/10 text-success';
            case 'amber':
                return 'border-warning/30 bg-warning/10 text-warning';
            case 'destructive':
                return 'border-destructive/30 bg-destructive/10 text-destructive';
            case 'primary':
                return 'border-primary/30 bg-primary/10 text-primary';
            case 'slate':
            default:
                return 'border-border bg-muted text-muted-foreground';
        }
    }

    function parseSuggestionResponse(result) {
        if (
            !result ||
            typeof result !== 'object' ||
            !Array.isArray(result.items) ||
            result.items.length > MAX_SUGGESTIONS ||
            typeof result.matches_truncated !== 'boolean'
        ) {
            return null;
        }

        const seenKeys = new SvelteSet();

        for (const item of result.items) {
            if (!isValidSuggestion(item) || seenKeys.has(item.key)) {
                return null;
            }

            seenKeys.add(item.key);
        }

        return {
            items: result.items,
            matchesTruncated: result.matches_truncated,
        };
    }

    function emitAction(action) {
        if (typeof onactivate !== 'function') {
            setUnavailable(
                'Search actions are unavailable. You can still edit the search.',
            );
            return;
        }

        dismiss();
        onactivate(action);
    }

    function activationHandler(option) {
        const requestGeneration = generation;
        const expectedQuery = currentQuery;
        const requestContext = contextSignature();

        return () =>
            activateOption(
                option,
                requestGeneration,
                expectedQuery,
                requestContext,
            );
    }

    function activateOption(
        option,
        expectedGeneration = generation,
        expectedQuery = currentQuery,
        expectedContext = contextSignature(),
    ) {
        if (
            expectedGeneration !== generation ||
            expectedQuery !== currentQuery ||
            expectedContext !== contextSignature() ||
            expectedQuery !== String(search ?? '') ||
            !isOpen ||
            dismissed
        ) {
            return;
        }

        const query = normalizedQuery(expectedQuery);

        if (option === FALLBACK_OPTION) {
            if (!canShowFallback()) {
                return;
            }

            emitAction({ type: 'filter', query, source: 'fallback' });
            return;
        }

        const key =
            typeof option === 'string' && option.startsWith('suggestion:')
                ? option.slice('suggestion:'.length)
                : null;
        const item = currentSuggestions().find(
            (suggestion) => suggestion.key === key,
        );
        if (!item) {
            return;
        }

        selectItem(item);
    }

    export function selectItem(item) {
        if (!item || !isValidSuggestionItem(item)) {
            setUnavailable();
            return;
        }

        const destinationUrl = item.destination?.url ?? item.destination_url;
        const entityType = item.entity_type ?? item.kind;

        if (typeof onactivate === 'function') {
            const hasPipelineFields =
                item.pipeline_state !== undefined ||
                item.target_tab !== undefined ||
                item.readiness_bucket !== undefined ||
                entityType === 'waitlist_applicant' ||
                entityType === 'pipeline_applicant' ||
                (item.attempt_id !== null && item.attempt_id !== undefined);

            const payload =
                entityType === 'session'
                    ? {
                          type: 'filter',
                          query: item.session_code ? item.session_code.trim() : '',
                          source: 'session',
                      }
                    : {
                          type: 'navigate',
                          url: destinationUrl,
                          key: item.key,
                      };

            if (hasPipelineFields) {
                payload.attemptId = item.attempt_id ?? null;
                payload.pipelineState = item.pipeline_state ?? null;
                payload.targetTab = item.target_tab ?? null;
                payload.readinessBucket = item.readiness_bucket ?? null;
            }

            dismiss();
            onactivate(payload);
            return;
        }

        if (destinationUrl) {
            dismiss();
            router.visit(destinationUrl);
            return;
        }

        setUnavailable();
    }

    export function handleKeydown(event) {
        if (event.isComposing || event.keyCode === 229) {
            composing = true;
            notifyInteractionChange();
            return;
        }

        composing = false;

        if (event.key === 'ArrowDown') {
            if (moveSelection('down')) {
                event.preventDefault();
            }
        } else if (event.key === 'ArrowUp') {
            if (moveSelection('up')) {
                event.preventDefault();
            }
        } else if (event.key === 'Enter') {
            event.preventDefault();

            const query = normalizedQuery();

            if (queryLength(query) > MAX_QUERY_LENGTH) {
                status = 'invalid';
                isOpen = true;
                activeOption = null;
                notifyInteractionChange();
                return;
            }

            if (navigationOnly === true) {
                const activeKey =
                    typeof activeOption === 'string' &&
                    activeOption.startsWith('suggestion:')
                        ? activeOption.slice('suggestion:'.length)
                        : null;
                const target =
                    currentSuggestions().find((item) => item.key === activeKey) ??
                    currentSuggestions()[0];

                if (target) {
                    activateOption(`suggestion:${target.key}`);
                }

                return;
            }

            if (isOpen && activeOption && activeOption !== FALLBACK_OPTION) {
                const key = activeOption.startsWith('suggestion:')
                    ? activeOption.slice('suggestion:'.length)
                    : null;

                if (
                    key &&
                    currentSuggestions().some((item) => item.key === key)
                ) {
                    activateOption(activeOption);
                    return;
                }
            }

            if (
                isOpen &&
                activeOption === FALLBACK_OPTION &&
                canShowFallback()
            ) {
                activateOption(FALLBACK_OPTION);
                return;
            }

            emitAction({ type: 'filter', query, source: 'enter' });
        } else if (event.key === 'Escape') {
            if (isOpen || debounceTimer !== null || controller !== null) {
                event.preventDefault();
            }
            dismiss();
        } else if (event.key === 'Tab') {
            dismiss();
        }
    }

    async function fetchSuggestions(
        query,
        requestGeneration,
        requestContext,
        requestSnapshot,
    ) {
        if (!isCurrentRequest(requestGeneration, requestContext, query)) {
            return;
        }

        if (!lookupContextIsValid()) {
            setUnavailable();
            return;
        }
        const requestController = new AbortController();
        controller = requestController;
        status = 'loading';

        const fallbackWasActive =
            activeOption === FALLBACK_OPTION && canShowFallback();
        suggestions = [];
        matchesTruncated = false;
        activeOption = fallbackWasActive ? FALLBACK_OPTION : null;
        notifyInteractionChange();

        const params = new SvelteURLSearchParams({
            q: query,
            academic_cycle_id: requestSnapshot.academicCycleId,
            domain_context: requestSnapshot.domainContext,
        });

        if (
            (requestSnapshot.domainContext === 'scheduling' ||
                requestSnapshot.domainContext === 'consultation_scheduling' ||
                requestSnapshot.domainContext === 'grading') &&
            requestSnapshot.status
        ) {
            params.set('status', requestSnapshot.status);
        }

        if (requestSnapshot.examSessionId) {
            params.set('exam_session_id', requestSnapshot.examSessionId);
        }
        if (requestSnapshot.lookupScope) {
            params.set('lookup_scope', requestSnapshot.lookupScope);
        }
        if (requestSnapshot.destinationKind) {
            params.set('destination_kind', requestSnapshot.destinationKind);
        }
        if (requestSnapshot.tab) {
            params.set('tab', requestSnapshot.tab);
        }
        if (requestSnapshot.date) {
            params.set('date', requestSnapshot.date);
        }
        if (requestSnapshot.roomId) {
            params.set('room_id', requestSnapshot.roomId);
        }
        try {
            const response = await fetch(
                `/exams/search-suggestions?${params.toString()}`,
                {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: requestController.signal,
                },
            );

            if (
                !isCurrentRequest(
                    requestGeneration,
                    requestContext,
                    query,
                    requestController,
                )
            ) {
                return;
            }

            if (!response.ok) {
                throw new Error('Suggestion request failed.');
            }

            const result = await response.json();

            if (
                !isCurrentRequest(
                    requestGeneration,
                    requestContext,
                    query,
                    requestController,
                )
            ) {
                return;
            }

            const parsed = parseSuggestionResponse(result);

            if (!parsed) {
                setUnavailable();
                return;
            }

            suggestions = parsed.items;
            matchesTruncated = parsed.matchesTruncated;
            activeOption = null;
            resultsContext = requestContext;
            resultsQuery = query;
            resultsGeneration = requestGeneration;
            status = parsed.items.length > 0 ? 'ready' : 'empty';
            notifyInteractionChange();
        } catch {
            if (
                !isCurrentRequest(
                    requestGeneration,
                    requestContext,
                    query,
                    requestController,
                )
            ) {
                return;
            }

            setUnavailable();
        } finally {
            if (
                isCurrentRequest(
                    requestGeneration,
                    requestContext,
                    query,
                    requestController,
                )
            ) {
                if (status === 'loading') {
                    setUnavailable();
                }

                if (controller === requestController) {
                    controller = null;
                }
            }
        }
    }

    $effect(() => {
        const nextSearch = String(search ?? '');
        const nextContextSignature = contextSignature();

        if (!observedProps) {
            observedProps = true;
            previousSearch = nextSearch;
            previousContextSignature = nextContextSignature;
            currentQuery = nextSearch;
            return;
        }

        const searchChanged = nextSearch !== previousSearch;
        const contextChanged =
            nextContextSignature !== previousContextSignature;
        const searchChangedOutsideInput =
            searchChanged && nextSearch !== untrack(() => currentQuery);
        const shouldRefreshForContext =
            contextChanged &&
            !searchChangedOutsideInput &&
            userEditedInput &&
            !dismissed &&
            (inputFocused || composing || isOpen);

        previousSearch = nextSearch;
        previousContextSignature = nextContextSignature;

        if (!searchChanged && !contextChanged) {
            return;
        }

        if (contextChanged) {
            refreshForContextChange(nextSearch, shouldRefreshForContext);
            return;
        }

        if (searchChangedOutsideInput) {
            currentQuery = nextSearch;
            dismiss();
        }
    });

    onDestroy(() => {
        clearTimeout(debounceTimer);
        generation += 1;
        controller?.abort();
    });
</script>

<svelte:window onpointerdown={handleOutsidePointerdown} />

<div bind:this={dropdownEl} class="relative {className}">
    {#if isOpen}
        <div
            class="border-border/80 bg-card/95 absolute top-full left-0 z-50 mt-1.5 w-full max-w-[calc(100vw-2rem)] min-w-75 overflow-hidden rounded-xl border p-1.5 text-xs shadow-2xl backdrop-blur-md sm:w-105"
        >
            {#if status === 'debouncing'}
                <div
                    class="text-muted-foreground p-2"
                    role="status"
                    aria-live="polite"
                >
                    Waiting for suggestions…
                </div>
            {:else if status === 'loading'}
                <div
                    class="space-y-2 p-2"
                    role="status"
                    aria-live="polite"
                    aria-busy="true"
                >
                    <span class="sr-only">Loading suggestions</span>
                    <div class="bg-muted h-3 animate-pulse rounded"></div>
                    <div class="bg-muted h-3 w-2/3 animate-pulse rounded"></div>
                </div>
            {:else if status === 'invalid'}
                <div class="text-destructive p-2" role="alert">
                    Search must be 100 characters or fewer.
                </div>
            {:else if status === 'empty'}
                <div
                    class="text-muted-foreground p-2"
                    role="status"
                    aria-live="polite"
                >
                    No matching sessions found.
                </div>
            {:else if status === 'unavailable'}
                <div
                    class="text-muted-foreground p-2"
                    role="status"
                    aria-live="polite"
                >
                    {unavailableMessage}
                </div>
            {/if}

            {#if listboxIsRendered()}
                <div
                    id={listboxId}
                    role="listbox"
                    aria-label="Search suggestions"
                >
                    {#each currentSuggestions() as item (item.key)}
                        <button
                            type="button"
                            id={optionIdFor(item.key)}
                            role="option"
                            tabindex="-1"
                            aria-selected={activeOption ===
                                `suggestion:${item.key}`}
                            class="hover:bg-muted/70 block w-full rounded-lg px-2.5 py-2 text-left {activeOption ===
                            `suggestion:${item.key}`
                                ? 'bg-muted'
                                : ''}"
                            onpointerdown={keepInputFocused}
                            onclick={activationHandler(
                                `suggestion:${item.key}`,
                            )}
                            onmouseenter={() =>
                                setActiveOption(`suggestion:${item.key}`)}
                        >
                            {#if (item.entity_type ?? item.kind) === 'session'}
                                <div class="flex items-center justify-between gap-2">
                                    <div class="text-foreground font-semibold truncate">
                                        <Building2 class="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
                                        {item.room_label}
                                        <span class="text-muted-foreground font-mono font-normal">({item.session_code})</span>
                                    </div>
                                    {#if item.pipeline_badge_label}
                                        <span class="inline-flex shrink-0 items-center rounded border px-1.5 py-0.5 text-[10px] font-medium {pipelineBadgeClass(item.pipeline_badge_tone)}">
                                            {item.pipeline_badge_label}
                                        </span>
                                    {/if}
                                </div>
                                <div class="text-muted-foreground mt-1 flex items-center gap-2">
                                    <span class="bg-muted rounded px-1.5 py-0.5">{item.session_status}</span>
                                    {formatTime(item.starts_at)}
                                </div>
                                <div class="text-primary mt-1">Filter sessions</div>
                            {:else}
                                <div class="flex items-center justify-between gap-2">
                                    <div class="text-foreground font-semibold truncate">
                                        <User class="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
                                        {item.name}
                                        <span class="text-muted-foreground font-mono font-normal">({item.reference_code})</span>
                                    </div>
                                    {#if item.pipeline_badge_label}
                                        <span class="inline-flex shrink-0 items-center rounded border px-1.5 py-0.5 text-[10px] font-medium {pipelineBadgeClass(item.pipeline_badge_tone)}">
                                            {item.pipeline_badge_label}
                                        </span>
                                    {/if}
                                </div>
                                {#if (item.entity_type ?? item.kind) === 'waitlist_applicant'}
                                    <div class="text-muted-foreground mt-1 truncate">
                                        <ClipboardList class="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
                                        Waitlist · Ready for Assignment
                                    </div>
                                    <div class="text-primary mt-1">Focus in waitlist pool</div>
                                {:else if (item.entity_type ?? item.kind) === 'pipeline_applicant'}
                                    <div class="text-muted-foreground mt-1 truncate">
                                        <Clock class="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
                                        Pipeline · Pending Prerequisites
                                    </div>
                                    <div class="text-primary mt-1">View applicant status</div>
                                {:else}
                                    <div class="text-muted-foreground mt-1 truncate">
                                        <MapPin class="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
                                        {item.room_label} · {item.session_code} · {formatTime(item.starts_at)}
                                    </div>
                                    <div class="text-primary mt-1">Open examinee</div>
                                {/if}
                            {/if}
                        </button>
                    {/each}

                    {#if canShowFallback()}
                        <button
                            type="button"
                            id={`${listboxId}-option-fallback`}
                            role="option"
                            tabindex="-1"
                            aria-selected={activeOption === FALLBACK_OPTION}
                            class="border-border/70 text-primary hover:bg-muted/70 block w-full border-t px-2.5 py-2 text-left font-medium {activeOption ===
                            FALLBACK_OPTION
                                ? 'bg-muted'
                                : ''}"
                            onpointerdown={keepInputFocused}
                            onclick={activationHandler(FALLBACK_OPTION)}
                            onmouseenter={() =>
                                setActiveOption(FALLBACK_OPTION)}
                        >
                            Filter sessions matching "{normalizedQuery()}"
                        </button>
                    {/if}
                </div>
            {/if}

            {#if suggestionsAreCurrent() && matchesTruncated}
                <div
                    class="border-border/70 text-muted-foreground border-t px-2.5 py-2"
                    role="status"
                    aria-live="polite"
                >
                    Showing the top 8 matches. Type more to narrow the
                    suggestions.
                </div>
            {/if}
        </div>
    {/if}
</div>
