<?php

namespace App\Modules\Academics\Models;

class AcademicSession extends AcademicRecord
{
    protected $table = 'academic_sessions';

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }
}
