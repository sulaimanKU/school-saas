<?php

namespace Database\Seeders;

use App\Modules\Academics\AcademicService;
use App\Modules\Academics\Operations\Models\ExamType;
use App\Modules\Academics\Operations\Models\GradingScheme;
use App\Modules\Academics\Operations\Models\TimetablePeriod;
use App\Modules\Academics\Operations\Models\WorkingDay;
use App\Modules\Academics\Operations\OperationsService;
use App\Modules\Platform\Preview\DemoCatalog;
use App\Modules\Schools\Models\School;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use RuntimeException;

final class OperationsDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || ! config('preview.enabled') || ! config('preview.academic_crud')) {
            throw new RuntimeException('Local academic preview flags are required.');
        }
        foreach (School::where('is_demo', true)->whereIn('slug', array_keys(DemoCatalog::SCHOOLS))->get() as $school) {
            app(TenantContext::class)->set($school);
            app(AcademicService::class)->transaction(function () {
                $service = app(OperationsService::class);
                foreach (range(1, 7) as $day) {
                    if (! WorkingDay::where('weekday', $day)->exists()) {
                        $service->save('working-days', ['weekday' => $day, 'is_working' => $day <= 5, 'opens_at' => '08:00', 'closes_at' => '15:00']);
                    }
                }
                foreach ([['P1', 'Period 1', '08:00', '08:45', 'teaching'], ['P2', 'Period 2', '08:45', '09:30', 'teaching'], ['BREAK', 'Morning break', '09:30', '09:45', 'break'], ['ASSEMBLY', 'School assembly', '14:30', '14:45', 'non_teaching']] as [$code,$name,$start,$end,$kind]) {
                    if (! TimetablePeriod::where('code', $code)->exists()) {
                        $service->save('periods', ['name' => $name, 'code' => $code, 'starts_at' => $start, 'ends_at' => $end, 'kind' => $kind]);
                    }
                }
                foreach (['MONTHLY' => 'Monthly test', 'MIDTERM' => 'Midterm', 'FINAL' => 'Final', 'CUSTOM' => 'Custom'] as $code => $name) {
                    if (! ExamType::where('code', $code)->exists()) {
                        $service->save('exam-types', ['name' => $name, 'code' => $code]);
                    }
                }
                if (! GradingScheme::where('name', 'Default academic grading')->exists()) {
                    $service->save('grading', ['name' => 'Default academic grading', 'ranking_enabled' => false, 'logo_path' => '/images/school-emblem.svg', 'bands' => [
                        ['label' => 'F', 'minimum_percent' => 0, 'maximum_percent' => 50, 'is_pass' => false], ['label' => 'D', 'minimum_percent' => 50, 'maximum_percent' => 60, 'is_pass' => true], ['label' => 'C', 'minimum_percent' => 60, 'maximum_percent' => 70, 'is_pass' => true], ['label' => 'B', 'minimum_percent' => 70, 'maximum_percent' => 80, 'is_pass' => true], ['label' => 'A', 'minimum_percent' => 80, 'maximum_percent' => 90, 'is_pass' => true], ['label' => 'A+', 'minimum_percent' => 90, 'maximum_percent' => 100, 'is_pass' => true],
                    ]]);
                }
            });
        }
    }
}
