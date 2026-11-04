<?php

namespace App\Modules\Academics\Operations;

use App\Modules\Academics\Models\AcademicClass;
use App\Modules\Academics\Models\AcademicSession;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Models\Student;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Models\Teacher;
use App\Modules\Academics\Models\TeacherAssignment;
use App\Modules\Academics\Operations\Models\AcademicAuditLog;
use App\Modules\Academics\Operations\Models\Exam;
use App\Modules\Academics\Operations\Models\ExamSchedule;
use App\Modules\Academics\Operations\Models\ExamType;
use App\Modules\Academics\Operations\Models\GradingScheme;
use App\Modules\Academics\Operations\Models\PublishedReportCard;
use App\Modules\Academics\Operations\Models\StaffAttendanceRecord;
use App\Modules\Academics\Operations\Models\TimetableEntry;
use App\Modules\Academics\Operations\Models\TimetablePeriod;
use App\Modules\Platform\Preview\PreviewData;
use App\Modules\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class OperationsController
{
    private function base(Request $r): string
    {
        return '/preview/schools/'.$r->route('school').'/academic/operations';
    }

    private function filters(Request $r): array
    {
        $school = app(TenantContext::class)->school()->id;
        $rules = ['attendance_date' => ['nullable', 'date_format:Y-m-d'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'], 'report_type' => ['nullable', Rule::in(['daily', 'attendance', 'monthly', 'student-history', 'staff', 'class-comparison', 'performance', 'subject-results', 'class-results', 'student-academic', 'pass-fail', 'top'])], 'q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']];
        foreach (['academic_session_id' => 'academic_sessions', 'academic_class_id' => 'academic_classes', 'section_id' => 'sections', 'student_id' => 'students', 'teacher_id' => 'teachers', 'exam_id' => 'exams', 'exam_schedule_id' => 'exam_schedules'] as $key => $table) {
            $rules[$key] = ['nullable', 'integer', Rule::exists($table, 'id')->where('school_id', $school)];
        }
        $values = $r->validate($rules);
        $today = now('Asia/Karachi')->toDateString();
        $values['attendance_date'] = $values['attendance_date'] ?? $today;
        $values['from'] = $values['from'] ?? now('Asia/Karachi')->startOfMonth()->toDateString();
        $values['to'] = $values['to'] ?? $today;
        if ($values['from'] > $values['to'] || Carbon::parse($values['from'])->diffInDays($values['to']) > 366) {
            throw ValidationException::withMessages(['to' => 'Select an ordered date range of at most 366 days.']);
        }

        return $values;
    }

    private function options(): array
    {
        return ['sessions' => AcademicSession::orderByDesc('starts_on')->get()->map(fn ($r) => ['id' => $r->id, 'label' => $r->name]), 'classes' => AcademicClass::orderBy('display_order')->get()->map(fn ($r) => ['id' => $r->id, 'label' => $r->name]), 'sections' => Section::with('academicClass')->get()->map(fn ($r) => ['id' => $r->id, 'academic_class_id' => $r->academic_class_id, 'label' => $r->academicClass->name.' / '.$r->name]), 'subjects' => Subject::get()->map(fn ($r) => ['id' => $r->id, 'label' => $r->name]), 'teachers' => Teacher::get()->map(fn ($r) => ['id' => $r->id, 'label' => $r->full_name]), 'students' => Student::get()->map(fn ($r) => ['id' => $r->id, 'label' => $r->admission_number.' / '.$r->full_name]), 'periods' => TimetablePeriod::orderBy('starts_at')->get()->map(fn ($r) => ['id' => $r->id, 'label' => $r->name.' / '.substr($r->starts_at, 0, 5).'–'.substr($r->ends_at, 0, 5), 'kind' => $r->kind]), 'assignments' => TeacherAssignment::where('kind', 'teaching')->with(['teacher', 'subject', 'section', 'academicClass', 'academicSession'])->get()->map(fn ($r) => ['id' => $r->id, 'academic_session_id' => $r->academic_session_id, 'section_id' => $r->section_id, 'label' => $r->teacher->full_name.' / '.$r->subject->name.' / '.$r->academicClass->name.' '.$r->section->name.' / '.$r->academicSession->name]), 'schemes' => GradingScheme::with('bands')->get()->map(fn ($r) => ['id' => $r->id, 'label' => $r->name]), 'examTypes' => ExamType::get()->map(fn ($r) => ['id' => $r->id, 'label' => $r->name]), 'exams' => Exam::get()->map(fn ($r) => ['id' => $r->id, 'label' => $r->name, 'status' => $r->status]), 'schedules' => ExamSchedule::with(['exam', 'subject', 'section', 'academicClass'])->get()->map(fn ($r) => ['id' => $r->id, 'label' => $r->exam->name.' / '.$r->subject->name.' / '.$r->academicClass->name.' '.$r->section->name, 'maximum_marks' => $r->maximum_marks, 'locked' => $r->exam->status === 'published'])];
    }

    public function page(Request $request, PreviewData $preview)
    {
        $page = $request->route('page');
        abort_unless(isset(OperationsCatalog::TITLES[$page]), 404);
        $filters = $this->filters($request);
        $options = $this->options();
        $roster = [];
        $rows = null;
        $reports = null;
        if (in_array($page, ['timetable', 'teacher-timetable']) && empty($filters['academic_session_id'])) {
            $filters['academic_session_id'] = AcademicSession::where('is_current', true)->value('id');
        }
        if (isset(OperationsCatalog::MODELS[$page])) {
            $model = OperationsCatalog::model($page);
            $query = $model::query();
            $relations = match ($page) {
                'grading' => ['bands'],'exams' => ['academicSession', 'examType', 'gradingScheme'],'schedules' => ['exam', 'subject', 'section', 'academicClass'],'timetable' => ['academicSession', 'section.academicClass', 'timetablePeriod', 'teacherAssignment.teacher', 'teacherAssignment.subject'],default => []
            };
            if (! empty($filters['q']) && in_array($page, ['grading', 'exams', 'exam-types', 'periods'])) {
                $query->whereRaw('lower(name) LIKE ?', ['%'.strtolower($filters['q']).'%']);
            }
            foreach (['academic_session_id', 'section_id', 'exam_id'] as $key) {
                if (! empty($filters[$key]) && in_array($key, match ($page) {
                    'exams' => ['academic_session_id'],'schedules' => ['section_id', 'exam_id'],'timetable' => ['academic_session_id', 'section_id'],default => []
                })) {
                    $query->where($key, $filters[$key]);
                }
            }
            $rows = $query->with($relations)->orderByDesc('id')->paginate(20)->withQueryString();
        }
        if ($page === 'student-attendance' && ! empty($filters['academic_session_id']) && ! empty($filters['section_id'])) {
            $roster = app(AttendanceService::class)->roster($filters['academic_session_id'], $filters['section_id'], $filters['attendance_date']);
        }
        if ($page === 'staff-attendance') {
            $records = StaffAttendanceRecord::where('attendance_date', $filters['attendance_date'])->get()->keyBy('teacher_id');
            $roster = Teacher::where(fn ($q) => $q->where('status', 'active')->orWhereIn('id', $records->keys()))->when(! empty($filters['teacher_id']), fn ($q) => $q->whereKey($filters['teacher_id']))->get()->map(fn ($r) => ['teacher_id' => $r->id, 'name' => $r->full_name, 'identifier' => $r->employee_code, 'status' => $records->get($r->id)?->status ?? '']);
        }
        if ($page === 'marks' && ! empty($filters['exam_schedule_id'])) {
            $roster = app(ExamService::class)->roster(ExamSchedule::findOrFail($filters['exam_schedule_id']));
        }
        if ($page === 'reports') {
            $reports = app(AcademicReports::class)->preview($filters);
        }
        if ($page === 'results') {
            $rows = PublishedReportCard::with('exam')->when(! empty($filters['exam_id']), fn ($q) => $q->where('exam_id', $filters['exam_id']))->orderByDesc('id')->paginate(20)->withQueryString();
        }
        if ($page === 'audit') {
            $rows = AcademicAuditLog::orderByDesc('id')->paginate(25)->withQueryString();
        }
        $weekly = in_array($page, ['timetable', 'teacher-timetable']) ? TimetableEntry::with(['section.academicClass', 'timetablePeriod', 'teacherAssignment.teacher', 'teacherAssignment.subject'])->when(! empty($filters['academic_session_id']), fn ($q) => $q->where('academic_session_id', $filters['academic_session_id']))->when(! empty($filters['section_id']), fn ($q) => $q->where('section_id', $filters['section_id']))->when(! empty($filters['teacher_id']), fn ($q) => $q->whereHas('teacherAssignment', fn ($q) => $q->where('teacher_id', $filters['teacher_id'])))->get() : [];

        return Inertia::render('Operations', [...$preview->get($request->route('school')), 'page' => $page, 'title' => OperationsCatalog::TITLES[$page], 'fields' => isset(OperationsCatalog::MODELS[$page]) ? OperationsCatalog::fields($page) : [], 'rows' => $rows, 'roster' => $roster, 'options' => $options, 'filters' => $filters, 'reports' => $reports, 'weekly' => $weekly, 'draftReports' => $page === 'results' && ! empty($filters['exam_id']) ? app(ExamService::class)->reports(Exam::findOrFail($filters['exam_id'])) : [], 'baseUrl' => $this->base($request), 'flash' => ['success' => $request->session()->get('success')]]);
    }

    private function safe(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (QueryException $e) {
            if (str_starts_with((string) $e->getCode(), '23')) {
                throw ValidationException::withMessages(['record' => 'Database integrity rejected this operation. Check duplicate, dependency, time, enrollment or publication constraints.']);
            }throw $e;
        }
    }

    public function save(OperationsRequest $r, OperationsService $service)
    {
        $resource = $r->route('resource');
        $model = OperationsCatalog::model($resource);
        $record = $r->route('record') ? $model::findOrFail($r->route('record')) : null;
        $this->safe(fn () => $service->save($resource, $r->validated(), $record));

        return redirect($this->base($r).'/'.$resource)->with('success', 'Changes saved.');
    }

    public function destroy(OperationsRequest $r, OperationsService $service)
    {
        $resource = $r->route('resource');
        $model = OperationsCatalog::model($resource);
        $this->safe(fn () => $service->delete($resource, $model::findOrFail($r->route('record'))));

        return back()->with('success', 'Unused record deleted.');
    }

    public function submit(OperationsRequest $r)
    {
        $this->safe(fn () => match ($r->route('resource')) {
            'student-attendance' => app(AttendanceService::class)->students($r->validated()),'staff-attendance' => app(AttendanceService::class)->staff($r->validated()),'marks' => app(ExamService::class)->marks($r->validated())
        });

        return back()->with('success', 'Recorded values saved. Blank rows remain unmarked or unchanged.');
    }

    public function publication(OperationsRequest $r)
    {
        $exam = Exam::findOrFail($r->route('record'));
        $this->safe(fn () => $r->route('operation') === 'publish' ? app(ExamService::class)->publish($exam) : app(ExamService::class)->reopen($exam, $r->validated('reason')));

        return back()->with('success', 'Publication workflow completed. Earlier snapshots are preserved.');
    }

    public function card(Request $r, PreviewData $preview)
    {
        if ($r->route('record')) {
            $card = PublishedReportCard::findOrFail($r->route('record'));
            $snapshot = $card->snapshot;
        } else {
            $exam = Exam::findOrFail($r->route('exam'));
            Student::withTrashed()->findOrFail($r->route('student'));
            $snapshot = collect(app(ExamService::class)->reports($exam))->firstWhere('student_id', (int) $r->route('student'));
            abort_if(! $snapshot, 404);
            $snapshot = [...$snapshot, 'publication' => 'draft', 'version' => null];
        }

        return Inertia::render('ReportCard', [...$preview->get($r->route('school')), 'page' => 'report-card', 'title' => 'Student Report Card', 'snapshot' => $snapshot]);
    }

    public function export(Request $r)
    {
        return app(AcademicReports::class)->csv($this->filters($r));
    }
}
