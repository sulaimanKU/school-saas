<?php

use App\Domain\Finance\FinanceService;
use App\Domain\Finance\Models\StudentInvoice;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Schools\Models\School;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || ! preg_match('/\Aphase1_test_[a-f0-9]{16}\z/', config('database.connections.pgsql.search_path'))) {
    throw new RuntimeException('Isolated PostgreSQL testing schema required.');
}
app(TenantContext::class)->set(School::where('slug', 'demo-finance-concurrency')->firstOrFail());
$service = app(FinanceService::class);
$race = ($argv[1] ?? '') === 'race';
if (! $race) {
    $service->generate(['academic_session_id' => AcademicSession::firstOrFail()->id, 'period' => '2026-10-01', 'issued_on' => '2026-10-01', 'due_on' => '2026-10-10', 'issue' => true, 'auto_assign' => true]);
}
$i = StudentInvoice::orderBy('id')->firstOrFail();
try {
    $service->pay(['student_id' => $i->student_id, 'paid_on' => '2026-10-02', 'amount' => '400.00', 'method' => 'cash', 'idempotency_key' => $race ? 'race-worker-'.($argv[2] ?? '') : 'concurrent-payment-1', 'allocations' => [['invoice_id' => $i->id, 'amount' => '400.00']]]);
} catch (ValidationException $e) {
    if (! $race) {
        throw $e;
    } echo 'Competing overpayment rejected';
}
