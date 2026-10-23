#!/usr/bin/env bash
set -euo pipefail

# ──────────────────────────────────────────────────────────────────────────
# NEXIAM: Clean Operational Pipeline Data (Preserve Setup & Staff)
#
# Purges:
#   - Test applicants, identities, attempts, requirements, verifications
#   - Test exam sessions, seat allocations, assignments, attendance
#   - Test scoring runs, raw results, result documents, releases
#   - Test consultation encounters, sessions, decisions
#   - Test notifications, inquiry messages, AI runs
#   - Resets admission application reference counter back to 1
#
# Preserves (100% Intact):
#   - Institution profile, colleges/departments, programs, quotas, benchmarks
#   - Rooms, room seating profiles, default seating arrangements
#   - Academic cycle, intake windows, intake requirements, grade fields
#   - Test instruments, versions, subtests, metrics, answer keys, norm tables
#   - Test kits, booklets, answer sheet templates, OMR coordinate setups
#   - Communication & result sheet templates, public policies, knowledge base
#   - All 26 Staff accounts, profiles, authentication states, roles & permissions
# ──────────────────────────────────────────────────────────────────────────

system_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
db_name="${1:-nexiam_public_ispsc_20261016}"

if [[ -t 1 ]] && command -v tput >/dev/null 2>&1 && [[ "$(tput colors 2>/dev/null || echo 0)" -ge 8 ]]; then
  BOLD=$(tput bold); DIM=$(tput dim); RESET=$(tput sgr0)
  BLUE=$(tput setaf 4); GREEN=$(tput setaf 2); YELLOW=$(tput setaf 3); RED=$(tput setaf 1)
else
  BOLD=""; DIM=""; RESET=""; BLUE=""; GREEN=""; YELLOW=""; RED=""
fi

say()  { printf '  %s\n' "$1"; }
warn() { printf '  %s⚠ %s%s\n' "$YELLOW" "$1" "$RESET"; }

printf '\n%s%s=== NEXIAM Production Pipeline Cleaner ===%s\n\n' "$BOLD" "$BLUE" "$RESET"
say "Target Database: ${BOLD}$db_name${RESET}"
say "This will remove applicant journeys, exam sessions, and scoring runs."
say "All institutional setup, test instruments/kits, and staff accounts will be preserved."
printf '\n'

confirm() {
  local reply=""
  printf '  %s? %s [y/N] ' "$YELLOW" "$1"
  read -r reply || true
  [[ "$reply" =~ ^[Yy] ]]
}

confirm "Proceed with pipeline cleanup on '$db_name'?" || {
  say "Cancelled. No changes made."
  exit 0
}

# 1. Automatic pre-clean backup
mkdir -p "$system_dir/storage/app/private"
timestamp="$(date +%Y%m%d_%H%M%S)"
backup_file="$system_dir/storage/app/private/backup_before_purge_${db_name}_${timestamp}.sql"

say "Taking automatic pre-clean backup..."
(cd -- "$system_dir" && docker compose exec -T postgres pg_dump -U nexiam "$db_name" > "$backup_file")
say "${GREEN}✓ Backup created:${RESET} $backup_file ($(du -h "$backup_file" | cut -f1))"

# 2. Transactional cleanup SQL
purge_sql="
BEGIN;

-- Temporarily disable immutability triggers on operational tables being purged
ALTER TABLE assessment_administrations DISABLE TRIGGER USER;
ALTER TABLE assessment_conversion_raw_dependencies DISABLE TRIGGER USER;
ALTER TABLE assessment_conversion_revisions DISABLE TRIGGER USER;
ALTER TABLE assessment_scoring_runs DISABLE TRIGGER USER;
ALTER TABLE exam_assignment_logistics_seat_allocations DISABLE TRIGGER USER;
ALTER TABLE exam_session_logistics_plans DISABLE TRIGGER USER;
ALTER TABLE exam_session_logistics_seats DISABLE TRIGGER USER;
ALTER TABLE exam_sessions DISABLE TRIGGER USER;
ALTER TABLE score_record_revisions DISABLE TRIGGER USER;

-- 1. Result Releases & Consultation dependencies
DELETE FROM result_releases;
DELETE FROM consultation_decisions;
DELETE FROM consultation_encounters;
DELETE FROM consultation_assignments;
DELETE FROM consultation_sessions;
DELETE FROM consultation_online_exceptions;

-- 2. Result Documents & Print Receipts
DELETE FROM result_document_print_receipts;
DELETE FROM result_documents;
DELETE FROM print_job_items;
DELETE FROM print_jobs;

-- 3. Scoring & Assessments
DELETE FROM score_record_revisions;
DELETE FROM score_records;
DELETE FROM score_conversion_rows;
DELETE FROM assessment_conversion_raw_dependencies;
DELETE FROM assessment_conversion_revisions;
DELETE FROM assessment_result_sets;
DELETE FROM assessment_raw_result_revisions;
DELETE FROM assessment_scoring_runs;
DELETE FROM assessment_administrations;
DELETE FROM grading_sessions;

-- 4. Exam Sessions, Seats, Assignments, Submissions
DELETE FROM examination_action_execution_receipts;
DELETE FROM exam_submission_records;
DELETE FROM exam_attendance_records;
DELETE FROM exam_assignment_seat_allocations;
DELETE FROM exam_assignment_logistics_seat_allocations;
DELETE FROM exam_assignments;
DELETE FROM exam_session_seats;
DELETE FROM exam_session_material_sets;
DELETE FROM exam_session_material_snapshots;
DELETE FROM exam_session_logistics_seats;
DELETE FROM exam_session_logistics_plans;
DELETE FROM exam_sessions;

