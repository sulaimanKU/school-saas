<?php

namespace Tests\Feature;

use App\Modules\Platform\Preview\DemoCatalog;
use App\Modules\Platform\Preview\PreviewData;
use App\Modules\Schools\Models\School;
use Database\Seeders\LocalDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SchoolDashboardPreviewTest extends TestCase
{
    use RefreshDatabase;

    private const MODULES = ['classes', 'calendar', 'subjects', 'timetables', 'parents', 'clubs', 'communications', 'accounts', 'expenses', 'accounting', 'transport', 'staff', 'marketing', 'campuses', 'school-settings'];

    private function seedPreview(): void
    {
        $this->app->instance('env', 'local');
        config(['preview.enabled' => true]);
        $this->withoutVite();
        $this->seed(LocalDemoSeeder::class);
    }

    public function test_dashboard_samples_reconcile_for_every_seeded_school(): void
    {
        $this->seedPreview();
        foreach (array_keys(DemoCatalog::SCHOOLS) as $slug) {
            $result = app(PreviewData::class)->get($slug);
            $school = $result['selectedSchool'];
            $dashboard = $result['dashboard'];
            $this->assertTrue($dashboard['sample']);
            $this->assertSame($school['students'], $dashboard['gender']['boys'] + $dashboard['gender']['girls']);
            $this->assertCount($school['classrooms'], $dashboard['classes']);
            $this->assertSame($school['students'], array_sum(array_column($dashboard['classes'], 'students')));
            $this->assertSame($school['students'], array_sum(array_column($dashboard['distribution'], 'students')));
            $this->assertSame($dashboard['gender']['boys'], array_sum(array_column($dashboard['distribution'], 'boys')));
            $this->assertSame($school['teachers'], $dashboard['staff']['teachers']);
            $this->assertSame($dashboard['enrollment']['new'], array_sum(array_column($dashboard['enrollment']['cohorts'], 'count')));
            $this->assertSame($school['students'], $dashboard['enrollment']['previous_total'] + $dashboard['enrollment']['new']);
            foreach ($dashboard['finance']['series'] as $month) {
                $this->assertSame($month['expected_minor'], $month['collected_minor'] + $month['pending_minor']);
            }
            $this->assertSame($dashboard['finance']['pending_minor'], array_sum(array_column($dashboard['finance']['overdue_groups'], 'amount_minor')));
            $this->assertSame($dashboard, app(PreviewData::class)->get($slug)['dashboard']);
        }
    }

    public function test_school_switching_returns_the_selected_schools_sample_data(): void
    {
        $this->seedPreview();
        foreach (DemoCatalog::SCHOOLS as $slug => $fixture) {
            $this->get('/preview/schools/'.$slug.'/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
                ->component('Preview', false)
                ->where('selectedSchool.slug', $slug)
                ->where('selectedSchool.students', $fixture['students'])
                ->has('dashboard.classes', $fixture['classrooms'])
                ->where('dashboard.sample', true));
        }
        $this->assertDatabaseCount('schools', 4);
        $this->assertDatabaseCount('subscriptions', 4);
        $this->assertDatabaseCount('school_memberships', 0);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_every_new_navigation_module_is_a_guarded_read_only_preview(): void
    {
        $this->seedPreview();
        foreach (self::MODULES as $module) {
            $url = '/preview/schools/demo-maplewood/'.$module;
            $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component('Preview', false)->where('page', $module)->where('selectedSchool.slug', 'demo-maplewood'));
            $this->post($url, ['name' => 'Changed'])->assertStatus(405);
            $this->app->instance('env', 'production');
            $this->get($url)->assertNotFound();
            $this->app->instance('env', 'local');
            config(['preview.enabled' => false]);
            $this->get($url)->assertNotFound();
            config(['preview.enabled' => true]);
        }
    }

    public function test_dashboard_changes_only_with_its_own_school_metrics(): void
    {
        $this->seedPreview();
        $other = app(PreviewData::class)->get('demo-crescent')['dashboard'];
        $school = School::where('slug', 'demo-maplewood')->firstOrFail();
        $setting = $school->settings()->where('key', 'preview_metrics')->firstOrFail();
        $metrics = $setting->value;
        $metrics['students'] = 900;
        $metrics['trend'][5] = 900;
        $setting->update(['value' => $metrics]);
        $updated = app(PreviewData::class)->get('demo-maplewood')['dashboard'];
        $this->assertSame(900, $updated['gender']['boys'] + $updated['gender']['girls']);
        $this->assertSame($other, app(PreviewData::class)->get('demo-crescent')['dashboard']);
    }
}
