# Phase 2 ? Academic management delivery

Delivered November 4, 2026 in `E:/school-saas`, extending the existing application. No Git commit, authentication starter, real user accounts, database reset, or unrelated record deletion was performed. No packages were added. The SchoolLabs shell and responsive design are retained.

## Completed modules

- **Sessions:** create, edit, list, activate/deactivate, mark current; ordered dates, no overlapping active periods, one current session per school, and dependent-record delete protection.
- **Classes and sections:** create, show, edit, delete unused records, grade/order/status, school/class-scoped codes, capacity enforcement and protection of enrolled/assigned records.
- **Subjects:** catalog CRUD, status/search, compulsory/elective class assignments with duplicate protection and many-to-many relationships.
- **Teachers:** profile CRUD, employee code/contact/status, teaching and form-teacher assignments, profile assignment history, no authentication accounts. A section has one teacher per subject per session and one form teacher; a teacher can be form teacher of only one section in a session. Timetable scheduling/conflicts are outside this phase.
- **Students:** admissions, database-backed per-school admission counters, profiles, editable details, guardian contacts, search/status filters, 15-row pagination, soft archival and archived history viewing.
- **Enrollments:** active enrollment creation, session/class/section selection, transfer, promotion, withdrawal and immutable location/session history. Transfer and promotion create successor rows with `previous_enrollment_id`; earlier records retain their original class and section. A failed destination check rolls back the source transition. Generic enrollment edits/deletes are rejected.
- **Live dashboard:** selected-session active enrollment/gender/class distribution, active school class/teacher totals, current-month admissions, and historical month-end enrollment snapshots. A session selector is school validated. Finance, parent totals, calendar events and activity stay labeled as samples. No financial workflows or transactions were added.

All editable catalogs have database-backed create/read/update/delete tests. Dependencies intentionally prevent unsafe deletions. Students are archived; enrollment history is never deleted. Promotion/transfer is available as a dedicated dialog from enrollment rows/profiles rather than a separate static page.

## Database tables and relationships

| Table | Integrity and purpose |
| --- | --- |
| `academic_sessions` | School FK, unique school/name and school/id, dates, active/current, partial unique current index and PostgreSQL overlap trigger |
| `academic_classes` | School FK, unique school/code, grade/order/status |
| `sections` | Composite school/class FK, unique school/class/code, positive optional capacity |
| `subjects` | School FK, unique school/code, status |
| `class_subject` | School-owned many-to-many class/subject assignments; composite FKs, unique school/class/subject, classification |
| `teachers` | School FK, unique school/employee code, optional future user FK, employment status |
| `teacher_assignments` | Composite school/session, school/teacher, school/class/section and school/class/subject references; partial unique teaching/form-teacher indexes |
| `admission_counters` | School primary/FK and transactional next-number allocation |
| `students` | School FK, unique school/admission number, minimal admission/guardian fields, date check and soft-delete timestamp |
| `enrollments` | Composite school/student, school/session, school/class/section and predecessor FKs; one active row per school/student/session; ordered date/state checks |

Both new migrations were applied successfully. The first attempt detected a duplicate index declaration and PostgreSQL rolled back that migration transaction; the declaration was fixed and the migration then passed. No existing records were removed to resolve it.

School-level transaction locks serialize counter allocation, session changes, assignment conflicts and capacity decisions. Composite foreign keys prevent cross-school relationships even through direct SQL. The overlap trigger and partial indexes enforce session/current/active-enrollment/assignment invariants at database level. Application writes ignore no arbitrary school authority: school IDs are supplied by trusted route resolution and client `school_id` is prohibited.

## Local access and security

Academic CRUD requires **all** of:

```dotenv
APP_ENV=local
LOCAL_PREVIEW_ENABLED=true
LOCAL_ACADEMIC_CRUD_ENABLED=true
```

The last flag was added explicitly to the existing local `.env`; the example defaults to false. The middleware additionally requires a catalog slug and `is_demo=true`/active school. Non-local environments and disabled flags return 404 for reads and all write verbs, including with cached routes. CSRF protection is active for browser writes. Models throw without tenant context, scope ordinary queries, and reject moving/saving/deleting loaded records under another school's context.

