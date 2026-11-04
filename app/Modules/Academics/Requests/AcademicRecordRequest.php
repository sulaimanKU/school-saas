<?php

namespace App\Modules\Academics\Requests;

use App\Modules\Academics\AcademicPolicy;
use App\Modules\Academics\AcademicResources;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcademicRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(AcademicPolicy::class)->allows();
    }

    protected function prepareForValidation(): void
    {
        if ($this->route('record')) {
            $model = AcademicResources::model($this->route('resource'));
            $model::findOrFail($this->route('record'));
        }
        foreach (['code', 'employee_code'] as $key) {
            if ($this->has($key)) {
                $this->merge([$key => strtoupper(trim((string) $this->input($key)))]);
            }
        }
    }

    public function rules(): array
    {
        $resource = $this->route('resource');
        $school = app(TenantContext::class)->school()->id;
        $table = (new (AcademicResources::model($resource)))->getTable();
        $unique = fn ($key) => Rule::unique($table, $key)->where('school_id', $school)->ignore($this->route('record'));
        $exists = fn ($table) => ['required', 'integer', Rule::exists($table, 'id')->where('school_id', $school)];
        $name = ['required', 'string', 'max:100'];
        $code = ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/'];
        $active = ['required', 'boolean'];
        $rules = match ($resource) {
            'sessions' => ['name' => [...$name, $unique('name')], 'starts_on' => ['required', 'date_format:Y-m-d'], 'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'], 'active' => $active, 'is_current' => $active],
            'classes' => ['name' => $name, 'code' => [...$code, $unique('code')], 'grade_level' => ['required', 'integer', 'between:0,20'], 'display_order' => ['required', 'integer', 'between:0,9999'], 'active' => $active],
            'sections' => ['academic_class_id' => $exists('academic_classes'), 'name' => $name, 'code' => [...$code, Rule::unique('sections', 'code')->where('school_id', $school)->where('academic_class_id', $this->integer('academic_class_id'))->ignore($this->route('record'))], 'capacity' => ['nullable', 'integer', 'between:1,10000'], 'active' => $active],
            'subjects' => ['name' => $name, 'code' => [...$code, $unique('code')], 'active' => $active],
            'class-subjects' => ['academic_class_id' => $exists('academic_classes'), 'subject_id' => $exists('subjects'), 'classification' => ['required', Rule::in(['compulsory', 'elective'])]],
            'teachers' => ['employee_code' => [...$code, $unique('employee_code')], 'full_name' => ['required', 'string', 'max:150'], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:30'], 'status' => ['required', Rule::in(['active', 'on_leave', 'inactive'])]],
            'teacher-assignments' => ['academic_session_id' => $exists('academic_sessions'), 'academic_class_id' => $exists('academic_classes'), 'section_id' => $exists('sections'), 'teacher_id' => $exists('teachers'), 'subject_id' => ['nullable', 'required_if:kind,teaching', 'prohibited_if:kind,form_teacher', 'integer', Rule::exists('subjects', 'id')->where('school_id', $school)], 'kind' => ['required', Rule::in(['teaching', 'form_teacher'])]],
            'students' => ['full_name' => ['required', 'string', 'max:150'], 'date_of_birth' => ['required', 'date_format:Y-m-d', 'before_or_equal:admitted_on'], 'gender' => ['required', Rule::in(['male', 'female', 'other', 'unspecified'])], 'guardian_name' => ['required', 'string', 'max:150'], 'guardian_phone' => ['required', 'string', 'max:30'], 'admitted_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'status' => ['required', Rule::in(['active', 'inactive', 'graduated', 'archived'])]],
            'enrollments' => ['student_id' => $exists('students'), 'academic_session_id' => $exists('academic_sessions'), 'academic_class_id' => $exists('academic_classes'), 'section_id' => $exists('sections'), 'started_on' => ['required', 'date_format:Y-m-d']],
        };

        return [...$rules, 'school_id' => ['prohibited'], 'user_id' => ['prohibited'], 'admission_number' => ['prohibited']];
    }
}
