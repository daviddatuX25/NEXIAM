<script>
  import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card';
  import { Badge } from '@/Components/ui/badge';
  import { Database, BarChart3, TrendingUp, LineChart } from 'lucide-svelte';

  let {
    items = [
      {
        title: 'MIS SIS Integration',
        description: 'Connect with student info systems to sync enrollment data, exam history, and progression—no duplicate data entry.',
        icon: Database,
      },
      {
        title: 'Guidance Analytics',
        description: 'See where students commonly struggle—identify weak domains and topics to target support and prep resources.',
        icon: BarChart3,
      },
      {
        title: 'Consultation Success Rate',
        description: 'Track how often recommendations lead to enrollment or success. Measure the impact of guidance decisions.',
        icon: TrendingUp,
      },
      {
        title: 'Long-Term Monitoring',
        description: 'Cohort and trend analytics for admissions, exam performance, and outcomes—year-over-year insights.',
        icon: LineChart,
      },
    ]
  } = $props();
</script>

<section class="py-16 md:py-24 px-4 bg-background">
  <div class="max-w-7xl mx-auto">
    <div class="text-center mb-12">
      <h2 class="text-3xl md:text-4xl font-bold mb-4">
        Future Vision
      </h2>
      <p class="text-lg text-muted-foreground max-w-2xl mx-auto">
        SecureCAT will grow to support deeper analytics, MIS/SIS integration, and long-term monitoring
      </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      {#each items as item}
        <Card class="hover:shadow-lg transition-shadow duration-300">
          <CardHeader class="space-y-3">
            <div class="flex items-start gap-3">
              <div class="p-2 rounded-lg bg-muted text-muted-foreground shrink-0" aria-label={item.title}>
                <svelte:component this={item.icon} class="w-6 h-6" />
              </div>
              <CardTitle class="text-lg min-w-0">
                {item.title}
              </CardTitle>
            </div>
            <Badge variant="outline" class="text-xs w-fit">Coming soon</Badge>
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
