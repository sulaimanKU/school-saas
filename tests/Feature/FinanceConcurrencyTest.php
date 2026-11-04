<?php

namespace Tests\Feature;

use App\Domain\Finance\FinanceService;
use App\Domain\Finance\Models\FeeCategory;
use App\Domain\Finance\Models\StudentInvoice;
use App\Domain\Finance\Models\StudentPayment;
use App\Modules\Academics\Models\AcademicClass;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\Enrollment;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Student;
use App\Modules\Schools\Models\School;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinanceConcurrencyTest extends TestCase
{
    public function test_simultaneous_billing_numbering_duplicate_payments_and_competing_overpayments(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Real PostgreSQL concurrent posting');
        }
        $original = config('database.connections.pgsql.search_path');
        $schema = 'phase1_test_'.bin2hex(random_bytes(8));
        $workers = [];
        DB::statement('CREATE SCHEMA "'.$schema.'"');
        try {
            config(['database.connections.pgsql.search_path' => $schema]);
            DB::purge('pgsql');
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();
            $school = School::factory()->create(['slug' => 'demo-finance-concurrency', 'is_demo' => true]);
            app(TenantContext::class)->set($school);
            $session = AcademicSession::factory()->create();
            $class = AcademicClass::factory()->create();
            $section = Section::factory()->create(['academic_class_id' => $class->id]);
            foreach (Student::factory()->count(2)->create(['admitted_on' => '2026-08-01']) as $student) {
                Enrollment::create(['student_id' => $student->id, 'academic_session_id' => $session->id, 'academic_class_id' => $class->id, 'section_id' => $section->id, 'started_on' => '2026-08-01', 'status' => 'active']);
            }
            $category = FeeCategory::create(['name' => 'Tuition', 'active' => true]);
            app(FinanceService::class)->catalog('structures', ['name' => 'Concurrent fees', 'academic_session_id' => $session->id, 'academic_class_id' => $class->id, 'fee_category_id' => $category->id, 'amount' => '1000.00', 'frequency' => 'monthly', 'effective_from' => '2026-08-01', 'effective_to' => '2027-07-31', 'active' => true]);
            $env = array_merge(getenv(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_SCHEMA' => $schema]);
            foreach (['duplicate', 'race'] as $mode) {
                for ($n = 0; $n < 3; $n++) {
                    $pipes = [];
                    $p = proc_open([PHP_BINARY, 'scripts/finance-posting-worker.php', $mode, (string) $n], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env);
                    $this->assertIsResource($p);
                    fclose($pipes[0]);
                    $workers[] = [$p, $pipes];
                }
                foreach ($workers as [$p, $pipes]) {
                    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
                    fclose($pipes[1]);
                    fclose($pipes[2]);
                    $this->assertSame(0, proc_close($p), $output);
                } $workers = [];
                $this->assertSame(2, StudentInvoice::count());
                $this->assertSame(2, StudentInvoice::distinct()->count('number'));
                $this->assertSame($mode === 'race' ? 2 : 1, StudentPayment::count());
            }
            $this->assertSame(20000, app(FinanceService::class)->balance(StudentInvoice::orderBy('id')->firstOrFail())['outstanding']);
            $this->assertSame(3, (int) DB::table('finance_counters')->where('kind', 'inv')->value('next_number'));
        } finally {
            foreach ($workers as [$p]) {
                if (is_resource($p)) {
                    proc_terminate($p);
                }
            }
            if (! preg_match('/\Aphase1_test_[a-f0-9]{16}\z/', $schema)) {
                throw new \RuntimeException('Unsafe test schema');
            }
            config(['database.connections.pgsql.search_path' => $original]);
            DB::purge('pgsql');
            DB::statement('DROP SCHEMA "'.$schema.'" CASCADE');
        }
    }
}
