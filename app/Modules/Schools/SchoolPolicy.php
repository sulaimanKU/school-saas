<?php

namespace App\Modules\Schools;

use App\Models\User;
use App\Modules\Schools\Models\School;

/** Fail closed until platform roles and membership authorization are implemented. */
class SchoolPolicy
{
    public function viewAny(?User $user): bool
    {
        return false;
    }

    public function view(?User $user, School $school): bool
    {
        return false;
    }

    public function create(?User $user): bool
    {
        return false;
    }

    public function update(?User $user, School $school): bool
    {
        return false;
    }

    public function delete(?User $user, School $school): bool
    {
        return false;
    }
}
