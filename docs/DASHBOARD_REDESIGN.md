# SchoolLabs dashboard redesign

Implemented November 4, 2026 in the existing Laravel application. The attached text brief was available; its referenced screenshots were not attached. The result follows the written SchoolLabs design specifications, but an exact comparison to the first screenshot cannot be verified.

## Delivered

- New 212px scrollable sidebar with all five requested navigation groups, collapsible groups, consistent Lucide icons, active states, and separate platform navigation.
- New horizontal school switcher/topbar, campus-preview navigation, working search over demo schools and modules, sample-event notifications, dark/light mode, and an explicitly labeled demo profile.
- Four statistics cards and a three-column desktop dashboard containing gender distribution, class distribution, events, fee collection, school finance, anonymous overdue groups, enrollment, activity, and quick access.
- Responsive tablet and mobile layouts, keyboard focus states, skip navigation, mobile drawer, loading feedback, and preserved directory empty/search states.
- Reused the existing school directory, platform overview, subscription plan and settings views inside the new shared layout.

## Data and security

`SchoolDashboardData` derives deterministic aggregate samples from the selected demo school's seeded metrics. The class dataset sums to the selected school's enrollment, and the gender/chart aggregates reconcile to that dataset. Teaching staff comes from the seeded metric; support staff and parent totals are explicitly illustrative aggregates. Monthly fee amounts derive from sample enrollment and a documented illustrative PKR 8,500 rate. Expected equals collected plus pending; overdue groups reconcile to pending. Enrollment cohorts sum to the current month's net enrollment increase. No real student names, real arrears, invoices, revenue, payments, or transactions are invented or persisted.

No schema, model, membership, resolver, tenancy scope, authentication, or production-access changes were made. The only backend additions are a read-only data presenter and new placeholder names in the existing local-preview route constraint. Every new module is protected by the same local-environment and explicit-config middleware. No HTTP writes or authentication routes were added. Platform subscription values remain visually separate from school fee samples.

## Components and files

Created reusable components in `resources/js/components/`:

- `AppIcon.vue`
- `AppSidebar.vue`
- `AppTopbar.vue`
- `DashboardCard.vue`
- `StatCard.vue`
- `DonutChart.vue`
- `StudentGenderChart.vue`
- `ClassDistributionChart.vue`
- `FeeCollectionCard.vue`
- `FinanceOverviewChart.vue`
- `UpcomingEventsCard.vue`
- `FeeDefaultersCard.vue`
- `EnrollmentCard.vue`
- `RecentActivityCard.vue`
- `SchoolDashboard.vue`

Other created files:

- `resources/js/layouts/DashboardLayout.vue`
- `resources/js/navigation.ts`
- `resources/js/format.ts`
- `resources/css/dashboard.css`
- `resources/css/platform.css` (retained platform styling; obsolete shell rules removed)
- `app/Modules/Platform/Preview/SchoolDashboardData.php`
- `tests/Feature/SchoolDashboardPreviewTest.php`
- `scripts/dashboard-browser-qa.mjs`
- `docs/DASHBOARD_REDESIGN.md`

Modified files:

- `resources/js/pages/Preview.vue`
- `resources/js/components/MetricCard.vue` (reused with Lucide icons)
- `resources/js/components/TrendChart.vue` (reused for platform enrollment)
- `resources/js/types.ts`
- `resources/js/app.ts`
- `resources/css/app.css`
- `app/Modules/Platform/Preview/PreviewData.php`
- `routes/web.php`
- `package.json` / `package-lock.json` (only `@lucide/vue` added)
- `README.md`

`SchoolTable.vue` is reused unchanged. Charts use CSS/SVG and accessible text descriptions; no charting library was needed.

## Validation

| Check | Result |
| --- | --- |
| `npm run typecheck` | Passed |
| `npm run build` | Passed |
| `php artisan test --compact` | 33 passed, 6 PostgreSQL-specific tests skipped, 686 assertions |
| `php scripts/test-postgres.php` | 39 passed, 692 assertions |
| PostgreSQL suite with `php artisan route:cache` | 39 passed, 692 assertions; route cache cleared afterward |
| Laravel Pint on changed PHP files | Passed after formatting |
| `node scripts/dashboard-browser-qa.mjs` | Passed in headless Chrome |

