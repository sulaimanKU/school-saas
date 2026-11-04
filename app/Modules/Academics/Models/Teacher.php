<?php

namespace App\Modules\Academics\Models;

use App\Models\User;

class Teacher extends AcademicRecord
{
    protected $table = 'teachers';

    public function assignments()
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
