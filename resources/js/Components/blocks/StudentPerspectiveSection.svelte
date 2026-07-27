<script>
  import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card';
  import { FileEdit, Calendar, Eye, GraduationCap } from 'lucide-svelte';

  let {
    items = [
      {
        title: 'Apply Online',
        description: 'Submit your application from anywhere. Book an appointment or walk in—no long queues.',
        icon: FileEdit,
      },
      {
        title: 'Track Your Schedule',
        description: 'View your exam date, time, and room. Get notifications when your schedule is published.',
        icon: Calendar,
      },
      {
        title: 'Monitor Progress',
        description: 'See your status at every step—application, exam, grading, and consultation.',
        icon: Eye,
      },
      {
        title: 'Get Your Results',
        description: 'Receive your consultation summary and course recommendation through the applicant portal.',
        icon: GraduationCap,
      },
    ]
  } = $props();
</script>

<section class="py-16 md:py-24 px-4 bg-muted/20">
  <div class="max-w-7xl mx-auto">
    <div class="text-center mb-12">
      <h2 class="text-3xl md:text-4xl font-bold mb-4">
        For Applicants
      </h2>
      <p class="text-lg text-muted-foreground max-w-2xl mx-auto">
        What SecureCAT does for you—apply, track, and receive your results in one place
      </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      {#each items as item}
        <Card class="border-l-4 border-l-primary hover:shadow-md transition-shadow duration-300">
          <CardHeader>
            <div class="flex items-start gap-3">
              <div class="p-2 rounded-lg bg-primary/10 text-primary" aria-label={item.title}>
                <svelte:component this={item.icon} class="w-6 h-6" />
              </div>
              <CardTitle class="text-lg">
                {item.title}
              </CardTitle>
            </div>
          </CardHeader>
          <CardContent>
            <p class="text-sm text-muted-foreground leading-relaxed">
              {item.description}
            </p>
          </CardContent>
        </Card>
      {/each}
    </div>
  </div>
</section>
