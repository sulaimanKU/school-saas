<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CHECKS = [
        'schools' => ['phase1_school_status' => "status IN ('active','suspended','archived')", 'phase1_slug_format' => "slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$'"],
        'school_domains' => ['phase1_hostname_lowercase' => 'hostname = lower(hostname)'],
        'subscription_plans' => ['phase1_plan_values' => 'monthly_price_minor >= 0 AND school_capacity > 0', 'phase1_currency_format' => "currency ~ '^[A-Z]{3}$'"],
        'subscriptions' => ['phase1_subscription_status' => "status IN ('demo','trial','active','suspended','cancelled','expired')", 'phase1_subscription_dates' => 'ends_at IS NULL OR ends_at >= starts_at'],
        'school_memberships' => ['phase1_membership_role' => "role IN ('owner','administrator','teacher','staff')", 'phase1_membership_status' => "status IN ('pending','active','revoked')"],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        foreach (self::CHECKS as $table => $checks) {
            foreach ($checks as $name => $condition) {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$condition})");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        foreach (self::CHECKS as $table => $checks) {
            foreach ($checks as $name => $condition) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$name}");
            }
        }
    }
};
