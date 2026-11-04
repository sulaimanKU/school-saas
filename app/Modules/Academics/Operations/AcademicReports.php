<?php

namespace App\Modules\Academics\Operations;

use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Operations\Models\PublishedReportCard;
use App\Modules\Academics\Operations\Models\StaffAttendanceRecord;
use App\Modules\Academics\Operations\Models\StudentAttendanceRecord;
use Carbon\CarbonImmutable;
use Generator;

final class AcademicReports
{
    public function rows(array $filters): Generator
    {
        $type = $filters['report_type'] ?? 'attendance';
        if ($type === 'daily') {
            if (empty($filters['academic_session_id']) || empty($filters['section_id'])) {
                return;
            }
            $session = AcademicSession::findOrFail($filters['academic_session_id']);
            $section = Section::with('academicClass')->findOrFail($filters['section_id']);
            if (! empty($filters['academic_class_id']) && $section->academic_class_id != $filters['academic_class_id']) {
                return;
            }
            $date = CarbonImmutable::parse($filters['from']);
            $end = min($filters['to'], now('Asia/Karachi')->toDateString());
            while ($date->toDateString() <= $end) {
                $day = $date->toDateString();
                $date = $date->addDay();
                if ($day < $session->starts_on || $day > $session->ends_on) {
                    continue;
                }
                foreach (app(AttendanceService::class)->roster($session->id, $section->id, $day) as $row) {
                    if (! empty($filters['student_id']) && $row['student_id'] != $filters['student_id']) {
                        continue;
                    }
                    yield ['date' => $day, 'person' => $row['name'], 'identifier' => $row['admission_number'], 'class' => $section->academicClass->name, 'section' => $section->name, 'subject' => '', 'status' => $row['status'] ?: 'unmarked', 'score' => null, 'maximum' => null, 'percentage' => null, 'grade' => '', 'rank' => null];
                }
            }
        } elseif ($type === 'staff') {
            $query = StaffAttendanceRecord::with('teacher')->whereBetween('attendance_date', [$filters['from'], $filters['to']]);
            if (! empty($filters['teacher_id'])) {
                $query->where('teacher_id', $filters['teacher_id']);
            }
            foreach ($query->lazyById(250) as $r) {
                yield ['date' => $r->attendance_date, 'person' => $r->teacher->full_name, 'identifier' => $r->teacher->employee_code, 'class' => '', 'section' => '', 'subject' => '', 'status' => $r->status, 'score' => null, 'maximum' => null, 'percentage' => null, 'grade' => '', 'rank' => null];
            }
        } elseif (in_array($type, ['performance', 'subject-results', 'class-results', 'student-academic', 'pass-fail', 'top'])) {
            $query = PublishedReportCard::with('exam')->whereExists(fn ($q) => $q->selectRaw('1')->from('exams')->whereColumn('exams.id', 'published_report_cards.exam_id')->whereColumn('exams.school_id', 'published_report_cards.school_id')->whereColumn('exams.publication_version', 'published_report_cards.version'));
            if (! empty($filters['exam_id'])) {
                $query->where('exam_id', $filters['exam_id']);
            }
            if (! empty($filters['student_id'])) {
                $query->where('student_id', $filters['student_id']);
            }
            if (! empty($filters['academic_session_id'])) {
                $query->whereHas('exam', fn ($q) => $q->where('academic_session_id', $filters['academic_session_id']));
            }
            foreach ($query->lazyById(100) as $r) {
                $s = $r->snapshot;
                if (! empty($filters['section_id']) && $s['section_id'] != $filters['section_id']) {
                    continue;
                }
                if (! empty($filters['academic_class_id']) && $s['class_id'] != $filters['academic_class_id']) {
                    continue;
                }
                if ($type === 'top' && $s['rank'] === null) {
                    continue;
                }
                $subjects = $type === 'subject-results' ? $s['subjects'] : [$s];
                foreach ($subjects as $subject) {
                    yield ['date' => substr($s['published_at'], 0, 10), 'person' => $s['student_name'], 'identifier' => $s['admission_number'], 'class' => $s['class'], 'section' => $s['section'], 'subject' => $subject['subject'] ?? $s['exam_name'], 'status' => $subject['outcome'] ?? $subject['status'] ?? '', 'score' => $subject['obtained_marks'], 'maximum' => $subject['maximum_marks'], 'percentage' => $subject['percentage'], 'grade' => $subject['grade'] ?? '', 'rank' => $s['rank']];
                }
            }
        } else {
            $query = StudentAttendanceRecord::with(['student', 'attendanceSession.section.academicClass'])->whereBetween('attendance_date', [$filters['from'], $filters['to']]);
            if (! empty($filters['academic_session_id'])) {
                $query->where('academic_session_id', $filters['academic_session_id']);
            }
            if (! empty($filters['student_id'])) {
                $query->where('student_id', $filters['student_id']);
            }
            if (! empty($filters['section_id'])) {
                $query->whereHas('attendanceSession', fn ($q) => $q->where('section_id', $filters['section_id']));
            }
            if (! empty($filters['academic_class_id'])) {
                $query->whereHas('attendanceSession.section', fn ($q) => $q->where('academic_class_id', $filters['academic_class_id']));
            }
            foreach ($query->lazyById(250) as $r) {
                yield ['date' => $r->attendance_date, 'person' => $r->student->full_name, 'identifier' => $r->student->admission_number, 'class' => $r->attendanceSession->section->academicClass->name, 'section' => $r->attendanceSession->section->name, 'subject' => '', 'status' => $r->status, 'score' => null, 'maximum' => null, 'percentage' => null, 'grade' => '', 'rank' => null];
            }
        }
    }

