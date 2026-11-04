<?php

namespace App\Modules\Academics\Models;

class ClassSubject extends AcademicRecord
{
    protected $table = 'class_subject';

    public function academicClass()
    {
        return $this->belongsTo(AcademicClass::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
