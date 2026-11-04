<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_domains', fn (Blueprint $table) => $table->index('school_id'));
        Schema::table('subscriptions', fn (Blueprint $table) => $table->index('subscription_plan_id'));
        Schema::table('feature_subscription_plan', fn (Blueprint $table) => $table->index('subscription_plan_id'));
    }

    public function down(): void
    {
        Schema::table('school_domains', fn (Blueprint $table) => $table->dropIndex(['school_id']));
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropIndex(['subscription_plan_id']));
        Schema::table('feature_subscription_plan', fn (Blueprint $table) => $table->dropIndex(['subscription_plan_id']));
    }
};