This remains a development capability, **not production user authorization**. Authentication, roles, active memberships, actor audit logs and database RLS are absent. Internal raw SQL or deliberate scope bypasses are outside application-level scope protection. Do not expose the unauthenticated local workspace publicly; bind to 127.0.0.1. Correct production environment/config-cache deployment remains required. Production management endpoints remain inaccessible.

## Routes

Base: `/preview/schools/{catalog-school}/academic/{resource}`.

Allowed resources: `sessions`, `classes`, `sections`, `subjects`, `class-subjects`, `teachers`, `teacher-assignments`, `students`, `enrollments`.

| Method | Suffix | Behavior |
| --- | --- | --- |
| GET | base | Directory, search, filters, pagination |
| GET | `/create` | Creation/admission/enrollment form |
| POST | base | Create validated school-owned record |
| GET | `/{record}` | Profile/detail and relevant history |
| GET | `/{record}/edit` | Edit form (enrollment history is not editable) |
| PUT | `/{record}` | Validated update; enrollment history edits rejected |
| DELETE | `/{record}` | Delete unused catalogs; soft-archive students; enrollment deletion rejected |
| POST | `/enrollments/{record}/change` relative to academic base | Transfer, promote or withdraw transaction |

Previous local module URLs for students, classes, subjects, academics and staff redirect into their functional academic directories when CRUD is enabled. In read-only mode they remain preview placeholders. All previous platform and dashboard URLs are preserved.

## Exact local URLs

Start `php artisan serve --host=127.0.0.1 --port=8000` after the README setup.

- School dashboard: http://127.0.0.1:8000/preview/schools/demo-maplewood/dashboard
- Academic sessions: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/sessions
- Classes: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/classes
- Sections: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/sections
- Subjects: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/subjects
- Class subject assignments: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/class-subjects
- Teacher directory: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/teachers
- Teacher assignments: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/teacher-assignments
- Student directory: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/students
- Student admission: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/students/create
- Enrollments / promotion / transfer: http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/enrollments
- Platform dashboard: http://127.0.0.1:8000/preview

For teacher/student profiles, use View in the directory; record IDs are database allocated. Other seeded demo slugs: `demo-crescent`, `demo-oakridge`, `demo-beacon`. The topbar preserves the selected academic resource when switching schools.

## Commands and verification

| Command / check | Result |
| --- | --- |
| `php artisan migrate --no-interaction` | Both new academic migrations applied without reset |
| `php artisan db:seed --class=AcademicDemoSeeder --no-interaction` | Created clearly labeled local fixtures only; no auth users or payments |
| `npm run typecheck` | Passed |
| `npm run build` | Passed |
| `php artisan test --compact` | 49 passed, 8 PostgreSQL-only checks skipped; 1,111 assertions |
| `php scripts/test-postgres.php` | 57 passed, 1,128 assertions in isolated schemas |
| Three simultaneous admission worker processes | 12 students, 12 distinct admission numbers, counter advanced to 13 |
| Cached-route PostgreSQL suite | All 57 tests passed; cache cleared after validation |
| Laravel Pint on changed PHP files | Passed after formatting |
| `node scripts/academic-browser-qa.mjs` | Passed in headless Chrome, with CSRF enabled |

The PostgreSQL-only checks cover integrity constraints, direct overlap protection and parallel allocation. Browser QA verified all nine directory/form screens, class create/edit/delete with a confirmation, generated admission number, student enrollment, transfer history, search, live academic labels and no console exceptions/warnings. Dashboard, directory and admission form had no document overflow at **375, 768, 1024 and 1440px**. Desktop/mobile screenshots were inspected. The academic test suite additionally covers catalog CRUD, assignment conflicts, capacity rollback, invalid input, school isolation across all academic models, non-local/disabled route protection, archived history, pagination, seeder repeatability and dashboard correctness.

QA intentionally creates clearly named Browser QA student/enrollment histories in the local demo workspace. Those histories remain available. It removes only the unused class created by that same QA run; it never deletes pre-existing application records. Other automated tests use in-memory SQLite and newly generated isolated PostgreSQL schemas. The temporary verification server is stopped after checks.

Screenshots in ignored storage:

- `storage/app/private/academic-qa/dashboard-live.png`
- `storage/app/private/academic-qa/student-directory.png`
- `storage/app/private/academic-qa/admission-375.png`
- `storage/app/private/academic-qa/admission-768.png`
- `storage/app/private/academic-qa/admission-1024.png`
- `storage/app/private/academic-qa/admission-1440.png`

