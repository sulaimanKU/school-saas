# Phase 1 delivery ? November 4, 2026

Implemented directly in `E:/school-saas`. No Git commit was made; the directory has no Git repository metadata. Existing tables and data were retained. No reset, fresh migration, or rollback was run against the public application schema. Test database refreshes ran only in in-memory SQLite and newly created temporary PostgreSQL schemas.

## Delivered

A modular Platform / Schools / Tenancy / Shared foundation, a responsive read-only local demo, light/dark theme persistence, school selection, directory search and plan filters, breadcrumbs, reusable cards/table/chart components, progress feedback, empty states, and placeholder academic modules. School configuration and plan pages have no pretend save or purchase controls. No authentication starter kit, login buttons, onboarding, operational student/fee/attendance/examination workflows, or real payment records were added.

Demo names, configuration, metrics, enrollment series, and subscription definitions are seeded from one deterministic catalog. Metrics are stored as `school_settings.preview_metrics`, not student records. The school selector and preview adapter require both a catalog slug and `is_demo=true`; unrelated schools and plans are excluded. Four schools, three plans, three feature definitions, four demo subscriptions, four domains, and eight settings are seeded. No demo users or memberships are seeded.

## Database tables created

| Table | Purpose and main constraints |
| --- | --- |
| `schools` | Unique validated slug, demo marker, indexed status |
| `school_domains` | School FK, globally unique hostname, verified timestamp |
| `school_settings` | School FK, unique school/key pair, JSON value |
| `subscription_plans` | Unique code, integer minor-unit monthly price, currency, capacity |
| `features` | Unique feature definition code |
| `feature_subscription_plan` | Composite primary key and both feature/plan foreign keys |
| `subscriptions` | Unique school FK (current subscription), restricted plan FK, dates and status |
| `school_memberships` | School/user FKs, unique school/user pair, role and status |

The third migration indexes foreign keys not already covered by unique/composite indexes. The second migration adds PostgreSQL checks for canonical slug/hostname casing, allowed statuses/roles, nonnegative pricing, positive capacity, uppercase currency, and ordered subscription dates. School-owned models expose `forTenant(TenantContext)`; using an empty context throws. School policy denies management until authorization is implemented. Memberships reference the existing user table but do not introduce authentication behavior.

## Commands and final results

| Command | Result |
| --- | --- |
| `php -v`, `php artisan --version`, `node -v`, `composer --version` | PHP 8.2.12, Laravel 12.71.0, Node 24.18.0, Composer 2.10.3 |
| `php artisan migrate:status` | Existing starter migrations were already applied; all three Phase 1 migrations now applied |
| `composer require "inertiajs/inertia-laravel:~2.0" --no-interaction` and `composer install --no-interaction` | Inertia Laravel 2.0.28 locked and installed; restricted-network and temporary archive-write attempts were retried successfully |
| `npm install vue@3 @inertiajs/vue3@2` | Vue 3.5.44 and Inertia Vue 2.3.29 installed |
| `npm install -D @vitejs/plugin-vue@6 typescript@5 vue-tsc` | Compatible frontend tooling installed; Vite 7.3.7 / Tailwind 4 retained |
| `npm install --package-lock-only --ignore-scripts` | Lockfile synchronized; unused icon dependency removed from final package inputs |
| `php artisan migrate --no-interaction` | All three new migrations applied to existing PostgreSQL database, without reset |
| `php artisan db:seed --class=LocalDemoSeeder --no-interaction` | Successful, including repeat execution |
| `npm run typecheck` | Passed |
| `npm run build` | Passed; Vite manifest, CSS and JS emitted to ignored `public/build` |
| `php artisan test` | 29 passed, 6 PostgreSQL-only checks skipped; 285 assertions |
| `php scripts/test-postgres.php` | 35 passed, 291 assertions; all PostgreSQL constraints tested |
| `php artisan route:cache`, PostgreSQL test suite, `php artisan route:clear` | Same 35 tests passed with cached routes; cache cleared afterward |
| Laravel Pint on changed PHP files | Applied standard PHP formatting |
| `php artisan route:list --except-vendor` | Root plus five guarded preview route definitions; no production management/auth routes |
| Local HTTP checks | Overview HTML 200, Inertia root present; selected Maplewood dashboard JSON 200 with correct school/page; no-store/private and noindex/nofollow headers verified |

Initial test harness and TypeScript adapter typing failures were fixed. The HTTP Inertia protocol check was repeated with the asset version header and passed. No browser automation was available, so responsive layout and theme behavior were implemented but not visually verified in a real browser. The temporary smoke-test server is stopped after validation; start the local server below to browse.

## Exact local URLs

Start with `php artisan serve --host=127.0.0.1 --port=8000` after the README setup.

