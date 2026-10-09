# NiCare ODK Implementation Notes

## Current Stop Point

Phase 13 is complete. The dashboard now uses real operational data and working quick actions. The system currently supports:

- assigned exercise/form/facility download and local cache
- exercise selector on facility selection
- facility selection scoped to active assignments
- facility selection
- offline drafts
- dynamic section and field rendering from the published schema
- GPS capture
- photo and document evidence capture
- signature capture
- validation review before finalizing
- local sync queue with retry and attachment upload
- backend submission window enforcement for active exercise dates
- review queue filtering by exercise
- exercise completion dashboard
- assessor/team progress reports
- facility completion status
- CSV exports for submissions, facility status, and progress
- real dashboard metrics from `/api/dashboard`
- current exercise overview
- live completion dashboard
- submissions-over-time chart
- recent submissions and assignment activity
- working dashboard quick actions for forms, exercises, assignments, submissions, and reports

The Expo dev server was stopped before switching back to web portal work. To resume mobile QA, run:

```bash
cd odk/mobile-app
npm.cmd run start
```

Use `http://localhost:8093` for Expo. If testing on a physical device, confirm `extra.apiBaseUrl` in `odk/mobile-app/app.json` points to a network address the device can reach.

## Mobile Implementation Phases

1. Published schema consumption and offline cache.
2. Dynamic mobile assessment rendering.
3. Offline draft lifecycle.
4. Validation, scoring, and finalize flow.
5. Sync queue and attachment upload.
6. Portal review compatibility and QA hardening.
7. Team and access management.
8. Facility reference sync from NiCare.
9. Assessment exercises.
10. Assignment manager.
11. Mobile assignment sync.
12. Exercise review and reporting.
13. Operational dashboard and quick actions.

## Phase 13 Completed

The Laravel/Vue dashboard has been rebuilt around real data and working navigation.

Implemented:

- dashboard API at `/api/dashboard`
- current exercise selector and date range selector
- live metrics for completion, pending facilities, awaiting review, returned, approved, and average score
- submissions-over-time chart from synced submissions
- recent submissions list
- recent assignment activity list
- current exercise overview with status, window, form, and version
- working quick actions:
  - Create or manage forms
  - Create exercise
  - Assign facilities
  - Review submissions
  - View reports

Verification completed:

- `php -l app\Http\Controllers\Api\DashboardController.php`
- `php -l routes\api.php`
- `php artisan route:list --path=api/dashboard`
- `npm.cmd run typecheck`
- `npm.cmd run build`

## Phase 12 Completed

The Laravel/Vue portal can now review and report by exercise.

Implemented:

- reports API at `/api/reports/exercises`
- CSV export API at `/api/reports/exercises/export`
- submissions API filter by `exercise_id`
- submissions list now includes exercise and assignment context
- Submissions screen filters review queue by exercise, status, and facility search
- Reports nav now opens a real exercise reporting screen
- reporting screen includes:
  - exercise summary metrics
  - completion dashboard
  - assessor progress
  - team progress
  - facility completion status
  - review queue snapshot
  - CSV export buttons

Verification completed:

- `php -l app\Http\Controllers\Api\ExerciseReportController.php`
- `php -l app\Http\Controllers\Api\AssessmentSubmissionController.php`
- `php -l routes\api.php`
- `php artisan route:list --path=api/reports/exercises`
- `npm.cmd run typecheck`
- `npm.cmd run build`

## Phase 11 Completed

Mobile now downloads assignment-scoped work instead of the full reference directory.

Implemented:

- backend mobile assignment endpoint at `/api/mobile/assignments`
- assignment API resolves the assessor by staff code, email, or local user ID
- mobile payload includes assigned exercises, form versions, facilities, and assignment metadata
- mobile caches assignments separately from forms and facilities
- facility selection is scoped by selected exercise
- drafts now store exercise ID/UUID and assignment ID/UUID
- sync payload includes `assessment_exercise_id`, `assessment_exercise_uuid`, `assessment_assignment_id`, and `assignment_uuid`
- local start/finalize flow blocks assessments outside the exercise window
- backend submission service blocks non-draft submissions unless the exercise is active and within its start/end dates

Verification completed:

- `php -l app\Http\Controllers\Api\MobileAssignmentController.php`
- `php -l app\Services\Submissions\AssessmentSubmissionService.php`
- `php artisan route:list --path=api/mobile/assignments`
- `npx.cmd tsc --noEmit` in `odk/mobile-app`

## Phase 1 Completed

