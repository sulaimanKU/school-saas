# SchoolLabs ? Multi-tenant School SaaS

The Laravel 12 / PostgreSQL modular monolith includes academic management, academic operations, and Phase 4 school finance with Vue 3, TypeScript, Inertia and Tailwind. Management is available exclusively in an explicitly enabled local demo workspace. Authentication, production management and parent portals remain outside this phase.

## Run locally

Existing database credentials are preserved in `.env`. Required PHP extensions include `pdo_pgsql` and `pdo_sqlite` for fast tests.

```sh
composer install
npm ci
php artisan migrate
```

Set these values for local academic development:

```dotenv
APP_ENV=local
LOCAL_PREVIEW_ENABLED=true
LOCAL_ACADEMIC_CRUD_ENABLED=true
```

```sh
php artisan config:clear
php artisan db:seed --class=LocalDemoSeeder
php artisan db:seed --class=AcademicDemoSeeder
php artisan db:seed --class=OperationsDemoSeeder
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Open http://127.0.0.1:8000/preview/schools/demo-maplewood/dashboard. Use `npm run dev` in a second terminal for frontend development. Demo seeding is explicit; the default database seeder creates no fake users. AcademicDemoSeeder only targets catalog demo schools and preserves existing matching records. It creates obviously labeled synthetic academic students and teachers, without authentication accounts or financial transactions. No reset/fresh migration is required.

## Academic workflow

1. Create an active, non-overlapping academic session and mark it current.
2. Create classes, sections and subjects. Assign subjects to classes.
3. Create teacher profiles, teaching assignments and form-teacher assignments.
4. Admit a student; the school-scoped admission number is allocated transactionally.
5. Create the student's enrollment in an active session and section.
6. Use **Change** in the enrollment directory/profile for transfer, promotion or withdrawal. Previous class/section/session rows are retained.

For promotion, create and activate a later session and a class with a higher grade first. Sessions may both be active when their date periods do not overlap; only one can be current. A transfer stays within the same session/class. Withdrawal closes the active enrollment. Students with active enrollments must be withdrawn before archiving. Archived students remain available using the Archived directory filter and retain their history.

## Validation

```sh
npm run typecheck
npm run build
php artisan test --compact
php scripts/test-postgres.php
node scripts/academic-browser-qa.mjs
node scripts/operations-browser-qa.mjs
node scripts/operations-keyboard-qa.mjs
```

The PostgreSQL runner creates isolated temporary schemas, refuses cached configuration, and drops only the generated schemas afterward. Tests reject the public application schema before database refresh. Parallel admission workers are restricted to these test schemas. The Chrome QA script requires the local server, enabled academic CRUD and seeded fixtures. It creates a clearly named QA student/enrollment, checks a transfer, and leaves that new history intact; it deletes only its own newly created unused QA class. It requires the installed Windows Chrome executable and a dedicated workspace profile. The older dashboard-browser-qa script targets the Phase 1 read-only sample mode.

## Architecture and security

- `app/Modules/Platform`: SaaS plans, features, subscriptions, and local preview read models.
- `app/Modules/Schools`: schools, domains, settings and future memberships.
- `app/Modules/Tenancy`: validated tenant resolution and request-scoped context.
- `app/Modules/Academics`: school-scoped models, Form Requests, local-capability policy, transactional services, read models and controllers.
- `app/Modules/Shared`: explicit tenant query patterns for foundational models.

Academic models require tenant context and apply a global school scope. Every academic relationship uses school-aware composite database foreign keys where applicable. Client school IDs are prohibited. All academic routes require local environment, both explicit configuration flags, and a catalog school with `is_demo=true`. An early guard denies non-local CRUD even before CSRF middleware. CSRF remains enabled for browser writes. Keep the server bound to loopback.

Tenant identification is not user authorization. There is no authentication or production membership/role authorization. There is no database RLS; raw SQL or explicitly unscoped internal code can bypass application scopes. Production academic routes fail closed. Deploy production with correctly built production config, not a local config cache. Before production use, implement authentication, active memberships, policies, audit logging and security review.

The school dashboard identifies live academics, attendance, examinations, published results and audit activity alongside live school finance when enabled. Parent totals and sample events remain labeled demonstrations. Platform subscriptions remain separate from student fee billing.

Delivery records: [Phase 3 academic operations](docs/PHASE_3_DELIVERY.md), [Phase 2 academic management](docs/PHASE_2_DELIVERY.md), [dashboard redesign](docs/DASHBOARD_REDESIGN.md), [Phase 1 foundation](docs/PHASE_1_DELIVERY.md).

## Phase 3 operations workflow

Open http://127.0.0.1:8000/preview/schools/demo-maplewood/academic/operations/student-attendance.

- **Attendance:** choose session, section and date; load actual enrollment roster, record selected statuses, then submit. Empty rows stay unmarked/unchanged. Transfers preserve the original attendance enrollment. Staff attendance reuses teacher profiles.
- **Timetables:** configure working days/hours and periods; add session/section entries using existing teaching assignments. Break/non-teaching periods have no teaching assignment. Overlapping teacher/section times are rejected. Use Teacher Timetable and Print for weekly schedules.
- **Exams:** create a grading scheme and exam type, then a draft exam. Add class/section/subject schedules with maximum/passing marks. Marks entry loads actual exam-date enrollments; missing marks are not zero, absence is distinct, and exemption excludes the subject from the denominator.
- **Publication:** complete the exam, open Results, select it and confirm publication. Every eligible result must be recorded. Published report snapshots are immutable. A correction requires an explicit reopen action with a reason, followed by marks correction and a new publication version. Previous versions stay printable and unchanged.
- **Reports:** select attendance, daily roster, monthly/student/staff, class comparison, or published examination analyses. CSV exports stream all matches; the on-screen preview is limited to 100 detail rows. Daily roster reports require session/section and show unmarked separately. Attendance rates count present + late over present + late + absent; excused/leave/holiday/unmarked are excluded.

`OperationsDemoSeeder` seeds only working hours, periods, exam types and editable grading configuration. It never generates attendance, marks or published results. Browser QA explicitly enters controlled test values in clearly named QA examinations for existing synthetic demo students; those database-backed test records remain in the local workspace. It uses a dedicated Chrome profile and generates screenshots and an A4 PDF under ignored storage. Do not run QA against a real school.

Default grading boundaries use lower-inclusive/upper-exclusive ranges (100 is included in the top range). Ranking is optional per school-owned grading scheme, uses competition ties (1, 1, 3), and compares students within a class/section cohort. Teacher remarks come from actual marks-entry fields. School logos use an existing local `/images/` or `/storage/` asset path. No server-side external image fetch or PDF dependency is needed.


## Phase 4 school finance

School fee billing is separate from SaaS subscriptions. Enable finance explicitly in the existing local environment:

```dotenv
APP_ENV=local
LOCAL_PREVIEW_ENABLED=true
LOCAL_FINANCE_ENABLED=true
```

```sh
php artisan migrate
php artisan db:seed --class=FinanceDemoSeeder
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

