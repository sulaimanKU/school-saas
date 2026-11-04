<?php

namespace App\Modules\Academics\Models;

class Enrollment extends AcademicRecord
{
    protected $table = 'enrollments';

    public function student()
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function academicClass()
    {
        return $this->belongsTo(AcademicClass::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function previousEnrollment()
    {
        return $this->belongsTo(self::class, 'previous_enrollment_id');
    }
}
