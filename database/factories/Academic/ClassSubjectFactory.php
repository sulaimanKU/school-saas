<?php

namespace Database\Factories\Academic;

use App\Modules\Academics\Models\AcademicClass;
use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassSubjectFactory extends Factory
{
    protected $model = ClassSubject::class;

    public function definition(): array
    {
        return ['academic_class_id' => AcademicClass::factory(), 'subject_id' => Subject::factory(), 'classification' => 'compulsory'];
    }
}
