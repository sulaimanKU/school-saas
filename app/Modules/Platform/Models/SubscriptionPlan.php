<?php

namespace App\Modules\Platform\Models;

use Database\Factories\SubscriptionPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected static function newFactory(): SubscriptionPlanFactory
    {
        return SubscriptionPlanFactory::new();
    }

    protected $fillable = ['code', 'name', 'monthly_price_minor', 'currency', 'school_capacity', 'is_demo'];

    protected function casts(): array
    {
        return ['is_demo' => 'boolean', 'monthly_price_minor' => 'integer', 'school_capacity' => 'integer'];
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