Browser checks verified four statistics and nine dashboard panels; search result filtering and navigation; school selector changing all dashboard metrics; chart period changes; notification popup; dark mode; separate platform view; settings rendering; module placeholder labels; and all 25 rendered preview navigation targets returning HTTP 200. No console warnings or runtime exceptions were observed. No document overflow at 1440, 1024, 768, 390, and 360px widths. The mobile drawer opened and closed correctly. Desktop and mobile screenshots were inspected, and typography was refined after inspection. Headless Chrome required an escalated run because the sandboxed attempt could not start its debugging endpoint.

Browser artifacts (ignored application storage):

- `storage/app/private/dashboard-qa/desktop.png`
- `storage/app/private/dashboard-qa/mobile.png`
- `storage/app/private/dashboard-qa/dark.png`

The script uses a dedicated Chrome profile under `storage/framework/testing/dashboard-chrome`. It closes its own browser. The temporary PHP server used for validation is stopped after checks.

## URLs and routes

Start `php artisan serve --host=127.0.0.1 --port=8000`. Existing `.env` local preview configuration is preserved.

- School dashboard: http://127.0.0.1:8000/preview/schools/demo-maplewood/dashboard
- Platform dashboard: http://127.0.0.1:8000/preview
- Schools directory: http://127.0.0.1:8000/preview/schools
- School configuration: http://127.0.0.1:8000/preview/schools/demo-maplewood/profile
- Platform plans: http://127.0.0.1:8000/preview/plans
- System settings: http://127.0.0.1:8000/preview/settings

All module placeholders for Maplewood:

- http://127.0.0.1:8000/preview/schools/demo-maplewood/students
- http://127.0.0.1:8000/preview/schools/demo-maplewood/classes
- http://127.0.0.1:8000/preview/schools/demo-maplewood/calendar
- http://127.0.0.1:8000/preview/schools/demo-maplewood/academics
- http://127.0.0.1:8000/preview/schools/demo-maplewood/subjects
- http://127.0.0.1:8000/preview/schools/demo-maplewood/timetables
- http://127.0.0.1:8000/preview/schools/demo-maplewood/parents
- http://127.0.0.1:8000/preview/schools/demo-maplewood/clubs
- http://127.0.0.1:8000/preview/schools/demo-maplewood/communications
- http://127.0.0.1:8000/preview/schools/demo-maplewood/accounts
- http://127.0.0.1:8000/preview/schools/demo-maplewood/fees
- http://127.0.0.1:8000/preview/schools/demo-maplewood/expenses
- http://127.0.0.1:8000/preview/schools/demo-maplewood/accounting
- http://127.0.0.1:8000/preview/schools/demo-maplewood/transport
- http://127.0.0.1:8000/preview/schools/demo-maplewood/staff
- http://127.0.0.1:8000/preview/schools/demo-maplewood/marketing
- http://127.0.0.1:8000/preview/schools/demo-maplewood/campuses
- http://127.0.0.1:8000/preview/schools/demo-maplewood/school-settings
- http://127.0.0.1:8000/preview/schools/demo-maplewood/attendance
- http://127.0.0.1:8000/preview/schools/demo-maplewood/examinations

Other seeded school slugs: `demo-crescent`, `demo-oakridge`, `demo-beacon`. Switching the school in the topbar preserves the selected school module, or opens its dashboard when switching from a platform page.

## Deliberately incomplete

Academic, campus, communication, staff and finance modules are clearly labeled read-only placeholders. The campus control opens that placeholder because actual campus records and campus switching do not exist yet. Search covers only available local demo schools, platform pages and module previews. Notification entries are sample events. Profile is a demo label with no account behavior. There are no exports, real payments, authenticated operations, or production school access. Tenant isolation remains incomplete until authentication and authorization are implemented. No Git commit was made, and PostgreSQL was not reset or reseeded by this redesign.
