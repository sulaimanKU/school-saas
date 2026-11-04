# Phase 3 - Academic operations delivery

Implemented in the existing `E:/school-saas` Laravel/PostgreSQL application on November 4, 2026. README, Phase 2 delivery record, architecture, routes, models, services, components, migrations and tests were inspected before extension. Phase 1/2 tables, school tenancy and the SchoolLabs shell were retained. No authentication starter, fake authentication, fee workflow, dependency installation, database reset/truncate, or Git commit was introduced.

## Completed features

### Attendance

Student attendance loads actual enrollments for the selected session/section/date, including preserved historical enrollments. Present, absent, late and excused are supported with transactional bulk submission and correction. Blank rows remain unmarked or unchanged; they are never made absent automatically. A school/student/session/date has at most one recorded value. Session-date checks, historical section checks, cross-school validation and database triggers protect eligibility. Transfers retain the prior attendance's enrollment pointer. Backdated changes that would invalidate recorded attendance or marks are rejected.

Staff attendance reuses teacher profiles, supports present/absent/late/leave/holiday, bulk recording/correction, date filters, monthly summaries, teacher history and exports. Inactive teachers with historical records remain visible for correction; unrecorded inactive teachers cannot be newly marked. Staff employment start/end histories were not added, so reports use recorded attendance and the current teacher roster rather than invented employment dates.

Attendance percentage is `(present + late) / (present + late + absent) * 100`. Excused, leave, holiday and unmarked rows are excluded from that denominator. A zero denominator displays no percentage. Daily roster reports include explicit unmarked rows for selected calendar dates through today; recorded attendance/monthly reports do not invent expected absences or assume every calendar day was a school day.

### Timetables

School working days/hours, teaching periods, breaks and non-teaching periods have database-backed management. Entries are academic-session-specific and use existing subject/teacher/class/section assignments. Teacher and section conflicts compare actual time ranges, including periods with different IDs. Breaks/non-teaching slots cannot assign a subject teacher. Outside-hours/non-working-day entries are rejected. Referenced period times and teaching assignments are locked against edits that could silently invalidate a schedule. Weekly school/teacher views and printing are provided.

### Examinations, marks and grading

School-owned monthly/midterm/final/custom exam-type definitions, draft/scheduled/completed/published exams, and subject-wise section schedules are implemented. Schedules store dates, starts/ends, optional teacher invigilator, maximum and passing marks; section/invigilator overlaps and dates outside the academic session are rejected. Subjects must already be assigned to the selected class. The subject schedule combines the responsibilities of `exam_schedules` and an otherwise redundant `exam_subjects` table.

Marks entry uses actual examination-date enrollment rosters, bulk transactions, 0-to-maximum validation, scored/absent/exempt states, remarks, and duplicate protection. A student can have only one mark per exam/subject even after a section transfer. Future exam marks are rejected. Missing marks remain unmarked and prevent publication. Absence is a recorded zero for calculation and fails the subject; exemption is excluded from the denominator. Used assessment schedules are context-locked rather than silently changing existing marks' maximums or enrollment context.

School-specific grading schemes support configurable labels, percentage ranges, pass flags, local logo path and optional ranking. Ranges must cover 0-100 without gaps/overlaps. Lower bounds are inclusive, upper bounds exclusive, with 100 included. Backend calculation handles subject/overall totals, percentages, grades, subject thresholds, overall pass/fail and exemption. Ranking uses deterministic competition ties (1, 1, 3) within class/section cohorts, with admission number as stable display ordering for ties. Incomplete/exempt overall results are not ranked.

### Publication and report cards

Results can be previewed as clearly labeled drafts. Complete an exam and explicitly confirm publication to persist versioned report snapshots. Every eligible subject needs scored, absent or exempt data. Snapshots include school/logo, student/admission identity, session, class/section, exam, actual subject marks, totals, percentage, grades, outcome, optional rank, teacher remarks and grading bands. Future student/grading/mark changes do not rewrite an earlier snapshot. Published records and audit entries have PostgreSQL update/delete guards.

Published marks/schedules are locked. A controlled correction requires explicit confirmation and a meaningful reason, reopens the exam to completed, permits marks correction, and produces a new snapshot version on republishing. Prior versions remain accessible. There is no silent overwrite/unpublish of report cards. Printable A4 report cards use native browser printing; no PDF package was installed.

### Reports, audit and dashboard

Reports include recorded and daily-roster attendance, monthly/student history, staff attendance, class attendance comparisons, examination performance, subject/class results, student academic history, pass/fail and ranking-enabled top-performer records. Filters use the current tenant context with validated session/class/section/student/teacher/exam IDs. Attendance date ranges have a maximum 366-day span. Examination reports use the latest published version; exam/session filters apply to them, while from/to apply to attendance as described in the interface. On-screen detail previews contain the first 100 matches; CSV downloads stream all matching rows using chunked queries/generators and neutralize spreadsheet formula prefixes. Views also support printing.

