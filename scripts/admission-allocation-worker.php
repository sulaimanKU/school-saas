<?php

use App\Modules\Academics\AcademicService;
use App\Modules\Schools\Models\School;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
// This worker is test-only and can never write into public/application schemas.
if (! app()->environment('testing') || ! preg_match('/\Aphase1_test_[a-f0-9]{16}\z/', config('database.connections.pgsql.search_path'))) {
    throw new RuntimeException('An isolated testing schema is required.');
}
$school = School::where('slug', 'demo-concurrency')->firstOrFail();
app(TenantContext::class)->set($school);
for ($i = 0; $i < 4; $i++) {
    app(AcademicService::class)->save('students', ['full_name' => 'Concurrent allocation test', 'date_of_birth' => '2014-01-01', 'gender' => 'unspecified', 'guardian_name' => 'Test Guardian', 'guardian_phone' => '03000000000', 'admitted_on' => '2026-08-01', 'status' => 'active']);
}
