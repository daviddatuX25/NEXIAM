<?php

namespace App\Http\Controllers\Foundation;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Temporary internal project roadmap for feature-level implementation tracking.
 *
 * Keep the dashboard at macro level; interface checks, subfeatures, and
 * behavior sequences are available when a feature row is expanded.
 */
final class StatusController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Foundation/Status', [
            'foundation' => [
                'phase' => 'Feature roadmap',
                'artifact' => 'Project management checklist',
                'runtime' => 'Laravel '.app()->version().' · PHP '.PHP_VERSION.' · Inertia + Svelte',
                'database' => strtoupper((string) config('database.default', 'unknown')),
                'legacyReference' => 'Greenfield code lives in system/; legacy evidence remains in legacy_codebase/.',
            ],
            'roadmap' => $this->roadmap(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function roadmap(): array
    {
        $roadmap = [
            [
                'key' => 'proposal-defense-governance',
                'title' => 'Proposal defense & panel governance',
                'description' => 'Mandated methodology revisions and locked research scope from the proposal defense.',
                'items' => [
                    ['name' => 'Three-objective scope structure', 'status' => 'complete', 'detail' => 'SO1 (Process Analysis), SO2 (System Requirements & RAD methodology), and SO3 (TAM Acceptance post-test). SO4, paired t-tests, and null hypotheses H01/H02 deleted entirely.'],
                    ['name' => 'Defended Slovin population model (N=700 → n=255)', 'status' => 'complete', 'detail' => 'Applicant population baseline N=700 (adjusted for campus scale) at e=0.05 yields n=255 first-year students stratified across 11 courses (~23/course). Total evaluators: 255 students + 16 staff = 271.'],
                    ['name' => 'IT Expert panel removal & SO2 validation shift', 'status' => 'complete', 'detail' => 'IT Experts (n=3) removed from TAM; technical architecture, security, and data privacy are validated via SO2 iterative design review and internal peer review during Rapid Construction.'],
                    ['name' => 'Two-construct TAM post-test specification', 'status' => 'complete', 'detail' => 'Measures Perceived Usefulness (PU) and Perceived Ease of Use (PEOU) on a 5-point Likert scale (midpoint 3.0). Post-test only; ATU/BIU and pre-test indicators removed.'],
                ],
            ],
            [
                'key' => 'research-instruments-specs',
                'title' => 'Research instruments & sampling specifications',
                'description' => 'Field survey instruments, participant matrices, and interview guides across the RAD lifecycle.',
                'items' => [
                    ['name' => 'First-year student journey survey (Google Form / baseline)', 'status' => 'complete', 'detail' => '15-question journey instrument covering application hassle, test day friction, consultation queues, program capacity, 1-5 feature ratings, and constructive feedback.'],
                    ['name' => 'Guidance & Registrar process inquiry (interactive brief)', 'status' => 'complete', 'detail' => 'Field inquiry capturing >5 day scoring duration, stencil errors, 70-80/day consultation flow, and paper bottlenecks; live on /capstone/client-brief.'],
                    ['name' => 'Program Head departmental census protocol (N=11)', 'status' => 'complete', 'detail' => 'Total enumeration (N=11) via concise Google Form + in-depth interviews with key heads (BSIT, Education, BSHM) to report an institutional response rate.'],
                    ['name' => 'Cutover TAM evaluation role-adapted tables (n=271)', 'status' => 'complete', 'detail' => 'Four role-adapted item tables: Registrar (8 items), Guidance (13 items), First-Year Students (9 items), Program Heads (9 items) evaluating post-cutover acceptance.'],
                ],
            ],
            [
                'key' => 'institutional-strategies',
                'title' => 'Institutional policies & field strategies',
                'description' => 'Non-obvious operational rules, legal mandates (RA 10931 / RA 10173), and field strategies governing NEXIAM.',
                'items' => [
                    ['name' => 'Dual-phase sampling strategy (baseline vs. cutover)', 'status' => 'complete', 'detail' => 'Exploratory cohort (n=30–50 students) for preliminary SO1/SO2 baseline metrics; full Slovin sample (n=255) reserved for post-cutover TAM evaluation (SO3).'],
                    ['name' => 'State college open enrollment preservation (RA 10931)', 'status' => 'complete', 'detail' => 'Open admission by default; capacity limits and qualifying cutoffs apply strictly to laboratory-constrained, high-demand, or faculty-limited programs; counselor retains supremacy.'],
                    ['name' => 'Counselor decision supremacy & descriptive AI boundaries', 'status' => 'complete', 'detail' => 'Automated rejection/admission is strictly prohibited; ML clustering and RAG models serve exclusively as decision-support dashboards for the counselor.'],
                    ['name' => 'Student-pain-driven scheduling & hourly appointment slots', 'status' => 'complete', 'detail' => 'Directly justifies FR-04 and FR-07 by replacing all-day queue arrivals with 1-hour appointment windows and Guidance-controlled self-booking toggles (B4).'],
                ],
            ],
            [
                'key' => 'greenfield-parity',
                'title' => 'Legacy → Greenfield parity',
                'description' => 'Existing office capabilities being re-adopted in the new system.',
                'items' => [
                    ['name' => 'Foundation, access, and staff shell', 'status' => 'complete', 'detail' => 'Runtime, authentication, navigation, capability boundaries, and audit foundation.'],
                    ['name' => 'Admissions and applicant portal', 'status' => 'in_progress', 'detail' => 'Digital intake, review, correction, applicant status, and staff case work.'],
                    ['name' => 'Consultation, follow-ups, and quota', 'status' => 'in_progress', 'detail' => 'Trackable consultation handoff, remarks, program choice, and quota decisions.'],
                    ['name' => 'Examination scheduling and monitoring', 'status' => 'in_progress', 'detail' => 'Sessions, rooms, applicant assignments, attendance, and Test Administrator conduct.'],
                    ['name' => 'Grading, results, and release', 'status' => 'in_progress', 'detail' => 'Manual grading, result preparation, printing, release, and reversal controls.'],
                    ['name' => 'Setup and institutional reference data', 'status' => 'in_progress', 'detail' => 'Cycles, programs, rooms, policies, scoring setup, and operational configuration.'],
                ],
            ],
            [
                'key' => 'flagship-capabilities',
                'title' => 'New flagship capabilities',
                'description' => 'The differentiating work that extends beyond a direct legacy rewrite.',
                'items' => [
                    ['name' => 'Xiam AI boundary and skills', 'status' => 'in_progress', 'detail' => 'Policy-aware AI context, capabilities, skills, and typed actions.'],
                    ['name' => 'Chatbot / assistant experience', 'status' => 'in_progress', 'detail' => 'Staff-facing conversational help connected to the current page and allowed actions.'],
                    ['name' => 'OMR-powered checking', 'status' => 'in_progress', 'detail' => 'Digital capture and analysis with a safe manual fallback.', 'children' => ['Capture and batch ingest', 'Review and manual fallback', 'Inference worker and quality evidence']],
                    ['name' => 'Answer-sheet builder and authoring studio', 'status' => 'in_progress', 'detail' => 'Answer-sheet layouts, page and answer-key composition, sheet text, and proofing.', 'children' => ['Answer-sheet version model', 'Page and answer-key builder', 'Digital proof and activation checks']],
                    ['name' => 'Future intelligence and scheduling assistance', 'status' => 'in_progress', 'detail' => 'Frontend chatbot/assistant surface working and a capability-matrix gate (skill/capability registry) is in place; scheduling proposal assistance has been started but is currently stalled on the core scheduling contracts.'],
                ],
            ],
            [
                'key' => 'capstone-gates',
                'title' => 'Capstone readiness gates',
                'description' => 'Research and design work needed to keep the build aligned with the client.',
                'items' => [
                    ['name' => 'Requirements matrix and Chapter 3 working draft', 'status' => 'complete', 'detail' => 'Current working draft is marked complete from the recorded process matrix.'],
                    ['name' => 'User design wireframe packet', 'status' => 'complete', 'detail' => 'Main-content wireframe review and existing UI kit reference are ready.'],
                    ['name' => 'Next client requirements interview', 'status' => 'complete', 'detail' => 'Assumed complete 2026-08-26 so Chapter 3 SO1 can be drafted from the client visit brief (simulated evidence); live client validation remains scheduled.'],
                    ['name' => 'Client wireframe review and corrections', 'status' => 'next', 'detail' => 'Capture changes only after the client reviews the prepared screens.'],
                ],
            ],
        ];

        $interfaceCoverage = [
            'Foundation, access, and staff shell' => ['Dashboard/home layout', 'Sidebar/navigation', 'Profile and user menu', 'Notifications and toasts', 'Capability-aware empty/denied states'],
            'Admissions and applicant portal' => ['Dashboard/work queue', 'Application list/table view', 'Application detail/dossier', 'Status and action bar', 'Correction dialog/form', 'Applicant portal/status timeline'],
            'Consultation, follow-ups, and quota' => ['Consultation dashboard', 'Schedule/list view', 'Applicant workspace', 'Follow-up queue', 'Remarks and thread history', 'Program/quota decision view'],
            'Examination scheduling and monitoring' => ['Session dashboard', 'Table/card view', 'Schedule statistics/KPIs', 'Session detail', 'Roster/monitoring table', 'Room and session assignment', 'Live refresh and elapsed state'],
            'Grading, results, and release' => ['Grading dashboard', 'Session statistics/progress', 'Sheet review workspace', 'Manual score form', 'Result preview/print', 'Release and reversal actions'],
            'Setup and institutional reference data' => ['Setup dashboard', 'Catalog index/list', 'Create/edit forms', 'Activate/deactivate actions', 'Validation and empty states', 'Audit/history view'],
            'Xiam AI boundary and skills' => ['AI dock/panel entry', 'Current page context', 'Skill/action availability', 'Capability-denied state', 'Execution progress and result'],
            'Chatbot / assistant experience' => ['Chat thread', 'Composer/loading/error states', 'Suggested prompts/actions', 'Result or source card', 'Confirm/cancel action'],
            'OMR-powered checking' => ['Capture dashboard', 'Batch progress', 'Sheet review queue', 'Image/page viewer', 'Manual fallback editor', 'Quality/error states'],
            'OMR template builder and authoring studio' => ['Template list', 'Builder canvas', 'Page/booklet controls', 'Overlay and marker profile', 'Proof/test capture', 'Publish/version state'],
            'Future intelligence and scheduling assistance' => ['Planning assistant entry', 'Recommendation/result view', 'Human confirmation state', 'Unavailable-provider state'],
            'Requirements matrix and Chapter 3 working draft' => ['Requirements brief', 'Decision matrix', 'Evidence/trace table', 'Chapter 3 summary'],
            'User design wireframe packet' => ['Wireframe review page', 'Screen-by-screen flow', 'Client annotations', 'UI kit reference'],
            'Next client requirements interview' => ['Interview guide', 'Question sequence', 'Decision capture', 'Follow-up task'],
            'Client wireframe review and corrections' => ['Wireframe review page', 'Correction capture', 'Owner/date fields', 'Backlog handoff'],
        ];

        $sequences = $this->behaviorSequences();

        return array_map(
            fn (array $group): array => [
                ...$group,
                'items' => array_map(
                    fn (array $item): array => [
                        ...$item,
                        'interface_checks' => array_map(
                            fn (string $label): array => ['label' => $label, 'status' => 'verify'],
                            $interfaceCoverage[$item['name']] ?? [],
                        ),
                        'subfeature_checks' => array_map(
                            fn (string $label): array => ['label' => $label, 'status' => 'verify'],
                            $item['children'] ?? [],
                        ),
                        'sequence' => $sequences[$item['name']] ?? null,
                    ],
                    $group['items'],
                ),
            ],
            $roadmap,
        );
    }

    /**
     * @return array<string, string>
     */
    private function behaviorSequences(): array
    {
        return [
            'Foundation, access, and staff shell' => <<<'MERMAID'
sequenceDiagram
    actor Staff
    participant Browser
    participant Auth
    participant Capability
    Staff->>Browser: Open staff page
    Browser->>Auth: Request authenticated session
    Auth-->>Browser: Return user context
    Browser->>Capability: Resolve allowed actions
    Capability-->>Browser: Allow or deny each action
    Browser-->>Staff: Render shell and available controls
MERMAID,
            'Admissions and applicant portal' => <<<'MERMAID'
sequenceDiagram
    actor Applicant
    participant Portal
    participant Admission
    participant Registrar
    Applicant->>Portal: Submit application
    Portal->>Admission: Validate and create version
    Admission-->>Registrar: Add review item to queue
    Registrar->>Portal: Request correction or accept
    Portal-->>Applicant: Show next action and status
    Applicant->>Portal: Resubmit correction
    Portal->>Admission: Preserve version and audit event
MERMAID,
            'Consultation, follow-ups, and quota' => <<<'MERMAID'
sequenceDiagram
    actor Guidance
    participant Consultation
    participant Applicant
    participant ProgramHead
    Guidance->>Consultation: Create or assign session
    Consultation-->>Applicant: Confirm online or face-to-face mode
    Guidance->>Consultation: Record remark and next action
    Consultation->>ProgramHead: Submit program/quota decision
    ProgramHead-->>Consultation: Select, transfer, decline, or follow up
    Consultation-->>Guidance: Show owner and due work
MERMAID,
            'Examination scheduling and monitoring' => <<<'MERMAID'
sequenceDiagram
    actor Guidance
    participant Scheduler
    participant Session
    participant Roster
    Guidance->>Scheduler: Create room and time
    Scheduler->>Session: Publish session
    Session->>Roster: Assign applicants
    Roster-->>Guidance: Show live attendance state
    Guidance->>Roster: Authorized Test Administrator records attendance and submission
    Roster-->>Session: Close only when conduct is complete
MERMAID,
            'Grading, results, and release' => <<<'MERMAID'
sequenceDiagram
    actor Guidance
    participant Grading
    participant Review
    participant Registrar
    Guidance->>Grading: Open scoring session
    Grading->>Review: Send incomplete or disputed records
    Review-->>Grading: Approve correction or request review
    Guidance->>Grading: Finalize complete scores
    Grading->>Registrar: Prepare eligible result
    Registrar-->>Applicant: Release approved result
MERMAID,
            'Setup and institutional reference data' => <<<'MERMAID'
sequenceDiagram
    actor Staff
    participant Setup
    participant Catalog
    participant Audit
    Staff->>Setup: Open catalog dashboard
    Setup->>Catalog: Load active cycles, programs, rooms, or policies
    Staff->>Catalog: Create or edit reference item
    Catalog->>Audit: Record authority and change
    Setup-->>Staff: Show active, inactive, or validation state
MERMAID,
            'Xiam AI boundary and skills' => <<<'MERMAID'
sequenceDiagram
    actor Staff
    participant Page
    participant Xiam
    participant Policy
    participant Action
    Staff->>Page: Ask for help or action
    Page->>Xiam: Send page context and request
    Xiam->>Policy: Resolve capability and allowed skills
    Policy-->>Xiam: Allow, constrain, or deny
    Xiam->>Action: Prepare typed action when allowed
    Action-->>Staff: Return result for confirmation
MERMAID,
            'Chatbot / assistant experience' => <<<'MERMAID'
sequenceDiagram
    actor Staff
    participant Chat
    participant Assistant
    participant System
    Staff->>Chat: Enter question or command
    Chat->>Assistant: Send conversation and context
    Assistant->>System: Read allowed data or propose action
    System-->>Assistant: Return evidence or validation error
    Assistant-->>Chat: Show answer, source, or confirmation prompt
    Staff->>Chat: Confirm or cancel proposed action
MERMAID,
            'OMR-powered checking' => <<<'MERMAID'
sequenceDiagram
    actor Guidance
    participant Capture
    participant Worker
    participant Review
    Guidance->>Capture: Create batch and upload sheets
    Capture->>Worker: Enqueue signed capture set
    Worker-->>Capture: Return analysis and confidence state
    Capture->>Review: Route unreadable or disputed sheets
    Review-->>Capture: Approve manual fallback or retry
    Capture-->>Guidance: Show complete, review, or failed state
MERMAID,
            'OMR template builder and authoring studio' => <<<'MERMAID'
sequenceDiagram
    actor Guidance
    participant Studio
    participant Template
    participant Proof
    Guidance->>Studio: Open draft or create template
    Studio->>Template: Compose pages, booklet, markers, and overlays
    Template-->>Studio: Validate structure and configuration
    Guidance->>Proof: Run digital proof or test capture
    Proof-->>Studio: Return quality issues or pass
    Studio->>Template: Activate version after approval
MERMAID,
            'Future intelligence and scheduling assistance' => <<<'MERMAID'
sequenceDiagram
    actor Staff
    participant Assistant
    participant Planner
    participant StaffDecision
    Staff->>Assistant: Request planning suggestion
    Assistant->>Planner: Evaluate bounded inputs
    Planner-->>Assistant: Return recommendation or unavailable state
    Assistant-->>Staff: Explain recommendation and constraints
    Staff->>StaffDecision: Confirm or reject proposed plan
MERMAID,
            'Requirements matrix and Chapter 3 working draft' => <<<'MERMAID'
sequenceDiagram
    actor Team
    participant Evidence
    participant Matrix
    participant Manuscript
    Team->>Evidence: Review legacy and current system facts
    Evidence->>Matrix: Record process and decision change
    Matrix->>Manuscript: Summarize result and discussion evidence
    Manuscript-->>Team: Mark working draft complete
MERMAID,
            'User design wireframe packet' => <<<'MERMAID'
sequenceDiagram
    actor Team
    participant Wireframe
    participant UIKit
    participant Client
    Team->>Wireframe: Prepare screen flow and states
    Team->>UIKit: Reference existing components
    Team->>Client: Walk through main content screens
    Client-->>Wireframe: Mark missing steps or corrections
    Wireframe-->>Team: Create confirmed design backlog
MERMAID,
            'Next client requirements interview' => <<<'MERMAID'
sequenceDiagram
    actor Interviewer
    participant Guide
    participant Client
    participant Matrix
    Interviewer->>Guide: Open questions and current artifacts
    Guide-->>Client: Walk through process and decisions
    Client-->>Interviewer: Confirm, correct, or add rule
    Interviewer->>Matrix: Record owner, state, and evidence
    Matrix-->>Team: Create follow-up tasks
MERMAID,
            'Client wireframe review and corrections' => <<<'MERMAID'
sequenceDiagram
    actor Client
    participant Wireframe
    participant Capture
    participant Backlog
    Client->>Wireframe: Review screen and interface checklist
    Client->>Capture: Mark missing behavior or field
    Capture->>Backlog: Add correction with owner and date
    Backlog-->>Team: Prioritize confirmed change
MERMAID,
        ];
    }
}