The Expo app now treats the published Laravel form snapshot as the source of truth for mobile assessment setup.

Implemented:

- downloads published form schema from `/api/mobile/forms/published`
- maps sections, fields, validation rules, visibility rules, scoring rules, evidence rules, signature rules, and metadata into the mobile schema
- carries scoring metadata into the mobile cache:
  - `requires_scoring`
  - `scoring_max_score`
  - `metadata_schema`
- normalizes downloaded sections and fields before saving them offline
- stores separate form cache metadata including API base URL, download time, cached form count, cached facility count, and form version summaries
- improves the Forms & downloads screen so assessors can see cached version, sections, fields, rule counts, scoring status, and last download time

Verification completed:

- `npx.cmd tsc --noEmit` in `odk/mobile-app`

## Phase 6 Completed

The Laravel/Vue portal review flow now displays the key data submitted by the Expo mobile app.

Implemented:

- submissions API list response now includes form scoring metadata and evidence details needed by the review queue
- submission detail response now loads ordered answers, attachments with download URLs, reviews, and sync events
- portal submission review now shows:
  - facility details
  - assessor and submitted date
  - score with max score when available
  - validation issue count
  - evidence count
  - GPS coordinates and accuracy
  - answers requiring review
  - submitted answer list
  - signature/attestation rows
  - validation issue details
  - evidence download links with file size
  - sync trail events
- submission service TypeScript types now cover score details, GPS fields, snapshots, answer rows, attachment metadata, and sync events

Verification completed:

- `php -l app/Http/Controllers/Api/AssessmentSubmissionController.php`
- `npm.cmd run typecheck` in `odk/backend`
- `npm.cmd run build` in `odk/backend`

## Phase 7 Completed

The ODK portal now has the first operational access layer for users, roles, and teams.

Implemented:

- database tables:
  - `odk_roles`
  - `odk_users`
  - `odk_teams`
  - `odk_team_members`
- models:
  - `OdkRole`
  - `OdkUser`
  - `OdkTeam`
- seeded default roles:
  - Admin
  - Form manager
  - QA coordinator
  - Team lead
  - Assessor
  - Reviewer
- seeded pilot users and a starter Niger State QA team
- API endpoints:
  - `GET /api/team-access`
  - `POST /api/team-access/users`
  - `PATCH /api/team-access/users/{user}`
  - `POST /api/team-access/teams`
  - `PATCH /api/team-access/teams/{team}`
- Vue service for team access API calls
- real Team & access screen replacing the old placeholder
- Team screen supports:
  - viewing users and roles
  - creating users
  - viewing teams
  - creating teams
  - selecting team lead and members

Phase 7 UI update:

- Team & access now uses tabs for Users, Teams, and Roles
- user and team forms now open in modals so the data tables can use the full content width
- creating a user now requires only email and password; name, role profile details, LGA, and ward can be completed later
- teams can now be edited from the Teams tab
- team editing supports changing lead, coverage, status, description, and adding/removing members

Verification completed:

- `php -l` on new PHP files
- `php artisan migrate`
- `php artisan db:seed --class=OdkTeamAccessSeeder`
- `php artisan route:list --path=api/team-access`
- `npm.cmd run typecheck` in `odk/backend`
- `npm.cmd run build` in `odk/backend`

## Phase 8 Completed

Facility reference sync is now wired from the main `new-web` system into the ODK portal.

Implemented:

- added a dedicated `new-web` server-to-server reference export API:
  - `GET /api/odk/reference/facilities`
  - `GET /api/odk/reference/lgas`
  - `GET /api/odk/reference/wards`
  - `GET /api/odk/reference/premium-metadata`
- protected the `new-web` reference export API with `ODK_REFERENCE_API_TOKEN`
- updated ODK NICARE endpoint defaults to use the dedicated reference export URLs
- improved ODK pagination handling for `new-web` paginator responses
- improved reference sync active-status parsing so both numeric `1` and string `active` are treated correctly
- added ODK portal API for synced facilities:
  - `GET /api/facilities/reference`
- replaced the placeholder Facilities screen with a synced facility directory:
  - sync from NiCare action
  - active/total/LGA/ward metrics
  - search
  - LGA and ward filters
  - active/all status filter
  - paginated facility table
  - last sync status

Configuration required:

- set the same shared secret in `new-web/.env` and `odk/backend/.env`:
  - `new-web`: `ODK_REFERENCE_API_TOKEN=...`
  - `odk/backend`: `NICARE_API_TOKEN=...`
