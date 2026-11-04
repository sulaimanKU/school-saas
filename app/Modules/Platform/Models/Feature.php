<?php

namespace App\Modules\Platform\Models;

use Database\Factories\FeatureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
{
    use HasFactory;

    protected static function newFactory(): FeatureFactory
    {
        return FeatureFactory::new();
    }

    protected $fillable = ['code', 'name', 'description'];

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(SubscriptionPlan::class);
    }
}
