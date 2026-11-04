<?php

namespace Database\Seeders;

use App\Modules\Platform\Models\Feature;
use App\Modules\Platform\Models\SubscriptionPlan;
use App\Modules\Platform\Preview\DemoCatalog;
use App\Modules\Schools\Models\School;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LocalDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local') || ! config('preview.enabled')) {
            throw new RuntimeException('Demo seeding requires local environment and explicit preview configuration.');
        }
        DB::transaction(function () {
            $features = collect(['academic-preview' => 'Academic workspace', 'school-configuration' => 'School configuration', 'reporting-preview' => 'Reporting preview'])->map(fn ($name, $code) => Feature::firstOrCreate(['code' => $code], ['name' => $name, 'description' => 'Phase 1 definition; workflows are not active.']));
            foreach (DemoCatalog::PLANS as $code => $values) {
                $plan = SubscriptionPlan::where('code', $code)->first();
                if ($plan && ! $plan->is_demo) {
                    throw new RuntimeException('Demo plan code belongs to non-demo data.');
                }
                $plan = SubscriptionPlan::updateOrCreate(['code' => $code], [...$values, 'currency' => 'PKR', 'is_demo' => true]);
                $plan->features()->syncWithoutDetaching($features->pluck('id'));
            }
            foreach (DemoCatalog::SCHOOLS as $slug => $values) {
                $school = School::where('slug', $slug)->first();
                if ($school && ! $school->is_demo) {
                    throw new RuntimeException('Demo school slug belongs to non-demo data.');
                }
                $school = School::updateOrCreate(['slug' => $slug], ['name' => $values['name'], 'city' => $values['city'], 'status' => 'active', 'is_demo' => true]);
                $hostname = $slug.'.schools.test';
                if (DB::table('school_domains')->where('hostname', $hostname)->where('school_id', '!=', $school->id)->exists()) {
                    throw new RuntimeException('Demo hostname already assigned.');
                }
                $school->domains()->updateOrCreate(['hostname' => $hostname], ['verified_at' => '2026-08-01 00:00:00']);
                $school->settings()->updateOrCreate(['key' => 'general'], ['value' => ['timezone' => 'Asia/Karachi', 'locale' => 'en', 'academic_year' => '2026–2027', 'week_starts_on' => 'Monday']]);
                $school->settings()->updateOrCreate(['key' => 'preview_metrics'], ['value' => array_intersect_key($values, array_flip(['students', 'teachers', 'classrooms', 'color', 'trend']))]);
                $school->subscription()->updateOrCreate([], ['subscription_plan_id' => SubscriptionPlan::where('code', $values['plan'])->value('id'), 'status' => 'demo', 'starts_at' => '2026-08-01', 'ends_at' => '2027-07-31']);
            }
        });
    }
}
