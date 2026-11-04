<?php

namespace App\Modules\Platform\Preview;

use App\Domain\Finance\FinanceReports;
use App\Modules\Academics\AcademicDashboardData;
use App\Modules\Platform\Models\SubscriptionPlan;
use App\Modules\Schools\Models\School;
use App\Modules\Tenancy\TenantContext;

final class PreviewData
{
    public function get(?string $slug, ?int $sessionId = null): array
    {
        $schools = School::where('is_demo', true)->whereIn('slug', array_keys(DemoCatalog::SCHOOLS))->with(['domains', 'settings', 'subscription.plan'])->orderBy('name')->get()->map(function (School $school) {
            $sample = $school->settings->firstWhere('key', 'preview_metrics')?->value ?? ['students' => 0, 'teachers' => 0, 'classrooms' => 0, 'color' => '#635bff', 'trend' => [0, 0, 0, 0, 0, 0]];
            $plan = $school->subscription?->plan;
            $safePlan = $plan && $plan->is_demo && array_key_exists($plan->code, DemoCatalog::PLANS) ? $plan : null;

            return ['slug' => $school->slug, 'name' => $school->name, 'city' => $school->city, 'status' => 'Demo',
                'students' => $sample['students'], 'teachers' => $sample['teachers'], 'classrooms' => $sample['classrooms'], 'color' => $sample['color'], 'trend' => $sample['trend'],
                'plan' => $safePlan?->name ?? 'Unassigned', 'monthly_price_minor' => $safePlan?->monthly_price_minor ?? 0,
                'domain' => $school->domains->firstWhere('hostname', $school->slug.'.schools.test')?->hostname,
                'settings' => $school->settings->firstWhere('key', 'general')?->value ?? []];
        });
        $selected = $slug === null ? $schools->first() : $schools->firstWhere('slug', $slug);
        if ($slug !== null) {
            abort_if($selected === null, 404);
        }

        $live = app()->environment('local') && config('preview.academic_crud') === true;
        $schoolId = $selected ? School::where('slug', $selected['slug'])->where('is_demo', true)->value('id') : null;
        $finance = app()->environment('local') && config('preview.enabled') === true && config('preview.finance') === true;
        $liveFinance = null;
        if ($finance && $schoolId) {
            $financeSchool = School::whereKey($schoolId)->where('status', 'active')->first();
            if ($financeSchool) {
                app(TenantContext::class)->set($financeSchool);
                $liveFinance = app(FinanceReports::class)->dashboard();
            }
        }

        return ['schools' => $schools->values(), 'selectedSchool' => $selected,
            'academicEnabled' => $live,
            'financeEnabled' => $finance,
            'liveFinance' => $liveFinance,
            'liveAcademic' => $live && $schoolId ? app(AcademicDashboardData::class)->build($schoolId, $sessionId) : null,
            'dashboard' => $selected ? (new SchoolDashboardData)->build($selected) : null,
            'plans' => SubscriptionPlan::where('is_demo', true)->whereIn('code', array_keys(DemoCatalog::PLANS))->with('features')->get()->map(fn ($plan) => [
                'code' => $plan->code, 'name' => $plan->name, 'monthly_price_minor' => $plan->monthly_price_minor, 'currency' => $plan->currency, 'capacity' => $plan->school_capacity,
                'features' => $plan->features->whereIn('code', ['academic-preview', 'school-configuration', 'reporting-preview'])->pluck('name')->values(), 'schools' => $schools->where('plan', $plan->name)->count(),
            ]), 'sampleMonths' => ['Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov'], 'sampleDate' => 'November 2026'];
    }
}
