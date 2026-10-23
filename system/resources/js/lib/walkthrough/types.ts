import type { XiamAvatarEmotion } from '@/lib/xiam-avatar-reducer';

export const PHASE_DEFAULT_EMOTIONS: Record<TourPhase, TourEmotion> = {
    overview: 'hi',
    orientation: 'hi',
    concept: 'thinking',
    workflow: 'talking-presenting',
    action: 'talking-presenting',
    guidance: 'thumbs-up',
    milestone: 'celebrating',
    diagnostic: 'shock',
    recovery: 'sad',
};

export type TourEmotion = XiamAvatarEmotion;

export type TourPersona =
    | 'staff-copilot' // Professional, sharp, supportive institutional co-pilot for admins and staff
    | 'applicant-mentor' // Warm, empathetic, calming, encouraging guide/older-sibling for students & applicants
    | 'exam-administrator-guide'; // Procedure-focused, calm, checklist-oriented guide for Test Administrators

export type TourDomain =
    'setup' | 'admissions' | 'examinations' | 'consultation' | 'applicant';

export type TourPhase =
    | 'overview' // Welcome & Purpose of the page
    | 'concept' // Foundational Domain Mental Model & Rules
    | 'workflow' // Operational Procedure & How to use controls
    | 'guidance' // Best practices, Pro-tips, and Policy reassurance
    | 'orientation' // Backward-compatible alias for overview
    | 'action' // Backward-compatible alias for workflow
    | 'diagnostic'
    | 'milestone'
    | 'recovery';

export interface PersonaAppearance {
    readonly badgeLabel: string;
    readonly badgeClass: string;
    readonly bubbleBorderClass: string;
    readonly accentClass: string;
    readonly avatarContainerClass: string;
}

export const PERSONA_CONFIGS: Record<TourPersona, PersonaAppearance> = {
    'staff-copilot': {
        badgeLabel: 'Xiam • Staff Co-pilot',
        badgeClass:
            'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20',
        bubbleBorderClass:
            'border-emerald-500/25 bg-card/80 backdrop-blur-xs shadow-xs',
        accentClass: 'text-primary',
        avatarContainerClass: '',
    },
    'applicant-mentor': {
        badgeLabel: 'Xiam • Student Guide',
        badgeClass:
            'bg-amber-500/15 text-amber-800 dark:text-amber-300 border border-amber-500/25',
        bubbleBorderClass:
            'border-amber-500/30 bg-card/80 backdrop-blur-xs shadow-xs',
        accentClass: 'text-amber-600 dark:text-amber-400',
        avatarContainerClass: '',
    },
    'exam-administrator-guide': {
        badgeLabel: 'Xiam • Test Administrator Guide',
        badgeClass:
            'bg-blue-500/15 text-blue-700 dark:text-blue-300 border border-blue-500/25',
        bubbleBorderClass:
            'border-blue-500/30 bg-card/80 backdrop-blur-xs shadow-xs',
        accentClass: 'text-blue-600 dark:text-blue-400',
        avatarContainerClass: '',
    },
};

export interface TourContextBase {
    readonly entityKey: string;
    // Changes only when legal instruction or branch meaning changes
    readonly safetyKey: string;
    readonly freshness: 'current' | 'loading' | 'stale' | 'error';
}

export type TourValue<C, V> = V | ((ctx: Readonly<C>) => V);
export type TourPredicate<C> = (ctx: Readonly<C>) => boolean;

export interface TourNotice {
    readonly severity: 'info' | 'warning' | 'blocked';
    readonly text: string;
}

export type TourDestination =
    | { readonly kind: 'step'; readonly stepId: string }
    | { readonly kind: 'complete' };

export interface TourTarget {
    readonly dataTour: string;
    readonly scopeKey?: string;
    readonly placement?: 'auto' | 'top' | 'bottom' | 'left' | 'right';
}

export interface TourChoice<C = any> {
    readonly id: string;
    readonly label: TourValue<C, string>;
    readonly when?: TourPredicate<C>;
    readonly destination: TourDestination;
}

