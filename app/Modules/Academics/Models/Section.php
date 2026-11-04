<?php

namespace App\Modules\Academics\Models;

class Section extends AcademicRecord
{
    protected $table = 'sections';

    public function academicClass()
    {
        return $this->belongsTo(AcademicClass::class);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }
}
