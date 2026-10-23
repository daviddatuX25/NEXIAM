import {
    PHASE_DEFAULT_EMOTIONS,
    type TourEmotion,
    type TourPersona,
    type TourPhase,
} from './types';

import type {
    ResolvedTour,
    ResolvedTourAdvance,
    ResolvedTourChoice,
    ResolvedTourStep,
    TourContextBase,
    TourDefinition,
    TourDestination,
    TourFactory,
    TourNotice,
    TourStep,
    TourTarget,
    TourValue,
} from './types';

export function resolveStepEmotion(
    stepEmotion?: TourEmotion,
    phase?: TourPhase,
    _persona?: TourPersona,
): TourEmotion {
    if (stepEmotion) return stepEmotion;
    if (phase && PHASE_DEFAULT_EMOTIONS[phase])
        return PHASE_DEFAULT_EMOTIONS[phase];
    return 'idle';
}

function evaluateValue<C, V>(
    value: TourValue<C, V> | undefined,
    context: Readonly<C>,
): V | undefined {
    if (value === undefined) return undefined;
    if (typeof value === 'function') {
        return (value as (ctx: Readonly<C>) => V)(context);
    }
    return value;
}

/**
 * Pure synchronous resolver that materializes a TourDefinition or TourFactory into
 * an immutable, non-functional ResolvedTour ready for the Svelte player.
 */
export function resolveTour<C extends TourContextBase = any>(
    source: TourDefinition<C> | TourFactory<C>,
    context?: Readonly<C>,
): ResolvedTour {
    const ctx = (context ?? {
        entityKey: 'global',
        safetyKey: 'default',
        freshness: 'current',
    }) as Readonly<C>;

    const def: TourDefinition<C> =
        typeof source === 'function' ? source(ctx) : source;

    const variant = def.variant ?? 'default';
    const entityKey = ctx.entityKey ?? 'global';
    const safetyKey = ctx.safetyKey ?? 'default';
    const instanceKey = `${def.id}:r${def.revision}:${variant}:${entityKey}:${safetyKey}`;

    // 1. Identify which steps are included vs filtered out
    const stepInclusion = new Map<
        string,
        { included: boolean; otherwise?: TourDestination }
    >();
    for (const step of def.steps) {
        if (step.when && !step.when(ctx)) {
            stepInclusion.set(step.id, {
                included: false,
                otherwise: step.otherwise ?? { kind: 'complete' },
            });
        } else {
            stepInclusion.set(step.id, { included: true });
        }
    }

    // Helper to follow redirects if a step is filtered out
    function resolveDestination(
        dest: TourDestination,
        visited = new Set<string>(),
    ): TourDestination {
        if (dest.kind === 'complete') return dest;
        const info = stepInclusion.get(dest.stepId);
        if (!info || info.included) return dest;

        if (visited.has(dest.stepId)) {
            return { kind: 'complete' }; // Break cycle safely
        }
        visited.add(dest.stepId);

        return resolveDestination(
            info.otherwise ?? { kind: 'complete' },
            visited,
        );
    }

    // 2. Materialize included steps
    const resolvedSteps: ResolvedTourStep[] = [];
    for (const step of def.steps) {
        const info = stepInclusion.get(step.id);
        if (!info?.included) continue;

        const resolvedTitle = evaluateValue(step.title, ctx) ?? '';
        const resolvedText = evaluateValue(step.text, ctx) ?? '';
        const resolvedNotice: TourNotice | null =
            evaluateValue(step.notice, ctx) ?? null;
        const resolvedTarget: TourTarget | null =
            evaluateValue(step.target, ctx) ?? null;

        let resolvedAdvance: ResolvedTourAdvance;
        if (step.advance.kind === 'next') {
            resolvedAdvance = {
                kind: 'next',
                destination: resolveDestination(step.advance.destination),
            };
        } else {
            const validOptions: ResolvedTourChoice[] = [];
            for (const opt of step.advance.options) {
                if (opt.when && !opt.when(ctx)) continue;
                const label = evaluateValue(opt.label, ctx) ?? '';
                validOptions.push({
                    id: opt.id,
                    label,
                    destination: resolveDestination(opt.destination),
                });
            }

            if (validOptions.length > 0) {
                resolvedAdvance = {
                    kind: 'choices',
                    options: validOptions,
                };
            } else {
                // If all options were filtered out, fall back to explicit fallback destination or complete
                const fallback = step.advance.fallback ?? { kind: 'complete' };
                resolvedAdvance = {
                    kind: 'next',
                    destination: resolveDestination(fallback),
                };
            }
        }

        resolvedSteps.push({
            id: step.id,
            phase: step.phase ?? 'action',
            title: resolvedTitle,
            text: resolvedText,
            emotion: resolveStepEmotion(
                step.emotion,
                step.phase,
                step.personaOverride ?? def.persona,
            ),
            personaOverride: step.personaOverride,
            speechMode: step.speechMode ?? 'typewriter',
            notice: resolvedNotice,
            target: resolvedTarget,
            advance: resolvedAdvance,
            allowAskAi: step.allowAskAi ?? def.allowAskAi,
        });
    }

    // Resolve start node
    const resolvedStartDest = resolveDestination({
        kind: 'step',
        stepId: def.start,
    });
    const resolvedStart =
        resolvedStartDest.kind === 'step'
            ? resolvedStartDest.stepId
            : (resolvedSteps[0]?.id ?? '');

    return {
        id: def.id,
        revision: def.revision,
        variant,
        instanceKey,
        title: def.title,
        domain: def.domain,
        persona: def.persona,
        start: resolvedStart,
        diagnostic: def.diagnostic,
        notice: def.notice ?? null,
        steps: resolvedSteps,
        allowAskAi: def.allowAskAi,
    };
}