export type TourAdvance<C = any> =
    | { readonly kind: 'next'; readonly destination: TourDestination }
    | {
          readonly kind: 'choices';
          readonly options: readonly TourChoice<C>[];
          readonly fallback?: TourDestination;
      };

export interface TourStep<C = any> {
    readonly id: string;
    readonly phase?: TourPhase;
    readonly title: TourValue<C, string>;
    readonly text: TourValue<C, string>;
    readonly emotion?: TourEmotion;
    readonly personaOverride?: TourPersona;
    readonly speechMode?: 'typewriter' | 'instant';
    readonly notice?: TourValue<C, TourNotice | null>;
    readonly target?: TourValue<C, TourTarget | null>;
    readonly when?: TourPredicate<C>;
    readonly otherwise?: TourDestination;
    readonly advance: TourAdvance<C>;
    readonly allowAskAi?: boolean;
}

export interface TourDefinition<C = any> {
    readonly id: string;
    readonly revision: number;
    readonly variant?: string;
    readonly title: string;
    readonly domain?: TourDomain;
    readonly persona?: TourPersona;
    readonly start: string;
    readonly diagnostic?: string;
    readonly notice?: TourNotice | null;
    readonly steps: readonly TourStep<C>[];
    readonly allowAskAi?: boolean;
}

// ================= RESOLVED IMMUTABLE GRAPH (Consumed by Player) =================

export interface ResolvedTourChoice {
    readonly id: string;
    readonly label: string;
    readonly destination: TourDestination;
}

export type ResolvedTourAdvance =
    | { readonly kind: 'next'; readonly destination: TourDestination }
    | {
          readonly kind: 'choices';
          readonly options: readonly ResolvedTourChoice[];
      };

export interface ResolvedTourStep {
    readonly id: string;
    readonly phase: TourPhase;
    readonly title: string;
    readonly text: string;
    readonly emotion: TourEmotion;
    readonly personaOverride?: TourPersona;
    readonly speechMode: 'typewriter' | 'instant';
    readonly notice: TourNotice | null;
    readonly target: TourTarget | null;
    readonly advance: ResolvedTourAdvance;
    readonly allowAskAi?: boolean;
}

export interface ResolvedTour {
    readonly id: string;
    readonly revision: number;
    readonly variant: string;
    readonly instanceKey: string;
    readonly title: string;
    readonly domain?: TourDomain;
    readonly persona?: TourPersona;
    readonly start: string;
    readonly diagnostic?: string;
    readonly notice: TourNotice | null;
    readonly steps: readonly ResolvedTourStep[];
    readonly allowAskAi?: boolean;
}

export type TourFactory<C extends TourContextBase = any> = (
    ctx: Readonly<C>,
) => TourDefinition<C>;

// ================= DOMAIN CONTEXT SPECIFICATIONS =================

export type ApplicantScheduleState =
    | 'denied'
    | 'pending_acceptance'
    | 'waiting_for_reservation_open'
    | 'calendar_open'
    | 'assigned_tentative'
    | 'assigned_published';

