<script>
  import { onMount } from 'svelte';
  import * as Card from '@/Components/ui/card';
  import { Lightbulb, Info, RefreshCw } from 'lucide-svelte';
  import { Button } from '@/Components/ui/button';

  const tips = [
    { type: 'Tip', text: 'Check your applicant portal regularly for updates on your exam schedule.' },
    { type: 'Trivia', text: 'SecureCAT processes over 10,000 applicants per admission season!' },
    { type: 'Tip', text: 'Make sure to bring your printed admission slip and a valid ID on exam day.' },
    { type: 'Trivia', text: 'The university main building was established in 1948 and is a heritage site.' },
    { type: 'Tip', text: 'You can use the AI Companion later to get course recommendations based on your scores.' },
    { type: 'Tip', text: 'Arrive at the testing center at least 30 minutes before your scheduled time.' }
  ];

  let currentIndex = $state(0);
  let interval;

  function nextTip() {
    currentIndex = (currentIndex + 1) % tips.length;
  }

  onMount(() => {
    interval = setInterval(nextTip, 10000); // rotate every 10 seconds
    return () => clearInterval(interval);
  });
</script>

<Card.Root class="overflow-hidden bg-gradient-to-br from-primary/5 to-transparent border-primary/10 transition-all duration-500 hover:shadow-md">
  <Card.Header class="pb-2 flex flex-row items-center justify-between">
    <div class="space-y-1">
      <Card.Title class="text-sm font-medium text-primary flex items-center gap-2">
        {#if tips[currentIndex].type === 'Tip'}
          <Lightbulb class="h-4 w-4" />
        {:else}
          <Info class="h-4 w-4" />
        {/if}
        {tips[currentIndex].type} of the day
      </Card.Title>
    </div>
    <Button variant="ghost" size="icon" class="h-6 w-6 text-muted-foreground hover:text-primary transition-colors" aria-label="Next tip" onclick={nextTip}>
      <RefreshCw class="h-3 w-3" />
    </Button>
  </Card.Header>
  <Card.Content>
    <div class="h-[60px] flex items-center">
      {#key currentIndex}
        <p class="text-sm text-foreground/80 leading-relaxed animate-in fade-in zoom-in-95 slide-in-from-bottom-2 duration-300">
          {tips[currentIndex].text}
        </p>
      {/key}
    </div>
  </Card.Content>
</Card.Root>
