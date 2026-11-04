<?php

namespace Tests\Feature;

use App\Modules\Schools\Models\School;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdmissionConcurrencyTest extends TestCase
{
    public function test_parallel_admissions_allocate_distinct_numbers_without_lost_updates(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Concurrent row locking is verified on PostgreSQL.');
        }
        $original = config('database.connections.pgsql.search_path');
        $schema = 'phase1_test_'.bin2hex(random_bytes(8));
        DB::statement('CREATE SCHEMA "'.$schema.'"');
        $workers = [];
        try {
            config(['database.connections.pgsql.search_path' => $schema]);
            DB::purge('pgsql');
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();
            School::factory()->create(['slug' => 'demo-concurrency', 'is_demo' => true]);
            $environment = array_merge(getenv(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_SCHEMA' => $schema]);
            for ($i = 0; $i < 3; $i++) {
                $pipes = [];
                $process = proc_open([PHP_BINARY, 'scripts/admission-allocation-worker.php'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $environment);
                $this->assertIsResource($process);
                fclose($pipes[0]);
                $workers[] = [$process, $pipes];
            }
            foreach ($workers as [$process,$pipes]) {
                $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($process), $output);
            }
            $workers = [];
            $this->assertSame(12, DB::table('students')->count());
            $this->assertSame(12, DB::table('students')->distinct()->count('admission_number'));
            $this->assertSame(13, (int) DB::table('admission_counters')->value('next_number'));
        } finally {
            foreach ($workers as [$process,$pipes]) {
                if (is_resource($process)) {
                    proc_terminate($process);
                }
            }
            if (! preg_match('/\Aphase1_test_[a-f0-9]{16}\z/', $schema)) {
                throw new \RuntimeException('Unsafe schema');
            }
            config(['database.connections.pgsql.search_path' => $original]);
            DB::purge('pgsql');
            DB::statement('DROP SCHEMA "'.$schema.'" CASCADE');
        }
    }
}