Academic audit events record school, operation, entity/id, timestamps, minimized before/after values, optional correction reason and an explicit local-development actor context with `authenticated=false`. Attendance correction, staff correction, timetable changes, marks changes, publication and reopen are logged transactionally. Personal names/guardian contacts and full teacher remark text are excluded from the audit changes payload. Legacy Phase 2 historical operations are not backfilled as invented audit events.

The dashboard now shows actual today's attendance/coverage/unmarked counts, staff attendance, class-wise attendance rates, upcoming scheduled examinations, published performance, recent versioned report cards and academic activity. Empty datasets have clear empty states. Finance, parent totals and the remaining sample event/activity cards stay visibly labeled as demonstrations.

## Database tables and relationships

All 14 new tables have `school_id`, timestamps and school/id identity constraints. Composite FKs reference existing school-owned academic records; dependency deletes use RESTRICT.

| Table | Main relationships / integrity |
| --- | --- |
| `attendance_sessions` | Session + section + date, unique per tenant/roster date |
| `student_attendance_records` | Composite attendance-session identity and student/enrollment identity; unique student/session/date |
| `staff_attendance_records` | Existing teacher identity; unique teacher/date |
| `school_working_days` | Unique school/weekday, valid weekday and school hours |
| `timetable_periods` | Unique school/code, valid start/end, teaching/break/non-teaching kind |
| `timetable_entries` | Session, section, period and composite session/section teaching assignment; unique slot and overlap triggers |
| `grading_schemes` | School-specific configuration, ranking, logo path |
| `grading_bands` | Composite scheme FK, minimum/maximum checks and unique scheme/minimum |
| `exam_types` | Unique school/type code |
| `exams` | Composite session/type/scheme relationships, status and publication version |
| `exam_schedules` | Composite exam/session, class/section and class/subject relationships, invigilator, times/marks checks |
| `exam_marks` | Composite schedule/exam/subject and student/enrollment identities, unique exam/student/subject, bounds/publication guards |
| `published_report_cards` | Exam/student identities, unique version, immutable JSON snapshot |
| `academic_audit_logs` | School-scoped append-only operation/actor/change metadata |

Four additive migrations were applied successfully. They also add referenceable composite identities on existing enrollments and teacher assignments. No existing table was reset/recreated. PostgreSQL guards protect direct invalid attendance, timetable collisions, out-of-session exams, out-of-bounds marks, published locks, snapshots/audit immutability, used period/assignment context, recorded enrollment context and session-date changes. Application services provide corresponding friendly validation and serialize multi-record operations with the existing per-school transaction lock.

## Routes and exact local URLs

Base: `http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/operations`.

Start the server with `php artisan serve --host=127.0.0.1 --port=8000` after README setup.

- Student attendance: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/operations/student-attendance
- Staff attendance: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/operations/staff-attendance
- Attendance and academic reports: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/operations/reports
- Timetable management: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/operations/timetable
- Teacher timetable: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/operations/teacher-timetable
- School working days/hours: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/operations/working-days
- School periods: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/operations/periods
- Exam types: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/operations/exam-types
- Examination management: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/operations/exams
- Exam scheduling: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/operations/schedules
- Marks entry: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/operations/marks
- Grading configuration: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/operations/grading
- Results / report cards: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/operations/results
- Audit trail: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/operations/audit

- Dashboard: http://127.0.0.1:8000/preview/schools/demo-maplewood/dashboard
- Existing admissions: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/students

Other demo slugs are `demo-crescent`, `demo-oakridge` and `demo-beacon`. Published report IDs are allocated by the database; open View/print in Results for their exact URLs. Legacy attendance/timetables/examinations module URLs redirect to the new screens when academic CRUD is enabled. New operations navigation is hidden in the original read-only preview mode.

Implemented route patterns under `/academic/operations`:

| Method | Path | Behavior |
| --- | --- | --- |
| GET | `/{page}` | 14 operations screens |
| POST | `/{resource}` | Create working day, period, timetable entry, grading scheme, type, exam or schedule |
| PUT / DELETE | `/{resource}/{record}` | Validated update / delete unused record |
| POST | `/{student-attendance,staff-attendance,marks}/submit` | Transactional bulk operations |
| POST | `/results/{exam}/{publish,reopen}` | Explicit publication/correction workflow |
| GET | `/results/{exam}/students/{student}` | Eligible student's actual draft preview |
| GET | `/report-cards/{record}` | Historical immutable snapshot |
| GET | `/reports/export` | Tenant-scoped streamed CSV |

