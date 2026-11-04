<?php

namespace Database\Factories\Operations;

use App\Modules\Academics\Models\Student;
use App\Modules\Academics\Operations\Models\Exam;
use App\Modules\Academics\Operations\Models\PublishedReportCard;
use Illuminate\Database\Eloquent\Factories\Factory;

class PublishedReportCardFactory extends Factory
{
    protected $model = PublishedReportCard::class;

    public function definition(): array
    {
        return ['exam_id' => Exam::factory(), 'student_id' => Student::factory(), 'version' => 1, 'snapshot' => ['publication' => 'published', 'test_fixture' => true], 'published_at' => now()];
    }
}
