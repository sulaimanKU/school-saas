# Phase 4 — School finance delivery

Delivered November 4, 2026 in `E:/school-saas`. This extends the existing Laravel/PostgreSQL application and preserves academic records, authentication boundaries, the SchoolLabs shell and SaaS subscription billing. No dependencies, authentication accounts, database reset/truncate, external payment integration, or Git commit were introduced.

## Completed modules

- School fee and expense category CRUD. The explicit local seeder supplies the ten requested fee category defaults and five expense categories without fabricating transactions.
- School/session/class fee structures, optional section overrides, effective dates, active status, monthly/quarterly/annual/one-time frequencies, duplicate-period protection and immutable assigned versions.
- Individual and transactional roster assignment, student adjustments, enrollment snapshots and assignment activation/deactivation. Invoice generation can automatically assign applicable structures; section-specific configuration wins over the class default.
- Single-student/class/section invoice generation, draft issuance, historical items/concessions, school invoice counters, due dates, void reasons and derived payment/overdue states. Repeat billing returns the original invoice.
- Manual cash/bank-transfer/cheque/other payments, multiple invoice allocations, partial payments, unallocated student credit, later credit allocation, idempotent posting and reasoned reversals.
- Numbered, printable/reprintable A4 receipts with historical school/student/class/section/payment/allocation snapshots and clear reversed/refunded annotations. Bank/cheque entries are explicitly not independently verified.
- Fixed/percentage scholarships, sibling, staff-child, need-based and custom concessions with approval notes, student/session/category scope, effective dates, history and explicit non-stacking rules.
- Fixed/percentage late-fee policies, grace days, caps, invoice exemptions, preview before confirmation and one separately posted penalty per invoice.
- Manual refund requests, reserved eligibility, explicit posting/rejection and audit history. Posted allocated refunds reopen the relevant invoice balance; original payments remain intact.
- Immutable posted school expenses, payees, methods, categories, dates, descriptions and optional private PDF/JPEG/PNG attachments up to 5 MB. Downloads resolve the expense under the current tenant before accessing storage.
- Overdue lists, due-date filters, 1–30/31–60/61–90/over-90 aging, student statements, class/student outstanding totals, daily/monthly/class/category/method collections, concession/refund/expense reports, expense-category summaries, streamed CSV and browser printing.
- Live school finance dashboard metrics, six-month collection/expense trends, recent payments and top overdue student balances. Live-enabled schools display zeros/empty states instead of sample finance. Platform subscription statistics remain separate.

## Files created and modified

New implementation:

- `app/Domain/Finance/`: `Money`, `FinanceCatalog`, `FinanceRequest`, `FinanceService`, `FinanceReports`, `FinanceController`, `EnsureFinancePreview`, `FinanceEnvironmentGuard`, and fourteen school-scoped concrete models plus `FinanceRecord`.
- `database/migrations/2026_11_04_000010_create_school_finance.php` through `2026_11_04_000016_preserve_billed_enrollment_days.php`: seven additive migrations.
- `database/seeders/FinanceDemoSeeder.php`, `routes/finance.php`.
- `resources/js/pages/Finance.vue`, `FinanceDocument.vue`, `resources/js/components/FinanceDashboard.vue`, `resources/css/finance.css`.
- `tests/Feature/SchoolFinanceTest.php`, `FinanceConcurrencyTest.php`, `scripts/finance-posting-worker.php`, `scripts/finance-browser-qa.mjs`, this delivery record.

Integration changes: `bootstrap/app.php`, `config/preview.php`, `.env.example`, local `.env` finance flag, `routes/web.php`, preview controller/read model, `resources/js/app.ts`, navigation, school dashboard, dashboard layout, CSS imports, `phpunit.xml`, PostgreSQL test runner and README. Existing credentials and package inputs were preserved. Vite output and QA artifacts remain in ignored directories.

## Database tables and relationships

Every financial entity has a school FK, school/id identity, timestamps and composite school-scoped references. Delete restrictions preserve dependencies. Reporting indexes cover school/date, class/session, invoice/payment and category access paths.

| Table | Purpose and integrity |
| --- | --- |
| `fee_categories`, `expense_categories` | Unique school/name, active configuration |
| `finance_counters` | School/kind primary key, transactional invoice/receipt numbering |
| `fee_structures` | Session/class/optional section/category, money/currency, frequency/dates, overlap guards |
| `student_fee_assignments` | Student/enrollment/session/structure/category, amount and identity snapshots, unique enrollment/structure |
| `student_discounts` | Student/session/optional category, concession kind, fixed value or basis points, approval/effective dates |
| `late_fee_policies` | Fixed value/basis points, grace days, positive cap |
| `student_invoices` | Student/enrollment/session/class/section, unique school number and student/session/month, immutable totals/identity |
| `student_invoice_items` | Assignment/category/student/session obligations, amounts/concession snapshots; unique student/session/category/month |
| `invoice_penalties` | One immutable policy/amount snapshot per invoice |
| `student_payments` | Student, amount/currency/method/date, unique idempotency key and request hash, posted/reversed state |
| `payment_allocations` | Immutable payment/invoice amounts, unique payment/invoice |
| `payment_receipts` | Unique school receipt number and payment, immutable JSON snapshot |
| `student_refunds` | Payment/optional invoice, requested/posted/rejected state, unique idempotency key |
| `school_expenses` | Category/payee/method/date/amount, idempotency key and private attachment pointer |

