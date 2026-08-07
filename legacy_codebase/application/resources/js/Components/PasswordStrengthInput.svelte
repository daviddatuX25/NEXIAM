<script>
  import { Input } from '@/Components/ui/input';
  import { Button } from '@/Components/ui/button';
  import { Eye, EyeOff, Check } from 'lucide-svelte';
  import {
    getRequirements,
    allRequirementsMet,
    getStrengthScore,
    getStrengthLabel,
    passwordsMatch,
  } from '@/lib/password-strength.js';

  let {
    password = $bindable(''),
    passwordConfirmation = $bindable(''),
    showConfirmation = true,
    id = 'password',
    label = 'Password',
    errors = {},
    confirmationLabel = 'Confirm password',
    passwordError,
    confirmationError,
  } = $props();

  let showPassword = $state(false);
  let showConfirmationPassword = $state(false);

  const requirements = $derived(getRequirements(password));
  const valid = $derived(allRequirementsMet(password));
  const strengthScore = $derived(getStrengthScore(password));
  const strengthLabel = $derived(getStrengthLabel(strengthScore));
  const match = $derived(showConfirmation ? passwordsMatch(password, passwordConfirmation) : true);
  const allValid = $derived(valid && (showConfirmation ? match : true));

  const pwError = $derived(passwordError ?? errors?.password);
  const confError = $derived(confirmationError ?? errors?.password_confirmation);
  const pwErrorText = $derived(
    pwError ? (typeof pwError === 'string' ? pwError : pwError[0]) : null
  );
  const confErrorText = $derived(
    confError ? (typeof confError === 'string' ? confError : confError[0]) : null
  );
</script>

<div class="space-y-4">
  <div class="space-y-2">
    <label for={id} class="text-sm font-medium leading-none">{label}</label>
    <div class="relative">
      <Input
        {id}
        type={showPassword ? 'text' : 'password'}
        bind:value={password}
        placeholder="••••••••"
        autocomplete="new-password"
        required
        aria-invalid={!!pwErrorText}
        aria-describedby={pwErrorText ? `${id}-error` : `${id}-requirements`}
        class="pr-10"
      />
      <Button
        type="button"
        variant="ghost"
        size="icon-sm"
        class="absolute right-1 top-1/2 -translate-y-1/2 size-8 text-muted-foreground hover:text-foreground"
        onclick={() => (showPassword = !showPassword)}
        aria-label={showPassword ? 'Hide password' : 'Show password'}
      >
        {#if showPassword}
          <EyeOff class="size-4" />
        {:else}
          <Eye class="size-4" />
        {/if}
      </Button>
    </div>

    <!-- Strength meter -->
    <div
      id="{id}-strength"
      role="status"
      aria-live="polite"
      aria-label="Password strength: {strengthLabel}"
      class="mt-1.5"
    >
      <div class="flex gap-1">
        {#each [0, 1, 2, 3, 4] as segment}
          <div
            class="h-1 flex-1 rounded-full transition-colors"
            class:bg-destructive={strengthScore >= 1 && segment < strengthScore && strengthScore <= 2}
            class:bg-muted={strengthScore === 0 || segment >= strengthScore}
            class:bg-yellow-500={strengthScore === 3 && segment < strengthScore}
            class:bg-primary={strengthScore >= 4 && segment < strengthScore}
          >
          </div>
        {/each}
      </div>
    </div>

    <!-- Requirements checklist -->
    <ul id="{id}-requirements" class="mt-2 space-y-1 text-sm text-muted-foreground">
      <li class="flex items-center gap-2">
        {#if requirements.minLength}
          <Check class="size-4 shrink-0 text-primary" aria-hidden="true" />
          <span class="text-primary">At least 8 characters</span>
        {:else}
          <span class="size-4 shrink-0" aria-hidden="true">•</span>
          <span>At least 8 characters</span>
        {/if}
      </li>
      <li class="flex items-center gap-2">
        {#if requirements.hasUppercase}
          <Check class="size-4 shrink-0 text-primary" aria-hidden="true" />
          <span class="text-primary">One uppercase letter</span>
        {:else}
          <span class="size-4 shrink-0" aria-hidden="true">•</span>
          <span>One uppercase letter</span>
        {/if}
      </li>
      <li class="flex items-center gap-2">
        {#if requirements.hasLowercase}
          <Check class="size-4 shrink-0 text-primary" aria-hidden="true" />
          <span class="text-primary">One lowercase letter</span>
        {:else}
          <span class="size-4 shrink-0" aria-hidden="true">•</span>
          <span>One lowercase letter</span>
        {/if}
      </li>
      <li class="flex items-center gap-2">
        {#if requirements.hasNumber}
          <Check class="size-4 shrink-0 text-primary" aria-hidden="true" />
          <span class="text-primary">One number</span>
        {:else}
          <span class="size-4 shrink-0" aria-hidden="true">•</span>
          <span>One number</span>
        {/if}
      </li>
    </ul>

    {#if pwErrorText}
      <p id="{id}-error" class="text-sm text-destructive">{pwErrorText}</p>
    {/if}
  </div>

  {#if showConfirmation}
    <div class="space-y-2">
      <label for="{id}-confirmation" class="text-sm font-medium leading-none"
        >{confirmationLabel}</label
      >
      <div class="relative">
        <Input
          id="{id}-confirmation"
          type={showConfirmationPassword ? 'text' : 'password'}
          bind:value={passwordConfirmation}
          placeholder="••••••••"
          autocomplete="new-password"
          required
          aria-invalid={!!confErrorText}
          aria-describedby={confErrorText ? `${id}-confirmation-error` : `${id}-confirmation-match`}
          class="pr-10"
        />
        <Button
          type="button"
          variant="ghost"
          size="icon-sm"
          class="absolute right-1 top-1/2 -translate-y-1/2 size-8 text-muted-foreground hover:text-foreground"
          onclick={() => (showConfirmationPassword = !showConfirmationPassword)}
          aria-label={showConfirmationPassword ? 'Hide password' : 'Show password'}
        >
          {#if showConfirmationPassword}
            <EyeOff class="size-4" />
          {:else}
            <Eye class="size-4" />
          {/if}
        </Button>
      </div>

      {#if passwordConfirmation.length > 0}
        <p
          id="{id}-confirmation-match"
          class="text-sm"
          class:text-primary={match}
          class:text-destructive={!match}
        >
          {match ? 'Passwords match' : "Passwords don't match"}
        </p>
      {/if}

      {#if confErrorText}
        <p id="{id}-confirmation-error" class="text-sm text-destructive">{confErrorText}</p>
      {/if}
    </div>
  {/if}
</div>
