<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 63)->unique();
            $table->string('name');
            $table->string('city');
            $table->enum('status', ['active', 'suspended', 'archived'])->default('active')->index();
            $table->boolean('is_demo')->default(false)->index();
            $table->timestamps();
        });
        Schema::create('school_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('hostname', 253)->unique();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('school_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->json('value');
            $table->timestamps();
            $table->unique(['school_id', 'key']);
        });
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedInteger('monthly_price_minor');
            $table->char('currency', 3)->default('PKR');
            $table->unsignedInteger('school_capacity');
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
        });
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('description');
            $table->timestamps();
        });
        Schema::create('feature_subscription_plan', function (Blueprint $table) {
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
            $table->primary(['feature_id', 'subscription_plan_id']);
        });
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['demo', 'trial', 'active', 'suspended', 'cancelled', 'expired'])->index();
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->timestamps();
        });
        Schema::create('school_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'administrator', 'teacher', 'staff']);
            $table->enum('status', ['pending', 'active', 'revoked'])->default('pending');
            $table->timestamps();
            $table->unique(['school_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['school_memberships', 'subscriptions', 'feature_subscription_plan', 'features', 'subscription_plans', 'school_settings', 'school_domains', 'schools'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
