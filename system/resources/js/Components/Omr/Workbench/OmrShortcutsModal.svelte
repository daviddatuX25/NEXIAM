<script lang="ts">
    import * as Dialog from '@/Components/ui/dialog';

    let { open = false, onClose }: { open?: boolean; onClose: () => void } =
        $props();
    const shortcuts = [
        {
            key: 'Space',
            action: 'Capture the current page',
            context: 'Camera ready',
        },
        { key: 'U', action: 'Upload an image', context: 'Camera view' },
        { key: '?', action: 'Open keyboard shortcuts', context: 'Console' },
        {
            key: 'Esc',
            action: 'Close a dialog or return to camera',
            context: 'Review navigation preserves unsaved changes',
        },
    ];
</script>

<Dialog.Root
    {open}
    onOpenChange={(value: boolean) => {
        if (!value) onClose();
    }}
>
    <Dialog.Content
        portalProps={{}}
        showCloseButton={false}
        class="bg-card text-foreground max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-lg"
    >
        <Dialog.Header class="pr-8">
            <Dialog.Title class="text-base font-semibold"
                >Keyboard shortcuts</Dialog.Title
            >
            <Dialog.Description class="text-muted-foreground text-sm"
                >Shortcuts pause while editing a field or using a dialog.</Dialog.Description
            >
        </Dialog.Header>
        <dl class="divide-border divide-y">
            {#each shortcuts as shortcut (shortcut.key)}
                <div class="flex items-start justify-between gap-4 py-3">
                    <div class="min-w-0">
                        <dt class="text-sm font-medium">{shortcut.action}</dt>
                        <dd class="text-muted-foreground mt-1 text-xs">
                            {shortcut.context}
                        </dd>
                    </div>
                    <kbd
                        class="border-border bg-muted shrink-0 rounded border px-2 py-1 text-xs"
                        >{shortcut.key}</kbd
                    >
                </div>
            {/each}
        </dl>
        <Dialog.Footer class="flex justify-end">
            <button
                type="button"
                class="bg-primary text-primary-foreground focus-visible:outline-ring rounded-md px-4 py-2 text-sm font-medium focus-visible:outline-2"
                onclick={onClose}>Close</button
            >
        </Dialog.Footer>
    </Dialog.Content>
</Dialog.Root>