| Page | URL |
| --- | --- |
| Platform overview | http://127.0.0.1:8000/preview |
| Schools directory | http://127.0.0.1:8000/preview/schools |
| School profile / configuration | http://127.0.0.1:8000/preview/schools/demo-maplewood |
| Plans | http://127.0.0.1:8000/preview/plans |
| School dashboard | http://127.0.0.1:8000/preview/schools/demo-maplewood/dashboard |
| Students placeholder | http://127.0.0.1:8000/preview/schools/demo-maplewood/students |
| Academics placeholder | http://127.0.0.1:8000/preview/schools/demo-maplewood/academics |
| Attendance placeholder | http://127.0.0.1:8000/preview/schools/demo-maplewood/attendance |
| Examinations placeholder | http://127.0.0.1:8000/preview/schools/demo-maplewood/examinations |
| Student fees placeholder | http://127.0.0.1:8000/preview/schools/demo-maplewood/fees |
| System settings | http://127.0.0.1:8000/preview/settings |

Other seeded school slugs: `demo-crescent`, `demo-oakridge`, `demo-beacon`. The selector navigates to their corresponding read-only previews. `.schools.test` domains are fixture hostname mappings for resolution tests; they do not configure DNS, virtual hosts, or production tenant routes. Use the loopback URLs above.

## Security limitations and next phase

Tenant isolation is a foundation, **not a completed security boundary**. No authentication, active-membership authorization, platform-role authorization, database RLS, write ownership enforcement, audit trail, or production tenant access exists yet. Explicit Eloquent scopes can be bypassed by unscoped code. `TenantContext::set()` and the resolver must only be used within future trusted authenticated/authorized entrypoints; neither grants access. Policy checks currently deny all school management. Local preview has no user authorization and should remain bound to loopback. Correct production environment/config cache deployment is required.

Recommended Phase 2: implement authentication deliberately, platform roles and active memberships, then policy-enforced tenant context middleware, authorized Form Requests and transactional management actions, cross-tenant access tests, and audit logging. Only after that enable production school management. Add operational academic workflows in a later phase. Platform billing needs an independent provider/invoice/event model and must stay separate from student fee billing.

## Created source files

- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Modules/Platform/Models/Feature.php`
- `app/Modules/Platform/Models/Subscription.php`
- `app/Modules/Platform/Models/SubscriptionPlan.php`
- `app/Modules/Platform/Preview/DemoCatalog.php`
- `app/Modules/Platform/Preview/EnsureLocalPreview.php`
- `app/Modules/Platform/Preview/PreviewController.php`
- `app/Modules/Platform/Preview/PreviewData.php`
- `app/Modules/Schools/Models/School.php`
- `app/Modules/Schools/Models/SchoolDomain.php`
- `app/Modules/Schools/Models/SchoolMembership.php`
- `app/Modules/Schools/Models/SchoolSetting.php`
- `app/Modules/Schools/SchoolPolicy.php`
- `app/Modules/Shared/BelongsToSchool.php`
- `app/Modules/Tenancy/TenantContext.php`
- `app/Modules/Tenancy/TenantResolver.php`
- `config/preview.php`
- `database/factories/FeatureFactory.php`
- `database/factories/SchoolDomainFactory.php`
- `database/factories/SchoolFactory.php`
- `database/factories/SchoolMembershipFactory.php`
- `database/factories/SchoolSettingFactory.php`
- `database/factories/SubscriptionFactory.php`
- `database/factories/SubscriptionPlanFactory.php`
- `database/migrations/2026_11_04_000001_create_saas_foundation.php`
- `database/migrations/2026_11_04_000002_add_foundation_integrity_checks.php`
- `database/migrations/2026_11_04_000003_index_foundation_foreign_keys.php`
- `database/seeders/LocalDemoSeeder.php`
- `docs/PHASE_1_DELIVERY.md`
- `resources/js/app.ts`
- `resources/js/components/MetricCard.vue`
- `resources/js/components/SchoolTable.vue`
- `resources/js/components/TrendChart.vue`
- `resources/js/pages/Preview.vue`
- `resources/js/types.ts`
- `resources/views/app.blade.php`
- `scripts/test-postgres.php`
- `tests/Feature/SaasFoundationTest.php`
- `tsconfig.json`

## Modified source/configuration files

- `.env (local flag only; credentials retained; do not commit)`
- `.env.example`
- `app/Models/User.php`
- `app/Providers/AppServiceProvider.php`
- `bootstrap/app.php`
- `composer.json`
- `composer.lock`
- `config/database.php`
- `database/seeders/DatabaseSeeder.php`
- `package.json`
- `package-lock.json`
- `README.md`
- `resources/css/app.css`
- `routes/web.php`
- `tests/Feature/ExampleTest.php`
- `tests/TestCase.php`
- `vite.config.js`

Generated artifacts: ignored `public/build/manifest.json` and hashed CSS/JS; normal Composer package discovery/cache files. No secrets are included in this record.
