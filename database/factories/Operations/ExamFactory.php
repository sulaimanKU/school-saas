<?php

namespace Database\Factories\Operations;

use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Operations\Models\Exam;
use App\Modules\Academics\Operations\Models\ExamType;
use App\Modules\Academics\Operations\Models\GradingScheme;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExamFactory extends Factory
{
    protected $model = Exam::class;

    public function definition(): array
    {
        return ['name' => fake()->unique()->bothify('Exam ####'), 'academic_session_id' => AcademicSession::factory(), 'exam_type_id' => ExamType::factory(), 'grading_scheme_id' => GradingScheme::factory(), 'status' => 'draft'];
    }
}