export interface ApplicantScheduleContext extends TourContextBase {
    readonly pageState: ApplicantScheduleState;
    readonly hasAssignedSeatInstruction?: boolean;
    readonly message: string;
    readonly needsCorrection: boolean;
    readonly canOpenCorrection: boolean;
    readonly slotCount: number;
    readonly availableSlotCount: number;
    readonly reservationError: string | null;
    readonly appointment: {
        readonly date: string | null;
        readonly startTime: string | null;
        readonly endTime: string | null;
        readonly room: string | null;
        readonly published: boolean;
        readonly source: 'staff_assigned' | 'applicant_reserved' | 'unknown';
    } | null;
    readonly schedulingPolicy?: {
        readonly kind: 'self-reservation' | 'staff-managed' | 'mixed';
        readonly officeLabel: string | null;
    } | null;
}
export interface OmrScannerContext extends TourContextBase {
    readonly hasPendingCapture: boolean;
    readonly captureStatus: string | null;
    readonly cameraState: 'ready' | 'starting' | 'unavailable';
    readonly isResolvedSession: boolean;
    readonly manualIdentityAllowed: boolean;
    readonly displayCodeAllowed: boolean;
    readonly isBlocked: boolean;
    readonly recoveryCode: string | null;
    readonly recoveryOwner: string | null;
    readonly candidateCount: number;
    readonly sessionCode: string | null;
    readonly roomName: string | null;
}
export interface GradingDashboardContext extends TourContextBase {
    readonly pageState?: string;
    readonly sessionsCount?: number;
    readonly aptitudeAreasCount?: number;
    readonly isDegraded?: boolean;
    readonly inProgressCount?: number;
    readonly finalizedCount?: number;
    readonly repairRequiredCount?: number;
    readonly canImportScores?: boolean;
}
export interface ReleaseIndexContext extends TourContextBase {
    readonly pageState: string;
    readonly hasActiveTemplate: boolean;
    readonly activeTemplateName: string | null;
    readonly canManageTemplates: boolean;
    readonly sessionsCount: number;
    readonly totalExaminees: number;
    readonly totalReleased: number;
    readonly finalizedSessionsCount: number;
}
export interface ApplicantProgramChoiceContext extends TourContextBase {
    readonly pageState: string;
    readonly isResultReleased: boolean;
    readonly isAdvised: boolean;
    readonly selectedProgramCode: string | null;
    readonly isAcknowledged: boolean;
    readonly selectionSource: 'guidance_default' | 'student_self' | 'none';
    readonly isEnrollingOrEnrolled: boolean;
    readonly hasDefaultProgram: boolean;
    readonly defaultProgramName: string | null;
    readonly alternativeOptionsCount: number;
    readonly canStillChoose: boolean;
}
export interface ExamMonitoringContext extends TourContextBase {
    readonly pageState: string;
    readonly isLive: boolean;
    readonly sessionsCount: number;
    readonly activeRoomsCount: number;
    readonly totalRoomsCount: number;
    readonly totalScheduled: number;
    readonly presentCount: number;
    readonly absentCount: number;
    readonly absencePercent: number;
    readonly absenceWarning: boolean;
    readonly canManage: boolean;
}
export interface DataIngestionContext extends TourContextBase {
    readonly activePreset: string;
    readonly hasAcademicCycle: boolean;
    readonly academicCycleLabel: string | null;
    readonly hasSelectedFile: boolean;
    readonly isAnalyzing: boolean;
    readonly hasAnalysisResult: boolean;
    readonly detectedSheetsCount: number;
    readonly recentBatchesCount: number;
    readonly pendingReviewCount: number;
    readonly builderOpen?: boolean;
    readonly hasTemplateSchema?: boolean;
    readonly hasFieldDetails?: boolean;
    readonly hasUploader?: boolean;
    readonly hasTemplateEntry?: boolean;
}
export interface EnrollmentEligibilityContext extends TourContextBase {
    readonly pageState: 'search' | 'card';
    readonly hasQuery: boolean;
    readonly resultsCount: number;
    readonly hasCard: boolean;
    readonly candidateName: string | null;
    readonly isEligible: boolean;
    readonly missingFacts: string[];
}
export interface AdmissionSupportSetupContext extends TourContextBase {
    readonly hasActiveCycle: boolean;
    readonly cycleName: string | null;
    readonly hasActiveBenchmark: boolean;
    readonly activeBenchmarkVersion: string | null;
    readonly activeBenchmarkHash: string | null;
    readonly programsCount: number;
    readonly aptitudeAreasCount: number;
    readonly historySetsCount: number;
    readonly tabFmStatus: string;
}
export interface SetupReadinessContext extends TourContextBase {
    readonly overallPercent: number;
    readonly passedCount: number;
    readonly totalCount: number;
    readonly isFullyReady: boolean;
    readonly hasCriticalBlockers: boolean;
    readonly criticalBlockerCount: number;
    readonly firstFailingCategoryTitle: string | null;
    readonly firstFailingCategoryDestination: string | null;
    readonly groupsCount: number;
}
export interface ExamSessionsContext extends TourContextBase {
    readonly pageState: string;
    readonly sessionsCount: number;
    readonly draftCount: number;
    readonly publishedCount: number;
    readonly openReservationCount: number;
    readonly staffOnlyCount: number;
    readonly selectedSessionsCount: number;
    readonly totalCapacity: number;
    readonly totalAssigned: number;
}
export interface ConsultationSchedulingContext extends TourContextBase {
    readonly pageState: string;
    readonly sessionsCount: number;
    readonly draftCount: number;
    readonly publishedCount: number;
    readonly openReservationCount: number;
    readonly staffOnlyCount: number;
    readonly selectedSessionsCount: number;
    readonly totalCapacity: number;
    readonly totalAssigned: number;
    readonly actionError: string | null;
}
export interface ConsultationHubContext extends TourContextBase {
    readonly pageState: string;
    readonly pendingReleaseCount: number;
    readonly scheduledCount: number;
    readonly unscheduledCount: number;
    readonly releasedCount: number;
    readonly followUpsCount: number;
    readonly todayScheduledCount: number;
    readonly todaySessionsCount: number;
    readonly canPlan: boolean;
}
export interface ExamMaterialsContext extends TourContextBase {
    readonly hasAnswerSheet: boolean;
    readonly answerSheetName: string | null;
    readonly answerSheetStatus: string | null;
    readonly bookletsCount: number;
    readonly finalizedBookletsCount: number;
    readonly isOmrTestReady: boolean;
    readonly omrTestStatus: string;
}
export interface ProgramHeadOverviewContext extends TourContextBase {
    readonly pageState: string;
    readonly programsCount: number;
    readonly cycle: string | null;
    readonly firstProgramCode: string | null;
}
export interface XiamKnowledgeContext extends TourContextBase {
    readonly pageState: string;
    readonly totalCount: number;
    readonly activeCount: number;
    readonly reviewCount: number;
    readonly policyCount: number;
    readonly faqCount: number;
    readonly canManage: boolean;
    readonly canApprove: boolean;
}
export interface ApplicantAccessKeySafetyContext extends TourContextBase {
    readonly hasAccessKey: boolean;
    readonly hasReferenceCode: boolean;
}
export interface ApplicantApplyContext extends TourContextBase {
    readonly hasCycle: boolean;
    readonly hasWindow: boolean;
    readonly programCount: number;
}
export interface ApplicantMyApplicationContext extends TourContextBase {
    readonly applicationStatus: string;
    readonly needsCorrection: boolean;
    readonly correctionNotes: string | null;
    readonly hasPreferences: boolean;
    readonly canAmend: boolean;
}
export interface ApplicantPortalContext extends TourContextBase {
    readonly pageState: string;
    readonly applicantName: string | null;
    readonly totalStagesCount: number;
    readonly currentStageTitle: string | null;
    readonly hasActionRequired: boolean;
    readonly actionStageTitle: string | null;
    readonly hasQrLink: boolean;
    readonly completedStagesCount: number;
}
export interface ApplicantQrContext extends TourContextBase {
    readonly pageState: string;
    readonly hasQr: boolean;
    readonly attemptId: number | null;
}
export interface ApplicantConsultationContext extends TourContextBase {
    readonly pageState: string;
    readonly isAdvised: boolean;
    readonly hasAppointment: boolean;
    readonly openSlotsCount: number;
}
export interface StaffOperationsContext extends TourContextBase {
    readonly staffOffice: string;
    readonly staffName: string;
    readonly activeCycleName: string | null;
    readonly hasRegistrar: boolean;
    readonly hasGuidance: boolean;
    readonly hasProgramHead: boolean;
    readonly activityCount: number;
}