- ensure `odk/backend/.env` points at the main system:
  - `NICARE_API_BASE_URL=http://nicare-system.test` or the active `new-web` local domain
  - `NICARE_FACILITIES_ENDPOINT=/api/odk/reference/facilities`
  - `NICARE_LGAS_ENDPOINT=/api/odk/reference/lgas`
  - `NICARE_WARDS_ENDPOINT=/api/odk/reference/wards`
  - `NICARE_PREMIUM_METADATA_ENDPOINT=/api/odk/reference/premium-metadata`

Verification completed:

- `php -l app/Http/Controllers/Api/OdkReferenceController.php` in `new-web`
- `php artisan route:list --path=api/odk/reference` in `new-web`
- `php -l app/Services/Integration/NicareReferenceSyncService.php` in `odk/backend`
- `php -l app/Http/Controllers/Api/ReferenceFacilityController.php` in `odk/backend`
- `php artisan route:list --path=api/facilities/reference` in `odk/backend`
- `npm.cmd run typecheck` in `odk/backend`
- `npm.cmd run build` in `odk/backend`

## Phase 9 Completed

Assessment exercises are now the planning layer for opening form submission windows.

Implemented:

- `assessment_exercises` table with:
  - UUID and generated code
  - name and description
  - period type: quarter, month, or custom
  - year, quarter, month, start date, and end date
  - selected published form and published form version
  - status: draft, scheduled, active, closed, archived
  - activation/closure timestamps
- `AssessmentExercise` model
- assessment exercise API endpoints:
  - `GET /api/exercises`
  - `POST /api/exercises`
  - `GET /api/exercises/{exercise:uuid}`
  - `PATCH /api/exercises/{exercise:uuid}`
- API validates that the selected form version belongs to the selected form and is published
- portal Exercises screen:
  - sidebar navigation item
  - metrics for total, draft, scheduled, and active exercises
  - search, status, period, and year filters
  - paginated exercises table
  - create/edit modal
  - period-aware date helpers for Q1-Q4 and monthly exercises
  - published form/version selector

Verification completed:

- `php -l app/Http/Controllers/Api/AssessmentExerciseController.php`
- `php -l app/Models/AssessmentExercise.php`
- `php -l routes/api.php`
- `php artisan migrate`
- `php artisan route:list --path=api/exercises`
- `php artisan config:clear`
- `npm.cmd run typecheck` in `odk/backend`
- `npm.cmd run build` in `odk/backend`

## Phase 10 Completed

Assessment assignments now connect exercises to assessors, teams, and facilities.

Implemented:

- `assessment_assignments` table with:
  - UUID
  - exercise reference
  - facility reference
  - assignee type: user or team
  - assigned user/team reference
  - assignment status: assigned, in progress, submitted, reviewed, cancelled, expired
  - duplicate-submission permission flag
  - assigned/due/started/submitted/completed timestamps
- nullable `assessment_exercise_id` and `assessment_assignment_id` links on submissions
- `AssessmentAssignment` model and relationships from exercises, facilities, users, teams, and submissions
- assignment API endpoints:
  - `GET /api/assignments`
  - `POST /api/assignments`
  - `PATCH /api/assignments/{assignment:uuid}`
- assignment creation supports selecting one exercise, one user/team assignee, and multiple facilities
- assignment duplicate guard prevents assigning the same facility to the same exercise unless duplicate submissions are explicitly allowed
- submission service now accepts assignment/exercise identifiers from mobile payloads:
  - `assignment_uuid`
  - `assignment_id`
  - `assessment_assignment_id`
  - `exercise_uuid`
  - `exercise_id`
  - `assessment_exercise_id`
- submission service prevents a second submitted assessment for the same exercise/facility unless the assignment allows duplicate submissions
- idempotent mobile retries using the same `client_submission_id` remain allowed
- assignment status is advanced automatically when mobile drafts/submissions are received
- portal Assignments screen:
  - sidebar navigation item
  - metrics for total, in progress, submitted, and reviewed assignments
  - search, exercise, status, and assignee filters
  - paginated assignment table
  - create/edit modal
  - user/team assignee selector
  - facility multi-select
  - duplicate-submission toggle

Verification completed:

