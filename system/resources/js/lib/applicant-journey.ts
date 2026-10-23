export type JourneyStatus = 'done' | 'current' | 'action' | 'auto' | 'upcoming';

export const JOURNEY_STATUS_APPEARANCE: Record<JourneyStatus, {
  label: string;
  nodeClass: string;
  iconClass: string;
  pulse: boolean;
}> = {
  done: {
    label: 'Completed',
    nodeClass: 'border-primary bg-primary text-primary-foreground',
    iconClass: 'text-primary-foreground',
    pulse: false,
  },
  current: {
    label: 'Current step',
    nodeClass: 'border-primary bg-primary/10 text-primary ring-4 ring-primary/10',
    iconClass: 'text-primary',
    pulse: true,
  },
  action: {
    label: 'Action needed',
    nodeClass: 'border-amber-500 bg-amber-50 text-amber-700 ring-4 ring-amber-500/10 dark:bg-amber-950/40 dark:text-amber-300',
    iconClass: 'text-amber-700 dark:text-amber-300',
    pulse: true,
  },
  auto: {
    label: 'In progress automatically',
    nodeClass: 'border-sky-500 bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300',
    iconClass: 'text-sky-700 dark:text-sky-300',
    pulse: false,
  },
  upcoming: {
    label: 'Upcoming',
    nodeClass: 'border-muted-foreground/30 bg-background text-muted-foreground',
    iconClass: 'text-muted-foreground',
    pulse: false,
  },
};

export function resolveJourneyStatusAppearance(status: string) {
  return JOURNEY_STATUS_APPEARANCE[status as JourneyStatus] ?? JOURNEY_STATUS_APPEARANCE.upcoming;
}

export function buildJourneyPositions(pipeline: Array<{ steps?: Array<{ id: string }> }> = []) {
  const steps = pipeline.flatMap((phase) => phase.steps ?? []);
  const positions = Object.fromEntries(steps.map((step, index) => [step.id, {
    index,
    isFirst: index === 0,
    isLast: index === steps.length - 1,
  }]));

  return {
    totalSteps: steps.length,
    firstStepId: steps[0]?.id ?? null,
    lastStepId: steps.at(-1)?.id ?? null,
    positions,
    isLast(stepId: string) {
      return positions[stepId]?.isLast ?? false;
    },
  };
}