Open http://127.0.0.1:8000/preview/schools/demo-maplewood/finance/dashboard. The local flag is enabled in this workspace; `.env.example` defaults it off. FinanceDemoSeeder adds only fee/expense categories, never invoices or transactions.

1. Configure school currency before adding financial records (PKR by default).
2. Create fee structures with session, class, optional section, frequency, effective dates and amounts. Add student concessions if needed.
3. Assign structures individually or by roster, or select **Auto assign** during invoice generation. Section overrides take precedence during automatic assignment.
4. Generate a normalized monthly billing period; only due monthly/quarterly/annual/one-time obligations are included. Draft invoices require issuance before collection. Existing monthly invoices remain unchanged on repeat runs.
5. Record a manual payment and invoice allocations, or retain student credit and use **Apply credit** later. Open the historical receipt to print/reprint A4.
6. Preview a configured late fee before posting. Refund requests reserve eligibility; explicitly post or reject them. Reverse erroneous payments with a reason instead of deleting them.
7. Record expenses and optional private PDF/JPEG/PNG receipts (5 MB maximum). Review reports, statements, aging, overdue balances and streamed CSV exports.

Amounts use integer minor units and two-decimal inputs. Concessions never stack: the largest eligible concession per item wins. Invoice totals and receipts retain historical snapshots. Pending refunds are not cash outflows; posted refunds reopen allocated invoice balances. Manually recorded bank/cheque entries are not independently verified.

```sh
php scripts/test-postgres.php
npm run typecheck
npm run build
```

For Chrome workflow/viewport/print QA, start the local server on port 8014 and run `node scripts/finance-browser-qa.mjs` (or set `PREVIEW_URL`). QA creates explicitly named finance configuration and manual transactions only for existing synthetic demo students; it preserves those records. See [Phase 4 delivery](docs/PHASE_4_DELIVERY.md) for routes, accounting rules, verification and limitations.
