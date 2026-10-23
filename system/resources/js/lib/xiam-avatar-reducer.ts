export const XIAM_AVATAR_EMOTIONS = [
    'idle',
    'thinking',
    'searching-paper',
    'searching-phone',
    'hi',
    'thumbs-up',
    'celebrating',
    'star-eyes',
    'laughing',
    'shock',
    'sad',
    'angry',
    'talking-calm',
    'talking-presenting',
    'talking-expressive',
] as const;

export type XiamAvatarEmotion = (typeof XIAM_AVATAR_EMOTIONS)[number];

export type XiamAvatarState = {
    emotion: XiamAvatarEmotion;
    generation: number;
    priority: number;
    mood?: XiamAvatarEmotion;
    activeTools?: string[];
    incidentActive?: boolean;
};

export type XiamAvatarEvent = {
    emotion: string;
    generation?: number;
    priority?: number;
    mood?: XiamAvatarEmotion;
    toolStart?: string;
    toolEnd?: string;
    incident?: boolean;
    resolveIncident?: boolean;
    lifecycle?: boolean;
};

const PRIORITY: Record<XiamAvatarEmotion, number> = {
    idle: 0,
    'talking-calm': 5,
    'talking-presenting': 5,
    'talking-expressive': 5,
    hi: 10,
    'thumbs-up': 20,
    laughing: 30,
    'star-eyes': 40,
    celebrating: 50,
    shock: 60,
    sad: 70,
    angry: 80,
    thinking: 90,
    'searching-paper': 90,
    'searching-phone': 90,
};

export const INITIAL_XIAM_AVATAR_STATE: XiamAvatarState = {
    emotion: 'idle',
    generation: 0,
    priority: PRIORITY.idle,
};

export function reduceXiamAvatar(
    state: XiamAvatarState,
    event: XiamAvatarEvent,
): XiamAvatarState {
    if (
        !isXiamAvatarEmotion(event.emotion) ||
        (event.mood !== undefined && !isXiamAvatarEmotion(event.mood))
    ) {
        return state;
    }

    const activeTools = new Set(state.activeTools ?? []);
    if (event.toolStart !== undefined) {
        activeTools.add(event.toolStart);
    }
    if (event.toolEnd !== undefined) {
        activeTools.delete(event.toolEnd);
    }

    const mood =
        event.mood ??
        (event.lifecycle && isXiamAvatarEmotion(event.emotion)
            ? event.emotion
            : state.mood);
    const incidentActive = event.resolveIncident
        ? false
        : event.incident ?? state.incidentActive;
    const eventPriority = event.priority ?? PRIORITY[event.emotion];
    const acceptsEmotion =
        Number.isFinite(eventPriority) &&
        (event.lifecycle === true ||
            event.resolveIncident === true ||
            event.emotion === 'idle' ||
            eventPriority >= state.priority);
    let emotion = acceptsEmotion ? event.emotion : state.emotion;

    if (
        activeTools.size > 0 &&
        (event.toolStart !== undefined || isXiamAvatarToolPose(state.emotion))
    ) {
        emotion = xiamAvatarToolPose(activeTools);
    } else if (
        event.toolEnd !== undefined &&
        activeTools.size === 0 &&
        isXiamAvatarToolPose(state.emotion)
    ) {
        emotion = mood ?? 'idle';
    }

    const hasMetadataChange =
        event.toolStart !== undefined ||
        event.toolEnd !== undefined ||
        event.mood !== undefined ||
        event.incident !== undefined ||
        event.resolveIncident === true;
    if (!acceptsEmotion && !hasMetadataChange) {
        return state;
    }

    const generation =
        acceptsEmotion || event.toolStart !== undefined || event.toolEnd !== undefined
            ? Math.max(
                  state.generation + 1,
                  Number.isSafeInteger(event.generation)
                      ? (event.generation ?? 0)
                      : 0,
              )
            : state.generation;

    return {
        emotion,
        generation,
        priority:
            acceptsEmotion && emotion === event.emotion
                ? (event.lifecycle ? PRIORITY[emotion] : eventPriority)
                : PRIORITY[emotion],
        ...(mood === undefined ? {} : { mood }),
        ...(activeTools.size === 0 ? {} : { activeTools: [...activeTools] }),
        ...(incidentActive === undefined ? {} : { incidentActive }),
    };
}

export function completeXiamAvatar(
    state: XiamAvatarState,
    generation: number,
): XiamAvatarState {
    if (generation !== state.generation) {
        return state;
    }

    let emotion: XiamAvatarEmotion;
    if (state.incidentActive === true) {
        emotion =
            state.mood !== undefined && isConcernedXiamAvatarMood(state.mood)
                ? state.mood
                : 'sad';
    } else if ((state.activeTools?.length ?? 0) > 0) {
        emotion = xiamAvatarToolPose(new Set(state.activeTools));
    } else if (state.mood !== undefined && isConcernedXiamAvatarMood(state.mood)) {
        emotion = state.mood;
    } else {
        emotion = 'idle';
    }

    if (emotion === state.emotion) {
        return state;
    }

    return {
        ...state,
        emotion,
        priority: PRIORITY[emotion],
        mood: isConcernedXiamAvatarMood(emotion) ? emotion : 'idle',
    };
}

export function isXiamAvatarEmotion(value: string): value is XiamAvatarEmotion {
    return (XIAM_AVATAR_EMOTIONS as readonly string[]).includes(value);
}

export function xiamAvatarPriority(emotion: XiamAvatarEmotion): number {
    return PRIORITY[emotion];
}

function isXiamAvatarSearchingPose(
    emotion: XiamAvatarEmotion,
): emotion is 'searching-paper' | 'searching-phone' {
    return emotion === 'searching-paper' || emotion === 'searching-phone';
}

export function isXiamAvatarToolPose(emotion: XiamAvatarEmotion): boolean {
    return emotion === 'thinking' || isXiamAvatarSearchingPose(emotion);
}

function isConcernedXiamAvatarMood(emotion: XiamAvatarEmotion): boolean {
    return emotion === 'sad' || emotion === 'shock';
}

function xiamAvatarToolPose(activeTools: Set<string>): XiamAvatarEmotion {
    for (const tool of activeTools) {
        if (/phone|contact|call/i.test(tool)) {
            return 'searching-phone';
        }
        if (/paper|document|file|search/i.test(tool)) {
            return 'searching-paper';
        }
    }

    return 'thinking';
}