The existing append-only `academic_audit_logs` table is reused with `finance.*` operation names, financial entity identity, before/after metadata, reasons, timestamps and `authenticated=false` local-development-preview actor context. No human identity is invented.

PostgreSQL additionally enforces monetary bounds, status/frequency checks, effective-period conflicts, financial enrollment identity, student/currency allocation identity, payment/invoice over-allocation limits, refund reservations, immutable snapshots and posted history. Deferred constraint triggers verify invoice totals against items when a transaction commits. Financially referenced enrollment identities cannot be rewritten. Transfer/withdrawal end dates are exclusive; promotion/completion end dates retain the existing academic inclusive behavior.

## Routes and local URLs

Base: `http://127.0.0.1:8000/preview/schools/demo-maplewood/finance`.

| Method | Suffix | Operation |
| --- | --- | --- |
| GET | `/dashboard` | Live school finance overview |
| GET | `/{page}` | All finance screens listed below |
| POST | `/{resource}` | Validated configuration or transactional posting |
| PUT / DELETE | `/{categories,structures,discounts,late-fees,expense-categories}/{id}` | Edit/delete eligible configuration only |
| POST | `/invoices/{id}/{issue,void,preview-penalty,post-penalty}` | Controlled invoice actions |
| POST | `/payments/{id}/{reverse,allocate}` | Reversal or later credit allocation |
| POST | `/refunds/{id}/{post,reject}` | Process a manual refund request |
| POST | `/assignments/{id}/{activate,deactivate}` | Control future billing |
| GET | `/documents/{invoice,receipt}/{id}` | Print invoice or historical payment receipt; receipt URL uses payment ID |
| GET | `/expenses/{id}/attachment` | Tenant-protected private download |
| GET | `/reports/export` | Stream matching CSV records |
| POST | `/settings` | Set school currency before finance records exist |

Exact screen suffixes: `/dashboard`, `/categories`, `/structures`, `/assignments`, `/generate`, `/invoices`, `/payments`, `/discounts`, `/late-fees`, `/refunds`, `/expenses`, `/expense-categories`, `/defaulters`, `/statements`, `/reports`, `/audit`.

The existing school dashboard remains `http://127.0.0.1:8000/preview/schools/demo-maplewood/dashboard`. Legacy Accounts/Fee Collection/Expenses/Accounting URLs redirect to finance when enabled. Other catalog school slugs are `demo-crescent`, `demo-oakridge`, `demo-beacon`; switching schools retains the finance page.

## Financial calculation rules

1. Money is stored as signed 64-bit integer minor units with positive/nonnegative database constraints. Inputs have at most two decimal places; no binary floating-point arithmetic determines posted money. Currency defaults to PKR and is configured before financial records. Display always shows two decimals.
2. Percentage values use integer basis points; calculations round half up using integer division. Discounts are capped at the item amount. The largest eligible concession per item wins; concessions do not stack. Issued item snapshots retain the actual chosen concession.
3. Billing periods normalize to the first of the month. Monthly/quarterly/annual cadence follows the structure's effective start month; one-time fees begin at the assignment's first eligible month and are not charged again after transfer. There is no daily proration. Transfers within a month do not duplicate a fee category. The earliest eligible assignment for that month supplies its historical item.
4. Invoice total equals subtotal minus discounts. A late fee is a separately immutable posting, not a rewrite of that original total. Effective charge is original total plus posted penalty.
5. Net invoice paid equals allocations from posted payments minus posted allocated refunds. Outstanding equals effective charge minus net paid. Drafts are excluded from receivables; voids have zero outstanding. Paid/partially-paid/overdue are calculated, not permanently written states. An unpaid invoice becomes overdue only after its due date.
6. Unallocated credit equals the posted payment minus allocations and requested/posted refunds against credit. Existing allocation rows are never destructively edited. Apply-credit retries to the same invoice/value are idempotent; a different amount against that same payment/invoice is rejected.
7. Refund requests reserve the relevant allocation or unallocated credit. Rejected requests release it; posted refunds preserve original payment rows. Reversal is blocked while any requested/posted refund exists. A payment reversal invalidates its allocations for balance calculations and marks its receipt reversed.
8. A penalty is eligible only for a non-exempt issued invoice with outstanding money after grace days. Its value is fixed or a percentage of the previewed outstanding amount, capped by policy. Posting rechecks the previewed amount under the school lock. One penalty per invoice prevents repeated accumulation.
9. Invoice reports use invoice issue dates; collections use payment dates; refunds/expenses use their own record dates. Gross collections include credit and exclude reversed payments; refunds/expenses are separate. Class/category collection reports describe allocated gross collections. Category allocation apportions by discounted item share, with the residual explicitly labeled penalty/allocation rounding. Invoice category filters select invoices containing that category; they do not relabel whole-invoice totals as category revenue.
10. Statements list invoice balance snapshots followed by payment/reversal/refund events. Invoice paid columns and payment-event columns describe different views of the same activity and must not be added together. Aging uses today's date in Asia/Karachi. CSV escapes spreadsheet formula prefixes and streams all matching rows; previews show at most 100 detail rows.

