<?php

namespace App\Modules\Tenancy;

use App\Modules\Schools\Models\School;
use LogicException;

/** Resolution is not authorization. Populate only after trusted resolution and future policy checks. */
final class TenantContext
{
    private ?School $school = null;

    public function set(School $school): void
    {
        $this->school = $school;
    }

    public function school(): School
    {
        return $this->school ?? throw new LogicException('No school context has been established.');
    }
}
