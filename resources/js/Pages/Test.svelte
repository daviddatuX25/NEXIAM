<script>
  import { Button } from '@/Components/ui/button';
  import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/Components/ui/card';
  import { Input } from '@/Components/ui/input';
  
  let { message = 'Hello from Inertia + Svelte 5!' } = $props();
  
  let testInput = $state('');
  
  function handleClick() {
    alert(`You entered: ${testInput || '(nothing)'}`);
  }
</script>

<div class="min-h-screen flex items-center justify-center bg-background p-4">
  <Card class="w-full max-w-md">
    <CardHeader>
      <CardTitle class="text-2xl">Welcome to SecureCAT</CardTitle>
      <CardDescription>
        Inertia.js v2 + Svelte 5 + TailwindCSS 4 + shadcn-svelte
      </CardDescription>
    </CardHeader>
    <CardContent class="space-y-4">
      <div class="rounded-lg bg-muted p-4 border border-border">
        <p class="text-sm font-medium text-card-foreground">{message}</p>
        <p class="text-xs text-muted-foreground mt-2">
          This page is rendered via Inertia.js v2 with Svelte 5 runes!
        </p>
      </div>
      
      <div class="space-y-2">
        <label for="test-input" class="text-sm font-medium text-foreground">
          Test Input Component
        </label>
        <Input
          id="test-input"
          bind:value={testInput}
          placeholder="Type something to test..."
          class="w-full"
        />
      </div>
      
      <div class="flex gap-2 pt-2">
        <Button onclick={handleClick} variant="default" class="flex-1">
          Test Button
        </Button>
        <Button variant="outline" class="flex-1">
          Outline
        </Button>
      </div>
      
      <div class="pt-4 border-t border-border">
        <p class="text-xs text-muted-foreground text-center">
          ✓ Svelte 5 Runes • ✓ Inertia v2 • ✓ TailwindCSS 4 • ✓ shadcn-svelte
        </p>
      </div>
    </CardContent>
  </Card>
</div>
