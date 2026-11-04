<?php

namespace App\Modules\Academics\Operations;

use App\Modules\Academics\AcademicService;
use App\Modules\Academics\Operations\Models\Exam;
use App\Modules\Academics\Operations\Models\ExamMark;
use App\Modules\Academics\Operations\Models\ExamSchedule;
use App\Modules\Academics\Operations\Models\PublishedReportCard;
use App\Modules\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class ExamService
{
    public function roster(ExamSchedule $schedule): array
    {
        $marks = ExamMark::where('exam_id', $schedule->exam_id)->where('subject_id', $schedule->subject_id)->get()->keyBy('student_id');

        return app(EnrollmentRoster::class)->query($schedule->academic_session_id, $schedule->section_id, $schedule->exam_date)->get()->map(function ($e) use ($marks, $schedule) {
            $mark = $marks->get($e->student_id);

            return ['student_id' => $e->student_id, 'enrollment_id' => $e->id, 'name' => $e->student->full_name, 'admission_number' => $e->student->admission_number, 'status' => $mark?->status ?? '', 'obtained_marks' => $mark?->obtained_marks, 'remarks' => $mark?->remarks ?? '', 'assessed_elsewhere' => $mark && $mark->exam_schedule_id !== $schedule->id];
        })->all();
    }

    public function marks(array $data): void
    {
        app(AcademicService::class)->transaction(function () use ($data) {
            $schedule = ExamSchedule::with('exam')->findOrFail($data['exam_schedule_id']);
            if ($schedule->exam->status === 'published') {
                $this->fail('exam_schedule_id', 'Published marks are locked. Reopen through controlled correction first.');
            }
            if ($schedule->exam_date > CarbonImmutable::now('Asia/Karachi')->toDateString()) {
                $this->fail('exam_schedule_id', 'Marks cannot be entered before the examination date.');
            }
            $roster = collect($this->roster($schedule))->keyBy('student_id');
            foreach ($data['records'] as $row) {
                $student = $roster->get($row['student_id']);
                if (! $student) {
                    $this->fail('records', 'Student was not enrolled for this examination schedule.');
                }
                if (empty($row['status'])) {
                    continue;
                }
                if ($student['assessed_elsewhere']) {
                    $this->fail('records', 'Student already has a mark for this exam subject in another section.');
                }
                if ($row['status'] === 'scored' && (! isset($row['obtained_marks']) || $row['obtained_marks'] < 0 || $row['obtained_marks'] > $schedule->maximum_marks)) {
                    $this->fail('records', 'Scored marks must be between zero and the scheduled maximum.');
                }
                if ($row['status'] !== 'scored' && isset($row['obtained_marks'])) {
                    $this->fail('records', 'Absent or exempt results cannot contain numeric marks.');
                }
                $existing = ExamMark::where('exam_id', $schedule->exam_id)->where('subject_id', $schedule->subject_id)->where('student_id', $row['student_id'])->first();
                $before = $existing?->toArray() ?? [];
                $mark = ExamMark::updateOrCreate(['exam_id' => $schedule->exam_id, 'subject_id' => $schedule->subject_id, 'student_id' => $row['student_id']], ['exam_schedule_id' => $schedule->id, 'enrollment_id' => $student['enrollment_id'], 'status' => $row['status'], 'obtained_marks' => $row['status'] === 'scored' ? $row['obtained_marks'] : null, 'remarks' => $row['remarks'] ?? null]);
                app(AuditTrail::class)->record($existing ? 'marks.corrected' : 'marks.recorded', 'exam_mark', $mark->id, $before, $mark->toArray());
            }
        });
    }

    public function reports(Exam $exam): array
    {
        $exam->load(['academicSession', 'gradingScheme.bands', 'schedules.subject', 'schedules.academicClass', 'schedules.section']);
        $bands = $exam->gradingScheme->bands->toArray();
        app(GradingCalculator::class)->validateBands($bands);
        $marks = ExamMark::where('exam_id', $exam->id)->get()->keyBy(fn ($m) => $m->student_id.':'.$m->subject_id);
        $candidates = [];
        foreach ($exam->schedules->sortBy('exam_date') as $schedule) {
            foreach (app(EnrollmentRoster::class)->query($exam->academic_session_id, $schedule->section_id, $schedule->exam_date)->get() as $enrollment) {
                $id = $enrollment->student_id;
                $mark = $marks->get($id.':'.$schedule->subject_id);
                if (! isset($candidates[$id])) {
                    $candidates[$id] = ['student' => $enrollment->student, 'subjects' => [], 'class' => $schedule->academicClass->name, 'class_id' => $schedule->academic_class_id, 'section' => $schedule->section->name, 'section_id' => $schedule->section_id];
                }
                if (isset($candidates[$id]['subjects'][$schedule->subject_id]) && (! $mark || $mark->exam_schedule_id !== $schedule->id)) {
                    continue;
                }
                $candidates[$id]['subjects'][$schedule->subject_id] = ['subject' => $schedule->subject->name, 'subject_id' => $schedule->subject_id, 'maximum_marks' => (float) $schedule->maximum_marks, 'passing_marks' => (float) $schedule->passing_marks, 'obtained_marks' => $mark?->obtained_marks !== null ? (float) $mark->obtained_marks : null, 'status' => $mark?->status ?? 'unmarked', 'remarks' => $mark?->remarks ?? ''];
            }
        }
        $school = app(TenantContext::class)->school();
        $reports = [];
        foreach ($candidates as $id => $candidate) {
            $result = app(GradingCalculator::class)->calculate(array_values($candidate['subjects']), $bands);
            $reports[] = [...$result, 'student_id' => $id, 'student_name' => $candidate['student']->full_name, 'admission_number' => $candidate['student']->admission_number, 'school_name' => $school->name, 'school_logo' => $exam->gradingScheme->logo_path, 'session' => $exam->academicSession->name, 'exam_name' => $exam->name, 'exam_id' => $exam->id, 'class' => $candidate['class'], 'class_id' => $candidate['class_id'], 'section' => $candidate['section'], 'section_id' => $candidate['section_id'], 'scheme_name' => $exam->gradingScheme->name, 'grading_bands' => $bands, 'ranking_enabled' => $exam->gradingScheme->ranking_enabled];
        }
        // Rank within each class/section cohort, rather than comparing unrelated classes.
        $groups = collect($reports)->groupBy(fn ($r) => $r['class_id'].':'.$r['section_id']);
        $ranked = [];
        foreach ($groups as $group) {
            $ranked = array_merge($ranked, app(GradingCalculator::class)->rank($group->all(), $exam->gradingScheme->ranking_enabled));
        }

        return $ranked;
    }

    public function publish(Exam $exam): void
    {
        app(AcademicService::class)->transaction(function () use ($exam) {
            $exam = Exam::lockForUpdate()->findOrFail($exam->id);
            if ($exam->status !== 'completed') {
                $this->fail('record', 'Complete the exam before publication.');
            }
            if (! $exam->schedules()->exists()) {
                $this->fail('record', 'Add examination schedules first.');
            }
            $reports = $this->reports($exam);
            if (! $reports) {
                $this->fail('record', 'No eligible enrolled students.');
            }
            foreach ($reports as $report) {
                if ($report['outcome'] === 'incomplete') {
                    $this->fail('record', 'All eligible subjects need scored, absent, or exempt marks before publication.');
                }
            }
            $version = $exam->publication_version + 1;
            foreach ($reports as $report) {
                PublishedReportCard::create(['exam_id' => $exam->id, 'student_id' => $report['student_id'], 'version' => $version, 'snapshot' => [...$report, 'version' => $version, 'publication' => 'published', 'published_at' => now()->toIso8601String()], 'published_at' => now()]);
            }
            $before = $exam->toArray();
            $exam->update(['status' => 'published', 'publication_version' => $version]);
            app(AuditTrail::class)->record('results.published', 'exam', $exam->id, $before, $exam->toArray());
        });
    }

    public function reopen(Exam $exam, string $reason): void
    {
        app(AcademicService::class)->transaction(function () use ($exam, $reason) {
            $exam = Exam::lockForUpdate()->findOrFail($exam->id);
            if ($exam->status !== 'published') {
                $this->fail('record', 'Only a published exam can be reopened for correction.');
            }
            $before = $exam->toArray();
            $exam->update(['status' => 'completed']);
            app(AuditTrail::class)->record('results.reopened', 'exam', $exam->id, $before, $exam->toArray(), $reason);
        });
    }

    private function fail(string $field,string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
