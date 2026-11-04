<?php

namespace App\Modules\Shared;

use App\Modules\Schools\Models\School;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToSchool
{
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** Explicit scope: callers must establish context; IDs from requests are never authority. */
    public function scopeForTenant(Builder $query, TenantContext $context): Builder
    {
        return $query->where($this->qualifyColumn('school_id'), $context->school()->getKey());
    }
}