## Commands and verification

| Check | Executed result |
| --- | --- |
| `php artisan migrate --no-interaction` | All four new additive migrations applied |
| `php artisan db:seed --class=OperationsDemoSeeder --no-interaction` | Configuration seeded; no marks/attendance/results fabricated |
| `npm run typecheck` | Passed |
| `npm run build` | Passed |
| `php artisan test --compact` | 64 passed, 14 PostgreSQL-specific cases skipped; 1,519 assertions |
| `php scripts/test-postgres.php` | 78 passed, 1,548 assertions in isolated schemas |
| PostgreSQL suite with cached routes | 78 passed, 1,548 assertions; route cache cleared afterward |
| Laravel Pint on changed PHP files | Passed after formatting |
| `node scripts/operations-browser-qa.mjs` | Passed, including actual browser writes with CSRF |
| `node scripts/operations-keyboard-qa.mjs` | Passed: initial dialog focus, Tab containment, Escape and focus restoration |

The tests retain all existing Phase 1/2 cases, including concurrent admission allocation, and add attendance editing/duplicate/date checks, transfer history, staff attendance, timetable conflicts/working hours/breaks, examination scheduling, marks validation and atomic rollback, grading boundaries/gaps/overlaps/ties, publication immutability/versioned correction, actual report data, direct PostgreSQL guards, CSV safety, explicit unmarked export, tenant isolation, local-only guards, audit metadata, dashboard counts and session/enrollment context protection.

## Browser verification and artifacts

Headless Chrome verified all 14 screens; bulk student/staff attendance; period/timetable creation; visible conflict errors; exam creation/scheduling; roster-based marks entry; exam completion; draft report; publication; published marks locked; actual performance values; CSV export; A4 PDF creation; dark mode; and all rendered navigation links returning 200. No browser runtime errors or console warnings remained.

Populated attendance and marks grids, timetable, grading, report card and dashboard had no horizontal document overflow at **375, 768, 1024 and 1440px**. Desktop report-card and marks screenshots were inspected. The generated PDF has one page and an approximately 595 x 842 point media box (A4), and the print stylesheet hides navigation. One initial run hit a Node/undici HTTP harness error after UI checks; the harness was corrected to consume responses through the browser, and the full rerun passed.

Artifacts in ignored local storage:

- `storage/app/private/operations-qa/student-attendance.png`
- `storage/app/private/operations-qa/marks-entry.png`
- `storage/app/private/operations-qa/weekly-timetable.png`
- `storage/app/private/operations-qa/report-card.png`
- `storage/app/private/operations-qa/report-card-a4.pdf`
- `storage/app/private/operations-qa/dashboard-375.png`
- `storage/app/private/operations-qa/dashboard-768.png`
- `storage/app/private/operations-qa/dashboard-1024.png`
- `storage/app/private/operations-qa/dashboard-1440.png`
- `storage/app/private/operations-qa/dark-375.png`

Browser QA uses explicitly synthetic demo students and clearly named `QA Operations Exam ...` assessments with controlled test scores to exercise entry/publication. Those local QA records remain visible and are not presented as genuine school assessments. The seeder itself never supplies marks, attendance or published cards. No pre-existing unrelated records were deleted. The temporary PHP server is stopped after verification; start it using the command above.

## Files created

