<?php

namespace App\Modules\Academics\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends AcademicRecord
{
    protected $table = 'students';

    use SoftDeletes;

    public function enrollments()
    {
        return $this->hasMany(Enrollment::class)->orderByDesc('started_on')->orderByDesc('id');
    }
}