## Files created

- `app/Modules/Academics/AcademicDashboardData.php`
- `app/Modules/Academics/AcademicEnvironmentGuard.php`
- `app/Modules/Academics/AcademicPolicy.php`
- `app/Modules/Academics/AcademicResources.php`
- `app/Modules/Academics/AcademicService.php`
- `app/Modules/Academics/Controllers/AcademicController.php`
- `app/Modules/Academics/EnsureAcademicPreview.php`
- `app/Modules/Academics/Models/AcademicClass.php`
- `app/Modules/Academics/Models/AcademicRecord.php`
- `app/Modules/Academics/Models/AcademicSession.php`
- `app/Modules/Academics/Models/ClassSubject.php`
- `app/Modules/Academics/Models/Enrollment.php`
- `app/Modules/Academics/Models/Section.php`
- `app/Modules/Academics/Models/Student.php`
- `app/Modules/Academics/Models/Subject.php`
- `app/Modules/Academics/Models/Teacher.php`
- `app/Modules/Academics/Models/TeacherAssignment.php`
- `app/Modules/Academics/Requests/AcademicDeleteRequest.php`
- `app/Modules/Academics/Requests/AcademicRecordRequest.php`
- `app/Modules/Academics/Requests/EnrollmentChangeRequest.php`
- `database/factories/Academic/AcademicClassFactory.php`
- `database/factories/Academic/AcademicSessionFactory.php`
- `database/factories/Academic/ClassSubjectFactory.php`
- `database/factories/Academic/EnrollmentFactory.php`
- `database/factories/Academic/SectionFactory.php`
- `database/factories/Academic/StudentFactory.php`
- `database/factories/Academic/SubjectFactory.php`
- `database/factories/Academic/TeacherAssignmentFactory.php`
- `database/factories/Academic/TeacherFactory.php`
- `database/migrations/2026_11_04_000004_create_academic_management.php`
- `database/migrations/2026_11_04_000005_add_form_teacher_constraint.php`
- `database/seeders/AcademicDemoSeeder.php`
- `docs/PHASE_2_DELIVERY.md`
- `resources/css/academic.css`
- `resources/js/pages/Academic.vue`
- `routes/academic.php`
- `scripts/academic-browser-qa.mjs`
- `scripts/admission-allocation-worker.php`
- `tests/Feature/AcademicManagementTest.php`
- `tests/Feature/AdmissionConcurrencyTest.php`

## Files modified

- `.env (local academic CRUD flag only; credentials retained)`
- `.env.example`
- `bootstrap/app.php`
- `config/preview.php`
- `phpunit.xml`
- `routes/web.php`
- `scripts/test-postgres.php`
- `app/Modules/Platform/Preview/PreviewController.php`
- `app/Modules/Platform/Preview/PreviewData.php`
- `resources/js/app.ts`
- `resources/js/navigation.ts`
- `resources/js/types.ts`
- `resources/js/pages/Preview.vue`
- `resources/js/layouts/DashboardLayout.vue`
- `resources/js/components/AppSidebar.vue`
- `resources/js/components/AppTopbar.vue`
- `resources/js/components/SchoolDashboard.vue`
- `resources/js/components/StatCard.vue`
- `resources/js/components/StudentGenderChart.vue`
- `resources/js/components/ClassDistributionChart.vue`
- `resources/js/components/EnrollmentCard.vue`
- `resources/css/app.css`
- `README.md`

Generated Vite artifacts are in ignored `public/build`; dependency versions and lockfiles are unchanged.

## Remaining limits and recommended Phase 3

No authentication, password reset, onboarding or fake login was introduced. There are no fee payments, attendance, examination or parent-portal workflows. Teachers have profiles, not user accounts. Promotion is individual, not bulk. Assignment conflict checks cover duplicated subject/form roles, not timetable collisions. Archived students can be viewed but restoring them is not implemented. Record selectors currently load the selected school's option lists; large production deployments will need searchable/paginated option endpoints. There is no production-facing tenant administration or actor audit trail.

Recommended Phase 3: add authentication deliberately; implement platform roles and active school memberships, per-action policies and actor audit logs; link teacher accounts through authorized workflows; strengthen operational monitoring/backups and conduct a production tenancy/security review. Only then enable production academic routes. Add attendance or examinations afterward, keeping platform subscription billing separate from future student fees.
