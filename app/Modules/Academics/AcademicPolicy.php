<?php

namespace App\Modules\Academics;

use App\Modules\Academics\Models\AcademicRecord;
use App\Modules\Tenancy\TenantContext;

/** An explicitly local capability; replace with authenticated membership policies before production. */
final class AcademicPolicy
{
    public function allows(?AcademicRecord $record = null): bool
    {
        if (! app()->environment('local') || config('preview.enabled') !== true || config('preview.academic_crud') !== true) {
            return false;
        }
        $school = app(TenantContext::class)->school();

        return $school->is_demo && ($record === null || $record->school_id === $school->id);
    }
}
