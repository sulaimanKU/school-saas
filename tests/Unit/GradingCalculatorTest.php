<?php

namespace Tests\Unit;

use App\Modules\Academics\Operations\AttendanceService;
use App\Modules\Academics\Operations\GradingCalculator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GradingCalculatorTest extends TestCase
{
    private function bands(): array
    {
        return [['label' => 'F', 'minimum_percent' => 0, 'maximum_percent' => 50, 'is_pass' => false], ['label' => 'B', 'minimum_percent' => 50, 'maximum_percent' => 90, 'is_pass' => true], ['label' => 'A+', 'minimum_percent' => 90, 'maximum_percent' => 100, 'is_pass' => true]];
    }

    public function test_boundaries_absence_exemption_missing_and_overall_grades(): void
    {
        $c = new GradingCalculator;
        $this->assertSame('B', $c->grade(50, $this->bands())['label']);
        $this->assertSame('A+', $c->grade(100, $this->bands())['label']);
        $row = ['subject' => 'Math', 'subject_id' => 1, 'maximum_marks' => 100, 'passing_marks' => 50, 'obtained_marks' => 90, 'status' => 'scored'];
        $report = $c->calculate([$row, [...$row, 'subject' => 'English', 'status' => 'exempt', 'obtained_marks' => null]], $this->bands());
        $this->assertSame(90.0, $report['percentage']);
        $this->assertSame('pass', $report['outcome']);
        $this->assertSame(100.0, $report['maximum_marks']);
        $this->assertSame('fail', $c->calculate([[...$row, 'status' => 'absent', 'obtained_marks' => null]], $this->bands())['outcome']);
        $missing = $c->calculate([[...$row, 'status' => 'unmarked', 'obtained_marks' => null]], $this->bands());
        $this->assertSame('incomplete', $missing['outcome']);
        $this->assertNull($missing['percentage']);
        $ties = $c->rank([['percentage' => 90.0, 'admission_number' => 'A2'], ['percentage' => 80.0, 'admission_number' => 'A3'], ['percentage' => 90.0, 'admission_number' => 'A1']], true);
        $this->assertSame([1, 1, 3], array_column($ties, 'rank'));
        $this->assertSame('A1', $ties[0]['admission_number']);
        $this->assertNull(AttendanceService::summary(['excused' => 5])['percentage']);
        $this->assertSame(50.0, AttendanceService::summary(['late' => 1, 'absent' => 1, 'excused' => 20])['percentage']);
    }

    public function test_grading_gaps_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        (new GradingCalculator)->validateBands([['label' => 'F', 'minimum_percent' => 0, 'maximum_percent' => 40, 'is_pass' => false], ['label' => 'A', 'minimum_percent' => 50, 'maximum_percent' => 100, 'is_pass' => true]]);
    }

    public function test_grading_overlaps_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        (new GradingCalculator)->validateBands([['label' => 'F', 'minimum_percent' => 0, 'maximum_percent' => 60, 'is_pass' => false], ['label' => 'A', 'minimum_percent' => 50, 'maximum_percent' => 100, 'is_pass' => true]]);
    }
}
