<?php

namespace App\Modules\Platform\Preview;

/** Deterministic aggregate fixtures only. No students, invoices, or transactions are created. */
final class SchoolDashboardData
{
    public function build(array $school): array
    {
        $total = (int) $school['students'];
        $classCount = (int) $school['classrooms'];
        $classes = [];
        $gradeSections = [];
        for ($i = 0; $i < $classCount; $i++) {
            $grade = min(8, (int) floor($i * 8 / max($classCount, 1)) + 1);
            $section = $gradeSections[$grade] = ($gradeSections[$grade] ?? 0) + 1;
            $students = intdiv($total, $classCount) + ($i < $total % $classCount ? 1 : 0);
            $boys = (int) round($students * (0.49 + ($i % 4) * 0.02));
            $classes[] = ['name' => $grade.'-'.chr(64 + $section), 'grade' => 'Grade '.$grade, 'boys' => $boys, 'girls' => $students - $boys, 'students' => $students];
        }
        $distribution = collect($classes)->groupBy('grade')->map(fn ($rows, $grade) => ['name' => $grade, 'boys' => $rows->sum('boys'), 'girls' => $rows->sum('girls'), 'students' => $rows->sum('students')])->values()->all();
        $feeRate = 850000; // Illustrative PKR 8,500 per student, stored in minor units.
        $finance = [];
        foreach ($school['trend'] as $index => $enrollment) {
            $expected = $enrollment * $feeRate;
            $collected = (int) floor($expected * (0.77 + $index * 0.022));
            $finance[] = ['expected_minor' => $expected, 'collected_minor' => $collected, 'pending_minor' => $expected - $collected];
        }
        $current = $finance[array_key_last($finance)] ?? ['expected_minor' => 0, 'collected_minor' => 0, 'pending_minor' => 0];
        $pendingCases = [];
        // Aggregate buckets, deliberately without invented student identities.
        $remaining = $current['pending_minor'];
        foreach ([0.4, 0.3, 0.2, 0.1] as $i => $weight) {
            $amount = $i === 3 ? $remaining : (int) floor($current['pending_minor'] * $weight);
            $remaining -= $amount;
            $pendingCases[] = ['label' => 'Sample overdue group '.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT), 'period' => ['Over 60 days', '31–60 days', '15–30 days', 'Under 15 days'][$i], 'amount_minor' => $amount];
        }
        $new = max(0, ($school['trend'][5] ?? 0) - ($school['trend'][4] ?? 0));
        $first = (int) floor($new * 0.6);

        return [
            'sample' => true,
            'gender' => ['boys' => array_sum(array_column($classes, 'boys')), 'girls' => array_sum(array_column($classes, 'girls'))],
            'classes' => $classes, 'distribution' => $distribution,
            'parents' => (int) ceil($total * 0.82),
            'staff' => ['teachers' => $school['teachers'], 'support' => (int) ceil($school['teachers'] * 0.22)],
            'finance' => ['series' => $finance, ...$current, 'overdue_groups' => $pendingCases],
            'enrollment' => ['new' => $new, 'previous_total' => $school['trend'][4] ?? 0, 'cohorts' => [['label' => 'Primary intake', 'count' => $first], ['label' => 'Middle school intake', 'count' => $new - $first]]],
            'events' => [
                ['day' => '06', 'month' => 'NOV', 'title' => 'Science & discovery fair', 'time' => '9:00 AM – 12:00 PM', 'location' => 'School auditorium', 'type' => 'Academic'],
                ['day' => '12', 'month' => 'NOV', 'title' => 'Parent–teacher meetings', 'time' => '10:00 AM – 2:00 PM', 'location' => 'Classroom block', 'type' => 'Community'],
                ['day' => '18', 'month' => 'NOV', 'title' => 'Inter-house sports day', 'time' => '8:30 AM – 1:00 PM', 'location' => 'Sports ground', 'type' => 'Sports'],
            ],
            'activities' => [
                ['title' => $new.' sample enrollments this month', 'detail' => 'Illustrative intake summary', 'time' => 'November 2026', 'type' => 'enrollment'],
                ['title' => count($classes).' class groups prepared', 'detail' => 'Sample academic distribution', 'time' => 'November 2026', 'type' => 'academic'],
                ['title' => 'Fee collection snapshot available', 'detail' => 'Aggregate sample amounts only', 'time' => 'November 2026', 'type' => 'finance'],
            ],
        ];
    }
}
