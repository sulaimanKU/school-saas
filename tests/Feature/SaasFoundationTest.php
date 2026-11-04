<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Feature;
use App\Modules\Schools\Models\School;
use App\Modules\Schools\Models\SchoolSetting;
use App\Modules\Tenancy\TenantContext;
use App\Modules\Tenancy\TenantResolver;
use Database\Factories\FeatureFactory;
use Database\Factories\SchoolDomainFactory;
use Database\Factories\SchoolMembershipFactory;
use Database\Factories\SchoolSettingFactory;
use Database\Factories\SubscriptionFactory;
use Database\Factories\SubscriptionPlanFactory;
use Database\Seeders\LocalDemoSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SaasFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function preview(): void
    {
        $this->app->instance('env', 'local');
        config(['preview.enabled' => true]);
        $this->withoutVite();
        $this->seed(LocalDemoSeeder::class);
    }

    public function test_demo_seeding_is_repeatable_and_preserves_unrelated_data(): void
    {
        $real = School::factory()->create();
        $this->preview();
        $this->seed(LocalDemoSeeder::class);
        $this->assertDatabaseCount('schools', 5);
        $this->assertDatabaseCount('subscriptions', 4);
        $this->assertDatabaseCount('school_settings', 8);
        $this->assertDatabaseCount('school_domains', 4);
        $this->assertDatabaseCount('subscription_plans', 3);
        $this->assertDatabaseCount('feature_subscription_plan', 9);
        $this->assertDatabaseHas('schools', ['id' => $real->id, 'name' => $real->name, 'is_demo' => false]);
    }

    public function test_relationships_and_memberships(): void
    {
        $this->preview();
        $school = School::where('slug', 'demo-maplewood')->firstOrFail();
        $user = User::factory()->create();
        $membership = $school->memberships()->create(['user_id' => $user->id, 'role' => 'administrator', 'status' => 'pending']);
        $this->assertTrue($membership->user->is($user));
        $this->assertTrue($user->schoolMemberships->first()->is($membership));
        $this->assertTrue($membership->school->is($school));
        $this->assertTrue($school->domains->first()->school->is($school));
        $this->assertTrue($school->settings->first()->school->is($school));
        $this->assertTrue($school->subscription->school->is($school));
        $this->assertSame('Growth', $school->subscription->plan->name);
        $this->assertCount(3, $school->subscription->plan->features);
        $this->assertCount(3, Feature::first()->plans);
        $this->assertFalse(Gate::forUser($user)->allows('view', $school));
        $this->assertFalse(Gate::forUser($user)->allows('update', $school));
        $this->assertFalse(Gate::allows('view', $school));
    }

    public function test_tenant_resolution_uses_validated_slug_and_verified_hostname(): void
    {
        $this->preview();
        $resolver = app(TenantResolver::class);
        $school = $resolver->bySlug('demo-maplewood');
        $this->assertTrue($resolver->byHostname('DEMO-MAPLEWOOD.schools.test.')->is($school));
        $school->domains()->create(['hostname' => 'unverified.schools.test']);
        $this->expectException(ModelNotFoundException::class);
        $resolver->byHostname('unverified.schools.test');
    }

    public static function invalidTenants(): array
    {
        return [['bySlug', '1'], ['bySlug', '../school'], ['bySlug', 'UPPER'], ['bySlug', 'a--b'], ['bySlug', str_repeat('a', 64)], ['byHostname', 'https://school.test'], ['byHostname', 'school.test:8000'], ['byHostname', 'school.test/path'], ['byHostname', 'bad_host.test']];
    }

    #[DataProvider('invalidTenants')]
    public function test_invalid_identifiers_are_rejected(string $method, string $input): void
    {
        if ($method === 'bySlug' && $input === '1') {
            $this->expectException(ModelNotFoundException::class);
        } else {
            $this->expectException(\InvalidArgumentException::class);
        }
        app(TenantResolver::class)->$method($input);
    }

    public function test_inactive_and_unknown_schools_do_not_resolve(): void
    {
        School::factory()->create(['slug' => 'inactive', 'status' => 'suspended']);
        foreach (['inactive', 'unknown'] as $slug) {
            try {
                app(TenantResolver::class)->bySlug($slug);
                $this->fail('Resolved unavailable tenant');
            } catch (ModelNotFoundException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_scoped_queries_only_return_the_current_school(): void
    {
        $this->preview();
        $school = School::where('slug', 'demo-maplewood')->firstOrFail();
        $context = new TenantContext;
        $context->set($school);
        $results = SchoolSetting::forTenant($context)->get();
        $this->assertCount(2, $results);
        $this->assertSame($school->id, $results->first()->school_id);
        $this->expectException(\LogicException::class);
        SchoolSetting::forTenant(new TenantContext)->get();
    }

    public static function environments(): array
    {
        return [['production', true], ['staging', true], ['testing', true], ['local', false], ['production', false]];
    }

    #[DataProvider('environments')]
    public function test_preview_is_inaccessible_without_both_guards(string $env, bool $enabled): void
    {
        $this->app->instance('env', $env);
        config(['preview.enabled' => $enabled]);
        foreach (['/', '/preview', '/preview/schools', '/preview/plans', '/preview/settings', '/preview/schools/demo-maplewood', '/preview/schools/demo-maplewood/dashboard', '/preview/schools/demo-maplewood/academics'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    public function test_all_preview_pages_render_and_school_selection_is_correct(): void
    {
        $this->preview();
        foreach (['/preview' => 'overview', '/preview/schools' => 'schools', '/preview/plans' => 'plans', '/preview/settings' => 'settings'] as $url => $page) {
            $this->get($url)->assertOk()->assertInertia(fn (Assert $a) => $a->component('Preview', false)->where('page', $page)->has('schools', 4));
        }
        foreach (['profile', 'dashboard', 'students', 'academics', 'attendance', 'examinations', 'fees'] as $page) {
            $this->get('/preview/schools/demo-maplewood/'.$page)->assertOk()->assertInertia(fn (Assert $a) => $a->component('Preview', false)->where('page', $page)->where('selectedSchool.slug', 'demo-maplewood'));
        }
        $this->get('/preview/schools/demo-crescent')->assertOk()->assertInertia(fn (Assert $a) => $a->component('Preview', false)->where('page', 'profile')->where('selectedSchool.slug', 'demo-crescent'));
        $this->get('/')->assertRedirect('/preview');
    }

    public function test_preview_excludes_real_and_non_catalog_schools_and_rejects_writes(): void
    {
        $this->preview();
        $real = School::factory()->create(['slug' => 'demo-private', 'name' => 'Private school']);
        School::factory()->create(['is_demo' => true]);
        $this->get('/preview')->assertInertia(fn (Assert $a) => $a->component('Preview', false)->has('schools', 4));
        $this->get('/preview/schools/demo-private')->assertNotFound();
        $this->get('/preview/schools/'.$real->id)->assertNotFound();
        $this->get('/preview/schools/demo-maplewood')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $before = DB::table('schools')->orderBy('id')->get()->toJson();
        foreach (['post', 'put', 'patch', 'delete'] as $method) {
            $this->$method('/preview/schools/demo-maplewood', ['name' => 'Changed'])->assertStatus(405);
        }
        $this->assertSame($before, DB::table('schools')->orderBy('id')->get()->toJson());
        foreach (['/schools', '/platform', '/login', '/register', '/forgot-password'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    public function test_seeder_refuses_non_local_even_with_configuration_enabled(): void
    {
        config(['preview.enabled' => true]);
        $this->app->instance('env', 'production');
        $this->expectException(\RuntimeException::class);
        (new LocalDemoSeeder)->run();
    }

    public function test_seeder_refuses_disabled_local_configuration(): void
    {
        config(['preview.enabled' => false]);
        $this->app->instance('env', 'local');
        $this->expectException(\RuntimeException::class);
        $this->seed(LocalDemoSeeder::class);
    }

    public function test_seeder_rolls_back_on_real_slug_collision(): void
    {
        School::factory()->create(['slug' => 'demo-maplewood']);
        $this->app->instance('env', 'local');
        config(['preview.enabled' => true]);
        try {
            $this->seed(LocalDemoSeeder::class);
            $this->fail('Overwrote real data');
        } catch (\RuntimeException) {
            $this->assertDatabaseCount('subscription_plans', 0);
            $this->assertDatabaseCount('schools', 1);
        }
    }

    public function test_school_setting_key_is_unique_per_school(): void
    {
        $this->preview();
        $school = School::first();
        $this->expectException(QueryException::class);
        $school->settings()->create(['key' => 'general', 'value' => []]);
    }

    public function test_related_factories_build_valid_relationships(): void
    {
        $domain = SchoolDomainFactory::new()->create();
        $setting = SchoolSettingFactory::new()->create();
        $membership = SchoolMembershipFactory::new()->create();
        $subscription = SubscriptionFactory::new()->create();
        $feature = FeatureFactory::new()->create();
        $subscription->plan->features()->attach($feature);
        $this->assertNotNull($domain->school);
        $this->assertNotNull($setting->school);
        $this->assertNotNull($membership->user);
        $this->assertNotNull($subscription->school);
        $this->assertTrue($subscription->plan->features->first()->is($feature));
    }

    public function test_preview_guards_are_checked_on_each_request(): void
    {
        $this->preview();
        $this->get('/preview')->assertOk();
        $this->app->instance('env', 'production');
        $this->get('/preview')->assertNotFound();
        $this->app->instance('env', 'local');
        config(['preview.enabled' => false]);
        $this->get('/preview')->assertNotFound();
    }

    public static function invalidConstraints(): array
    {
        return [['school-status'], ['school-slug'], ['negative-price'], ['subscription-date'], ['duplicate-membership'], ['foreign-key']];
    }

    #[DataProvider('invalidConstraints')]
    public function test_postgresql_constraints_reject_invalid_records(string $case): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL-specific integrity checks; run scripts/test-postgres.php.');
        }
        $this->expectException(QueryException::class);
        match ($case) {
            'school-status' => School::factory()->create(['status' => 'unrecognized']),
            'school-slug' => School::factory()->create(['slug' => 'UPPER CASE']),
            'negative-price' => SubscriptionPlanFactory::new()->create(['monthly_price_minor' => -1]),
            'subscription-date' => SubscriptionFactory::new()->create(['starts_at' => '2026-09-01', 'ends_at' => '2026-08-01']),
            'duplicate-membership' => (function () {
                $member = SchoolMembershipFactory::new()->create();
                DB::table('school_memberships')->insert(['school_id' => $member->school_id, 'user_id' => $member->user_id, 'role' => 'staff', 'status' => 'pending']);
            })(),
            'foreign-key' => DB::table('school_settings')->insert(['school_id' => 999999, 'key' => 'invalid', 'value' => '{}']),
        };
    }
}
