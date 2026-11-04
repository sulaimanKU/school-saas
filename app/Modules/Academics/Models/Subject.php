<?php

namespace App\Modules\Academics\Models;

class Subject extends AcademicRecord
{
    protected $table = 'subjects';

    public function classes()
    {
        return $this->belongsToMany(AcademicClass::class, 'class_subject')->withPivot(['school_id', 'classification']);
    }
}