- `app/Modules/Academics/Operations/AcademicReports.php`
- `app/Modules/Academics/Operations/AttendanceService.php`
- `app/Modules/Academics/Operations/AuditTrail.php`
- `app/Modules/Academics/Operations/EnrollmentRoster.php`
- `app/Modules/Academics/Operations/ExamService.php`
- `app/Modules/Academics/Operations/GradingCalculator.php`
- `app/Modules/Academics/Operations/Models/AcademicAuditLog.php`
- `app/Modules/Academics/Operations/Models/AttendanceSession.php`
- `app/Modules/Academics/Operations/Models/Exam.php`
- `app/Modules/Academics/Operations/Models/ExamMark.php`
- `app/Modules/Academics/Operations/Models/ExamSchedule.php`
- `app/Modules/Academics/Operations/Models/ExamType.php`
- `app/Modules/Academics/Operations/Models/GradingBand.php`
- `app/Modules/Academics/Operations/Models/GradingScheme.php`
- `app/Modules/Academics/Operations/Models/PublishedReportCard.php`
- `app/Modules/Academics/Operations/Models/StaffAttendanceRecord.php`
- `app/Modules/Academics/Operations/Models/StudentAttendanceRecord.php`
- `app/Modules/Academics/Operations/Models/TimetableEntry.php`
- `app/Modules/Academics/Operations/Models/TimetablePeriod.php`
- `app/Modules/Academics/Operations/Models/WorkingDay.php`
- `app/Modules/Academics/Operations/OperationsCatalog.php`
- `app/Modules/Academics/Operations/OperationsController.php`
- `app/Modules/Academics/Operations/OperationsDashboard.php`
- `app/Modules/Academics/Operations/OperationsRequest.php`
- `app/Modules/Academics/Operations/OperationsService.php`
- `app/Modules/Academics/Operations/TimetableService.php`
- `database/factories/Operations/AcademicAuditLogFactory.php`
- `database/factories/Operations/AttendanceSessionFactory.php`
- `database/factories/Operations/ExamFactory.php`
- `database/factories/Operations/ExamMarkFactory.php`
- `database/factories/Operations/ExamScheduleFactory.php`
- `database/factories/Operations/ExamTypeFactory.php`
- `database/factories/Operations/GradingBandFactory.php`
- `database/factories/Operations/GradingSchemeFactory.php`
- `database/factories/Operations/PublishedReportCardFactory.php`
- `database/factories/Operations/StaffAttendanceRecordFactory.php`
- `database/factories/Operations/StudentAttendanceRecordFactory.php`
- `database/factories/Operations/TimetableEntryFactory.php`
- `database/factories/Operations/TimetablePeriodFactory.php`
- `database/factories/Operations/WorkingDayFactory.php`
- `database/migrations/2026_11_04_000006_create_academic_operations.php`
- `database/migrations/2026_11_04_000007_guard_academic_operations.php`
- `database/migrations/2026_11_04_000008_protect_operation_context.php`
- `database/migrations/2026_11_04_000009_preserve_session_operations.php`
- `database/seeders/OperationsDemoSeeder.php`
- `docs/PHASE_3_DELIVERY.md`
- `public/images/school-emblem.svg`
- `resources/css/operations.css`
- `resources/js/components/OperationsDashboard.vue`
- `resources/js/pages/Operations.vue`
- `resources/js/pages/ReportCard.vue`
- `routes/operations.php`
- `scripts/operations-browser-qa.mjs`
- `scripts/operations-keyboard-qa.mjs`
- `tests/Feature/AcademicOperationsTest.php`
- `tests/Unit/GradingCalculatorTest.php`

## Files modified

- `README.md`
- `routes/web.php`
- `app/Modules/Academics/AcademicService.php`
- `app/Modules/Academics/AcademicDashboardData.php`
- `app/Modules/Platform/Preview/PreviewController.php`
- `resources/js/app.ts`
- `resources/js/navigation.ts`
- `resources/js/types.ts`
- `resources/js/components/SchoolDashboard.vue`
- `resources/js/components/AppSidebar.vue`
- `resources/js/components/AppTopbar.vue`
- `resources/js/layouts/DashboardLayout.vue`
- `resources/css/app.css`

Generated Vite files are in ignored `public/build`. No Composer/npm package inputs, credentials, existing user accounts or Git commits were changed by this phase.

## Security limitations, remaining work and Phase 4

Operational writes still require the existing local environment, both explicit preview flags, an active catalog demo school and CSRF validation. Production/staging/non-local access fails closed even with the flag enabled or routes cached. No client school ID establishes authority. There is still no authentication, membership/role authorization or verified human actor; audit entries correctly state local development. There is no PostgreSQL RLS, and privileged internal SQL can bypass application scopes or disable database protections. Correct production config/cache deployment and a loopback-bound local server remain essential. Production operations must not be enabled before the authorization layer is implemented.

Remaining scope limits: no finance/fees, parent portal, login, password reset or SaaS subscription workflow was added. Staff identity remains the teacher profile; non-teaching staff onboarding and employment-period history are not added. There are no automatic absence assumptions, SMS/email notifications, holiday calendars, timetable rooms/rotation weeks or room conflicts. Period/assessment contexts with dependencies are deliberately locked rather than destructively rewritten. Correction changes marks and republishes; it does not silently edit old snapshots or rewrite an already assessed schedule. CSV is streamed synchronously with a bounded filter span; very large production datasets will benefit from background exports and searchable/paginated record selectors. Draft and published views intentionally differ. Legacy historical operations are not fabricated into the new audit log.

Recommended Phase 4: implement authentication, active school memberships, platform roles and per-action authorization; add authenticated actor attribution to the existing audit boundary; conduct a production tenancy/privacy/security review, backup/restore and operational monitoring work; then enable authorized production academic operations. Extend calendars/notifications and export jobs afterward. Keep any later student fee billing separate from platform subscription billing.
