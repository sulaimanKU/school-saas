<?php

namespace App\Modules\Academics\Requests;

use App\Modules\Academics\AcademicPolicy;
use App\Modules\Academics\Models\Enrollment;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnrollmentChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(AcademicPolicy::class)->allows();
    }

    protected function prepareForValidation(): void
    {
        abort_unless($this->route('resource') === 'enrollments', 404);
        Enrollment::findOrFail($this->route('record'));
    }

    public function rules(): array
    {
        $school = app(TenantContext::class)->school()->id;

        return ['operation' => ['required', Rule::in(['transfer', 'promote', 'withdraw'])],
            'academic_session_id' => ['required_unless:operation,withdraw', 'nullable', 'integer', Rule::exists('academic_sessions', 'id')->where('school_id', $school)],
            'academic_class_id' => ['required_unless:operation,withdraw', 'nullable', 'integer', Rule::exists('academic_classes', 'id')->where('school_id', $school)],
            'section_id' => ['required_unless:operation,withdraw', 'nullable', 'integer', Rule::exists('sections', 'id')->where('school_id', $school)],
            'started_on' => ['required', 'date_format:Y-m-d'], 'school_id' => ['prohibited']];
    }
}