## Verification results

| Check | Result |
| --- | --- |
| Additive `php artisan migrate --no-interaction` | All seven finance migrations applied; no reset or unrelated record deletion |
| `php artisan db:seed --class=FinanceDemoSeeder --no-interaction` | Configuration-only categories seeded |
| `php artisan test --compact` | 82 passed, 17 PostgreSQL-only skips; 1,679 assertions |
| `php scripts/test-postgres.php` | 99 passed; 1,738 assertions in isolated temporary schemas |
| PostgreSQL suite with cached routes | 99 passed; 1,736 assertions; route cache cleared afterward |
| Three real concurrent finance worker processes | Duplicate billing/payment requests committed once; invoice numbers unique; competing overpayments rejected |
| `npm run typecheck` | Passed |
| `npm run build` | Passed |
| Laravel Pint on new finance PHP files | Passed after formatting |
| `node scripts/finance-browser-qa.mjs` | Passed with real browser CSRF, all 16 screens, billing/payment/receipt/report filtering and viewport checks |

Tests cover configuration CRUD, duplicate assignment/billing, monthly numbering, frequency calculations, transfer snapshots/section overrides, concessions and zero-total invoices, partial/multiple allocations, credit, request idempotency, refunds/reservations/reversals, penalties/exemptions/caps, private expenses/upload validation, reporting/aging/empty school metrics, tenant isolation, local/non-local route guards, audit context, PostgreSQL immutable/amount guards and deferred invoice item totals. Earlier academic/platform tests remain in the same suites.

## Browser verification

Chrome completed category/structure creation, automatic assignments, invoice generation, payment allocation, receipt viewing, actual report filter submission and school dashboard integration without console warnings/runtime exceptions. Dashboard, payments, reports and an open structure form had no document overflow at **375, 768, 1024 and 1440px**. The dark mobile school dashboard also passed. Mobile dashboard and desktop receipt screenshots were visually inspected.

The printed receipt PDF has one page and a `594.96 × 841.92` point A4 media box. Print styling hides navigation/actions. Artifacts are in ignored `storage/app/private/finance-qa/`, including `receipt.png`, `receipt-a4.pdf`, viewport screenshots, `school-dashboard.png` and `dark-mobile.png`.

Browser QA intentionally creates `QA Finance Tuition ...` configuration and manually recorded payments against synthetic academic students. Repeated runs leave those explicitly synthetic financial histories intact; already generated invoices remain unchanged. They are not genuine school collections. No existing academic record was reset/deleted. The owned temporary QA server is stopped after verification.

## Security limitations and remaining work

All finance routes require **APP_ENV=local**, **LOCAL_PREVIEW_ENABLED=true**, **LOCAL_FINANCE_ENABLED=true**, and an active catalog demo school with `is_demo=true`. The environment check is independent of the user-controlled feature flag and executes before session/CSRF processing for matched finance routes. CSRF remains enabled for browser writes. Non-local requests fail closed even with flags enabled or cached routes. Client school IDs are prohibited; school authority comes from validated route resolution. Expense files stay on the private local disk.

This is local development access, not production authentication/role authorization. No authentication, verified actor, PostgreSQL RLS, real gateway, external bank/cheque verification, external refund processing, general ledger or audited financial statements are provided. Bind the local server to loopback. No public financial management capability has been enabled.

Assigned fee versions freeze configuration; create deliberate effective versions instead of changing historical assignments. Billing is run explicitly from the UI/service; no unattended scheduler is enabled. Selectors currently load the chosen school's option catalogs, and some grouped report calculations traverse matching allocations; production scale needs paginated selectors, aggregate query tuning and background exports. Statements group document/event views rather than implementing a chronological general ledger. Posted expenses are immutable and have no expense-correction lifecycle. Browser QA covers the principal posting flow and screen/layout behavior; less common concession/refund/credit/penalty branches are verified by automated domain/HTTP/PostgreSQL tests rather than every browser interaction.

## Recommended Phase 5

Implement authentication, active school memberships, roles/policies and verified actor attribution before enabling production financial access. Add backup/restore and financial reconciliation checks, operational monitoring, security/privacy review, then production-scale selectors/export jobs and deliberate scheduled billing with operator controls. Keep SaaS subscriptions separate from school finance.
