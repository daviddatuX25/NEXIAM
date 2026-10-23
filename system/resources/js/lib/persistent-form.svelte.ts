import { useForm } from '@inertiajs/svelte';
import {
    clearDraft,
    loadDraft,
    saveDraft,
} from './draft-storage';

export interface PersistentFormOptions<T extends Record<string, any>> {
    formId: string;
    getContext?: () => Record<string, unknown>;
    initialData: T;
    excludeKeys?: (keyof T)[];
    debounceMs?: number;
    ttlMs?: number;
    onRestored?: (data: Partial<T>) => void;
}

export function usePersistentForm<T extends Record<string, any>>(
    options: PersistentFormOptions<T>,
) {
    const context = options.getContext?.() ?? {};
    const excludedKeys = new Set<keyof T>(options.excludeKeys ?? []);
    const restoredData = loadDraft<Partial<T>>(options.formId, context);
    const initialData = { ...options.initialData };
    let isRestored = $state(restoredData !== null);
    let lastSavedAt = $state<number | null>(null);

    if (restoredData !== null) {
        for (const [key, value] of Object.entries(restoredData) as [keyof T, T[keyof T]][]) {
            if (!excludedKeys.has(key)) {
                initialData[key] = value;
            }
        }
        options.onRestored?.(restoredData);
    }

    const form = useForm<T>(initialData);
    const debounceMs = options.debounceMs ?? 500;

    let pendingSave: ReturnType<typeof setTimeout> | undefined;

    $effect(() => {
        const data = form.data();
        const draftData = {} as Partial<T>;

        for (const [key, value] of Object.entries(data) as [keyof T, T[keyof T]][]) {
            if (!excludedKeys.has(key)) {
                draftData[key] = value;
            }
        }

        pendingSave = setTimeout(() => {
            pendingSave = undefined;
            if (saveDraft(options.formId, context, draftData, options.ttlMs)) {
                lastSavedAt = Date.now();
            }
        }, debounceMs);

        return () => {
            if (pendingSave !== undefined) {
                clearTimeout(pendingSave);
                pendingSave = undefined;
            }
        };
    });

    function clearSavedDraft(): void {
        if (pendingSave !== undefined) {
            clearTimeout(pendingSave);
            pendingSave = undefined;
        }

        clearDraft(options.formId);
        isRestored = false;
        lastSavedAt = null;
    }

    type SubmitOptions = Parameters<typeof form.post>[1];
    const withDraftClearedOnSuccess = (submitOptions?: SubmitOptions): SubmitOptions => ({
        ...submitOptions,
        onSuccess: (page) => {
            clearSavedDraft();
            submitOptions?.onSuccess?.(page);
        },
    });

    const originalPost = form.post.bind(form);
    form.post = (url, submitOptions) => {
        originalPost(url, withDraftClearedOnSuccess(submitOptions));
    };

    const originalSubmit = form.submit.bind(form);
    form.submit = ((...args: Parameters<typeof form.submit>) => {
        const submitArgs = [...args] as unknown[];
        const firstArgument = args[0];
        const optionsIndex = typeof firstArgument === 'string'
            ? 2
            : typeof firstArgument === 'object' && firstArgument !== null && 'url' in firstArgument
              ? 1
              : 0;
        submitArgs[optionsIndex] = withDraftClearedOnSuccess(
            submitArgs[optionsIndex] as SubmitOptions | undefined,
        );
        originalSubmit(...(submitArgs as Parameters<typeof form.submit>));
    }) as typeof form.submit;

    return {
        form,
        get isRestored() {
            return isRestored;
        },
        clearSavedDraft,
        get lastSavedAt() {
            return lastSavedAt;
        },
    };
}