    public function preview(array $filters): array
    {
        $rows = [];
        $counts = [];
        $groups = [];
        $total = 0;
        $percentageSum = 0;
        $percentageCount = 0;
        foreach ($this->rows($filters) as $row) {
            $total++;
            if (count($rows) < 100) {
                $rows[] = $row;
            }
            if ($row['percentage'] !== null) {
                $percentageSum += (float) $row['percentage'];
                $percentageCount++;
            }
            $counts[$row['status']] = ($counts[$row['status']] ?? 0) + 1;
            $key = match ($filters['report_type'] ?? 'attendance') {
                'monthly' => substr($row['date'], 0, 7).' / '.$row['identifier'],'student-history','student-academic','staff' => $row['identifier'].' / '.$row['person'],'class-comparison','class-results' => $row['class'].' / '.$row['section'],'subject-results' => $row['subject'],default => $row['date']
            };
            $groups[$key] ??= [];
            $groups[$key][$row['status']] = ($groups[$key][$row['status']] ?? 0) + 1;
        }
        $decisions = ($counts['pass'] ?? 0) + ($counts['fail'] ?? 0);

        return ['rows' => $rows, 'total' => $total, 'average_percentage' => $percentageCount ? round($percentageSum / $percentageCount, 2) : null, 'pass_rate' => $decisions ? round(($counts['pass'] ?? 0) / $decisions * 100, 2) : null, 'summary' => AttendanceService::summary($counts), 'groups' => collect($groups)->map(fn ($counts, $label) => ['label' => $label, ...AttendanceService::summary($counts)])->values()];
    }

    public function csv(array $filters)
    {
        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $header = false;
            foreach ($this->rows($filters) as $row) {
                if (! $header) {
                    fputcsv($out, array_keys($row), ',', '"', '');
                    $header = true;
                } $safe = array_map(fn ($value) => is_string($value) && preg_match('/^[=+@\-\t\r\n]/', $value) ? "'".$value : $value, $row);
                fputcsv($out, $safe, ',', '"', '');
            }
            if (! $header) {
                fputcsv($out, ['No matching recorded data'], ',', '"', '');
            }fclose($out);
        }, 'academic-report.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
    }
}
