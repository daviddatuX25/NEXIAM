<script>
    import { Badge } from '@/Components/ui/badge';
    import { Button } from '@/Components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogHeader,
        DialogTitle,
        DialogTrigger,
    } from '@/Components/ui/dialog';
    import { Input } from '@/Components/ui/input';
    import { Label } from '@/Components/ui/label';
    import { router } from '@inertiajs/svelte';
    import { CheckCircle2, FileText, KeyRound, Plus } from 'lucide-svelte';

    let { can_view_question_content = false, kit_context = null } = $props();

    let open = $state(false);
    let name = $state(`ICAT Answerkeys ${new Date().getFullYear()}`);
    let mode = $state('answer_keys_only');
    let isSynthetic = $state(false);
    let saving = $state(false);

    function createBooklet() {
        if (saving || !name.trim()) return;
        saving = true;
        const url = new URL('/setup/exam-materials/booklets', window.location.origin);
        if (kit_context) {
            url.searchParams.set('test_kit_id', String(kit_context.test_kit_id));
            if (kit_context.preparation_id !== null) {
                url.searchParams.set('preparation_id', String(kit_context.preparation_id));
            }
            if (kit_context.test_kit_version_id !== null) {
                url.searchParams.set('test_kit_version_id', String(kit_context.test_kit_version_id));
            }
            if (kit_context.authoring_booklet_version_id !== null) {
                url.searchParams.set(
                    'authoring_booklet_version_id',
                    String(kit_context.authoring_booklet_version_id),
                );
            }

        }
        router.post(
            `${url.pathname}${url.search}`,
            {
                name: name.trim(),
                authoring_mode: mode,
                is_synthetic: isSynthetic,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    open = false;
                    name = '';
                },
                onFinish: () => (saving = false),
            },
        );
    }
</script>

<Dialog bind:open>
    <DialogTrigger>
        {#snippet child({ props })}
            <Button {...props} class="min-h-11 gap-2">
                <Plus class="size-4" />New Booklet
            </Button>
        {/snippet}
    </DialogTrigger>
    <DialogContent class="sm:max-w-lg">
        <DialogHeader>
            <DialogTitle>New Booklet</DialogTitle>
            <DialogDescription>
                Choose how this Booklet's questions and keys are managed.
            </DialogDescription>
        </DialogHeader>

        <div class="space-y-5 py-2">
            <div class="space-y-2">
                <Label for="new-booklet-name">Booklet name</Label>
                <Input
                    id="new-booklet-name"
                    bind:value={name}
                    placeholder={`e.g. ICAT Answerkeys ${new Date().getFullYear()}`}
                />
            </div>

            <fieldset class="space-y-2">
                <legend class="flex w-full items-center justify-between pb-1 text-sm font-medium">
                    <span>Booklet Mode & Questions</span>
                    <span class="text-xs font-normal text-muted-foreground">Default: Answer keys only</span>
                </legend>

                <div class="grid gap-2.5">
                    <label
                        class="border-border has-checked:border-sky-500 has-checked:bg-sky-500/5 dark:has-checked:bg-sky-500/10 relative flex cursor-pointer items-start gap-3 rounded-xl border p-3.5 transition-all"
                    >
                        <input
                            type="radio"
                            bind:group={mode}
                            value="answer_keys_only"
                            class="mt-1 size-4 text-sky-600 focus:ring-sky-500"
                        />
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <KeyRound class="size-4 text-sky-600 dark:text-sky-400 shrink-0" />
                                <strong class="text-sm font-semibold text-foreground">Answer keys only (External paper booklets)</strong>
                                <Badge variant="outline" class="border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300 text-[10px] font-bold">
                                    Default · Recommended
                                </Badge>
                            </div>
                            <p class="text-muted-foreground text-xs leading-relaxed">
                                Questions remain in externally printed paper booklets. Staff declare each section’s literal response tokens and enter its exact required key tokens.
                            </p>
                            {#if mode === 'answer_keys_only'}
                                <div class="mt-2 flex items-center gap-1.5 text-[11px] font-medium text-sky-700 dark:text-sky-300">
                                    <CheckCircle2 class="size-3.5" />
                                    <span>Fast setup: No question stems or choices required in NEXIAM.</span>
                                </div>
                            {/if}
                        </div>
                    </label>

                    <label
                        class="border-border has-checked:border-purple-500 has-checked:bg-purple-500/5 dark:has-checked:bg-purple-500/10 relative flex cursor-pointer items-start gap-3 rounded-xl border p-3.5 transition-all"
                        class:opacity-50={!can_view_question_content}
                    >
                        <input
                            type="radio"
                            bind:group={mode}
                            value="stored_questions"
                            disabled={!can_view_question_content}
                            class="mt-1 size-4 text-purple-600 focus:ring-purple-500"
                        />
                        <div class="min-w-0 flex-1 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <FileText class="size-4 text-purple-600 dark:text-purple-400 shrink-0" />
                                <strong class="text-sm font-semibold text-foreground">Questions and answers (Stored questions)</strong>
                            </div>
                            <p class="text-muted-foreground text-xs leading-relaxed">
                                Author and securely store questions and choices directly inside NEXIAM. Use this only if maintaining an internal digital question bank.
                            </p>
                            {#if !can_view_question_content}
                                <p class="text-xs text-amber-600 dark:text-amber-400">
                                    Requires question authoring permissions.
                                </p>
                            {/if}
                        </div>
                    </label>
                </div>
            </fieldset>

            <label class="flex items-start gap-3 rounded-lg border border-border p-3 text-sm">
                <input
                    type="checkbox"
                    class="mt-0.5 size-4 accent-primary"
                    bind:checked={isSynthetic}
                />
                <span>
                    <span class="block font-medium">Synthetic test material</span>
                    <span class="mt-1 block text-xs text-muted-foreground">
                        Mark only purpose-created test content as synthetic. Leave unchecked for institution-owned source material.
                    </span>
                </span>
            </label>
        </div>

        <DialogFooter>
            <Button
                onclick={createBooklet}
                disabled={saving ||
                    !name.trim() ||
                    (answer_sheets.length > 1 && !templateVersionId && !kit_context)}
            >
                {saving ? 'Creating Booklet…' : 'Create Booklet'}
            </Button>
        </DialogFooter>
    </DialogContent>
</Dialog>
