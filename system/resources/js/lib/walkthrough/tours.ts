import { resolveTour } from './resolver';
import type { ResolvedTour, TourContextBase, TourDefinition, TourFactory } from './types';
import { admissionSupportTour } from './tours/admission-support';
import { applicantAccessKeySafetyTour } from './tours/applicant-access-key-safety';
import { applicantApplyGuideTour } from './tours/applicant-apply-guide';
import { applicantConsultationTour } from './tours/applicant-consultation';
import { applicantExamScheduleTour } from './tours/applicant-exam-schedule';
import { applicantMyApplicationTour } from './tours/applicant-my-application';
import { applicantPortalTour } from './tours/applicant-portal';
import { applicantProgramChoiceTour } from './tours/applicant-program-choice';
import { applicantQrTour } from './tours/applicant-qr';
import { assessmentGradingDashboardTour } from './tours/assessment-grading-dashboard';
import { assessmentOmrCameraScannerTour } from './tours/assessment-omr-camera-scanner';
import { consultationHubTour } from './tours/consultation-hub';
import { consultationSchedulingTour } from './tours/consultation-scheduling';
import { dataIngestionTour } from './tours/data-ingestion';
import { enrollmentEligibilityTour } from './tours/enrollment-eligibility';
import { examMaterialsTour } from './tours/exam-materials';
import { examMonitoringTour } from './tours/exam-monitoring';
import { examSessionsTour } from './tours/exam-sessions';
import { programHeadOverviewTour } from './tours/program-head-overview';
import { releaseBatchReleasingTour } from './tours/release-batch-releasing';
import { setupReadinessHubTour } from './tours/setup-readiness-hub';
import { staffOperationsDashboardTour } from './tours/staff-operations-dashboard';
import { xiamKnowledgeTour } from './tours/xiam-knowledge';

type RawTourEntry = TourDefinition<any> | TourFactory<any>;

const tours: Record<string, RawTourEntry> = {
    'admission-support': admissionSupportTour,
    'applicant-access-key-safety': applicantAccessKeySafetyTour,
    'applicant-apply-guide': applicantApplyGuideTour,
    'applicant-consultation': applicantConsultationTour,
    'applicant-exam-schedule': applicantExamScheduleTour,
    'applicant-my-application': applicantMyApplicationTour,
    'applicant-portal': applicantPortalTour,
    'applicant-program-choice': applicantProgramChoiceTour,
    'applicant-qr': applicantQrTour,
    'assessment-grading-dashboard': assessmentGradingDashboardTour,
    'assessment-omr-camera-scanner': assessmentOmrCameraScannerTour,
    'consultation-hub': consultationHubTour,
    'consultation-scheduling': consultationSchedulingTour,
    'data-ingestion': dataIngestionTour,
    'enrollment-eligibility': enrollmentEligibilityTour,
    'exam-materials': examMaterialsTour,
    'exam-monitoring': examMonitoringTour,
    'exam-sessions': examSessionsTour,
    'program-head-overview': programHeadOverviewTour,
    'release-batch-releasing': releaseBatchReleasingTour,
    'setup-readiness-hub': setupReadinessHubTour,
    'staff-operations-dashboard': staffOperationsDashboardTour,
    'xiam-knowledge': xiamKnowledgeTour,
};

export function getTour<C extends TourContextBase = TourContextBase>(
    tourKey: string,
    context?: Readonly<C>,
): ResolvedTour | null {
    const raw = tours[tourKey];
    if (!raw) return null;
    return resolveTour(raw, context);
}
export function hasTour(tourKey: string): boolean {
    return Object.prototype.hasOwnProperty.call(tours, tourKey);
}
