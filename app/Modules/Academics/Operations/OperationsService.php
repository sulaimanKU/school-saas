<?php

namespace App\Modules\Academics\Operations;

use App\Modules\Academics\AcademicService;
use App\Modules\Academics\Models\AcademicRecord;
use App\Modules\Academics\Models\ClassSubject;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Operations\Models\Exam;
use App\Modules\Academics\Operations\Models\ExamMark;
use App\Modules\Academics\Operations\Models\ExamSchedule;
use App\Modules\Academics\Operations\Models\GradingBand;
use App\Modules\Academics\Operations\Models\TimetableEntry;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class OperationsService
{
    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    public function save(string $resource, array $data, ?AcademicRecord $record = null): AcademicRecord
    {
        return app(AcademicService::class)->transaction(function () use ($resource, $data, $record) {
            $bands = $data['bands'] ?? null;
            unset($data['bands']);
            if ($resource === 'grading') {
                $bands = app(GradingCalculator::class)->validateBands($bands ?? []);
            }
            if ($resource === 'timetable') {
                app(TimetableService::class)->validate($data, $record?->id);
            }
            if ($resource === 'periods' && $record && TimetableEntry::where('timetable_period_id', $record->id)->exists() && ($data['starts_at'] !== substr($record->starts_at, 0, 5) || $data['ends_at'] !== substr($record->ends_at, 0, 5) || $data['kind'] !== $record->kind)) {
                $this->fail('starts_at', 'Used periods are time-locked. Remove timetable entries or create a replacement period.');
            }
            if ($resource === 'working-days') {
                foreach (TimetableEntry::where('weekday', $data['weekday'])->with('timetablePeriod')->get() as $entry) {
                    if (! $data['is_working'] || substr($entry->timetablePeriod->starts_at, 0, 5) < $data['opens_at'] || substr($entry->timetablePeriod->ends_at, 0, 5) > $data['closes_at']) {
                        $this->fail('opens_at', 'These hours would invalidate an existing timetable.');
                    }
                }
                if ($record && $record->weekday != $data['weekday'] && TimetableEntry::where('weekday', $record->weekday)->exists()) {
                    $this->fail('weekday', 'A scheduled weekday cannot be moved.');
                }
            }
            if ($resource === 'exams') {
                if ($record?->status === 'published') {
                    $this->fail('status', 'Published exam is locked. Use controlled correction in Result Management.');
                }
                if ($record && $record->schedules()->exists() && $record->academic_session_id != $data['academic_session_id']) {
                    $this->fail('academic_session_id', 'An exam with schedules cannot change sessions.');
                }
                if (in_array($data['status'], ['scheduled', 'completed']) && (! $record || ! $record->schedules()->exists())) {
                    $this->fail('status', 'Create a draft and add its schedules first.');
                }
            }
            if ($resource === 'schedules') {
                $exam = Exam::with('academicSession')->findOrFail($data['exam_id']);
                if ($exam->status === 'published') {
                    $this->fail('exam_id', 'Published schedules are locked.');
                }
                if ($record && ExamMark::where('exam_schedule_id', $record->id)->exists()) {
                    $this->fail('record', 'Schedules with marks are locked. Use a new schedule rather than changing historical assessment context.');
                }
                $data['academic_session_id'] = $exam->academic_session_id;
                $section = Section::findOrFail($data['section_id']);
                if ($section->academic_class_id != $data['academic_class_id'] || ! ClassSubject::where('academic_class_id', $data['academic_class_id'])->where('subject_id', $data['subject_id'])->exists()) {
                    $this->fail('subject_id', 'Select a section and assigned subject for this class.');
                }
                if ($data['exam_date'] < $exam->academicSession->starts_on || $data['exam_date'] > $exam->academicSession->ends_on) {
                    $this->fail('exam_date', 'Date is outside the academic session.');
                }
                $start = CarbonImmutable::parse($data['exam_date'].' '.$data['starts_at']);
                $end = $start->addMinutes((int) $data['duration_minutes']);
                if ($end->toDateString() !== $data['exam_date']) {
                    $this->fail('duration_minutes', 'An examination must finish on its scheduled day.');
                }
                $data['starts_at'] = $start->format('H:i:s');
                $data['ends_at'] = $end->format('H:i:s');
                unset($data['duration_minutes']);
                $conflict = ExamSchedule::where('exam_date', $data['exam_date'])->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at'])->when($record, fn ($q) => $q->whereKeyNot($record->id))->where(fn ($q) => $q->where('section_id', $data['section_id'])->when(! empty($data['teacher_id']), fn ($q) => $q->orWhere('teacher_id', $data['teacher_id'])));
                if ($conflict->exists()) {
                    $this->fail('starts_at', 'Section or invigilator already has an overlapping examination.');
                }
            }
            $model = $record ?? new (OperationsCatalog::model($resource));
            $before = $record?->toArray() ?? [];
            $model->fill($data);
            $model->save();
            if ($bands !== null) {
                GradingBand::where('grading_scheme_id', $model->id)->delete();
                foreach ($bands as $band) {
                    GradingBand::create([...$band, 'grading_scheme_id' => $model->id]);
                }
            }
            app(AuditTrail::class)->record($resource.'.'.($record ? 'updated' : 'created'), $resource, $model->id, $before, $model->toArray());

            return $model;
        });
    }

    public function delete(string $resource, AcademicRecord $record): void
    {
        app(AcademicService::class)->transaction(function () use ($resource, $record) {
            if ($record instanceof Exam && $record->status === 'published') {
                $this->fail('record', 'Published exams cannot be deleted.');
            }
            if ($record instanceof ExamSchedule && $record->exam->status === 'published') {
                $this->fail('record', 'Published schedules cannot be deleted.');
            }
            if ($resource === 'working-days' && TimetableEntry::where('weekday', $record->weekday)->exists()) {
                $this->fail('record', 'Working day has scheduled entries.');
            }
            if ($resource === 'grading') {
                if (Exam::where('grading_scheme_id', $record->id)->exists()) {
                    $this->fail('record', 'Scheme is used by an exam.');
                } GradingBand::where('grading_scheme_id', $record->id)->delete();
            }
            $before = $record->toArray();
            $record->delete();
            app(AuditTrail::class)->record($resource.'.deleted',$resource,$record->id,$before,[]);
        });
    }
}
