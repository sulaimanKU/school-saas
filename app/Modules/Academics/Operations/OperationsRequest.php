<?php

namespace App\Modules\Academics\Operations;

use App\Modules\Academics\AcademicPolicy;
use App\Modules\Academics\Operations\Models\Exam;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OperationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(AcademicPolicy::class)->allows();
    }

    protected function prepareForValidation(): void
    {
        $resource = $this->route('resource');
        if ($this->route('record')) {
            $model = $resource === 'results' ? Exam::class : OperationsCatalog::model($resource);
            $model::findOrFail($this->route('record'));
        }
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
        }
    }

    public function rules(): array
    {
        $school = app(TenantContext::class)->school()->id;
        $resource = $this->route('resource');
        $exists = fn ($table, $required = true) => [$required ? 'required' : 'nullable', 'integer', Rule::exists($table, 'id')->where('school_id', $school)];
        $time = ['required', 'date_format:H:i'];
        $name = ['required', 'string', 'max:100'];
        $unique = fn ($table, $key) => Rule::unique($table, $key)->where('school_id', $school)->ignore($this->route('record'));
        $rules = $this->isMethod('delete') ? [] : match ($resource) {
            'student-attendance' => ['academic_session_id' => $exists('academic_sessions'), 'section_id' => $exists('sections'), 'attendance_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'records' => ['required', 'array', 'max:1000'], 'records.*.student_id' => [...$exists('students'), 'distinct'], 'records.*.status' => ['nullable', Rule::in(['present', 'absent', 'late', 'excused'])]],
            'staff-attendance' => ['attendance_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'records' => ['required', 'array', 'max:1000'], 'records.*.teacher_id' => [...$exists('teachers'), 'distinct'], 'records.*.status' => ['nullable', Rule::in(['present', 'absent', 'late', 'leave', 'holiday'])]],
            'marks' => ['exam_schedule_id' => $exists('exam_schedules'), 'records' => ['required', 'array', 'max:1000'], 'records.*.student_id' => [...$exists('students'), 'distinct'], 'records.*.status' => ['nullable', Rule::in(['scored', 'absent', 'exempt'])], 'records.*.obtained_marks' => ['nullable', 'numeric', 'min:0', 'max:999999.99', 'decimal:0,2'], 'records.*.remarks' => ['nullable', 'string', 'max:500']],
            'working-days' => ['weekday' => ['required', 'integer', 'between:1,7', $unique('school_working_days', 'weekday')], 'is_working' => ['required', 'boolean'], 'opens_at' => $time, 'closes_at' => [...$time, 'after:opens_at']],
            'periods' => ['name' => $name, 'code' => ['required', 'string', 'max:30', $unique('timetable_periods', 'code')], 'starts_at' => $time, 'ends_at' => [...$time, 'after:starts_at'], 'kind' => ['required', Rule::in(['teaching', 'break', 'non_teaching'])]],
            'timetable' => ['academic_session_id' => $exists('academic_sessions'), 'section_id' => $exists('sections'), 'weekday' => ['required', 'integer', 'between:1,7'], 'timetable_period_id' => $exists('timetable_periods'), 'teacher_assignment_id' => $exists('teacher_assignments', false)],
            'grading' => ['name' => [...$name, $unique('grading_schemes', 'name')], 'ranking_enabled' => ['required', 'boolean'], 'logo_path' => ['required', 'string', 'max:255', 'regex:~^/(images|storage)/[A-Za-z0-9/_-]+\.(svg|png|jpg|jpeg|webp)$~'], 'bands' => ['required', 'array', 'min:1', 'max:30'], 'bands.*.label' => ['required', 'string', 'max:20'], 'bands.*.minimum_percent' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'], 'bands.*.maximum_percent' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'], 'bands.*.is_pass' => ['required', 'boolean']],
            'exam-types' => ['name' => $name, 'code' => ['required', 'string', 'max:30', $unique('exam_types', 'code')]],
            'exams' => ['name' => ['required', 'string', 'max:150'], 'academic_session_id' => $exists('academic_sessions'), 'exam_type_id' => $exists('exam_types'), 'grading_scheme_id' => $exists('grading_schemes'), 'status' => ['required', Rule::in(['draft', 'scheduled', 'completed'])]],
            'schedules' => ['exam_id' => $exists('exams'), 'academic_class_id' => $exists('academic_classes'), 'section_id' => $exists('sections'), 'subject_id' => $exists('subjects'), 'teacher_id' => $exists('teachers', false), 'exam_date' => ['required', 'date_format:Y-m-d'], 'starts_at' => $time, 'duration_minutes' => ['required', 'integer', 'between:1,720'], 'maximum_marks' => ['required', 'numeric', 'gt:0', 'max:999999.99', 'decimal:0,2'], 'passing_marks' => ['required', 'numeric', 'min:0', 'lte:maximum_marks', 'decimal:0,2']],
            'results' => ['confirm' => ['required', 'accepted'], 'reason' => [$this->route('operation') === 'reopen' ? 'required' : 'nullable', 'string', 'min:10', 'max:500']],
            default => abort(404),
        };

        return [...$rules, 'school_id' => ['prohibited'], 'actor_id' => ['prohibited'], 'publication_version' => ['prohibited']];
    }
}