-- 5. Communications, Inquiries, Threads, Notifications & Challenges
DELETE FROM applicant_access_challenges;
DELETE FROM record_thread_messages;
DELETE FROM record_thread_reads;
DELETE FROM record_threads;
DELETE FROM program_governance_reads;
DELETE FROM program_governance_threads;
DELETE FROM program_advising_note_publications;
DELETE FROM program_advising_note_revisions;
DELETE FROM program_advising_notes;
DELETE FROM applicant_office_inquiry_reads;
DELETE FROM applicant_office_inquiry_messages;
DELETE FROM applicant_office_inquiries;
DELETE FROM applicant_office_inquiry_demo_actors;
DELETE FROM notification_delivery_attempt_artifacts;
DELETE FROM notification_deliveries;

-- 6. Admission Attempts & Program choices
DELETE FROM admission_clearance_receipts;
DELETE FROM enrollment_sheet_handoff_receipts;
DELETE FROM admission_document_correction_request_items;
DELETE FROM admission_document_correction_requests;
DELETE FROM admission_attempt_program_selections;
DELETE FROM admission_attempt_program_declines;
DELETE FROM admission_attempt_program_follow_ups;
DELETE FROM admission_attempt_program_options;
DELETE FROM admission_attempt_program_pools;
DELETE FROM admission_attempt_enrollment_stamps;
DELETE FROM admission_attempt_document_verifications;
DELETE FROM admission_attempt_grade_values;
DELETE FROM admission_attempt_requirement_internal_notes;
DELETE FROM admission_attempt_requirement_items;
DELETE FROM admission_attempt_requirement_sets;
DELETE FROM admission_attempts;

-- 7. Applicant Accounts & Identities & Applicant Users (keep staff users intact)
DELETE FROM applicant_accounts;
DELETE FROM applicant_identities;
DELETE FROM users WHERE id NOT IN (SELECT user_id FROM staff_profiles);

-- 8. Reset application counters so new real applicants start at 1
UPDATE admission_reference_counters SET next_value = 1;

-- 9. Purge test AI runs and camera pairings
DELETE FROM xiam_run_events;
DELETE FROM xiam_run_steps;
DELETE FROM xiam_runs;
DELETE FROM xiam_turns;
DELETE FROM xiam_usage_reservations;
DELETE FROM xiam_proposals;
DELETE FROM xiam_conversations;
DELETE FROM omr_camera_commands;
DELETE FROM omr_camera_pairings;
DELETE FROM omr_pending_capture_sets;
DELETE FROM omr_diagnostic_runs;

-- Re-enable triggers
ALTER TABLE assessment_administrations ENABLE TRIGGER USER;
ALTER TABLE assessment_conversion_raw_dependencies ENABLE TRIGGER USER;
ALTER TABLE assessment_conversion_revisions ENABLE TRIGGER USER;
ALTER TABLE assessment_scoring_runs ENABLE TRIGGER USER;
ALTER TABLE exam_assignment_logistics_seat_allocations ENABLE TRIGGER USER;
ALTER TABLE exam_session_logistics_plans ENABLE TRIGGER USER;
ALTER TABLE exam_session_logistics_seats ENABLE TRIGGER USER;
ALTER TABLE exam_sessions ENABLE TRIGGER USER;
ALTER TABLE score_record_revisions ENABLE TRIGGER USER;

COMMIT;
"

say "Executing pipeline purge transaction..."
(cd -- "$system_dir" && docker compose exec -T postgres psql -U nexiam -d "$db_name" <<< "$purge_sql")

say "${GREEN}✓ Transaction committed successfully.${RESET}"
printf '\n'
say "Post-cleanup database state:"
(cd -- "$system_dir" && docker compose exec -T postgres psql -U nexiam -d "$db_name" -c "
SELECT 
    (SELECT count(*) FROM users) as users_count,
    (SELECT count(*) FROM staff_profiles) as staff_count,
    (SELECT count(*) FROM institutions) as institutions_count,
    (SELECT count(*) FROM departments) as departments_count,
    (SELECT count(*) FROM programs) as programs_count,
    (SELECT count(*) FROM rooms) as rooms_count,
    (SELECT count(*) FROM test_instruments) as instruments_count,
    (SELECT count(*) FROM test_kits) as test_kits_count,
    (SELECT count(*) FROM applicant_accounts) as applicants_count,
    (SELECT count(*) FROM exam_sessions) as sessions_count;
")

printf '\n'
say "Clearing application cache..."
(cd -- "$system_dir" && docker compose exec -T app php artisan cache:clear || true)
(cd -- "$system_dir" && docker compose exec -T app php artisan config:clear || true)
if docker ps --format '{{.Names}}' 2>/dev/null | grep -q 'nexiam-system-https-test-app'; then
    (cd -- "$system_dir" && docker compose exec -T https-test-app php artisan cache:clear || true)
    (cd -- "$system_dir" && docker compose exec -T https-test-app php artisan config:clear || true)
fi

say "${GREEN}✓ Pipeline cleanup complete. Ready for fresh applicants!${RESET}"