- `php -l app/Http/Controllers/Api/AssessmentAssignmentController.php`
- `php -l app/Models/AssessmentAssignment.php`
- `php -l app/Services/Submissions/AssessmentSubmissionService.php`
- `php -l routes/api.php`
- `php artisan migrate`
- `php artisan migrate:status --path=database/migrations/2026_10_09_000002_create_assessment_assignments_table.php`
- `php artisan route:list --path=api/assignments`
- `php artisan route:list --path=api/mobile/submissions`
- `php artisan config:clear`
- `npm.cmd run typecheck` in `odk/backend`
- `npm.cmd run build` in `odk/backend`

## Phase 5 Completed

The Expo mobile sync queue is now more resilient for offline and poor-network uploads.

Implemented:

- queued submissions now track:
  - attempts
  - last attempt time
  - last error
  - remote submission UUID
  - per-attachment upload status
- queue hydration now resets stale `syncing` items back to `pending` after app restart
- queue hydration adds default attachment status for older queued assessments
- sync preserves the remote UUID when the submission was created but evidence upload failed
- retrying a partially failed queue item reuses the saved remote UUID instead of creating another submission
- retrying skips evidence files already marked as uploaded
- Sync centre now shows:
  - attempt count
  - last attempt time
  - remote submission UUID when available
  - per-evidence upload status
  - retry button for failed items
- synced history records how many evidence files were uploaded

Backend note:

- The Laravel backend already supports idempotent mobile submission creation through unique `client_submission_id`, so mobile retries do not create duplicate assessment submissions.

Verification completed:

- `npx.cmd tsc --noEmit` in `odk/mobile-app`

## Phase 4 Completed

The Expo mobile app now has a stronger local validation, scoring, and finalize flow.

Implemented:

- validation now returns one structured result with:
  - errors
  - completed section count
  - score details
- local validation honors common published builder/Laravel-style rules:
  - `integer`
  - `numeric`
  - `date`
  - `before_or_equal:today`
  - `min:n`
  - `max:n`
  - `accepted`
  - `array`
- `yes_no_with_count` validates its nested count using `count_rules`
- required GPS and signature fields show specific capture errors
- photo/document collection fields validate configured `min_items` and `max_items`
- local score calculation now reads published `option_score` scoring rules
- score result includes total, max score, percent, and per-field score details
- Review & finalize screen now shows:
  - completed sections
  - issues to fix
  - assessment score for scored forms
  - top scored answers
- sync payload now includes local validation summary inside device metadata for traceability

Verification completed:

- `npx.cmd tsc --noEmit` in `odk/mobile-app`

## Phase 3 Completed

The Expo mobile draft lifecycle is now safer for offline use and form-version changes.

Implemented:

- every new draft stores a snapshot of the exact published form schema it was started with
- resumed drafts render against their saved schema snapshot, not whatever form version is currently cached
- older locally cached drafts are hydrated with a recovered schema from the available form cache
- active draft ID is persisted locally so the app can recover the last active draft after restart
- finalizing an assessment clears the active draft pointer
- section navigation and final validation now use the draft's saved schema
- the assessment screen warns when a newer form version is available while the draft continues using its original version

Verification completed:

- `npx.cmd tsc --noEmit` in `odk/mobile-app`
- `php artisan route:list --path=api/mobile/forms` in `odk/backend`

## Phase 2 Completed

The Expo mobile assessment renderer now handles the published builder schema more faithfully.

Implemented:

- renders builder field groups as section dividers in the mobile checklist
- respects the `readonly` flag independently of field type
- renders `reference_select` fields as selectable option fields when options exist, while keeping metadata reference fields read-only when they are prefilled from facility context
- keeps support for:
  - `text`
  - `textarea`
  - `number`
  - `currency`
  - `date`
  - `yes_no`
  - `yes_no_with_count`
  - `select`
  - `reference_select`
  - `gps`
  - `photo_collection`
  - `file_collection`
  - `signature`
  - `checkbox`
  - `readonly`
- improves read-only display so option-backed values show labels when available
- improves conditional visibility and conditional required logic so compound answers like `yes_no_with_count` compare using their selected `answer`, not the whole stored object

Verification completed:

- `npx.cmd tsc --noEmit` in `odk/mobile-app`

## Form Builder Improvement Completed

The Laravel/Vue form builder has been improved so users no longer need to edit options as raw JSON for normal field setup.

Implemented:

- create new sections from the Builder tab
- structured option rows with label, stored value, score, and review flag
- user-friendly validation rule selectors and custom validation rule entry
- conditional display rules for follow-up fields
- conditional required rules for cases like "Yes, provide value" or "Other, specify"

Verification completed:

- `npm.cmd run typecheck`
- `npm.cmd run build`
- `php artisan route:list --path=api/forms`
