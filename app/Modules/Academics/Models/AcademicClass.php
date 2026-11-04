<?php

namespace App\Modules\Academics\Models;

class AcademicClass extends AcademicRecord
{
    protected $table = 'academic_classes';

    public function sections()
    {
        return $this->hasMany(Section::class);
    }

    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'class_subject')->withPivot(['school_id', 'classification']);
    }

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }
}
