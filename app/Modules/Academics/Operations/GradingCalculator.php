<?php

namespace App\Modules\Academics\Operations;

use Illuminate\Validation\ValidationException;

final class GradingCalculator
{
    public function validateBands(array $bands): array
    {
        usort($bands, fn ($a, $b) => $a['minimum_percent'] <=> $b['minimum_percent']);
        $next = 0;
        foreach ($bands as $band) {
            $min = (int) round((float) $band['minimum_percent'] * 100);
            $max = (int) round((float) $band['maximum_percent'] * 100);
            if ($min !== $next || $max <= $min || $max > 10000) {
                throw ValidationException::withMessages(['bands' => 'Grade ranges must cover 0–100 continuously, without gaps or overlaps. Boundaries are lower-inclusive and upper-exclusive, except 100.']);
            }
            $next = $max;
        }
        if ($next !== 10000) {
            throw ValidationException::withMessages(['bands' => 'Grade ranges must cover 0–100.']);
        }

        return $bands;
    }

    public function grade(float $percentage, array $bands): array
    {
        foreach ($bands as $b) {
            if ($percentage >= (float) $b['minimum_percent'] && ($percentage < (float) $b['maximum_percent'] || ((float) $b['maximum_percent'] === 100.0 && $percentage <= 100))) {
                return ['label' => $b['label'], 'is_pass' => (bool) $b['is_pass']];
            }
        }
        throw ValidationException::withMessages(['bands' => 'No grading band matches the percentage.']);
    }

    public function calculate(array $rows, array $bands): array
    {
        $bands = $this->validateBands($bands);
        $max = 0;
        $obtained = 0;
        $incomplete = false;
        $failed = false;
        $subjects = [];
        foreach ($rows as $row) {
            $status = $row['status'] ?? 'unmarked';
            $percentage = null;
            $grade = null;
            $pass = null;
            if ($status === 'unmarked') {
                $incomplete = true;
                $max += (float) $row['maximum_marks'];
            } elseif ($status !== 'exempt') {
                $score = $status === 'absent' ? 0 : (float) $row['obtained_marks'];
                $max += (float) $row['maximum_marks'];
                $obtained += $score;
                $percentage = round($score / (float) $row['maximum_marks'] * 100, 2);
                $band = $this->grade($percentage, $bands);
                $grade = $band['label'];
                $pass = $status !== 'absent' && $score >= (float) $row['passing_marks'] && $band['is_pass'];
                $failed = $failed || ! $pass;
            }
            $subjects[] = [...$row, 'percentage' => $percentage, 'grade' => $grade, 'passed' => $pass];
        }
        $percentage = ! $incomplete && $max > 0 ? round($obtained / $max * 100, 2) : null;
        $overall = $percentage !== null ? $this->grade($percentage, $bands) : null;

        return ['subjects' => $subjects, 'maximum_marks' => round($max, 2), 'obtained_marks' => round($obtained, 2), 'percentage' => $percentage, 'grade' => $overall['label'] ?? null, 'outcome' => $incomplete ? 'incomplete' : ($max === 0 ? 'exempt' : (! $failed && $overall['is_pass'] ? 'pass' : 'fail')), 'rank' => null];
    }

    public function rank(array $reports, bool $enabled): array
    {
        if (! $enabled) {
            return $reports;
        }
        usort($reports, fn ($a, $b) => ($b['percentage'] ?? -1) <=> ($a['percentage'] ?? -1) ?: strcmp($a['admission_number'], $b['admission_number']));
        $last = null;
        $rank = 0;
        $position = 0;
        foreach ($reports as &$report) {
            if ($report['percentage'] === null) {
                continue;
            } $position++;
            if ($last !== $report['percentage']) {
                $rank = $position;
            } $report['rank'] = $rank;
            $last = $report['percentage'];
        }

        return $reports;
    }
}
